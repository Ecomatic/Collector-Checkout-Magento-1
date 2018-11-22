<?php
class Ecomatic_Collectorbank_Model_Payment_Creditmemo_Total extends Mage_Sales_Model_Order_Creditmemo_Total_Abstract
{
    public function collect(Mage_Sales_Model_Order_Creditmemo $cm)
    {
        if ($cm->getOrder()->getPayment()->getMethodInstance()->getCode() != 'collectorbank_invoice') {
            return $this;
        }
        
        $_invoice = $cm->getInvoice();
        if (!$_invoice and ($cm->getInvoiceId())) {
            $_invoice = Mage::getModel('sales/order_invoice')->load($cm->getInvoiceId());
        }
        
        $feeKey = Ecomatic_Collectorbank_Model_Collectorbank_Abstract::COLLECTOR_INVOICE_FEE;
        $feeInvoicedKey = Ecomatic_Collectorbank_Model_Collector_Abstract::COLLECTOR_INVOICE_FEE_INVOICED;
        $feeRefundedKey = Ecomatic_Collectorbank_Model_Collector_Abstract::COLLECTOR_INVOICE_FEE_REFUNDED;
        $feeInvoiceNoKey = Ecomatic_Collectorbank_Model_Collector_Abstract::COLLECTOR_INVOICE_FEE_INVOICE_NO;
        $orderData = $cm->getOrder()->getPayment()->getAdditionalInformation();
        
        if ($orderData[$feeInvoiceNoKey] != $_invoice->getTransactionId()) {
            return $this;
        }
        
        if ($orderData[$feeInvoicedKey] == $orderData[$feeRefundedKey]) {
            return $this;
        }

        $itemkeys = array();
        $paramCreditmemo = Mage::app()->getRequest()->getParam('creditmemo');
        if (isset($paramCreditmemo['items'])) {
            $itemkeys = array_keys($paramCreditmemo['items']);
        }
        $invoiceItemQty = array();
        foreach ($cm->getInvoice()->getAllItems() as $invoiceItem) {
            if (!in_array($invoiceItem->getOrderItemId(), $itemkeys)) {
                continue;
            }
            $invoiceItemQty[$invoiceItem->getOrderItemId()] = $invoiceItem->getQty();
        }
        foreach ($cm->getAllItems() as $item) {
            if (!in_array($item->getOrderItemId(), $itemkeys)) {
                continue;
            }
            if ((string)(float)$invoiceItemQty[$item->getOrderItemId()] != (string)(float)$item->getQty()) {
                Mage::register('current_creditmemo_is_partial', true);
                
                $baseCMTotal = $cm->getBaseGrandTotal();
                $CMTotal = $cm->getGrandTotal();

                $cm->setBaseGrandTotal($baseCMTotal+0);
                $cm->setGrandTotal($CMTotal+0);
        
                return $this;
            }
        }
        
        
        $feeToCredit = 0;
        $baseFeeToCredit = 0;
        
        $feeKey = Ecomatic_Collectorbank_Model_Collectorbank_Abstract::COLLECTOR_INVOICE_FEE; 
        $feeInvoicedKey = Ecomatic_Collectorbank_Model_Collectorbank_Abstract::COLLECTOR_INVOICE_FEE_INVOICED;
        
        $orderData = $cm->getOrder()->getPayment()->getAdditionalInformation();
        if ($orderData[$feeInvoicedKey] == $orderData[$feeKey]) {
            //return $this;
        }
        
        if ($cm->getInvoice()) {
            $store = $cm->getOrder()->getStore();
            $baseFeeToCredit = $orderData[$feeKey];
            $feeToCredit = $store->convertPrice($baseFeeToCredit,false);
        }
        else {
            $store = $cm->getOrder()->getStore();
            $baseFeeToCredit = $orderData[$feeInvoicedKey];
            $feeToCredit = $store->convertPrice($baseFeeToCredit,false);
        }

        $baseCMTotal = $cm->getBaseGrandTotal();
        $CMTotal = $cm->getGrandTotal();

        $cm->setBaseGrandTotal($baseCMTotal+$baseFeeToCredit);
        $cm->setGrandTotal($CMTotal+$feeToCredit);

        return $this;
    }

}
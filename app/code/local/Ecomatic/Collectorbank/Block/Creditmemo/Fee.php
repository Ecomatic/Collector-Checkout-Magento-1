<?php

class Ecomatic_Collectorbank_Block_Creditmemo_Fee extends Mage_Adminhtml_Block_Sales_Order_Creditmemo_Totals
{   
    public function initTotals()
    {
        $parent = $this->getParentBlock();
        $cm = $this->getCreditmemo();
        if ($cm) {
            $_invoice = $cm->getInvoice();
            if (!$_invoice and ($cm->getInvoiceId())) {
                $_invoice = Mage::getModel('sales/order_invoice')->load($cm->getInvoiceId());
            }
            if ($_invoice AND $_invoice->getId()) {
                $feeKey = Ecomatic_Collectorbank_Model_Collectorbank_Abstract::COLLECTOR_INVOICE_FEE;
                $feeInvoicedKey = Ecomatic_Collectorbank_Model_Collectorbank_Abstract::COLLECTOR_INVOICE_FEE_INVOICED;
                $feeRefundedKey = Ecomatic_Collectorbank_Model_Collectorbank_Abstract::COLLECTOR_INVOICE_FEE_REFUNDED;
                $feeInvoiceNoKey = Ecomatic_Collectorbank_Model_Collectorbank_Abstract::COLLECTOR_INVOICE_FEE_INVOICE_NO;
                $orderData = $cm->getOrder()->getPayment()->getAdditionalInformation();
                
                if ($orderData[$feeInvoiceNoKey] != $_invoice->getTransactionId()) {
                    return $this;
                }
                
                if ($orderData[$feeInvoicedKey] == $orderData[$feeRefundedKey]) {
                    return $this;
                }
                
                if($orderData[$feeKey] > 0){
                    $fee = new Varien_Object();
                    if (Mage::registry('current_creditmemo_is_partial')) {
                        $fee->setLabel($this->__('Collector Invoice Fee (not refundable on partial creditmemo)'));
                        $fee->setValue($_invoice->getOrder()->getStore()->convertPrice(0,false));
                        $fee->setBaseValue(0);
                    }
                    else {
                        $fee->setLabel($this->__('Collector Invoice Fee'));
                        $fee->setValue($_invoice->getOrder()->getStore()->convertPrice($orderData[$feeKey],false));
                        $fee->setBaseValue($orderData[$feeKey]);
                    }
                    $fee->setCode('collectorinvoicefee');
                    $parent->addTotalBefore($fee,'tax');
                }
            }
        }

        return $this;
    }

}
<?php

class Ecomatic_Collectorbank_Block_Invoice_Fee extends Mage_Core_Block_Abstract
{
    public function initTotals() {
        $parent = $this->getParentBlock();
        $this->_invoice = $parent->getInvoice();

        if ($this->_invoice instanceOf Mage_Sales_Model_Order_Invoice) {
            $order = $this->_invoice->getOrder();
            $orderData = $order->getPayment()->getAdditionalInformation();
            $feeKey = Ecomatic_Collectorbank_Model_Collectorbank_Abstract::COLLECTOR_INVOICE_FEE;
            $feeInvoicedKey = Ecomatic_Collectorbank_Model_Collectorbank_Abstract::COLLECTOR_INVOICE_FEE_INVOICED;
            $feeInvoiceNoKey = Ecomatic_Collectorbank_Model_Collectorbank_Abstract::COLLECTOR_INVOICE_FEE_INVOICE_NO;
            
            if ((!$this->_invoice->getId() AND $orderData[$feeInvoiceNoKey] == 0)
                OR $orderData[$feeInvoiceNoKey] == $this->_invoice->getTransactionId()) {
                if(isset($orderData[$feeKey])) {
                    $fee = new Varien_Object();
                    $fee->setLabel($this->__('Collector Invoice Fee'));
                    $fee->setValue($order->getStore()->convertPrice($orderData[$feeKey],false));
                    $fee->setBaseValue($orderData[$feeKey]);
                    $fee->setCode('collectorinvoicefee');
                    $parent->addTotalBefore($fee,'tax');
                }
            }
        }

        return $this;
    }

}
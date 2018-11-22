<?php

class Ecomatic_Collectorbank_Block_Order_Fee extends Mage_Core_Block_Abstract
{

    public function initTotals() {
    /*    $parent = $this->getParentBlock();
        $this->_order = $parent->getOrder();
        $orderData = $this->_order->getPayment()->getAdditionalInformation();
        $feeKey = Ecomatic_Collectorbank_Model_Collectorbank_Abstract::COLLECTOR_INVOICE_FEE;
        if(isset($orderData[$feeKey])) {
            $fee = new Varien_Object();
            $fee->setLabel($this->__('Collector Invoice Fee'));
            $fee->setValue($this->_order->getStore()->convertPrice($orderData[$feeKey],false));
            $fee->setBaseValue($orderData[$feeKey]);
            $fee->setCode('collectorinvoicefee');
            $parent->addTotalBefore($fee,'tax');
        }
        return $this;*/
    }

}
<?php

class Ecomatic_Collectorbank_Model_Payment_Quote_Total extends Mage_Sales_Model_Quote_Address_Total_Abstract {

    protected $_code = 'collectorinvoicefee';

    public function collect(Mage_Sales_Model_Quote_Address $address) {
        if ($address->getAddressType() != "shipping") {
            return $this;
        }

        $paymentMethod = Mage::app()->getFrontController()->getRequest()->getParam('payment');
        $paymentMethod = Mage::app()->getStore()->isAdmin() && isset($paymentMethod['method']) ? $paymentMethod['method'] : null;
        if ($paymentMethod != 'collectorbank_invoice' && (!count($address->getQuote()->getPaymentsCollection()) || !$address->getQuote()->getPayment()->hasMethodInstance())){
            return $this;
        }

        $paymentMethod = $address->getQuote()->getPayment()->getMethodInstance();
        if ($paymentMethod->getCode() != 'collectorbank_invoice') {
            return $this;
        }


        $fee = $paymentMethod->getInvoiceFee();
        $store = $address->getQuote()->getStore();

        $address->setCollectorInvoiceFee($store->convertPrice($fee,false));
        $address->setBaseCollectorInvoiceFee($fee);


        $address->setBaseGrandTotal($address->getBaseGrandTotal()+$fee);
        $address->setGrandTotal($address->getGrandTotal()+$store->convertPrice($fee, false));

        return $address;
    }

    public function fetch(Mage_Sales_Model_Quote_Address $address) {

        if ($address->getAddressType() != "shipping") {
            return $this;
        }

        $amount = $address->getCollectorInvoiceFee();

        if ($amount!=0) {
            $address->addTotal(array(
                'code' => $this->getCode(),
                'title' => Mage::helper('collectorbank')->__('Collector Invoice Fee'),
                'value' => $amount,
            ));
        }
        return $this;
    }

    protected function getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

}

<?php

class Ecomatic_Collectorbank_Model_Info
{
    protected $_hasTransaction = false;

    protected $_publicMap = array(
        //'payment_ref',
    );

    protected $_secureMap = array(
        //'payment_ref',
    );

    public function getPublicPaymentInfo($payment) {
        return $this->_makeMap($this->_publicMap,$payment);
    }

    public function getPaymentInfo($payment) {
        return $this->_makeMap($this->_secureMap,$payment);
    }

    protected function _makeMap($map,$payment) {
        $result = array();
        foreach ($map as $key) {
            $result[$this->_getLabel($key)] = $this->_getValue($key,$payment);
        }

        return $result;
    }

    protected function _getLabel($key) {
        switch ($key) {
            case 'payment_ref':
                return Mage::helper('collectorbank')->__('Payment Ref');
        }
    }

    protected function _getValue($key,$payment) {
        switch ($key) {
            case 'payment_ref':
                $value = $payment->getAdditionalInformation('collector_'.$key);
                $this->_hasTransaction = ($value)?true:false;
                break;
        }

        if (!$value) {
            $value = '';
        }

        return $value;
    }

    public function hasTransaction() {
        return $this->_hasTransaction;
    }
}
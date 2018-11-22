<?php

class Ecomatic_Collectorbank_Block_Adminhtml_Extendduedate extends Mage_Core_Block_Template
{
    protected function _construct() {
        parent::_construct();
        $this->setTemplate('collectorbank/paymentinfo.phtml');
    }
}
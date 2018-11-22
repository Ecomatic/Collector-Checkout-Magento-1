<?php

class Ecomatic_Collectorbank_Model_Source_Orderstatus extends Mage_Adminhtml_Model_System_Config_Source_Order_Status
{
    public function toOptionArray() {
        $parent = parent::toOptionArray();
        $parent[] = array(
           'value' => 'collector',
           'label' => 'Pending Collector'
        );
        return $parent;
    }
}
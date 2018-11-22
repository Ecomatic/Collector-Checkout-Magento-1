<?php

class Ecomatic_Collectorbank_Model_Source_B2ccountry
{
    public function toOptionArray() {
        $countries = array(
            array('value' => 'NO', 'label' => Mage::helper('collectorbank')->__('Norway')),
            array('value' => 'SE', 'label' => Mage::helper('collectorbank')->__('Sweden')),
			array('value' => 'FI', 'label' => Mage::helper('collectorbank')->__('Finland')),
			array('value' => 'DE', 'label' => Mage::helper('collectorbank')->__('Germany')),
			array('value' => 'DK', 'label' => Mage::helper('collectorbank')->__('Denmark')),
        );

        return $countries;
    }
}
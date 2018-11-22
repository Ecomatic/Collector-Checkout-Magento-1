<?php

class Ecomatic_Collectorbank_Model_Source_Invoicemethod
{
    public function toOptionArray() {
        return array(
            array(
                'value'=>0, 'label'=>Mage::helper('collectorbank')->__('Merchant')
            ),
            array(
                'value'=>1, 'label'=>'Collector'
            ),
        );
    }
}

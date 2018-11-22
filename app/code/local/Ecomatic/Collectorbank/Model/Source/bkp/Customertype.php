<?php

class Ecomatic_Collectorbank_Model_Source_Customertype {
	public function toOptionArray() {
		return array(
			array('value' => 1, 'label' => Mage::helper('collectorbank')->__('Private customers')),
			array('value' => 2, 'label' => Mage::helper('collectorbank')->__('Private customers & Business customers'))
		);
	}

}
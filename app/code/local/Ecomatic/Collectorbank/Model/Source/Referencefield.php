<?php
class Ecomatic_Collectorbank_Model_Source_Referencefield {
	public function toOptionArray() {
		return array(
			array('value' => 'no_reference', 'label' => Mage::helper('collectorbank')->__('No reference field')),
			array('value' => 'firstname_lastname', 'label' => Mage::helper('collectorbank')->__('Use firstname + lastname')),
			array('value' => 'custom', 'label' => Mage::helper('collectorbank')->__('Custom field'))
		);
	}
	public function toArray() {
		return array(
			'no_reference' => Mage::helper('collectorbank')->__('No reference field'),
			'firstname_lastname' => Mage::helper('collectorbank')->__('Use firstname + lastname'),
			'custom' => Mage::helper('collectorbank')->__('Custom field')
		);
	}
}
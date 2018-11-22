<?php
class Ecomatic_Fee_Model_Fee extends Varien_Object{
	
	public static function getFee(){
		return Mage::getStoreConfig('ecomatic_collectorbank/invoice/invoice_fee');
	}
	public static function getB2BFee(){
		return Mage::getStoreConfig('ecomatic_collectorbank/invoice/invoice_fee_company');
	}
	public static function canApply($address){
		
		return true;
		
	}
}
<?php
class Ecomatic_Fee_Model_Sales_Quote_Address_Total_Fee extends Mage_Sales_Model_Quote_Address_Total_Abstract{
	protected $_code = 'fee';

	public function collect(Mage_Sales_Model_Quote_Address $address)
	{
		parent::collect($address);
		$this->_setAmount(0);
		$this->_setBaseAmount(0);
		$currentUrl = Mage::helper('core/url')->getCurrentUrl();
		$url = Mage::getSingleton('core/url')->parseUrl($currentUrl);
		$path = $url->getPath();
		$checkoutPaths = array('/collectorcheckout/index/success');
		if (!in_array($path, $checkoutPaths)){
			return $this;
		}
		$items = $this->_getAddressItems($address);
		if (!count($items)) {
			return $this;
		}


		$quote = $address->getQuote();

		if(Ecomatic_Fee_Model_Fee::canApply($address)){
			$session = Mage::getSingleton('checkout/session');
			if ($session->getUseFee() == 5){
				return;
			}
			if ($session->getCtype() == 'b2b'){
				$fee = Ecomatic_Fee_Model_Fee::getB2BFee();
			}
			else {
				$fee = Ecomatic_Fee_Model_Fee::getFee();
			}
            if ($fee == ""){
                $fee = 0;
            }
			$exist_amount = $quote->getFeeAmount();
			$balance = $fee - $exist_amount;
			// 			$balance = $fee;

			//$this->_setAmount($balance);
			//$this->_setBaseAmount($balance);

			$address->setFeeAmount($balance);
			$address->setBaseFeeAmount($balance);
				
			$quote->setFeeAmount($balance);

			$address->setGrandTotal($address->getGrandTotal() + $address->getFeeAmount());
			$address->setBaseGrandTotal($address->getBaseGrandTotal() + $address->getBaseFeeAmount());
			$quote->save();
			$address->save();
		}
	}

	public function fetch(Mage_Sales_Model_Quote_Address $address)
	{
		$currentUrl = Mage::helper('core/url')->getCurrentUrl();
		$url = Mage::getSingleton('core/url')->parseUrl($currentUrl);
		$path = $url->getPath();
        $checkoutPaths = array('/collectorcheckout/index/success');
		if (!in_array($path, $checkoutPaths)){
			return $this;
		}
		$amt = $address->getFeeAmount();
		$address->addTotal(array(
				'code'=>$this->getCode(),
				'title'=>Mage::helper('fee')->__('Invoice Fee'),
				'value'=> $amt
		));
		return $this;
	}
}
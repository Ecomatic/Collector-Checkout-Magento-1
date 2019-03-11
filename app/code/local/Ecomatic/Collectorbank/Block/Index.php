<?php   
class Ecomatic_Collectorbank_Block_Index extends Mage_Core_Block_Template {   

	public function getIframeSrc(){
		$session = Mage::getSingleton('checkout/session');
		if (Mage::getStoreConfig('ecomatic_collectorbank/general/sandbox_mode')){
			$session->setData('src', "https://checkout-uat.collector.se/collector-checkout-loader.js");
			return "https://checkout-uat.collector.se/collector-checkout-loader.js";
		}
		$session->setData('src', "https://checkout.collector.se/collector-checkout-loader.js");
		return "https://checkout.collector.se/collector-checkout-loader.js";
	}
	
	public function getTypeData(){
		$session = Mage::getSingleton('checkout/session');
		$typeData = $session->getTypeData();
		
		return $typeData;
	}
	
	public function getVarient(){
		$session = Mage::getSingleton('checkout/session');
		$typeData = $this->getTypeData();
		if (Mage::getStoreConfig('ecomatic_collectorbank/general/customer_type') == 3 && !isset($typeData)){
			$typeData['ctype'] = 'b2b';
		}
		$dataVariant = ' async';
		if(isset($typeData)){
			if($typeData['ctype'] == 'b2b'){
				$dataVariant = 'data-variant="b2b" async';
				
			}
		}
		$session->setData('data_variant', $dataVariant);
		return $dataVariant;
	}
	
	public function getLanguage(){
		$session = Mage::getSingleton('checkout/session');
		$typeData = $this->getTypeData();
		$lang = Mage::getStoreConfig('general/country/default');
		if ($lang == "NO"){
			$session->setData('language', "nb-NO");
			return "nb-NO";
		}
		else if ($lang == "SE"){
			$session->setData('language', "sv");
			return "sv";
		}
		else if ($lang == "FI"){
			$session->setData('language', "fi-FI");
			return "fi-FI";
		}
		else if ($lang == "DK"){
			$session->setData('language', "en-DK");
			return "en-DK";
		}
		else if ($lang == "DE"){
			$session->setData('language', "en-DE");
			return "en-DE";
		}
		else {
			return null;
		}
	}
	
	public function getPublicTokeniFrame(){
		$session = Mage::getSingleton('checkout/session');
		$typeData = $this->getTypeData();
		$cart = Mage::getModel('checkout/cart')->getQuote();
		$publicToken = '';
		if (Mage::getStoreConfig('ecomatic_collectorbank/general/customer_type') == 3 && !isset($typeData)){
			$typeData['ctype'] = 'b2b';
		}
		$session->setData('ctype', $typeData['ctype']);
		
		if($typeData['ctype'] == 'b2b'){
			if($session->getBusinessPrivateId() && $session->getLanguage() == $this->getLanguage()){
				if($session->getIsShppingChanged() == 1){
					$privateId = $session->getBusinessPrivateId();
					$updateFees = Mage::getModel('collectorbank/api')->getUpdateFees($typeData,$privateId);
				} else {
					$privateId = $session->getBusinessPrivateId();
					$tokenData = Mage::getModel('collectorbank/api')->getUpdateCart($typeData,$privateId);
					$updateFees = Mage::getModel('collectorbank/api')->getUpdateFees($typeData,$privateId);
				}
				
			} else {
				$tokenData = Mage::getModel('collectorbank/api')->getPublicToken($typeData);
                if ($tokenData['code'] !== 1){
                    return array('error' => 1, 'message' => $tokenData['error']);
                }
				$publicToken  =  $tokenData['publicToken'];	
				$privateId  =  $tokenData['privateId'];
				$hashstr  =  $tokenData['hashstr'];
				
				$session->setData('business_public_token', $publicToken);	
				$session->setData('success_public_token', $publicToken);
				$session->setData('business_private_id', $privateId);	
				$session->setData('business_hashstr', $hashstr);
			}
			
			if($publicToken == ''){
				$publicToken = $session->getBusinessPublicToken();
			}
			
		} 
		else {
			//For B2C
			if($session->getPrivateId() && $session->getLanguage() == $this->getLanguage()){
				if($session->getIsShppingChanged() == 1){
					$privateId = $session->getPrivateId();
					$updateFees = Mage::getModel('collectorbank/api')->getUpdateFees($typeData,$privateId);
				} else {
					$privateId = $session->getPrivateId();
					$tokenData = Mage::getModel('collectorbank/api')->getUpdateCart($typeData,$privateId);
					$updateFees = Mage::getModel('collectorbank/api')->getUpdateFees($typeData,$privateId);
				}
			
			} else {
				$tokenData = Mage::getModel('collectorbank/api')->getPublicToken($typeData);
                if ($tokenData['code'] !== 1){
                    return array('error' => 1, 'message' => $tokenData['error']);
                }
				$publicToken  =  $tokenData['publicToken'];
				$privateId  =  $tokenData['privateId'];
				$hashstr  =  $tokenData['hashstr'];
				
				$session->setData('public_token', $publicToken);
				$session->setData('success_public_token', $publicToken);
				$session->setData('private_id', $privateId);	
				$session->setData('hashstr', $hashstr);
			}
			
			if($publicToken == ''){
				$publicToken = $session->getPublicToken();
			}
		}
		$session->setData('is_shpping_changed',0);
        return array('error' => 0, 'message' => $publicToken);
	
	}


}
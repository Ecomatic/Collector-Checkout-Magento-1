<?php

class Ecomatic_Collectorbank_Model_Api extends Mage_Core_Model_Abstract
{
	public function _construct()
    {
        parent::_construct();
        $this->_init('collectorbank/api');
    }
	
	public function getRedirectPageUri(){
		$getBaseUrl = Mage::getUrl();
		$redirectionUrl = $getBaseUrl."collectorcheckout/index/success";
		return $redirectionUrl;
	}
	
	
	public function getBusinessRedirectPageUri(){
		$getBaseUrl = Mage::getUrl();
		$redirectionUrl = $getBaseUrl."collectorcheckout/index/bsuccess";	
		return $redirectionUrl;
	}
	
	
	public function getMerchantTermsUri(){
		$termsUrl = Mage::getStoreConfig('ecomatic_collectorbank/general/terms_url');
		return $termsUrl;
	}

	public function getValidationUri($nextOrderId){
        $getBaseUrl = Mage::getUrl();
        $validationUrl = $getBaseUrl."collectorcheckout/index/validation?OrderNo=".$nextOrderId;
        return $validationUrl;
    }
	
	public function getNotificationUri($nextOrderId){
		$getBaseUrl = Mage::getUrl();
		$notifyUrl = $getBaseUrl."collectorcheckout/index/notification?OrderNo=".$nextOrderId;
		return $notifyUrl;
	}
	
	
	public function getTaxByPercent($taxclassid){
		$store = Mage::app()->getStore('');
		$request = Mage::getSingleton('tax/calculation')->getRateRequest(null, null, null, $store);
		$percent = Mage::getSingleton('tax/calculation')->getRate($request->setProductClassId($taxclassid));
		return $percent;
	}
	
	public function getCartItems($cart){
		$discount = 0;
		if(count($cart->getAllItems())){
			$bundlesWithFixedPrice = array();
			foreach ($cart->getAllItems() as $item){
				if ($item->getProductType() == 'configurable') {
                    continue;
                }
                elseif (in_array($item->getParentItemId(), $bundlesWithFixedPrice)) {
                    continue;
                }
                elseif ($item->getProductType() == 'bundle') {
                    $product = $item->getProduct();
                    if ($product->getPriceType() == Mage_Bundle_Model_Product_Price::PRICE_TYPE_FIXED) {
                        $bundlesWithFixedPrice[] = $item->getItemId();
                    }
                    elseif ($product->getPriceType() == Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC) {
                        continue;
                    }
                }
				$_product = Mage::getModel('catalog/product')->load($item->getProductId());
				//$cartt["id"] = $item->getProductId();
				$cartt["id"] = $item->getId().'/'.$item->getSku();
				$cartt["description"] = $item->getName();
				if ($item->getParentItemId() && $item->getPriceInclTax() == 0 && $item->getParentItem()->getProductType() != 'bundle') {
					$cartt["unitPrice"] = round($item->getParentItem()->getPriceInclTax(),2);
				}
				else {
					$cartt["unitPrice"] = round($item->getPriceInclTax(),2);
				}
				if ($item->getParentItemId()){
					$cartt["quantity"] = $item->getParentItem()->getQty();
				}
				else {
					$cartt["quantity"] = $item->getQty();
				}
				$taxClassId = $_product->getTaxClassId();
				$percent = $this->getTaxByPercent($taxClassId);
				$cartt["vat"] = round($percent,2);
				//$cartt["vat"] = round($item->getPriceInclTax() - $item->getPrice(),2);
				$cartarray[] = $cartt;
				
				
				$percentage = $item->getTaxPercent();
				$discount = $discount  + ($item->getDiscountAmount() + (($percentage / 100) * ($item->getDiscountAmount())));
				
			}
			
		
			if (strlen($cart->getCouponCode()) > 0){
				$cartt["id"] = 'discount code';
				$cartt["description"] = $cart->getCouponCode();
				$cartt["unitPrice"] = $cart->getTotals()['discount']->getValue();
				$cartt["quantity"] = 1;
				$cartt["vat"] = 0;
				$cartarray[] = $cartt;
			}
			else if(array_key_exists('discount',$cart->getTotals())){
				if ($cart->getTotals()['discount']->getValue() < 0){
					$cartt["id"] = 'discount';
					$cartt["description"] = 'Applied discount amount';
					$cartt["unitPrice"] = $cart->getTotals()['discount']->getValue();
					$cartt["quantity"] = 1;
					$cartt["vat"] = 0;
					$cartarray[] = $cartt;
				}
			}
			
			return $cartarray;
		}
	}
	
	public function getUpdateCart($typeData,$privateId){
		
		$pusername = '';
		$psharedSecret='';
		$pstoreId ='';
		$array = array();
		$session = Mage::getSingleton('checkout/session');
		$cart = Mage::getModel('checkout/cart')->getQuote();
		
		if(isset($typeData)){
			if($typeData['ctype'] == 'b2b'){
				$pusername = trim(Mage::getModel('collectorbank/config')->getBusinessUsername());
				$psharedSecret = trim(Mage::getModel('collectorbank/config')->getBusinessSecretkey());
				$pstoreId = Mage::getModel('collectorbank/config')->getBusinessStoreId();
				$array['storeId'] = $pstoreId;
			} else {
				$pusername = trim(Mage::getModel('collectorbank/config')->getPrivateUsername());
				$psharedSecret = trim(Mage::getModel('collectorbank/config')->getPrivateSecretkey());
				$pstoreId = Mage::getModel('collectorbank/config')->getPrivateStoreId();
				$array['storeId'] = $pstoreId;
			}
			
		} else {
			$pusername = trim(Mage::getModel('collectorbank/config')->getPrivateUsername());
			$psharedSecret = trim(Mage::getModel('collectorbank/config')->getPrivateSecretkey());
			$pstoreId = Mage::getModel('collectorbank/config')->getPrivateStoreId();
			$array['storeId'] = $pstoreId;			
		}
		
		$array["items"] = $this->getCartItems($cart);
		
		$init = Mage::getModel('collectorbank/config')->getInitializeUrl();
		$path = '/merchants/'.$array['storeId'].'/checkouts/'.$privateId.'/cart';
		
		
		$json = json_encode($array);
		Mage::log('REQUEST FOR UPDATE CART -->'.$json, null,'cartiframe.log');
		$hash = $pusername.":".hash("sha256",$json.$path.$psharedSecret);
		$hashstr = 'SharedKey '.base64_encode($hash); 
		
		$ch = curl_init($init.$path);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'charset=utf-8','Authorization:'.$hashstr));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		$output = curl_exec($ch);
		Mage::log('RESPONSE FOR UPDATE CART -->'.$output, null,'cartiframe.log');	
		$data = json_decode($output,true);
		if(curl_error($ch)){
			Mage::log('ERROR FOR UPDATE CART -->'.curl_error($ch), null,'cartiframe.log');	
		}

		curl_close($ch);
	}
	
	
	public function getUpdateFees($typeData,$privateId){
		
		$pusername = '';
		$psharedSecret='';
		$pstoreId ='';
		$array = array();
		$session = Mage::getSingleton('checkout/session');
		$cart = Mage::getModel('checkout/cart')->getQuote();
	
		if(isset($typeData)){
			if($typeData['ctype'] == 'b2b'){
				$pusername = trim(Mage::getModel('collectorbank/config')->getBusinessUsername());
				$psharedSecret = trim(Mage::getModel('collectorbank/config')->getBusinessSecretkey());
				$pstoreId = Mage::getModel('collectorbank/config')->getBusinessStoreId();
				$array['storeId'] = $pstoreId;
			} else {
				$pusername = trim(Mage::getModel('collectorbank/config')->getPrivateUsername());
				$psharedSecret = trim(Mage::getModel('collectorbank/config')->getPrivateSecretkey());
				$pstoreId = Mage::getModel('collectorbank/config')->getPrivateStoreId();
				$array['storeId'] = $pstoreId;
			}
			
		} else {
			$pusername = trim(Mage::getModel('collectorbank/config')->getPrivateUsername());
			$psharedSecret = trim(Mage::getModel('collectorbank/config')->getPrivateSecretkey());
			$pstoreId = Mage::getModel('collectorbank/config')->getPrivateStoreId();
			$array['storeId'] = $pstoreId;			
		}
		
		$address =  $cart->getShippingAddress();
		
		$shippingTaxClassId = Mage::getStoreConfig('tax/classes/shipping_tax_class');
		$shippingTaxPercent = $this->getTaxByPercent($shippingTaxClassId);
		
		$selectedShipMethod = $address->getShippingMethod();		
		$sihpDesc = $address->getShippingDescription();
		$sihpAmount = $address->getShippingAmount();
		$sihpDiscAmount = $address->getShippingDiscountAmount();
		$sihpHidTaxAmount = $address->getShippingHiddenTaxAmount();
		$sihpInclTaxAmount = $address->getShippingInclTax();
		$sihpTaxAmount = $address->getShippingTaxAmount();
		
		$array["shipping"]["id"] = $selectedShipMethod;
		$array["shipping"]["description"] = Mage::helper('collectorbank/data')->cutStringAt($sihpDesc, 50);
		$array["shipping"]["unitPrice"] = round($sihpInclTaxAmount,2);
		$array["shipping"]["vat"] = round($shippingTaxPercent,2);
		
		if ($typeData['ctype'] == 'b2b'){
			$invoiceFee = Mage::getStoreConfig('ecomatic_collectorbank/invoice/invoice_fee_company');
			$invoicetaxclassid = Mage::getStoreConfig('ecomatic_collectorbank/invoice/invoice_fee_company_tax_class');
		}
		else {
			$invoiceFee = Mage::getStoreConfig('ecomatic_collectorbank/invoice/invoice_fee');
			$invoicetaxclassid = Mage::getStoreConfig('ecomatic_collectorbank/invoice/invoice_fee_tax_class');
		}
		
		$invoicepercent = $this->getTaxByPercent($invoicetaxclassid);
		
		$array["directinvoicenotification"]["id"] = "INVOICE_FEE";
		$array["directinvoicenotification"]["description"] = "Invoice fee";
		$array["directinvoicenotification"]["unitPrice"] = round($invoiceFee,2);
		$array["directinvoicenotification"]["vat"] = round($invoicepercent,2);
			
		
		$init = Mage::getModel('collectorbank/config')->getInitializeUrl();
		$path = '/merchants/'.$array['storeId'].'/checkouts/'.$privateId.'/fees';
		
		$json = json_encode($array);
		
		if (isset($_SESSION['prev_get_fees_req'])){
			if ($_SESSION['prev_get_fees_req'] == $json){
				return;
			}
		}
		$_SESSION['prev_get_fees_req'] = $json;
		
		Mage::log('REQUEST FOR UPDATE FEES -->'.$json, null,'cartiframe.log');
		$hash = $pusername.":".hash("sha256",$json.$path.$psharedSecret);
		$hashstr = 'SharedKey '.base64_encode($hash); 
		
		$ch = curl_init($init.$path);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'charset=utf-8','Authorization:'.$hashstr));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		$output = curl_exec($ch);
		Mage::log('RESPONSE FOR UPDATE FEES -->'.$output, null,'cartiframe.log');	
		$data = json_decode($output,true);
		
		if(curl_error($ch)){
			Mage::log('ERROR FOR UPDATE FEES -->'.curl_error($ch), null,'cartiframe.log');	
		}

		curl_close($ch);
	}
	
	public function getPublicToken($typeData){
		$selectedShipMethod = '';
		$pusername = '';
		$psharedSecret='';
		$pstoreId ='';
		$array = array();
		if (Mage::getStoreConfig('ecomatic_collectorbank/general/customer_type') == 3 && !isset($typeData)){
			$typeData['ctype'] = 'b2b';
		}
		
		if(isset($typeData)){
			if($typeData['ctype'] == 'b2b'){
				$pusername = trim(Mage::getModel('collectorbank/config')->getBusinessUsername());
				$psharedSecret = trim(Mage::getModel('collectorbank/config')->getBusinessSecretkey());
				$pstoreId = Mage::getModel('collectorbank/config')->getBusinessStoreId();
				$array['storeId'] = $pstoreId;
			} else {
				$pusername = trim(Mage::getModel('collectorbank/config')->getPrivateUsername());
				$psharedSecret = trim(Mage::getModel('collectorbank/config')->getPrivateSecretkey());
				$pstoreId = Mage::getModel('collectorbank/config')->getPrivateStoreId();
				$array['storeId'] = $pstoreId;
			}
			
		} else {
			$pusername = trim(Mage::getModel('collectorbank/config')->getPrivateUsername());
			$psharedSecret = trim(Mage::getModel('collectorbank/config')->getPrivateSecretkey());
			$pstoreId = Mage::getModel('collectorbank/config')->getPrivateStoreId();
			$array['storeId'] = $pstoreId;
		}
		$array['countryCode'] = Mage::getStoreConfig('general/country/default');
		$cart = Mage::getSingleton('checkout/session')->getQuote();
		$currency = $cart->getStoreCurrencyCode();
		$lang = $array['countryCode'];
		if (!(($lang == "SE" && $currency == "SEK") || ($lang == "NO" && $currency == "NOK") || ($lang == "DE" && $currency == "EUR") || ($lang == "DK" && $currency == "DKK") || ($lang == "FI" && $currency == "EUR"))){
			return;
		}
		
		$init = Mage::getModel('collectorbank/config')->getInitializeUrl();
		$path = '/checkout';		
		
		$session = Mage::getSingleton('checkout/session');
		$cart = Mage::getModel('checkout/cart')->getQuote();
		
		
		$cart->reserveOrderId();
		$nextOrderId = $cart->getReservedOrderId();
		$array['reference'] = $nextOrderId;
		
		
		
		if($typeData['ctype'] == 'b2b'){
			$array['redirectPageUri'] = $this->getBusinessRedirectPageUri();
		} else {
			$array['redirectPageUri'] = $this->getRedirectPageUri();
		}
		$array['merchantTermsUri'] = $this->getMerchantTermsUri();
		$array['notificationUri'] = $this->getNotificationUri($nextOrderId);
        $array['validationUri'] = $this->getValidationUri($nextOrderId);
		$address =  $cart->getShippingAddress();
		$selectedShipMethod = $address->getShippingMethod();

		$methods = Mage::getSingleton('shipping/config')->getActiveCarriers();
		/*if(empty($selectedShipMethod)){
			$selectedShipMethod = "freeshipping_freeshipping";
			$sihpDesc = "Free Shipping";
			$sihpInclTaxAmount = 0;
			$shippingTaxPercent = 0;
		}*/
		if(!empty($selectedShipMethod)){
			
			$sihpDesc = $address->getShippingDescription()? $address->getShippingDescription() : 'shipping';
			$sihpAmount = $address->getShippingAmount();
			$sihpDiscAmount = $address->getShippingDiscountAmount();
			$sihpHidTaxAmount = $address->getShippingHiddenTaxAmount();
			$sihpInclTaxAmount = $address->getShippingInclTax();
			$sihpTaxAmount = $address->getShippingTaxAmount();			
				  
			$array["cart"]["items"] = $this->getCartItems($cart);
			
			
			$shippingTaxClassId = Mage::getStoreConfig('tax/classes/shipping_tax_class');
			$shippingTaxPercent = $this->getTaxByPercent($shippingTaxClassId);
		 
			$array["fees"]["shipping"]["id"] = $selectedShipMethod;
			$array["fees"]["shipping"]["description"] = Mage::helper('collectorbank/data')->cutStringAt($sihpDesc, 50);
			$array["fees"]["shipping"]["unitPrice"] = round($sihpInclTaxAmount,2);
			$array["fees"]["shipping"]["vat"] = round($shippingTaxPercent,2);					
			
			if ($typeData['ctype'] == 'b2b'){
				$invoiceFee = Mage::getStoreConfig('ecomatic_collectorbank/invoice/invoice_fee_company');
				$invoicetaxclassid = Mage::getStoreConfig('ecomatic_collectorbank/invoice/invoice_fee_company_tax_class');
			}
			else {
				$typeData['ctype'] = 'b2c';
				$invoiceFee = Mage::getStoreConfig('ecomatic_collectorbank/invoice/invoice_fee');
				$invoicetaxclassid = Mage::getStoreConfig('ecomatic_collectorbank/invoice/invoice_fee_tax_class');
			}
			$invoicepercent = $this->getTaxByPercent($invoicetaxclassid);			
			
			$array["fees"]["directinvoicenotification"]["id"] = "INVOICE_FEE";
			$array["fees"]["directinvoicenotification"]["description"] = "Invoice fee";
			$array["fees"]["directinvoicenotification"]["unitPrice"] = round($invoiceFee,2);
			$array["fees"]["directinvoicenotification"]["vat"] = round($invoicepercent,2);
            if(Mage::getSingleton('customer/session')->isLoggedIn()){
                $telephone = false;
                $postalCode = false;
                $email = Mage::getSingleton('customer/session')->getCustomer()->getEmail();
                if (Mage::getSingleton('customer/session')->getCustomer()->getPrimaryShippingAddress()->getTelephone() !== null){
                    $telephone = Mage::getSingleton('customer/session')->getCustomer()->getPrimaryShippingAddress()->getTelephone();
                }
                else if (Mage::getSingleton('customer/session')->getCustomer()->getPrimaryBillingAddress()->getTelephone() !== null){
                    $telephone = Mage::getSingleton('customer/session')->getCustomer()->getPrimaryBillingAddress()->getTelephone();
                }
                if (Mage::getSingleton('customer/session')->getCustomer()->getPrimaryShippingAddress()->getPostcode() !== null){
                    $postalCode = Mage::getSingleton('customer/session')->getCustomer()->getPrimaryShippingAddress()->getPostcode();
                }
                else if (Mage::getSingleton('customer/session')->getCustomer()->getPrimaryBillingAddress()->getPostcode() !== null){
                    $postalCode = Mage::getSingleton('customer/session')->getCustomer()->getPrimaryBillingAddress()->getPostcode();
                }
                if ($email !== false && $postalCode !== false && $telephone !== false) {
                    $array['customer'] = array(
                        'email' => $email,
                        'mobilePhoneNumber' => $telephone,
                        'postalCode' => $postalCode,
                    );
                }
            }
			
			$json = json_encode($array);
			Mage::log('REQUEST -->'.$json, null,'cartiframe.log');	
			$hash = $pusername.":".hash("sha256",$json.$path.$psharedSecret);
			$hashstr = 'SharedKey '.base64_encode($hash); 
			
			
			$ch = curl_init($init.$path);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'charset=utf-8','Authorization:'.$hashstr));
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
			$output = curl_exec($ch);
			ob_start();
			var_dump(curl_getinfo($ch));
			
			$error = ' Curl error: ' . curl_error($ch);
			$info = ' Curl Info: ' . ob_get_clean();
		
			Mage::log('RESPONSE -->'.$output.$error.$info.$init.$path."\n", null,'cartiframe.log');	
			
			if(curl_error($ch)){
				Mage::log('ERROR -->'.curl_error($ch), null,'cartiframe.log');	
			}
			curl_close($ch);
			
			$data = json_decode($output,true);

			$cart->setData('coll_purchase_identifier', $data["data"]["privateId"]);
			$cart->setData('coll_customer_type', $typeData['ctype']);
			$cart->save();
			$session->setData('reference',$nextOrderId);
			//set selected shipping method in checkout session
			if($session->getSelectedShippingmethod()){
				$session->unsSelectedShippingmethod();
				$session->setData('selected_shippingmethod',$selectedShipMethod);
			} else {
				$session->setData('selected_shippingmethod',$selectedShipMethod);
			}
			
			
			if($data["data"]){
				$result['code'] = 1;
				$result['publicToken'] = $data["data"]["publicToken"];
				$result['privateId'] = $data["data"]["privateId"];
				$result['hashstr'] = $hashstr;
				
			} else {
				$result['code'] = 0;
				$result['error'] = $data["error"];
				Mage::log('ERROR -->'.$data["error"], null,'cartiframe.log');
				
			}
		} else {
			Mage::log('ERROR -->'."Please select Shipping Method for Collector Checkout", null,'cartiframe.log');
			$result['code'] = -1;
			$result['error'] = "Please select Shipping Method";
		}
		
		return $result;
		
	}
	
	public function getOrderResponse() {
	
		$sessData = Mage::getSingleton('checkout/session')->getData();
		$typeData = $sessData['type_data'];
		
		if($typeData['ctype'] == 'b2b'){
			$privateId = $sessData['business_private_id'];
		} else {
			$privateId = $sessData['private_id'];
		}
		
		$init = Mage::getModel('collectorbank/config')->getInitializeUrl();
		if($privateId){
			
			if(isset($typeData)){
				if($typeData['ctype'] == 'b2b'){
					$pusername = trim(Mage::getModel('collectorbank/config')->getBusinessUsername());
					$psharedSecret = trim(Mage::getModel('collectorbank/config')->getBusinessSecretkey());
					$pstoreId = Mage::getModel('collectorbank/config')->getBusinessStoreId();
					$array['storeId'] = $pstoreId;
				} else {
					$pusername = trim(Mage::getModel('collectorbank/config')->getPrivateUsername());
					$psharedSecret = trim(Mage::getModel('collectorbank/config')->getPrivateSecretkey());
					$pstoreId = Mage::getModel('collectorbank/config')->getPrivateStoreId();
					$array['storeId'] = $pstoreId;
				}
				
			} else {
				$pusername = trim(Mage::getModel('collectorbank/config')->getPrivateUsername());
				$psharedSecret = trim(Mage::getModel('collectorbank/config')->getPrivateSecretkey());
				$pstoreId = Mage::getModel('collectorbank/config')->getPrivateStoreId();
				$array['storeId'] = $pstoreId;
			}
					
			$path = '/merchants/'.$pstoreId.'/checkouts/'.$privateId;
			$hash = $pusername.":".hash("sha256",$path.$psharedSecret);
			$hashstr = 'SharedKey '.base64_encode($hash);
			
			Mage::log('REQUEST >>> Private id is '.$privateId .' with shared key --> '.$hashstr, null,'magentoorder.log');			

			$ch = curl_init($init.$path);
			curl_setopt($ch, CURLOPT_HTTPGET, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization:'.$hashstr));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

			$output = curl_exec($ch);
			Mage::log('RESPONSE >>> '.$output, null,'magentoorder.log');
			$data = json_decode($output,true);
			
			if($data["data"]){
				$result['code'] = 1;
				$result['id'] = $data["id"];
				$result['data'] = $data["data"];
				
			} else {
				$result['code'] = 0;
				$result['error'] = $data["error"];
				
			}			
			return $result;
		}
	}
	
	
}
<?php

class Ecomatic_Ajax_AjaxController extends Mage_Core_Controller_Front_Action {
    public function indexAction(){
        $this->_redirect('checkout/onepage', array('_secure'=>true));
    }


	public function headercartAction(){
		$cart = Mage::getSingleton('checkout/cart');
		$messages = array();
        foreach ($cart->getQuote()->getMessages() as $message) {
            if ($message) {
                // Escape HTML entities in quote message to prevent XSS
                $message->setCode(Mage::helper('core')->escapeHtml($message->getCode()));
                $messages[] = $message;
            }
        }
        $cart->getCheckoutSession()->addUniqueMessages($messages);
		$this->loadLayout()
			->_initLayoutMessages('checkout/session')
			->_initLayoutMessages('catalog/session');
		$this->renderLayout();
	}

    public function headercartdeleteAction() {
		
        if ($this->getRequest()->getParam('btn_lnk')){
            $id = $this->getRequest()->getParam('id');
            if ($id) {
                try {
                    Mage::getSingleton('checkout/cart')->removeItem($id)->save();
                } catch (Exception $e) {
                    Mage::getSingleton('checkout/session')->addError($this->__('Cannot remove item'));
                }
            }
			/*$found = false;
			$shippingaddress = Mage::getSingleton('checkout/cart')->getQuote()->getShippingAddress();
			$_shippingRateGroups = $shippingaddress->getGroupedAllShippingRates();
			foreach ($_shippingRateGroups as $code => $_rates){
				foreach ($_rates as $rate){
					if ($rate->getCode() === $shippingaddress->getShippingMethod()){
						$found = true;
					}
				}
			}
			if (!$found){
				$first = true;
				foreach ($_shippingRateGroups as $code => $_rates){
					foreach ($_rates as $rate){
						if ($first){
							$this->_getQuote()->getShippingAddress()->setShippingMethod($rate->getCode())/*->collectTotals()->save();
							$first = false;
						}
					}
				}
			}*/
			$cart = $this->_getCart();
			$cart->getQuote()->collectTotals();
			$cart->getQuote()->save();
			$cart->save();
            $cart = Mage::getSingleton('checkout/cart');
			$messages = array();
			foreach ($cart->getQuote()->getMessages() as $message) {
				if ($message) {
					// Escape HTML entities in quote message to prevent XSS
					$message->setCode(Mage::helper('core')->escapeHtml($message->getCode()));
					$messages[] = $message;
				}
			}
			$cart->getCheckoutSession()->addUniqueMessages($messages);
			$cart->getQuote()->collectTotals();
			$cart->getQuote()->save();
			$cart->save();
			$this->loadLayout()->_initLayoutMessages('checkout/session')->_initLayoutMessages('catalog/session');
            $newblock = $this->getLayout()->getBlock('cart_sidebar_ajax')->toHtml();
			$this->getLayout()->getBlock('collectorbank_index')->toHtml();
            $this->getResponse()->setBody($newblock);
            $this->_initLayoutMessages('checkout/session');
            $this->renderLayout();
        } else {
            $backUrl = $this->_getRefererUrl();
            $this->getResponse()->setRedirect($backUrl);
        }
    }

    public function headercartupdateAction(){
        try {
            $cartData = array($_POST['item'] => array('qty' => $_POST['qty']));

            if (is_array($cartData)) {
                $filter = new Zend_Filter_LocalizedToNormalized(
                    array('locale' => Mage::app()->getLocale()->getLocaleCode())
                );
                foreach ($cartData as $index => $data) {
                    if (isset($data['qty'])) {
                        $cartData[$index]['qty'] = $filter->filter(trim($data['qty']));
                    }
                }
                $cart = $this->_getCart();
                if (! $cart->getCustomerSession()->getCustomer()->getId() && $cart->getQuote()->getCustomerId()) {
                    $cart->getQuote()->setCustomerId(null);
                }

                $cartData = $cart->suggestItemsQty($cartData);
                $cart->updateItems($cartData)->save();
                $this->_getSession()->setCartWasUpdated(true);

                $cart = Mage::getSingleton('checkout/cart');
		$messages = array();
        foreach ($cart->getQuote()->getMessages() as $message) {
            if ($message) {
                // Escape HTML entities in quote message to prevent XSS
                $message->setCode(Mage::helper('core')->escapeHtml($message->getCode()));
                $messages[] = $message;
            }
        }
        $cart->getCheckoutSession()->addUniqueMessages($messages);
		$this->loadLayout()
			->_initLayoutMessages('checkout/session')
			->_initLayoutMessages('catalog/session');
                $this->renderLayout();
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError(Mage::helper('core')->escapeHtml($e->getMessage()));
        } catch (Exception $e) {
            $this->_getSession()->addException($e, $this->__('Cannot update shopping cart.'));
            Mage::logException($e);
        }
    }

    public function _getCart(){
        return Mage::getSingleton('checkout/cart');
    }

    public function _getSession(){
        return Mage::getSingleton('checkout/session');
    }

    public function _getQuote(){
        return $this->_getCart()->getQuote();
    }
	
	public function qtyincreseAction(){
		$session = Mage::getSingleton('checkout/session');
		try {
        	$response = array();
			$data = $this->getRequest()->getPost();
			$tmp = $data;
			$cartData = array($data['item_id'] => array('qty' => $data['item_qty']+$data['prevQty']));
			
            if (is_array($cartData)) {
                $filter = new Zend_Filter_LocalizedToNormalized(
                    array('locale' => Mage::app()->getLocale()->getLocaleCode())
                );
				
                foreach ($cartData as $index => $data) {
                    if (isset($data['qty'])) {                        
                        $cartData[$index]['qty'] = trim($data['qty']);
                    }
                }				
                $cart = $this->_getCart();
				$outOfStock = false;
				foreach ($cart->getItems() as $item){
					if ($tmp['item_id'] == $item->getId()){
                        $_product = Mage::getModel('catalog/product')->loadByAttribute('sku', $item->getSku());
						$stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($_product);
						if ($stock->getQty() < $tmp['item_qty']+$tmp['prevQty']){
							$outOfStock = true;
						}
					}
				}
				if ($outOfStock){
					$session->addError($this->__('That Product is Out of Stock'));
				}
                if (! $cart->getCustomerSession()->getCustomer()->getId() && $cart->getQuote()->getCustomerId()) {
                    $cart->getQuote()->setCustomerId(null);
                }
				if (!$outOfStock){
					$cartData = $cart->suggestItemsQty($cartData);
					$cart->updateItems($cartData)->save();
				}
				$cart = Mage::getSingleton('checkout/cart');
				$messages = array();
				foreach ($cart->getQuote()->getMessages() as $message) {
					if ($message) {
						// Escape HTML entities in quote message to prevent XSS
						$message->setCode(Mage::helper('core')->escapeHtml($message->getCode()));
						$messages[] = $message;
					}
				}
				$this->loadLayout()
					->_initLayoutMessages('checkout/session')
					->_initLayoutMessages('catalog/session');
				
				if(Mage::getSingleton('core/design_package')->getPackageName()== "rwd") {
					
					$cart_list = $this->getLayout()->createBlock('checkout/cart')->setTemplate('ajax/checkout/cart.phtml')->toHtml();
					$cart_list = $this->getLayout()->getBlock('cart_content_ajax')->toHtml();
					$response['cart_content_ajax'] = $cart_list;
				
					$mini_cart = $this->getLayout()->getBlock('minicart_head')->toHtml();
					$response['min_cart'] = $mini_cart;
					
					$collector = $this->getLayout()->getBlock('collectorbank_index')->toHtml();
					$response['collectorbank_index'] = $collector;
					
				}
				else if (Mage::getSingleton('core/design_package')->getPackageName()== "smartwave"){
					$cart_list = $this->getLayout()->getBlock('cart_content_ajax')->toHtml();
					$response['cart_content_ajax'] = $cart_list;
					$mini_cart = $this->getLayout()->getBlock('minicart_head')->toHtml();
					$this->getLayout()->getBlock('collectorbank_index')->toHtml();
					$response['min_cart'] = $mini_cart;
					$response['smart_min_cart'] = $this->getLayout()->getBlock('minicart')->toHtml();
				}
				else {	  
     				$cart_list = $this->getLayout()->getBlock('cart_content_ajax')->toHtml();
					$this->getLayout()->getBlock('collectorbank_index')->toHtml();
					$response['cart_content_ajax'] = $cart_list;
					$toplink = $this->getLayout()->getBlock('cartTop')->toHtml();                      
                    $response['header_cart'] = $toplink;
					$mini_cart = $this->getLayout()->getBlock('cart_sidebar')->toHtml();                      
                    $response['min_cart'] = $mini_cart;
				}
				$response['message'] = $this->__("Quantity increased successfully.");
            }
        } catch (Mage_Core_Exception $e) {           
			$response['status'] = 'ERROR';
			$response['message'] = $this->__($e->getMessage());
        } catch (Exception $e) {           
            $response['status'] = 'ERROR';
			$response['message'] = $this->__('Cannot update shopping cart.');
        }
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
		return;
	}
	
	public function qtydecreseAction(){
	 	try {
        	$response = array();          
			$data = $this->getRequest()->getPost();
			$preQty = $data['prevQty'];
			$minusQty = $data['item_qty'];
			$finalQty = $preQty - $minusQty;
			if($finalQty <= 0){
				$finalQty = 1;	
			}
            $cartData = array($data['item_id'] => array('qty' =>$finalQty)); 
					
            if (is_array($cartData)) {
                $filter = new Zend_Filter_LocalizedToNormalized(
                    array('locale' => Mage::app()->getLocale()->getLocaleCode())
                );
                foreach ($cartData as $index => $data) {
                    if (isset($data['qty'])) {
                        $cartData[$index]['qty'] = trim($data['qty']);
                    }
                }
				
                $cart = $this->_getCart();
                if (! $cart->getCustomerSession()->getCustomer()->getId() && $cart->getQuote()->getCustomerId()) {
                    $cart->getQuote()->setCustomerId(null);
                }
                $cartData = $cart->suggestItemsQty($cartData);
                $cart->updateItems($cartData)->save();
			
				$cart = Mage::getSingleton('checkout/cart');
				$messages = array();
				foreach ($cart->getQuote()->getMessages() as $message) {
					if ($message) {
						// Escape HTML entities in quote message to prevent XSS
						$message->setCode(Mage::helper('core')->escapeHtml($message->getCode()));
						$messages[] = $message;
					}
				}
				$cart->getCheckoutSession()->addUniqueMessages($messages);
				$this->loadLayout()
					->_initLayoutMessages('checkout/session')
					->_initLayoutMessages('catalog/session');
				
				if(Mage::getSingleton('core/design_package')->getPackageName()== "rwd"){
					$cart_list = $this->getLayout()->getBlock('cart_content_ajax')->toHtml();
					$response['cart_content_ajax'] = $cart_list;
					
					$mini_cart = $this->getLayout()->getBlock('minicart_head')->toHtml();
					$this->getLayout()->getBlock('collectorbank_index')->toHtml();
					$response['min_cart'] = $mini_cart;
					$cart_sidebar = $this->getLayout()->getBlock('cart_sidebar')->toHtml();
					$response['cart_sidebar'] = $cart_sidebar;
				}
				else if (Mage::getSingleton('core/design_package')->getPackageName()== "smartwave"){
					$cart_list = $this->getLayout()->getBlock('cart_content_ajax')->toHtml();
					$response['cart_content_ajax'] = $cart_list;
					$mini_cart = $this->getLayout()->getBlock('minicart_head')->toHtml();
					$this->getLayout()->getBlock('collectorbank_index')->toHtml();
					$response['min_cart'] = $mini_cart;
					$response['smart_min_cart'] = $this->getLayout()->getBlock('minicart')->toHtml();
				}
				else {
     				$cart_list = $this->getLayout()->getBlock('cart_content_ajax')->toHtml();
					$this->getLayout()->getBlock('collectorbank_index')->toHtml();
					$response['cart_content_ajax'] = $cart_list;
					$toplink = $this->getLayout()->getBlock('cartTop')->toHtml();                      
                    $response['header_cart'] = $toplink;
					$mini_cart = $this->getLayout()->getBlock('cart_sidebar')->toHtml();                      
                    $response['min_cart'] = $mini_cart;
				}
				$response['message'] = $this->__("Quantity decreased successfully.");
            }
            
        } catch (Mage_Core_Exception $e) {           
			$response['status'] = 'ERROR';
			$response['message'] = $this->__($e->getMessage());
        } catch (Exception $e) {           
            $response['status'] = 'ERROR';
			$response['message'] = $this->__('Cannot update shopping cart.');
        }
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
		return;		
	}
	
	public function estimateUpdatePostAction()
	{
		try {
        	$response = array();
			$data = $this->getRequest()->getPost();
			
			$code = (string) $this->getRequest()->getParam('estimate_method');
			if (!empty($code)) {
				$this->_getQuote()->getShippingAddress()->setShippingMethod($code)/*->collectTotals()*/->save();
			}
			$cart = $this->_getCart();                
			$cart->save();
			
			$cart = Mage::getSingleton('checkout/cart');
			$messages = array();
			foreach ($cart->getQuote()->getMessages() as $message) {
				if ($message) {
					// Escape HTML entities in quote message to prevent XSS
					$message->setCode(Mage::helper('core')->escapeHtml($message->getCode()));
					$messages[] = $message;
				}
			}
			$cart->getCheckoutSession()->addUniqueMessages($messages);
			$this->loadLayout()
				->_initLayoutMessages('checkout/session')
				->_initLayoutMessages('catalog/session');
			$session = Mage::getSingleton('checkout/session');
			$session->setData('is_shpping_changed',1); 
			$cart_list = $this->getLayout()->getBlock('cart_content_ajax')->toHtml();
			if ($session->getPublicToken() == null) {
                $checkout = $this->getLayout()->getBlock('collectorbank_index')->toHtml();
                $response['checkout_ajax'] = $checkout;
			}
			else {
                $this->getLayout()->getBlock('collectorbank_index')->toHtml();
            }
			$response['cart_content_ajax'] = $cart_list;
            
        } catch (Mage_Core_Exception $e) {           
			$response['status'] = 'ERROR';
			$response['message'] = $this->__($e->getMessage());
        } catch (Exception $e) {           
            $response['status'] = 'ERROR';
			$response['message'] = $this->__('Cannot update shopping cart.');
        }
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
		return;
	}
	
	public function loadiframeAction()
	{
		//echo "in herereere";die;
		$data = $this->getRequest()->getPost();
		//echo "<pre>";print_r($data);die;
		$cart = Mage::getSingleton('checkout/cart');
		$messages = array();
        foreach ($cart->getQuote()->getMessages() as $message) {
            if ($message) {
                // Escape HTML entities in quote message to prevent XSS
                $message->setCode(Mage::helper('core')->escapeHtml($message->getCode()));
                $messages[] = $message;
            }
        }
        $cart->getCheckoutSession()->addUniqueMessages($messages);
		$this->loadLayout()
			->_initLayoutMessages('checkout/session')
			->_initLayoutMessages('catalog/session');
		$session = Mage::getSingleton('checkout/session');
		$session->setData('type_data', $data);
		$session->setData('ctype', $data['ctype']);
		$session->setBusinessPrivateId(null);
        $session->setPrivateId(null);
		$cart_list = $this->getLayout()->getBlock('cart_content_ajax')->toHtml();
		$response['cart_content_ajax'] = $cart_list;
		$response['iframe_ajax'] = $this->getLayout()->getBlock('collectorbank_index')->toHtml();
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
		return;
	}
	
	public function subscribeAction()
	{
		//echo "in herereere";die;
		$data = $this->getRequest()->getPost();
		//echo "<pre>";print_r($data);die;
		
		$session = Mage::getSingleton('checkout/session');
		$session->setData('is_subscribed', $data['is_subscribed']);	
		$cart = Mage::getSingleton('checkout/cart');
		$messages = array();
        foreach ($cart->getQuote()->getMessages() as $message) {
            if ($message) {
                // Escape HTML entities in quote message to prevent XSS
                $message->setCode(Mage::helper('core')->escapeHtml($message->getCode()));
                $messages[] = $message;
            }
        }
        $cart->getCheckoutSession()->addUniqueMessages($messages);
		$this->loadLayout()
			->_initLayoutMessages('checkout/session')
			->_initLayoutMessages('catalog/session');
		$this->renderLayout();
		/* $cart_list = $this->getLayout()->getBlock('cart_content_ajax')->toHtml();
		$response['cart_content_ajax'] = $cart_list;
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response)); */
		return;
	}
}
?>

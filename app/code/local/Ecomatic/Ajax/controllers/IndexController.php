<?php
class Ecomatic_Ajax_IndexController extends Mage_Core_Controller_Front_Action{
    public function indexAction() {
      
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
}
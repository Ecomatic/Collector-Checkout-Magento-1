<?php

class Ecomatic_Collectorbank_Model_Observer {
	public function checkEnabled(){
		// Disable the module itself
		$moduleName = "Ecomatic_Collectorbank";
		$nodePath = "modules/".$moduleName."/active";
		if (!Mage::helper('core/data')->isModuleEnabled($moduleName)) {
			Mage::getConfig()->setNode($nodePath, 'false', true);
		}
	 
		// Disable its output as well (which was already loaded)
		if(!Mage::getModel('collectorbank/config')->getEnabled()) {
			$outputPath = "advanced/modules_disable_output/".$moduleName;
			if (!Mage::getStoreConfig($outputPath)) {
				Mage::app()->getStore()->setConfig($outputPath, true);
			}
		}
		//Mage::app()->getCache()->clean();
	}
	
	/* For selection of shipping method by default on cart */
	public function handleCollect($observer) {
	    if (Mage::app()->getFrontController()->getAction() !== null) {
            if (Mage::app()->getFrontController()->getAction()->getFullActionName() == 'collectorbank_index_index') {
                $quote = $observer->getEvent()->getQuote();
                $shippingAddress = $quote->getShippingAddress();
                $saveQuote = false;
                //if (!$shippingAddress->getCountryId()) {
                if (!$shippingAddress->getShippingMethod()) {
                    $country = Mage::getStoreConfig('shipping/origin/country_id');
                    $state = Mage::getStoreConfig('shipping/origin/region_id');
                    $postcode = Mage::getStoreConfig('shipping/origin/postcode');
                    $method = Mage::getStoreConfig('payment/collectorbank_invoice/shippingmethod');
                    $shippingAddress
                        ->setCountryId($country)
                        ->setRegionId($state)
                        ->setPostcode($postcode)
                        ->setShippingMethod($method)
                        ->setCollectShippingRates(true);
                    $shippingAddress->save();
                    $saveQuote = true;
                }
                if ($saveQuote)
                    $quote->save();
                return $this;
            }
        }
	}
	
	public function order_cancel_after(Varien_Event_Observer $observer) {
        //Exit if the order is cancelled due to use of Edit Ordre 
        if (Mage::registry('collector_order_edited')) {
            return $this;
        }
        $order = $observer->getOrder();
        if ($order->getId()) {
            if (in_array($order->getPayment()->getMethodInstance()->getCode(), array('collectorpay','collectorbank_invoice'))) {
                $collector = $order->getPayment()->getMethodInstance();
                $collector->cancel($order->getPayment());
            }
        }
        return $this;
    }

    // Embedded ERP integration
    public function embeddedERPOrderpreparationBeforeCaptureInvoice(Varien_Event_Observer $observer) {
        $invoice = $observer->getEvent()->getInvoice();
        Mage::unregister('current_invoice');
        Mage::register('current_invoice', $invoice);
    }

    public function deleteOrders(){
        $order_collection = Mage::getModel('sales/order')->getCollection()->addFieldToFilter('status', array('eq' => 'pending_payment'))->addFieldToFilter('created_at', ['to' => new \Zend_Db_Expr('DATE_ADD(NOW(), INTERVAL -5 HOUR)')]);;
        foreach ($order_collection as $order){
            $method = $order->getPayment()->getMethodInstance()->getCode();
            if (strpos($method, 'collector') !== false){
                Mage::log("deleted order: " . $order->getIncrementId() . " due to not reciving notificaion callback", null, 'collector.log');
                try {
                    $order->delete();
                }
                catch (Exception $e){
                    Mage::log("could not delete order: " . $order->getIncrementId() . " " . $e->getMessage(), null, 'collector.log');
                }
            }
        }
    }

}
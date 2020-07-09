<?php
class Ecomatic_Collectorbank_IndexController extends Mage_Core_Controller_Front_Action{

    protected $ns = 'http://schemas.ecommerce.collector.se/v30/InvoiceService';
    
	public function indexAction(){
        $cart = Mage::getSingleton('checkout/cart');
        $quote = $cart->getQuote();
	    if ($quote->getGrandTotal() < floatval(Mage::getStoreConfig('sales/minimum_order/amount'))){
            $this->_redirect('checkout/cart');
        }
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
	
	/* Redirection URL Action */	
	public function bsuccessAction() {
        $session = Mage::getSingleton('checkout/session');
        if ($session->getSeenSuccess() == '1'){
            $session->setSeenSuccess('0');
            return Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl());
        }
        $session->setSeenSuccess('1');
        $quote = Mage::getSingleton('checkout/cart')->getQuote();
        $order = Mage::getSingleton('sales/order');
        $order = $order->loadByIncrementId($quote->getReservedOrderId());
        $order->getSendConfirmation(null);
        $order->sendNewOrderEmail();
        $session->setLastOrderId($order->getId());
        $quote->setData('is_active', 0);
        $quote->save();
        $this->loadLayout();
        Mage::dispatchEvent('checkout_onepage_controller_success_action', array('order_ids' => array($order->getId())));
        $this->renderLayout();
        $session->clear();
    }
	
	/* Redirection URL Action */	
	public function successAction() {
        $session = Mage::getSingleton('checkout/session');
        if ($session->getSeenSuccess() == '1'){
            $session->setSeenSuccess('0');
            return Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl());
        }
        $session->setSeenSuccess('1');
        $quote = Mage::getSingleton('checkout/cart')->getQuote();
        $order = Mage::getSingleton('sales/order');
        $order = $order->loadByIncrementId($quote->getReservedOrderId());
        $order->getSendConfirmation(null);
        $order->sendNewOrderEmail();
        $session->setLastOrderId($order->getId());
        $quote->setData('is_active', 0);
        $quote->save();
        $this->loadLayout();
        Mage::dispatchEvent('checkout_onepage_controller_success_action', array('order_ids' => array($order->getId())));
        $this->renderLayout();
        $session->clear();
	}
	
	/* Notification URL Action */
	public function notificationAction(){
        if (isset($_GET['OrderNo']) && isset($_GET['InvoiceStatus'])){
            Mage::log('received on hold callback for order: ' . $_GET['OrderNo'] . " InvoiceStatus: " . $_GET['InvoiceStatus'], null, 'collector.log');
			$order = Mage::getModel('sales/order')->loadByIncrementId($_GET['OrderNo']);
			if ($order->getId()){
                Mage::log('on hold callback order exists', null, 'collector.log');
				if ($_GET['InvoiceStatus'] == "0"){
                    Mage::log('on hold callback set status 0', null, 'collector.log');
					$pending = Mage::getStoreConfig('ecomatic_collectorbank/general/pending_order_status');
					$order->setState($pending, true);
					$order->save();
				}
				else if ($_GET['InvoiceStatus'] == "1"){
                    Mage::log('on hold callback set status 1', null, 'collector.log');
					$auth = Mage::getStoreConfig('ecomatic_collectorbank/general/authorized_order_status');
					$order->setState($auth, true);
					$order->save();
				}
				else {
                    Mage::log('on hold callback set status else', null, 'collector.log');
					$denied = Mage::getStoreConfig('ecomatic_collectorbank/general/denied_order_status');
					$order->setState($denied, true);
					$order->save();
				}
                Mage::log('on hold callback set status pre loadlayout', null, 'collector.log');
				$this->loadLayout();
				$this->renderLayout();
			}
            Mage::log('on hold callback end', null, 'collector.log');
		}
		if (isset($_GET['OrderNo']) && !isset($_GET['InvoiceStatus'])){
            Mage::log('received notification callback for order: ' . $_GET['OrderNo'], null, 'collector.log');
            $quote = Mage::getModel('sales/quote')->getCollection()->addFieldToFilter('entity_id', $_GET['OrderNo'])->getFirstItem();
            $reservedOrderId = $quote->getReservedOrderId();
			$order = Mage::getModel('sales/order')->loadByIncrementId($reservedOrderId);
            if ($order->getId()){
                Mage::log('notification callback order exists', null, 'collector.log');
                $btype = $quote->getData('coll_customer_type');
                Mage::log('notification callback btype: ' . $btype, null, 'collector.log');
                $privId = $quote->getData('coll_purchase_identifier');
                Mage::log('notification callback privId: ' . $privId, null, 'collector.log');
                $resp = $this->getResp($privId, $btype);
                Mage::log('notification callback resp: ' . json_encode($resp), null, 'collector.log');
                $orderDetails = $resp['data'];
                $status = "";
                if ($orderDetails["purchase"]["result"] == "OnHold"){
                    $pending = Mage::getStoreConfig('ecomatic_collectorbank/general/pending_order_status');
                    $status = $pending;
                    $order->setState($pending, true);
                    $order->save();
                }
                else if ($orderDetails["purchase"]["result"] == "Preliminary" || $orderDetails["purchase"]["result"] == "Activated"){
                    $auth = Mage::getStoreConfig('ecomatic_collectorbank/general/authorized_order_status');
                    $status = $auth;
                    $order->setState($auth, true);
                    $order->save();
                }
                else {
                    $denied = Mage::getStoreConfig('ecomatic_collectorbank/general/denied_order_status');
                    $status = $denied;
                    $order->setState($denied, true);
                    $order->save();
                }
                $payment = $order->getPayment();
                $response = $orderDetails;
                $colpayment_method = "";
                if (array_key_exists('paymentMethod', $response['purchase'])){
                    $colpayment_method = $response['purchase']['paymentMethod'];
                }
                $colpayment_details = json_encode($response['purchase']);
                $payment->setCollPaymentMethod($colpayment_method);
                $payment->setCollPaymentDetails($colpayment_details );

                $session = Mage::getSingleton('checkout/session');
                $result['invoice_status'] = $response['status'];
                $result['invoice_no'] = $response['purchase']['purchaseIdentifier'];
                $result['total_amount'] =  $response['order']['totalAmount'];


                $payment->setAdditionalInformation(Ecomatic_Collectorbank_Model_Collectorbank_Invoice::COLLECTOR_INVOICE_NO, isset($result['invoice_no']) ? $result['invoice_no'] : '');
                $payment->setAdditionalInformation(Ecomatic_Collectorbank_Model_Collectorbank_Invoice::COLLECTOR_PAYMENT_REF, isset($result['payment_reference']) ? $result['payment_reference'] : '');
                $payment->setAdditionalInformation(Ecomatic_Collectorbank_Model_Collectorbank_Invoice::COLLECTOR_LOWEST_AMOUNT_TO_PAY, isset($result['lowest_amount_to_pay']) ? $result['lowest_amount_to_pay'] : '');
                $payment->setAdditionalInformation(Ecomatic_Collectorbank_Model_Collectorbank_Invoice::COLLECTOR_TOTAL_AMOUNT, isset($result['total_amount']) ? $result['total_amount'] : '');
                $payment->setAdditionalInformation(Ecomatic_Collectorbank_Model_Collectorbank_Invoice::COLLECTOR_DUE_DATE, isset($result['due_date']) ? $result['due_date'] : '');
                $payment->setAdditionalInformation(Ecomatic_Collectorbank_Model_Collectorbank_Invoice::COLLECTOR_AVAILABLE_RESERVATION_AMOUNT, isset($result['available_reservation_amount']) ? $result['available_reservation_amount'] : '');
                $payment->setAdditionalInformation(Ecomatic_Collectorbank_Model_Collectorbank_Invoice::COLLECTOR_INVOICE_STATUS, isset($result['invoice_status']) ? $result['invoice_status'] : '');
                if ($orderDetails['purchase']["paymentName"] == "DirectInvoice"){
                    $isBusiness = false;
                    if ($btype == 'b2b'){
                        $isBusiness = true;
                    }
                    $fee = $this->getInvoiceFee($isBusiness);
                    $payment->setAdditionalInformation(Ecomatic_Collectorbank_Model_Collectorbank_Invoice::COLLECTOR_INVOICE_FEE, $fee);
                    $payment->setAdditionalInformation(Ecomatic_Collectorbank_Model_Collectorbank_Invoice::COLLECTOR_INVOICE_FEE_TAX, $this->getInvoiceFeeTax($order, $isBusiness));
                    $payment->setAdditionalInformation(Ecomatic_Collectorbank_Model_Collectorbank_Invoice::COLLECTOR_INVOICE_FEE_TAX_INVOICED, 0);
                    $payment->setAdditionalInformation(Ecomatic_Collectorbank_Model_Collectorbank_Invoice::COLLECTOR_INVOICE_FEE_INVOICED, 0);
                    $payment->setAdditionalInformation(Ecomatic_Collectorbank_Model_Collectorbank_Invoice::COLLECTOR_INVOICE_FEE_REFUNDED, 0);
                    $payment->setAdditionalInformation(Ecomatic_Collectorbank_Model_Collectorbank_Invoice::COLLECTOR_INVOICE_FEE_INVOICE_NO, 0);
                    $payment->setAdditionalInformation(Ecomatic_Collectorbank_Model_Collectorbank_Invoice::COLLECTOR_INVOICE_FEE_DESCRIPTION, Mage::helper('collectorbank')->__('Invoice fee'));
                    $order->setFeeAmount($fee);
                    $order->setBaseFeeAmount($fee);
                    $order->setGrandTotal($order->getGrandTotal() + $fee);
                    $order->setBaseGrandTotal($order->getBaseGrandTotal() + $fee);
                    $order->save();
                }
                $payment->save();

                $order = $payment->getOrder();
                if ($order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                    $comment = Mage::helper('collectorbank')->__('Collector authorization successful');
                    $order->addStatusToHistory($status, $comment)
                        ->setIsCustomerNotified(false)
                        ->save();
                }
            }
        }
	}
	
	public function createB2BOrder($quote, $orderData, $privateId, $orderId){

        $sessionCustomer = Mage::getSingleton("customer/session");

        if($sessionCustomer->isLoggedIn()) {
            $customerLoggedIn = 1;
        } else {
            $customerLoggedIn = 0;
        }

		$session = Mage::getSingleton('checkout/session');
		
		$logFileName = 'magentoorder.log';
		
		Mage::log('----------------- START ------------------------------- ', null, $logFileName);	
		
		if(isset($orderData['error'])){
			$session->addError($orderData['error']['message']);
			$this->_redirect('checkout/cart');
			return;
		}
		$orderDetails = $orderData['data'];
		if ($orderDetails['purchase']["paymentName"] == "DirectInvoice"){
			$session->setData('use_fee', 1);
		}
		else {
			$session->setData('use_fee', 5);
		}
		$email = $orderDetails['businessCustomer']['email'];
		$mobile = $orderDetails['businessCustomer']['mobilePhoneNumber'];
		$firstName = $orderDetails['businessCustomer']['deliveryAddress']['companyName'];
		$lastName = $orderDetails['businessCustomer']['deliveryAddress']['companyName'];
		$sstreet = $orderDetails['businessCustomer']['deliveryAddress']['address'];
		if ($orderDetails['businessCustomer']['deliveryAddress']['address'] == ''){
			$sstreet = $orderDetails['businessCustomer']['deliveryAddress']['city'];
		}
        $bstreet = $orderDetails['businessCustomer']['invoiceAddress']['address'];
        if ($orderDetails['businessCustomer']['invoiceAddress']['address'] == ''){
            $bstreet = $orderDetails['businessCustomer']['invoiceAddress']['city'];
        }
		if($orderDetails['businessCustomer']['deliveryAddress']['country'] == 'Sverige'){
			$scountry_id = "SE";
		}
		else if ($orderDetails['businessCustomer']['deliveryAddress']['country'] == 'Norge'){
			$scountry_id = "NO";
		}
		else if ($orderDetails['businessCustomer']['deliveryAddress']['country'] == 'Suomi'){
			$scountry_id = "FI";
		}
		else if ($orderDetails['businessCustomer']['deliveryAddress']['country'] == 'Deutschland'){
			$scountry_id = "DE";
		}
		else {
			$scountry_id = $orderDetails['businessCustomer']['countryCode'];
		}
		if($orderDetails['businessCustomer']['invoiceAddress']['country'] == 'Sverige'){
			$bcountry_id = "SE";
		}
		else if ($orderDetails['businessCustomer']['invoiceAddress']['country'] == 'Norge'){
			$bcountry_id = "NO";
		}
		else if ($orderDetails['businessCustomer']['invoiceAddress']['country'] == 'Suomi'){
			$bcountry_id = "FI";
		}
		else if ($orderDetails['businessCustomer']['invoiceAddress']['country'] == 'Deutschland'){
			$bcountry_id = "DE";
		}
		else {
			$bcountry_id = $orderDetails['businessCustomer']['countryCode'];
		}
		$billingAddress = array(
			'customer_address_id' => '',
			'prefix' => '',
			'firstname' => $firstName,
			'middlename' => '',
			'lastname' => $lastName,
			'suffix' => '',
			'company' => $orderDetails['businessCustomer']['invoiceAddress']['companyName'], 
			'street' => $bstreet,
			'city' => $orderDetails['businessCustomer']['invoiceAddress']['city'],
			'country_id' => $bcountry_id, // two letters country code
			'region' => '', // can be empty '' if no region
			'region_id' => '', // can be empty '' if no region_id
			'postcode' => $orderDetails['businessCustomer']['invoiceAddress']['postalCode'],
			'telephone' => $mobile,
			'fax' => '',
			'save_in_address_book' => 1,
            'email' => $email
		);
		$shippingAddress = array(
			'customer_address_id' => '',
			'prefix' => '',
			'firstname' => $firstName,
			'middlename' => '',
			'lastname' => $lastName,
			'suffix' => '',
			'company' => $orderDetails['businessCustomer']['deliveryAddress']['companyName'], 
			'street' => $sstreet,
			'city' => $orderDetails['businessCustomer']['deliveryAddress']['city'],
			'country_id' => $scountry_id, // two letters country code
			'region' => '', // can be empty '' if no region
			'region_id' => '', // can be empty '' if no region_id
			'postcode' => $orderDetails['businessCustomer']['deliveryAddress']['postalCode'],
			'telephone' => $mobile,
			'fax' => '',
			'save_in_address_book' => 1,
            'email' => $email
		);
		
		$store = Mage::app()->getStore();
		$website = Mage::app()->getWebsite();
		$customer = Mage::getModel('customer/customer')->setWebsiteId($website->getId())->loadByEmail($email);
        $createAccount = Mage::getModel('collectorbank/config')->getRegisterCustomer();
		// if the customer is not already registered
        if (!$customer->getId() && $createAccount) {
			$customer = Mage::getModel('customer/customer');			
			$customer->setWebsiteId($website->getId())
					 ->setStore($store)
					 ->setFirstname($firstName)
					 ->setLastname($lastName)
					 ->setEmail($email);  
            $password = $customer->generatePassword();
            $customer->setPassword($password);
            // set the customer as confirmed
            $customer->setForceConfirmed(true);
            // save customer
            $customer->save();
            $customer->setConfirmation(null);
            $customer->save();

            // set customer address
            $customerId = $customer->getId();
            $customAddress = Mage::getModel('customer/address');
            $customAddress->setData($billingAddress)
                          ->setCustomerId($customerId)
                          ->setIsDefaultBilling('1')
                          ->setIsDefaultShipping('1')
                          ->setSaveInAddressBook('1');

            // save customer address
            $customAddress->save();
            // send new account email to customer

            $storeId = $customer->getSendemailStoreId();
            $customer->sendNewAccountEmail('registered', '', $storeId);

            Mage::log('Customer with email '.$email.' is successfully created.', null, $logFileName);
		}
        if ($createAccount) {
            $quote->assignCustomer($customer);
        }
		
		$billingAddressData = $quote->getBillingAddress()->addData($billingAddress);
		$shippingAddressData = $quote->getShippingAddress()->addData($shippingAddress);
		$shippingAddressData->setCollectShippingRates(true)->collectShippingRates();
		$paymentMethod = 'collectorbank_invoice';
		$shippingAddressData->setPaymentMethod($paymentMethod);
		$colpayment_method = $orderDetails['purchase']['paymentMethod'];
		$colpayment_details = json_encode($orderDetails['purchase']);
		$quote->getPayment()->importData(array('method' => $paymentMethod,'coll_payment_method' => $colpayment_method,'coll_payment_details' => $colpayment_details));
        $orderReservedId = $session->getReference();
        $quote->setData('collector_response', json_encode($orderDetails));
        $quote->setCollCustomerType($orderDetails['customerType']);
        $quote->setCollBusinessCustomer($orderDetails['businessCustomer']);
        $quote->setCollStatus($orderDetails['status']);
        $quote->setCollPurchaseIdentifier($orderDetails['purchase']['purchaseIdentifier']);
        $quote->setCollTotalAmount($orderDetails['order']['totalAmount']);
        if($orderDetails['reference'] == $orderReservedId){
            $quote->setReservedOrderId($orderReservedId);
        } else {
            $quote->setReservedOrderId($orderDetails['reference']);
        }
        $quote->setCustomerEmail($email);
        if ($createAccount || $customerLoggedIn == 1){
            $quote->getBillingAddress()->setCustomerId($customer->getId());
            $quote->getShippingAddress()->setCustomerId($customer->getId());
        }
        if (!$createAccount && !$customerLoggedIn == 1){
            $quote->setCustomerId(null);
            $quote->setCustomerEmail($email);
            $quote->setCustomerIsGuest(true);
            $quote->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
            $quote->setCheckoutMethod(Mage_Sales_Model_Quote::CHECKOUT_METHOD_GUEST);
        }
        $quote->collectTotals();
        $quote->save();
        $service = Mage::getModel('sales/service_quote', $quote);
        $service->submitAll();
        $quote->setData('is_active', 1);
        $quote->save();
        $incrementId = $service->getOrder()->getRealOrderId();
        if($session->getIsSubscribed() == 1){
            Mage::getModel('newsletter/subscriber')->subscribe($email);
        }
        $session->setLastQuoteId($quote->getId())->setLastSuccessQuoteId($quote->getId())->clearHelperData();
        Mage::getSingleton('checkout/session')->setLastOrderId($service->getOrder()->getId());
        $session->unsBusinessPrivateId();
        $session->unsReference();
        Mage::log('Order created with increment id: '.$incrementId, null, $logFileName);
        $result['success'] = true;
        $result['error']   = false;
        $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
        $order->setState('pending_payment', true);
        $order->save();
        $quote->setReservedOrderId($order->getIncrementId());
        $quote->save();
        $block = Mage::app()->getLayout()->getBlock('collectorbank_success');
        if ($block){//check if block actually exists
            if ($order->getId()) {
                $orderId = $order->getId();
                $isVisible = !in_array($order->getState(),Mage::getSingleton('sales/order_config')->getInvisibleOnFrontStates());
                $block->setOrderId($incrementId);
                $block->setIsOrderVisible($isVisible);
                $block->setViewOrderId($block->getUrl('sales/order/view/', array('order_id' => $orderId)));
                $block->setViewOrderUrl($block->getUrl('sales/order/view/', array('order_id' => $orderId)));
                $block->setPrintUrl($block->getUrl('sales/order/print', array('order_id'=> $orderId)));
                $block->setCanPrintOrder($isVisible);
                $block->setCanViewOrder(Mage::getSingleton('customer/session')->isLoggedIn() && $isVisible);
            }
        }
        Mage::dispatchEvent('checkout_onepage_controller_success_action', array('order_ids' => array($order->getId())));
		Mage::log('----------------- END ------------------------------- ', null, $logFileName);
		return $order;
	}
	
	public function createB2COrder($quote, $orderData, $privateId, $orderId){
		$logFileName = 'magentoorder.log';
        $sessionCustomer = Mage::getSingleton("customer/session");

        if($sessionCustomer->isLoggedIn()) {
            $customerLoggedIn = 1;
        } else {
            $customerLoggedIn = 0;
        }
		$session = Mage::getSingleton('checkout/session');
		Mage::log('----------------- START ------------------------------- ', null, $logFileName);

		if(isset($orderData['error'])){
			$session->addError($orderData['error']['message']);
			$this->_redirect('checkout/cart');
			return;
		}
		$orderDetails = $orderData['data'];
		if ($orderDetails['purchase']["paymentName"] == "DirectInvoice"){
			$session->setData('use_fee', 1);
		}
		else {
			$session->setData('use_fee', 5);
		}

		$email = $orderDetails['customer']['email'];
		$mobile = $orderDetails['customer']['mobilePhoneNumber'];
		$firstName = $orderDetails['customer']['deliveryAddress']['firstName'];
		$lastName = $orderDetails['customer']['deliveryAddress']['lastName'];
		if($orderDetails['customer']['deliveryAddress']['country'] == 'Sverige'){
			$scountry_id = "SE";
		}
		else if ($orderDetails['customer']['deliveryAddress']['country'] == 'Norge'){
			$scountry_id = "NO";
		}
		else if ($orderDetails['customer']['deliveryAddress']['country'] == 'Suomi'){
			$scountry_id = "FI";
		}
		else if ($orderDetails['customer']['deliveryAddress']['country'] == 'Deutschland'){
			$scountry_id = "DE";
		}
		else {
			$scountry_id = $orderDetails['countryCode'];
		}
		if($orderDetails['customer']['billingAddress']['country'] == 'Sverige'){
			$bcountry_id = "SE";
		}
		else if ($orderDetails['customer']['billingAddress']['country'] == 'Norge'){
			$bcountry_id = "NO";
		}
		else if ($orderDetails['customer']['billingAddress']['country'] == 'Suomi'){
			$bcountry_id = "FI";
		}
		else if ($orderDetails['customer']['billingAddress']['country'] == 'Deutschland'){
			$bcountry_id = "DE";
		}
		else {
			$bcountry_id = $orderDetails['countryCode'];
		}
		$billingAddress = array(
			'customer_address_id' => '',
			'prefix' => '',
			'firstname' => $firstName,
			'middlename' => '',
			'lastname' => $lastName,
			'suffix' => '',
			'company' => $orderDetails['customer']['billingAddress']['coAddress'],
			'street' => $orderDetails['customer']['billingAddress']['address'],
			'city' => $orderDetails['customer']['billingAddress']['city'],
			'country_id' => $bcountry_id, // two letters country code
			'region' => '', // can be empty '' if no region
			'region_id' => '', // can be empty '' if no region_id
			'postcode' => $orderDetails['customer']['billingAddress']['postalCode'],
			'telephone' => $mobile,
			'fax' => '',
			'save_in_address_book' => 1,
            'email' => $email
		);
		$shippingAddress = array(
			'customer_address_id' => '',
			'prefix' => '',
			'firstname' => $firstName,
			'middlename' => '',
			'lastname' => $lastName,
			'suffix' => '',
			'company' => $orderDetails['customer']['deliveryAddress']['coAddress'],
			'street' => $orderDetails['customer']['deliveryAddress']['address'],
			'city' => $orderDetails['customer']['deliveryAddress']['city'],
			'country_id' => $scountry_id, // two letters country code
			'region' => '', // can be empty '' if no region
			'region_id' => '', // can be empty '' if no region_id
			'postcode' => $orderDetails['customer']['deliveryAddress']['postalCode'],
			'telephone' => $mobile,
			'fax' => '',
			'save_in_address_book' => 1,
            'email' => $email
		);

		$store = Mage::app()->getStore();
		$website = Mage::app()->getWebsite();
		$customer = Mage::getModel('customer/customer')->setWebsiteId($website->getId())->loadByEmail($email);
		$createAccount = Mage::getModel('collectorbank/config')->getRegisterCustomer();
		// if the customer is not already registered
		if (!$customer->getId() && $createAccount) {
			$customer = Mage::getModel('customer/customer');
			$customer->setWebsiteId($website->getId())->setStore($store)->setFirstname($firstName)->setLastname($lastName)->setEmail($email);
            $password = $customer->generatePassword();
            $customer->setPassword($password);
            // set the customer as confirmed
            $customer->setForceConfirmed(true);
            // save customer
            $customer->save();
            $customer->setConfirmation(null);
            $customer->save();

            // set customer address
            $customerId = $customer->getId();
            $customAddress = Mage::getModel('customer/address');
            $customAddress->setData($billingAddress)->setCustomerId($customerId)->setIsDefaultBilling('1')->setIsDefaultShipping('1')->setSaveInAddressBook('1');

            // save customer address
            $customAddress->save();
            // send new account email to customer
            $storeId = $customer->getSendemailStoreId();
            $customer->sendNewAccountEmail('registered', '', $storeId);
            Mage::log('Customer with email '.$email.' is successfully created.', null, $logFileName);
		}
		// Assign Customer To Sales Order Quote
        if ($createAccount) {
            $quote->assignCustomer($customer);
        }

		$billingAddressData = $quote->getBillingAddress()->addData($billingAddress);
		$shippingAddressData = $quote->getShippingAddress()->addData($shippingAddress);
		$shippingAddressData->setCollectShippingRates(true)->collectShippingRates();
		$shippingAddressData->save();
		$paymentMethod = 'collectorbank_invoice';
		$shippingAddressData->setPaymentMethod($paymentMethod);
		$colpayment_method = $orderDetails['purchase']['paymentMethod'];
		$colpayment_details = json_encode($orderDetails['purchase']);
		$quote->getPayment()->importData(array('method' => $paymentMethod,'coll_payment_method' => $colpayment_method,'coll_payment_details' => $colpayment_details));
        $orderReservedId = $orderId;
        $quote->setData('collector_response', json_encode($orderDetails));
        $quote->setCollCustomerType($orderDetails['customerType']);
        $quote->setCollBusinessCustomer($orderDetails['businessCustomer']);
        $quote->setCollStatus($orderDetails['status']);
        $quote->setCollPurchaseIdentifier($orderDetails['purchase']['purchaseIdentifier']);
        $quote->setCollTotalAmount($orderDetails['order']['totalAmount']);
        if($orderDetails['reference'] == $orderReservedId){
            $quote->setReservedOrderId($orderReservedId);
        } else {
            $quote->setReservedOrderId($orderDetails['reference']);
        }
        $quote->collectTotals();
        $quote->save();
        if ($createAccount || $customerLoggedIn == 1){
            $quote->getBillingAddress()->setCustomerId($customer->getId());
            $quote->getShippingAddress()->setCustomerId($customer->getId());
        }
        if (!$createAccount && !$customerLoggedIn == 1){
            $quote->setCustomerId(null);
            $quote->setCustomerEmail($email);
            $quote->setCustomerIsGuest(true);
            $quote->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
            $quote->setCheckoutMethod(Mage_Sales_Model_Quote::CHECKOUT_METHOD_GUEST);
        }
        $service = Mage::getModel('sales/service_quote', $quote);
        $service->submitAll();
        $incrementId = $service->getOrder()->getRealOrderId();
        Mage::getSingleton('checkout/session')->setLastOrderId($service->getOrder()->getId());
        $quote->setData('is_active', 1);
        $quote->save();

        Mage::log('Order created with increment id: '.$incrementId, null, $logFileName);
        $result['success'] = true;
        $result['error']   = false;

        $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
        $order->setData('is_iframe', 1);
        $order->setState('pending_payment', true);
        $order->save();
        $quote->setReservedOrderId($order->getIncrementId());
        $quote->save();
        $block = Mage::app()->getLayout()->getBlock('collectorbank_success');
        if ($block){//check if block actually exists
                if ($order->getId()) {
                    $orderId = $order->getId();
                    $isVisible = !in_array($order->getState(),Mage::getSingleton('sales/order_config')->getInvisibleOnFrontStates());
                    $block->setOrderId($incrementId);
                    $block->setIsOrderVisible($isVisible);
                    $block->setViewOrderId($block->getUrl('sales/order/view/', array('order_id' => $orderId)));
                    $block->setViewOrderUrl($block->getUrl('sales/order/view/', array('order_id' => $orderId)));
                    $block->setPrintUrl($block->getUrl('sales/order/print', array('order_id'=> $orderId)));
                    $block->setCanPrintOrder($isVisible);
                    $block->setCanViewOrder(Mage::getSingleton('customer/session')->isLoggedIn() && $isVisible);
                }
        }
        Mage::dispatchEvent('checkout_onepage_controller_success_action', array('order_ids' => array($order->getId())));
		Mage::log('----------------- END ------------------------------- ', null, $logFileName);
        return $order;
	}

	public function getResp($privId, $btype){
	    $logFile = "collector.log";
        Mage::log("Retrieving information from collector for private id: " . $privId, null, $logFile);
		$init = Mage::getModel('collectorbank/config')->getInitializeUrl();
		if($privId){
			if(isset($btype)){
				if($btype == 'b2b'){
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
					
			$path = '/merchants/'.$pstoreId.'/checkouts/'.$privId;
			$hash = $pusername.":".hash("sha256",$path.$psharedSecret);
			$hashstr = 'SharedKey '.base64_encode($hash);
			
			Mage::log('REQUEST >>> Private id is '.$privId .' with shared key --> '.$hashstr, null,'magentoorder.log');
            Mage::log('REQUEST >>> Private id is '.$privId .' with shared key --> '.$hashstr, null,$logFile);

			$ch = curl_init($init.$path);
			curl_setopt($ch, CURLOPT_HTTPGET, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization:'.$hashstr));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

			$output = curl_exec($ch);
			Mage::log('RESPONSE >>> '.$output, null,'magentoorder.log');
            Mage::log('RESPONSE >>> '.$output, null,$logFile);
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
		else {
		    return false;
        }
	}

	public function validationAction(){
	    $order = null;
        $quote = Mage::getModel('sales/quote')->getCollection()->addFieldToFilter('entity_id', $_GET['OrderNo'])->getFirstItem();
        $reservedOrderId = $quote->getReservedOrderId();
	    $logFile = "collector.log";
        Mage::log("Received validation callback for order: " . $reservedOrderId, null, $logFile);
        $order = Mage::getModel('sales/order')->loadByIncrementId($reservedOrderId);
        if ($order->getId()){
            Mage::log("Removing old order: " . $reservedOrderId, null, $logFile);
            $tmpRegistry = Mage::registry('isSecureArea');
            if ($tmpRegistry != 1){
                Mage::unregister('isSecureArea');
                Mage::register('isSecureArea', 1);
            }
            foreach ($order->getItemsCollection() as $item)
            {
                $productId  = $item->getProductId();
                $qty = (int)$item->getQtyOrdered();
                $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
                $product_qty_before = (int)$stockItem->getQty();
                $product_qty_after = (int)($product_qty_before + $qty);
                $stockItem->setQty($product_qty_after);
                if($product_qty_after > 0) {
                    $stockItem->setIsInStock(1);
                }
                else{
                    $stockItem->setIsInStock(0);
                }
                $stockItem->save();
            }
            $order->addStatusHistoryComment('Order was canceled, new order was created');
            $order->setState('canceled', true);
            $order->save();
            $order->cancel();
            Mage::unregister('isSecureArea');
            Mage::register('isSecureArea', $tmpRegistry);
        }
        try {
            if ($quote->getId()) {
                Mage::log("Quote exists for order: " . $reservedOrderId, null, $logFile);
                $quote->setData('is_iframe', 1);
                $quote->save();
                $btype = $quote->getData('coll_customer_type');
                $privId = $quote->getData('coll_purchase_identifier');
                $resp = $this->getResp($privId, $btype);
                if ($resp !== false) {
                    if ($btype == 'b2b') {
                        Mage::log("B2B for order: " . $reservedOrderId, null, $logFile);
                        $order = $this->createB2BOrder($quote, $resp, $privId, $reservedOrderId);
                    } else {
                        Mage::log("B2C for order: " . $reservedOrderId, null, $logFile);
                        $order = $this->createB2COrder($quote, $resp, $privId, $reservedOrderId);
                    }
                }
                else {
                    Mage::log("Could not place order: " . $reservedOrderId . " quote does not have a private id", null, $logFile);
                    $return = array(
                        'title' => $this->__("Could not place Order"),
                        'message' => $this->__("Your Session Has Expired")
                    );
                    return $this->getResponse()
                        ->clearHeaders()
                        ->setHeader('Content-type', 'application/json', true)
                        ->setHeader('HTTP/1.0', 500, true)
                        ->setBody(json_encode($return));
                }
                $quote->setData('coll_customer_type', $btype);
                $quote->setData('coll_purchase_identifier', $privId);
                $quote->save();
            }
            else {
                Mage::log("Could not place order: " . $reservedOrderId . " quote does not exist", null, $logFile);
                $return = array(
                    'title' => $this->__("Could not place Order"),
                    'message' => $this->__("Your Session Has Expired")
                );
                return $this->getResponse()
                    ->clearHeaders()
                    ->setHeader('Content-type', 'application/json', true)
                    ->setHeader('HTTP/1.0', 500, true)
                    ->setBody(json_encode($return));
            }
           // $order = Mage::getModel('sales/order')->loadByIncrementId($_GET['OrderNo']);
            Mage::log("order get increment id: " . $order->getIncrementId(), null, $logFile);
            if ($order->getIncrementId() == null){
                Mage::log("Order does not have an ID: " . $reservedOrderId, null, $logFile);
                $return = array(
                    'title' => $this->__("Could not place Order"),
                    'message' => $this->__("Please Try again")
                );
                return $this->getResponse()
                    ->clearHeaders()
                    ->setHeader('Content-type', 'application/json', true)
                    ->setHeader('HTTP/1.0', 500, true)
                    ->setBody(json_encode($return));
            }
            else {
                Mage::log("Order: " . $order->getIncrementId() . " was created successfully", null, $logFile);
                $return = array(
                    'orderReference' => $order->getIncrementId()
                );
                return $this->getResponse()
                    ->clearHeaders()
                    ->setHeader('Content-type', 'application/json', true)
                    ->setHeader('HTTP/1.0', 200, true)
                    ->setBody(json_encode($return));
            }
        }
        catch (Exception $e){
            Mage::log($e->getMessage(), null, $logFile);
            $return = array(
                'title' => $this->__("Could not place Order"),
                'message' => $e->getMessage()
            );
            return $this->getResponse()
                ->clearHeaders()
                ->setHeader('Content-type', 'application/json', true)
                ->setHeader('HTTP/1.0', 500, true)
                ->setBody(json_encode($return));
        }
    }

    public function getInvoiceFeeTax($order, $isBusiness)
    {
        $store = $order->getStore();
        $custTaxClassId = $order->getCustomerTaxClassId();

        $taxCalculationModel = Mage::getSingleton('tax/calculation');
        /* @var $taxCalculationModel Mage_Tax_Model_Calculation */
        $request = $taxCalculationModel->getRateRequest($order->getShippingAddress(), $order->getBillingAddress(), $custTaxClassId, $store);
        $shippingTaxClass = Mage::helper('collectorbank/invoiceservice')->getModuleConfig('invoice/invoice_fee_tax_class');

        $feeTax = 0;

        if ($shippingTaxClass) {
            if ($rate = $taxCalculationModel->getRate($request->setProductClassId($shippingTaxClass))) {
                $feeTax = ($this->getInvoiceFee($isBusiness) / ($rate + 100)) * $rate;
                $feeTax = $store->roundPrice($feeTax);
            }
        }

        return $feeTax;
    }

    public function getInvoiceFee($isBuisiness)
    {
//		$paymentInfo = $this->getInfoInstance();
//		$payment = $paymentInfo->getOrder()->getPayment();
//		$purchaseData = json_decode($payment->getCollPaymentDetails(),true);
        if ($isBuisiness) {
            return Mage::helper('collectorbank/invoiceservice')->getModuleConfig('invoice/invoice_fee_company');
        }

        return Mage::helper('collectorbank/invoiceservice')->getModuleConfig('invoice/invoice_fee');
    }
}

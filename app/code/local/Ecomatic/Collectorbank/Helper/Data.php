<?php

class Ecomatic_Collectorbank_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function getModuleConfig($path, $store_id = null){
		return Mage::getStoreConfig('ecomatic_collectorbank/'.$path, $store_id);
    }

    public function getCountryCode($storeId = null){
        return Mage::getStoreConfig('general/country/default', $storeId);
    }

    public function log($msg){
        $pattern = '/(<ns1\:Password>.+?)+(<\/ns1\:Password>)/i';
        $msg = preg_replace($pattern, '<ns1:Password>********</ns1:Password>', $msg);

        $pattern = '/(<ns1\:RegNo>.+?)+(<\/ns1\:RegNo>)/i';
        $msg = preg_replace($pattern, '<ns1:RegNo>***********</ns1:RegNo>', $msg);

        Mage::log($msg, Zend_Log::DEBUG, 'ecomatic_collector_exception.log');
    }

    public function logException($e){
        Mage::log("\n".$e->__toString(), Zend_Log::ERR, 'ecomatic_collector_exception.log');
    }

    public function getLogos(){
        return array(
            'black.png' => 'Black',
        );
    }

    public function cutStringAt($string, $limit, $endingDots = true){
        if ($endingDots) {
            $dots = '...';
            $limit = $limit - strlen($dots);
        }
        if (strlen($string) > $limit) {
            $string = substr($string, 0, $limit);
            $stringRev = strrev($string);
            $pos = strpos($stringRev, ' ');
            $stringRev = substr($stringRev, $pos);
            $string = strrev($stringRev).$dots;
        }

        return $string;
    }

    public function isFirstInvoice($order, $invoice){
        $orderInvoice = $order->getInvoiceCollection()->getFirstItem();
        if ($orderInvoice->getId() and $orderInvoice->getId() == $invoice->getId()) {
            return true;
        }

        return false;
    }

	public function getPaymentConfig($path, $method, $store_id = null) {
        return Mage::getStoreConfig('payment/'.$method.'/'.$path, $store_id);
    }
	
	public function getStoreId(){
		return Mage::getStoreConfig('ecomatic_collectorbank/general/store_id_b2c', null);
	}
	
	public function getStoreIdCompany(){
		return Mage::getStoreConfig('ecomatic_collectorbank/general/store_id_b2b', null);
	}
	
	public function getUsername($storeid){
		return Mage::getStoreConfig('ecomatic_collectorbank/general/username', $storeid);
	}
	
	public function getPassword($storeid){
		return Mage::helper('core')->decrypt(Mage::getStoreConfig('ecomatic_collectorbank/general/password_iframe', $storeid));
	}

	public function exceptionHandler(Exception $e, $client) {
        $this->log('Request:');
        $this->log($client->__getLastRequest());
        $this->log('Response:');
        $this->log($client->__getLastResponse());
        $this->logException($e);
    }
	
	public function getSoapClient($trace = false, $headers = array()) {
        if ($this->getModuleConfig('general/sandbox_mode')) {
            $client = new SoapClient("https://ecommercetest.collector.se/v3.0/InvoiceServiceV33.svc?singleWsdl", array('trace' => $trace, 'location' => 'https://ecommercetest.collector.se/v3.0/InvoiceServiceV33.svc?singleWsdl'));
        }
        else {
            $client = new SoapClient("https://ecommerce.collector.se/v3.0/InvoiceServiceV33.svc?singleWsdl", array('trace' => $trace, 'location' => 'https://ecommerce.collector.se/v3.0/InvoiceServiceV33.svc?singleWsdl'));
        }
        return $client;
    }
	
	public function getInformationSoapClient($trace = false, $headers = array()) {
        if ($this->getModuleConfig('general/sandbox_mode')) {
            $client = new SoapClient("https://ecommercetest.collector.se/v3.0/InformationService.svc?singleWsdl", array('trace' => $trace, 'location' => 'https://ecommercetest.collector.se/v3.0/InformationService.svc?singleWsdl'));
        }
        else {
            $client = new SoapClient("https://ecommerce.collector.se/v3.0/InformationService.svc?singleWsdl", array('trace' => $trace, 'location' => 'https://ecommerce.collector.se/v3.0/InformationService.svc?singleWsdl'));
        }
        return $client;
    }
	
	public function prepareResponse($response) {
        $result = array();
        $result['error'] = false;
        if (is_object($response)) {
            if(isset($response->PaymentReference)) {
                $result['payment_reference'] = $response->PaymentReference;
            }
            if(isset($response->AvailableReservationAmount)) {
                $result['available_reservation_amount'] = $response->AvailableReservationAmount;
            }
            if(isset($response->LowestAmountToPay)) {
                $result['lowest_amount_to_pay'] = $response->LowestAmountToPay;
            }
            if(isset($response->TotalAmount)) {
                $result['total_amount'] = $response->TotalAmount;
            }
            if(isset($response->InvoiceNo)) {
                $result['invoice_no'] = $response->InvoiceNo;
            }
            if(isset($response->InvoiceStatus)) {
                $result['invoice_status'] = $response->InvoiceStatus;
            }
            if(isset($response->DueDate)) {
                $result['due_date'] = $response->DueDate;
            }
            if(isset($response->InvoiceUrl)) {
                $result['invoice_url'] = $response->InvoiceUrl;
            }
            if(isset($response->NewInvoiceNo)) {
                $result['new_invoice_no'] = $response->NewInvoiceNo;
            }
        }
        else {
        	$result['error'] = true;
        	$result['error_message'] = 'Response is not an object.';
        }
        return $result;
    }
	
	public function getAddInvoiceRequest(Mage_Sales_Model_Order $order, $additionalData = false) {
        if($order->getPayment()->getMethodInstance()->getCode() == 'collector_partpayment') {
            $campaignId = $additionalData->getData($additionalData->getData('method') . '_campaign_id');
            $invoiceType = $order->getPayment()->getMethodInstance()->getInvoiceType($additionalData->getData($additionalData->getData('method') . '_campaign_id'));
        }
        else {
            $invoiceType = $order->getPayment()->getMethodInstance()->getInvoiceType();
        }

        $request = array(
            'StoreId' => $this->getStoreId(),
            'CorrelationId' => $order->getId(),
            'CountryCode' => $this->getCountryCode(),
            'RegNo' => $additionalData->getData($additionalData->getData('method') . '_regno'),
            'ClientIpAddress' => Mage::helper('core/http')->getRemoteAddr(),
            'Currency' => $order->getOrderCurrency()->getCurrencyCode(),
            'CustomerNo' => $order->getCustomerId(),
            'OrderNo' => $order->getIncrementId(),
            'OrderDate' => date('Y-m-d', strtotime($order->getCreatedAt())),
            'InvoiceType' => $invoiceType,
            'ActivationOption' => $this->getActivationOption(),
            'Reference' => $additionalData->getData('collector_reference')
                                            ?$additionalData->getData('collector_reference')
                                            :null,
            'CostCenter' => $additionalData->getData('collector_cost_center')
                                            ?$additionalData->getData('collector_cost_center')
                                            :null,
            'Gender' => $order->getCustomer()->getCollectorGender(),
            'InvoiceDeliveryMethod' => $order->getPayment()->getMethodInstance()->getDeliveryMethod(),
            'PurchaseType' => 1,
            'SalesPerson' => null,
        );
        $_customerType = $this->guessCustomerType($order->getBillingAddress());
        if($_customerType == "company") {
            unset($request['Gender']);
            $request['StoreId'] = $this->getStoreIdCompany();

            $_referenceType = $this->getReferenceField();
            if($_referenceType == 'no_reference') {
                $request['Reference'] = '';
            }
            elseif($_referenceType == 'firstname_lastname') {
                $_address = $order->getBillingAddress();
                $request['Reference'] = $_address->getFirstname()." ".$_address->getLastname();
            }
            elseif($_referenceType == 'custom') {
                $request['Reference'] = $order->getBillingAddress()->getData($this->getCustomReference());
            }
        }
        if (!isset($request['StoreId']) || !$request['StoreId']) {
            unset($request['StoreId']);
        }

        $request['InvoiceRows'] = array();
        if (count($order->getAllItems())) {
            $bundlesWithFixedPrice = array();
            foreach ($order->getAllItems() as $item) {
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

                $request['InvoiceRows'][] = $this->invoiceRow($item);
            }
        }
        $request['InvoiceRows'][] = $this->invoiceRowShipping($order);
        
        if ($additionalData->getData('method') == 'collector_invoice') {
            $request['InvoiceRows'][] = $this->invoiceRowFee($order);
        }
        
        $giftcards = unserialize($order->getGiftCards());
        if (is_array($giftcards)) {
            if (count($giftcards)) {
                foreach($giftcards as $giftcard) {
                    $request['InvoiceRows'][] = $this->invoiceRowCredit($giftcard['c'], $this->__('Gift Card') . ': ' .$giftcard['c'], $giftcard['authorized']);
                }
            }
        }
        
        if ($order->getData('base_customer_balance_amount') > 0) {
            $storeCreditAmount = $order->getData('base_customer_balance_amount');
            $request['InvoiceRows'][] = $this->invoiceRowCredit('STORE_CREDIT', $this->__('Store Credit'), $storeCreditAmount);
        }
        
        if ($order->getData('base_reward_currency_amount') > 0) {
            $rewardAmount = $order->getData('base_reward_currency_amount');
            $request['InvoiceRows'][] = $this->invoiceRowCredit('REWARD_POINT', $this->__('Reward Points'), $rewardAmount);
        }

        $request['InvoiceAddress'] = null;
        if ($order->getBillingAddress()) {
            $billingAddress = $order->getBillingAddress();
            if ($this->isCellPhoneNumber($billingAddress->getTelephone())) {
                $billingAddress->setCellPhoneNumber($billingAddress->getTelephone());
            }
            $request['InvoiceAddress'] = $this->address($order->getBillingAddress());
            
            if (!$request['InvoiceAddress']['Email']) {
                $request['InvoiceAddress']['Email'] = $order->getCustomerEmail();
            }

            if (($_customerType != 'company' && !$order->getPayment()->getMethodInstance()->getConfigData('separate_address')) ||
                ($_customerType == 'company' && !$order->getPayment()->getMethodInstance()->getConfigData('separate_address_company'))) {
                $shippingAddress = $order->getShippingAddress();
                $shippingAddress->setFirstname($billingAddress->getFirstname());
                $shippingAddress->setLastname($billingAddress->getLastname());
                $shippingAddress->setCompany($billingAddress->getCompany());
                $shippingAddress->setStreetFull($billingAddress->getStreetFull());
                $shippingAddress->setPostcode($billingAddress->getPostcode());
                $shippingAddress->setCity($billingAddress->getCity());
                $shippingAddress->setTelephone($billingAddress->getTelephone());
                $shippingAddress->setEmail($billingAddress->getEmail());
                $shippingAddress->setCountryId($billingAddress->getCountryId());
                
                $shippingAddress->save();
            }
        }
        $request['DeliveryAddress'] = null;
        if ($order->getShippingAddress()) {
            $shippingAddress = $order->getShippingAddress();
            if ($this->isCellPhoneNumber($shippingAddress->getTelephone())) {
                $shippingAddress->setCellPhoneNumber($shippingAddress->getTelephone());
            }
            $request['DeliveryAddress'] = $this->address($order->getShippingAddress());
            
            if (!$request['DeliveryAddress']['Email']) {
                $request['DeliveryAddress']['Email'] = $order->getCustomerEmail();
            }
        }
        $request['ProductCode'] = null;
		
        $chosenCampaign = $additionalData->getData($additionalData->getData('method').'_campaign_id');
        if($chosenCampaign) {
            $ids = array();
            if($chosenCampaign && $_customerType == 'company') {
                $campaigns = unserialize($order->getPayment()->getMethodInstance()->getConfigData('campaign_list_company'));
                foreach($campaigns as $_camp) {
                    $ids[] = $_camp['value'];
                }
            }
            else {
                $campaigns = unserialize($order->getPayment()->getMethodInstance()->getConfigData('campaign_list'));
                foreach($campaigns as $_camp) {
                    $ids[] = $_camp['value'];
                }   
            }
            if(in_array($chosenCampaign, $ids)) {
                $request['ProductCode'] = $additionalData->getData($additionalData->getData('method') . '_campaign_id');
            }
        }

        if ($additionalData->getData($additionalData->getData('method') . '_campaign_id') && $_customerType != 'company') {
            $request['ProductCode'] = $additionalData->getData($additionalData->getData('method') . '_campaign_id');
        }
        return $request;
    }
	
	public function invoiceRow(Mage_Sales_Model_Order_Item $item) {
        $priceInclTaxAndDiscount = $item->getBasePriceInclTax();
        if ($item->getBaseDiscountAmount()) {
            $discountAmount = $item->getBaseDiscountAmount() / $item->getQtyOrdered();
            $priceInclTaxAndDiscount = $item->getBasePriceInclTax() - $discountAmount;
        }

        $unitPrice = sprintf("%01.2f", $priceInclTaxAndDiscount);
        $vat = sprintf("%01.2f", $item->getTaxPercent());
        
        if ($item->getParentItemId()) {
            $parentItem = $item->getParentItem();
            if ($parentItem->getProductType() == 'configurable') {
                $parentPriceInclTaxAndDiscount = $parentItem->getBasePriceInclTax();
                if ($item->getBaseDiscountAmount()) {
                    $discountAmount = $parentItem->getBaseDiscountAmount() / $parentItem->getQtyOrdered();
                    $parentPriceInclTaxAndDiscount = $parentItem->getBasePriceInclTax() - $discountAmount;
                }

                $unitPrice = sprintf("%01.2f", $parentPriceInclTaxAndDiscount);
                $vat = sprintf("%01.2f", $parentItem->getTaxPercent());
            }
        }
        
        $articleId = (strlen($item->getSku()) > 50) ? $item->getItemId() : $item->getSku();
        
        return array(
            'ArticleId' => $articleId,
            'Description' => $this->cutStringAt($item->getName(), 50),
            'Quantity' => $item->getQtyOrdered(),
            'UnitPrice' => $unitPrice,
            'VAT' => $vat,
        );
    }

    public function invoiceRowShipping($order) {
        return array(
            'ArticleId' => 'SHIPPING',
            'Description' => $this->cutStringAt($order->getShippingDescription(), 50),
            'Quantity' => 1,
            'UnitPrice' => sprintf("%01.2f", $order->getBaseShippingInclTax()),
            'VAT' => sprintf("%01.2f", $order->getBaseShippingTaxAmount() / $order->getBaseShippingAmount() * 100),
        );
    }
    
    public function invoiceRowFee($order) {
        $paymentMethod = $order->getPayment()->getMethodInstance();
        
        return array(
            'ArticleId' => 'INVOICE_FEE',
            'Description' => $this->cutStringAt($this->__('Invoice fee'), 50),
            'Quantity' => 1,
            'UnitPrice' => sprintf("%01.2f", $paymentMethod->getInvoiceFee()),
            'VAT' => sprintf("%01.2f", $paymentMethod->getInvoiceFeeTaxPercent($order)),
        );
    }
    
    public function invoiceRowCredit($code, $desc, $value) {
        return array(
            'ArticleId' => $code,
            'Description' => $this->cutStringAt($desc, 50),
            'Quantity' => 1,
            'UnitPrice' => sprintf("%01.2f", ($value * -1)),
            'VAT' => sprintf("%01.2f", 0),
        );
    }

    public function guessCustomerType(Mage_Customer_Model_Address_Abstract $address) {
        if($address->getCompany()) {
            return "company";
        }
        return "private";
    }

    public function address(Mage_Customer_Model_Address_Abstract $address) {
        // For company customers
        if($address->getCompany()) {
            return array(
                'CompanyName' => $address->getCompany(),
                'Address1' => $address->getStreetFull(),
                //'Address2' => ,
                //'COAddress' => ,
                'PostalCode' => $address->getPostcode(),
                'City' => $address->getCity(),
                //'PhoneNumber' => $address->getTelephone(),
                'CellPhoneNumber' => $address->hasCellPhoneNumber() ? $address->getCellPhoneNumber() : null,
                'Email' => $address->getEmail(),
                'CountryCode' => $address->getCountryId(),
            );
        }

        return array(
            'Firstname' => $address->getFirstname(),
            'Lastname' => $address->getLastname(),
            'Address1' => $address->getStreetFull(),
            //'Address2' => ,
            //'COAddress' => ,
            'PostalCode' => $address->getPostcode(),
            'City' => $address->getCity(),
            //'PhoneNumber' => $address->getTelephone(),
            'CellPhoneNumber' => $address->hasCellPhoneNumber() ? $address->getCellPhoneNumber() : null,
            'Email' => $address->getEmail(),
            'CountryCode' => $address->getCountryId(),
        );
    }

    public function articleList($item, $additionalData) {
        $qty = $item->getQty();
        $orderItem = $item->getOrderItem();
        if ($orderItem->getId() AND $orderItem->getProductType() == 'configurable') {
            $childrenItems = $orderItem->getChildrenItems();
            if (is_array($childrenItems)) {
                $child = array_pop($childrenItems);
                if ($child) {
                    //Keep parent (conf.) invoiced qty
                    $qty = $item->getQty();
                    //Swap conf. child with current conf.item
                    $item = $child;
                }
            }
        }
        
        $itemId = false;
        $quote = $additionalData['quote'];
        $colItems = json_decode($quote->getData('collector_response'))->cart->items;
        foreach ($colItems as $colItem){
            if (strpos($colItem->id, $item->getSku()) !== false){
                $itemId = $colItem->id;
            }
        }

        return array(
            'ArticleId' => $itemId,
            'Description' => $item->getName(),
            'Quantity' => $qty,
        );
    }
    
    public function articleListShipping($order) {
        return array(
            'ArticleId' => $this->cutStringAt($order->getShippingMethod(), 50),
            'Description' => $this->cutStringAt($order->getShippingDescription(), 50),
            'Quantity' => 1,
        );
    }
    
    public function articleListFee($order) {
        $feeDescription = $order->getPayment()->getAdditionalInformation('collector_invoice_fee_description');
        return array(
            'ArticleId' => 'INVOICE_FEE',
            'Description' => $this->cutStringAt($feeDescription, 50),
            'Quantity' => 1,
        );
    }

    public function getProductTypes() {
        return array(
            0 => $this->__('Invoice / Part Payment'),
            3 => $this->__('Account'),
//            0 => $this->__('Invoice will be in the package and/or directly sent with e-mail if InvoiceDeliveryMethod is set to e-mail, Collector will not send this invoice to the customer, you will send it as part of the package.'),
//            1 => $this->__('Monthly invoice. Collector will send this invoice.'),
//            2 => $this->__('Part Payment. Collector will send a part payment invoice with interest.'),
//            3 => $this->__('Aggregated invoice. Collector will send the invoice. All invoices incoming during the same month with this flag will be aggregated to one invoice.'),
        );
    }

    public function getInvoiceTypes() {
        return array(
            0 => $this->__('Invoice will be in the package and/or directly sent with e-mail if InvoiceDeliveryMethod is set to e-mail, Collector will not send this invoice to the customer, you will send it as part of the package.'),
            1 => $this->__('Monthly invoice. Collector will send this invoice.'),
        );
    }

    public function getGender() {
        return array(
            0 => $this->__('Not known'),
            1 => $this->__('Male'),
            2 => $this->__('Female'),
            9 => $this->__('Not applicable'),
        );
    }

    public function getDeliveryMethods($type) {
        $methods = array();
        if ($type == 'merchant') {
            $methods[1] = $this->__('In Package by merchant');
            $methods[3] = $this->__('In Package and e-mail');
            $methods[2] = $this->__('E-mail only');
        }
        elseif ($type == 'collector') {
            $methods[1] = $this->__('By Collector regular mail');
            $methods[3] = $this->__('By Collector regular mail and email');
        }
        return $methods;
    }
	
	public function isCustomerError($code) {
        $errorCodes = array(
            'DENIED_TO_PURCHASE',
            'CREDIT_CHECK_DENIED',
            'RESERVATION_NOT_APPROVED',
            'PURCHASE_AMOUNT_GREATER_THAN_MAX_CREDIT_AMOUNT',
            'INVALID_REGISTRATION_NUMBER',
            'AGREEMENT_RULES_VALIDATION_FAILED',
            'UNHANDLED_EXCEPTION',
            'INVALID_DELIVERY_ADDRESS_USAGE',
            'INVALID_PRODUCT_CODE',
        );

        return in_array($code, $errorCodes);
    }

    public function getActivationOptions() {
        return array(
            0 => $this->__('Standard'),
            1 => $this->__('Immediate'),
        );
    }

    public function isPartial($object) {
        $origList = array();
        if ($object instanceof Mage_Sales_Model_Order_Creditmemo) {
            foreach ($object->getInvoice()->getAllItems() as $item) {
                $origList[$item->getOrderItemId()] = $item->getQty();
            }
        }
        elseif ($object instanceof Mage_Sales_Model_Order_Invoice) {
            foreach ($object->getOrder()->getAllItems() as $item) {
                $origList[$item->getId()] = $item->getQtyOrdered();
            }
        }
       
        $objectList = $object->getAllItems();
        
        if (count($objectList) != count($origList)) {
            return true;
        }
        
        foreach ($objectList as $item) {
            if (isset($origList[$item->getOrderItemId()])) {
                if ($origList[$item->getOrderItemId()] - $item->getQty() > 0) {
                    return true;
                }
            }
        }
        return false;
    }
    
    public function isCellPhoneNumber($number) {
        if (strlen($number) == 8 AND (substr($number, 0, 1) == '9' OR substr($number, 0, 1) == '4')) {
            return true;
        }
        return false;
    }
    
    public function getPaymentReference($invoiceNo) {
        $collectorInvoice = Mage::getModel('collector/invoice')->load($invoiceNo, 'invoice_no');
        return $collectorInvoice->getPaymenReference();
    }
	
	public function getActivateRequest($payment, $storeId = null) {
        $request = array(
            'StoreId' => $this->getModuleConfig('general/store_id_b2c', $storeId) ? $this->getModuleConfig('general/store_id_b2c', $storeId) : null,
            'CorrelationId' => $payment->getOrder()->getId(),
            'CountryCode' => $this->getCountryCode($payment->getOrder()->getStoreId()),
            'InvoiceNo' => $payment->getAdditionalInformation('collector_invoice_no'),
        );
        if($this->guessCustomerType($payment->getOrder()->getBillingAddress()) == "company") {
            $request['StoreId'] = $this->getModuleConfig('general/store_id_b2b', $storeId);
        }
        // We don't want to send StoreId at all if it hasn't been set.
        if (!isset($request['StoreId']) || !$request['StoreId']) {
            unset($request['StoreId']);
        }
		return $request;
    }
	
	public function hasCredit($payment) {
        $giftCard = ($payment->getOrder()->getData('base_gift_cards_amount') > 0) ? true : false;
        $storeCredit = ($payment->getOrder()->getData('base_customer_balance_amount') > 0) ? true : false;
        $rewardPoint = ($payment->getOrder()->getData('base_reward_currency_amount') > 0) ? true : false;
        
        return ($giftCard || $storeCredit || $rewardPoint) ? true : false;
    }
	
	public function getPartActivateRequest($payment, $additionalData = false) {
		$request = array(
            'StoreId' => $this->getModuleConfig('general/store_id_b2c') ? $this->getModuleConfig('general/store_id_b2c') : null,
            'CorrelationId' => $payment->getOrder()->getId(),
            'CountryCode' => $this->getCountryCode($payment->getOrder()->getStoreId()),
            'InvoiceNo' => $payment->getAdditionalInformation('collector_invoice_no'),
        );
		if($this->guessCustomerType($payment->getOrder()->getBillingAddress()) == "company") {
            $request['StoreId'] = $this->getModuleConfig('general/store_id_b2b');
        }
		// We don't want to send StoreId at all if it hasn't been set.
        if (!isset($request['StoreId']) || !$request['StoreId']) {
            unset($request['StoreId']);
        }
		$request['ArticleList'] = array();
		$bundlesWithFixedPrice = array();
		if (isset($additionalData['invoice'])) {
		    $invoice = $additionalData['invoice'];
		}
		if (!is_array($invoice->getAllItems())) {
		    return 0;
		}
		if (!count($invoice->getAllItems())) {
		    return 0;
		}
		
        foreach ($invoice->getAllItems() as $item) {
		    $orderItem = $item->getOrderItem();
		    $currentProduct = Mage::getModel('catalog/product')->load($item->getProductId());
		    if ($orderItem->getParentItemId()) {
		        $parentItem = $orderItem->getParentItem();
		        if (!($parentItem AND $parentItem->getProductType() == 'bundle'
                    AND $parentItem->getPriceType() == Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC)) {
		            continue;
		        }
		    }
            elseif (in_array($orderItem->getParentItemId(), $bundlesWithFixedPrice)) {
		        //Skip bundle kids if bundle has a fixed price
                continue;
		    }
            elseif ($orderItem->getProductType() == 'bundle') {
		        if ($currentProduct->getPriceType() == Mage_Bundle_Model_Product_Price::PRICE_TYPE_FIXED) {
		            //Use bundle product, skip kids
                    $bundlesWithFixedPrice[] = $orderItem->getItemId();
		        }
                elseif ($currentProduct->getPriceType() == Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC) {
		            continue;
		        }
		    }
		    $request['ArticleList'][] = $this->articleList($item, $additionalData);
		}
		
        
        if ($payment->getOrder()->getInvoiceCollection()->count() <= 1) {
		    $request['ArticleList'][] = $this->articleListShipping($payment->getOrder());
		    if ($payment->hasAdditionalInformation('collector_invoice_fee')) {
		        $request['ArticleList'][] = $this->articleListFee($payment->getOrder());
		    }
		}
		return $request;
    }
	
	public function getPartialCreditRequest($payment, $additionalData = false) {
        $creditmemo = $payment->getCreditmemo();
        
        $request = array(
            'StoreId' => $this->getModuleConfig('general/store_id_b2c') ? $this->getModuleConfig('general/store_id_b2c') : null,
            'CorrelationId' => $creditmemo->getOrder()->getId(),
            'CountryCode' => $this->getCountryCode($creditmemo->getOrder()->getStoreId()),
            'InvoiceNo' => $creditmemo->getInvoice()->getTransactionId(),
            'CreditDate' => date('Y-m-d', Mage::getModel('core/date')->timestamp()),
        );
		
        if($this->guessCustomerType($payment->getOrder()->getBillingAddress()) == "company") {
            $request['StoreId'] = $this->getModuleConfig('general/store_id_b2b');
        }
		
        if (!isset($request['StoreId']) || !$request['StoreId']) {
            unset($request['StoreId']);
        }

        $request['ArticleList'] = array();
        $bundlesWithFixedPrice = array();
        foreach ($creditmemo->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();
            $currentProduct = Mage::getModel('catalog/product')->load($item->getProductId());
            if ($orderItem->getParentItemId()) {
                $parentItem = $orderItem->getParentItem();
                if (!($parentItem AND $parentItem->getProductType() == 'bundle'
                    AND $parentItem->getPriceType() == Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC)) {
                    
                    continue;
                }
            }
            elseif (in_array($orderItem->getParentItemId(), $bundlesWithFixedPrice)) {
                //Skip bundle kids if bundle has a fixed price
                continue;
            }
            elseif ($orderItem->getProductType() == 'bundle') {
                if ($currentProduct->getPriceType() == Mage_Bundle_Model_Product_Price::PRICE_TYPE_FIXED) {
                    //Use bundle product, skip kids
                    $bundlesWithFixedPrice[] = $orderItem->getItemId();
                }
                elseif ($currentProduct->getPriceType() == Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC) {
                    continue;
                }
            }

            $request['ArticleList'][] = $this->articleList($item, $additionalData);
        }
        
        if ($creditmemo->getBaseShippingAmount() == $payment->getBaseShippingAmount() AND $creditmemo->getBaseShippingAmount() > 0) {
            $request['ArticleList'][] = $this->articleListShipping($payment->getOrder());
        }
        return $request;
    }

	public function getFullCreditRequest($payment) {
        $creditmemo = $payment->getCreditmemo();
        $request = array(
            'StoreId' => $this->getModuleConfig('general/store_id_b2c') ? $this->getModuleConfig('general/store_id_b2c') : null,
            'CorrelationId' => $creditmemo->getOrder()->getId(),
            'CountryCode' => $this->getCountryCode($creditmemo->getOrder()->getStoreId()),
            'InvoiceNo' => $creditmemo->getInvoice()->getTransactionId(),
            'CreditDate' => date('Y-m-d', Mage::getModel('core/date')->timestamp()),
        );
        if($this->guessCustomerType($payment->getOrder()->getBillingAddress()) == "company") {
            $request['StoreId'] = $this->getModuleConfig('general/store_id_b2b');
        }
        // We don't want to send StoreId at all if it hasn't been set.
        if (!isset($request['StoreId']) || !$request['StoreId']) {
            unset($request['StoreId']);
        }

        return $request;
    }
	
	public function getReplaceInvoiceRequest($payment) {
        $order = $payment->getOrder();
        
        $request = array(
            'StoreId' => $this->getModuleConfig('general/store_id_b2c') ? $this->getModuleConfig('general/store_id_b2c') : null,
            'CorrelationId' => $order->getId(),
            'CountryCode' => $this->getCountryCode($order->getStoreId()),
            'InvoiceNo' => $payment->getAdditionalInformation('collector_invoice_no'),
        );
        if($this->guessCustomerType($order->getBillingAddress()) == "company") {
            $request['StoreId'] = $this->getModuleConfig('general/store_id_b2b');
        }
        // We don't want to send StoreId at all if it hasn't been set.
        if (!isset($request['StoreId']) || !$request['StoreId']) {
            unset($request['StoreId']);
        }

        $request['InvoiceRows'] = array();
        if (count($order->getAllItems())) {
            $bundlesWithFixedPrice = array();
            foreach ($order->getAllItems() as $item) {
                if ($item->getProductType() == 'configurable') {
                    continue;
                }
                elseif (in_array($item->getParentItemId(), $bundlesWithFixedPrice)) {
                    //Skip bundle kids if bundle has a fixed price
                    continue;
                }
                elseif ($item->getProductType() == 'bundle') {
                    $product = $item->getProduct();
                    if ($product->getPriceType() == Mage_Bundle_Model_Product_Price::PRICE_TYPE_FIXED) {
                        //Use bundle product, skip kids
                        $bundlesWithFixedPrice[] = $item->getItemId();
                    }
                    elseif ($product->getPriceType() == Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC) {
                        continue;
                    }
                }

                $request['InvoiceRows'][] = $this->invoiceRow($item);
            }
        }
        
        $request['InvoiceRows'][] = $this->invoiceRowShipping($order);
        
        $feeInvoiced = $payment->getAdditionalInformation('collector_invoice_fee_invoiced');
        if ($payment->getMethodInstance()->getCode() == 'collector_invoice' AND empty($feeInvoiced)) {
            $request['InvoiceRows'][] = $this->invoiceRowFee($order);
        }
        
        $giftcards = unserialize($order->getGiftCards());
        if (count($giftcards)) {
            foreach($giftcards as $giftcard) {
                $request['InvoiceRows'][] = $this->invoiceRowCredit($giftcard['c'], $this->__('Gift Card') . ': ' .$giftcard['c'], $giftcard['authorized']);
            }
        }
        
        if ($order->getData('base_customer_balance_amount') > 0) {
            $storeCreditAmount = $order->getData('base_customer_balance_amount');
            $request['InvoiceRows'][] = $this->invoiceRowCredit('STORE_CREDIT', $this->__('Store Credit'), $storeCreditAmount);
        }
        
        if ($order->getData('base_reward_currency_amount') > 0) {
            $rewardAmount = $order->getData('base_reward_currency_amount');
            $request['InvoiceRows'][] = $this->invoiceRowCredit('REWARD_POINT', $this->__('Reward Points'), $rewardAmount);
        }

        return $request;
    }
	
	public function getCancelInvoiceRequest($payment) {
        $request = array(
            'StoreId' => $this->getModuleConfig('general/store_id_b2c') ? $this->getModuleConfig('general/store_id_b2c') : null,
            'CorrelationId' => $payment->getOrder()->getId(),
            'CountryCode' => $this->getCountryCode($payment->getOrder()->getStoreId()),
            'InvoiceNo' => $payment->getAdditionalInformation('collector_invoice_no'),
        );
        if($this->guessCustomerType($payment->getOrder()->getBillingAddress()) == "company") {
            $request['StoreId'] = $this->getModuleConfig('general/store_id_b2b');
        }
        // We don't want to send StoreId at all if it hasn't been set.
        if (!isset($request['StoreId']) || !$request['StoreId']) {
            unset($request['StoreId']);
        }

        return $request;
    }
	
} 
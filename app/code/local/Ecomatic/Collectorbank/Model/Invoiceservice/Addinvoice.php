<?php
class Ecomatic_Collectorbank_Model_Invoiceservice_Addinvoice extends Ecomatic_Collectorbank_Model_Invoiceservice_Abstract
{
    protected $_request;

    public function _construct() {
        parent::_construct();
    }

    public function setRequest(Mage_Sales_Model_Order $order, $additionalData = false) {
        if($order->getPayment()->getMethodInstance()->getCode() == 'collector_partpayment') {
            // Set invoice type based on $additionalData campaign info
            $campaignId = $additionalData->getData($additionalData->getData('method') . '_campaign_id');
            $invoiceType = $order->getPayment()->getMethodInstance()->getInvoiceType($campaignId);
        }
        else {
            $invoiceType = $order->getPayment()->getMethodInstance()->getInvoiceType();
        }

        $request = array(
            'StoreId' => $this->helper->getModuleConfig('general/store_id_private') ? $this->helper->getModuleConfig('general/store_id_private') : null,
            'CorrelationId' => $order->getId(),
            'CountryCode' => $this->helper->getCountryCode(),
            'RegNo' => $additionalData->getData($additionalData->getData('method') . '_regno'),
            'ClientIpAddress' => Mage::helper('core/http')->getRemoteAddr(),
            'Currency' => $order->getOrderCurrency()->getCurrencyCode(),
            'CustomerNo' => $order->getCustomerId(),
            'OrderNo' => $order->getIncrementId(),
            'OrderDate' => date('Y-m-d', strtotime($order->getCreatedAt())),
            'InvoiceType' => $invoiceType,
            'ActivationOption' => $this->helper->getModuleConfig('general/activation_option'),
            'Reference' => $additionalData->getData('collector_reference')
                                            ?$additionalData->getData('collector_reference')
                                            :null,
            'CostCenter' => $additionalData->getData('collector_cost_center')
                                            ?$additionalData->getData('collector_cost_center')
                                            :null,
            'Gender' => $order->getCustomer()->getCollectorGender(),
            'InvoiceDeliveryMethod' => $order->getPayment()->getMethodInstance()->getDeliveryMethod(),
            'PurchaseType' => 1, // E-Commerce
            'SalesPerson' => null,
        );
        // No gender if we're dealing with a business customer
        // Also uses a different store id value.
        $_customerType = $this->helper->guessCustomerType($order->getBillingAddress());
        if($_customerType == "company") {
            unset($request['Gender']);
            $request['StoreId'] = $this->helper->getModuleConfig('general/store_id_company');

            $_referenceType = $this->helper->getModuleConfig('invoice/reference_field');
            if($_referenceType == 'no_reference') {
                $request['Reference'] = '';
            }
            elseif($_referenceType == 'firstname_lastname') {
                $_address = $order->getBillingAddress();
                $request['Reference'] = $_address->getFirstname()." ".$_address->getLastname();
            }
            elseif($_referenceType == 'custom') {
                $request['Reference'] = $order->getBillingAddress()->getData($this->helper->getModuleConfig('invoice/custom_reference'));
            }
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

                $request['InvoiceRows'][] = $this->helper->invoiceRow($item);
            }
        }
        //Mage::helper('collectorbank')->log(print_r($request, true));
        
        $request['InvoiceRows'][] = $this->helper->invoiceRowShipping($order);
        
        if ($additionalData->getData('method') == 'collectorbank_invoice') {
            $request['InvoiceRows'][] = $this->helper->invoiceRowFee($order);
        }
        
        $giftcards = unserialize($order->getGiftCards());
        if (is_array($giftcards)) {
            if (count($giftcards)) {
                foreach($giftcards as $giftcard) {
                    $request['InvoiceRows'][] = $this->helper->invoiceRowCredit($giftcard['c'], $this->helper->__('Gift Card') . ': ' .$giftcard['c'], $giftcard['authorized']);
                }
            }
        }
        
        if ($order->getData('base_customer_balance_amount') > 0) {
            $storeCreditAmount = $order->getData('base_customer_balance_amount');
            $request['InvoiceRows'][] = $this->helper->invoiceRowCredit('STORE_CREDIT', $this->helper->__('Store Credit'), $storeCreditAmount);
        }
        
        if ($order->getData('base_reward_currency_amount') > 0) {
            $rewardAmount = $order->getData('base_reward_currency_amount');
            $request['InvoiceRows'][] = $this->helper->invoiceRowCredit('REWARD_POINT', $this->helper->__('Reward Points'), $rewardAmount);
        }

        $request['InvoiceAddress'] = null;
        if ($order->getBillingAddress()) {
            $billingAddress = $order->getBillingAddress();
            if ($this->helper->isCellPhoneNumber($billingAddress->getTelephone())) {
                $billingAddress->setCellPhoneNumber($billingAddress->getTelephone());
            }
            $request['InvoiceAddress'] = $this->helper->address($order->getBillingAddress());
            
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
            if ($this->helper->isCellPhoneNumber($shippingAddress->getTelephone())) {
                $shippingAddress->setCellPhoneNumber($shippingAddress->getTelephone());
            }
            $request['DeliveryAddress'] = $this->helper->address($order->getShippingAddress());
            
            if (!$request['DeliveryAddress']['Email']) {
                $request['DeliveryAddress']['Email'] = $order->getCustomerEmail();
            }
        }
//Product type 2 is not supported yet
//        $request['CreditTime'] = null;
//        if ($this->helper->getModuleConfig('settings/product_type')==2) {
//            $request['CreditTime'] = $this->helper->getModuleConfig('settings/credit_time');
//        }
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
        
        $this->_request = $request;
        //Mage::log(print_r($request, true));

        return $this;
    }

    public function getRequest() {
        return $this->_request;
    }

    public function addInvoice() {
        $request = $this->getRequest();

        $headers = array(
            'ClientIpAddress' => $request['ClientIpAddress'],
        );
        unset($request['ClientIpAddress']);

        $request = array('AddInvoiceRequest' => $request);

        try {
            $client = $this->createSoapClient(1, $headers);
        }
        catch (Exception $e) {
            Mage::throwException(Mage::helper('collectorbank')->__('Payment failed, please try again.'));
        }
        
        try {
            $response = $client->__soapCall('AddInvoice', $request);
        }
        catch (Exception $e) {
            $response = null;
            $this->exceptionHandler($e, $client);
            if (isset($e->faultcode)) {
                $faultCode = explode(':', $e->faultcode);
                if (is_array($faultCode) AND isset($faultCode[1]) AND $this->isCustomerError($faultCode[1])) {
                    $faultCode = $this->helper->__(isset($faultCode[1]) ? $faultCode[1] : 'An error occurred, please contact the site administrator.');
                    Mage::throwException($faultCode);
                }
            }
            Mage::throwException(Mage::helper('collectorbank')->__('Payment failed, please try again.'));
        }

        $response = $this->prepareResponse($response);

        return $response;
    }
}

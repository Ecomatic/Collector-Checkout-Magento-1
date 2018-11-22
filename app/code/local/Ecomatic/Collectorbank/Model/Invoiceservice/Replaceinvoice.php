<?php
class Ecomatic_Collectorbank_Model_Invoiceservice_Replaceinvoice extends Ecomatic_Collectorbank_Model_Invoiceservice_Abstract
{
    protected $_request;

    public function _construct() {
        parent::_construct();
    }

    public function setRequest(Mage_Sales_Model_Order $order, $additionalData = false) {
        $payment = $additionalData['payment'];
        
        $this->setStoreId($order->getStoreId());
        
        $request = array(
            'StoreId' => $this->helper->getModuleConfig('general/store_id_private') ? $this->helper->getModuleConfig('general/store_id_private') : null,
            'CorrelationId' => $order->getId(),
            'CountryCode' => $this->helper->getCountryCode($order->getStoreId()),
            'InvoiceNo' => $payment->getAdditionalInformation(Ecomatic_Collectorbank_Model_Collectorbank_Abstract::COLLECTOR_INVOICE_NO),
        );
        if($this->helper->guessCustomerType($order->getBillingAddress()) == "company") {
            $request['StoreId'] = $this->helper->getModuleConfig('general/store_id_company');
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
        
        $request['InvoiceRows'][] = $this->helper->invoiceRowShipping($order);
        
        $feeInvoiced = $payment->getAdditionalInformation(Ecomatic_Collectorbank_Model_Collectorbank_Abstract::COLLECTOR_INVOICE_FEE_INVOICED);
        if ($payment->getMethodInstance()->getCode() == 'collector_invoice' AND empty($feeInvoiced)) {
            $request['InvoiceRows'][] = $this->helper->invoiceRowFee($order);
        }
        
        $giftcards = unserialize($order->getGiftCards());
        if (count($giftcards)) {
            foreach($giftcards as $giftcard) {
                $request['InvoiceRows'][] = $this->helper->invoiceRowCredit($giftcard['c'], $this->helper->__('Gift Card') . ': ' .$giftcard['c'], $giftcard['authorized']);
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

        $this->_request = $request;

        return $this;
    }

    public function getRequest() {
        return $this->_request;
    }

    public function replaceInvoice() {
        $request = $this->getRequest();

        $headers = array(
            'ClientIpAddress' => $request['ClientIpAddress'],
        );
        unset($request['ClientIpAddress']);

        $request = array('ReplaceInvoiceRequest' => $request);

        try {
            $client = $this->createSoapClient(1, $headers);
        }
        catch (Exception $e) {
            Mage::throwException(Mage::helper('collectorbank')->__('Payment failed, please try again.'));
        }
        
        try {
            $response = $client->__soapCall('ReplaceInvoice', $request);
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

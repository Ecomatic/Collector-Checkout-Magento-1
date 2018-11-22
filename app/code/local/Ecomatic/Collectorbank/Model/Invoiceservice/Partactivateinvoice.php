<?php
class Ecomatic_Collectorbank_Model_Invoiceservice_Partactivateinvoice extends Ecomatic_Collectorbank_Model_Invoiceservice_Abstract
{
    protected $request;

    public function _construct() {
        parent::_construct();
    }

    public function setRequest($payment, $additionalData = false) {
        
        $this->setStoreId($payment->getOrder()->getStoreId());
        
        $request = array(
            'StoreId' => $this->helper->getModuleConfig('general/store_id_private') ? $this->helper->getModuleConfig('general/store_id_private') : null,
            'CorrelationId' => $payment->getOrder()->getId(),
            'CountryCode' => $this->helper->getCountryCode($payment->getOrder()->getStoreId()),
            'InvoiceNo' => $payment->getAdditionalInformation('collector_invoice_no'),
        );
        if($this->helper->guessCustomerType($payment->getOrder()->getBillingAddress()) == "company") {
            $request['StoreId'] = $this->helper->getModuleConfig('general/store_id_company');
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
            return $this;
        }
        
        if (!count($invoice->getAllItems())) {
            return $this;
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
			
            $request['ArticleList'][] = $this->helper->articleList($item);
        }
        
        if ($payment->getOrder()->getInvoiceCollection()->count() <= 1) {
            $request['ArticleList'][] = $this->helper->articleListShipping($payment->getOrder());
            if ($payment->hasAdditionalInformation('collector_invoice_fee')) {
                $request['ArticleList'][] = $this->helper->articleListFee($payment->getOrder());
            }
        }
        
        $this->request = $request;

        return $this;
    }

    public function getRequest() {
        return $this->request;
    }

    public function partActivateInvoice() {
        try {
            $client = $this->createSoapClient(1);
            $response = $client->__soapCall('PartActivateInvoice', array('PartActivateInvoiceRequest' => $this->getRequest()));
        }
        catch (Exception $e) {
            $response = null;
            $this->exceptionHandler($e, $client);
            Mage::throwException(Mage::helper('collectorbank')->__('Activation of invoice failed, please try again.'));
        }
        $response = $this->prepareResponse($response);

        return $response;
    }
}

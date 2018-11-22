<?php
class Ecomatic_Collectorbank_Model_Invoiceservice_Partcreditinvoice extends Ecomatic_Collectorbank_Model_Invoiceservice_Abstract
{
    protected $request;

    public function _construct() {
        parent::_construct();
    }

    public function setRequest($payment, $additionalData = false) {
        $creditmemo = $additionalData['creditmemo'];
        
        $this->setStoreId($creditmemo->getOrder()->getStoreId());
        
        $request = array(
            'StoreId' => $this->helper->getModuleConfig('general/store_id_private') ? $this->helper->getModuleConfig('general/store_id_private') : null,
            'CorrelationId' => $creditmemo->getOrder()->getId(),
            'CountryCode' => $this->helper->getCountryCode($creditmemo->getOrder()->getStoreId()),
            'InvoiceNo' => $creditmemo->getInvoice()->getTransactionId(),
            'CreditDate' => date('Y-m-d', Mage::getModel('core/date')->timestamp()),
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

            $request['ArticleList'][] = $this->helper->articleList($item);
        }
        
        if ($creditmemo->getBaseShippingAmount() == $payment->getBaseShippingAmount() AND $creditmemo->getBaseShippingAmount() > 0) {
            $request['ArticleList'][] = $this->helper->articleListShipping($payment->getOrder());
        }

        $this->request = $request;

        return $this;
    }

    public function getRequest() {
        return $this->request;
    }

    public function partCreditInvoice() {
        try {
            $client = $this->createSoapClient(1);
            $response = $client->__soapCall('PartCreditInvoice', array('PartCreditInvoiceRequest' => $this->getRequest()));
        }
        catch (Exception $e) {
            $this->exceptionHandler($e, $client);
            Mage::throwException(Mage::helper('collectorbank')->__('Credit memo failed: %s', $e->getMessage()));
        }
        $response = $this->prepareResponse($response);

        return $response;
    }
}

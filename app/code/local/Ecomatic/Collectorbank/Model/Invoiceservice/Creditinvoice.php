<?php
class Ecomatic_Collectorbank_Model_Invoiceservice_Creditinvoice extends Ecomatic_Collectorbank_Model_Invoiceservice_Abstract
{
    protected $request;

    public function _construct() {
        parent::_construct();
    }

    public function setRequest($payment, $additionalData = false) {
        $creditmemo = $additionalData['creditmemo'];
        $request = array(
            'StoreId' => $this->helper->getModuleConfig('settings/store_id_private') ? $this->helper->getModuleConfig('settings/store_id_private') : null,
            'CorrelationId' => $creditmemo->getOrder()->getId(),
            'CountryCode' => $this->helper->getCountryCode($creditmemo->getOrder()->getStoreId()),
            'InvoiceNo' => $creditmemo->getInvoice()->getTransactionId(),
            'CreditDate' => date('Y-m-d', Mage::getModel('core/date')->timestamp()),
        );
        if($this->helper->guessCustomerType($payment->getOrder()->getBillingAddress()) == "company") {
            $request['StoreId'] = $this->helper->getModuleConfig('settings/store_id_company');
        }
        // We don't want to send StoreId at all if it hasn't been set.
        if (!isset($request['StoreId']) || !$request['StoreId']) {
            unset($request['StoreId']);
        }
        
        $this->setStoreId($payment->getOrder()->getStoreId()); 

        $this->request = $request;

        return $this;
    }

    public function getRequest() {
        return $this->request;
    }

    public function creditInvoice() {
        try {
            $client = $this->createSoapClient(1);
            $response = $client->__soapCall('CreditInvoice', array('CreditInvoiceRequest' => $this->getRequest()));
        }
        catch (Exception $e) {
            $response = null;
            $this->exceptionHandler($e, $client);
            Mage::throwException(Mage::helper('collectorbank')->__('Credit memo failed: %s', $e->getMessage()));
        }
        $response = $this->prepareResponse($response);

        return $response;
    }
}

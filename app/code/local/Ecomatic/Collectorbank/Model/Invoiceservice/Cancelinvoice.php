<?php
class Ecomatic_Collectorbank_Model_Invoiceservice_Cancelinvoice extends Ecomatic_Collectorbank_Model_Invoiceservice_Abstract
{
    protected $request;

    public function _construct() {
        parent::_construct();
    }

    public function setRequest($payment, $additionalData = false) {
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

        $this->setStoreId($payment->getOrder()->getStoreId());

        $this->request = $request;

        return $this;
    }

    public function getRequest() {
        return $this->request;
    }

    public function cancelInvoice() {
        $request = array('CancelInvoiceRequest' => $this->getRequest());

        try {
            $client = $this->createSoapClient(1);
            $response = $client->__soapCall('CancelInvoice', $request);
        }
        catch (Exception $e) {
            $response = null;
            $this->exceptionHandler($e, $client);
            Mage::throwException(Mage::helper('collectorbank')->__('Cancel invoice failed, please try again. %s', $e->getMessage()));
        }
        $response = $this->prepareResponse($response);

        return $response;
    }
}

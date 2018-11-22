<?php
class Ecomatic_Collectorbank_Model_Informationservice_Getinvoice extends Ecomatic_Collectorbank_Model_Informationservice_Abstract
{
    protected $request;

    public function _construct() {
        parent::_construct();
    }

    public function setRequest($payment, $additionalData = false) {
        $invoice = $additionalData['invoice'];
        $request = array(
            'StoreId' => $this->helper->getModuleConfig('general/store_id_private') ? $this->helper->getModuleConfig('general/store_id_private') : null,
            'CorrelationId' => $payment->getOrder()->getId(),
            'CountryCode' => $this->helper->getCountryCode($payment->getOrder()->getStoreId()),
            'InvoiceNo' => $invoice->getTransactionId(),
            //'PaymentReference' => $this->helper->getPaymentReference($invoice->getTransactionId()),
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

    public function getInvoice() {
        $request = $this->getRequest();

        $request = array('GetCurrentInvoiceRequest' => $request);

        try {
            $client = $this->createSoapClient(1);
            $response = $client->__soapCall('GetCurrentInvoice', $request);
        }
        catch (Exception $e) {
            $response = null;
            $this->exceptionHandler($e, $client);
        }
        $response = $this->prepareResponse($response);

        return $response;
    }
}

<?php
class Ecomatic_Collectorbank_Model_Invoiceservice_Extendduedate extends Ecomatic_Collectorbank_Model_Invoiceservice_Abstract
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

    public function extendDueDate() {
        $request = $this->getRequest();

        $request = array('ExtendDueDateRequest' => $request);

        try {
            $client = $this->createSoapClient(1);
            $response = $client->__soapCall('ExtendDueDate', $request);
            return $this->prepareResponse($response);
        }
        catch (Exception $e) {
            $this->exceptionHandler($e, $client);
            return array('error' => true, 'message' => $e->getMessage());
        }
    }
}

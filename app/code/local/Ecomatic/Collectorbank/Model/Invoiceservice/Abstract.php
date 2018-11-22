<?php
class Ecomatic_Collectorbank_Model_Invoiceservice_Abstract extends Mage_Core_Model_Abstract
{
    const WSDL_FILE_PROD = 'InvoiceServiceProduction.wsdl';
    const WSDL_FILE_TEST = 'InvoiceServiceTest.wsdl';

    const ART_ID_SHIPPING = 'SHIPPING';
    const ART_ID_INVOICE_FEE = 'INVOICE_FEE';

    protected $ns = 'http://schemas.ecommerce.collector.se/v30/InvoiceService';

    protected $helper;
    protected $_storeId = null;

    public function _construct() {
        $this->helper = Mage::helper('collectorbank/invoiceservice');
    }
    
    public function setStoreId($storeId) {
        $this->_storeId = $storeId;
    }
    
    public function getStoreId() {		
        return $this->_storeId;
    }

    public function createSoapClient($trace = false, $headers = array()) {
        $wsdlPath = Mage::getModuleDir('etc', 'Ecomatic_Collectorbank') . DS . 'wsdl';

		if ($this->helper->getModuleConfig('general/sandbox_mode')) {
            $client = new SoapClient($wsdlPath . DS . self::WSDL_FILE_TEST, array('trace' => $trace));
            $client->__setLocation('https://ecommercetest.collector.se/v3.0/InvoiceServicev31.svc');
        }
        else {
            $client = new SoapClient($wsdlPath . DS . self::WSDL_FILE_PROD, array('trace' => $trace));
            $client->__setLocation('https://ecommerce.collector.se/v3.0/InvoiceServicev31.svc');
        }
		
        $headers['Username'] = $this->helper->getModuleConfig('general/username');
        $headers['Password'] = $this->helper->getModuleConfig('general/password'); 

        $headerList = array();
        foreach ($headers as $k => $v) {
            $headerList[] = new SoapHeader($this->ns, $k, $v);
        }
        $client->__setSoapHeaders($headerList);

        return $client;
    }

    public function prepareResponse($response) {
        $result = array();
        $result['error'] = false;
        if (is_object($response)) {
            if(isset($response->CorrelationId)) {
                $request = $this->getRequest();
                if (isset($request['CorrelationId']) AND $request['CorrelationId'] == $response->CorrelationId) {
                    $result['correlation_id'] = $response->CorrelationId;
                }
                else {
                    $result['error'] = true;
                    $result['error_message'] = 'Mismatch between request and response correlation id.';
                }
            }
            else {
            	$result['error'] = true;
            	$result['error_message'] = 'Missing correlation id.';
            }
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

    public function exceptionHandler(Exception $e, $client) {
        Mage::helper('collectorbank')->log('Request:');
        Mage::helper('collectorbank')->log($client->__getLastRequest());
        Mage::helper('collectorbank')->log('Response:');
        Mage::helper('collectorbank')->log($client->__getLastResponse());
        Mage::helper('collectorbank')->logException($e);
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
}

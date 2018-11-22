<?php

class Ecomatic_Collectorbank_Block_PaymentInfo extends Mage_Payment_Block_Info
{
    protected $_collectorData;
	protected $ns = 'http://schemas.ecommerce.collector.se/v30/InformationService';
    
    protected function _construct() {
        parent::_construct();
        $this->setTemplate('collectorbank/paymentinfo.phtml');
    }

    /**
     * Retrieve info model
     *
     * @return Mage_Payment_Model_Info
     */
    /*public function getInfo() {
        $info = $this->getData('info');
        if (!($info instanceof Mage_Payment_Model_Info)) {
            Mage::throwException($this->__('Cannot retrieve the payment info model object.'));
        }
        return $info;
    }*/
    
    public function getLogoUrl() {
        return $this->getSkinUrl('images' . DS . 'ecomatic' . DS . 'collectorbank' . DS . 'logos' . DS . $this->getMethod()->getLogo());
    }
    
    public function isInvoice() {
        if (Mage::app()->getRequest()->getParam('invoice_id')) {
            return true;
        }
        return false;
    }
    
    public function getInvoiceId() {
        return Mage::app()->getRequest()->getParam('invoice_id');
    }
    
    public function getInvoiceType() {
        $invoiceId = $this->getInvoiceId();
        $invoice = Mage::getModel('sales/order_invoice')->load($invoiceId);
        $order = $invoice->getOrder();
        $payment = $order->getPayment();
        return $payment->getMethodInstance()->getInvoiceType();
    }

    /**
     * Retrieve payment method model
     *
     * @return Mage_Payment_Model_Method_Abstract
     */
    public function getMethod() {
        return $this->getInfo()->getMethodInstance();
    }

    public function getCollectorInfo() {
        $helper = Mage::helper('collectorbank');
        if ($this->_collectorData) {
            return $this->_collectorData;
        }
        $data = array();
        $order = $this->getInfo()->getOrder();
        $payment = $order->getPayment();
        if ($order->getId()) {
            $orderData = $order->getPayment()->getAdditionalInformation();

            if ($this->getIsSecureMode()) {
                if ($this->isInvoice() AND $this->getInvoiceType() != 3) {
                    $invoice = Mage::getModel('sales/order_invoice')->load($this->getInvoiceId());
                    if ($invoice->getId() AND $invoice->getTransactionId()) {
                   //     $collectorData = Mage::getModel('collector/informationservice_getinvoice')->setRequest($payment, array('invoice' => $invoice))->getInvoice();
                        
						$request = array(
							'StoreId' => $helper->getModuleConfig('settings/store_id_private') ? $helper->getModuleConfig('settings/store_id_private') : null,
							'CorrelationId' => $payment->getOrder()->getIncrementId(),
							'CountryCode' => $helper->getCountryCode($payment->getOrder()->getStoreId()),
							'InvoiceNo' => $invoice->getTransactionId(),
						);
						if($helper->guessCustomerType($payment->getOrder()->getBillingAddress()) == "company") {
							$request['StoreId'] = $helper->getModuleConfig('settings/store_id_company');
						}
						if (!isset($request['StoreId']) || !$request['StoreId']) {
							unset($request['StoreId']);
						}
						$request = array('GetCurrentInvoiceRequest' => $request);
						$client = $helper->getInformationSoapClient();
						$headers = array();
						$headers['Username'] = $helper->getUsername($payment->getOrder()->getStoreId());
						$headers['Password'] = $helper->getPassword($payment->getOrder()->getStoreId());
						$headerList = array();
						foreach ($headers as $k => $v) {
							$headerList[] = new SoapHeader($this->ns, $k, $v);
						}
						$client->__setSoapHeaders($headerList);
						try {
							$response = $client->__soapCall('GetCurrentInvoice', $request);
						}
						catch (Exception $e) {
							$response = null;
							$helper->exceptionHandler($e, $client);
						}
						$collectorData = $helper->prepareResponse($response);
						
                        $data['invoice_no'] = array('label' => $this->__('Invoice No.'), 'value' => $invoice->getTransactionId());
                        $data['payment_reference'] = array('label' => $this->__('Payment Reference'), 'value' => $collectorData['payment_reference']);
                        $data['invoice_url'] = array('label' => $this->__('Invoice PDF'), 'value' => $collectorData['invoice_url']);
                        
                        //Since the returned due date is already in local time, we set the due date to GMT
                        $gmtTime = Mage::getModel('core/date')->gmtDate(null, $collectorData['due_date']);
                        $data['due_date'] = array('label' => $this->__('Due Date'), 'value' => Mage::helper('core')->formatDate($gmtTime, 'medium', false));
                    } 
                }
                else {
                    $data['invoice_no'] = array('label' => $this->__('Invoice No.'), 'value' => $orderData['collector_invoice_no']);
                }
            }
        }
        $this->_collectorData = $data;
        
        return $data;
    }

    protected function checkout() {
        return Mage::getSingleton('checkout/session');
    }


    public function getLanguageCode() {
        $quote = $this->checkout()->getQuote();
        $language = $quote->getBillingAddress()->getCountryId();
        return $language;
    }

    public function getIsSecureMode() {
        if ($this->hasIsSecureMode()) {
            return (bool)(int)$this->_getData('is_secure_mode');
        }
        if (!$payment = $this->getInfo()) {
            return true;
        }
        if (!$method = $payment->getMethodInstance()) {
            return true;
        }
        return Mage::app()->getStore($method->getStore())->isAdmin();
    }

    /**
     * Render the value as an array
     *
     * @param mixed $value
     * @param bool $escapeHtml
     * @return $array
     */
    public function getValueAsArray($value, $escapeHtml = false)
    {
        if (empty($value)) {
            return array();
        }
        if (!is_array($value)) {
            $value = array($value);
        }
        if ($escapeHtml) {
            foreach ($value as $_key => $_val) {
                $value[$_key] = $this->escapeHtml($_val);
            }
        }
        return $value;
    }
    
    public function getExtendDueDateUrl() {
        return Mage::helper('adminhtml')->getUrl('collectorbank/adminhtml_invoice/extend', array('invoice_id'=>$this->getInvoiceId()));
    }
    
    public function getResendUrl() {
        return Mage::helper('adminhtml')->getUrl('collectorbank/adminhtml_invoice/resend', array('invoice_id'=>$this->getInvoiceId()));
    }

    /**
     * Render as PDF
     * @return string
     */
    public function toPdf()
    {
        $this->setTemplate('payment/info/pdf/default.phtml');
        return $this->toHtml();
    }
}

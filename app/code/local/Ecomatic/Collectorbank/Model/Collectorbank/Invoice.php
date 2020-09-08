<?php

class Ecomatic_Collectorbank_Model_Collectorbank_Invoice extends Mage_Payment_Model_Method_Abstract {
    protected $_code = 'collectorbank_invoice';
	protected $ns = 'http://schemas.ecommerce.collector.se/v30/InvoiceService';
	protected $_infoBlockType = 'collectorbank/paymentInfo';
    protected $_formBlockType = 'collectorbank/form';

    const COLLECTOR_INVOICE_NO = 'collector_invoice_no';
    const COLLECTOR_NEW_INVOICE_NO = 'collector_new_invoice_no';
    const COLLECTOR_PAYMENT_REF = 'collector_payment_ref';
    const COLLECTOR_LOWEST_AMOUNT_TO_PAY = 'collector_lowest_amount_to_pay';
    const COLLECTOR_TOTAL_AMOUNT = 'collector_total_amount';
    const COLLECTOR_DUE_DATE = 'collector_due_date';
    const COLLECTOR_AVAILABLE_RESERVATION_AMOUNT = 'collector_available_reservation_amount';
    const COLLECTOR_INVOICE_STATUS = 'collector_invoice_status';
    const COLLECTOR_INVOICE_URL = 'collector_invoice_url';
    const COLLECTOR_INVOICE_FEE = 'collector_invoice_fee';
    const COLLECTOR_INVOICE_FEE_TAX = 'collector_invoice_fee_tax';
    const COLLECTOR_INVOICE_FEE_TAX_INVOICED = 'collector_invoice_fee_tax_invoiced';
    const COLLECTOR_INVOICE_FEE_INVOICED = 'collector_invoice_fee_invoiced';
    const COLLECTOR_INVOICE_FEE_TAX_REFUNDED = 'collector_invoice_fee_tax_refunded';
    const COLLECTOR_INVOICE_FEE_REFUNDED = 'collector_invoice_fee_refunded';
    const COLLECTOR_INVOICE_FEE_INVOICE_NO = 'collector_invoice_fee_invoice_no';
    const COLLECTOR_INVOICE_FEE_DESCRIPTION = 'collector_invoice_fee_description';

    protected $_canReviewPayment = true;
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_canSaveCc = false;
    protected $_isInitializeNeeded = false;
    protected $_useCampaign = false;
	
	public function validate(){
         $paymentInfo = $this->getInfoInstance();
         if ($paymentInfo instanceof Mage_Sales_Model_Order_Payment) {
             $billingCountry = $paymentInfo->getOrder()->getBillingAddress()->getCountryId();
         } else {
             $billingCountry = $paymentInfo->getQuote()->getBillingAddress()->getCountryId();
         }
         return $this;
    }
	
	protected function validShippingAddress() {
        $isCompany = Mage::helper('collectorbank')->guessCustomerType(Mage::getSingleton('checkout/session')->getQuote()->getBillingAddress()) == 'company';

        if($isCompany) {
            $allowSeparate = $this->getConfigData('separate_address_company');
        }
        else {
            $allowSeparate = $this->getConfigData('separate_address');
        }
        $shippingAddress = Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress();

        if((!($shippingAddress->getdata('same_as_billing') == 1) && !$allowSeparate && !$this->isAdmin()) ||
           (!$allowSeparate && $this->isAdmin() && !$this->validShippingAddressInAdmin())) {
                return false;
        }
        return true;
    }
	
    protected function validShippingAddressInAdmin() {
        return Mage::app()->getRequest()->getParam('shipping_same_as_billing') == 'on' ? true : false;
    }
	
    protected function isAdmin() {
        if(Mage::app()->getStore()->isAdmin()) {
                return true;
        }
        if(Mage::getDesign()->getArea() == 'adminhtml') {
                return true;
        }

        return false;
    }
	
	public function validateAddress() {
		$helper = Mage::helper('collectorbank');
        $isCompany = $helper->guessCustomerType(Mage::getSingleton('checkout/session')->getQuote()->getBillingAddress()) == 'company';

        if($isCompany) {
            $allowSeparate = $this->getConfigData('separate_address_company');
        }
        else {
            $allowSeparate = $this->getConfigData('separate_address');
        }
        $shippingAddress = Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress();

        if((!($shippingAddress->getdata('same_as_billing') == 1) && !$allowSeparate && !$this->isAdmin()) || (!$allowSeparate && $this->isAdmin() && !$this->validShippingAddressInAdmin())) {
            return false;
        }
        return true;
    }
	
	public function getCheckout() {
        return Mage::getSingleton('checkout/session');
    }
	
	public function addAdditionalData($data) {
        $checkoutSession = $this->getCheckout();
        if ($checkoutSession->getCollectorData()) {
            $additionalData = $checkoutSession->getCollectorData();
            if (!$additionalData instanceof Varien_Object) {
                $additionalData = new Varien_Object($data);
            }
            else {
                $additionalData->addData($data);
            }
            $checkoutSession->setCollectorData($additionalData);
        }
        else {
            $additionalData = new Varien_Object($data);
            $checkoutSession->setCollectorData($additionalData);
        }
        return $this;
    }

    public function getAdditionalData() {
        return $this->getCheckout()->getCollectorData();
    }
	
    public function assignData($data) {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $this->addAdditionalData($data->getData());
        return $this;
    }
	
	public function getLogo() {
        return 'black.png';
    }

    public function authorize(Varien_Object $payment, $amount){

        // Added validation of shipping and billing address
        if (!$this->validShippingAddress()) {
            Mage::helper('collectorbank')->logException(new Exception("Shipping and billing addresses don't match"));
            Mage::throwException(Mage::helper('collectorbank')->__('Billing address does not match shipping address'));

            return $this;
        }

        $order = $payment->getOrder();

        $originalIncrementId = $order->getOriginalIncrementId();
        $newIncrementId = $order->getIncrementId();
        if ($order->getQuote()->getData('is_iframe')){
            return $this;
        }
        if ($originalIncrementId != null and $originalIncrementId != $newIncrementId) {
            $this->replaceInvoice($payment, $amount);
        } 
        $session = Mage::getSingleton('checkout/session');
        $quote = $order->getQuote();
        $response = json_decode($quote->getData('collector_response'));
        $colpayment_method = "";
        if (array_key_exists('paymentMethod', $response['purchase'])){
            $colpayment_method = $response['purchase']['paymentMethod'];
        }
        $colpayment_details = json_encode($response['purchase']);
        $payment->setCollPaymentMethod($colpayment_method);
        $payment->setCollPaymentDetails($colpayment_details );


        $result['invoice_status'] = $response['status'];
        $result['invoice_no'] = $response['purchase']['purchaseIdentifier'];
        $result['total_amount'] =  $response['order']['totalAmount'];

        $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_NO, isset($result['invoice_no']) ? $result['invoice_no'] : '');
        $payment->setAdditionalInformation(self::COLLECTOR_PAYMENT_REF, isset($result['payment_reference']) ? $result['payment_reference'] : '');
        $payment->setAdditionalInformation(self::COLLECTOR_LOWEST_AMOUNT_TO_PAY, isset($result['lowest_amount_to_pay']) ? $result['lowest_amount_to_pay'] : '');
        $payment->setAdditionalInformation(self::COLLECTOR_TOTAL_AMOUNT, isset($result['total_amount']) ? $result['total_amount'] : '');
        $payment->setAdditionalInformation(self::COLLECTOR_DUE_DATE, isset($result['due_date']) ? $result['due_date'] : '');
        $payment->setAdditionalInformation(self::COLLECTOR_AVAILABLE_RESERVATION_AMOUNT, isset($result['available_reservation_amount']) ? $result['available_reservation_amount'] : '');
        $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_STATUS, isset($result['invoice_status']) ? $result['invoice_status'] : '');
        if ($session->getUseFee() != 5){
            $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE, $this->getInvoiceFee());
            $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_TAX, $this->getInvoiceFeeTax($order));
            $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_TAX_INVOICED, 0);
            $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_INVOICED, 0);
            $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_REFUNDED, 0);
            $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_INVOICE_NO, 0);
            $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_DESCRIPTION, Mage::helper('collectorbank')->__('Invoice fee'));
        }


        $payment->save();

        $order = $payment->getOrder();
        if ($order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
            $comment = Mage::helper('collectorbank')->__('Collector authorization successful');
            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
                ->addStatusToHistory($this->getConfigData('order_status'), $comment)
                ->setIsCustomerNotified(false)
                ->save();
        }
        return $this;
    }

    public function capture(Varien_Object $payment, $amount)
    {
		try {
			$helper = Mage::helper('collectorbank');
			$invoice = Mage::registry('current_invoice');
			$order = $payment->getOrder();
			if (!($invoice instanceof Mage_Sales_Model_Order_Invoice)) {
				if ($order->hasInvoices()) {
					$oInvoiceCollection = $order->getInvoiceCollection();
					if (count($oInvoiceCollection) == 1){
						foreach ($oInvoiceCollection as $inv){
							$invoice = $inv;
							break;
						}
					}
					else {
						Mage::throwException(Mage::helper('collectorbank')->__('Activating invoice failed'));
					}
				}
			}

			$transactionId = $payment->getAdditionalInformation(self::COLLECTOR_INVOICE_NO);

			if (Mage::helper('collectorbank')->isPartial($invoice)) {
				if ($helper->hasCredit($payment)) {
					$result['error'] = true;
					$result['error_message'] = Mage::helper('collectorbank')->__('Orders with gift card, store credit or reward point can not be partial invoiced');
				}
				else {
                    $quote = Mage::getModel('sales/quote')->getCollection()->addFieldToFilter('reserved_order_id', $order->getIncrementId())->getFirstItem();
					$additionalData = array('invoice' => $invoice, 'quote' => $quote);
					$request = $helper->getPartActivateRequest($payment, $additionalData);
					$client = $helper->getSoapClient();
					$headers = array();
					$headers['Username'] = $helper->getUsername($payment->getOrder()->getStoreId());
					$headers['Password'] = $helper->getPassword($payment->getOrder()->getStoreId());
					$headerList = array();
					foreach ($headers as $k => $v) {
						$headerList[] = new SoapHeader($this->ns, $k, $v);
					}
					$client->__setSoapHeaders($headerList);
					try {
						$response = $client->__soapCall('PartActivateInvoice', array('PartActivateInvoiceRequest' => $request));
					}
					catch (Exception $e) {
						$response = null;
						$helper->exceptionHandler($e, $client);
						Mage::throwException(Mage::helper('collectorbank')->__('Activation of invoice failed, please try again.'));
					}
					$result = $helper->prepareResponse($response);
					if (!$result['error']) {
						$payment->setStatus(Mage_Payment_Model_Method_Abstract::STATUS_APPROVED);
						if (!$payment->getParentTransactionId() || $transactionId != $payment->getParentTransactionId()) {
							$payment->setTransactionId($transactionId);
						}
						if (array_key_exists('invoice_url', $result)){
							Mage::getSingleton('adminhtml/session')->setData('collector_invoice_url', $result['invoice_url']);
						}

						$payment->setAdditionalInformation(self::COLLECTOR_INVOICE_NO, isset($result['new_invoice_no']) ? $result['new_invoice_no'] : '');

						//First invoice, add invoice fee
						if (!$payment->getAdditionalInformation(self::COLLECTOR_INVOICE_FEE_INVOICED)) {
							$invoiceFee = $payment->getAdditionalInformation(self::COLLECTOR_INVOICE_FEE);
							$invoiceFeeTax = $payment->getAdditionalInformation(self::COLLECTOR_INVOICE_FEE_TAX);
							$payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_INVOICED, $invoiceFee);
							$payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_TAX_INVOICED, $invoiceFeeTax);
							$payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_INVOICE_NO, $transactionId);
						}

						$payment->save();
					}
				}
			}
			else {
				$request = $helper->getActivateRequest($payment, $order->getStoreId());
				$client = $helper->getSoapClient();
				$headers = array();
				$headers['Username'] = $helper->getUsername($payment->getOrder()->getStoreId());
				$headers['Password'] = $helper->getPassword($payment->getOrder()->getStoreId());
				$headerList = array();
				foreach ($headers as $k => $v) {
					$headerList[] = new SoapHeader($this->ns, $k, $v);
				}
				$client->__setSoapHeaders($headerList);
				$request = array('ActivateInvoiceRequest' => $request);
				try {
					$response = $client->__soapCall('ActivateInvoice', $request);
				}
				catch (Exception $e) {
					$response = null;
					$helper->exceptionHandler($e, $client);
					Mage::throwException(Mage::helper('collectorbank')->__('Activation of invoice failed, please try again.'));
				}
				$result = $helper->prepareResponse($response);
				if (!$result['error']) {
					$payment->setStatus(Mage_Payment_Model_Method_Abstract::STATUS_APPROVED);
					if (!$payment->getParentTransactionId() || $transactionId != $payment->getParentTransactionId()) {
						$payment->setTransactionId($transactionId);
					}

					Mage::getSingleton('adminhtml/session')->setData('collector_invoice_url', $result['invoice_url']);
					
					if (!$payment->getAdditionalInformation(self::COLLECTOR_INVOICE_FEE_INVOICED)) {
						$invoiceFee = $payment->getAdditionalInformation(self::COLLECTOR_INVOICE_FEE);
						$invoiceFeeTax = $payment->getAdditionalInformation(self::COLLECTOR_INVOICE_FEE_TAX);
						$payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_INVOICED, $invoiceFee);
						$payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_TAX_INVOICED, $invoiceFeeTax);
						$payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_INVOICE_NO, $transactionId);
					}

					$payment->save();
				}
			}

			if ($result['error']) {
                $helper->exceptionHandler($e, $client);
				Mage::throwException(Mage::helper('collectorbank')->__('Activating invoice failed: %s', $result['error_message']));
			}
			return $this;
		}
		catch (Exception $e){
            $helper->exceptionHandler($e, $client);
			Mage::throwException(Mage::helper('collectorbank')->__('Activating invoice failed: %s', $e->getMessage()));
		}
    }

	public function getTitle(){
		return $this->getConfigData('title');
    }
	
    public function getInvoiceFee()
    {
//		$paymentInfo = $this->getInfoInstance();
//		$payment = $paymentInfo->getOrder()->getPayment();
//		$purchaseData = json_decode($payment->getCollPaymentDetails(),true); 
        if ($this->isBusinessCustomer()) {
            return Mage::helper('collectorbank/invoiceservice')->getModuleConfig('invoice/invoice_fee_company');
        }

        return Mage::helper('collectorbank/invoiceservice')->getModuleConfig('invoice/invoice_fee');
    }

    public function getTaxClass()
    {
        if ($this->isBusinessCustomer()) {
            return Mage::helper('collectorbank/invoiceservice')->getModuleConfig('invoice/invoice_fee_company_tax_class');
        }

        return Mage::helper('collectorbank/invoiceservice')->getModuleConfig('invoice/invoice_fee_tax_class');
    }

    public function getInvoiceFeeTax($order)
    {
        $store = $order->getStore();
        $custTaxClassId = $order->getCustomerTaxClassId();

        $taxCalculationModel = Mage::getSingleton('tax/calculation');
        /* @var $taxCalculationModel Mage_Tax_Model_Calculation */
        $request = $taxCalculationModel->getRateRequest($order->getShippingAddress(), $order->getBillingAddress(), $custTaxClassId, $store);
        $shippingTaxClass = Mage::helper('collectorbank/invoiceservice')->getModuleConfig('invoice/invoice_fee_tax_class');

        $feeTax = 0;

        if ($shippingTaxClass) {
            if ($rate = $taxCalculationModel->getRate($request->setProductClassId($shippingTaxClass))) {
                $feeTax = ($this->getInvoiceFee() / ($rate + 100)) * $rate;
                $feeTax = $store->roundPrice($feeTax);
            }
        }

        return $feeTax;
    }

    public function getInvoiceFeeTaxPercent($order)
    {
        $feeAmount = $this->getInvoiceFee();
        $taxAmount = $this->getInvoiceFeeTax($order);

        $exTax = $feeAmount - $taxAmount;
        $taxPercent = ($taxAmount / $exTax) * 100;

        return $taxPercent;
    }

    public function getDeliveryMethod()
    {
		return 1;
    }
	
	public function getInvoiceType(){
        return 3;
    }

    public function getInfoText($company = false){
        if ($company) {
            $infoText = Mage::helper('collectorbank')->getModuleConfig('invoice/info_text_company');
        } 
		else {
            $infoText = Mage::helper('collectorbank')->getModuleConfig('invoice/info_text');
        }

        if (empty($infoText)) {
            if ($company) {
                $infoText = Mage::helper('collectorbank')->__("When you choose invoice you will get your items delivered before you pay. Invocing happeneds in cooperation with Collector Credit AB. More information at http://www.collector.se");
            } else {
                $infoText = Mage::helper('collectorbank')->__("When you choose invoice you will get your items delivered before you pay. You can then choose to pay the entire amount at once or split the payment into smaller parts. To purchase with Collectors invoice you need to be atleast 18 years old. More information at http://www.collector.se");
            }
        }

        return $infoText;
    }

    public function useCampaign()
    {
        return $this->getConfigData('use_campaign');
    }

    /**
     * Determines payment method availability.
     */
    public function isAvailable($quote = null)
    {
		return true;
    }

    /**
     * Detect if customer is a business customer.
     */
    public function isBusinessCustomer()
    {
        $paymentInfo = $this->getInfoInstance();
        $quote = $paymentInfo->getQuote();
        $order = $paymentInfo->getOrder();

        if (isset($order)) {
            $billingAddress = $order->getBillingAddress();
        } elseif (isset($quote)) {
            $billingAddress = $quote->getBillingAddress();
        }

        if ($billingAddress->getCompany()) {
            return true;
        }

        return false;
    }

	public function refund(Varien_Object $payment, $amount) {
		$helper = Mage::helper('collectorbank');
        $creditmemo = $payment->getCreditmemo();
        if (!($creditmemo instanceof Mage_Sales_Model_Order_Creditmemo)) {
            return $this;
        }
        if ($creditmemo->getBaseShippingAmount() > 0 AND $creditmemo->getBaseShippingAmount() < $creditmemo->getInvoice()->getBaseShippingAmount()) {
            Mage::throwException(Mage::helper('collectorbank')->__('Refunded shipping amount can only be 0 or %s incl. Tax', number_format($payment->getBaseShippingAmount(), 2)));
        }
        if ($creditmemo->getBaseAdjustment() != 0) {
            Mage::throwException(Mage::helper('collectorbank')->__('Adjustments refund/fee is not supported by Collector. Use value 0.'));
        }
        $partialCredit = Mage::helper('collectorbank')->isPartial($creditmemo);
        if ($partialCredit) {
            $quote = Mage::getModel('sales/quote')->getCollection()->addFieldToFilter('reserved_order_id', $payment->getOrder()->getIncrementId())->getFirstItem();
            $additionalData = array('quote' => $quote);
			$request = $helper->getPartialCreditRequest($payment, $additionalData);
			$client = $helper->getSoapClient();
			$headers = array();
			$headers['Username'] = $helper->getUsername($payment->getOrder()->getStoreId());
			$headers['Password'] = $helper->getPassword($payment->getOrder()->getStoreId());
			$headerList = array();
			foreach ($headers as $k => $v) {
				$headerList[] = new SoapHeader($this->ns, $k, $v);
			}
			$client->__setSoapHeaders($headerList);
			try {
				$response = $client->__soapCall('PartCreditInvoice', array('PartCreditInvoiceRequest' => $request));
			}
			catch (Exception $e) {
				$helper->exceptionHandler($e, $client);
				Mage::throwException(Mage::helper('collectorbank')->__('Credit memo failed: %s', $e->getMessage()));
			}
			$result = $helper->prepareResponse($response);
        }
        else {
			$request = $helper->getFullCreditRequest($payment);
			$client = $helper->getSoapClient();
			$headers = array();
			$headers['Username'] = $helper->getUsername($payment->getOrder()->getStoreId());
			$headers['Password'] = $helper->getPassword($payment->getOrder()->getStoreId());
			$headerList = array();
			foreach ($headers as $k => $v) {
				$headerList[] = new SoapHeader($this->ns, $k, $v);
			}
			$client->__setSoapHeaders($headerList);
			try {
				$response = $client->__soapCall('CreditInvoice', array('CreditInvoiceRequest' => $request));
			}
			catch (Exception $e) {
				$helper->exceptionHandler($e, $client);
				Mage::throwException(Mage::helper('collectorbank')->__('Credit memo failed: %s', $e->getMessage()));
			}
			$result = $helper->prepareResponse($response);
        }
        if ($result['error']) {
            Mage::throwException(Mage::helper('collectorbank')->__('Credit memo failed: %s', $result['error_message']));
        }
        else {
            if ($payment->getAdditionalInformation(self::COLLECTOR_INVOICE_FEE_REFUNDED) == 0) {
                $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_REFUNDED, $this->getInvoiceFee());
                $payment->save();
            }
        }
        return $this;
    }
	
	public function replaceInvoice(Varien_Object $payment, $amount) {
		$helper = Mage::helper('collectorbank');
        $order = $payment->getOrder();
        $request = $helper->getReplaceInvoiceRequest($payment);
		$client = $helper->getSoapClient();
		$headers = array();
		$headers['Username'] = $helper->getUsername($order->getStoreId());
		$headers['Password'] = $helper->getPassword($order->getStoreId());
		$headers['ClientIpAddress'] = $request['ClientIpAddress'];
		
		$headerList = array();
		foreach ($headers as $k => $v) {
			$headerList[] = new SoapHeader($this->ns, $k, $v);
		}
		$client->__setSoapHeaders($headerList);
		
        try {
            $response = $client->__soapCall('ReplaceInvoice', $request);
        }
        catch (Exception $e) {
            $response = null;
            $helper->exceptionHandler($e, $client);
            if (isset($e->faultcode)) {
                $faultCode = explode(':', $e->faultcode);
                if (is_array($faultCode) AND isset($faultCode[1]) AND $helper->isCustomerError($faultCode[1])) {
                    $faultCode = $helper->__(isset($faultCode[1]) ? $faultCode[1] : 'An error occurred, please contact the site administrator.');
                    Mage::throwException($faultCode);
                }
            }
            Mage::throwException(Mage::helper('collectorbank')->__('Payment failed, please try again.'));
        }

        $result = $helper->prepareResponse($response);
        if (!$result['error']) {
            $payment->setAdditionalInformation(self::COLLECTOR_TOTAL_AMOUNT, isset($result['total_amount'])?$result['total_amount']:'');
            $payment->setAdditionalInformation(self::COLLECTOR_AVAILABLE_RESERVATION_AMOUNT, isset($result['available_reservation_amount'])?$result['available_reservation_amount']:'');
            $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_STATUS, isset($result['invoice_status'])?$result['invoice_status']:'');
        
            $payment->save();
            if ($order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                $comment = Mage::helper('collectorbank')->__('Collector authorization successful');
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING)->addStatusToHistory($this->getConfigData('order_status'),$comment)->setIsCustomerNotified(false)->save();
            }
            
            Mage::register('collector_order_edited', true);
        }
        else {
            Mage::helper('collectorbank')->logException(new Exception($result['error_message']));
            Mage::throwException('Payment failed, please try again.');
        }
    }
	
	public function cancel(Varien_Object $payment) {
		$helper = Mage::helper('collectorbank');
		$request = $helper->getCancelInvoiceRequest($payment);
		$client = $helper->getSoapClient();
		$headers = array();
		$headers['Username'] = $helper->getUsername($payment->getOrder()->getStoreId());
		$headers['Password'] = $helper->getPassword($payment->getOrder()->getStoreId());
		$headerList = array();
		foreach ($headers as $k => $v) {
			$headerList[] = new SoapHeader($this->ns, $k, $v);
		}
		$client->__setSoapHeaders($headerList);
		$request = array('CancelInvoiceRequest' => $request);
		
		try {
            $response = $client->__soapCall('CancelInvoice', $request);
        }
        catch (Exception $e) {
            $response = null;
            $helper->exceptionHandler($e, $client);
            Mage::throwException(Mage::helper('collectorbank')->__('Cancel invoice failed, please try again. %s', $e->getMessage()));
        }
        $result = $helper->prepareResponse($response);
        if ($result['error']) {
            Mage::throwException(Mage::helper('collectorbank')->__('Cancel invoice failed: %s', $result['error_message']));
        }
        return $this;
    }
}

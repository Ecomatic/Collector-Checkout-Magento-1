<?php
class Ecomatic_Collectorbank_Model_Collectorbank_Abstract extends Mage_Payment_Model_Method_Abstract
{
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

    public function validate() {
         return $this;
    }

    public function assignData($data) {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $this->addAdditionalData($data->getData());
        return $this;
    }

    public function initialize($paymentAction, $stateObject) {
        return $this;
    }
    
    public function refund(Varien_Object $payment, $amount) {
        $creditmemo = $payment->getCreditmemo();
        if (!($creditmemo instanceof Mage_Sales_Model_Order_Creditmemo)) {
            return $this;
        }

        $shippingRefund = true;
        if ($creditmemo->getBaseShippingAmount() > 0 AND $creditmemo->getBaseShippingAmount() < $payment->getBaseShippingAmount()) {
            Mage::throwException(Mage::helper('collectorbank')->__('Refunded shipping amount can only be 0 or %s incl. Tax', number_format($payment->getBaseShippingAmount(), 2)));
        }

        if ($creditmemo->getBaseAdjustment() != 0) {
            Mage::throwException(Mage::helper('collectorbank')->__('Adjustments refund/fee is not supported by Collector. Use value 0.'));
        }

        $partialCredit = Mage::helper('collectorbank/invoiceservice')->isPartial($creditmemo);

        if ($partialCredit) {
            $partCreditInvoice = Mage::getModel('collectorbank/invoiceservice_partcreditinvoice')
                ->setRequest($payment, array('creditmemo' => $creditmemo));
            $result = $partCreditInvoice->partCreditInvoice();
        }
        else {
            $creditInvoice = Mage::getModel('collectorbank/invoiceservice_creditinvoice')
                ->setRequest($payment, array('creditmemo' => $creditmemo));
            $result = $creditInvoice->creditInvoice();
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
        $order = $payment->getOrder();
        
        $replaceInvoiceService = Mage::getModel('collectorbank/invoiceservice_replaceinvoice')
            ->setRequest($order, array('payment' => $payment));
        
        $result = $replaceInvoiceService->replaceInvoice(); 
        
        if (!$result['error']) {
            $payment->setAdditionalInformation(self::COLLECTOR_TOTAL_AMOUNT, isset($result['total_amount'])?$result['total_amount']:'');
            $payment->setAdditionalInformation(self::COLLECTOR_AVAILABLE_RESERVATION_AMOUNT, isset($result['available_reservation_amount'])?$result['available_reservation_amount']:'');
            $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_STATUS, isset($result['invoice_status'])?$result['invoice_status']:'');
        
            $payment->save();

            if ($order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                $comment = Mage::helper('collectorbank')->__('Collector authorization successful');
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
                ->addStatusToHistory($this->getConfigData('order_status'),$comment)
                ->setIsCustomerNotified(false)
                ->save();
            }
            
            Mage::register('collector_order_edited', true);
        }
        else {
            Mage::helper('collectorbank')->logException(new Exception($result['error_message']));
            Mage::throwException('Payment failed, please try again.');
        }

    }

    public function cancel(Varien_Object $payment) {
        $cancelInvoice = Mage::getModel('collectorbank/invoiceservice_cancelinvoice')
            ->setRequest($payment);
        $result = $cancelInvoice->cancelInvoice();

        if ($result['error']) {
            Mage::throwException(Mage::helper('collectorbank')->__('Cancel invoice failed: %s', $result['error_message']));
        }

        return $this;
    }

    public function getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

    public function getOrder() {
        $orderId = $this->getCheckout()->getLastOrderId();
        $order = Mage::getModel('sales/order')->load($orderId);

        return $order;
    }

    public function getLogo() {
        return 'black.png';
    }

    protected function _getSession() {
        return Mage::getSingleton('adminhtml/session');
    }
    
    public function hasCredit($payment) {
        $giftCard = ($payment->getOrder()->getData('base_gift_cards_amount') > 0) ? true : false;
        $storeCredit = ($payment->getOrder()->getData('base_customer_balance_amount') > 0) ? true : false;
        $rewardPoint = ($payment->getOrder()->getData('base_reward_currency_amount') > 0) ? true : false;
        
        return ($giftCard || $storeCredit || $rewardPoint) ? true : false;
    }
    
    public function inRegister() {
        $regcode = $this->getRegCode();
        $carray = explode(".",$_SERVER[base64_decode('U0VSVkVSX05BTUU=')]);
        $d = strtolower($carray[count($carray)-2]);

        return $this->checkLicense($regcode,$_SERVER[base64_decode('U0VSVkVSX05BTUU=')]);
    }

    protected function checkLicense($serial,$domain) {
        $mKey = "dHJvbGx3ZWJfY29sbGVjdG9y";
        $secret = ${base64_decode('ZG9tYWlu')};
        $carray = explode('.',trim($domain));
        $regcode = $serial;
        if (count($carray) < 2) {
            $carray = array(uniqid(),uniqid());
        }

        $domain_array = array(
              'ao','ar','au','bd','bn','co','cr','cy','do','eg','et','fj','fk','gh','gn','id','il','jm','jp','kh','kw','kz','lb','lc','lr','ls',
              'mv','mw','mx','my','ng','ni','np','nz','om','pa','pe','pg','py','sa','sb','sv','sy','th','tn','tz','uk','uy','va','ve','ye','yu',
              'za','zm','zw'
              );
              $key = $secret.$regcode.$domain.serialize($domain_array);

              $tld = trim($carray[count($carray)-1]);
              if (in_array($tld,$domain_array)) {
                  $darr = array_splice($carray,-3);
              }
              else {
                  $darr = array_splice($carray,-2);
              }

              $d = strtolower(join(".",$darr));
              $secret = $d;
              $offset = 0;
              $privkey = rand(1,strlen($domain));
              $offset = (strlen($key)*32)-(strlen($key)*64)+$privkey-$offset+(strlen($key)*32);
              $f = base64_decode("c2hhMQ==");
              return ($f(base64_encode(strtolower(substr($secret,0,strlen($d) % $offset).substr($d,(strlen($secret) % $offset))).base64_decode(${base64_decode('bUtleQ==')}))) == ${base64_decode('cmVnY29kZQ==')});
    }
    
    public function isAvailable($quote = null) {
        return true;
    }
    
    public function useCampaign() {
        return $this->_useCampaign;
    }

    /**
     * Makes sure that order is not sent if shipping
     * address is not set to same address as billing
     * when "Allow separate shipping and billing" is
     * set to "no"
     * @return bool True if properly set
     */
    protected function validShippingAddress() {
        $isCompany = Mage::helper('collectorbank/invoiceservice')->guessCustomerType(Mage::getSingleton('checkout/session')->getQuote()->getBillingAddress()) == 'company';

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

    /**
     * This is to get around checkout/session not existing
     * when editing an order in the admin panel.
     */
    protected function validShippingAddressInAdmin() {
        return Mage::app()->getRequest()->getParam('shipping_same_as_billing') == 'on' ? true : false;
    }

    /**
     * Checks if we're in the admin panel
     */
    protected function isAdmin() {
        if(Mage::app()->getStore()->isAdmin()) {
                return true;
        }
        if(Mage::getDesign()->getArea() == 'adminhtml') {
                return true;
        }

        return false;
    }
}

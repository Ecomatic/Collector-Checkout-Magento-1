<?php
class Ecomatic_Collectorbank_Block_Adminhtml_Order_Create_Billing_Method_Form  extends Mage_Adminhtml_Block_Sales_Order_Create_Billing_Method_Form
{
    public function isCollector() {
        if (strpos($this->getSelectedMethodCode(), 'collectorbank') === false) {
            return false;
        }
        return true;
    }
    
    public function getMethod() {
        $currentMethodCode = $this->getQuote()->getPayment()->getMethod();
        if ($currentMethodCode) {
            return $currentMethodCode;
        }
        return false;
    }
    
    public function getInfo() {
        return $this->getQuote()->getPayment()->getAdditionalInformation();
    }
    
    public function getInvoiceNo() {
        $data = $this->getInfo();
        if (isset($data['collector_invoice_no'])) {
            return $data['collector_invoice_no'];
        }
        return false;
    }
}

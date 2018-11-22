<?php

class Ecomatic_Collectorbank_Adminhtml_InvoiceController extends Mage_Adminhtml_Controller_Action
{
    public function extendAction() {
        $invoiceId = $this->getRequest()->getParam('invoice_id');
        $invoice = Mage::getModel('sales/order_invoice')->load($invoiceId);
        if ($invoice->getId() and $invoice->getTransactionId()) {
            $order = $invoice->getOrder();
            $payment = $order->getPayment();
            
            $result = Mage::getModel('collectorbank/invoiceservice_extendduedate')->setRequest($payment, array('invoice' => $invoice))->extendDueDate();

            if ($result['error']) {
                Mage::getSingleton('adminhtml/session')->addError($result['message']);
            }
            else {
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('collectorbank')->__('Due date was extended.'));
            }
            
            $this->_redirect('adminhtml/sales_order_invoice/view', array('invoice_id' => $invoiceId, 'order_id' => $order->getId()));
        }
    }
    
    public function resendAction() {
        $newEmail = $this->getRequest()->getParam('collector_new_email');
        $invoiceId = $this->getRequest()->getParam('invoice_id');
        $invoice = Mage::getModel('sales/order_invoice')->load($invoiceId);
        if ($invoice->getId() and $invoice->getTransactionId()) {
            $order = $invoice->getOrder();
            $payment = $order->getPayment();
    
            $result = Mage::getModel('collectorbank/invoiceservice_sendinvoice')->setRequest($payment, array('invoice' => $invoice, 'new_email' => $newEmail))->resend();
    
            if ($result['error']) {
                Mage::getSingleton('adminhtml/session')->addError($result['message']);
            }
            else {
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('collectorbank')->__('Invoice was resent successfully.'));
            }
    
            $this->_redirect('adminhtml/sales_order_invoice/view', array('invoice_id' => $invoiceId, 'order_id' => $order->getId()));
        }
    }
}

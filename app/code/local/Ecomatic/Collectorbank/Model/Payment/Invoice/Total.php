<?php
class Ecomatic_Collectorbank_Model_Payment_Invoice_Total extends Mage_Sales_Model_Order_Invoice_Total_Abstract
{
    public function collect(Mage_Sales_Model_Order_Invoice $invoice)
    {
        if ($invoice->getOrder()->getPayment()->getMethodInstance()->getCode() != 'collectorbank_invoice') {
            return $this;
        }

        $orderData = $invoice->getOrder()->getPayment()->getAdditionalInformation();
        $feeKey = Ecomatic_Collectorbank_Model_Collectorbank_Abstract::COLLECTOR_INVOICE_FEE;
        $feeInvoicedKey = Ecomatic_Collectorbank_Model_Collectorbank_Abstract::COLLECTOR_INVOICE_FEE_INVOICED;
        $feeTaxKey = Ecomatic_Collectorbank_Model_Collectorbank_Abstract::COLLECTOR_INVOICE_FEE_TAX;
        $feeTaxInvoicedKey = Ecomatic_Collectorbank_Model_Collectorbank_Abstract::COLLECTOR_INVOICE_FEE_TAX_INVOICED;

        $store = $invoice->getOrder()->getStore();
        $baseInvoiceFee = $orderData[$feeKey] - $orderData[$feeTaxKey];
        $baseInvoiceFeeInvoiced = $orderData[$feeInvoicedKey] - $orderData[$feeTaxInvoicedKey];
        $invoiceFee =  $store->convertPrice($baseInvoiceFee,false);
        $invoiceFeeInvoiced =  $store->convertPrice($baseInvoiceFeeInvoiced,false);

        if (!$invoiceFee || $baseInvoiceFee == $baseInvoiceFeeInvoiced){
            return $this;
        }

        $baseInvoiceTotal = $invoice->getBaseGrandTotal();
        $invoiceTotal = $invoice->getGrandTotal();

        $baseInvoiceTotal = $baseInvoiceTotal + ($baseInvoiceFee - $baseInvoiceFeeInvoiced);
        $invoiceTotal = $invoiceTotal + ($invoiceFee - $invoiceFeeInvoiced);

        $invoice->setBaseGrandTotal($baseInvoiceTotal);
        $invoice->setGrandTotal($invoiceTotal);

        return $this;
    }

}
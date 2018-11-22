<?php
class Ecomatic_Collectorbank_Model_Payment_Invoice_Tax extends Mage_Sales_Model_Order_Invoice_Total_Abstract
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
        $baseInvoiceFeeTax = $orderData[$feeTaxKey];
        $baseInvoiceFeeTaxInvoiced = $orderData[$feeTaxInvoicedKey];

        $invoiceFeeTax =  $store->convertPrice($baseInvoiceFeeTax,false);
        $invoiceFeeTaxInvoiced =  $store->convertPrice($baseInvoiceFeeTaxInvoiced,false);


        $includeTax = true;

        $ifTax = 0;
        $baseIfTax = 0;

        if (!$invoiceFeeTax || $baseInvoiceFeeTax == $baseInvoiceFeeTaxInvoiced) {
            $includeTax = false;
        }

        if ($includeTax) {
            $ifTax += $invoiceFeeTax;
            $baseIfTax += $baseInvoiceFeeTax;
        }

        $order = $invoice->getOrder();

        $allowedTax     = $order->getTaxAmount() - $order->getTaxInvoiced();
        $allowedBaseTax = $order->getBaseTaxAmount() - $order->getBaseTaxInvoiced();
        $totalTax = $invoice->getTaxAmount();
        $baseTotalTax = $invoice->getBaseTaxAmount();

        if (!$invoice->isLast() && $allowedTax > $totalTax) {
            $newTotalTax           = min($allowedTax, $totalTax + $ifTax);
            $newBaseTotalTax       = min($allowedBaseTax, $baseTotalTax + $baseIfTax);

            $invoice->setTaxAmount($newTotalTax);
            $invoice->setBaseTaxAmount($newBaseTotalTax);

            $invoice->setGrandTotal($invoice->getGrandTotal() - $totalTax + $newTotalTax);
            $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() - $baseTotalTax + $newBaseTotalTax);
        }
        else {
            // Remove Invoice Fee Tax from SubtotalInclTax.
            $invoice->setSubtotalInclTax($invoice->getSubtotalInclTax()-$ifTax);
            $invoice->setBaseSubtotalInclTax($invoice->getBaseSubtotalInclTax()-$baseIfTax);
        }

        return $this;
    }
}
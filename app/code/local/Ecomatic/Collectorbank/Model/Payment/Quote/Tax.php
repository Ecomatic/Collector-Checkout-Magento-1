<?php
class Ecomatic_Collectorbank_Model_Payment_Quote_Tax extends Mage_Sales_Model_Quote_Address_Total_Tax {

    public function collect(Mage_Sales_Model_Quote_Address $address)
    {

        if (!$address->getQuote()->getId()) {
          return $this;
        }

        if ($address->getAddressType() != "shipping") {
          return $address;
        }

        $paymentMethod = Mage::app()->getFrontController()->getRequest()->getParam('payment');
        $paymentMethod = Mage::app()->getStore()->isAdmin() && isset($paymentMethod['method']) ? $paymentMethod['method'] : null;
        if ($paymentMethod != 'collectorbank_invoice' && (!count($address->getQuote()->getPaymentsCollection()) || !$address->getQuote()->getPayment()->hasMethodInstance())){
            return $this;
        }

        $paymentMethod = $address->getQuote()->getPayment()->getMethodInstance();

        if ($paymentMethod->getCode() != 'collectorbank_invoice') {
            return $this;
        }

        $store = $address->getQuote()->getStore();
        $custTaxClassId = $address->getQuote()->getCustomerTaxClassId();

        $taxCalculationModel = Mage::getSingleton('tax/calculation');
        /* @var $taxCalculationModel Mage_Tax_Model_Calculation */
        $request = $taxCalculationModel->getRateRequest($address, $address->getQuote()->getBillingAddress(), $custTaxClassId, $store);
        $shippingTaxClass = (int)$paymentMethod->getTaxClass();

        $feeTax      = 0;
        $feeBaseTax  = 0;

        if ($shippingTaxClass) {
            if ($rate = $taxCalculationModel->getRate($request->setProductClassId($shippingTaxClass))) {
                $feeTax = ($address->getCollectorInvoiceFee() / ($rate+100))*$rate;
                $feeBaseTax= ($address->getBaseCollectorInvoiceFee() / ($rate+100))*$rate;
                $feeTax    = $store->roundPrice($feeTax);
                $feeBaseTax= $store->roundPrice($feeBaseTax);

                $address->setTaxAmount($address->getTaxAmount() + $feeTax);
                $address->setBaseTaxAmount($address->getBaseTaxAmount() + $feeBaseTax);

                $this->_saveAppliedTaxes(
                    $address,
                    $taxCalculationModel->getAppliedRates($request),
                    $feeTax,
                    $feeBaseTax,
                    $rate
                );
            }
        }

        return $address;
    }

    public function fetch(Mage_Sales_Model_Quote_Address $address) {
        return $this;
    }

    protected function getCheckout() {
      return Mage::getSingleton('checkout/session');
    }

 }

<?php
class Ecomatic_Collectorbank_Helper_Invoiceservice extends Ecomatic_Collectorbank_Helper_Data
{
    public function invoiceRow(Mage_Sales_Model_Order_Item $item) {
        $priceInclTaxAndDiscount = $item->getBasePriceInclTax();
        if ($item->getBaseDiscountAmount()) {
            $discountAmount = $item->getBaseDiscountAmount() / $item->getQtyOrdered();
            $priceInclTaxAndDiscount = $item->getBasePriceInclTax() - $discountAmount;
        }

        $unitPrice = sprintf("%01.2f", $priceInclTaxAndDiscount);
        $vat = sprintf("%01.2f", $item->getTaxPercent());
        
        if ($item->getParentItemId()) {
            $parentItem = $item->getParentItem();
            if ($parentItem->getProductType() == 'configurable') {
                $parentPriceInclTaxAndDiscount = $parentItem->getBasePriceInclTax();
                if ($item->getBaseDiscountAmount()) {
                    $discountAmount = $parentItem->getBaseDiscountAmount() / $parentItem->getQtyOrdered();
                    $parentPriceInclTaxAndDiscount = $parentItem->getBasePriceInclTax() - $discountAmount;
                }

                $unitPrice = sprintf("%01.2f", $parentPriceInclTaxAndDiscount);
                $vat = sprintf("%01.2f", $parentItem->getTaxPercent());
            }
        }
        
        $articleId = (strlen($item->getSku()) > 50) ? $item->getItemId() : $item->getSku();
        
        return array(
            'ArticleId' => $articleId,
            'Description' => $this->cutStringAt($item->getName(), 50),
            'Quantity' => $item->getQtyOrdered(),
            'UnitPrice' => $unitPrice,
            'VAT' => $vat,
        );
    }

    public function invoiceRowShipping($order) {
        return array(
            'ArticleId' => Ecomatic_Collectorbank_Model_Invoiceservice_Abstract::ART_ID_SHIPPING,
            'Description' => $this->cutStringAt($order->getShippingDescription(), 50),
            'Quantity' => 1,
            'UnitPrice' => sprintf("%01.2f", $order->getBaseShippingInclTax()),
            'VAT' => sprintf("%01.2f", $order->getBaseShippingTaxAmount() / $order->getBaseShippingAmount() * 100),
        );
    }
    
    public function invoiceRowFee($order) {
        $paymentMethod = $order->getPayment()->getMethodInstance();
        
        return array(
            'ArticleId' => Ecomatic_Collectorbank_Model_Invoiceservice_Abstract::ART_ID_INVOICE_FEE,
            'Description' => $this->cutStringAt($this->__('Invoice fee'), 50),
            'Quantity' => 1,
            'UnitPrice' => sprintf("%01.2f", $paymentMethod->getInvoiceFee()),
            'VAT' => sprintf("%01.2f", $paymentMethod->getInvoiceFeeTaxPercent($order)),
        );
    }
    
    public function invoiceRowCredit($code, $desc, $value) {
        return array(
            'ArticleId' => $code,
            'Description' => $this->cutStringAt($desc, 50),
            'Quantity' => 1,
            'UnitPrice' => sprintf("%01.2f", ($value * -1)),
            'VAT' => sprintf("%01.2f", 0),
        );
    }

    /**
     * We need a way for the module to figure out if a customer is a business
     * or a person, this method attempts to solve this problem
     * @param Mage_Customer_Model_Address_Abstract $address Customer billing address
     * @return string "private" for a person, "comapny" for a business
     */
    public function guessCustomerType(Mage_Customer_Model_Address_Abstract $address) {
        if($address->getCompany()) {
            return "company";
        }
        return "private";
    }

    public function address(Mage_Customer_Model_Address_Abstract $address) {
        // For company customers
        if($address->getCompany()) {
            return array(
                'CompanyName' => $address->getCompany(),
                'Address1' => $address->getStreetFull(),
                //'Address2' => ,
                //'COAddress' => ,
                'PostalCode' => $address->getPostcode(),
                'City' => $address->getCity(),
                //'PhoneNumber' => $address->getTelephone(),
                'CellPhoneNumber' => $address->hasCellPhoneNumber() ? $address->getCellPhoneNumber() : null,
                'Email' => $address->getEmail(),
                'CountryCode' => $address->getCountryId(),
            );
        }

        return array(
            'Firstname' => $address->getFirstname(),
            'Lastname' => $address->getLastname(),
            'Address1' => $address->getStreetFull(),
            //'Address2' => ,
            //'COAddress' => ,
            'PostalCode' => $address->getPostcode(),
            'City' => $address->getCity(),
            //'PhoneNumber' => $address->getTelephone(),
            'CellPhoneNumber' => $address->hasCellPhoneNumber() ? $address->getCellPhoneNumber() : null,
            'Email' => $address->getEmail(),
            'CountryCode' => $address->getCountryId(),
        );
    }

    public function articleList($item, $additionalData) {
        $qty = $item->getQty();
        $orderItem = $item->getOrderItem();
        if ($orderItem->getId() AND $orderItem->getProductType() == 'configurable') {
            $childrenItems = $orderItem->getChildrenItems();
            if (is_array($childrenItems)) {
                $child = array_pop($childrenItems);
                if ($child) {
                    //Keep parent (conf.) invoiced qty
                    $qty = $item->getQty();
                    //Swap conf. child with current conf.item
                    $item = $child;
                }
            }
        }

        $itemId = false;
        $quote = $additionalData['quote'];
        $colItems = json_decode($quote->getData('collector_response'))->cart->items;
        foreach ($colItems as $colItem){
            Mage::log('expression ' . ob_get_clean(), null, 'coldev.log');
            if (strpos($colItem->id, $item->getSku()) !== false){
                $itemId = $colItem->id;
            }
        }

        return array(
            'ArticleId' => $itemId,
            'Description' => $item->getName(),
            'Quantity' => $qty,
        );
    }
    
    public function articleListShipping($order) {
        return array(
            //'ArticleId' => Ecomatic_Collectorbank_Model_Invoiceservice_Abstract::ART_ID_SHIPPING,
			'ArticleId' => $order->getShippingMethod(),
            'Description' => $this->cutStringAt($order->getShippingDescription(), 50),
            'Quantity' => 1,
        );
    }
    
    public function articleListFee($order) {
        $feeDescription = $order->getPayment()->getAdditionalInformation(
                Ecomatic_Collectorbank_Model_Collectorbank_Abstract::COLLECTOR_INVOICE_FEE_DESCRIPTION);
        return array(
            'ArticleId' => Ecomatic_Collectorbank_Model_Invoiceservice_Abstract::ART_ID_INVOICE_FEE,
			'Description' => $this->cutStringAt($feeDescription, 50),
            'Quantity' => 1,
        );
    }

    public function getProductTypes() {
        return array(
            0 => $this->__('Invoice / Part Payment'),
            3 => $this->__('Account'),
//            0 => $this->__('Invoice will be in the package and/or directly sent with e-mail if InvoiceDeliveryMethod is set to e-mail, Collector will not send this invoice to the customer, you will send it as part of the package.'),
//            1 => $this->__('Monthly invoice. Collector will send this invoice.'),
//            2 => $this->__('Part Payment. Collector will send a part payment invoice with interest.'),
//            3 => $this->__('Aggregated invoice. Collector will send the invoice. All invoices incoming during the same month with this flag will be aggregated to one invoice.'),
        );
    }

    public function getInvoiceTypes() {
        return array(
            0 => $this->__('Invoice will be in the package and/or directly sent with e-mail if InvoiceDeliveryMethod is set to e-mail, Collector will not send this invoice to the customer, you will send it as part of the package.'),
            1 => $this->__('Monthly invoice. Collector will send this invoice.'),
        );
    }

    public function getGender() {
        return array(
            0 => $this->__('Not known'),
            1 => $this->__('Male'),
            2 => $this->__('Female'),
            9 => $this->__('Not applicable'),
        );
    }

    public function getDeliveryMethods($type) {
        $methods = array();
        if ($type == 'merchant') {
            $methods[1] = $this->__('In Package by merchant');
            $methods[3] = $this->__('In Package and e-mail');
            $methods[2] = $this->__('E-mail only');
        }
        elseif ($type == 'collector') {
            $methods[1] = $this->__('By Collector regular mail');
            $methods[3] = $this->__('By Collector regular mail and email');
        }
        return $methods;
    }

    public function getActivationOption() {
        return array(
            0 => $this->__('Standard'),
            1 => $this->__('Immediate'),
            //2 => $this->__('Pre-Paid invoice. The purchase will be activated first when an invoice is paid. Not used at the moment.'),
        );
    }

    public function getCountryCode($storeId = null) {
        return Mage::getStoreConfig('general/country/default', $storeId);
    }

    public function isPartial($object) {
        $origList = array();
        if ($object instanceof Mage_Sales_Model_Order_Creditmemo) {
            foreach ($object->getInvoice()->getAllItems() as $item) {
                $origList[$item->getOrderItemId()] = $item->getQty();
            }
        }
        elseif ($object instanceof Mage_Sales_Model_Order_Invoice) {
            foreach ($object->getOrder()->getAllItems() as $item) {
                $origList[$item->getId()] = $item->getQtyOrdered();
            }
        }
       
        $objectList = $object->getAllItems();
        
        if (count($objectList) != count($origList)) {
            return true;
        }
        
        foreach ($objectList as $item) {
            if (isset($origList[$item->getOrderItemId()])) {
                if ($origList[$item->getOrderItemId()] - $item->getQty() > 0) {
                    return true;
                }
            }
        }
        return false;
    }
    
    public function isCellPhoneNumber($number) {
        if (strlen($number) == 8 AND (substr($number, 0, 1) == '9' OR substr($number, 0, 1) == '4')) {
            return true;
        }
        return false;
    }
    
    public function getPaymentReference($invoiceNo) {
        $collectorInvoice = Mage::getModel('collectorbank/invoice')->load($invoiceNo, 'invoice_no');
        return $collectorInvoice->getPaymenReference();
    }
}
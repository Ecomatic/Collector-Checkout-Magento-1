<?php
class Ecomatic_Collectorbank_Block_Checkout_Onepage_Link extends Mage_Checkout_Block_Onepage_Link
{
    public function getCheckoutUrl()
    {
        if (!Mage::getStoreConfig('ecomatic_collectorbank/general/active')){
            return parent::getCheckoutUrl();
        }
        return Mage::getBaseUrl() . 'collectorcheckout';
    }
}

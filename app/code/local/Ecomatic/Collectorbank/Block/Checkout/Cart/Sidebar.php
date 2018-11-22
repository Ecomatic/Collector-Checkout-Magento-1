<?php
class Ecomatic_Collectorbank_Block_Checkout_Cart_Sidebar extends Mage_Checkout_Block_Cart_Sidebar
{
    public function getCheckoutUrl()
    {
        if (!Mage::getStoreConfig('ecomatic_collectorbank/general/active')){
            return parent::getCheckoutUrl();
        }
        return Mage::getBaseUrl() . 'collectorcheckout';
    }
}

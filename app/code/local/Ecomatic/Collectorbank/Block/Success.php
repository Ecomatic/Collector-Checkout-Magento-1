<?php   
class Ecomatic_Collectorbank_Block_Success extends Mage_Core_Block_Template{   


	public function _prepareLayout()
    {
        $quote = Mage::getSingleton('checkout/cart')->getQuote();
        $quote->setData('is_active', 0);
        $quote->save();
		return parent::_prepareLayout();
    }


}
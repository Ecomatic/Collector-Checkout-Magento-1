<?php

class Ecomatic_Collectorbank_Model_Source_Deliverymethodmerchant
{
    public function toOptionArray() {
      $helper = Mage::helper('collectorbank/invoiceservice');
        
      $arr = array();
      foreach ($helper->getDeliveryMethods('merchant') as $k=>$v) {
          $arr[] = array('value'=>$k, 'label'=>$v);
      }
      return $arr;
    }
}

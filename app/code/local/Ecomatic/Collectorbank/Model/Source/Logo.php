<?php

class Ecomatic_Collectorbank_Model_Source_Logo
{
    public function toOptionArray() {
      $helper = Mage::helper('collectorbank');
        
      $arr = array();
      foreach ($helper->getLogos() as $k=>$v) {
          $arr[] = array('value'=>$k, 'label'=>$v);
      }
      return $arr;
    }
}

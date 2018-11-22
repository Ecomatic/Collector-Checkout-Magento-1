<?php

class Ecomatic_Collectorbank_Model_Source_Defaultshippingmethods
{
	
	public function toOptionArray()
	{
		$methods = Mage::getSingleton('shipping/config')->getActiveCarriers();
				$options = array();

				foreach($methods as $_code => $_method)
				{
					if ($methods = $_method->getAllowedMethods()){
						foreach ($methods as $_mcode => $_mname){
							$code = $_code . '_' . $_mcode;
							$title = $_mname;

							if(!$title = Mage::getStoreConfig("carriers/$_code/title"))
								$title = $_code;

							$options[] = array(
								'value' => $code,
								'label' => $title. ' (' . $_mcode.')'
							);
						}
					}
			
				}

		return $options; // This array will have all the active shipping methods
	} 
}
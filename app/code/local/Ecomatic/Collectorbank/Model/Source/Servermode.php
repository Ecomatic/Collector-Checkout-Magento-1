<?php

class Ecomatic_Collectorbank_Model_Source_Servermode
{
	public function toOptionArray()
	{
		return array(
			array(
				'label' => 'Live',
				'value' => Ecomatic_Collectorbank_Model_Config::SERVER_MODE_LIVE
			),
			array(
				'label' => 'Test',
				'value' => Ecomatic_Collectorbank_Model_Config::SERVER_MODE_DEMO
			)
		);
	}
}

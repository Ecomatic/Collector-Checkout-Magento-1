<?php

class Ecomatic_Collectorbank_Model_Source_Deniedorderstatus extends Mage_Adminhtml_Model_System_Config_Source_Order_Status
{
    public function toOptionArray() {
        /*
		    const STATE_PENDING_PAYMENT = 'pending_payment';
			const STATE_PROCESSING      = 'processing';
			const STATE_COMPLETE        = 'complete';
			const STATE_CLOSED          = 'closed';
			const STATE_CANCELED        = 'canceled';
			const STATE_HOLDED          = 'holded';
			const STATE_PAYMENT_REVIEW  = 'payment_review';
		*/
        $parent = Array();
		$parent[] = array(
           'value' => 'canceled',
           'label' => 'Canceled'
        );
        return $parent;
    }
}
<?php

$installer = $this;
try {
    $installer->startSetup();
//		ALTER TABLE  `".$this->getTable('sales/order')."` ADD  `fee_amount_invoiced` DECIMAL( 10, 2 ) NOT NULL;
    $installer->run("

		ALTER TABLE  `" . $this->getTable('sales/order') . "` ADD  `base_fee_amount_invoiced` DECIMAL( 10, 2 ) NOT NULL;

	");
}
catch (Exception $e){}
$installer->endSetup();
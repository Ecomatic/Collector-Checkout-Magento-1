<?php

$installer = new Mage_Sales_Model_Resource_Setup('core_setup');
/**
 * Add 'custom_attribute' attribute for entities
 */
try {
    $items = array(
        'quote',
        'order'
    );


    /* $options1 = array(
        'type'     => Varien_Db_Ddl_Table::TYPE_TEXT,
        'visible'  => true,
        'required' => false
    ); */

    $options = array(
        'type' => Varien_Db_Ddl_Table::TYPE_VARCHAR,
        'visible' => true,
        'required' => false
    );


    foreach ($items as $item) {
        $installer->addAttribute($item, 'coll_customer_type', $options);
        $installer->addAttribute($item, 'coll_business_customer', $options);
        $installer->addAttribute($item, 'coll_status', $options);
        $installer->addAttribute($item, 'coll_purchase_identifier', $options);
        $installer->addAttribute($item, 'coll_total_amount', $options);
    }
}
catch (Exception $e){}

$installer->endSetup(); 
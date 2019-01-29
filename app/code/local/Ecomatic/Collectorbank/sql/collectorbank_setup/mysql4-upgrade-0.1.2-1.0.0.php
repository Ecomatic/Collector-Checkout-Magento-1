<?php
$installer = new Mage_Sales_Model_Resource_Setup('core_setup');
/**
 * Add 'custom_attribute' attribute for entities
 */
$items = array(
    'quote',
    'order'
);

$optionsVarChar = array(
    'type'     => Varien_Db_Ddl_Table::TYPE_VARCHAR,
    'visible'  => true,
    'required' => false
);

$optionsSmallInt = array(
    'type' => Varien_Db_Ddl_Table::TYPE_SMALLINT,
    'visible' => true,
    'required' => false
);

foreach ($items as $item) {
    $installer->addAttribute($item, 'collector_public_token', $optionsVarChar);
    $installer->addAttribute($item, 'collector_response', $optionsVarChar);
    $installer->addAttribute($item, 'newsletter_signup', $optionsSmallInt);
    $installer->addAttribute($item, 'is_iframe', $optionsSmallInt);
    $installer->addAttribute($item, 'shown_success_page', $optionsSmallInt);
}


$installer->endSetup();
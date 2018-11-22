<?php
class Ecomatic_Collectorbank_Block_Adminhtml_Form_Field_Campaign extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    /**
     * Prepare to render
     */
    public function _prepareToRender() {
        $this->addColumn('label', array(
            'label' => Mage::helper('collectorbank')->__('Label'),
            'style' => 'width:200px',
        ));
        $this->addColumn('value', array(
            'label' => Mage::helper('collectorbank')->__('Campaign ID'),
            'style' => 'width:90%',
        ));
        $this->addColumn('position', array(
           'label' => Mage::helper('collectorbank')->__('Position'),
           'style' => 'width:90%',
        ));

        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('collectorbank')->__('Add campaign');
    }
}

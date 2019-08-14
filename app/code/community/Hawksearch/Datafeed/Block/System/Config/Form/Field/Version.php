<?php
/**
 * Copyright (c) 2013 Hawksearch (www.hawksearch.com) - All Rights Reserved
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 */
 
class Hawksearch_Datafeed_Block_System_Config_Form_Field_Version extends Mage_Adminhtml_Block_System_Config_Form_Field //extends Varien_Data_Form_Element_Abstract
{

    public function getElementHtml() {
	
        $modules = Mage::getConfig()->getNode('modules')->children();
        $info = $modules->Hawksearch_Datafeed->asArray();

        return isset($info['version']) ? $info['version'] : '';
    }
	protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {
		return $this->getElementHtml();
	}
}

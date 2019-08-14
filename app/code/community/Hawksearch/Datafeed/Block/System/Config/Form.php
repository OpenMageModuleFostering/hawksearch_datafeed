<?php
/**
 * Copyright (c) 2013 Hawksearch (www.hawksearch.com) - All Rights Reserved
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 */
 
class Hawksearch_Datafeed_Block_System_Config_Form extends Mage_Adminhtml_Block_System_Config_Form {
	 
    protected function _getAdditionalElementTypes()
    {
        $types = parent::_getAdditionalElementTypes();
        $types["version"] = Mage::getConfig()->getBlockClassName('hawksearch_datafeed/system_config_form_field_version');
        return $types;
    }
    
}
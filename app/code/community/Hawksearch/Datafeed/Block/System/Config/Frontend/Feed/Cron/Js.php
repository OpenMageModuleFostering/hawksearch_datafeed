<?php
/**
 * Copyright (c) 2013 Hawksearch (www.hawksearch.com) - All Rights Reserved
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 */
 
class Hawksearch_Datafeed_Block_System_Config_Frontend_Feed_Cron_Js extends Mage_Adminhtml_Block_Template {

    /**
     * Sets javascript template to be included in the adminhtml js text_list block
     */
    protected function _construct() {
        parent::_construct();
        $this->setTemplate('hawksearch/datafeed/validate.phtml');
    }

    /**
     * Returns the cron string validation url
     *
     * @return string
     */
    public function getValidateUrl() {
		return Mage::getModel('adminhtml/url')->getUrl(
			'adminhtml/hawkdatagenerate/validateCronString',
			array(
				'_secure' => true,
				'_store' => Mage_Core_Model_App::ADMIN_STORE_ID
			)
		);
    }
}
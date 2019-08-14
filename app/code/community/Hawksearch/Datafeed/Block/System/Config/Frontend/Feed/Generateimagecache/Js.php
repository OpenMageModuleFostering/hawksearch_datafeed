<?php
/**
 * Copyright (c) 2013 Hawksearch (www.hawksearch.com) - All Rights Reserved
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 */
 
class Hawksearch_Datafeed_Block_System_Config_Frontend_Feed_Generateimagecache_Js extends Mage_Adminhtml_Block_Template {

    /**
     * Sets javascript template to be included in the adminhtml js text_list block
     */
    protected function _construct() {
        parent::_construct();
        $this->setTemplate('hawksearch/datafeed/generateimagecache/js.phtml');
    }

    /**
     * Returns the run all feeds async url
     *
     * @return string
     */
	public function getGenerateUrl() {
		return Mage::getModel('adminhtml/url')->getUrl(
			'adminhtml/hawkdatagenerate/runImageCacheGeneration',
			array(
				'_secure' => true,
				'_store' => Mage_Core_Model_App::ADMIN_STORE_ID
			)
		);
	}

	public function getGenerateUrlold() {
		return Mage::getUrl('hawksearch_datafeed/search/runImageCacheGeneration/',
			array(
				'_secure' => true,
				'_store' => Mage_Core_Model_App::ADMIN_STORE_ID
			));
    }
}
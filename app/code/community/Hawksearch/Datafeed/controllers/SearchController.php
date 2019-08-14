<?php
/**
 * Copyright (c) 2013 Hawksearch (www.hawksearch.com) - All Rights Reserved
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 */

class Hawksearch_Datafeed_SearchController extends Mage_Core_Controller_Front_Action {
    
    public function templateAction() {
        $this->loadLayout();
        $this->renderLayout();
    }
	
	/**
     * API Call for Image CacheKey to get images from cache on auto resize. 
     */
    public function getCacheKeyAction() {
		$response = array("error" => false);
        try {
			/** @var Mage_Catalog_Model_Resource_Product_Collection $coll */
			$coll = Mage::getModel('catalog/product')->getCollection();
			$coll->addAttributeToSelect('small_image');
			$coll->addAttributeToFilter('small_image', array(
				'notnull' => true
			));
			$coll->getSelect()->limit(100);
			$item = $coll->getLastItem();
			$path = (string) Mage::helper('catalog/image')->init($item, 'small_image');
			$imageArray = explode("/", $path);
			$cache_key = "";
			foreach($imageArray as $part) {
				if(preg_match('/[0-9a-fA-F]{32}/', $part)) {
					$cache_key = $part;
				}
			}
			
			$response['cache_key'] = $cache_key;
			$response['date_time'] = date('Y-m-d H:i:s');
        }
        catch (Exception $e) {
            $response['error'] = $e->getMessage();
        }
        $this->getResponse()
                ->setHeader("Content-Type", "application/json")
                ->setBody(json_encode($response));
	}

    /**
     * Asynchronous posting to feed generation url for each store. 
     */
    public function runFeedGenerationAction() {
        $response = array("error" => false);

        try {
			$disabledFuncs = explode(',', ini_get('disable_functions'));
			$isShellDisabled = is_array($disabledFuncs) ? in_array('shell_exec', $disabledFuncs) : true;
			$isShellDisabled = (stripos(PHP_OS, 'win') === false) ? $isShellDisabled : true;

			if($isShellDisabled) {
				$response['error'] = 'This installation cannot run one off feed generations. Must use cron.';
			} else {
				Mage::helper('hawksearch_datafeed/feed')->generateFeedsForAllStores();
			}
        }
        catch (Exception $e) {
            Mage::logException($e);
            $response['error'] = "An unknown error occurred.";
        }
        $this->getResponse()
                ->setHeader("Content-Type", "application/json")
                ->setBody(json_encode($response));
    }
	
    /**
     * Refreshes image cache based on passed in store id. Defaults store id to default store 
     */
    public function runImageCacheGenerationAction() {
        $response = array("error" => false);
        try {
            $storeId = $this->getRequest()->getParam("storeId");

            if (!$storeId) {
                $storeId = Mage::app()->getDefaultStoreView()->getId();
            }
			$disabledFuncs = explode(',', ini_get('disable_functions'));
			$isShellDisabled = is_array($disabledFuncs) ? in_array('shell_exec', $disabledFuncs) : true;
			$isShellDisabled = (stripos(PHP_OS, 'win') === false) ? $isShellDisabled : true;

			if($isShellDisabled) {
				$response['error'] = 'This installation cannot run one-off cache generations. Must use cron.';
			} else {
				Mage::helper('hawksearch_datafeed/feed')->refreshImageCache($storeId);
			}
        }
        catch (Exception $e) {
            Mage::logException($e);
			$response['error'] = "An unknown error occurred.";
        }
		$this->getResponse()
			->setHeader("Content-Type", "application/json")
			->setBody(json_encode($response));
    }
}
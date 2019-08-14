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
	
	public function indexAction () {
         $coupon_code = $this->getRequest()->getParam('coupon_code'); //to automatically apply a coupon code
         if ($coupon_code != '') 
            {
                Mage::getSingleton("checkout/session")->setData("coupon_code",$coupon_code);
                Mage::getSingleton('checkout/cart')->getQuote()->setCouponCode($coupon_code)->save();
                Mage::getSingleton('core/session')->addSuccess($this->__('Coupon was automatically applied'));
            }
         $product_id = $this->getRequest()->getParam('product');
            $qty = $this->getRequest()->getParam('qty');  //used if your qty is not hard coded
            $cart = Mage::getModel('checkout/cart');
            $cart->init();
            if ($product_id == '') {
                $this->_redirect('/');
            }
            $productModel = Mage::getModel('catalog/product')->load($product_id);
            if (TRUE) {
                try
                {
                   $cart->addProduct($productModel, array('qty' => '1'));  //qty is hard coded
                }
                catch (Exception $e) {
                   $this->_redirect('/');
                }
            }
            $cart->save();
            if ($this->getRequest()->isXmlHttpRequest()) {
               exit('1');
            }
            $this->_redirect('checkout/cart');
    }
	/**
     * API Call for Image CacheKey to get images from cache on auto resize. 
     */
    public function getCacheKeyAction() {
		$response = array("error" => false);
        try {
			$resource = Mage::getSingleton('core/resource');
			$read = $resource->getConnection('core_read');
			$productCatalogTable = (string)Mage::getConfig()->getTablePrefix() . 'catalog_product_entity';
			
			$select_qry =$read->query("SELECT entity_id FROM ".$productCatalogTable." LIMIT 1");
			$newrow = $select_qry->fetch();
			$entity_id = $newrow['entity_id'];
			$product = Mage::getModel('catalog/product')->load($entity_id);
			$full_path_url = Mage::helper('catalog/image')->init($product, 'thumbnail');
			$imageArray = explode("/", $full_path_url);
			
			if(isset($imageArray[9])) {
				$cache_key = $imageArray[9];
			} else {
				$cache_key = "";
			}
			
			$response['cache_key'] = $cache_key;
			$response['date_time'] = date('Y-m-d H:i:s');
        }
        catch (Exception $e) {
            $response['error'] = $e->getMessage();
        }
        catch (Exception $e) {
            Mage::logException($e);
            $response['error'] = "An unknown error occurred.";
        }
        $this->getResponse()
                ->setHeader("Content-Type", "application/json")
                ->setBody(json_encode($response));
	}

	/*public function getFormKeyAction() {
	
			$response = array("error" => false);
			try {
				
				$formKey = Mage::getSingleton('core/session')->getFormKey();
				$formGuid = Mage::helper('core/url')->getEncodedUrl();
				$response['form_guid'] = $formGuid;
				$response['form_key'] = $formKey;
				$response['date_time'] = date('Y-m-d H:i:s');
			}
			catch (Exception $e) {
				$response['error'] = $e->getMessage();
			}
			catch (Exception $e) {
				Mage::logException($e);
				$response['error'] = "An unknown error occurred.";
			}
			$this->getResponse()
					->setHeader("Content-Type", "application/json")
					->setBody(json_encode($response));
	
	}*/
	
    /**
     * Asynchronous posting to feed generation url for each store. 
     */
    public function runFeedGenerationAction() {
        $response = array("error" => false);

        try {
            Mage::helper('hawksearch_datafeed/feed')->generateFeedsForAllStores();
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

            Mage::getModel('hawksearch_datafeed/feed')->setData('store_id', $storeId)->refreshImageCache();
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
     * Generates a feed based on passed in store id. Defaults store id to default store 
     */
    public function generateFeedAction() {
        $response = "";
        try {
            $storeId = $this->getRequest()->getParam("storeId");

            if (!$storeId) {
                $storeId = Mage::app()->getDefaultStoreView()->getId();
            }
            Mage::getModel('hawksearch_datafeed/feed')->setData('store_id', $storeId)->generateFeed(true);            
        }
        catch (Exception $e) {
            Mage::logException($e);
            $response = "An unknown error occurred.";
        }
        $this->getResponse()->setBody($response);
    }
}
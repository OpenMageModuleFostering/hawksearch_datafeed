<?php
/**
 * Created by PhpStorm.
 * User: magentouser
 * Date: 3/11/15
 * Time: 1:47 PM
 */

class Hawksearch_Datafeed_Adminhtml_HawkdatagenerateController
	extends Mage_Adminhtml_Controller_Action {

	public function validateCronStringAction() {
		$response = array('valid' => false);

		$cron = Mage::getSingleton('hawksearch_datafeed/cron');
		if($cron->isValidCronString($this->getRequest()->getParam('cronString'))){
			$response['valid'] = true;
		}
		$this->getResponse()
			->setHeader('Content-Type', 'application/json')
			->setBody(json_encode($response));
	}

	public function runFeedGenerationAction() {
		$response = array('error' => false);
		try {
			$disabledFuncs = explode(',', ini_get('disable_functions'));
			$isShellDisabled = is_array($disabledFuncs) ? in_array('shell_exec', $disabledFuncs) : true;
			$isShellDisabled = (stripos(PHP_OS, 'win') === false) ? $isShellDisabled : true;

			if($isShellDisabled) {
				$response['error'] = 'This installation cannot run one off feed generation because the PHP function "shell_exec" has been disabled. Please use cron.';
			} else {
				$helper = Mage::helper('hawksearch_datafeed/feed');
				if(strtolower($this->getRequest()->getParam('force')) == 'true') {
					$helper->RemoveFeedLocks();
				}
				$helper->generateFeedsForAllStores();
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
	 * Refreshes image cache.
	 */
	public function runImageCacheGenerationAction() {
		$response = array("error" => false);
		try {
			$disabledFuncs = explode(',', ini_get('disable_functions'));
			$isShellDisabled = is_array($disabledFuncs) ? in_array('shell_exec', $disabledFuncs) : true;
			$isShellDisabled = (stripos(PHP_OS, 'win') === false) ? $isShellDisabled : true;

			if($isShellDisabled) {
				$response['error'] = 'This installation cannot run one-off cache generations. Must use cron.';
			} else {
				$helper = Mage::helper('hawksearch_datafeed/feed');
				if(strtolower($this->getRequest()->getParam('force')) == 'true') {
					$helper->RemoveFeedLocks();
				}
				$helper->refreshImageCache();
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


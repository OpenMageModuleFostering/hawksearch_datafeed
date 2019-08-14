<?php

/**
 * Copyright (c) 2013 Hawksearch (www.hawksearch.com) - All Rights Reserved
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 */
class Hawksearch_Datafeed_Block_System_Config_Frontend_Feed_Next extends Mage_Adminhtml_Block_System_Config_Form_Field {

	/**
	 * Returns the config xml set job code for the cron job
	 *
	 * @return string
	 */
	protected function _getHawkSearchCronJobCode() {

		$jobCode = Mage::getConfig()->getNode('crontab/hawksearch_datafeed/job_code');

		if (!$jobCode) {
			if (Mage::helper('hawksearch_datafeed/data')->isLoggingEnabled()) {
				Mage::log("No cron job code set for hawksearch_datafeed cron job in config xml.", null, 'hawksearch_errors.log');
			}
			Mage::throwException("No cron job code set for hawksearch_datafeed cron job in config xml.");
		}
		return $jobCode;
	}

	/**
	 * Renders the next scheduled cron time.
	 *
	 * @param Varien_Data_Form_Element_Abstract $element
	 * @return string
	 */
	protected function  _getElementHtml(Varien_Data_Form_Element_Abstract $element) {
//		$helper = Mage::helper('hawksearch_datafeed');
//		$scheduledAt = $helper->getNextRunDateFromCronTime();

		$scheduledAt = Mage::getSingleton('hawksearch_datafeed/system_config_backend_cron')->getNextRuntime();

		return sprintf('<span id="%s">%s</span>', $element->getHtmlId(), $scheduledAt);
	}


}
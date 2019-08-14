<?php
/**
 * Copyright (c) 2013 Hawksearch (www.hawksearch.com) - All Rights Reserved
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 */
 
class Hawksearch_Datafeed_Model_System_Config_Backend_Cron extends Mage_Core_Model_Config_Data {
    
    const CRON_STRING_PATH = 'crontab/jobs/hawksearch_datafeed/schedule/cron_expr';
    const CRON_MODEL_PATH = 'crontab/jobs/hawksearch_datafeed/run/model';

	protected function _afterSave(){
		try {
			// hawksearch_datafeed/feed/cron_string
			// hawksearch_datafeed/imagecache/cron_string
			Mage::getModel('core/config_data')
				->load(self::CRON_STRING_PATH, 'path')
				->setValue(trim($this->getValue()))
				->setPath(self::CRON_STRING_PATH)
				->save();
			Mage::getModel('core/config_data')
				->load(self::CRON_MODEL_PATH, 'path')
				->setValue((string) Mage::getConfig()->getNode(self::CRON_MODEL_PATH))
				->setPath(self::CRON_MODEL_PATH)
				->save();
		} catch (Exception $e) {
			throw new Exception(Mage::helper('cron')->__('Unable to save the cron expression.'));
		}

	}
}

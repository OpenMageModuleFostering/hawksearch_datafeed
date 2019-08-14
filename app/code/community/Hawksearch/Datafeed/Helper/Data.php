<?php
/**
 * Copyright (c) 2013 Hawksearch (www.hawksearch.com) - All Rights Reserved
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 */

class Hawksearch_Datafeed_Helper_Data extends Mage_Core_Helper_Abstract {

    const SECTION = "hawksearch_datafeed/";
    const GENERAL_GROUP = "general/";
    const FEED_GROUP = "feed/";
    const CRON_GROUP = "cron/";

    /**
     * Returns true/false on whether or not the module is enabled
     *
     * @return boolean
     */
	 
    public function isEnabled($store_id = 0) {
        return (bool) Mage::app()->getStore($store_id)->getConfig(self::SECTION . self::GENERAL_GROUP . 'enabled');
    }

    /**
     * Returns an integer which is the log level
     *
     * @return int
     */
    public function isLoggingEnabled($store_id = 0) {
        return (int) Mage::app()->getStore($store_id)->getConfig(self::SECTION . self::GENERAL_GROUP . 'logging_enabled');
    }    
  
    /**
     * Returns true/false on whether or not to include out of stock items in feed
     *
     * @return boolean
     */
    public function isIncludeOutOfStockItems($store_id = 0) {
        return (bool) Mage::app()->getStore($store_id)->getConfig(self::SECTION . self::FEED_GROUP . "stockstatus");
    }    
    
    /**
     * Returns true/false on whether or not to include disabled categories in feed
     *
     * @return boolean
     */
    public function isIncludeDisabledCategories($store_id = 0) {
        return (bool) Mage::app()->getStore($store_id)->getConfig(self::SECTION . self::FEED_GROUP . "categorystatus");
    }  
	/**
     * Returns the Batch Limit for Product Export Feed
     *
     * @return string
     */
    public function getBatchLimit() {
        return Mage::getStoreConfig(self::SECTION . self::FEED_GROUP . "batch_limit");
    }

     /**
     * Returns the Image Width
     *
     * @return string
     */
    public function getImageWidth() {
        return Mage::getStoreConfig(self::SECTION . self::FEED_GROUP . "image_width");
    }

     /**
     * Returns the Image Height
     *
     * @return string
     */
    public function getImageHeight() {
        return Mage::getStoreConfig(self::SECTION . self::FEED_GROUP . "image_height");
    }
	
	/**
     * Returns the Brand Attribute Value for Product Export Feed
     *
     * @return string
     */
    public function getBrandAttribute() {
        return Mage::getStoreConfig(self::SECTION . self::FEED_GROUP . "brand_attribute");
    }

	public function getAllowDisabledAttribute(){
		$res = Mage::getStoreConfig(self::SECTION . self::FEED_GROUP . "itemstatus");
		if(isset($res) && $res == 0) {
			return false;
		}
		return true;
	}
    /**
     * Returns the email to send notifications to when the cron runs
     *
     * @return string
     */
    public function getCronEmail() {
        return Mage::getStoreConfig(self::SECTION . self::CRON_GROUP . "email");
    }

    /**
     * Returns the frequency that the cron should run.
     *
     * @return string
     */
    public function getCronFrequency() {
        return Mage::getStoreConfig(self::SECTION . self::CRON_GROUP . "frequency");
    }

    /**
     * Returns the time of day that the cron should run at.
     *
     * @return string
     */
    public function getCronTime() {
        return Mage::getStoreConfig(self::SECTION . self::CRON_GROUP . "time");
    }

    /**
     * Return crontab formatted time for cron set time.
     *
     * @param string $frequency
     * @param array $time
     * @return string
     */
    public function getCronTimeAsCrontab($crontime) {
        
        $timescheduled = "";
        switch ($crontime) {
            case "every_minute" :
                $timescheduled = "* * * * *";
                break;
            case "every_5min" :
                $timescheduled = "*/5 * * * *";
                break;
            case "every_15min" :
                $timescheduled = "*/15 * * * *";
                break;
            case "every_30min" :
                $timescheduled = "0,30 * * * *";
                break;
            case "every_hour" :
                $timescheduled = "0 * * * *";
                break;
            case "8_hours" :
                $timescheduled = "0 */8 * * *";
                break;
            case "daily" :
                $timescheduled = "0 0 * * *";
                break;
            default :
                $timescheduled = "";
        }
        return $timescheduled;
    }

    /**
     * Gets the next run date based on cron settings.
     *
     * @return Zend_Date
     */
    public function getNextRunDateFromCronTime() {
        $now = Mage::app()->getLocale()->date();
        $frequency = $this->getCronFrequency();
        list($hours, $minutes, $seconds) = explode(',', $this->getCronTime());

        $time = Mage::app()->getLocale()->date();
        $time->setHour($hours)->setMinute($minutes)->setSecond($seconds);

        //Parse through frequencies
        switch ($frequency) {
            case "D":
                if ($time->compare($now) == -1) {
                    $time->addDay(1);
                }
                break;
            case "W":
                $time->setWeekday(7);
                if ($time->compare($now) == -1) {
                    $time->addWeek(1);
                }
                break;
            case "M":
                $time->setDay(1);
                if ($time->compare($now) == -1) {
                    $time->addMonth(1);
                }
                break;
        }

        return $time;
    }

	/**
	 * Gets the output file delimiter character
	 *
	 * @return string
	 */
	public function getOutputFileDelimiter() {
		$v = Mage::getStoreConfig(self::SECTION . self::FEED_GROUP . 'output_file_delimiter');
		$o = array('tab' => "\t", 'comma' => ",");
		return isset($o[$v]) ? $o[$v] : "\t";
	}
	public function getBufferSize() {
		$size = Mage::getStoreConfig(self::SECTION . self::FEED_GROUP . 'buffer_size');
		return is_numeric($size) ? $size : null;
	}
	public function getOutputFileExtension() {
		return Mage::getStoreConfig(self::SECTION . self::FEED_GROUP . 'output_file_ext');
	}
	public function getFeedFilePath() {
		return Mage::getBaseDir('base') . DS . Mage::getStoreConfig(self::SECTION . self::FEED_GROUP . 'feed_path');
	}
    function mtime() {
        list($usec, $sec) = explode(" ", microtime());
        return ((float) $usec + (float) $sec);
    }


}
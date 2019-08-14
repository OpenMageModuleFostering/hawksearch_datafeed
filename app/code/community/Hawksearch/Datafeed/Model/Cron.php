<?php
/**
 * Copyright (c) 2013 Hawksearch (www.hawksearch.com) - All Rights Reserved
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 */
 
class Hawksearch_Datafeed_Model_Cron {

    /**
     * Generates the feeds and sends email of status when done
     */
	 
    public function generateFeeds() {
        if(!Mage::getStoreConfig('hawksearch_datafeed/cron/disabled'))
        {
            try {
				Mage::getModel('hawksearch_datafeed/feed')->generateFeed();
                $msg = "HawkSeach Feed Generated!";
            }
            catch (Exception $e) {
                $msg = $e->getMessage();
            }
            $this->_sendEmail($msg);
        }
    }

    /**
     * If there is a system config email set, send out the cron notification email.
     */
    protected function _sendEmail($msg) {
        Mage::getModel('hawksearch_datafeed/email')->setData('msg', $msg)->send();
    }
}
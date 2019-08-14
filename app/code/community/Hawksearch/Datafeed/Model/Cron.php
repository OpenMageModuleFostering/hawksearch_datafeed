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
                Mage::helper('hawksearch_datafeed/feed')->generateFeedsForAllStores();
                $msg = "HawkSeach Feed Generated!";
            }
            catch (Exception $e) {
                $msg = $e->getMessage();
            }
            catch (Exception $e) {
                $msg = "Unknown Error: {$e->getMessage()} in {$e->getFile()} on line {$e->getLine()}. Please contact HawkSearch.";
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
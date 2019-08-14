<?php
/**
 * Copyright (c) 2013 Hawksearch (www.hawksearch.com) - All Rights Reserved
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 */
 
//Save cron job to the core config data table

$frequency = Mage::getConfig()->getNode('default/hawksearch_datafeed/cron/frequency');

$time = explode(",", Mage::getConfig()->getNode('default/hawksearch_datafeed/cron/time'));

$cronTab = Mage::helper('hawksearch_datafeed')->getCronTimeAsCrontab($frequency, $time);

Mage::getModel("hawksearch_datafeed/system_config_backend_cron")->saveCronTab($cronTab);
<?php
/**
 * Copyright (c) 2013 Hawksearch (www.hawksearch.com) - All Rights Reserved
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 */

$opts = getopt('r:t:i:');
chdir($opts['r']);
require 'app/Mage.php';

Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
/** @var Mage $app */
$app = Mage::app();

$helper = Mage::helper('hawksearch_datafeed/feed');
$feed = Mage::getModel('hawksearch_datafeed/feed');

if ($helper->thereAreFeedLocks()) {
	Mage::throwException("One or more feeds are being generated. Generation temporarily locked.");
}
if ($helper->CreateFeedLocks()) {
	if (isset($opts['i'])) {
		$feed->refreshImageCache();
	} else {
		$feed->generateFeed();
	}
}
unlink($opts['t']);


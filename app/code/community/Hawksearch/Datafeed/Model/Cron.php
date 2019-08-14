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


	public function isValidCronString($string) {
		$e = preg_split('#\s+#', $string, null, PREG_SPLIT_NO_EMPTY);
		if (sizeof($e) < 5 || sizeof($e) > 6) {
			return false;
		}
		$isValid = $this->testCronPartSimple(0, $e)
			&& $this->testCronPartSimple(1, $e)
			&& $this->testCronPartSimple(2, $e)
			&& $this->testCronPartSimple(3, $e)
			&& $this->testCronPartSimple(4, $e);

		if (!$isValid) {
			return false;
		}
		return true;
	}

	public function testCronPartSimple($p, $e) {
		if ($p === 0) {
			// we only accept a single numeric value for the minute and it must be in range
			if (!ctype_digit($e[$p])) {
				return false;
			}
			if ($e[0] < 0 || $e[0] > 59) {
				return false;
			}
			return true;
		}
		return $this->testCronPart($p, $e);
	}

	public function testCronPart($p, $e) {

		if ($e[$p] === '*') {
			return true;
		}

		foreach (explode(',', $e[$p]) as $v) {
			if (!$this->isValidCronRange($p, $v)) {
				return false;
			}
		}
		return true;
	}

	private function isValidCronRange($p, $v) {
		static $range = array(array(0, 59), array(0, 23), array(1, 31), array(1, 12), array(0, 6));
		//$n = Mage::getSingleton('cron/schedule')->getNumeric($v);

		// steps can be used with ranges
		if (strpos($v, '/') !== false) {
			$ops = explode('/', $v);
			if (count($ops) !== 2) {
				return false;
			}
			// step must be digit
			if (!ctype_digit($ops[1])) {
				return false;
			}
			$v = $ops[0];
		}
		if (strpos($v, '-') !== false) {
			$ops = explode('-', $v);
			if(count($ops) !== 2){
				return false;
			}
			if ($ops[0] > $ops[1] || $ops[0] < $range[$p][0] || $ops[0] > $range[$p][1] || $ops[1] < $range[$p][0] || $ops[1] > $range[$p][1]) {
				return false;
			}
		} else {
			$a = Mage::getSingleton('cron/schedule')->getNumeric($v);
			if($a < $range[$p][0] || $a > $range[$p][1]){
				return false;
			}
		}
		return true;
	}

	public function generateImagecache() {
		if(Mage::getStoreConfigFlag('hawksearch_datafeed/imagecache/cron_enable')) {
			try{
				Mage::getModel('hawksearch_datafeed/feed')->refreshImageCache();
				$msg = "Hawksearch Image cache generated!";
			} catch (Exception $e) {
				$msg = "Hawksearch Image cache generation exception: " . $e->getMessage();
			}
			$this->_sendEmail($msg);
		}
	}

	public function generateFeeds() {
		if (Mage::getStoreConfigFlag('hawksearch_datafeed/feed/cron_enable')) {
			try {
				Mage::getModel('hawksearch_datafeed/feed')->generateFeed();
				$msg = "HawkSeach Feed Generated!";
			} catch (Exception $e) {
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
<?php
/**
 * Copyright (c) 2013 Hawksearch (www.hawksearch.com) - All Rights Reserved
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 */
 
class Hawksearch_Datafeed_Helper_Feed {
    
    protected $_feedFilePath = null;
    
    /**
     * Open socket to feed generation url with store id as passed parameter.
     * 
     * @param Mage_Core_Model_Store $store
     * @param array $urlParts
     * @throws Mage_Core_Exception 
     */
    #public function postToGenerateFeed($store, $urlParts) {
    public function postToGenerateFeed($urlParts) {
        $feedSocket = @fsockopen($urlParts['host'], 80, $errNo, $errStr, 10);
                
        if (!$feedSocket) {
           Mage::throwException("Err. #$errNo: Cannot access feed generation uri.");
        }

        #$storeParam = "storeId={$store->getId()}";
        $storeParam = "storeId={1}";
        $storeParamLen = strlen($storeParam);
        $EOL = "\r\n";
		
		$username = Mage::getStoreConfig('hawksearch_datafeed/feed/optional_htaccess_user', Mage::app ()->getStore ());
		$password = Mage::getStoreConfig('hawksearch_datafeed/feed/optional_htaccess_password', Mage::app ()->getStore ());
		
        $request = "POST {$urlParts['path']} HTTP/1.1$EOL";
        $request .= "HOST: {$urlParts['host']}$EOL";
		if($username !="" && $password !="") {
       	 $request .= "Authorization: Basic " . base64_encode("$username:$password") . $EOL;
		}
        $request .= "Content-Length: $storeParamLen$EOL";
        $request .= "Content-Type: application/x-www-form-urlencoded$EOL";
        $request .= "Connection: Close$EOL$EOL";
        $request .= "$storeParam";
		
        $result = fwrite($feedSocket, $request);
        
        if (!$result) {
            Mage::throwException("Error writing to feed generation uri.");
        }
		
        fclose($feedSocket);
    }
    
    /**
     * Returns url that controls feed generation
     * 
     * @return string
     */
	 
    public function getGenerateFeedUrl() {
        $curStore = Mage::app()->getStore();
        Mage::app()->setCurrentStore(1); //default storeID will always be 1
        $myUrl = Mage::getUrl('hawksearch_datafeed/search/generateFeed');
        Mage::app()->setCurrentStore($curStore);       
        return $myUrl;
    }
    
    /**
     * Asynchronously starts a feed generation for each store 
     */
    public function generateFeedsForAllStores() {
        if ($this->thereAreFeedLocks()) {
            Mage::throwException("One or more feeds are being generated. Generation temporarily locked.");
        }
		
		if($this->CreateFeedLocks()) {
			$feedUrl = $this->getGenerateFeedUrl();
			$urlParts = parse_url($feedUrl);
	
			if(Mage::helper('hawksearch_datafeed/data')->isLoggingEnabled()) {
				Mage::log($feedUrl);
				Mage::log($urlParts);
			}
			try
			{
				$this->postToGenerateFeed($urlParts);
				/*
					$stores = Mage::getResourceModel('core/store_collection');
					foreach($stores as $store) {
						$this->postToGenerateFeed($store, $urlParts);
					}
				*/
			}
			catch (Exception $e) {
				Mage::logException($e);
			}
		} else {
            Mage::throwException("Error Generating Feed Locks. Generation temporarily locked.");
		}
    }
    
    /**
     * Returns the feed file path
     * 
     * @return string
     */
    public function getFeedFilePath() {
        if ($this->_feedFilePath === null) {
            $this->_feedFilePath = $this->makeVarPath(array('hawksearch', 'feeds'));
        }
        return $this->_feedFilePath;
    }
    
    /**
     * Create path within var folder if necessary given an array of directory names
     * 
     * @param array $directories
     * @return string 
     */
    public function makeVarPath($directories) {
        $path = Mage::getBaseDir('var');
        foreach ($directories as $dir) {
            $path .= DS . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0777);
            }
        }
        return $path;
    }
    /**
     * Remove locks currently in place
     *
     * @return boolean 
     */
    public function CreateFeedLocks() {
	
        $path = $this->getFeedFilePath();
		$filename = $path . "/hawksearchfeeds.lock";
		$content = date("Y-m-d H:i:s");
		
		if(!file_exists($filename))
		{
			$handle = fopen($filename, "w+");
			fclose($handle);
		}

		if (is_writable($filename)) {
			if (!$handle = fopen($filename, 'w+')) {
				if(Mage::helper('hawksearch_datafeed/data')->isLoggingEnabled()) {
					Mage::log("Cannot open lock file (".$filename.")", null,'hawksearch_errors.log');
				}
				return false;
			}
			if (fwrite($handle, $content) === FALSE) {
				if(Mage::helper('hawksearch_datafeed/data')->isLoggingEnabled()) {
					Mage::log("Cannot write to lock file (".$filename.")", null,'hawksearch_errors.log');
				}
				return false;
			}
			return true;
			fclose($handle);
		}
        return false;
    }
	
    /**
     * Whether or not there are feed generation locks currently in place
     *
     * @return boolean 
     */
    public function thereAreFeedLocks() {
        $path = $this->getFeedFilePath();
        foreach (scandir($path) as $file) {
            $fullFile = $path.DS.$file;
            if (is_file($fullFile) && !is_dir($fullFile) && is_numeric(strpos($file, '.lock'))) {
                return true;
            }
        }
        return false;
    }
	/**
     * Remove locks currently in place
     *
     * @return boolean 
     */
    public function RemoveFeedLocks() {
        $path = $this->getFeedFilePath();
        foreach (scandir($path) as $file) {
            $fullFile = $path.DS.$file;
            if (is_file($fullFile) && !is_dir($fullFile) && is_numeric(strpos($file, '.lock'))) {
				unlink($fullFile);
                return true;
            }
        }
        return false;
    }
	
	public function deleteDir($dirPath) {
		if (! is_dir($dirPath)) {
			throw new InvalidArgumentException("$dirPath must be a directory");
		}
		if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
			$dirPath .= '/';
		}
		$files = glob($dirPath . '*', GLOB_MARK);
		foreach ($files as $file) {
			if (is_dir($file)) {
				self::deleteDir($file);
			} else {
				unlink($file);
			}
		}
		rmdir($dirPath);
   }
    
}
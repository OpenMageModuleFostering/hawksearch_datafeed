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

    public function refreshImageCache($storeid) {
		$tmppath = sys_get_temp_dir();
		$tmpfile = tempnam($tmppath, 'hawkfeed_');

		$parts = explode(DIRECTORY_SEPARATOR, __FILE__);
		array_pop($parts);
		$parts[] = 'runfeed.php';
		$runfile = implode(DIRECTORY_SEPARATOR, $parts);
		$root = getcwd();

		$f = fopen($tmpfile, 'w');
		fwrite($f, '#!/bin/sh' . "\n");
		$phpbin = PHP_BINDIR . DIRECTORY_SEPARATOR . "php";

		fwrite($f, "$phpbin $runfile -i $storeid -r $root -t $tmpfile\n");

		shell_exec("/bin/sh $tmpfile > /dev/null 2>&1 &");

	}
    /**
     * Asynchronously starts a feed generation for each store 
     */
    public function generateFeedsForAllStores() {
		$tmppath = sys_get_temp_dir();
		$tmpfile = tempnam($tmppath, 'hawkfeed_');

		$parts = explode(DIRECTORY_SEPARATOR, __FILE__);
		array_pop($parts);
		$parts[] = 'runfeed.php';
		$runfile = implode(DIRECTORY_SEPARATOR, $parts);
		$root = getcwd();

		$f = fopen($tmpfile, 'w');
		fwrite($f, '#!/bin/sh' . "\n");
		$phpbin = PHP_BINDIR . DIRECTORY_SEPARATOR . "php";

		fwrite($f, "$phpbin $runfile -r $root -t $tmpfile\n");

		shell_exec("/bin/sh $tmpfile > /dev/null 2>&1 &");

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
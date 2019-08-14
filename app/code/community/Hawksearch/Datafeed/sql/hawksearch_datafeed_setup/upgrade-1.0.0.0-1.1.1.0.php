<?php
/**
 * Created by PhpStorm.
 * User: magentouser
 * Date: 2/17/15
 * Time: 2:01 PM
 */

$installer = $this;

$installer->startSetup();

//$installer->getConnection()->modifyColumn(
//	$this->getTable('table/name'), 'datetime', 'DATETIME'
//);
//
//
//
//$cfg = Mage::getModel('core/config_data')
//	->load('path/to/var', 'path');
//if($cfg->getId()) {
//	$cfg->setPath('path/to/var')
//		->save();
//}


$installer->endSetup();

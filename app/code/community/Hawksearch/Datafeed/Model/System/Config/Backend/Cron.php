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

    const XML_PATH_CRON_DISABLED        = 'groups/cron/fields/disabled/value';		
    const XML_PATH_CRON_TIME            = 'groups/cron/fields/time/value';
    const XML_PATH_CRON_FREQUENCY       = 'groups/cron/fields/frequency/value';	

    /**
     * When frequency system configuration saves, save the values from the frequency and time as a cron string to a parsable path that the crontab will pick up
     */
    protected function _afterSave()
    {
        $disabled    = $this->getData(self::XML_PATH_CRON_DISABLED);
        $frequncy    = $this->getData(self::XML_PATH_CRON_FREQUENCY);
        $time        = $this->getData(self::XML_PATH_CRON_TIME);

        $frequencyDaily     = Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_DAILY;
        $frequencyWeekly    = Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_WEEKLY;
        $frequencyMonthly   = Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_MONTHLY;
        $cronDayOfWeek      = date('N');

        $cronExprArray      = array(
            intval($time[1]),                                   # Minute
            intval($time[0]),                                   # Hour
            ($frequncy == $frequencyMonthly) ? '1' : '*',       # Day of the Month
            '*',                                                # Month of the Year
            ($frequncy == $frequencyWeekly) ? '1' : '*',         # Day of the Week
        );

        $cronExprString     = join(' ', $cronExprArray);

            try {
            Mage::getModel('core/config_data')
                ->load(self::CRON_STRING_PATH, 'path')
                ->setValue($cronExprString)
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
	

    /**
     * Saves the necessary core config data entries for the cron to pull them from the database
     */
    public function saveCronTab($cronTab) {
        Mage::getModel('core/config_data')
            ->load(self::CRON_STRING_PATH, 'path')
            ->setValue($cronTab)
            ->setPath(self::CRON_STRING_PATH)
            ->save();
        Mage::getModel('core/config_data')
            ->load(self::CRON_MODEL_PATH, 'path')
            ->setValue((string) Mage::getConfig()->getNode(self::CRON_MODEL_PATH))
            ->setPath(self::CRON_MODEL_PATH)
            ->save();
    }

}

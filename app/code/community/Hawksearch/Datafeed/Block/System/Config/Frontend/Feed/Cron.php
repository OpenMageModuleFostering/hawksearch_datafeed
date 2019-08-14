<?php

class Hawksearch_Datafeed_Block_System_Config_Frontend_Feed_Cron extends Mage_Adminhtml_Block_System_Config_Form_Field {

	protected $_buttonId = "generate_feed_button";

	protected function _prepareLayout() {
		$block = $this->getLayout()->createBlock("hawksearch_datafeed/system_config_frontend_feed_cron_js");

		$this->getLayout()->getBlock('js')->append($block);
		return parent::_prepareLayout();
	}
}
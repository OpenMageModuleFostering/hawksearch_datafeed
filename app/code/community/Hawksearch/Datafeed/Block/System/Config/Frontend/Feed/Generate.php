<?php
/**
 * Copyright (c) 2013 Hawksearch (www.hawksearch.com) - All Rights Reserved
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 */
 

class Hawksearch_Datafeed_Block_System_Config_Frontend_Feed_Generate extends Mage_Adminhtml_Block_System_Config_Form_Field {

    protected $_buttonId = "generate_feed_button";

    /**
     * Programmatically include the generate feed javascript in the adminhtml JS block.
     *
     * @return <type>
     */
	 
    protected function _prepareLayout() {
        $block = $this->getLayout()->createBlock("hawksearch_datafeed/system_config_frontend_feed_generate_js");
        $block->setData("button_id", $this->_buttonId);
        
        $this->getLayout()->getBlock('js')->append($block);
        return parent::_prepareLayout();
    }

    /**
     * Return element html
     *
     * @param  Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {
        $button = $this->getButtonHtml();

        $notice = "";
        if ($this->_feedGenIsLocked()) {
            $notice = "<p id='hawksearch_display_msg' class='note'>".Mage::getModel("hawksearch_datafeed/feed")->getAjaxNotice()."</p>";
        }
        return $button.$notice;
    }

    /**
     * Generate button html for the feed button
     *
     * @return string
     */
    public function getButtonHtml() {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(array(
                'id' => $this->_buttonId,
                'label' => $this->helper('hawksearch_datafeed')->__('Generate Feeds'),
                'onclick' => 'javascript:hawkSearchFeed.generateFeed(); return false;'
            ));
		$force = '';
        if ($this->_feedGenIsLocked()) {
			$button->setDisabled(true);
			$force = ' <input type="checkbox" value="feedforce" id="feedforce" name="feedforce" onclick="hawkSearchFeed.forceFeed();"> force';
        }

        return $button->toHtml() . $force;
    }

    /**
     * Check to see if there are any locks for any feeds
     *
     * @return boolean
     */
    protected function _feedGenIsLocked() {
        return Mage::helper('hawksearch_datafeed/feed')->thereAreFeedLocks();
    }

}

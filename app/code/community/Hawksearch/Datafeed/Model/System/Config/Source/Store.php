<?php


/**
 * Used in creating options for Yes|No config value selection
 *
 */
//class Mage_Adminhtml_Model_System_Config_Source_Yesno
class Hawksearch_Datafeed_Model_System_Config_Source_Store
{

	/**
	 * Options getter
	 *
	 * @return array
	 */
	public function toOptionArray()
	{
		return Mage::getSingleton('adminhtml/system_store')->getStoreValuesForForm(false, false);
	}

	/**
	 * Get options in "key-value" format
	 *
	 * @return array
	 */

}

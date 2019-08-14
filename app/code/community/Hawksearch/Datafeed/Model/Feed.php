<?php

#error_reporting(E_ALL);
#ini_set('display_errors', '1');
/**
 * Copyright (c) 2013 Hawksearch (www.hawksearch.com) - All Rights Reserved
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 */
class Hawksearch_Datafeed_Model_Feed extends Mage_Core_Model_Abstract {
	protected $_ajaxNotice,
		$_batchSize,
		$_categoryTypeId,
		$_dbConnection,
		$_excludedFields,
		$_tablePrefix,
		$_entityTypeId,
		$_feedPath,
		$isLoggingEnabled,
		$_totalProductCount,
		$_imageHeight,
		$_imageWidth,
		$_optionType;

	private $countryMap;
	private $outputFileDelimiter;
	private $bufferSize;
	private $outputFileExtension;
	protected $multiSelectValues;

	/**
	 * Constructor
	 */
	function __construct() {

		// Ignore user aborts and allow the script
		// to run forever
		ignore_user_abort(true); // If Varnish decides that the results timed out. Browsers are better behaved.
		set_time_limit(0); // Even with DB calls not counting towards execution time, it's still a long running script.  Vampiric bite FTW.
		/** @var $helper Hawksearch_Datafeed_Helper_Data */
		$helper = Mage::helper('hawksearch_datafeed/data');

		$this->_ajaxNotice = 'Generating feeds. Please wait.';
		$this->_tablePrefix = (string)Mage::getConfig()->getTablePrefix();
		$this->_entityTypeId = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
		$this->_excludedFields = Mage::getStoreConfig('hawksearch_datafeed/feed/exclude_fields', Mage::app()->getStore());
		$this->_categoryTypeId = Mage::getModel('eav/entity')->setType('catalog_category')->getTypeId();
		$this->isLoggingEnabled = Mage::helper('hawksearch_datafeed/data')->isLoggingEnabled();
		$this->_optionType = Mage::getStoreConfig('hawksearch_datafeed/feed/option_type', Mage::app()->getSafeStore());

		$this->_feedPath = $helper->getFeedFilePath();
		if (empty($this->_feedPath)) {
			$this->_feedPath = 'var/hawksearch/feeds';
		}

		$this->_batchSize = $helper->getBatchLimit();
		if (empty($this->_batchSize)) {
			$this->_batchSize = 10000;
		}

		$this->_imageWidth = $helper->getImageWidth();
		if (empty($this->_imageWidth)) {
			$this->_imageWidth = 135;
		}

		$this->_imageHeight = $helper->getImageHeight();

		$this->outputFileDelimiter = $helper->getOutputFileDelimiter();
		$this->bufferSize = $helper->getBufferSize();
		$this->outputFileExtension = $helper->getOutputFileExtension();

		parent::__construct();
	}

	/**
	 * Retrieves the values of select/multiselect fields, to be dereferenced into values useful to Hawk
	 *
	 * @return array
	 */
	protected function getMultiSelectValues() {
		if (!empty($this->multiSelectValues)) {
			return $this->multiSelectValues;
		}

		$eavTable = $this->_tablePrefix . 'eav_attribute';
		$eavAttributeTable = $this->_tablePrefix . 'eav_attribute';
		$eavOptionValueTable = $this->_tablePrefix . 'eav_attribute_option_value';
		$catalogProdEntityInt = $this->_tablePrefix . 'catalog_product_entity_int';
		$catalogProdEntityVarchar = $this->_tablePrefix . 'catalog_product_entity_varchar';

		$write = $this->_getConnection();
		$entity_type_id = $this->_entityTypeId;
		$attributeIds = array();
		$idList = "";


		$brandAttribute = Mage::helper('hawksearch_datafeed/data')->getBrandAttribute();
		if (!empty($brandAttribute)) {
			$attributeIds[$brandAttribute] = "";
		}

		// Get the list of attribute IDs to dereference and the attribute_codes that they match
		$sql = <<<EOSQL
SELECT  
    v.value, a.attribute_code
FROM 
    $catalogProdEntityVarchar v
LEFT JOIN
    $eavAttributeTable a ON v.attribute_id = a.attribute_id
WHERE
    v.entity_type_id = $this->_entityTypeId 
AND 
    a.frontend_input IN ('select', 'multiselect')
AND a.attribute_code != 'msrp_enabled' AND a.attribute_code != 'msrp_display_actual_price_type' AND a.attribute_code != 'is_recurring' AND a.attribute_code != 'enable_googlecheckout' AND a.attribute_code != 'tax_class_id' AND a.attribute_code != 'visibility' AND a.attribute_code != 'status'
EOSQL;

		// Prepare the array of attribute codes and compile a unique list of IDs to dereference

		//die($sql.'Andrew');

		if ($rows = $write->fetchAll($sql)) {
			foreach ($rows as $row) {
				$opts = explode(',', $row['value']);
				foreach ($opts as $opt) {
					#print_r($opt);
					if (isset($attributeIds[$row['attribute_code']])) {
						if ((is_numeric($opt)) && !in_array($opt, $attributeIds[$row['attribute_code']])) {
							$attributeIds[$row['attribute_code']][$opt] = $opt;
							$attributeIds['ids'][$opt] = $opt;
						}
					}
				}
			}
			if (!empty($attributeIds['ids'])) {
				$idList = "'" . implode("', '", $attributeIds['ids']) . "'";
			}
		}

		// Get the list of attribute IDs to dereference and the attribute_codes that they match
		$sql = <<<EOSQL
SELECT  
    v.value, a.attribute_code
FROM 
    $catalogProdEntityInt v
LEFT JOIN
    $eavAttributeTable a ON v.attribute_id = a.attribute_id
WHERE
    v.entity_type_id = $this->_entityTypeId 
AND 
    a.frontend_input IN ('select', 'multiselect')
AND a.attribute_code != 'msrp_enabled' AND a.attribute_code != 'msrp_display_actual_price_type' AND a.attribute_code != 'is_recurring' AND a.attribute_code != 'enable_googlecheckout' AND a.attribute_code != 'tax_class_id' AND a.attribute_code != 'visibility' AND a.attribute_code != 'status'
EOSQL;

		// Prepare the array of attribute codes and compile a unique list of IDs to dereference
		if ($rows = $write->fetchAll($sql)) {
			foreach ($rows as $row) {
				$opts = explode(',', $row['value']);
				foreach ($opts as $opt) {
					#print_r($opt);
					if (isset($attributeIds[$row['attribute_code']])) {
						if ((is_numeric($opt)) && !empty($attributeIds['ids']) && !in_array($opt, $attributeIds[$row['attribute_code']])) {
							$attributeIds[$row['attribute_code']][$opt] = $opt;
							$attributeIds['ids'][$opt] = $opt;
						}
					}
				}
			}

			if (!empty($attributeIds['ids'])) {
				$idList = "'" . implode("', '", $attributeIds['ids']) . "'";
			}
		}


		if (!empty($idList)) {
			// Get the dereferenced values
			$sql = <<<EOSQL
SELECT  
    value, 
    option_id 
FROM 
    $eavOptionValueTable
WHERE
    option_id IN ($idList)
EOSQL;

			// Replace the IDs with their values
			if ($rows = $write->fetchAll($sql)) {
				foreach ($rows as $row) {
					foreach ($attributeIds as &$attrGroup) {
						if (!empty($attrGroup[$row['option_id']])) {
							$attrGroup[$row['option_id']] = $row['value'];
						}
					}
				}
			}
		}

		// Cheating, I know, but it saves a tick.
		return $this->multiSelectValues = $attributeIds;
	}

	/**
	 * Generate feed based on store and returns success
	 *
	 * @return boolean
	 */

	protected function _getAttributeData() {
		// Initializations
		//$path = $this->_feedPath;
		$done = false;
		$offset = 0;

		$filename = $this->_feedPath . DS . "attributes" . '.' . $this->outputFileExtension;
		$arrayExcludeFields = explode(",", $this->_excludedFields);

		$eavAttributeTable = $this->_tablePrefix . 'eav_attribute';
		$productEntityTable = $this->_tablePrefix . 'catalog_product_entity';
		$eavOptionValueTable = $this->_tablePrefix . 'eav_attribute_option_value';
		$categoryProductTable = $this->_tablePrefix . 'catalog_category_product';
		$productEntityValueTable = $this->_tablePrefix . 'catalog_product_entity_';

		$tables = array(
			'int',
			'text',
			'varchar',
			'decimal',
			'datetime',
//			'catalog_category_product',
		);

		$multiSelectValues = $this->getMultiSelectValues();

		$write = $this->_getConnection();

		// This _should_ be escaped to prevent SQL injection... however the source of data is in the
		// admin panel so _should_ be generally safe.  TODO: Check the Magento writer DB object for ways
		// to escape content.
		$excludeFields = implode("', '", $arrayExcludeFields);
		if (!empty($excludeFields)) {
			$excludeFields = " AND attribute_code NOT IN ('" . $excludeFields . "')";
		}

		$output = new CsvWriter($filename, $this->outputFileDelimiter, $this->bufferSize);
		$output->appendRow(array('unique_id', 'key', 'value'));

		//$content = "unique_id\tkey\tvalue\n";

		// Loop through each of the catalog_product_entity_XXX tables separately.  Despite tribal knowledge to the contrary
		// among DB developers, in this specific case multiple queries happens to be faster than multiple joins.
		foreach ($tables as $table) {
			//$output->appendRow(array('NOW', 'OUTPUTTING', $table));
			$done = false;
			$offset = 0;
			$valueTable = $productEntityValueTable . $table;
			if ($table == "catalog_category_product") {

				$selectQry = <<<EOSQL
SELECT 
    e.entity_id,
    e.sku,
    a.attribute_code,
    a.source_model,
    a.attribute_id,
	a.attribute_code As value
FROM
    $productEntityTable e
LEFT JOIN
    $eavAttributeTable a ON e.entity_type_id = a.entity_type_id
WHERE
    e.entity_type_id = $this->_entityTypeId AND a.attribute_code = 'category_ids'
ORDER BY e.entity_id ASC
EOSQL;

				//die($selectQry);
			} elseif ($this->_optionType == 'eav') {
				// No temporary table any more, after exhaustive testing. It takes just as long to itterate
				// through the temp table as it does through a fresh query, plus the temp table has the
				// overhead of being set up in the first place.  This query takes less than 2 seconds each
				// time it is called, compared to the over 5 minutes it took previously, when the temp
				// table was a good idea.
				$valColumn = "v.value";
				$eaovTable = '';
				if ($table == 'int') {
					$valColumn = "case when a.frontend_input = 'select' and (a.source_model = 'eav/entity_attribute_source_table' or a.source_model = '' or a.source_model IS NULL) then ov.value else v.value end AS value";
					$eaovTable = "LEFT JOIN $eavOptionValueTable ov ON ov.option_id = v.value and ov.store_id = 0";
				} elseif ($table == 'varchar') {
					$valColumn = "case a.frontend_input when 'multiselect' then ov.value else v.value end AS value";
					$eaovTable = "LEFT JOIN $eavOptionValueTable ov ON ov.option_id = v.value and ov.store_id = 0";
				}
				$selectQry = <<<EOSQL
SELECT 
    e.entity_id,
    e.sku,
    a.attribute_code,
    a.source_model,
    a.attribute_id,
    $valColumn
FROM
    $productEntityTable e
LEFT JOIN
    $valueTable v ON e.entity_id = v.entity_id
LEFT JOIN
    $eavAttributeTable a ON v.attribute_id = a.attribute_id
$eaovTable
WHERE
    e.entity_type_id = $this->_entityTypeId
$excludeFields
ORDER BY e.entity_id ASC
EOSQL;
				Mage::log($selectQry);

			} else {
				$selectQry = <<<EOSQL
SELECT
    e.entity_id,
    e.sku,
    a.attribute_code,
    a.source_model,
    a.attribute_id,
    v.value
FROM
    $productEntityTable e
FF0000LEFT JOIN
    $valueTable v ON e.entity_id = v.entity_id
LEFT JOIN
    $eavAttributeTable a ON v.attribute_id = a.attribute_id
WHERE
    e.entity_type_id = $this->_entityTypeId
$excludeFields
ORDER BY e.entity_id ASC
EOSQL;
			}

			while (!$done) {
				//echo "TableName: " . $table;
				//die( "select Query: " . $selectQry);

				try {
					// Messy, messy, messy.  I appologize in advance.
					// Perhaps some prose will help.
					// The fetchAll() within this first if() statement runs the query, including the LIMIT and OFFSET.
					// Then, we itterate through the results.  If the value is a number or a list of comma-separated numbers, then
					// we attempt to dereference to the actual values for the field.
					// Finally, at the end of itterating through the results, we write to disk and reset our loop-specific variables before
					// launching back through the while() above and doing fetchAll() again.
					// Also note the foreach (tables as table) above, which moves us to the next DB table.
					if (($rows = $write->fetchAll($selectQry . ' LIMIT ' . $offset . ', ' . $this->_batchSize)) && (count($rows) > 0)) {
						foreach ($rows as $row) {
							$values = explode(',', $row['value']);

							if ($values[0] == "category_ids" && $row['attribute_code'] == 'category_ids') {
								$category_ids_for_export = "";
								$select_qry = 'SELECT category_id FROM ' . $categoryProductTable . ' WHERE product_id = "' . $row['entity_id'] . '"';
								$rows1 = $write->fetchAll($select_qry);
								foreach ($rows1 as $category_data) {
									//$content .= "\"" . $row['sku'] . "\"\t\"category_id\"\t\"" . $category_data['category_id'] . "\"\n";
									$output->appendRow(array($row['sku'], 'category_id', $category_data['category_id']));
								}
							} elseif ($row['attribute_code'] == 'country_of_manufacture') {
								//$content .= "\"" . $row['sku'] . "\"\t\"" . $row['attribute_code'] . "\"\t\"" . str_replace("\"", "\"\"", $countryMap[$row['value']]) . "\"\n";
								$output->appendRow(array($row['sku'], $row['attribute_code'], $this->getCountryName($row['value'])));

							} else if (is_numeric($values[0])) {
								foreach ($values as $val) {
									if (false && !empty($multiSelectValues[$row['attribute_code']][$val])) {
										//$content .= "\"" . $row['sku'] . "\"\t\"" . $row['attribute_code'] . "\"\t\"" . str_replace("\"", "\"\"", $multiSelectValues[$row['attribute_code']][$val]) . "\"\n";
										$output->appendRow(array($row['sku'], $row['attribute_code'], $multiSelectValues[$row['attribute_code']][$val]));
									} elseif ($table == 'int' && !empty($row['source_model'])) {
										$source = Mage::getSingleton($row['source_model']);
										if ($row['source_model'] == 'eav/entity_attribute_source_table') {
											$attribute = Mage::getModel('eav/entity_attribute')->load($row['attribute_id']);
											$source->setAttribute($attribute);
										}
										$output->appendRow(array($row['sku'], $row['attribute_code'], $source->getOptionText($row['value'])));
									} else {
										//$content .= "\"" . $row['sku'] . "\"\t\"" . $row['attribute_code'] . "\"\t\"" . str_replace("\"", "\"\"", $val) . "\"\n";
										$output->appendRow(array($row['sku'], $row['attribute_code'], $val));
									}
								}
							} // Otherwise, add each individually.
							else {
								//$content .= "\"" . $row['sku'] . "\"\t\"" . $row['attribute_code'] . "\"\t\"" . str_replace("\"", "\"\"", $row['value']) . "\"\n";
								$output->appendRow(array($row['sku'], $row['attribute_code'], $row['value']));
							}
						}


						//$this->writeFile($filename, '', $content, ($firstRecord ? 1 : 2));

						// Reset for the next iteration.
						// Commentary: It is necessary to set for next iteration at the end, rather than the beginning, because the first
						// iteration has special cases, such as setting the TSV header and removing/re-creating the file itself.
						// $firstRecord = false;
						// $content = '';
						$offset += $this->_batchSize;
					} else {
						$done = true;
					}
				} catch (Exception $e) {
					// remove lock
					Mage::helper('hawksearch_datafeed/feed')->RemoveFeedLocks();

					if ($this->isLoggingEnabled) {
						Mage::log(date('c') . " - Exception thrown on line " . $e->getLine() . " of " . $e->getFile() . ": " . $e, null, 'hawksearch_errors.log');
					}
					return false;
				}
			}
		}

		/* custom attribute code/category_id */
		$collection = Mage::getModel('catalog/product')->getCollection();
		$collection->addAttributeToSelect('sku');
		$collection->addAttributeToFilter('visibility', array('neq' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE));
		if (!Mage::helper('hawksearch_datafeed/data')->getAllowDisabledAttribute()) {
			$collection->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
		}
		if(!Mage::helper('hawksearch_datafeed/data')->isIncludeOutOfStockItems()) {
			/** @var Mage_CatalogInventory_Model_Stock $stockfilter */
			$stockfilter = Mage::getSingleton('cataloginventory/stock');
			$stockfilter->addInStockFilterToCollection($collection);
		}

		$collection->setPageSize($this->_batchSize);
		$totalPages = $collection->getLastPageNumber();

		$currentPage = 1;
		do {
			$collection->setCurPage($currentPage);
			/** @var Mage_Catalog_Model_Product $product */
			foreach ($collection as $product) {
				foreach($product->getCategoryIds() as $id) {
					$output->appendRow(array($product->getSku(), 'category_id', $id));
				}
				/* Example of custom attribute generation */
//				$product->load('media_gallery');
//				/** @var Varien_Data_Collection $images */
//				$images = $product->getMediaGalleryImages();
//				$image = $images->getLastItem();
//				$imagePath = $image->getFile();
//				$output->appendRow(array($product->getSku(), 'rollover_image', $imagePath));

				/* Gallery images should come out sorted,
					but if not, try this instead of getLastItem(): */
//				$pos = 0;
//				foreach ($images as $image) {
//					if ($image->getPosition() >= $pos) {
//						$pos = $image->getPosition();
//						$imagePath = $image->getFile();
//					}
//				}
			}
			$collection->clear();
			$currentPage++;
		} while ($currentPage <= $totalPages);

		return true;
	}

	protected function _getCategoryData() {
		$done = false;
		$offset = 0;
		$filename = $this->_feedPath . DS . "hierarchy" . '.' . $this->outputFileExtension;

		//$firstRecord = true;

		$categoryTable = $this->_tablePrefix . 'catalog_category_entity';
		$eavAttributesTable = $this->_tablePrefix . 'eav_attribute';
		$categoryVarCharTable = $this->_tablePrefix . 'catalog_category_entity_varchar';

		$write = $this->_getConnection();

		$selectQry = <<<EOSQL
SELECT
    a.entity_id,
    a.parent_id,
    b.value,
    a.position
FROM
    $categoryTable AS a
LEFT JOIN
    $categoryVarCharTable AS b ON a.entity_id = b.entity_id
WHERE
    b.attribute_id IN
    (
        SELECT 
            attribute_id
        FROM
            $eavAttributesTable
        WHERE
            attribute_code = 'name'
    )
AND
    b.store_id = '0'
AND
    a.parent_id != '0'
ORDER BY
    a.position ASC
EOSQL;

		//$content = "category_id\tcategory_name\tparent_category_id\tsort_order\n1\tRoot\t0\t0\n";
		$output = new CsvWriter($filename, $this->outputFileDelimiter, $this->bufferSize);
		$output->appendRow(array('category_id', 'category_name', 'parent_category_id', 'sort_order'));
		$output->appendRow(array('1', 'Root', '0', '0'));

		while (!$done) {
			try {
				if ($rows = $write->fetchAll($selectQry . ' LIMIT ' . $offset . ', ' . $this->_batchSize)) {
					if (count($rows) > 0) {
						foreach ($rows as $row) {
							//$content .= $row['entity_id'] . "\t" . $row['value'] . "\t" . $row['parent_id'] . "\t" . $row['position'] . "\n";
							$output->appendRow(array($row['entity_id'], $row['value'], $row['parent_id'], $row['position']));
						}
						$offset += $this->_batchSize;
						//$this->writeFile($filename, '', $content, ($firstRecord ? 1 : 2));
						//$firstRecord = false;
						//$content = '';
					}
				} else {
					$done = true;
				}
			} catch (Exception $e) {
				// remove lock
				Mage::helper('hawksearch_datafeed/feed')->RemoveFeedLocks();

				if ($this->isLoggingEnabled) {
					Mage::log("SQL ERROR: (" . $e . ")", null, 'hawksearch_errors.log');
				}
				return false;
			}
		} //end whiledone loop

		return true;
	}

	protected function _getProductData() {
		$done = false;
		$offset = 0;
		$filename = $this->_feedPath . DS . "items" . '.' . $this->outputFileExtension;
		$brand_sql = "";
		$attrCodes = array();
		$brand_select = "";
		$entity_type_id = $this->_entityTypeId;

		$this->_batchSize = 10000;

		$eavTable = $this->_tablePrefix . 'eav_attribute';
		$productEntityTable = $this->_tablePrefix . 'catalog_product_entity';
		$eavOptionValueTable = $this->_tablePrefix . 'eav_attribute_option_value';
		$productRelationTable = $this->_tablePrefix . 'catalog_product_relation';
		$productEntityIntTable = $this->_tablePrefix . 'catalog_product_entity_int';
		$productEntityTextTable = $this->_tablePrefix . 'catalog_product_entity_text';
		$productEntityVarCharTable = $this->_tablePrefix . 'catalog_product_entity_varchar';
		$productEntityDecimalTable = $this->_tablePrefix . 'catalog_product_entity_decimal';
		$productEntityDateTimeTable = $this->_tablePrefix . 'catalog_product_entity_datetime';
		$catalogInventoryStockTable = $this->_tablePrefix . 'cataloginventory_stock_item';
		$productEntityUrlTable = $this->_tablePrefix . 'catalog_product_entity_url_key';

		//$baseurl = Mage::getUrl();
		//$mediaurl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
		$brandAttribute = Mage::helper('hawksearch_datafeed/data')->getBrandAttribute();
		$allowDisabled = Mage::helper('hawksearch_datafeed/data')->getAllowDisabledAttribute();

		$write = $this->_getConnection();

		$attrCodesQuery = <<<EOSQL
SELECT attribute_id, attribute_code
FROM $eavTable
WHERE attribute_code IN
(
    'name',
    'url_key',
    'image',
    'description',
    'short_description',
    'meta_keyword',
    'visibility',
    'price',
    'special_from_date',
    'special_to_date',
    'msrp',
    'special_price',
    'status',
	'$brandAttribute'
)
AND entity_type_id = '$entity_type_id'
EOSQL;

		if ($codeRows = $write->fetchAll($attrCodesQuery)) {
			foreach ($codeRows as $row) {
				$attrCodes[$row['attribute_code']] = $row['attribute_id'];
			}
		}

		$disabled_sql = '';
		$CONN = ' WHERE ';
		if (!$allowDisabled) {
			$disabled_sql = "LEFT JOIN $productEntityIntTable AS T6 ON P.entity_id = T6.entity_id AND T6.attribute_id = '" . $attrCodes['status'] . "'";
		}

		$brandAttribute = Mage::helper('hawksearch_datafeed/data')->getBrandAttribute();
		if (!empty($brandAttribute)) {
			$brand_select = ", B5.value AS Brand";
			$brand_sql = "LEFT JOIN " . $productEntityIntTable . " AS B5 ON P.entity_id = B5.entity_id AND B5.attribute_id = '" . $attrCodes[$brandAttribute] . "'";
		}
		$urlfield = ", CONCAT(V1.value, '.html') AS Link";
		$urljoin = "LEFT JOIN $productEntityUrlTable AS V1  ON P.entity_id = V1.entity_id  AND V1.attribute_id  = '" . $attrCodes['url_key'] . "'";
		if (!Mage::getSingleton('core/resource')->getConnection('core_write')->isTableExists('catalog_product_entity_url_key')) {
			$urlfield = '';
			$urljoin = '';
		}

		$select_qry = "SELECT  P.attribute_set_id, P.entity_id AS ProductID, P.type_id, P.sku, P.has_options, V.value AS Name, T1.value AS ProdDesc, T2.value AS ShortDesc, T3.value AS MetaKeyword, T5.value AS visibility, D.value AS Price, S.value AS Special_Price, SDF.value As Special_Date_From, SDT.value As Special_Date_To, ST.qty, ST.is_in_stock AS IsInStock, X.value AS Msrp" . $brand_select . ", R.parent_id AS GroupID $urlfield,
                        CASE
                            WHEN V2.Value IS NULL
                                THEN '/no-image.jpg'
                            ELSE CONCAT('', V2.value)
                        END AS Image
                        FROM $productEntityTable AS P
                        INNER JOIN " . $productEntityVarCharTable . "  AS V   ON P.entity_id = V.entity_id   AND V.attribute_id   = '" . $attrCodes['name'] . "'
                        INNER JOIN " . $catalogInventoryStockTable . " AS ST  ON P.entity_id = ST.product_id 
						" . $urljoin . "
                        LEFT JOIN  " . $productEntityVarCharTable . "  AS V2  ON P.entity_id = V2.entity_id  AND V2.attribute_id  = '" . $attrCodes['image'] . "'
                        LEFT JOIN  " . $productEntityTextTable . "     AS T1  ON P.entity_id = T1.entity_id  AND T1.attribute_id  = '" . $attrCodes['description'] . "'
                        LEFT JOIN  " . $productEntityTextTable . "     AS T2  ON P.entity_id = T2.entity_id  AND T2.attribute_id  = '" . $attrCodes['short_description'] . "'
                        LEFT JOIN  " . $productEntityTextTable . "     AS T3  ON P.entity_id = T3.entity_id  AND T3.attribute_id  = '" . $attrCodes['meta_keyword'] . "'
                        LEFT JOIN  " . $productEntityIntTable . "      AS T5  ON P.entity_id = T5.entity_id  AND T5.attribute_id  = '" . $attrCodes['visibility'] . "'
                        " . $disabled_sql . "
                        LEFT JOIN  " . $productEntityDecimalTable . "  AS D   ON P.entity_id = D.entity_id   AND D.attribute_id   = '" . $attrCodes['price'] . "'
                        LEFT JOIN  " . $productEntityDateTimeTable . " AS SDF ON P.entity_id = SDF.entity_id AND SDF.attribute_id = '" . $attrCodes['special_from_date'] . "'
                        LEFT JOIN  " . $productEntityDateTimeTable . " AS SDT ON P.entity_id = SDT.entity_id AND SDT.attribute_id = '" . $attrCodes['special_to_date'] . "'
					    " . $brand_sql . "
                        LEFT JOIN  " . $productEntityDecimalTable . "  AS X   ON P.entity_id = X.entity_id   AND X.attribute_id   = '" . $attrCodes['msrp'] . "'
						
                        LEFT JOIN  " . $productRelationTable . "       AS R   ON P.entity_id = R.parent_id   OR  P.entity_id = R.child_id 
                        LEFT JOIN  " . $productEntityDecimalTable . "  AS S   ON P.entity_id = S.entity_id   AND S.attribute_id   = '" . $attrCodes['special_price'] . "'";

		if (!Mage::helper('hawksearch_datafeed/data')->isIncludeOutOfStockItems()) {
			$select_qry .= " $CONN ST.is_in_stock = 1";
			$CONN = ' AND ';
		}
		if (!$allowDisabled) {
			$select_qry .= " $CONN T6.value = 1 ";
			$CONN = ' AND ';
		}

		// die($select_qry);
		// $content = "\"product_id\"\t\"unique_id\"\t\"name\"\t\"url_detail\"\t\"image\"\t\"price_retail\"\t\"price_sale\"\t\"price_special\"\t\"price_special_from_date\"\t\"price_special_to_date\"\t\"group_id\"\t\"description_short\"\t\"description_long\"\t\"brand\"\t\"sku\"\t\"sort_default\"\t\"sort_rating\"\t\"is_free_shipping\"\t\"is_new\"\t\"is_on_sale\"\t\"keyword\"\t\"metric_inventory\"\n";
		$output = new CsvWriter($filename, $this->outputFileDelimiter, $this->bufferSize);
		$output->appendRow(array(
			'product_id',
			'unique_id',
			'name',
			'url_detail',
			'image',
			'price_retail',
			'price_sale',
			'price_special',
			'price_special_from_date',
			'price_special_to_date',
			'group_id',
			'description_short',
			'description_long',
			'brand',
			'sku',
			'sort_default',
			'sort_rating',
			'is_free_shipping',
			'is_new',
			'is_on_sale',
			'keyword',
			'metric_inventory'));

		while (!$done) {
			try {
				if ($rows = $write->fetchAll($select_qry . ' LIMIT ' . $offset . ', ' . $this->_batchSize)) {
					if (count($rows) > 0) {
						foreach ($rows as $row) {
							//$name = empty($row['Name']) ? $row['Name'] : str_replace("\"", "\"\"", $row['Name']);
							//$description = empty($row['ProdDesc']) ? $row['ProdDesc'] : str_replace("\"", "\"\"", $row['ProdDesc']);
							//$shortdescription = empty($row['ShortDesc']) ? $row['ShortDesc'] : str_replace("\"", "\"\"", $row['ShortDesc']);
							//$metakeyword = empty($row['MetaKeyword']) ? $row['MetaKeyword'] : str_replace("\"", "\"\"", $row['MetaKeyword']);

							$data_is_on_sale = empty($row['Special_Price']) ? 0 : 1;
							if (isset($row['Brand'])) {
								$select_brand_qry = $write->query("SELECT value FROM " . $eavOptionValueTable . " WHERE `option_id`=\"" . $row['Brand'] . "\" AND store_id ='0'");
								$brandRow = $select_brand_qry->fetch();
								$brand_text = $brandRow['value'];
							} else {
								$brand_text = "";
							}
							//$content .= "\"" . $row['ProductID'] . "\"\t\"" . $row['sku'] . "\"\t\"" . $name . "\"\t\"" . $row['Link'] . "\"\t\"" . $row['Image'] . "\"\t\"" . $row['Msrp'] . "\"\t\"" . $row['Price'] . "\"\t\"" . $row['Special_Price'] . "\"\t\"" . $row['Special_Date_From'] . "\"\t\"" . $row['Special_Date_To'] . "\"\t\"" . $row['GroupID'] . "\"\t\"" . $shortdescription . "\"\t\"" . $description . "\"\t\"" . $brand_text . "\"\t\"" . $row['sku'] . "\"\t\"0\"\t\"0\"\t\"0\"\t\"0\"\t\"" . $data_is_on_sale . "\"\t\"" . $metakeyword . "\"\t\"" . $row['qty'] . "\"\n";
							$output->appendRow(array(
								$row['ProductID'],
								$row['sku'],
								$row['Name'],
								$row['Link'],
								$row['Image'],
								$row['Msrp'],
								$row['Price'],
								$row['Special_Price'],
								$row['Special_Date_From'],
								$row['Special_Date_To'],
								$row['GroupID'],
								$row['ShortDesc'],
								$row['ProdDesc'],
								$brand_text,
								$row['sku'],
								0, // sort_default
								0, // sort_rating
								0, // is_free_shipping
								0, // is_new
								$data_is_on_sale,
								$row['MetaKeyword'],
								$row['qty']));

						}
						$offset += $this->_batchSize;
						//$this->writeFile($filename, '', $content, ($firstRecord ? 1 : 2));
						//$firstRecord = false;
						//$content = '';
					}
				} else {
					$done = true;
				}
			} catch (Exception $e) {
				// remove lock
				Mage::helper('hawksearch_datafeed/feed')->RemoveFeedLocks();

				if ($this->isLoggingEnabled) {
					Mage::log("SQL ERROR: (" . $e . ")", null, 'hawksearch_errors.log');
				}

				return false;
			}
		} // end while
		return true;
	}

	protected function _getContentData() {
		//$resource = Mage::getSingleton('core/resource');

		$done = false;
		$offset = 0;
		$baseurl = Mage::getUrl();
		//$filename = $this->_feedPath . "/content.txt";
		$filename = $this->_feedPath . DS . "content" . '.' . $this->outputFileExtension;

		//$firstRecord = true;
		$cmsPageTable = $this->_tablePrefix . 'cms_page';


		$select_qry = "SELECT page_id, title, CONCAT('" . $baseurl . "', identifier) AS Link, content_heading, content, creation_time, is_active FROM " . $cmsPageTable . "";

		$write = $this->_getConnection();

		//$content = "unique_id\tname\turl_detail\tdescription_short\tcreated_date\n";
		$output = new CsvWriter($filename, $this->outputFileDelimiter, $this->bufferSize);
		$output->appendRow(array('unique_id', 'name', 'url_detail', 'description_short', 'created_date'));

		while (!$done) {
			try {
				if ($rows = $write->fetchAll($select_qry . ' LIMIT ' . $offset . ', ' . $this->_batchSize)) {
					if (($numRows = count($rows)) && $numRows > 0) {
						foreach ($rows as $row) {
							//$content .= $row['page_id'] . "\t" . $row['title'] . "\t" . $row['Link'] . "\t" . $row['content_heading'] . "\t" . $row['creation_time'] . "\n";
							$output->appendRow(array($row['page_id'], $row['title'], $row['Link'], $row['content_heading'], $row['creation_time']));
						}

						$offset += $this->_batchSize;
						// $this->writeFile($filename, '', $content, ($firstRecord ? 1 : 2));
						// $firstRecord = false;
						// $content = '';
					} else {
						$done = true;
					}
				} else {
					$done = true;
				}
			} catch (Exception $e) {
				// remove lock
				Mage::helper('hawksearch_datafeed/feed')->RemoveFeedLocks();

				if ($this->isLoggingEnabled) {
					Mage::log("SQL ERROR: (" . $e . ")", null, 'hawksearch_errors.log');
				}
				return false;
			}
		} // end while
		return true;
	}

	/*private function writeFile($filename, $header, $content, $recordcount) {
		if ($recordcount === 1) {
			if (file_exists($filename)) {
				unlink($filename);
			}
		}

		if (!file_exists($filename)) {
			$handle = fopen($filename, "a");
			fclose($handle);
		}

		if (is_writable($filename)) {
			if (!$handle = fopen($filename, 'a')) {
				if ($this->isLoggingEnabled) {
					Mage::log("Cannot open file (" . $filename . ")", null, 'hawksearch_errors.log');
				}
				return false;
			}
			if (fwrite($handle, $content) === FALSE) {
				if ($this->isLoggingEnabled) {
					Mage::log("Cannot write to file (" . $filename . ")", null, 'hawksearch_errors.log');
				}
				return false;
			}
			return true; // Success, wrote ($somecontent) to file ($filename);
			fclose($handle);
		} else {
			if ($this->isLoggingEnabled) {
				Mage::log("The file " . $filename . " is not writable", null, 'hawksearch_errors.log');
			}
		}
		return true;
	}*/

	public function getCountryName($code) {
		/* map friendly country_of_origin names */
		if (!isset($this->countryMap)) {
			$options = Mage::getModel('directory/country')->getResourceCollection()->toOptionArray();

			$this->countryMap = array();
			foreach ($options as $option) {
				if ($option['value'] != '') {
					$this->countryMap[$option['value']] = $option['label'];
				}
			}
		}
		return isset($this->countryMap[$code]) ? $this->countryMap[$code] : $code;
	}

	public function generateFeed($price_feed = false) {
		try {
			//exports Attribute Data
			$this->_getAttributeData();

			//exports Category Data
			$this->_getCategoryData();

			//exports Product Data
			$this->_getProductData();

			//exports CMS / Content Data
			$this->_getContentData();

			//refresh image cache
			$this->refreshImageCache();

			// remove locks
			Mage::helper('hawksearch_datafeed/feed')->RemoveFeedLocks();
		} catch (Exception $e) {
			// remove lock
			Mage::helper('hawksearch_datafeed/feed')->RemoveFeedLocks();
			#Mage::log("Exception: {$e->getMessage()}");
			if ($this->isLoggingEnabled) {
				Mage::log(sprintf('Exception: %s', $e->getMessage()), null, 'hawksearch_errors.log');
			}
		}
	}

	public function refreshImageCache() {
		$products = Mage::getModel('catalog/product')->getCollection()->addAttributeToSelect(array('image', 'small_image'));
		$products->setPageSize(1000);

		$pages = $products->getLastPageNumber();
		$currentPage = 1;

		do {
			$products->setCurPage($currentPage);
			$products->load();

			foreach ($products as $product) {
				if (empty($this->_imageHeight)) {
					Mage::helper('catalog/image')->init($product, 'small_image')->resize($this->_imageWidth) . '';
				} else {
					Mage::helper('catalog/image')->init($product, 'small_image')->resize($this->_imageWidth, $this->_imageHeight) . '';
				}
			}

			$currentPage++;

			//clear collection and free memory
			$products->clear();

		} while ($currentPage <= $pages);
	}

	/**
	 * Returns the total number of products in the store catalog
	 *
	 * @return int
	 */
	protected function _getProductCount() {
		if ($this->_totalProductCount === null) {
			$count = $this->_getConnection()->query("select count(entity_id) from " . Mage::getSingleton('core/resource')->getTableName('catalog/product'));
			$this->_totalProductCount = ($count) ? $count->fetch(PDO::FETCH_COLUMN) : 0;
		}
		return $this->_totalProductCount;
	}

	/**
	 * Returns the database connection used by the feed
	 *
	 * @return PDO
	 */
	protected function _getConnection() {
		if (!$this->_dbConnection) {
			$this->_dbConnection = Mage::getSingleton('core/resource')->getConnection('core_write');
		} else {
			$this->_dbConnection->closeConnection();
			$this->_dbConnection->getConnection();
		}

		return $this->_dbConnection;
	}

	public function getAjaxNotice() {
		$this->_ajaxNotice = "<span style='color:red'>Currently generating feeds... containing up to {$this->_getProductCount()} products. </span>";
		return $this->_ajaxNotice;
	}

	public function getAjaxNoticeImageCache() {
		$this->_ajaxNotice = "<span style='color:red'>Currently re-generating image cache... containing up to {$this->_getProductCount()} products. </span>";
		return $this->_ajaxNotice;
	}
}

/**
 * CsvWriter
 *
 * The purpose of this class is to allow uniform escaping of CSV data via the fputcsv()
 * along with handling the boilerplate of file operations.
 */
class CsvWriter {
	private $finalDestinationPath;
	private $outputFile;
	private $outputOpen = false;
	private $delimiter;
	private $bufferSize;

	public function __construct($destFile, $delim, $buffSize = null) {
		$this->finalDestinationPath = $destFile;
		if (file_exists($this->finalDestinationPath)) {
			if (false === unlink($this->finalDestinationPath)) {
				throw new Exception("CsvWriteBuffer: unable to remove old file '$this->finalDestinationPath'");
			}
		}
		$this->delimiter = $delim;
		$this->bufferSize = $buffSize;
	}

	public function __destruct() {
		$this->closeOutput();
	}

	public function appendRow(array $fields) {
		if (!$this->outputOpen) {
			$this->openOutput();
		}
		if (false === fputcsv($this->outputFile, $fields, $this->delimiter)) {
			throw new Exception("CsvWriter: failed to write row.");
		}
	}

	public function openOutput() {
		if (false === ($this->outputFile = fopen($this->finalDestinationPath, 'a'))) {
			throw new Exception("CsvWriter: Failed to open destination file '$this->finalDestinationPath'.");
		}
		if (!is_null($this->bufferSize)) {
			stream_set_write_buffer($this->outputFile, $this->bufferSize);
		}
		$this->outputOpen = true;
	}

	public function closeOutput() {
		if (!$this->outputOpen) {
			if (false === fclose($this->outputFile)) {
				throw new Exception("CsvWriter: Failed to close destination file'$this->finalDestinationPath'.");
			}
			$this->outputOpen = false;
		}
	}

}

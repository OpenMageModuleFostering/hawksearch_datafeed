<?php

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
		$_storeId,
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
		set_time_limit(0); // Even with DB calls not counting towards execution time, it's still a long running script.
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
			$this->_feedPath = Mage::getBaseDir('base') . DS . 'var/hawksearch/feeds';
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
		$this->_storeId = 0;

		parent::__construct();
	}

	/**
	 * Adds a log entry to the hawksearch proxy log. Logging must
	 * be enabled for both the module and Magneto
	 * (See System >> Configuration >> Developer >> Log Settings
	 *
	 * @param $message
	 */
	public function log($message) {
		if ($this->isLoggingEnabled) {
			Mage::log("HAWKSEARCH: $message", null, 'hawksearch.log');
		}
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
		$this->log('starting _getAttributeData');
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
		);

		$write = $this->_getConnection();

		$excludeFields = implode("', '", $arrayExcludeFields);
		if (!empty($excludeFields)) {
			$this->log('adding exclude fields');
			$excludeFields = " AND attribute_code NOT IN ('" . $excludeFields . "')";
		}

		$this->log(sprintf('going to open feed file %s', $filename));
		$output = new CsvWriter($filename, $this->outputFileDelimiter, $this->bufferSize);
		$this->log('feed file open, appending header');
		$output->appendRow(array('unique_id', 'key', 'value'));
		$this->log('header appended');

		foreach ($tables as $table) {
			$this->log(sprintf('starting to pull from attribute table %s', $table));

			$done = false;
			$offset = 0;
			$valueTable = $productEntityValueTable . $table;
			if ($table == "catalog_category_product") {
				$this->log('creating query for catalog_category_product');

				$selectQry = <<<EOSQL
SELECT 
    e.entity_id,
    e.sku,
    a.attribute_code,
    a.source_model,
    a.frontend_input,
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

			} else {
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
    a.frontend_input,
    a.attribute_id,
    $valColumn
FROM
    $productEntityTable e
LEFT JOIN
    $valueTable v ON e.entity_id = v.entity_id and v.store_id = 0
LEFT JOIN
    $eavAttributeTable a ON v.attribute_id = a.attribute_id
$eaovTable
WHERE
    e.entity_type_id = $this->_entityTypeId
$excludeFields
ORDER BY e.entity_id ASC
EOSQL;
				Mage::log($selectQry);

			}

			while (!$done) {
				$this->log(sprintf('querying data for table %s at offset %d', $table, $offset));
				try {
					if (($rows = $write->fetchAll($selectQry . ' LIMIT ' . $offset . ', ' . $this->_batchSize)) && (count($rows) > 0)) {
						$this->log(sprintf('fetch all succeeded for att type %s, processing %d rows', $table, count($rows)));
						foreach ($rows as $row) {
							if ($row['frontend_input'] == 'multiselect') {
								$values = explode(',', $row['value']);
								foreach ($values as $val) {
									if ($table == 'int' && !empty($row['source_model'])) {
										$source = Mage::getSingleton($row['source_model']);
										if ($row['source_model'] == 'eav/entity_attribute_source_table') {
											$attribute = Mage::getModel('eav/entity_attribute')->load($row['attribute_id']);
											$source->setAttribute($attribute);
										}
										$output->appendRow(array($row['sku'], $row['attribute_code'], $source->getOptionText($row['value'])));
									} else {
										$output->appendRow(array($row['sku'], $row['attribute_code'], $val));
									}
								}
							} else if ($row['attribute_code'] == 'category_ids' && $row['value'] == "category_ids") {
								$select_qry = 'SELECT category_id FROM ' . $categoryProductTable . ' WHERE product_id = "' . $row['entity_id'] . '"';
								$rows1 = $write->fetchAll($select_qry);
								foreach ($rows1 as $category_data) {
									$output->appendRow(array($row['sku'], 'category_id', $category_data['category_id']));
								}
							} elseif ($row['attribute_code'] == 'country_of_manufacture') {
								$output->appendRow(array($row['sku'], $row['attribute_code'], $this->getCountryName($row['value'])));

							} elseif ($table == 'int' && !empty($row['source_model']) && $row['source_model'] != 'eav/entity_attribute_source_table') {
								try {
									$source = Mage::getSingleton($row['source_model']);
									$attribute = Mage::getModel('eav/entity_attribute')->load($row['attribute_id']);
									$source->setAttribute($attribute);
									$output->appendRow(array($row['sku'], $row['attribute_code'], $source->getOptionText($row['value'])));
								} catch (Exception $e) {
									$this->log(sprintf('%s - Caught Exception on line %d or %s: %s', date('c'), $e->getLine(), $e->getFile(), $e->getTraceAsString()));
									$output->appendRow(array($row['sku'], $row['attribute_code'], $row['value']));
								}
							} else {
								$output->appendRow(array($row['sku'], $row['attribute_code'], $row['value']));
							}
						}
						$offset += $this->_batchSize;
					} else {
						$this->log(sprintf('done with table %s', $table));
						$done = true;
					}
				} catch (Exception $e) {
					// remove lock
					Mage::helper('hawksearch_datafeed/feed')->RemoveFeedLocks();

					$this->log(sprintf('%s - Exception thrown on line %d or %s: %s', date('c'), $e->getLine(), $e->getFile(), $e->getTraceAsString()));
					$this->log('exiting function _getAttributeData() due to exception');
					return false;
				}
			}
		}
		$this->log('going to get product collection for category id selection');
		/* custom attribute code/category_id */
		$collection = Mage::getModel('catalog/product')->getCollection();
		$collection->addAttributeToSelect('sku');
		//$collection->addAttributeToFilter('visibility', array('neq' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE));
		if (!Mage::helper('hawksearch_datafeed/data')->getAllowDisabledAttribute()) {
			$this->log('adding status filter');
			$collection->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
		}
		if (!Mage::helper('hawksearch_datafeed/data')->isIncludeOutOfStockItems()) {
			$this->log('adding out of stock filter');
			/** @var Mage_CatalogInventory_Model_Stock $stockfilter */
			$stockfilter = Mage::getSingleton('cataloginventory/stock');
			$stockfilter->addInStockFilterToCollection($collection);
		}

		$this->log(sprintf('setting page size to %d', $this->_batchSize));
		$collection->setPageSize($this->_batchSize);
		$totalPages = $collection->getLastPageNumber();
		$this->log(sprintf('retrieved %d total pages', $totalPages));

		$currentPage = 1;
		do {
			$this->log(sprintf('setting current page to %d', $currentPage));
			$collection->setCurPage($currentPage);
			$collection->clear();
			$this->log(sprintf('going to process %d products on page %d', $collection->count(), $currentPage));

			/*
			 * add ratings information
			 */
			/** @var Mage_Review_Model_Review $review */
			$review = Mage::getSingleton('review/review');
			$review->appendSummary($collection);
			
			/** @var Mage_Catalog_Model_Product $product */
			foreach ($collection as $product) {
				foreach ($product->getCategoryIds() as $id) {
					$output->appendRow(array($product->getSku(), 'category_id', $id));
				}
				if(($rs = $product->getRatingSummary()) &&  $rs->getReviewsCount() > 0){
					$output->appendRow(array($product->getSku(), 'rating_summary', $rs->getRatingSummary()));
					$output->appendRow(array($product->getSku(), 'reviews_count', $rs->getReviewsCount()));
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
			$currentPage++;
		} while ($currentPage <= $totalPages);

		$this->log('done processing attributes');
		return true;
	}

	/**
	 * @return bool
	 * @throws Mage_Core_Exception
	 */
	protected function _getCategoryData() {
		$this->log('starting _getCategoryData()');
		$filename = $this->_feedPath . DS . "hierarchy" . '.' . $this->outputFileExtension;

		$collection = Mage::getModel('catalog/category')->getCollection();
		$collection->addAttributeToSelect(array('name', 'is_active', 'parent_id', 'position'));
		$collection->addAttributeToFilter('is_active', array('eq' => '1'));
		$collection->addAttributeToSort('entity_id')->addAttributeToSort('parent_id')->addAttributeToSort('position');

		$collection->setPageSize($this->_batchSize);
		$pages = $collection->getLastPageNumber();
		$currentPage = 1;

		$this->log(sprintf('going to open feed file %s', $filename));
		$output = new CsvWriter($filename, $this->outputFileDelimiter, $this->bufferSize);
		$this->log('file open, going to append header and root');
		$output->appendRow(array('category_id', 'category_name', 'parent_category_id', 'sort_order', 'is_active', 'category_url'));
		$output->appendRow(array('1', 'Root', '0', '0', '1', '/'));
		$this->log('header and root appended');
		$base = Mage::app()->getStore()->getBaseUrl();

		$cats = array();
		do {
			$this->log(sprintf('getting category page %d', $currentPage));
			$collection->setCurPage($currentPage);
			$collection->clear();
			$collection->load();
			foreach ($collection as $cat) {
				$fullUrl = Mage::helper('catalog/category')->getCategoryUrl($cat);
				$category_url = substr($fullUrl, strlen($base));
				if(substr($category_url, 0, 1) != '/'){
					$category_url = '/' . $category_url;
				}
				$this->log(sprintf("got full category url: %s, returning relative url %s", $fullUrl, $category_url));
				$cats[] = array(
					'id' => $cat->getId(),
					'name' => $cat->getName(),
					'pid' => $cat->getParentId(),
					'pos' => $cat->getPosition(),
					'ia' => $cat->getIsActive(),
					'url' => $category_url
				);
			}
			$currentPage++;
		} while ($currentPage <= $pages);

		$rcid = Mage::app()->getStore()->getRootCategoryId();
		$myCategories = array();
		foreach($cats as $storecat){
			if($storecat['id'] == $rcid){
				$myCategories[] = $storecat;
			}
		}

		$this->log("using root category id: $rcid");
		$this->r_find($rcid, $cats, $myCategories);

		foreach($myCategories as $final) {
			$output->appendRow(array(
				$final['id'],
				$final['name'],
				$final['pid'],
				$final['pos'],
				$final['ia'],
				$final['url']
			));
		}

		$this->log('done with _getCategoryData()');
		return true;
	}

	/**
	 * Recursively sets up the category tree without introducing
	 * duplicate data.
	 *
	 * @param $pid
	 * @param $all
	 * @param $tree
	 */
	private function r_find($pid, &$all, &$tree) {
		foreach($all as $item) {
			if($item['pid'] == $pid) {
				$tree[] = $item;
				$this->r_find($item['id'], $all, $tree);
			}
		}
	}

	protected function _getProductData() {
		$this->log('starting _getProductData()');
		/** @var Mage_Catalog_Helper_Product $helper */
		$helper = Mage::helper('catalog/product');
		$urlSuffix = $helper->getProductUrlSuffix(0);
		if (!empty($urlSuffix)) {
			if (substr($urlSuffix, 0, 1) != '.') {
				$urlSuffix = '.' . $urlSuffix;
			}
		}
		$this->log(sprintf("using '%s' for the product url suffix", $urlSuffix));

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
    'url_key',
	'$brandAttribute'
)
AND entity_type_id = '$entity_type_id'
EOSQL;

		$this->log('fetching attribute codes');
		if ($codeRows = $write->fetchAll($attrCodesQuery)) {
			foreach ($codeRows as $row) {
				$attrCodes[$row['attribute_code']] = $row['attribute_id'];
			}
		}
		$this->log(sprintf('found %d codes: %s', count($attrCodes), implode("', '", array_keys($attrCodes))));

		$disabled_sql = '';
		$CONN = ' WHERE ';
		if (!$allowDisabled) {
			$disabled_sql = "LEFT JOIN $productEntityIntTable AS T6 ON P.entity_id = T6.entity_id AND T6.attribute_id = '" . $attrCodes['status'] . "' AND T6.store_id = '" . $this->_storeId . "'";
		}

		if (!empty($brandAttribute)) {
			$brand_select = ", B5.value AS Brand";
			$brand_sql = "LEFT JOIN " . $productEntityIntTable . " AS B5 ON P.entity_id = B5.entity_id AND B5.attribute_id = '" . $attrCodes[$brandAttribute] . "' AND B5.store_id = '" . $this->_storeId . "'";
		}
		$urlfield = ", CONCAT(V1.value, '" . $urlSuffix . "') AS Link";
		$urljoin = "LEFT JOIN $productEntityUrlTable AS V1  ON P.entity_id = V1.entity_id  AND V1.attribute_id  = '" . $attrCodes['url_key'] . "' AND V1.store_id = '" . $this->_storeId . "'";
		if (!Mage::getSingleton('core/resource')->getConnection('core_write')->isTableExists('catalog_product_entity_url_key')) {
			$this->log('using catalog_product_entity_url_table');
			$urljoin = ' LEFT JOIN  ' . $productEntityVarCharTable . " AS V1  ON P.entity_id = V1.entity_id  AND V1.attribute_id  = '" . $attrCodes['url_key'] . "' AND V1.store_id = '" . $this->_storeId . "'";
		}

		$select_qry = "SELECT  P.attribute_set_id, P.entity_id AS ProductID, P.type_id, P.sku, P.has_options, V.value AS Name, T1.value AS ProdDesc, T2.value AS ShortDesc, T3.value AS MetaKeyword, T5.value AS visibility, D.value AS Price, S.value AS Special_Price, SDF.value As Special_Date_From, SDT.value As Special_Date_To, ST.qty, ST.is_in_stock AS IsInStock, X.value AS Msrp" . $brand_select . ", R.parent_id AS GroupID $urlfield,
                        CASE
                            WHEN V2.Value IS NULL
                                THEN '/no-image.jpg'
                            ELSE CONCAT('', V2.value)
                        END AS Image
                        FROM $productEntityTable AS P
                        INNER JOIN " . $productEntityVarCharTable . "  AS V   ON P.entity_id = V.entity_id   AND V.attribute_id   = '" . $attrCodes['name'] . "' AND V.store_id = '" . $this->_storeId . "'
                        INNER JOIN " . $catalogInventoryStockTable . " AS ST  ON P.entity_id = ST.product_id
                        INNER JOIN " . $productEntityIntTable . " AS VIS ON VIS.entity_id = P.entity_id AND VIS.value != 1
						" . $urljoin . "
                        LEFT JOIN  " . $productEntityVarCharTable . "  AS V2  ON P.entity_id = V2.entity_id  AND V2.attribute_id  = '" . $attrCodes['image'] . "' AND V2.store_id = '" . $this->_storeId . "'
                        LEFT JOIN  " . $productEntityTextTable . "     AS T1  ON P.entity_id = T1.entity_id  AND T1.attribute_id  = '" . $attrCodes['description'] . "' AND T1.store_id = '" . $this->_storeId . "'
                        LEFT JOIN  " . $productEntityTextTable . "     AS T2  ON P.entity_id = T2.entity_id  AND T2.attribute_id  = '" . $attrCodes['short_description'] . "' AND T2.store_id = '" . $this->_storeId . "'
                        LEFT JOIN  " . $productEntityTextTable . "     AS T3  ON P.entity_id = T3.entity_id  AND T3.attribute_id  = '" . $attrCodes['meta_keyword'] . "' AND T3 .store_id = '" . $this->_storeId . "'
                        LEFT JOIN  " . $productEntityIntTable . "      AS T5  ON P.entity_id = T5.entity_id  AND T5.attribute_id  = '" . $attrCodes['visibility'] . "' AND T5.store_id = '" . $this->_storeId . "'
                        " . $disabled_sql . "
                        LEFT JOIN  " . $productEntityDecimalTable . "  AS D   ON P.entity_id = D.entity_id   AND D.attribute_id   = '" . $attrCodes['price'] . "' AND D.store_id = '" . $this->_storeId . "'
                        LEFT JOIN  " . $productEntityDateTimeTable . " AS SDF ON P.entity_id = SDF.entity_id AND SDF.attribute_id = '" . $attrCodes['special_from_date'] . "' AND SDF.store_id = '" . $this->_storeId . "'
                        LEFT JOIN  " . $productEntityDateTimeTable . " AS SDT ON P.entity_id = SDT.entity_id AND SDT.attribute_id = '" . $attrCodes['special_to_date'] . "' AND SDT.store_id = '" . $this->_storeId . "'
					    " . $brand_sql . "
                        LEFT JOIN  " . $productEntityDecimalTable . "  AS X   ON P.entity_id = X.entity_id   AND X.attribute_id   = '" . $attrCodes['msrp'] . "' AND X.store_id = '" . $this->_storeId . "'
                        LEFT JOIN  " . $productRelationTable . "       AS R   ON P.entity_id = R.parent_id   OR  P.entity_id = R.child_id 
                        LEFT JOIN  " . $productEntityDecimalTable . "  AS S   ON P.entity_id = S.entity_id   AND S.attribute_id   = '" . $attrCodes['special_price'] . "' AND S.store_id = '" . $this->_storeId . "'";

		if (!Mage::helper('hawksearch_datafeed/data')->isIncludeOutOfStockItems()) {
			$this->log('filtering for in-stock items');
			$select_qry .= " $CONN ST.is_in_stock = 1";
			$CONN = ' AND ';
		}
		if (!$allowDisabled) {
			$this->log('filtering disabled items');
			$select_qry .= " $CONN T6.value = 1 ";
			$CONN = ' AND ';
		}

		$select_qry .= " GROUP BY P.entity_id ";

		$this->log(sprintf('going to open feed file %s', $filename));
		$output = new CsvWriter($filename, $this->outputFileDelimiter, $this->bufferSize);
		$this->log('appending feed header');
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

		$full_query = 'not yet set';
		while (!$done) {
			try {
				$full_query = $select_qry . ' LIMIT ' . $offset . ', ' . $this->_batchSize;
				$this->log(sprintf('going to fetch %d products starting at offset %d', $this->_batchSize, $offset));
				if ($rows = $write->fetchAll($full_query)) {
					$this->log(sprintf('query returned %d rows', count($rows)));
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
					}
				} else {
					$this->log('done fetching products');
					$done = true;
				}
			} catch (Exception $e) {
				// remove lock
				Mage::helper('hawksearch_datafeed/feed')->RemoveFeedLocks();

				$this->log(sprintf('%s - Exception thrown on line %d or %s: %s', date('c'), $e->getLine(), $e->getFile(), $e->getTraceAsString()));
				$this->log(sprintf("\nSQL was:\n%s", $full_query));
				$this->log('exiting function _getProductData() due to exception');
				return false;
			}
		} // end while
		$this->log('done with _getProductData()');
		return true;
	}

	protected function _getContentData() {
		$this->log('starting _getContentData()');
		$done = false;
		$offset = 0;
		$baseurl = Mage::getUrl();
		//$filename = $this->_feedPath . "/content.txt";
		$filename = $this->_feedPath . DS . "content" . '.' . $this->outputFileExtension;

		//$firstRecord = true;
		$cmsPageTable = $this->_tablePrefix . 'cms_page';


		$select_qry = "SELECT page_id, title, CONCAT('" . $baseurl . "', identifier) AS Link, content_heading, content, creation_time, is_active FROM " . $cmsPageTable . "";

		$write = $this->_getConnection();

		$this->log(sprintf('going to open feed file %s', $filename));
		$output = new CsvWriter($filename, $this->outputFileDelimiter, $this->bufferSize);
		$this->log('feed file open, going to append header');
		$output->appendRow(array('unique_id', 'name', 'url_detail', 'description_short', 'created_date'));
		$this->log('header appended, going to fetch data');

		$full_query = 'not yet set';
		while (!$done) {
			try {
				$this->log(sprintf('going to fetch %d rows at offset %d', $this->_batchSize, $offset));
				$full_query = $select_qry . ' LIMIT ' . $offset . ', ' . $this->_batchSize;
				if ($rows = $write->fetchAll($full_query)) {
					if (($numRows = count($rows)) && $numRows > 0) {
						foreach ($rows as $row) {
							//$content .= $row['page_id'] . "\t" . $row['title'] . "\t" . $row['Link'] . "\t" . $row['content_heading'] . "\t" . $row['creation_time'] . "\n";
							$output->appendRow(array($row['page_id'], $row['title'], $row['Link'], $row['content_heading'], $row['creation_time']));
						}

						$offset += $this->_batchSize;
					} else {
						$done = true;
					}
				} else {
					$done = true;
				}
			} catch (Exception $e) {
				// remove lock
				Mage::helper('hawksearch_datafeed/feed')->RemoveFeedLocks();

				$this->log(sprintf('%s - Exception thrown on line %d or %s: %s', date('c'), $e->getLine(), $e->getFile(), $e->getTraceAsString()));
				$this->log(sprintf("\nSQL was:\n%s", $full_query));
				$this->log('exiting function _getContentData() due to exception');
				return false;
			}
		} // end while
		$this->log('done fetching content data');
		return true;
	}

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

	public function generateFeed() {
		try {
			$sid = Mage::app()->getDefaultStoreView()->getId();
			// for working with proxy module

//			$code = Mage::getStoreConfig('hawksearch_proxy/proxy/store_code');
//			if(!empty($code)) {
//				/** @var Mage_Core_Model_Resource_Store_Collection $store */
//				$store = Mage::getModel('core/store')->getCollection();
//				$sid = $store->addFieldToFilter('code', $code)->getFirstItem()->getId();
//			}

			// end proxy module connection

			$this->log('starting environment for store id: ' . $sid);
			/** @var Mage_Core_Model_App_Emulation $appEmulation */
			$appEmulation = Mage::getSingleton('core/app_emulation');
			$initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($sid);

			//exports Category Data
			$this->_getCategoryData();

			//exports Product Data
			$this->_getProductData();

			//exports Attribute Data
			$this->_getAttributeData();

			//exports CMS / Content Data
			$this->_getContentData();

			//refresh image cache no longer part of feed generation
			//$this->refreshImageCache();
			$this->log('done generating data feed files, going to remove lock files.');
			// remove locks
			Mage::helper('hawksearch_datafeed/feed')->RemoveFeedLocks();
			$appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
		} catch (Exception $e) {
			// remove lock
			Mage::helper('hawksearch_datafeed/feed')->RemoveFeedLocks();
			$this->log(sprintf("General Exception %s at generateFeed() line %d, stack:\n%s", $e->getMessage(), $e->getLine(), $e->getTraceAsString()));
		}
		$this->log('done with generateFeed()');
	}

	public function refreshImageCache($storeid = null) {
		$this->log('starting refreshImageCache()');
//		$sid = Mage::app()->getWebsite()->getDefaultGroup()->getDefaultStoreId();
//		$h = Mage::helper("hawksearch_proxy");
//		if (!empty($h)) {
//			$sid = $h->getCategoryStoreId();
//		}
		$sid = Mage::app()->getDefaultStoreView()->getId();

		$this->log('starting environment for store id: ' . $sid);

		$appEmulation = Mage::getSingleton('core/app_emulation');
		$initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($sid);

		$products = Mage::getModel('catalog/product')
			->getCollection()->addAttributeToSelect(array('image', 'small_image'));
		$products->setPageSize(100);
		$pages = $products->getLastPageNumber();

		$currentPage = 1;

		do {
			$this->log(sprintf('going to page %d of images', $currentPage));
			$products->clear();
			$products->setCurPage($currentPage);
			$products->load();

			foreach ($products as $product) {
				if (empty($this->_imageHeight)) {
					$this->log(
						sprintf('going to resize image for url: %s',
							Mage::helper('catalog/image')->init($product, 'small_image')->resize($this->_imageWidth
							)));
				} else {
					$this->log(
						sprintf('going to resize image for url: %s',
							Mage::helper('catalog/image')->init($product, 'small_image')->resize($this->_imageWidth, $this->_imageHeight
							)));
				}
			}

			$currentPage++;

			//clear collection and free memory

		} while ($currentPage <= $pages);
		$this->log('done generating image cache, going to remove locks.');
		Mage::helper('hawksearch_datafeed/feed')->RemoveFeedLocks();
		$appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
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
		if ($this->outputOpen) {
			if (false === fflush($this->outputFile)) {
				throw new Exception(sprintf("CsvWriter: Failed to flush feed file: %s", $this->finalDestinationPath));
			}
			if (false === fclose($this->outputFile)) {
				throw new Exception(sprintf("CsvWriter: Failed to close feed file ", $this->finalDestinationPath));
			}
			$this->outputOpen = false;
		}
	}
}

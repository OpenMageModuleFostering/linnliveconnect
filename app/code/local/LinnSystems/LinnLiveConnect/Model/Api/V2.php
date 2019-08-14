<?php

//move to models or replace using config if need
class Mage_Catalog_Model_Product_Api_V2_LL extends Mage_Catalog_Model_Product_Api_V2 {

	public function create($type, $set, $sku, $productData, $store = NULL) {
		$tries = 0;
		$maxtries = 3;


		for ($tries = 0; $tries < $maxtries; $tries++) {
			try {
				return parent::create($type, $set, $sku, $productData, $store);
			} catch (Exception $e) {
				if ($e -> getMessage() == 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction') {
					sleep(1);
				} else {
					throw $e;
				}
			}
		}
	}

	public function update($productId, $productData, $store = null, $identifierType = null) {
		$tries = 0;
		$maxtries = 3;

		for ($tries = 0; $tries < $maxtries; $tries++) {
			try {
				return parent::update($productId, $productData, $store, $identifierType);
			} catch (Exception $e) {
				if ($e -> getMessage() == 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction') {
					sleep(1);
				} else {
					throw $e;
				}
			}
		}
	}

}

class LinnSystems_LinnLiveConnect_Model_Api_V2 {

	public function checkProducts($version, $data) {

		$worker = Factory::createWorker($version);
		return $worker -> checkProducts($data);
	}

	public function createSimpleProducts($version, $data) {
		$worker = Factory::createWorker($version);
		return $worker -> createSimpleProducts($data);
	}

	public function createConfigurableProducts($version, $data) {

		$worker = Factory::createWorker($version);
		return $worker -> createConfigurableProducts($data);
	}

	public function createRelatedProducts($version, $data) {

		$worker = Factory::createWorker($version);
		return $worker -> createRelatedProducts($data);
	}

	public function createProductImages($version, $data) {

		$worker = Factory::createWorker($version);
		return $worker -> createProductImages($data);
	}

	public function updateSimpleProducts($version, $data) {

		$worker = Factory::createWorker($version);
		return $worker -> updateSimpleProducts($data);
	}

	public function updateConfigurableProducts($version, $data) {

		$worker = Factory::createWorker($version);
		return $worker -> updateConfigurableProducts($data);
	}

	public function updateProductImages($version, $data) {

		$worker = Factory::createWorker($version);
		return $worker -> updateProductImages($data);
	}

	public function updatePriceBulk($version, $data, $store = null, $identifierType = 'id') {

		$worker = Factory::createWorker($version);
		return $worker -> updateProductPrices($data, $store, $identifierType);
	}

	public function deleteProducts($version, $data) {

		$worker = Factory::createWorker($version);
		return $worker -> deleteProducts($data);
	}

	public function deleteRelatedProducts($version, $data) {

		$worker = Factory::createWorker($version);
		return $worker -> deleteRelatedProducts($data);
	}

	public function deleteProductImages($version, $data) {

		$worker = Factory::createWorker($version);
		return $worker -> deleteProductImages($data);
	}

	public function getProductStoreURL($version, $productId, $store = null, $identifierType = 'id') {

		$worker = Factory::createWorker($version);
		return $worker -> getProductStoreURL($productId, $store, $identifierType);
	}

	public function getStoreCode($version, $store = null) {

		$worker = Factory::createWorker($version);
		return $worker -> getStoreCode($store);
	}

	public function getGeneralInfo($version) {

		$worker = Factory::createWorker($version);
		return $worker -> getGeneralInfo();
	}

	//todo: rename
	public function productList($version, $page, $perPage, $filters = null, $store = null) {

		$worker = Factory::createWorker($version);
		return $worker -> getProductList($page, $perPage, $filters, $store);
	}

	//todo: rename
	public function productAttributeOptions($version, $setId) {

		$worker = Factory::createWorker($version);
		return $worker -> getProductAttributeOptions($setId);
	}

	public function storesList($version) {

		$worker = Factory::createWorker($version);
		return $worker -> storesList();
	}

	public function disableIndexing($version) {
		$worker = Factory::createWorker($version);
		return $worker -> disableIndexing();
	}

	public function restoreIndexingById($version, $data) {
		$worker = Factory::createWorker($version);
		return $worker -> restoreIndexingById($data);
	}

}

class Factory {

	private static function _checkVersion($version) {
		$version = intval($version);

		if ($version == 0) {
			throw new Mage_Api_Exception('version_not_specified');
		}

		if (Mage::helper('linnLiveConnect/settings') -> getShortVersion() < $version) {
			throw new Mage_Api_Exception('wrong_version');
		}
	}

	public static function createWorker($version) {
		self::_checkVersion($version);

		if (Mage::GetEdition() == Mage::EDITION_COMMUNITY || Mage::GetEdition() == Mage::EDITION_ENTERPRISE) {
			return new LinnLiveCommunity();
		}

		throw new Mage_Api_Exception('unsupported_edition');
	}

}

class LinnLiveMain extends Mage_Core_Model_Abstract {

	protected $_ignoredAttributes = array('created_at', 'updated_at', 'category_ids', 'required_options', 'old_id', 'url_key', 'url_path', 'has_options', 'image_label', 'small_image_label', 'thumbnail_label', 'image', 'small_image', 'thumbnail', 'options_container', 'entity_id', 'entity_type_id', 'attribute_set_id', 'type_id', 'sku', 'name', 'status', 'stock_item', 'description', );

	protected $_permittedAttributes = array('select', 'multiselect', 'text', 'textarea', 'date', 'price');

	protected function _prepareConfigurableData($productsSet, $attributesSet, $isUpdate) {
		$helper = Mage::helper('linnLiveConnect');

		$assignedProductsArray = $helper -> objectToArray($this -> _createProductsData($productsSet));

		$_newAttributeOptions = $this -> _newConfigurableOptions($assignedProductsArray);
		if (count($_newAttributeOptions) > 0) {
			$this -> _checkAssignedProductsOptions($helper -> createOptions($_newAttributeOptions), $assignedProductsArray);
		}

		if (!is_array($attributesSet)) {
			$attributesSet = array($attributesSet);
		}

		$attributesSetArray = $this -> _prepareAttributesData($helper -> objectToArray($attributesSet), $assignedProductsArray);

		foreach ($attributesSetArray as $key => $value) {
			$attributesSetArray[$key]["id"] = NULL;
			$attributesSetArray[$key]["position"] = NULL;
			$attributesSetArray[$key]["store_label"] = isset($value['frontend_label']) ? $value['frontend_label'] : NULL;
			//$attributesSetArray[$key]["use_default"] = 0;

			if ($isUpdate == false) {
				//check if attribute exists and available
				$checkAttribute = Mage::getModel('catalog/resource_eav_attribute') -> loadByCode('catalog_product', $attributesSetArray[$key]["attribute_code"]);

				if (!$checkAttribute -> getId() || !$this -> _isConfigurable($checkAttribute)) {
					throw new Mage_Api_Exception('invalid_variation_attribute', 'Invalid attribute [' . $checkAttribute['attribute_code'] . '] provided to Magento extension for creating Variation / Product with options. Check attributes/variations in LinnLive Magento configurator if they do exist/match the ones on the back-end.');
				}
			}

		}
		return array($assignedProductsArray, $attributesSetArray);
	}

	protected function _isConfigurable($attribute) {
		$isConfigurable = 0;

		if (isset($attribute['is_global']) && $attribute['is_global']) {
			$attribute['scope'] = 'global';
		}

		if (($attribute['scope'] == 'global') && ($attribute['is_configurable'])) {
			if (is_array($attribute['apply_to']) && sizeof($attribute['apply_to'])) {
				if (in_array('simple', $attribute['apply_to'])) {
					$isConfigurable = 1;
				}
			} elseif (is_string($attribute['apply_to']) && strlen($attribute['apply_to'])) {
				if (strpos($attribute['apply_to'], 'simple') !== false) {
					$isConfigurable = 1;
				}
			} else {
				$isConfigurable = 1;
			}
		}
		return $isConfigurable;
	}

	protected function _checkAssignedProductsOptions($availableOptions, &$assignedProductsArray) {
		foreach ($assignedProductsArray as $id => $productOptions) {
			foreach ($productOptions as $index => $option) {
				if (isset($availableOptions[$option['attribute_id']][strtolower($option['label'])])) {
					$assignedProductsArray[$id][$index]['value_index'] = $availableOptions[$option['attribute_id']][strtolower($option['label'])];
				}
			}
		}
	}

	protected function _newConfigurableOptions($assignedProductsArray) {
		$_attributesOptions = array();
		foreach ($assignedProductsArray as $id => $productOptions) {
			foreach ($productOptions as $option) {
				if (isset($option['value_index']) && $option['value_index'] == '-1') {
					if (isset($_attributesOptions[$option['attribute_id']])) {
						$_attributesOptions[$option['attribute_id']][] = $option['label'];
					} else {
						$_attributesOptions[$option['attribute_id']] = array($option['label']);
					}
				}
			}
		}
		return $_attributesOptions;
	}

	protected function _containsOption($attributeOption, $option) {
		foreach ($attributeOption as $inArrayOption)
			if ($inArrayOption['value_index'] == $option['value_index'])
				return true;

		return false;
	}

	protected function _prepareAttributesData($attributesSetArray, $assignedProductsArray) {

		$_attributesOptions = array();
		foreach ($assignedProductsArray as $id => $productOptions) {
			foreach ($productOptions as $option) {
				if (isset($_attributesOptions[$option['attribute_id']]) && !$this -> _containsOption($_attributesOptions[$option['attribute_id']], $option)) {
					$_attributesOptions[$option['attribute_id']][] = $option;
				} else if (!isset($_attributesOptions[$option['attribute_id']])) {
					$_attributesOptions[$option['attribute_id']] = array();
					$_attributesOptions[$option['attribute_id']][] = $option;
				}
			}
		}

		foreach ($attributesSetArray as $key => $attribute) {
			if (isset($_attributesOptions[$attribute['attribute_id']])) {
				$attributesSetArray[$key]['values'] = $_attributesOptions[$attribute['attribute_id']];
			}
		}

		return $attributesSetArray;
	}

	protected function _updateConfigurable($store, $productId, $assignedProducts, $assignedAttributes, $identifierType, $isUpdate = false, $reindex = true) {
		$magentoVer = $this -> _getCurrentVersion();
		if ($magentoVer == 162) {
			$store = Mage::app() -> getStore($store) -> getId();
		} else {
			$store = NULL;
		}

		$product = Mage::helper('catalog/product') -> getProduct($productId, $store, $identifierType);

		$product -> setConfigurableProductsData($assignedProducts);

		if ($isUpdate == false) {
			$product -> setConfigurableAttributesData($assignedAttributes);
			$product -> setCanSaveConfigurableAttributes(true);
		}

		try {
			$result = $product -> save();
		} catch (Exception $e) {
			throw new Mage_Api_Exception('configurable_creating_error', $e -> getMessage());
		}

		return $result;
	}

	protected function _createProductsData($productData) {
		$assignedProductsData = array();

		if (is_array($productData)) {
			foreach ($productData as $product) {
				$assignedProductsData[$product -> product_id] = array();
                if (is_array($product -> values)) {
                    foreach ($product->values as $productValue) {
                        $assignedProductsData[$product -> product_id][] = $productValue;
                    }
                }               
			}
		}

		return $assignedProductsData;
	}

	protected function _getCurrentVersion() {
		$verInfo = Mage::getVersionInfo();

		return intval($verInfo['major'] . $verInfo['minor'] . $verInfo['revision']);
	}

	protected function _removeIgnoredAttributes($attributesList) {
		$_preparedAttributes = array();
		if (is_array($attributesList) && count($attributesList) > 0) {
			foreach ($attributesList as $key => $value) {
				if (!in_array($key, $this -> _ignoredAttributes) && !is_array($value))
					$_preparedAttributes[] = array('key' => $key, 'value' => $value);
			}
		}

		return $_preparedAttributes;
	}

	protected function _productAttributeInfo($attribute_id, $attributeAPI) {
		$model = Mage::getResourceModel('catalog/eav_attribute') -> setEntityTypeId(Mage::getModel('eav/entity') -> setType('catalog_product') -> getTypeId());

		$model -> load($attribute_id);

		if (!$model -> getId()) {
			throw new Mage_Api_Exception('attribute_not_exists');
		}

		if ($model -> isScopeGlobal()) {
			$scope = 'global';
		} elseif ($model -> isScopeWebsite()) {
			$scope = 'website';
		} else {
			$scope = 'store';
		}

		$result = array('attribute_id' => $model -> getId(), 'attribute_code' => $model -> getAttributeCode(), 'frontend_input' => $model -> getFrontendInput(), 'default_value' => $model -> getDefaultValue(), 'is_unique' => $model -> getIsUnique(), 'is_required' => $model -> getIsRequired(), 'apply_to' => $model -> getApplyTo(), 'is_configurable' => $model -> getIsConfigurable(), 'is_searchable' => $model -> getIsSearchable(), 'is_visible_in_advanced_search' => $model -> getIsVisibleInAdvancedSearch(), 'is_comparable' => $model -> getIsComparable(), 'is_used_for_promo_rules' => $model -> getIsUsedForPromoRules(), 'is_visible_on_front' => $model -> getIsVisibleOnFront(), 'used_in_product_listing' => $model -> getUsedInProductListing(), 'scope' => $scope, );

		// set options
		$options = $attributeAPI -> options($model -> getId());
		// remove empty first element
		if ($model -> getFrontendInput() != 'boolean') {
			array_shift($options);
		}

		if (count($options) > 0) {
			$result['options'] = $options;
		}

		return $result;
	}

	protected function _log($message) {
		Mage::log(print_r($message, true), null, 'LinnLiveExt.log');
	}

	/********************************Indexer block***********************************************/
	/*****************************************************************************************/
	/*****************************************************************************************/
	public function disableIndexing() {
		$states = array();
		$blocked = array('cataloginventory_stock', 'catalog_product_flat', 'catalog_category_flat', 'catalogsearch_fulltext');

		$processes = Mage::getSingleton('index/indexer') -> getProcessesCollection();
		foreach ($processes as $process) {

			$code = $process -> getIndexerCode();

			if (in_array($code, $blocked) || $process -> getId() > 9) {
				continue;
			}

			$states[] = array('key' => $code, 'value' => $process -> getMode());

			$process -> setMode(Mage_Index_Model_Process::MODE_MANUAL) -> save();
		}
		return $states;
	}

	public function restoreIndexingById($data) {

		foreach ($data as $key => $value) {

			$process = Mage::getModel('index/indexer') -> getProcessByCode($key);
			if ($process && $process -> getIndexerCode()) {

				$value = $value == Mage_Index_Model_Process::MODE_MANUAL ? Mage_Index_Model_Process::MODE_MANUAL : Mage_Index_Model_Process::MODE_REAL_TIME;

				if ($process -> getMode() != $value) {
					$process -> setMode($value) -> save();
				}

				if ($process -> getStatus() == Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX) {
					$process -> reindexEverything();
				}
			}
		}
	}

	protected function reindexProducts() {
		$processes = Mage::getSingleton('index/indexer') -> getProcessesCollection();
		foreach ($processes as $process) {
			if ($process -> getStatus() == Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX) {
				$process -> reindexEverything();
			}
		}
	}

	protected function lockIndexer() {
        Mage::setIsDeveloperMode(true);
		//Mage::getSingleton('index/indexer') -> getProcessesCollection() -> walk('lockAndBlock');
	}

	protected function unlockIndexer() {
		//Mage::getSingleton('index/indexer') -> getProcessesCollection() -> walk('unlock');
        Mage::setIsDeveloperMode(false);
	}

	protected function cleanCache() {
		Mage::app() -> getCacheInstance() -> flush();
		Mage::app() -> cleanCache();
	}

	protected function disableAllIndexing() {
		$processes = Mage::getSingleton('index/indexer') -> getProcessesCollection();
		$processes -> walk('setMode', array(Mage_Index_Model_Process::MODE_MANUAL));
		$processes -> walk('save');
	}

	protected function enableAllIndexing() {
		$processes = Mage::getSingleton('index/indexer') -> getProcessesCollection();
		//$processes -> walk('reindexAll');
		$processes -> walk('setMode', array(Mage_Index_Model_Process::MODE_REAL_TIME));
		$processes -> walk('save');
	}

}

class LinnLiveCommunity extends LinnLiveMain {

	//obsolete
	public function storesList() {
		return ($this -> _getCurrentVersion() >= 160);
	}

	/**
	 * Get products
	 * Implementation of catalogProductList because of bug in associativeArray.
	 * Extended to filter by category id too.
	 *
	 * Use 'entity_id' for product_id,
	 * 'type_id' instead of product type.
	 * @return array | mixed
	 */
	public function getProductList($page, $perPage, $filters = null, $store = null) {
		$helper = Mage::helper('linnLiveConnect');

		//get store
		try {
			$storeId = Mage::app() -> getStore($helper -> currentStoreCode($store)) -> getId();
		} catch (Mage_Core_Model_Store_Exception $e) {
			throw new Mage_Api_Exception('store_not_exists', null);
		}

		//prepare and convert filters to array
		$preparedFilters = $helper -> convertFiltersToArray($filters);
		if (empty($preparedFilters)) {
			throw new Mage_Api_Exception('filters_invalid', null);
		}

		//load collection
		$collection = Mage::getModel('catalog/product') -> getCollection() -> addStoreFilter($storeId);

		//filter collection by category if exists
		if (isset($preparedFilters['category']) && is_string($preparedFilters['category'])) {
			$_category = Mage::getModel('catalog/category') -> load(intval($preparedFilters['category']));

			if ($_category -> getId()) {
				$collection = $collection -> addCategoryFilter($_category);
			}

			unset($preparedFilters['category']);
		}

		//add prepared filters to collection
		try {
			foreach ($preparedFilters as $field => $data) {
				if (is_array($data)) {
					foreach ($data as $key => $value) {
						$collection -> addFieldToFilter($field, array($key => $value));
					}
				} else {
					$collection -> addFieldToFilter($field, $data);
				}
			}
		} catch (Mage_Core_Exception $e) {
			throw new Mage_Api_Exception('filters_invalid', $e -> getMessage());
		}

		if ($page == 1) {
			//TODO: limit page size
			$count = $collection -> count();
		} else {
			$count = 0;
			$collection -> setPageSize($perPage) -> setCurPage($page);
		}

		$result = array('count' => $count, 'products' => array());

		$_assignedIds = array();
		$_fetchedIds = array();

		$i = 0;
		foreach ($collection as $_product) {

			if ($i >= ($perPage * $page))
				break;
			//TODO remove
			$_loadedProduct = Mage::helper('catalog/product') -> getProduct($_product -> getId(), $storeId, 'id');

			$_allAttributes = $_loadedProduct -> getData();

			$_description = isset($_allAttributes['description']) ? $_allAttributes['description'] : '';

			$_productImages = $helper -> productImages($_allAttributes);
			$_productAttributes = $this -> _removeIgnoredAttributes($_allAttributes);

			$_fetchedIds[] = $_loadedProduct -> getId();

			$result['products'][$i] = array('product_id' => $_loadedProduct -> getId(), 'sku' => $_loadedProduct -> getSku(), 'name' => $_loadedProduct -> GetName(), 'set' => $_loadedProduct -> getAttributeSetId(), 'type' => $_loadedProduct -> getTypeId(), 'price' => $_loadedProduct -> getPrice(), 'status' => $_loadedProduct -> getStatus(), 'description' => $_description, 'category_ids' => $_loadedProduct -> getCategoryIds(), 'website_ids' => $_loadedProduct -> getWebsiteIds(), 'assigned_ids' => array(), 'conf_attrib_ids' => array(), 'images' => $_productImages, 'attributes' => $_productAttributes, );

			if ($_loadedProduct -> getTypeId() == "configurable") {
				$_typeInstance = $_loadedProduct -> getTypeInstance();
				$result['products'][$i]['assigned_ids'] = $_typeInstance -> getUsedProductIds();
				foreach ($_typeInstance->getConfigurableAttributes() as $attribute) {
					$_prices = array();
					foreach ($attribute->getPrices() as $price) {
						$_prices[] = array('value_index' => $price['value_index'], 'is_fixed' => !$price['is_percent'], 'price_diff' => $price['pricing_value'], 'label' => $price['label'], );
					}

					$result['products'][$i]['conf_attrib_ids'][] = array('code' => $attribute -> getProductAttribute() -> getAttributeCode(), 'prices' => $_prices);
				}
				$_assignedIds = array_merge($_assignedIds, $result['products'][$i]['assigned_ids']);
			}

			$i++;
		}

		$_absentIds = array_diff($_assignedIds, $_fetchedIds);

		if (count($_absentIds) > 0) {
			$collection = Mage::getModel('catalog/product') -> getCollection() -> addIdFilter($_absentIds);

			foreach ($collection as $_product) {
				$_loadedProduct = Mage::helper('catalog/product') -> getProduct($_product -> getId(), $storeId, 'id');

				$_allAttributes = $_product -> getData();

				$_description = isset($_allAttributes['description']) ? $_allAttributes['description'] : '';

				$_productImages = $helper -> productImages($_allAttributes);
				$_productAttributes = $this -> _removeIgnoredAttributes($_allAttributes);

				$result['products'][] = array('product_id' => $_loadedProduct -> getId(), 'sku' => $_loadedProduct -> getSku(), 'name' => $_loadedProduct -> GetName(), 'set' => $_loadedProduct -> getAttributeSetId(), 'type' => $_loadedProduct -> getTypeId(), 'price' => $_loadedProduct -> getPrice(), 'status' => $_loadedProduct -> getStatus(), 'description' => $_description, 'category_ids' => $_loadedProduct -> getCategoryIds(), 'website_ids' => $_loadedProduct -> getWebsiteIds(), 'assigned_ids' => array(), 'conf_attrib_ids' => array(), 'images' => $_productImages, 'attributes' => $this -> _removeIgnoredAttributes($_loadedProduct -> getData()), );
			}
		}

		return $result;
	}

	/**
	 * Get attribute set attrobites
	 *
	 * @return array | mixed
	 */
	public function getProductAttributeOptions($setId) {

		$result = array();

		$setId = intval($setId);
		if ($setId <= 0) {
			return $result;
		}

		$attributeAPI = Mage::getModel('catalog/product_attribute_api');

		$items = $attributeAPI -> items($setId);

		$attributes = Mage::getModel('catalog/product') -> getResource() -> loadAllAttributes();

		foreach ($items as $item) {
			if (!isset($item['attribute_id']) || empty($item['attribute_id']))
				continue;
			$attributeId = intval($item['attribute_id']);
			if ($attributeId <= 0)
				continue;

			$additionInfo = $this -> _productAttributeInfo($attributeId, $attributeAPI);

			if (in_array($additionInfo['frontend_input'], $this -> _permittedAttributes) && !in_array($additionInfo['attribute_code'], $this -> _ignoredAttributes)) {

				$attribute = array('attribute_id' => $additionInfo['attribute_id'], 'code' => $additionInfo['attribute_code'], 'type' => $additionInfo['frontend_input'], 'required' => $additionInfo['is_required'], 'scope' => $additionInfo['scope'], 'can_config' => 0);

				if (($additionInfo['frontend_input'] == 'select') || ($additionInfo['frontend_input'] == 'multiselect')) {
					if (isset($additionInfo['options'])) {

						if (sizeof($additionInfo['options']) && is_array($additionInfo['options'][0]['value'])) {
							continue;
							//ignore attributes with multidimensional options
						}
						$attribute['attribute_options'] = $additionInfo['options'];
					}

					$attribute['can_config'] = $this -> _isConfigurable($additionInfo);
				}

				$result[] = $attribute;
			}
		}

		return $result;
	}

	/**
	 * Get general information about magento installation
	 *
	 * @return array | mixed
	 */
	public function getGeneralInfo() {
		$config = Mage::getStoreConfig("api/config");
		$verInfo = Mage::getVersionInfo();

		$result = array('llc_ver' => Mage::helper('linnLiveConnect/settings') -> getVersion(), 'magento_ver' => trim("{$verInfo['major']}.{$verInfo['minor']}.{$verInfo['revision']}" . ($verInfo['patch'] != '' ? ".{$verInfo['patch']}" : "") . "-{$verInfo['stability']}{$verInfo['number']}", '.-'), 'php_ver' => phpversion(), 'api_config' => $config, 'compilation_enabled' => (bool)(defined('COMPILER_INCLUDE_PATH')), 'max_upload_size' => min((int)ini_get("upload_max_filesize"), (int)ini_get("post_max_size"), (int)ini_get("memory_limit")));

		return $result;
	}

	/**
	 * Get store code
	 *
	 * @return string
	 */
	public function getStoreCode($store = null) {
		$helper = Mage::helper('linnLiveConnect');
		return $helper -> currentStoreCode($store);
	}

	/**
	 * Get product url
	 *
	 * @return string
	 */
	public function getProductStoreURL($productId, $store = null, $identifierType = 'id') {

		$storeId = $this -> getStoreCode($store);

		$_loadedProduct = Mage::helper('catalog/product') -> getProduct($productId, $storeId, $identifierType);

		if (!$_loadedProduct -> getId()) {
			throw new Mage_Api_Exception('product_not_exists', null);
		}

		return $_loadedProduct -> getProductUrl();
	}

	/********************************Single block***********************************************/
	/*****************************************************************************************/
	/*****************************************************************************************/
	/**
	 * Check if product exists
	 *
	 * @return boolean
	 */
	protected function checkProduct($sku, $store = null, $identifierType = 'id') {
		$product = Mage::helper('catalog/product') -> getProduct($sku, $store, $identifierType);
		return ($product && $product -> getId());
	}

	/**
	 * Create simple product
	 *
	 * @return int
	 */
	public function createSimpleProduct($type, $set, $sku, $productData, $store = null, $allowToUseInventoryProduct = true) {
		$helper = Mage::helper('linnLiveConnect');

        if($allowToUseInventoryProduct){        
	        $product = $helper -> getProductBySku($sku);
	        if ($product) {   
                    return $product -> getId();
	        }
        }

		$store = $helper -> currentStoreCode($store);

		$productData = $helper -> createProductData($productData);

		$productData = $helper -> updateProperties($productData);

		$productData = $helper -> fixAttributes($productData);

		$productAPI = new Mage_Catalog_Model_Product_Api_V2_LL();

		return $productAPI -> create($type, $set, $sku, $productData, $store);
	}

	/**
	 * Create configurable product
	 *
	 * @return int
	 */
	public function createConfigurableProduct($set, $sku, $reindex, $productData, $productsSet, $attributesSet, $store = null) {

		if (!$set || !$sku) {
			throw new Mage_Api_Exception('data_invalid');
		}

		$helper = Mage::helper('linnLiveConnect');

		$helper -> updateConfigurableQuantity($productData);

		$productData = $helper -> createProductData($productData);

		$productData = $helper -> fixAttributes($productData);

		$store = $helper -> currentStoreCode($store);

		//merge into 1?
		$productAPI = new Mage_Catalog_Model_Product_Api_V2_LL();
		$productId = $productAPI -> create('configurable', $set, $sku, $productData, $store);

		list($assignedProductsArray, $attributesSetArray) = $this -> _prepareConfigurableData($productsSet, $attributesSet, false);
		$this -> _updateConfigurable($store, $productId, $assignedProductsArray, $attributesSetArray, 'id', false, $reindex);

		return $productId;
	}

	/**
	 * Create product image
	 *
	 * @return string
	 */
	protected function createProductImage($productId, $data, $store = null, $identifierType = 'id') {

		return Mage::getModel('catalog/product_attribute_media_api') -> create($productId, Mage::helper('linnLiveConnect') -> objectToArray($data), $store, $identifierType);
	}

	/**
	 * Create product link association
	 *
	 * @return boolean
	 */
	protected function createRelatedProduct($type, $productId, $linkedProductId, $identifierType = 'id') {

		return Mage::getModel('catalog/product_link_api') -> assign($type, $productId, $linkedProductId, null, $identifierType);
	}

	/**
	 * Update simple product
	 *
	 * @return boolean
	 */
	public function updateSimpleProduct($productId, $productData, $store = null, $identifierType = 'id') {

		$helper = Mage::helper('linnLiveConnect');
		$store = $helper -> currentStoreCode($store);

		$helper -> updateProductData($productId, $productData, $store, $identifierType);

		$productData = $helper -> updateProperties($productData);

		$productData = $helper -> fixAttributes($productData);

		$productAPI = new Mage_Catalog_Model_Product_Api_V2();

		return $productAPI -> update($productId, $productData, $store, $identifierType);
	}

	/**
	 * Update configurable product
	 *
	 * @return boolean
	 */
	public function updateConfigurableProduct($productId, $reindex, $productData, $productsSet, $attributesSet, $store = null, $identifierType = 'id') {

		$helper = Mage::helper('linnLiveConnect');

		$helper -> updateConfigurableQuantity($productData);

		$productData = $helper -> fixAttributes($productData);

		$store = $helper -> currentStoreCode($store);

		$helper -> updateProductData($productId, $productData, $store, $identifierType);

		$productAPI = new Mage_Catalog_Model_Product_Api_V2();

		$productAPI -> update($productId, $productData, $store, $identifierType);

		list($assignedProductsArray, $attributesSetArray) = $this -> _prepareConfigurableData($productsSet, $attributesSet, true);

		return $this -> _updateConfigurable($store, $productId, $assignedProductsArray, $attributesSetArray, $identifierType, true, $reindex);
	}

	/**
	 * Update product image
	 *
	 * @return boolean
	 */
	protected function updateProductImage($productId, $file, $data, $store = null, $identifierType = 'id') {

		return Mage::getModel('catalog/product_attribute_media_api') -> update($productId, $file, Mage::helper('linnLiveConnect') -> objectToArray($data), $store, $identifierType);
	}

	/**
	 * Update product price
	 *
	 * @return boolean
	 */
	protected function updateProductPrice($productId, $price, $store = null, $identifierType = 'id') {

		$product = Mage::helper('catalog/product') -> getProduct($productId, $store, $identifierType);

		if ($product && $product -> getId()) {
			if ($product -> getPrice() != $price) {
				$product -> setPrice($price);
				$product -> save();
			}
			return true;
		}
		return false;
	}

	/**
	 * Delete product
	 *
	 * @return boolean
	 */
	protected function deleteProduct($productId, $store = null, $identifierType = 'id') {
		$product = Mage::helper('catalog/product') -> getProduct($productId, $store, $identifierType);
		if ($product && $product -> getId()) {
			return $product -> delete();
		}
		return false;
	}

	/**
	 * Delete product image
	 *
	 * @return boolean
	 */
	protected function deleteProductImage($productId, $file, $identifierType = 'id') {

		return Mage::getModel('catalog/product_attribute_media_api') -> remove($productId, $file, $identifierType);
	}

	/**
	 * Remove product link association
	 *
	 * @return boolean
	 */
	protected function deleteRelatedProduct($type, $productId, $linkedProductId, $identifierType = 'id') {

		return Mage::getModel('catalog/product_link_api') -> remove($type, $productId, $linkedProductId, $identifierType);
	}

	/********************************Bulk block***********************************************/
	/*****************************************************************************************/
	/*****************************************************************************************/
	/**
	 * Bulk check products by sku/productId
	 */
	public function checkProducts($data) {

		$response = array();

		for ($i = 0; $i < sizeof($data); $i++) {
			$entity = $data[$i];
			$product = Mage::helper('catalog/product') -> getProduct();
			$response[] = array('sku' => $entity -> sku, 'success' => $this -> checkProduct($entity -> sku, $entity -> store, $entity -> identifierType));
		}

		return $response;

	}

	/**
	 * Bulk create simple products
	 */
	public function createSimpleProducts($data) {
		$this -> lockIndexer();

		$response = array();

		for ($i = 0; $i < sizeof($data); $i++) {
			$entity = $data[$i];
			try {
				$productId = $this -> createSimpleProduct('simple', $entity -> set, $entity -> sku, $entity -> productData, $entity -> store, false);
				$response[] = array('sku' => $entity -> sku, 'productId' => $productId, 'isError' => ($productId < 1));
			} catch (Exception $e) {
				$response[] = array('sku' => $entity -> sku, 'productId' => 0, 'isError' => true, 'error' => $e -> getMessage());
			}
		}

		$this -> unlockIndexer();

		return $response;
	}

	/**
	 * Bulk create configurable products
	 */
	public function createConfigurableProducts($data) {

		$this -> lockIndexer();

		$response = array();

		for ($i = 0; $i < sizeof($data); $i++) {
			$entity = $data[$i];
			try {
				$productId = $this -> createConfigurableProduct($entity -> set, $entity -> sku, false, $entity -> productData, $entity -> productsSet, $entity -> attributesSet, $entity -> store);
				$response[] = array('sku' => $entity -> sku, 'productId' => $productId, 'isError' => ($productId < 1));

			} catch (Exception $e) {
				$response[] = array('sku' => $entity -> sku, 'productId' => 0, 'isError' => true, 'error' => $e -> getMessage());
			}
		}

		$this -> unlockIndexer();

		return $response;
	}

	/**
	 * Bulk create related products
	 */
	public function createRelatedProducts($data) {

		$this -> lockIndexer();

		$response = array();

		for ($i = 0; $i < sizeof($data); $i++) {
			$entity = $data[$i];
			try {
				$result = $this -> createRelatedProduct($entity -> type, $entity -> productId, $entity -> linkedProductId, null, $entity -> identifierType);
				$response[] = array('relatedId' => $entity -> relatedId, 'productId' => $entity -> productId, 'isError' => !$result);
			} catch (Exception $e) {
				$response[] = array('relatedId' => $entity -> relatedId, 'productId' => $entity -> productId, 'isError' => true, 'error' => $e -> getMessage());
			}
		}

		$this -> unlockIndexer();

		return $response;
	}

	/**
	 * Bulk create product images
	 */
	public function createProductImages($data) {

		$this -> lockIndexer();

		$response = array();

		for ($i = 0; $i < sizeof($data); $i++) {
			$entity = $data[$i];
			try {
				$result = $this -> createProductImage($entity -> productId, $entity -> data, $entity -> store, $entity -> identifierType);
				$response[] = array('imageId' => $entity -> imageId, 'productId' => $entity -> productId, 'isError' => empty($result), 'file' => $result);
			} catch (Exception $e) {
				$response[] = array('imageId' => $entity -> imageId, 'productId' => $entity -> productId, 'isError' => true, 'error' => $e -> getMessage());
			}
		}

		$this -> unlockIndexer();

		return $response;
	}

	/**
	 * Bulk update product images
	 */
	public function updateProductImages($data) {

		$this -> lockIndexer();

		$response = array();

		for ($i = 0; $i < sizeof($data); $i++) {
			$entity = $data[$i];
			try {
				$result = $this -> updateProductImage($entity -> productId, $entity -> file, $entity -> data, $entity -> store, $entity -> identifierType);
				$response[] = array('imageId' => $entity -> imageId, 'productId' => $entity -> productId, 'isError' => !$result);
			} catch (Exception $e) {
				$response[] = array('imageId' => $entity -> imageId, 'productId' => $entity -> productId, 'isError' => true, 'error' => $e -> getMessage());
			}
		}

		$this -> unlockIndexer();

		return $response;
	}

	/**
	 * Bulk price update, TODO: success change to isError
	 */
	public function updateProductPrices($data, $store, $identifierType = 'id') {

		$this -> lockIndexer();

		$response = array();

		for ($i = 0; $i < sizeof($data); $i++) {
			$entity = $data[$i];
			try {
				$result = $this -> updateProductPrice($entity -> sku, $entity -> price, $store, $identifierType);
				$response[] = array('sku' => $entity -> sku, 'success' => $result);
			} catch (Exception $e) {
				$response[] = array('sku' => $entity -> sku, 'success' => false);
			}
		}

		$this -> unlockIndexer();

		return $response;
	}

	/**
	 * Bulk update simple products
	 */
	public function updateSimpleProducts($data) {

		$this -> lockIndexer();

		$response = array();

		for ($i = 0; $i < sizeof($data); $i++) {
			$entity = $data[$i];
			try {
				$result = $this -> updateSimpleProduct($entity -> productId, $entity -> productData, $entity -> store, $entity -> identifierType);
				$response[] = array('sku' => $entity -> sku, 'productId' => $entity -> productId, 'isError' => !$result);
			} catch (Exception $e) {
				$response[] = array('sku' => $entity -> sku, 'productId' => $entity -> productId, 'isError' => true, 'error' => $e -> getMessage());
			}
		}

		$this -> unlockIndexer();

		return $response;
	}

	/**
	 * Bulk update configurable products
	 */
	public function updateConfigurableProducts($data) {

		$this -> lockIndexer();

		$response = array();

		for ($i = 0; $i < sizeof($data); $i++) {
			$entity = $data[$i];

			try {
				$result = $this -> updateConfigurableProduct($entity -> productId, false, $entity -> productData, $entity -> productsSet, $entity -> attributesSet, $entity -> store, $entity -> identifierType);
				$response[] = array('sku' => $entity -> sku, 'productId' => $entity -> productId, 'isError' => !$result);
			} catch (Exception $e) {
				$response[] = array('sku' => $entity -> sku, 'productId' => $entity -> productId, 'isError' => true, 'error' => $e -> getMessage());
			}
		}

		$this -> unlockIndexer();

		return $response;
	}

	/**
	 * Bulk delete products
	 */
	public function deleteProducts($data) {

		$this -> lockIndexer();

		$response = array();

		for ($i = 0; $i < sizeof($data); $i++) {
			$entity = $data[$i];
			try {
				$result = $this -> deleteProduct($entity -> productId, $entity -> store, $entity -> identifierType);

				$response[] = array('sku' => $entity -> sku, 'productId' => $entity -> productId, 'isError' => !$result);

			} catch (Exception $e) {
				$response[] = array('sku' => $entity -> sku, 'productId' => $entity -> productId, 'isError' => true, 'error' => $e -> getMessage());
			}
		}

		$this -> unlockIndexer();

		return $response;
	}

	/**
	 * Bulk delete related products
	 */
	public function deleteRelatedProducts($data) {

		$this -> lockIndexer();

		$response = array();

		for ($i = 0; $i < sizeof($data); $i++) {
			$entity = $data[$i];
			try {
				$result = $this -> deleteRelatedProduct($entity -> type, $entity -> productId, $entity -> linkedProductId, $entity -> identifierType);
				$response[] = array('relatedId' => $entity -> relatedId, 'productId' => $entity -> productId, 'isError' => !$result);
			} catch (Exception $e) {
				$response[] = array('relatedId' => $entity -> relatedId, 'productId' => $entity -> productId, 'isError' => true, 'error' => $e -> getMessage());
			}
		}

		$this -> unlockIndexer();

		return $response;
	}

	/**
	 * Bulk delete product images
	 */
	public function deleteProductImages($data) {

		$this -> lockIndexer();

		$response = array();

		for ($i = 0; $i < sizeof($data); $i++) {
			$entity = $data[$i];
			try {
				$result = $this -> deleteProductImage($entity -> productId, $entity -> file, $entity -> identifierType);
				$response[] = array('imageId' => $entity -> imageId, 'productId' => $entity -> productId, 'isError' => !$result);
			} catch (Exception $e) {
				$response[] = array('imageId' => $entity -> imageId, 'productId' => $entity -> productId, 'isError' => true, 'error' => $e -> getMessage());
			}
		}

		$this -> unlockIndexer();

		return $response;
	}

}
?>
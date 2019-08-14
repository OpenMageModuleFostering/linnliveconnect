<?php
class LinnSystems_LinnLiveConnect_Model_Main extends Mage_Core_Model_Abstract {

	protected $_ignoredAttributes = array('created_at', 'updated_at', 'category_ids', 'required_options', 'old_id', 'url_key', 'url_path', 'has_options', 'image_label', 'small_image_label', 'thumbnail_label', 'image', 'small_image', 'thumbnail', 'options_container', 'entity_id', 'entity_type_id', 'attribute_set_id', 'type_id', 'sku', 'name', 'status', 'stock_item', 'description', );

	protected $_permittedAttributes = array('select', 'multiselect', 'text', 'textarea', 'date', 'price');

	protected function _prepareConfigurableData($productsSet, $attributesSet, $productId, $isUpdate) {
    
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
            
            if($isUpdate && isset($value['attribute_id']) && $value['attribute_id'] > 0) {
                $superAttribute = Mage::getModel('catalog/product_type_configurable_attribute')->getCollection()
                    ->addFieldToFilter('product_id', $productId)
                    ->addFieldToFilter('attribute_id', $value['attribute_id'])
                    ->getData(); 
                    
                if(sizeof($superAttribute)){
                    $superAttribute= array_pop($superAttribute);          
                    if(isset($superAttribute['product_super_attribute_id']))
                    {
                        $attributesSetArray[$key]["id"] = $superAttribute['product_super_attribute_id'];
                        $attributesSetArray[$key]["position"] = $superAttribute['position'];
                    }                              
                }
            }
            
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

		$product -> setConfigurableAttributesData($assignedAttributes);
		$product -> setCanSaveConfigurableAttributes(true);


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

		$result = array(
            'attribute_id' => $model -> getId(), 
            'attribute_code' => $model -> getAttributeCode(), 
            'frontend_input' => $model -> getFrontendInput(), 
            'default_value' => $model -> getDefaultValue(), 
            'is_unique' => $model -> getIsUnique(),
            'is_required' => $model -> getIsRequired(), 
            'apply_to' => $model -> getApplyTo(), 
            'is_configurable' => $model -> getIsConfigurable(), 
            'is_searchable' => $model -> getIsSearchable(), 
            'is_visible_in_advanced_search' => $model -> getIsVisibleInAdvancedSearch(), 
            'is_comparable' => $model -> getIsComparable(), 
            'is_used_for_promo_rules' => $model -> getIsUsedForPromoRules(), 
            'is_visible_on_front' => $model -> getIsVisibleOnFront(), 
            'used_in_product_listing' => $model -> getUsedInProductListing(), 
            'scope' => $scope, 
         );

		// set options
		$options = $attributeAPI -> options($model -> getId());
		// remove empty first element
		if ($model -> getFrontendInput() != 'boolean' && $model->getIsUserDefined()) {
			array_shift($options);
		}

		if (count($options) > 0) {
			$result['options'] = $options;
		}

		return $result;
	}

	protected function log($message) {
    
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

?>
<?php
class LinnSystems_LinnLiveConnect_Model_Community extends LinnSystems_LinnLiveConnect_Model_Main {

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
			if (!isset($item['attribute_id']) || empty($item['attribute_id'])){
				continue;
            }

			$attributeId = intval($item['attribute_id']);
			if ($attributeId <= 0){
				continue;
            }

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

		$result = array(
            'llc_ver' => Mage::helper('linnLiveConnect/settings') -> getVersion(), 
            'magento_ver' => trim("{$verInfo['major']}.{$verInfo['minor']}.{$verInfo['revision']}" . ($verInfo['patch'] != '' ? ".{$verInfo['patch']}" : "") . "-{$verInfo['stability']}{$verInfo['number']}", '.-'),
            'php_ver' => phpversion(),
            'api_config' => $config, 
            'compilation_enabled' => (bool)(defined('COMPILER_INCLUDE_PATH')),
            'max_upload_size' => min((int)ini_get("upload_max_filesize"), (int)ini_get("post_max_size"), (int)ini_get("memory_limit")),
            'store'=>Mage::helper('linnLiveConnect') -> currentStoreCode($store),
            'is_multi_store'=> !Mage::app()->isSingleStoreMode(),
            'extension_version'=>Mage::helper('linnLiveConnect/settings') -> getVersion(),
            'max_execution_time'=>ini_get("max_execution_time")
        );

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

		$productAPI = Mage::getModel('catalog/product_api_v2');

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
		$productAPI = Mage::getModel('catalog/product_api_v2');
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

		$productAPI = Mage::getModel('catalog/product_api_v2');

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

		$productAPI = Mage::getModel('catalog/product_api_v2');

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
				$result = $this -> createRelatedProduct($entity -> type, $entity -> productId, $entity -> linkedProductId, null, 'id');
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
				$result = $this -> deleteRelatedProduct($entity -> type, $entity -> productId, $entity -> linkedProductId, 'id');
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
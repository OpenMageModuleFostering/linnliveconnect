<?php

class LinnSystems_LinnLiveConnect_Model_Api_V2 {
    protected $factory = null;
    
    public function __construct(){
        $this->factory = Mage::helper('linnLiveConnect/factory');
    }

	public function checkProducts($version, $data) {

		$worker = $this->factory->createWorker($version);
		return $worker -> checkProducts($data);
	}

	public function createSimpleProducts($version, $data) {
		$worker = $this->factory->createWorker($version);
		return $worker -> createSimpleProducts($data);
	}

	public function createConfigurableProducts($version, $data) {

		$worker = $this->factory->createWorker($version);
		return $worker -> createConfigurableProducts($data);
	}

	public function createRelatedProducts($version, $data) {

		$worker = $this->factory->createWorker($version);
		return $worker -> createRelatedProducts($data);
	}

	public function createProductImages($version, $data) {

		$worker = $this->factory->createWorker($version);
		return $worker -> createProductImages($data);
	}

	public function updateSimpleProducts($version, $data) {

		$worker = $this->factory->createWorker($version);
		return $worker -> updateSimpleProducts($data);
	}

	public function updateConfigurableProducts($version, $data) {

		$worker = $this->factory->createWorker($version);
		return $worker -> updateConfigurableProducts($data);
	}

	public function updateProductImages($version, $data) {

		$worker = $this->factory->createWorker($version);
		return $worker -> updateProductImages($data);
	}

	public function updatePriceBulk($version, $data, $store = null, $identifierType = 'id') {

		$worker = $this->factory->createWorker($version);
		return $worker -> updateProductPrices($data, $store, $identifierType);
	}

	public function deleteProducts($version, $data) {

		$worker = $this->factory->createWorker($version);
		return $worker -> deleteProducts($data);
	}

	public function deleteRelatedProducts($version, $data) {

		$worker = $this->factory->createWorker($version);
		return $worker -> deleteRelatedProducts($data);
	}

	public function deleteProductImages($version, $data) {

		$worker = $this->factory->createWorker($version);
		return $worker -> deleteProductImages($data);
	}

	public function getProductStoreURL($version, $productId, $store = null, $identifierType = 'id') {

		$worker = $this->factory->createWorker($version);
		return $worker -> getProductStoreURL($productId, $store, $identifierType);
	}

	public function getStoreCode($version, $store = null) {

		$worker = $this->factory->createWorker($version);
		return $worker -> getStoreCode($store);
	}

	public function getGeneralInfo($version) {

		$worker = $this->factory->createWorker($version);
		return $worker -> getGeneralInfo();
	}

	//todo: rename
	public function productList($version, $page, $perPage, $filters = null, $store = null) {

		$worker = $this->factory->createWorker($version);
		return $worker -> getProductList($page, $perPage, $filters, $store);
	}

	//todo: rename
	public function productAttributeOptions($version, $setId) {

		$worker = $this->factory->createWorker($version);
		return $worker -> getProductAttributeOptions($setId);
	}

	public function storesList($version) {

		$worker = $this->factory->createWorker($version);
		return $worker -> storesList();
	}

	public function disableIndexing($version) {
		$worker = $this->factory->createWorker($version);
		return $worker -> disableIndexing();
	}

	public function restoreIndexingById($version, $data) {
		$worker = $this->factory->createWorker($version);
		return $worker -> restoreIndexingById($data);
	}

}

?>
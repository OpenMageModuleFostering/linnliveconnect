<?php
class Settings {
    public static function getVersion() {   
        return Mage::getConfig()->getModuleConfig("LinnSystems_LinnLiveConnect")->version;
    }
    public static function getShortVersion(){
        $version = explode('.',self::getVersion());
        return array_pop($version);
    }
}

class Mage_Catalog_Model_Product_Api_V2_LL extends Mage_Catalog_Model_Product_Api_V2{

    public function create($type, $set, $sku, $productData, $store = NULL){
        $tries = 0;
        $maxtries = 3;
        Mage::setIsDeveloperMode(true);
        
        do {
            $retry = false;
            try {       
                return parent::create($type, $set, $sku, $productData, $store);
            } catch (Exception $e) {
               
                if ($tries < $maxtries && $e->getMessage()=='SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction') {
                    $retry = true;
                    sleep(1);
                }else{
                    throw $e;
                }
                $tries++;
            }

        } while ($retry);      
    }
    
    public function update($productId, $productData, $store = null, $identifierType = null){
        $tries = 0;
        $maxtries = 3;
        Mage::setIsDeveloperMode(true);
        
        do {
            $retry = false;
            try {       
                return parent::update($productId, $productData, $store, $identifierType);
            } catch (Exception $e) {
               
                if ($tries < $maxtries && $e->getMessage()=='SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction') {
                    $retry = true;
                    sleep(1);
                }else{
                    throw $e->getMessage();
                }
                $tries++;
            }

        } while ($retry);     
    }
}

class LinnSystems_LinnLiveConnect_Model_Api_V2 {


    public function getProductStoreURL($version, $productId, $store = null, $identifierType = 'id') {
        $worker = Factory::createWorker($version);
        return $worker->getProductStoreURL($productId, $store, $identifierType);
    }

    public function configurableProduct($version, $set, $sku, $reindex, $productData, $productsSet, $attributesSet, $store=null)
    {
        $worker = Factory::createWorker($version);
        return $worker->configurableProduct($set, $sku, $reindex, $productData, $productsSet, $attributesSet, $store);
    }

    public function updateConfigurableProduct($version, $productId, $reindex, $productData, $productsSet, $attributesSet, $store=null, $identifierType='id')
    {
        $worker = Factory::createWorker($version);
        return $worker->updateConfigurableProduct($productId, $reindex, $productData, $productsSet, $attributesSet, $store, $identifierType);
    }

    public function storesList($version)
    {
        $worker = Factory::createWorker($version);
        return $worker->storesList();
    }

    public function getStoreCode($version, $store=null)
    {
        $worker = Factory::createWorker($version);
        return $worker->getStoreCode($store);
    }

    public function deleteAssigned($version, $productId, $store=null, $identifierType='id')
    {
        $worker = Factory::createWorker($version);
        return $worker->deleteAssigned($productId, $store, $identifierType);
    }

    public function assignImages($version, $productImages)
    {
        $worker = Factory::createWorker($version);
        return $worker->assignImages($productImages);
    }

    public function productList($version, $page, $perPage, $filters = null, $store = null)
    {
        $worker = Factory::createWorker($version);
        return $worker->productList($page, $perPage, $filters, $store);
    }

    public function productAttributeOptions($version, $setId)
    {
        $worker = Factory::createWorker($version);
        return $worker->productAttributeOptions($setId);
    }

    public function update($version, $productId, $productData, $store = null, $identifierType = 'id')
    {
        $worker = Factory::createWorker($version);
        return $worker->update($productId, $productData, $store, $identifierType);
    }

    public function create($version, $type, $set, $sku, $productData, $store = null)
    {
        $worker = Factory::createWorker($version);
        return $worker->create($type, $set, $sku, $productData, $store);
    }

    public function updatePriceBulk($version, $data, $store = null, $identifierType = 'id')
    {
        $worker = Factory::createWorker($version);
        return $worker->updatePriceBulk($data, $store, $identifierType);
    }

    public function getGeneralInfo($version)
    {
        $worker = Factory::createWorker($version);
        return $worker->getGeneralInfo();
    }    
    
    
}

class Factory {

    private static function _checkVersion($version)
    {
        $version = intval($version);

        if ($version == 0) throw new Mage_Api_Exception('version_not_specified');
        if (Settings::getShortVersion() < $version ) throw new Mage_Api_Exception('wrong_version');
    }

    public static function createWorker($version)
    {
        self::_checkVersion($version);

        if (Mage::GetEdition() == Mage::EDITION_COMMUNITY)
        {
            return new LinnLiveCommunity();
        }
        else if (Mage::GetEdition() == Mage::EDITION_ENTERPRISE)
        {
            return new LinnLiveEnterprise();
        }

        throw new Mage_Api_Exception('unsupported_edition');
    }
}

class LinnLiveMain extends Mage_Core_Model_Abstract {


    protected $_ignoredAttributes = array(
			'created_at',
			'updated_at',
			'category_ids',
			'required_options',
			'old_id',
			'url_key',
			'url_path',
			'has_options',
			'image_label',
			'small_image_label',
			'thumbnail_label',
			'image',
			'small_image',
			'thumbnail',
			'options_container',
			'entity_id',
			'entity_type_id',
			'attribute_set_id',
			'type_id',
			'sku',
			'name',
			'status',
			'stock_item',
			'description',
	);

    protected $_permittedAttributes = array (
		  'select',
		  'multiselect',
		  'text',
		  'textarea',
		  'date',
		  'price'
	);

    
    private function _prepareConfigurableData($productsSet, $attributesSet, $isUpdate)
    {
        $assignedProductsArray = $this->_objectToArray(
            $this->_createProductsData($productsSet)
        );

        $_newAttributeOptions = $this->_newConfigurableOptions($assignedProductsArray);
        if (count($_newAttributeOptions) > 0)
        {
            $this->_checkAssignedProductsOptions(
                $this->_createOptions($_newAttributeOptions), 
                $assignedProductsArray
            );
        }

        
        if (!is_array($attributesSet))
        {
            $attributesSet = array($attributesSet);
        }        
        
        $attributesSetArray = $this->_prepareAttributesData(
            $this->_objectToArray($attributesSet),
            $assignedProductsArray
        );     

        foreach($attributesSetArray as $key=>$value)
        {
            $attributesSetArray[$key]["id"] = NULL;
            $attributesSetArray[$key]["position"] = NULL;
            $attributesSetArray[$key]["store_label"] = $value['frontend_label'];
            //$attributesSetArray[$key]["use_default"] = 0;
            
            if($isUpdate==false){
                //check if attribute exists and available
                $checkAttribute = Mage::getModel('catalog/resource_eav_attribute')->loadByCode('catalog_product',$attributesSetArray[$key]["attribute_code"]);

                if(!$checkAttribute->getId() || !$this->_isConfigurable($checkAttribute)){
                    throw new Mage_Api_Exception('invalid_variation_attribute', 'Invalid attribute ['.$checkAttribute['attribute_code'].'] provided to Magento extension for creating Variation / Product with options. Check attributes/variations in LinnLive Magento configurator if they do exist/match the ones on the back-end.');
                }
            }
            
        }
        return array($assignedProductsArray, $attributesSetArray);
    }

    
    private function _isConfigurable($attribute){
        $isConfigurable = 0;              

        if(isset($attribute['is_global']) && $attribute['is_global']){
            $attribute['scope'] = 'global';
        }

        if (($attribute['scope'] == 'global') && ($attribute['is_configurable']))
        {
            if(is_array($attribute['apply_to']) && sizeof($attribute['apply_to'])){
                if (in_array('simple', $attribute['apply_to'])) {
                    $isConfigurable = 1;
                }           
            }elseif(is_string($attribute['apply_to']) && strlen($attribute['apply_to'])){
                if(strpos($attribute['apply_to'],'simple')!==false){
                    $isConfigurable = 1;
                }            
            }else{
                $isConfigurable = 1;
            }       
        }
        return $isConfigurable;
    }
    
    private function _checkAssignedProductsOptions($availableOptions, & $assignedProductsArray)
    {
        foreach ($assignedProductsArray as $id => $productOptions)
        {
            foreach($productOptions as $index => $option)
            {
                if (isset($availableOptions[$option['attribute_id']][strtolower($option['label'])]))
                {
                    $assignedProductsArray[$id][$index]['value_index'] = $availableOptions[$option['attribute_id']][strtolower($option['label'])];
                }
            }
        }
    }

    protected function _createOptions($newOptions)
    {
        $installer = new Mage_Eav_Model_Entity_Setup('core_setup');
        $attributeApi = Mage::getModel('catalog/product_attribute_api');
        $helperCatalog = Mage::helper('catalog');

        $installer->startSetup();

        $attributesList = array();

        foreach($newOptions as $attribute_id=>$options)
        {
            $aOption = array();
            $aOption['attribute_id'] = $attribute_id;
            $attributesList[] = $attribute_id;

            $i=0;
            foreach($options as $option)
            {
                $optionTitle = $helperCatalog->stripTags($option);

                $aOption['value']['option'.$i][0] = $optionTitle;
                $i++;
            }
            $installer->addAttributeOption($aOption);
        }
        $installer->endSetup();

        Mage::app()->cleanCache(array(Mage_Core_Model_Translate::CACHE_TAG));


        $currentOptions = array();
        $attributeApi = Mage::getModel('catalog/product_attribute_api');
        foreach($attributesList as $attribute_id)
        {
            if (!isset($currentOptions[$attribute_id]))
                $currentOptions[$attribute_id] = array();

            $attributeOptions = $attributeApi->options($attribute_id);

            foreach ($attributeOptions as $opts)
            {
                $label = strtolower($opts['label']);
                $optionId = $opts['value'];
                if (!isset($currentOptions[$attribute_id][$label]))
                    $currentOptions[$attribute_id][$label] = $optionId;
                else {
                    $oldId = $currentOptions[$attribute_id][$label];
                    if ($oldId > $optionId)
                    {
                        $attributeApi->removeOption($attribute_id, $oldId);
                        $currentOptions[$attribute_id][$label] = $optionId;
                    }
                    else
                    {
                        $attributeApi->removeOption($attribute_id, $optionId);
                    }
                }
            }
        }

        return $currentOptions;
    }

    private function _newConfigurableOptions($assignedProductsArray)
    {
        $_attributesOptions = array();
        foreach ($assignedProductsArray as $id => $productOptions)
        {
            foreach($productOptions as $option)
            {
                if (isset($option['value_index']) && $option['value_index'] == '-1')
                {
                    if (isset($_attributesOptions[$option['attribute_id']]))
                    {
                        $_attributesOptions[$option['attribute_id']][] = $option['label'];
                    }
                    else
                    {
                        $_attributesOptions[$option['attribute_id']] = array($option['label']);
                    }
                }
            }
        }
        return $_attributesOptions;
    }

    protected function _newOptions($productData)
    {
        $_attributesOptions = array();

        if (property_exists($productData, 'additional_attributes')) {
            $_attributes = $productData->additional_attributes;
            if (is_array($_attributes)) {
                foreach($_attributes as $_attribute) {
                    if (($_attribute->type == 'select' || $_attribute->type == 'multiselect') && $_attribute->value == "-1")
                    {
                        if (isset($_attributesOptions[$_attribute->attribute_id]))
                        {
                            $_attributesOptions[$_attribute->attribute_id][] = $_attribute->label;
                        }
                        else
                        {
                            $_attributesOptions[$_attribute->attribute_id] = array($_attribute->label);
                        }
                    }
                }
            }
            else
            {
                if (($_attributes->type == 'select' || $_attributes->type == 'multiselect') && $_attributes->value == "-1")
                {
                    if (isset($_attributesOptions[$_attributes->attribute_id]))
                    {
                        $_attributesOptions[$_attributes->attribute_id][] = $_attributes->label;
                    }
                    else
                    {
                        $_attributesOptions[$_attributes->attribute_id] = array($_attributes->label);
                    }
                }
            }

        }

        return $_attributesOptions;
    }

    private function _containsOption($attributeOption, $option)
    {
        foreach ($attributeOption as $inArrayOption)
        if ($inArrayOption['value_index'] == $option['value_index']) return true;

        return false;
    }

    private function _prepareAttributesData($attributesSetArray, $assignedProductsArray)
    {

        $_attributesOptions = array();
        foreach ($assignedProductsArray as $id => $productOptions)
        {
            foreach($productOptions as $option)
            {
                if (isset($_attributesOptions[$option['attribute_id']]) && !$this->_containsOption($_attributesOptions[$option['attribute_id']], $option))
                {
                    $_attributesOptions[$option['attribute_id']][] = $option;
                }
                else if (!isset($_attributesOptions[$option['attribute_id']]))
                {
                    $_attributesOptions[$option['attribute_id']] = array();
                    $_attributesOptions[$option['attribute_id']][] = $option;
                }
            }
        }

        foreach($attributesSetArray as $key => $attribute)
        {
            if (isset($_attributesOptions[$attribute['attribute_id']])){
                $attributesSetArray[$key]['values'] = $_attributesOptions[$attribute['attribute_id']];
            }
        }

        return $attributesSetArray;
    }

    private function _updateConfigurable($store, $productId, $assignedProducts, $assignedAttributes, $identifierType, $isUpdate=false, $reindex=true)
    {
        $magentoVer = $this->_getCurrentVersion();
        if ($magentoVer == 162)
        {
            $store = Mage::app()->getStore($store)->getId();
        }else{
            $store = NULL;
        }

        $product = Mage::helper('catalog/product')->getProduct($productId, $store, $identifierType);
        
        $product->setConfigurableProductsData($assignedProducts);    

        if ($isUpdate == false) {
           $product->setConfigurableAttributesData($assignedAttributes);     
           $product->setCanSaveConfigurableAttributes(true);           
        }
        
    

        try {
            $result = $product->save();
        }
        catch (Exception $e) {
            throw new Mage_Api_Exception('configurable_creating_error', $e->getMessage());
        }

        if ($reindex === true)
        {
            try {
                $indexer = Mage::getSingleton('index/indexer');
                $process = $indexer->getProcessByCode('catalog_product_price');
                $process->reindexEverything();
            }
            catch (Mage_Core_Exception $e) {
                throw new Mage_Api_Exception('configurable_creating_error', $e->getMessage());
            }
        }

        return $result;
    }

    private function _createProductsData($productData)
    {
        $assignedProductsData = array();

        if (is_array($productData))
        {
            foreach ($productData as $product)
            {
                $assignedProductsData[$product->product_id] = array();
                $this->_fillAssignedProductValues($product, $assignedProductsData);
            }
        }
        else
        {
            $assignedProductsData[$productData->product_id] = array();
            $this->_fillAssignedProductValues($product, $assignedProductsData);
        }


        return $assignedProductsData;
    }

    private function _fillAssignedProductValues(& $product, & $assignedProductsData)
    {
        if (is_array($product->values))
        {
            foreach ($product->values as $productValue)
            {
                $assignedProductsData[$product->product_id][] = $productValue;
            }
        }
        else
        {
            $assignedProductsData[$product->product_id][] = $product->values;
        }
    }

    private function _updateConfigurableQuantity( & $productData)
    {
        $productData = $this->_updateProperties($productData);

        if (!property_exists($productData, 'stock_data'))
        {
            $productData->stock_data = new stdClass();
        }

        $productData->stock_data->manage_stock = 1;
        $productData->stock_data->is_in_stock = 1;
    }

    private function _updateProperties($productData)
    {
        if (property_exists($productData, 'status')) {
            $productData->status = ($productData->status == 1) ? Mage_Catalog_Model_Product_Status::STATUS_ENABLED : Mage_Catalog_Model_Product_Status::STATUS_DISABLED;
        }

        if (property_exists($productData, 'stock_data') && property_exists($productData->stock_data, 'qty')) {
            $productData->stock_data->qty = intval($productData->stock_data->qty);
            $productData->stock_data->is_in_stock = 1;
            $productData->stock_data->manage_stock = 1;
        }
        return $productData;
    }

    //TODO: use helper func
    private function _objectToArray( $result )
    {
        $array = array();
        foreach ($result as $key=>$value)
        {
            if (is_object($value) || is_array($value))
            {
                $array[$key]=$this->_objectToArray($value);
            }
            else
            {
                $array[$key]=$value;
            }
        }
        return $array;
    }

    private function _getCurrentVersion()
    {
        $verInfo = Mage::getVersionInfo();

        return intval($verInfo['major'].$verInfo['minor'].$verInfo['revision']);
    }

    private function _productImages($attributesList)
    {
        $_images = array();

        if (is_array($attributesList) && count($attributesList) > 0 && isset($attributesList['media_gallery']))
        {
            $small = empty($attributesList['small_image']) ? false : $attributesList['small_image'];
            $base = empty($attributesList['image']) ? false : $attributesList['image'];
            $thumb = empty($attributesList['thumbnail']) ? false : $attributesList['thumbnail'];

            foreach($attributesList['media_gallery']['images'] as $key=>$value)
            {
                $newImage = array();
                $newImage['file'] = $value['file'];
                $newImage['label'] = $value['label'];
                $newImage['disabled'] = $value['disabled'] ? true : false;

                $newImage['small_image'] = $small == $newImage['file'];
                $newImage['image'] 		 = $base  == $newImage['file'];
                $newImage['thumbnail'] 	 = $thumb == $newImage['file'];

                $_images[] = $newImage;
            }
        }

        return $_images;
    }

    private function _removeIgnoredAttributes($attributesList)
    {
        $_preparedAttributes = array();
        if (is_array($attributesList) && count($attributesList) > 0)
        {
            foreach($attributesList as $key=>$value)
            {
                if (!in_array($key, $this->_ignoredAttributes) && !is_array($value))
                    $_preparedAttributes[]= array('key' => $key, 'value' => $value);
            }
        }

        return $_preparedAttributes;
    }

    private function _currentStoreCode($store=null)
    {
        if ($store != null) {
            return $store;
        }
        return $this->_getStore()->getCode();
    }

    private function _getProductBySku($sku)
    {
        if ($sku) {
            $product = Mage::getModel('catalog/product');
            $productId = $product->getIdBySku((string)$sku);
            if ($productId) {
                $product->load($productId);
                if ($product->getId()) {
                    return $product;
                }
            }
        }
    }

    private function _getStore($storeCode=null)
    {
        if (Mage::app()->isSingleStoreMode()) {
            return Mage::app()->getWebsite(true)->getDefaultStore();
        }

        $mageRunCode = isset($_SERVER['MAGE_RUN_CODE']) ? $_SERVER['MAGE_RUN_CODE'] : '';
        $mageRunType = isset($_SERVER['MAGE_RUN_TYPE']) ? $_SERVER['MAGE_RUN_TYPE'] : 'store';

        if ($storeCode != null) {
            return Mage::getModel('core/store')->load( $storeCode, 'code');
        }

        if ($mageRunType == 'store') {
            if (!empty($mageRunCode))
            {
                return Mage::getModel('core/store')->load( $mageRunCode, 'code');
            }
        } else {
            if ($mageRunType == 'website') {
                $websiteCode = empty($mageRunCode) ? '' : $mageRunCode;
            } else {
                $websiteCode = empty($mageRunType) ? '' : $mageRunType;
            }

            if (!empty($websiteCode))
            {
                $currentWebSite = Mage::getModel('core/website')->load( $websiteCode, 'code');
                $defaultStore = $currentWebSite->getDefaultStore();
                if (isset($defaultStore)){
                    return $defaultStore;
				}
            }
        }


        return Mage::app()->getWebsite(true)->getDefaultStore();//Mage::app()->getStore();
    }

    private function _getWebsiteId($store=null)
    {
        return array($this->_getStore($store)->getWebsiteId());
    }

    private function _productAttributeInfo($attribute_id, $attributeAPI)
    {
        $model = Mage::getResourceModel('catalog/eav_attribute')->setEntityTypeId(Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId());

        $model->load($attribute_id);

        if (!$model->getId()) {
            throw new Mage_Api_Exception('attribute_not_exists');
        }

        if ($model->isScopeGlobal()) {
            $scope = 'global';
        } elseif ($model->isScopeWebsite()) {
            $scope = 'website';
        } else {
            $scope = 'store';
        }

        $result = array(
			  'attribute_id' => $model->getId(),
			  'attribute_code' => $model->getAttributeCode(),
			  'frontend_input' => $model->getFrontendInput(),
			  'default_value' => $model->getDefaultValue(),
			  'is_unique' => $model->getIsUnique(),
			  'is_required' => $model->getIsRequired(),
			  'apply_to' => $model->getApplyTo(),
			  'is_configurable' => $model->getIsConfigurable(),
			  'is_searchable' => $model->getIsSearchable(),
			  'is_visible_in_advanced_search' => $model->getIsVisibleInAdvancedSearch(),
			  'is_comparable' => $model->getIsComparable(),
			  'is_used_for_promo_rules' => $model->getIsUsedForPromoRules(),
			  'is_visible_on_front' => $model->getIsVisibleOnFront(),
			  'used_in_product_listing' => $model->getUsedInProductListing(),
			  'scope' => $scope,
		);

        // set options
        $options = $attributeAPI->options($model->getId());
        // remove empty first element
        if ($model->getFrontendInput() != 'boolean') {
            array_shift($options);
        }

        if (count($options) > 0) {
            $result['options'] = $options;
        }

        return $result;
    }

    //Fix for buggy associativeArray implementation
    private function _fixAttributes($productData)
    {
        $_newAttributeOptions = $this->_newOptions($productData);
        $_availableOptions = array();
        if (count($_newAttributeOptions) > 0)
        {
            $_availableOptions = $this->_createOptions($_newAttributeOptions);
        }

        if (property_exists($productData, 'additional_attributes')) {
            $tmpAttr = $productData->additional_attributes;
            if (count($tmpAttr) == 0)
            {
                unset($productData->additional_attributes);
                return $productData;
            }

            if (is_array($tmpAttr))
            {
                foreach ($tmpAttr as $option) {
                    $code = $option->code;
                    if ( ($option->type == 'multiselect') && (is_array($productData->$code) == false) )
                    {
                        $productData->$code = array();
                    }
                    if (isset($_availableOptions[$option->attribute_id][strtolower($option->label)]))
                    {
                        if ($option->type == 'multiselect')
                        {
                            array_push($productData->$code, $_availableOptions[$option->attribute_id][strtolower($option->label)]);
                        }
                        else
                        {
                            $productData->$code = $_availableOptions[$option->attribute_id][strtolower($option->label)];
                        }
                    }
                    else
                    {
                        if ($option->type == 'multiselect')
                        {
                            array_push($productData->$code, $option->value);
                        }
                        else
                        {
                            $productData->$code = $option->value;
                        }
                    }

                }
            }
            else
            {
                $code = $tmpAttr->code;
                if (isset($_availableOptions[$tmpAttr->attribute_id][strtolower($tmpAttr->label)]))
                    $productData->$code = $_availableOptions[$tmpAttr->attribute_id][strtolower($tmpAttr->label)];
                else
                    $productData->$code = $tmpAttr->value;
            }
        }

        unset($productData->additional_attributes);

        return $productData;
    }

    
    protected function reindexSingleProduct($product){
        /*        $indexer = Mage::getSingleton('index/indexer');
        $indexer->lockIndexer();        
        $indexer->unlockIndexer();   */    
        $event = Mage::getSingleton('index/indexer')->logEvent( $product, $product->getResource()->getType(), Mage_Index_Model_Event::TYPE_SAVE, false);
        Mage::getSingleton('index/indexer')->getProcessByCode('catalog_url')->setMode(Mage_Index_Model_Process::MODE_REAL_TIME)->processEvent($event);   
    }
    
    protected function disableIndexing(){
        $processes = Mage::getSingleton('index/indexer')->getProcessesCollection();
        $processes->walk('setMode', array(Mage_Index_Model_Process::MODE_MANUAL));
        $processes->walk('save');
    }

    protected function enableIndexing(){
        $processes = Mage::getSingleton('index/indexer')->getProcessesCollection();
        $processes->walk('reindexAll');
        $processes->walk('setMode', array(Mage_Index_Model_Process::MODE_REAL_TIME));
        $processes->walk('save');    
    }
    
    /*
     * Helper for productList     
        $helper = Mage::helper('api');
        $helper->v2AssociativeArrayUnpacker($data);
        Mage::helper('api')->toArray($data);
    
     */
    private function _convertFiltersToArray($filters) {
        $arrayParams = array(
			   'nin',
			   'in',
		);

        $preparedFilters = array();

        if (isset($filters->filter)) {
            $preparedFilters = $filters->filter;
        }

        if (isset($filters->complex_filter)) {

            foreach ($filters->complex_filter as $idx=>$data) {
                if (is_object($data->value)) {
                    //1.8
                    $field = $data->key;
                    $opts = $data->value;

                } else {
                    //1.7
                    $field = $idx;
                    $opts = $data;
                }

                $value = (in_array($opts->key, $arrayParams)) ? explode(',', $opts->value) : $opts->value;
                $preparedFilters[$field][$opts->key] = $value;
            }
        }
        return $preparedFilters;
    }

    protected function _log($message) {
        Mage::log(print_r($message, true), null, 'LinnLiveExt.log');
    }

    /*
     *
     *   Public functions(API)
     *
     */
    public function configurableProduct($set, $sku, $reindex, $productData, $productsSet, $attributesSet, $store=null)
    {

        if (!$set || !$sku) {
            throw new Mage_Api_Exception('data_invalid');
        }
       
        $this->_updateConfigurableQuantity($productData);
        $productData = $this->_fixAttributes($productData);   
        
        $store = $this->_currentStoreCode($store);
        $defaultStore = $this->_getStore();

        if (property_exists($productData, 'websites') === false && isset($defaultStore) ) {
            $productData->websites = array($defaultStore->getWebsiteId());
        }

        if (property_exists($productData, 'category_ids') && !is_array($productData->category_ids))
        {
            if (is_string($productData->category_ids)) {
                $productData->category_ids = array($productData->category_ids);
            }
        }
        else if (property_exists($productData, 'category_ids') === false)
        {
            $productData->category_ids = array();
        }

        $productData->categories = $productData->category_ids;
        
        //merge into 1?
        $productAPI = new Mage_Catalog_Model_Product_Api_V2_LL();
        $productId = $productAPI->create('configurable', $set, $sku, $productData, $store);

        list($assignedProductsArray, $attributesSetArray) = $this->_prepareConfigurableData($productsSet, $attributesSet, false);           
        $this->_updateConfigurable($store, $productId, $assignedProductsArray, $attributesSetArray, 'id', false, $reindex);

        return $productId;
    }

    public function updateConfigurableProduct($productId, $reindex, $productData, $productsSet, $attributesSet, $store=null, $identifierType='id')
    {

        $this->_updateConfigurableQuantity($productData);
        $productData = $this->_fixAttributes($productData);
        
           
        
        $store = $this->_currentStoreCode($store);

        try {
            $storeId = Mage::app()->getStore($store)->getId();
        }
        catch (Mage_Core_Model_Store_Exception $e) {
            throw new Mage_Api_Exception('store_not_exists', null);
        }

        $_loadedProduct = Mage::helper('catalog/product')->getProduct($productId, $storeId, $identifierType);

        if (!$_loadedProduct->getId())
        {
            throw new Mage_Api_Exception('product_not_exists', null);
        }

        $_categoryIds = $_loadedProduct->getCategoryIds();
		
        if (property_exists($productData, 'category_ids'))
        {

            if (!is_array($productData->category_ids))
            {
                $productData->category_ids = array($productData->category_ids);
            }

            $productData->category_ids = array_merge($_categoryIds, $productData->category_ids);
        }
        else
        {
            $productData->category_ids = $_categoryIds;
        }

        $productData->category_ids = array_unique($productData->category_ids);

        if ( (property_exists($productData, 'removed_categories') === true) && (is_array($productData->removed_categories) === true) && (count($productData->removed_categories) > 0) )
        {
            $tmpCats = array();
            $productData->category_ids = array_diff($productData->category_ids, $productData->removed_categories);
        }

        $productData->categories = $productData->category_ids;

        if (property_exists($productData, 'add_to_websites') && $productData->add_to_websites === true)
        {
            $currentWebsites = $_loadedProduct->getWebsiteIds();
            $websiteId = $this->_getWebsiteId();
            $websiteId = $websiteId[0];

            if (in_array($websiteId, $currentWebsites) === false)
            {
                $currentWebsites[] = $websiteId;
                $productData->websites = $currentWebsites;
            }
        }

        $productAPI = new Mage_Catalog_Model_Product_Api_V2();

        $productAPI->update($productId, $productData, $store, $identifierType);

        list($assignedProductsArray, $attributesSetArray) = $this->_prepareConfigurableData($productsSet, $attributesSet, true);            
        return $this->_updateConfigurable($store, $productId, $assignedProductsArray, $attributesSetArray, $identifierType, true, $reindex);
    }

    /*
     *   Checks if this Magento server has valid Extension installed
     */
     
     //http://magento18.ixander.eu/api/v2_soap
    public function getGeneralInfo(){
        $config = Mage::getStoreConfig("api/config");
        $verInfo = Mage::getVersionInfo();

        $result = array(
            'llc_ver' => Settings::getVersion(),
            'magento_ver' => trim("{$verInfo['major']}.{$verInfo['minor']}.{$verInfo['revision']}" . ($verInfo['patch'] != '' ? ".{$verInfo['patch']}" : ""). "-{$verInfo['stability']}{$verInfo['number']}", '.-'),
            'php_ver' => phpversion(),
            'api_config' => $config
        );

        return $result;    
    }
     
     
    public function storesList()
    {
        return ($this->_getCurrentVersion() >= 160);
    }

    public function getStoreCode($store=null)
    {
        return $this->_currentStoreCode($store);
    }

    public function deleteAssigned($productId, $store=null, $identifierType='id')
    {
        $store = $this->_currentStoreCode($store);

        try {
            $storeId = Mage::app()->getStore($store)->getId();
        }
        catch (Mage_Core_Model_Store_Exception $e) {
            throw new Mage_Api_Exception('store_not_exists', null);
        }

        $_loadedProduct = Mage::helper('catalog/product')->getProduct($productId, $storeId, $identifierType);

        if (!$_loadedProduct->getId())
        {
            throw new Mage_Api_Exception('product_not_exists', null);
        }


        $currentWebsites = $_loadedProduct->getWebsiteIds();
        $websiteId = $this->_getWebsiteId($store);
        $websiteId = $websiteId[0];

        $newWebsiteIds = array();

        if (in_array($websiteId, $currentWebsites) === true)
        {
            for ($i = 0; $i < count($currentWebsites); $i++)
            {
                if ($currentWebsites[$i] != $websiteId)
                {
                    $newWebsiteIds[] = $currentWebsites[$i];
                }
            }

            $_loadedProduct->setWebsiteIds($newWebsiteIds);

            $_loadedProduct->save();
        }

        return true;
    }


    public function assignImages($productImages)
    {
        $store = $this->_currentStoreCode(null);

        foreach($productImages as $imageData)
        {
            $productId = intval($imageData->product_id);
            if ($productId < 1) {
                throw new Mage_Api_Exception('product_not_exists', null);
            }

            $product = Mage::helper('catalog/product')->getProduct($productId, $store, 'id');

            $images = $imageData->images;

            $baseImageExist = false;
            foreach($images as $image)
            {
                if (is_array($image->types) && in_array('image', $image->types))
                {
                    $baseImageExist = true;
                }
            }

            if ($baseImageExist == false && count($images) > 0)
            {
                $images[0]->types = array('image');
            }

            reset($images);

            foreach($images as $image)
            {
                $catalogProductDir = Mage::getBaseDir('media') . DS . 'catalog/product';
                $filePath = $catalogProductDir.$image->image_name;

                if (is_array($image->types) && count($image->types) > 0)
                {
                    $imageTypes = $image->types;
                }
                else
                {
                    $imageTypes = "";
                }

                try
                {
                    $product->addImageToMediaGallery($filePath, $imageTypes, false, false);
                }
                catch (Exception $e) {  }

            }

            $product->save();
        }

        return true;
    }




    /*
     * Implementation of catalogProductList because of bug in associativeArray.
     * Extended to filter by category id too.
     *
     * Use 'entity_id' for product_id,
     * 'type_id' instead of product type.
     */
    public function productList($page, $perPage, $filters = null, $store = null)
    {
        //get store
        try {
            $storeId = Mage::app()->getStore( $this->_currentStoreCode($store) )->getId();
        }
        catch (Mage_Core_Model_Store_Exception $e) {
            throw new Mage_Api_Exception('store_not_exists', null);
        }

        //prepare and convert filters to array
        $preparedFilters = $this->_convertFiltersToArray($filters);
        if (empty($preparedFilters)) {
            throw new Mage_Api_Exception('filters_invalid', null);
        }

        //load collection
        $collection = Mage::getModel('catalog/product')->getCollection()->addStoreFilter($storeId);

        //filter collection by category if exists
        if (isset($preparedFilters['category']) && is_string($preparedFilters['category']))
        {
            $_category = Mage::getModel('catalog/category')->load( intval($preparedFilters['category']) );

            if ($_category->getId()) {
                $collection = $collection->addCategoryFilter($_category);
            }

            unset($preparedFilters['category']);
        }

        //add prepared filters to collection
        try {
            foreach ($preparedFilters as $field => $value) {
                $collection->addFieldToFilter($field, $value);
            }
        }
        catch (Mage_Core_Exception $e) {
            throw new Mage_Api_Exception('filters_invalid', $e->getMessage());
        }


        if ($page == 1)
        {
            //TODO: limit page size
            $count = $collection->count();
        }
        else
        {
            $count = 0;
            $collection->setPageSize($perPage)->setCurPage($page);
        }

        $result = array(
			  'count'=>$count,
			  'products'=>array()
		);

        $_assignedIds = array();
        $_fetchedIds = array();

        $i = 0;
        foreach ($collection as $_product) {

            if ($i >= ($perPage * $page)) break;//TODO remove
            $_loadedProduct = Mage::helper('catalog/product')->getProduct($_product->getId(), $storeId, 'id');

            $_allAttributes = $_loadedProduct->getData();


            $_description = isset($_allAttributes['description']) ? $_allAttributes['description'] : '';

            $_productImages = $this->_productImages($_allAttributes);
            $_productAttributes = $this->_removeIgnoredAttributes($_allAttributes);

            $_fetchedIds[] = $_loadedProduct->getId();

            $result['products'][$i] = array(
				  'product_id'   => $_loadedProduct->getId(),
				  'sku'          => $_loadedProduct->getSku(),
				  'name'         => $_loadedProduct->GetName(),
				  'set'          => $_loadedProduct->getAttributeSetId(),
				  'type'         => $_loadedProduct->getTypeId(),
				  'price'        => $_loadedProduct->getPrice(),
				  'status'       => $_loadedProduct->getStatus(),
				  'description'  => $_description,
				  'category_ids' => $_loadedProduct->getCategoryIds(),
				  'website_ids'  => $_loadedProduct->getWebsiteIds(),
				  'assigned_ids' => array(),
				  'conf_attrib_ids' => array(),
				  'images' 	   => $_productImages,
				  'attributes'   => $_productAttributes,
			);

            if ($_loadedProduct->getTypeId() == "configurable")
            {
                $_typeInstance = $_loadedProduct->getTypeInstance();
                $result['products'][$i]['assigned_ids'] = $_typeInstance->getUsedProductIds();
                foreach($_typeInstance->getConfigurableAttributes() as $attribute) {
                    $_prices = array();
                    foreach($attribute->getPrices() as $price)
                    {
                        $_prices[] = array(
							 'value_index' 	=> $price['value_index'],
							 'is_fixed' 	=> !$price['is_percent'],
							 'price_diff' 	=> $price['pricing_value'],
							 'label' 		=> $price['label'],
						);
                    }

                    $result['products'][$i]['conf_attrib_ids'][] = array(
						'code' 		=> $attribute->getProductAttribute()->getAttributeCode(),
						'prices' 	=> $_prices
					);
                }
                $_assignedIds = array_merge($_assignedIds, $result['products'][$i]['assigned_ids']);
            }

            $i++;
        }

        $_absentIds = array_diff($_assignedIds, $_fetchedIds);

        if (count($_absentIds) > 0)
        {
            $collection = Mage::getModel('catalog/product')->getCollection()->addIdFilter($_absentIds);

            foreach ($collection as $_product) {
                $_loadedProduct = Mage::helper('catalog/product')->getProduct($_product->getId(), $storeId, 'id');

                $_allAttributes = $_product->getData();

                $_description = isset($_allAttributes['description']) ? $_allAttributes['description'] : '';

                $_productImages = $this->_productImages($_allAttributes);
                $_productAttributes = $this->_removeIgnoredAttributes($_allAttributes);

                $result['products'][] = array(
					'product_id'   => $_loadedProduct->getId(),
					'sku'          => $_loadedProduct->getSku(),
					'name'         => $_loadedProduct->GetName(),
					'set'          => $_loadedProduct->getAttributeSetId(),
					'type'         => $_loadedProduct->getTypeId(),
					'price'        => $_loadedProduct->getPrice(),
					'status'       => $_loadedProduct->getStatus(),
					'description'  => $_description,
					'category_ids' => $_loadedProduct->getCategoryIds(),
					'website_ids'  => $_loadedProduct->getWebsiteIds(),
					'assigned_ids' => array(),
					'conf_attrib_ids' => array(),
					'images' 	   => $_productImages,
					'attributes'   => $this->_removeIgnoredAttributes($_loadedProduct->getData()),
				);
            }
        }

        return $result;
    }

    public function productAttributeOptions($setId)
    {

        $result = array();

        $setId = intval($setId);
        if ($setId <= 0) return $result;

        $attributeAPI = Mage::getModel('catalog/product_attribute_api');

        $items = $attributeAPI->items($setId);

        $attributes = Mage::getModel('catalog/product')->getResource()->loadAllAttributes();

        foreach ($items as $item) {
            if (!isset($item['attribute_id']) || empty($item['attribute_id'])) continue;
            $attributeId = intval($item['attribute_id']);
            if ($attributeId <= 0) continue;

            $additionInfo = $this->_productAttributeInfo($attributeId, $attributeAPI);

            if (in_array($additionInfo['frontend_input'], $this->_permittedAttributes) && !in_array($additionInfo['attribute_code'], $this->_ignoredAttributes))
            {

                $attribute = array(
                    'attribute_id' => $additionInfo['attribute_id'],
                    'code' => $additionInfo['attribute_code'],
                    'type' => $additionInfo['frontend_input'],
                    'required' => $additionInfo['is_required'],
                    'scope' => $additionInfo['scope'],
                    'can_config' => 0
                );

                if ( ($additionInfo['frontend_input'] == 'select') || ($additionInfo['frontend_input'] == 'multiselect') ) {
                    if (isset($additionInfo['options'])) {

                        if (sizeof($additionInfo['options']) && is_array($additionInfo['options'][0]['value'])) {
                            continue;//ignore attributes with multidimensional options
                        }
                        $attribute['attribute_options'] = $additionInfo['options'];
                    }
 
                    $attribute['can_config'] = $this->_isConfigurable($additionInfo);
                }

                $result[] = $attribute;
            }
        }

        return $result;
    }

    public function update($productId, $productData, $store = null, $identifierType = 'id')
    {
        $store = $this->_currentStoreCode($store);
        try {
            $storeId = Mage::app()->getStore($store)->getId();
        }
        catch (Mage_Core_Model_Store_Exception $e) {
            throw new Mage_Api_Exception('store_not_exists', null);
        }

        $_loadedProduct = Mage::helper('catalog/product')->getProduct($productId, $storeId, $identifierType);

        if (!$_loadedProduct->getId())
        {
            throw new Mage_Api_Exception('product_not_exists', null);
        }


        $_categoryIds = $_loadedProduct->getCategoryIds();
        if (property_exists($productData, 'category_ids'))
        {
            if (!is_array($productData->category_ids))
            {
                $productData->category_ids = array($productData->category_ids);
            }

            $productData->category_ids = array_merge($_categoryIds, $productData->category_ids);
        }
        else
        {
            $productData->category_ids = $_categoryIds;
        }

        $productData->category_ids = array_unique($productData->category_ids);

        if ( (property_exists($productData, 'removed_categories') === true) && (is_array($productData->removed_categories) === true) && (count($productData->removed_categories) > 0) )
        {
            $tmpCats = array();

            $productData->category_ids = array_diff($productData->category_ids, $productData->removed_categories);
        }

        $productData->categories = $productData->category_ids;

        if (property_exists($productData, 'add_to_websites') && $productData->add_to_websites === true)
        {
            $currentWebsites = $_loadedProduct->getWebsiteIds();
            $websiteId = $this->_getWebsiteId();
            $websiteId = $websiteId[0];

            if (in_array($websiteId, $currentWebsites) === false)
            {
                $currentWebsites[] = $websiteId;
                $productData->websites = $currentWebsites;
            }
        }

        $productData = $this->_updateProperties($productData);

        $productData = $this->_fixAttributes($productData);

        $productAPI = new Mage_Catalog_Model_Product_Api_V2();

        $result = $productAPI->update($productId, $productData, $store, $identifierType);

        return $result;
    }

    public function create($type, $set, $sku, $productData, $store = null)
    {
        $product = $this->_getProductBySku($sku);   
        if ($product) {
            return $product->getId();
        }

        $store = $this->_currentStoreCode($store);

        $defaultStore = $this->_getStore();

        if (property_exists($productData, 'websites') === false && isset($defaultStore)) {
            $productData->websites = array($defaultStore->getWebsiteId());
        }

        if (property_exists($productData, 'category_ids') && !is_array($productData->category_ids)) {
            if (is_string($productData->category_ids)){
                $productData->category_ids = array($productData->category_ids);
			}
        }else if (property_exists($productData, 'category_ids') === false)
        {
            $productData->category_ids = array();
        }

        $productData->categories = $productData->category_ids;

        $productData = $this->_updateProperties($productData);

        $productData = $this->_fixAttributes($productData);

        $productAPI = new Mage_Catalog_Model_Product_Api_V2_LL();
        
        return $productAPI->create($type, $set, $sku, $productData, $store);
    }

    public function getProductStoreURL($productId, $store = null, $identifierType = 'id') {

        $storeId = $this->getStoreCode($store);

        $_loadedProduct = Mage::helper('catalog/product')->getProduct($productId, $storeId, $identifierType);

        if (!$_loadedProduct->getId())
        {
            throw new Mage_Api_Exception('product_not_exists', null);
        }

        return $_loadedProduct->getProductUrl();
    }

    public function updatePriceBulk($data, $store, $identifierType) {
        $response = array();
        for ($i = 0; $i< sizeof($data); $i++) {
            $d = $data[$i];
            $product = Mage::helper('catalog/product')->getProduct($d->sku, $store, $identifierType);
            if ($product && $product->getId()) {
                if ($product->getPrice()!=$d->price) {
                    $product->setPrice($d->price);
                    $product->save();
                }
            }
            $response[] = array('sku'=>$d->sku, 'success'=>($product && $product->getId()));
        }
        return $response;
    }
}

class LinnLiveEnterprise extends LinnLiveMain {

    public function productAttributeOptions($setId)
    {
        $result = array();

        $setId = intval($setId);
        if ($setId <= 0) return $result;

        $attributeAPI = Mage::getModel('catalog/product_attribute_api');

        $attributes = Mage::getModel('catalog/product')->getResource()->loadAllAttributes()->getSortedAttributes($setId);

        foreach ($attributes as $attribute) {

            if ((!$attribute->getId() || $attribute->isInSet($setId))
                    && !in_array($attribute->getAttributeCode(), $this->_ignoredAttributes)
                    && in_array($attribute->getFrontendInput(), $this->_permittedAttributes)) {

                if (!$attribute->getId() || $attribute->isScopeGlobal()) {
                    $scope = 'global';
                } elseif ($attribute->isScopeWebsite()) {
                    $scope = 'website';
                } else {
                    $scope = 'store';
                }

                $result[] = array(
                    'attribute_id' => $attribute->getId(),
                    'code' => $attribute->getAttributeCode(),
                    'type' => $attribute->getFrontendInput(),
                    'required' => $attribute->getIsRequired(),
                    'scope' => $scope,
                    'can_config' => 0
                );

                if ( ($attribute->getFrontendInput() == 'select') || ($attribute->getFrontendInput() == 'multiselect') ) {
                    if (($scope == 'global') && $attribute->getIsConfigurable())
                    {
                        if (strpos($attribute->getApplyTo(), 'simple') !== false)
                            $result[]['can_config'] = 1;
                    }

                    $options = $attributeAPI->options($attribute->getId());

                    // remove empty first element
                    if ($attribute->getFrontendInput() != 'boolean') {
                        array_shift($options);
                    }

                    if (count($options) > 0) {
                        $result[]['attribute_options'] = $options;
                    }
                }
            }
        }

        return $result;
    }

    //Fix for buggy associativeArray implementation
    private function _fixAttributes($productData)
    {
        $_newAttributeOptions = $this->_newOptions($productData);
        $_availableOptions = array();
        if (count($_newAttributeOptions) > 0)
        {
            $_availableOptions = $this->_createOptions($_newAttributeOptions);
        }

        if (property_exists($productData, 'additional_attributes')) {
            $tmpAttr = $productData->additional_attributes;
            if (count($tmpAttr) == 0)
            {
                unset($productData->additional_attributes);
                return $productData;
            }
            $productData->additional_attributes = new stdClass();
            $productData->additional_attributes->single_data = array();
            $i=0;
            if (is_array($tmpAttr))
            {
                foreach ($tmpAttr as $option) {
                    $productData->additional_attributes->single_data[$i] = new stdClass();
                    $productData->additional_attributes->single_data[$i]->key = $option->code;

                    if ( ($option->type == 'multiselect') && (is_array($productData->additional_attributes->single_data[$i]->value) == false) )
                    {
                        $productData->additional_attributes->single_data[$i]->value = array();
                    }

                    if (isset($_availableOptions[$option->attribute_id][strtolower($option->label)]))
                    {
                        if ($option->type == 'multiselect')
                        {
                            array_push($productData->additional_attributes->single_data[$i]->value, $_availableOptions[$option->attribute_id][strtolower($option->label)]);
                        }
                        else
                        {
                            $productData->additional_attributes->single_data[$i]->value = $_availableOptions[$option->attribute_id][strtolower($option->label)];
                        }
                    }
                    else
                    {
                        if ($option->type == 'multiselect')
                        {
                            array_push($productData->additional_attributes->single_data[$i]->value, $option->value);
                        }
                        else
                        {
                            $productData->additional_attributes->single_data[$i]->value = $option->value;
                        }
                    }
                    $i++;
                }
            }
            else
            {
                $productData->additional_attributes->single_data[0] = new stdClass();
                $productData->additional_attributes->single_data[0]->key = $tmpAttr->code;
                if (isset($_availableOptions[$tmpAttr->attribute_id][strtolower($tmpAttr->label)]))
                    $productData->additional_attributes->single_data[0]->value = $_availableOptions[$tmpAttr->attribute_id][strtolower($tmpAttr->label)];
                else
                    $productData->additional_attributes->single_data[0]->value = $tmpAttr->value;
            }
        }

        return $productData;
    }
}

class LinnLiveCommunity extends LinnLiveMain {

}

?>
<?php
class LinnSystems_LinnLiveConnect_Helper_Data extends Mage_Core_Helper_Abstract
{

    //TODO: use helper func
    public function objectToArray( $result )
    {
        $array = array();
        foreach ($result as $key=>$value)
        {
            if (is_object($value) || is_array($value))
            {
                $array[$key]=$this->objectToArray($value);
            }
            else
            {
                $array[$key]=$value;
            }
        }
        return $array;
    }

    public function getProductBySku($sku)
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


    public function convertFiltersToArray($filters) {
    
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

    public function productImages($attributesList)
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


    public function currentStoreCode($store=null)
    {
        if ($store != null) {
            return $store;
        }
        return $this->getDefaultStore()->getCode();
    }

    private function getDefaultStore($storeCode=null)
    {
        if (Mage::app()->isSingleStoreMode()) {
            return Mage::app()->getWebsite(true)->getDefaultStore();
        }

        if ($storeCode != null) {
            return Mage::getModel('core/store')->load( $storeCode, 'code');
        }
        
        $mageRunCode = isset($_SERVER['MAGE_RUN_CODE']) ? $_SERVER['MAGE_RUN_CODE'] : '';
        $mageRunType = isset($_SERVER['MAGE_RUN_TYPE']) ? $_SERVER['MAGE_RUN_TYPE'] : 'store';

        if(!empty($mageRunCode)){
            switch($mageRunType){
                case 'store':
                    return Mage::getModel('core/store')->load( $mageRunCode, 'code');
                break;
            
                case 'website':
                    return Mage::getModel('core/website')->load( $mageRunCode, 'code')->getDefaultStore();    
                break;
            }              
        }

        return Mage::app()->getWebsite(true)->getDefaultStore();//Mage::app()->getStore();
    }    
    

    public function updateConfigurableQuantity( & $productData)
    {
        $productData = $this->updateProperties($productData);

        if (!property_exists($productData, 'stock_data'))
        {
            $productData->stock_data = new stdClass();
        }

        $productData->stock_data->manage_stock = 1;
        $productData->stock_data->is_in_stock = 1;
    }

    public function updateProperties($productData)
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

    public function createOptions($newOptions)
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
            if (!isset($currentOptions[$attribute_id])){
                $currentOptions[$attribute_id] = array();
            }

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
    
    
    //Fix for buggy associativeArray implementation
    public function fixAttributes($productData)
    {    
        $_newAttributeOptions = $this->newOptions($productData);
        
        $_availableOptions = array();
        if (count($_newAttributeOptions) > 0)
        {
            $_availableOptions = $this->createOptions($_newAttributeOptions);
        }
        
        if (property_exists($productData, 'additional_attributes')) {
        
            $additional_attributes = new stdClass();
            $additional_attributes->single_data = array();
            $i = 0;
            
            if (is_array($productData->additional_attributes))
            {  
                foreach ($productData->additional_attributes as $option) {
                    $additional_attributes->single_data[$i] = new stdClass();
                    $additional_attributes->single_data[$i]->key = $option->code;
                    
                    $value = isset($option->value)? $option->value : "";
                    
                    if (isset($_availableOptions[$option->attribute_id]) && isset($_availableOptions[$option->attribute_id][strtolower($option->label)]))
                    {
                        $value = $_availableOptions[$option->attribute_id][strtolower($option->label)];
                    }
                    
                    
                    if ($option->type == 'multiselect')
                    {           
                        if(!isset($additional_attributes->single_data[$i]->value)){
                            $additional_attributes->single_data[$i]->value = array();
                        }
                    
                        array_push($additional_attributes->single_data[$i]->value, $value);
                    }
                    else
                    {
                        $additional_attributes->single_data[$i]->value = $value; 
                    }
                    
                    $i++;                             
                }
            }

            if ($i>0)
            {       
                $productData->additional_attributes = $additional_attributes;
            }
            else
            {
                unset($productData->additional_attributes);
            }
            
        }
        return $productData;
    }

    protected function newOptions($productData)
    {
        $_attributesOptions = array();

        if (property_exists($productData, 'additional_attributes') && is_array($productData->additional_attributes)) {

            foreach($productData->additional_attributes as $_attribute) {
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

        return $_attributesOptions;
    }    
    
    
    public function createProductData($productData){

        $defaultStore = $this->getDefaultStore();
        if($defaultStore){
            $productData->websites = array($defaultStore->getWebsiteId());
        }

        if (property_exists($productData, 'category_ids') === true) {
            $productData->category_ids = is_array($productData->category_ids) ? $productData->category_ids : array($productData->category_ids);
            $productData->categories = $productData->category_ids;
        }
        
        return $productData;
    }  
    
    public function getWebsiteId($store=null)
    {
        return $this->getDefaultStore($store)->getWebsiteId();
    }    
    
    
    public function updateProductData($productId, $productData, $store = null, $identifierType = 'id'){

        $store = $this->currentStoreCode($store);
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

        if (property_exists($productData, 'category_ids'))
        {
            $productData->category_ids = is_array($productData->category_ids) ? $productData->category_ids : array($productData->category_ids); 
            $productData->categories = $productData->category_ids;
        }

        $websiteId  = $this->getWebsiteId();    
        $currentWebsites = $_loadedProduct->getWebsiteIds();
        if(!is_array($currentWebsites)){
            $currentWebsites = array();
        }
           
        if (in_array($websiteId, $currentWebsites) === false){
            $currentWebsites[] = $websiteId;
            $productData->websites = $currentWebsites;
        }    
    }

    public function flushWsdlCache(){
        $cache_dir = ini_get('soap.wsdl_cache_dir');
        if($cache_dir){
            foreach (glob($cache_dir . DS . "wsdl-*") as $filename) {
                @unlink($filename);
            }
        }
    }
}
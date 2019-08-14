<?php

class LinnSystems_LinnLiveConnect_Model_Product_Api_V2 extends Mage_Catalog_Model_Product_Api_V2
{
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
?>
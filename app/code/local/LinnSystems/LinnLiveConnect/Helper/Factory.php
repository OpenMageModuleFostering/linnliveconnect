<?php
class LinnSystems_LinnLiveConnect_Helper_Factory{

	private function checkVersion($version) {
		$version = intval($version);

		if ($version == 0) {
			throw new Mage_Api_Exception('version_not_specified');
		}

		if (Mage::helper('linnLiveConnect/settings') -> getShortVersion() < $version) {
			throw new Mage_Api_Exception('wrong_version');
		}
	}

	public function createWorker($version) {
		$this->checkVersion($version);

		if (Mage::GetEdition() == Mage::EDITION_COMMUNITY || Mage::GetEdition() == Mage::EDITION_ENTERPRISE) {
			return Mage::getModel('linnLiveConnect/community');
		}

		throw new Mage_Api_Exception('unsupported_edition');
	}
}

?>
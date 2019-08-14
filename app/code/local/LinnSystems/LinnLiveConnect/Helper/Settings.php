<?php
class LinnSystems_LinnLiveConnect_Helper_Settings{
	public function getVersion() {
		return Mage::getConfig() -> getModuleConfig("LinnSystems_LinnLiveConnect") -> version;
	}

	public function getShortVersion() {
		$version = explode('.', $this->getVersion());
		return array_pop($version);
	}
}

?>
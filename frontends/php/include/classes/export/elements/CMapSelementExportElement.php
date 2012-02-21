<?php

class CMapSelementExportElement extends CExportElement{

	public function __construct(array $selement) {
		parent::__construct('selement', $selement);

		$this->addElementRef($selement['elementid']);
		$this->addIconOff($selement['iconid_off']);
		$this->addIconOn($selement['iconid_on']);
		$this->addIconDisabled($selement['iconid_disabled']);
		$this->addIconMaintenance($selement['iconid_maintenance']);
		$this->addUrls($selement['urls']);
	}

	protected function requiredFields() {
		return array('elementtype', 'label', 'label_location', 'x', 'y', 'elementsubtype', 'areatype', 'width', 'height',
			'viewtype', 'use_iconmap', 'selementid');
	}

	protected function addElementRef(array $element) {
		$this->addElement(new CExportElement('element', $element));
	}

	protected function addIconOff(array $iconOff) {
		$this->addElement(new CExportElement('icon_off', $iconOff));
	}

	protected function addIconOn(array $iconOn) {
		$this->addElement(new CExportElement('icon_on', $iconOn));
	}

	protected function addIconDisabled(array $iconDisabled) {
		$this->addElement(new CExportElement('icon_disabled', $iconDisabled));
	}

	protected function addIconMaintenance(array $iconMaintenance) {
		$this->addElement(new CExportElement('icon_maintenance', $iconMaintenance));
	}

	protected function addUrls(array $urls) {
		$mapUrlsElement = new CExportElement('urls');
		foreach ($urls as $url) {
			$mapUrlsElement->addElement(new CMapSelementUrlExportElement($url));
		}
		$this->addElement($mapUrlsElement);
	}

}

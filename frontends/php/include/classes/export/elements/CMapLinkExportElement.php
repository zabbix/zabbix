<?php

class CMapLinkExportElement extends CExportElement{

	public function __construct(array $link) {
		parent::__construct('link', $link);

		$this->addLinkTriggers($link['linktriggers']);
	}

	protected function referenceFields() {
		return array('selement_ref1', 'selement_ref2');
	}

	protected function requiredFields() {
		return array('drawtype', 'color', 'label');
	}

	protected function addLinkTriggers(array $linkTriggers) {
		$mapLinkTriggersElement = new CExportElement('linktriggers');
		foreach ($linkTriggers as $linktrigger) {
			$mapLinkTriggersElement->addElement(new CMapLinkTriggerExportElement($linktrigger));
		}
		$this->addElement($mapLinkTriggersElement);
	}

}

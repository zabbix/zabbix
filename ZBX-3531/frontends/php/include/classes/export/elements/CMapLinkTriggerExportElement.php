<?php

class CMapLinkTriggerExportElement extends CExportElement{

	public function __construct(array $linkTrigger) {
		parent::__construct('linktrigger', $linkTrigger);

		$this->addTrigger($linkTrigger['triggerid']);
	}

	protected function requiredFields() {
		return array('drawtype', 'color');
	}

	protected function addTrigger(array $trigger) {
		$this->addElement(new CExportElement('trigger', $trigger));
	}

}

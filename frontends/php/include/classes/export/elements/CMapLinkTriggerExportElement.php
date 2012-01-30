<?php

class CMapLinkTriggerExportElement extends CExportElement{

	public function __construct(array $linkTrigger) {
		parent::__construct('linktrigger', $linkTrigger);
	}

	protected function requiredFields() {
		return array('drawtype', 'color');
	}

}

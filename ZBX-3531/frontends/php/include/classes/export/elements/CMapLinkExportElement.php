<?php

class CMapLinkExportElement extends CExportElement{

	public function __construct(array $link) {
		parent::__construct('link', $link);
	}

	protected function referenceFields() {
		return array('selement_ref1', 'selement_ref2');
	}
	protected function requiredFields() {
		return array('drawtype', 'color', 'label');
	}

}

<?php

class CMapSelementExportElement extends CExportElement{

	public function __construct(array $selement) {
		parent::__construct('selement', $selement);
	}

	protected function requiredFields() {
		return array('elementtype', 'label', 'label_location', 'x', 'y', 'elementsubtype', 'areatype', 'width', 'height',
			'viewtype', 'use_iconmap');
	}

}

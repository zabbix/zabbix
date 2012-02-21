<?php

class CMapSelementUrlExportElement extends CExportElement {

	public function __construct(array $url) {
		parent::__construct('url', $url);
	}

	protected function requiredFields() {
		return array('name', 'url');
	}
}

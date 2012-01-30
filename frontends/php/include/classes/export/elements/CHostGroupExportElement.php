<?php

class CHostGroupExportElement extends CExportElement {

	public function __construct($hostGroup) {
		parent::__construct('group', $hostGroup);
	}

	protected function requiredFields() {
		return array('name');
	}

}

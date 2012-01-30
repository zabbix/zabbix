<?php

class CGroupExportElement extends CExportElement {

	public function __construct($group) {
		parent::__construct('group', $group);
	}

	protected function requiredFields() {
		return array('name');
	}

}

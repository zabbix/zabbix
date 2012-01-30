<?php

class CHostApplicationExportElement extends CExportElement {

	public function __construct($application) {
		parent::__construct('application', $application);
	}

	protected function requiredFields() {
		return array('name');
	}

}

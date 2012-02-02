<?php

class CHostTemplateExportElement extends CExportElement{

	public function __construct($template) {
		parent::__construct('template', $template);
	}

	protected function requiredFields() {
		return array('host');
	}

	protected function fieldNameMap() {
		return array(
			'host' => 'name'
		);
	}

}

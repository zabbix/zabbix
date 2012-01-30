<?php

class CTemplateExportElement extends CExportElement{

	public function __construct(array $template) {
		parent::__construct('template', $template);
	}

	protected function requiredFields() {
		return array('host', 'name');
	}

	protected function fieldNameMap() {
		return array(
			'host' => 'template'
		);
	}
}

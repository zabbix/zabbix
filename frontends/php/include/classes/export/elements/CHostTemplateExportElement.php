<?php

class CHostTemplateExportElement extends CExportElement{

	public function __construct($template) {
		$template = ArrayHelper::getByKeys($template, array('host'));
		parent::__construct('template', $template);
	}

	protected function requiredFields() {
		return array('name');
	}

}

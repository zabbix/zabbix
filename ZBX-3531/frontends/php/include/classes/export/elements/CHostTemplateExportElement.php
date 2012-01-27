<?php

class CHostTemplateExportElement extends CNodeExportElement{

	public function __construct($template) {
		$template = ArrayHelper::getByKeys($template, array('host'));
		parent::__construct('template', $template);
	}

}

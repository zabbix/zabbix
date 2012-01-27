<?php

class CTemplateExportElement extends CNodeExportElement{

	public function __construct(array $template) {
		$template = ArrayHelper::getByKeys($template, array('host', 'name'));
		parent::__construct('template', $template);
	}

}

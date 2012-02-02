<?php

class CTriggerDependencyExportElement extends CExportElement{

	public function __construct($dependency) {
		parent::__construct('dependency', $dependency);
	}

	protected function requiredFields() {
		return array('expression', 'description');
	}

	protected function fieldNameMap() {
		return array(
			'description' => 'name'
		);
	}
}

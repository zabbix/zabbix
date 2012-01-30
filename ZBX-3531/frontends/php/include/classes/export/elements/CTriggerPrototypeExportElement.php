<?php

class CTriggerPrototypeExportElement extends CExportElement{

	public function __construct($trigger) {
		parent::__construct('trigger_prototype', $trigger);
	}

	protected function requiredFields() {
		return array('expression', 'description', 'url', 'status', 'value', 'priority', 'comments',
			'type', 'comments');
	}


}

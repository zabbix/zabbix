<?php

class CTriggerExportElement extends CExportElement{

	public function __construct($trigger) {
		parent::__construct('trigger', $trigger);

		$this->addDependencies($trigger['dependencies']);
	}

	protected function requiredFields() {
		return array('expression', 'description', 'url', 'status', 'value', 'priority', 'comments',
			'type', 'comments');
	}

	protected function addDependencies(array $dependencies) {
		$dependenciesElement = new CExportElement('dependencies');
		foreach ($dependencies as $dependency) {
			$dependenciesElement->addElement(new CTriggerDependencyExportElement($dependency));
		}
		$this->addElement($dependenciesElement);
	}
}

<?php

class CInterfaceExportElement extends CExportElement {

	public function __construct($interface) {
		$interface = ArrayHelper::getByKeys($interface, array('main', 'type', 'useip', 'ip', 'dns', 'port', 'interface_ref'));
		parent::__construct('interface', $interface);
	}

	protected function fieldNameMap() {
		return array(
			'main' => 'default'
		);
	}

}

<?php

class CInterfaceExportElement extends CNodeExportElement {

	public function __construct($interface) {
		$interface = ArrayHelper::getByKeys($interface, array('main', 'type', 'useip', 'ip', 'dns', 'port', 'interface_ref'));
		parent::__construct('interface', $interface);
	}

}

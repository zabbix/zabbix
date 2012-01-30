<?php

class CItemExportElement extends CExportElement{

	public function __construct($item) {
		parent::__construct('item', $item);
	}

	protected function requiredFields() {
		return array(
			'type', 'snmp_community', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends',
			'status', 'value_type', 'trapper_hosts', 'units', 'delta', 'snmpv3_securityname', 'snmpv3_securitylevel',
			'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'formula', 'valuemapid', 'delay_flex', 'params',
			'ipmi_sensor', 'data_type', 'authtype', 'username', 'password', 'publickey', 'privatekey',
			'port', 'description', 'inventory_link'
		);
	}

	protected function referenceFields() {
		return array('interface_ref');
	}

	protected function fieldNameMap() {
		return array(
			'key_' => 'key'
		);
	}

}

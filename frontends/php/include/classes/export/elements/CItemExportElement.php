<?php

class CItemExportElement extends CNodeExportElement{

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

	public function __construct($item) {
		$requiredField = array();
		if (isset($item['interface_ref'])) {
			$requiredField[] = 'interface_ref';
		}
		$item = ArrayHelper::getByKeys($item, $requiredField);
		parent::__construct('item', $item);
	}

}

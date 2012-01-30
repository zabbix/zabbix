<?php

class CDiscoveyRuleExportElement extends CExportElement {

	public function __construct($discoveryRule) {
		parent::__construct('discovery_rule', $discoveryRule);
	}

	protected function requiredFields() {
		return array('type', 'snmp_community', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends',
			'status', 'value_type', 'trapper_hosts', 'units', 'delta', 'snmpv3_securityname', 'snmpv3_securitylevel',
			'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'formula', 'valuemapid', 'delay_flex', 'params',
			'ipmi_sensor', 'data_type', 'authtype', 'username', 'password', 'publickey', 'privatekey',
			'interfaceid', 'port', 'description', 'inventory_link');
	}
}

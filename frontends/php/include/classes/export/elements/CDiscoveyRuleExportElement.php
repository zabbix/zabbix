<?php

class CDiscoveyRuleExportElement extends CExportElement {

	public function __construct($discoveryRule) {
		parent::__construct('discovery_rule', $discoveryRule);
	}

	protected function requiredFields() {
		return array('type', 'snmp_community', 'snmp_oid', 'name', 'key_', 'delay', 'multiplier',
			'status', 'trapper_hosts', 'snmpv3_securityname', 'snmpv3_securitylevel',
			'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'delay_flex', 'params',
			'ipmi_sensor', 'authtype', 'username', 'password', 'publickey', 'privatekey',
			'port', 'description');
	}

	protected function fieldNameMap() {
		return array(
			'key_' => 'key',
			'trapper_hosts' => 'allowed_hosts'
		);
	}
}

<?php

class CItemExportElement extends CExportElement {

	public function __construct($item) {
		parent::__construct('item', $item);

		$this->addApplications($item['applications']);
		$this->addValueMap($item['valuemapid']);
	}

	protected function requiredFields() {
		return array(
			'type', 'snmp_community', 'multiplier', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends',
			'status', 'value_type', 'trapper_hosts', 'units', 'delta', 'snmpv3_securityname', 'snmpv3_securitylevel',
			'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'formula', 'delay_flex', 'params',
			'ipmi_sensor', 'data_type', 'authtype', 'username', 'password', 'publickey', 'privatekey',
			'port', 'description', 'inventory_link'
		);
	}

	protected function referenceFields() {
		return array('interface_ref');
	}

	protected function fieldNameMap() {
		return array(
			'key_' => 'key',
			'trapper_hosts' => 'allowed_hosts'
		);
	}

	protected function addApplications(array $applications) {
		order_result($applications, 'name');
		$applicationsElement = new CExportElement('applications');
		foreach ($applications as $application) {
			$applicationsElement->addElement(new CItemApplicationExportElement($application));
		}
		$this->addElement($applicationsElement);
	}

	protected function addValueMap(array $valueMap) {
		$this->addElement(new CExportElement('valuemap', $valueMap));
	}

}

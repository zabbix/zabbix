<?php

class CDiscoveyRuleExportElement extends CExportElement {

	public function __construct($discoveryRule) {
		parent::__construct('discovery_rule', $discoveryRule);

		$this->addItemPrototypes($discoveryRule['itemPrototypes']);
		$this->addTriggerPrototypes($discoveryRule['triggerPrototypes']);
		$this->addGraphPrototypes($discoveryRule['graphPrototypes']);
	}

	protected function requiredFields() {
		return array('type', 'snmp_community', 'snmp_oid', 'name', 'key_', 'delay', 'multiplier',
			'status', 'trapper_hosts', 'snmpv3_securityname', 'snmpv3_securitylevel',
			'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'delay_flex', 'params',
			'ipmi_sensor', 'authtype', 'username', 'password', 'publickey', 'privatekey',
			'port', 'description');
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

	protected function addItemPrototypes(array $itemPrototypes) {
		order_result($itemPrototypes, 'name');
		$itemPrototypesElement = new CExportElement('item_prototypes');
		foreach ($itemPrototypes as $itemPrototype) {
			$itemPrototypesElement->addElement(new CItemPrototypeExportElement($itemPrototype));
		}
		$this->addElement($itemPrototypesElement);
	}

	protected function addTriggerPrototypes(array $triggerPrototypes) {
		order_result($triggerPrototypes, 'description');
		$triggerPrototypesElement = new CExportElement('trigger_prototypes');
		foreach ($triggerPrototypes as $triggerPrototype) {
			$triggerPrototypesElement->addElement(new CTriggerPrototypeExportElement($triggerPrototype));
		}
		$this->addElement($triggerPrototypesElement);
	}

	protected function addGraphPrototypes(array $graphPrototypes) {
		order_result($graphPrototypes, 'name');
		$graphPrototypesElement = new CExportElement('graph_prototypes');
		foreach ($graphPrototypes as $graphPrototype) {
			$graphPrototypesElement->addElement(new CGraphPrototypeExportElement($graphPrototype));
		}
		$this->addElement($graphPrototypesElement);
	}
}

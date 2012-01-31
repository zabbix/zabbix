<?php

class CHostExportElement extends CExportElement {

	public function __construct(array $host) {
		parent::__construct('host', $host);

		$this->references = array(
			'num' => 1,
			'refs' => array()
		);

		$this->addGroups($host['groups']);
		$this->addInterfaces($host['interfaces']);
		$this->addItems($host['items']);
		$this->addDiscoveryRules($host['discoveryRules']);
		$this->addItemPrototypes($host['itemPrototypes']);
		$this->addMacros($host['macros']);
		$this->addTemplates($host['parentTemplates']);
		$this->addApplications($host['applications']);
		$this->addInventory($host['inventory']);
	}

	protected function requiredFields() {
		return array('proxy_hostid', 'host', 'status',
			'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'ipmi_disable_until', 'ipmi_available',
			'name');
	}

	protected function addGroups(array $groups) {
		order_result($groups, 'name');
		$groupsElement = new CExportElement('groups');
		foreach ($groups as $group) {
			$groupsElement->addElement(new CHostGroupExportElement($group));
		}
		$this->addElement($groupsElement);
	}

	protected function addInterfaces(array $interfaces) {
		order_result($interfaces, 'ip');
		$interfacesElement = new CExportElement('interfaces');
		foreach ($interfaces as $interface) {
			$refNum = $this->references['num']++;
			$referenceKey = 'if'.$refNum;
			$interface['interface_ref'] = $referenceKey;
			$this->references['refs'][$interface['interfaceid']] = $referenceKey;

			$interfacesElement->addElement(new CInterfaceExportElement($interface));
		}
		$this->addElement($interfacesElement);
	}

	protected function addItems(array $items) {
		order_result($items, 'name');
		$itemsElement = new CExportElement('items');
		foreach ($items as $item) {
			$item['interface_ref'] = $this->references['refs'][$item['interfaceid']];
			$itemsElement->addElement(new CItemExportElement($item));
		}
		$this->addElement($itemsElement);
	}

	protected function addDiscoveryRules(array $discoveryRules) {
		order_result($discoveryRules, 'name');
		$discoveryRulesElement = new CExportElement('discovery_rules');
		foreach ($discoveryRules as $discoveryRule) {
			$discoveryRulesElement->addElement(new CDiscoveyRuleExportElement($discoveryRule));
		}
		$this->addElement($discoveryRulesElement);
	}

	protected function addItemPrototypes(array $itemPrototypes) {
		order_result($itemPrototypes, 'name');
		$itemPrototypesElement = new CExportElement('item_prototypes');
		foreach ($itemPrototypes as $itemPrototype) {
			$itemPrototype['interface_ref'] = $this->references['refs'][$itemPrototype['interfaceid']];
			$itemPrototypesElement->addElement(new CItemPrototypeExportElement($itemPrototype));
		}
		$this->addElement($itemPrototypesElement);
	}

	protected function addMacros(array $macros) {
		order_result($macros, 'macro');
		$macrosElement = new CExportElement('macros');
		foreach ($macros as $macro) {
			$macrosElement->addElement(new CMacroExportElement($macro));
		}
		$this->addElement($macrosElement);
	}

	protected function addTemplates(array $templates) {
		order_result($templates, 'host');
		$templatesElement = new CExportElement('templates');
		foreach ($templates as $template) {
			$templatesElement->addElement(new CHostTemplateExportElement($template));
		}
		$this->addElement($templatesElement);
	}

	protected function addApplications(array $applications) {
		order_result($applications, 'name');
		$applicationsElement = new CExportElement('application');
		foreach ($applications as $application) {
			$applicationsElement->addElement(new CHostApplicationExportElement($application));
		}
		$this->addElement($applicationsElement);
	}

	protected function addInventory(array $inventory) {
		$this->addElement(new CHostInventoryExportElement('inventory', $inventory));
	}
}

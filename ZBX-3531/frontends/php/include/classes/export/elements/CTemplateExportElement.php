<?php

class CTemplateExportElement extends CExportElement{

	public function __construct(array $template) {
		parent::__construct('template', $template);

		$this->addGroups($template['groups']);
		$this->addItems($template['items']);
		$this->addDiscoveryRules($template['discoveryRules']);
		$this->addItemPrototypes($template['itemPrototypes']);
		$this->addMacros($template['macros']);
		$this->addTemplates($template['parentTemplates']);
		$this->addApplications($template['applications']);
	}

	protected function requiredFields() {
		return array('host', 'name');
	}

	protected function fieldNameMap() {
		return array(
			'host' => 'template'
		);
	}

	protected function addGroups(array $groups) {
		order_result($groups, 'name');
		$groupsElement = new CExportElement('groups');
		foreach ($groups as $group) {
			$groupsElement->addElement(new CHostGroupExportElement($group));
		}
		$this->addElement($groupsElement);
	}

	protected function addItems(array $items) {
		order_result($items, 'name');
		$itemsElement = new CExportElement('items');
		foreach ($items as $item) {
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
}

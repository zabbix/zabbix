<?php

class CConfigurationExportBuilder {

	const EXPORT_VERSION = '2.0';

	/**
	 * @var CNodeExportElement
	 */
	private $rootElement;

	/**
	 * @var CExportReferral
	 */
	private $referral;

	public function __construct() {
		$this->referral = new CExportReferral;
	}

	/**
	 * @return mixed
	 */
	public function getExport() {
		return $this->rootElement;
	}

	public function buildRoot() {
		$zabbixExportData = array(
			'version' => self::EXPORT_VERSION,
			'date' => date('Y-m-d\TH:i:s\Z', time() - date('Z')),
		);
		$this->rootElement = new CNodeExportElement('zabbix_export', $zabbixExportData);
	}

	public function buildGroups($groups) {
		order_result($groups, 'name');
		$groupsElement = new CNodeExportElement('groups');

		foreach ($groups as $group) {
			$groupsElement->addElement(new CGroupExportElement($group));
		}

		$this->rootElement->addElement($groupsElement);
	}

	public function buildHosts($hosts) {
		$hostsElement = new CNodeExportElement('hosts');

		order_result($hosts, 'host');
		foreach ($hosts as $host) {
			$this->referral->clearReferences('interface_ref');

			$hostElement = new CHostExportElement($host);
			if (!empty($host['groups'])) {
				order_result($host['groups'], 'name');
				$hostElement->addElement($this->prepareElement($host['groups'], 'groups', 'CHostGroupExportElement'));
			}
			if (!empty($host['interfaces'])) {
				order_result($host['interfaces'], 'ip');
				$hostElement->addElement($this->prepareInterfaces($host['interfaces']));
			}
			if (!empty($host['items'])) {
				order_result($host['items'], 'name');
				$hostElement->addElement($this->prepareItems($host['items']));
			}
			if (!empty($host['discoveryRules'])) {
				order_result($host['discoveryRules'], 'name');
				$hostElement->addElement($this->prepareElement($host['discoveryRules'], 'discovery_rules', 'CDiscoveyRuleExportElement'));
			}
			if (!empty($host['itemPrototypes'])) {
				order_result($host['itemPrototypes'], 'name');
				$hostElement->addElement($this->prepareItemPrototypes($host['itemPrototypes']));
			}
			if (!empty($host['macros'])) {
				order_result($host['macros'], 'macro');
				$hostElement->addElement($this->prepareElement($host['macros'], 'macros', 'CMacroExportElement'));
			}
			if (!empty($host['parentTemplates'])) {
				order_result($host['parentTemplates'], 'host');
				$hostElement->addElement($this->prepareElement($host['parentTemplates'], 'templates', 'CHostTemplateExportElement'));
			}
			if (!empty($host['applications'])) {
				order_result($host['applications'], 'name');
				$hostElement->addElement($this->prepareElement($host['applications'], 'applications', 'CHostApplicationExportElement'));
			}
			if (!empty($host['inventory'])) {
				$hostElement->addElement($this->prepareInventory($host['inventory']));
			}

			$hostsElement->addElement($hostElement);
		}

		$this->rootElement->addElement($hostsElement);
	}

	public function buildTemplates($templates) {
		$templatesElement = new CNodeExportElement('templates');

		order_result($templates, 'host');

		foreach ($templates as $template) {
			$templateElement = new CTemplateExportElement($template);
			if (!empty($template['groups'])) {
				order_result($template['groups'], 'name');
				$templateElement->addElement($this->prepareElement($template['groups'], 'groups', 'CHostGroupExportElement'));
			}
			if (!empty($template['items'])) {
				order_result($template['items'], 'name');
				$templateElement->addElement($this->prepareItems($template['items']));
			}
			if (!empty($template['discoveryRules'])) {
				order_result($template['discoveryRules'], 'name');
				$templateElement->addElement($this->prepareElement($template['discoveryRules'], 'discovery_rules', 'CDiscoveyRuleExportElement'));
			}
			if (!empty($template['itemPrototypes'])) {
				order_result($template['itemPrototypes'], 'name');
				$templateElement->addElement($this->prepareItemPrototypes($template['itemPrototypes']));
			}
			if (!empty($template['macros'])) {
				order_result($template['macros'], 'macro');
				$templateElement->addElement($this->prepareElement($template['macros'], 'macros', 'CMacroExportElement'));
			}
			if (!empty($template['parentTemplates'])) {
				order_result($template['parentTemplates'], 'host');
				$templateElement->addElement($this->prepareElement($template['parentTemplates'], 'templates', 'CHostTemplateExportElement'));
			}
			if (!empty($template['applications'])) {
				order_result($template['applications'], 'name');
				$templateElement->addElement($this->prepareElement($template['applications'], 'applications', 'CHostApplicationExportElement'));
			}


			$templatesElement->addElement($templateElement);
		}

		$this->rootElement->addElement($templatesElement);
	}

	public function buildGraphs(array $graphs) {
		$graphsElement = new CNodeExportElement('graphs');

		foreach ($graphs as $graph) {

			$graphElement = new CGraphExportElement($graph);
			if ($graph['gitems']) {
				$graphElement->addElement($this->prepareGraphItems($graph['gitems']));
			}
			if ($graph['ymin_itemid']) {
				$graphElement->addElement(new CNodeExportElement('ymin_itemid', $graph['ymin_itemid']));
			}
			if ($graph['ymax_itemid']) {
				$graphElement->addElement(new CNodeExportElement('ymax_itemid', $graph['ymax_itemid']));
			}
			$graphsElement->addElement($graphElement);
		}

		$this->rootElement->addElement($graphsElement);
	}

	public function buildGraphPrototypes(array $graphPrototypes) {
		$graphPrototypesElement = new CNodeExportElement('graph_prototypes');

		foreach ($graphPrototypes as $graphPrototype) {
			$graphPrototypeElement = new CGraphPrototypeExportElement($graphPrototype);
			if ($graphPrototype['gitems']) {
				$graphPrototypeElement->addElement($this->prepareGraphItems($graphPrototype['gitems']));
			}
			if ($graphPrototype['ymin_itemid']) {
				$graphPrototypeElement->addElement(new CNodeExportElement('ymin_itemid', $graphPrototype['ymin_itemid']));
			}
			if ($graphPrototype['ymax_itemid']) {
				$graphPrototypeElement->addElement(new CNodeExportElement('ymax_itemid', $graphPrototype['ymax_itemid']));
			}
			$graphPrototypesElement->addElement($graphPrototypeElement);
		}

		$this->rootElement->addElement($graphPrototypesElement);
	}

	public function buildTriggers(array $triggers) {
		$triggersElement = new CNodeExportElement('triggers');

		foreach ($triggers as $trigger) {

			$triggerElement = new CTriggerExportElement($trigger);
			if ($trigger['dependencies']) {
//				$triggerElement->addElement($this->prepareElement($trigger['gitems'], 'graph_items', 'CGraphItemExportElement'));
			}
			$triggersElement->addElement($triggerElement);
		}

		$this->rootElement->addElement($triggersElement);
	}

	public function buildTriggerPrototypes(array $triggerPrototypes) {
		$triggerPrototypesElement = new CNodeExportElement('trigger_prototypes');

		foreach ($triggerPrototypes as $triggerPrototype) {
			$triggerPrototypesElement->addElement(new CTriggerPrototypeExportElement($triggerPrototype));
		}

		$this->rootElement->addElement($triggerPrototypesElement);
	}


	protected function prepareElement($data, $NodeName, $class) {
		$element = new CNodeExportElement($NodeName);

		foreach ($data as $value) {
			$element->addElement(new $class($value));
		}

		return $element;
	}

	protected function prepareInterfaces(array $interfaces) {
		$interfacesElement = new CNodeExportElement('interfaces');

		foreach ($interfaces as $interface) {
			$interface = $this->referral->createReference('interface_ref', $interface);
			$interfacesElement->addElement(new CInterfaceExportElement($interface));
		}

		return $interfacesElement;
	}

	protected function prepareItems(array $items) {
		$itemsElement = new CNodeExportElement('items');

		foreach ($items as $item) {
			$item = $this->referral->addReference('interface_ref', $item);

			$itemElement = new CItemExportElement($item);
			if (!empty($item['applications'])) {
				$itemElement->addElement($this->prepareElement($item['applications'], 'applications', 'CItemApplicationExportElement'));
			}
			$itemsElement->addElement($itemElement);
		}

		return $itemsElement;
	}

	protected function prepareItemPrototypes(array $itemPrototypes) {
		$itemPrototypesElement = new CNodeExportElement('item_prototypes');

		foreach ($itemPrototypes as $itemPrototype) {
			$itemPrototype = $this->referral->addReference('interface_ref', $itemPrototype);

			$itemPrototypeElement = new CItemPrototypeExportElement($itemPrototype);
			if (!empty($itemPrototype['applications'])) {
				$itemPrototypeElement->addElement($this->prepareElement($itemPrototype['applications'], 'applications', 'CItemApplicationExportElement'));
			}
			$itemPrototypesElement->addElement($itemPrototypeElement);
		}

		return $itemPrototypesElement;
	}

	protected function prepareInventory(array $inventory) {
		$inventoryElement = new CHostInventoryExportElement('inventory', $inventory);

		return $inventoryElement;
	}

	protected function prepareGraphItems(array $graphItems) {
		$graphItemsElement = new CNodeExportElement('item_prototypes');
		foreach ($graphItems as $graphItem) {
			$graphItemElement = new CGraphItemExportElement($graphItem);
			$graphItemElement->addElement(new CNodeExportElement('itemid', $graphItem['itemid']));
			$graphItemsElement->addElement($graphItemElement);
		}

		return $graphItemsElement;
	}

}


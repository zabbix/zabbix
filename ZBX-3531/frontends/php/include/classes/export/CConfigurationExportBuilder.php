<?php

class CConfigurationExportBuilder {

	const EXPORT_VERSION = '2.0';

	/**
	 * @var CExportElement
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
		$this->rootElement = new CExportElement('zabbix_export', $zabbixExportData);
	}

	public function buildGroups($groups) {
		order_result($groups, 'name');
		$groupsElement = new CExportElement('groups');

		foreach ($groups as $group) {
			$groupsElement->addElement(new CGroupExportElement($group));
		}

		$this->rootElement->addElement($groupsElement);
	}

	public function buildHosts($hosts) {
		$hostsElement = new CExportElement('hosts');

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
		$templatesElement = new CExportElement('templates');

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
		$graphsElement = new CExportElement('graphs');

		foreach ($graphs as $graph) {

			$graphElement = new CGraphExportElement($graph);
			if ($graph['gitems']) {
				$graphElement->addElement($this->prepareGraphItems($graph['gitems']));
			}
			if ($graph['ymin_itemid']) {
				$graphElement->addElement(new CExportElement('ymin_item', $graph['ymin_itemid']));
			}
			if ($graph['ymax_itemid']) {
				$graphElement->addElement(new CExportElement('ymax_item', $graph['ymax_itemid']));
			}
			$graphsElement->addElement($graphElement);
		}

		$this->rootElement->addElement($graphsElement);
	}

	public function buildGraphPrototypes(array $graphPrototypes) {
		$graphPrototypesElement = new CExportElement('graph_prototypes');

		foreach ($graphPrototypes as $graphPrototype) {
			$graphPrototypeElement = new CGraphPrototypeExportElement($graphPrototype);
			if ($graphPrototype['gitems']) {
				$graphPrototypeElement->addElement($this->prepareGraphItems($graphPrototype['gitems']));
			}
			if ($graphPrototype['ymin_itemid']) {
				$graphPrototypeElement->addElement(new CExportElement('ymin_itemid', $graphPrototype['ymin_itemid']));
			}
			if ($graphPrototype['ymax_itemid']) {
				$graphPrototypeElement->addElement(new CExportElement('ymax_itemid', $graphPrototype['ymax_itemid']));
			}
			$graphPrototypesElement->addElement($graphPrototypeElement);
		}

		$this->rootElement->addElement($graphPrototypesElement);
	}

	public function buildTriggers(array $triggers) {
		$triggersElement = new CExportElement('triggers');

		foreach ($triggers as $trigger) {

			$triggerElement = new CTriggerExportElement($trigger);

			if ($trigger['dependencies']) {
				$dependenciesElement = new CExportElement('dependencies');
				foreach ($trigger['dependencies'] as $dependency) {
					$dependenciesElement->addElement(new CTriggerDependencyExportElement($dependency));
				}
				$triggerElement->addElement($dependenciesElement);
			}

			$triggersElement->addElement($triggerElement);
		}

		$this->rootElement->addElement($triggersElement);
	}

	public function buildTriggerPrototypes(array $triggerPrototypes) {
		$triggerPrototypesElement = new CExportElement('trigger_prototypes');

		foreach ($triggerPrototypes as $triggerPrototype) {
			$triggerPrototypesElement->addElement(new CTriggerPrototypeExportElement($triggerPrototype));
		}

		$this->rootElement->addElement($triggerPrototypesElement);
	}

	public function buildScreens(array $screens) {

	}

	public function buildImages(array $images) {
		$imagesElement = new CExportElement('images');

		foreach ($images as $image) {
			$imagesElement->addElement(new CImageExportElement($image));
		}

		$this->rootElement->addElement($imagesElement);
	}

	public function buildMaps(array $maps) {
		$mapsElement = new CExportElement('maps');
		foreach ($maps as $map) {
			$mapsElement->addElement(new CMapExportElement($map));
		}
		$this->rootElement->addElement($mapsElement);
	}


	protected function prepareElement($data, $NodeName, $class) {
		$element = new CExportElement($NodeName);

		foreach ($data as $value) {
			$element->addElement(new $class($value));
		}

		return $element;
	}

	protected function prepareInterfaces(array $interfaces) {
		$interfacesElement = new CExportElement('interfaces');

		foreach ($interfaces as $interface) {
			$interface = $this->referral->createReference('interface_ref', $interface);
			$interfacesElement->addElement(new CInterfaceExportElement($interface));
		}

		return $interfacesElement;
	}

	protected function prepareItems(array $items) {
		$itemsElement = new CExportElement('items');

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
		$itemPrototypesElement = new CExportElement('item_prototypes');

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
		$graphItemsElement = new CExportElement('graph_items');

		foreach ($graphItems as $graphItem) {
			$graphItemElement = new CGraphItemExportElement($graphItem);
			$graphItemElement->addElement(new CExportElement('item', $graphItem['itemid']));
			$graphItemsElement->addElement($graphItemElement);
		}

		return $graphItemsElement;
	}

}


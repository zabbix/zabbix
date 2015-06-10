<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CConfigurationExport {

	/**
	 * @var CExportWriter
	 */
	protected $writer;

	/**
	 * @var CConfigurationExportBuilder
	 */
	protected $builder;

	/**
	 * Array with data that must be exported.
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * Array with data fields that must be exported.
	 *
	 * @var array
	 */
	protected $dataFields;

	/**
	 * Constructor.
	 *
	 * @param array $options ids of elements that should be exported.
	 */
	public function __construct(array $options) {
		$this->options = [
			'hosts' => [],
			'templates' => [],
			'groups' => [],
			'screens' => [],
			'images' => [],
			'maps' => []
		];
		$this->options = array_merge($this->options, $options);

		$this->data = [
			'groups' => [],
			'templates' => [],
			'hosts' => [],
			'triggers' => [],
			'triggerPrototypes' => [],
			'graphs' => [],
			'graphPrototypes' => [],
			'screens' => [],
			'images' => [],
			'maps' => []
		];

		$this->dataFields = [
			'item' => ['hostid', 'multiplier', 'type', 'snmp_community', 'snmp_oid', 'name', 'key_', 'delay', 'history',
				'trends', 'status', 'value_type', 'trapper_hosts', 'units', 'delta', 'snmpv3_contextname',
				'snmpv3_securityname', 'snmpv3_securitylevel', 'snmpv3_authprotocol', 'snmpv3_authpassphrase',
				'snmpv3_privprotocol', 'snmpv3_privpassphrase', 'formula', 'valuemapid', 'delay_flex', 'params',
				'ipmi_sensor', 'data_type', 'authtype', 'username', 'password', 'publickey', 'privatekey',
				'interfaceid', 'port', 'description', 'inventory_link', 'flags', 'logtimefmt'
			],
			'drule' => ['itemid', 'hostid', 'type', 'snmp_community', 'snmp_oid', 'name', 'key_', 'delay', 'history',
				'trends', 'status', 'value_type', 'trapper_hosts', 'units', 'delta', 'snmpv3_contextname',
				'snmpv3_securityname', 'snmpv3_securitylevel', 'snmpv3_authprotocol', 'snmpv3_authpassphrase',
				'snmpv3_privprotocol', 'snmpv3_privpassphrase', 'formula', 'valuemapid', 'delay_flex', 'params',
				'ipmi_sensor', 'data_type', 'authtype', 'username', 'password', 'publickey', 'privatekey',
				'interfaceid', 'port', 'description', 'inventory_link', 'flags', 'filter', 'lifetime'
			],
			'discoveryrule' => ['hostid', 'multiplier', 'type', 'snmp_community', 'snmp_oid', 'name', 'key_',
				'delay', 'history', 'trends', 'status', 'value_type', 'trapper_hosts', 'units', 'delta',
				'snmpv3_contextname', 'snmpv3_securityname', 'snmpv3_securitylevel', 'snmpv3_authprotocol',
				'snmpv3_authpassphrase', 'snmpv3_privprotocol', 'snmpv3_privpassphrase', 'formula', 'valuemapid',
				'delay_flex', 'params', 'ipmi_sensor', 'data_type', 'authtype', 'username', 'password', 'publickey',
				'privatekey', 'interfaceid', 'port', 'description', 'inventory_link', 'flags', 'logtimefmt'
			]
		];
	}

	/**
	 * Setter for $writer property.
	 *
	 * @param CExportWriter $writer
	 */
	public function setWriter(CExportWriter $writer) {
		$this->writer = $writer;
	}

	/**
	 * Setter for builder property.
	 *
	 * @param CConfigurationExportBuilder $builder
	 */
	public function setBuilder(CConfigurationExportBuilder $builder) {
		$this->builder = $builder;
	}

	/**
	 * Export elements whose ids were passed to constructor.
	 * The resulting export format depends on the export writer that was set,
	 * the export structure depends on the builder that was set.
	 *
	 * @return string
	 */
	public function export() {
		$this->gatherData();

		if ($this->data['groups']) {
			$this->builder->buildGroups($this->data['groups']);
		}
		if ($this->data['templates']) {
			$this->builder->buildTemplates($this->data['templates']);
		}
		if ($this->data['hosts']) {
			$this->builder->buildHosts($this->data['hosts']);
		}
		if ($this->data['triggers']) {
			$this->builder->buildTriggers($this->data['triggers']);
		}
		if ($this->data['graphs']) {
			$this->builder->buildGraphs($this->data['graphs']);
		}
		if ($this->data['screens']) {
			$this->builder->buildScreens($this->data['screens']);
		}
		if ($this->data['images']) {
			$this->builder->buildImages($this->data['images']);
		}
		if ($this->data['maps']) {
			$this->builder->buildMaps($this->data['maps']);
		}

		return $this->writer->write($this->builder->getExport());
	}

	/**
	 * Gathers data required for export from database depends on $options passed to constructor.
	 */
	protected function gatherData() {
		$options = $this->filterOptions($this->options);

		if ($options['groups']) {
			$this->gatherGroups($options['groups']);
		}

		if ($options['templates']) {
			$this->gatherTemplates($options['templates']);
		}

		if ($options['hosts']) {
			$this->gatherHosts($options['hosts']);
		}

		if ($options['templates'] || $options['hosts']) {
			$this->gatherGraphs($options['hosts'], $options['templates']);
			$this->gathertriggers($options['hosts'], $options['templates']);
		}

		if ($options['screens']) {
			$this->gatherScreens($options['screens']);
		}

		if ($options['maps']) {
			$this->gatherMaps($options['maps']);
		}
	}

	/**
	 * Excludes objects that cannot be exported.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	protected function filterOptions(array $options) {
		if ($options['hosts']) {
			// exclude discovered hosts
			$hosts = API::Host()->get([
				'output' => ['hostid'],
				'hostids' => $options['hosts'],
				'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
			]);

			$options['hosts'] = zbx_objectValues($hosts, 'hostid');
		}

		return $options;
	}

	/**
	 * Get groups for export from database.
	 *
	 * @param array $groupIds
	 */
	protected function gatherGroups(array $groupIds) {
		$this->data['groups'] = API::HostGroup()->get([
			'groupids' => $groupIds,
			'preservekeys' => true,
			'output' => API_OUTPUT_EXTEND
		]);
	}

	/**
	 * Get templates for export from database.
	 *
	 * @param array $templateIds
	 */
	protected function gatherTemplates(array $templateIds) {
		$templates = API::Template()->get([
			'templateids' => $templateIds,
			'output' => ['host', 'name', 'description'],
			'selectMacros' => API_OUTPUT_EXTEND,
			'selectGroups' => API_OUTPUT_EXTEND,
			'selectParentTemplates' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		]);

		// merge host groups with all groups
		$templateGroups = [];

		foreach ($templates as &$template) {
			$templateGroups += zbx_toHash($template['groups'], 'groupid');

			$template['screens'] = [];
			$template['applications'] = [];
			$template['discoveryRules'] = [];
			$template['items'] = [];
		}
		unset($template);

		$this->data['groups'] += $templateGroups;

		// applications
		$applications = API::Application()->get([
			'hostids' => $templateIds,
			'output' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'preservekeys' => true
		]);

		foreach ($applications as $application) {
			if (!isset($templates[$application['hostid']]['applications'])) {
				$templates[$application['hostid']]['applications'] = [];
			}

			$templates[$application['hostid']]['applications'][] = $application;
		}

		// screens
		$screens = API::TemplateScreen()->get([
			'templateids' => $templateIds,
			'selectScreenItems' => API_OUTPUT_EXTEND,
			'noInheritance' => true,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		]);
		$this->prepareScreenExport($screens);

		foreach ($screens as $screen) {
			if (!isset($templates[$screen['templateid']]['screens'])) {
				$templates[$screen['templateid']]['screens'] = [];
			}

			$templates[$screen['templateid']]['screens'][] = $screen;
		}

		$this->data['templates'] = $templates;

		$this->gatherTemplateItems($templateIds);
		$this->gatherTemplateDiscoveryRules($templateIds);
	}

	/**
	 * Get Hosts for export from database.
	 *
	 * @param array $hostIds
	 */
	protected function gatherHosts(array $hostIds) {
		$hosts = API::Host()->get([
			'hostids' => $hostIds,
			'output' => [
				'proxy_hostid', 'host', 'status', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password',
				'name', 'description'
			],
			'selectInventory' => true,
			'selectInterfaces' => ['interfaceid', 'main', 'type', 'useip', 'ip', 'dns', 'port', 'bulk'],
			'selectMacros' => API_OUTPUT_EXTEND,
			'selectGroups' => API_OUTPUT_EXTEND,
			'selectParentTemplates' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		]);

		// merge host groups with all groups
		$hostGroups = [];

		foreach ($hosts as &$host) {
			$hostGroups += zbx_toHash($host['groups'], 'groupid');

			$host['applications'] = [];
			$host['discoveryRules'] = [];
			$host['items'] = [];
		}
		unset($host);

		$this->data['groups'] += $hostGroups;

		// applications
		$applications = API::Application()->get([
			'hostids' => $hostIds,
			'output' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'preservekeys' => true
		]);

		foreach ($applications as $application) {
			if (!isset($hosts[$application['hostid']]['applications'])) {
				$hosts[$application['hostid']]['applications'] = [];
			}

			$hosts[$application['hostid']]['applications'][] = $application;
		}

		// proxies
		$dbProxies = DBselect(
			'SELECT h.hostid,h.host'.
			' FROM hosts h'.
			' WHERE '.dbConditionInt('h.hostid', zbx_objectValues($hosts, 'proxy_hostid'))
		);

		$proxies = [];

		while ($proxy = DBfetch($dbProxies)) {
			$proxies[$proxy['hostid']] = $proxy['host'];
		}

		foreach ($hosts as &$host) {
			$host['proxy'] = $host['proxy_hostid'] ? ['name' => $proxies[$host['proxy_hostid']]] : null;
		}
		unset($host);

		$this->data['hosts'] = $hosts;

		$this->gatherHostItems($hostIds);
		$this->gatherHostDiscoveryRules($hostIds);
	}

	/**
	 * Get hosts items from database.
	 *
	 * @param array $hostIds
	 */
	protected function gatherHostItems(array $hostIds) {
		$items = API::Item()->get([
			'hostids' => $hostIds,
			'output' => $this->dataFields['item'],
			'selectApplications' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'filter' => ['flags' => [ZBX_FLAG_DISCOVERY_NORMAL]],
			'preservekeys' => true
		]);

		$items = $this->prepareItems($items);

		foreach ($items as $item) {
			if (!isset($this->data['hosts'][$item['hostid']]['items'])) {
				$this->data['hosts'][$item['hostid']]['items'] = [];
			}

			$this->data['hosts'][$item['hostid']]['items'][] = $item;
		}
	}

	/**
	 * Get templates items from database.
	 *
	 * @param array $templateIds
	 */
	protected function gatherTemplateItems(array $templateIds) {
		$items = API::Item()->get([
			'hostids' => $templateIds,
			'output' => $this->dataFields['item'],
			'selectApplications' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'filter' => ['flags' => [ZBX_FLAG_DISCOVERY_NORMAL]],
			'preservekeys' => true
		]);

		$items = $this->prepareItems($items);

		foreach ($items as $item) {
			if (!isset($this->data['templates'][$item['hostid']]['items'])) {
				$this->data['templates'][$item['hostid']]['items'] = [];
			}

			$this->data['templates'][$item['hostid']]['items'][] = $item;
		}
	}

	/**
	 * Get items related objects data from database.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	protected function prepareItems(array $items) {
		// gather value maps
		$valueMapNames = [];

		$dbValueMaps = DBselect(
			'SELECT vm.valuemapid, vm.name FROM valuemaps vm'.
			' WHERE '.dbConditionInt('vm.valuemapid', zbx_objectValues($items, 'valuemapid'))
		);

		while ($valueMap = DBfetch($dbValueMaps)) {
			$valueMapNames[$valueMap['valuemapid']] = $valueMap['name'];
		}

		foreach ($items as &$item) {
			$item['valuemap'] = [];

			if ($item['valuemapid']) {
				$item['valuemap'] = ['name' => $valueMapNames[$item['valuemapid']]];
			}
		}
		unset($item);

		return $items;
	}

	/**
	 * Get hosts discovery rules from database.
	 *
	 * @param array $hostIds
	 */
	protected function gatherHostDiscoveryRules(array $hostIds) {
		$items = API::DiscoveryRule()->get([
			'hostids' => $hostIds,
			'output' => $this->dataFields['drule'],
			'selectFilter' => ['evaltype', 'formula', 'conditions'],
			'inherited' => false,
			'preservekeys' => true
		]);

		$items = $this->prepareDiscoveryRules($items);

		foreach ($items as $item) {
			if (!isset($this->data['hosts'][$item['hostid']]['items'])) {
				$this->data['hosts'][$item['hostid']]['discoveryRules'] = [];
			}

			$this->data['hosts'][$item['hostid']]['discoveryRules'][] = $item;
		}
	}

	/**
	 * Get templates discovery rules from database.
	 *
	 * @param array $templateIds
	 */
	protected function gatherTemplateDiscoveryRules(array $templateIds) {
		$items = API::DiscoveryRule()->get([
			'hostids' => $templateIds,
			'output' => $this->dataFields['drule'],
			'selectFilter' => ['evaltype', 'formula', 'conditions'],
			'inherited' => false,
			'preservekeys' => true
		]);

		$items = $this->prepareDiscoveryRules($items);

		foreach ($items as $item) {
			if (!isset($this->data['templates'][$item['hostid']]['discoveryRules'])) {
				$this->data['templates'][$item['hostid']]['discoveryRules'] = [];
			}

			$this->data['templates'][$item['hostid']]['discoveryRules'][] = $item;
		}
	}

	/**
	 * Get discovery rules related objects from database.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	protected function prepareDiscoveryRules(array $items) {
		foreach ($items as &$item) {
			$item['itemPrototypes'] = [];
			$item['graphPrototypes'] = [];
			$item['triggerPrototypes'] = [];
			$item['hostPrototypes'] = [];

			// unset unnecessary condition fields
			foreach ($item['filter']['conditions'] as &$condition) {
				unset($condition['item_conditionid'], $condition['itemid']);
			}
		}
		unset($item);

		// gather item prototypes
		$prototypes = API::ItemPrototype()->get([
			'discoveryids' => zbx_objectValues($items, 'itemid'),
			'output' => $this->dataFields['discoveryrule'],
			'selectApplications' => API_OUTPUT_EXTEND,
			'selectDiscoveryRule' => ['itemid'],
			'inherited' => false,
			'preservekeys' => true
		]);

		// gather value maps
		$valueMaps = [];

		$dbValueMaps = DBselect(
			'SELECT vm.valuemapid, vm.name FROM valuemaps vm'.
			' WHERE '.dbConditionInt('vm.valuemapid', zbx_objectValues($prototypes, 'valuemapid'))
		);

		while ($valueMap = DBfetch($dbValueMaps)) {
			$valueMaps[$valueMap['valuemapid']] = $valueMap['name'];
		}

		foreach ($prototypes as $prototype) {
			$prototype['valuemap'] = [];

			if ($prototype['valuemapid']) {
				$prototype['valuemap']['name'] = $valueMaps[$prototype['valuemapid']];
			}

			$items[$prototype['discoveryRule']['itemid']]['itemPrototypes'][] = $prototype;
		}

		// gather graph prototypes
		$graphs = API::GraphPrototype()->get([
			'discoveryids' => zbx_objectValues($items, 'itemid'),
			'selectDiscoveryRule' => API_OUTPUT_EXTEND,
			'selectGraphItems' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'preservekeys' => true
		]);

		$graphs = $this->prepareGraphs($graphs);

		foreach ($graphs as $graph) {
			$items[$graph['discoveryRule']['itemid']]['graphPrototypes'][] = $graph;
		}

		// gather trigger prototypes
		$triggers = API::TriggerPrototype()->get([
			'discoveryids' => zbx_objectValues($items, 'itemid'),
			'output' => ['expression', 'description', 'url', 'status', 'priority', 'comments', 'type'],
			'selectDiscoveryRule' => API_OUTPUT_EXTEND,
			'selectDependencies' => ['description', 'expression'],
			'selectItems' => ['flags', 'type'],
			'inherited' => false,
			'preservekeys' => true
		]);

		foreach($triggers as $trigger){
			foreach ($trigger['items'] as $item) {
				if ($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED || $item['type'] == ITEM_TYPE_HTTPTEST) {
					continue 2;
				}
			}

			$trigger['expression'] = explode_exp($trigger['expression']);

			foreach ($trigger['dependencies'] as &$dependency) {
				$dependency['expression'] = explode_exp($dependency['expression']);
			}
			unset($dependency);

			$items[$trigger['discoveryRule']['itemid']]['triggerPrototypes'][] = $trigger;
		}

		// gather host prototypes
		$hostPrototypes = API::HostPrototype()->get([
			'discoveryids' => zbx_objectValues($items, 'itemid'),
			'output' => API_OUTPUT_EXTEND,
			'selectGroupLinks' => API_OUTPUT_EXTEND,
			'selectGroupPrototypes' => API_OUTPUT_EXTEND,
			'selectDiscoveryRule' => API_OUTPUT_EXTEND,
			'selectTemplates' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'preservekeys' => true
		]);

		// replace group prototype group IDs with references
		$groupIds = [];

		foreach ($hostPrototypes as $hostPrototype) {
			foreach ($hostPrototype['groupLinks'] as $groupLink) {
				$groupIds[$groupLink['groupid']] = $groupLink['groupid'];
			}
		}

		$groups = $this->getGroupsReferences($groupIds);

		// export the groups used in group prototypes
		$this->data['groups'] += $groups;

		foreach ($hostPrototypes as $hostPrototype) {
			foreach ($hostPrototype['groupLinks'] as &$groupLink) {
				$groupLink['groupid'] = $groups[$groupLink['groupid']];
			}

			unset($groupLink);

			$items[$hostPrototype['discoveryRule']['itemid']]['hostPrototypes'][] = $hostPrototype;
		}

		return $items;
	}

	/**
	 * Get graphs for export from database.
	 *
	 * @param array $hostIds
	 * @param array $templateIds
	 */
	protected function gatherGraphs(array $hostIds, array $templateIds) {
		$hostIds = array_merge($hostIds, $templateIds);

		$graphs = API::Graph()->get([
			'hostids' => $hostIds,
			'filter' => ['flags' => [ZBX_FLAG_DISCOVERY_NORMAL]],
			'selectGraphItems' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		]);

		$this->data['graphs'] = $this->prepareGraphs($graphs);
	}

	/**
	 * Unset graphs that have lld created items or web items.
	 *
	 * @param array $graphs
	 *
	 * @return array
	 */
	protected function prepareGraphs(array $graphs) {
		// get item axis items info
		$graphItemIds = [];

		foreach ($graphs as $graph) {
			foreach ($graph['gitems'] as $gItem) {
				$graphItemIds[$gItem['itemid']] = $gItem['itemid'];
			}

			if ($graph['ymin_itemid']) {
				$graphItemIds[$graph['ymin_itemid']] = $graph['ymin_itemid'];
			}
			if ($graph['ymax_itemid']) {
				$graphItemIds[$graph['ymax_itemid']] = $graph['ymax_itemid'];
			}
		}

		$graphItems = API::Item()->get([
			'itemids' => $graphItemIds,
			'output' => ['key_', 'flags', 'type'],
			'webitems' => true,
			'selectHosts' => ['host'],
			'preservekeys' => true,
			'filter' => ['flags' => null]
		]);

		foreach ($graphs as $gnum => $graph) {
			if ($graph['ymin_itemid'] && isset($graphItems[$graph['ymin_itemid']])) {
				$axisItem = $graphItems[$graph['ymin_itemid']];

				// unset lld and web graphs
				if ($axisItem['flags'] == ZBX_FLAG_DISCOVERY_CREATED || $axisItem['type'] == ITEM_TYPE_HTTPTEST) {
					unset($graphs[$gnum]);
					continue;
				}

				$axisItemHost = reset($axisItem['hosts']);

				$graphs[$gnum]['ymin_itemid'] = [
					'host' => $axisItemHost['host'],
					'key' => $axisItem['key_']
				];
			}

			if ($graph['ymax_itemid'] && isset($graphItems[$graph['ymax_itemid']])) {
				$axisItem = $graphItems[$graph['ymax_itemid']];

				// unset lld and web graphs
				if ($axisItem['flags'] == ZBX_FLAG_DISCOVERY_CREATED || $axisItem['type'] == ITEM_TYPE_HTTPTEST) {
					unset($graphs[$gnum]);
					continue;
				}

				$axisItemHost = reset($axisItem['hosts']);

				$graphs[$gnum]['ymax_itemid'] = [
					'host' => $axisItemHost['host'],
					'key' => $axisItem['key_']
				];
			}

			foreach ($graph['gitems'] as $ginum => $gItem) {
				$item = $graphItems[$gItem['itemid']];

				// unset lld and web graphs
				if ($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED || $item['type'] == ITEM_TYPE_HTTPTEST) {
					unset($graphs[$gnum]);
					continue 2;
				}

				$itemHost = reset($item['hosts']);

				$graphs[$gnum]['gitems'][$ginum]['itemid'] = [
					'host' => $itemHost['host'],
					'key' => $item['key_']
				];
			}
		}

		return $graphs;
	}

	/**
	 * Get triggers for export from database.
	 *
	 * @param array $hostIds
	 * @param array $templateIds
	 */
	protected function gatherTriggers(array $hostIds, array $templateIds) {
		$hostIds = array_merge($hostIds, $templateIds);

		$triggers = API::Trigger()->get([
			'hostids' => $hostIds,
			'output' => ['expression', 'description', 'url', 'status', 'priority', 'comments', 'type'],
			'filter' => ['flags' => [ZBX_FLAG_DISCOVERY_NORMAL]],
			'selectDependencies' => ['description', 'expression'],
			'selectItems' => ['flags', 'type'],
			'inherited' => false,
			'preservekeys' => true
		]);

		foreach($triggers as $trigger){
			foreach ($trigger['items'] as $item) {
				if ($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED || $item['type'] == ITEM_TYPE_HTTPTEST) {
					continue 2;
				}
			}

			$trigger['expression'] = explode_exp($trigger['expression']);

			foreach ($trigger['dependencies'] as &$dependency) {
				$dependency['expression'] = explode_exp($dependency['expression']);
			}
			unset($dependency);

			$this->data['triggers'][] = $trigger;
		}
	}

	/**
	 * Get maps for export from database.
	 *
	 * @param array $mapIds
	 */
	protected function gatherMaps(array $mapIds) {
		$sysmaps = API::Map()->get([
			'sysmapids' => $mapIds,
			'selectSelements' => API_OUTPUT_EXTEND,
			'selectLinks' => API_OUTPUT_EXTEND,
			'selectIconMap' => API_OUTPUT_EXTEND,
			'selectUrls' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		]);

		$this->prepareMapExport($sysmaps);

		$this->data['maps'] = $sysmaps;

		$images = API::Image()->get([
			'output' => ['imageid', 'name', 'imagetype'],
			'sysmapids' => zbx_objectValues($sysmaps, 'sysmapid'),
			'select_image' => true,
			'preservekeys' => true
		]);

		foreach ($images as &$image) {
			$image = [
				'name' => $image['name'],
				'imagetype' => $image['imagetype'],
				'encodedImage' => $image['image'],
			];
		}
		unset($image);

		$this->data['images'] = $images;
	}

	/**
	 * Get screens for export from database.
	 *
	 * @param array $screenIds
	 */
	protected function gatherScreens(array $screenIds) {
		$screens = API::Screen()->get([
			'screenids' => $screenIds,
			'selectScreenItems' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND
		]);

		$this->prepareScreenExport($screens);
		$this->data['screens'] = $screens;
	}

	/**
	 * Change screen elements real database resource id to unique field references.
	 *
	 * @param array $exportScreens
	 */
	protected function prepareScreenExport(array &$exportScreens) {
		$screenIds = [];
		$sysmapIds = [];
		$groupIds = [];
		$hostIds = [];
		$graphIds = [];
		$itemIds = [];

		// gather element ids that must be substituted
		foreach ($exportScreens as $screen) {
			foreach ($screen['screenitems'] as $screenItem) {
				if ($screenItem['resourceid'] != 0) {
					switch ($screenItem['resourcetype']) {
						case SCREEN_RESOURCE_HOSTS_INFO:
						case SCREEN_RESOURCE_TRIGGERS_INFO:
						case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
						case SCREEN_RESOURCE_DATA_OVERVIEW:
						case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
							$groupIds[$screenItem['resourceid']] = $screenItem['resourceid'];
							break;

						case SCREEN_RESOURCE_HOST_TRIGGERS:
							$hostIds[$screenItem['resourceid']] = $screenItem['resourceid'];
							break;

						case SCREEN_RESOURCE_GRAPH:
						case SCREEN_RESOURCE_LLD_GRAPH:
							$graphIds[$screenItem['resourceid']] = $screenItem['resourceid'];
							break;

						case SCREEN_RESOURCE_SIMPLE_GRAPH:
						case SCREEN_RESOURCE_LLD_SIMPLE_GRAPH:
						case SCREEN_RESOURCE_PLAIN_TEXT:
							$itemIds[$screenItem['resourceid']] = $screenItem['resourceid'];
							break;

						case SCREEN_RESOURCE_MAP:
							$sysmapIds[$screenItem['resourceid']] = $screenItem['resourceid'];
							break;

						case SCREEN_RESOURCE_SCREEN:
							$screenIds[$screenItem['resourceid']] = $screenItem['resourceid'];
							break;

						case SCREEN_RESOURCE_CLOCK:
							if ($screenItem['style'] == TIME_TYPE_HOST) {
								$itemIds[$screenItem['resourceid']] = $screenItem['resourceid'];
							}
							break;
					}
				}
			}
		}

		$screens = $this->getScreensReferences($screenIds);
		$sysmaps = $this->getMapsReferences($sysmapIds);
		$groups = $this->getGroupsReferences($groupIds);
		$hosts = $this->getHostsReferences($hostIds);
		$graphs = $this->getGraphsReferences($graphIds);
		$items = $this->getItemsReferences($itemIds);

		foreach ($exportScreens as &$screen) {
			unset($screen['screenid']);

			foreach ($screen['screenitems'] as &$screenItem) {
				if ($screenItem['resourceid'] != 0) {
					switch ($screenItem['resourcetype']) {
						case SCREEN_RESOURCE_HOSTS_INFO:
						case SCREEN_RESOURCE_TRIGGERS_INFO:
						case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
						case SCREEN_RESOURCE_DATA_OVERVIEW:
						case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
							$screenItem['resourceid'] = $groups[$screenItem['resourceid']];
							break;

						case SCREEN_RESOURCE_HOST_TRIGGERS:
							$screenItem['resourceid'] = $hosts[$screenItem['resourceid']];
							break;

						case SCREEN_RESOURCE_GRAPH:
						case SCREEN_RESOURCE_LLD_GRAPH:
							$screenItem['resourceid'] = $graphs[$screenItem['resourceid']];
							break;

						case SCREEN_RESOURCE_SIMPLE_GRAPH:
						case SCREEN_RESOURCE_LLD_SIMPLE_GRAPH:
						case SCREEN_RESOURCE_PLAIN_TEXT:
							$screenItem['resourceid'] = $items[$screenItem['resourceid']];
							break;

						case SCREEN_RESOURCE_MAP:
							$screenItem['resourceid'] = $sysmaps[$screenItem['resourceid']];
							break;

						case SCREEN_RESOURCE_SCREEN:
							$screenItem['resourceid'] = $screens[$screenItem['resourceid']];
							break;

						case SCREEN_RESOURCE_CLOCK:
							if ($screenItem['style'] == TIME_TYPE_HOST) {
								$screenItem['resourceid'] = $items[$screenItem['resourceid']];
							}
							break;
					}
				}
			}
			unset($screenItem);
		}
		unset($screen);
	}

	/**
	 * Change map elements real database selement id and icons ids to unique field references.
	 *
	 * @param array $exportMaps
	 */
	protected function prepareMapExport(array &$exportMaps) {
		$sysmapIds = $groupIds = $hostIds = $triggerIds = $imageIds = [];

		// gather element ids that must be substituted
		foreach ($exportMaps as $sysmap) {
			foreach ($sysmap['selements'] as $selement) {
				switch ($selement['elementtype']) {
					case SYSMAP_ELEMENT_TYPE_MAP:
						$sysmapIds[$selement['elementid']] = $selement['elementid'];
						break;

					case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
						$groupIds[$selement['elementid']] = $selement['elementid'];
						break;

					case SYSMAP_ELEMENT_TYPE_HOST:
						$hostIds[$selement['elementid']] = $selement['elementid'];
						break;

					case SYSMAP_ELEMENT_TYPE_TRIGGER:
						$triggerIds[$selement['elementid']] = $selement['elementid'];
						break;
				}

				if ($selement['iconid_off'] > 0) {
					$imageIds[$selement['iconid_off']] = $selement['iconid_off'];
				}
				if ($selement['iconid_on'] > 0) {
					$imageIds[$selement['iconid_on']] = $selement['iconid_on'];
				}
				if ($selement['iconid_disabled'] > 0) {
					$imageIds[$selement['iconid_disabled']] = $selement['iconid_disabled'];
				}
				if ($selement['iconid_maintenance'] > 0) {
					$imageIds[$selement['iconid_maintenance']] = $selement['iconid_maintenance'];
				}
			}

			if ($sysmap['backgroundid'] > 0) {
				$imageIds[$sysmap['backgroundid']] = $sysmap['backgroundid'];
			}

			foreach ($sysmap['links'] as $link) {
				foreach ($link['linktriggers'] as $linktrigger) {
					$triggerIds[$linktrigger['triggerid']] = $linktrigger['triggerid'];
				}
			}
		}

		$sysmaps = $this->getMapsReferences($sysmapIds);
		$groups = $this->getGroupsReferences($groupIds);
		$hosts = $this->getHostsReferences($hostIds);
		$triggers = $this->getTriggersReferences($triggerIds);
		$images = $this->getImagesReferences($imageIds);

		foreach ($exportMaps as &$sysmap) {
			if (!empty($sysmap['iconmap'])) {
				$sysmap['iconmap'] = ['name' => $sysmap['iconmap']['name']];
			}

			foreach ($sysmap['urls'] as $unum => $url) {
				unset($sysmap['urls'][$unum]['sysmapurlid']);
			}

			$sysmap['backgroundid'] = ($sysmap['backgroundid'] > 0) ? $images[$sysmap['backgroundid']] : [];

			foreach ($sysmap['selements'] as &$selement) {
				switch ($selement['elementtype']) {
					case SYSMAP_ELEMENT_TYPE_MAP:
						$selement['elementid'] = $sysmaps[$selement['elementid']];
						break;
					case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
						$selement['elementid'] = $groups[$selement['elementid']];
						break;
					case SYSMAP_ELEMENT_TYPE_HOST:
						$selement['elementid'] = $hosts[$selement['elementid']];
						break;
					case SYSMAP_ELEMENT_TYPE_TRIGGER:
						$selement['elementid'] = $triggers[$selement['elementid']];
						break;
				}

				$selement['iconid_off'] = $selement['iconid_off'] > 0 ? $images[$selement['iconid_off']] : '';
				$selement['iconid_on'] = $selement['iconid_on'] > 0 ? $images[$selement['iconid_on']] : '';
				$selement['iconid_disabled'] = $selement['iconid_disabled'] > 0 ? $images[$selement['iconid_disabled']] : '';
				$selement['iconid_maintenance'] = $selement['iconid_maintenance'] > 0 ? $images[$selement['iconid_maintenance']] : '';
			}
			unset($selement);

			foreach ($sysmap['links'] as &$link) {
				foreach ($link['linktriggers'] as &$linktrigger) {
					$linktrigger['triggerid'] = $triggers[$linktrigger['triggerid']];
				}
				unset($linktrigger);
			}
			unset($link);
		}
		unset($sysmap);
	}

	/**
	 * Get groups references by group ids.
	 *
	 * @param array $groupIds
	 *
	 * @return array
	 */
	protected function getGroupsReferences(array $groupIds) {
		$ids = [];

		$groups = API::HostGroup()->get([
			'groupids' => $groupIds,
			'output' => ['name'],
			'preservekeys' => true
		]);

		foreach ($groups as $id => $group) {
			$ids[$id] = ['name' => $group['name']];
		}

		return $ids;
	}

	/**
	 * Get hosts references by host ids.
	 *
	 * @param array $hostIds
	 *
	 * @return array
	 */
	protected function getHostsReferences(array $hostIds) {
		$ids = [];

		$hosts = API::Host()->get([
			'hostids' => $hostIds,
			'output' => ['host'],
			'preservekeys' => true
		]);

		foreach ($hosts as $id => $host) {
			$ids[$id] = ['host' => $host['host']];
		}

		return $ids;
	}

	/**
	 * Get screens references by screen ids.
	 *
	 * @param array $screenIds
	 *
	 * @return array
	 */
	protected function getScreensReferences(array $screenIds) {
		$ids = [];

		$screens = API::Screen()->get([
			'screenids' => $screenIds,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		]);

		foreach ($screens as $id => $screen) {
			$ids[$id] = ['name' => $screen['name']];
		}

		return $ids;
	}

	/**
	 * Get maps references by map ids.
	 *
	 * @param array $mapIds
	 *
	 * @return array
	 */
	protected function getMapsReferences(array $mapIds) {
		$ids = [];

		$maps = API::Map()->get([
			'sysmapids' => $mapIds,
			'output' => ['name'],
			'preservekeys' => true
		]);

		foreach ($maps as $id => $map) {
			$ids[$id] = ['name' => $map['name']];
		}

		return $ids;
	}

	/**
	 * Get graphs references by graph ids.
	 *
	 * @param array $graphIds
	 *
	 * @return array
	 */
	protected function getGraphsReferences(array $graphIds) {
		$ids = [];

		$graphs = API::Graph()->get([
			'graphids' => $graphIds,
			'selectHosts' => ['host'],
			'output' => ['name'],
			'preservekeys' => true,
			'filter' => ['flags' => null]
		]);

		foreach ($graphs as $id => $graph) {
			$host = reset($graph['hosts']);

			$ids[$id] = [
				'name' => $graph['name'],
				'host' => $host['host']
			];
		}

		return $ids;
	}

	/**
	 * Get items references by item ids.
	 *
	 * @param array $itemIds
	 *
	 * @return array
	 */
	protected function getItemsReferences(array $itemIds) {
		$ids = [];

		$items = API::Item()->get([
			'itemids' => $itemIds,
			'output' => ['key_'],
			'selectHosts' => ['host'],
			'webitems' => true,
			'preservekeys' => true,
			'filter' => ['flags' => null]
		]);

		foreach ($items as $id => $item) {
			$host = reset($item['hosts']);

			$ids[$id] = [
				'key' => $item['key_'],
				'host' => $host['host']
			];
		}

		return $ids;
	}

	/**
	 * Get triggers references by trigger ids.
	 *
	 * @param array $triggerIds
	 *
	 * @return array
	 */
	protected function getTriggersReferences(array $triggerIds) {
		$ids = [];

		$triggers = API::Trigger()->get([
			'triggerids' => $triggerIds,
			'output' => ['description', 'expression'],
			'preservekeys' => true
		]);

		foreach ($triggers as $id => $trigger) {
			$ids[$id] = [
				'description' => $trigger['description'],
				'expression' => explode_exp($trigger['expression'])
			];
		}

		return $ids;
	}

	/**
	 * Get images references by image ids.
	 *
	 * @param array $imageIds
	 *
	 * @return array
	 */
	protected function getImagesReferences(array $imageIds) {
		$ids = [];

		$images = API::Image()->get([
			'output' => ['imageid', 'name'],
			'imageids' => $imageIds,
			'preservekeys' => true
		]);

		foreach ($images as $id => $image) {
			$ids[$id] = ['name' => $image['name']];
		}

		return $ids;
	}
}

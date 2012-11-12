<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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
	 * Constructor.
	 *
	 * @param array $options ids of elements that should be exported.
	 */
	public function __construct(array $options) {
		$this->options = array(
			'hosts' => array(),
			'templates' => array(),
			'groups' => array(),
			'screens' => array(),
			'images' => array(),
			'maps' => array()
		);
		$this->options = array_merge($this->options, $options);

		$this->data = array(
			'groups' => array(),
			'templates' => array(),
			'hosts' => array(),
			'triggers' => array(),
			'triggerPrototypes' => array(),
			'graphs' => array(),
			'graphPrototypes' => array(),
			'screens' => array(),
			'images' => array(),
			'maps' => array()
		);
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
		if ($this->options['groups']) {
			$this->gatherGroups();
		}

		if ($this->options['templates']) {
			$this->gatherTemplates();
		}

		if ($this->options['hosts']) {
			$this->gatherHosts();
		}

		if ($this->options['templates'] || $this->options['hosts']) {
			$this->gatherGraphs();
			$this->gathertriggers();
		}

		if ($this->options['screens']) {
			$this->gatherScreens();
		}

		if ($this->options['maps']) {
			$this->gatherMaps();
		}
	}

	/**
	 * Get groups for export from database.
	 */
	protected function gatherGroups() {
		$this->data['groups'] = API::HostGroup()->get(array(
			'groupids' => $this->options['groups'],
			'preservekeys' => true,
			'output' => API_OUTPUT_EXTEND
		));
	}

	/**
	 * Get templates for export from database.
	 */
	protected function gatherTemplates() {
		$templates = API::Template()->get(array(
			'templateids' => $this->options['templates'],
			'output' => array('host', 'name'),
			'selectMacros' => API_OUTPUT_EXTEND,
			'selectGroups' => API_OUTPUT_EXTEND,
			'selectParentTemplates' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));

		// merge host groups with all groups
		$templateGroups = array();
		foreach ($templates as &$template) {
			$templateGroups += zbx_toHash($template['groups'], 'groupid');

			$template['screens'] = array();
			$template['applications'] = array();
			$template['discoveryRules'] = array();
			$template['items'] = array();
		}
		unset($template);
		$this->data['groups'] += $templateGroups;

		// applications
		$applications = API::Application()->get(array(
			'hostids' => $this->options['templates'],
			'output' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'preservekeys' => true
		));

		foreach ($applications as $application) {
			if (!isset($templates[$application['hostid']]['applications'])) {
				$templates[$application['hostid']]['applications'] = array();
			}
			$templates[$application['hostid']]['applications'][] = $application;
		}

		// screens
		$screens = API::TemplateScreen()->get(array(
			'templateids' => $this->options['templates'],
			'selectScreenItems' => API_OUTPUT_EXTEND,
			'noInheritance' => true,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));
		$this->prepareScreenExport($screens);

		foreach ($screens as $screen) {
			if (!isset($templates[$screen['templateid']]['screens'])) {
				$templates[$screen['templateid']]['screens'] = array();
			}
			$templates[$screen['templateid']]['screens'][] = $screen;
		}

		$this->data['templates'] = $templates;

		$this->gatherTemplateItems();
		$this->gatherTemplateDiscoveryRules();
	}

	/**
	 * Get Hosts for export from database.
	 */
	protected function gatherHosts() {
		$hosts = API::Host()->get(array(
			'hostids' => $this->options['hosts'],
			'output' => array('proxy_hostid', 'host', 'status', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username',
				'ipmi_password', 'name'),
			'selectInventory' => true,
			'selectInterfaces' => array('interfaceid', 'main', 'type', 'useip', 'ip', 'dns', 'port'),
			'selectMacros' => API_OUTPUT_EXTEND,
			'selectGroups' => API_OUTPUT_EXTEND,
			'selectParentTemplates' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));

		// merge host groups with all groups
		$hostGroups = array();
		foreach ($hosts as &$host) {
			$hostGroups += zbx_toHash($host['groups'], 'groupid');
			$host['applications'] = array();
			$host['discoveryRules'] = array();
			$host['items'] = array();
		}
		unset($host);
		$this->data['groups'] += $hostGroups;

		// applications
		$applications = API::Application()->get(array(
			'hostids' => $this->options['hosts'],
			'output' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'preservekeys' => true
		));
		foreach ($applications as $application) {
			if (!isset($hosts[$application['hostid']]['applications'])) {
				$hosts[$application['hostid']]['applications'] = array();
			}
			$hosts[$application['hostid']]['applications'][] = $application;
		}

		// proxies
		$dbProxies = DBselect('SELECT h.hostid, h.host FROM hosts h WHERE '.
				DBcondition('h.hostid', zbx_objectValues($hosts, 'proxy_hostid')));
		$proxies = array();
		while ($proxy = DBfetch($dbProxies)) {
			$proxies[$proxy['hostid']] = $proxy['host'];
		}

		foreach ($hosts as &$host) {
			$host['proxy'] = $host['proxy_hostid'] ? array('name' => $proxies[$host['proxy_hostid']]) : null;
		}
		unset($host);


		$this->data['hosts'] = $hosts;

		$this->gatherHostItems();
		$this->gatherHostDiscoveryRules();
	}

	/**
	 * Get hosts items from database.
	 */
	protected function gatherHostItems() {
		$items = API::Item()->get(array(
			'hostids' => $this->options['hosts'],
			'output' => array('hostid', 'multiplier', 'type', 'snmp_community', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends',
				'status', 'value_type', 'trapper_hosts', 'units', 'delta', 'snmpv3_securityname', 'snmpv3_securitylevel',
				'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'formula', 'valuemapid', 'delay_flex', 'params',
				'ipmi_sensor', 'data_type', 'authtype', 'username', 'password', 'publickey', 'privatekey',
				'interfaceid', 'port', 'description', 'inventory_link', 'flags'),
			'selectApplications' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL)),
			'preservekeys' => true
		));
		$items = $this->prepareItems($items);
		foreach ($items as $item) {
			if (!isset($this->data['hosts'][$item['hostid']]['items'])) {
				$this->data['hosts'][$item['hostid']]['items'] = array();
			}

			$this->data['hosts'][$item['hostid']]['items'][] = $item;
		}
	}

	/**
	 * Get templates items from database.
	 */
	protected function gatherTemplateItems() {
		$items = API::Item()->get(array(
			'hostids' => $this->options['templates'],
			'output' => array('hostid', 'multiplier', 'type', 'snmp_community', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends',
				'status', 'value_type', 'trapper_hosts', 'units', 'delta', 'snmpv3_securityname', 'snmpv3_securitylevel',
				'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'formula', 'valuemapid', 'delay_flex', 'params',
				'ipmi_sensor', 'data_type', 'authtype', 'username', 'password', 'publickey', 'privatekey',
				'interfaceid', 'port', 'description', 'inventory_link', 'flags'),
			'selectApplications' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL)),
			'preservekeys' => true
		));

		$items = $this->prepareItems($items);

		foreach ($items as $item) {
			if (!isset($this->data['templates'][$item['hostid']]['items'])) {
				$this->data['templates'][$item['hostid']]['items'] = array();
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
		$valueMapIds = zbx_objectValues($items, 'valuemapid');
		$dbValueMaps = DBselect('SELECT vm.valuemapid, vm.name FROM valuemaps vm WHERE '.DBcondition('vm.valuemapid', $valueMapIds));
		$valueMapNames = array();
		while ($valueMap = DBfetch($dbValueMaps)) {
			$valueMapNames[$valueMap['valuemapid']] = $valueMap['name'];
		}

		foreach ($items as &$item) {
			$item['valuemap'] = array();
			if ($item['valuemapid']) {
				$item['valuemap'] = array('name' => $valueMapNames[$item['valuemapid']]);
			}
		}
		unset($item);

		return $items;
	}

	/**
	 * Get hosts discovery rules from database.
	 */
	protected function gatherHostDiscoveryRules() {
		$items = API::DiscoveryRule()->get(array(
			'hostids' => $this->options['hosts'],
			'output' => array('itemid', 'hostid', 'type', 'snmp_community', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends',
				'status', 'value_type', 'trapper_hosts', 'units', 'delta', 'snmpv3_securityname', 'snmpv3_securitylevel',
				'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'formula', 'valuemapid', 'delay_flex', 'params',
				'ipmi_sensor', 'data_type', 'authtype', 'username', 'password', 'publickey', 'privatekey',
				'interfaceid', 'port', 'description', 'inventory_link', 'flags', 'filter', 'lifetime'),
			'inherited' => false,
			'preservekeys' => true
		));

		$items = $this->prepareDiscoveryRules($items);

		foreach ($items as $item) {
			if (!isset($this->data['hosts'][$item['hostid']]['items'])) {
				$this->data['hosts'][$item['hostid']]['discoveryRules'] = array();
			}
			$this->data['hosts'][$item['hostid']]['discoveryRules'][] = $item;
		}
	}

	/**
	 * Get templates discovery rules from database.
	 */
	protected function gatherTemplateDiscoveryRules() {
		$items = API::DiscoveryRule()->get(array(
			'hostids' => $this->options['templates'],
			'output' => array('itemid', 'hostid', 'type', 'snmp_community', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends',
				'status', 'value_type', 'trapper_hosts', 'units', 'delta', 'snmpv3_securityname', 'snmpv3_securitylevel',
				'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'formula', 'valuemapid', 'delay_flex', 'params',
				'ipmi_sensor', 'data_type', 'authtype', 'username', 'password', 'publickey', 'privatekey',
				'interfaceid', 'port', 'description', 'inventory_link', 'flags', 'filter', 'lifetime'),
			'inherited' => false,
			'preservekeys' => true
		));

		$items = $this->prepareDiscoveryRules($items);

		foreach ($items as $item) {
			if (!isset($this->data['templates'][$item['hostid']]['discoveryRules'])) {
				$this->data['templates'][$item['hostid']]['discoveryRules'] = array();
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
			$item['itemPrototypes'] = array();
			$item['graphPrototypes'] = array();
			$item['triggerPrototypes'] = array();
		}
		unset($item);

		// gather item prototypes
		$prototypes = API::ItemPrototype()->get(array(
			'discoveryids' => zbx_objectValues($items, 'itemid'),
			'output' => array('hostid', 'multiplier', 'type', 'snmp_community', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends',
				'status', 'value_type', 'trapper_hosts', 'units', 'delta', 'snmpv3_securityname', 'snmpv3_securitylevel',
				'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'formula', 'valuemapid', 'delay_flex', 'params',
				'ipmi_sensor', 'data_type', 'authtype', 'username', 'password', 'publickey', 'privatekey',
				'interfaceid', 'port', 'description', 'inventory_link', 'flags'),
			'selectApplications' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'preservekeys' => true
		));

		// gather value maps
		$valueMapIds = zbx_objectValues($prototypes, 'valuemapid');
		$DbValueMaps = DBselect('SELECT vm.valuemapid, vm.name FROM valuemaps vm WHERE '.DBcondition('vm.valuemapid', $valueMapIds));
		$valueMaps = array();
		while ($valueMap = DBfetch($DbValueMaps)) {
			$valueMaps[$valueMap['valuemapid']] = $valueMap['name'];
		}

		foreach ($prototypes as $prototype) {
			$prototype['valuemap'] = array();
			if ($prototype['valuemapid']) {
				$prototype['valuemap']['name'] = $valueMaps[$prototype['valuemapid']];
			}

			$items[$prototype['parent_itemid']]['itemPrototypes'][] = $prototype;
		}

		// gather graph prototypes
		$graphs = API::GraphPrototype()->get(array(
			'discoveryids' => zbx_objectValues($items, 'itemid'),
			'selectDiscoveryRule' => API_OUTPUT_EXTEND,
			'selectGraphItems' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'preservekeys' => true
		));
		$graphs = $this->prepareGraphs($graphs);
		foreach ($graphs as $graph) {
			$items[$graph['discoveryRule']['itemid']]['graphPrototypes'][] = $graph;
		}

		// gather trigger prototypes
		$triggers = API::TriggerPrototype()->get(array(
			'discoveryids' => zbx_objectValues($items, 'itemid'),
			'output' => API_OUTPUT_EXTEND,
			'selectDiscoveryRule' => API_OUTPUT_EXTEND,
			'selectItems' => array('flags', 'type'),
			'inherited' => false,
			'preservekeys' => true,
			'expandData' => true
		));

		foreach($triggers as $trigger){
			foreach ($trigger['items'] as $item) {
				if ($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED || $item['type'] == ITEM_TYPE_HTTPTEST) {
					continue 2;
				}
			}

			$trigger['expression'] = explode_exp($trigger['expression']);
			$items[$trigger['discoveryRule']['itemid']]['triggerPrototypes'][] = $trigger;
		}

		return $items;
	}

	/**
	 * Get graphs for export from database.
	 */
	protected function gatherGraphs() {
		$hostIds = array_merge($this->options['hosts'], $this->options['templates']);

		$graphs = API::Graph()->get(array(
			'hostids' => $hostIds,
			'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL)),
			'selectGraphItems' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));

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
		$graphItemIds = array();
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

		$options = array(
			'itemids' => $graphItemIds,
			'output' => array('key_', 'flags', 'type'),
			'webitems' => true,
			'selectHosts' => array('host'),
			'preservekeys' => true
		);
		$graphItems = API::Item()->get($options);
		$graphItems += API::ItemPrototype()->get($options);

		foreach ($graphs as $gnum => $graph) {
			if ($graph['ymin_itemid'] && isset($graphItems[$graph['ymin_itemid']])) {
				$axisItem = $graphItems[$graph['ymin_itemid']];
				// unset lld and web graphs
				if ($axisItem['flags'] == ZBX_FLAG_DISCOVERY_CREATED || $axisItem['type'] == ITEM_TYPE_HTTPTEST) {
					unset($graphs[$gnum]);
					continue;
				}

				$axisItemHost = reset($axisItem['hosts']);
				$graphs[$gnum]['ymin_itemid'] = array(
					'host' => $axisItemHost['host'],
					'key' => $axisItem['key_']
				);
			}
			if ($graph['ymax_itemid'] && isset($graphItems[$graph['ymax_itemid']])) {
				$axisItem = $graphItems[$graph['ymax_itemid']];
				// unset lld and web graphs
				if ($axisItem['flags'] == ZBX_FLAG_DISCOVERY_CREATED || $axisItem['type'] == ITEM_TYPE_HTTPTEST) {
					unset($graphs[$gnum]);
					continue;
				}
				$axisItemHost = reset($axisItem['hosts']);
				$graphs[$gnum]['ymax_itemid'] = array(
					'host' => $axisItemHost['host'],
					'key' => $axisItem['key_']
				);
			}

			foreach ($graph['gitems'] as $ginum => $gItem) {
				$item = $graphItems[$gItem['itemid']];

				// unset lld and web graphs
				if ($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED || $item['type'] == ITEM_TYPE_HTTPTEST) {
					unset($graphs[$gnum]);
					continue 2;
				}
				$itemHost = reset($item['hosts']);
				$graphs[$gnum]['gitems'][$ginum]['itemid'] = array(
					'host' => $itemHost['host'],
					'key' => $item['key_']
				);
			}
		}

		return $graphs;
	}

	/**
	 * Get triggers for export from database.
	 */
	protected function gatherTriggers() {
		$hostIds = array_merge($this->options['hosts'], $this->options['templates']);

		$triggers = API::Trigger()->get(array(
			'hostids' => $hostIds,
			'output' => API_OUTPUT_EXTEND,
			'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL)),
			'selectDependencies' => API_OUTPUT_EXTEND,
			'selectItems' => array('flags', 'type'),
			'inherited' => false,
			'preservekeys' => true,
			'expandData' => true
		));

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
	 */
	protected function gatherMaps() {
		$sysmaps = API::Map()->get(array(
			'sysmapids' => $this->options['maps'],
			'selectSelements' => API_OUTPUT_EXTEND,
			'selectLinks' => API_OUTPUT_EXTEND,
			'selectIconMap' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));
		$this->prepareMapExport($sysmaps);
		$this->data['maps'] = $sysmaps;

		$images = API::Image()->get(array(
			'sysmapids' => zbx_objectValues($sysmaps, 'sysmapid'),
			'output' => API_OUTPUT_EXTEND,
			'select_image' => true,
			'preservekeys' => true
		));
		foreach ($images as &$image) {
			$image = array(
				'name' => $image['name'],
				'imagetype' => $image['imagetype'],
				'encodedImage' => $image['image'],
			);
		}
		unset($image);

		$this->data['images'] = $images;
	}

	/**
	 * Get screens for export from database.
	 */
	protected function gatherScreens() {
		$screens = API::Screen()->get(array(
			'screenids' => $this->options['screens'],
			'selectScreenItems' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND
		));

		$this->prepareScreenExport($screens);
		$this->data['screens'] = $screens;
	}

	/**
	 * Change screen elements real database resource id to unique field references.
	 *
	 * @param array $exportScreens
	 */
	protected function prepareScreenExport(array &$exportScreens) {
		$screenIds = array();
		$sysmapIds = array();
		$groupIds = array();
		$hostIds = array();
		$graphIds = array();
		$itemIds = array();

		// gather element ids that must be substituted
		foreach ($exportScreens as $screen) {
			foreach ($screen['screenitems'] as $screenItem) {
				if ($screenItem['resourceid'] != 0) {
					switch ($screenItem['resourcetype']) {
						case SCREEN_RESOURCE_HOSTS_INFO:
							// fall through
						case SCREEN_RESOURCE_TRIGGERS_INFO:
							// fall through
						case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
							// fall through
						case SCREEN_RESOURCE_DATA_OVERVIEW:
							// fall through
						case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
							$groupIds[$screenItem['resourceid']] = $screenItem['resourceid'];
							break;

						case SCREEN_RESOURCE_HOST_TRIGGERS:
							$hostIds[$screenItem['resourceid']] = $screenItem['resourceid'];
							break;

						case SCREEN_RESOURCE_GRAPH:
							$graphIds[$screenItem['resourceid']] = $screenItem['resourceid'];
							break;

						case SCREEN_RESOURCE_SIMPLE_GRAPH:
							// fall through
						case SCREEN_RESOURCE_PLAIN_TEXT:
							$itemIds[$screenItem['resourceid']] = $screenItem['resourceid'];
							break;

						case SCREEN_RESOURCE_MAP:
							$sysmapIds[$screenItem['resourceid']] = $screenItem['resourceid'];
							break;

						case SCREEN_RESOURCE_SCREEN:
							$screenIds[$screenItem['resourceid']] = $screenItem['resourceid'];
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

			foreach	($screen['screenitems'] as &$screenItem) {
				if ($screenItem['resourceid'] != 0) {
					switch ($screenItem['resourcetype']) {
						case SCREEN_RESOURCE_HOSTS_INFO:
							// fall through
						case SCREEN_RESOURCE_TRIGGERS_INFO:
							// fall through
						case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
							// fall through
						case SCREEN_RESOURCE_DATA_OVERVIEW:
							// fall through
						case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
							$screenItem['resourceid'] = $groups[$screenItem['resourceid']];
							break;

						case SCREEN_RESOURCE_HOST_TRIGGERS:
							$screenItem['resourceid'] = $hosts[$screenItem['resourceid']];
							break;

						case SCREEN_RESOURCE_GRAPH:
							$screenItem['resourceid'] = $graphs[$screenItem['resourceid']];
							break;

						case SCREEN_RESOURCE_SIMPLE_GRAPH:
							// fall through
						case SCREEN_RESOURCE_PLAIN_TEXT:
							$screenItem['resourceid'] = $items[$screenItem['resourceid']];
							break;

						case SCREEN_RESOURCE_MAP:
							$screenItem['resourceid'] = $sysmaps[$screenItem['resourceid']];
							break;

						case SCREEN_RESOURCE_SCREEN:
							$screenItem['resourceid'] = $screens[$screenItem['resourceid']];
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
		$sysmapIds = array();
		$groupIds = array();
		$hostIds = array();
		$triggerIds = array();
		$imageIds = array();

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
				$sysmap['iconmap'] = array('name' => $sysmap['iconmap']['name']);
			}

			foreach ($sysmap['urls'] as $unum => $url) {
				unset($sysmap['urls'][$unum]['sysmapurlid']);
			}

			$sysmap['backgroundid'] = ($sysmap['backgroundid'] > 0) ? $images[$sysmap['backgroundid']] : array();

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
		$idents = array();
		$groups = API::HostGroup()->get(array(
			'groupids' => $groupIds,
			'output' => array('name'),
			'nodeids' => get_current_nodeid(true),
			'preservekeys' => true
		));
		foreach ($groups as $id => $group) {
			$idents[$id] = array('name' => $group['name']);
		}

		return $idents;
	}

	/**
	 * Get hosts references by host ids.
	 *
	 * @param array $hostIds
	 *
	 * @return array
	 */
	protected function getHostsReferences(array $hostIds) {
		$idents = array();
		$hosts = API::Host()->get(array(
			'hostids' => $hostIds,
			'output' => array('host'),
			'nodeids' => get_current_nodeid(true),
			'preservekeys' => true
		));
		foreach ($hosts as $id => $host) {
			$idents[$id] = array('host' => $host['host']);
		}

		return $idents;
	}

	/**
	 * Get screens references by screen ids.
	 *
	 * @param array $screenIds
	 *
	 * @return array
	 */
	protected function getScreensReferences(array $screenIds) {
		$idents = array();
		$screens = API::Screen()->get(array(
			'screenids' => $screenIds,
			'output' => API_OUTPUT_EXTEND,
			'nodeids' => get_current_nodeid(true),
			'preservekeys' => true
		));
		foreach ($screens as $id => $screen) {
			$idents[$id] = array('name' => $screen['name']);
		}

		return $idents;
	}

	/**
	 * Get maps references by map ids.
	 *
	 * @param array $mapIds
	 *
	 * @return array
	 */
	protected function getMapsReferences(array $mapIds) {
		$idents = array();
		$maps = API::Map()->get(array(
			'sysmapids' => $mapIds,
			'output' => array('name'),
			'nodeids' => get_current_nodeid(true),
			'preservekeys' => true
		));
		foreach ($maps as $id => $map) {
			$idents[$id] = array('name' => $map['name']);
		}

		return $idents;
	}

	/**
	 * Get graphs references by graph ids.
	 *
	 * @param array $graphIds
	 *
	 * @return array
	 */
	protected function getGraphsReferences(array $graphIds) {
		$idents = array();
		$graphs = API::Graph()->get(array(
			'graphids' => $graphIds,
			'selectHosts' => array('host'),
			'output' => array('name'),
			'nodeids' => get_current_nodeid(true),
			'preservekeys' => true
		));
		foreach ($graphs as $id => $graph) {
			$host = reset($graph['hosts']);
			$idents[$id] = array(
				'name' => $graph['name'],
				'host' => $host['host']
			);
		}

		return $idents;
	}

	/**
	 * Get items references by item ids.
	 *
	 * @param array $itemIds
	 *
	 * @return array
	 */
	protected function getItemsReferences(array $itemIds) {
		$idents = array();
		$options = array(
			'itemids' => $itemIds,
			'output' => array('key_'),
			'selectHosts' => array('host'),
			'nodeids' => get_current_nodeid(true),
			'webitems' => true,
			'preservekeys' => true
		);
		$items = API::Item()->get($options);
		$items += API::ItemPrototype()->get($options);
		foreach ($items as $id => $item) {
			$host = reset($item['hosts']);
			$idents[$id] = array(
				'key' => $item['key_'],
				'host' => $host['host']
			);
		}

		return $idents;
	}

	/**
	 * Get triggers references by trigger ids.
	 *
	 * @param array $triggerIds
	 *
	 * @return array
	 */
	protected function getTriggersReferences(array $triggerIds) {
		$idents = array();
		$triggers = API::Trigger()->get(array(
			'triggerids' => $triggerIds,
			'output' => array('description', 'expression'),
			'nodeids' => get_current_nodeid(true),
			'preservekeys' => true
		));
		foreach ($triggers as $id => $trigger) {
			$idents[$id] = array(
				'description' => $trigger['description'],
				'expression' => explode_exp($trigger['expression'])
			);
		}

		return $idents;
	}

	/**
	 * Get images references by image ids.
	 *
	 * @param array $imageIds
	 *
	 * @return array
	 */
	protected function getImagesReferences(array $imageIds) {
		$idents = array();
		$images = API::Image()->get(array(
			'imageids' => $imageIds,
			'output' => API_OUTPUT_EXTEND,
			'nodeids' => get_current_nodeid(true),
			'preservekeys' => true
		));
		foreach ($images as $id => $image) {
			$idents[$id] = array('name' => $image['name']);
		}

		return $idents;
	}
}

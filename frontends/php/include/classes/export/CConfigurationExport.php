<?php

class CConfigurationExport {

	/**
	 * @var CExportWriter
	 */
	protected $writer;

	/**
	 * @var CConfigurationExportBuilder
	 */
	protected $builder;

	protected $data;


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
		$this->gatherData();
	}

	public function setWriter(CExportWriter $writer) {
		$this->writer = $writer;
	}

	public function setBuilder(CConfigurationExportBuilder $builder) {
		$this->builder = $builder;
	}

	public function export() {
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

	protected function gatherGroups() {
		$this->data['groups'] = API::HostGroup()->get(array(
			'hostids' => $this->options['groups'],
			'preservekeys' => true,
			'output' => API_OUTPUT_EXTEND
		));
	}

	protected function gatherTemplates() {
		$templates = API::Template()->get(array(
			'templateids' => $this->options['templates'],
			'output' => array('host', 'name'),
			'selectMacros' => API_OUTPUT_EXTEND,
			'selectGroups' => API_OUTPUT_EXTEND,
			'selectParentTemplates' => API_OUTPUT_EXTEND,
			'selectScreens' => API_OUTPUT_REFER,
			'preservekeys' => true
		));

		// merge host groups with all groups
		$templateGroups = $templateScreenIds = array();
		foreach ($templates as &$template) {
			$templateGroups += zbx_toHash($template['groups'], 'groupid');
			$templateScreenIds = array_merge($templateScreenIds, zbx_objectValues($template['screens'], 'screenid'));
			unset($template['screens']);
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
			'screenids' => $templateScreenIds,
			'selectScreenItems' => API_OUTPUT_EXTEND,
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
		foreach ($hosts as $host) {
			$hostGroups += zbx_toHash($host['groups'], 'groupid');
		}
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

		$this->data['hosts'] = $hosts;

		$this->gatherHostItems();
		$this->gatherHostDiscoveryRules();
	}

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

	protected function prepareItems(array $items) {
		// gather value maps
		$valueMapIds = zbx_objectValues($items, 'valuemapid');
		$DbValueMaps = DBselect('SELECT vm.valuemapid, vm.name FROM valuemaps vm WHERE '.DBcondition('vm.valuemapid', $valueMapIds));
		$valueMaps = array();
		while ($valueMap = DBfetch($DbValueMaps)) {
			$valueMaps[$valueMap['valuemapid']] = array('name' => $valueMap['name']);
		}

		foreach ($items as $item) {
			if ($item['valuemapid']) {
				$item['valuemapid'] = $valueMaps[$item['valuemapid']];
			}
		}

		return $items;
	}

	protected function gatherHostDiscoveryRules() {
		$items = API::DiscoveryRule()->get(array(
			'hostids' => $this->options['hosts'],
			'output' => array('itemid', 'hostid', 'multiplier', 'type', 'snmp_community', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends',
				'status', 'value_type', 'trapper_hosts', 'units', 'delta', 'snmpv3_securityname', 'snmpv3_securitylevel',
				'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'formula', 'valuemapid', 'delay_flex', 'params',
				'ipmi_sensor', 'data_type', 'authtype', 'username', 'password', 'publickey', 'privatekey',
				'interfaceid', 'port', 'description', 'inventory_link', 'flags'),
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

	protected function gatherTemplateDiscoveryRules() {
		$items = API::DiscoveryRule()->get(array(
			'hostids' => $this->options['templates'],
			'output' => array('itemid', 'hostid', 'multiplier', 'type', 'snmp_community', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends',
				'status', 'value_type', 'trapper_hosts', 'units', 'delta', 'snmpv3_securityname', 'snmpv3_securitylevel',
				'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'formula', 'valuemapid', 'delay_flex', 'params',
				'ipmi_sensor', 'data_type', 'authtype', 'username', 'password', 'publickey', 'privatekey',
				'interfaceid', 'port', 'description', 'inventory_link', 'flags'),
			'inherited' => false,
			'preservekeys' => true
		));

		$items = $this->prepareDiscoveryRules($items);

		foreach ($items as $item) {
			if (!isset($this->data['templates'][$item['hostid']]['items'])) {
				$this->data['templates'][$item['hostid']]['discoveryRules'] = array();
			}
			$this->data['templates'][$item['hostid']]['discoveryRules'][] = $item;
		}
	}

	protected function prepareDiscoveryRules(array $items) {
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
			$valueMaps[$valueMap['valuemapid']] = array('name' => $valueMap['name']);
		}

		foreach ($prototypes as $prototype) {
			if (!isset($hosts[$prototype['hostid']]['items'])) {
				$hosts[$prototype['hostid']]['items'] = array();
				$hosts[$prototype['hostid']]['discoveryRules'] = array();
				$hosts[$prototype['hostid']]['itemPrototypes'] = array();
			}

			if ($prototype['valuemapid']) {
				$prototype['valuemapid'] = $valueMaps[$prototype['valuemapid']];
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
		$graphItems = API::Item()->get(array(
			'itemids' => $graphItemIds,
			'output' => array('key_', 'flags'),
			'selectHosts' => array('host'),
			'preservekeys' => true
		));

		foreach ($graphs as $gnum => $graph) {
			if ($graph['ymin_itemid']) {
				$axisItem = $graphItems[$graph['ymin_itemid']];
				// unset lld dependeent graphs
				if($axisItem['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
					unset($graphs[$gnum]);
					continue;
				}

				$axisItemHost = reset($axisItem['hosts']);
				$graph['ymin_itemid'] = array(
					'host' => $axisItemHost['host'],
					'key' => $axisItem['key_']
				);
			}
			if ($graph['ymax_itemid']) {
				$axisItem = $graphItems[$graph['ymax_itemid']];
				// unset lld dependeent graphs
				if($axisItem['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
					unset($graphs[$gnum]);
					continue;
				}
				$axisItemHost = reset($axisItem['hosts']);
				$graph['ymax_itemid'] = array(
					'host' => $axisItemHost['host'],
					'key' => $axisItem['key_']
				);
			}

			foreach ($graph['gitems'] as $ginum => $gItem) {
				$item = $graphItems[$gItem['itemid']];

				if($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
					unset($graphs[$gnum]);
					continue 2;
				}
				$itemHost = reset($item['hosts']);
				$graph['gitems'][$ginum]['itemid'] = array(
					'host' => $itemHost['host'],
					'key' => $item['key_']
				);
			}
			$items[$graph['discoveryRule']['itemid']]['graphPrototypes'][] = $graph;
		}

		// gather trigger prortotypes
		$triggers = API::TriggerPrototype()->get(array(
			'discoveryids' => zbx_objectValues($items, 'itemid'),
			'output' => API_OUTPUT_EXTEND,
			'selectDiscoveryRule' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'preservekeys' => true,
			'expandData' => true
		));

		foreach($triggers as $trigger){
			$trigger['expression'] = explode_exp($trigger['expression']);
			$items[$trigger['discoveryRule']['itemid']]['triggerPrototypes'][] = $trigger;
		}

		return $items;
	}

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


		$graphItems = API::Item()->get(array(
			'itemids' => $graphItemIds,
			'output' => array('key_', 'flags'),
			'selectHosts' => array('host'),
			'preservekeys' => true
		));

		foreach ($graphs as $gnum => $graph) {
			if ($graph['ymin_itemid']) {
				$axisItem = $graphItems[$graph['ymin_itemid']];
				// unset lld dependeent graphs
				if($axisItem['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
					unset($graphs[$gnum]);
					continue;
				}

				$axisItemHost = reset($axisItem['hosts']);
				$graph['ymin_itemid'] = array(
					'host' => $axisItemHost['host'],
					'key' => $axisItem['key_']
				);
			}
			if ($graph['ymax_itemid']) {
				$axisItem = $graphItems[$graph['ymax_itemid']];
				// unset lld dependeent graphs
				if($axisItem['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
					unset($graphs[$gnum]);
					continue;
				}
				$axisItemHost = reset($axisItem['hosts']);
				$graph['ymax_itemid'] = array(
					'host' => $axisItemHost['host'],
					'key' => $axisItem['key_']
				);
			}

			foreach ($graph['gitems'] as $ginum => $gItem) {
				$item = $graphItems[$gItem['itemid']];

				if($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
					unset($graphs[$gnum]);
					continue 2;
				}
				$itemHost = reset($item['hosts']);
				$graph['gitems'][$ginum]['itemid'] = array(
					'host' => $itemHost['host'],
					'key' => $item['key_']
				);
			}

			$this->data['graphs'][] = $graph;
		}

	}

	protected function gatherTriggers() {
		$hostIds = array_merge($this->options['hosts'], $this->options['templates']);

		$triggers = API::Trigger()->get(array(
			'hostids' => $hostIds,
			'output' => API_OUTPUT_EXTEND,
			'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL)),
			'selectDependencies' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'preservekeys' => true,
			'expandData' => true
		));

		foreach($triggers as $trigger){
			$trigger['expression'] = explode_exp($trigger['expression']);

			foreach ($trigger['dependencies'] as &$dependency) {
				$dependency['expression'] = explode_exp($dependency['expression']);
			}
			unset($dependency);

			$this->data['triggers'][] = $trigger;
		}
	}

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
	 * Format screens data for export.
	 * @todo It's copy of old prepareScreenExport function, should be refactored
	 *
	 * @param array $exportScreens
	 */
	protected function prepareScreenExport(array &$exportScreens) {
		$screens = array();
		$sysmaps = array();
		$hostgroups = array();
		$hosts = array();
		$graphs = array();
		$items = array();

		foreach ($exportScreens as $screen) {
			$screenItems = separateScreenElements($screen);

			$screens = array_merge($screens, zbx_objectValues($screenItems['screens'], 'resourceid'));
			$sysmaps = array_merge($sysmaps, zbx_objectValues($screenItems['sysmaps'], 'resourceid'));
			$hostgroups = array_merge($hostgroups, zbx_objectValues($screenItems['hostgroups'], 'resourceid'));
			$hosts = array_merge($hosts, zbx_objectValues($screenItems['hosts'], 'resourceid'));
			$graphs = array_merge($graphs, zbx_objectValues($screenItems['graphs'], 'resourceid'));
			$items = array_merge($items, zbx_objectValues($screenItems['items'], 'resourceid'));
		}

		$screens = screenIdents($screens);
		$sysmaps = sysmapIdents($sysmaps);
		$hostgroups = hostgroupIdents($hostgroups);
		$hosts = hostIdents($hosts);
		$graphs = graphIdents($graphs);
		$items = itemIdents($items);

		foreach ($exportScreens as &$screen) {
			unset($screen['screenid'], $screen['hostid']);

			foreach	($screen['screenitems'] as &$screenItem) {
				if ($screenItem['resourceid'] == 0) {
					continue;
				}

				switch ($screenItem['resourcetype']) {
					case SCREEN_RESOURCE_HOSTS_INFO:
					case SCREEN_RESOURCE_TRIGGERS_INFO:
					case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
					case SCREEN_RESOURCE_DATA_OVERVIEW:
					case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
						$screenItem['resourceid'] = $hostgroups[$screenItem['resourceid']];
						break;
					case SCREEN_RESOURCE_HOST_TRIGGERS:
						$screenItem['resourceid'] = $hosts[$screenItem['resourceid']];
						break;
					case SCREEN_RESOURCE_GRAPH:
						$screenItem['resourceid'] = $graphs[$screenItem['resourceid']];
						break;
					case SCREEN_RESOURCE_SIMPLE_GRAPH:
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
			unset($screenItem);
		}
		unset($screen);
	}

	/**
	 * Format maps data for export.
	 * @todo It's copy of old prepareMapExport function, should be refactored
	 *
	 * @param array $exportMaps
	 */
	protected function prepareMapExport(array &$exportMaps) {
		$sysmaps = array();
		$hostgroups = array();
		$hosts = array();
		$triggers = array();
		$images = array();

		foreach ($exportMaps as $sysmap) {
			$selements = separateMapElements($sysmap);

			$sysmaps += zbx_objectValues($selements['sysmaps'], 'elementid');
			$hostgroups += zbx_objectValues($selements['hostgroups'], 'elementid');
			$hosts += zbx_objectValues($selements['hosts'], 'elementid');
			$triggers += zbx_objectValues($selements['triggers'], 'elementid');
			$images += zbx_objectValues($selements['images'], 'elementid');

			foreach ($sysmap['selements'] as $selement) {
				if ($selement['iconid_off'] > 0) {
					$images[$selement['iconid_off']] = $selement['iconid_off'];
				}
				if ($selement['iconid_on'] > 0) {
					$images[$selement['iconid_on']] = $selement['iconid_on'];
				}
				if ($selement['iconid_disabled'] > 0) {
					$images[$selement['iconid_disabled']] = $selement['iconid_disabled'];
				}
				if ($selement['iconid_maintenance'] > 0) {
					$images[$selement['iconid_maintenance']] = $selement['iconid_maintenance'];
				}
			}

			$images[$sysmap['backgroundid']] = $sysmap['backgroundid'];

			foreach ($sysmap['links'] as $link) {
				foreach ($link['linktriggers'] as $linktrigger) {
					array_push($triggers, $linktrigger['triggerid']);
				}
			}
		}

		$sysmaps = sysmapIdents($sysmaps);
		$hostgroups = hostgroupIdents($hostgroups);
		$hosts = hostIdents($hosts);
		$triggers = triggerIdents($triggers);
		$images = imageIdents($images);

		foreach ($exportMaps as &$sysmap) {
			if (!empty($sysmap['iconmap'])) {
				$sysmap['iconmap'] = array('name' => $sysmap['iconmap']['name']);
			}

			foreach ($sysmap['urls'] as $unum => $url) {
				unset($sysmap['urls'][$unum]['sysmapurlid']);
			}

			$sysmap['backgroundid'] = ($sysmap['backgroundid'] > 0) ? $images[$sysmap['backgroundid']] : '';

			foreach ($sysmap['selements'] as &$selement) {
				switch ($selement['elementtype']) {
					case SYSMAP_ELEMENT_TYPE_MAP:
						$selement['elementid'] = $sysmaps[$selement['elementid']];
						break;
					case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
						$selement['elementid'] = $hostgroups[$selement['elementid']];
						break;
					case SYSMAP_ELEMENT_TYPE_HOST:
						$selement['elementid'] = $hosts[$selement['elementid']];
						break;
					case SYSMAP_ELEMENT_TYPE_TRIGGER:
						$selement['elementid'] = $triggers[$selement['elementid']];
						break;
					case SYSMAP_ELEMENT_TYPE_IMAGE:
					default:
						$selement['elementid'] = $images[$selement['elementid']];
				}

				$selement['iconid_off'] = ($selement['iconid_off'] > 0) ? $images[$selement['iconid_off']] : '';
				$selement['iconid_on'] = ($selement['iconid_on'] > 0) ? $images[$selement['iconid_on']] : '';
				$selement['iconid_disabled'] = ($selement['iconid_disabled'] > 0) ? $images[$selement['iconid_disabled']] : '';
				$selement['iconid_maintenance'] = ($selement['iconid_maintenance'] > 0) ? $images[$selement['iconid_maintenance']] : '';
			}
			unset($selement);

			foreach ($sysmap['links'] as &$link) {
				foreach ($link['linktriggers'] as &$linktrigger) {
					$linktrigger['triggerid'] = $triggers[$linktrigger['triggerid']];
				}
			}
			unset($linktrigger);
			unset($link);
		}
		unset($sysmap);
	}
}

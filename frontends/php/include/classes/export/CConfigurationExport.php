<?php

class CConfigurationExport {

	/**
	 * @var CExportWriter
	 */
	private $writer;

	/**
	 * @var CConfigurationExportBuilder
	 */
	private $builder;

	private $data;


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

	private function gatherData() {
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

	private function gatherGroups() {
		$this->data['groups'] = API::HostGroup()->get(array(
			'hostids' => $this->options['groups'],
			'preservekeys' => true,
			'output' => API_OUTPUT_EXTEND
		));
	}

	private function gatherTemplates() {
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


		// items
		$items = API::Item()->get(array(
			'hostids' => $this->options['templates'],
			'output' => array('hostid', 'multiplier', 'type', 'snmp_community', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends',
				'status', 'value_type', 'trapper_hosts', 'units', 'delta', 'snmpv3_securityname', 'snmpv3_securitylevel',
				'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'formula', 'valuemapid', 'delay_flex', 'params',
				'ipmi_sensor', 'data_type', 'authtype', 'username', 'password', 'publickey', 'privatekey',
				'interfaceid', 'port', 'description', 'inventory_link', 'flags'),
			'selectApplications' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY, ZBX_FLAG_DISCOVERY_CHILD)),
			'preservekeys' => true
		));

		foreach ($items as $item) {
			if (!isset($templates[$item['hostid']]['items'])) {
				$templates[$item['hostid']]['items'] = array();
				$templates[$item['hostid']]['discoveryRules'] = array();
				$templates[$item['hostid']]['itemPrototypes'] = array();
			}

			switch ($item['flags']) {
				case ZBX_FLAG_DISCOVERY_NORMAL:
					$templates[$item['hostid']]['items'][] = $item;
					break;

				case ZBX_FLAG_DISCOVERY:
					$templates[$item['hostid']]['discoveryRules'][] = $item;
					break;

				case ZBX_FLAG_DISCOVERY_CHILD:
					$templates[$item['hostid']]['itemPrototypes'][] = $item;
					break;

				default:
					throw new LogicException(sprintf('Incorrect item flag "%1$s".', $item['flags']));
			}
		}


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

		prepareScreenExport($screens);

		foreach ($screens as $screen) {
			if (!isset($templates[$screen['templateid']]['screens'])) {
				$templates[$screen['templateid']]['screens'] = array();
			}
			$templates[$screen['templateid']]['screens'][] = $screen;
		}

		$this->data['templates'] = $templates;
	}

	private function gatherHosts() {
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

	private function gatherHostItems() {
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

		// gather value maps
		$valueMapIds = zbx_objectValues($items, 'valuemapid');
		$DbValueMaps = DBselect('SELECT vm.valuemapid, vm.name FROM valuemaps vm WHERE '.DBcondition('vm.valuemapid', $valueMapIds));
		$valueMaps = array();
		while ($valueMap = DBfetch($DbValueMaps)) {
			$valueMaps[$valueMap['valuemapid']] = array('name' => $valueMap['name']);
		}

		foreach ($items as $item) {
			if (!isset($this->data['hosts'][$item['hostid']]['items'])) {
				$this->data['hosts'][$item['hostid']]['items'] = array();
			}

			if ($item['valuemapid']) {
				$item['valuemapid'] = $valueMaps[$item['valuemapid']];
			}

			$this->data['hosts'][$item['hostid']]['items'][] = $item;
		}
	}

	private function gatherHostDiscoveryRules() {
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

		foreach ($items as $item) {
			if (!isset($this->data['hosts'][$item['hostid']]['items'])) {
				$this->data['hosts'][$item['hostid']]['discoveryRules'] = array();
			}
			$this->data['hosts'][$item['hostid']]['discoveryRules'][] = $item;
		}
	}

	private function gatherGraphs() {
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

	private function gatherTriggers() {
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

	private function gatherMaps() {
		$sysmaps = API::Map()->get(array(
			'sysmapids' => $this->options['maps'],
			'selectSelements' => API_OUTPUT_EXTEND,
			'selectLinks' => API_OUTPUT_EXTEND,
			'selectIconMap' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		));
		prepareMapExport($sysmaps);
		$this->data['maps'] = $sysmaps;

		$images = API::Image()->get(array(
			'sysmapids' => zbx_objectValues($sysmaps, 'sysmapid'),
			'output' => API_OUTPUT_EXTEND,
			'select_image' => true,
			'preservekeys' => true
		));
		$images = prepareImageExport($images);
		$this->data['images'] = $images;

	}

	private function gatherScreens() {
		$screens = API::Screen()->get(array(
			'screenids' => $this->options['screens'],
			'selectScreenItems' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND
		));

		prepareScreenExport($screens);
		$this->data['screens'] = $screens;
	}

}

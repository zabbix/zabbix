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
		$this->builder->buildRoot();

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
		if ($this->data['triggerPrototypes']) {
			$this->builder->buildTriggerPrototypes($this->data['triggerPrototypes']);
		}
		if ($this->data['graphs']) {
			$this->builder->buildGraphs($this->data['graphs']);
		}
		if ($this->data['graphPrototypes']) {
			$this->builder->buildGraphPrototypes($this->data['graphPrototypes']);
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
		$params = array(
			'hostids' => $this->options['groups'],
			'preservekeys' => true,
			'output' => API_OUTPUT_EXTEND
		);
		$this->data['groups'] = API::HostGroup()->get($params);
	}

	private function gatherTemplates() {
		$params = array(
			'templateids' => $this->options['templates'],
			'output' => array('host', 'name'),
			'selectMacros' => API_OUTPUT_EXTEND,
			'selectGroups' => API_OUTPUT_EXTEND,
			'selectParentTemplates' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		);
		$templates = API::Template()->get($params);


		// merge host groups with all groups
		$templateGroups = array();
		foreach ($templates as $template) {
			$templateGroups += zbx_toHash($template['groups'], 'groupid');
		}
		$this->data['groups'] += $templateGroups;


		// items
		$params = array(
			'hostids' => $this->options['templates'],
			'output' => array('hostid', 'type', 'snmp_community', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends',
				'status', 'value_type', 'trapper_hosts', 'units', 'delta', 'snmpv3_securityname', 'snmpv3_securitylevel',
				'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'formula', 'valuemapid', 'delay_flex', 'params',
				'ipmi_sensor', 'data_type', 'authtype', 'username', 'password', 'publickey', 'privatekey',
				'interfaceid', 'port', 'description', 'inventory_link', 'flags'),
			'selectApplications' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY, ZBX_FLAG_DISCOVERY_CHILD)),
			'preservekeys' => true
		);
		$items = API::Item()->get($params);

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
		$params = array(
			'hostids' => $this->options['templates'],
			'output' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'preservekeys' => true
		);
		$applications = API::Application()->get($params);

		foreach ($applications as $application) {
			if (!isset($templates[$application['hostid']]['applications'])) {
				$templates[$application['hostid']]['applications'] = array();
			}
			$templates[$application['hostid']]['applications'][] = $application;
		}

		$this->data['templates'] = $templates;
	}

	private function gatherHosts() {
		$params = array(
			'hostids' => $this->options['hosts'],
			'output' => array('proxy_hostid', 'host', 'status', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username',
				'ipmi_password', 'ipmi_disable_until', 'ipmi_available', 'name'),
			'selectInventory' => true,
			'selectInterfaces' => array('interfaceid', 'main', 'type', 'useip', 'ip', 'dns', 'port'),
			'selectMacros' => API_OUTPUT_EXTEND,
			'selectGroups' => API_OUTPUT_EXTEND,
			'selectParentTemplates' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		);
		$hosts = API::Host()->get($params);


		// merge host groups with all groups
		$hostGroups = array();
		foreach ($hosts as $host) {
			$hostGroups += zbx_toHash($host['groups'], 'groupid');
		}
		$this->data['groups'] += $hostGroups;


		// items
		$params = array(
			'hostids' => $this->options['hosts'],
			'output' => array('hostid', 'type', 'snmp_community', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends',
				'status', 'value_type', 'trapper_hosts', 'units', 'delta', 'snmpv3_securityname', 'snmpv3_securitylevel',
				'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'formula', 'valuemapid', 'delay_flex', 'params',
				'ipmi_sensor', 'data_type', 'authtype', 'username', 'password', 'publickey', 'privatekey',
				'interfaceid', 'port', 'description', 'inventory_link', 'flags'),
			'selectApplications' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY, ZBX_FLAG_DISCOVERY_CHILD)),
			'preservekeys' => true
		);
		$items = API::Item()->get($params);

		foreach ($items as $item) {
			if (!isset($hosts[$item['hostid']]['items'])) {
				$hosts[$item['hostid']]['items'] = array();
				$hosts[$item['hostid']]['discoveryRules'] = array();
				$hosts[$item['hostid']]['itemPrototypes'] = array();
			}

			switch ($item['flags']) {
				case ZBX_FLAG_DISCOVERY_NORMAL:
					$hosts[$item['hostid']]['items'][] = $item;
					break;

				case ZBX_FLAG_DISCOVERY:
					$hosts[$item['hostid']]['discoveryRules'][] = $item;
					break;

				case ZBX_FLAG_DISCOVERY_CHILD:
					$hosts[$item['hostid']]['itemPrototypes'][] = $item;
					break;

				default:
					throw new LogicException(sprintf('Incorrect item flag "%1$s".', $item['flags']));
			}
		}


		// applications
		$params = array(
			'hostids' => $this->options['hosts'],
			'output' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'preservekeys' => true
		);
		$applications = API::Application()->get($params);

		foreach ($applications as $application) {
			if (!isset($hosts[$application['hostid']]['applications'])) {
				$hosts[$application['hostid']]['applications'] = array();
			}
			$hosts[$application['hostid']]['applications'][] = $application;
		}


		$this->data['hosts'] = $hosts;
	}

	private function gatherGraphs() {
		$hostIds = array_merge($this->options['hosts'], $this->options['templates']);

		$params = array(
			'hostids' => $hostIds,
			'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CHILD)),
			'selectGraphItems' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		);
		$graphs = API::Graph()->get($params);

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


		$params = array(
			'itemids' => $graphItemIds,
			'output' => array('key_', 'flags'),
			'selectHosts' => array('host'),
			'preservekeys' => true
		);
		$graphItems = API::Item()->get($params);

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

			switch ($graph['flags']) {
				case ZBX_FLAG_DISCOVERY_NORMAL:
					$this->data['graphs'][] = $graph;
					break;

				case ZBX_FLAG_DISCOVERY_CHILD:
					$this->data['graphPrototypes'][] = $graph;
					break;

				default:
					throw new LogicException(sprintf('Incorrect graph flag "%1$s".', $graph['flags']));
			}
		}

	}

	private function gatherTriggers() {
		$hostIds = array_merge($this->options['hosts'], $this->options['templates']);

		$params = array(
			'hostids' => $hostIds,
			'output' => API_OUTPUT_EXTEND,
			'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CHILD)),
			'selectDependencies' => API_OUTPUT_EXTEND,
			'inherited' => false,
			'preservekeys' => true,
			'expandData' => true
		);
		$triggers = API::Trigger()->get($params);

		foreach($triggers as $trigger){
			$trigger['expression'] = explode_exp($trigger['expression']);

			foreach ($trigger['dependencies'] as &$dependency) {
				$dependency['expression'] = explode_exp($dependency['expression']);
			}
			unset($dependency);

			switch ($trigger['flags']) {
				case ZBX_FLAG_DISCOVERY_NORMAL:
					$this->data['triggers'][] = $trigger;
					break;

				case ZBX_FLAG_DISCOVERY_CHILD:
					$this->data['triggerPrototypes'][] = $trigger;
					break;

				default:
					throw new LogicException(sprintf('Incorrect trigger flag "%1$s".', $trigger['flags']));
			}
		}
	}

	private function gatherMaps() {
		$options = array(
			'sysmapids' => $this->options['maps'],
			'selectSelements' => API_OUTPUT_EXTEND,
			'selectLinks' => API_OUTPUT_EXTEND,
			'selectIconMap' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		);
		$sysmaps = API::Map()->get($options);
		prepareMapExport($sysmaps);
		$this->data['maps'] = $sysmaps;

		$options = array(
			'sysmapids' => zbx_objectValues($sysmaps, 'sysmapid'),
			'output' => API_OUTPUT_EXTEND,
			'select_image' => true,
			'preservekeys' => true
		);
		$images = API::Image()->get($options);
		$images = prepareImageExport($images);
		$this->data['images'] = $images;

	}

	private function gatherScreens() {
		$options = array(
			'screenids' => $this->options['screens'],
			'selectScreenItems' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND
		);
		$screens = API::Screen()->get($options);

		prepareScreenExport($screens);
		$this->data['screens'] = $screens;
	}

}

<?php

class C20ImportFormatter extends CImportFormatter {

	public function getGroups() {
		if (!isset($this->data['groups'])) {
			return array();
		}
		return $this->data['groups'];
	}

	public function getTemplates() {
		if (!isset($this->data['templates'])) {
			return array();
		}
		$hostsData = array();

		foreach ($this->data['templates'] as $host) {
			if (empty($host['templates'])) {
				unset($host['templates']);
			}
			else {
				foreach ($host['templates'] as $tnum => $template) {
					$host['templates'][$tnum] = $this->renameData($template, array('template' => 'host'));
				}
				$host['templates'] = array_values($host['templates']);
			}

			$host['macros'] = array_values($host['macros']);

			$host = $this->renameData($host, array('template' => 'host'));

			$host['groups'] = array_values($host['groups']);


			$hostsData[] = ArrayHelper::getByKeys($host, array('groups', 'macros', 'templates', 'host', 'status', 'name'));
		}

		return $hostsData;
	}

	public function getHosts() {
		if (!isset($this->data['hosts'])) {
			return array();
		}
		$hostsData = array();

		foreach ($this->data['hosts'] as $host) {
			$host = $this->renameData($host, array('proxyid' => 'proxy_hostid'));

			foreach ($host['interfaces'] as $inum => $interface) {
				$host['interfaces'][$inum] = $this->renameData($interface, array('default' => 'main'));
			}
			$host['interfaces'] = array_values($host['interfaces']);


			if (empty($host['templates'])) {
				unset($host['templates']);
			}
			else {
				$host['templates'] = array_values($host['templates']);
			}

			$host['macros'] = array_values($host['macros']);

			$host['groups'] = array_values($host['groups']);

			if (!empty($host['inventory']) && isset($host['inventory']['inventory_mode'])) {
				$host['inventory_mode'] = $host['inventory']['inventory_mode'];
				unset($host['inventory']['inventory_mode']);
			}

			$hostsData[] = ArrayHelper::getByKeys($host, array('inventory', 'proxy_hostid', 'groups', 'templates', 'macros', 'interfaces',
				'host', 'status', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'name', 'inventory_mode'));
		}

		return $hostsData;
	}

	public function getApplications() {
		$applicationsData = array();

		if (isset($this->data['hosts'])) {
			foreach ($this->data['hosts'] as $host) {
				foreach ($host['applications'] as $application) {
					$applicationsData[$host['host']][$application['name']] = $application;
				}
			}
		}
		if (isset($this->data['templates'])) {
			foreach ($this->data['templates'] as $template) {
				foreach ($template['applications'] as $application) {
					$applicationsData[$template['template']][$application['name']] = $application;
				}
			}
		}

		return $applicationsData;
	}

	public function getItems() {
		$itemsData = array();

		if (isset($this->data['hosts'])) {
			foreach ($this->data['hosts'] as $host) {
				foreach ($host['items'] as $item) {
					$item = $this->renameItemFields($item);
					$itemsData[$host['host']][$item['key_']] = $item;
				}
			}
		}
		if (isset($this->data['templates'])) {
			foreach ($this->data['templates'] as $template) {
				foreach ($template['items'] as $item) {
					$item = $this->renameItemFields($item);
					$itemsData[$template['template']][$item['key_']] = $item;
				}
			}
		}

		return $itemsData;
	}

	public function getDiscoveryRules() {
		$discoveryRulesData = array();

		if (isset($this->data['hosts'])) {
			foreach ($this->data['hosts'] as $host) {
				foreach ($host['discovery_rules'] as $item) {
					$item = $this->renameItemFields($item);
					foreach ($item['item_prototypes'] as &$prototype) {
						$prototype = $this->renameItemFields($prototype);
					}
					unset($prototype);

					foreach ($item['trigger_prototypes'] as &$trigger) {
						$trigger = $this->renameData($trigger, array('description' => 'comments'));
						$trigger = $this->renameData($trigger, array(
							'name' => 'description',
							'severity' => 'priority'
						));
					}
					unset($trigger);

					$discoveryRulesData[$host['host']][$item['key_']] = $item;
				}
			}
		}

		if (isset($this->data['templates'])) {
			foreach ($this->data['templates'] as $template) {
				foreach ($template['discovery_rules'] as $item) {
					$item = $this->renameItemFields($item);
					foreach ($item['item_prototypes'] as &$prototype) {
						$prototype = $this->renameItemFields($prototype);
					}
					unset($prototype);

					foreach ($item['trigger_prototypes'] as &$trigger) {
						$trigger = $this->renameData($trigger, array('description' => 'comments'));
						$trigger = $this->renameData($trigger, array(
							'name' => 'description',
							'severity' => 'priority'
						));
					}
					unset($trigger);

					$discoveryRulesData[$template['host']][$item['key_']] = $item;
				}
			}
		}

		return $discoveryRulesData;
	}

	public function getGraphs() {
		if (!isset($this->data['graphs'])) {
			return array();
		}

		$graphsData = array();
		foreach ($this->data['graphs'] as $graph) {
			$graph = $this->renameData($graph, array(
				'type' => 'graphtype',
				'ymin_type_1' => 'ymin_type',
				'ymax_type_1' => 'ymax_type',
				'graph_items' => 'gitems'
			));

			$graph['gitems'] = array_values($graph['gitems']);

			$graphsData[] = $graph;
		}

		return $graphsData;
	}

	public function getTriggers() {
		if (!isset($this->data['triggers'])) {
			return array();
		}

		$triggersData = array();
		foreach ($this->data['triggers'] as $trigger) {
			$trigger = $this->renameData($trigger, array('description' => 'comments'));
			$trigger = $this->renameData($trigger, array(
				'name' => 'description',
				'severity' => 'priority'
			));

			$triggersData[] = $trigger;
		}

		return $triggersData;
	}

	public function getImages() {
		if (!isset($this->data['images'])) {
			return array();
		}
		foreach ($this->data['images'] as &$image) {
			$image = $this->renameData($image, array('encodedImage' => 'image'));
		}
		unset($image);
		return $this->data['images'];
	}

	public function getMaps() {
		if (!isset($this->data['maps'])) {
			return array();
		}
		return $this->data['maps'];
	}

	protected function renameItemFields(array $item) {
		$item = $this->renameData($item, array('key' => 'key_', 'allowed_hosts' => 'trapper_hosts'));
		return $item;
	}
}

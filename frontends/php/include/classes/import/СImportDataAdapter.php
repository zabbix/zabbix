<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


/**
 * Import formatter
 */
class СImportDataAdapter {

	/**
	 * @var array configuration import data
	 */
	protected $data;

	/**
	 * Object used for converting older import versions.
	 *
	 * @var CConverterChain
	 */
	protected $converterChain;

	/**
	 * Current import version.
	 *
	 * @var string
	 */
	protected $currentVersion;

	/**
	 * @param string            $currentVersion     current import version
	 * @param CConverterChain   $converterChain     object used for converting older import versions
	 */
	public function __construct($currentVersion, CConverterChain $converterChain) {
		$this->currentVersion = $currentVersion;
		$this->converterChain = $converterChain;
	}

	/**
	 * Set the data and initialize the adapter.
	 *
	 * @param array $data   import data
	 *
	 * @throws InvalidArgumentException     if the data is invalid
	 */
	public function load(array $data) {
		$version = $data['zabbix_export']['version'];

		if ($this->currentVersion != $version) {
			// check if this import version is supported
			if (!$this->converterChain->hasConverter($version)) {
				throw new InvalidArgumentException(_s('Unsupported import version "%1$s"', $version));
			}

			$data = $this->converterChain->convert($data, $version);
		}

		$this->data = $data['zabbix_export'];
	}

	/**
	 * Get groups from the imported data.
	 *
	 * @return array
	 */
	public function getGroups() {
		if (!isset($this->data['groups'])) {
			return array();
		}

		return array_values($this->data['groups']);
	}

	/**
	 * Get templates from the imported data.
	 *
	 * @return array
	 */
	public function getTemplates() {
		$templatesData = array();

		if (!empty($this->data['templates'])) {
			foreach ($this->data['templates'] as $template) {
				$template = $this->renameData($template, array('template' => 'host'));

				CArrayHelper::convertFieldToArray($template, 'templates');

				if (empty($template['templates'])) {
					unset($template['templates']);
				}

				CArrayHelper::convertFieldToArray($template, 'macros');
				CArrayHelper::convertFieldToArray($template, 'groups');

				$templatesData[] = CArrayHelper::getByKeys($template, array(
					'groups', 'macros', 'templates', 'host', 'status', 'name', 'description'
				));
			}
		}

		return $templatesData;
	}

	/**
	 * Get hosts from the imported data.
	 *
	 * @return array
	 */
	public function getHosts() {
		$hostsData = array();

		if (!empty($this->data['hosts'])) {
			foreach ($this->data['hosts'] as $host) {
				$host = $this->renameData($host, array('proxyid' => 'proxy_hostid'));

				CArrayHelper::convertFieldToArray($host, 'interfaces');

				if (!empty($host['interfaces'])) {
					foreach ($host['interfaces'] as $inum => $interface) {
						$host['interfaces'][$inum] = $this->renameData($interface, array('default' => 'main'));
					}
				}

				CArrayHelper::convertFieldToArray($host, 'templates');

				if (empty($host['templates'])) {
					unset($host['templates']);
				}

				CArrayHelper::convertFieldToArray($host, 'macros');
				CArrayHelper::convertFieldToArray($host, 'groups');

				if (!empty($host['inventory']) && isset($host['inventory']['inventory_mode'])) {
					$host['inventory_mode'] = $host['inventory']['inventory_mode'];
					unset($host['inventory']['inventory_mode']);
				}
				else {
					$host['inventory_mode'] = HOST_INVENTORY_DISABLED;
				}

				$hostsData[] = CArrayHelper::getByKeys($host, array(
					'inventory', 'proxy', 'groups', 'templates', 'macros', 'interfaces', 'host', 'status', 'description',
					'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'name', 'inventory_mode'
				));
			}
		}

		return $hostsData;
	}

	/**
	 * Get applications from the imported data.
	 *
	 * @return array
	 */
	public function getApplications() {
		$applicationsData = array();
		if (isset($this->data['hosts'])) {
			foreach ($this->data['hosts'] as $host) {
				if (!empty($host['applications'])) {
					foreach ($host['applications'] as $application) {
						$applicationsData[$host['host']][$application['name']] = $application;
					}
				}
			}
		}

		if (isset($this->data['templates'])) {
			foreach ($this->data['templates'] as $template) {
				if (!empty($template['applications'])) {
					foreach ($template['applications'] as $application) {
						$applicationsData[$template['template']][$application['name']] = $application;
					}
				}
			}
		}

		return $applicationsData;
	}

	/**
	 * Get items from the imported data.
	 *
	 * @return array
	 */
	public function getItems() {
		$itemsData = array();

		if (isset($this->data['hosts'])) {
			foreach ($this->data['hosts'] as $host) {
				if (!empty($host['items'])) {
					foreach ($host['items'] as $item) {
						$item = $this->formatItem($item);

						$itemsData[$host['host']][$item['key_']] = $item;
					}
				}
			}
		}

		if (isset($this->data['templates'])) {
			foreach ($this->data['templates'] as $template) {
				if (!empty($template['items'])) {
					foreach ($template['items'] as $item) {
						$item = $this->formatItem($item);

						$itemsData[$template['template']][$item['key_']] = $item;
					}
				}
			}
		}

		return $itemsData;
	}

	/**
	 * Get discovery rules from the imported data.
	 *
	 * @return array
	 */
	public function getDiscoveryRules() {
		$discoveryRulesData = array();

		if (isset($this->data['hosts'])) {
			foreach ($this->data['hosts'] as $host) {
				if (!empty($host['discovery_rules'])) {
					foreach ($host['discovery_rules'] as $item) {
						$item = $this->formatDiscoveryRule($item);

						$discoveryRulesData[$host['host']][$item['key_']] = $item;
					}
				}
			}
		}

		if (isset($this->data['templates'])) {
			foreach ($this->data['templates'] as $template) {
				if (!empty($template['discovery_rules'])) {
					foreach ($template['discovery_rules'] as $item) {
						$item = $this->formatDiscoveryRule($item);

						$discoveryRulesData[$template['template']][$item['key_']] = $item;
					}
				}
			}
		}

		return $discoveryRulesData;
	}

	/**
	 * Get graphs from the imported data.
	 *
	 * @return array
	 */
	public function getGraphs() {
		$graphsData = array();

		if (isset($this->data['graphs']) && $this->data['graphs']) {
			foreach ($this->data['graphs'] as $graph) {
				$graph = $this->renameGraphFields($graph);

				if (isset($graph['gitems']) && $graph['gitems']) {
					$graph['gitems'] = array_values($graph['gitems']);
				}

				$graphsData[] = $graph;
			}
		}

		return $graphsData;
	}

	/**
	 * Get triggers from the imported data.
	 *
	 * @return array
	 */
	public function getTriggers() {
		$triggersData = array();

		if (!empty($this->data['triggers'])) {
			foreach ($this->data['triggers'] as $trigger) {
				CArrayHelper::convertFieldToArray($trigger, 'dependencies');

				$triggersData[] = $this->renameTriggerFields($trigger);
			}
		}

		return $triggersData;
	}

	/**
	 * Get images from the imported data.
	 *
	 * @return array
	 */
	public function getImages() {
		$imagesData = array();

		if (!empty($this->data['images'])) {
			foreach ($this->data['images'] as $image) {
				$imagesData[] = $this->renameData($image, array('encodedImage' => 'image'));
			}
		}

		return $imagesData;
	}

	/**
	 * Get maps from the imported data.
	 *
	 * @return array
	 */
	public function getMaps() {
		$mapsData = array();

		if (!empty($this->data['maps'])) {
			foreach ($this->data['maps'] as $map) {
				CArrayHelper::convertFieldToArray($map, 'selements');

				foreach ($map['selements'] as &$selement) {
					CArrayHelper::convertFieldToArray($selement, 'urls');
				}
				unset($selement);

				CArrayHelper::convertFieldToArray($map, 'links');

				foreach ($map['links'] as &$link) {
					CArrayHelper::convertFieldToArray($link, 'linktriggers');
				}
				unset($link);

				CArrayHelper::convertFieldToArray($map, 'urls');

				$mapsData[] = $map;
			}
		}

		return $mapsData;
	}

	/**
	 * Get screens from the imported data.
	 *
	 * @return array
	 */
	public function getScreens() {
		$screensData = array();

		if (!empty($this->data['screens'])) {
			foreach ($this->data['screens'] as $screen) {
				$screen = $this->renameData($screen, array('screen_items' => 'screenitems'));

				CArrayHelper::convertFieldToArray($screen, 'screenitems');

				$screensData[] = $screen;
			}
		}

		return $screensData;
	}

	/**
	 * Get template screens from the imported data.
	 *
	 * @return array
	 */
	public function getTemplateScreens() {
		$screensData = array();

		if (isset($this->data['templates'])) {
			foreach ($this->data['templates'] as $template) {
				if (!empty($template['screens'])) {
					foreach ($template['screens'] as $screen) {
						$screen = $this->renameData($screen, array('screen_items' => 'screenitems'));

						CArrayHelper::convertFieldToArray($screen, 'screenitems');

						$screensData[$template['template']][$screen['name']] = $screen;
					}
				}
			}
		}

		return $screensData;
	}

	/**
	 * Format item.
	 *
	 * @param array $item
	 *
	 * @return array
	 */
	protected function formatItem(array $item) {
		$item = $this->renameItemFields($item);

		if (empty($item['applications'])) {
			$item['applications'] = array();
		}

		return $item;
	}

	/**
	 * Format discovery rule.
	 *
	 * @param array $discoveryRule
	 *
	 * @return array
	 */
	protected function formatDiscoveryRule(array $discoveryRule) {
		$discoveryRule = $this->renameItemFields($discoveryRule);

		if (!empty($discoveryRule['item_prototypes'])) {
			foreach ($discoveryRule['item_prototypes'] as &$prototype) {
				$prototype = $this->renameItemFields($prototype);

				CArrayHelper::convertFieldToArray($prototype, 'applications');
			}
			unset($prototype);
		}
		else {
			$discoveryRule['item_prototypes'] = array();
		}

		if (!empty($discoveryRule['trigger_prototypes'])) {
			foreach ($discoveryRule['trigger_prototypes'] as &$trigger) {
				$trigger = $this->renameTriggerFields($trigger);
			}
			unset($trigger);
		}
		else {
			$discoveryRule['trigger_prototypes'] = array();
		}

		if (!empty($discoveryRule['graph_prototypes'])) {
			foreach ($discoveryRule['graph_prototypes'] as &$graph) {
				$graph = $this->renameGraphFields($graph);
			}
			unset($graph);
		}
		else {
			$discoveryRule['graph_prototypes'] = array();
		}

		if (!empty($discoveryRule['host_prototypes'])) {
			foreach ($discoveryRule['host_prototypes'] as &$hostPrototype) {
				CArrayHelper::convertFieldToArray($hostPrototype, 'group_prototypes');
				CArrayHelper::convertFieldToArray($hostPrototype, 'templates');
			}
			unset($hostPrototype);
		}
		else {
			$discoveryRule['host_prototypes'] = array();
		}

		if (!empty($discoveryRule['filter'])) {
			if (is_array($discoveryRule['filter'])) {
				CArrayHelper::convertFieldToArray($discoveryRule['filter'], 'conditions');
			}
		}

		return $discoveryRule;
	}

	/**
	 * Rename items, discovery rules, item prototypes fields.
	 *
	 * @param array $item
	 *
	 * @return array
	 */
	protected function renameItemFields(array $item) {
		return $this->renameData($item, array('key' => 'key_', 'allowed_hosts' => 'trapper_hosts'));
	}

	/**
	 * Rename triggers, trigger prototypes fields.
	 *
	 * @param array $trigger
	 *
	 * @return array
	 */
	protected function renameTriggerFields(array $trigger) {
		$trigger = $this->renameData($trigger, array('description' => 'comments'));

		return $this->renameData($trigger, array('name' => 'description', 'severity' => 'priority'));
	}

	/**
	 * Rename graphs, graph prototypes fields.
	 *
	 * @param array $graph
	 *
	 * @return array
	 */
	protected function renameGraphFields(array $graph) {
		return $this->renameData($graph, array(
			'type' => 'graphtype',
			'ymin_type_1' => 'ymin_type',
			'ymax_type_1' => 'ymax_type',
			'graph_items' => 'gitems'
		));
	}

	/**
	 * Renames array elements keys according to given map.
	 *
	 * @param array $data
	 * @param array $fieldMap
	 *
	 * @return array
	 */
	protected function renameData(array $data, array $fieldMap) {
		foreach ($data as $key => $value) {
			if (isset($fieldMap[$key])) {
				$data[$fieldMap[$key]] = $value;
				unset($data[$key]);
			}
		}

		return $data;
	}
}

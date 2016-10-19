<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
class CImportDataAdapter {

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
	 */
	public function load(array $data) {
		$version = $data['zabbix_export']['version'];

		if ($this->currentVersion != $version) {
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
		return array_key_exists('groups', $this->data) ? $this->data['groups'] : [];
	}

	/**
	 * Get templates from the imported data.
	 *
	 * @return array
	 */
	public function getTemplates() {
		$templates = [];

		if (array_key_exists('templates', $this->data)) {
			foreach ($this->data['templates'] as $template) {
				$template = CArrayHelper::renameKeys($template, ['template' => 'host']);

				$templates[] = CArrayHelper::getByKeys($template, [
					'groups', 'macros', 'templates', 'host', 'status', 'name', 'description'
				]);
			}
		}

		return $templates;
	}

	/**
	 * Get hosts from the imported data.
	 *
	 * @return array
	 */
	public function getHosts() {
		$hosts = [];

		if (array_key_exists('hosts', $this->data)) {
			foreach ($this->data['hosts'] as $host) {
				$host = CArrayHelper::renameKeys($host, ['proxyid' => 'proxy_hostid']);

				if (array_key_exists('interfaces', $host)) {
					foreach ($host['interfaces'] as $inum => $interface) {
						if ($interface['type'] != INTERFACE_TYPE_SNMP) {
							unset($interface['bulk']);
						}

						$host['interfaces'][$inum] = CArrayHelper::renameKeys($interface, ['default' => 'main']);
					}
				}

				if (array_key_exists('inventory', $host)) {
					if (array_key_exists('inventory_mode', $host['inventory'])) {
						$host['inventory_mode'] = $host['inventory']['inventory_mode'];
						unset($host['inventory']['inventory_mode']);
					}
					else {
						$host['inventory_mode'] = HOST_INVENTORY_DISABLED;
					}
				}

				$hosts[] = CArrayHelper::getByKeys($host, [
					'inventory', 'proxy', 'groups', 'templates', 'macros', 'interfaces', 'host', 'status',
					'description', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'name',
					'inventory_mode', 'tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject', 'tls_psk_identity',
					'tls_psk'
				]);
			}
		}

		return $hosts;
	}

	/**
	 * Get applications from the imported data.
	 *
	 * @return array
	 */
	public function getApplications() {
		$applications = [];

		if (array_key_exists('hosts', $this->data)) {
			foreach ($this->data['hosts'] as $host) {
				if (array_key_exists('applications', $host)) {
					foreach ($host['applications'] as $application) {
						$applications[$host['host']][$application['name']] = $application;
					}
				}
			}
		}

		if (array_key_exists('templates', $this->data)) {
			foreach ($this->data['templates'] as $template) {
				if (array_key_exists('applications', $template)) {
					foreach ($template['applications'] as $application) {
						$applications[$template['template']][$application['name']] = $application;
					}
				}
			}
		}

		return $applications;
	}

	/**
	 * Get value maps from the imported data.
	 *
	 * @return array
	 */
	public function getValueMaps() {
		return array_key_exists('value_maps', $this->data) ? $this->data['value_maps'] : [];
	}

	/**
	 * Get items from the imported data.
	 *
	 * @return array
	 */
	public function getItems() {
		$items = [];

		if (array_key_exists('hosts', $this->data)) {
			foreach ($this->data['hosts'] as $host) {
				if (array_key_exists('items', $host)) {
					foreach ($host['items'] as $item) {
						$items[$host['host']][$item['key']] = $this->renameItemFields($item);
					}
				}
			}
		}

		if (array_key_exists('templates', $this->data)) {
			foreach ($this->data['templates'] as $template) {
				if (array_key_exists('items', $template)) {
					foreach ($template['items'] as $item) {
						$items[$template['template']][$item['key']] = $this->renameItemFields($item);
					}
				}
			}
		}

		return $items;
	}

	/**
	 * Get discovery rules from the imported data.
	 *
	 * @return array
	 */
	public function getDiscoveryRules() {
		$discovery_rules = [];

		if (array_key_exists('hosts', $this->data)) {
			foreach ($this->data['hosts'] as $host) {
				if (array_key_exists('discovery_rules', $host)) {
					foreach ($host['discovery_rules'] as $discovery_rule) {
						$discovery_rules[$host['host']][$discovery_rule['key']] =
							$this->formatDiscoveryRule($discovery_rule);
					}
				}
			}
		}

		if (array_key_exists('templates', $this->data)) {
			foreach ($this->data['templates'] as $template) {
				if (array_key_exists('discovery_rules', $template)) {
					foreach ($template['discovery_rules'] as $discovery_rule) {
						$discovery_rules[$template['template']][$discovery_rule['key']] =
							$this->formatDiscoveryRule($discovery_rule);
					}
				}
			}
		}

		return $discovery_rules;
	}

	/**
	 * Get graphs from the imported data.
	 *
	 * @return array
	 */
	public function getGraphs() {
		$graphs = [];

		if (array_key_exists('graphs', $this->data)) {
			foreach ($this->data['graphs'] as $graph) {
				$graphs[] = $this->renameGraphFields($graph);
			}
		}

		return $graphs;
	}

	/**
	 * Get triggers from the imported data.
	 *
	 * @return array
	 */
	public function getTriggers() {
		$triggers = [];

		if (array_key_exists('triggers', $this->data)) {
			foreach ($this->data['triggers'] as $trigger) {
				$triggers[] = $this->renameTriggerFields($trigger);
			}
		}

		return $triggers;
	}

	/**
	 * Get images from the imported data.
	 *
	 * @return array
	 */
	public function getImages() {
		$images = [];

		if (array_key_exists('images', $this->data)) {
			foreach ($this->data['images'] as $image) {
				$images[] = CArrayHelper::renameKeys($image, ['encodedImage' => 'image']);
			}
		}

		return $images;
	}

	/**
	 * Get maps from the imported data.
	 *
	 * @return array
	 */
	public function getMaps() {
		return array_key_exists('maps', $this->data) ? $this->data['maps'] : [];
	}

	/**
	 * Get screens from the imported data.
	 *
	 * @return array
	 */
	public function getScreens() {
		$screens = [];

		if (array_key_exists('screens', $this->data)) {
			foreach ($this->data['screens'] as $screen) {
				$screens[] = CArrayHelper::renameKeys($screen, ['screen_items' => 'screenitems']);
			}
		}

		return $screens;
	}

	/**
	 * Get template screens from the imported data.
	 *
	 * @return array
	 */
	public function getTemplateScreens() {
		$screens = [];

		if (array_key_exists('templates', $this->data)) {
			foreach ($this->data['templates'] as $template) {
				if (array_key_exists('screens', $template)) {
					foreach ($template['screens'] as $screen) {
						$screens[$template['template']][$screen['name']] =
							CArrayHelper::renameKeys($screen, ['screen_items' => 'screenitems']);
					}
				}
			}
		}

		return $screens;
	}

	/**
	 * Format discovery rule.
	 *
	 * @param array $discovery_rule
	 *
	 * @return array
	 */
	protected function formatDiscoveryRule(array $discovery_rule) {
		$discovery_rule = $this->renameItemFields($discovery_rule);

		foreach ($discovery_rule['item_prototypes'] as &$item_prototype) {
			$item_prototype = $this->renameItemFields($item_prototype);
		}
		unset($item_prototype);

		foreach ($discovery_rule['trigger_prototypes'] as &$trigger_prototype) {
			$trigger_prototype = $this->renameTriggerFields($trigger_prototype);
		}
		unset($trigger_prototype);

		foreach ($discovery_rule['graph_prototypes'] as &$graph_prototype) {
			$graph_prototype = $this->renameGraphFields($graph_prototype);
		}
		unset($graph_prototype);

		return $discovery_rule;
	}

	/**
	 * Rename items, discovery rules, item prototypes fields.
	 *
	 * @param array $item
	 *
	 * @return array
	 */
	protected function renameItemFields(array $item) {
		return CArrayHelper::renameKeys($item, ['key' => 'key_', 'allowed_hosts' => 'trapper_hosts']);
	}

	/**
	 * Rename triggers, trigger prototypes fields.
	 *
	 * @param array $trigger
	 *
	 * @return array
	 */
	protected function renameTriggerFields(array $trigger) {
		$trigger = CArrayHelper::renameKeys($trigger, ['description' => 'comments']);

		return CArrayHelper::renameKeys($trigger, ['name' => 'description', 'severity' => 'priority']);
	}

	/**
	 * Rename graphs, graph prototypes fields.
	 *
	 * @param array $graph
	 *
	 * @return array
	 */
	protected function renameGraphFields(array $graph) {
		return CArrayHelper::renameKeys($graph, [
			'type' => 'graphtype',
			'ymin_type_1' => 'ymin_type',
			'ymax_type_1' => 'ymax_type',
			'graph_items' => 'gitems'
		]);
	}
}

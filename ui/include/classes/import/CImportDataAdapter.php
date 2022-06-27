<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
	 * Current import version.
	 *
	 * @var string
	 */
	protected $currentVersion;

	/**
	 * Set the data and initialize the adapter.
	 *
	 * @param array $data   import data
	 */
	public function load(array $data) {
		$this->data = $data['zabbix_export'];
	}

	public function getData(): array {
		return $this->data;
	}

	/**
	 * Get template groups from the imported data.
	 *
	 * @return array
	 */
	public function getTemplateGroups(): array {
		return array_key_exists('template_groups', $this->data) ? $this->data['template_groups'] : [];
	}

	/**
	 * Get host groups from the imported data.
	 *
	 * @return array
	 */
	public function getHostGroups(): array {
		return array_key_exists('host_groups', $this->data) ? $this->data['host_groups'] : [];
	}

	/**
	 * Get templates from the imported data.
	 *
	 * @return array
	 */
	public function getTemplates(): array {
		$templates = [];

		if (array_key_exists('templates', $this->data)) {
			foreach ($this->data['templates'] as $template) {
				$template = CArrayHelper::renameKeys($template, ['template' => 'host']);

				$templates[] = CArrayHelper::getByKeys($template, [
					'uuid', 'groups', 'macros', 'templates', 'host', 'status', 'name', 'description', 'tags',
					'valuemaps'
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
	public function getHosts(): array {
		$hosts = [];

		if (array_key_exists('hosts', $this->data)) {
			foreach ($this->data['hosts'] as $host) {
				$host = CArrayHelper::renameKeys($host, ['proxyid' => 'proxy_hostid']);

				if (array_key_exists('interfaces', $host)) {
					foreach ($host['interfaces'] as $index => $interface) {
						$host['interfaces'][$index] = CArrayHelper::renameKeys($interface, ['default' => 'main']);
					}
				}

				$hosts[] = CArrayHelper::getByKeys($host, [
					'inventory', 'proxy', 'groups', 'templates', 'macros', 'interfaces', 'host', 'status',
					'description', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'name',
					'inventory_mode', 'tags', 'valuemaps'
				]);
			}
		}

		return $hosts;
	}

	/**
	 * Get items from the imported data.
	 *
	 * @return array
	 */
	public function getItems(): array {
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
	public function getDiscoveryRules(): array {
		$discovery_rules = [];

		if (array_key_exists('hosts', $this->data)) {
			foreach ($this->data['hosts'] as $host) {
				if (array_key_exists('discovery_rules', $host)) {
					foreach ($host['discovery_rules'] as $discovery_rule) {
						$discovery_rules[$host['host']][$discovery_rule['key']] =
							$this->formatDiscoveryRule($discovery_rule, $host['host']);
					}
				}
			}
		}

		if (array_key_exists('templates', $this->data)) {
			foreach ($this->data['templates'] as $template) {
				if (array_key_exists('discovery_rules', $template)) {
					foreach ($template['discovery_rules'] as $discovery_rule) {
						$discovery_rules[$template['template']][$discovery_rule['key']] =
							$this->formatDiscoveryRule($discovery_rule, $template['template']);
					}
				}
			}
		}

		return $discovery_rules;
	}

	/**
	 * Get web scenarios from the imported data.
	 *
	 * @return array
	 */
	public function getHttpTests(): array {
		$httptests = [];

		if (array_key_exists('hosts', $this->data)) {
			foreach ($this->data['hosts'] as $host) {
				if (array_key_exists('httptests', $host)) {
					foreach ($host['httptests'] as $httptest) {
						$httptests[$host['host']][$httptest['name']] = $this->formatHttpTest($httptest);
					}
				}
			}
		}

		if (array_key_exists('templates', $this->data)) {
			foreach ($this->data['templates'] as $template) {
				if (array_key_exists('httptests', $template)) {
					foreach ($template['httptests'] as $httptest) {
						$httptests[$template['template']][$httptest['name']] = $this->formatHttpTest($httptest);
					}
				}
			}
		}

		return $httptests;
	}

	/**
	 * Get web scenario steps from the imported data.
	 *
	 * @return array
	 */
	public function getHttpSteps(): array {
		$httpsteps = [];

		if (array_key_exists('hosts', $this->data)) {
			foreach ($this->data['hosts'] as $host) {
				if (array_key_exists('httptests', $host)) {
					foreach ($host['httptests'] as $httptest) {
						foreach ($httptest['steps'] as $httpstep) {
							$httpsteps[$host['host']][$httptest['name']][$httpstep['name']] = $httpstep;
						}
					}
				}
			}
		}

		if (array_key_exists('templates', $this->data)) {
			foreach ($this->data['templates'] as $template) {
				if (array_key_exists('httptests', $template)) {
					foreach ($template['httptests'] as $httptest) {
						foreach ($httptest['steps'] as $httpstep) {
							$httpsteps[$template['template']][$httptest['name']][$httpstep['name']] = $httpstep;
						}
					}
				}
			}
		}

		return $httpsteps;
	}

	/**
	 * Get graphs from the imported data.
	 *
	 * @return array
	 */
	public function getGraphs(): array {
		$graphs = [];

		if (array_key_exists('graphs', $this->data)) {
			foreach ($this->data['graphs'] as $graph) {
				if (array_key_exists('uuid', $graph) && $graph['uuid'] === '') {
					unset($graph['uuid']);
				}

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

		foreach (['hosts', 'templates'] as $source) {
			if (array_key_exists($source, $this->data)) {
				foreach ($this->data[$source] as $host) {
					if (array_key_exists('items', $host)) {
						foreach ($host['items'] as $item) {
							if (array_key_exists('triggers', $item)) {
								foreach ($item['triggers'] as $trigger) {
									$triggers[] = $this->renameTriggerFields($trigger);
								}
							}
						}
					}
				}
			}
		}

		if (array_key_exists('triggers', $this->data)) {
			foreach ($this->data['triggers'] as $trigger) {
				if (array_key_exists('uuid', $trigger) && $trigger['uuid'] === '') {
					unset($trigger['uuid']);
				}

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
	 * Get template dashboards from the imported data.
	 *
	 * @return array
	 */
	public function getTemplateDashboards() {
		$dashboards = [];

		if (array_key_exists('templates', $this->data)) {
			foreach ($this->data['templates'] as $template) {
				if (array_key_exists('dashboards', $template)) {
					foreach ($template['dashboards'] as $dashboard) {
						foreach ($dashboard['pages'] as &$dashboard_page) {
							// Rename hide_header to view_mode in widgets.
							if (array_key_exists('widgets', $dashboard_page)) {
								$dashboard_page['widgets'] = array_map(function (array $widget): array {
									$widget = CArrayHelper::renameKeys($widget, ['hide_header' => 'view_mode']);

									return $widget;
								}, $dashboard_page['widgets']);
							}
						}
						unset($dashboard_page);

						$dashboards[$template['template']][$dashboard['name']] = $dashboard;
					}
				}
			}
		}

		return $dashboards;
	}

	/**
	 * Get media types from the imported data.
	 *
	 * @return array
	 */
	public function getMediaTypes() {
		$media_types = [];

		if (array_key_exists('media_types', $this->data)) {
			$keys = [
				'password' => 'passwd',
				'script_name' => 'exec_path',
				'max_sessions' => 'maxsessions',
				'attempts' => 'maxattempts'
			];

			$message_template_keys = [
				'event_source' => 'eventsource',
				'operation_mode' => 'recovery'
			];

			foreach ($this->data['media_types'] as $media_type) {
				if (array_key_exists('message_templates', $media_type)) {
					foreach ($media_type['message_templates'] as &$message_template) {
						$message_template = CArrayHelper::renameKeys($message_template, $message_template_keys);
					}
					unset($message_template);
				}

				if ($media_type['type'] == MEDIA_TYPE_EXEC && array_key_exists('parameters', $media_type)) {
					$media_type['exec_params'] = $media_type['parameters']
						? implode("\n", $media_type['parameters'])."\n"
						: '';
					unset($media_type['parameters']);
				}

				$media_types[] = CArrayHelper::renameKeys($media_type, $keys);
			}
		}

		return $media_types;
	}

	/**
	 * Format discovery rule.
	 *
	 * @param array  $discovery_rule
	 * @param string $host
	 *
	 * @return array
	 */
	protected function formatDiscoveryRule(array $discovery_rule, $host) {
		$discovery_rule = $this->renameItemFields($discovery_rule);
		$discovery_rule = $this->formatDiscoveryRuleOverrideFields($discovery_rule);

		foreach ($discovery_rule['item_prototypes'] as &$item_prototype) {
			if (array_key_exists('trigger_prototypes', $item_prototype)) {
				$discovery_rule['trigger_prototypes'] = array_merge($discovery_rule['trigger_prototypes'],
					$item_prototype['trigger_prototypes']
				);
			}

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

		foreach ($discovery_rule['host_prototypes'] as &$host_prototype) {
			// Optionally remove interfaces array also if no custom interfaces are set.
			if ($host_prototype['custom_interfaces'] == HOST_PROT_INTERFACES_INHERIT) {
				unset($host_prototype['interfaces']);
			}

			if (array_key_exists('interfaces', $host_prototype)) {
				foreach ($host_prototype['interfaces'] as &$interface) {
					$interface = CArrayHelper::renameKeys($interface, ['default' => 'main']);

					// Import creates empty arrays. Remove them, since they are not required.
					if ($interface['type'] != INTERFACE_TYPE_SNMP) {
						unset($interface['details']);
					}
				}
				unset($interface);
			}
		}
		unset($host_prototype);

		return $discovery_rule;
	}

	/**
	 * Format low-level discovery rule overrides.
	 *
	 * @param array $discovery_rule  Data of single low-level discovery rule.
	 *
	 * @return array
	 */
	protected function formatDiscoveryRuleOverrideFields(array $discovery_rule) {
		if ($discovery_rule['overrides']) {
			foreach ($discovery_rule['overrides'] as &$override) {
				if (!$override['filter']) {
					unset($override['filter']);
				}

				foreach ($override['operations'] as &$operation) {
					if (array_key_exists('discover', $operation) && $operation['discover'] !== '') {
						$operation['opdiscover']['discover'] = $operation['discover'];
					}

					switch ($operation['operationobject']) {
						case OPERATION_OBJECT_ITEM_PROTOTYPE:
							if (array_key_exists('status', $operation) && $operation['status'] !== '') {
								$operation['opstatus']['status'] = $operation['status'];
							}
							if (array_key_exists('delay', $operation) && $operation['delay'] !== '') {
								$operation['opperiod']['delay'] = $operation['delay'];
							}
							if (array_key_exists('history', $operation) && $operation['history'] !== '') {
								$operation['ophistory']['history'] = $operation['history'];
							}
							if (array_key_exists('trends', $operation) && $operation['trends'] !== '') {
								$operation['optrends']['trends'] = $operation['trends'];
							}
							if (array_key_exists('tags', $operation) && $operation['tags']) {
								$operation['optag'] = [];
								foreach ($operation['tags'] as $tag) {
									$operation['optag'][] = $tag;
								}
							}
							break;

						case OPERATION_OBJECT_TRIGGER_PROTOTYPE:
							if (array_key_exists('status', $operation) && $operation['status'] !== '') {
								$operation['opstatus']['status'] = $operation['status'];
							}
							if (array_key_exists('severity', $operation) && $operation['severity'] !== '') {
								$operation['opseverity']['severity'] = $operation['severity'];
							}
							if (array_key_exists('tags', $operation) && $operation['tags']) {
								$operation['optag'] = [];
								foreach ($operation['tags'] as $tag) {
									$operation['optag'][] = $tag;
								}
							}
							break;

						case OPERATION_OBJECT_HOST_PROTOTYPE:
							if (array_key_exists('status', $operation) && $operation['status'] !== '') {
								$operation['opstatus']['status'] = $operation['status'];
							}
							if (array_key_exists('templates', $operation) && $operation['templates']) {
								$operation['optemplate'] = [];
								foreach ($operation['templates'] as $template) {
									$operation['optemplate'][] = $template;
								}
							}
							if (array_key_exists('tags', $operation) && $operation['tags']) {
								$operation['optag'] = [];
								foreach ($operation['tags'] as $tag) {
									$operation['optag'][] = $tag;
								}
							}
							if (array_key_exists('inventory_mode', $operation) && $operation['inventory_mode'] !== '') {
								$operation['opinventory']['inventory_mode'] = $operation['inventory_mode'];
							}
							break;
					}

					unset($operation['status'], $operation['discover'], $operation['delay'], $operation['history'],
						$operation['trends'], $operation['severity'], $operation['tags'], $operation['templates'],
						$operation['inventory_mode']
					);
				}
				unset($operation);
			}
			unset($override);
		}

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
	 * Format web scenario.
	 *
	 * @param array $httptest
	 *
	 * @return array
	 */
	protected function formatHttpTest(array $httptest) {
		$httptest = $this->renameHttpTestFields($httptest);

		$no = 0;
		foreach ($httptest['steps'] as &$step) {
			$step['no'] = ++$no;
		}
		unset($step);

		return $httptest;
	}

	/**
	 * Rename web scenarios fields.
	 *
	 * @param array $httptest
	 *
	 * @return array
	 */
	protected function renameHttpTestFields(array $httptest) {
		return CArrayHelper::renameKeys($httptest, ['attempts' => 'retries']);
	}

	/**
	 * Rename triggers, trigger prototypes fields.
	 *
	 * @param array $trigger
	 *
	 * @return array
	 */
	protected function renameTriggerFields(array $trigger): array {
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

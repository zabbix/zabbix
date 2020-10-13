<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
					'groups', 'macros', 'templates', 'host', 'status', 'name', 'description', 'tags'
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
						$host['interfaces'][$inum] = CArrayHelper::renameKeys($interface, ['default' => 'main']);
					}
				}

				$hosts[] = CArrayHelper::getByKeys($host, [
					'inventory', 'proxy', 'groups', 'templates', 'macros', 'interfaces', 'host', 'status',
					'description', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'name',
					'inventory_mode', 'tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject', 'tls_psk_identity',
					'tls_psk', 'tags'
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
	public function getHttpTests() {
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
	public function getHttpSteps() {
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
	 * Get simple triggers from the imported data.
	 *
	 * @return array
	 */
	protected function getSimpleTriggers() {
		$simple_triggers = [];
		$expression_options = ['lldmacros' => false, 'allow_func_only' => true];

		if (array_key_exists('hosts', $this->data)) {
			foreach ($this->data['hosts'] as $host) {
				if (array_key_exists('items', $host)) {
					foreach ($host['items'] as $item) {
						if (array_key_exists('triggers', $item)) {
							foreach ($item['triggers'] as $simple_trigger) {
								$simple_trigger = $this->enrichSimpleTriggerExpression($host['host'], $item['key'],
									$simple_trigger, $expression_options
								);
								$simple_triggers[] = $this->renameTriggerFields($simple_trigger);
							}
							unset($item['triggers']);
						}
					}
				}
			}
		}

		if (array_key_exists('templates', $this->data)) {
			foreach ($this->data['templates'] as $template) {
				if (array_key_exists('items', $template)) {
					foreach ($template['items'] as $item) {
						if (array_key_exists('triggers', $item)) {
							foreach ($item['triggers'] as $simple_trigger) {
								$simple_trigger = $this->enrichSimpleTriggerExpression($template['template'],
									$item['key'], $simple_trigger, $expression_options
								);
								$simple_triggers[] = $this->renameTriggerFields($simple_trigger);
							}
							unset($item['triggers']);
						}
					}
				}
			}
		}

		return $simple_triggers;
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

		return array_merge($triggers, $this->getSimpleTriggers());
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
						// Rename hide_header to view_mode in widgets.
						if (array_key_exists('widgets', $dashboard)) {
							$dashboard['widgets'] = array_map(function (array $widget): array {
								$widget = CArrayHelper::renameKeys($widget, ['hide_header' => 'view_mode']);

								return $widget;
							}, $dashboard['widgets']);
						}

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

				$media_types[] = CArrayHelper::renameKeys($media_type,
					$keys + (($media_type['type'] == MEDIA_TYPE_EXEC) ? ['parameters' => 'exec_params'] : [])
				);
			}
		}

		return $media_types;
	}

	/**
	 * Enriches trigger expression and trigger recovery expression with host:item pair.
	 *
	 * @param string $host
	 * @param string $item_key
	 * @param array  $simple_trigger
	 * @param string $simple_trigger['expression]
	 * @param int    $simple_trigger['recovery_mode]
	 * @param string $simple_trigger['recovery_expression]
	 * @param array  $options
	 * @param bool   $options['lldmacros']                  (optional)
	 * @param bool   $options['allow_func_only']            (optional)
	 *
	 * @return array
	 */
	protected function enrichSimpleTriggerExpression($host, $item_key, array $simple_trigger, array $options) {
		$expression_data = new CTriggerExpression($options);
		$prefix = $host.':'.$item_key.'.';

		if ($expression_data->parse($simple_trigger['expression'])) {
			foreach (array_reverse($expression_data->expressions) as $expression) {
				if ($expression['host'] === '' && $expression['item'] === '') {
					$simple_trigger['expression'] = substr_replace($simple_trigger['expression'], $prefix,
						$expression['pos'] + 1, 0
					);
				}
			}
		}

		if ($simple_trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION
				&& $expression_data->parse($simple_trigger['recovery_expression'])) {
			foreach (array_reverse($expression_data->expressions) as $expression) {
				if ($expression['host'] === '' && $expression['item'] === '') {
					$simple_trigger['recovery_expression'] = substr_replace($simple_trigger['recovery_expression'],
						$prefix, $expression['pos'] + 1, 0
					);
				}
			}
		}

		return $simple_trigger;
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
				foreach ($item_prototype['trigger_prototypes'] as $trigger_prototype) {
					$discovery_rule['trigger_prototypes'][] =  $this->enrichSimpleTriggerExpression($host,
						$item_prototype['key'], $trigger_prototype, ['allow_func_only' => true]
					);
				}
				unset($item_prototype['trigger_prototypes']);
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
	 * Format low-level disovery rule overrides.
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

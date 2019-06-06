<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

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
 * Import formatter for version 2.0
 */
class C20ImportFormatter extends CImportFormatter {

	/**
	 * Converter for trigger expressions.
	 *
	 * @var C24TriggerConverter
	 */
	protected $triggerExpressionConverter;

	/**
	 * @param C24TriggerConverter $triggerExpressionConverter
	 */
	public function __construct(C24TriggerConverter $triggerExpressionConverter) {
		$this->triggerExpressionConverter = $triggerExpressionConverter;
	}

	public function getGroups() {
		if (!isset($this->data['groups'])) {
			return array();
		}

		return array_values($this->data['groups']);
	}

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
				CArrayHelper::convertFieldToArray($template, 'screens');

				if (!empty($template['screens'])) {
					foreach ($template['screens'] as &$screen) {
						$screen = $this->renameData($screen, array('screen_items' => 'screenitems'));
					}

					unset($screen);
				}

				$templatesData[] = CArrayHelper::getByKeys($template, array(
					'groups', 'macros', 'screens', 'templates', 'host', 'status', 'name', 'description'
				));
			}
		}

		return $templatesData;
	}

	public function getHosts() {
		$hostsData = array();

		if (!empty($this->data['hosts'])) {
			foreach ($this->data['hosts'] as $host) {
				$host = $this->renameData($host, array('proxyid' => 'proxy_hostid'));

				CArrayHelper::convertFieldToArray($host, 'interfaces');

				if (!empty($host['interfaces'])) {
					foreach ($host['interfaces'] as $inum => $interface) {
						// set bulk default value
						if (!isset($interface['bulk'])) {
							$interface['bulk'] = SNMP_BULK_ENABLED;
						}

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

	public function getItems() {
		$itemsData = array();

		if (isset($this->data['hosts'])) {
			foreach ($this->data['hosts'] as $host) {
				if (!empty($host['items'])) {
					foreach ($host['items'] as $item) {
						// if a host item has the "Not supported" status, convert it to "Active"
						if ($item['status'] == ITEM_STATUS_NOTSUPPORTED) {
							$item['status'] = ITEM_STATUS_ACTIVE;
						}

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

	public function getDiscoveryRules() {
		$discoveryRulesData = array();

		if (isset($this->data['hosts'])) {
			foreach ($this->data['hosts'] as $host) {
				if (!empty($host['discovery_rules'])) {
					foreach ($host['discovery_rules'] as $item) {
						// if a discovery rule has the "Not supported" status, convert it to "Active"
						if ($item['status'] == ITEM_STATUS_NOTSUPPORTED) {
							$item['status'] = ITEM_STATUS_ACTIVE;
						}

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

	public function getTriggers() {
		$triggersData = array();

		if (!empty($this->data['triggers'])) {
			foreach ($this->data['triggers'] as $trigger) {
				CArrayHelper::convertFieldToArray($trigger, 'dependencies');

				$trigger['expression'] = $this->triggerExpressionConverter->convert($trigger['expression']);

				$triggersData[] = $this->renameTriggerFields($trigger);
			}
		}

		return $triggersData;
	}

	public function getImages() {
		$imagesData = array();

		if (!empty($this->data['images'])) {
			foreach ($this->data['images'] as $image) {
				$imagesData[] = $this->renameData($image, array('encodedImage' => 'image'));
			}
		}

		return $imagesData;
	}

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
				$trigger['expression'] = $this->triggerExpressionConverter->convert($trigger['expression']);

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
			// array filter structure
			if (is_array($discoveryRule['filter'])) {
				CArrayHelper::convertFieldToArray($discoveryRule['filter'], 'conditions');
			}
			// string {#MACRO}:value syntax
			else {
				list ($filterMacro, $filterValue) = explode(':', $discoveryRule['filter']);
				if ($filterMacro) {
					$discoveryRule['filter'] = array(
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'formula' => '',
						'conditions' => array(
							array(
								'macro' => $filterMacro,
								'value' => $filterValue,
								'operator' => CONDITION_OPERATOR_REGEXP,
							)
						)
					);
				}
				// if the macro is empty, ignore the filter
				else {
					unset($discoveryRule['filter']);
				}
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
}

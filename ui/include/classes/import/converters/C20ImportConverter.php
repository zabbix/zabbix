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
 * Converter for converting import data from 2.x to 3.0.
 */
class C20ImportConverter extends CConverter {

	/**
	 * Item key converter.
	 *
	 * @var C20ItemKeyConverter
	 */
	protected $itemKeyConverter;

	/**
	 * Legacy screen resource types.
	 */
	private const SCREEN_RESOURCE_TYPE_SIMPLE_GRAPH = 1;
	private const SCREEN_RESOURCE_TYPE_PLAIN_TEXT = 3;

	/**
	 * Converter used for converting trigger expressions from 2.2 to 2.4 format.
	 *
	 * @var CConverter
	 */
	protected $triggerExpressionConverter;

	public function __construct() {
		$this->itemKeyConverter = new C20ItemKeyConverter();
		$this->triggerExpressionConverter = new C20TriggerConverter();
	}

	public function convert($data) {
		$data['zabbix_export']['version'] = '3.0';

		if (array_key_exists('hosts', $data['zabbix_export'])) {
			$data['zabbix_export']['hosts'] = $this->convertHosts($data['zabbix_export']['hosts']);
		}
		if (array_key_exists('templates', $data['zabbix_export'])) {
			$data['zabbix_export']['templates'] = $this->convertTemplates($data['zabbix_export']['templates']);
		}
		if (array_key_exists('graphs', $data['zabbix_export'])) {
			$data['zabbix_export']['graphs'] = $this->convertGraphs($data['zabbix_export']['graphs']);
		}
		if (array_key_exists('triggers', $data['zabbix_export'])) {
			$data['zabbix_export']['triggers'] = $this->convertTriggers($data['zabbix_export']['triggers']);
		}
		if (array_key_exists('maps', $data['zabbix_export'])) {
			$data['zabbix_export']['maps'] = $this->convertMaps($data['zabbix_export']['maps']);
		}

		return $data;
	}

	/**
	 * Convert host elements.
	 *
	 * @param array $hosts
	 *
	 * @return array
	 */
	protected function convertHosts(array $hosts) {
		foreach ($hosts as &$host) {
			if (array_key_exists('interfaces', $host)) {
				$host['interfaces'] = $this->convertHostInterfaces($host['interfaces']);
			}
			if (array_key_exists('items', $host)) {
				$host['items'] = $this->convertItems($host['items']);
			}
			if (array_key_exists('discovery_rules', $host)) {
				$host['discovery_rules'] = $this->convertDiscoveryRules($host['discovery_rules']);
			}

			if (!array_key_exists('description', $host)) {
				$host['description'] = '';
			}

			$host['tls_connect'] = (string) CXmlConstantValue::NO_ENCRYPTION;
			$host['tls_accept'] = (string) CXmlConstantValue::NO_ENCRYPTION;
			$host['tls_issuer'] = '';
			$host['tls_subject'] = '';
			$host['tls_psk_identity'] = '';
			$host['tls_psk'] = '';
		}
		unset($host);

		return $hosts;
	}

	/**
	 * Convert template elements.
	 *
	 * @param array $templates
	 *
	 * @return array
	 */
	protected function convertTemplates(array $templates) {
		foreach ($templates as &$template) {
			if (array_key_exists('items', $template)) {
				$template['items'] = $this->convertItems($template['items']);
			}
			if (array_key_exists('discovery_rules', $template)) {
				$template['discovery_rules'] = $this->convertDiscoveryRules($template['discovery_rules']);
			}
			if (array_key_exists('screens', $template)) {
				$template['screens'] = $this->convertScreens($template['screens']);
			}

			if (!array_key_exists('description', $template)) {
				$template['description'] = '';
			}
		}
		unset($template);

		return $templates;
	}

	/**
	 * Convert item elements.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	protected function convertItems(array $items) {
		foreach ($items as &$item) {
			if ($item['status'] == ITEM_STATUS_NOTSUPPORTED) {
				$item['status'] = (string) ITEM_STATUS_ACTIVE;
			}

			$item['key'] = $this->itemKeyConverter->convert($item['key']);

			/*
			 * Prepare the 2.0 format for 3.0 validator. 2.2-2.4 will already have these fields exported.
			 *
			 * 2.2 introduced new fields "snmpv3_contextname", "snmpv3_authprotocol", "snmpv3_privprotocol", but this
			 * converter is for all 2.x. In 2.2+ they are exported, but for 2.0 they have to be added. It is, however,
			 * expected by 3.0 validator for all items, not just SNMP v3. In 2.2 a field "logtimefmt" was also
			 * added which is required by 3.0 validator.
			 */
			foreach (['snmpv3_contextname', 'snmpv3_authprotocol', 'snmpv3_privprotocol', 'logtimefmt'] as $field) {
				if (!array_key_exists($field, $item)) {
					$item[$field] = '';
				}
			}
		}
		unset($item);

		return $items;
	}

	/**
	 * Convert interface elements.
	 *
	 * @param array $interfaces
	 *
	 * @return array
	 */
	protected function convertHostInterfaces(array $interfaces) {
		foreach ($interfaces as &$interface) {
			if (!array_key_exists('bulk', $interface)) {
				if ($interface['type'] == INTERFACE_TYPE_SNMP) {
					$interface['bulk'] = (string) SNMP_BULK_ENABLED;
				}
				else {
					$interface['bulk'] = (string) SNMP_BULK_DISABLED;
				}
			}
		}
		unset($interface);

		return $interfaces;
	}

	/**
	 * Convert trigger elements.
	 *
	 * @param array $triggers
	 *
	 * @return array
	 */
	protected function convertTriggers(array $triggers) {
		foreach ($triggers as &$trigger) {
			if (array_key_exists('dependencies', $trigger)) {
				foreach ($trigger['dependencies'] as &$dependency) {
					$dependency['expression'] = $this->triggerExpressionConverter->convert($dependency['expression']);
				}
				unset($dependency);
			}

			$trigger['expression'] = $this->triggerExpressionConverter->convert($trigger['expression']);
		}
		unset($trigger);

		return $triggers;
	}

	/**
	 * Convert screens.
	 *
	 * @param array $screens
	 *
	 * @return array
	 */
	protected function convertScreens(array $screens) {
		foreach ($screens as &$screen) {
			if (array_key_exists('screen_items', $screen)) {
				$screen['screen_items'] = $this->convertScreenItems($screen['screen_items']);
			}
		}
		unset($screen);

		return $screens;
	}

	/**
	 * Convert screen items.
	 *
	 * @param array $screen_items
	 *
	 * @return array
	 */
	protected function convertScreenItems(array $screen_items) {
		foreach ($screen_items as &$screen_item) {
			if ($screen_item['rowspan'] == 0) {
				$screen_item['rowspan'] = (string) 1;
			}
			if ($screen_item['colspan'] == 0) {
				$screen_item['colspan'] = (string) 1;
			}

			if (zbx_is_int($screen_item['resourcetype'])) {
				switch ($screen_item['resourcetype']) {
					case self::SCREEN_RESOURCE_TYPE_SIMPLE_GRAPH:
					case self::SCREEN_RESOURCE_TYPE_PLAIN_TEXT:
						$screen_item['resource']['key'] =
							$this->itemKeyConverter->convert($screen_item['resource']['key']);
						break;
				}
			}

			$screen_item['application'] = '';

			if (!array_key_exists('max_columns', $screen_item)) {
				$screen_item['max_columns'] = (string) SCREEN_SURROGATE_MAX_COLUMNS_DEFAULT;
			}
		}
		unset($screen_item);

		return $screen_items;
	}

	/**
	 * Convert map elements.
	 *
	 * @param array $maps
	 *
	 * @return array
	 */
	protected function convertMaps(array $maps) {
		foreach ($maps as &$map) {
			if (array_key_exists('selements', $map)) {
				foreach ($map['selements'] as &$selement) {
					if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
						$selement['element']['expression'] = $this->triggerExpressionConverter->convert(
							$selement['element']['expression']
						);
					}
				}
				unset($selement);
			}

			foreach ($map['links'] as &$link) {
				if (array_key_exists('linktriggers', $link)) {
					foreach ($link['linktriggers'] as &$linktrigger) {
						$linktrigger['trigger']['expression'] = $this->triggerExpressionConverter->convert(
							$linktrigger['trigger']['expression']
						);
					}
					unset($linktrigger);
				}
			}
			unset($link);
		}
		unset($map);

		return $maps;
	}

	/**
	 * Convert discovery rule elements.
	 *
	 * @param array $discovery_rules
	 *
	 * @return array
	 */
	protected function convertDiscoveryRules(array $discovery_rules) {
		foreach ($discovery_rules as &$discovery_rule) {
			if ($discovery_rule['status'] == ITEM_STATUS_NOTSUPPORTED) {
				$discovery_rule['status'] = (string) ITEM_STATUS_ACTIVE;
			}

			/*
			 * Prepare the 2.0 format for 3.0 validator. 2.2-2.4 will already have these fields exported.
			 *
			 * 2.2 introduced new fields "snmpv3_contextname", "snmpv3_authprotocol", "snmpv3_privprotocol", but this
			 * converter is for all 2.x. In 2.2+ they are exported, but for 2.0 they have to be added. It is, however,
			 * expected by 3.0 validator for all discovery rules, not just SNMP v3.
			 */
			foreach (['snmpv3_contextname', 'snmpv3_authprotocol', 'snmpv3_privprotocol'] as $field) {
				if (!array_key_exists($field, $discovery_rule)) {
					$discovery_rule[$field] = '';
				}
			}

			if (in_array($discovery_rule['type'], [ITEM_TYPE_SNMPV1, ITEM_TYPE_SNMPV2C, ITEM_TYPE_SNMPV3])) {
				$param = CItemKey::quoteParam($discovery_rule['snmp_oid']);
				if ($param !== false) {
					$discovery_rule['snmp_oid'] = 'discovery[{#SNMPVALUE},'.$param.']';
				}
			}

			if (!array_key_exists('host_prototypes', $discovery_rule)) {
				$discovery_rule['host_prototypes'] = [];
			}

			$discovery_rule['filter'] = $this->convertDiscoveryRuleFilter($discovery_rule['filter']);
			$discovery_rule['item_prototypes'] = $this->convertItemPrototypes($discovery_rule['item_prototypes']);
			$discovery_rule['graph_prototypes'] = $this->convertGraphs($discovery_rule['graph_prototypes']);
			$discovery_rule['trigger_prototypes'] =
				$this->convertTriggerPrototypes($discovery_rule['trigger_prototypes']);
		}
		unset($discovery_rule);

		return $discovery_rules;
	}

	/**
	 * Convert item prototype elements.
	 *
	 * @param array $item_prototypes
	 *
	 * @return array
	 */
	protected function convertItemPrototypes(array $item_prototypes) {
		foreach ($item_prototypes as &$item_prototype) {
			$item_prototype['key'] = $this->itemKeyConverter->convert($item_prototype['key']);
			$item_prototype['application_prototypes'] = '';

			/*
			 * Prepare the 2.0 format for 3.0 validator. 2.2-2.4 will already have these fields exported.
			 *
			 * 2.2 introduced new fields "snmpv3_contextname", "snmpv3_authprotocol", "snmpv3_privprotocol", but this
			 * converter is for all 2.x. In 2.2+ they are exported, but for 2.0 they have to be added. It is, however,
			 * expected by 3.0 validator for all item prototypes, not just SNMP v3. In 2.2 a field "logtimefmt" was also
			 * added which is required by 3.0 validator.
			 */
			foreach (['snmpv3_contextname', 'snmpv3_authprotocol', 'snmpv3_privprotocol', 'logtimefmt'] as $field) {
				if (!array_key_exists($field, $item_prototype)) {
					$item_prototype[$field] = '';
				}
			}
		}
		unset($item_prototype);

		return $item_prototypes;
	}

	/**
	 * Convert graph elements.
	 *
	 * @param array $graphs
	 *
	 * @return array
	 */
	protected function convertGraphs(array $graphs) {
		foreach ($graphs as &$graph) {
			$graph['graph_items'] = $this->convertGraphItems($graph['graph_items']);

			if (zbx_is_int($graph['ymin_type_1'])) {
				if ($graph['ymin_type_1'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
					$graph['ymin_item_1']['key'] = $this->itemKeyConverter->convert($graph['ymin_item_1']['key']);
				}
			}

			if (zbx_is_int($graph['ymax_type_1'])) {
				if ($graph['ymax_type_1'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
					$graph['ymax_item_1']['key'] = $this->itemKeyConverter->convert($graph['ymax_item_1']['key']);
				}
			}
		}
		unset($graph);

		return $graphs;
	}

	/**
	 * Convert graph items elements.
	 *
	 * @param array $graph_items
	 *
	 * @return array
	 */
	protected function convertGraphItems(array $graph_items) {
		foreach ($graph_items as &$graph_item) {
			$graph_item['item']['key'] = $this->itemKeyConverter->convert($graph_item['item']['key']);
		}
		unset($graph_item);

		return $graph_items;
	}

	/**
	 * Convert trigger prototype elements.
	 *
	 * @param array $trigger_prototypes
	 *
	 * @return array
	 */
	protected function convertTriggerPrototypes(array $trigger_prototypes) {
		foreach ($trigger_prototypes as &$trigger_prototype) {
			$trigger_prototype['expression'] =
				$this->triggerExpressionConverter->convert($trigger_prototype['expression']);
		}
		unset($trigger_prototype);

		return $trigger_prototypes;
	}

	/**
	 * Convert filters from the 2.0 and 2.2 string representations to a 2.4 array.
	 *
	 * @param mixed $filter
	 * @return array
	 */
	protected function convertDiscoveryRuleFilter($filter) {
		// string filters were exported as "{#MACRO}:regex"
		if (is_string($filter)) {
			$new_filter = [
				'evaltype' => (string) CONDITION_EVAL_TYPE_AND_OR,
				'formula' => '',
				'conditions' => []
			];

			if (strpos($filter, ':') !== false && $filter !== ':') {
				list ($macro, $value) = explode(':', $filter);

				$new_filter['conditions'][] = [
					'macro' => $macro,
					'value' => $value,
					'operator' => (string) CONDITION_OPERATOR_REGEXP
				];
			}

			return $new_filter;
		}

		return $filter;
	}
}

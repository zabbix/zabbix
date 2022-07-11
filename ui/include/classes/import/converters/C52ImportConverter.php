<?php declare(strict_types = 0);
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
 * Converter for converting import data from 5.2 to 5.4.
 */
class C52ImportConverter extends CConverter {

	/**
	 * Convert import data from 5.2 to 5.4 version.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function convert(array $data): array {
		$data['zabbix_export']['version'] = '5.4';

		if (array_key_exists('value_maps', $data['zabbix_export'])) {
			/**
			 * Value maps conversion should be done before Template conversion,
			 * value map uuid will be generated during Template conversion it requires template name.
			 */
			$data['zabbix_export'] = self::convertValueMaps($data['zabbix_export']);
		}

		if (array_key_exists('hosts', $data['zabbix_export'])) {
			$data['zabbix_export']['hosts'] = self::convertHosts($data['zabbix_export']['hosts']);
		}

		if (array_key_exists('templates', $data['zabbix_export'])) {
			$data['zabbix_export']['templates'] = self::convertTemplates($data['zabbix_export']['templates']);
		}

		if (array_key_exists('maps', $data['zabbix_export'])) {
			$data['zabbix_export']['maps'] = self::convertMaps($data['zabbix_export']['maps']);
		}

		if (array_key_exists('triggers', $data['zabbix_export'])) {
			$data['zabbix_export']['triggers'] = self::convertTriggers($data['zabbix_export']['triggers'], true);
		}

		if (array_key_exists('graphs', $data['zabbix_export'])) {
			$data['zabbix_export']['graphs'] = self::convertGraphs($data['zabbix_export']['graphs']);
		}

		if (array_key_exists('groups', $data['zabbix_export'])) {
			foreach ($data['zabbix_export']['groups'] as &$group) {
				$group['uuid'] = generateUuidV4($group['name']);
			}
			unset($group);
		}

		return $data;
	}

	/**
	 * Convert hosts.
	 *
	 * @static
	 *
	 * @param array $hosts
	 *
	 * @return array
	 */
	private static function convertHosts(array $hosts): array {
		$tls_fields = array_flip(['tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject', 'tls_psk_identity',
			'tls_psk'
		]);

		foreach ($hosts as &$host) {
			$host = array_diff_key($host, $tls_fields);

			if (array_key_exists('items', $host)) {
				$host['items'] = self::convertItems($host['items'], $host['host']);
			}

			if (array_key_exists('interfaces', $host)) {
				$host['interfaces'] = self::convertInterfaces($host['interfaces']);
			}

			if (array_key_exists('discovery_rules', $host)) {
				$host['discovery_rules'] = self::convertDiscoveryRules($host['discovery_rules'], $host['host']);
			}

			if (array_key_exists('httptests', $host)) {
				$host['httptests'] = self::convertHttpTests($host['httptests']);
			}

			unset($host['applications']);
		}
		unset($host);

		return $hosts;
	}

	/**
	 * Convert templates.
	 *
	 * @static
	 *
	 * @param array $templates
	 *
	 * @return array
	 */
	private static function convertTemplates(array $templates): array {
		foreach ($templates as &$template) {
			$short_template_name = self::prepareTemplateName($template['template']);
			$template['uuid'] = generateUuidV4($short_template_name);

			if (array_key_exists('items', $template)) {
				$template['items'] = self::convertItems($template['items'], $template['template'],
					$short_template_name);
			}

			if (array_key_exists('discovery_rules', $template)) {
				$template['discovery_rules'] = self::convertDiscoveryRules($template['discovery_rules'],
					$template['template'], $short_template_name
				);
			}

			if (array_key_exists('httptests', $template)) {
				$template['httptests'] = self::convertHttpTests($template['httptests'], $short_template_name);
			}

			unset($template['applications']);

			if (array_key_exists('dashboards', $template)) {
				$template['dashboards'] = self::convertTemplateDashboards($template['dashboards'],
					$short_template_name);
			}

			if (array_key_exists('valuemaps', $template)) {
				foreach ($template['valuemaps'] as &$valuemap) {
					$valuemap['uuid'] = generateUuidV4($short_template_name.'/'.$valuemap['name']);
				}
				unset($valuemap);
			}
		}
		unset($template);

		return $templates;
	}

	/**
	 * Convert template dashboards.
	 *
	 * @static
	 *
	 * @param array  $dashboards
	 * @param string $short_template_name
	 *
	 * @return array
	 */
	private static function convertTemplateDashboards(array $dashboards, string $short_template_name): array {
		$result = [];

		foreach ($dashboards as $dashboard) {
			$dashboard_page = [];

			if (array_key_exists('widgets', $dashboard)) {
				$dashboard_page['widgets'] = $dashboard['widgets'];
			}

			$dashboard = [
				'uuid' => generateUuidV4($short_template_name.'/'.$dashboard['name']),
				'name' => $dashboard['name'],
				'pages' => [$dashboard_page]
			];

			$result[] = $dashboard;
		}

		return $result;
	}

	/**
	 * Convert interfaces.
	 *
	 * @static
	 *
	 * @param array $interfaces
	 *
	 * @return array
	 */
	private static function convertInterfaces(array $interfaces): array {
		$result = [];

		foreach ($interfaces as $interface) {
			if (array_key_exists('type', $interface)
					&& $interface['type'] === CXmlConstantName::SNMP
					&& array_key_exists('details', $interface)
					&& array_key_exists('version', $interface['details'])
					&& $interface['details']['version'] === CXmlConstantName::SNMPV3) {
				if (array_key_exists('authprotocol', $interface['details'])
						&& $interface['details']['authprotocol'] === CXmlConstantName::SHA) {
					$interface['details']['authprotocol'] = CXmlConstantName::SHA1;
				}

				if (array_key_exists('privprotocol', $interface['details'])
						&& $interface['details']['privprotocol'] === CXmlConstantName::AES) {
					$interface['details']['privprotocol'] = CXmlConstantName::AES128;
				}
			}

			$result[] = $interface;
		}

		return $result;
	}

	/**
	 * Convert discover rules.
	 *
	 * @param array       $discovery_rules
	 * @param string      $hostname
	 * @param string|null $short_template_name
	 *
	 * @return array
	 */
	private static function convertDiscoveryRules(array $discovery_rules, string $hostname,
			?string $short_template_name = null): array {
		$result = [];

		foreach ($discovery_rules as $discovery_rule) {
			if ($short_template_name !== null) {
				$discovery_rule['uuid'] = generateUuidV4($short_template_name.'/'.$discovery_rule['key']);
			}

			if (array_key_exists('host_prototypes', $discovery_rule)) {
				$discovery_rule['host_prototypes'] = self::convertHostPrototypes($discovery_rule['host_prototypes'],
					$discovery_rule['key'], $short_template_name);
			}

			if (array_key_exists('item_prototypes', $discovery_rule)) {
				$discovery_rule['item_prototypes'] = self::convertItemPrototypes($discovery_rule['item_prototypes'],
					$hostname, $discovery_rule['key'], $short_template_name
				);
			}

			if (array_key_exists('trigger_prototypes', $discovery_rule)) {
				$discovery_rule['trigger_prototypes'] = self::convertTriggers($discovery_rule['trigger_prototypes'],
					$short_template_name !== null, null, null, $discovery_rule['key']);
			}

			if (array_key_exists('graph_prototypes', $discovery_rule) && $short_template_name !== null) {
				foreach ($discovery_rule['graph_prototypes'] as &$graph_prototype) {
					$seed = $short_template_name.'/'.$discovery_rule['key'].'/'.$graph_prototype['name'];
					$graph_prototype['uuid'] = generateUuidV4($seed);
				}

				unset($graph_prototype);
			}

			$result[] = $discovery_rule;
		}

		return $result;
	}

	/**
	 * Convert item prototypes.
	 *
	 * @static
	 *
	 * @param array       $item_prototypes
	 * @param string      $hostname
	 * @param string|null $discovery_rule_key
	 * @param string|null $short_template_name
	 *
	 * @return array
	 */
	private static function convertItemPrototypes(array $item_prototypes, string $hostname,
			?string $discovery_rule_key = null, ?string $short_template_name = null): array {
		$result = [];
		$calculated_item_converter = new C52CalculatedItemConverter();
		$aggregate_item_key_converter = new C52AggregateItemKeyConverter(['lldmacros' => true]);

		foreach ($item_prototypes as $item_prototype) {
			if (array_key_exists('trigger_prototypes', $item_prototype)) {
				$item_prototype['trigger_prototypes'] = self::convertTriggers($item_prototype['trigger_prototypes'],
					$short_template_name !== null, $hostname, $item_prototype['key'], $discovery_rule_key
				);
			}

			if ($discovery_rule_key !== null && $short_template_name !== null) {
				$seed = $short_template_name . '/' . $discovery_rule_key . '/' . $item_prototype['key'];
				$item_prototype['uuid'] = generateUuidV4($seed);
			}

			$applications = array_key_exists('applications', $item_prototype) ? $item_prototype['applications'] : [];

			if (array_key_exists('application_prototypes', $item_prototype)) {
				$applications = array_merge($applications, $item_prototype['application_prototypes']);
			}

			if ($applications) {
				$i = 0;
				$item_prototype['tags'] = [];

				foreach (self::convertApplicationsToTags($applications) as $tag) {
					$item_prototype['tags']['tag'.($i > 0 ? $i : '')] = $tag;
					$i++;
				}

				unset($item_prototype['applications'], $item_prototype['application_prototypes']);
			}

			if (array_key_exists('type', $item_prototype)) {
				if ($item_prototype['type'] === CXmlConstantName::CALCULATED) {
					$item_prototype = $calculated_item_converter->convert($item_prototype);
				}
				else if ($item_prototype['type'] === CXmlConstantName::AGGREGATE) {
					$item_prototype['type'] = CXmlConstantName::CALCULATED;
					$item_prototype['params'] = $aggregate_item_key_converter->convert($item_prototype['key']);
				}
			}

			$result[] = $item_prototype;
		}

		return $result;
	}

	/**
	 * Convert items.
	 *
	 * @static
	 *
	 * @param array       $items
	 * @param string      $hostname
	 * @param string|null $short_template_name
	 *
	 * @return array
	 */
	private static function convertItems(array $items, string $hostname, ?string $short_template_name = null): array {
		$calculated_item_converter = new C52CalculatedItemConverter();
		$aggregate_item_key_converter = new C52AggregateItemKeyConverter();

		foreach ($items as &$item) {
			if (array_key_exists('applications', $item)) {
				$i = 0;
				$item['tags'] = [];

				foreach (self::convertApplicationsToTags($item['applications']) as $tag) {
					$item['tags']['tag'.($i > 0 ? $i : '')] = $tag;
					$i++;
				}

				unset($item['applications']);
			}

			if (array_key_exists('triggers', $item)) {
				$item['triggers'] = self::convertTriggers($item['triggers'], $short_template_name !== null, $hostname,
					$item['key']);
			}

			if ($short_template_name !== null) {
				$item['uuid'] = generateUuidV4($short_template_name.'/'.$item['key']);
			}

			if (array_key_exists('type', $item)) {
				if ($item['type'] === CXmlConstantName::CALCULATED) {
					$item = $calculated_item_converter->convert($item);
				}
				else if ($item['type'] === CXmlConstantName::AGGREGATE) {
					$item['type'] = CXmlConstantName::CALCULATED;
					$item['params'] = $aggregate_item_key_converter->convert($item['key']);
				}
			}
		}
		unset($item);

		return $items;
	}

	/**
	 * Convert host prototypes.
	 *
	 * @static
	 *
	 * @param array       $host_prototypes
	 * @param string|null $discovery_rule_key
	 * @param string|null $short_template_name
	 *
	 * @return array
	 */
	private static function convertHostPrototypes(array $host_prototypes, ?string $discovery_rule_key = null,
			?string $short_template_name = null): array {
		$result = [];

		foreach ($host_prototypes as $host_prototype) {
			if ($discovery_rule_key !== null && $short_template_name !== null) {
				$seed = $short_template_name.'/'.$discovery_rule_key.'/'.$host_prototype['host'];
				$host_prototype['uuid'] = generateUuidV4($seed);
			}

			if (array_key_exists('interfaces', $host_prototype)) {
				$host_prototype['interfaces'] = self::convertInterfaces($host_prototype['interfaces']);
			}

			$result[] = $host_prototype;
		}

		return $result;
	}

	private static function convertValueMaps(array $import): array {
		$valuemaps = zbx_toHash($import['value_maps'], 'name');
		unset($import['value_maps']);

		if (array_key_exists('hosts', $import)) {
			$import['hosts'] = self::moveValueMaps($import['hosts'], $valuemaps);
		}

		if (array_key_exists('templates', $import)) {
			$import['templates'] = self::moveValueMaps($import['templates'], $valuemaps);
		}

		return $import;
	}

	private static function moveValueMaps(array $hosts, array $valuemaps): array {
		foreach ($hosts as &$host) {
			$used_valuemaps = [];

			if (array_key_exists('items', $host)) {
				foreach ($host['items'] as $item) {
					if (array_key_exists('valuemap', $item)
							&& array_key_exists($item['valuemap']['name'], $valuemaps)
							&& !in_array($item['valuemap']['name'], $used_valuemaps)) {
						$host['valuemaps'][] = $valuemaps[$item['valuemap']['name']];
						$used_valuemaps[] = $item['valuemap']['name'];
					}
				}
			}

			if (array_key_exists('discovery_rules', $host)) {
				foreach ($host['discovery_rules'] as $discovery_rule) {
					if (!array_key_exists('item_prototypes', $discovery_rule)) {
						continue;
					}

					foreach ($discovery_rule['item_prototypes'] as $item_prototype) {
						if (array_key_exists('valuemap', $item_prototype)
								&& array_key_exists($item_prototype['valuemap']['name'], $valuemaps)
								&& !in_array($item_prototype['valuemap']['name'], $used_valuemaps)) {
							$host['valuemaps'][] = $valuemaps[$item_prototype['valuemap']['name']];
							$used_valuemaps[] = $item_prototype['valuemap']['name'];
						}
					}
				}
			}
		}
		unset($host);

		return $hosts;
	}

	/**
	 * Convert applications to item tags.
	 *
	 * @static
	 *
	 * @param array $applications
	 *
	 * @return array
	 */
	private static function convertApplicationsToTags(array $applications): array {
		$tags = [];

		foreach (array_values($applications) as $i => $app) {
			$tags['tag'.($i > 0 ? $i : '')] = [
				'tag' => 'Application',
				'value' => $app['name']
			];
		}

		return $tags;
	}

	/**
	 * Convert maps.
	 *
	 * @static
	 *
	 * @param array $maps
	 *
	 * @return array
	 */
	private static function convertMaps(array $maps): array {
		foreach ($maps as &$map) {
			foreach ($map['selements'] as &$selement) {
				$selement['evaltype'] = (string) CONDITION_EVAL_TYPE_AND_OR;

				if ($selement['application'] !== '') {
					$selement['tags'] = array_map(function ($tag) {
						return $tag + ['operator' => (string) TAG_OPERATOR_LIKE];
					}, self::convertApplicationsToTags([['name' => $selement['application']]]));
				}
				unset($selement['application']);

				if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
					$selement['elements'] = self::convertTriggers($selement['elements'], false);
				}
			}
			unset($selement);

			foreach ($map['links'] as &$link) {
				foreach ($link['linktriggers'] as &$linktrigger) {
					$linktrigger['trigger'] = self::convertTriggers([$linktrigger['trigger']], false)[0];
				}
				unset($linktrigger);
			}
			unset($link);
		}
		unset($map);

		return $maps;
	}

	/**
	 * Convert http tests.
	 *
	 * @static
	 *
	 * @param array       $httptests
	 * @param string|null $short_template_name
	 *
	 * @return array
	 */
	private static function convertHttpTests(array $httptests, ?string $short_template_name = null): array {
		foreach ($httptests as &$httptest) {
			if ($short_template_name !== null) {
				$httptest['uuid'] = generateUuidV4($short_template_name.'/'.$httptest['name']);
			}

			if (array_key_exists('application', $httptest)) {
				$httptest['tags'] = self::convertApplicationsToTags([$httptest['application']]);
				unset($httptest['application']);
			}
		}
		unset($httptest);

		return $httptests;
	}

	/**
	 * Convert array of triggers.
	 *
	 * @param array       $triggers
	 * @param bool        $generate_uuid
	 * @param string|null $host
	 * @param string|null $item
	 * @param string|null $discovery_rule_key
	 *
	 * @return array
	 */
	private static function convertTriggers(array $triggers, bool $generate_uuid, ?string $host = null,
			?string $item = null, ?string $discovery_rule_key = null): array {
		$expression_converter = new C52TriggerExpressionConverter();
		$event_name_converter = new C52EventNameConverter();

		$expression_parser = new CExpressionParser(['usermacros' => true]);

		foreach ($triggers as &$trigger) {
			$trigger['expression'] = $expression_converter->convert([
				'expression' => $trigger['expression'],
				'host' => $host,
				'item' => $item
			]);

			if (array_key_exists('event_name', $trigger) && $trigger['event_name'] !== '') {
				$trigger['event_name'] = $event_name_converter->convert($trigger['event_name']);
			}

			if (array_key_exists('recovery_expression', $trigger) && $trigger['recovery_expression'] !== '') {
				$trigger['recovery_expression'] = $expression_converter->convert([
					'expression' => $trigger['recovery_expression'],
					'host' => $host,
					'item' => $item
				]);
			}

			if (array_key_exists('dependencies', $trigger)) {
				$trigger['dependencies'] = self::convertTriggers($trigger['dependencies'], false);
			}

			// Generate UUID
			if ($generate_uuid) {
				$new_trigger_expression = $trigger['expression'];
				$seed = $discovery_rule_key !== null
					? [$discovery_rule_key.'/'.$trigger['name']]
					: [$trigger['name']];

				if ($expression_parser->parse($new_trigger_expression) == CParser::PARSE_SUCCESS) {
					foreach ($expression_parser->getResult()->getHosts() as $old_name) {
						$new_name = self::prepareTemplateName($old_name);
						$new_trigger_expression = triggerExpressionReplaceHost($new_trigger_expression, $old_name, $new_name);
					}
				}

				$seed[] = $new_trigger_expression;

				if (array_key_exists('recovery_expression', $trigger)) {
					$new_trigger_recovery_expression = $trigger['recovery_expression'];

					if ($expression_parser->parse($new_trigger_recovery_expression) == CParser::PARSE_SUCCESS) {
						foreach ($expression_parser->getResult()->getHosts() as $old_name) {
							$new_name = self::prepareTemplateName($old_name);
							$new_trigger_recovery_expression = triggerExpressionReplaceHost($new_trigger_recovery_expression,
								$old_name, $new_name
							);
						}
					}

					$seed[] = $new_trigger_recovery_expression;
				}

				$trigger['uuid'] = generateUuidV4(implode('/', $seed));
			}
		}
		unset($trigger);

		return $triggers;
	}

	/**
	 * Convert graphs.
	 *
	 * @static
	 *
	 * @param array $graphs
	 *
	 * @return array
	 */
	private static function convertGraphs(array $graphs): array {
		$result = [];

		foreach ($graphs as $graph) {
			$seed = [$graph['name']];
			$templates_names = [];

			foreach ($graph['graph_items'] as $graph_item) {
				$templates_names[] = $graph_item['item']['host'];
			}

			foreach ($templates_names as $template_name) {
				$seed[] = self::prepareTemplateName($template_name);
			}

			$graph['uuid'] = generateUuidV4(implode('/', $seed));

			$result[] = $graph;
		}

		return $result;
	}

	/**
	 * Rename template name to be used for UUID generation.
	 *
	 * @static
	 *
	 * @param string $template_name
	 *
	 * @return string
	 */
	private static function prepareTemplateName(string $template_name): string {
		$old_name_match = '/Template (APP|App|DB|Module|Net|OS|SAN|Server|Tel|VM) (?<mapped_name>.{3,})/';

		$new_template_name = preg_match($old_name_match, $template_name, $match)
			? $match['mapped_name']
			: $template_name;

		return str_replace('SNMPv2', 'SNMP', $new_template_name);
	}
}

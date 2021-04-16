<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
	public function convert($data): array {
		$data['zabbix_export']['version'] = '5.4';
		$templates_names = [];

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
			$templates_names = array_column($data['zabbix_export']['templates'], 'name');
			$data['zabbix_export']['templates'] = self::convertTemplates($data['zabbix_export']['templates']);
		}

		if (array_key_exists('graphs', $data['zabbix_export']) && $templates_names) {
			$data['zabbix_export']['graphs'] = self::convertGraphs($data['zabbix_export']['graphs'], $templates_names);
		}

		if (array_key_exists('groups', $data['zabbix_export']) && $templates_names) {
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

			if (array_key_exists('interfaces', $host)) {
				$host['interfaces'] = self::convertIntefaces($host['interfaces']);
			}

			if (array_key_exists('discovery_rules', $host)) {
				$host['discovery_rules'] = self::convertDiscoveryRules($host['discovery_rules']);
			}
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
		$result = [];
		$old_name_match = '/Template (APP|App|DB|Module|Net|OS|SAN|Server|Tel|VM) (?<mapped_name>.{3,})/';

		foreach ($templates as $template) {
			$tmpl_name = $template['name'];

			if (preg_match($old_name_match, $tmpl_name, $match)) {
				$tmpl_name = $match['mapped_name'];
			}

			if (array_key_exists('discovery_rules', $template)) {
				$template['discovery_rules'] = self::convertDiscoveryRules($template['discovery_rules'], $tmpl_name);
			}

			if (array_key_exists('dashboards', $template)) {
				$template['dashboards'] = self::convertTemplateDashboards($template['dashboards'], $tmpl_name);
			}

			if (array_key_exists('items', $template)) {
				foreach ($template['items'] as &$item) {
					$item['uuid'] = generateUuidV4($tmpl_name.'/'.$item['key']);

					if (!array_key_exists('triggers', $item)) {
						continue;
					}

					foreach ($item['triggers'] as &$trigger) {
						$seed = $trigger['name'].'/'.$trigger['expression'];

						if (array_key_exists('recovery_expression', $trigger)) {
							$seed .= '/'.$trigger['recovery_expression'];
						}

						$trigger['uuid'] = generateUuidV4($seed);
					}
					unset($trigger);
				}
				unset($item);
			}

			if (array_key_exists('httptests', $template)) {
				foreach ($template['httptests'] as &$httptest) {
					$httptest['uuid'] = generateUuidV4($tmpl_name.'/'.$httptest['name']);
				}
				unset($httptest);
			}

			if (array_key_exists('valuemaps', $template)) {
				foreach ($template['valuemaps'] as &$valuemap) {
					$valuemap['uuid'] = generateUuidV4($tmpl_name.'/'.$valuemap['name']);
				}
				unset($valuemap);
			}

			$template['uuid'] = generateUuidV4($tmpl_name);
			$result[] = $template;
		}

		return $result;
	}

	/**
	 * Convert template dashboards.
	 *
	 * @static
	 *
	 * @param array  $dashboards
	 * @param string $template_name
	 *
	 * @return array
	 */
	private static function convertTemplateDashboards(array $dashboards, string $template_name): array {
		$result = [];

		foreach ($dashboards as $dashboard) {
			$dashboard_page = [];

			if (array_key_exists('widgets', $dashboard)) {
				$dashboard_page['widgets'] = $dashboard['widgets'];
			}

			$dashboard = [
				'name' => $dashboard['name'],
				'pages' => [$dashboard_page],
				'uuid' => generateUuidV4($template_name.'/'.$dashboard['name'])
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
	private static function convertIntefaces(array $interfaces): array {
		$result = [];

		foreach ($interfaces as $interface) {
			$snmp_v3_convert = array_key_exists('type', $interface)
				&& $interface['type'] === CXmlConstantName::SNMP
				&& array_key_exists('details', $interface)
				&& array_key_exists('version', $interface['details'])
				&& $interface['details']['version'] === CXmlConstantName::SNMPV3;

			if ($snmp_v3_convert) {
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
	 * @static
	 *
	 * @param array  $discovery_rules
	 * @param string $template_name
	 *
	 * @return array
	 */
	private static function convertDiscoveryRules(array $discovery_rules, string $template_name = ''): array {
		$result = [];

		foreach ($discovery_rules as $discovery_rule) {
			if (array_key_exists('host_prototypes', $discovery_rule)) {
				$discovery_rule['host_prototypes'] = self::convertHostPrototypes($discovery_rule['host_prototypes']);
			}

			if ($template_name !== '') {
				if (array_key_exists('host_prototypes', $discovery_rule)) {
					foreach ($discovery_rule['host_prototypes'] as &$host_prototype) {
						$seed = $template_name.'/'.$discovery_rule['key'].'/'.$host_prototype['name'];
						$host_prototype['uuid'] = generateUuidV4($seed);
					}
					unset($host_prototype);
				}

				if (array_key_exists('item_prototypes', $discovery_rule)) {
					foreach ($discovery_rule['item_prototypes'] as &$item_prototype) {
						if (array_key_exists('trigger_prototypes', $item_prototype)) {
							foreach ($item_prototype['trigger_prototypes'] as &$trigger_prototype) {
								$seed = $discovery_rule['key'].'/'.$trigger_prototype['name'].'/'
									.$trigger_prototype['expression'];

								if (array_key_exists('recovery_expression', $trigger_prototype)) {
									$seed .= '/'.$trigger_prototype['recovery_expression'];
								}

								$trigger_prototype['uuid'] = generateUuidV4($seed);
							}
							unset($trigger_prototype);
						}

						$seed = $template_name.'/'.$discovery_rule['key'].'/'.$item_prototype['key'];
						$item_prototype['uuid'] = generateUuidV4($seed);
					}
					unset($item_prototype);
				}

				if (array_key_exists('graph_prototypes', $discovery_rule)) {
					foreach ($discovery_rule['graph_prototypes'] as &$graph_prototype) {
						$seed = $template_name.'/'.$discovery_rule['key'].'/'.$graph_prototype['name'];
						$graph_prototype['uuid'] = generateUuidV4($seed);
					}
					unset($graph_prototype);
				}

				$discovery_rule['uuid'] = generateUuidV4($template_name.'/'.$discovery_rule['key']);
			}

			$result[] = $discovery_rule;
		}

		return $result;
	}

	/**
	 * Convert host prototypes.
	 *
	 * @static
	 *
	 * @param array $host_prototypes
	 *
	 * @return array
	 */
	private static function convertHostPrototypes(array $host_prototypes): array {
		$result = [];

		foreach ($host_prototypes as $host_prototype) {
			if (array_key_exists('interfaces', $host_prototype)) {
				$host_prototype['interfaces'] = self::convertIntefaces($host_prototype['interfaces']);
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

	private static function moveValueMaps(array $hosts, array $valuemaps) {
		foreach ($hosts as &$host) {
			$used_valuemaps = [];

			if (array_key_exists('items', $host)) {
				foreach ($host['items'] as $item) {
					if (array_key_exists('valuemap', $item) && !in_array($item['valuemap']['name'], $used_valuemaps)) {
						if (array_key_exists($item['valuemap']['name'], $valuemaps)) {
							$host['valuemaps'][] = $valuemaps[$item['valuemap']['name']];
							$used_valuemaps[] = $item['valuemap']['name'];
						}
					}
				}
			}

			if (array_key_exists('discovery_rules', $host)) {
				foreach ($host['discovery_rules'] as $drule) {
					if (!array_key_exists('item_prototypes', $drule)) {
						continue;
					}

					foreach ($drule['item_prototypes'] as $item_prototype) {
						if (array_key_exists('valuemap', $item_prototype)
								&& !in_array($item_prototype['valuemap']['name'], $used_valuemaps)) {
							if (array_key_exists($item_prototype['valuemap']['name'], $valuemaps)) {
								$host['valuemaps'][] = $valuemaps[$item_prototype['valuemap']['name']];
								$used_valuemaps[] = $item_prototype['valuemap']['name'];
							}
						}
					}
				}
			}
		}
		unset($host);

		return $hosts;
	}

	/**
	 * Convert graphs.
	 *
	 * @static
	 *
	 * @param array $graphs
	 * @param array $import_templates_names
	 *
	 * @return array
	 */
	private static function convertGraphs(array $graphs, array $import_templates_names): array {
		$result = [];
		$old_name_match = '/Template (APP|App|DB|Module|Net|OS|SAN|Server|Tel|VM) (?<mapped_name>.{3,})/';

		foreach ($graphs as $graph) {
			$templates_names = array_intersect(array_column($graph['graph_items'], 'host'), $import_templates_names);

			if ($templates_names) {
				$seed = [$graph['name']];

				foreach ($templates_names as $template_name) {
					$seed[] = preg_match($old_name_match, $template_name, $match)
						? $match['mapped_name']
						: $template_name;
				}

				$graph['uuid'] = generateUuidV4(implode('/', $seed));
			}

			$result[] = $graph;
		}

		return $result;
	}
}

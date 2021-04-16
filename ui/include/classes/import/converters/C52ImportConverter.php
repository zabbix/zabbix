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

		if (array_key_exists('hosts', $data['zabbix_export'])) {
			$data['zabbix_export']['hosts'] = self::convertHosts($data['zabbix_export']['hosts']);
		}

		if (array_key_exists('templates', $data['zabbix_export'])) {
			$data['zabbix_export']['templates'] = self::convertTemplates($data['zabbix_export']['templates']);
		}

		if (array_key_exists('maps', $data['zabbix_export'])) {
			$data['zabbix_export']['maps'] = self::convertMaps($data['zabbix_export']['maps']);
		}

		if (array_key_exists('value_maps', $data['zabbix_export'])) {
			$data['zabbix_export'] = self::convertValueMaps($data['zabbix_export']);
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
				$host['items'] = self::convertItems($host['items']);
			}

			if (array_key_exists('interfaces', $host)) {
				$host['interfaces'] = self::convertIntefaces($host['interfaces']);
			}

			if (array_key_exists('discovery_rules', $host)) {
				$host['discovery_rules'] = self::convertDiscoveryRules($host['discovery_rules']);
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
			if (array_key_exists('items', $template)) {
				$template['items'] = self::convertItems($template['items']);
			}

			if (array_key_exists('discovery_rules', $template)) {
				$template['discovery_rules'] = self::convertDiscoveryRules($template['discovery_rules']);
			}

			if (array_key_exists('httptests', $template)) {
				$template['httptests'] = self::convertHttpTests($template['httptests']);
			}

			unset($template['applications']);

			if (array_key_exists('dashboards', $template)) {
				$template['dashboards'] = self::convertTemplateDashboards($template['dashboards']);
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
	 * @param array $dashboards
	 *
	 * @return array
	 */
	private static function convertTemplateDashboards(array $dashboards): array {
		$result = [];

		foreach ($dashboards as $dashboard) {
			$dashboard_page = [];

			if (array_key_exists('widgets', $dashboard)) {
				$dashboard_page['widgets'] = $dashboard['widgets'];
			}

			$dashboard = [
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
	 * Convert discover rules.
	 *
	 * @static
	 *
	 * @param array $discovery_rules
	 *
	 * @return array
	 */
	private static function convertDiscoveryRules(array $discovery_rules): array {
		$result = [];

		foreach ($discovery_rules as $discovery_rule) {
			if (array_key_exists('host_prototypes', $discovery_rule)) {
				$discovery_rule['host_prototypes'] = self::convertHostPrototypes($discovery_rule['host_prototypes']);
			}

			if (array_key_exists('item_prototypes', $discovery_rule)) {
				$discovery_rule['item_prototypes'] = self::convertItems($discovery_rule['item_prototypes']);
			}

			$result[] = $discovery_rule;
		}

		return $result;
	}

	/**
	 * Convert items.
	 *
	 * @static
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	private static function convertItems(array $items): array {
		foreach ($items as &$item) {
			$item['tags'] = [];
			$i = 0;
			if (array_key_exists('applications', $item)) {
				foreach (self::convertApplicationsToTags($item['applications']) as $tag) {
					$item['tags']['tag'.($i ? $i : '')] = $tag;
					$i++;
				}
			}
			if (array_key_exists('application_prototypes', $item)) {
				foreach (self::convertApplicationsToTags($item['application_prototypes']) as $tag) {
					$item['tags']['tag'.($i ? $i : '')] = $tag;
					$i++;
				}
			}
			unset($item['applications'], $item['application_prototypes']);
		}
		unset($item);

		return $items;
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
			$tags['tag'.($i ? $i : '')] = [
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
		foreach ($maps as $i => $map) {
			if (array_key_exists('selements', $map)) {
				foreach ($map['selements'] as $s => $selement) {
					$maps[$i]['selements'][$s]['evaltype'] = (string) CONDITION_EVAL_TYPE_AND_OR;

					if (array_key_exists('application', $selement) && $selement['application'] !== '') {
						$maps[$i]['selements'][$s]['tags'] = self::convertApplicationsToTags([[
							'name' => $selement['application']
						]]);

						$maps[$i]['selements'][$s]['tags'] = array_map(function ($tag) {
							return $tag + ['operator' => (string) TAG_OPERATOR_LIKE];
						}, $maps[$i]['selements'][$s]['tags']);
					}
					unset($maps[$i]['selements'][$s]['application']);
				}
			}
		}

		return $maps;
	}

	/**
	 * Convert http tests.
	 *
	 * @static
	 *
	 * @param array $http_tests
	 *
	 * @return array
	 */
	private static function convertHttpTests(array $http_tests): array {
		foreach ($http_tests as &$http_test) {
			if (array_key_exists('application', $http_test)) {
				$http_test['tags'] = self::convertApplicationsToTags([$http_test['application']]);
				unset($http_test['application']);
			}
		}
		unset($http_test);

		return $http_tests;
	}
}

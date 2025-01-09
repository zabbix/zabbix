<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * Converter for converting import data from 7.0 to 7.2.
 */
class C70ImportConverter extends CConverter {

	/**
	 * Convert import data from 7.0 to 7.2 version.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function convert(array $data): array {
		$data['zabbix_export']['version'] = '7.2';

		if (array_key_exists('templates', $data['zabbix_export'])) {
			$data['zabbix_export']['templates'] = self::convertTemplates($data['zabbix_export']['templates']);
		}

		if (array_key_exists('hosts', $data['zabbix_export'])) {
			$data['zabbix_export']['hosts'] = self::convertHosts($data['zabbix_export']['hosts']);
		}

		return $data;
	}

	/**
	 * Convert templates.
	 *
	 * @param array $templates
	 *
	 * @return array
	 */
	private static function convertTemplates(array $templates): array {
		foreach ($templates as &$template) {
			if (array_key_exists('dashboards', $template)) {
				$template['dashboards'] = self::convertDashboards($template['dashboards']);
			}
		}
		unset($template);

		return $templates;
	}

	/**
	 * Convert hosts.
	 *
	 * @param array $hosts
	 *
	 * @return array
	 */
	private static function convertHosts(array $hosts): array {
		foreach ($hosts as &$host) {
			if (!array_key_exists('inventory_mode', $host)) {
				$host['inventory_mode'] = CXmlConstantName::MANUAL;
			}

			if (array_key_exists('discovery_rules', $host)) {
				$host['discovery_rules'] = self::convertHostDiscoveryRules($host['discovery_rules']);
			}
		}
		unset($host);

		return $hosts;
	}

	/**
	 * Convert host discovery rules.
	 *
	 * @param array $discovery_rules
	 *
	 * @return array
	 */
	private static function convertHostDiscoveryRules(array $discovery_rules): array {
		foreach ($discovery_rules as &$discovery_rule) {
			if (array_key_exists('host_prototypes', $discovery_rule)) {
				$discovery_rule['host_prototypes'] = self::convertHostPrototypes($discovery_rule['host_prototypes']);
			}
		}
		unset($discovery_rule);

		return $discovery_rules;
	}

	/**
	 * Convert host prototypes.
	 *
	 * @param array $host_prototypes
	 *
	 * @return array
	 */
	private static function convertHostPrototypes(array $host_prototypes): array {
		foreach ($host_prototypes as &$host_prototype) {
			if (!array_key_exists('inventory_mode', $host_prototype)) {
				$host_prototype['inventory_mode'] = CXmlConstantName::MANUAL;
			}
		}
		unset($host_prototype);

		return $host_prototypes;
	}

	/**
	 * Convert dashboards.
	 *
	 * @param array $dashboards
	 *
	 * @return array
	 */
	private static function convertDashboards(array $dashboards): array {
		foreach ($dashboards as &$dashboard) {
			if (!array_key_exists('pages', $dashboard)) {
				continue;
			}

			foreach ($dashboard['pages'] as &$dashboard_page) {
				if (!array_key_exists('widgets', $dashboard_page)) {
					continue;
				}

				foreach ($dashboard_page['widgets'] as &$widget) {
					if ($widget['type'] === 'clock') {
						if (!array_key_exists('fields', $widget)) {
							continue;
						}

						$fields_to_remove = ['time_size', 'date_size', 'tzone_size'];

						$widget['fields'] = array_values(array_filter($widget['fields'],
							static function (array $field) use ($fields_to_remove): bool {
								return !in_array($field['name'], $fields_to_remove, true);
							}
						));
					}

					if ($widget['type'] === 'tophosts') {
						if (!array_key_exists('fields', $widget)) {
							continue;
						}

						foreach ($widget['fields'] as &$field) {
							if (preg_match('/^columns\.\d+\.history$/', $field['name'])) {
								$field['value'] = [
									'1' => '0',		// HISTORY_DATA_AUTO
									'2' => '1',		// HISTORY_DATA_HISTORY
									'3' => '2'		// HISTORY_DATA_TRENDS
								][$field['value']];
							}
						}
						unset($field);

						// Clear default values.
						$widget['fields'] = array_filter($widget['fields'], static function($field) {
							return !(
								(preg_match('/^columns\.\d+\.base_color$/', $field['name']) && $field['value'] === '')
								|| (preg_match('/^columns\.\d+\.display$/', $field['name']) && $field['value'] === '1')
								|| (preg_match('/^columns\.\d+\.decimal_places$/', $field['name']) && $field['value'] === '2')
								|| (preg_match('/^columns\.\d+\.aggregate_function/', $field['name']) && $field['value'] === '0')
								|| (preg_match('/^columns\.\d+\.history$/', $field['name']) && $field['value'] === '0')
							);
						});
					}
				}
				unset($widget);
			}
			unset($dashboard_page);
		}
		unset($dashboard);

		return $dashboards;
	}
}

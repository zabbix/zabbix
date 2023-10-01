<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * Converter for converting import data from 6.4 to 7.0.
 */
class C64ImportConverter extends CConverter {

	/**
	 * Convert import data from 6.4 to 7.0 version.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function convert(array $data): array {
		$data['zabbix_export']['version'] = '7.0';

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
			if (array_key_exists('items', $template)) {
				$template['items'] = self::convertItems($template['items']);
			}

			if (array_key_exists('discovery_rules', $template)) {
				$template['discovery_rules'] = self::convertDiscoveryRules($template['discovery_rules']);
			}

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
			if (array_key_exists('items', $host)) {
				$host['items'] = self::convertItems($host['items']);
			}

			if (array_key_exists('discovery_rules', $host)) {
				$host['discovery_rules'] = self::convertDiscoveryRules($host['discovery_rules']);
			}
		}
		unset($host);

		return $hosts;
	}

	/**
	 * Convert items.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	private static function convertItems(array $items): array {
		foreach ($items as &$item) {
			$item += ['type' => CXmlConstantName::ZABBIX_PASSIVE];

			if ($item['type'] !== CXmlConstantName::HTTP_AGENT && $item['type'] !== CXmlConstantName::SCRIPT) {
				unset($item['timeout']);
			}

			self::convertPreprocessing($item);
		}
		unset($item);

		return $items;
	}

	/**
	 * Convert discovery rules.
	 *
	 * @param array $discovery_rules
	 *
	 * @return array
	 */
	private static function convertDiscoveryRules(array $discovery_rules): array {
		foreach ($discovery_rules as &$discovery_rule) {
			$discovery_rule += ['type' => CXmlConstantName::ZABBIX_PASSIVE];

			if ($discovery_rule['type'] !== CXmlConstantName::HTTP_AGENT
					&& $discovery_rule['type'] !== CXmlConstantName::SCRIPT) {
				unset($discovery_rule['timeout']);
			}

			if (array_key_exists('item_prototypes', $discovery_rule)) {
				$discovery_rule['item_prototypes'] = self::convertItemPrototypes($discovery_rule['item_prototypes']);
			}
		}
		unset($discovery_rule);

		return $discovery_rules;
	}

	/**
	 * Convert item prototypes.
	 *
	 * @param array $item_prototypes
	 *
	 * @return array
	 */
	private static function convertItemPrototypes(array $item_prototypes): array {
		foreach ($item_prototypes as &$item_prototype) {
			$item_prototype += ['type' => CXmlConstantName::ZABBIX_PASSIVE];

			if ($item_prototype['type'] !== CXmlConstantName::HTTP_AGENT
					&& $item_prototype['type'] !== CXmlConstantName::SCRIPT) {
				unset($item_prototype['timeout']);
			}

			self::convertPreprocessing($item_prototype);
		}
		unset($item_prototype);

		return $item_prototypes;
	}

	/**
	 * @param array $item Item or item prototype.
	 */
	private static function convertPreprocessing(array &$item): void {
		if (!array_key_exists('preprocessing', $item)) {
			return;
		}

		foreach ($item['preprocessing'] as &$step) {
			if ($step['type'] == CXmlConstantName::CHECK_NOT_SUPPORTED) {
				$step['parameters'] = [(string) ZBX_PREPROC_MATCH_ERROR_ANY];
			}
		}
		unset($step);
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

			$reference_index = 0;

			foreach ($dashboard['pages'] as &$dashboard_page) {
				if (!array_key_exists('widgets', $dashboard_page)) {
					continue;
				}

				foreach ($dashboard_page['widgets'] as &$widget) {
					if (in_array($widget['type'], ['graph', 'svggraph', 'graphprototype'])) {
						$reference = self::createWidgetReference($reference_index++);

						if (!array_key_exists('fields', $widget)) {
							$widget['fields'] = [];
						}

						$widget['fields'][] = [
							'type' => 'STRING',
							'name' => 'reference',
							'value' => $reference
						];

						usort($widget['fields'],
							static function(array $widget_field_a, array $widget_field_b): int {
								return strnatcasecmp($widget_field_a['name'], $widget_field_b['name']);
							}
						);
					}

					if (array_key_exists('fields', $widget)) {
						foreach ($widget['fields'] as &$field) {
							$field['name'] = preg_replace('/^([a-z]+)\.([a-z_]+)\.(\d+)\.(\d+)$/',
								'$1.$3.$2.$4', $field['name']
							);
							$field['name'] = preg_replace('/^([a-z]+)\.([a-z_]+)\.(\d+)$/',
								'$1.$3.$2', $field['name']
							);
						}
						unset($field);
					}
				}
				unset($widget);
			}
			unset($dashboard_page);
		}
		unset($dashboard);

		return $dashboards;
	}

	/**
	 * Create a unique widget reference (required for broadcasting widgets).
	 *
	 * @param int $index  Unique reference index
	 *
	 * @return string
	 */
	private static function createWidgetReference(int $index): string {
		$reference = '';

		for ($i = 0; $i < 5; $i++) {
			$reference = chr(65 + $index % 26).$reference;
			$index = floor($index / 26);
		}

		return $reference;
	}
}

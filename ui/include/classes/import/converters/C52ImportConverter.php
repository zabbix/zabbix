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

		return $data;
	}

	/**
	 * Convert hosts and templates.
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

			if (array_key_exists('discovery_rules', $host)) {
				$host['discovery_rules'] = self::convertDiscoveryRules($host['discovery_rules']);
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

			unset($template['applications']);
		}
		unset($template);

		return $templates;
	}

	/**
	 * Convert discovery rules.
	 *
	 * @static
	 *
	 * @param array $discovery_rules
	 *
	 * @return array
	 */
	private static function convertDiscoveryRules(array $discovery_rules): array {
		foreach ($discovery_rules as &$discovery_rule) {
			if (array_key_exists('item_prototypes', $discovery_rule)) {
				$discovery_rule['item_prototypes'] = self::convertItems($discovery_rule['item_prototypes']);
			}
		}
		unset($discovery_rule);

		return $discovery_rules;
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
			if (array_key_exists('applications', $item)) {
				$item['tags'] = self::convertApplicationsToTags($item['applications']);
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

						$maps[$i]['selements'][$s]['tags'] = array_map(function($tag) {
							return $tag + ['operator' => (string) TAG_OPERATOR_LIKE];
						}, $maps[$i]['selements'][$s]['tags']);
					}
					unset($maps[$i]['selements'][$s]['application']);
				}
			}
		}

		return $maps;
	}
}

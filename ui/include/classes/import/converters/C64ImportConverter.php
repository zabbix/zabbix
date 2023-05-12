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

		if (array_key_exists('hosts', $data['zabbix_export'])) {
			$data['zabbix_export']['hosts'] = self::convertHosts($data['zabbix_export']['hosts']);
		}

		if (array_key_exists('templates', $data['zabbix_export'])) {
			$data['zabbix_export']['templates'] = self::convertTemplates($data['zabbix_export']['templates']);
		}

		return $data;
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
	 * @param array $templates
	 *
	 * @return array
	 */
	private static function convertTemplates(array $templates): array {
		foreach ($templates as &$template) {
			if (array_key_exists('discovery_rules', $template)) {
				$template['discovery_rules'] = self::convertDiscoveryRules($template['discovery_rules']);
			}
		}
		unset($template);

		return $templates;
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
			if (array_key_exists('filter', $discovery_rule) && !array_key_exists('conditions', $discovery_rule)) {
				unset($discovery_rule['filter']);
			}
		}
		unset($discovery_rule);

		return $discovery_rules;
	}
}

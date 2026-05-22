<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * Converter for converting import data from 7.4 to 8.0.
 */
class C74ImportConverter extends CConverter {

	/**
	 * Convert import data from 7.4 to 8.0 version.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function convert(array $data): array {
		$data['zabbix_export']['version'] = '8.0';

		if (array_key_exists('hosts', $data['zabbix_export'])) {
			$data['zabbix_export']['hosts'] = self::convertHosts($data['zabbix_export']['hosts']);
		}
		if (array_key_exists('templates', $data['zabbix_export'])) {
			$data['zabbix_export']['templates'] = self::convertTemplates($data['zabbix_export']['templates']);
		}

		return $data;
	}

	private static function convertHosts(array $hosts): array {
		foreach ($hosts as &$host) {
			if (array_key_exists('discovery_rules', $host)) {
				$host['discovery_rules'] = self::convertDiscoveryRules($host['discovery_rules']);
			}
			if (array_key_exists('items', $host)) {
				$host['items'] = self::convertItems($host['items']);
			}
		}
		unset($host);

		return $hosts;
	}

	private static function convertTemplates(array $templates): array {
		foreach ($templates as &$template) {
			if (array_key_exists('discovery_rules', $template)) {
				$template['discovery_rules'] = self::convertDiscoveryRules($template['discovery_rules']);
			}
			if (array_key_exists('items', $template)) {
				$template['items'] = self::convertItems($template['items']);
			}
		}
		unset($template);

		return $templates;
	}

	private static function convertDiscoveryRules(array $discovery_rules): array {
		foreach ($discovery_rules as &$discovery_rule) {
			if (array_key_exists('item_prototypes', $discovery_rule)) {
				$discovery_rule['item_prototypes'] = self::convertItems($discovery_rule['item_prototypes']);
			}

			if (self::shouldAssignDefaultTrapperHosts($discovery_rule)) {
				$discovery_rule['allowed_hosts'] = ZBX_DEFAULT_TRAPPER_HOSTS;
			}

		}
		unset($discovery_rule);

		return $discovery_rules;
	}

	private static function convertItems(array $items): array {
		foreach ($items as &$item) {
			if (self::shouldAssignDefaultTrapperHosts($item)) {
				$item['allowed_hosts'] = ZBX_DEFAULT_TRAPPER_HOSTS;
			}
		}
		unset($item);

		return $items;
	}

	/**
	 * Determines whether default trapper hosts should be assigned to the given item.
	 *
	 * @param array $item
	 * @return bool
	 */
	private static function shouldAssignDefaultTrapperHosts(array $item): bool {
		return !array_key_exists('allowed_hosts', $item)
			&& ($item['type'] == CXmlConstantName::TRAP || $item['type'] == CXmlConstantName::HTTP_AGENT
				&& array_key_exists('allow_traps', $item) && $item['allow_traps'] == CXmlConstantName::YES);
	}
}

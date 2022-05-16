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
 * Converter for converting import data from 6.0 to 6.2.
 */
class C60ImportConverter extends CConverter {

	/**
	 * Convert import data from 6.0 to 6.2 version.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function convert(array $data): array {
		$data['zabbix_export']['version'] = '6.2';

		if (array_key_exists('groups', $data['zabbix_export'])) {
			$data['zabbix_export'] = self::convertGroups($data['zabbix_export']);
		}

		return $data;
	}

	/**
	 * Convert groups.
	 *
	 * @param array $zabbix_export
	 *
	 * @return array
	 */
	private static function convertGroups(array $zabbix_export): array {
		$template_groups = [];
		$host_groups = [];

		if (array_key_exists('templates', $zabbix_export)) {
			foreach ($zabbix_export['templates'] as $template) {
				foreach ($template['groups'] as $group) {
					$template_groups[] = $group['name'];
				}

				if (array_key_exists('discovery_rules', $template)) {
					foreach ($template['discovery_rules'] as $discovery_rule) {
						if (array_key_exists('host_prototypes', $discovery_rule)) {
							foreach ($discovery_rule['host_prototypes'] as $host_prototype) {
								if (array_key_exists('group_links', $host_prototype)) {
									foreach ($host_prototype['group_links'] as $group_link) {
										$host_groups[] = $group_link['group']['name'];
									}
								}
							}
						}
					}
				}
			}
		}

		if (array_key_exists('hosts', $zabbix_export)) {
			foreach ($zabbix_export['hosts'] as $host) {
				foreach ($host['groups'] as $group) {
					$host_groups[] = $group['name'];
				}

				if (array_key_exists('discovery_rules', $host)) {
					foreach ($host['discovery_rules'] as $discovery_rule) {
						if (array_key_exists('host_prototypes', $discovery_rule)) {
							foreach ($discovery_rule['host_prototypes'] as $host_prototype) {
								if (array_key_exists('group_links', $host_prototype)) {
									foreach ($host_prototype['group_links'] as $group_link) {
										$host_groups[] = $group_link['group']['name'];
									}
								}
							}
						}
					}
				}
			}
		}

		foreach ($zabbix_export['groups'] as $group) {
			if (in_array($group['name'], $host_groups) || !in_array($group['name'], $template_groups)) {
				$zabbix_export['host_groups'][] = $group;
			}
			if (in_array($group['name'], $template_groups)) {
				$zabbix_export['template_groups'][] = $group;
			}
		}

		unset($zabbix_export['groups']);

		return $zabbix_export;
	}
}

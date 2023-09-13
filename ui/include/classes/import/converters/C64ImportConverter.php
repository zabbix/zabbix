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

					if ($widget['type'] === 'item' && array_key_exists('fields', $widget)) {
						foreach ($widget['fields'] as &$field) {
							$field['name'] = preg_replace('/^thresholds\.(threshold|color)\.(\d+)$/',
								'thresholds.$2.$1', $field['name']);
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
		$pow_base = 26;

		$num_reference_ABCDE = ($pow_base ** 3) + ($pow_base ** 2) * 2 + ($pow_base ** 1) * 3 + 4;
		$num_reference = $num_reference_ABCDE + $index;

		$reference = '';

		for ($i = 0; $i < 5; $i++) {
			$reference = chr(65 + $num_reference % $pow_base).$reference;
			$num_reference = floor($num_reference / $pow_base);
		}

		return $reference;
	}
}

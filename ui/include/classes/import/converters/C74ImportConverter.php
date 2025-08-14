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

		if (array_key_exists('templates', $data['zabbix_export'])) {
			$data['zabbix_export']['templates'] = self::convertTemplates($data['zabbix_export']['templates']);
		}

		return $data;
	}

	private static function convertTemplates(array $templates): array {
		foreach ($templates as &$template) {
			if (array_key_exists('dashboards', $template)) {
				$template['dashboards'] = self::convertDashboards($template['dashboards']);
			}
		}
		unset($template);

		return $templates;
	}

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
					if ($widget['type'] === 'geomap') {
						if (!array_key_exists('fields', $widget)) {
							continue;
						}

						$fields_to_add = ['clustering_mode' => '0', 'clustering_zoom_level' => '0'];
						$field_names = array_column($widget['fields'], 'name');

						foreach ($fields_to_add as $field_name => $field_value) {
							if (!in_array($field_name, $field_names)) {
								$widget['fields'][] = [
									'type' => CXmlConstantName::DASHBOARD_WIDGET_FIELD_TYPE_STRING,
									'name' => $field_name,
									'value' => $field_value,
								];
							}
						}
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

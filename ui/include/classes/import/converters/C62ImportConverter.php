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
 * Converter for converting import data from 6.2 to 6.4.
 */
class C62ImportConverter extends CConverter {

	private const DASHBOARD_WIDGET_TYPE = [
		CXmlConstantName::DASHBOARD_WIDGET_TYPE_CLOCK => CXmlConstantValue::DASHBOARD_WIDGET_TYPE_CLOCK,
		CXmlConstantName::DASHBOARD_WIDGET_TYPE_GRAPH_CLASSIC => CXmlConstantValue::DASHBOARD_WIDGET_TYPE_GRAPH_CLASSIC,
		CXmlConstantName::DASHBOARD_WIDGET_TYPE_GRAPH_PROTOTYPE => CXmlConstantValue::DASHBOARD_WIDGET_TYPE_GRAPH_PROTOTYPE,
		CXmlConstantName::DASHBOARD_WIDGET_TYPE_ITEM => CXmlConstantValue::DASHBOARD_WIDGET_TYPE_ITEM,
		CXmlConstantName::DASHBOARD_WIDGET_TYPE_PLAIN_TEXT => CXmlConstantValue::DASHBOARD_WIDGET_TYPE_PLAIN_TEXT,
		CXmlConstantName::DASHBOARD_WIDGET_TYPE_URL => CXmlConstantValue::DASHBOARD_WIDGET_TYPE_URL
	];

	/**
	 * Convert import data from 6.2 to 6.4 version.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function convert(array $data): array {
		$data['zabbix_export']['version'] = '6.4';

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

			foreach ($dashboard['pages'] as &$dashboard_page) {
				if (!array_key_exists('widgets', $dashboard_page)) {
					continue;
				}

				foreach ($dashboard_page['widgets'] as &$widget) {
					$widget['type'] = self::DASHBOARD_WIDGET_TYPE[$widget['type']];
				}
				unset($widget);
			}
			unset($dashboard_page);
		}
		unset($dashboard);

		return $dashboards;
	}
}

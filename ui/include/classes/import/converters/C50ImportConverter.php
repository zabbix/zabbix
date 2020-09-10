<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * Converter for converting import data from 5.0 to 5.2.
 */
class C50ImportConverter extends CConverter {

	/**
	 * Convert import data from 5.0 to 5.2 version.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function convert($data): array {
		$data['zabbix_export']['version'] = '5.2';

		if (array_key_exists('templates', $data['zabbix_export'])) {
			$data['zabbix_export']['templates'] = $this->convertScreensToDashboards(
				$data['zabbix_export']['templates']
			);
		}

		return $data;
	}

	/**
	 * Convert template screens to template dashboards.
	 *
	 * @param array $templates
	 *
	 * @return array
	 */
	protected function convertScreensToDashboards(array $templates): array {
		foreach ($templates as &$template) {
			if (!array_key_exists('screens', $template)) {
				continue;
			}

			$dashboards = [];

			foreach ($template['screens'] as $screen) {
				try {
					$dashboard = (new CTemplateScreenConverter($screen))->convertToTemplateDashboard();

					$key = 'dashboard';
					if (count($dashboards) > 0) {
						$key .= count($dashboards);
					}

					$dashboards[$key] = $dashboard;
				}
				catch (Exception $e) {
					// Skip screens containing errors.
				}
			}

			unset($template['screens']);

			if ($dashboards) {
				$template['dashboards'] = $dashboards;
			}
		}
		unset($template);

		return $templates;
	}
}

<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CControllerDashboardSort extends CController {

	protected function checkInput() {
		$fields = [
			'grid' =>	'fatal|required|json'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$widgets = [
				WIDGET_SYSTEM_STATUS, WIDGET_ZABBIX_STATUS, WIDGET_LAST_ISSUES, WIDGET_WEB_OVERVIEW,
				WIDGET_DISCOVERY_STATUS, WIDGET_HOST_STATUS, WIDGET_FAVOURITE_GRAPHS, WIDGET_FAVOURITE_MAPS,
				WIDGET_FAVOURITE_SCREENS
			];

			/*
			 * {
			 *     "0": {
			 *         "0": "stszbx_widget",
			 *         "1": "favgrph_widget",
			 *         "2": "favscr_widget",
			 *         "3": "favmap_widget"
			 *     },
			 *     "1": {
			 *         "0": "lastiss_widget",
			 *         "1": "webovr_widget",
			 *         "2": "dscvry_widget"
			 *     },
			 *     "2": {
			 *         "0": "syssum_widget",
			 *         "1": "hoststat_widget"
			 *     }
			 * }
			 */
			foreach (CJs::decodeJson($this->getInput('grid')) as $col => $column) {
				if (!CNewValidator::is_int32($col) || $col < 0 || $col > 2 || !is_array($column)) {
					$ret = false;
					break;
				}

				foreach ($column as $row => $widgetName) {
					if (!CNewValidator::is_int32($row) || $row < 0 || !is_string($widgetName)) {
						$ret = false;
						break 2;
					}

					$widgetName = str_replace('_widget', '', $widgetName);

					if (!in_array($widgetName, $widgets)) {
						$ret = false;
						break 2;
					}
				}
			}
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		foreach (CJs::decodeJson($this->getInput('grid')) as $col => $column) {
			foreach ($column as $row => $widgetName) {
				$widgetName = str_replace('_widget', '', $widgetName);

				CProfile::update('web.dashboard.widget.'.$widgetName.'.col', $col, PROFILE_TYPE_INT);
				CProfile::update('web.dashboard.widget.'.$widgetName.'.row', $row, PROFILE_TYPE_INT);
			}
		}

		$data = [
			'main_block' => ''
		];

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}

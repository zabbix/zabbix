<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
		$fields = array(
// Should be JSON validated
			'grid' =>				'fatal'
		);

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		foreach (CJs::decodeJson(getRequest('grid')) as $col => $column) {
			foreach ($column as $row => $widgetName) {
				$widgetName = str_replace('_widget', '', $widgetName);

				CProfile::update('web.dashboard.widget.'.$widgetName.'.col', $col, PROFILE_TYPE_INT);
				CProfile::update('web.dashboard.widget.'.$widgetName.'.row', $row, PROFILE_TYPE_INT);
			}
		}

		$data=array();

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}

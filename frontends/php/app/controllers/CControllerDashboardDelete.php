<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
 * controller to delete dashboards
 *
 */
class CControllerDashboardDelete extends CController {

	protected function checkInput() {
		$fields = [
			'dashboardids' =>	'required|array_db dashboard.dashboardid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		$dashboards = API::Dashboard()->get([
			'countOutput' => true,
			'dashboardids' => $this->getInput('dashboardids'),
			'editable' => true
		]);

		return ($dashboards == count($this->getInput('dashboardids')));
	}

	protected function doAction() {
		$dashboardids = $this->getInput('dashboardids');

		$result = (bool) API::Dashboard()->delete($dashboardids);

		$deleted = count($dashboardids);

		$response = new CControllerResponseRedirect('zabbix.php?action=dashboard.list&uncheck=1');

		if ($result) {
			$response->setMessageOk(_n('Dashboard deleted', 'Dashboards deleted', $deleted));
		}
		else {
			$response->setMessageError(_n('Cannot delete dashboard', 'Cannot delete dashboards', $deleted));
		}

		$this->setResponse($response);
	}
}

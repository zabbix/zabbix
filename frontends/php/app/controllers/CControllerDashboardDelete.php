<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
 * Controller to delete dashboards.
 */
class CControllerDashboardDelete extends CController {

	protected function checkInput() {
		$fields = [
			'dashboardids' =>	'required|array_db dashboard.dashboardid',
			'fullscreen' =>		'in 0,1'
		];

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
		$dashboardids = $this->getInput('dashboardids');

		$result = (bool) API::Dashboard()->delete($dashboardids);

		$deleted = count($dashboardids);

		$url = (new CUrl('zabbix.php'))
			->setArgument('action', 'dashboard.list')
			->setArgument('uncheck', '1');
		if ($this->getInput('fullscreen', 0)) {
			$url->setArgument('fullscreen', '1');
		}

		$response = new CControllerResponseRedirect($url->getUrl());

		if ($result) {
			$response->setMessageOk(_n('Dashboard deleted', 'Dashboards deleted', $deleted));
		}
		else {
			$response->setMessageError(_n('Cannot delete dashboard', 'Cannot delete dashboards', $deleted));
		}

		$this->setResponse($response);
	}
}

<?php
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
 * Controller to delete dashboards.
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
		return $this->checkAccess(CRoleHelper::UI_MONITORING_DASHBOARD)
			&& $this->checkAccess(CRoleHelper::ACTIONS_EDIT_DASHBOARDS);
	}

	protected function doAction() {
		$dashboardids = $this->getInput('dashboardids');

		$result = (bool) API::Dashboard()->delete($dashboardids);

		$deleted = count($dashboardids);

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))
				->setArgument('action', 'dashboard.list')
				->setArgument('page', CPagerHelper::loadPage('dashboard.list', null))
		);

		if ($result) {
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_n('Dashboard deleted', 'Dashboards deleted', $deleted));
		}
		else {
			CMessageHelper::setErrorTitle(_n('Cannot delete dashboard', 'Cannot delete dashboards', $deleted));
		}

		$this->setResponse($response);
	}
}

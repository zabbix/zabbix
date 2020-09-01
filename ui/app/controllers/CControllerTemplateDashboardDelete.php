<?php declare(strict_types=1);
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


class CControllerTemplateDashboardDelete extends CController {

	protected function checkInput() {
		$fields = [
			'dashboardids' => 'required|array_db dashboard.dashboardid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() < USER_TYPE_ZABBIX_ADMIN) {
			return false;
		}

		return count($this->getInput('dashboardids')) == API::TemplateDashboard()->get([
			'countOutput' => true,
			'dashboardids' => $this->getInput('dashboardids'),
			'editable' => true
		]);
	}

	protected function doAction() {
		$dashboardids = $this->getInput('dashboardids');

		$db_dashboards = API::TemplateDashboard()->get([
			'output' => ['templateid'],
			'dashboardids' => $dashboardids,
			'limit' => 1
		]);
		$result = API::TemplateDashboard()->delete($dashboardids);

		$deleted = count($dashboardids);

		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'template.dashboard.list')
			->setArgument('templateid', $db_dashboards[0]['templateid'])
			->setArgument('page', CPagerHelper::loadPage('template.dashboard.list', null))
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

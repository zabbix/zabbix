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


class CControllerDashboardPropertiesEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'dashboardid' =>		'db dashboard.dashboardid',
			'name'		  =>		'string',
			'userid'	  =>		'db users.userid',
			'new'		  =>		'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$errors = CJs::encodeJson(['errors' => [getMessages()->toString()]]);
			$this->setResponse(
				(new CControllerResponseData(['main_block' => $errors]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		if ($this->hasInput('new')) {
			$dashboard = CControllerDashboardView::getNewDashboard();
		}
		elseif ($this->hasInput('dashboardid')) {
			$dashboards = API::Dashboard()->get([
				'output' => ['name', 'dashboardid', 'userid'],
				'dashboardids' => $this->getInput('dashboardid'),
				'editable' => true,
				'preservekeys' => true
			]);

			$dashboard = reset($dashboards);
		}
		else {
			$dashboard = false;
		}

		if ($dashboard !== false) {
			if ($this->hasInput('userid') && $this->getInput('userid') == 0) {
				$user = null;
			}
			elseif ($this->hasInput('userid')) {
				$user = CControllerDashboardView::getOwnerData($this->getInput('userid'));
			}
			elseif (array_key_exists('userid', $dashboard)) {
				$user = CControllerDashboardView::getOwnerData($dashboard['userid']);
			}
			elseif (array_key_exists('owner', $dashboard)) {
				$user = $dashboard['owner'];
			}

			// Prepare data for view.
			$data = [
				'dashboard' => [
					'name' => $this->getInput('name', $dashboard['name']),
					'dashboardid' => $dashboard['dashboardid'],
					'owner' => $user
				],
				'user' => [
					'debug_mode' => $this->getDebugMode()
				]
			];

			$this->setResponse(new CControllerResponseData($data));
		}
		else {
			error(_('No permissions to referred object or it does not exist!'));

			$errors = CJs::encodeJson(['errors' => [getMessages()->toString()]]);
			$this->setResponse(
				(new CControllerResponseData(['main_block' => $errors]))->disableView()
			);
		}
	}
}

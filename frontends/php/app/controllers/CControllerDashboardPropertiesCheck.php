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


class CControllerDashboardPropertiesCheck extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'dashboardid' =>		'required|db dashboard.dashboardid',
			'userid'	  =>		'required|db users.userid',
			'name'		  =>		'string|not_empty'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => CJs::encodeJson(['errors' => getMessages()->toString()])
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		// Dashboard with ID 0 is considered as newly created dashboard.
		if ($this->getInput('dashboardid') != 0) {
			$dashboards = API::Dashboard()->get([
				'output' => [],
				'dashboardids' => $this->getInput('dashboardid'),
				'editable' => true
			]);

			if (!$dashboards) {
				error(_('No permissions to referred object or it does not exist!'));
			}
		}

		if (!hasErrorMesssages()) {
			$users = API::User()->get([
				'output' => [],
				'userids' => $this->getInput('userid')
			]);

			if (!$users) {
				error(_s('User with ID "%1$s" is not available.', $this->getInput('userid')));
			}
		}

		$output = [];
		if (($messages = getMessages()) !== null) {
			$output = [
				'errors' => $messages->toString()
			];
		}

		$this->setResponse(
			(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]))->disableView()
		);
	}
}

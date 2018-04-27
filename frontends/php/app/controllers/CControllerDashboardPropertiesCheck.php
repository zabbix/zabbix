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
			'name'		  =>		'string|not_empty',
			'userid'	  =>		'required|db users.userid'
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
		if ($this->getInput('dashboardid') != 0) {
			$dashboards = API::Dashboard()->get([
				'output' => [],
				'dashboardids' => $this->getInput('dashboardid'),
				'editable' => true
			]);

			$dashboard = reset($dashboards);
		}
		else {
			$dashboard = true;
		}

		if ($dashboard === false) {
			error(_('No permissions to referred object or it does not exist!'));
		}
		else {
			if (!$this->hasInput('userid') || $this->getInput('userid') == 0) {
				error(_s('Incorrect value for field "%1$s": %2$s.', 'userid', _('cannot be empty')));
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

<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
			'template' =>		'in 1',
			'userid' =>			'db users.userid',
			'name' =>			'required|db dashboard.name',
			'display_period' =>	'required|db dashboard.display_period|in '.implode(',', DASHBOARD_DISPLAY_PERIODS),
			'auto_start' =>		'required|db dashboard.auto_start|in 0,1'
		];

		$ret = $this->validateInput($fields);

		if (!$this->hasInput('template') && !$this->hasInput('userid')) {
			error(_s('Field "%1$s" is mandatory.', 'userid'));

			$ret = false;
		}

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->hasInput('template')) {
			return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
		}
		else {
			return $this->checkAccess(CRoleHelper::UI_MONITORING_DASHBOARD)
				&& $this->checkAccess(CRoleHelper::ACTIONS_EDIT_DASHBOARDS);
		}
	}

	protected function doAction() {
		$data = [
			'dashboard' => [
				'template' => $this->hasInput('template'),
				'name' => $this->getInput('name'),
				'display_period' => (int) $this->getInput('display_period'),
				'auto_start' => (int) $this->getInput('auto_start')
			],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		if (!$this->hasInput('template')) {
			$userid = $this->getInput('userid');

			$data['dashboard']['owner'] = [
				'id' => $userid,
				'name' => CDashboardHelper::getOwnerName($userid)
			];
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}

<?php declare(strict_types = 0);
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


class CControllerPopupScheduledReportList extends CController {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'dashboardid' => 'required|db dashboard.dashboardid'
		];

		$ret = $this->validateInput($fields);

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
		return $this->checkAccess(CRoleHelper::UI_REPORTS_SCHEDULED_REPORTS);
	}

	protected function doAction() {
		$data = [
			'title' => _('Related reports'),
			'allowed_edit' => $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'reports' => API::Report()->get([
				'output' => ['reportid', 'userid', 'name', 'status', 'period', 'cycle', 'active_till', 'state',
					'lastsent', 'info'
				],
				'filter' => ['dashboardid' => $this->getInput('dashboardid')]
			])
		];

		if ($data['reports']) {
			CArrayHelper::sort($data['reports'], ['name']);

			$users = API::User()->get([
				'output' => ['userid', 'username', 'name', 'surname'],
				'userids' => array_column($data['reports'], 'userid'),
				'preservekeys' => true
			]);

			foreach ($data['reports'] as &$report) {
				$report['owner'] = array_key_exists($report['userid'], $users)
					? getUserFullname($users[$report['userid']])
					: _('Inaccessible user');
			}
			unset($report);
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}

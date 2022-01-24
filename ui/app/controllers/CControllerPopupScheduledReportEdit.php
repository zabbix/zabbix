<?php declare(strict_types = 1);
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


class CControllerPopupScheduledReportEdit extends CController {

	protected $report = [];

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'dashboardid' => 'required|db report.dashboardid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['errors' => getMessages()->toString()])
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_REPORTS_SCHEDULED_REPORTS)
			&& $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS);
	}

	protected function doAction() {
		$db_defaults = DB::getDefaults('report');
		$current_user_name = getUserFullname(CWebUser::$data);
		$data = [
			'userid' => CWebUser::$data['userid'],
			'name' => $db_defaults['name'],
			'dashboardid' => $this->getInput('dashboardid', 0),
			'period' => $db_defaults['period'],
			'cycle' => $db_defaults['cycle'],
			'hours' => '00',
			'minutes' => '00',
			'weekdays' => 127,
			'active_since' => '',
			'active_till' => '',
			'subject' => '',
			'message' => '',
			'subscriptions' => [[
				'recipientid' => CWebUser::$data['userid'],
				'recipient_type' => ZBX_REPORT_RECIPIENT_TYPE_USER,
				'recipient_name' => $current_user_name,
				'recipient_inaccessible' => 0,
				'creatorid' => CWebUser::$data['userid'],
				'creator_type' => ZBX_REPORT_CREATOR_TYPE_USER,
				'creator_name' => $current_user_name,
				'creator_inaccessible' => 0,
				'exclude' => ZBX_REPORT_EXCLUDE_USER_FALSE
			]],
			'description' => $db_defaults['description'],
			'status' => $db_defaults['status'],
			'form_refresh' => 0,
			'allowed_edit' => $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS)
		];

		$this->getInputs($data, ['name', 'period', 'cycle', 'hours', 'minutes', 'active_since', 'active_till',
			'subject', 'message', 'description', 'status', 'form_refresh'
		]);

		if ($data['form_refresh'] != 0) {
			$data['userid'] = $this->getInput('userid', 0);
			$data['dashboardid'] = $this->getInput('dashboardid', 0);
			$data['weekdays'] = array_sum($this->getInput('weekdays', []));
			$data['subscriptions'] = $this->getInput('subscriptions', []);
		}

		$data['ms_user'] = [];
		if ($data['userid'] != 0) {
			if (CWebUser::$data['userid'] != $data['userid']) {
				$users = API::User()->get([
					'output' => ['username', 'name', 'surname'],
					'userids' => $data['userid']
				]);

				$user_name = $users ? getUserFullname($users[0]) : _('Inaccessible user');
			}
			else {
				$user_name = $current_user_name;
			}

			$data['ms_user'] = [['id' => $data['userid'], 'name' => $user_name]];
		}

		$data['ms_dashboard'] = [];
		if ($data['dashboardid'] != 0) {
			$dashboards = API::Dashboard()->get([
				'output' => ['name'],
				'dashboardids' => $data['dashboardid']
			]);

			$dashboard_name = $dashboards ? $dashboards[0]['name'] : _('Inaccessible dashboard');

			$data['ms_dashboard'] = [['id' => $data['dashboardid'], 'name' => $dashboard_name]];
		}

		$this->setResponse(new CControllerResponseData([
			'title' => _('Add scheduled report'),
			'user' => ['debug_mode' => $this->getDebugMode()]
		] + $data));
	}
}

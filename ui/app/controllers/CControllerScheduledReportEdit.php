<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CControllerScheduledReportEdit extends CController {

	protected $report = [];

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'reportid' =>		'db report.reportid',
			'userid' =>			'db report.userid',
			'name' =>			'db report.name',
			'dashboardid' =>	'db report.dashboardid',
			'period' =>			'db report.period|in '.implode(',', [ZBX_REPORT_PERIOD_DAY, ZBX_REPORT_PERIOD_WEEK, ZBX_REPORT_PERIOD_MONTH, ZBX_REPORT_PERIOD_YEAR]),
			'cycle' =>			'db report.cycle|in '.implode(',', [ZBX_REPORT_CYCLE_DAILY, ZBX_REPORT_CYCLE_WEEKLY, ZBX_REPORT_CYCLE_MONTHLY, ZBX_REPORT_CYCLE_YEARLY]),
			'weekdays' =>		'array',
			'hours' =>			'int32',
			'minutes' =>		'int32',
			'active_since' =>	'string',
			'active_till' =>	'string',
			'subject' =>		'string',
			'message' =>		'string',
			'subscriptions' =>	'array',
			'description' =>	'db report.description',
			'status' =>			'db report.status|in '.ZBX_REPORT_STATUS_DISABLED.','.ZBX_REPORT_STATUS_ENABLED,
			'form_refresh' =>	'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_REPORTS_SCHEDULED_REPORTS)
				|| (!$this->hasInput('reportid')
					&& !$this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS))) {
			return false;
		}

		if ($this->hasInput('reportid') && !$this->hasInput('form_refresh')) {
			if (!$this->getInput('reportid', 0)) {
				return false;
			}

			$reports = API::Report()->get([
				'output' => ['reportid', 'userid', 'name', 'description', 'status', 'dashboardid', 'period', 'cycle',
					'weekdays', 'start_time', 'active_since', 'active_till', 'subject', 'message'
				],
				'selectUsers' => ['userid', 'access_userid', 'exclude'],
				'selectUserGroups' => ['usrgrpid', 'access_userid'],
				'reportids' => $this->getInput('reportid')
			]);

			if (!$reports) {
				return false;
			}

			$this->report = $reports[0];
		}

		return true;
	}

	protected function doAction() {
		$db_defaults = DB::getDefaults('report');
		$current_user = getUserFullname(CWebUser::$data);
		$data = [
			'reportid' => 0,
			'userid' => CWebUser::$data['userid'],
			'name' => $db_defaults['name'],
			'dashboardid' => 0,
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
				'recipient_name' => $current_user,
				'creator_type' => ZBX_REPORT_CREATOR_TYPE_USER,
				'exclude' => ZBX_REPORT_EXCLUDE_USER_FALSE
			]],
			'ms_user' => [['id' => CWebUser::$data['userid'], 'name' => $current_user]],
			'ms_dashboard' => [],
			'description' => $db_defaults['description'],
			'status' => $db_defaults['status'],
			'form_refresh' => 0,
			'allowed_edit' => $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS)
		];

		if ($this->hasInput('reportid') && !$this->hasInput('form_refresh')) {
			$data['reportid'] = $this->report['reportid'];
			$data['userid'] = $this->report['userid'];
			$data['name'] = $this->report['name'];
			$data['dashboardid'] = $this->report['dashboardid'];
			$data['period'] = $this->report['period'];
			$data['cycle'] = $this->report['cycle'];
			$data['hours'] = sprintf("%02d", floor($this->report['start_time'] / SEC_PER_HOUR));
			$data['minutes'] = sprintf("%02d", floor(($this->report['start_time'] % SEC_PER_HOUR) / SEC_PER_MIN));
			$data['weekdays'] = $this->report['weekdays'];
			$data['active_since'] = ($this->report['active_since'] > 0)
				? date(ZBX_DATE, (int) $this->report['active_since'])
				: '';
			$data['active_till'] = ($this->report['active_till'] > 0)
				? date(ZBX_DATE, (int) $this->report['active_till'])
				: '';
			$data['subject'] = $this->report['subject'];
			$data['message'] = $this->report['message'];
			$data['description'] = $this->report['description'];
			$data['status'] = $this->report['status'];
			$data['subscriptions'] = [];

			$userids = ($data['userid'] == CWebUser::$data['userid'])
				? array_column($this->report['users'], 'userid')
				: array_unique(array_merge([$data['userid']], array_column($this->report['users'], 'userid')));

			$db_users = API::User()->get([
				'output' => ['username', 'name', 'surname'],
				'userids' => $userids,
				'preservekeys' => true
			]);

			foreach ($this->report['users'] as $user) {
				$subscription = [
					'recipientid' => $user['userid'],
					'recipient_type' => ZBX_REPORT_RECIPIENT_TYPE_USER,
					'recipient_name' => _('Inaccessible user'),
					'creator_type' => ($user['access_userid'] == CWebUser::$data['userid'])
						? ZBX_REPORT_CREATOR_TYPE_USER
						: ZBX_REPORT_CREATOR_TYPE_RECIPIENT,
					'exclude' => $user['exclude']
				];

				if (array_key_exists($user['userid'], $db_users)) {
					$subscription['recipient_name'] = getUserFullname($db_users[$user['userid']]);
				}

				$data['subscriptions'][] = $subscription;
			}

			if ($data['userid'] != CWebUser::$data['userid']) {
				$data['ms_user'] = [[
					'id' => $data['userid'],
					'name' => array_key_exists($data['userid'], $db_users)
						? getUserFullname($db_users[$data['userid']])
						: _('Inaccessible user')
				]];
			}

			$db_usrgrps = API::UserGroup()->get([
				'output' => ['name'],
				'usrgrpids' => array_column($this->report['user_groups'], 'usrgrpid'),
				'preservekeys' => true
			]);

			foreach ($this->report['user_groups'] as $user_group) {
				$subscription = [
					'recipientid' => $user_group['usrgrpid'],
					'recipient_type' => ZBX_REPORT_RECIPIENT_TYPE_USER_GROUP,
					'recipient_name' => _('Inaccessible user group'),
					'creator_type' => ($user_group['access_userid'] == CWebUser::$data['userid'])
						? ZBX_REPORT_CREATOR_TYPE_USER
						: ZBX_REPORT_CREATOR_TYPE_RECIPIENT
				];

				if (array_key_exists($user_group['usrgrpid'], $db_usrgrps)) {
					$subscription['recipient_name'] = $db_usrgrps[$user_group['usrgrpid']]['name'];
				}

				$data['subscriptions'][] = $subscription;
			}

			CArrayHelper::sort($data['subscriptions'], ['recipient_name']);
		}

		$this->getInputs($data, ['reportid', 'name', 'dashboardid', 'period', 'cycle', 'hours', 'minutes',
			'active_since', 'active_till', 'subject', 'message', 'description', 'status', 'form_refresh'
		]);

		if ($data['form_refresh'] != 0) {
			$data['userid'] = $this->getInput('userid', 0);
			$data['weekdays'] = array_sum($this->getInput('weekdays', []));
			$data['subscriptions'] = $this->getInput('subscriptions', []);
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
					$user_name = getUserFullname(CWebUser::$data);
				}

				$data['ms_user'] = [['id' => $data['userid'], 'name' => $user_name]];
			}
		}

		if ($data['dashboardid'] != 0) {
			$dashboards = API::Dashboard()->get([
				'output' => ['name'],
				'dashboardids' => $data['dashboardid']
			]);

			$dashboard_name = $dashboards ? $dashboards[0]['name'] : _('Inaccessible dashboard');

			$data['ms_dashboard'] = [['id' => $data['dashboardid'], 'name' => $dashboard_name]];
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Scheduled reports'));
		$this->setResponse($response);
	}
}

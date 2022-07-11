<?php declare(strict_types = 0);
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


class CControllerScheduledReportEdit extends CController {

	protected $report = [];

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'reportid' =>			'db report.reportid',
			'userid' =>				'db report.userid',
			'name' =>				'db report.name',
			'dashboardid' =>		'db report.dashboardid',
			'old_dashboardid' =>	'db report.dashboardid',
			'period' =>				'db report.period|in '.implode(',', [ZBX_REPORT_PERIOD_DAY, ZBX_REPORT_PERIOD_WEEK, ZBX_REPORT_PERIOD_MONTH, ZBX_REPORT_PERIOD_YEAR]),
			'cycle' =>				'db report.cycle|in '.implode(',', [ZBX_REPORT_CYCLE_DAILY, ZBX_REPORT_CYCLE_WEEKLY, ZBX_REPORT_CYCLE_MONTHLY, ZBX_REPORT_CYCLE_YEARLY]),
			'weekdays' =>			'array',
			'hours' =>				'int32',
			'minutes' =>			'int32',
			'active_since' =>		'string',
			'active_till' =>		'string',
			'subject' =>			'string',
			'message' =>			'string',
			'subscriptions' =>		'array',
			'description' =>		'db report.description',
			'status' =>				'db report.status|in '.ZBX_REPORT_STATUS_DISABLED.','.ZBX_REPORT_STATUS_ENABLED,
			'form_refresh' =>		'int32'
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
		$current_user_name = getUserFullname(CWebUser::$data);
		$data = [
			'reportid' => 0,
			'userid' => CWebUser::$data['userid'],
			'name' => $db_defaults['name'],
			'dashboardid' => 0,
			'old_dashboardid' => 0,
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
			'ms_user' => [['id' => CWebUser::$data['userid'], 'name' => $current_user_name]],
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
			$data['old_dashboardid'] = $this->report['dashboardid'];
			$data['period'] = $this->report['period'];
			$data['cycle'] = $this->report['cycle'];
			$data['hours'] = sprintf("%02d", floor($this->report['start_time'] / SEC_PER_HOUR));
			$data['minutes'] = sprintf("%02d", floor(($this->report['start_time'] % SEC_PER_HOUR) / SEC_PER_MIN));
			$data['weekdays'] = ($this->report['cycle'] == ZBX_REPORT_CYCLE_WEEKLY) ? $this->report['weekdays'] : 127;
			$data['active_since'] = $this->report['active_since'];
			$data['active_till'] = $this->report['active_till'];
			$data['subject'] = $this->report['subject'];
			$data['message'] = $this->report['message'];
			$data['description'] = $this->report['description'];
			$data['status'] = $this->report['status'];
			$data['subscriptions'] = [];

			$userids = [$data['userid'] => true];
			$usrgrpids = [];

			foreach ($this->report['users'] as $user) {
				$userids[$user['userid']] = true;
				$userids[$user['access_userid']] = true;
			}

			foreach ($this->report['user_groups'] as $usrgrp) {
				$usrgrpids[$usrgrp['usrgrpid']] = true;
				$userids[$usrgrp['access_userid']] = true;
			}

			unset($userids[CWebUser::$data['userid']]);

			$db_users = $userids
				? API::User()->get([
					'output' => ['username', 'name', 'surname'],
					'userids' => array_keys($userids),
					'preservekeys' => true
				])
				: [];

			foreach ($this->report['users'] as $user) {
				$subscription = [
					'recipientid' => $user['userid'],
					'recipient_type' => ZBX_REPORT_RECIPIENT_TYPE_USER,
					'recipient_name' => _('Inaccessible user'),
					'recipient_inaccessible' => 1,
					'creatorid' => $user['access_userid'],
					'creator_type' => ZBX_REPORT_CREATOR_TYPE_USER,
					'creator_name' => _('Inaccessible user'),
					'creator_inaccessible' => 1,
					'exclude' => $user['exclude']
				];

				if ($user['userid'] == CWebUser::$data['userid']) {
					$subscription['recipient_name'] = $current_user_name;
					$subscription['recipient_inaccessible'] = 0;
				}
				elseif (array_key_exists($user['userid'], $db_users)) {
					$subscription['recipient_name'] = getUserFullname($db_users[$user['userid']]);
					$subscription['recipient_inaccessible'] = 0;
				}

				if ($user['access_userid'] == 0) {
					$subscription['creator_type'] = ZBX_REPORT_CREATOR_TYPE_RECIPIENT;
					$subscription['creator_name'] = _('Recipient');
					$subscription['creator_inaccessible'] = $subscription['recipient_inaccessible'];
				}
				elseif ($user['access_userid'] == CWebUser::$data['userid']) {
					$subscription['creator_name'] = $current_user_name;
					$subscription['creator_inaccessible'] = 0;
				}
				elseif (array_key_exists($user['access_userid'], $db_users)) {
					$subscription['creator_name'] = getUserFullname($db_users[$user['access_userid']]);
					$subscription['creator_inaccessible'] = 0;
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

			$db_usrgrps = $usrgrpids
				? API::UserGroup()->get([
					'output' => ['name'],
					'usrgrpids' => array_keys($usrgrpids),
					'preservekeys' => true
				])
				: [];

			foreach ($this->report['user_groups'] as $usrgrp) {
				$subscription = [
					'recipientid' => $usrgrp['usrgrpid'],
					'recipient_type' => ZBX_REPORT_RECIPIENT_TYPE_USER_GROUP,
					'recipient_name' => _('Inaccessible user group'),
					'recipient_inaccessible' => 1,
					'creatorid' => $usrgrp['access_userid'],
					'creator_type' => ZBX_REPORT_CREATOR_TYPE_USER,
					'creator_name' => _('Inaccessible user'),
					'creator_inaccessible' => 1
				];

				if (array_key_exists($usrgrp['usrgrpid'], $db_usrgrps)) {
					$subscription['recipient_name'] = $db_usrgrps[$usrgrp['usrgrpid']]['name'];
					$subscription['recipient_inaccessible'] = 0;
				}

				if ($usrgrp['access_userid'] == 0) {
					$subscription['creator_type'] = ZBX_REPORT_CREATOR_TYPE_RECIPIENT;
					$subscription['creator_name'] = _('Recipient');
					$subscription['creator_inaccessible'] = $subscription['recipient_inaccessible'];
				}
				elseif ($usrgrp['access_userid'] == CWebUser::$data['userid']) {
					$subscription['creator_name'] = $current_user_name;
					$subscription['creator_inaccessible'] = 0;
				}
				elseif (array_key_exists($usrgrp['access_userid'], $db_users)) {
					$subscription['creator_name'] = getUserFullname($db_users[$usrgrp['access_userid']]);
					$subscription['creator_inaccessible'] = 0;
				}

				$data['subscriptions'][] = $subscription;
			}

			CArrayHelper::sort($data['subscriptions'], ['recipient_name']);
		}

		$this->getInputs($data, ['reportid', 'name', 'dashboardid', 'old_dashboardid', 'period', 'cycle', 'hours',
			'minutes', 'active_since', 'active_till', 'subject', 'message', 'description', 'status', 'form_refresh'
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
					$user_name = $current_user_name;
				}

				$data['ms_user'] = [['id' => $data['userid'], 'name' => $user_name]];
			}
		}

		$data['dashboard_inaccessible'] = false;

		if ($data['dashboardid'] != 0) {
			$dashboards = API::Dashboard()->get([
				'output' => ['name'],
				'dashboardids' => $data['dashboardid']
			]);

			if ($dashboards) {
				$data['ms_dashboard'] = [['id' => $data['dashboardid'], 'name' => $dashboards[0]['name']]];
			}
			else {
				$data['ms_dashboard'] = [['id' => $data['dashboardid'], 'name' => _('Inaccessible dashboard')]];
				$data['dashboard_inaccessible'] = true;
			}
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Scheduled reports'));
		$this->setResponse($response);
	}
}

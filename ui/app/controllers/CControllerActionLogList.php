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


class CControllerActionLogList extends CController {

	protected function checkInput(): bool {
		$fields = [
			'page' =>					'ge 1',
			'filter_rst' =>				'in 1',
			'filter_set' =>				'in 1',
			'filter_userids' =>			'array_db users.userid',
			'from' =>					'range_time',
			'to' =>						'range_time'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_REPORTS_ACTION_LOG);
	}

	protected function doAction(): void {
		if ($this->getInput('filter_set', 0)) {
			$this->updateProfiles();
		}
		elseif ($this->getInput('filter_rst', 0)) {
			$this->deleteProfiles();
		}

		$timeselector_options = [
			'profileIdx' => 'web.actionlog.filter',
			'profileIdx2' => 0,
			'from' => null,
			'to' => null
		];
		$this->getInputs($timeselector_options, ['from', 'to']);
		updateTimeSelectorPeriod($timeselector_options);

		$data = [
			'page' => $this->getInput('page', 1),
			'userids' => CProfile::getArray('web.actionlog.filter.userids', []),
			'users' => [],
			'alerts' => [],
			'action' => $this->getAction(),
			'timeline' => getTimeSelectorPeriod($timeselector_options),
			'active_tab' => CProfile::get('web.actionlog.filter.active', 1)
		];

		$userids = [];

		if ($data['userids']) {
			$data['users'] = API::User()->get([
				'output' => ['userid', 'username', 'name', 'surname'],
				'userids' => $data['userids'],
				'preservekeys' => true
			]);

			$userids = array_column($data['users'], 'userid');

			$data['userids'] = $this->sanitizeUsersForMultiselect($data['users']);
		}

		if (!$data['userids'] || $data['users']) {
			$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
			foreach (eventSourceObjects() as $eventSource) {
				$data['alerts'] = array_merge($data['alerts'], API::Alert()->get([
					'output' => ['alertid', 'actionid', 'userid', 'clock', 'sendto', 'subject', 'message', 'status',
						'retries', 'error', 'alerttype'
					],
					'selectMediatypes' => ['mediatypeid', 'name', 'maxattempts'],
					'userids' => $userids ? $userids : null,
					// API::Alert operates with 'open' time interval therefore before call have to alter 'from' and 'to' values.
					'time_from' => $data['timeline']['from_ts'] - 1,
					'time_till' => $data['timeline']['to_ts'] + 1,
					'eventsource' => $eventSource['source'],
					'eventobject' => $eventSource['object'],
					'sortfield' => 'alertid',
					'sortorder' => ZBX_SORT_DOWN,
					'limit' => $limit
				]));
			}

			CArrayHelper::sort($data['alerts'], [
				['field' => 'alertid', 'order' => ZBX_SORT_DOWN]
			]);

			$data['alerts'] = array_slice($data['alerts'], 0, CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1);

			$data['paging'] = CPagerHelper::paginate($data['page'], $data['alerts'], ZBX_SORT_DOWN,
				(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
			);

			if (!$data['userids']) {
				$data['users'] = API::User()->get([
					'output' => ['userid', 'username', 'name', 'surname'],
					'userids' => array_column($data['alerts'], 'userid'),
					'preservekeys' => true
				]);
			}
		}

		if ($data['alerts']) {
			$data['actions'] = API::Action()->get([
				'output' => ['actionid', 'name'],
				'actionids' => array_unique(array_column($data['alerts'], 'actionid')),
				'preservekeys' => true
			]);
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Action log'));
		$this->setResponse($response);
	}

	protected function init(): void {
		$this->disableSIDValidation();
	}

	private function updateProfiles(): void {
		CProfile::updateArray('web.actionlog.filter.userids', $this->getInput('filter_userids', []), PROFILE_TYPE_ID);
	}

	private function deleteProfiles(): void {
		CProfile::deleteIdx('web.actionlog.filter.userids');
	}


	private function sanitizeUsersForMultiselect(array $users): array {
		$users = array_map(function(array $value): array {
			return ['id' => $value['userid'], 'name' => getUserFullname($value)];
		}, $users);

		CArrayHelper::sort($users, ['name']);

		return $users;
	}

}

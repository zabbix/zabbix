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


/**
 * Controller for the "Action log" page and Action log CSV export.
 */

class CControllerActionLogList extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'page' =>					'ge 1',
			'filter_rst' =>				'in 1',
			'filter_set' =>				'in 1',
			'filter_userids' =>			'array_db users.userid',
			'filter_actionids' =>		'array_db actions.actionid',
			'filter_mediatypeids' =>	'array_db media_type.mediatypeid',
			'filter_statuses' =>		'array_db alerts.status',
			'filter_messages' =>		'string',
			'from' =>					'range_time',
			'to' =>						'range_time'
		];

		$ret = $this->validateInput($fields) && $this->validateTimeSelectorPeriod();

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_REPORTS_ACTION_LOG);
	}

	protected function doAction(): void {
		if ($this->hasInput('filter_set')) {
			$this->updateProfiles();
		}
		elseif ($this->hasInput('filter_rst')) {
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
			'actionids' => CProfile::getArray('web.actionlog.filter.actionids', []),
			'actions' => [],
			'mediatypeids' => CProfile::getArray('web.actionlog.filter.mediatypeids', []),
			'media_types' => [],
			'actionlog_statuses' => CProfile::getArray('web.actionlog.filter.statuses', []),
			'statuses' => self::getStatusList(),
			'messages' => CProfile::get('web.actionlog.filter.messages', ''),
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

			$userids = array_keys($data['users']);
			$data['userids'] = $this->prepareDataForMultiselect($data['users'], 'users');
		}

		$actionids = [];

		if ($data['actionids']) {
			$data['actions'] = API::Action()->get([
				'output' => ['actionid', 'name'],
				'actionids' => $data['actionids'],
				'preservekeys' => true
			]);

			$actionids = array_keys($data['actions']);
			$data['actionids'] = $this->prepareDataForMultiselect($data['actions'], 'actions');
		}

		$mediatypeids = [];

		if ($data['mediatypeids']) {
			$data['media_types'] = API::MediaType()->get([
				'output' => ['mediatypeid', 'name', 'maxattempts'],
				'mediatypeids' => $data['mediatypeids'],
				'preservekeys' => true
			]);

			$mediatypeids = array_keys($data['media_types']);
			$data['mediatypeids'] = $this->prepareDataForMultiselect($data['media_types'], 'media_types');
		}

		$search = $data['messages'] === '' ? null : $data['messages'];
		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;

		foreach (eventSourceObjects() as $event_source) {
			$data['alerts'] = array_merge($data['alerts'], API::Alert()->get([
				'output' => ['alertid', 'actionid', 'userid', 'clock', 'sendto', 'subject', 'message', 'status',
					'retries', 'error', 'alerttype'
				],
				'filter' => [
					'status' => $data['actionlog_statuses'] ?: null
				],
				'selectMediatypes' => ['mediatypeid', 'name', 'maxattempts'],
				'userids' => $userids ?: null,
				'actionids' => $actionids ?: null,
				'mediatypeids' => $mediatypeids ?: null,
				'search' => [
					'subject' => $search,
					'message' => $search
				],
				'searchByAny' => true,
				'time_from' => $data['timeline']['from_ts'] - 1,
				'time_till' => $data['timeline']['to_ts'] + 1,
				'eventsource' => $event_source['source'],
				'eventobject' => $event_source['object'],
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

		if ($data['alerts']) {
			$data['actions'] = API::Action()->get([
				'output' => ['actionid', 'name'],
				'actionids' => array_unique(array_column($data['alerts'], 'actionid')),
				'preservekeys' => true
			]);
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Action log'));

		if ($data['action'] === 'actionlog.csv') {
			$response->setFileName('zbx_actionlog_export.csv');
		}

		$this->setResponse($response);
	}

	/**
	 * Return associated list of available statuses and labels.
	 *
	 * @return array
	 */
	private static function getStatusList(): array {
		return [
			ALERT_STATUS_NOT_SENT => _('In progress'),
			ALERT_STATUS_SENT => _('Sent/Executed'),
			ALERT_STATUS_FAILED => _('Failed')
		];
	}

	private function updateProfiles(): void {
		$filter_statuses = $this->getInput('filter_statuses', []);

		if (in_array(ALERT_STATUS_NOT_SENT, $filter_statuses)) {
			$filter_statuses[] = ALERT_STATUS_NEW;
		}

		CProfile::updateArray('web.actionlog.filter.userids', $this->getInput('filter_userids', []), PROFILE_TYPE_ID);
		CProfile::updateArray('web.actionlog.filter.actionids', $this->getInput('filter_actionids', []),
			PROFILE_TYPE_ID);
		CProfile::updateArray('web.actionlog.filter.mediatypeids', $this->getInput('filter_mediatypeids', []),
			PROFILE_TYPE_ID);
		CProfile::updateArray('web.actionlog.filter.statuses', $filter_statuses, PROFILE_TYPE_ID);
		CProfile::update('web.actionlog.filter.messages', $this->getInput('filter_messages', ''), PROFILE_TYPE_STR);
	}

	private function deleteProfiles(): void {
		CProfile::deleteIdx('web.actionlog.filter.userids');
		CProfile::deleteIdx('web.actionlog.filter.actionids');
		CProfile::deleteIdx('web.actionlog.filter.mediatypeids');
		CProfile::deleteIdx('web.actionlog.filter.statuses');
		CProfile::deleteIdx('web.actionlog.filter.messages');
	}

	/**
	 * Prepare data for multiselect fields.
	 *
	 * @param array $data
	 * @param string $type  Defines data type ('users', 'actions', 'media_types').
	 *
	 * @return array
	 */

	private function prepareDataForMultiselect(array $data, string $type): array {
		$prepared_data = [];

		foreach ($data as $value) {
			switch ($type) {
				case 'users':
					$prepared_data[$value['userid']] = [
						'id' => $value['userid'],
						'name' => getUserFullname($value)
					];
					break;
				case 'actions':
					$prepared_data[$value['actionid']] = [
						'id' => $value['actionid'],
						'name' => $value['name']
					];
					break;
				case 'media_types':
					$prepared_data[$value['mediatypeid']] = [
						'id' => $value['mediatypeid'],
						'name' => $value['name'],
						'maxattempts' => $value['maxattempts']
					];
					break;
			}
		}

		CArrayHelper::sort($prepared_data, ['name']);

		return $prepared_data;
	}
}

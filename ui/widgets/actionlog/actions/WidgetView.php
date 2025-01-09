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


namespace Widgets\ActionLog\Actions;

use API,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CArrayHelper,
	CSettingsHelper;

class WidgetView extends CControllerDashboardWidgetView {

	protected function init(): void {
		parent::init();

		$this->addValidationRules([
			'has_custom_time_period' => 'in 1'
		]);
	}

	protected function doAction(): void {
		[$sortfield, $sortorder] = self::getSorting($this->fields_values['sort_triggers']);

		$data = [
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'userids' => $this->fields_values['userids'],
			'users' => [],
			'actionids' => $this->fields_values['actionids'],
			'actions' => [],
			'mediatypeids' => $this->fields_values['mediatypeids'],
			'media_types' => [],
			'statuses' => $this->fields_values['statuses'],
			'message' => $this->fields_values['message'],
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'info' => $this->makeWidgetInfo(),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
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

		$search = $data['message'] === '' ? null : $data['message'];

		$time_from = $this->fields_values['time_period']['from_ts'];
		$time_to = $this->fields_values['time_period']['to_ts'];

		$alerts = [];
		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);

		foreach (eventSourceObjects() as $eventSource) {
			$alerts = array_merge($alerts, API::Alert()->get([
				'output' => ['actionid', 'userid', 'clock', 'mediatypeid', 'sendto', 'subject', 'message', 'status',
					'retries', 'error', 'alerttype'
				],
				'selectMediatypes' => ['name', 'maxattempts'],
				'eventsource' => $eventSource['source'],
				'eventobject' => $eventSource['object'],
				'userids' => $userids ?: null,
				'actionids' => $actionids ?: null,
				'mediatypeids' => $mediatypeids ?: null,
				'filter' => ['status' => $data['statuses']],
				'search' => [
					'subject' => $search,
					'message' => $search
				],
				'searchByAny' => true,
				'time_from' => $time_from - 1,
				'time_till' => $time_to + 1,
				'sortfield' => 'alertid',
				'sortorder' => ZBX_SORT_DOWN,
				'limit' => $limit
			]));
		}

		CArrayHelper::sort($alerts, [['field' => $sortfield, 'order' => $sortorder]]);
		$alerts = array_slice($alerts, 0, $this->fields_values['show_lines'], true);

		foreach ($alerts as &$alert) {
			$alert['description'] = '';

			if ($alert['mediatypeid'] != 0 && array_key_exists(0, $alert['mediatypes'])) {
				$alert['description'] = $alert['mediatypes'][0]['name'];
				$alert['maxattempts'] = $alert['mediatypes'][0]['maxattempts'];
			}
			unset($alert['mediatypes']);

			$alert['action_type'] = ZBX_EVENT_HISTORY_ALERT;
		}
		unset($alert);

		$data['alerts'] = $alerts;

		$data['db_users'] = $this->getDbUsers($data['alerts']);

		$data['actions'] = API::Action()->get([
			'output' => ['actionid', 'name'],
			'actionids' => array_unique(array_column($data['alerts'], 'actionid')),
			'preservekeys' => true
		]);

		$this->setResponse(new CControllerResponseData($data));
	}

	private function getDbUsers(array $alerts): array {
		$userids = [];

		foreach ($alerts as $alert) {
			$userids[$alert['userid']] = true;
		}
		unset($userids[0]);

		return $userids
			? API::User()->get([
				'output' => ['userid', 'username', 'name', 'surname'],
				'userids' => array_keys($userids),
				'preservekeys' => true
			])
			: [];
	}

	private static function getSorting(int $sort_triggers): array {
		switch ($sort_triggers) {
			case SCREEN_SORT_TRIGGERS_TIME_ASC:
				return ['clock', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_TIME_DESC:
			default:
				return ['clock', ZBX_SORT_DOWN];

			case SCREEN_SORT_TRIGGERS_MEDIA_TYPE_ASC:
				return ['mediatypeid', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_MEDIA_TYPE_DESC:
				return ['mediatypeid', ZBX_SORT_DOWN];

			case SCREEN_SORT_TRIGGERS_STATUS_ASC:
				return ['status', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_STATUS_DESC:
				return ['status', ZBX_SORT_DOWN];

			case SCREEN_SORT_TRIGGERS_RECIPIENT_ASC:
				return ['sendto', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_RECIPIENT_DESC:
				return ['sendto', ZBX_SORT_DOWN];
		}
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

	/**
	 * Make widget specific info to show in widget's header.
	 */
	private function makeWidgetInfo(): array {
		$info = [];

		if ($this->hasInput('has_custom_time_period')) {
			$info[] = [
				'icon' => ZBX_ICON_TIME_PERIOD,
				'hint' => relativeDateToText($this->fields_values['time_period']['from'],
					$this->fields_values['time_period']['to']
				)
			];
		}

		return $info;
	}
}

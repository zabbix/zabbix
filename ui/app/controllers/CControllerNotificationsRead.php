<?php
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


class CControllerNotificationsRead extends CController {

	protected function checkInput(): bool {
		$fields = [
			'ids' => 'array_db events.eventid|required'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return (!CWebUser::isGuest() && $this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction(): void {
		$msg_settings = getMessageSettings();

		$events = API::Event()->get([
			'output' => ['clock', 'r_eventid'],
			'eventids' => $this->input['ids'],
			'preservekeys' => true
		]);

		$recovery_eventids = array_filter(zbx_objectValues($events, 'r_eventid'));
		if ($recovery_eventids) {
			$events += API::Event()->get([
				'output' => ['clock'],
				'eventids' => $recovery_eventids,
				'preservekeys' => true
			]);
		}

		CArrayHelper::sort($events, [
			['field' => 'clock', 'order' => ZBX_SORT_DOWN]
		]);

		$last_event = reset($events);

		$msg_settings['last.clock'] = $last_event['clock'] + 1;
		updateMessageSettings($msg_settings);

		$data = json_encode(['ids' => array_keys($events)]);
		$this->setResponse(new CControllerResponseData(['main_block' => $data]));
	}
}

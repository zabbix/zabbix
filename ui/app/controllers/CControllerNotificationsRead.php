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


class CControllerNotificationsRead extends CController {

	protected function checkInput() {
		$fields = [
			'ids' => 'array_db events.eventid|required'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$data = json_encode(['error' => true]);
			$this->setResponse(new CControllerResponseData(['main_block' => $data]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return (!CWebUser::isGuest() && $this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
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

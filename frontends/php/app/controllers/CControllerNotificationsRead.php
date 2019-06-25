<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
			$data = CJs::encodeJson(['error' => _('Invalid request.')]);
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
			'output'    => ['clock'],
			'eventids'  => $this->input['ids'],
			'sortfield' => ['clock', 'eventid'],
			'sortorder' => 'DESC',
			'preservekeys' => true
		]);

		$last_event = reset($events);

		$msg_settings['last.clock'] = $last_event['clock'] + 1;
		updateMessageSettings($msg_settings);

		$data = CJs::encodeJson(['ids' => array_keys($events)]);
		$this->setResponse(new CControllerResponseData(['main_block' => $data]));
	}
}

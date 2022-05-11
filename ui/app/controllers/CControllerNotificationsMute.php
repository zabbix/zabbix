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


class CControllerNotificationsMute extends CController {

	protected function checkInput() {
		$fields = [
			'muted' => 'required|in 0,1'
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
		$msg_settings['sounds.mute'] = $this->input['muted'];

		updateMessageSettings($msg_settings);

		$data = json_encode(['muted' => (int) $msg_settings['sounds.mute']]);
		$this->setResponse(new CControllerResponseData(['main_block' => $data]));
	}
}

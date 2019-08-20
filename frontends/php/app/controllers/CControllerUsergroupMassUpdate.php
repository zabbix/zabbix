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


class CControllerUsergroupMassUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'usrgrpids' => 'required|array_db usrgrp.usrgrpid',
			'update' => 'required|array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$update = $this->getInput('update');
		$user_groups = [];
		foreach ($this->getInput('usrgrpids') as $usrgrpid) {
			$user_groups[] = ['usrgrpid' => $usrgrpid] + $update;
		}

		$result = (bool) API::UserGroup()->update($user_groups);

		$url = (new CUrl('zabbix.php'))->setArgument('action', 'usergroup.list');
		$response = new CControllerResponseRedirect($url->getUrl());
		$response->setFormData(['uncheck' => '1']);

		list($msg_ok, $msg_error) = $this->getUpdateMessages($update, count($user_groups));

		$result
			? $response->setMessageOk($msg_ok)
			: $response->setMessageError($msg_error);

		$this->setResponse($response);
	}

	/**
	 * @param array $update
	 * @param int $count
	 *
	 * @return array
	 */
	protected function getUpdateMessages(array $update, $count) {
		switch (key($update)) {
			case 'users_status':
				if ($update['users_status'] == GROUP_STATUS_ENABLED) {
					$msg_ok = _n('User group enabled', 'User groups enabled', $count);
					$msg_error = _n('Cannot enable user group', 'Cannot enable user groups', $count);
				}
				else {
					$msg_ok = _n('User group disabled', 'User groups disabled', $count);
					$msg_error = _n('Cannot disable user group', 'Cannot disable user groups', $count);
				}
				break;
			case 'debug_mode':
				$msg_ok = _('Debug mode updated');
				$msg_error = _('Cannot update debug mode');
				break;
		}

		return [$msg_ok, $msg_error];
	}
}

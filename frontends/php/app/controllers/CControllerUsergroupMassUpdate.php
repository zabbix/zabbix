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
			'usrgrpids'    => 'required|array_db usrgrp.usrgrpid',
			'gui_access'   => 'db usrgrp.gui_access|in '.implode(',', [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_INTERNAL, GROUP_GUI_ACCESS_LDAP, GROUP_GUI_ACCESS_DISABLED]),
			'users_status' => 'db usrgrp.users_status|in '.GROUP_STATUS_ENABLED.','.GROUP_STATUS_DISABLED,
			'debug_mode'   => 'db usrgrp.debug_mode|in '.GROUP_DEBUG_MODE_ENABLED.','.GROUP_DEBUG_MODE_DISABLED
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
		$user_group = [];

		$this->getInputs($user_group, ['gui_access', 'users_status', 'debug_mode']);

		$user_groups = [];
		foreach ($this->getInput('usrgrpids') as $usrgrpid) {
			$user_groups[] = ['usrgrpid' => $usrgrpid] + $user_group;
		}

		$result = (bool) API::UserGroup()->update($user_groups);

		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'usergroup.list')
			->getUrl()
		);

		$update_message = $this->getUpdateMessage($result);

		if ($result) {
			$response->setFormData(['uncheck' => '1']);
			$response->setMessageOk($update_message);
		}
		else {
			$response->setMessageError($update_message);
		}

		$this->setResponse($response);
	}

	/**
	 * @param bool $result
	 *
	 * @return string
	 */
	private function getUpdateMessage($result) {
		if ($this->hasInput('gui_access')) {
			$message = $result ? _('Frontend access updated') : _('Cannot update frontend access');
		}
		elseif ($this->hasInput('users_status')) {
			$updated = count($this->getInput('usrgrpids'));

			if ($this->getInput('users_status') == GROUP_STATUS_ENABLED) {
				$message = $result
					? _n('User group enabled', 'User groups enabled', $updated)
					: _n('Cannot enable user group', 'Cannot enable user groups', $updated);
			}
			else {
				$message = $result
					? _n('User group disabled', 'User groups disabled', $updated)
					: _n('Cannot disable user group', 'Cannot disable user groups', $updated);
			}
		}
		else {
			$message = $result ? _('Debug mode updated') : _('Cannot update debug mode');
		}

		return $message;
	}
}

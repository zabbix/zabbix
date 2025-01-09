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
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USER_GROUPS);
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
			->setArgument('page', CPagerHelper::loadPage('usergroup.list', null))
		);

		$updated = count($user_groups);

		if ($result) {
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_n('User group updated', 'User groups updated', $updated));
		}
		else {
			CMessageHelper::setErrorTitle(_n('Cannot update user group', 'Cannot update user groups', $updated));
		}

		$this->setResponse($response);
	}
}

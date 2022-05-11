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


class CControllerUsergroupDelete extends CController {

	protected function checkInput() {
		$fields = [
			'usrgrpids' => 'required|array_db usrgrp.usrgrpid'
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
		$usrgrpids = $this->getInput('usrgrpids');

		$result = (bool) API::UserGroup()->delete($usrgrpids);

		$deleted = count($usrgrpids);

		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'usergroup.list')
			->setArgument('page', CPagerHelper::loadPage('usergroup.list', null))
		);

		if ($result) {
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_n('User group deleted', 'User groups deleted', $deleted));
		}
		else {
			CMessageHelper::setErrorTitle(_n('Cannot delete user group', 'Cannot delete user groups', $deleted));
		}

		$this->setResponse($response);
	}
}

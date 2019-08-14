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


class CControllerUsergroupDisableDebug extends CController {

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
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}

		$usergroup_ctn = API::UserGroup()->get([
			'usrgrpids' => $this->getInput('usrgrpids'),
			'countOutput' => true,
			'editable' => true
		]);

		return ($usergroup_ctn == count($this->getInput('usrgrpids')));
	}

	protected function doAction() {

		$user_groups = [];
		foreach ($this->getInput('usrgrpids') as $usrgrpid) {
			$user_groups[] = [
				'usrgrpid' => $usrgrpid,
				'debug_mode' => GROUP_DEBUG_MODE_DISABLED
			];
		}

		$result = (bool) API::UserGroup()->update($user_groups);

		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'usergroup.list')
			->getUrl()
		);

		if ($result) {
			$response->setFormData(['uncheck' => '1']);
			$response->setMessageOk(_('Debug mode updated'));
		}
		else {
			$response->setMessageError(_('Cannot update debug mode'));
		}
		$this->setResponse($response);
	}
}

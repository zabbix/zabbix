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


class CControllerUsergroupDelete extends CController {

	protected function checkInput() {
		$fields = [
			'usergroupids' => 'required|array_db usrgrp.usrgrpid'
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
			'usrgrpids' => $this->getInput('usergroupids'),
			'countOutput' => true,
			'editable' => true
		]);

		return ($usergroup_ctn == count($this->getInput('usergroupids')));
	}

	protected function doAction() {

		$result = true;

		$response = new CControllerResponseRedirect('zabbix.php?action=usergroup.list');

		$deleted = count($this->getInput('usergroupids'));

		if ($result) {
			$response->setMessageOk(_n('.. deleted', '..s deleted', $deleted));
		}
		else {
			$response->setMessageError(_n('Cannot delete ..', 'Cannot delete ..s', $deleted));
		}
		$this->setResponse($response);
	}
}

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


class CControllerUserDelete extends CController {

	protected function checkInput() {
		$fields = [
			'userids' =>	'required|array_db users.userid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USERS);
	}

	protected function doAction() {
		$userids = $this->getInput('userids');

		$result = (bool) API::User()->delete($userids);

		$deleted = count($userids);

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))
				->setArgument('action', 'user.list')
				->setArgument('page', CPagerHelper::loadPage('user.list', null))
		);

		if ($result) {
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_n('User deleted', 'Users deleted', $deleted));
		}
		else {
			CMessageHelper::setErrorTitle(_n('Cannot delete user', 'Cannot delete users', $deleted));
		}

		$this->setResponse($response);
	}
}

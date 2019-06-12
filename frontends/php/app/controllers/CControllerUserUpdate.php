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


/**
 * Class containing operations for updating a user.
 */
class CControllerUserUpdate extends CControllerUserUpdateGeneral {

	protected function checkInput() {
		$this->appendValidationRules([
			'alias' =>			'required|db users.alias|not_empty',
			'name' =>			'db users.name',
			'surname' =>		'db users.surname',
			'user_type' =>		'db users.type|in '.USER_TYPE_ZABBIX_USER.','.USER_TYPE_ZABBIX_ADMIN.','.USER_TYPE_SUPER_ADMIN,
			'user_groups' =>	'required|array_id|not_empty'
		]);

		$this->redirect = 'zabbix.php?action=user.edit';

		return parent::checkInput();
	}

	/**
	 * Validate password directly from input when updating user.
	 */
	protected function vadidatePassword() {
		if ($this->getInput('password1', '') !== $this->getInput('password2', '')) {
			error(_('Cannot update user. Both passwords must be equal.'));
			return false;
		}

		return true;
	}

	protected function checkPermissions() {
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}

		$this->userid = $this->getInput('userid');

		return parent::checkPermissions();
	}

	protected function doAction() {
		$this->fields = ['userid', 'alias', 'name', 'surname'];

		parent::doAction();

		$this->user['usrgrps'] = zbx_toObject($this->getInput('user_groups', []), 'usrgrpid');
		$this->setUserMedias();

		$result = (bool) API::User()->update($this->user);

		if ($result) {
			$response = new CControllerResponseRedirect('zabbix.php?action=user.list&uncheck=1');
			$response->setMessageOk(_('User updated'));
		}
		else {
			$response = new CControllerResponseRedirect('zabbix.php?action=user.edit');
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot update user'));
		}

		$this->setResponse($response);
	}
}

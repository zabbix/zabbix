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
 * Class containing operations for updating user profile.
 */
class CControllerUserProfileUpdate extends CControllerUserUpdateGeneral {

	protected function checkInput() {
		$this->appendValidationRules(['messages' => 'array']);

		$this->redirect = 'zabbix.php?action=userprofile.edit';

		return parent::checkInput();
	}

	/**
	 * Validate password based on current user ID.
	 */
	protected function vadidatePassword() {
		$auth_type = getUserAuthenticationType(CWebUser::$data['userid']);

		if ($auth_type != ZBX_AUTH_INTERNAL) {
			$password1 = null;
			$password2 = null;
		}
		else {
			$password1 = $this->hasInput('password1') ? $this->getInput('password1') : null;
			$password2 = $this->hasInput('password2') ? $this->getInput('password2') : null;
		}

		if ($password1 !== null && CWebUser::$data['alias'] != ZBX_GUEST_USER && $password1 === '') {
			error(_s('Incorrect value for field "%1$s": cannot be empty.', 'passwd'));
			return false;
		}

		if ($password1 !== $password2) {
			error(_('Both passwords must be equal.'));
			return false;
		}

		return true;
	}

	protected function checkPermissions() {
		$this->userid = CWebUser::$data['userid'];

		return parent::checkPermissions();
	}

	protected function doAction() {
		parent::doAction();

		$this->user['userid'] = CWebUser::$data['userid'];
		$messages = $this->getInput('messages', []);

		if (CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER) {
			$this->setUserMedias();
		}

		DBstart();
		$result = updateMessageSettings($messages);
		$result = $result && (bool) API::User()->update($this->user);
		$result = DBend($result);

		if ($result) {
			redirect(ZBX_DEFAULT_URL);
		}
		else {
			$response = new CControllerResponseRedirect('zabbix.php?action=userprofile.edit');
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot update user'));
		}

		$this->setResponse($response);
	}
}

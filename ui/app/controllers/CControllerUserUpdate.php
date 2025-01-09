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


/**
 * Class containing operations for updating a user.
 */
class CControllerUserUpdate extends CControllerUserUpdateGeneral {

	protected function checkInput() {
		$locales = array_keys(getLocales());
		$locales[] = LANG_DEFAULT;
		$themes = array_keys(APP::getThemes());
		$themes[] = THEME_DEFAULT;

		$fields = [
			'userid' =>				'fatal|required|db users.userid',
			'username' =>			'required|db users.username|not_empty',
			'name' =>				'db users.name',
			'surname' =>			'db users.surname',
			'user_groups' =>		'array_id',
			'current_password' =>	'string',
			'password1' =>			'string',
			'password2' =>			'string',
			'medias' =>				'array',
			'lang' =>				'db users.lang|in '.implode(',', $locales),
			'timezone' =>			'db users.timezone|in '.implode(',', $this->timezones),
			'theme' =>				'db users.theme|in '.implode(',', $themes),
			'autologin' =>			'db users.autologin|in 0,1',
			'autologout' =>			'db users.autologout|not_empty',
			'refresh' =>			'db users.refresh|not_empty',
			'rows_per_page' =>		'db users.rows_per_page',
			'url' =>				'db users.url',
			'roleid' =>				'id',
			'form_refresh' =>		'int32'
		];

		$ret = $this->validateInput($fields);
		$error = $this->GetValidationError();

		if (CWebUser::$data['userid'] == $this->getInput('userid')
				&& CWebUser::$data['roleid'] == USER_TYPE_SUPER_ADMIN) {
			if ($ret && !$this->validateCurrentPassword()) {
				$error = self::VALIDATION_ERROR;
				$ret = false;
			}
		}

		if ($ret && (!$this->validatePassword() || !$this->validateUserRole())) {
			$error = self::VALIDATION_ERROR;
			$ret = false;
		}

		if (!$ret) {
			switch ($error) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('zabbix.php?action=user.edit');
					$response->setFormData($this->getInputAll());
					CMessageHelper::setErrorTitle(_('Cannot update user'));
					$this->setResponse($response);
					break;

				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USERS)) {
			return false;
		}

		return (bool) API::User()->get([
			'output' => [],
			'userids' => $this->getInput('userid'),
			'editable' => true
		]);
	}

	protected function doAction() {
		$user = [
			'roleid' => 0,
			'medias' => $this->getInputUserMedia()
		];

		$this->getInputs($user, ['userid', 'username', 'name', 'surname', 'lang', 'timezone', 'theme', 'autologin',
			'autologout', 'refresh', 'rows_per_page', 'url', 'roleid'
		]);

		if ($this->getInput('current_password', '') !== '' || ($this->hasInput('current_password')
				&& CWebUser::$data['auth_type'] == ZBX_AUTH_INTERNAL)) {
			$user['current_passwd'] = $this->getInput('current_password');
		}

		if ($this->getInput('password1', '') !== '' || ($this->hasInput('password1') && !$this->allow_empty_password)) {
			$user['passwd'] = $this->getInput('password1');
		}

		[$db_user] = API::User()->get([
			'output' => ['userdirectoryid'],
			'userids' => [$user['userid']]
		]);

		if ($db_user['userdirectoryid']) {
			$provisioned_fields = ['username', 'name', 'surname', 'roleid', 'passwd'];
			$user = array_diff_key($user, array_fill_keys($provisioned_fields, ''));
		}
		else {
			$user['usrgrps'] = zbx_toObject($this->getInput('user_groups', []), 'usrgrpid');
		}

		$result = (bool) API::User()->update($user);

		if ($result) {
			if (array_key_exists('passwd', $user) && CWebUser::$data['userid'] == $user['userid']) {
				redirect('index.php');
			}

			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'user.list')
				->setArgument('page', CPagerHelper::loadPage('user.list', null))
			);
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_('User updated'));
		}
		else {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'user.edit')
			);
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot update user'));
		}

		$this->setResponse($response);
	}
}

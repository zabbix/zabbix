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
 * Class containing operations for updating user profile.
 */
class CControllerUserProfileUpdate extends CControllerUserUpdateGeneral {

	protected function checkInput() {
		$locales = array_keys(getLocales());
		$locales[] = LANG_DEFAULT;
		$themes = array_keys(APP::getThemes());
		$themes[] = THEME_DEFAULT;

		$fields = [
			'userid' =>				'fatal|required|db users.userid',
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
			'messages' =>			'array',
			'form_refresh' =>		'int32'
		];

		$ret = $this->validateInput($fields);
		$error = $this->GetValidationError();

		if ($ret && !$this->validateCurrentPassword()) {
			$error = self::VALIDATION_ERROR;
			$ret = false;
		}

		if ($ret && !$this->validatePassword()) {
			$error = self::VALIDATION_ERROR;
			$ret = false;
		}

		if (!$ret) {
			switch ($error) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
						->setArgument('action', 'userprofile.edit')
					);
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
		return (bool) API::User()->get([
			'output' => [],
			'userids' => $this->getInput('userid'),
			'editable' => true
		]);
	}

	protected function doAction() {
		$user = [];

		$this->getInputs($user, ['lang', 'timezone', 'theme', 'autologin', 'autologout', 'refresh', 'rows_per_page',
			'url'
		]);
		$user['userid'] = CWebUser::$data['userid'];

		if ($this->getInput('current_password', '') !== ''
				|| ($this->hasInput('current_password') && CWebUser::$data['auth_type'] == ZBX_AUTH_INTERNAL)) {
			$user['current_passwd'] = $this->getInput('current_password');
		}

		if ($this->getInput('password1', '') !== ''
				|| ($this->hasInput('password1') && CWebUser::$data['auth_type'] == ZBX_AUTH_INTERNAL)) {
			$user['passwd'] = $this->getInput('password1');
		}

		if (CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER) {
			$user['medias'] = $this->getInputUserMedia();
		}

		DBstart();
		$result = updateMessageSettings($this->getInput('messages', []));
		$result = $result && (bool) API::User()->update($user);
		$result = DBend($result);

		if ($result) {
			if (array_key_exists('passwd', $user)) {
				redirect('index.php');
			}
			$response = new CControllerResponseRedirect(new CUrl(CMenuHelper::getFirstUrl()));
			CMessageHelper::setSuccessTitle(_('User updated'));
		}
		else {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))->setArgument('action', 'userprofile.edit'));
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot update user'));
		}

		$this->setResponse($response);
	}
}

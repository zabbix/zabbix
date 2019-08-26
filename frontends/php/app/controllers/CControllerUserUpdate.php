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
		$locales = array_keys(getLocales());
		$themes = array_keys(Z::getThemes());
		$themes[] = THEME_DEFAULT;

		$fields = [
			'userid' =>			'fatal|required|db users.userid',
			'alias' =>			'required|db users.alias|not_empty',
			'name' =>			'db users.name',
			'surname' =>		'db users.surname',
			'user_groups' =>	'required|array_id|not_empty',
			'password1' =>		'db users.passwd',
			'password2' =>		'db users.passwd',
			'user_medias' =>	'array',
			'lang' =>			'db users.lang|in '.implode(',', $locales),
			'theme' =>			'db users.theme|in '.implode(',', $themes),
			'autologin' =>		'db users.autologin|in 0,1',
			'autologout' =>		'db users.autologout|not_empty',
			'refresh' =>		'db users.refresh|not_empty',
			'rows_per_page' =>	'db users.rows_per_page',
			'url' =>			'db users.url',
			'type' =>			'db users.type|in '.USER_TYPE_ZABBIX_USER.','.USER_TYPE_ZABBIX_ADMIN.','.USER_TYPE_SUPER_ADMIN,
			'form_refresh' =>	'int32'
		];

		$ret = $this->validateInput($fields);
		$error = $this->GetValidationError();

		if ($ret && !$this->validatePassword()) {
			$error = self::VALIDATION_ERROR;
			$ret = false;
		}

		if (!$ret) {
			switch ($error) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('zabbix.php?action=user.edit');
					$response->setFormData($this->getInputAll());
					$response->setMessageError(_('Cannot update user'));
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
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}

		return (bool) API::User()->get([
			'output' => [],
			'userids' => $this->getInput('userid'),
			'editable' => true
		]);
	}

	protected function doAction() {
		$user = [];

		$this->getInputs($user, ['userid', 'alias', 'name', 'surname', 'lang', 'theme', 'autologin', 'autologout',
			'refresh', 'rows_per_page', 'url', 'type'
		]);
		$user['usrgrps'] = zbx_toObject($this->getInput('user_groups', []), 'usrgrpid');

		if ($this->getInput('password1', '') !== '' || ($this->hasInput('password1') && !$this->allow_empty_password)) {
			$user['passwd'] = $this->getInput('password1');
		}

		$user['user_medias'] = [];

		foreach ($this->getInput('user_medias', []) as $media) {
			$user['user_medias'][] = [
				'mediatypeid' => $media['mediatypeid'],
				'sendto' => $media['sendto'],
				'active' => $media['active'],
				'severity' => $media['severity'],
				'period' => $media['period']
			];
		}

		$result = (bool) API::User()->update($user);

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

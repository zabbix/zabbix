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
			'userid' =>			'fatal|required|db users.userid',
			'username' =>		'required|db users.username|not_empty',
			'name' =>			'db users.name',
			'surname' =>		'db users.surname',
			'user_groups' =>	'required|array_id|not_empty',
			'password1' =>		'string',
			'password2' =>		'string',
			'medias' =>			'array',
			'lang' =>			'db users.lang|in '.implode(',', $locales),
			'timezone' =>		'db users.timezone|in '.implode(',', $this->timezones),
			'theme' =>			'db users.theme|in '.implode(',', $themes),
			'autologin' =>		'db users.autologin|in 0,1',
			'autologout' =>		'db users.autologout|not_empty',
			'refresh' =>		'db users.refresh|not_empty',
			'rows_per_page' =>	'db users.rows_per_page',
			'url' =>			'db users.url',
			'roleid' =>			'required|db users.roleid',
			'form_refresh' =>	'int32'
		];

		$ret = $this->validateInput($fields);
		$error = $this->GetValidationError();

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
		$user = [];

		$this->getInputs($user, ['userid', 'username', 'name', 'surname', 'lang', 'timezone', 'theme', 'autologin',
			'autologout', 'refresh', 'rows_per_page', 'url', 'roleid'
		]);
		$user['usrgrps'] = zbx_toObject($this->getInput('user_groups', []), 'usrgrpid');

		if ($this->getInput('password1', '') !== '' || ($this->hasInput('password1') && !$this->allow_empty_password)) {
			$user['passwd'] = $this->getInput('password1');
		}

		$user['medias'] = [];

		foreach ($this->getInput('medias', []) as $media) {
			$user['medias'][] = [
				'mediatypeid' => $media['mediatypeid'],
				'sendto' => $media['sendto'],
				'active' => $media['active'],
				'severity' => $media['severity'],
				'period' => $media['period']
			];
		}

		$result = (bool) API::User()->update($user);

		if ($result) {
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

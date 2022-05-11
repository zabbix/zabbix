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


class CControllerUserCreate extends CControllerUserUpdateGeneral {

	protected function checkInput() {
		$locales = array_keys(getLocales());
		$locales[] = LANG_DEFAULT;
		$themes = array_keys(APP::getThemes());
		$themes[] = THEME_DEFAULT;

		$fields = [
			'username' =>		'required|db users.username|not_empty',
			'name' =>			'db users.name',
			'surname' =>		'db users.surname',
			'password1' =>		'required|string',
			'password2' =>		'required|string',
			'user_groups' =>	'required|array_id|not_empty',
			'medias' =>			'array',
			'lang' =>			'db users.lang|in '.implode(',', $locales),
			'timezone' =>		'db users.timezone|in '.implode(',', $this->timezones),
			'theme' =>			'db users.theme|in '.implode(',', $themes),
			'autologin' =>		'db users.autologin|in 0,1',
			'autologout' =>		'db users.autologout|not_empty',
			'url' =>			'db users.url',
			'refresh' =>		'required|db users.refresh|not_empty',
			'rows_per_page' =>	'required|db users.rows_per_page',
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
					CMessageHelper::setErrorTitle(_('Cannot add user'));
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
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USERS);
	}

	protected function doAction() {
		$user = [];

		$this->getInputs($user, ['username', 'name', 'surname', 'url', 'autologin', 'autologout', 'theme', 'refresh',
			'rows_per_page', 'lang', 'timezone', 'roleid'
		]);
		$user['usrgrps'] = zbx_toObject($this->getInput('user_groups'), 'usrgrpid');

		if ($this->getInput('password1', '') !== '' || !$this->allow_empty_password) {
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

		$result = (bool) API::User()->create($user);

		if ($result) {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'user.list')
				->setArgument('page', CPagerHelper::loadPage('user.list', null))
			);
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_('User added'));
		}
		else {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'user.edit')
			);
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot add user'));
		}
		$this->setResponse($response);
	}
}

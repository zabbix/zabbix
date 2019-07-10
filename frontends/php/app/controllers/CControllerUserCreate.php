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


class CControllerUserCreate extends CController {

	protected function checkInput() {
		$locales = array_keys(getLocales());
		$themes = array_keys(Z::getThemes());
		$themes[] = THEME_DEFAULT;

		$fields = [
			'alias' =>				'required|db users.alias|not_empty',
			'name' =>				'db users.name',
			'surname' =>			'db users.surname',
			'password1' =>			'required|db users.passwd',
			'password2' =>			'required|db users.passwd',
			'user_type' =>			'db users.type|in '.USER_TYPE_ZABBIX_USER.','.USER_TYPE_ZABBIX_ADMIN.','.USER_TYPE_SUPER_ADMIN,
			'user_groups' =>		'required|array_id|not_empty',
			'user_medias' =>		'array',
			'lang' =>				'db users.lang|in '.implode(',', $locales),
			'theme' =>				'db users.theme|in '.implode(',', $themes),
			'autologin' =>			'db users.autologin|in 0,1',
			'autologout' =>			'db users.autologout',
			'autologout_visible' =>	'in 0,1',
			'url' =>				'string',
			'refresh' =>			'required|string|not_empty',
			'rows_per_page' =>		'required|int32|not_empty|ge 1|le 999999',
			'form_refresh' =>		'int32'
		];

		$ret = $this->validateInput($fields);
		$error = $this->GetValidationError();

		if ($ret) {
			if ($this->getInput('password1') !== $this->getInput('password2')) {
				error(_('Both passwords must be equal.'));
				$error = self::VALIDATION_ERROR;
				$ret = false;
			}
		}

		if (!$ret) {
			switch ($error) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('zabbix.php?action=user.edit');
					$response->setFormData($this->getInputAll());
					$response->setMessageError(_('Cannot add user'));
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
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$user = [];

		$this->getInputs($user, ['alias', 'name', 'surname', 'url', 'autologin', 'theme', 'refresh', 'rows_per_page',
			'lang'
		]);
		$user['autologout'] = $this->hasInput('autologout_visible') ? $this->getInput('autologout') : '0';
		$user['usrgrps'] = zbx_toObject($this->getInput('user_groups', []), 'usrgrpid');
		$user['type'] = $this->getInput('user_type');
		$user_medias = $this->getInput('user_medias', []);

		if ($this->getInput('password1', '') !== '') {
			$user['passwd'] = $this->getInput('password1');
		}

		foreach ($user_medias as $media) {
			$user['user_medias'][] = [
				'mediatypeid' => $media['mediatypeid'],
				'sendto' => $media['sendto'],
				'active' => $media['active'],
				'severity' => $media['severity'],
				'period' => $media['period']
			];
		}

		$result = (bool) API::User()->create($user);

		if ($result) {
			$response = new CControllerResponseRedirect('zabbix.php?action=user.list&uncheck=1');
			$response->setMessageOk(_('User added'));
		}
		else {
			$response = new CControllerResponseRedirect('zabbix.php?action=user.edit');
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot add user'));
		}
		$this->setResponse($response);
	}
}

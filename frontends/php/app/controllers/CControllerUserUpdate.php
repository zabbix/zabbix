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


class CControllerUserUpdate extends CController {

	private $is_profile;

	protected function checkInput() {
		$themes = array_keys(Z::getThemes());
		$themes[] = THEME_DEFAULT;

		$supported_locales = array_keys(getLocales());

		$this->is_profile = ($this->getAction() === 'profile.update') ? true : false;

		// shite
		$fields = [
			'userid' =>				'fatal|required|db users.userid',
			'password1' =>			'db users.passwd',
			'password2' =>			'db users.passwd',
			'user_medias' =>		'array',
			'lang' =>				'db users.lang|in '.implode(',', $supported_locales),
			'theme' =>				'db users.theme|in '.implode(',', $themes),
			'autologin' =>			'db users.autologin|in 0,1',
			'autologout' =>			'db users.autologout',
			'autologout_visible' =>	'in 0,1',
			'url' =>				'string',
			'refresh' =>			'required|string|not_empty',
			'rows_per_page' =>		'required|int32|not_empty|ge 1|le 999999',
			'form_refresh' =>		'int32'
		];

		if ($this->is_profile) {
			$fields += [
				'messages' =>		'array'
			];
		}
		else {
			$fields += [
				'alias' =>			'required|db users.alias|not_empty',
				'name' =>			'db users.name',
				'surname' =>		'db users.surname',
				'user_type' =>		'db users.type|in '.USER_TYPE_ZABBIX_USER.','.USER_TYPE_ZABBIX_ADMIN.','.USER_TYPE_SUPER_ADMIN,
				'user_groups' =>	'required|array_id|not_empty'
			];
		}

		$ret = $this->validateInput($fields);
		$error = $this->GetValidationError();

		if ($ret) {
			$userid = $this->is_profile ? CWebUser::$data['userid'] : $this->getInput('userid');

			$auth_type = getUserAuthenticationType($userid);

			if ($auth_type != ZBX_AUTH_INTERNAL) {
				$password1 = null;
				$password2 = null;
			}
			else {
				$password1 = $this->hasInput('password1') ? $this->getInput('password1') : null;
				$password2 = $this->hasInput('password2') ? $this->getInput('password2') : null;
			}

			if ($password1 !== $password2) {
				error(_('Cannot update user. Both passwords must be equal.'));
				$ret = false;
			}
			elseif ($password1 !== null && CWebUser::$data['alias'] != ZBX_GUEST_USER && zbx_empty($password1)) {
				error(_s('Incorrect value for field "%1$s": cannot be empty.', 'passwd'));
				$ret = false;
			}
		}

		if (!$ret) {
			switch ($error) {
				case self::VALIDATION_ERROR:
				case self::VALIDATION_OK:
					$response = new CControllerResponseRedirect('zabbix.php?action='.
						($this->is_profile ? 'profile.edit' : 'user.edit')
					);
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
		if (!$this->is_profile && $this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}

		return (bool) API::User()->get([
			'output' => [],
			'userids' => $this->is_profile ? CWebUser::$data['userid'] : $this->getInput('userid'),
			'editable' => true
		]);
	}

	protected function doAction() {
		$user = [];

		$fields = ['url', 'autologin', 'theme', 'refresh', 'rows_per_page', 'lang'];

		if ($this->is_profile) {
			$user['userid'] = CWebUser::$data['userid'];
			$messages = $this->getInput('messages', []);
		}
		else {
			$fields = array_merge($fields, ['userid', 'alias', 'name', 'surname']);
		}
		$this->getInputs($user, $fields);

		$user['autologout'] = $this->hasInput('autologout_visible') ? $this->getInput('autologout') : '0';

		if (!$this->is_profile) {
			$user['usrgrps'] = zbx_toObject($this->getInput('user_groups', []), 'usrgrpid');
			$user['type'] = $this->getInput('user_type');
		}

		if ($this->getInput('password1', '') !== '') {
			$user['passwd'] = $this->getInput('password1');
		}

		if (!$this->is_profile || ($this->is_profile && CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER)) {
			$user_medias = $this->getInput('user_medias', []);

			foreach ($user_medias as $media) {
				$user['user_medias'][] = [
					'mediatypeid' => $media['mediatypeid'],
					'sendto' => $media['sendto'],
					'active' => $media['active'],
					'severity' => $media['severity'],
					'period' => $media['period']
				];
			}
		}

		if ($this->is_profile) {
			DBstart();
			$result = updateMessageSettings($messages);
			$result = $result && (bool) API::User()->update($user);
			$result = DBend($result);
		}
		else {
			$result = (bool) API::User()->update($user);
		}

		if ($result) {
			if ($this->is_profile) {
				redirect(ZBX_DEFAULT_URL);
			}
			else {
				$response = new CControllerResponseRedirect('zabbix.php?action=user.list&uncheck=1');
				$response->setMessageOk(_('User updated'));
			}
		}
		else {
			$response = new CControllerResponseRedirect('zabbix.php?action='.
				($this->is_profile ? 'profile.edit' : 'user.edit')
			);
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot update user'));
		}
		$this->setResponse($response);
	}
}

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
 * Class containing operations with user profile edit form.
 */
class CControllerUserProfileEdit extends CControllerUserEditGeneral {

	protected function checkInput(): bool {
		$locales = array_keys(getLocales());
		$locales[] = LANG_DEFAULT;
		$themes = array_keys(APP::getThemes());
		$themes[] = THEME_DEFAULT;

		$fields = [
			'change_password' =>	'in 1',
			'current_password' =>	'string',
			'password1' =>			'string',
			'password2' =>			'string',
			'lang' =>				'db users.lang|in '.implode(',', $locales),
			'timezone' =>			'db users.timezone|in '.implode(',', array_keys($this->timezones)),
			'theme' =>				'db users.theme|in '.implode(',', $themes),
			'autologin' =>			'db users.autologin|in 0,1',
			'autologout' =>			'db users.autologout',
			'refresh' =>			'db users.refresh',
			'rows_per_page' =>		'db users.rows_per_page',
			'url' =>				'db users.url',
			'messages' =>			'array',
			'form_refresh' =>		'int32'
		];

		if (CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER) {
			$fields += [
				'medias' =>			'array'
			];
		}

		$ret = $this->validateInput($fields);
		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (CWebUser::isGuest() || !CWebUser::isLoggedIn()) {
			return false;
		}

		$users = API::User()->get([
			'output' => ['username', 'name', 'surname', 'lang', 'theme', 'autologin', 'autologout', 'refresh',
				'rows_per_page', 'url', 'timezone', 'provisioned'
			],
			'userids' => CWebUser::$data['userid'],
			'editable' => true
		]);

		if (!$users) {
			return false;
		}

		$this->user = $users[0];

		return true;
	}

	/**
	 * Set user medias if user is at least admin and set messages in data.
	 */
	protected function doAction(): void {
		$data = [
			'current_password' => '',
			'password1' => '',
			'password2' => '',
			'lang' => $this->user['lang'],
			'timezone' => $this->user['timezone'],
			'theme' => $this->user['theme'],
			'autologin' => $this->user['autologin'],
			'autologout' => $this->user['autologout'],
			'refresh' => $this->user['refresh'],
			'rows_per_page' => $this->user['rows_per_page'],
			'url' => $this->user['url'],
			'form_refresh' => 0
		];

		// Overwrite with input variables.
		$this->getInputs($data, array_keys($data));

		$data += [
			'userid' => CWebUser::$data['userid'],
			'username' => $this->user['username'],
			'name' => $this->user['name'],
			'surname' => $this->user['surname'],
			'change_password' => $this->hasInput('change_password') || $this->hasInput('password1'),
			'timezones' => $this->timezones,
			'action' => $this->getAction(),
			'internal_auth' => CWebUser::$data['auth_type'] == ZBX_AUTH_INTERNAL,
			'password_requirements' => $this->getPasswordRequirements(),
			'readonly' => $this->user['provisioned'] == CUser::PROVISION_STATUS_YES
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Profile'));
		$this->setResponse($response);
	}
}

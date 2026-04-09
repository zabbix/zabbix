<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'userid' => ['db users.userid', 'required'],
			'change_password' => ['boolean'],
			'allow_empty_password' => ['boolean'],
			'password1' => [
				[
					'string', 'required', 'not_empty',
					'when' => [['allow_empty_password', 'in' => [0]], ['change_password', 'in' => [1]]]
				],
				[
					'string', 'required',
					'use' => [CPasswordComplexityValidator::class, [
						'passwd_min_length' => CAuthenticationHelper::get(CAuthenticationHelper::PASSWD_MIN_LENGTH),
						'passwd_check_rules' => CAuthenticationHelper::get(CAuthenticationHelper::PASSWD_CHECK_RULES)
					]],
					'when' => ['change_password', 'in' => [1]]
				]
			],
			'password2' => ['string', 'required', 'when' => ['change_password', 'in' => [1]]],
			'current_password' => [
				['string'],
				[
					'string', 'required', 'not_empty',
					'when' => [['allow_empty_password', 'in' => [0]], ['change_password', 'in' => [1]]]
				]
			],
			'lang' => ['db users.lang', 'in' => self::getAllowedLocales()],
			'timezone' => ['db users.timezone', 'in' => self::getAllowedTimezones()],
			'theme' => ['db users.theme', 'in' => self::getAllowedThemes()],
			'autologin' => ['boolean'],
			'autologout_visible' => ['boolean'],
			'autologout' => ['db users.autologout', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 90, 'max' => SEC_PER_DAY, 'accept_zero' => true]],
				'when' => [
					['autologin', 'in' => [0]],
					['autologout_visible', 'in' => [1]]
				]
			],
			'refresh' => ['db users.refresh', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 0, 'max' => SEC_PER_HOUR]]
			],
			'rows_per_page' => ['db users.rows_per_page', 'required', 'min' => 1, 'max' => 999999],
			'url' => ['db users.url'
				// 'use' => [CHtmlUrlValidator::class, ['allow_user_macro' => false]]
			]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if ($ret && (!$this->validateCurrentPassword() || !$this->validatePassword())) {
			$ret = false;
		}

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot update user'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return (bool) API::User()->get([
			'output' => [],
			'userids' => $this->getInput('userid'),
			'editable' => true
		]);
	}

	protected function doAction(): void {
		$user = [];

		$this->getInputs($user, ['lang', 'timezone', 'theme', 'autologin', 'autologout', 'refresh', 'rows_per_page',
			'url'
		]);

		if ($this->getInput('autologout_visible') == 0) {
			$user['autologout'] = 0;
		}

		$user['userid'] = CWebUser::$data['userid'];

		if ($this->getInput('current_password', '') !== ''
				|| ($this->hasInput('current_password') && CWebUser::$data['auth_type'] == ZBX_AUTH_INTERNAL)) {
			$user['current_passwd'] = $this->getInput('current_password');
		}

		if ($this->getInput('password1', '') !== ''
				|| ($this->hasInput('password1') && CWebUser::$data['auth_type'] == ZBX_AUTH_INTERNAL)) {
			$user['passwd'] = $this->getInput('password1');
		}

		DBstart();
		$result = (bool) API::User()->update($user);
		$result = DBend($result);

		if ($result) {
			$response = ['success' => [
				'title' => _('User updated'),
				'redirect' => array_key_exists('passwd', $user)
					? (new CUrl('index.php'))->getUrl()
					: (new CUrl(CMenuHelper::getFirstUrl()))->getUrl()
			]];
		}
		else {
			$response = ['error' => [
				'title' => _('Cannot update user'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			]];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($response)]));
	}
}

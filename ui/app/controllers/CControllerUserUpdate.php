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
 * Class containing operations for updating a user.
 */
class CControllerUserUpdate extends CControllerUserUpdateGeneral {

	public static function getValidationRules(): array {
		$api_uniq = [
			['user.get', ['username' => '{username}'], 'userid']
		];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'userid' => ['db users.userid', 'required'],
			'username' => ['db users.username', 'required', 'not_empty'],
			'name' => ['db users.name'],
			'surname' => ['db users.surname'],
			'user_groups' => ['array', 'field' => ['db users_groups.usrgrpid']],
			'change_password' => ['boolean'],
			'password1' => ['string', 'required',
				'use' => [CPasswordComplexityValidator::class, [
					'passwd_min_length' => CAuthenticationHelper::get(CAuthenticationHelper::PASSWD_MIN_LENGTH),
					'passwd_check_rules' => CAuthenticationHelper::get(CAuthenticationHelper::PASSWD_CHECK_RULES)
				]],
				'when' => ['change_password', 'in' => [1]]
			],
			'password2' => ['string', 'required', 'when' => ['change_password', 'in' => [1]]],
			'current_password' => ['string'],
			'medias' => ['objects', 'fields' => [
				'mediaid' => ['db media.mediaid'],
				'mediatypeid' => ['db media.mediatypeid', 'required'],
				'mediatype_type' => ['integer', 'required'],
				'sendto' => [
					[
						'db media.sendto', 'required', 'not_empty',
						'when' => ['mediatype_type', 'not_in' => [MEDIA_TYPE_EMAIL]]
					],
					[
						'array', 'required', 'not_empty',
						'field' => ['db media.sendto', 'required'
							// TODO: uncomment with DEV-4644
							// 'not_empty', 'use' => [CEmailValidator::class, []]
						],
						'when' => ['mediatype_type', 'in' => [MEDIA_TYPE_EMAIL]]
					]
				],
				'period' => ['string', 'required', 'not_empty',
					'use' => [CTimePeriodsParser::class, ['usermacros' => true]],
					'messages' => ['use' => _('Invalid period.')]
				],
				'severity' => ['db media.severity', 'required'],
				'active' => ['integer', 'required', 'in' => [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED]]
			]],
			'lang' => ['db users.lang', 'in' => self::getAllowedLocales(),
				'when' => ['username', 'not_in' => [ZBX_GUEST_USER]]
			],
			'timezone' => ['db users.timezone', 'in' => self::getAllowedTimezones(),
				'when' => ['username', 'not_in' => [ZBX_GUEST_USER]]
			],
			'theme' => ['db users.theme', 'in' => self::getAllowedThemes(),
				'when' => ['username', 'not_in' => [ZBX_GUEST_USER]]
			],
			'autologin' => ['boolean'],
			'autologout_visible' => ['boolean', 'when' => ['username', 'not_in' => [ZBX_GUEST_USER]]],
			'autologout' => ['db users.autologout', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 90, 'max' => SEC_PER_DAY, 'accept_zero' => true]],
				'when' => [
					['username', 'not_in' => [ZBX_GUEST_USER]],
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
			],
			'roleid' => ['db users.roleid', 'required']
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (CWebUser::$data['userid'] == $this->getInput('userid')
				&& CWebUser::$data['roleid'] == USER_TYPE_SUPER_ADMIN) {
			if ($ret && !$this->validateCurrentPassword()) {
				$ret = false;
			}
		}

		if ($ret && (!$this->validatePassword() || !$this->validateUserRole())) {
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
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USERS)) {
			return false;
		}

		return (bool) API::User()->get([
			'output' => [],
			'userids' => $this->getInput('userid'),
			'editable' => true
		]);
	}

	protected function doAction(): void {
		$user = [
			'roleid' => 0
		];

		$this->getInputs($user, ['userid', 'username', 'name', 'surname', 'lang', 'timezone', 'theme', 'autologin',
			'autologout', 'refresh', 'rows_per_page', 'url', 'roleid'
		]);

		if ($this->hasInput('autologout_visible') && $this->getInput('autologout_visible') == 0) {
			$user['autologout'] = 0;
		}

		$can_edit_media = bccomp(CWebUser::$data['userid'], $user['userid']) == 0
			? $this->checkAccess(CRoleHelper::ACTIONS_EDIT_OWN_MEDIA)
			: $this->checkAccess(CRoleHelper::ACTIONS_EDIT_USER_MEDIA);

		if ($can_edit_media) {
			$user['medias'] = $this->getInputUserMedia();
		}

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
			$response = ['success' => ['title' => _('User updated')]];

			if (array_key_exists('passwd', $user) && CWebUser::$data['userid'] == $user['userid']) {
				$response['success']['redirect'] = (new CUrl('index.php'))->getUrl();
			}
			else {
				$response['success']['redirect'] = (new CUrl('zabbix.php'))
					->setArgument('action', 'user.list')
					->setArgument('page', CPagerHelper::loadPage('user.list', null))
					->getUrl();
			}
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

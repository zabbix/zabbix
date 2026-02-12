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


class CControllerUserCreate extends CControllerUserUpdateGeneral {

	public static function getValidationRules(): array {
		$api_uniq = [
			['user.get', ['username' => '{username}']]
		];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
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

		if ($ret && (!$this->validatePassword() || !$this->validateUserRole())) {
			$ret = false;
		}

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot add user'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_USERS);
	}

	protected function doAction(): void {
		$user = [];

		$this->getInputs($user, ['username', 'name', 'surname', 'url', 'autologin', 'autologout', 'theme', 'refresh',
			'rows_per_page', 'lang', 'timezone', 'roleid'
		]);

		if ($this->hasInput('autologout_visible') && $this->getInput('autologout_visible') == 0) {
			$user['autologout'] = 0;
		}

		$user['usrgrps'] = zbx_toObject($this->getInput('user_groups', []), 'usrgrpid');

		if ($this->getInput('password1', '') !== '' || !$this->allow_empty_password) {
			$user['passwd'] = $this->getInput('password1');
		}

		if ($this->checkAccess(CRoleHelper::ACTIONS_EDIT_USER_MEDIA)) {
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
		}

		$result = (bool) API::User()->create($user);

		if ($result) {
			$response = ['success' => [
				'title' => _('User added'),
				'redirect' => (new CUrl('zabbix.php'))
					->setArgument('action', 'user.list')
					->setArgument('page', CPagerHelper::loadPage('user.list', null))
					->getUrl()
			]];
		}
		else {
			$response = ['error' => [
				'title' => _('Cannot add user'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			]];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($response)]));
	}
}

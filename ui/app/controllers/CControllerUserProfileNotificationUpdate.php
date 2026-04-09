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
 * Class containing operations for updating user profile notification.
 */
class CControllerUserProfileNotificationUpdate extends CControllerUserUpdateGeneral {

	public static function getValidationRules(bool $with_medias): array {
		$sounds = array_values(getSounds());

		$rules = ['object', 'fields' => [
			'userid' => ['db users.userid', 'required'],
			'messages' => ['object', 'required', 'fields' => [
				'enabled' => ['boolean'],
				'timeout' => ['db profiles.value_str', 'required', 'not_empty',
					'use' => [CTimeUnitValidator::class, ['min' => 30, 'max' => SEC_PER_DAY]],
					'when' => ['enabled', 'in' => [1]]
				],
				'sounds.repeat' => ['integer', 'required', 'in' => [1, 10, -1], 'when' => ['enabled', 'in' => [1]]],
				'triggers.recovery' => ['boolean', 'when' => ['enabled', 'in' => [1]]],
				'triggers.severities' => ['object', 'when' => ['enabled', 'in' => [1]], 'fields' => [
					'0' => ['boolean'],
					'1' => ['boolean'],
					'2' => ['boolean'],
					'3' => ['boolean'],
					'4' => ['boolean'],
					'5' => ['boolean']
				]],
				'sounds.recovery' => ['db profiles.value_str', 'required', 'in' => $sounds,
					'when' => ['enabled', 'in' => [1]]
				],
				'sounds.0' => ['db profiles.value_str', 'required', 'in' => $sounds,
					'when' => ['enabled', 'in' => [1]]
				],
				'sounds.1' => ['db profiles.value_str', 'required', 'in' => $sounds,
					'when' => ['enabled', 'in' => [1]]
				],
				'sounds.2' => ['db profiles.value_str', 'required', 'in' => $sounds,
					'when' => ['enabled', 'in' => [1]]
				],
				'sounds.3' => ['db profiles.value_str', 'required', 'in' => $sounds,
					'when' => ['enabled', 'in' => [1]]
				],
				'sounds.4' => ['db profiles.value_str', 'required', 'in' => $sounds,
					'when' => ['enabled', 'in' => [1]]
				],
				'sounds.5' => ['db profiles.value_str', 'required', 'in' => $sounds,
					'when' => ['enabled', 'in' => [1]]
				],
				'show_suppressed' => ['boolean', 'when' => ['enabled', 'in' => [1]]]
			]]
		]];

		if ($with_medias) {
			$rules['fields'] += ['medias' =>
				['objects', 'fields' => [
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
				]]
			];
		}

		return $rules;
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules($this->canEditMedia()));

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

	private function canEditMedia(): bool {
		return $this->checkAccess(CRoleHelper::ACTIONS_EDIT_OWN_MEDIA);
	}

	protected function doAction(): void {
		$user = [
			'userid' => CWebUser::$data['userid']
		];

		if ($this->canEditMedia()) {
			$user['medias'] = $this->getInputUserMedia();
		}

		DBstart();
		$result = updateMessageSettings($this->getInput('messages', []));
		$result = $result && (bool) API::User()->update($user);
		$result = DBend($result);

		if ($result) {
			$response = ['success' => [
				'title' => _('User updated'),
				'redirect' => (new CUrl(CMenuHelper::getFirstUrl()))->getUrl()
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

<?php declare(strict_types = 0);
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


class CControllerPopupMediaCheck extends CController {

	private ?array $mediatype;
	private ?array $sendto_emails;

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'row_index' => ['integer', 'required'],
			'userid' => ['db users.userid', 'required'],
			'mediaid' => ['db media.mediaid'],
			'mediatypeid' => ['db media.mediatypeid', 'required'],
			'mediatype_type' => ['integer', 'required'],
			'sendto' => ['db media.sendto', 'not_empty',
				'when' => ['mediatype_type', 'not_in' => [MEDIA_TYPE_EMAIL]]
			],
			'sendto_emails' => ['array', 'required', 'not_empty',
				'field' => ['string'
					// TODO: uncomment with DEV-4644
					// 'not_empty', 'use' => [CEmailValidator::class, []]
				],
				'when' => ['mediatype_type', 'in' => [MEDIA_TYPE_EMAIL]]
			],
			'period' => ['string', 'required', 'not_empty',
				'use' => [CTimePeriodsParser::class, ['usermacros' => true]],
				'messages' => ['use' => _('Invalid period.')]
			],
			'severities' => ['array',
				'field' => ['integer', 'min' => 0, 'max' => 6]
			],
			'active' => ['integer', 'required', 'in' => [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED]],
			'provisioned' => ['integer', 'required', 'in' => [CUser::PROVISION_STATUS_NO, CUser::PROVISION_STATUS_YES]]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules()) && $this->validateMediatypeid() && $this->validateSendto();

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	private function validateMediatypeid(): bool {
		$mediatypeid = $this->getInput('mediatypeid');

		$db_mediatypes = API::MediaType()->get([
			'output' => ['mediatypeid', 'name', 'type', 'status'],
			'mediatypeids' => [$mediatypeid]
		]);

		if (!$db_mediatypes) {
			error(_s('Media type with ID "%1$s" is not available.', $mediatypeid));

			return false;
		}

		$this->mediatype = $db_mediatypes[0];

		return true;
	}

	private function validateSendto(): bool {
		if ($this->mediatype['type'] == MEDIA_TYPE_EMAIL) {
			$sendto_emails = array_values(array_filter($this->getInput('sendto_emails', [])));

			if (!$sendto_emails) {
				error(_s('Incorrect value for field "%1$s": %2$s.', 'sendto_emails', _('cannot be empty')));

				return false;
			}

			$email_validator = new CEmailValidator();

			foreach ($sendto_emails as $email) {
				if (!$email_validator->validate($email)) {
					error($email_validator->getError());

					return false;
				}
			}

			$this->sendto_emails = $sendto_emails;
		}
		elseif ($this->getInput('sendto', '') === '') {
			error(_s('Incorrect value for field "%1$s": %2$s.', 'sendto', _('cannot be empty')));

			return false;
		}

		return true;
	}

	protected function checkPermissions(): bool {
		if (bccomp($this->getInput('userid'), CWebUser::$data['userid']) == 0) {
			return $this->checkAccess(CRoleHelper::ACTIONS_EDIT_OWN_MEDIA);
		}

		return $this->checkAccess(CRoleHelper::ACTIONS_EDIT_USER_MEDIA);
	}

	protected function doAction(): void {
		$severity = 0;
		foreach ($this->getInput('severities', []) as $id) {
			$severity |= 1 << $id;
		}

		$data = [
			'row_index' => $this->getInput('row_index'),
			'mediatypeid' => $this->mediatype['mediatypeid'],
			'sendto' => $this->mediatype['type'] == MEDIA_TYPE_EMAIL
				? $this->sendto_emails
				: $this->getInput('sendto'),
			'period' => $this->getInput('period'),
			'severity' => $severity,
			'active' => $this->getInput('active', MEDIA_STATUS_DISABLED),
			'provisioned' => $this->getInput('provisioned', CUser::PROVISION_STATUS_NO),
			'mediatype_name' => $this->mediatype['name'],
			'mediatype_status' => $this->mediatype['status'],
			'mediatype_type' => $this->mediatype['type']
		];

		if ($this->hasInput('mediaid')) {
			$data['mediaid'] = $this->getInput('mediaid');
		}

		if ($this->getDebugMode() == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$data['debug'] = CProfiler::getInstance()->make()->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}

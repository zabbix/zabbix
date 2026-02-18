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


class CControllerMfaCheck extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	public static function getValidationRules(array $existing_names = []): array {
		$name_extra_rules = ['string'];

		if (count($existing_names) > 0) {
			$name_extra_rules += ['not_in' => $existing_names,
				'messages' => ['not_in' => _('Name already exists.')]
			];
		}

		return ['object', 'fields' => [
			'mfaid' => ['db mfa.mfaid'],
			'existing_names' => ['array', 'field' => ['string']],
			'type' => ['db mfa.type', 'required', 'in' => [MFA_TYPE_TOTP, MFA_TYPE_DUO]],
			'name' => [
				['db mfa.name', 'required', 'not_empty'],
				$name_extra_rules
			],
			'hash_function' => ['db mfa.hash_function', 'required',
				'in' => [TOTP_HASH_SHA1, TOTP_HASH_SHA256, TOTP_HASH_SHA512],
				'when' => ['type', 'in' => [MFA_TYPE_TOTP]]
			],
			'code_length' => ['db mfa.code_length', 'required', 'in' => [TOTP_CODE_LENGTH_6, TOTP_CODE_LENGTH_8],
				'when' => ['type', 'in' => [MFA_TYPE_TOTP]]
			],
			'api_hostname' => ['db mfa.api_hostname', 'required', 'not_empty',
				'when' => ['type', 'in' => [MFA_TYPE_DUO]]
			],
			'clientid' => ['db mfa.clientid', 'required', 'not_empty',
				'when' => ['type', 'in' => [MFA_TYPE_DUO]]
			],
			'client_secret' => ['db mfa.client_secret', 'not_empty', 'when' => ['type', 'in' => [MFA_TYPE_DUO]]]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if ($ret && $this->getInput('existing_names', []) !== []) {
			$ret = $this->validateInput(self::getValidationRules($this->getInput('existing_names', [])));
		}

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Invalid MFA configuration'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION);
	}

	protected function doAction(): void {
		$data = [
			'type' => MFA_TYPE_TOTP,
			'name' => '',
			'hash_function' => TOTP_HASH_SHA1,
			'code_length' => TOTP_CODE_LENGTH_6,
			'api_hostname' => '',
			'clientid' => ''
		];

		$this->getInputs($data, array_keys($data));

		if ($this->hasInput('mfaid')) {
			$data['mfaid'] = $this->getInput('mfaid');
		}

		if ($this->hasInput('client_secret')) {
			$data['client_secret'] = $this->getInput('client_secret');
		}

		$data['type_name'] = ($data['type'] == MFA_TYPE_TOTP) ? _('TOTP') : _('Duo Universal Prompt');

		switch ($data['type']) {
			case MFA_TYPE_TOTP:
				unset($data['api_hostname'], $data['clientid'], $data['client_secret']);
				break;

			case MFA_TYPE_DUO:
				unset($data['hash_function'], $data['code_length']);
				break;
		}

		if ($this->getDebugMode() == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$data['debug'] = CProfiler::getInstance()->make()->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}

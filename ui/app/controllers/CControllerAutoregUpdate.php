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


class CControllerAutoregUpdate extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'tls_in_psk' => ['boolean'],
			'tls_in_none' => [
				['boolean'],
				['boolean', 'required', 'when' => ['tls_in_psk', false],
					'messages' => ['in' => _('At least one encryption level must be selected.')]
				]
			],
			'psk_required' => ['boolean'],
			'tls_psk_identity' => [
				['db config_autoreg_tls.tls_psk_identity'],
				['db config_autoreg_tls.tls_psk_identity', 'required', 'not_empty',
					'when' => [['tls_in_psk', true], ['psk_required', true]]
				]
			],
			'tls_psk' => [
				['db config_autoreg_tls.tls_psk',
					'regex' => ZBX_TLS_PSK_PATTERN,
					'messages' => ['regex' => _('PSK must be an even number of characters.')]
				],
				['db config_autoreg_tls.tls_psk',
					'regex' => '/.{32,}/',
					'messages' => ['regex' => _('PSK must be at least 32 characters long.')]
				],
				['db config_autoreg_tls.tls_psk',
					'regex' => '/^[0-9a-f]*$/i',
					'messages' => ['regex' => _('PSK must contain only hexadecimal characters.')]
				],
				['db config_autoreg_tls.tls_psk', 'required', 'not_empty',
					'when' => [['tls_in_psk', true], ['psk_required', true]]
				]
			]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot update configuration'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction(): void {
		$data = [
			'tls_accept' => 0x00
		];

		if ($this->getInput('tls_in_none', 0)) {
			$data['tls_accept'] |= HOST_ENCRYPTION_NONE;
		}

		if ($this->getInput('tls_in_psk', 0)) {
			$data['tls_accept'] |= HOST_ENCRYPTION_PSK;

			foreach (['tls_psk_identity', 'tls_psk'] as $field) {
				if ($this->hasInput($field) && $this->getInput($field, '') !== '') {
					$data[$field] = $this->getInput($field);
				}
			}
		}

		$result = (bool) API::Autoregistration()->update($data);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Configuration updated');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update configuration'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}

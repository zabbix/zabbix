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


class CControllerApmDbUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
	}

	public static function getValidationRules(): array {
		$status_enabled = ['status', 'in' => [1]];
		$auth_type_basic = ['authentication_type', 'in' => [APM_AUTH_TYPE_PASSWORD]];
		$auth_type_vault_path = ['authentication_type', 'in' => [APM_AUTH_TYPE_VAULT_PATH]];

		return ['object', 'fields' => [
			'status' => ['boolean', 'required', 'in' => [0, 1]],
			'url' => ['string', 'required', 'not_empty', 'length' => 2048, 'when' => $status_enabled,
				'use' => [CUrlValidator::class, ['schemes' => ['http', 'https']]]],
			'authentication_type' => ['integer', 'required',
				'in' => [APM_AUTH_TYPE_PASSWORD, APM_AUTH_TYPE_VAULT_PATH, APM_AUTH_TYPE_NONE],
				'when' => $status_enabled
			],
			'username' => ['string', 'required', 'not_empty', 'length' => 255,
				'when' => [$status_enabled, $auth_type_basic]],
			'password' => ['string', 'required', 'length' => 255, 'when' => [$status_enabled, $auth_type_basic]],
			'vault_path' => ['string', 'required', 'not_empty', 'length' => 255,
				'when' => [$status_enabled, $auth_type_vault_path]],
			'db' => ['string', 'required', 'length' => 255, 'when' => $status_enabled],
			'ssl_verify_peer' => ['boolean', 'required', 'when' => $status_enabled],
			'ssl_ca_location' => ['string', 'required', 'length' => 2048,
				'when' => [$status_enabled, ['ssl_verify_peer', 'in' => [1]]]
			],
			'ssl_verify_host' => ['boolean', 'required', 'when' => $status_enabled],
			'ssl_cert_file' => ['string', 'required', 'length' => 2048, 'when' => $status_enabled],
			'ssl_key_file' => ['string', 'required', 'length' => 2048, 'when' => $status_enabled],
			'ssl_key_password' => [
				['string', 'length' => 255],
				['string', 'length' => 255, 'in' => [''],
					'when' => [$status_enabled, ['ssl_key_file', 'in' => ['']]],
					'messages' => ['in' => 'Must be empty, if previous field is not specified.']
				]
			],
			'change_password' => ['integer', 'required', 'in' => [0, 1]],
		]];
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_DATA_SOURCE);
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

	protected function doAction(): void {
		$apm = $this->getInputAll();
		self::processApmInput($apm);

		// TODO: ApmSettings->update($apm)
		$result = true;

		$output = [];

		if ($result) {
			$output['success'] = [
				'title' => _('APM data source updated.')
			];
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update configuration'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	protected static function processApmInput(array &$apm): void {
		$reset_fields = [];

		switch ($apm['authentication_type']) {
			case APM_AUTH_TYPE_PASSWORD:
				$reset_fields = ['vault_path'];
				break;

			case APM_AUTH_TYPE_VAULT_PATH:
				$reset_fields = ['username', 'password'];
				break;

			case APM_AUTH_TYPE_NONE:
				$reset_fields = ['vault_path', 'username', 'password'];
				break;
		}

		$apm['url'] = CUrlValidator::sanitizeUrl($apm['url']);

		if (str_starts_with($apm['url'], 'https://')) {
			if ($apm['ssl_verify_peer'] === 0) {
				$reset_fields = array_merge($reset_fields, ['ssl_verify_host', 'ssl_ca_location']);
			}
		} else {
			$reset_fields = array_merge($reset_fields, ['ssl_verify_peer', 'ssl_ca_location', 'ssl_verify_host',
				'ssl_cert_file', 'ssl_key_file', 'ssl_key_password']);
		}

		$default_values = CControllerApmDbEdit::getDefaultValues();

		foreach ($reset_fields as $field) {
			if (array_key_exists($field, $default_values)) {
				$apm[$field] = $default_values[$field];
			}
		}

		$apm = array_merge([
			// TODO: ApmSettings->get()
		], $apm);

		unset($apm['change_password']);
	}
}

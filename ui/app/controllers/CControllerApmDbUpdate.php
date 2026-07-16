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
		$encryption_enabled = ['encryption', 'in' => [1]];
		$type_sql = ['type', 'in' => [ZBX_DB_MYSQL, ZBX_DB_POSTGRESQL]];
		$type_elasticsearch = ['type', 'in' => [ZBX_DB_ELASTICSEARCH]];

		return ['object', 'fields' => [
			'status' => ['boolean', 'required', 'in' => [0, 1]],
			'type' => ['string', 'required',
				'in' => [ZBX_DB_CLICKHOUSE, ZBX_DB_MYSQL, ZBX_DB_POSTGRESQL, ZBX_DB_ELASTICSEARCH],
				'when' => $status_enabled
			],
			'host' => ['string', 'required', 'not_empty', 'length' => 255, 'when' => $status_enabled],
			'port' => ['integer', 'required', 'min' => 0, 'max' => 65535, 'when' => $status_enabled],
			'database' => ['string', 'required', 'not_empty', 'length' => 255,
				'when' => [$status_enabled, $type_sql]
			],
			'authentication' => ['integer', 'required',
				'in' => [ELASTICSEARCH_AUTH_NONE, ELASTICSEARCH_AUTH_BASIC, ELASTICSEARCH_AUTH_API_KEY],
				'when' => [$status_enabled, $type_elasticsearch]
			],
			'change_password' => ['integer', 'required', 'in' => [0, 1], 'when' => $status_enabled],
			'change_api_key' => ['integer', 'required', 'in' => [0, 1], 'when' => $status_enabled],
			'username' => ['string', 'required', 'length' => 255, 'when' => $status_enabled],
			'password' => ['string', 'required', 'length' => 255, 'when' => $status_enabled],
			'api_key' => ['string', 'required', 'not_empty', 'length' => 255,
				'when' => [
					['authentication', 'in' => [ELASTICSEARCH_AUTH_API_KEY]],
					['change_api_key', 'in' => [1]]
				]
			],
			'encryption' => ['boolean', 'required', 'when' => $status_enabled],
			'verify_peer' => ['boolean', 'required', 'when' => [$status_enabled, $encryption_enabled]],
			'ca_file' => ['string', 'required', 'length' => 255,
				'when' => [$status_enabled, $encryption_enabled, ['verify_peer', 'in' => [1]]]
			],
			'cert_file' => ['string', 'required', 'length' => 255,
				'when' => [$status_enabled, $encryption_enabled, $type_sql]
			],
			'key_file' => ['string', 'required', 'length' => 255,
				'when' => [$status_enabled, $encryption_enabled, $type_sql]
			],
			'verify_host' => ['boolean', 'required', 'when' => [$status_enabled, $encryption_enabled]],
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
		$default_values = CControllerApmDbEdit::getDefaultValues();

		// TODO: ApmSettings->get()

		switch ($apm['type']) {
			case ZBX_DB_CLICKHOUSE:
				foreach (['schema', 'authentication', 'api_key', 'key_file'] as $key) {
					$apm[$key] = $default_values[$key];
				}
				break;

			case ZBX_DB_ELASTICSEARCH:
				switch ($apm['authentication']) {
					case ELASTICSEARCH_AUTH_NONE:
						foreach (['username', 'password', 'api_key'] as $key) {
							$apm[$key] = $default_values[$key];
						}
						break;

					case ELASTICSEARCH_AUTH_BASIC:
						$apm['api_key'] = $default_values['api_key'];
						break;

					case ELASTICSEARCH_AUTH_API_KEY:
						foreach (['username', 'password'] as $key) {
							$apm[$key] = $default_values[$key];
						}
						break;
				}

				$apm['key_file'] = $default_values['key_file'];
				break;

			case ZBX_DB_MYSQL:
			case ZBX_DB_POSTGRESQL:
				foreach (['authentication', 'api_key'] as $key) {
					$apm[$key] = $default_values[$key];
				}
				break;
		}

		if ($apm['encryption'] == 1) {
			if ($apm['verify_peer'] == 0) {
				$apm['ca_file'] = $default_values['ca_file'];
			}
		}
		else {
			foreach (['verify_peer', 'ca_file', 'cert_file', 'key_file', 'verify_host'] as $key) {
				$apm[$key] = $default_values[$key];
			}
		}

		unset($apm['change_api_key'], $apm['change_password']);
	}
}

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
		$status_configured = ['status', 'in' => [APM_GLOBAL_DB_STATUS_CONFIGURED]];
		$auth_type_password = ['authentication_type', 'in' => [APM_GLOBAL_DB_AUTHTYPE_PASSWORD]];
		$verify_certificate = ['ssl_verify_peer', 'in' => [APM_GLOBAL_DB_VERIFY_PEER_ENABLED]];
		$url_scheme_https = ['url', 'regex' => '/^https\:\/\//i'];

		return ['object', 'fields' => [
			'status' => ['boolean', 'required',
				'in' => [APM_GLOBAL_DB_STATUS_NOT_CONFIGURED, APM_GLOBAL_DB_STATUS_CONFIGURED]],
			'url' => ['string', 'required', 'not_empty', 'length' => 2048, 'when' => [$status_configured],
				'use' => [CUrlValidator::class, ['schemes' => ['http', 'https']]]],
			'authentication_type' => ['integer', 'required',
				'in' => [APM_GLOBAL_DB_AUTHTYPE_PASSWORD, APM_GLOBAL_DB_AUTHTYPE_NONE],
				'when' => [$status_configured]
			],
			'username' => ['string', 'required', 'not_empty', 'length' => 255,
				'when' => [$status_configured, $auth_type_password]],
			'password' => ['string', 'length' => 255, 'when' => [$status_configured, $auth_type_password]],
			'db' => ['string', 'required', 'length' => 255, 'when' => $status_configured],
			'ssl_verify_peer' => ['integer', 'required', 'when' => [$status_configured, $url_scheme_https]],
			'ssl_verify_host' => ['integer', 'required',
				'when' => [$status_configured, $url_scheme_https, $verify_certificate]
			]
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
		$result = API::Settings()->update(['apm_global_db' => $apm]);

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
}

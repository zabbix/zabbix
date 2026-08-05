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


class CControllerApmDbEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_DATA_SOURCE);
	}

	protected function checkInput(): bool {
		return true;
	}

	public static function getDefaultValues(): array {
		return [
			'status' => 0,
			'url' => '',
			'authentication_type' => APM_AUTH_TYPE_PASSWORD,
			'username' => '',
			'password' => '',
			'vault_path' => '',
			'db' => '',
			'ssl_verify_peer' => 0,
			'ssl_ca_location' => '',
			'ssl_verify_host' => 0,
			'ssl_cert_file' => '',
			'ssl_key_file' => '',
			'ssl_key_password' => ''
		];
	}

	protected function doAction(): void {
		$default_values = self::getDefaultValues();

		$data = array_merge($default_values, [
			// TODO: Settings->get()
		]);

		$data['js_validation_rules'] = (new CFormValidator(CControllerApmDbUpdate::getValidationRules()))
			->getRules();
		$data['default_values'] = $default_values;

		$data['has_password'] = $data['authentication_type'] === APM_AUTH_TYPE_PASSWORD;
		$data['has_ssl_key_password'] = $data['ssl_key_file'] !== '';

		$data['show_fields'] = $data['status'] == 1;
		$data['show_user_fields'] = $data['show_fields'] && $data['authentication_type'] === APM_AUTH_TYPE_PASSWORD;
		$data['show_vault_path'] = $data['show_fields'] && $data['authentication_type'] === APM_AUTH_TYPE_VAULT_PATH;
		$data['show_ssl_fields'] = $data['show_fields'] &&  str_starts_with($data['url'], 'https://');
		$data['show_ssl_verify_peer_fields'] = $data['show_ssl_fields'] && $data['ssl_verify_peer'] === 1;

		$response = new CControllerResponseData($data);
		$response->setTitle(_('APM data source'));
		$this->setResponse($response);
	}
}

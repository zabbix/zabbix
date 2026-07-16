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
			'type' => ZBX_DB_CLICKHOUSE,
			'host' => '',
			'port' => '0',
			'database' => '',
			'schema' => '',
			'authentication' => ELASTICSEARCH_AUTH_NONE,
			'username' => '',
			'password' => '',
			'api_key' => '',
			'encryption' => 0,
			'verify_peer' => 0,
			'ca_file' => '',
			'cert_file' => '',
			'key_file' => '',
			'verify_host' => 0,
			'change_password' => 0,
			'change_api_key' => 0
		];
	}

	protected function doAction(): void {
		$default_values = self::getDefaultValues();

		$data = array_merge($default_values, [
			// TODO: ApmSettings->get()
		]);

		$data['js_validation_rules'] = (new CFormValidator(CControllerApmDbUpdate::getValidationRules()))
			->getRules();
		$data['default_values'] = $default_values;

		$data['has_password'] = $data['password'] !== '';
		$data['has_api_key'] = $data['api_key'] !== '';

		$data['is_type_sql'] = in_array($data['type'], [ZBX_DB_MYSQL, ZBX_DB_POSTGRESQL]);
		$data['is_type_postgresql'] = $data['type'] == ZBX_DB_POSTGRESQL;
		$data['is_type_elasticsearch'] = $data['type'] == ZBX_DB_ELASTICSEARCH;

		$data['show_fields'] = $data['status'] == 1;
		$data['show_change_host_btn'] = strlen($data['host']) > 0;
		$data['show_database_fields'] = $data['show_fields'] && !$data['is_type_elasticsearch'];
		$data['show_schema_fields'] = $data['show_fields'] && $data['is_type_postgresql'];
		$data['show_authentication_fields'] = $data['show_fields'] && $data['is_type_elasticsearch'];
		$data['show_api_key_fields'] = $data['show_authentication_fields']
			&& $data['authentication'] === ELASTICSEARCH_AUTH_API_KEY;
		$data['show_encryption_fields'] = $data['show_fields'] && $data['encryption'] === 1;
		$data['show_key_file_fields'] = $data['show_encryption_fields'] && $data['is_type_sql'];
		$data['show_cert_file_fields'] = $data['show_encryption_fields'] && $data['is_type_sql'];
		$data['show_user_fields'] = $data['show_fields']
			&& ($data['show_database_fields']
				|| (!$data['show_api_key_fields'] && $data['authentication'] !== ELASTICSEARCH_AUTH_NONE));
		$data['show_verify_peer'] = $data['show_encryption_fields'] && $data['verify_peer'] === 1;

		$response = new CControllerResponseData($data);
		$response->setTitle(_('APM data source'));
		$this->setResponse($response);
	}
}

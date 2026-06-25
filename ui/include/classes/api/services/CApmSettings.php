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


/**
 * Class containing methods for operations with the APM configuration.
 */
class CApmSettings extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'testconnection' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	public const APM_DB = 'apm_db';

	private array $output_fields = [
		'type', 'server', 'port', 'database', 'user', 'encryption', 'verify_host', 'key_file', 'cert_file', 'ca_file',
		'cipher_list', 'store_creds', 'vault_url', 'vault_prefix', 'vault_db_path', 'vault_token', 'vault_cert_file',
		'vault_key_file'
	];

	public function get(array $options = []): array {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'output' =>	['type' => API_OUTPUT, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->output_fields), 'default' => API_OUTPUT_EXTEND]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_apm_settings = DB::select('settings', [
			'output' => ['value_str'],
			'filter' => ['name' => self::APM_DB]
		]);

		$apm_settings = json_decode($db_apm_settings[0]['value_str'], true);

		return is_array($apm_settings) ? array_intersect_key($apm_settings, array_flip($options['output'])) : [];
	}

	public function update(array $settings): array {
		$this->validate($settings);

		DB::update('settings', [
			'values' => ['value_str' => json_encode($settings)],
			'where' => ['name' => self::APM_DB],
		]);

		unset($settings['password']);

		return $settings;
	}

	public function testconnection($settings): string {
		$this->validate($settings);

		$vault_provider = self::getVaultProvider($settings);

		if ($vault_provider !== null) {
			$db_credentials = $vault_provider->getCredentials();

			$settings['user'] = $db_credentials['user'];
			$settings['password'] = $db_credentials['password'];
		}

		$settings = array_diff_key($settings, array_flip(['store_creds', 'vault_url', 'vault_prefix', 'vault_db_path',
			'vault_token', 'vault_cert_file', 'vault_key_file'
		]));

		return ApmDb::create($settings)->test() ? '1' : '0';
	}

	private function validate(array &$settings): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'type' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => ZBX_DB_CLICKHOUSE],
			'server' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'port' => ['type' => API_INT32, 'in' => ZBX_MIN_PORT_NUMBER.':'.ZBX_MAX_PORT_NUMBER, 'default' => 0],
			'database' => ['type' => API_STRING_UTF8, 'flags' => API_ALLOW_NULL],
			'user' => ['type' => API_STRING_UTF8, 'flags' => API_ALLOW_NULL, 'default' => null],
			'password' => ['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'user', 'in' => null], 'type' => API_UNEXPECTED ],
				['else' => true, 'type' => API_STRING_UTF8, 'flags' => API_ALLOW_NULL]
			]],
			'schema' => ['type' => API_STRING_UTF8, 'flags' => API_ALLOW_NULL],
			'encryption' => ['type' => API_BOOLEAN, 'default' => false],
			'verify_host' => ['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'encryption', 'in' => true], 'type' => API_BOOLEAN, 'default' => false],
				['else' => true, 'type' => API_UNEXPECTED]
			]],
			'key_file' => ['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'encryption', 'in' => true], 'type' => API_STRING_UTF8],
				['else' => true, 'type' => API_UNEXPECTED]
			]],
			'cert_file' => ['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'encryption', 'in' => true], 'type' => API_STRING_UTF8],
				['else' => true, 'type' => API_UNEXPECTED]
			]],
			'ca_file' => ['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'encryption', 'in' => true], 'type' => API_STRING_UTF8],
				['else' => true, 'type' => API_UNEXPECTED]
			]],
			'cipher_list' => ['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'encryption', 'in' => true], 'type' => API_STRING_UTF8],
				['else' => true, 'type' => API_UNEXPECTED]
			]],
			'store_creds' => ['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'user', 'in' => null], 'type' => API_INT32, 'in' => implode(',', [DB_STORE_CREDS_CONFIG, DB_STORE_CREDS_VAULT_HASHICORP, DB_STORE_CREDS_VAULT_CYBERARK]), 'default' => DB_STORE_CREDS_CONFIG],
				['else' => true, 'type' => API_INT32, 'in' => DB_STORE_CREDS_CONFIG, 'default' => DB_STORE_CREDS_CONFIG]
			]],
			'vault_url' => ['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'store_creds', 'in' => DB_STORE_CREDS_CONFIG], 'type' => API_UNEXPECTED],
				['else' => true, 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
			]],
			'vault_prefix' => ['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'store_creds', 'in' => DB_STORE_CREDS_CONFIG], 'type' => API_UNEXPECTED],
				['else' => true, 'type' => API_STRING_UTF8, 'default' => '']
			]],
			'vault_db_path' => ['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'store_creds', 'in' => DB_STORE_CREDS_CONFIG], 'type' => API_UNEXPECTED],
				['else' => true, 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
			]],
			'vault_token' => ['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'store_creds', 'in' => DB_STORE_CREDS_VAULT_HASHICORP], 'type' => API_STRING_UTF8, 'default' => ''],
				['else' => true, 'type' => API_UNEXPECTED]
			]],
			'vault_cert_file' => ['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'store_creds', 'in' => DB_STORE_CREDS_VAULT_CYBERARK], 'type' => API_STRING_UTF8, 'default' => ''],
				['else' => true, 'type' => API_UNEXPECTED]
			]],
			'vault_key_file' => ['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'store_creds', 'in' => DB_STORE_CREDS_VAULT_CYBERARK], 'type' => API_STRING_UTF8, 'default' => ''],
				['else' => true, 'type' => API_UNEXPECTED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $settings, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$vault_provider = self::getVaultProvider($settings);

		if ($vault_provider !== null &&
				(!$vault_provider->validateParameters() || $vault_provider->getCredentials() === null)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, implode(' ', $vault_provider->getErrors()));
		}

		$settings = $settings + array_fill_keys($this->output_fields, null);
	}

	private static function getVaultProvider(array $settings): CVaultHashiCorp|CVaultCyberArk|null {
		return match ($settings['store_creds']) {
			DB_STORE_CREDS_VAULT_HASHICORP => new CVaultHashiCorp($settings['vault_url'], $settings['vault_prefix'],
				$settings['vault_db_path'], $settings['vault_token']
			),
			DB_STORE_CREDS_VAULT_CYBERARK => new CVaultCyberArk($settings['vault_url'], $settings['vault_prefix'], $settings['vault_db_path'],
				$settings['vault_cert_file'], $settings['vault_key_file']
			),
			default => null
		};
	}
}

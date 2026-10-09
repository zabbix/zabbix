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
 * A class for loading secrets from HashiCorp Vault secret manager.
 */
class CVaultHashiCorp extends CVault {

	public const TYPE					= ZBX_VAULT_TYPE_HASHICORP;
	public const NAME					= 'HashiCorp';
	public const API_ENDPOINT_DEFAULT	= 'https://localhost:8200';
	public const DB_PREFIX_DEFAULT		= '';
	public const DB_PREFIX_PLACEHOLDER	= '/v1/secret/data/';
	public const DB_PATH_PLACEHOLDER	= 'path/to/secret';

	private const APP_ROLE_LOGIN_PATH 	= '/v1/auth/approle/login';

	private string $api_endpoint;
	private string $db_prefix;
	private string $db_path;
	private ?string $token;
	private string $role_id;
	private string $secret_id;

	public function __construct(string $api_endpoint, string $db_prefix, string $db_path, ?string $token,
			string $role_id = '', string $secret_id = '') {
		$this->api_endpoint = $api_endpoint;
		$this->db_prefix = $db_prefix;
		$this->db_path = $db_path;
		$this->token = $token;
		$this->role_id = $role_id;
		$this->secret_id = $secret_id;
	}

	public function validateParameters(): bool {
		if (parse_url($this->api_endpoint, PHP_URL_HOST) === null) {
			$this->addError(_s('Provided API endpoint "%1$s" is invalid.', $this->api_endpoint));
		}

		$secret_parser = new CVaultSecretParser([
			'provider' => ZBX_VAULT_TYPE_HASHICORP,
			'with_namespace' => $this->db_prefix == self::DB_PREFIX_DEFAULT,
			'with_key' => false
		]);

		if ($secret_parser->parse($this->db_path) != CParser::PARSE_SUCCESS) {
			$this->addError(_s('Provided secret path "%1$s" is invalid.', $this->db_path));
		}

		if (!$this->token) {
			if ($this->token === '' && $this->role_id === '' && $this->secret_id === '') {
				$this->addError(_s('Provided authentication token "%1$s" is empty.', $this->token));
			}
			else {
				if ($this->role_id === '') {
					$this->addError(_s('Provided authentication role ID "%1$s" is empty.', $this->role_id));
				}

				if ($this->secret_id === '') {
					$this->addError(_s('Provided authentication secret ID "%1$s" is empty.', $this->secret_id));
				}
			}
		}
		elseif ($this->role_id !== '' || $this->secret_id !== '') {
			$this->addError(
				_s('Authentication role ID and secret ID must be empty if authentication token is provided.')
			);
		}

		return !$this->getErrors();
	}

	public function getCredentials(): ?array {
		$api_endpoint = rtrim($this->api_endpoint, '/');

		if ($this->db_prefix == self::DB_PREFIX_DEFAULT) {
			$path_parts = explode('/', $this->db_path);
			array_splice($path_parts, 1, 0, 'data');

			$url = $api_endpoint.'/v1/'.implode('/', $path_parts);
		}
		else {
			$url = $api_endpoint.$this->db_prefix.$this->db_path;
		}

		if (!$this->token) {
			$login_url = $api_endpoint.self::APP_ROLE_LOGIN_PATH;

			$data = [
				'role_id' => $this->role_id,
				'secret_id' => $this->secret_id
			];

			$fetch_token = @file_get_contents($login_url, false, stream_context_create([
				'http' => [
					'method' => 'POST',
					'header' => "Content-Type: application/json\r\n",
					'content' => json_encode($data, JSON_THROW_ON_ERROR),
					'ignore_errors' => true
				]
			]));

			if ($fetch_token === false) {
				$this->addError(_('Vault AppRole login connection failed'));

				return null;
			}

			$fetch_token = $fetch_token ? json_decode($fetch_token, true) : null;

			if (!is_array($fetch_token)) {
				$this->addError(_('Unable to load token from Vault.'));

				return null;
			}

			if (!array_key_exists('auth', $fetch_token) || !array_key_exists('client_token', $fetch_token['auth'])
					|| !is_string($fetch_token['auth']['client_token'])
					|| $fetch_token['auth']['client_token'] === '') {
				$this->addError(_('Unable to load token from Vault.'));

				if (array_key_exists('errors', $fetch_token)) {
					foreach ($fetch_token['errors'] as $error) {
						$this->addError($error);
					}
				}

				return null;
			}

			$token = $fetch_token['auth']['client_token'];
		}
		else {
			$token = $this->token;
		}

		$secret = @file_get_contents($url, false, stream_context_create([
			'http' => [
				'method' => 'GET',
				'header' => "X-Vault-Token: $token\r\n",
				'ignore_errors' => true
			]
		]));

		if ($secret === false) {
			$this->addError(_('Vault connection failed.'));

			return null;
		}

		$secret = $secret ? json_decode($secret, true) : null;

		if (!is_array($secret)) {
			$this->addError(_('Unable to load database credentials from Vault.'));

			return null;
		}

		if (!isset($secret['data']['data']) || !is_array($secret['data']['data'])) {
			$this->addError(_('Unable to load database credentials from Vault.'));

			if (array_key_exists('errors', $secret)) {
				foreach ($secret['errors'] as $error) {
					$this->addError($error);
				}
			}

			return null;
		}

		$db_credentials = $secret['data']['data'];

		if (!array_key_exists('username', $db_credentials) || !array_key_exists('password', $db_credentials)) {
			$this->addError(_('Username and password must be stored in Vault secret keys "username" and "password".'));

			return null;
		}

		return [
			'user' => $db_credentials['username'],
			'password' => $db_credentials['password']
		];
	}
}

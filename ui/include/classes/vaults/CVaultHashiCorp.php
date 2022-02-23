<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * A class for loading secrets from HashiCorp Vault secret manager.
 */
class CVaultHashiCorp extends CVault {

	public const NAME = 'HashiCorp';
	public const API_ENDPOINT_DEFAULT = 'https://localhost:8200';
	public const DB_PATH_PLACEHOLDER = 'path/to/secret:key';

	/**
	 * @var string
	 */
	private $api_endpoint;

	/**
	 * @var string
	 */
	private $db_path;

	/**
	 * @var string
	 */
	private $token;

	public function __construct(string $api_endpoint, string $db_path, string $token) {
		$this->api_endpoint = rtrim(trim($api_endpoint), '/');
		$this->db_path = $db_path;
		$this->token = trim($token);
	}

	public function validateParameters(): bool {
		if (parse_url($this->api_endpoint, PHP_URL_HOST) === null) {
			$this->addError(_s('Provided API endpoint "%1$s" is invalid.', $this->api_endpoint)); // TODO: 7402 - translation string
		}

		$secret_parser = new CVaultSecretParser(['with_key' => false]);
		if ($secret_parser->parse($this->db_path) != CParser::PARSE_SUCCESS) {
			$this->addError(_s('Provided secret path "%1$s" is invalid.', $this->db_path)); // TODO: 7402 - translation string
		}

		if ($this->token === '') {
			// Function validates if token is not empty string
			$this->addError(_s('Provided authentication token "%1$s" is empty.', $this->token)); // TODO: 7402 - translation string
		}

		return !$this->getErrors();
	}

	public function getCredentials(): ?array {
//		$this->addError(_('Unable to load database credentials from Vault.')); // TODO: 7402 - translation
//		return null;

		$path_parts = explode('/', $this->db_path);
		array_splice($path_parts, 1, 0, 'data');

		$url = $this->api_endpoint.'/v1/'.implode('/', $path_parts);

		try {
			$secret = file_get_contents($url, false, stream_context_create([
				'http' => [
					'method' => 'GET',
					'header' => "X-Vault-Token: $this->token\r\n",
					'ignore_errors' => true
				]
			]));
		}
		catch (Exception $e) {
			$this->addError(_('Vault connection failed.'));
			$this->addError($e->getMessage());

			return null;
		}

		$secret ? $secret = json_decode($secret, true) : $secret = null;

		if ($secret === null || !isset($secret['data']['data']) || !is_array($secret['data']['data'])) {
			$this->addError(_('Unable to load database credentials from Vault.')); // TODO: 7402 - translation

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

	// TODO: 7402
	public function validateMacroValue(string $value): bool {
		return false;
	}
}

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
class CVaultCyberArk extends CVault {

	public const NAME = 'CyberArk';
	public const API_ENDPOINT_DEFAULT = 'https://localhost:1858';
	public const DB_PATH_PLACEHOLDER = 'AppID=foo&Query=Safe=bar;Object=buzz:key';

	/**
	 * @var string
	 */
	protected $api_endpoint;

	/**
	 * @var string
	 */
	protected $db_path;

	/**
	 * @var string
	 */
	protected $cert_file;

	/**
	 * @var string
	 */
	protected $key_file;

	public function __construct($api_endpoint, $db_path, $cert_file, $key_file) {
		$this->api_endpoint = rtrim(trim($api_endpoint), '/');
		$this->db_path = $db_path;
		$this->cert_file = $cert_file;
		$this->key_file = $key_file;
	}

	public function validateParameters(): bool {
		if (parse_url($this->api_endpoint, PHP_URL_HOST) === null) {
			$this->addError(_s('Provided API endpoint "%1$s" is invalid.', $this->api_endpoint)); // TODO: 7402 - translation string
		}

		$secret_parser = new CVaultSecretParser(['with_key' => false]);
		if ($secret_parser->parse($this->db_path) != CParser::PARSE_SUCCESS) {
			$this->addError(_s('Provided secret path "%1$s" is invalid.', $this->db_path)); // TODO: 7402 - translation string
		}

		return !$this->getErrors();
	}

	public function getCredentials(): ?array {
		$this->addError(_('Unable to load database credentials from Vault.')); // TODO: 7402 - translation
		return null;

		$path_parts = explode('/', $this->db_path); // TODO: db_path validator and parser needs to be implemented
		array_splice($path_parts, 1, 0, 'data');

		$url = $this->api_endpoint.'/v1/'.implode('/', $path_parts);

		try {
			$secret = file_get_contents($url, false, stream_context_create([
				'http' => [
					'method' => 'GET',
					'header' => "X-Vault-Cert-File: $this->cert_file\r\n", // TODO: Modify the request for CyberArk
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

	public function validateMacroValue(string $value): bool {
		return false;
	}
}

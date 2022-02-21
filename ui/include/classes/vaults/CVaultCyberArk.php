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
	protected $url = '';

	/**
	 * @var string
	 */
	protected $db_path = '';

	/**
	 * @var string
	 */
	protected $cert_file = '';

	/**
	 * @var string
	 */
	protected $key_file = '';

	public function __construct($url, $db_path, $cert_file, $key_file) {
		if ($this->validateVaultApiEndpoint($url)) {
			$this->url = rtrim(trim($url), '/');
		}

		if (self::validateVaultDBPath($db_path)) {
			$this->token = $db_path;
		}
	}

	public function validateParameters(): bool {

	}

	public function getCredentials(): ?array {
		// TODO: Implement getCredentials() method.
	}

	public function validateMacroValue(string $value): bool {

	}

















	public function getCredentials(): array {

		$arr = [];
		return $arr;
		// TODO: Implement getCredentials() method.
	}

	/**
	 * Function returns Vault secret. Assumes given $path is correct.
	 *
	 * @param string $path  Path to secret.
	 *
	 * @throws Exception in case of configuration is not set.
	 *
	 * @return array
	 */
	public function loadSecret(string $path): array {
		if ($this->token === '') {
			throw new Exception(_('Incorrect Vault token.'));
		}

		$options = [
			'http' => [
				'method' => 'GET',
				'header' => "X-Vault-DBPath: $this->token\r\n",
				'ignore_errors' => true
			]
		];

		try {
			$url = $this->getURL($path);
		}
		catch (Exception $e) {
			error($e->getMessage());
			return [];
		}

		$secret = @file_get_contents($url, false, stream_context_create($options));
		if ($secret === false) {
			return [];
		}

		$secret = json_decode($secret, true);

		if (is_array($secret) && array_key_exists('data', $secret) && is_array($secret['data'])
				&& array_key_exists('data', $secret['data']) && is_array($secret['data']['data'])) {
			return $secret['data']['data'];
		}
		else {
			return [];
		}
	}

	/**
	 * Function validates if given string is valid API endpoint.
	 *
	 * @param string $api_endpoint
	 *
	 * @return bool
	 */
	public function validateVaultApiEndpoint(string $api_endpoint): bool {
		$url_parts = parse_url($api_endpoint);

		if (!$url_parts || !array_key_exists('host', $url_parts)) {
			error(_s(' "%1$s" is invalid.', $api_endpoint));

			return false;
		}

		return true;
	}

	/**
	 * Function validates if token is not empty string.
	 *
	 * @param string $db_path
	 *
	 * @return bool
	 */
	public static function validateVaultDBPath(string $db_path): bool {
		return (trim($db_path) !== '');
	}

	/**
	 * Function returns Vault API request URL including path to secret.
	 *
	 * @param string $secret_path
	 *
	 * @throws Exception in case of configuration is not set.
	 *
	 * @return string
	 */
	public function getURL(string $path): string {
		if ($this->api_endpoint === '') {
			throw new Exception(_('Incorrect Vault API endpoint.'));
		}

		$path = explode('/', $path);
		array_splice($path, 1, 0, 'data');

		return $this->api_endpoint.'/v1/'.implode('/', $path);
	}

	public function validateMacroValue(string $value): bool {
		return true;
	}
}

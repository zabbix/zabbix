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
 * A class for load secrets from HashiCorp Vault secret manager.
 */
class CVaultHelper {

	/**
	 * Vault API endpoint.
	 *
	 * @var string
	 */
	protected $api_endpoint = '';

	/**
	 * Vault access token.
	 *
	 * @var string
	 */
	protected $token = '';

	public function __construct(string $api_endpoint, string $token) {
		if (self::validateVaultApiEndpoint($api_endpoint)) {
			$this->api_endpoint = rtrim(trim($api_endpoint), '/');
		}
		if (self::validateVaultToken($token)) {
			$this->token = $token;
		}
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
				'header' => "X-Vault-Token: $this->token\r\n",
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
	public static function validateVaultApiEndpoint(string $api_endpoint): bool {
		$url_parts = parse_url($api_endpoint);

		if (!$url_parts || !array_key_exists('host', $url_parts)) {
			error(_s('Provided URL "%1$s" is invalid.', $api_endpoint));

			return false;
		}

		return true;
	}

	/**
	 * Function validates if token is not empty string.
	 *
	 * @param string $token
	 *
	 * @return bool
	 */
	public static function validateVaultToken(string $token): bool {
		return (trim($token) !== '');
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
}

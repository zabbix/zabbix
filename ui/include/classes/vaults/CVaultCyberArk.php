<?php declare(strict_types = 0);
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

	public const TYPE					= ZBX_VAULT_TYPE_CYBERARK;
	public const NAME					= 'CyberArk';
	public const API_ENDPOINT_DEFAULT	= 'https://localhost:1858';
	public const DB_PATH_PLACEHOLDER	= 'AppID=foo&Query=Safe=bar;Object=buzz';

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

	public function __construct(string $api_endpoint, string $db_path, ?string $cert_file, ?string $key_file) {
		$this->api_endpoint = rtrim(trim($api_endpoint), '/');
		$this->db_path = trim($db_path);
		$this->cert_file = $cert_file !== null ? trim($cert_file) : null;
		$this->key_file = $key_file !== null ? trim($key_file) : null;
	}

	public function validateParameters(): bool {
		$api_endpoint = parse_url($this->api_endpoint);

		if (!$api_endpoint || !array_key_exists('scheme', $api_endpoint) || !array_key_exists('host', $api_endpoint)
				|| strtolower($api_endpoint['scheme']) !== 'https' || $api_endpoint['host'] === '') {
			$this->addError(_s('Provided API endpoint "%1$s" is invalid.', $this->api_endpoint));
		}

		$secret_parser = new CVaultSecretParser(['provider' => ZBX_VAULT_TYPE_CYBERARK, 'with_key' => false]);

		if ($secret_parser->parse($this->db_path) != CParser::PARSE_SUCCESS) {
			$this->addError(_s('Provided secret query string "%1$s" is invalid.', $this->db_path));
		}

		return !$this->getErrors();
	}

	public function getCredentials(): ?array {
		$http_context = [
			'method' => 'GET',
			'header' => 'Content-Type: application/json',
			'ignore_errors' => true
		];

		if ($this->cert_file !== null && $this->key_file !== null) {
			$http_context['ssl'] = [
				'local_cert'		=> $this->cert_file,
				'local_pk'			=> $this->key_file,
				'verify_peer'		=> false,
				'verify_peer_name'	=> false,
				'allow_self_signed'	=> true
			];
		}

		$secret = @file_get_contents($this->api_endpoint.'/AIMWebService/api/Accounts?'.$this->db_path, false,
			stream_context_create(['http' => $http_context])
		);

		if ($secret === false) {
			$this->addError(_('Vault connection failed.'));

			return null;
		}

		$db_credentials = $secret ? json_decode($secret, true) : null;

		if ($db_credentials === null) {
			$this->addError(_('Unable to load database credentials from Vault.'));

			return null;
		}

		if (!array_key_exists('UserName', $db_credentials) || !array_key_exists('Content', $db_credentials)) {
			$this->addError(_('Username and password must be stored in Vault secret keys "UserName" and "Content".'));

			return null;
		}

		return [
			'user' => $db_credentials['UserName'],
			'password' => $db_credentials['Content']
		];
	}
}

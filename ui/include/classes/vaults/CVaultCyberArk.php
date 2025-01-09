<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
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
class CVaultCyberArk extends CVault {

	public const TYPE					= ZBX_VAULT_TYPE_CYBERARK;
	public const NAME					= 'CyberArk';
	public const API_ENDPOINT_DEFAULT	= 'https://localhost:1858';
	public const DB_PREFIX_DEFAULT		= '/AIMWebService/api/Accounts?';
	public const DB_PATH_PLACEHOLDER	= 'AppID=foo&Query=Safe=bar;Object=buzz';

	private string $api_endpoint;
	private string $db_prefix;
	private string $db_path;
	private string $cert_file;
	private string $key_file;

	public function __construct(string $api_endpoint, string $db_prefix, string $db_path, string $cert_file,
			string $key_file) {
		$this->api_endpoint = $api_endpoint;
		$this->db_prefix = $db_prefix !== '' ? $db_prefix : self::DB_PREFIX_DEFAULT;
		$this->db_path = $db_path;
		$this->cert_file = trim($cert_file);
		$this->key_file = trim($key_file);
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
		$context = [
			'http' => [
				'method' => 'GET',
				'header' => 'Content-Type: application/json',
				'ignore_errors' => true
			]
		];

		if ($this->cert_file !== '' && $this->key_file !== '') {
			$context['ssl'] = [
				'local_cert'		=> $this->cert_file,
				'local_pk'			=> $this->key_file,
				'verify_peer'		=> false,
				'verify_peer_name'	=> false,
				'allow_self_signed'	=> true
			];
		}

		$secret = @file_get_contents($this->api_endpoint.$this->db_prefix.$this->db_path, false,
			stream_context_create($context)
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

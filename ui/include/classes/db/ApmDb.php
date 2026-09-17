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


class ApmDb {

	private const PROVIDER_ZABBIX = 'zabbix';
	private const PROVIDER_CLICKHOUSE = 'clickhouse';

	private static ?self $instance = null;

	private string $provider;
	private array $config;

	private function __construct() {
		$this->init();
	}

	public static function getInstance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function isLocal(): bool {
		return $this->provider === self::PROVIDER_ZABBIX;
	}

	public function getConfig(): array {
		if ($this->isLocal()) {
			throw new DBException(_('Configuration cannot be retrieved if a local database is defined for APM.'),
				DB::INIT_ERROR
			);
		}

		return $this->config;
	}

	private function init(): void {
		global $TELEMETRY_PROVIDERS;

		if ($TELEMETRY_PROVIDERS) {
			$this->initWithConfigFile();
		}
		else {
			$this->initWithApmGlobal();
		}
	}

	private function initWithConfigFile(): void {
		global $TELEMETRY_PROVIDERS;

		$provider_config = $TELEMETRY_PROVIDERS[0];

		switch ($provider_config['provider']) {
			case self::PROVIDER_ZABBIX:
				$this->provider = self::PROVIDER_ZABBIX;
				break;

			case self::PROVIDER_CLICKHOUSE:
				$this->provider = self::PROVIDER_CLICKHOUSE;
				$this->config = self::resolveConfigFileConfiguration($provider_config);
				break;
		}
	}

	private function initWithApmGlobal(): void {
		$this->provider = self::PROVIDER_CLICKHOUSE;
		$this->config = self::resolveApmGlobalConfiguration(CSettingsHelper::getApmGlobalDb());
	}

	/**
	 * @throws DBException|JsonException
	 */
	private static function resolveConfigFileConfiguration(array $config): array {
		global $DB, $APM_CA_FILE, $APM_CA_LOCATION;

		if ($config['vault_path'] !== '') {
			if ($DB['VAULT'] === '') {
				throw new DBException(_('Vault is not configured.'), DB::INIT_ERROR);
			}

			$vault = $DB['VAULT'] === CVaultHashiCorp::NAME
				? new CVaultHashiCorp($DB['VAULT_URL'], $DB['VAULT_PREFIX'], $config['vault_path'], $DB['VAULT_TOKEN'],
					$DB['VAULT_APP_ROLE_ID'], $DB['VAULT_APP_SECRET_ID']
				)
				: new CVaultCyberArk($DB['VAULT_URL'], $DB['VAULT_PREFIX'], $config['vault_path'], $DB['VAULT_CERT_FILE'],
					$DB['VAULT_KEY_FILE']
				);

			$credentials = $vault->getCredentials();

			if ($credentials === null) {
				$errors = $vault->getErrors();

				throw new DBException(reset($errors), DB::INIT_ERROR);
			}

			$config['username'] = $credentials['user'];
			$config['password'] = $credentials['password'];
		}

		if ($config['ssl_verify_peer'] === true && $config['ssl_ca_file'] === '' && $config['ssl_ca_location'] === '') {
			$config['ssl_ca_file'] = $APM_CA_FILE;
			$config['ssl_ca_location'] = $APM_CA_LOCATION;
		}

		return array_diff_key($config, array_flip(['vault_path', 'provider']));
	}

	/**
	 * @throws DBException
	 */
	public static function resolveApmGlobalConfiguration(array $config): array {
		if ($config['status'] == APM_GLOBAL_DB_STATUS_NOT_CONFIGURED) {
			throw new DBException(_('APM DB is not configured.'), DB::INIT_ERROR);
		}

		$config['ssl_verify_peer'] = $config['ssl_verify_peer'] == APM_GLOBAL_DB_VERIFY_PEER_ENABLED;
		$config['ssl_verify_host'] = $config['ssl_verify_host'] == APM_GLOBAL_DB_VERIFY_HOST_ENABLED;

		$config += [
			'ssl_cert_file' => '',
			'ssl_key_file' => '',
			'ssl_key_password' => '',
			'ssl_ca_file' => '',
			'ssl_ca_location' => ''
		];

		return array_diff_key($config, array_flip(['status', 'authentication_type']));
	}
}

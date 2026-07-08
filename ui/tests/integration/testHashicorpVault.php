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

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';

/**
 * Test suite for HashiCorp Vault integration (DB credentials retrieval by Zabbix server).
 *
 * A disposable HashiCorp Vault instance is started, for the duration of this test suite, from the
 * official "hashicorp/vault" Docker image (https://hub.docker.com/r/hashicorp/vault) in dev mode.
 * Requires a working "docker" CLI, with the user running the tests able to reach the Docker daemon
 * (e.g. member of the "docker" group), and network access to pull the image on first use.
 *
 * The instance is configured with:
 *   - a KV v2 secret containing the credentials of the database used by the test environment;
 *   - a policy granting read access to that secret;
 *   - the AppRole auth method, with a role bound to that policy;
 *   - a plain token bound to that policy (for the "VaultToken" authentication scenario).
 *
 * @onAfter clearData
 */
class testHashicorpVault extends CIntegrationTest {

	const VAULT_IMAGE = 'hashicorp/vault:2.0.3';
	const VAULT_CONTAINER_NAME = 'zbx-it-vault';
	const VAULT_PORT = 8284;
	const VAULT_ADDR = 'http://127.0.0.1:'.self::VAULT_PORT;
	const VAULT_ROOT_TOKEN = 'zbx-it-root-token';
	const VAULT_ROLE_NAME = 'zbx-it-role';
	const VAULT_POLICY_NAME = 'zbx-it-policy';
	const VAULT_SECRET_PATH = 'zabbix/db';
	const VAULT_DB_PATH = 'secret/'.self::VAULT_SECRET_PATH;

	const HOSTNAME = 'test_hashicorp_vault';
	const TRAPPER_ITEM_KEY = 'vault_trap';

	private static $role_id;
	private static $secret_id;
	private static $static_token;

	private static $hostid;

	/**
	 * Start a disposable HashiCorp Vault dev-mode container and wait until it responds.
	 *
	 * @throws Exception    on failure to start Vault within the allotted time
	 */
	private static function vaultStart(): void {
		// Remove any stale container left over from a previously interrupted run.
		shell_exec('docker rm -f '.self::VAULT_CONTAINER_NAME.' > /dev/null 2>&1');

		// Pulled separately (with no timeout budget of its own) so a cold image cache doesn't eat
		// into the startup wait loop below.
		shell_exec('docker pull '.self::VAULT_IMAGE.' > /dev/null 2>&1');

		$cmd = 'docker run -d --name '.self::VAULT_CONTAINER_NAME.
				' --cap-add=IPC_LOCK'.
				' -p 127.0.0.1:'.self::VAULT_PORT.':8200'.
				' -e VAULT_DEV_ROOT_TOKEN_ID='.self::VAULT_ROOT_TOKEN.
				' -e VAULT_DEV_LISTEN_ADDRESS=0.0.0.0:8200'.
				' '.self::VAULT_IMAGE.
				' > /dev/null 2>&1';
		shell_exec($cmd);

		for ($i = 0; $i < 50; $i++) {
			if (@file_get_contents(self::VAULT_ADDR.'/v1/sys/health') !== false) {
				return;
			}

			usleep(200000);
		}

		throw new Exception('Failed to start local HashiCorp Vault container for integration testing. Log:'."\n".
				shell_exec('docker logs '.self::VAULT_CONTAINER_NAME.' 2>&1')
		);
	}

	/**
	 * Stop and remove the container started by {@see vaultStart()}.
	 */
	private static function vaultStop(): void {
		shell_exec('docker rm -f '.self::VAULT_CONTAINER_NAME.' > /dev/null 2>&1 &');
	}

	/**
	 * Perform a request against the Vault HTTP API.
	 *
	 * @param string      $method    HTTP method
	 * @param string      $path      request path, starting with "/v1/"
	 * @param array|null  $data      request payload, null for no body
	 * @param string|null $token     "X-Vault-Token" header value, null to omit the header
	 *
	 * @return array    ['status' => int, 'body' => array|null]
	 *
	 * @throws Exception    on failure to communicate with Vault
	 */
	private static function vaultRequest(string $method, string $path, ?array $data = null,
			?string $token = null): array {
		$context = stream_context_create([
			'http' => [
				'method' => $method,
				'header' => "X-Vault-Token: $token\r\nContent-Type: application/json\r\n",
				'content' => ($data !== null) ? json_encode($data) : '',
				'ignore_errors' => true
			]
		]);

		$body = @file_get_contents(self::VAULT_ADDR.$path, false, $context);

		if ($body === false) {
			throw new Exception('Failed to communicate with Vault at path "'.$path.'".');
		}

		$status = 0;
		foreach ($http_response_header as $header) {
			if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
				$status = (int) $matches[1];
			}
		}

		return ['status' => $status, 'body' => ($body !== '' ? json_decode($body, true) : null)];
	}

	/**
	 * List the accessors of every Vault token currently issued for the given AppRole role.
	 *
	 * @param string $role_name
	 *
	 * @return array
	 */
	private static function vaultRoleAccessors(string $role_name): array {
		$accessors = self::vaultRequest('GET', '/v1/auth/token/accessors?list=true', null, self::VAULT_ROOT_TOKEN);
		$matching = [];

		foreach ($accessors['body']['data']['keys'] ?? [] as $accessor) {
			$lookup = self::vaultRequest('POST', '/v1/auth/token/lookup-accessor', ['accessor' => $accessor],
					self::VAULT_ROOT_TOKEN
			);

			if (($lookup['body']['data']['meta']['role_name'] ?? null) === $role_name) {
				$matching[] = $accessor;
			}
		}

		return $matching;
	}

	/**
	 * Revoke the Vault tokens with the given accessors. Used to simulate a token being expired or
	 * revoked out-of-band, in order to trigger the AppRole re-login logic.
	 *
	 * @param array $accessors
	 */
	private static function vaultRevokeAccessors(array $accessors): void {
		foreach ($accessors as $accessor) {
			self::vaultRequest('POST', '/v1/auth/token/revoke-accessor', ['accessor' => $accessor],
					self::VAULT_ROOT_TOKEN
			);
		}
	}

	/**
	 * Start the server component with a deliberately broken Vault configuration and wait for the
	 * expected error to be logged, instead of waiting for a successful startup.
	 *
	 * @param array  $vault_config     "Vault*" configuration options to apply on top of the defaults
	 * @param string $expected_error   substring expected to appear in the server log
	 */
	private function startServerAndExpectVaultError(array $vault_config, string $expected_error): void {
		$configuration = self::getDefaultComponentConfiguration();
		$configuration[self::COMPONENT_SERVER] = array_merge($configuration[self::COMPONENT_SERVER], [
			'DBUser' => [],
			'DBPassword' => []
		], $vault_config);

		self::prepareComponentConfiguration(self::COMPONENT_SERVER, $configuration);
		self::clearLog(self::COMPONENT_SERVER);

		self::executeCommand(PHPUNIT_BINARY_DIR.'zabbix_'.self::COMPONENT_SERVER,
				['-c', PHPUNIT_CONFIG_DIR.'zabbix_'.self::COMPONENT_SERVER.'.conf']
		);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, $expected_error, false, 10, 1);
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		self::vaultStart();

		global $DB;

		// Store the credentials of the database used by the test environment as a Vault secret,
		// mirroring how a real deployment would keep DB credentials out of zabbix_server.conf.
		self::vaultRequest('POST', '/v1/secret/data/'.self::VAULT_SECRET_PATH, [
			'data' => [
				'username' => $DB['USER'],
				'password' => $DB['PASSWORD']
			]
		], self::VAULT_ROOT_TOKEN);

		self::vaultRequest('PUT', '/v1/sys/policy/'.self::VAULT_POLICY_NAME, [
			'policy' => 'path "secret/data/'.self::VAULT_SECRET_PATH.'" { capabilities = ["read"] }'
		], self::VAULT_ROOT_TOKEN);

		self::vaultRequest('POST', '/v1/sys/auth/approle', ['type' => 'approle'], self::VAULT_ROOT_TOKEN);

		self::vaultRequest('POST', '/v1/auth/approle/role/'.self::VAULT_ROLE_NAME, [
			'token_policies' => self::VAULT_POLICY_NAME,
			'token_ttl' => '20s',
			'token_max_ttl' => '1h',
			'secret_id_ttl' => '1h'
		], self::VAULT_ROOT_TOKEN);

		$role = self::vaultRequest('GET', '/v1/auth/approle/role/'.self::VAULT_ROLE_NAME.'/role-id', null,
				self::VAULT_ROOT_TOKEN
		);
		self::$role_id = $role['body']['data']['role_id'];

		$secret = self::vaultRequest('POST', '/v1/auth/approle/role/'.self::VAULT_ROLE_NAME.'/secret-id', [],
				self::VAULT_ROOT_TOKEN
		);
		self::$secret_id = $secret['body']['data']['secret_id'];

		$token = self::vaultRequest('POST', '/v1/auth/token/create', [
			'policies' => [self::VAULT_POLICY_NAME],
			'ttl' => '1h'
		], self::VAULT_ROOT_TOKEN);
		self::$static_token = $token['body']['auth']['client_token'];

		// Host and trapper item used to prove that the server (started with Vault-sourced DB
		// credentials) actually has a working configuration cache and database connection.
		$response = $this->call('host.create', [
			'host' => self::HOSTNAME,
			'interfaces' => [],
			'groups' => [['groupid' => 4]],
			'status' => HOST_STATUS_MONITORED
		]);
		self::$hostid = $response['result']['hostids'][0];

		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => self::TRAPPER_ITEM_KEY,
			'key_' => self::TRAPPER_ITEM_KEY,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertArrayHasKey('itemids', $response['result']);

		return true;
	}

	public static function clearData(): void {
		self::vaultStop();
		CDataHelper::call('host.delete', [self::$hostid]);
	}

	/**
	 * Verify that the server, once started, is able to process values for the trapper item, which
	 * proves that it successfully connected to the database using the credentials it obtained from
	 * Vault.
	 */
	private function assertServerIsOperational(): void {
		// sendSenderValue() already asserts that the value was processed successfully, which is
		// only possible if the server loaded its configuration cache from the database.
		$this->sendSenderValue(self::HOSTNAME, self::TRAPPER_ITEM_KEY, 1);
	}

	public function tokenAuthenticationConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DBUser' => [],
				'DBPassword' => [],
				'Vault' => 'HashiCorp',
				'VaultURL' => self::VAULT_ADDR,
				'VaultDBPath' => self::VAULT_DB_PATH,
				'VaultToken' => self::$static_token
			]
		];
	}

	/**
	 * @required-components server
	 * @configurationDataProvider tokenAuthenticationConfigurationProvider
	 */
	public function testHashicorpVault_tokenAuthentication() {
		$this->assertServerIsOperational();
	}

	public function appRoleAuthenticationConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DBUser' => [],
				'DBPassword' => [],
				'Vault' => 'HashiCorp',
				'VaultURL' => self::VAULT_ADDR,
				'VaultDBPath' => self::VAULT_DB_PATH,
				'VaultAppRoleID' => self::$role_id,
				'VaultAppSecretID' => self::$secret_id
			]
		];
	}

	/**
	 * @required-components server
	 * @configurationDataProvider appRoleAuthenticationConfigurationProvider
	 */
	public function testHashicorpVault_appRoleAuthentication() {
		$this->assertServerIsOperational();
	}

	/**
	 * @required-components server
	 * @configurationDataProvider appRoleAuthenticationConfigurationProvider
	 */
	public function testHashicorpVault_appRoleReLoginAfterTokenRevocation() {
		// Confirm the server is up and running with the token obtained on the initial AppRole login.
		$this->assertServerIsOperational();

		$old_accessors = self::vaultRoleAccessors(self::VAULT_ROLE_NAME);
		$this->assertNotEmpty($old_accessors, 'Server did not log in with AppRole and obtain a Vault token.');

		// Simulate the AppRole-issued token being revoked or expired externally, without the
		// server's knowledge. The role's token TTL is short (see appRoleAuthenticationConfigurationProvider's
		// setup in prepareData()), so the server will attempt to renew it, notice it is gone, and
		// transparently re-login well within the polling loop below.
		self::vaultRevokeAccessors($old_accessors);

		$relogged_in = false;

		for ($i = 0; $i < 40; $i++) {
			if (array_diff(self::vaultRoleAccessors(self::VAULT_ROLE_NAME), $old_accessors)) {
				$relogged_in = true;
				break;
			}

			sleep(1);
		}

		$this->assertTrue($relogged_in, 'Server did not re-login with AppRole after its Vault token was revoked.');

		// The server must have transparently switched to the newly obtained token and must still
		// be able to serve requests.
		$this->assertServerIsOperational();
	}

	public function testHashicorpVault_missingTokenAndAppRoleId() {
		$this->startServerAndExpectVaultError([
			'Vault' => 'HashiCorp',
			'VaultURL' => self::VAULT_ADDR,
			'VaultDBPath' => self::VAULT_DB_PATH
		], 'at least one configuration parameter ("VaultToken" or "VaultAppRoleID")');
	}

	public function testHashicorpVault_appRoleIdWithoutSecretId() {
		$this->startServerAndExpectVaultError([
			'Vault' => 'HashiCorp',
			'VaultURL' => self::VAULT_ADDR,
			'VaultDBPath' => self::VAULT_DB_PATH,
			'VaultAppRoleID' => self::$role_id
		], '"VaultAppRoleID" is defined but "VaultAppSecretID" is not defined');
	}

	public function testHashicorpVault_tokenAndAppRoleIdBothDefined() {
		$this->startServerAndExpectVaultError([
			'Vault' => 'HashiCorp',
			'VaultURL' => self::VAULT_ADDR,
			'VaultDBPath' => self::VAULT_DB_PATH,
			'VaultToken' => self::$static_token,
			'VaultAppRoleID' => self::$role_id
		], 'either "VaultToken" or "VaultAppRoleID" configuration parameter or corresponding environment '.
				'variable can be defined but not both'
		);
	}

	public function testHashicorpVault_tokenAndAppSecretIdBothDefined() {
		$this->startServerAndExpectVaultError([
			'Vault' => 'HashiCorp',
			'VaultURL' => self::VAULT_ADDR,
			'VaultDBPath' => self::VAULT_DB_PATH,
			'VaultToken' => self::$static_token,
			'VaultAppSecretID' => self::$secret_id
		], '"VaultToken" and "VaultAppSecretID" configuration parameters or corresponding environment '.
				'variables cannot be defined at the same time'
		);
	}

	public function testHashicorpVault_invalidAppRoleCredentials() {
		$this->startServerAndExpectVaultError([
			'Vault' => 'HashiCorp',
			'VaultURL' => self::VAULT_ADDR,
			'VaultDBPath' => self::VAULT_DB_PATH,
			'VaultAppRoleID' => self::$role_id,
			'VaultAppSecretID' => '00000000-0000-0000-0000-000000000000'
		], 'cannot initialize vault: cannot login with AppRole method');
	}
}

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
 * This test suite does not manage the Vault instance's lifecycle: it expects a HashiCorp Vault
 * dev-mode instance, started independently (e.g. from the official "hashicorp/vault" Docker image,
 * see https://hub.docker.com/r/hashicorp/vault), to already be running and reachable at VAULT_ADDR
 * with the VAULT_ROOT_TOKEN root token before this suite executes, for example:
 *
 *   sudo docker run --cap-add=IPC_LOCK \
 *       --name vault \
 *       -p 8200:8200 \
 *       -e VAULT_DEV_ROOT_TOKEN_ID=root \
 *       -e VAULT_DEV_LISTEN_ADDRESS=0.0.0.0:8200 \
 *       hashicorp/vault:latest
 *
 * On top of that instance, this suite sets up (idempotently, so re-running the suite against the
 * same long-lived instance is safe):
 *   - a KV v2 secret containing the credentials of the database used by the test environment;
 *   - a policy granting read access to that secret;
 *   - the AppRole auth method, with a role bound to that policy;
 *   - a plain token bound to that policy (for the "VaultToken" authentication scenario).
 *
 * @onAfter clearData
 */
class testHashicorpVault extends CIntegrationTest {

	const VAULT_PORT = 8200;
	const VAULT_ADDR = 'http://127.0.0.1:'.self::VAULT_PORT;
	const VAULT_ROOT_TOKEN = 'root';
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
	 * Wait until the pre-existing Vault instance (see class docblock) is reachable. Does not start,
	 * stop or otherwise manage the instance.
	 *
	 * @throws Exception    if Vault is not reachable within the allotted time
	 */
	private static function vaultWaitUntilReady(): void {
		for ($i = 0; $i < 50; $i++) {
			if (@file_get_contents(self::VAULT_ADDR.'/v1/sys/health') !== false) {
				return;
			}

			usleep(200000);
		}

		throw new Exception('HashiCorp Vault instance at "'.self::VAULT_ADDR.'" is not reachable. It must be '.
				'started independently before running this test suite, see the class docblock.'
		);
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
				// json_encode() renders an empty PHP array as "[]", but Vault requires a JSON
				// object ("{}") for empty request bodies; casting to stdClass forces the latter.
				'content' => ($data !== null) ? json_encode($data ?: new stdClass()) : '',
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
	 *
	 * Every Vault call below is either read-only or safe to repeat against the same long-lived
	 * Vault instance across separate runs of this suite (see class docblock): KV writes just create
	 * a new secret version, the policy PUT and the role POST both overwrite in place, and the
	 * secret-id/token creation calls always succeed and simply mint an additional credential
	 * (each self-expires via its TTL, so nothing accumulates unbounded). The one call that is NOT
	 * naturally repeatable, enabling the AppRole auth method, is explicitly guarded below.
	 */
	public function prepareData() {
		self::vaultWaitUntilReady();

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

		// Enabling an already-enabled auth method errors out, so check first.
		$auth_methods = self::vaultRequest('GET', '/v1/sys/auth', null, self::VAULT_ROOT_TOKEN);

		if (!isset($auth_methods['body']['approle/'])) {
			self::vaultRequest('POST', '/v1/sys/auth/approle', ['type' => 'approle'], self::VAULT_ROOT_TOKEN);
		}

		// A role POST to an already-existing role name overwrites its configuration in place.
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

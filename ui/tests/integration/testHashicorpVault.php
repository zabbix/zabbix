<?php declare(strict_types = 1);
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
 * Test suite for HashiCorp Vault integration: DB credentials retrieval by Zabbix server, and
 * resolution of Vault secret macros (a separate feature, exercised by
 * testHashicorpVault_vaultMacroResolution()).
 *
 * This test suite does not manage the Vault instance's lifecycle: it expects a HashiCorp Vault
 * dev-mode instance, started independently (e.g. from the official "hashicorp/vault" Docker image,
 * see https://hub.docker.com/r/hashicorp/vault), to already be running and reachable at PHPUNIT_HASHICORP_ADDRESS
 * with the PHPUNIT_HASHICORP_ROOT_TOKEN_ID root token before this suite executes, for example:
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
 * The Vault instance itself is shared between parallel test runs (e.g. concurrent Jenkins
 * executors), unlike the Zabbix components and database, which get a fresh instance per run. The
 * role/policy/secret names are therefore namespaced with PHPUNIT_PORT_PREFIX - the same value
 * already used elsewhere in CIntegrationTest to keep parallel runs from colliding on ports - so
 * that concurrent runs never read, write or revoke each other's Vault objects.
 *
 * @onAfter clearData
 */
class testHashicorpVault extends CIntegrationTest {

	const VAULT_ROLE_NAME = 'zbx-it-role-'.PHPUNIT_PORT_PREFIX;
	const VAULT_POLICY_NAME = 'zbx-it-policy-'.PHPUNIT_PORT_PREFIX;
	const VAULT_SECRET_PATH = 'zabbix/'.PHPUNIT_PORT_PREFIX.'/db';
	const VAULT_DB_PATH = 'secret/'.self::VAULT_SECRET_PATH;

	// Deliberately a *different* Vault path than VAULT_SECRET_PATH/VAULT_DB_PATH: Zabbix refuses to
	// register a Vault macro whose path equals "VaultDBPath" with key "username"/"password" (see
	// um_macro_register_kvs() in user_macro.c) - that combination is reserved for DB credentials and
	// silently never gets fetched, so a macro pointing at it would always resolve to "*UNKNOWN*".
	const VAULT_MACRO_SECRET_PATH = 'zabbix/'.PHPUNIT_PORT_PREFIX.'/macro';
	const VAULT_MACRO_SECRET_KEY = 'value';
	const VAULT_MACRO_SECRET_VALUE = 'vault-macro-secret-'.PHPUNIT_PORT_PREFIX;

	const HOSTNAME = 'test_hashicorp_vault';
	const TRAPPER_ITEM_KEY = 'vault_trap';
	const VAULT_MACRO = '{$VAULT_SECRET_VALUE}';
	const VAULT_MACRO_ITEM_KEY = 'vault_secret_macro_test';

	private static $role_id;
	private static $secret_id;
	private static $static_token;

	private static $hostid;
	private static $vault_macro_itemid;

	/**
	 * Check test precondition: HashiCorp Vault integration tests are explicitly enabled, since they
	 * require a pre-existing Vault instance (see class docblock) that most local/CI runs won't have
	 * up. Disabled by default, similarly to PHPUNIT_SAML_TESTS_ENABLED.
	 *
	 * If the tests are not enabled the suite is marked skipped (rather than failed).
	 */
	protected function onBeforeTestSuite() {
		if (!defined('PHPUNIT_HASHICORP_VAULT_TESTS_ENABLED') || !PHPUNIT_HASHICORP_VAULT_TESTS_ENABLED) {
			self::markTestSuiteSkipped('HashiCorp Vault integration tests are disabled. Define '.
				'PHPUNIT_HASHICORP_VAULT_TESTS_ENABLED as true in bootstrap.php to enable them, and start a Vault '.
				'instance independently before running this test suite, see the class docblock.'
			);

			return;
		}

		parent::onBeforeTestSuite();
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
	private static function vaultRequest($method, $path, $data = null, $token = null) {
		$curl = curl_init(PHPUNIT_HASHICORP_ADDRESS.$path);
		curl_setopt_array($curl, [
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_HTTPHEADER => ['X-Vault-Token: '.$token, 'Content-Type: application/json'],
			CURLOPT_RETURNTRANSFER => true,
			// json_encode() renders an empty PHP array as "[]", but Vault requires a JSON
			// object ("{}") for empty request bodies; casting to stdClass forces the latter.
			CURLOPT_POSTFIELDS => ($data !== null) ? json_encode($data ?: new stdClass()) : ''
		]);

		$body = curl_exec($curl);

		if ($body === false) {
			throw new Exception('Failed to communicate with Vault at path "'.$path.'".');
		}

		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		return ['status' => $status, 'body' => ($body !== '' ? json_decode($body, true) : null)];
	}

	/**
	 * List the accessors of every Vault token currently issued for the given AppRole role.
	 *
	 * @param string $role_name
	 *
	 * @return array
	 */
	private static function vaultRoleAccessors($role_name) {
		$accessors = self::vaultRequest('GET', '/v1/auth/token/accessors?list=true', null, PHPUNIT_HASHICORP_ROOT_TOKEN_ID);
		$matching = [];

		foreach ($accessors['body']['data']['keys'] ?? [] as $accessor) {
			$lookup = self::vaultRequest('POST', '/v1/auth/token/lookup-accessor', ['accessor' => $accessor],
					PHPUNIT_HASHICORP_ROOT_TOKEN_ID
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
	private static function vaultRevokeAccessors($accessors) {
		foreach ($accessors as $accessor) {
			self::vaultRequest('POST', '/v1/auth/token/revoke-accessor', ['accessor' => $accessor],
					PHPUNIT_HASHICORP_ROOT_TOKEN_ID
			);
		}
	}

	/**
	 * Start the server component with a deliberately broken Vault configuration and wait for the
	 * expected error to be logged, instead of waiting for a successful startup.
	 *
	 * These tests are not annotated with "@required-components", so the usual after-test cleanup
	 * never stops this component; that's normally fine, since a rejected configuration makes the
	 * server exit on its own. But if the expected error line never shows up (e.g. a regression lets
	 * the server start successfully instead), it would otherwise be left running and hold the port
	 * for the next test - so it's explicitly stopped here regardless of the outcome.
	 *
	 * @param array  $vault_config     "Vault*" configuration options to apply on top of the defaults
	 * @param string $expected_error   substring expected to appear in the server log
	 */
	private function startServerAndExpectVaultError($vault_config, $expected_error) {
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

		try {
			self::waitForLogLineToBePresent(self::COMPONENT_SERVER, $expected_error, false,
					self::WAIT_ITERATIONS_STARTUP, self::WAIT_ITERATION_DELAY
			);
		} finally {
			try {
				self::stopComponent(self::COMPONENT_SERVER);
			} catch (Exception $e) {
				// Nothing left to stop if the server already exited on its own, as expected.
			}
		}
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
		global $DB;

		// Store the credentials of the database used by the test environment as a Vault secret,
		// mirroring how a real deployment would keep DB credentials out of zabbix_server.conf.
		self::vaultRequest('POST', '/v1/secret/data/'.self::VAULT_SECRET_PATH, [
			'data' => [
				'username' => $DB['USER'],
				'password' => $DB['PASSWORD']
			]
		], PHPUNIT_HASHICORP_ROOT_TOKEN_ID);

		// A separate, unrelated secret used only by the Vault secret macro test below - see the
		// VAULT_MACRO_SECRET_PATH comment for why it can't just reuse VAULT_SECRET_PATH.
		self::vaultRequest('POST', '/v1/secret/data/'.self::VAULT_MACRO_SECRET_PATH, [
			'data' => [
				self::VAULT_MACRO_SECRET_KEY => self::VAULT_MACRO_SECRET_VALUE
			]
		], PHPUNIT_HASHICORP_ROOT_TOKEN_ID);

		self::vaultRequest('PUT', '/v1/sys/policy/'.self::VAULT_POLICY_NAME, [
			'policy' => 'path "secret/data/'.self::VAULT_SECRET_PATH.'" { capabilities = ["read"] }'.
					"\n".'path "secret/data/'.self::VAULT_MACRO_SECRET_PATH.'" { capabilities = ["read"] }'
		], PHPUNIT_HASHICORP_ROOT_TOKEN_ID);

		// Enabling an already-enabled auth method errors out, so check first.
		$auth_methods = self::vaultRequest('GET', '/v1/sys/auth', null, PHPUNIT_HASHICORP_ROOT_TOKEN_ID);

		if (!isset($auth_methods['body']['approle/'])) {
			self::vaultRequest('POST', '/v1/sys/auth/approle', ['type' => 'approle'], PHPUNIT_HASHICORP_ROOT_TOKEN_ID);
		}

		// A role POST to an already-existing role name overwrites its configuration in place.
		self::vaultRequest('POST', '/v1/auth/approle/role/'.self::VAULT_ROLE_NAME, [
			'token_policies' => self::VAULT_POLICY_NAME,
			'token_ttl' => '20s',
			'token_max_ttl' => '1h',
			'secret_id_ttl' => '1h'
		], PHPUNIT_HASHICORP_ROOT_TOKEN_ID);

		$role = self::vaultRequest('GET', '/v1/auth/approle/role/'.self::VAULT_ROLE_NAME.'/role-id', null,
				PHPUNIT_HASHICORP_ROOT_TOKEN_ID
		);
		self::$role_id = $role['body']['data']['role_id'];

		$secret = self::vaultRequest('POST', '/v1/auth/approle/role/'.self::VAULT_ROLE_NAME.'/secret-id', [],
				PHPUNIT_HASHICORP_ROOT_TOKEN_ID
		);
		self::$secret_id = $secret['body']['data']['secret_id'];

		$token = self::vaultRequest('POST', '/v1/auth/token/create', [
			'policies' => [self::VAULT_POLICY_NAME],
			'ttl' => '1h'
		], PHPUNIT_HASHICORP_ROOT_TOKEN_ID);
		self::$static_token = $token['body']['auth']['client_token'];

		// Create a host and trapper item. They will be used only to prove that the server was
		// successfully started with Vault-sourced DB credentials and is accepting the trapper item.
		// The trapper item is not related to testing of vault secret macro resolution.
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

		// A Vault secret macro is a separate feature from Vault-sourced DB credentials: it resolves
		// a "path:key" reference into the actual secret value wherever the macro is used, via a
		// periodic cache sync ("zbx_dc_sync_kvs_paths") rather than at server startup. Making the
		// frontend accept a HashiCorp-style "path:key" macro value requires the "vault_provider"
		// setting to be HashiCorp (it already defaults to that, but this suite doesn't rely on it).
		$this->call('settings.update', ['vault_provider' => ZBX_VAULT_TYPE_HASHICORP]);

		$response = $this->call('usermacro.create', [
			'hostid' => self::$hostid,
			'macro' => self::VAULT_MACRO,
			'type' => ZBX_MACRO_TYPE_VAULT,
			'value' => 'secret/'.self::VAULT_MACRO_SECRET_PATH.':'.self::VAULT_MACRO_SECRET_KEY
		]);
		$this->assertArrayHasKey('hostmacroids', $response['result']);

		// Create a script item that shows the macro's resolved secret value in the script's parameters.
		// Polling item history will allow to test is it resolved to expected value.
		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => self::VAULT_MACRO_ITEM_KEY,
			'key_' => self::VAULT_MACRO_ITEM_KEY,
			'type' => ITEM_TYPE_SCRIPT,
			'value_type' => ITEM_VALUE_TYPE_TEXT,
			'timeout' => '3s',
			'delay' => '1s',
			'parameters' => ['name' => 'secret', 'value' => self::VAULT_MACRO],
			'params' => 'return JSON.parse(value).secret;'
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		self::$vault_macro_itemid = $response['result']['itemids'][0];

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
				'VaultURL' => PHPUNIT_HASHICORP_ADDRESS,
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
		// tokenAuthenticationConfigurationProvider() function prepared server configuration
		// where DB credentials are obtained from vault with VaultToken. So, if server starts
		// and accepts a trapper item the test passes.
		$this->assertServerIsOperational();
	}

	/**
	 * Verify that a Vault secret macro ({$VAULT_SECRET_VALUE}, created in prepareData() with a
	 * "path:key" value) is actually resolved to the real secret stored in Vault, rather than just
	 * accepted by the API. This is a separate code path from Vault-sourced DB credentials: it is
	 * resolved via the periodic "zbx_dc_sync_kvs_paths" cache sync (which also runs once, right at
	 * startup, as part of the initial configuration sync), not the DB-credentials-at-startup one.
	 *
	 * The host, macro and script item all already existed in the database before this test's server
	 * was started (they were created once in prepareData(), at suite setup), so no configuration
	 * cache reload is needed here - the server's own initial sync picks them up.
	 *
	 * @required-components server
	 * @configurationDataProvider tokenAuthenticationConfigurationProvider
	 */
	public function testHashicorpVault_vaultMacroResolution() {
		// The script item returns the macro's resolved value as its own history value on every
		// execution (delay=1s); if the macro had failed to resolve, this would stay "*UNKNOWN*"
		// (Zabbix's placeholder for an unresolved Vault macro) instead of the value actually stored
		// in Vault.
		$this->callUntilDataIsPresent('history.get', [
			'itemids' => [self::$vault_macro_itemid],
			'history' => ITEM_VALUE_TYPE_TEXT
		], null, null, function($response) {
			$value = $response['result'][0]['value'];

			return ($value === self::VAULT_MACRO_SECRET_VALUE) ? true : 'unexpected value "'.$value.'"';
		});
	}

	public function appRoleAuthenticationConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DBUser' => [],
				'DBPassword' => [],
				'Vault' => 'HashiCorp',
				'VaultURL' => PHPUNIT_HASHICORP_ADDRESS,
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
		// appRoleAuthenticationConfigurationProvider() function prepared server configuration
		// where DB credentials are obtained from vault with VaultAppRoleID/VaultAppSecretID.
		// So, if server starts and accepts a trapper item the test passes.
		$this->assertServerIsOperational();
	}

	/**
	 * @required-components server
	 * @configurationDataProvider appRoleAuthenticationConfigurationProvider
	 */
	public function testHashicorpVault_appRoleReLoginAfterTokenRevocation() {
		// appRoleAuthenticationConfigurationProvider() function prepared server configuration
		// where DB credentials are obtained from vault with VaultAppRoleID/VaultAppSecretID.
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

		for ($i = 0; $i < self::WAIT_ITERATIONS; $i++) {
			if (array_diff(self::vaultRoleAccessors(self::VAULT_ROLE_NAME), $old_accessors)) {
				$relogged_in = true;
				break;
			}

			sleep(self::WAIT_ITERATION_DELAY);
		}

		$this->assertTrue($relogged_in, 'Server did not re-login with AppRole after its Vault token was revoked.');

		// The server must have transparently switched to the newly obtained token and must still
		// be able to serve requests.
		$this->assertServerIsOperational();
	}

	// Tests with various invalid configurations. The server is expected to start and
	// immediately stop with error logged.

	public function testHashicorpVault_missingTokenAndAppRoleId() {
		$this->startServerAndExpectVaultError([
			'Vault' => 'HashiCorp',
			'VaultURL' => PHPUNIT_HASHICORP_ADDRESS,
			'VaultDBPath' => self::VAULT_DB_PATH
		], 'either "VaultToken" or "VaultAppRoleID" configuration parameter should be defined');
	}

	public function testHashicorpVault_appRoleIdWithoutSecretId() {
		$this->startServerAndExpectVaultError([
			'Vault' => 'HashiCorp',
			'VaultURL' => PHPUNIT_HASHICORP_ADDRESS,
			'VaultDBPath' => self::VAULT_DB_PATH,
			'VaultAppRoleID' => self::$role_id
		], '"VaultAppRoleID" is defined but "VaultAppSecretID" is not defined');
	}

	public function testHashicorpVault_tokenAndAppRoleIdBothDefined() {
		$this->startServerAndExpectVaultError([
			'Vault' => 'HashiCorp',
			'VaultURL' => PHPUNIT_HASHICORP_ADDRESS,
			'VaultDBPath' => self::VAULT_DB_PATH,
			'VaultToken' => self::$static_token,
			'VaultAppRoleID' => self::$role_id
		], 'either "VaultToken" or "VaultAppRoleID" configuration parameter or corresponding environment '.
				'variable can be defined for HashiCorp vault but not both'
		);
	}

	public function testHashicorpVault_tokenAndAppSecretIdBothDefined() {
		$this->startServerAndExpectVaultError([
			'Vault' => 'HashiCorp',
			'VaultURL' => PHPUNIT_HASHICORP_ADDRESS,
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
			'VaultURL' => PHPUNIT_HASHICORP_ADDRESS,
			'VaultDBPath' => self::VAULT_DB_PATH,
			'VaultAppRoleID' => self::$role_id,
			'VaultAppSecretID' => '00000000-0000-0000-0000-000000000000'
		], 'cannot login into HashiCorp vault with AppRole method');
	}

	// The three tests below don't need a running CyberArk vault: "Vault=CyberArk" together with any
	// of "VaultToken"/"VaultAppRoleID"/"VaultAppSecretID" is rejected by configuration validation
	// alone, before any connection is attempted (AppRole authentication is HashiCorp-specific and is
	// not a supported CyberArk credential source).

	public function testHashicorpVault_cyberArkWithToken() {
		$this->startServerAndExpectVaultError([
			'Vault' => 'CyberArk',
			'VaultToken' => self::$static_token
		], 'configuration parameter "VaultToken" or "VAULT_TOKEN" environment variable ' .
				'cannot be used with CyberArk vault'
		);
	}

	public function testHashicorpVault_cyberArkWithAppRoleId() {
		$this->startServerAndExpectVaultError([
			'Vault' => 'CyberArk',
			'VaultAppRoleID' => self::$role_id
		], 'configuration parameter "VaultAppRoleID" cannot be used with CyberArk vault');
	}

	public function testHashicorpVault_cyberArkWithAppSecretId() {
		$this->startServerAndExpectVaultError([
			'Vault' => 'CyberArk',
			'VaultAppSecretID' => self::$secret_id
		], 'configuration parameter "VaultAppSecretID" cannot be used with CyberArk vault');
	}
}

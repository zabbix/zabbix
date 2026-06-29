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
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';

/**
 * Test suite for TLS encryption in data collection.
 *
 * Scenarios covered:
 *  1. Passive Zabbix agent data collection with certificate encryption
 *  2. Active + passive Zabbix Agent 2 with certificate encryption
 *  3. TLSCRLFile: empty CRL (connection succeeds); revoked cert CRL (connection rejected)
 *  4. TLSCipherCert / TLSCipherCert13 for certificate-based auth (AES-128)
 *  5. TLSCipherPSK / TLSCipherPSK13 for PSK-based auth (AES-128)
 *  6. TLSCipherAll / TLSCipherAll13 for all auth types (AES-128)
 *  7. Zabbix sender with certificate encryption
 *  8. libgnutls certificate encryption (skipped when compiled with OpenSSL)
 *  9. AES-256 cert ciphers – ECDHE-RSA-AES256-GCM-SHA384 / TLS_AES_256_GCM_SHA384
 *       (preferred by newer OpenSSL 3.x defaults)
 * 10. CHACHA20-POLY1305 PSK ciphers – hardware-independent modern cipher preference
 * 11. AES-256 TLSCipherAll – cert + PSK with stronger ciphers
 * 12. Cipher mismatch: non-overlapping server/agent lists → handshake rejected (negative)
 * 13. libgnutls TLS 1.2 + TLS 1.3 combined priority for cert
 *       (upgrade path from TLS 1.2-only; skipped when compiled with OpenSSL)
 * 14. libgnutls PSK with AES-128 priority string (skipped when compiled with OpenSSL)
 * 15. libgnutls AES-256 priority string for cert (skipped when compiled with OpenSSL)
 *
 * @onAfter clearData
 */
class testEncryptionDataCollection extends CIntegrationTest {

	private static array  $certDirs = [];
	private static string $pskFile  = '';
	private static array  $hostids  = [];
	private static array  $itemids  = [];

	const TRAPPER_ITEM_KEY = 'trapper.enc.test';
	const PSK_IDENTITY     = 'zabbix_enc_test_psk';
	const PSK_KEY          = '1f87b595725ac58dd977beef14b97461a7c1045b9a1c963065002c5473194952';

	// OpenSSL cipher strings
	const CIPHER_CERT_TLS12 = 'EECDH+aRSA+AES128:RSA+aRSA+AES128';
	const CIPHER_CERT_TLS13 = 'TLS_AES_256_GCM_SHA384:TLS_AES_128_GCM_SHA256';
	const CIPHER_PSK_TLS12  = 'kECDHEPSK+AES128:kPSK+AES128';
	const CIPHER_PSK_TLS13  = 'TLS_CHACHA20_POLY1305_SHA256:TLS_AES_128_GCM_SHA256';
	const CIPHER_ALL_TLS12  = 'EECDH+aRSA+AES128:RSA+aRSA+AES128:kECDHEPSK+AES128:kPSK+AES128';
	const CIPHER_ALL_TLS13  = 'TLS_AES_256_GCM_SHA384:TLS_CHACHA20_POLY1305_SHA256:TLS_AES_128_GCM_SHA256';

	// GnuTLS priority strings (TLS 1.2; Cert13 / PSK13 / All13 reuse *TLS12 with +VERS-TLS1.3)
	const GNUTLS_CIPHER_CERT =
		'NONE:+VERS-TLS1.2:+ECDHE-RSA:+RSA:+AES-128-GCM:+AES-128-CBC:+AEAD:+SHA256:+SHA1:+CURVE-ALL:+COMP-NULL:+SIGN-ALL:+CTYPE-X.509';
	const GNUTLS_CIPHER_PSK  =
		'NONE:+VERS-TLS1.2:+ECDHE-PSK:+PSK:+AES-128-GCM:+AES-128-CBC:+AEAD:+SHA256:+SHA1:+CURVE-ALL:+COMP-NULL:+SIGN-ALL';
	const GNUTLS_CIPHER_ALL  =
		'NONE:+VERS-TLS1.2:+ECDHE-RSA:+RSA:+ECDHE-PSK:+PSK:+AES-128-GCM:+AES-128-CBC:+AEAD:+SHA256:+SHA1:+CURVE-ALL:+COMP-NULL:+SIGN-ALL:+CTYPE-X.509';

	// OpenSSL – AES-256 variants (preferred by newer OpenSSL 3.x defaults)
	const CIPHER_CERT_TLS12_AES256 = 'ECDHE-RSA-AES256-GCM-SHA384:RSA+AES256';
	const CIPHER_CERT_TLS13_AES256 = 'TLS_AES_256_GCM_SHA384';
	const CIPHER_PSK_TLS12_CHACHA  = 'kECDHEPSK+CHACHA20:kPSK+CHACHA20:kECDHEPSK+AES256:kPSK+AES256';
	const CIPHER_PSK_TLS13_CHACHA  = 'TLS_CHACHA20_POLY1305_SHA256:TLS_AES_256_GCM_SHA384';
	const CIPHER_ALL_TLS12_AES256  = 'ECDHE-RSA-AES256-GCM-SHA384:RSA+AES256:kECDHEPSK+AES256:kPSK+AES256';
	const CIPHER_ALL_TLS13_AES256  = 'TLS_AES_256_GCM_SHA384:TLS_CHACHA20_POLY1305_SHA256';

	// OpenSSL – non-overlapping cipher sets; pairing server + agent produces a handshake failure
	const CIPHER_MISMATCH_SERVER_TLS12 = 'ECDHE-RSA-AES256-GCM-SHA384';
	const CIPHER_MISMATCH_SERVER_TLS13 = 'TLS_AES_256_GCM_SHA384';
	const CIPHER_MISMATCH_AGENT_TLS12  = 'ECDHE-RSA-AES128-GCM-SHA256';
	const CIPHER_MISMATCH_AGENT_TLS13  = 'TLS_AES_128_GCM_SHA256';

	// GnuTLS – TLS 1.2 + TLS 1.3 combined priority (covers the upgrade path)
	const GNUTLS_CIPHER_CERT_TLS12_TLS13 =
		'NONE:+VERS-TLS1.2:+VERS-TLS1.3:+ECDHE-RSA:+RSA:+AES-256-GCM:+AES-128-GCM:+AEAD:+SHA384:+SHA256:+SHA1:+CURVE-ALL:+COMP-NULL:+SIGN-ALL:+CTYPE-X.509';
	// GnuTLS – AES-256-only cert priority (newer-cipher preference of GnuTLS 3.7+)
	const GNUTLS_CIPHER_CERT_AES256 =
		'NONE:+VERS-TLS1.2:+ECDHE-RSA:+RSA:+AES-256-GCM:+AEAD:+SHA384:+SHA256:+CURVE-ALL:+COMP-NULL:+SIGN-ALL:+CTYPE-X.509';
	// GnuTLS – PSK with AES-256
	const GNUTLS_CIPHER_PSK_AES256 =
		'NONE:+VERS-TLS1.2:+ECDHE-PSK:+PSK:+AES-256-GCM:+AEAD:+SHA384:+CURVE-ALL:+COMP-NULL:+SIGN-ALL';

	// =========================================================================
	// Setup / teardown
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public function prepareData(): void {
		$agent_port  = $this->getConfigurationValue(self::COMPONENT_AGENT,  'ListenPort');
		$agent2_port = $this->getConfigurationValue(self::COMPONENT_AGENT2, 'ListenPort');

		$groups = ['groupid' => 4];

		$result = CDataHelper::createHosts([
			[
				'host'       => 'enc_agent',
				'interfaces' => [
					'type' => INTERFACE_TYPE_AGENT, 'main' => 1, 'useip' => 1,
					'ip'   => '127.0.0.1', 'dns' => '', 'port' => $agent_port
				],
				'groups' => $groups,
				'status' => HOST_STATUS_NOT_MONITORED,
				'items'  => [
					[
						'name'       => 'Enc ping passive',
						'key_'       => 'agent.ping',
						'type'       => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay'      => '1s'
					],
					[
						'name'       => 'Enc hostname active',
						'key_'       => 'agent.hostname',
						'type'       => ITEM_TYPE_ZABBIX_ACTIVE,
						'value_type' => ITEM_VALUE_TYPE_TEXT,
						'delay'      => '1s'
					]
				]
			],
			[
				'host'       => 'enc_agent2',
				'interfaces' => [
					'type' => INTERFACE_TYPE_AGENT, 'main' => 1, 'useip' => 1,
					'ip'   => '127.0.0.1', 'dns' => '', 'port' => $agent2_port
				],
				'groups' => $groups,
				'status' => HOST_STATUS_NOT_MONITORED,
				'items'  => [
					[
						'name'       => 'Enc2 ping passive',
						'key_'       => 'agent.ping',
						'type'       => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay'      => '1s'
					],
					[
						'name'       => 'Enc2 hostname active',
						'key_'       => 'agent.hostname',
						'type'       => ITEM_TYPE_ZABBIX_ACTIVE,
						'value_type' => ITEM_VALUE_TYPE_TEXT,
						'delay'      => '1s'
					]
				]
			],
			[
				'host'       => 'enc_trapper',
				'interfaces' => [
					'type' => INTERFACE_TYPE_AGENT, 'main' => 1, 'useip' => 1,
					'ip'   => '127.0.0.1', 'dns' => '', 'port' => $agent_port
				],
				'groups'      => $groups,
				'status'      => HOST_STATUS_NOT_MONITORED,
				'tls_accept'  => HOST_ENCRYPTION_CERTIFICATE,
				'tls_issuer'  => 'CN=ZabbixTestCA',
				'tls_subject' => 'CN=zabbix_agent',
				'items'  => [
					[
						'name'       => 'Enc trapper',
						'key_'       => self::TRAPPER_ITEM_KEY,
						'type'       => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_TEXT
					]
				]
			]
		]);

		self::$hostids = $result['hostids'];
		self::$itemids = $result['itemids'];

		self::$pskFile = '/tmp/zabbix_enc_psk_'.time().'.txt';
		file_put_contents(self::$pskFile, self::PSK_KEY);
	}

	/**
	 * Remove hosts, certificate directories and PSK file created during the suite.
	 */
	public static function clearData(): void {
		if (!empty(self::$hostids)) {
			CDataHelper::call('host.delete', array_values(self::$hostids));
		}

		foreach (self::$certDirs as $dir) {
			self::rmdirRecursive($dir);
		}
		self::$certDirs = [];

		if ('' !== self::$pskFile && file_exists(self::$pskFile)) {
			unlink(self::$pskFile);
		}
	}

	// =========================================================================
	// Certificate / CRL helpers
	// =========================================================================

	/**
	 * Generate CA, server, agent, agent2 and proxy certificates in a unique
	 * temporary directory.
	 *
	 * @return array  Associative: dir, ca_key, ca_crt, server_{key,crt},
	 *                agent_{key,crt}, agent2_{key,crt}, proxy_{key,crt}
	 */
	private static function generateCertificates(): array {
		$dir = '/tmp/zabbix_enc_cert_'.time().'_'.mt_rand(10000, 99999).'/';
		mkdir($dir, 0755, true);
		self::$certDirs[] = $dir;

		$f = [
			'ca_key'     => $dir.'ca.key',     'ca_crt'     => $dir.'ca.crt',
			'server_key' => $dir.'server.key', 'server_csr' => $dir.'server.csr',
			'server_crt' => $dir.'server.crt',
			'agent_key'  => $dir.'agent.key',  'agent_csr'  => $dir.'agent.csr',
			'agent_crt'  => $dir.'agent.crt',
			'agent2_key' => $dir.'agent2.key', 'agent2_csr' => $dir.'agent2.csr',
			'agent2_crt' => $dir.'agent2.crt',
			'proxy_key'  => $dir.'proxy.key',  'proxy_csr'  => $dir.'proxy.csr',
			'proxy_crt'  => $dir.'proxy.crt'
		];

		shell_exec("openssl genrsa -out {$f['ca_key']} 4096 2>/dev/null");
		shell_exec("openssl req -x509 -new -nodes -key {$f['ca_key']} -sha256 -days 1".
			" -out {$f['ca_crt']} -subj '/CN=ZabbixTestCA' 2>/dev/null");

		foreach ([
			['server', 'CN=zabbix_server'],
			['agent',  'CN=zabbix_agent'],
			['agent2', 'CN=zabbix_agent2'],
			['proxy',  'CN=zabbix_proxy']
		] as [$name, $subj]) {
			shell_exec("openssl genrsa -out {$f[$name.'_key']} 2048 2>/dev/null");
			shell_exec("openssl req -new -key {$f[$name.'_key']} -out {$f[$name.'_csr']}".
				" -subj '/$subj' 2>/dev/null");
			shell_exec("openssl x509 -req -in {$f[$name.'_csr']} -CA {$f['ca_crt']}".
				" -CAkey {$f['ca_key']} -CAcreateserial -out {$f[$name.'_crt']}".
				" -days 1 -sha256 2>/dev/null");
		}

		return array_merge(['dir' => $dir], $f);
	}

	/**
	 * Create a CRL from the given CA.  If $revoke_crt is provided, revoke it
	 * before generating the CRL.
	 *
	 * @param  string      $dir         Certificate directory (must already exist)
	 * @param  string      $ca_key      Path to CA private key
	 * @param  string      $ca_crt      Path to CA certificate
	 * @param  string|null $revoke_crt  Certificate to revoke, or null for empty CRL
	 * @return string                   Path to generated CRL file
	 */
	private static function generateCRL(string $dir, string $ca_key, string $ca_crt,
			?string $revoke_crt = null): string {
		$db = $dir.'ca_db/';
		mkdir($db, 0755, true);
		file_put_contents($db.'index.txt', '');
		file_put_contents($db.'serial', "01\n");

		$cfg = $dir.'openssl_ca.cnf';
		file_put_contents($cfg, implode("\n", [
			'[ca]',
			'default_ca = CA_default',
			'',
			'[CA_default]',
			"dir           = $db",
			"database      = {$db}index.txt",
			"serial        = {$db}serial",
			"certificate   = $ca_crt",
			"private_key   = $ca_key",
			"new_certs_dir = $db",
			'default_md    = sha256',
			'policy        = policy_anything',
			'default_crl_days = 1',
			'',
			'[policy_anything]',
			'commonName = supplied'
		]));

		if (null !== $revoke_crt) {
			shell_exec("openssl ca -config $cfg -revoke $revoke_crt".
				" -keyfile $ca_key -cert $ca_crt -batch 2>/dev/null");
		}

		$crl = $dir.'crl.pem';
		shell_exec("openssl ca -config $cfg -gencrl".
			" -keyfile $ca_key -cert $ca_crt -out $crl 2>/dev/null");

		return $crl;
	}

	// =========================================================================
	// Misc helpers
	// =========================================================================

	/**
	 * Detect whether the Zabbix server binary was linked against GnuTLS or OpenSSL.
	 */
	private function detectTLSLibrary(): string {
		$out = shell_exec('strings '.escapeshellarg(PHPUNIT_BINARY_DIR.'zabbix_server').
			' 2>/dev/null | grep -Ei "^GnuTLS|^OpenSSL" | head -3');

		return ($out !== null && stripos($out, 'gnutls') !== false) ? 'gnutls' : 'openssl';
	}

	/**
	 * Run zabbix_sender with certificate TLS options and return true when the
	 * server reports "processed: 1".
	 */
	private function runSenderWithCert(string $zbx_host, string $key, string $value,
			string $ca_crt, string $cert_file, string $key_file,
			string $issuer, string $subject, ?string &$output = null): bool {
		$port = $this->getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort');
		$cmd  = sprintf(
			'%s -z 127.0.0.1 -p %d -s %s -k %s -o %s'.
			' --tls-connect cert'.
			' --tls-ca-file %s --tls-cert-file %s --tls-key-file %s'.
			' --tls-server-cert-issuer %s --tls-server-cert-subject %s'.
			' 2>&1',
			escapeshellarg(dirname(rtrim(PHPUNIT_BINARY_DIR, '/')).'/bin/zabbix_sender'),
			(int)$port,
			escapeshellarg($zbx_host),
			escapeshellarg($key),
			escapeshellarg($value),
			escapeshellarg($ca_crt),
			escapeshellarg($cert_file),
			escapeshellarg($key_file),
			escapeshellarg($issuer),
			escapeshellarg($subject)
		);

		$out    = shell_exec($cmd);
		$output = $out;

		return $out !== null && strpos($out, 'processed: 1') !== false;
	}

	/**
	 * Set cert-based TLS on a host (both connect and accept).
	 */
	private function updateHostCertTLS(string $host, string $issuer, string $subject): void {
		$r = $this->call('host.get', ['filter' => ['host' => $host], 'output' => ['hostid']]);
		$this->assertNotEmpty($r['result'], "Host '$host' not found");

		$this->call('host.update', [
			'hostid'      => $r['result'][0]['hostid'],
			'tls_connect' => HOST_ENCRYPTION_CERTIFICATE,
			'tls_accept'  => HOST_ENCRYPTION_CERTIFICATE,
			'tls_issuer'  => $issuer,
			'tls_subject' => $subject
		]);
	}

	/**
	 * Set PSK-based TLS on a host (both connect and accept).
	 */
	private function updateHostPSKTLS(string $host): void {
		$r = $this->call('host.get', ['filter' => ['host' => $host], 'output' => ['hostid']]);
		$this->assertNotEmpty($r['result'], "Host '$host' not found");

		$this->call('host.update', [
			'hostid'           => $r['result'][0]['hostid'],
			'tls_connect'      => HOST_ENCRYPTION_PSK,
			'tls_accept'       => HOST_ENCRYPTION_PSK,
			'tls_psk_identity' => self::PSK_IDENTITY,
			'tls_psk'          => self::PSK_KEY
		]);
	}

	/** Recursively delete a directory tree. */
	private static function rmdirRecursive(string $dir): void {
		$dir = rtrim($dir, '/');
		if (!is_dir($dir)) {
			return;
		}
		foreach (array_diff(scandir($dir), ['.', '..']) as $entry) {
			$path = $dir.'/'.$entry;
			is_dir($path) ? self::rmdirRecursive($path) : unlink($path);
		}
		rmdir($dir);
	}

	// =========================================================================
	// Test 1 – Passive agent (agent) with certificate encryption
	// =========================================================================

	/**
	 * @return array
	 */
	public function configProviderPassiveCert(): array {
		$c = self::generateCertificates();
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel'        => 4,
				'UnreachablePeriod' => 5,
				'UnavailableDelay'  => 5,
				'UnreachableDelay'  => 1,
				'TLSCAFile'         => $c['ca_crt'],
				'TLSCertFile'       => $c['server_crt'],
				'TLSKeyFile'        => $c['server_key']
			],
			self::COMPONENT_AGENT => [
				'Hostname'             => 'enc_agent',
				'ServerActive'         => '127.0.0.1',
				'DebugLevel'           => 4,
				'TLSConnect'           => 'cert',
				'TLSAccept'            => 'cert',
				'TLSCAFile'            => $c['ca_crt'],
				'TLSCertFile'          => $c['agent_crt'],
				'TLSKeyFile'           => $c['agent_key'],
				'TLSServerCertIssuer'  => 'CN=ZabbixTestCA',
				'TLSServerCertSubject' => 'CN=zabbix_server'
			]
		];
	}

	/**
	 * Passive Zabbix agent data collection with certificate encryption.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider configProviderPassiveCert
	 * @hosts enc_agent
	 * @backup hosts
	 */
	public function testEncryption_passiveAgentCert(): void {
		$this->updateHostCertTLS('enc_agent', 'CN=ZabbixTestCA', 'CN=zabbix_agent');

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, [
			'enabling Zabbix agent checks on host "enc_agent": interface became available',
			'resuming Zabbix agent checks on host "enc_agent": connection restored'
		]);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'zbx_tls_connect() peer certificate issuer:"CN=ZabbixTestCA" subject:"CN=zabbix_agent"');
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_tls_connect():SUCCEED (established TLS');
		self::waitForLogLineToBePresent(self::COMPONENT_AGENT,
			'End of zbx_tls_accept():SUCCEED (established TLS');

		$data = $this->callUntilDataIsPresent('history.get', [
			'itemids' => self::$itemids['enc_agent:agent.ping'],
			'history' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertNotEmpty($data['result'], 'No passive agent data collected with cert encryption');
		foreach ($data['result'] as $row) {
			$this->assertEquals(1, $row['value']);
		}
	}

	// =========================================================================
	// Test 2 – Zabbix Agent 2: active + passive with certificate encryption
	// =========================================================================

	/**
	 * @return array
	 */
	public function configProviderAgent2Cert(): array {
		$c = self::generateCertificates();
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel'        => 4,
				'UnreachablePeriod' => 5,
				'UnavailableDelay'  => 5,
				'UnreachableDelay'  => 1,
				'TLSCAFile'         => $c['ca_crt'],
				'TLSCertFile'       => $c['server_crt'],
				'TLSKeyFile'        => $c['server_key']
			],
			self::COMPONENT_AGENT2 => [
				'Hostname'             => 'enc_agent2',
				'ServerActive'         => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'DebugLevel'           => 4,
				'RefreshActiveChecks'  => 1,
				'TLSConnect'           => 'cert',
				'TLSAccept'            => 'cert',
				'TLSCAFile'            => $c['ca_crt'],
				'TLSCertFile'          => $c['agent2_crt'],
				'TLSKeyFile'           => $c['agent2_key'],
				'TLSServerCertIssuer'  => 'CN=ZabbixTestCA',
				'TLSServerCertSubject' => 'CN=zabbix_server'
			]
		];
	}

	/**
	 * Active and passive Zabbix Agent 2 data collection with certificate encryption.
	 *
	 * @required-components server, agent2
	 * @configurationDataProvider configProviderAgent2Cert
	 * @hosts enc_agent2
	 * @backup hosts
	 */
	public function testEncryption_agent2Cert(): void {
		$this->updateHostCertTLS('enc_agent2', 'CN=ZabbixTestCA', 'CN=zabbix_agent2');

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, [
			'enabling Zabbix agent checks on host "enc_agent2": interface became available',
			'resuming Zabbix agent checks on host "enc_agent2": connection restored'
		]);

		// Passive: server → agent2
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'zbx_tls_connect() peer certificate issuer:"CN=ZabbixTestCA" subject:"CN=zabbix_agent2"');
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_tls_connect():SUCCEED (established TLS');
		// Agent 2 is Go-based and does not emit C-style "zbx_tls_accept()" messages;
		// it logs "connection established using <cipher>" on both accept and connect.
		self::waitForLogLineToBePresent(self::COMPONENT_AGENT2,
			'connection established using');

		$passive = $this->callUntilDataIsPresent('history.get', [
			'itemids' => self::$itemids['enc_agent2:agent.ping'],
			'history' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertNotEmpty($passive['result'], 'No passive Agent 2 data collected');
		foreach ($passive['result'] as $row) {
			$this->assertEquals(1, $row['value']);
		}

		// Active: agent2 → server
		self::waitForLogLineToBePresent(self::COMPONENT_AGENT2,
			'connection established using');

		$active = $this->callUntilDataIsPresent('history.get', [
			'itemids' => self::$itemids['enc_agent2:agent.hostname'],
			'history' => ITEM_VALUE_TYPE_TEXT
		]);
		$this->assertNotEmpty($active['result'], 'No active Agent 2 data collected');
		foreach ($active['result'] as $row) {
			$this->assertEquals('enc_agent2', $row['value']);
		}
	}

	// =========================================================================
	// Test 3a – TLSCRLFile: empty CRL allows connection
	// =========================================================================

	/**
	 * @return array
	 */
	public function configProviderCRLEmpty(): array {
		$c   = self::generateCertificates();
		$crl = self::generateCRL($c['dir'], $c['ca_key'], $c['ca_crt']);
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel'        => 4,
				'UnreachablePeriod' => 5,
				'UnavailableDelay'  => 5,
				'UnreachableDelay'  => 1,
				'TLSCAFile'         => $c['ca_crt'],
				'TLSCertFile'       => $c['server_crt'],
				'TLSKeyFile'        => $c['server_key'],
				'TLSCRLFile'        => $crl
			],
			self::COMPONENT_AGENT => [
				'Hostname'             => 'enc_agent',
				'ServerActive'         => '127.0.0.1',
				'DebugLevel'           => 4,
				'TLSConnect'           => 'cert',
				'TLSAccept'            => 'cert',
				'TLSCAFile'            => $c['ca_crt'],
				'TLSCertFile'          => $c['agent_crt'],
				'TLSKeyFile'           => $c['agent_key'],
				'TLSCRLFile'           => $crl,
				'TLSServerCertIssuer'  => 'CN=ZabbixTestCA',
				'TLSServerCertSubject' => 'CN=zabbix_server'
			]
		];
	}

	/**
	 * TLSCRLFile configured with an empty (no revocations) CRL: connection succeeds.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider configProviderCRLEmpty
	 * @hosts enc_agent
	 * @backup hosts
	 */
	public function testEncryption_crlEmpty(): void {
		$this->updateHostCertTLS('enc_agent', 'CN=ZabbixTestCA', 'CN=zabbix_agent');

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, [
			'enabling Zabbix agent checks on host "enc_agent": interface became available',
			'resuming Zabbix agent checks on host "enc_agent": connection restored'
		]);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_tls_connect():SUCCEED (established TLS');

		$data = $this->callUntilDataIsPresent('history.get', [
			'itemids' => self::$itemids['enc_agent:agent.ping'],
			'history' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertNotEmpty($data['result'], 'No data collected with empty TLSCRLFile');
	}

	// =========================================================================
	// Test 3b – TLSCRLFile: revoked agent certificate blocks connection
	// =========================================================================

	/**
	 * @return array
	 */
	public function configProviderCRLRevoked(): array {
		$c   = self::generateCertificates();
		$crl = self::generateCRL($c['dir'], $c['ca_key'], $c['ca_crt'], $c['agent_crt']);
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel'        => 4,
				'UnreachablePeriod' => 5,
				'UnavailableDelay'  => 5,
				'UnreachableDelay'  => 1,
				'TLSCAFile'         => $c['ca_crt'],
				'TLSCertFile'       => $c['server_crt'],
				'TLSKeyFile'        => $c['server_key'],
				'TLSCRLFile'        => $crl
			],
			self::COMPONENT_AGENT => [
				'Hostname'             => 'enc_agent',
				'ServerActive'         => '127.0.0.1',
				'DebugLevel'           => 4,
				'TLSConnect'           => 'cert',
				'TLSAccept'            => 'cert',
				'TLSCAFile'            => $c['ca_crt'],
				'TLSCertFile'          => $c['agent_crt'],
				'TLSKeyFile'           => $c['agent_key'],
				'TLSCRLFile'           => $crl,
				'TLSServerCertIssuer'  => 'CN=ZabbixTestCA',
				'TLSServerCertSubject' => 'CN=zabbix_server'
			]
		];
	}

	/**
	 * TLSCRLFile containing the revoked agent certificate: connection is rejected.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider configProviderCRLRevoked
	 * @hosts enc_agent
	 * @backup hosts
	 */
	public function testEncryption_crlRevokedCert(): void {
		$start_time = time();
		$this->updateHostCertTLS('enc_agent', 'CN=ZabbixTestCA', 'CN=zabbix_agent');

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, [
			'certificate revoked',
			'failed to accept an incoming connection',
			'TLS handshake'
		], true, 30);

		// No history must have been collected during this test run
		$data = $this->call('history.get', [
			'itemids'   => self::$itemids['enc_agent:agent.ping'],
			'history'   => ITEM_VALUE_TYPE_UINT64,
			'time_from' => $start_time
		]);
		$this->assertEmpty($data['result'],
			'Data was collected despite a revoked agent certificate in TLSCRLFile');
	}

	// =========================================================================
	// Test 4 – TLSCipherCert + TLSCipherCert13
	// =========================================================================

	/**
	 * @return array
	 */
	public function configProviderCipherCert(): array {
		$c = self::generateCertificates();
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel'        => 4,
				'UnreachablePeriod' => 5,
				'UnavailableDelay'  => 5,
				'UnreachableDelay'  => 1,
				'TLSCAFile'         => $c['ca_crt'],
				'TLSCertFile'       => $c['server_crt'],
				'TLSKeyFile'        => $c['server_key'],
				'TLSCipherCert'     => self::CIPHER_CERT_TLS12,
				'TLSCipherCert13'   => self::CIPHER_CERT_TLS13
			],
			self::COMPONENT_AGENT => [
				'Hostname'             => 'enc_agent',
				'ServerActive'         => '127.0.0.1',
				'DebugLevel'           => 4,
				'TLSConnect'           => 'cert',
				'TLSAccept'            => 'cert',
				'TLSCAFile'            => $c['ca_crt'],
				'TLSCertFile'          => $c['agent_crt'],
				'TLSKeyFile'           => $c['agent_key'],
				'TLSServerCertIssuer'  => 'CN=ZabbixTestCA',
				'TLSServerCertSubject' => 'CN=zabbix_server',
				'TLSCipherCert'        => self::CIPHER_CERT_TLS12,
				'TLSCipherCert13'      => self::CIPHER_CERT_TLS13
			]
		];
	}

	/**
	 * Data collection succeeds when TLSCipherCert and TLSCipherCert13 are set
	 * to compatible cipher lists on server and agent.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider configProviderCipherCert
	 * @hosts enc_agent
	 * @backup hosts
	 */
	public function testEncryption_cipherCert(): void {
		$this->updateHostCertTLS('enc_agent', 'CN=ZabbixTestCA', 'CN=zabbix_agent');

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, [
			'enabling Zabbix agent checks on host "enc_agent": interface became available',
			'resuming Zabbix agent checks on host "enc_agent": connection restored'
		]);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_tls_connect():SUCCEED (established TLS');

		$data = $this->callUntilDataIsPresent('history.get', [
			'itemids' => self::$itemids['enc_agent:agent.ping'],
			'history' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertNotEmpty($data['result'],
			'No data collected with TLSCipherCert / TLSCipherCert13 configured');
	}

	// =========================================================================
	// Test 5 – TLSCipherPSK + TLSCipherPSK13
	// =========================================================================

	/**
	 * @return array
	 */
	public function configProviderCipherPSK(): array {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel'        => 4,
				'UnreachablePeriod' => 5,
				'UnavailableDelay'  => 5,
				'UnreachableDelay'  => 1,
				'TLSCipherPSK'      => self::CIPHER_PSK_TLS12,
				'TLSCipherPSK13'    => self::CIPHER_PSK_TLS13
			],
			self::COMPONENT_AGENT => [
				'Hostname'       => 'enc_agent',
				'ServerActive'   => '127.0.0.1',
				'DebugLevel'     => 4,
				'TLSConnect'     => 'psk',
				'TLSAccept'      => 'psk',
				'TLSPSKIdentity' => self::PSK_IDENTITY,
				'TLSPSKFile'     => self::$pskFile,
				'TLSCipherPSK'   => self::CIPHER_PSK_TLS12,
				'TLSCipherPSK13' => self::CIPHER_PSK_TLS13
			]
		];
	}

	/**
	 * Data collection succeeds when TLSCipherPSK and TLSCipherPSK13 are set
	 * to compatible cipher lists on server and agent.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider configProviderCipherPSK
	 * @hosts enc_agent
	 * @backup hosts
	 */
	public function testEncryption_cipherPSK(): void {
		$this->updateHostPSKTLS('enc_agent');

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, [
			'enabling Zabbix agent checks on host "enc_agent": interface became available',
			'resuming Zabbix agent checks on host "enc_agent": connection restored'
		]);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_tls_connect():SUCCEED (established TLS');

		$data = $this->callUntilDataIsPresent('history.get', [
			'itemids' => self::$itemids['enc_agent:agent.ping'],
			'history' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertNotEmpty($data['result'],
			'No data collected with TLSCipherPSK / TLSCipherPSK13 configured');
	}

	// =========================================================================
	// Test 6 – TLSCipherAll + TLSCipherAll13
	// =========================================================================

	/**
	 * @return array
	 */
	public function configProviderCipherAll(): array {
		$c = self::generateCertificates();
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel'        => 4,
				'UnreachablePeriod' => 5,
				'UnavailableDelay'  => 5,
				'UnreachableDelay'  => 1,
				'TLSCAFile'         => $c['ca_crt'],
				'TLSCertFile'       => $c['server_crt'],
				'TLSKeyFile'        => $c['server_key'],
				'TLSCipherAll'      => self::CIPHER_ALL_TLS12,
				'TLSCipherAll13'    => self::CIPHER_ALL_TLS13
			],
			// TLSCipherAll on agentd requires ctx_all (the combined cert+PSK SSL context), which is
			// only created when both cert AND PSK are configured.  Without a PSK file the agent has
			// no ctx_psk, ctx_all stays NULL, and zbx_tls_init_child() calls zbx_exit(EXIT_FAILURE).
			// Add PSK alongside cert so that ctx_all is built and TLSCipherAll is actually applied.
			self::COMPONENT_AGENT => [
				'Hostname'             => 'enc_agent',
				'ServerActive'         => '127.0.0.1',
				'DebugLevel'           => 4,
				'TLSConnect'           => 'cert',
				'TLSAccept'            => 'cert,psk',
				'TLSCAFile'            => $c['ca_crt'],
				'TLSCertFile'          => $c['agent_crt'],
				'TLSKeyFile'           => $c['agent_key'],
				'TLSServerCertIssuer'  => 'CN=ZabbixTestCA',
				'TLSServerCertSubject' => 'CN=zabbix_server',
				'TLSPSKIdentity'       => self::PSK_IDENTITY,
				'TLSPSKFile'           => self::$pskFile,
				'TLSCipherAll'         => self::CIPHER_ALL_TLS12,
				'TLSCipherAll13'       => self::CIPHER_ALL_TLS13
			]
		];
	}

	/**
	 * Data collection succeeds when TLSCipherAll and TLSCipherAll13 are set
	 * to compatible cipher lists on server and agent.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider configProviderCipherAll
	 * @hosts enc_agent
	 * @backup hosts
	 */
	public function testEncryption_cipherAll(): void {
		$this->updateHostCertTLS('enc_agent', 'CN=ZabbixTestCA', 'CN=zabbix_agent');

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, [
			'enabling Zabbix agent checks on host "enc_agent": interface became available',
			'resuming Zabbix agent checks on host "enc_agent": connection restored'
		]);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_tls_connect():SUCCEED (established TLS');

		$data = $this->callUntilDataIsPresent('history.get', [
			'itemids' => self::$itemids['enc_agent:agent.ping'],
			'history' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertNotEmpty($data['result'],
			'No data collected with TLSCipherAll / TLSCipherAll13 configured');
	}

	// =========================================================================
	// Test 7 – Zabbix sender with certificate encryption
	// =========================================================================

	/**
	 * @return array
	 */
	public function configProviderSenderCert(): array {
		$c = self::generateCertificates();
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'TLSCAFile'  => $c['ca_crt'],
				'TLSCertFile'=> $c['server_crt'],
				'TLSKeyFile' => $c['server_key']
			]
		];
	}

	/**
	 * Zabbix sender delivers a trapper item value over a certificate-encrypted
	 * connection.
	 *
	 * @required-components server
	 * @configurationDataProvider configProviderSenderCert
	 * @hosts enc_trapper
	 * @backup hosts
	 */
	public function testEncryption_senderCert(): void {
		// TLS settings are pre-configured on enc_trapper in prepareData so the
		// server loads them at startup without needing a runtime host.update + reload.
		$dir       = end(self::$certDirs);
		$ca_crt    = $dir.'ca.crt';
		$agent_crt = $dir.'agent.crt';
		$agent_key = $dir.'agent.key';

		$value  = 'sender_cert_'.time();
		$sender_out = null;
		$sent   = $this->runSenderWithCert(
			'enc_trapper', self::TRAPPER_ITEM_KEY, $value,
			$ca_crt, $agent_crt, $agent_key,
			'CN=ZabbixTestCA', 'CN=zabbix_server',
			$sender_out
		);

		$this->assertTrue($sent,
			'zabbix_sender with cert TLS did not report "processed: 1"; output: '.(string)$sender_out);

		$data = $this->callUntilDataIsPresent('history.get', [
			'itemids' => self::$itemids['enc_trapper:'.self::TRAPPER_ITEM_KEY],
			'history' => ITEM_VALUE_TYPE_TEXT
		]);
		$this->assertNotEmpty($data['result'], 'No history from cert-encrypted sender');
		$this->assertEquals($value, $data['result'][0]['value'],
			'Received value does not match what sender transmitted');
	}

	// =========================================================================
	// Test 8 – libgnutls certificate encryption
	// (skipped automatically when the binary was compiled with OpenSSL)
	// =========================================================================

	/**
	 * @return array
	 */
	public function configProviderLibgnutls(): array {
		$c       = self::generateCertificates();
		$isGnutls = ('gnutls' === $this->detectTLSLibrary());

		// GnuTLS priority strings are only valid when the binaries are compiled against GnuTLS.
		// Passing them to an OpenSSL build makes TLS initialisation fail: for the C agent the
		// listener child exits after logging "started [listener #1]" (startup check still passes,
		// but the interface never becomes available); for Agent 2 the whole process exits before
		// writing its PID file, so waitForStartup() times out.  Only set the cipher strings when
		// GnuTLS is actually in use; the test body will call markTestSkipped() on OpenSSL builds.
		$config = [
			self::COMPONENT_SERVER => [
				'DebugLevel'        => 4,
				'UnreachablePeriod' => 5,
				'UnavailableDelay'  => 5,
				'UnreachableDelay'  => 1,
				'TLSCAFile'         => $c['ca_crt'],
				'TLSCertFile'       => $c['server_crt'],
				'TLSKeyFile'        => $c['server_key']
			],
			self::COMPONENT_AGENT => [
				'Hostname'             => 'enc_agent',
				'ServerActive'         => '127.0.0.1',
				'DebugLevel'           => 4,
				'TLSConnect'           => 'cert',
				'TLSAccept'            => 'cert',
				'TLSCAFile'            => $c['ca_crt'],
				'TLSCertFile'          => $c['agent_crt'],
				'TLSKeyFile'           => $c['agent_key'],
				'TLSServerCertIssuer'  => 'CN=ZabbixTestCA',
				'TLSServerCertSubject' => 'CN=zabbix_server'
			],
			self::COMPONENT_AGENT2 => [
				'Hostname'             => 'enc_agent2',
				'ServerActive'         => '127.0.0.1',
				'DebugLevel'           => 4,
				'TLSConnect'           => 'cert',
				'TLSAccept'            => 'cert',
				'TLSCAFile'            => $c['ca_crt'],
				'TLSCertFile'          => $c['agent2_crt'],
				'TLSKeyFile'           => $c['agent2_key'],
				'TLSServerCertIssuer'  => 'CN=ZabbixTestCA',
				'TLSServerCertSubject' => 'CN=zabbix_server'
			]
		];

		if ($isGnutls) {
			$config[self::COMPONENT_SERVER]['TLSCipherCert'] = self::GNUTLS_CIPHER_CERT;
			$config[self::COMPONENT_SERVER]['TLSCipherAll']  = self::GNUTLS_CIPHER_ALL;
			$config[self::COMPONENT_AGENT]['TLSCipherCert']  = self::GNUTLS_CIPHER_CERT;
			$config[self::COMPONENT_AGENT]['TLSCipherAll']   = self::GNUTLS_CIPHER_ALL;
			$config[self::COMPONENT_AGENT2]['TLSCipherCert'] = self::GNUTLS_CIPHER_CERT;
			$config[self::COMPONENT_AGENT2]['TLSCipherAll']  = self::GNUTLS_CIPHER_ALL;
		}

		return $config;
	}

	/**
	 * Certificate-based data collection using GnuTLS priority strings.
	 * Tests both agent and agent2 over the same GnuTLS cipher configuration.
	 * Automatically skipped when the server binary was compiled with OpenSSL.
	 *
	 * @required-components server, agent, agent2
	 * @configurationDataProvider configProviderLibgnutls
	 * @hosts enc_agent, enc_agent2
	 * @backup hosts
	 */
	public function testEncryption_libgnutls(): void {
		if ('gnutls' !== $this->detectTLSLibrary()) {
			$this->markTestSkipped('Server not compiled with GnuTLS; skipping libgnutls test.');
		}

		$this->updateHostCertTLS('enc_agent',  'CN=ZabbixTestCA', 'CN=zabbix_agent');
		$this->updateHostCertTLS('enc_agent2', 'CN=ZabbixTestCA', 'CN=zabbix_agent2');

		// Agent 1
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, [
			'enabling Zabbix agent checks on host "enc_agent": interface became available',
			'resuming Zabbix agent checks on host "enc_agent": connection restored'
		]);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_tls_connect():SUCCEED (established TLS');

		$data1 = $this->callUntilDataIsPresent('history.get', [
			'itemids' => self::$itemids['enc_agent:agent.ping'],
			'history' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertNotEmpty($data1['result'],
			'No agent data collected in libgnutls test');

		// Agent 2
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, [
			'enabling Zabbix agent checks on host "enc_agent2": interface became available',
			'resuming Zabbix agent checks on host "enc_agent2": connection restored'
		]);

		$data2 = $this->callUntilDataIsPresent('history.get', [
			'itemids' => self::$itemids['enc_agent2:agent.ping'],
			'history' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertNotEmpty($data2['result'],
			'No Agent 2 data collected in libgnutls test');
	}

	// =========================================================================
	// Test 9 – AES-256 cert ciphers (newer OpenSSL 3.x default preference)
	// =========================================================================

	/**
	 * @return array
	 */
	public function configProviderCipherCertAES256(): array {
		$c = self::generateCertificates();
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel'        => 4,
				'UnreachablePeriod' => 5,
				'UnavailableDelay'  => 5,
				'UnreachableDelay'  => 1,
				'TLSCAFile'         => $c['ca_crt'],
				'TLSCertFile'       => $c['server_crt'],
				'TLSKeyFile'        => $c['server_key'],
				'TLSCipherCert'     => self::CIPHER_CERT_TLS12_AES256,
				'TLSCipherCert13'   => self::CIPHER_CERT_TLS13_AES256
			],
			self::COMPONENT_AGENT => [
				'Hostname'             => 'enc_agent',
				'ServerActive'         => '127.0.0.1',
				'DebugLevel'           => 4,
				'TLSConnect'           => 'cert',
				'TLSAccept'            => 'cert',
				'TLSCAFile'            => $c['ca_crt'],
				'TLSCertFile'          => $c['agent_crt'],
				'TLSKeyFile'           => $c['agent_key'],
				'TLSServerCertIssuer'  => 'CN=ZabbixTestCA',
				'TLSServerCertSubject' => 'CN=zabbix_server',
				'TLSCipherCert'        => self::CIPHER_CERT_TLS12_AES256,
				'TLSCipherCert13'      => self::CIPHER_CERT_TLS13_AES256
			]
		];
	}

	/**
	 * Certificate auth succeeds with AES-256 cipher suites matching the stronger
	 * defaults preferred by newer OpenSSL 3.x deployments.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider configProviderCipherCertAES256
	 * @hosts enc_agent
	 * @backup hosts
	 */
	public function testEncryption_cipherCertAES256(): void {
		$this->updateHostCertTLS('enc_agent', 'CN=ZabbixTestCA', 'CN=zabbix_agent');

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, [
			'enabling Zabbix agent checks on host "enc_agent": interface became available',
			'resuming Zabbix agent checks on host "enc_agent": connection restored'
		]);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_tls_connect():SUCCEED (established TLS');

		$data = $this->callUntilDataIsPresent('history.get', [
			'itemids' => self::$itemids['enc_agent:agent.ping'],
			'history' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertNotEmpty($data['result'],
			'No data collected with AES-256 TLSCipherCert / TLSCipherCert13');
	}

	// =========================================================================
	// Test 10 – CHACHA20-POLY1305 PSK (modern hardware-independent cipher)
	// =========================================================================

	/**
	 * @return array
	 */
	public function configProviderCipherPSKChacha(): array {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel'        => 4,
				'UnreachablePeriod' => 5,
				'UnavailableDelay'  => 5,
				'UnreachableDelay'  => 1,
				'TLSCipherPSK'      => self::CIPHER_PSK_TLS12_CHACHA,
				'TLSCipherPSK13'    => self::CIPHER_PSK_TLS13_CHACHA
			],
			self::COMPONENT_AGENT => [
				'Hostname'       => 'enc_agent',
				'ServerActive'   => '127.0.0.1',
				'DebugLevel'     => 4,
				'TLSConnect'     => 'psk',
				'TLSAccept'      => 'psk',
				'TLSPSKIdentity' => self::PSK_IDENTITY,
				'TLSPSKFile'     => self::$pskFile,
				'TLSCipherPSK'   => self::CIPHER_PSK_TLS12_CHACHA,
				'TLSCipherPSK13' => self::CIPHER_PSK_TLS13_CHACHA
			]
		];
	}

	/**
	 * PSK auth succeeds with CHACHA20-POLY1305 ciphers preferred by newer TLS
	 * stacks on platforms without hardware AES acceleration; AES-256 is included
	 * as a fallback so the test is not platform-sensitive.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider configProviderCipherPSKChacha
	 * @hosts enc_agent
	 * @backup hosts
	 */
	public function testEncryption_cipherPSKChacha(): void {
		$this->updateHostPSKTLS('enc_agent');

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, [
			'enabling Zabbix agent checks on host "enc_agent": interface became available',
			'resuming Zabbix agent checks on host "enc_agent": connection restored'
		]);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_tls_connect():SUCCEED (established TLS');

		$data = $this->callUntilDataIsPresent('history.get', [
			'itemids' => self::$itemids['enc_agent:agent.ping'],
			'history' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertNotEmpty($data['result'],
			'No data collected with CHACHA20-POLY1305 TLSCipherPSK / TLSCipherPSK13');
	}

	// =========================================================================
	// Test 11 – AES-256 TLSCipherAll (cert + PSK combined, stronger ciphers)
	// =========================================================================

	/**
	 * @return array
	 */
	public function configProviderCipherAllAES256(): array {
		$c = self::generateCertificates();
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel'        => 4,
				'UnreachablePeriod' => 5,
				'UnavailableDelay'  => 5,
				'UnreachableDelay'  => 1,
				'TLSCAFile'         => $c['ca_crt'],
				'TLSCertFile'       => $c['server_crt'],
				'TLSKeyFile'        => $c['server_key'],
				'TLSCipherAll'      => self::CIPHER_ALL_TLS12_AES256,
				'TLSCipherAll13'    => self::CIPHER_ALL_TLS13_AES256
			],
			// Same ctx_all requirement as configProviderCipherAll: PSK must be present so that
			// the combined cert+PSK SSL context is built and TLSCipherAll is actually applied.
			self::COMPONENT_AGENT => [
				'Hostname'             => 'enc_agent',
				'ServerActive'         => '127.0.0.1',
				'DebugLevel'           => 4,
				'TLSConnect'           => 'cert',
				'TLSAccept'            => 'cert,psk',
				'TLSCAFile'            => $c['ca_crt'],
				'TLSCertFile'          => $c['agent_crt'],
				'TLSKeyFile'           => $c['agent_key'],
				'TLSServerCertIssuer'  => 'CN=ZabbixTestCA',
				'TLSServerCertSubject' => 'CN=zabbix_server',
				'TLSPSKIdentity'       => self::PSK_IDENTITY,
				'TLSPSKFile'           => self::$pskFile,
				'TLSCipherAll'         => self::CIPHER_ALL_TLS12_AES256,
				'TLSCipherAll13'       => self::CIPHER_ALL_TLS13_AES256
			]
		];
	}

	/**
	 * TLSCipherAll / TLSCipherAll13 restricted to AES-256 suites; verifies
	 * cert auth still succeeds when the cipher list is tightened after an upgrade.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider configProviderCipherAllAES256
	 * @hosts enc_agent
	 * @backup hosts
	 */
	public function testEncryption_cipherAllAES256(): void {
		$this->updateHostCertTLS('enc_agent', 'CN=ZabbixTestCA', 'CN=zabbix_agent');

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, [
			'enabling Zabbix agent checks on host "enc_agent": interface became available',
			'resuming Zabbix agent checks on host "enc_agent": connection restored'
		]);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_tls_connect():SUCCEED (established TLS');

		$data = $this->callUntilDataIsPresent('history.get', [
			'itemids' => self::$itemids['enc_agent:agent.ping'],
			'history' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertNotEmpty($data['result'],
			'No data collected with AES-256 TLSCipherAll / TLSCipherAll13');
	}

	// =========================================================================
	// Test 12 – Cipher mismatch: non-overlapping lists → handshake failure
	// =========================================================================

	/**
	 * @return array
	 */
	public function configProviderCipherMismatch(): array {
		$c = self::generateCertificates();
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel'        => 4,
				'UnreachablePeriod' => 5,
				'UnavailableDelay'  => 5,
				'UnreachableDelay'  => 1,
				'TLSCAFile'         => $c['ca_crt'],
				'TLSCertFile'       => $c['server_crt'],
				'TLSKeyFile'        => $c['server_key'],
				'TLSCipherCert'     => self::CIPHER_MISMATCH_SERVER_TLS12,
				'TLSCipherCert13'   => self::CIPHER_MISMATCH_SERVER_TLS13
			],
			self::COMPONENT_AGENT => [
				'Hostname'             => 'enc_agent',
				'ServerActive'         => '127.0.0.1',
				'DebugLevel'           => 4,
				'TLSConnect'           => 'cert',
				'TLSAccept'            => 'cert',
				'TLSCAFile'            => $c['ca_crt'],
				'TLSCertFile'          => $c['agent_crt'],
				'TLSKeyFile'           => $c['agent_key'],
				'TLSServerCertIssuer'  => 'CN=ZabbixTestCA',
				'TLSServerCertSubject' => 'CN=zabbix_server',
				'TLSCipherCert'        => self::CIPHER_MISMATCH_AGENT_TLS12,
				'TLSCipherCert13'      => self::CIPHER_MISMATCH_AGENT_TLS13
			]
		];
	}

	/**
	 * Server locked to AES-256 and agent locked to AES-128 cipher suites:
	 * no cipher is shared across TLS 1.2 or TLS 1.3, so every handshake is
	 * rejected and no data is collected.  Models a broken upgrade where the
	 * server and agent cipher configurations are changed independently.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider configProviderCipherMismatch
	 * @hosts enc_agent
	 * @backup hosts
	 */
	public function testEncryption_cipherMismatch(): void {
		$start_time = time();
		$this->updateHostCertTLS('enc_agent', 'CN=ZabbixTestCA', 'CN=zabbix_agent');

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, [
			'no shared cipher',
			'no cipher can be selected',
			'handshake failure',
			'SSL_accept() failed',
			'failed to accept an incoming connection',
			'zbx_tls_accept(): handshake'
		], true, 30);

		$data = $this->call('history.get', [
			'itemids'   => self::$itemids['enc_agent:agent.ping'],
			'history'   => ITEM_VALUE_TYPE_UINT64,
			'time_from' => $start_time
		]);
		$this->assertEmpty($data['result'],
			'Data was collected despite non-overlapping cipher lists on server and agent');
	}

	// =========================================================================
	// Test 13 – libgnutls: TLS 1.2 + TLS 1.3 combined priority for cert
	// =========================================================================

	/**
	 * @return array
	 */
	public function configProviderGnutlsCertTLS13(): array {
		$c = self::generateCertificates();
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel'        => 4,
				'UnreachablePeriod' => 5,
				'UnavailableDelay'  => 5,
				'UnreachableDelay'  => 1,
				'TLSCAFile'         => $c['ca_crt'],
				'TLSCertFile'       => $c['server_crt'],
				'TLSKeyFile'        => $c['server_key'],
				'TLSCipherCert'     => self::GNUTLS_CIPHER_CERT_TLS12_TLS13,
				'TLSCipherAll'      => self::GNUTLS_CIPHER_CERT_TLS12_TLS13
			],
			self::COMPONENT_AGENT => [
				'Hostname'             => 'enc_agent',
				'ServerActive'         => '127.0.0.1',
				'DebugLevel'           => 4,
				'TLSConnect'           => 'cert',
				'TLSAccept'            => 'cert',
				'TLSCAFile'            => $c['ca_crt'],
				'TLSCertFile'          => $c['agent_crt'],
				'TLSKeyFile'           => $c['agent_key'],
				'TLSServerCertIssuer'  => 'CN=ZabbixTestCA',
				'TLSServerCertSubject' => 'CN=zabbix_server',
				'TLSCipherCert'        => self::GNUTLS_CIPHER_CERT_TLS12_TLS13,
				'TLSCipherAll'         => self::GNUTLS_CIPHER_CERT_TLS12_TLS13
			]
		];
	}

	/**
	 * GnuTLS priority string that enables both TLS 1.2 and TLS 1.3 with AES-256
	 * and AES-128; covers the common upgrade path of adding TLS 1.3 support to a
	 * TLS 1.2-only deployment without dropping existing cipher compatibility.
	 * Automatically skipped when the server binary was compiled with OpenSSL.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider configProviderGnutlsCertTLS13
	 * @hosts enc_agent
	 * @backup hosts
	 */
	public function testEncryption_gnutlsCertTLS13(): void {
		if ('gnutls' !== $this->detectTLSLibrary()) {
			$this->markTestSkipped('Server not compiled with GnuTLS; skipping GnuTLS TLS 1.2+1.3 test.');
		}

		$this->updateHostCertTLS('enc_agent', 'CN=ZabbixTestCA', 'CN=zabbix_agent');

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, [
			'enabling Zabbix agent checks on host "enc_agent": interface became available',
			'resuming Zabbix agent checks on host "enc_agent": connection restored'
		]);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_tls_connect():SUCCEED (established TLS');

		$data = $this->callUntilDataIsPresent('history.get', [
			'itemids' => self::$itemids['enc_agent:agent.ping'],
			'history' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertNotEmpty($data['result'],
			'No data collected with GnuTLS TLS 1.2+1.3 combined priority string');
	}

	// =========================================================================
	// Test 14 – libgnutls: PSK priority string (AES-128)
	// =========================================================================

	/**
	 * @return array
	 */
	public function configProviderGnutlsPSK(): array {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel'        => 4,
				'UnreachablePeriod' => 5,
				'UnavailableDelay'  => 5,
				'UnreachableDelay'  => 1,
				'TLSCipherPSK'      => self::GNUTLS_CIPHER_PSK,
				'TLSCipherAll'      => self::GNUTLS_CIPHER_PSK
			],
			self::COMPONENT_AGENT => [
				'Hostname'       => 'enc_agent',
				'ServerActive'   => '127.0.0.1',
				'DebugLevel'     => 4,
				'TLSConnect'     => 'psk',
				'TLSAccept'      => 'psk',
				'TLSPSKIdentity' => self::PSK_IDENTITY,
				'TLSPSKFile'     => self::$pskFile,
				'TLSCipherPSK'   => self::GNUTLS_CIPHER_PSK,
				'TLSCipherAll'   => self::GNUTLS_CIPHER_PSK
			]
		];
	}

	/**
	 * PSK data collection using a GnuTLS priority string; complements test 8
	 * which only covered certificate auth with GnuTLS priority strings.
	 * Automatically skipped when the server binary was compiled with OpenSSL.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider configProviderGnutlsPSK
	 * @hosts enc_agent
	 * @backup hosts
	 */
	public function testEncryption_gnutlsPSK(): void {
		if ('gnutls' !== $this->detectTLSLibrary()) {
			$this->markTestSkipped('Server not compiled with GnuTLS; skipping GnuTLS PSK test.');
		}

		$this->updateHostPSKTLS('enc_agent');

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, [
			'enabling Zabbix agent checks on host "enc_agent": interface became available',
			'resuming Zabbix agent checks on host "enc_agent": connection restored'
		]);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_tls_connect():SUCCEED (established TLS');

		$data = $this->callUntilDataIsPresent('history.get', [
			'itemids' => self::$itemids['enc_agent:agent.ping'],
			'history' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertNotEmpty($data['result'],
			'No data collected with GnuTLS PSK AES-128 priority string');
	}

	// =========================================================================
	// Test 15 – libgnutls: AES-256 priority string for cert (stronger cipher)
	// =========================================================================

	/**
	 * @return array
	 */
	public function configProviderGnutlsCertAES256(): array {
		$c = self::generateCertificates();
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel'        => 4,
				'UnreachablePeriod' => 5,
				'UnavailableDelay'  => 5,
				'UnreachableDelay'  => 1,
				'TLSCAFile'         => $c['ca_crt'],
				'TLSCertFile'       => $c['server_crt'],
				'TLSKeyFile'        => $c['server_key'],
				'TLSCipherCert'     => self::GNUTLS_CIPHER_CERT_AES256,
				'TLSCipherAll'      => self::GNUTLS_CIPHER_CERT_AES256
			],
			self::COMPONENT_AGENT => [
				'Hostname'             => 'enc_agent',
				'ServerActive'         => '127.0.0.1',
				'DebugLevel'           => 4,
				'TLSConnect'           => 'cert',
				'TLSAccept'            => 'cert',
				'TLSCAFile'            => $c['ca_crt'],
				'TLSCertFile'          => $c['agent_crt'],
				'TLSKeyFile'           => $c['agent_key'],
				'TLSServerCertIssuer'  => 'CN=ZabbixTestCA',
				'TLSServerCertSubject' => 'CN=zabbix_server',
				'TLSCipherCert'        => self::GNUTLS_CIPHER_CERT_AES256,
				'TLSCipherAll'         => self::GNUTLS_CIPHER_CERT_AES256
			]
		];
	}

	/**
	 * GnuTLS certificate auth restricted to AES-256-GCM only; models the
	 * stronger-cipher preference of GnuTLS 3.7+ deployments where AES-128
	 * suites have been administratively removed.
	 * Automatically skipped when the server binary was compiled with OpenSSL.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider configProviderGnutlsCertAES256
	 * @hosts enc_agent
	 * @backup hosts
	 */
	public function testEncryption_gnutlsCertAES256(): void {
		if ('gnutls' !== $this->detectTLSLibrary()) {
			$this->markTestSkipped('Server not compiled with GnuTLS; skipping GnuTLS AES-256 test.');
		}

		$this->updateHostCertTLS('enc_agent', 'CN=ZabbixTestCA', 'CN=zabbix_agent');

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, [
			'enabling Zabbix agent checks on host "enc_agent": interface became available',
			'resuming Zabbix agent checks on host "enc_agent": connection restored'
		]);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_tls_connect():SUCCEED (established TLS');

		$data = $this->callUntilDataIsPresent('history.get', [
			'itemids' => self::$itemids['enc_agent:agent.ping'],
			'history' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertNotEmpty($data['result'],
			'No data collected with GnuTLS AES-256 priority string');
	}
}

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
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_active_hb
 * @onBefore clearData
 * @onAfter clearData
 */
class testActiveCheckHeartbeat extends CIntegrationTest {

	const HOST_NAME = 'test_active_hb';
	const HOST_NAME_PSK = 'test_active_hb_psk';
	const HOST_NAME_HOSTNAME_ITEM = 'test_active_hb_hostnameitem';
	const HOST_NAME_MULTI_1 = 'test_active_hb_multi1';
	const HOST_NAME_MULTI_2 = 'test_active_hb_multi2';
	const HOST_NAME_MISSED_HEARTBEAT = 'test_active_hb_missed';
	const PROXY_NAME = 'heartbeat_test_proxy';

	const PSK_IDENTITY = 'test_heartbeat_psk';
	const PSK_KEY = '53E79a76526473c982eab32473e9e1643ead36cc5cfe693a7955b1b0527ec7fe';

	private static $hostid;
	private static $hostid_psk;
	private static $psk_file;

	// Hosts/proxies created by an individual test method, cleaned up via clearExtraData().
	private $extra_hostids = [];
	private $extra_proxyids = [];

	/**
	 * Component configuration provider for server-only tests.
	 *
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20
			]
		];
	}

	/**
	 * Component configuration provider for server with PSK agent.
	 *
	 * @return array
	 */
	public function serverPSKConfigProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20
			],
			self::COMPONENT_AGENT => [
				'Hostname' => self::HOST_NAME_PSK,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'HeartbeatFrequency' => 5,
				'TLSConnect' => 'psk',
				'TLSAccept' => 'psk',
				'TLSPSKIdentity' => self::PSK_IDENTITY,
				'TLSPSKFile' => self::$psk_file
			]
		];
	}

	/**
	 * Component configuration provider for active proxy with plaintext agent.
	 *
	 * @return array
	 */
	public function proxyNoEncryptionConfigProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20
			],
			self::COMPONENT_PROXY => [
				'ProxyMode' => PROXY_OPERATING_MODE_ACTIVE,
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'Hostname' => self::PROXY_NAME,
				'Server' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort')
			],
			self::COMPONENT_AGENT => [
				'Hostname' => 'test_active_hb_proxy',
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_PROXY, 'ListenPort'),
				'HeartbeatFrequency' => 5
			]
		];
	}

	/**
	 * Component configuration provider for server with PSK agent claiming a plaintext-only host.
	 * The agent uses the same PSK identity as HOST_NAME_PSK so the TLS handshake succeeds, but
	 * the heartbeat names HOST_NAME whose tls_accept = unencrypted-only, triggering rejection.
	 *
	 * @return array
	 */
	public function serverPSKForPlaintextConfigProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20
			],
			self::COMPONENT_AGENT => [
				'Hostname' => self::HOST_NAME,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'HeartbeatFrequency' => 5,
				'TLSConnect' => 'psk',
				'TLSAccept' => 'psk',
				'TLSPSKIdentity' => self::PSK_IDENTITY,
				'TLSPSKFile' => self::$psk_file
			]
		];
	}

	/**
	 * Component configuration provider for active proxy with PSK agent.
	 *
	 * @return array
	 */
	public function proxyPSKConfigProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20
			],
			self::COMPONENT_PROXY => [
				'ProxyMode' => PROXY_OPERATING_MODE_ACTIVE,
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'Hostname' => self::PROXY_NAME,
				'Server' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'TLSAccept' => 'psk,unencrypted'
			],
			self::COMPONENT_AGENT => [
				'Hostname' => 'test_active_hb_proxy_psk',
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_PROXY, 'ListenPort'),
				'HeartbeatFrequency' => 5,
				'TLSConnect' => 'psk',
				'TLSAccept' => 'psk',
				'TLSPSKIdentity' => self::PSK_IDENTITY,
				'TLSPSKFile' => self::$psk_file
			]
		];
	}

	/**
	 * Component configuration provider for active proxy with PSK agent claiming a plaintext-only host.
	 * The proxy accepts both psk and unencrypted; the PSK identity is resolved via the PSK host
	 * created in the test body, so the TLS handshake succeeds while the heartbeat host is plaintext-only.
	 *
	 * @return array
	 */
	public function proxyPSKForPlaintextConfigProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20
			],
			self::COMPONENT_PROXY => [
				'ProxyMode' => PROXY_OPERATING_MODE_ACTIVE,
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'Hostname' => self::PROXY_NAME,
				'Server' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'TLSAccept' => 'psk,unencrypted'
			],
			self::COMPONENT_AGENT => [
				'Hostname' => 'test_active_hb_proxy',
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_PROXY, 'ListenPort'),
				'HeartbeatFrequency' => 5,
				'TLSConnect' => 'psk',
				'TLSAccept' => 'psk',
				'TLSPSKIdentity' => self::PSK_IDENTITY,
				'TLSPSKFile' => self::$psk_file
			]
		];
	}

	/**
	 * Component configuration provider for agent with neither "Hostname" nor "HostnameItem" defined.
	 * "Hostname" is set to an empty array so the template's default active value gets commented out
	 * instead of replaced, leaving the agent to fall back to the built-in "system.hostname" item.
	 *
	 * @return array
	 */
	public function serverNoHostnameConfigProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20
			],
			self::COMPONENT_AGENT => [
				'Hostname' => [],
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'HeartbeatFrequency' => 5
			]
		];
	}

	/**
	 * Component configuration provider for agent resolving its hostname via "HostnameItem" instead
	 * of "Hostname". Uses "system.run[]" rather than a UserParameter, since HostnameItem is resolved
	 * before user parameters are loaded and does not support them.
	 *
	 * @return array
	 */
	public function serverHostnameItemConfigProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20
			],
			self::COMPONENT_AGENT => [
				'Hostname' => [],
				'HostnameItem' => 'system.run[echo '.self::HOST_NAME_HOSTNAME_ITEM.']',
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'HeartbeatFrequency' => 5
			]
		];
	}

	/**
	 * Component configuration provider for agent with a comma-separated "Hostname" value, which
	 * makes it run one active-check worker (and heartbeat) per hostname.
	 *
	 * @return array
	 */
	public function serverMultipleHostnamesConfigProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20
			],
			self::COMPONENT_AGENT => [
				'Hostname' => self::HOST_NAME_MULTI_1.','.self::HOST_NAME_MULTI_2,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'HeartbeatFrequency' => 5
			]
		];
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		self::$psk_file = '/tmp/test_heartbeat_psk.txt'.microtime();

		if (file_put_contents(self::$psk_file, self::PSK_KEY) === false) {
			throw new Exception('Cannot create PSK file: '.self::$psk_file);
		}

		$response = $this->call('host.create', [
			'host' => self::HOST_NAME,
			'interfaces' => [
				[
					'type' => INTERFACE_TYPE_AGENT,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => 10050
				]
			],
			'groups' => [['groupid' => 4]],
			'status' => HOST_STATUS_MONITORED
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		$response = $this->call('host.create', [
			'host' => self::HOST_NAME_PSK,
			'interfaces' => [
				[
					'type' => INTERFACE_TYPE_AGENT,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => 10050
				]
			],
			'groups' => [['groupid' => 4]],
			'status' => HOST_STATUS_MONITORED,
			'tls_accept' => HOST_ENCRYPTION_PSK,
			'tls_psk_identity' => self::PSK_IDENTITY,
			'tls_psk' => self::PSK_KEY
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid_psk = $response['result']['hostids'][0];

		return true;
	}

	/**
	 * Delete all data created by this test suite.
	 */
	public static function clearData(): void {
		if (self::$hostid !== null) {
			CDataHelper::call('host.delete', [self::$hostid]);
			self::$hostid = null;
		}

		if (self::$hostid_psk !== null) {
			CDataHelper::call('host.delete', [self::$hostid_psk]);
			self::$hostid_psk = null;
		}

		if (self::$psk_file !== null && file_exists(self::$psk_file)) {
			unlink(self::$psk_file);
		}
	}

	/**
	 * Delete hosts/proxies created by an individual test method.
	 */
	public function clearExtraData(): void {
		if ($this->extra_hostids) {
			CDataHelper::call('host.delete', $this->extra_hostids);
			$this->extra_hostids = [];
		}

		if ($this->extra_proxyids) {
			CDataHelper::call('proxy.delete', $this->extra_proxyids);
			$this->extra_proxyids = [];
		}
	}

	private function sendHeartbeatRequest($component, $host, $heartbeat_freq, array $extra = []) {
		$this->getClient($component)->sendHeartbeat($host, $heartbeat_freq, $extra);
	}

	/**
	 * Create a plaintext-only monitored host and return its hostid.
	 *
	 * @param string $name    host name
	 *
	 * @return string
	 */
	private function createHost($name) {
		$response = $this->call('host.create', [
			'host' => $name,
			'interfaces' => [
				[
					'type' => INTERFACE_TYPE_AGENT,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => 10050
				]
			],
			'groups' => [['groupid' => 4]],
			'status' => HOST_STATUS_MONITORED
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);

		$hostid = $response['result']['hostids'][0];
		$this->extra_hostids[] = $hostid;

		return $hostid;
	}

	private function createProxyWithHosts(array $host_defs) {
		$hostids = [];
		foreach ($host_defs as $def) {
			$response = $this->call('host.create', $def);
			$this->assertArrayHasKey('hostids', $response['result']);
			$hostids[] = $response['result']['hostids'][0];
		}

		$this->extra_hostids = array_merge($this->extra_hostids, $hostids);

		$hosts = [];
		foreach ($hostids as $hostid) {
			$hosts[] = ['hostid' => $hostid];
		}

		$response = $this->call('proxy.create', [
			'name' => self::PROXY_NAME,
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
			'hosts' => $hosts
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);
		$proxyid = $response['result']['proxyids'][0];
		$this->extra_proxyids[] = $proxyid;

		return [$proxyid, $hostids];
	}

	private function syncProxyConfig() {
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->reloadConfigurationCache(self::COMPONENT_PROXY);
		self::waitForLogLineToBePresent(self::COMPONENT_PROXY, 'received configuration data from server', false, 15, 1);
	}

	/**
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testActiveCheckHeartbeat_invalidHostname() {
		$this->sendHeartbeatRequest(self::COMPONENT_SERVER, 'invalid host!', 60);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'invalid host name "invalid host!" for heartbeat', false, 5, 1);
	}

	/**
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testActiveCheckHeartbeat_zeroFrequency() {
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->sendHeartbeatRequest(self::COMPONENT_SERVER, self::HOST_NAME, 0);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'received invalid heartbeat frequency "0"', false, 5, 1);
	}

	/**
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testActiveCheckHeartbeat_negativeFrequency() {
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->sendHeartbeatRequest(self::COMPONENT_SERVER, self::HOST_NAME, -1);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'received invalid heartbeat frequency "-1"', false, 5, 1);
	}

	/**
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testActiveCheckHeartbeat_frequencyTooLarge() {
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->sendHeartbeatRequest(self::COMPONENT_SERVER, self::HOST_NAME, 3601);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'received invalid heartbeat frequency "3601"', false, 5, 1);
	}

	/**
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testActiveCheckHeartbeat_overflowFrequency() {
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->sendHeartbeatRequest(self::COMPONENT_SERVER, self::HOST_NAME, PHP_INT_MAX);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'received invalid heartbeat frequency "'.PHP_INT_MAX.'"', false, 5, 1);
	}

	/**
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testActiveCheckHeartbeat_nonNumericFrequency() {
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->sendHeartbeatRequest(self::COMPONENT_SERVER, self::HOST_NAME, 'abc');

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'received invalid heartbeat frequency "abc"', false, 5, 1);
	}

	/**
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testActiveCheckHeartbeat_emojiFrequency() {
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->sendHeartbeatRequest(self::COMPONENT_SERVER, self::HOST_NAME, '😀');

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'received invalid heartbeat frequency "😀"', false, 5, 1);
	}

	/**
	 * A request padded well beyond 1024 bytes with an unknown field is processed normally.
	 * Verifies that the server does not reject or crash on unexpectedly large heartbeat packets.
	 *
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testActiveCheckHeartbeat_largePacket() {
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->sendHeartbeatRequest(self::COMPONENT_SERVER, self::HOST_NAME, 60, [
			'padding' => str_repeat('a', 10 * 1024)
		]);

		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => ['active_available'],
			'hostids' => [self::$hostid],
			'filter' => ['active_available' => INTERFACE_AVAILABLE_TRUE]
		], 10, 2);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(INTERFACE_AVAILABLE_TRUE, $response['result'][0]['active_available']);
	}

	/**
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testActiveCheckHeartbeat_serverPlaintextAccepted() {
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->sendHeartbeatRequest(self::COMPONENT_SERVER, self::HOST_NAME, 60);

		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => ['active_available'],
			'hostids' => [self::$hostid],
			'filter' => ['active_available' => INTERFACE_AVAILABLE_TRUE]
		], 10, 2);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(INTERFACE_AVAILABLE_TRUE, $response['result'][0]['active_available']);
	}

	/**
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testActiveCheckHeartbeat_serverUnauthenticatedRejected() {
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->sendHeartbeatRequest(self::COMPONENT_SERVER, self::HOST_NAME_PSK, 60);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'cannot process heartbeat from host "'.self::HOST_NAME_PSK.
				'": connection of type "unencrypted" is not allowed for host "'.self::HOST_NAME_PSK.'"',
			false, 5, 1);
	}

	/**
	 * @required-components server, agent
	 * @configurationDataProvider serverPSKForPlaintextConfigProvider
	 */
	public function testActiveCheckHeartbeat_serverEncryptedRejected() {
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'cannot process heartbeat from host "'.self::HOST_NAME.
				'": connection of type "TLS with PSK" is not allowed for host "'.self::HOST_NAME.'"',
			false, 15, 1);
	}

	/**
	 * @required-components server, agent
	 * @configurationDataProvider serverPSKConfigProvider
	 */
	public function testActiveCheckHeartbeat_serverPSKAccepted() {
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => ['active_available'],
			'hostids' => [self::$hostid_psk],
			'filter' => ['active_available' => INTERFACE_AVAILABLE_TRUE]
		], 15, 2);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(INTERFACE_AVAILABLE_TRUE, $response['result'][0]['active_available']);
	}

	/**
	 * @required-components server, proxy
	 * @configurationDataProvider proxyNoEncryptionConfigProvider
	 * @onAfter clearExtraData
	 */
	public function testActiveCheckHeartbeat_proxyUnauthenticatedRejected() {
		[$proxyid, $hostids] = $this->createProxyWithHosts([[
			'host' => 'test_active_hb_proxy_psk',
			'interfaces' => [
				[
					'type' => INTERFACE_TYPE_AGENT,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => 10050
				]
			],
			'groups' => [['groupid' => 4]],
			'status' => HOST_STATUS_MONITORED,
			'tls_accept' => HOST_ENCRYPTION_PSK,
			'tls_psk_identity' => self::PSK_IDENTITY,
			'tls_psk' => self::PSK_KEY
		]]);

		$this->syncProxyConfig();
		$this->sendHeartbeatRequest(self::COMPONENT_PROXY, 'test_active_hb_proxy_psk', 60);

		self::waitForLogLineToBePresent(self::COMPONENT_PROXY,
			'cannot process heartbeat from host "test_active_hb_proxy_psk": '.
				'connection of type "unencrypted" is not allowed for host "test_active_hb_proxy_psk"',
			false, 5, 1);
	}

	/**
	 * PSK-encrypted connection arrives at proxy for a host whose tls_accept = unencrypted-only.
	 * A PSK host is also created so the proxy can complete the TLS handshake using its PSK identity;
	 * the rejection fires on the heartbeat host (plaintext-only), not on the TLS layer.
	 *
	 * @required-components server, proxy, agent
	 * @configurationDataProvider proxyPSKForPlaintextConfigProvider
	 * @onAfter clearExtraData
	 */
	public function testActiveCheckHeartbeat_proxyEncryptedRejected() {
		$this->createProxyWithHosts([
			[
				'host' => 'test_active_hb_proxy',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => 1,
						'useip' => 1,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => 10050
					]
				],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED
			],
			[
				'host' => 'test_active_hb_proxy_psk',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => 1,
						'useip' => 1,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => 10050
					]
				],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED,
				'tls_accept' => HOST_ENCRYPTION_PSK,
				'tls_psk_identity' => self::PSK_IDENTITY,
				'tls_psk' => self::PSK_KEY
			]
		]);

		$this->syncProxyConfig();

		self::waitForLogLineToBePresent(self::COMPONENT_PROXY,
			'cannot process heartbeat from host "test_active_hb_proxy": '.
				'connection of type "TLS with PSK" is not allowed for host "test_active_hb_proxy"',
			false, 15, 1);
	}

	/**
	 * @required-components server, proxy, agent
	 * @configurationDataProvider proxyNoEncryptionConfigProvider
	 * @onAfter clearExtraData
	 */
	public function testActiveCheckHeartbeat_proxyPlaintextAccepted() {
		[$proxyid, $hostids] = $this->createProxyWithHosts([[
			'host' => 'test_active_hb_proxy',
			'interfaces' => [
				[
					'type' => INTERFACE_TYPE_AGENT,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => 10050
				]
			],
			'groups' => [['groupid' => 4]],
			'status' => HOST_STATUS_MONITORED
		]]);

		$this->syncProxyConfig();

		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => ['active_available'],
			'hostids' => $hostids,
			'filter' => ['active_available' => INTERFACE_AVAILABLE_TRUE]
		], 20, 3);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(INTERFACE_AVAILABLE_TRUE, $response['result'][0]['active_available']);
	}

	/**
	 * @required-components server, proxy, agent
	 * @configurationDataProvider proxyPSKConfigProvider
	 * @onAfter clearExtraData
	 */
	public function testActiveCheckHeartbeat_proxyPSKAccepted() {
		[$proxyid, $hostids] = $this->createProxyWithHosts([[
			'host' => 'test_active_hb_proxy_psk',
			'interfaces' => [
				[
					'type' => INTERFACE_TYPE_AGENT,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => 10050
				]
			],
			'groups' => [['groupid' => 4]],
			'status' => HOST_STATUS_MONITORED,
			'tls_accept' => HOST_ENCRYPTION_PSK,
			'tls_psk_identity' => self::PSK_IDENTITY,
			'tls_psk' => self::PSK_KEY
		]]);

		$this->syncProxyConfig();

		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => ['active_available'],
			'hostids' => $hostids,
			'filter' => ['active_available' => INTERFACE_AVAILABLE_TRUE]
		], 20, 3);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(INTERFACE_AVAILABLE_TRUE, $response['result'][0]['active_available']);
	}

	/**
	 * An agent with neither "Hostname" nor "HostnameItem" defined falls back to the built-in
	 * "system.hostname" item, so its heartbeats are attributed to the machine's own hostname.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider serverNoHostnameConfigProvider
	 * @onAfter clearExtraData
	 */
	public function testActiveCheckHeartbeat_noHostnameConfigured() {
		$hostid = $this->createHost(trim(gethostname()));

		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => ['active_available'],
			'hostids' => [$hostid],
			'filter' => ['active_available' => INTERFACE_AVAILABLE_TRUE]
		], 20, 2);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(INTERFACE_AVAILABLE_TRUE, $response['result'][0]['active_available']);
	}

	/**
	 * An agent resolves its active-check hostname from "HostnameItem" when "Hostname" is not defined.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider serverHostnameItemConfigProvider
	 * @onAfter clearExtraData
	 */
	public function testActiveCheckHeartbeat_hostnameItem() {
		$hostid = $this->createHost(self::HOST_NAME_HOSTNAME_ITEM);

		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => ['active_available'],
			'hostids' => [$hostid],
			'filter' => ['active_available' => INTERFACE_AVAILABLE_TRUE]
		], 20, 2);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(INTERFACE_AVAILABLE_TRUE, $response['result'][0]['active_available']);
	}

	/**
	 * A comma-separated "Hostname" value makes the agent run one active-check worker per hostname,
	 * each sending its own heartbeat, so every listed host becomes available independently.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider serverMultipleHostnamesConfigProvider
	 * @onAfter clearExtraData
	 */
	public function testActiveCheckHeartbeat_multipleHostnames() {
		$hostid1 = $this->createHost(self::HOST_NAME_MULTI_1);
		$hostid2 = $this->createHost(self::HOST_NAME_MULTI_2);

		$this->callUntilCountIsPresent('host.get', [
			'hostids' => [$hostid1, $hostid2],
			'filter' => ['active_available' => INTERFACE_AVAILABLE_TRUE]
		], 2, 20, 2);
	}

	/**
	 * If a host stops sending heartbeats (e.g. the agent becomes unreachable or is stopped), the
	 * server must mark it as unavailable once the heartbeat frequency window (2x) elapses without
	 * a new one, rather than continuing to report it as available.
	 *
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 * @onAfter clearExtraData
	 */
	public function testActiveCheckHeartbeat_noHeartbeatReceivedMarksUnavailable() {
		$hostid = $this->createHost(self::HOST_NAME_MISSED_HEARTBEAT);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->sendHeartbeatRequest(self::COMPONENT_SERVER, self::HOST_NAME_MISSED_HEARTBEAT, 5);

		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => ['active_available'],
			'hostids' => [$hostid],
			'filter' => ['active_available' => INTERFACE_AVAILABLE_TRUE]
		], 10, 2);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(INTERFACE_AVAILABLE_TRUE, $response['result'][0]['active_available']);

		// No further heartbeats are sent; availability must flip to false after the 2x frequency window.
		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => ['active_available'],
			'hostids' => [$hostid],
			'filter' => ['active_available' => INTERFACE_AVAILABLE_FALSE]
		], 20, 2);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(INTERFACE_AVAILABLE_FALSE, $response['result'][0]['active_available']);
	}
}

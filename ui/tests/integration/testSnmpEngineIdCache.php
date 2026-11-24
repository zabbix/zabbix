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
 * Test suite for SNMPv3 engine ID caching in asynchronous SNMP poller.
 *
 * Test suite starts two Net-SNMP agents (snmpd must be installed, root is not required) on the same address but
 * different ports, each with its own engine ID and sysDescr, so that two devices with different engine IDs share
 * the same address. Both agents have SNMPv3 user "simulator" (authPriv, MD5 "auctoritas", DES "privatus").
 *
 * @required-components server
 * @suite-components-reuse true
 * @configurationDataProvider serverConfigurationProvider
 * @onAfter clearData
 */
class testSnmpEngineIdCache extends CIntegrationTest {
	private const HOST_NAME = 'snmp_engineid_cache_host';
	private const PORTS_HOST_NAME = 'snmp_engineid_cache_ports_host';
	private const HOST_GROUP_ID = 4;

	private const SNMP_IP = '127.0.0.1';
	private const SNMP_DNS = 'localhost';
	private const SNMP_PORT = '1161';

	/* second agent on the same address, has its own engine ID */
	private const SECOND_SNMP_PORT = '1162';

	/* engine ID and sysDescr of each agent, sysDescr tells values of the agents apart */
	private const SNMPD = [
		self::SNMP_PORT => [
			'engineid' => '0x80001f880400000001',
			'descr' => 'Zabbix engine ID cache test agent 1'
		],
		self::SECOND_SNMP_PORT => [
			'engineid' => '0x80001f880400000002',
			'descr' => 'Zabbix engine ID cache test agent 2'
		]
	];
	private const SNMPD_DIR = 'snmpd_engineid_cache';
	private const SNMPD_START_TIMEOUT = 10;

	/* number of alternating cached polls of both ports to check that engine IDs do not interfere */
	private const PORT_POLL_ROUNDS = 3;

	private const SNMP_OID = 'get[.1.3.6.1.2.1.1.1.0]';
	private const SAME_ADDRESS_ITEM_COUNT = 64;

	/* RFC 5737 TEST-NET-1 address and RFC 6761 reserved domain, both guaranteed to be unreachable */
	private const INVALID_IP = '192.0.2.1';
	private const INVALID_DNS = 'snmp-engineid-cache.invalid';

	private const SNMP_DETAILS = [
		'version' => SNMP_V3,
		'bulk' => SNMP_BULK_ENABLED,
		'contextname' => '',
		'securityname' => 'simulator',
		'securitylevel' => ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV,
		'authprotocol' => ITEM_SNMPV3_AUTHPROTOCOL_MD5,
		'authpassphrase' => 'auctoritas',
		'privprotocol' => ITEM_SNMPV3_PRIVPROTOCOL_DES,
		'privpassphrase' => 'privatus',
		'retries' => 2
	];

	private static string $hostid;
	private static ?string $ports_hostid = null;
	private static string $interfaceid;
	private static string $itemid;
	private static bool $snmp_poller_debug = false;

	public function serverConfigurationProvider(): array {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 3,
				'LogFileSize' => 0,
				'UnreachablePeriod' => 1,
				'UnavailableDelay' => 1,
				'UnreachableDelay' => 1,
				/* single SNMP poller, so that all engine IDs are in one cache */
				'StartSNMPPollers' => 1
			]
		];
	}

	private static function getSnmpdDir(): string {
		return PHPUNIT_COMPONENT_DIR.self::SNMPD_DIR;
	}

	private static function getSnmpdBinary(): ?string {
		foreach (['/usr/sbin/snmpd', '/usr/local/sbin/snmpd'] as $path) {
			if (is_executable($path)) {
				return $path;
			}
		}

		$path = trim((string) shell_exec('command -v snmpd 2>/dev/null'));

		return $path !== '' ? $path : null;
	}

	/**
	 * Start both SNMP agents from scratch: without persistent data of previous runs.
	 */
	private function snmpdStart(): void {
		$binary = self::getSnmpdBinary();
		$this->assertNotNull($binary, 'snmpd is not installed');

		self::snmpdStop();

		foreach (self::SNMPD as $port => $agent) {
			$dir = self::getSnmpdDir().'/'.$port;
			/* remove only agent directory of this test suite, never a symbolic link */
			if (strpos($dir, self::getSnmpdDir().'/') === 0 && is_dir($dir) && !is_link($dir)) {
				shell_exec('rm -rf '.escapeshellarg($dir));
			}
			$this->assertTrue(mkdir($dir.'/persist', 0700, true), 'Cannot create directory '.$dir);

			file_put_contents($dir.'/snmpd.conf', implode("\n", [
				'[snmp] persistentDir '.$dir.'/persist',
				'agentaddress udp:'.self::SNMP_IP.':'.$port.',udp6:[::1]:'.$port,
				'exactEngineID '.$agent['engineid'],
				'createUser '.self::SNMP_DETAILS['securityname'].' MD5 "'.self::SNMP_DETAILS['authpassphrase'].
						'" DES "'.self::SNMP_DETAILS['privpassphrase'].'"',
				'rouser '.self::SNMP_DETAILS['securityname'].' priv',
				'sysDescr '.$agent['descr']
			])."\n");

			/* -C: do not read default configuration files, -I: do not load modules that require root */
			shell_exec('SNMP_PERSISTENT_DIR='.escapeshellarg($dir.'/persist').' '.escapeshellarg($binary).
				' -C -c '.escapeshellarg($dir.'/snmpd.conf').' -p '.escapeshellarg($dir.'/snmpd.pid').
				' -Lf '.escapeshellarg($dir.'/snmpd.log').' -I -smux,mteTrigger,mteTriggerConf > /dev/null 2>&1'
			);
		}

		foreach (array_keys(self::SNMPD) as $port) {
			$log = self::getSnmpdDir().'/'.$port.'/snmpd.log';

			for ($i = 0; $i < self::SNMPD_START_TIMEOUT * 10; $i++) {
				if (is_readable($log) && strpos(file_get_contents($log), 'NET-SNMP version') !== false) {
					continue 2;
				}

				usleep(100000);
			}

			$this->fail('snmpd on port '.$port.' did not start, see '.$log);
		}
	}

	/**
	 * Stop only SNMP agents started by this test suite.
	 */
	private static function snmpdStop(): void {
		foreach (array_keys(self::SNMPD) as $port) {
			$pid_file = self::getSnmpdDir().'/'.$port.'/snmpd.pid';

			if (!is_readable($pid_file)) {
				continue;
			}

			$pid = trim((string) file_get_contents($pid_file));

			if (ctype_digit($pid)) {
				posix_kill((int) $pid, SIGTERM);
			}

			unlink($pid_file);
		}
	}

	public function prepareData(): bool {
		$this->snmpdStart();

		$response = $this->call('host.create', [
			'host' => self::HOST_NAME,
			'interfaces' => [self::getSnmpInterface()],
			'groups' => [
				['groupid' => self::HOST_GROUP_ID]
			],
			'status' => HOST_STATUS_MONITORED
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$hostid = $response['result']['hostids'][0];

		$response = $this->call('host.get', [
			'output' => [],
			'selectInterfaces' => ['interfaceid'],
			'hostids' => self::$hostid
		]);
		self::$interfaceid = $response['result'][0]['interfaces'][0]['interfaceid'];

		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => 'SNMPv3 engine ID cache',
			'key_' => 'snmp.engineid.cache',
			'type' => ITEM_TYPE_SNMP,
			'snmp_oid' => self::SNMP_OID,
			'value_type' => ITEM_VALUE_TYPE_TEXT,
			'delay' => '1s',
			'timeout' => '1s',
			'interfaceid' => self::$interfaceid
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		self::$itemid = $response['result']['itemids'][0];

		return true;
	}

	public static function clearData(): void {
		self::snmpdStop();

		if (isset(self::$hostid)) {
			CDataHelper::call('host.delete', [self::$hostid]);
		}

		if (self::$ports_hostid !== null) {
			CDataHelper::call('host.delete', [self::$ports_hostid]);
		}
	}

	/**
	 * Increase log level to debug for SNMP poller only, once per server run.
	 */
	private function enableSnmpPollerDebugLog(): void {
		if (self::$snmp_poller_debug) {
			return;
		}

		self::skipLog(self::COMPONENT_SERVER);
		$this->executeRuntimeControlCommand(self::COMPONENT_SERVER, 'log_level_increase=snmp poller');
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'log level has been increased to 4 (debug)');

		self::$snmp_poller_debug = true;
	}

	private static function getSnmpInterface(array $fields = [], array $details = []): array {
		return array_merge([
			'type' => INTERFACE_TYPE_SNMP,
			'main' => INTERFACE_PRIMARY,
			'useip' => INTERFACE_USE_IP,
			'ip' => self::SNMP_IP,
			'dns' => '',
			'port' => self::SNMP_PORT,
			'details' => array_merge(self::SNMP_DETAILS, $details)
		], $fields);
	}

	private static function getSecondSnmpInterface(array $fields = []): array {
		return self::getSnmpInterface(array_merge(['port' => self::SECOND_SNMP_PORT], $fields));
	}

	/**
	 * Update SNMP interface and reload configuration cache.
	 *
	 * @return int  time before the update, to be used when looking for new values
	 */
	private function updateSnmpInterface(array $interface): int {
		$since = time();

		$response = $this->call('host.update', [
			'hostid' => self::$hostid,
			'interfaces' => [
				array_merge($interface, ['interfaceid' => self::$interfaceid])
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		/* only lines logged with the new configuration are of interest */
		self::clearLog(self::COMPONENT_SERVER);

		return $since;
	}

	/**
	 * Wait for a new item value and available item and interface.
	 *
	 * @param bool|null $from_second  true - latest value must come from the second agent, false - from the first
	 *                                one, null - any value
	 */
	private function waitForItemValue(string $itemid, int $since, string $description, ?bool $from_second = null):
			void {
		$this->callUntilDataIsPresent('history.get', [
			'output' => ['clock', 'value'],
			'itemids' => [$itemid],
			'history' => ITEM_VALUE_TYPE_TEXT,
			'time_from' => $since,
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'limit' => 1
		], null, null, static function (array $response) use ($from_second, $description) {
			if ($from_second === null) {
				return true;
			}

			$value = $response['result'][0]['value'];

			if (($value === self::SNMPD[self::SECOND_SNMP_PORT]['descr']) === $from_second) {
				return true;
			}

			return 'value "'.$value.'" is not from '.($from_second ? 'second agent' : 'first agent').' with '.
				$description;
		});

		$this->callUntilDataIsPresent('item.get', [
			'output' => ['state', 'error'],
			'itemids' => [$itemid],
			'selectInterfaces' => ['available', 'error']
		], null, null, static function (array $response) use ($description) {
			$item = $response['result'][0];

			if ((int) $item['state'] === ITEM_STATE_NORMAL
					&& (int) $item['interfaces'][0]['available'] === INTERFACE_AVAILABLE_TRUE) {
				return true;
			}

			return 'item or interface is not available with '.$description;
		});
	}

	/**
	 * Wait until the item becomes not supported or its interface gets an error.
	 *
	 * @return string  item or interface error
	 */
	private function waitForItemFailure(string $itemid, string $description): string {
		$response = $this->callUntilDataIsPresent('item.get', [
			'output' => ['state', 'error'],
			'itemids' => [$itemid],
			'selectInterfaces' => ['available', 'error']
		], null, null, static function (array $response) use ($description) {
			$item = $response['result'][0];

			if ((int) $item['state'] === ITEM_STATE_NOTSUPPORTED || $item['interfaces'][0]['error'] !== '') {
				return true;
			}

			return 'item still works with '.$description;
		});

		$item = $response['result'][0];

		return (int) $item['state'] === ITEM_STATE_NOTSUPPORTED ? $item['error'] : $item['interfaces'][0]['error'];
	}

	/**
	 * Wait until the item skips probing because engine ID is cached.
	 *
	 * Log is cleared after each configuration change, so it is read from the start for every item.
	 */
	private function waitForEngineIdCached(string $itemid): void {
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::getEngineIdCachedLine($itemid), false);
	}

	private static function getEngineIdCachedLine(string $itemid): string {
		return 'itemid:'.$itemid.' EngineID already discovered, skipping probe';
	}

	/**
	 * Check that items keep polling with their own cached engine ID, without failing with it or rediscovering it.
	 *
	 * Log is cleared, then cached polls of the items are awaited in turn, so each item is polled after the others
	 * have used their engine IDs.
	 */
	private function assertEngineIdsStable(array $itemids, string $description): void {
		self::clearLog(self::COMPONENT_SERVER);

		for ($i = 0; $i < self::PORT_POLL_ROUNDS; $i++) {
			foreach ($itemids as $itemid) {
				self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::getEngineIdCachedLine($itemid));
			}
		}

		foreach ($itemids as $itemid) {
			$pattern = 'itemid:'.$itemid.' (EngineID added for address|updated EngineID for address|'.
					'cannot receive response from .* retrying with probe)';

			$this->assertFalse(self::isLogLinePresent(self::COMPONENT_SERVER, $pattern, false, true),
				'Engine ID of itemid:'.$itemid.' was rediscovered with '.$description
			);
		}
	}

	private function waitForItemsCached(array $itemids, int $since, string $description): void {
		foreach ($itemids as $itemid) {
			$this->waitForItemValue($itemid, $since, $description);
			$this->waitForEngineIdCached($itemid);
		}
	}

	public function testSnmpEngineIdCache_addressChange(): void {
		$this->enableSnmpPollerDebugLog();

		$since = $this->updateSnmpInterface(self::getSnmpInterface());
		$this->waitForItemValue(self::$itemid, $since, 'valid IP');
		$this->waitForEngineIdCached(self::$itemid);

		$since = $this->updateSnmpInterface(self::getSnmpInterface([
			'useip' => INTERFACE_USE_DNS,
			'dns' => self::SNMP_DNS
		]));
		$this->waitForItemValue(self::$itemid, $since, 'valid DNS');
		$this->waitForEngineIdCached(self::$itemid);

		$this->updateSnmpInterface(self::getSnmpInterface([
			'useip' => INTERFACE_USE_DNS,
			'dns' => self::INVALID_DNS
		]));
		$error = $this->waitForItemFailure(self::$itemid, 'invalid DNS');
		$this->assertStringContainsString('cannot resolve address', $error, 'Unexpected error: '.$error);

		$since = $this->updateSnmpInterface(self::getSnmpInterface([
			'useip' => INTERFACE_USE_DNS,
			'dns' => self::SNMP_DNS
		]));
		$this->waitForItemValue(self::$itemid, $since, 'fixed DNS');
		$this->waitForEngineIdCached(self::$itemid);

		$this->updateSnmpInterface(self::getSnmpInterface(['ip' => self::INVALID_IP]));
		$error = $this->waitForItemFailure(self::$itemid, 'invalid IP');
		$this->assertStringContainsString('timed out', $error, 'Unexpected error: '.$error);

		$since = $this->updateSnmpInterface(self::getSnmpInterface());
		$this->waitForItemValue(self::$itemid, $since, 'fixed IP');
		$this->waitForEngineIdCached(self::$itemid);
	}

	public function testSnmpEngineIdCache_credentialsChange(): void {
		$this->enableSnmpPollerDebugLog();

		$since = $this->updateSnmpInterface(self::getSnmpInterface());
		$this->waitForItemValue(self::$itemid, $since, 'valid credentials');
		$this->waitForEngineIdCached(self::$itemid);

		$invalid_details = [
			'securityname' => 'invalid_user',
			'authpassphrase' => 'invalid_auth',
			'privpassphrase' => 'invalid_priv',
			'authprotocol' => ITEM_SNMPV3_AUTHPROTOCOL_SHA1,
			'privprotocol' => ITEM_SNMPV3_PRIVPROTOCOL_AES128
		];

		foreach ($invalid_details as $field => $value) {
			$this->updateSnmpInterface(self::getSnmpInterface([], [$field => $value]));
			$this->waitForItemFailure(self::$itemid, 'invalid '.$field);

			$since = $this->updateSnmpInterface(self::getSnmpInterface());
			$this->waitForItemValue(self::$itemid, $since, 'fixed '.$field);
			$this->waitForEngineIdCached(self::$itemid);
		}
	}

	public function testSnmpEngineIdCache_sameAddressItems(): void {
		$this->enableSnmpPollerDebugLog();

		$items = [];

		for ($i = 1; $i <= self::SAME_ADDRESS_ITEM_COUNT; $i++) {
			$items[] = [
				'hostid' => self::$hostid,
				'name' => 'SNMPv3 engine ID cache '.$i,
				'key_' => 'snmp.engineid.cache['.$i.']',
				'type' => ITEM_TYPE_SNMP,
				'snmp_oid' => self::SNMP_OID,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'delay' => '1s',
				'timeout' => '1s',
				'interfaceid' => self::$interfaceid
			];
		}

		$response = $this->call('item.create', $items);
		$this->assertArrayHasKey('itemids', $response['result']);
		$itemids = array_merge([self::$itemid], $response['result']['itemids']);

		$since = $this->updateSnmpInterface(self::getSnmpInterface());
		$this->waitForItemsCached($itemids, $since, 'valid IP');

		$since = $this->updateSnmpInterface(self::getSnmpInterface([
			'useip' => INTERFACE_USE_DNS,
			'dns' => self::SNMP_DNS
		]));
		$this->waitForItemsCached($itemids, $since, 'valid DNS');

		$since = $this->updateSnmpInterface(self::getSnmpInterface());
		$this->waitForItemsCached($itemids, $since, 'IP after DNS');

		$this->call('item.delete', array_slice($itemids, 1));
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
	}

	/**
	 * Move interface between two devices with different engine IDs on the same address.
	 */
	public function testSnmpEngineIdCache_portChange(): void {
		$this->enableSnmpPollerDebugLog();

		$since = $this->updateSnmpInterface(self::getSnmpInterface());
		$this->waitForItemValue(self::$itemid, $since, 'first agent port', false);
		$this->waitForEngineIdCached(self::$itemid);

		$since = $this->updateSnmpInterface(self::getSecondSnmpInterface());
		$this->waitForItemValue(self::$itemid, $since, 'second agent port', true);
		$this->waitForEngineIdCached(self::$itemid);
		$this->assertEngineIdsStable([self::$itemid], 'second agent port');

		$since = $this->updateSnmpInterface(self::getSnmpInterface());
		$this->waitForItemValue(self::$itemid, $since, 'first agent port after second', false);
		$this->waitForEngineIdCached(self::$itemid);
		$this->assertEngineIdsStable([self::$itemid], 'first agent port after second');
	}

	/**
	 * Create host with two interfaces on the same address: two agents with different engine IDs.
	 *
	 * Both interfaces are created by the same configuration sync and get the same revision, so if port was not
	 * part of the cache key, engine ID of one device would be used for the other one.
	 *
	 * @return array  itemids of the first and the second agent items
	 */
	private function createPortsHost(): array {
		/* left over by previous test if it has failed before cleanup */
		if (self::$ports_hostid !== null) {
			$this->deletePortsHost();
		}

		$since = time();

		$response = $this->call('host.create', [
			'host' => self::PORTS_HOST_NAME,
			'interfaces' => [
				self::getSnmpInterface(),
				self::getSecondSnmpInterface(['main' => INTERFACE_SECONDARY])
			],
			'groups' => [
				['groupid' => self::HOST_GROUP_ID]
			],
			'status' => HOST_STATUS_MONITORED
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$ports_hostid = $response['result']['hostids'][0];

		$response = $this->call('host.get', [
			'output' => [],
			'selectInterfaces' => ['interfaceid', 'port'],
			'hostids' => self::$ports_hostid
		]);
		$interfaceids = array_column($response['result'][0]['interfaces'], 'interfaceid', 'port');

		$items = [];

		foreach ([self::SNMP_PORT, self::SECOND_SNMP_PORT] as $port) {
			$items[] = [
				'hostid' => self::$ports_hostid,
				'name' => 'SNMPv3 engine ID cache port '.$port,
				'key_' => 'snmp.engineid.cache.port['.$port.']',
				'type' => ITEM_TYPE_SNMP,
				'snmp_oid' => self::SNMP_OID,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'delay' => '1s',
				'timeout' => '1s',
				'interfaceid' => $interfaceids[$port]
			];
		}

		$response = $this->call('item.create', $items);
		$this->assertArrayHasKey('itemids', $response['result']);
		[$first_itemid, $second_itemid] = $response['result']['itemids'];

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$this->waitForItemValue($first_itemid, $since, 'first agent port', false);
		$this->waitForItemValue($second_itemid, $since, 'second agent port', true);
		$this->waitForEngineIdCached($first_itemid);
		$this->waitForEngineIdCached($second_itemid);

		return [$first_itemid, $second_itemid];
	}

	private function deletePortsHost(): void {
		$this->call('host.delete', [self::$ports_hostid]);
		self::$ports_hostid = null;
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
	}

	/**
	 * Two devices with different engine IDs on the same address but different ports, polled at the same time.
	 */
	public function testSnmpEngineIdCache_sameAddressDifferentPorts(): void {
		$this->enableSnmpPollerDebugLog();

		$itemids = $this->createPortsHost();
		$this->assertEngineIdsStable($itemids, 'two devices on the same address');

		$this->deletePortsHost();
	}

	/**
	 * Runtime command "snmp_cache_reload" must remove all engine IDs, devices and identities, after that engine IDs
	 * must be discovered and cached again.
	 */
	public function testSnmpEngineIdCache_cacheReload(): void {
		$this->enableSnmpPollerDebugLog();

		[$first_itemid, $second_itemid] = $this->createPortsHost();

		self::clearLog(self::COMPONENT_SERVER);
		$since = time();
		$this->executeRuntimeControlCommand(self::COMPONENT_SERVER, 'snmp_cache_reload');

		$reload_pattern = 'forced reloading of the snmp cache on \[snmp poller #1, deleted ([0-9]+) device\(s\)'.
				' from cache\]';
		$identities_pattern = 'zbx_clear_cache_snmp\(\) identities in [a-z -]+ cache: remaining:([0-9]+)';
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, $reload_pattern, false, null, null, true);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, $identities_pattern, false, null, null, true);

		$log = CLogHelper::readLog(self::getLogPath(self::COMPONENT_SERVER));

		/* reported "devices" are engine IDs: at least ones of both agents */
		$this->assertSame(1, preg_match('/'.$reload_pattern.'/', $log, $matches), 'Cache reload line not found');
		$this->assertGreaterThanOrEqual(2, (int) $matches[1], 'Unexpected device count: '.$matches[0]);

		$this->assertSame(1, preg_match('/'.$identities_pattern.'/', $log, $matches), 'Identities line not found');
		$this->assertSame(0, (int) $matches[1], 'Identities left after cache reload: '.$matches[0]);

		/* second agent port is used only by this host, so its engine ID must be discovered again */
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'itemid:'.$second_itemid.' sending probe', false);

		$this->waitForItemValue($first_itemid, $since, 'first agent port after cache reload', false);
		$this->waitForItemValue($second_itemid, $since, 'second agent port after cache reload', true);
		$this->waitForEngineIdCached($first_itemid);
		$this->waitForEngineIdCached($second_itemid);
		$this->assertEngineIdsStable([$first_itemid, $second_itemid], 'cache reload');

		$this->deletePortsHost();
	}
}

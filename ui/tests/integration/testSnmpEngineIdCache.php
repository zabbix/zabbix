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
 * Requires SNMPv3 agent listening on localhost:161 with user "simulator" (authPriv, MD5 "auctoritas",
 * DES "privatus").
 *
 * @required-components server
 * @suite-components-reuse true
 * @configurationDataProvider serverConfigurationProvider
 * @onAfter clearData
 */
class testSnmpEngineIdCache extends CIntegrationTest {
	private const HOST_NAME = 'snmp_engineid_cache_host';
	private const HOST_GROUP_ID = 4;

	private const SNMP_IP = '127.0.0.1';
	private const SNMP_DNS = 'localhost';
	private const SNMP_PORT = '161';
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
				'UnreachableDelay' => 1
			]
		];
	}

	public function prepareData(): bool {
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
		if (isset(self::$hostid)) {
			CDataHelper::call('host.delete', [self::$hostid]);
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

	private function waitForItemValue(string $itemid, int $since, string $description): void {
		$this->callUntilDataIsPresent('history.get', [
			'output' => ['clock', 'value'],
			'itemids' => [$itemid],
			'history' => ITEM_VALUE_TYPE_TEXT,
			'time_from' => $since,
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'limit' => 1
		]);

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
	private function waitForItemFailure(string $description): string {
		$response = $this->callUntilDataIsPresent('item.get', [
			'output' => ['state', 'error'],
			'itemids' => [self::$itemid],
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
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'itemid:'.$itemid.' EngineID already discovered, skipping probe', false
		);
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
		$error = $this->waitForItemFailure('invalid DNS');
		$this->assertStringContainsString('cannot resolve address', $error, 'Unexpected error: '.$error);

		$since = $this->updateSnmpInterface(self::getSnmpInterface([
			'useip' => INTERFACE_USE_DNS,
			'dns' => self::SNMP_DNS
		]));
		$this->waitForItemValue(self::$itemid, $since, 'fixed DNS');
		$this->waitForEngineIdCached(self::$itemid);

		$this->updateSnmpInterface(self::getSnmpInterface(['ip' => self::INVALID_IP]));
		$error = $this->waitForItemFailure('invalid IP');
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
			$this->waitForItemFailure('invalid '.$field);

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
}

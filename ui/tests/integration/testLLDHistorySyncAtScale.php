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
 * Test suite to verify that history.get correctly returns values for
 * a large set of LLD-discovered items across all supported value types.
 *
 * @required-components server
 * @suite-components-reuse true
 * @configurationDataProvider configurationProvider
 * @onAfter clearData
 */
class testLLDHistorySyncAtScale extends CIntegrationTest {

	const HOSTNAME = 'test_lld_history_sync_at_scale';
	const LLD_RULE_KEY = 'lld.multiple.history.trapper';
	const LLD_MACRO = '{#SENSOR}';
	const ITEM_PROTO_KEY = 'multiple.history.trap';
	const SENSOR_BASE = 'sensor';
	const LLD_DISCOVERY_COUNT = 10000;

	private static $hostid;
	private static $discovered_itemids = [];
	private static $discovered_triggerids = [];
	private static $total_expected;
	private static $total_trigger_expected;
	private static $tm_past;
	private static $tm_now;
	private static $prepared_past = [];
	private static $prepared_now = [];
	private static $sent_past = [];
	private static $sent_now = [];
	private static $vps_last;

	private static function prototypeDefs() {
		return [
			['suffix' => 'float', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['suffix' => 'uint', 'value_type' => ITEM_VALUE_TYPE_UINT64],
			['suffix' => 'str', 'value_type' => ITEM_VALUE_TYPE_STR],
			['suffix' => 'text', 'value_type' => ITEM_VALUE_TYPE_TEXT],
			['suffix' => 'log', 'value_type' => ITEM_VALUE_TYPE_LOG],
			['suffix' => 'json', 'value_type' => ITEM_VALUE_TYPE_JSON]
		];
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		$this->call('settings.update', ['auditlog_enabled' => 0, 'auditlog_mode' => 0]);
		$response = $this->call('hostgroup.get', [
			'filter' => ['name' => ['Zabbix servers']],
			'output' => ['groupid']
		]);
		$this->assertNotEmpty($response['result'], 'Host group "Zabbix servers" not found.');
		$groupid = $response['result'][0]['groupid'];

		$response = $this->call('host.create', [
			'host' => self::HOSTNAME,
			'interfaces' => [],
			'groups' => [['groupid' => $groupid]],
			'status' => HOST_STATUS_MONITORED
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		// Create LLD rule on the host (trapper type so tests can push data directly).
		$response = $this->call('discoveryrule.create', [
			'hostid' => self::$hostid,
			'name' => 'Multiple Items History LLD Rule',
			'key_' => self::LLD_RULE_KEY,
			'type' => ITEM_TYPE_TRAPPER,
			'lifetime_type' => ZBX_LLD_DELETE_IMMEDIATELY
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		$lld_ruleid = $response['result']['itemids'][0];

		// Create one item prototype per value type.
		foreach (self::prototypeDefs() as $def) {
			$response = $this->call('itemprototype.create', [
				'hostid' => self::$hostid,
				'ruleid' => $lld_ruleid,
				'name' => 'Sensor '.$def['suffix'].' ['.self::LLD_MACRO.']',
				'key_' => self::ITEM_PROTO_KEY.'.'.$def['suffix'].'['.self::LLD_MACRO.']',
				'type' => ITEM_TYPE_ZABBIX_ACTIVE,
				'value_type' => $def['value_type'],
				'delay' => '1s'
			]);
			$this->assertArrayHasKey('itemids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['itemids']);
		}

		return true;
	}
	public static function clearData(): void {
		if (self::$hostid !== null) {
			CDataHelper::call('host.delete', [self::$hostid]);
		}

		self::$hostid = null;

		CDataHelper::call('settings.update', ['auditlog_enabled' => 1, 'auditlog_mode' => 1]);
	}
	/**
	 * Component configuration provider.
	 *
	 * @return array
	 */
	public function configurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'LogFileSize' => 1,
				'DebugLevel' => 3,
				'CacheSize' => '128M',
				'HistoryCacheSize' => '32M',
				'HistoryIndexCacheSize' => '32M',
				'ValueCacheSize' => '128M',
				'LogSlowQueries' => '50000',
				'StartDBSyncers' => '32' /* LLD_DISCOVERY_COUNT * types / ZBX_HC_SYNC_MAX  */
			]
		];
	}

	/**
	 * Send LLD discovery data and verify that each item prototype is
	 * instantiated for every discovered sensor.
	 */
	public function testLLDHistorySyncAtScale_LLDDiscovery() {
		// Reload configuration cache so the server is aware of the LLD rule.
		$this->reloadConfigurationCacheAndWaitForLogLine();

		$this->sendDiscoveryData();

		$proto_defs = self::prototypeDefs();
		self::$total_expected = self::LLD_DISCOVERY_COUNT * count($proto_defs);
		$trigger_defs = array_filter($proto_defs, fn($d) => $d['value_type'] !== ITEM_VALUE_TYPE_JSON);
		self::$total_trigger_expected = self::LLD_DISCOVERY_COUNT * count($trigger_defs);

		// Wait until all items for all prototypes are created.
		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'.'],
			'output' => ['itemid', 'key_', 'value_type']
		], 120, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === self::$total_expected;
		});

		$this->assertCount(self::$total_expected, $response['result'],
			'Expected '.self::$total_expected.' discovered items, got '.count($response['result']).'.');

		foreach ($response['result'] as $item) {
			$vtype = (int) $item['value_type'];
			self::$discovered_itemids[$vtype][$item['key_']] = (int) $item['itemid'];
		}

		foreach ($proto_defs as $def) {
			$this->assertCount(self::LLD_DISCOVERY_COUNT,
				self::$discovered_itemids[$def['value_type']],
				'Expected '.self::LLD_DISCOVERY_COUNT.' discovered items for type '.$def['suffix'].'.');
		}

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
	}

	/**
	 * Prepare history values for past and current time.
	 *
	 * @depends testLLDHistorySyncAtScale_LLDDiscovery
	 */
	public function testLLDHistorySyncAtScale_HistoryPrepare() {
		self::$tm_past = time() - 3600;
		self::$tm_now = time();

		self::$prepared_past = $this->prepareHistoryAt(self::$tm_past);
		self::$prepared_now = $this->prepareHistoryAt(self::$tm_now);
	}

	/**
	 * Send history values 1 hour in the past.
	 *
	 * @depends testLLDHistorySyncAtScale_HistoryPrepare
	 */
	public function testLLDHistorySyncAtScale_HistoryPastSend() {
		['sent' => $sent, 'values' => $all_values] = self::$prepared_past;
		self::$vps_last = $this->getVpsWritten();
		$this->sendAgentDataValues($all_values, self::HOSTNAME, self::COMPONENT_SERVER, 0);

		self::$sent_past = $sent;
	}

	/**
	 * Verify that VPS written counter increased by the number of values sent in the past batch.
	 *
	 * @depends testLLDHistorySyncAtScale_HistoryPastSend
	 */
	public function testLLDHistorySyncAtScale_HistoryPastVpsWritten() {
		$this->assertVpsWrittenIncreasedBy(self::$vps_last, self::$total_expected);
	}

	/**
	 * Verify history values sent 1 hour in the past.
	 *
	 * @depends testLLDHistorySyncAtScale_HistoryPastVpsWritten
	 */
	public function testLLDHistorySyncAtScale_HistoryPastVerify() {
		$this->verifyHistoryAt(self::$tm_past, self::$sent_past);
	}

	/**
	 * Send history values at current time.
	 *
	 * @depends testLLDHistorySyncAtScale_HistoryPastVerify
	 */
	public function testLLDHistorySyncAtScale_HistoryNowSend() {
		['sent' => $sent, 'values' => $all_values] = self::$prepared_now;
		self::$vps_last = $this->getVpsWritten();
		$this->sendAgentDataValues($all_values, self::HOSTNAME, self::COMPONENT_SERVER, 0);

		self::$sent_now = $sent;
	}

	/**
	 * Verify that VPS written counter increased by the number of values sent in the current-time batch.
	 *
	 * @depends testLLDHistorySyncAtScale_HistoryNowSend
	 */
	public function testLLDHistorySyncAtScale_HistoryNowVpsWritten() {
		$this->assertVpsWrittenIncreasedBy(self::$vps_last, self::$total_expected);
	}

	/**
	 * Verify history values sent at current time.
	 *
	 * @depends testLLDHistorySyncAtScale_HistoryNowVpsWritten
	 */
	public function testLLDHistorySyncAtScale_HistoryNowVerify() {
		$this->verifyHistoryAt(self::$tm_now, self::$sent_now);
	}

	/**
	 * Verify that trends are generated for both past and current hour.
	 *
	 * @depends testLLDHistorySyncAtScale_HistoryNowVerify
	 */
	public function testLLDHistorySyncAtScale_HistoryAndTrends() {
		$this->verifyTrendsAtClock(self::$tm_past - (self::$tm_past % 3600));

		$this->stopComponent(self::COMPONENT_SERVER);
		$this->verifyTrendsAtClock(self::$tm_now - (self::$tm_now % 3600));
		$this->startComponent(self::COMPONENT_SERVER);
	}

	/**
	 * Add a trigger prototype per item type, verify that a trigger is created for every
	 * discovered sensor across all value types, then resend values for each type.
	 *
	 * @depends testLLDHistorySyncAtScale_LLDDiscovery
	 */
	public function testLLDHistorySyncAtScale_TriggerDiscovery() {
		foreach (self::prototypeDefs() as $def) {
			if ($def['value_type'] === ITEM_VALUE_TYPE_JSON) {
				continue;
			}

			$response = $this->call('triggerprototype.create', [
				'description' => 'Sensor '.$def['suffix'].' alert ['.self::LLD_MACRO.']',
				'expression' => 'last(/'.self::HOSTNAME.'/'.self::ITEM_PROTO_KEY.'.'.$def['suffix'].'['.self::LLD_MACRO.'])>0'
			]);
			$this->assertArrayHasKey('triggerids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['triggerids']);
		}

		$this->sendDiscoveryData();

		// Wait until a trigger instance is created for every discovered sensor and non-JSON type.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [self::$hostid],
			'output' => ['triggerid', 'description', 'status']
		], 120, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === self::$total_trigger_expected;
		});

		$this->assertCount(self::$total_trigger_expected, $response['result'],
			'Not all '.self::$total_trigger_expected.' discovered triggers were created.');

		foreach ($response['result'] as $trigger) {
			$this->assertEquals(TRIGGER_STATUS_ENABLED, $trigger['status']);
			self::$discovered_triggerids[] = (int) $trigger['triggerid'];
		}

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
	}

	/**
	 * @depends testLLDHistorySyncAtScale_TriggerDiscovery
	 */
	public function testLLDHistorySyncAtScale_TriggerFiring() {
		$tm = time();
		$sent = $this->sendHistoryAt($tm);
		$this->verifyHistoryAt($tm, $sent);

		// Verify all discovered triggers fired (value = PROBLEM, state = NORMAL).
		$this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [self::$hostid],
			'output' => ['triggerid', 'value', 'state']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			if (count($r['result']) !== self::$total_trigger_expected) {
				return false;
			}
			foreach ($r['result'] as $trigger) {
				if ((int) $trigger['value'] !== TRIGGER_VALUE_TRUE) {
					return false;
				}
				if ((int) $trigger['state'] !== TRIGGER_STATE_NORMAL) {
					return false;
				}
			}
			return true;
		});

		// Verify one event was created per discovered trigger.
		$this->callUntilDataIsPresent('event.get', [
			'objectids' => self::$discovered_triggerids,
			'source' => EVENT_SOURCE_TRIGGERS,
			'output' => ['eventid'],
			'limit' => self::$total_trigger_expected
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === self::$total_trigger_expected;
		});
	}

	/**
	 * @depends testLLDHistorySyncAtScale_TriggerFiring
	 */
	public function testLLDHistorySyncAtScale_TriggerFiringRestart() {
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_SERVER);
		$tm = time();
		$sent = $this->sendHistoryAt($tm);
		$this->verifyHistoryAt($tm, $sent);
	}

	private function verifyTrendsAtClock(int $trend_clock): void {
		foreach ([ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64] as $vtype) {
			$itemids = array_values(self::$discovered_itemids[$vtype]);

			$this->callUntilDataIsPresent('trend.get', [
				'itemids' => $itemids,
				'time_from' => $trend_clock,
				'time_till' => $trend_clock,
				'limit' => self::LLD_DISCOVERY_COUNT
			], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
				return count($response['result']) === self::LLD_DISCOVERY_COUNT;
			});

			$response = $this->call('trend.get', [
				'itemids' => $itemids,
				'time_from' => $trend_clock,
				'time_till' => $trend_clock,
				'countOutput' => true
			]);
			$this->assertEquals((string) self::LLD_DISCOVERY_COUNT, $response['result']);

			$idx = 0;
			$expected_by_itemid = [];
			foreach (self::$discovered_itemids[$vtype] as $itemid) {
				$expected_by_itemid[$itemid] = $idx + 1;
				$idx++;
			}

			$response = $this->call('trend.get', [
				'itemids' => $itemids,
				'time_from' => $trend_clock,
				'time_till' => $trend_clock,
				'limit' => self::LLD_DISCOVERY_COUNT
			]);
			$this->assertCount(self::LLD_DISCOVERY_COUNT, $response['result']);
			foreach ($response['result'] as $trend) {
				$expected = $expected_by_itemid[$trend['itemid']];
				$this->assertEquals((string) $trend_clock, $trend['clock']);
				$this->assertEquals('1', $trend['num']);
				if ($vtype === ITEM_VALUE_TYPE_FLOAT) {
					$this->assertEquals((float) $expected, (float) $trend['value_min']);
					$this->assertEquals((float) $expected, (float) $trend['value_avg']);
					$this->assertEquals((float) $expected, (float) $trend['value_max']);
				}
				else {
					$this->assertEquals((string) $expected, $trend['value_min']);
					$this->assertEquals((string) $expected, $trend['value_avg']);
					$this->assertEquals((string) $expected, $trend['value_max']);
				}
			}
		}
	}

	private function prepareHistoryAt(int $tm): array {
		$sent = [];
		$values_by_type = [];

		foreach (self::prototypeDefs() as $def) {
			$vtype = $def['value_type'];
			$items_by_key = self::$discovered_itemids[$vtype];

			$this->assertCount(self::LLD_DISCOVERY_COUNT, $items_by_key,
				'Expected '.self::LLD_DISCOVERY_COUNT.' discovered item IDs for type '.$def['suffix'].'.');

			$values = [];
			$idx = 0;
			$base_ns = (int)(microtime(true) * 1e9) % 1000000000;
			foreach ($items_by_key as $key => $itemid) {
				$values[] = [
					'itemid' => $itemid,
					'value' => (string)($idx + 1),
					'clock' => $tm,
					'ns' => ($base_ns + $idx) % 1000000000
				];
				$idx++;
			}

			$itemids = array_values($items_by_key);
			$expected_by_itemid = [];
			foreach ($itemids as $i => $itemid) {
				$expected_by_itemid[$itemid] = $values[$i];
			}

			$sent[$vtype] = [
				'itemids' => $itemids,
				'expected_by_itemid' => $expected_by_itemid
			];

			$values_by_type[] = $values;
		}

		$all_values = [];
		for ($i = 0; $i < self::LLD_DISCOVERY_COUNT; $i++) {
			foreach ($values_by_type as $values) {
				$all_values[] = $values[$i];
			}
		}

		return ['sent' => $sent, 'values' => $all_values];
	}

	private function sendHistoryAt(int $tm): array {
		['sent' => $sent, 'values' => $all_values] = $this->prepareHistoryAt($tm);
		$this->sendAgentDataValues($all_values, self::HOSTNAME, self::COMPONENT_SERVER, 0);

		return $sent;
	}

	private function verifyHistoryAt(int $tm, array $sent): void {
		foreach (self::prototypeDefs() as $def) {
			$vtype = $def['value_type'];
			$itemids = $sent[$vtype]['itemids'];
			$expected_by_itemid = $sent[$vtype]['expected_by_itemid'];

			$history_response = $this->callUntilDataIsPresent('history.get', [
				'history' => $vtype,
				'itemids' => $itemids,
				'time_from' => $tm,
				'time_till' => $tm,
				'limit' => self::LLD_DISCOVERY_COUNT
			], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
				return count($response['result']) === self::LLD_DISCOVERY_COUNT;
			});

			$this->assertCount(self::LLD_DISCOVERY_COUNT, $history_response['result']);
			foreach ($history_response['result'] as $record) {
				$exp = $expected_by_itemid[$record['itemid']];
				$this->assertEquals($tm, (int) $record['clock']);
				$this->assertEquals($exp['ns'], (int) $record['ns']);
				if ($vtype === ITEM_VALUE_TYPE_FLOAT) {
					$this->assertEquals((float) $exp['value'], (float) $record['value']);
				}
				else {
					$this->assertEquals($exp['value'], $record['value']);
				}
			}

			$response = $this->call('history.get', [
				'history' => $vtype,
				'itemids' => $itemids,
				'time_from' => $tm,
				'time_till' => $tm,
				'countOutput' => true
			]);
			$this->assertEquals((string) self::LLD_DISCOVERY_COUNT, $response['result']);

			// Verify sort + limit: returned records should be ordered by itemid DESC.
			$response = $this->call('history.get', [
				'history' => $vtype,
				'itemids' => $itemids,
				'time_from' => $tm,
				'time_till' => $tm,
				'sortfield' => 'itemid',
				'sortorder' => 'DESC',
				'limit' => 10
			]);
			$this->assertCount(10, $response['result']);
			for ($i = 0; $i < count($response['result']) - 1; $i++) {
				$this->assertGreaterThanOrEqual(
					(int) $response['result'][$i + 1]['itemid'],
					(int) $response['result'][$i]['itemid']
				);
			}
		}
	}

	private function sendDiscoveryData(): void {
		$data = [];
		for ($i = 1; $i <= self::LLD_DISCOVERY_COUNT; $i++) {
			$data[] = [self::LLD_MACRO => self::SENSOR_BASE.$i];
		}

		$this->sendDataValues('sender', [
			[
				'host' => self::HOSTNAME,
				'key' => self::LLD_RULE_KEY,
				'value' => json_encode(['data' => $data])
			]
		], self::COMPONENT_SERVER, 0);
	}


	private function testItemOnServer(string $hostid, array $item,
			array $options = ['single' => false, 'state' => 0]): array|false {
		if (CAPIHelper::getSessionId() === null) {
			$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		}

		$response = $this->call('host.get', [
			'hostids' => [$hostid],
			'output' => ['maintenance_status', 'maintenance_type', 'proxyid']
		]);
		$this->assertCount(1, $response['result']);
		$host = $response['result'][0];

		$data = [
			'options' => $options,
			'item' => $item,
			'host' => [
				'hostid' => $hostid,
				'maintenance_status' => $host['maintenance_status'],
				'maintenance_type' => $host['maintenance_type'],
				'proxyid' => (int) $host['proxyid']
			]
		];

		return $this->getClient(self::COMPONENT_SERVER)->testItem($data, CAPIHelper::getSessionId());
	}

	private function getVpsWritten(): int {
		$result = $this->testItemOnServer((string) self::$hostid,
			['value_type' => '3', 'type' => '5', 'key' => 'zabbix[vps,written]']
		);
		$this->assertNotFalse($result);
		$this->assertArrayHasKey('item', $result);
		$this->assertArrayNotHasKey('error', $result['item']);
		$this->assertArrayHasKey('result', $result['item']);
		$this->assertIsNumeric($result['item']['result']);

		return (int) $result['item']['result'];
	}

	private function assertVpsWrittenIncreasedBy(int $baseline, int $min_increase): void {
		$expected = $baseline + $min_increase;
		for ($i = 0; $i < self::WAIT_ITERATIONS; $i++) {
			if ($this->getVpsWritten() >= $expected) {
				break;
			}
			usleep(50000); // 50 ms: detects ~1.2 M values/s throughput lower bound (LLD_DISCOVERY_COUNT * types / 0.05 s)
		}
		$this->assertGreaterThanOrEqual($expected, $this->getVpsWritten());
	}

	/**
	 * Send empty LLD discovery, run housekeeper and verify that all discovered
	 * items, their history and trigger events are removed.
	 *
	 * @depends testLLDHistorySyncAtScale_TriggerFiringRestart
	 */
	public function testLLDHistorySyncAtScale_HousekeeperCleanup() {
		$this->sendDataValues('sender', [
			[
				'host' => self::HOSTNAME,
				'key' => self::LLD_RULE_KEY,
				'value' => json_encode(['data' => []])
			]
		], self::COMPONENT_SERVER, 0);

		$this->callUntilCountIsPresent('item.get', [
			'hostids' => [self::$hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'.']
		], 0, 120, self::WAIT_ITERATION_DELAY);

		/* check that server succeessfuly removed large amount of items from cache */
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
	}
}

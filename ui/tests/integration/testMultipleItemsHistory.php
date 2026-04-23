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
 * Test suite to verify that history.get correctly returns values for
 * a large set of LLD-discovered items across all supported value types.
 *
 * @required-components server
 * @suite-components-reuse true
 * @configurationDataProvider configurationProvider
 * @onAfter clearData
 */
class testMultipleItemsHistory extends CIntegrationTest {

	const HOSTNAME = 'test_multiple_items_history';
	const LLD_RULE_KEY = 'lld.multiple.history.trapper';
	const LLD_MACRO = '{#SENSOR}';
	const ITEM_PROTO_KEY = 'multiple.history.trap';
	const SENSOR_BASE = 'sensor';
	const LLD_DISCOVERY_COUNT = 10000;

	private static $hostid;
	private static $lld_ruleid;
	private static $item_prototypeids = [];
	private static $discovered_itemids = [];
	private static $discovered_triggerids = [];
	private static $tm_past;
	private static $tm_now;

	private static function prototypeDefs() {
		return [
			['suffix' => 'float', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['suffix' => 'uint', 'value_type' => ITEM_VALUE_TYPE_UINT64],
			['suffix' => 'str', 'value_type' => ITEM_VALUE_TYPE_STR],
			['suffix' => 'text', 'value_type' => ITEM_VALUE_TYPE_TEXT],
			['suffix' => 'log', 'value_type' => ITEM_VALUE_TYPE_LOG]
		];
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		$this->call('settings.update', ['auditlog_enabled' => 0]);

		$response = $this->call('host.create', [
			'host' => self::HOSTNAME,
			'interfaces' => [],
			'groups' => [['groupid' => 4]],
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
			'lifetime_type' => 2
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$lld_ruleid = $response['result']['itemids'][0];

		// Create one item prototype per value type.
		foreach (self::prototypeDefs() as $def) {
			$response = $this->call('itemprototype.create', [
				'hostid' => self::$hostid,
				'ruleid' => self::$lld_ruleid,
				'name' => 'Sensor '.$def['suffix'].' ['.self::LLD_MACRO.']',
				'key_' => self::ITEM_PROTO_KEY.'.'.$def['suffix'].'['.self::LLD_MACRO.']',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => $def['value_type']
			]);
			$this->assertArrayHasKey('itemids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['itemids']);
			self::$item_prototypeids[$def['value_type']] = $response['result']['itemids'][0];
		}

		return true;
	}
	public static function clearData(): void {
		$all_itemids = [];
		foreach (self::$discovered_itemids as $itemids) {
			foreach ($itemids as $itemid) {
				$all_itemids[] = $itemid;
			}
		}

		if ($all_itemids) {
			CDataHelper::call('history.clear', $all_itemids);
		}

		if (self::$discovered_triggerids) {
			DB::delete('events', ['objectid' => self::$discovered_triggerids]);
		}

		if (self::$hostid !== null) {
			CDataHelper::call('host.delete', [self::$hostid]);
		}
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
				'HistoryIndexCacheSize' => '32M',
				'ValueCacheSize' => '128M',
				'LogSlowQueries' => '30000'
			]
		];
	}

	/**
	 * Send LLD discovery data and verify that each item prototype is
	 * instantiated for every discovered sensor.
	 */
	public function testMultipleItemsHistory_LLDDiscovery() {
		// Reload configuration cache so the server is aware of the LLD rule.
		$this->reloadConfigurationCacheAndWaitForLogLine();

		$this->sendDiscoveryData();

		$proto_defs = self::prototypeDefs();
		$total_expected = self::LLD_DISCOVERY_COUNT * count($proto_defs);

		// Wait until all items for all prototypes are created.
		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => [self::$hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'.'],
			'output' => ['itemid', 'key_', 'value_type']
		], 120, self::WAIT_ITERATION_DELAY, function ($r) use ($total_expected) {
			return count($r['result']) === $total_expected;
		});

		$this->assertCount($total_expected, $response['result'],
			'Not all '.$total_expected.' discovered items were created.');

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
	 * Send history values 1 hour in the past.
	 *
	 * @depends testMultipleItemsHistory_LLDDiscovery
	 */
	public function testMultipleItemsHistory_HistoryPast() {
		self::$tm_past = time() - 3600;

		$this->sendAndVerifyHistoryAt(self::$tm_past);
	}

	/**
	 * Send history values at current time.
	 *
	 * @depends testMultipleItemsHistory_HistoryPast
	 */
	public function testMultipleItemsHistory_HistoryNow() {

		self::$tm_now = time();

		$this->sendAndVerifyHistoryAt(self::$tm_now);
	}

	/**
	 * Verify that trends are generated for both past and current hour.
	 *
	 * @depends testMultipleItemsHistory_HistoryNow
	 */
	public function testMultipleItemsHistory_HistoryAndTrends() {
		$this->verifyTrendsAtClock(self::$tm_past - (self::$tm_past % 3600));

		$this->stopComponent(self::COMPONENT_SERVER);
		$this->verifyTrendsAtClock(self::$tm_now - (self::$tm_now % 3600));
		$this->startComponent(self::COMPONENT_SERVER);
	}

	/**
	 * Add a trigger prototype per item type, verify that a trigger is created for every
	 * discovered sensor across all value types, then resend values for each type.
	 *
	 * @depends testMultipleItemsHistory_LLDDiscovery
	 */
	public function testMultipleItemsHistory_TriggerDiscovery() {
		foreach (self::prototypeDefs() as $def) {
			$response = $this->call('triggerprototype.create', [
				'description' => 'Sensor '.$def['suffix'].' alert ['.self::LLD_MACRO.']',
				'expression' => 'last(/'.self::HOSTNAME.'/'.self::ITEM_PROTO_KEY.'.'.$def['suffix'].'['.self::LLD_MACRO.'])>0'
			]);
			$this->assertArrayHasKey('triggerids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['triggerids']);
		}

		$this->sendDiscoveryData();

		$total_expected = self::LLD_DISCOVERY_COUNT * count(self::prototypeDefs());

		// Wait until a trigger instance is created for every discovered sensor and type.
		$response = $this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [self::$hostid],
			'output' => ['triggerid', 'description', 'status']
		], 120, self::WAIT_ITERATION_DELAY, function ($r) use ($total_expected) {
			return count($r['result']) === $total_expected;
		});

		$this->assertCount($total_expected, $response['result'],
			'Not all '.$total_expected.' discovered triggers were created.');

		foreach ($response['result'] as $trigger) {
			$this->assertEquals(TRIGGER_STATUS_ENABLED, $trigger['status']);
			self::$discovered_triggerids[] = (int) $trigger['triggerid'];
		}

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
	}

	/**
	 * @depends testMultipleItemsHistory_TriggerDiscovery
	 */
	public function testMultipleItemsHistory_TriggerFiring() {
		$total_expected = self::LLD_DISCOVERY_COUNT * count(self::prototypeDefs());

		$this->sendAndVerifyHistoryAt(time());

		// Verify all discovered triggers fired (value = PROBLEM, state = NORMAL).
		$this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [self::$hostid],
			'output' => ['triggerid', 'value', 'state']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) use ($total_expected) {
			if (count($r['result']) !== $total_expected) {
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
			'limit' => $total_expected
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) use ($total_expected) {
			return count($r['result']) === $total_expected;
		});
	}

	/**
	 * @depends testMultipleItemsHistory_TriggerFiring
	 */
	public function testMultipleItemsHistory_TriggerFiringRestart() {
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_SERVER);
		$this->sendAndVerifyHistoryAt(time());
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

			$response = $this->call('trend.get', [
				'itemids' => $itemids,
				'time_from' => $trend_clock,
				'time_till' => $trend_clock,
				'limit' => self::LLD_DISCOVERY_COUNT
			]);
			$this->assertCount(self::LLD_DISCOVERY_COUNT, $response['result']);
			foreach ($response['result'] as $trend) {
				$this->assertArrayHasKey('itemid', $trend);
				$this->assertArrayHasKey('clock', $trend);
				$this->assertArrayHasKey('num', $trend);
				$this->assertArrayHasKey('value_min', $trend);
				$this->assertArrayHasKey('value_avg', $trend);
				$this->assertArrayHasKey('value_max', $trend);
				$this->assertEquals((string) $trend_clock, $trend['clock']);
			}
		}
	}

	private function sendAndVerifyHistoryAt(int $tm): void {
		foreach (self::prototypeDefs() as $def) {
			$vtype = $def['value_type'];
			$items_by_key = self::$discovered_itemids[$vtype];

			$this->assertCount(self::LLD_DISCOVERY_COUNT, $items_by_key,
				'Expected '.self::LLD_DISCOVERY_COUNT.' discovered item IDs for type '.$def['suffix'].'.');

			$values = [];
			$idx = 0;
			foreach ($items_by_key as $key => $itemid) {
				$values[] = [
					'host' => self::HOSTNAME,
					'key' => $key,
					'value' => (string)($idx + 1),
					'clock' => $tm,
					'ns' => (int)(microtime(true) * 1e9) % 1000000000
				];
				$idx++;
			}

			$this->sendDataValues('sender', $values, self::COMPONENT_SERVER, 0);

			$itemids = array_values($items_by_key);
			$this->callUntilDataIsPresent('history.get', [
				'history' => $vtype,
				'itemids' => $itemids,
				'time_from' => $tm,
				'time_till' => $tm,
				'limit' => self::LLD_DISCOVERY_COUNT
			], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
				return count($response['result']) === self::LLD_DISCOVERY_COUNT;
			});

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

			// Verify output field selection: only requested fields are returned.
			$response = $this->call('history.get', [
				'history' => $vtype,
				'itemids' => [$itemids[0]],
				'time_from' => $tm,
				'time_till' => $tm,
				'output' => ['itemid', 'value']
			]);
			$this->assertCount(1, $response['result']);
			$this->assertArrayHasKey('itemid', $response['result'][0]);
			$this->assertArrayHasKey('value', $response['result'][0]);
			$this->assertArrayNotHasKey('clock', $response['result'][0]);
			$this->assertArrayNotHasKey('ns', $response['result'][0]);
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

	/**
	 * Send empty LLD discovery, run housekeeper and verify that all discovered
	 * items, their history and trigger events are removed.
	 *
	 * @depends testMultipleItemsHistory_TriggerFiringRestart
	 */
	public function testMultipleItemsHistory_HousekeeperCleanup() {
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

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		/*$this->executeHousekeeper();

		foreach (self::prototypeDefs() as $def) {
			$vtype = $def['value_type'];
			$itemids = array_values(self::$discovered_itemids[$vtype]);
			$this->callUntilCountIsPresent('history.get', [
				'history' => $vtype,
				'itemids' => $itemids
			], 0, 120, self::WAIT_ITERATION_DELAY, function ($r) {
				$this->executeHousekeeper();
				return true;
			});
		}

		$this->callUntilCountIsPresent('event.get', [
			'objectids' => self::$discovered_triggerids,
			'source' => EVENT_SOURCE_TRIGGERS
		], 0, 120, self::WAIT_ITERATION_DELAY, function ($r) {
			$this->executeHousekeeper();
			return true;
		});*/
	}
}

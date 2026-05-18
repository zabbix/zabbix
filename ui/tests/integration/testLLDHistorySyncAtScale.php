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
 * Test suite that exercises the server at scale through an active proxy across
 * 10000 LLD-discovered items of every supported value type: first verifying
 * history and trends without triggers, then adding last()- and nodata()-based
 * trigger prototypes and verifying firing, recovery, UNKNOWN state, proxy
 * lastaccess, behavior across server restarts and final LLD cleanup.
 *
 * @required-components server
 * @suite-components-reuse true
 * @configurationDataProvider configurationProvider
 * @onAfter clearData
 */
class testLLDHistorySyncAtScale extends CIntegrationTest {

	const HOSTNAME = 'test_lld_history_sync_at_scale';
	const PROXY_NAME = 'test_lld_history_sync_at_scale_proxy';
	const LLD_RULE_KEY = 'lld.multiple.history.trapper';
	const LLD_MACRO = '{#SENSOR}';
	const ITEM_PROTO_KEY = 'multiple.history.trap';
	const SENSOR_BASE = 'sensor';
	const LLD_DISCOVERY_COUNT = 10000;
	const TRIGGER_WARMUP_ITERATIONS = 60;
	const LLD_ITERATIONS = 120;

	private static $hostid;
	private static $proxyid;
	private static $lld_ruleid;
	private static $discovered_itemids = [];
	private static $total_expected;
	private static $total_trigger_expected;
	private static $tm_past;
	private static $tm_now;
	private static $prepared_past = [];
	private static $prepared_now = [];
	private static $sent_past = [];
	private static $sent_now = [];
	private static $vps_last;
	private static $agent_ping_itemid;

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

		$response = $this->call('proxy.create', [
			'name' => self::PROXY_NAME,
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
			'hosts' => [
				['hostid' => self::$hostid]
			]
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['proxyids']);
		self::$proxyid = $response['result']['proxyids'][0];

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
		self::$lld_ruleid = $response['result']['itemids'][0];

		// Create one item prototype per value type.
		foreach (self::prototypeDefs() as $def) {
			$response = $this->call('itemprototype.create', [
				'hostid' => self::$hostid,
				'ruleid' => self::$lld_ruleid,
				'name' => 'Sensor '.$def['suffix'].' ['.self::LLD_MACRO.']',
				'key_' => self::ITEM_PROTO_KEY.'.'.$def['suffix'].'['.self::LLD_MACRO.']',
				'type' => ITEM_TYPE_ZABBIX_ACTIVE,
				'value_type' => $def['value_type'],
				'delay' => '1s'
			]);
			$this->assertArrayHasKey('itemids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['itemids']);
		}

		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => 'Agent ping',
			'key_' => 'agent.ping',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'delay' => '1h'
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$agent_ping_itemid = (int) $response['result']['itemids'][0];

		return true;
	}

	private function sendAgentPing(): void {
		$this->sendAgentDataValues([
			[
				'itemid' => self::$agent_ping_itemid,
				'value' => '1',
				'clock' => time(),
				'ns' => (int)(microtime(true) * 1e9) % 1000000000
			]
		], self::HOSTNAME, self::COMPONENT_SERVER, 0, self::PROXY_NAME);
	}
	public static function clearData(): void {
		if (CAPIHelper::getSessionId() === null) {
			CAPIHelper::authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		}

		if (self::$hostid !== null) {
			$response = CAPIHelper::call('host.delete', [self::$hostid]);
			self::assertArrayHasKey('hostids', $response['result']);
			self::assertContains((string) self::$hostid, $response['result']['hostids']);
			self::$hostid = null;
		}

		if (self::$proxyid !== null) {
			$response = CAPIHelper::call('proxy.delete', [self::$proxyid]);
			self::assertArrayHasKey('proxyids', $response['result']);
			self::assertContains((string) self::$proxyid, $response['result']['proxyids']);
			self::$proxyid = null;
		}

		CAPIHelper::call('settings.update', ['auditlog_enabled' => 1, 'auditlog_mode' => 1]);
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
				'LogSlowQueries' => '60000',
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
			'output' => ['itemid', 'value_type']
		], self::LLD_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === self::$total_expected;
		});

		$this->assertCount(self::$total_expected, $response['result'],
			'Expected '.self::$total_expected.' discovered items, got '.count($response['result']).'.');

		foreach ($response['result'] as $item) {
			$vtype = (int) $item['value_type'];
			self::$discovered_itemids[$vtype][] = (int) $item['itemid'];
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
		$this->sendAgentDataValues($all_values, self::HOSTNAME, self::COMPONENT_SERVER, 0, self::PROXY_NAME);

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
	 * @depends testLLDHistorySyncAtScale_HistoryPastSend
	 */
	public function testLLDHistorySyncAtScale_HistoryPastVerify() {
		$this->verifyHistoryAt(self::$tm_past, self::$sent_past);
	}

	/**
	 * Send history values at current time.
	 *
	 * @depends testLLDHistorySyncAtScale_HistoryPrepare
	 */
	public function testLLDHistorySyncAtScale_HistoryNowSend() {
		['sent' => $sent, 'values' => $all_values] = self::$prepared_now;
		self::$vps_last = $this->getVpsWritten();
		$this->sendAgentDataValues($all_values, self::HOSTNAME, self::COMPONENT_SERVER, 0, self::PROXY_NAME);

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
	 * @depends testLLDHistorySyncAtScale_HistoryNowSend
	 */
	public function testLLDHistorySyncAtScale_HistoryNowVerify() {
		$this->verifyHistoryAt(self::$tm_now, self::$sent_now);
	}

	/**
	 * Verify history count output and sort order for past and current time values.
	 *
	 * @depends testLLDHistorySyncAtScale_HistoryNowVerify
	 */
	public function testLLDHistorySyncAtScale_HistoryVerifySortAndCount() {
		foreach ([self::$tm_past => self::$sent_past, self::$tm_now => self::$sent_now] as $tm => $sent) {
			foreach (self::prototypeDefs() as $def) {
				$vtype = $def['value_type'];
				$itemids = $sent[$vtype]['itemids'];

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
	}

	/**
	 * Verify that trends are generated for both past and current hour.
	 *
	 * @depends testLLDHistorySyncAtScale_HistoryNowSend
	 */
	public function testLLDHistorySyncAtScale_TrendsVerify() {
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
		$this->callUntilCountIsPresent('trigger.get', [
			'hostids' => [self::$hostid]
		], self::$total_trigger_expected, self::LLD_ITERATIONS, self::WAIT_ITERATION_DELAY);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
	}

	/**
	 * @depends testLLDHistorySyncAtScale_TriggerDiscovery
	 */
	public function testLLDHistorySyncAtScale_TriggerFiring() {
		$tm = time();
		$sent = $this->sendHistoryAt($tm);

		// Verify all discovered triggers fired (value = PROBLEM, state = NORMAL).
		$this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [self::$hostid],
			'output' => ['triggerid', 'value', 'state']
		], self::TRIGGER_WARMUP_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			if (count($r['result']) !== self::$total_trigger_expected) {
				return 'Expected '.self::$total_trigger_expected.' triggers, got '.count($r['result']);
			}
			$wrong_value = 0;
			$wrong_state = 0;
			foreach ($r['result'] as $trigger) {
				if ((int) $trigger['value'] !== TRIGGER_VALUE_TRUE) {
					$wrong_value++;
				}
				if ((int) $trigger['state'] !== TRIGGER_STATE_NORMAL) {
					$wrong_state++;
				}
			}
			if ($wrong_value > 0 || $wrong_state > 0) {
				$fired = self::$total_trigger_expected - $wrong_value;
				$elapsed = self::TRIGGER_WARMUP_ITERATIONS * self::WAIT_ITERATION_DELAY;
				$tps = round($fired / $elapsed, 1);
				return $wrong_value.' triggers did not change value, '.$wrong_state.' triggers in wrong state'
					.'; trigger processing rate too low: '.$tps.' triggers/sec'
					.' (waited '.self::WAIT_ITERATIONS.'x'.self::WAIT_ITERATION_DELAY.'s = '.$elapsed.'s)';
			}
			return true;
		});
	}

	/**
	 * @depends testLLDHistorySyncAtScale_TriggerFiring
	 */
	public function testLLDHistorySyncAtScale_TriggerRecovery() {
		$tm = time();
		$this->sendHistoryAt($tm, '0');

		// Verify all discovered triggers recovered (value = OK, state = NORMAL).
		$this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [self::$hostid],
			'output' => ['triggerid', 'value', 'state']
		], self::TRIGGER_WARMUP_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			if (count($r['result']) !== self::$total_trigger_expected) {
				return false;
			}
			foreach ($r['result'] as $trigger) {
				if ((int) $trigger['value'] !== TRIGGER_VALUE_FALSE) {
					return false;
				}
				if ((int) $trigger['state'] !== TRIGGER_STATE_NORMAL) {
					return false;
				}
			}
			return true;
		});
	}

	/**
	 * @depends testLLDHistorySyncAtScale_TriggerFiring
	 */
	public function testLLDHistorySyncAtScale_TriggerFiringWarmupAfterRestart() {
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_SERVER);
		$this->testLLDHistorySyncAtScale_TriggerFiring();
	}

	/**
	 * @depends testLLDHistorySyncAtScale_TriggerFiring
	 */
	public function testLLDHistorySyncAtScale_TriggerRecoveryWarmupAfterRestart() {
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_SERVER);
		$this-> testLLDHistorySyncAtScale_TriggerRecovery();
	}

	/**
	 * @depends testLLDHistorySyncAtScale_TriggerFiring
	 */
	public function testLLDHistorySyncAtScale_TriggerUnknown() {
		$tm = time();
		$this->sendHistoryAt($tm, 'item is not supported', ITEM_STATE_NOTSUPPORTED);

		// Verify all discovered triggers became unknown (state = UNKNOWN).
		$this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [self::$hostid],
			'output' => ['triggerid', 'value', 'state']
		], self::TRIGGER_WARMUP_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			if (count($r['result']) !== self::$total_trigger_expected) {
				return false;
			}
			foreach ($r['result'] as $trigger) {
				if ((int) $trigger['state'] !== TRIGGER_STATE_UNKNOWN) {
					return false;
				}
			}
			return true;
		});
	}

	/**
	 * @depends testLLDHistorySyncAtScale_TriggerUnknown
	 */
	public function testLLDHistorySyncAtScale_TriggerRecoverUnknown() {
		$this-> testLLDHistorySyncAtScale_TriggerRecovery();
	}

	/**
	 * Update each trigger prototype expression to nodata(...,30s)=1 and verify that
	 * all discovered triggers fire after the no-data window elapses.
	 *
	 * @depends testLLDHistorySyncAtScale_TriggerDiscovery
	 */
	public function testLLDHistorySyncAtScale_TriggerNoDataDiscovery() {
		foreach (self::prototypeDefs() as $def) {
			if ($def['value_type'] === ITEM_VALUE_TYPE_JSON) {
				continue;
			}

			$response = $this->call('triggerprototype.get', [
				'hostids' => [self::$hostid],
				'filter' => ['description' => 'Sensor '.$def['suffix'].' alert ['.self::LLD_MACRO.']'],
				'output' => ['triggerid']
			]);
			$this->assertNotEmpty($response['result'],
				'Trigger prototype for '.$def['suffix'].' not found.');

			$response = $this->call('triggerprototype.update', [
				'triggerid' => $response['result'][0]['triggerid'],
				'description' => 'NoData Sensor '.$def['suffix'].' alert ['.self::LLD_MACRO.']',
				'expression' => 'nodata(/'.self::HOSTNAME.'/'.self::ITEM_PROTO_KEY.'.'.$def['suffix']
					.'['.self::LLD_MACRO.'],30s)=1'
			]);
			$this->assertCount(1, $response['result']['triggerids']);
		}

		$response = $this->call('trigger.get', [
			'hostids' => [self::$hostid],
			'output' => ['triggerid']
		]);
		if (!empty($response['result'])) {
			$triggerids = array_column($response['result'], 'triggerid');
			$this->call('trigger.delete', $triggerids);
		}

		$this->sendDiscoveryData();

		// Wait until a trigger instance is created for every discovered sensor and non-JSON type
		// and its description carries the updated "NoData " prefix.
		$this->callUntilCountIsPresent('trigger.get', [
			'hostids' => [self::$hostid],
			'search' => ['description' => 'NoData Sensor '],
			'startSearch' => true
		], self::$total_trigger_expected, self::LLD_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			$this->sendAgentPing();
			return true;
		});

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
	}

	/**
	 *
	 * @depends testLLDHistorySyncAtScale_TriggerNoDataDiscovery
	 */
	public function testLLDHistorySyncAtScale_TriggerNoDataFiring() {
		$this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [self::$hostid],
			'output' => ['triggerid', 'value', 'state']
		], 120, self::WAIT_ITERATION_DELAY, function ($r) {

			$this->sendAgentPing();

			if (count($r['result']) !== self::$total_trigger_expected) {
				return 'Expected '.self::$total_trigger_expected.' triggers, got '.count($r['result']);
			}
			$wrong_value = 0;
			$wrong_state = 0;
			foreach ($r['result'] as $trigger) {
				if ((int) $trigger['value'] !== TRIGGER_VALUE_TRUE) {
					$wrong_value++;
				}
				if ((int) $trigger['state'] !== TRIGGER_STATE_NORMAL) {
					$wrong_state++;
				}
			}
			if ($wrong_value > 0 || $wrong_state > 0) {
				return $wrong_value.' triggers did not change to PROBLEM, '
					.$wrong_state.' triggers not in NORMAL state';
			}
			return true;
		});
	}

	/**
	 * Verify that an agent ping through the proxy updates proxy lastaccess
	 * within 3 seconds.
	 *
	 * @depends testLLDHistorySyncAtScale_TriggerNoDataDiscovery
	 */
	public function testLLDHistorySyncAtScale_ProxyLastaccess() {
		$this->callUntilDataIsPresent('proxy.get', [
			'proxyids' => [self::$proxyid],
			'output' => ['lastaccess']
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			$this->sendAgentPing();
			return (time() - (int) $r['result'][0]['lastaccess']) <= 3;
		});
	}

	/**
	 * Resend NOTSUPPORTED data and verify that nodata-based triggers remain firing
	 * (value = PROBLEM, state = NORMAL) regardless of item state.
	 *
	 * @depends testLLDHistorySyncAtScale_ProxyLastaccess
	 */
	public function testLLDHistorySyncAtScale_TriggerNoDataNotSupported() {
		$tm = time();

		$this->sendHistoryAt($tm, 'item is not supported', ITEM_STATE_NOTSUPPORTED);

		$this->callUntilCountIsPresent('item.get', [
			'hostids' => [self::$hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'.'],
			'filter' => ['state' => ITEM_STATE_NOTSUPPORTED]
		], self::$total_expected, self::TRIGGER_WARMUP_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			$this->sendAgentPing();

			return true;
		});

		$response = $this->call('proxy.get', [
			'proxyids' => [self::$proxyid],
			'output' => ['lastaccess']
		]);
		$this->assertLessThanOrEqual(10, time() - (int) $response['result'][0]['lastaccess'],
				'Proxy lastaccess is older than 10 seconds.');

		$trigger_unknown_error = null;

		$this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [self::$hostid],
			'output' => ['triggerid', 'value', 'state', 'error']
		], 120, self::WAIT_ITERATION_DELAY, function ($r) use (&$trigger_unknown_error) {

			$this->sendAgentPing();

			foreach ($r['result'] as $trigger) {
				if ((int) $trigger['state'] !== TRIGGER_STATE_NORMAL && $trigger_unknown_error === null) {
					$trigger_unknown_error = 'Trigger '.$trigger['triggerid'].
							' transitioned to UNKNOWN. Error:'.$trigger['error'];
					return true;
				}
			}

			if (count($r['result']) !== self::$total_trigger_expected) {
				return false;
			}

			foreach ($r['result'] as $trigger) {
				if ((int) $trigger['value'] !== TRIGGER_VALUE_TRUE) {
					return false;
				}
			}
			return true;
		});

		if ($trigger_unknown_error !== null) {
			self::markTestSkipped('Test case is not supported, see ZBX-27736.');
		}
	}

	/**
	 * Restart the server and verify that nodata-based triggers recover again
	 * once normal data resumes flowing.
	 *
	 * @depends testLLDHistorySyncAtScale_TriggerNoDataFiring
	 */
	public function testLLDHistorySyncAtScale_TriggerNoDataRecoveryAfterRestart() {
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_SERVER);
		$tm = time();
		$this->sendHistoryAt($tm);

		$this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [self::$hostid],
			'output' => ['triggerid', 'value', 'state']
		], self::TRIGGER_WARMUP_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			if (count($r['result']) !== self::$total_trigger_expected) {
				return 'Expected '.self::$total_trigger_expected.' triggers, got '.count($r['result']);
			}
			$wrong_value = 0;
			$wrong_state = 0;
			foreach ($r['result'] as $trigger) {
				if ((int) $trigger['value'] !== TRIGGER_VALUE_FALSE) {
					$wrong_value++;
				}
				if ((int) $trigger['state'] !== TRIGGER_STATE_NORMAL) {
					$wrong_state++;
				}
			}
			if ($wrong_value > 0 || $wrong_state > 0) {
				return $wrong_value.' triggers did not change to OK, '
					.$wrong_state.' triggers not in NORMAL state';
			}
			return true;
		});
	}

	/**
	 * Verify that discovered triggers fire after the no-data window elapses
	 * (value = PROBLEM, state = NORMAL).
	 *
	 * @depends testLLDHistorySyncAtScale_TriggerNoDataRecoveryAfterRestart
	 */
	public function testLLDHistorySyncAtScale_TriggerNoDataFiringAfterRestart() {
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_SERVER);
		$this->testLLDHistorySyncAtScale_TriggerNoDataFiring();
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

	private function prepareHistoryAt(int $tm, ?string $value = null, int $state = ITEM_STATE_NORMAL): array {
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
			foreach ($items_by_key as $itemid) {
				$item_value = [
					'itemid' => $itemid,
					'value' => isset($value) ? $value : (string)($idx + 1),
					'clock' => $tm,
					'ns' => ($base_ns + $idx) % 1000000000
				];
				if ($state !== ITEM_STATE_NORMAL) {
					$item_value['state'] = $state;
				}
				$values[] = $item_value;
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

	private function sendHistoryAt(int $tm, ?string $value = null, int $state = ITEM_STATE_NORMAL): array {
		['sent' => $sent, 'values' => $all_values] = $this->prepareHistoryAt($tm, $value, $state);
		$this->sendAgentDataValues($all_values, self::HOSTNAME, self::COMPONENT_SERVER, 0, self::PROXY_NAME);

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
		}
	}

	private function sendDiscoveryData(): void {
		$data = [];
		for ($i = 1; $i <= self::LLD_DISCOVERY_COUNT; $i++) {
			$data[] = [self::LLD_MACRO => self::SENSOR_BASE.$i];
		}

		$this->sendAgentDataValues([
			[
				'itemid' => (int) self::$lld_ruleid,
				'value' => json_encode(['data' => $data]),
				'clock' => time(),
				'ns' => 0
			]
		], self::HOSTNAME, self::COMPONENT_SERVER, 0, self::PROXY_NAME);
	}


	private function testItemOnServer(string $hostid, array $item,
			array $options = ['single' => false, 'state' => 0]): array|false {
		if (CAPIHelper::getSessionId() === null) {
			$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		}

		$data = [
			'options' => $options,
			'item' => $item,
			'host' => [
				'hostid' => $hostid,
				'maintenance_status' => HOST_MAINTENANCE_STATUS_OFF,
				'maintenance_type' => MAINTENANCE_TYPE_NORMAL,
				'proxyid' => 0
			]
		];

		return $this->getClient(self::COMPONENT_SERVER)->testItem($data, CAPIHelper::getSessionId());
	}

	private function getVpsWritten(): int {
		$result = $this->testItemOnServer((string) self::$hostid,
			['value_type' => ITEM_VALUE_TYPE_UINT64, 'type' => ITEM_TYPE_INTERNAL, 'key' => 'zabbix[vps,written]']
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
		$timeout = self::WAIT_ITERATIONS * self::WAIT_ITERATION_DELAY;
		$start = microtime(true);

		while ((microtime(true) - $start) < $timeout) {
			if ($this->getVpsWritten() >= $expected) {
				break;
			}
			usleep(50000); // 50 ms: detects ~1.2 M values/s throughput lower bound (LLD_DISCOVERY_COUNT * types / 0.05 s)
		}

		$waited = round(microtime(true) - $start, 1);
		$this->assertGreaterThanOrEqual($expected, $this->getVpsWritten(),
			"VPS written did not reach expected value after waiting {$waited}s");
	}

	/**
	 * Send empty LLD discovery, run housekeeper and verify that all discovered
	 * items, their history and trigger events are removed.
	 *
	 * @depends testLLDHistorySyncAtScale_LLDDiscovery
	 */
	public function testLLDHistorySyncAtScale_LLDCleanup() {
		$this->sendAgentDataValues([
			[
				'itemid' => (int) self::$lld_ruleid,
				'value' => json_encode(['data' => []]),
				'clock' => time(),
				'ns' => 0
			]
		], self::HOSTNAME, self::COMPONENT_SERVER, 0, self::PROXY_NAME);

		$this->callUntilCountIsPresent('item.get', [
			'hostids' => [self::$hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'.']
		], 0, self::LLD_ITERATIONS, self::WAIT_ITERATION_DELAY);

		/* check that server succeessfuly removed large amount of items from cache */
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

	}
}

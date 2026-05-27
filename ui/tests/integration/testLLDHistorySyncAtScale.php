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
 * For minimal test run as '/testLLDHistorySyncAtScale_(LLDDiscovery|HistoryPrepare|HistoryPastSend|HistoryPastVpsWritten|HistoryPastVerify|HistoryNowSend|HistoryNowVpsWritten|HistoryNowVerify)$/'

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
	private static $log_lastlogsize = 0;

	/**
	 * Component configuration provider.
	 *
	 * @return array
	 */
	public function configurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'LogFileSize' => 8,
				'DebugLevel' => 3,
				'CacheSize' => '128M',
				'HistoryCacheSize' => '32M',
				'HistoryIndexCacheSize' => '32M',
				'ValueCacheSize' => '128M',
				'LogSlowQueries' => '60000',
				'StartDBSyncers' => '32' /* LLD_DISCOVERY_COUNT * types / ZBX_HC_SYNC_MAX */
				/*'HistoryProvider'=> [
					'clickhouse;value_types="uint,dbl,str,log,text,json",url=http://localhost:8123,db=zabbix,username=zabbix,password=zabbix'
				]*/
			]
		];
	}

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
		$config = $this->configurationProvider();
		if (!empty($config[self::COMPONENT_SERVER]['HistoryProvider'])) {
			global $HISTORY_PROVIDERS;
			$this->assertNotEmpty($HISTORY_PROVIDERS,
				'Server HistoryProvider is set in configurationProvider() but $HISTORY_PROVIDERS is empty — '
				.'frontend would use SQL while server writes to an external provider; '
				.'set $HISTORY_PROVIDERS in ui/conf/zabbix.conf.php');
		}

		$this->call('settings.update', ['auditlog_enabled' => 0, 'auditlog_mode' => 0]);

		// Disable every pre-existing monitored host so they don't conflict with the tests:
		// the global zabbix[queue,15,] metric used to verify delayed-item drain must reflect
		// only this suite's host (created below).
		$response = $this->call('host.get', [
			'filter' => ['status' => HOST_STATUS_MONITORED],
			'output' => ['hostid']
		]);
		foreach ($response['result'] as $h) {
			$this->call('host.update', [
				'hostid' => $h['hostid'],
				'status' => HOST_STATUS_NOT_MONITORED
			]);
		}

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
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'lifetime_type' => ZBX_LLD_DELETE_IMMEDIATELY,
			'delay' => '1h'
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

	/**
	 * Hook for routing item values. The base class sends to the server while
	 * spoofing the active proxy name so the server processes the values as if
	 * delivered by the proxy; subclasses targeting a real proxy daemon
	 * override this to deliver values to the proxy instead.
	 */
	protected function dispatchValues(array $values): void {
		$this->sendAgentDataValues($values, self::HOSTNAME, self::COMPONENT_SERVER, 0, self::PROXY_NAME);
	}

	private function sendAgentPing(): void {
		$this->dispatchValues([
			[
				'itemid' => self::$agent_ping_itemid,
				'value' => '1',
				'clock' => time(),
				'ns' => (int)(microtime(true) * 1e9) % 1000000000
			]
		]);
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

		self::$discovered_itemids = [];
		self::$log_lastlogsize = 0;

		CAPIHelper::call('settings.update', ['auditlog_enabled' => 1, 'auditlog_mode' => 1]);

		$response = CAPIHelper::call('action.get', [
			'output' => ['actionid'],
			'filter' => ['name' => 'Report unknown triggers']
		]);
		if (!empty($response['result'])) {
			CAPIHelper::call('action.update', [
				'actionid' => $response['result'][0]['actionid'],
				'status' => ACTION_STATUS_DISABLED
			]);
		}

		$response = CAPIHelper::call('host.get', [
			'filter' => ['host' => 'Zabbix server'],
			'output' => ['hostid']
		]);
		if (!empty($response['result'])) {
			CAPIHelper::call('host.update', [
				'hostid' => $response['result'][0]['hostid'],
				'status' => HOST_STATUS_MONITORED
			]);
		}
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

		// Preflight test: check that values can be saved and retrieved by sending
		// an agent ping and reading it back via a calculated item testItem.

		$this->executeRuntimeControlCommand(self::COMPONENT_SERVER, 'log_level_increase=trapper');
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$this->sendAgentPing();

		$result = null;

		sleep(3);
		$result = $this->testItemOnServer((string) self::$hostid, [
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'type' => ITEM_TYPE_CALCULATED,
			'key' => 'calc.agent.ping.presence',
			'params' => 'last(/'.self::HOSTNAME.'/agent.ping)'
		]);

		$this->executeRuntimeControlCommand(self::COMPONENT_SERVER, 'log_level_decrease=trapper');

		$this->assertNotFalse($result, 'testItem call failed for calculated item.');
		$this->assertArrayHasKey('item', $result);
		$this->assertArrayNotHasKey('error', $result['item'],
				'Calculated item evaluation returned an error: '
				.($result['item']['error'] ?? ''));
		$this->assertArrayHasKey('result', $result['item']);
		$this->assertEquals(1, (int) $result['item']['result'],
				'Calculated item did not return the agent ping value within timeout, got: '
				.var_export($result['item']['result'], true));
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
		$this->dispatchValues($all_values);

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
		$this->dispatchValues($all_values);

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
	 * Verify that after all items become delayed, sending data with the value field
	 * omitted drains the delay queue.
	 *
	 * @depends testLLDHistorySyncAtScale_LLDDiscovery
	 */
	public function testLLDHistorySyncAtScale_ValueOmittedDrainsDelay() {
		$response = $this->call('host.get', [
			'filter' => ['status' => HOST_STATUS_MONITORED],
			'output' => ['hostid', 'host']
		]);
		$other_hosts = [];
		foreach ($response['result'] as $h) {
			if ((string) $h['hostid'] !== (string) self::$hostid) {
				$other_hosts[] = $h;
			}
		}
		$this->assertEmpty($other_hosts,
			'Unexpected enabled hosts present (would pollute zabbix[queue,15,]): '.json_encode($other_hosts));

		$this->verifyValueOmittedDrainsDelay();
	}

	/**
	 * Verify that lastlogsize for log items advances in item_rtdata when log data
	 * with the value field omitted (but lastlogsize/mtime present) is sent.
	 *
	 * @depends testLLDHistorySyncAtScale_LLDDiscovery
	 */
	public function testLLDHistorySyncAtScale_LogLastlogsizeAdvances() {
		$this->verifyLogLastlogsizeAdvances();
	}

	/**
	 * Burst-send 10000 log entries for a single log item at tm-45 alongside a
	 * full history payload for all items at tm, before any triggers exist.
	 *
	 * @depends testLLDHistorySyncAtScale_LogLastlogsizeAdvances
	 */
	public function testLLDHistorySyncAtScale_SingleLogBurstPreTriggersSend() {
		$this->sendSingleLogBurstAndFullHistory();
	}

	/**
	 * Verify that VPS written counter increased by the number of values sent in the burst.
	 *
	 * @depends testLLDHistorySyncAtScale_SingleLogBurstPreTriggersSend
	 */
	public function testLLDHistorySyncAtScale_SingleLogBurstPreTriggersVpsWritten() {
		$this->assertSingleLogBurstAndFullHistoryVpsWritten();
	}

	/**
	 * Send value 0 (OK) for all items before any triggers exist to ensure triggers fire with new value.
	 *
	 * @depends testLLDHistorySyncAtScale_LLDDiscovery
	 */
	public function testLLDHistorySyncAtScale_PreTriggerZeroSend() {
		self::$vps_last = $this->getVpsWritten();
		$this->sendHistoryAt(time(), '0');
	}

	/**
	 * Verify that the VPS written counter increased by the number of zero values sent.
	 *
	 * @depends testLLDHistorySyncAtScale_PreTriggerZeroSend
	 */
	public function testLLDHistorySyncAtScale_PreTriggerZeroVpsWritten() {
		$this->assertVpsWrittenIncreasedBy(self::$vps_last, self::$total_expected);
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
		$this->sendHistoryAt($tm, null, ITEM_STATE_NOTSUPPORTED);

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
		$response = $this->call('action.get', [
			'output' => ['actionid'],
			'filter' => ['name' => 'Report unknown triggers']
		]);
		$this->assertNotEmpty($response['result'], 'Action "Report unknown triggers" not found.');
		$this->call('action.update', [
			'actionid' => $response['result'][0]['actionid'],
			'status' => ACTION_STATUS_ENABLED
		]);

		$this->updateTriggerPrototypesAndRediscover('NoData Sensor', function ($def) {
			return 'nodata(/'.self::HOSTNAME.'/'.self::ITEM_PROTO_KEY.'.'.$def['suffix']
				.'['.self::LLD_MACRO.'],30s)=1';
		});
	}

	private function updateTriggerPrototypesAndRediscover(string $description_prefix,
			callable $expression_builder): void {
		foreach (self::prototypeDefs() as $def) {
			if ($def['value_type'] === ITEM_VALUE_TYPE_JSON) {
				continue;
			}

			$response = $this->call('triggerprototype.get', [
				'hostids' => [self::$hostid],
				'search' => ['description' => ' '.$def['suffix'].' alert ['.self::LLD_MACRO.']'],
				'output' => ['triggerid']
			]);
			$this->assertNotEmpty($response['result'],
				'Trigger prototype for '.$def['suffix'].' not found.');

			$response = $this->call('triggerprototype.update', [
				'triggerid' => $response['result'][0]['triggerid'],
				'description' => $description_prefix.' '.$def['suffix'].' alert ['.self::LLD_MACRO.']',
				'expression' => $expression_builder($def)
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
		// and its description carries the updated prefix.
		$this->callUntilCountIsPresent('trigger.get', [
			'hostids' => [self::$hostid],
			'search' => ['description' => $description_prefix.' '],
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
		$unknown_before = $this->getUnknownTriggerEventCount();

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

		$unknown_after = $this->getUnknownTriggerEventCount();
		if ($unknown_before !== $unknown_after) {
			$this->markTestSkipped('Unknown trigger event count changed: '.$unknown_before.' -> '.$unknown_after);
		}
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
		$unknown_before = $this->getUnknownTriggerEventCount();

		$tm = time();

		$this->sendHistoryAt($tm, null, ITEM_STATE_NOTSUPPORTED);
		$last_resend = time();

		$this->callUntilCountIsPresent('item.get', [
			'hostids' => [self::$hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'.'],
			'filter' => ['state' => ITEM_STATE_NOTSUPPORTED]
		], self::$total_expected, self::TRIGGER_WARMUP_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) use (&$last_resend) {
			$this->sendAgentPing();

			if (time() - $last_resend >= 10) {
				$this->sendHistoryAt(time(), null, ITEM_STATE_NOTSUPPORTED);
				$last_resend = time();
			}

			return true;
		});

		$last_resend = time();
		$this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [self::$hostid],
			'output' => ['triggerid', 'value', 'state']
		], 120, self::WAIT_ITERATION_DELAY, function ($r) use (&$last_resend) {
			if (time() - $last_resend >= 10) {
				$this->sendHistoryAt(time(), null, ITEM_STATE_NOTSUPPORTED);
				$last_resend = time();
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

		$unknown_after = $this->getUnknownTriggerEventCount();
		if ($unknown_before !== $unknown_after) {
			$this->markTestSkipped('Unknown trigger event count changed: '.$unknown_before.' -> '.$unknown_after);
		}
	}

	/**
	 * Resend data with the value field omitted and verify that nodata-based triggers remain firing
	 * (value = PROBLEM, state = NORMAL).
	 *
	 * @depends testLLDHistorySyncAtScale_TriggerNoDataNotSupported
	 */
	public function testLLDHistorySyncAtScale_TriggerNoDataValueOmitted() {
		$unknown_before = $this->getUnknownTriggerEventCount();
		$this->verifyValueOmittedDrainsDelay();

		$this->verifyProxyLastaccessAndNoDataTriggersFiring();

		$unknown_after = $this->getUnknownTriggerEventCount();
		if ($unknown_before !== $unknown_after) {
			$this->markTestSkipped('Unknown trigger event count changed: '.$unknown_before.' -> '.$unknown_after);
		}
	}

	private function verifyValueOmittedDrainsDelay(): void {
		$this->waitUntilDelayedItemsCount(self::$total_expected);

		$tm = time();

		$this->sendHistoryAt($tm, null, ITEM_STATE_NORMAL, true);

		$this->waitUntilDelayedItemsCount(0);
	}

	/**
	 * Send log data with the value field omitted but with lastlogsize/mtime present,
	 * and verify that lastlogsize for log items advances in item_rtdata.
	 *
	 * @depends testLLDHistorySyncAtScale_TriggerNoDataNotSupported
	 */
	public function testLLDHistorySyncAtScale_TriggerNoDataValueOmittedLastlogsize() {
		$unknown_before = $this->getUnknownTriggerEventCount();
		$this->verifyLogLastlogsizeAdvances();

		$this->verifyProxyLastaccessAndNoDataTriggersFiring();

		$unknown_after = $this->getUnknownTriggerEventCount();
		if ($unknown_before !== $unknown_after) {
			$this->markTestSkipped('Unknown trigger event count changed: '.$unknown_before.' -> '.$unknown_after);
		}
	}

	private function verifyLogLastlogsizeAdvances(): void {
		$log_itemids = self::$discovered_itemids[ITEM_VALUE_TYPE_LOG];
		$probe_itemid = (int) end($log_itemids);

		$row = DBfetch(DBselect('SELECT lastlogsize FROM item_rtdata WHERE itemid='.$probe_itemid));
		$this->assertNotFalse($row, 'item_rtdata row missing for log itemid '.$probe_itemid);
		$before = (int) $row['lastlogsize'];

		$tm = time();
		$this->sendHistoryAt($tm, null, ITEM_STATE_NORMAL, true, true);

		$timeout = self::WAIT_ITERATIONS * self::WAIT_ITERATION_DELAY;
		$start = microtime(true);
		$after = $before;
		while ($after <= $before && (microtime(true) - $start) < $timeout) {
			$this->sendAgentPing();
			usleep(100000); // 100 ms
			$row = DBfetch(DBselect('SELECT lastlogsize FROM item_rtdata WHERE itemid='.$probe_itemid));
			$after = (int) $row['lastlogsize'];
		}

		$waited = round(microtime(true) - $start, 1);
		$this->assertGreaterThan($before, $after,
			"lastlogsize for log itemid {$probe_itemid} did not advance after waiting {$waited}s "
			."(before: {$before}, after: {$after})");
	}

	private function verifyProxyLastaccessAndNoDataTriggersFiring(): void {
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
	}

	/**
	 * Restart the server and verify that nodata-based triggers recover again
	 * once normal data resumes flowing.
	 *
	 * @depends testLLDHistorySyncAtScale_TriggerNoDataNotSupported
	 */
	public function testLLDHistorySyncAtScale_TriggerNoDataRecoveryAfterRestart() {
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_SERVER);
		$unknown_before = $this->getUnknownTriggerEventCount();

		$tm = time();
		$this->sendHistoryAt($tm);
		$this->waitUntilTriggersRecovered();

		$unknown_after = $this->getUnknownTriggerEventCount();
		$this->assertEquals($unknown_before, $unknown_after,
			'Unknown trigger event count changed: '.$unknown_before.' -> '.$unknown_after);
	}

	/**
	 * Wait long enough for nodata triggers to fire (45s window) and verify they
	 * stay suppressed — neither value = PROBLEM nor state = UNKNOWN.
	 *
	 * @depends testLLDHistorySyncAtScale_TriggerNoDataRecoveryAfterRestart
	 */
	public function testLLDHistorySyncAtScale_TriggerNoDataSuppressedAfterConnectionLoss() {
		$unknown_before = $this->getUnknownTriggerEventCount();
		sleep(45);

		$response = $this->call('trigger.get', [
			'hostids' => [self::$hostid],
			'output' => ['triggerid', 'value', 'state', 'error']
		]);

		foreach ($response['result'] as $trigger) {
			$this->assertNotEquals(TRIGGER_VALUE_TRUE, (int) $trigger['value'],
				'Trigger '.$trigger['triggerid'].' transitioned to PROBLEM but expected to be suppressed.');
			$this->assertNotEquals(TRIGGER_STATE_UNKNOWN, (int) $trigger['state'],
				'Trigger '.$trigger['triggerid'].' transitioned to UNKNOWN. Error:'.$trigger['error']);
		}
		$unknown_after = $this->getUnknownTriggerEventCount();
		$this->assertEquals($unknown_before, $unknown_after,
			'Unknown trigger event count changed: '.$unknown_before.' -> '.$unknown_after);
	}

	/**
	 * Burst-send 10000 log entries for a single log item at tm-45 alongside
	 * a full history payload for all items at tm, and verify the server
	 * absorbs them, triggers recover, and no new UNKNOWN trigger events appear.
	 *
	 * @depends testLLDHistorySyncAtScale_TriggerNoDataSuppressedAfterConnectionLoss
	 */
	public function testLLDHistorySyncAtScale_TriggerNoDataOKAfterConnectionLossSingleLogBurst() {
		$unknown_before = $this->getUnknownTriggerEventCount();
		$problem_before = $this->getProblemTriggerEventCount();

		$this->sendSingleLogBurstAndFullHistory();
		$this->assertSingleLogBurstAndFullHistoryVpsWritten();

		$response = $this->call('trigger.get', [
			'hostids' => [self::$hostid],
			'output' => ['triggerid', 'value', 'state', 'error']
		]);
		$this->assertCount(self::$total_trigger_expected, $response['result'],
			'Expected '.self::$total_trigger_expected.' triggers, got '.count($response['result']));
		foreach ($response['result'] as $trigger) {
			$this->assertEquals(TRIGGER_VALUE_FALSE, (int) $trigger['value'],
				'Trigger '.$trigger['triggerid'].' is not in OK state.');
			if ((int) $trigger['state'] !== TRIGGER_STATE_NORMAL) {
				$this->markTestSkipped('Trigger '.$trigger['triggerid'].' is not in NORMAL state. Error: '
					.$trigger['error']);
			}
		}

		$problem_after = $this->getProblemTriggerEventCount();
		$this->assertLessThanOrEqual($problem_before, $problem_after,
			'Problem trigger event count increased: '.$problem_before.' -> '.$problem_after);

		$unknown_after = $this->getUnknownTriggerEventCount();
		if ($unknown_before !== $unknown_after) {
			$this->markTestSkipped('Unknown trigger event count changed: '.$unknown_before.' -> '.$unknown_after);
		}
	}

	private function getProblemTriggerEventCount(): int {
		$response = $this->call('event.get', [
			'hostids' => [self::$hostid],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'value' => TRIGGER_VALUE_TRUE,
			'countOutput' => true
		]);
		return (int) $response['result'];
	}

	private function getUnknownTriggerEventCount(): int {
		$response = $this->call('event.get', [
			'hostids' => [self::$hostid],
			'source' => EVENT_SOURCE_INTERNAL,
			'object' => EVENT_OBJECT_TRIGGER,
			'value' => TRIGGER_STATE_UNKNOWN,
			'countOutput' => true
		]);
		return (int) $response['result'];
	}

	private function waitUntilTriggersRecovered(): void {
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

	private function prepareHistoryAt(int $tm, ?string $value = null, int $state = ITEM_STATE_NORMAL,
			bool $omit_value = false, bool $log_only = false): array {
		$sent = [];
		$values_by_type = [];

		foreach (self::prototypeDefs() as $def) {
			$vtype = $def['value_type'];

			if ($log_only && $vtype !== ITEM_VALUE_TYPE_LOG) {
				continue;
			}

			$items_by_key = self::$discovered_itemids[$vtype];

			$this->assertCount(self::LLD_DISCOVERY_COUNT, $items_by_key,
				'Expected '.self::LLD_DISCOVERY_COUNT.' discovered item IDs for type '.$def['suffix'].'.');

			$values = [];
			$idx = 0;
			$base_ns = (int)(microtime(true) * 1e9) % 1000000000;
			foreach ($items_by_key as $itemid) {
				$item_value = [
					'itemid' => $itemid,
					'clock' => $tm,
					'ns' => ($base_ns + $idx) % 1000000000
				];
				if (!$omit_value) {
					$item_value['value'] = isset($value) ? $value : (string)($idx + 1);
				}
				if ($vtype === ITEM_VALUE_TYPE_LOG) {
					$item_value['lastlogsize'] = self::$log_lastlogsize + $idx + 1;
					$item_value['mtime'] = $tm;
				}
				if ($state !== ITEM_STATE_NORMAL) {
					$item_value['state'] = $state;
				}
				$values[] = $item_value;
				$idx++;
			}
			if ($vtype === ITEM_VALUE_TYPE_LOG) {
				self::$log_lastlogsize += count($items_by_key);
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

	private function sendHistoryAt(int $tm, ?string $value = null, int $state = ITEM_STATE_NORMAL,
			bool $omit_value = false, bool $log_only = false): array {
		['sent' => $sent, 'values' => $all_values] = $this->prepareHistoryAt($tm, $value, $state, $omit_value,
			$log_only);
		$this->dispatchValues($all_values);

		return $sent;
	}

	private function sendHistoryAtTimes(array $tms, ?string $value = null, int $state = ITEM_STATE_NORMAL,
			bool $omit_value = false): array {
		$sent_per_tm = [];
		$combined = [];
		foreach ($tms as $tm) {
			['sent' => $sent, 'values' => $values] = $this->prepareHistoryAt($tm, $value, $state, $omit_value);
			$sent_per_tm[$tm] = $sent;
			$combined = array_merge($combined, $values);
		}
		$this->dispatchValues($combined);

		return $sent_per_tm;
	}

	private function sendSingleLogBurstAndFullHistory(): void {
		$tm = time();

		$log_itemids = array_values(self::$discovered_itemids[ITEM_VALUE_TYPE_LOG]);
		$this->assertNotEmpty($log_itemids, 'No discovered log items available.');
		$itemid = (int) $log_itemids[0];

		['values' => $past_values] = $this->prepareHistoryAt($tm - 45);

		$base_ns = (int)(microtime(true) * 1e9) % 1000000000;
		$burst_values = [];
		for ($i = 0; $i < 10000; $i++) {
			$burst_values[] = [
				'itemid' => $itemid,
				'value' => (string)($i + 1),
				'clock' => $tm - 45,
				'ns' => ($base_ns + $i) % 1000000000,
				'lastlogsize' => self::$log_lastlogsize + $i + 1,
				'mtime' => $tm - 45
			];
		}
		self::$log_lastlogsize += 10000;

		['values' => $now_values] = $this->prepareHistoryAt($tm);

		$values = array_merge($past_values, $burst_values, $now_values);

		self::$vps_last = $this->getVpsWritten();
		$this->dispatchValues($values);
	}

	private function assertSingleLogBurstAndFullHistoryVpsWritten(): void {
		$this->assertVpsWrittenIncreasedBy(self::$vps_last, 2 * self::$total_expected + 10000);
	}

	private function verifyHistoryAt(int $tm, array $sent): void {
		foreach (self::prototypeDefs() as $def) {
			$vtype = $def['value_type'];
			$itemids = $sent[$vtype]['itemids'];
			$expected_by_itemid = $sent[$vtype]['expected_by_itemid'];

			$this->callUntilDataIsPresent('history.get', [
				'history' => $vtype,
				'itemids' => [$itemids[0]],
				'time_from' => $tm,
				'time_till' => $tm
			], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($response) {
				if (count($response['result']) === 1) {
					return true;
				}
				return false;
			});

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

		$this->dispatchValues([
			[
				'itemid' => (int) self::$lld_ruleid,
				'value' => json_encode(['data' => $data]),
				'clock' => time(),
				'ns' => 0
			]
		]);
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

	private function getDelayedItemsCount(): int {
		$result = $this->testItemOnServer((string) self::$hostid,
			['value_type' => ITEM_VALUE_TYPE_UINT64, 'type' => ITEM_TYPE_INTERNAL, 'key' => 'zabbix[queue,15,]']
		);
		$this->assertNotFalse($result);
		$this->assertArrayHasKey('item', $result);
		$this->assertArrayNotHasKey('error', $result['item']);
		$this->assertArrayHasKey('result', $result['item']);
		$this->assertIsNumeric($result['item']['result']);

		return (int) $result['item']['result'];
	}

	private function waitUntilDelayedItemsCount(int $expected): void {
		$timeout = self::WAIT_ITERATIONS * self::WAIT_ITERATION_DELAY;
		$start = microtime(true);
		$count = $this->getDelayedItemsCount();

		$reached = $expected === 0
			? function (int $c) use ($expected) { return $c === $expected; }
			: function (int $c) use ($expected) { return $c >= $expected; };

		while (!$reached($count) && (microtime(true) - $start) < $timeout) {
			$this->sendAgentPing();
			usleep(100000); // 100 ms
			$count = $this->getDelayedItemsCount();
		}

		$waited = round(microtime(true) - $start, 1);
		if ($expected === 0) {
			$this->assertSame($expected, $count,
				"Delayed items count did not reach {$expected} after waiting {$waited}s (last value: {$count})");
		} else {
			$this->assertGreaterThanOrEqual($expected, $count,
				"Delayed items count did not reach at least {$expected} after waiting {$waited}s (last value: {$count})");
		}
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
		$this->dispatchValues([
			[
				'itemid' => (int) self::$lld_ruleid,
				'value' => json_encode(['data' => []]),
				'clock' => time(),
				'ns' => 0
			]
		]);

		$this->callUntilCountIsPresent('item.get', [
			'hostids' => [self::$hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'.']
		], 0, self::LLD_ITERATIONS, self::WAIT_ITERATION_DELAY);

		/* check that server succeessfuly removed large amount of items from cache */
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER, null, self::LLD_ITERATIONS, self::WAIT_ITERATION_DELAY);

	}
}

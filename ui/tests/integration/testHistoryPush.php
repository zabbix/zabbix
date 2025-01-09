<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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
require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * Test suite for history.push API methods (pushing of history)
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_history_push1,test_history_push_non_monitored,test_history_push_maintained
 * @backup history,items,hosts,history_uint,history_text,history_str,history_log,ids
 */
class testHistoryPush extends CIntegrationTest {
	const HOSTNAME1 = 'test_history_push1';

	private static $hostid_normal;
	private static $hostid_non_monitored;
	private static $hostid_maintained;
	private static $itemids;

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "test_history_push1"
		$response = $this->call('host.create', [
			[
				'host' => self::HOSTNAME1,
				'interfaces' => [],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid_normal = $response['result']['hostids'][0];

		// Create host "test_history_push_non_monitored"
		$response = $this->call('host.create', [
			[
				'host' => "test_history_push_non_monitored",
				'interfaces' => [],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_NOT_MONITORED
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid_non_monitored = $response['result']['hostids'][0];

		// Create host "test_history_push_maintained"
		$response = $this->call('host.create', [
			[
				'host' => "test_history_push_maintained",
				'interfaces' => [],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid_maintained = $response['result']['hostids'][0];

		// Create items
		$items = [
			[
				'key_' => 'calc_item',
				'type' => ITEM_TYPE_CALCULATED,
				'params' => '1',
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => '3s'
			],
			[
				'key_' => 'trapper_uint',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			[
				'key_' => 'trapper_uint2',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			[
				'key_' => 'trapper_uint_host_key_test',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			[
				'key_' => 'trapper_uint_no_perms',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			[
				'key_' => 'trapper_uint_bad_valuetype',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			[
				'key_' => 'trapper_uint_disabled',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'status' => ITEM_STATUS_DISABLED
			],
			[
				'key_' => 'trapper_float',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			[
				'key_' => 'trapper_log',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_LOG
			],
			[
				'key_' => 'trapper_text',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			[
				'key_' => 'trapper_str',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			[
				'key_' => 'trapper_text_bad_allow_hosts',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'trapper_hosts' => 'non.existent.host'
			],
			[
				'key_' => 'http_agent_text',
				'type' => ITEM_TYPE_HTTPAGENT,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'url' => 'http://127.0.0.1:7123/httptest',
				'delay' => '10s',
				'allow_traps' => 1
			],
			[
				'key_' => 'http_agent_text_no_trap',
				'type' => ITEM_TYPE_HTTPAGENT,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'url' => 'http://127.0.0.1:7123/httptest',
				'delay' => '10s',
				'allow_traps' => 0
			]
		];

		foreach ($items as $item) {
			$item['name'] = $item['key_'];
			$item['hostid'] = self::$hostid_normal;

			$response = $this->call('item.create', $item);
			$this->assertArrayHasKey('itemids', $response['result']);
			$this->assertEquals(1, count($response['result']['itemids']));

			$itemid = $response['result']['itemids'][0];
			self::$itemids[$item['key_']] = $itemid;
		}

		$items_nonavail_hosts = [
			[
				'key_' => 'trapper_uint_non_monitored_host',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'hostid' => self::$hostid_non_monitored
			],
			[
				'key_' => 'trapper_uint_maintained_host',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'hostid' => self::$hostid_maintained
			],
			[
				'key_' => 'trapper_uint_no_perms_host',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'hostid' => self::$hostid_normal
			]
		];

		foreach ($items_nonavail_hosts as $item) {
			$item['name'] = $item['key_'];

			$response = $this->call('item.create', $item);
			$this->assertArrayHasKey('itemids', $response['result']);
			$this->assertEquals(1, count($response['result']['itemids']));

			$itemid = $response['result']['itemids'][0];
			self::$itemids[$item['key_']] = $itemid;
		}

		return true;
	}

	/**
	 * Component configuration provider for agent related tests.
	 *
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'LogFileSize' => 0,
				'DebugLevel' => 5
			]
		];
	}

	/**
	 * Push value of every type.
	 *
	 * @backup history_uint,history_text,history,history_str,history_log
	 */
	public function testHistoryPush_pushSingleTrapperValue() {
		$tcs = [
			[
				'itemid' => self::$itemids['trapper_uint'],
				'value' => 1,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			[
				'itemid' => self::$itemids['trapper_log'],
				'value' => 'somelog',
				'value_type' => ITEM_VALUE_TYPE_LOG
			],
			[
				'itemid' => self::$itemids['trapper_float'],
				'value' => 1.7,
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			[
				'itemid' => self::$itemids['trapper_str'],
				'value' => 'somestr',
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			[
				'itemid' => self::$itemids['http_agent_text'],
				'value' => 'sometext',
				'value_type' => ITEM_VALUE_TYPE_TEXT
			]
		];

		foreach ($tcs as $tc) {
			$value_sent = [
				'itemid' => $tc['itemid'],
				'value' => $tc['value'],
				'clock' => time() - 25,
				'ns' => 255
			];

			$response = $this->call('history.push', [
				$value_sent
			]);

			$this->assertArrayHasKey('data', $response['result']);
			$this->assertEquals(1, count($response['result']['data']));
			$this->assertArrayHasKey('itemid', $response['result']['data'][0]);
			$this->assertEquals($tc['itemid'], $response['result']['data'][0]['itemid']);
			$this->assertArrayNotHasKey('error', $response['result']['data'][0]);

			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In trapper_process_history_push', true, 95, 3);
			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of trapper_process_history_push', true, 95, 3);
			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of DBmass_add_history', true, 95, 3);

			$response = $this->call('history.get', [
				'output' => ['itemid', 'value', 'clock', 'ns'],
				'itemids' => [$tc['itemid']],
				'sortorder' => 'DESC',
				'sortfield' => 'clock',
				'limit' => 1,
				'history' => $tc['value_type']
			]);
			$this->assertEquals(1, count($response['result']));
			$value_retrieved = $response['result'][0];

			foreach (array_keys($value_retrieved) as $i) {
				$this->assertEquals(strval($value_sent[$i]), $value_retrieved[$i]);
			}
		}

		return true;
	}

	public function testHistoryPush_pushSingleValueHostKey() {
		$value_sent = [
			'host' => self::HOSTNAME1,
			'key' => 'trapper_uint_host_key_test',
			'value' => 123,
			'clock' => time() - 25,
			'ns' => 500
		];

		$response = $this->call('history.push', $value_sent);

		$this->assertArrayHasKey('data', $response['result']);
		$this->assertEquals(1, count($response['result']['data']));
		$this->assertArrayHasKey('itemid', $response['result']['data'][0]);
		$this->assertEquals(self::$itemids['trapper_uint_host_key_test'], $response['result']['data'][0]['itemid']);
		$this->assertArrayNotHasKey('error', $response['result']['data'][0]);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In trapper_process_history_push', true, 95, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of trapper_process_history_push', true, 95, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of DBmass_add_history', true, 95, 3);

		$response = $this->call('history.get', [
			'output' => ['value', 'clock', 'ns'],
			'itemids' => [self::$itemids['trapper_uint_host_key_test']],
			'sortorder' => 'DESC',
			'sortfield' => 'clock',
			'limit' => 1
		]);
		$this->assertEquals(1, count($response['result']));
		$value_retrieved = $response['result'][0];
		$this->assertEquals($value_sent['value'], $value_retrieved['value']);
		$this->assertEquals($value_sent['clock'], $value_retrieved['clock']);
		$this->assertEquals($value_sent['ns'], $value_retrieved['ns']);

		return true;
	}

	/**
	 * Push multiple values of different types in single request.
	 *
	 * @backup history_uint, history_text
	 */
	public function testHistoryPush_pushMultipleValues() {
		$values_sent_uint = [];
		$values_sent_text = [];
		$values_sent = [];
		$idx = 0;

		for (; $idx < 5; $idx++) {
			$values_sent_uint[] = [
				'itemid' => self::$itemids['trapper_uint2'],
				'value' => 1 + $idx,
				'clock' => time() - 20 + $idx,
				'ns' => intval(time() / 10000 + 25 * $idx)
			];
		}
		for (; $idx < 10; $idx++) {
			$values_sent_text[] = [
				'itemid' => self::$itemids['trapper_text'],
				'value' => "zabbix$idx",
				'clock' => time() - 15 + $idx,
				'ns' => intval(time() / 10000 + 25 * $idx)
			];
		}

		$values_sent = array_merge($values_sent_uint, $values_sent_text);

		$response = $this->call('history.push', $values_sent);

		$this->assertArrayHasKey('data', $response['result']);
		$this->assertEquals(count($values_sent), count($response['result']['data']));
		foreach ($response['result']['data'] as $rec) {
			$this->assertFalse(array_key_exists('error', $rec),
				"key 'error' exists in a response (itemid: ".$rec['itemid'].")");
		}

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In trapper_process_history_push', true, 95, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of trapper_process_history_push', true, 95, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of DBmass_add_history', true, 95, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of DBmass_add_history', true, 95, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of DBmass_add_history', true, 95, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of DBmass_add_history', true, 95, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of DBmass_add_history', true, 95, 3);

		$response = $this->call('history.get', [
			'output' => ['itemid', 'value', 'clock', 'ns'],
			'history' => ITEM_VALUE_TYPE_TEXT,
			'itemids' => self::$itemids['trapper_text'],
			'sortfield' => 'clock',
			'sortorder' => 'ASC'
		]);
		$this->assertEquals($values_sent_text, $response['result']);

		$response = $this->call('history.get', [
			'output' => ['itemid', 'value', 'clock', 'ns'],
			'itemids' => self::$itemids['trapper_uint2'],
			'sortfield' => 'clock',
			'sortorder' => 'ASC'
		]);
		$this->assertEquals($values_sent_uint, $response['result']);

		return true;
	}

	public function testHistoryPush_serverIsDown() {
		$this->stopComponent(self::COMPONENT_SERVER);

		// CAPITest::call() has assertions for 'result' key in a response, these will throw an exception
		$this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);

		$response = $this->call('history.push', [
			'itemid' => self::$itemids['trapper_uint'],
			'value' => 1,
			'clock' => time() - 25,
			'ns' => 255
		]);
	}

	public function testHistoryPush_httpAgentTrappingDisabled() {
		$response = $this->call('history.push', [
			'itemid' => self::$itemids['http_agent_text_no_trap'],
			'value' => 'a',
			'clock' => time() - 25,
			'ns' => 255
		]);

		$this->assertArrayHasKey('data', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['data']);
		$this->assertArrayHasKey('error', $response['result']['data'][0]);
	}

	public function testHistoryPush_invalidItemid() {
		$response = $this->call('history.push', [
			'itemid' => 99999,
			'value' => 1,
			'clock' => time() - 25,
			'ns' => 255
		]);

		$this->assertArrayHasKey('data', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['data']);
		$this->assertArrayHasKey('error', $response['result']['data'][0]);
	}

	public function testHistoryPush_disabledItem() {
		$response = $this->call('history.push', [
			'itemid' => self::$itemids['trapper_uint_disabled'],
			'value' => 1,
			'clock' => time() - 25,
			'ns' => 255
		]);

		$this->assertArrayHasKey('data', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['data']);
		$this->assertArrayHasKey('error', $response['result']['data'][0]);
	}

	public function testHistoryPush_notSupportedItemType() {
		$response = $this->call('history.push', [
			'itemid' => self::$itemids['calc_item'],
			'value' => 'x',
			'clock' => time() - 25,
			'ns' => 255
		]);

		$this->assertArrayHasKey('data', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['data']);
		$this->assertArrayHasKey('error', $response['result']['data'][0]);
	}

	public function testHistoryPush_nonMonitoredHost() {
		$response = $this->call('host.update', [
			'hostid' => self::$hostid_non_monitored,
			'status' => 1
		]);

		$this->reloadConfigurationCache();
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_dc_sync_configuration()', true);

		$response = $this->call('history.push', [
			'itemid' => self::$itemids['trapper_uint_non_monitored_host'],
			'value' => 999,
			'clock' => time() - 25,
			'ns' => 255
		]);

		$this->assertArrayHasKey('data', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['data']);
		$this->assertArrayHasKey('error', $response['result']['data'][0]);
	}

	public function testHistoryPush_hostUnderMaintenance() {
		$maint_start_tm = time();
		$maint_end_tm = $maint_start_tm + 60 * 2;

		$response = $this->call('maintenance.create', [
			'name' => 'Test maintenance',
			'hosts' => ['hostid' => self::$hostid_maintained],
			'active_since' => $maint_start_tm,
			'active_till' => $maint_end_tm,
			'maintenance_type' => MAINTENANCE_TYPE_NODATA,
			'timeperiods' => [
				'timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
				'period' => 300,
				'start_date' => $maint_start_tm
			]
		]);

		$this->assertArrayHasKey('maintenanceids', $response['result']);
		$this->assertEquals(1, count($response['result']['maintenanceids']));
		$maintenance_id = $response['result']['maintenanceids'][0];

		$this->reloadConfigurationCache();
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_dc_sync_configuration()', true);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_dc_update_maintenances() started:1 stopped:0 running:1', true);

		$response = $this->call('history.push', [
			'itemid' => self::$itemids['trapper_uint_maintained_host'],
			'value' => 12345,
			'clock' => $maint_start_tm,
			'ns' => 255
		]);

		$this->assertArrayHasKey('data', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['data']);
		$this->assertArrayHasKey('error', $response['result']['data'][0]);
	}

	public function testHistoryPush_clientNotInAllowedHosts() {
		$response = $this->call('history.push', [
			'itemid' => self::$itemids['trapper_text_bad_allow_hosts'],
			'value' => 12345,
			'clock' => time() - 25,
			'ns' => 255
		]);

		$this->assertArrayHasKey('data', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['data']);
		$this->assertArrayHasKey('error', $response['result']['data'][0]);
	}

	public function testHistoryPush_noPermission() {
		$response = $this->call('user.create', [
			'username' => 'John',
			'passwd' => 'Doe123123',
			'roleid' => 1,
			'usrgrps' => [['usrgrpid' => 8]]

		]);
		$this->assertArrayHasKey('userids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['userids']);
		$userid = $response['result']['userids'][0];

		$this->authorize('John', 'Doe123123');
		$value_sent = [
			'itemid' => self::$itemids['trapper_uint_no_perms'],
			'value' => 1,
			'clock' => time() - 25,
			'ns' => 255
		];

		$response = $this->call('history.push', [
			$value_sent
		]);

		$this->assertArrayHasKey('data', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['data']);
		$this->assertArrayHasKey('error', $response['result']['data'][0]);
	}

	public function testHistoryPush_duplicateTimestamp() {
		$tm = time();

		$response = $this->call('history.push', [
			[
			'itemid' => self::$itemids['trapper_uint'],
			'value' => 1,
			'clock' => $tm,
			'ns' => 500
			],
			[
			'itemid' => self::$itemids['trapper_uint'],
			'value' => 0,
			'clock' => $tm,
			'ns' => 500
			]
		]);

		$this->assertEquals(2, count($response['result']['data']));
		$this->assertArrayNotHasKey('error', $response['result']['data'][0]);
		$this->assertArrayHasKey('error', $response['result']['data'][1]);
	}

	public function testHistoryPush_sequentialDuplicatedTimestamps() {
		if (CAPIHelper::getSessionId() === null) {
			$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		}

		$response1 = CAPIHelper::call('history.push', [
			[
				'itemid' => self::$itemids['trapper_uint'],
				'value' => 10001
			],
			[
				'itemid' => self::$itemids['trapper_uint'],
				'value' => 10002
			]
		]);

		$response2 = CAPIHelper::call('history.push', [
			[
				'itemid' => self::$itemids['trapper_uint'],
				'value' => 10003
			],
			[
				'itemid' => self::$itemids['trapper_uint'],
				'value' => 10004
			]
		]);

		$this->checkResult($response1);
		$this->checkResult($response2);

		$response = $this->call('history.get', [
			'output' => ['itemid', 'value', 'clock', 'ns'],
			'itemids' => self::$itemids['trapper_uint'],
			'sortfield' => ['clock', 'ns'],
			'limit' => 1,
			'sortorder' => 'DESC'
		]);
		$this->assertEquals(1, count($response['result']));
	}

	public function testHistoryPush_malformedRequest() {
		// CAPITest::call() has assertions for 'result' key in a response, these will throw an exception
		$this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);

		$response = $this->call('history.push', [
			[
			'host' => "10000",
			'value' => 1,
			'clock' => time(),
			'ns' => 500
			]
		]);
	}
}

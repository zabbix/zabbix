<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';

define('REF_ACT_CHKS_INTERVAL', 60);
define('PROCESS_ACT_CHKS_DELAY', 60);
define('PSV_FILE_NAME', '/tmp/some_temp_file_psv');
define('ACT_FILE_NAME', '/tmp/some_temp_file_act');

/**
 * Test suite for items state change verification.
 *
 * @required-components server, agent
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_host
 * @backup history
 */
class testItemState extends CIntegrationTest {

	private static $hostid;
	private static $interfaceid;

	private static $items = [
		'zbx_psv_01' => [
			'key' => 'vfs.file.contents['.PSV_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX
		],
		'zbx_act_01' => [
			'key' => 'vfs.file.contents['.ACT_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE
		]
	];

	private static $scenarios = [
		[
			'name' => 'zbx_psv_01',
			'delay_s' => 7,
			'refresh_unsupported' => 10,
			'after_sync' => false
		],
		[
			'name' => 'zbx_psv_01',
			'delay_s' => 17,
			'refresh_unsupported' => 10,
			'after_sync' => false
		],
		[
			'name' => 'zbx_psv_01',
			'delay_s' => 7,
			'refresh_unsupported' => 10,
			'after_sync' => true
		],
		[
			'name' => 'zbx_psv_01',
			'delay_s' => 17,
			'refresh_unsupported' => 10,
			'after_sync' => true
		],
		[
			'name' => 'zbx_act_01',
			'delay_s' => 10,
			'refresh_unsupported' => 90
		],
		[
			'name' => 'zbx_act_01',
			'delay_s' => 90,
			'refresh_unsupported' => 10
		],
		[
			'name' => 'zbx_act_01',
			'delay_s' => 10,
			'refresh_unsupported' => 20
		],
		[
			'name' => 'zbx_act_01',
			'delay_s' => 10,
			'refresh_unsupported' => 50
		],
		[
			'name' => 'zbx_act_01',
			'delay_s' => 40,
			'refresh_unsupported' => 20
		],
		[
			'name' => 'zbx_act_01',
			'delay_s' => 40,
			'refresh_unsupported' => 60
		]
	];

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "test_host"
		$interfaces = [
			[
				'type' => 1,
				'main' => 1,
				'useip' => 1,
				'ip' => '127.0.0.1',
				'dns' => '',
				'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
			]
		];

		$response = $this->call('host.create', [
			[
				'host' => 'test_host',
				'interfaces' => $interfaces,
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_NOT_MONITORED
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		// Get host interface ids.
		$response = $this->call('host.get', [
			'output' => ['host'],
			'hostids' => [self::$hostid],
			'selectInterfaces' => ['interfaceid']
		]);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('interfaces', $response['result'][0]);
		$this->assertArrayHasKey(0, $response['result'][0]['interfaces']);
		self::$interfaceid = $response['result'][0]['interfaces'][0]['interfaceid'];

		// Create items
		foreach (self::$items as $key => $item) {
			$items[] = [
				'name' => $key,
				'key_' => $item['key'],
				'type' => $item['type'],
				'hostid' => self::$hostid,
				'interfaceid' => self::$interfaceid,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => '1s',
				'status' => ITEM_STATUS_DISABLED
			];
		}

		$response = $this->call('item.create', $items);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(count($items), count($response['result']['itemids']));
		$itemids = $response['result']['itemids'];
		$id = 0;

		foreach (self::$items as &$item) {
			$item['itemid'] = $itemids[$id++];
		}

		file_put_contents(PSV_FILE_NAME, '1');
		file_put_contents(ACT_FILE_NAME, '1');

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
				'DebugLevel' => 4,
				'LogFileSize' => 20
			],
			self::COMPONENT_AGENT => [
				'Hostname'  => 'test_host',
				'ServerActive'  => '127.0.0.1',
				'RefreshActiveChecks' => REF_ACT_CHKS_INTERVAL,
				'BufferSend' => 1
			]
		];
	}

	/**
	 * Routine to prepare item.
	 */
	public function prepareItem($itemid, $delay) {
		// Disable all items
		foreach (self::$items as $item) {
			$response = $this->call('item.update', [
				'itemid' => $item['itemid'],
				'status' => ITEM_STATUS_DISABLED
			]);

			$this->assertEquals($item['itemid'], $response['result']['itemids'][0]);
		}

		$this->reloadConfigurationCache();

		// Clear log
		$this->clearLog(self::COMPONENT_SERVER);

		// Enable item
		$response = $this->call('item.update', [
			'itemid' => $itemid,
			'status' => ITEM_STATUS_ACTIVE,
			'delay' => $delay,
		]);

		$this->assertEquals($itemid, $response['result']['itemids'][0]);
		$this->reloadConfigurationCache();
	}

	/**
	 * Routine to check item state and intervals.
	 */
	public function checkItemStatePassive($scenario, $state) {
		if ($scenario['after_sync'] === true || $state === ITEM_STATE_NORMAL) {
			$delay = $scenario['delay_s'];
		} else {
			$delay = $scenario['refresh_unsupported'];
		}

		$wait = $delay + 60;
		$key = self::$items[$scenario['name']]['key'];

		// Wait for item to be checked
		$first_check = true;
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, ["In get_value() key:'".$key."'"], true, $wait, 1, $first_check);

		// Check item state
		sleep(1);

		$response = $this->call('item.get', [
			'itemids' => self::$items[$scenario['name']]['itemid'],
			'output' => ['state']
		]);

		$this->assertEquals($state, $response['result'][0]['state'], 'Unexpected item state='.$response['result'][0]['state'].' (expected='.$state.')');

		// Verify item checks intervals
		$check = true;
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, ["In get_value() key:'".$key."'"], true, $wait, 1, $check);
		$this->assertTrue($check <= $first_check + $delay + 1);

		$next_check = true;
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, ["In get_value() key:'".$key."'"], true, $wait, 1, $next_check);
		$this->assertTrue($next_check <= $check + $delay + 1 && $next_check >= $check + $delay - 1);
	}

	/**
	 * Routine to check item state and intervals (active agent items).
	 */
	public function checkItemStateActive($scenario, $state, &$refresh) {
		$wait = max($scenario['delay_s'], $scenario['refresh_unsupported'], REF_ACT_CHKS_INTERVAL) + PROCESS_ACT_CHKS_DELAY + 60;
		$key = self::$items[$scenario['name']]['key'];

		// Wait for item to be checked
		$check = true;
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, [',"data":[{"host":"test_host","key":"'.$key.'","value":"'], true, $wait, 1, $check);

		// Update last refresh timestamp
		while ($check > $refresh + REF_ACT_CHKS_INTERVAL) {
			$refresh += REF_ACT_CHKS_INTERVAL;
		}

		// Check item state and read update interval
		sleep(1);

		$response = $this->call('item.get', [
			'itemids' => self::$items[$scenario['name']]['itemid'],
			'output' => ['state']
		]);

		$this->assertEquals($state, $response['result'][0]['state'], 'Unexpected item state='.$response['result'][0]['state'].' (expected='.$state.')');

		// Verify item checks intervals
		$next_check = true;
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, [',"data":[{"host":"test_host","key":"'.$key.'","value":"'], true, $wait, 1, $next_check);

		while ($next_check > $refresh + REF_ACT_CHKS_INTERVAL) {
			$refresh += REF_ACT_CHKS_INTERVAL;
		}

		if ($state === ITEM_STATE_NOTSUPPORTED) {
			$exp_nextcheck_item = $check + $scenario['refresh_unsupported'];
			while ($refresh < $exp_nextcheck_item) {
				$refresh += REF_ACT_CHKS_INTERVAL;
			}

			$exp_nextcheck_process = $check;
			while ($exp_nextcheck_process < $refresh) {
				$exp_nextcheck_process += PROCESS_ACT_CHKS_DELAY;
			}

			if ($scenario['delay_s'] > PROCESS_ACT_CHKS_DELAY) {
				$exp_nextcheck_item = $check;

				while ($exp_nextcheck_item < $exp_nextcheck_process) {
					$exp_nextcheck_item += $scenario['delay_s'];
				}
			} else {
				$exp_nextcheck_item = $exp_nextcheck_process;
			}

			$this->assertTrue($next_check <= $exp_nextcheck_item + 1 && $next_check >= $exp_nextcheck_item - 1);
		} else {
			$this->assertTrue($next_check <= $check + $scenario['delay_s'] + 1 && $next_check >= $check + $scenario['delay_s'] - 1);
		}

		return $refresh;
	}

	/**
	 * Data provider (passive checks).
	 *
	 * @return array
	 */
	public function getDataPassive() {
		$scenarios = [];
		foreach (self::$scenarios as $scenario) {
			if (self::$items[$scenario['name']]['type'] === ITEM_TYPE_ZABBIX_ACTIVE) {
				continue;
			}

			$scenarios[] = [$scenario];
		}

		return $scenarios;
	}

	/**
	 * Data provider (active checks).
	 *
	 * @return array
	 */
	public function getDataActive() {
		$scenarios = [];
		foreach (self::$scenarios as $scenario) {
			if (self::$items[$scenario['name']]['type'] === ITEM_TYPE_ZABBIX_ACTIVE) {
				$scenarios[] = [$scenario];
			}
		}

		return $scenarios;
	}

	/**
	 * Test if item becomes supported/not supported within expected time span (passive checks).
	 *
	 * @dataProvider getDataPassive
	 */
	public function testItemState_checkPassive($data) {
		// Set refresh unsupported items interval
		DBexecute("UPDATE config SET refresh_unsupported='".$data['refresh_unsupported']."s' WHERE configid=1");

		// Prepare item
		$this->prepareItem(self::$items[$data['name']]['itemid'], $data['delay_s'].'s');

		// Check item state and intervals
		$this->checkItemStatePassive($data, ITEM_STATE_NORMAL);

		// Make item not supported
		if ($data['after_sync'] === true) {
			file_put_contents(PSV_FILE_NAME, 'text');
		} else {
			unlink(PSV_FILE_NAME);
		}

		// Check item state and intervals
		$this->checkItemStatePassive($data, ITEM_STATE_NOTSUPPORTED);

		// Make item supported
		file_put_contents(PSV_FILE_NAME, '1');

		// Check item state and intervals
		$this->checkItemStatePassive($data, ITEM_STATE_NORMAL);
	}

	/**
	 * Test if item becomes supported/not supported within expected time span (active checks).
	 *
	 * @dataProvider getDataActive
	 */
	public function testItemState_checkActive($data) {
		// Set refresh unsupported items interval
		DBexecute("UPDATE config SET refresh_unsupported='".$data['refresh_unsupported']."s' WHERE configid=1");

		// Prepare item
		$this->prepareItem(self::$items[$data['name']]['itemid'], $data['delay_s'].'s');

		// Wait for the refresh active checks
		$refresh_active = true;
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, ['trapper got \'{"request":"active checks","host":"test_host"}\''], true, REF_ACT_CHKS_INTERVAL + 5, 1, $refresh_active);

		// Check item state and intervals
		$this->checkItemStateActive($data, ITEM_STATE_NORMAL, $refresh_active);

		// Make item not supported
		unlink(ACT_FILE_NAME);

		// Check item state and intervals
		$this->checkItemStateActive($data, ITEM_STATE_NOTSUPPORTED, $refresh_active);

		// Make item supported
		file_put_contents(ACT_FILE_NAME, '1');

		// Check item state and intervals
		$this->checkItemStateActive($data, ITEM_STATE_NORMAL, $refresh_active);
	}
}

<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

/**
 * Test suite for low level discovery (LLD).
 *
 * @required-components server
 * @backup items
 */
class testLowLevelDiscovery extends CIntegrationTest {

	private static $hostid;
	private static $ruleid;

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "discovery".
		$response = $this->call('host.create', [
			'host' => 'discovery',
			'interfaces' => [
				[
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				]
			],
			'groups' => [
				[
					'groupid' => 4
				]
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		// Create discovery rule.
		$response = $this->call('discoveryrule.create', [
			'hostid' => self::$hostid,
			'name' => 'Trapper discovery',
			'key_' => 'item_discovery',
			'type' => ITEM_TYPE_TRAPPER
		]);

		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$ruleid = $response['result']['itemids'][0];

		// Create item prototype.
		$response = $this->call('itemprototype.create', [
			'hostid' => self::$hostid,
			'ruleid' => self::$ruleid,
			'name' => 'Item: {#KEY}',
			'key_' => 'trap[{#KEY}]',
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_TEXT
		]);

		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);

		return true;
	}

	/**
	 * Test discovery by checking creation of items from item prototype.
	 */
	public function testLowLevelDiscovery_DiscoverItems() {
		$items = [];

		for ($i = 1; $i < 10; $i++) {
			$items[] = ['{#KEY}' => 'item'.$i];

			// Send value to discovery trapper.
			$this->sendSenderValue('discovery', 'item_discovery', ['data' => $items]);

			// Retrieve data from API.
			$data = $this->call('item.get', [
				'hostids'	=> self::$hostid,
				'output'	=> ['name', 'key_', 'type', 'value_type'],
				'sortfield'	=> 'key_'
			]);

			$this->assertTrue(is_array($data['result']));
			$this->assertEquals($i, count($data['result']));

			foreach ($data['result'] as $n => $item) {
				$key = 'item'.($n + 1);

				$this->assertEquals('Item: '.$key, $item['name']);
				$this->assertEquals('trap['.$key.']', $item['key_']);
				$this->assertEquals(ITEM_TYPE_TRAPPER, $item['type']);
				$this->assertEquals(ITEM_VALUE_TYPE_TEXT, $item['value_type']);
			}
		}
	}

	/**
	 * Test discovery by checking that lost resources are deleted.
	 *
	 * @depends testLowLevelDiscovery_DiscoverItems
	 */
	public function testLowLevelDiscovery_LooseItems() {
		// Update lifetime of discovery rule.
		$this->call('discoveryrule.update', [
			'itemid' => self::$ruleid,
			'lifetime' => 0
		]);

		// Reload configuration cache.
		$this->reloadConfigurationCache();

		$key = 'item5';
		// Send value to discovery trapper.
		$this->sendSenderValue('discovery', 'item_discovery', ['data' => [['{#KEY}' => $key]]]);

		// Retrieve data from API.
		$data = $this->call('item.get', [
			'hostids'	=> self::$hostid,
			'output'	=> ['itemid', 'name', 'key_', 'type', 'value_type'],
			'sortfield'	=> 'key_'
		]);

		$this->assertTrue(is_array($data['result']));
		$this->assertEquals(1, count($data['result']));

		$item = $data['result'][0];
		$this->assertEquals('Item: '.$key, $item['name']);
		$this->assertEquals('trap['.$key.']', $item['key_']);
		$this->assertEquals(ITEM_TYPE_TRAPPER, $item['type']);
		$this->assertEquals(ITEM_VALUE_TYPE_TEXT, $item['value_type']);

		// Data from API is passed as an input to testLowLevelDiscovery_CheckDiscoveredItem.
		return $item;
	}

	/**
	 * Test discovery by checking that discovered item can receive data.
	 *
	 * @depends testLowLevelDiscovery_LooseItems
	 */
	public function testLowLevelDiscovery_CheckDiscoveredItem($item) {
		$from = time();
		$value = md5($from.rand()).'-'.microtime();

		// Send value to discovery trapper.
		$this->sendSenderValue('discovery', $item['key_'], $value);

		// Retrieve history data from API as soon it is available.
		$data = $this->callUntilDataIsPresent('history.get', [
			'itemids'	=> $item['itemid'],
			'history'	=> ITEM_VALUE_TYPE_TEXT
		]);

		$item = $data['result'][0];
		$this->assertEquals($value, $item['value']);
		$this->assertGreaterThanOrEqual($from, $item['clock']);
		$this->assertLessThanOrEqual(time(), $item['clock']);
	}
}

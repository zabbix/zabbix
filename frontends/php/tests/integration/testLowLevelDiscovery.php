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

/**
 * Test suite for low level discovery (LLD).
 *
 * @required-components server
 * @backup items
 * @hosts discovery
 */
class testLowLevelDiscovery extends CIntegrationTest {

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
				'hostids'	=> 20001,
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
			'itemid' => 80001,
			'lifetime' => 0
		]);

		// Reload configuration cache.
		$this->reloadConfigurationCache();

		$key = 'item5';
		// Send value to discovery trapper.
		$this->sendSenderValue('discovery', 'item_discovery', ['data' => [['{#KEY}' => $key]]]);

		// Retrieve data from API.
		$data = $this->call('item.get', [
			'hostids'	=> 20001,
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

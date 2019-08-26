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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * Tests API methods 'hostprototype.get' 'host.get'. It is tested that `inventory_mode` field acts as `host` and
 * `hostprototype` object field, having read, write, filter properties. Meanwhile value for this field is retrieved
 * from an associative field in `host_inventory` table.
 */
class testHostPrototypeInventoryGet extends CAPITest {

	/**
	 * Host prototype object has no inventory object (it used to have it).
	 * Assert that API does not return that.
	 */
	public function testHostPrototypeHasNoInventoryObject() {
		$method = 'hostprototype.get';
		$hostprototypeid = 50011;


		$response = $this->call($method,
			['output' => ['hostid'], 'hostids' => $hostprototypeid, 'selectInventory' => ['inventory_mode']]
		);

		$this->assertEquals(
			['hostid' => $hostprototypeid],
			CTestArrayHelper::get($response, 'result.0')
		);
	}

	/**
	 * @backup host_inventory
	 */
	public function testHostPrototypeRetrievesInventoryModeProperty() {
		$hostprototypeid = 50011;
		$this->call('hostprototype.update', [
			'hostid' => $hostprototypeid,
			'inventory_mode' => HOST_INVENTORY_MANUAL
		]);

		$response = $this->call('hostprototype.get', [
			'output' => ['inventory_mode'],
			'hostids' => $hostprototypeid
		], null);

		$this->assertEquals(
			CDBHelper::getValue('SELECT inventory_mode FROM host_inventory WHERE hostid='.$hostprototypeid),
			CTestArrayHelper::get($response, 'result.0.inventory_mode', HOST_INVENTORY_AUTOMATIC)
		);

		$this->assertEquals(
			CTestArrayHelper::get($response, 'result.0.inventory_mode', HOST_INVENTORY_AUTOMATIC),
			HOST_INVENTORY_MANUAL
		);
	}

	/**
	 * Field 'inventory_mode' value in fact is a value of a related inventory object.
	 *
	 * @backup host_inventory
	 */
	public function testHostInventoryOutputContainsNoExtraFields() {
		$hostid = 50009;

		$this->call('host.update', [
			'hostid' => $hostid,
			'inventory_mode' => HOST_INVENTORY_MANUAL,
			'inventory' => []
		]);

		$response = $this->call('host.get', [
			'output' => ['hostid'],
			'hostids' => [$hostid],
			'selectInventory' => ['type']
		]);

		$this->assertEquals(
			CTestArrayHelper::get($response, 'result.0'),
			['hostid' => $hostid, 'inventory' => ['type' => '']]
		);
	}

	/**
	 * Assert that filter does work for host and hostprototype having a related record and without.
	 */
	public function testItDoesFilterByInventoryModeValue() {
		$hostprototypeid = 50011;

		// Hostprototype with record.
		$this->call('hostprototype.update',
			['hostid' => $hostprototypeid, 'inventory_mode' => HOST_INVENTORY_MANUAL,]
		);

		$response = $this->call('hostprototype.get', [
			'output' => ['inventory_mode'],
			'hostids' => [$hostprototypeid],
			'filter' => ['inventory_mode' => HOST_INVENTORY_MANUAL]
		]);
		$this->assertNotNull(CTestArrayHelper::get($response, 'result.0'));

		$response = $this->call('hostprototype.get', [
			'output' => ['inventory_mode'],
			'hostids' => [$hostprototypeid],
			'filter' => ['inventory_mode' => HOST_INVENTORY_DISABLED]
		]);
		$this->assertNull(CTestArrayHelper::get($response, 'result.0'));

		$response = $this->call('hostprototype.get', [
			'output' => ['inventory_mode'],
			'hostids' => [$hostprototypeid],
			'filter' => ['inventory_mode' => HOST_INVENTORY_AUTOMATIC]
		]);
		$this->assertNull(CTestArrayHelper::get($response, 'result.0'));

		// Hostprototype without record.
		$this->call('hostprototype.update',
			['hostid' => $hostprototypeid, 'inventory_mode' => HOST_INVENTORY_DISABLED]
		);

		$response = $this->call('hostprototype.get', [
			'output' => ['inventory_mode'],
			'hostids' => [$hostprototypeid],
			'filter' => ['inventory_mode' => HOST_INVENTORY_DISABLED]
		]);
		$this->assertNotNull(CTestArrayHelper::get($response, 'result.0'));

		$response = $this->call('hostprototype.get', [
			'output' => ['inventory_mode'],
			'hostids' => [$hostprototypeid],
			'filter' => ['inventory_mode' => HOST_INVENTORY_MANUAL]
		]);
		$this->assertNull(CTestArrayHelper::get($response, 'result.0'));

		$response = $this->call('hostprototype.get', [
			'output' => ['inventory_mode'],
			'hostids' => [$hostprototypeid],
			'filter' => ['inventory_mode' => HOST_INVENTORY_AUTOMATIC]
		]);
		$this->assertNull(CTestArrayHelper::get($response, 'result.0'));
	}
}

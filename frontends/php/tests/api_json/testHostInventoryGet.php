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
 * Tests API methods 'host.get'. It is tested that `inventory_mode` field acts as `host` object field, having read,
 * write, filter properties. Meanwhile value for this field is retrieved from an associative field in `host_inventory`
 * table.
 */
class testHostInventoryGet extends CAPITest {

	/**
	 * Assert that datatype for inventory field is array when disabled.
	 */
	public function testSelectingDisabledObject() {
		$hostid = 50009;

		$this->assertEquals(0,
			CDBHelper::getCount('SELECT inventory_mode FROM host_inventory WHERE hostid='.$hostid)
		);

		$response = $this->call('host.get', [
			'output' => null,
			'hostids' => $hostid,
			'selectInventory' => ['type']
		], null);

		$this->assertEquals(CTestArrayHelper::get($response, 'result.0.inventory'), []);
	}

	/**
	 * Field 'inventory_mode' value in fact is a value of a related inventory object.
	 *
	 * @backup host_inventory
	 */
	public function testOutputHasNoExtraFields() {
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
	 * When selectInventory is used, there should not be either hostid or inventory_mode fields.
	 *
	 * @backup host_inventory
	 */
	public function testInventoryObjectFields() {
		$hostid = 50009;

		$this->call('host.update', [
			'hostid' => $hostid,
			'inventory_mode' => HOST_INVENTORY_MANUAL,
		]);

		$response = $this->call('host.get', [
			'output' => ['inventory_mode'],
			'hostids' => [$hostid],
			'selectInventory' => API_OUTPUT_EXTEND
		]);

		$this->assertNull(CTestArrayHelper::get($response, 'result.0.inventory.hostid'));
		$this->assertNull(CTestArrayHelper::get($response, 'result.0.inventory.inventory_mode'));
		$this->assertNotNull(CTestArrayHelper::get($response, 'result.0.inventory.type'));

		$this->assertEquals(HOST_INVENTORY_MANUAL, CTestArrayHelper::get($response, 'result.0.inventory_mode'));
	}

	/**
	 * Assert that filter does work for host having a related record and without.
	 */
	public function testItDoesFilterByInventoryModeValue() {
		$hostid = 50009;

		// Host with record.
		$this->call('host.update',
			['hostid' => $hostid, 'inventory_mode' => HOST_INVENTORY_MANUAL]
		);

		$response = $this->call('host.get', [
			'output' => ['inventory_mode'],
			'hostids' => [$hostid],
			'filter' => ['inventory_mode' => HOST_INVENTORY_MANUAL]
		]);
		$this->assertNotNull(CTestArrayHelper::get($response, 'result.0'));

		$response = $this->call('host.get', [
			'output' => ['inventory_mode'],
			'hostids' => [$hostid],
			'filter' => ['inventory_mode' => HOST_INVENTORY_DISABLED]
		]);
		$this->assertNull(CTestArrayHelper::get($response, 'result.0'));

		$response = $this->call('host.get', [
			'output' => ['inventory_mode'],
			'hostids' => [$hostid],
			'filter' => ['inventory_mode' => HOST_INVENTORY_AUTOMATIC]
		]);
		$this->assertNull(CTestArrayHelper::get($response, 'result.0'));

		// Host without record.
		$this->call('host.update',
			['hostid' => $hostid, 'inventory_mode' => HOST_INVENTORY_DISABLED]
		);

		$response = $this->call('host.get', [
			'output' => ['inventory_mode'],
			'hostids' => [$hostid],
			'filter' => ['inventory_mode' => HOST_INVENTORY_DISABLED]
		]);
		$this->assertNotNull(CTestArrayHelper::get($response, 'result.0'));

		$response = $this->call('host.get', [
			'output' => ['inventory_mode'],
			'hostids' => [$hostid],
			'filter' => ['inventory_mode' => HOST_INVENTORY_MANUAL]
		]);
		$this->assertNull(CTestArrayHelper::get($response, 'result.0'));

		$response = $this->call('host.get', [
			'output' => ['inventory_mode'],
			'hostids' => [$hostid],
			'filter' => ['inventory_mode' => HOST_INVENTORY_AUTOMATIC]
		]);
		$this->assertNull(CTestArrayHelper::get($response, 'result.0'));
	}
}

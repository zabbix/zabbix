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
 * Tests API methods 'hostprototype.create' 'host.create'. It is tested that `inventory_mode` field acts as `host` and
 * `hostprototype` object field, having read, write, filter properties. Meanwhile value for this field is updated in
 * associated table `host_inventory`.
 */
class testHostPrototypeInventoryCreate extends CAPITest {

	/**
	 * @backup host_inventory
	 * @backup host_discovery
	 * @backup hosts
	 */
	public function testInventoryModeDB() {
		$ruleid = 23278;

		$initial_count_in_inventories = CDBHelper::getCount('SELECT inventory_mode FROM host_inventory');

		$this->call('hostprototype.create', [
			'host' => '{#TEST.HOST.0002}',
			'ruleid' => $ruleid,
			'groupLinks' => [['groupid' => 5]],
			'inventory_mode' => HOST_INVENTORY_AUTOMATIC
		], null);

		$this->assertEquals($initial_count_in_inventories + 1,
			CDBHelper::getCount('SELECT inventory_mode FROM host_inventory')
		);

		$this->call('hostprototype.create', [
			'host' => '{#TEST.HOST.0003}',
			'ruleid' => $ruleid,
			'groupLinks' => [['groupid' => 5]],
			'inventory_mode' => HOST_INVENTORY_DISABLED
		], null);

		$this->call('hostprototype.create', [
			'host' => '{#TEST.HOST.0004}',
			'ruleid' => $ruleid,
			'groupLinks' => [['groupid' => 5]],
			'inventory_mode' => null
		], -32602);

		$this->assertEquals($initial_count_in_inventories + 1,
			CDBHelper::getCount('SELECT inventory_mode FROM host_inventory')
		);

		$result = $this->call('hostprototype.create', [
			'host' => '{#TEST.HOST.0005}',
			'ruleid' => $ruleid,
			'groupLinks' => [['groupid' => 5]],
			'inventory_mode' => HOST_INVENTORY_MANUAL
		], null);
		$new_hostid = reset($result['result']['hostids']);

		$this->assertEquals($initial_count_in_inventories + 2,
			CDBHelper::getCount('SELECT inventory_mode FROM host_inventory')
		);

		$this->assertEquals(HOST_INVENTORY_MANUAL,
			CDBHelper::getValue('SELECT inventory_mode FROM host_inventory WHERE hostid='.$new_hostid)
		);

		$result = $this->call('hostprototype.create', [
			'host' => '{#TEST.HOST.0006}',
			'ruleid' => $ruleid,
			'groupLinks' => [['groupid' => 5]],
			'inventory_mode' => HOST_INVENTORY_AUTOMATIC
		], null);
		$new_hostid = reset($result['result']['hostids']);

		$this->assertEquals($initial_count_in_inventories + 3,
			CDBHelper::getCount('SELECT inventory_mode FROM host_inventory')
		);

		$this->assertEquals(HOST_INVENTORY_AUTOMATIC,
			CDBHelper::getValue('SELECT inventory_mode FROM host_inventory WHERE hostid='.$new_hostid)
		);
	}

	/**
	 * There is no such field 'inventory_mode' in invetory object.
	 *
	 * @backup host_inventory
	 * @backup host_discovery
	 * @backup hosts
	 */
	public function testItErrorsOnModePropertyWrite() {
		$ruleid = 23278;

		$this->call('hostprototype.create', [
			'host' => '{#TEST.HOST.0001}',
			'ruleid' => $ruleid,
			'groupLinks' => [['groupid' => 5]],
			'inventory' => ['inventory_mode' => HOST_INVENTORY_AUTOMATIC]
		], -32602);
	}
}

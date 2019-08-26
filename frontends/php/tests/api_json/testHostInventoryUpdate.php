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
 * Tests API methods 'hostprototype.update' 'host.update'. It is tested that `inventory_mode` field acts as `host` and
 * `hostprototype` object field, having read, write, filter properties. Meanwhile value for this field is updated in
 * associated table `host_inventory`.
 */
class testHostInventoryUpdate extends CAPITest {

	/**
	 * This test asserts that `host_inventory` table updates as expected when `hostprototype.update` and `host.update`
	 * methods are issued. During update, 'inventory_mode' field is optional int field. Null is accepted as value to
	 * switch field off. Else field is integer and only one of allowd.
	 */
	public function testHostPrototypeInventoryModeCanUpdate() {
		$hostid = 50011;
		$sql = 'SELECT inventory_mode FROM host_inventory WHERE hostid='.$hostid;

		$this->call('hostprototype.update', [
			'hostid' => $hostid,
			'inventory_mode' => HOST_INVENTORY_DISABLED
		], null);
		$this->assertEquals(0, CDBHelper::getCount($sql));

		$this->call('hostprototype.update', [
			'hostid' => $hostid,
			'inventory_mode' => HOST_INVENTORY_MANUAL
		], null);
		$this->assertEquals(1, CDBHelper::getCount($sql));
		$this->assertEquals(HOST_INVENTORY_MANUAL, CDBHelper::getValue($sql));

		$this->call('hostprototype.update', [
			'hostid' => $hostid,
			'inventory_mode' => null,
		], -32602);
		$this->assertEquals(1, CDBHelper::getCount($sql));
		$this->assertEquals(HOST_INVENTORY_MANUAL, CDBHelper::getValue($sql));

		$this->call('hostprototype.update', [
			'hostid' => $hostid,
			'inventory_mode' => HOST_INVENTORY_AUTOMATIC
		], null);
		$this->assertEquals(1, CDBHelper::getCount($sql));
		$this->assertEquals(HOST_INVENTORY_AUTOMATIC, CDBHelper::getValue($sql));

		$this->call('hostprototype.update', [
			'hostid' => $hostid,
			'inventory_mode' => HOST_INVENTORY_DISABLED
		], null);
		$this->assertEquals(0, CDBHelper::getCount($sql));

		$this->call('hostprototype.update', [
			'hostid' => $hostid,
			'inventory_mode' => 999
		], -32602);

		$this->call('hostprototype.update', [
			'hostid' => $hostid,
			'inventory_mode' => 'string'
		], -32602);

		$this->call('hostprototype.update', [
			'hostid' => $hostid
		], null);

		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	/**
	 * There is no invetory object.
	 */
	public function testHostPrototypeInventoryObjectCannotBeUpdated() {
		$hostid = 50011;

		$this->call('hostprototype.update', [
			'hostid' => $hostid,
			'inventory' => ['type' => '']
		], -32602);
	}

}

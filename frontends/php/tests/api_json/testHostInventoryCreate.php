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
class testHostInventoryCreate extends CAPITest {

	/**
	 * Assert that inventory_mode is not accepted as inventory object field.
	 *
	 * @backup host_inventory
	 * @backup host_discovery
	 * @backup hosts
	 */
	public function testHostInventoryTypeIsNotInInventoryObject() {
		$interfaces = [['type' => 1, 'main' => 1, 'useip' => 1, 'ip' => '192.168.3.1', 'dns' => '', 'port' => '10050']];

		$this->call('host.create', [
			'host' => 'TEST.HOST.0001',
			'interfaces' => $interfaces,
			'inventory' => ['inventory_mode' => HOST_INVENTORY_AUTOMATIC, 'type' => 'test'],
			'groups' => [['groupid' => '5']]
		], -32602);

		$this->call('host.create', [
			'host' => 'TEST.HOST.0002',
			'interfaces' => $interfaces,
			'inventory_mode' => HOST_INVENTORY_AUTOMATIC,
			'inventory' => ['type' => 'test'],
			'groups' => [['groupid' => '5']]
		], null);
	}
}

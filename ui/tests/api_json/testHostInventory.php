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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * Tests API methods 'host.create'. It is tested that `inventory_mode` field acts as `host` object field, having read,
 * write, filter properties. Meanwhile value for this field is updated in associated table `host_inventory`.
 *
 * @backup hosts
 */
class testHostInventory extends CAPITest {

	public static function dataProviderCreate() {
		$interfaces = [['type' => 1, 'main' => 1, 'useip' => 1, 'ip' => '192.168.3.1', 'dns' => '', 'port' => '10050']];

		return [
			[
				'host.create',
				[[
					'host' => 'TEST.HOST.0001',
					'groups' => [['groupid' => '5']],
					'interfaces' => $interfaces,
					'inventory_mode' => HOST_INVENTORY_MANUAL,
					'inventory' => ['type' => 'test']
				]],
				null
			],
			[
				'host.create',
				[[
					'host' => 'TEST.HOST.0002',
					'groups' => [['groupid' => '5']],
					'interfaces' => $interfaces,
					'inventory_mode' => HOST_INVENTORY_AUTOMATIC,
					'inventory' => ['type' => 'test']
				]],
				null
			],
			[
				'host.create',
				[[
					'host' => 'TEST.HOST.0003',
					'groups' => [['groupid' => '5']],
					'interfaces' => $interfaces,
					'inventory_mode' => HOST_INVENTORY_MANUAL
				]],
				null
			],
			[
				'host.create',
				[[
					'host' => 'TEST.HOST.0004',
					'groups' => [['groupid' => '5']],
					'interfaces' => $interfaces,
					'inventory_mode' => HOST_INVENTORY_AUTOMATIC
				]],
				null
			],
			[
				'host.create',
				[[
					'host' => 'TEST.HOST.0005',
					'groups' => [['groupid' => '5']],
					'interfaces' => $interfaces,
					'inventory_mode' => HOST_INVENTORY_DISABLED
				]],
				null
			],
			[
				'host.create',
				[[
					'host' => 'TEST.HOST.0006',
					'groups' => [['groupid' => '5']],
					'interfaces' => $interfaces,
					'inventory_mode' => HOST_INVENTORY_DISABLED,
					'inventory' => ['type' => 'test']
				]],
				'Cannot set inventory fields for disabled inventory.'
			],
			// Assert that inventory_mode is not accepted as inventory object field.
			[
				'host.create',
				[[
					'host' => 'TEST.HOST.0007',
					'groups' => [['groupid' => '5']],
					'interfaces' => $interfaces,
					'inventory' => ['inventory_mode' => HOST_INVENTORY_AUTOMATIC, 'type' => 'test']
				]],
				'Incorrect inventory field "inventory_mode".'
			]
		];
	}

	public static function dataProviderUpdate() {
		$hostid = 50009;

		return [
			[
				'host.update',
				[[
					'hostid' => $hostid,
					'inventory_mode' => HOST_INVENTORY_MANUAL,
					'inventory' => ['type' => 'test']
				]],
				null
			],
			[
				'host.update',
				[[
					'hostid' => $hostid,
					'inventory_mode' => HOST_INVENTORY_AUTOMATIC,
					'inventory' => ['os' => 'Windows']
				]],
				null
			],
			[
				'host.update',
				[[
					'hostid' => $hostid,
					'inventory_mode' => HOST_INVENTORY_MANUAL
				]],
				null
			],
			[
				'host.update',
				[[
					'hostid' => $hostid,
					'inventory_mode' => HOST_INVENTORY_AUTOMATIC
				]],
				null
			],
			[
				'host.update',
				[[
					'hostid' => $hostid,
					'inventory_mode' => HOST_INVENTORY_DISABLED
				]],
				null
			],
			[
				'host.update',
				[[
					'hostid' => $hostid,
					'inventory_mode' => HOST_INVENTORY_DISABLED,
					'inventory' => ['type' => 'test']
				]],
				'Cannot set inventory fields for disabled inventory.'
			],
			// Assert that inventory_mode is not accepted as inventory object field.
			[
				'host.update',
				[[
					'hostid' => $hostid,
					'inventory' => ['inventory_mode' => HOST_INVENTORY_AUTOMATIC, 'type' => 'test']
				]],
				'Incorrect inventory field "inventory_mode".'
			]
		];
	}
	/**
	 * @dataProvider dataProviderCreate
	 * @dataProvider dataProviderUpdate
	 */
	public function testHostInventoryMethods($method, $params, $expected_error) {
		$result = $this->call($method, $params, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['hostids'] as $index => $hostid) {
				$db_inventory = CDBHelper::getRow('select * from host_inventory where hostid='.zbx_dbstr($hostid));

				if ($params[$index]['inventory_mode'] == HOST_INVENTORY_DISABLED) {
					// Test database.
					$this->assertFalse($db_inventory);

					// Test host.get method.
					$response = $this->call('host.get', [
						'output' => ['inventory_mode'],
						'hostids' => $hostid,
						'selectInventory' => API_OUTPUT_EXTEND
					], null);

					$this->assertEquals(HOST_INVENTORY_DISABLED,
						CTestArrayHelper::get($response, 'result.0.inventory_mode')
					);
					$this->assertSame([], CTestArrayHelper::get($response, 'result.0.inventory'));

					// Test filtering in host.get method.
					$response = $this->call('host.get', [
						'output' => [],
						'hostids' => $hostid,
						'filter' => ['inventory_mode' => [HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]]
					]);
					$this->assertNull(CTestArrayHelper::get($response, 'result.0'));

					$response = $this->call('host.get', [
						'output' => [],
						'hostids' => $hostid,
						'filter' => ['inventory_mode' => HOST_INVENTORY_DISABLED]
					]);
					$this->assertNotNull(CTestArrayHelper::get($response, 'result.0'));
				}
				else {
					// Test database.
					$this->assertEquals($params[$index]['inventory_mode'], $db_inventory['inventory_mode']);

					unset($db_inventory['hostid'], $db_inventory['inventory_mode']);

					foreach ($db_inventory as $field => $value) {
						if (array_key_exists('inventory', $params[$index])
								&& array_key_exists($field, $params[$index]['inventory'])) {
							$this->assertSame($params[$index]['inventory'][$field], $value);
						}
						elseif ($method === 'host.create') {
							$this->assertSame('', $value);
						}

					}

					// Test host.get method.
					$response = $this->call('host.get', [
						'output' => ['inventory_mode'],
						'hostids' => $hostid,
						'selectInventory' => API_OUTPUT_EXTEND
					], null);

					$this->assertFalse(array_key_exists('hostid',
						CTestArrayHelper::get($response, 'result.0.inventory')
					));
					$this->assertFalse(array_key_exists('inventory_mode',
						CTestArrayHelper::get($response, 'result.0.inventory')
					));
					$this->assertEquals($params[$index]['inventory_mode'],
						CTestArrayHelper::get($response, 'result.0.inventory_mode')
					);

					foreach (CTestArrayHelper::get($response, 'result.0.inventory') as $field => $value) {
						if (array_key_exists('inventory', $params[$index])
								&& array_key_exists($field, $params[$index]['inventory'])) {
							$this->assertSame($params[$index]['inventory'][$field], $value);
						}
						elseif ($method === 'host.create') {
							$this->assertSame('', $value);
						}
					}

					// Test filtering in host.get method.
					$response = $this->call('host.get', [
						'output' => [],
						'hostids' => $hostid,
						'filter' => ['inventory_mode' => [HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]]
					]);
					$this->assertNotNull(CTestArrayHelper::get($response, 'result.0'));

					$response = $this->call('host.get', [
						'output' => [],
						'hostids' => $hostid,
						'filter' => ['inventory_mode' => HOST_INVENTORY_DISABLED]
					]);
					$this->assertNull(CTestArrayHelper::get($response, 'result.0'));
				}
			}
		}
	}
}

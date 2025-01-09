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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * Tests API methods 'hostprototype.get', 'hostprototype.create' and 'hostprototype.update'. It is tested that
 * `inventory_mode` field acts as `hostprototype` object field, having read, write, filter properties. Meanwhile
 * value for this field is updated in associated table `host_inventory`.
 *
 * @backup hosts
 */
class testHostPrototypeInventory extends CAPITest {

	public static function dataProviderCreate() {
		$ruleid = 400660;

		return [
			[
				'hostprototype.create',
				[[
					'host' => '{#TEST.HOST.0001}',
					'ruleid' => $ruleid,
					'groupLinks' => [['groupid' => '5']],
					'inventory_mode' => HOST_INVENTORY_MANUAL
				]],
				null
			],
			[
				'hostprototype.create',
				[[
					'host' => '{#TEST.HOST.0002}',
					'ruleid' => $ruleid,
					'groupLinks' => [['groupid' => '5']],
					'inventory_mode' => HOST_INVENTORY_AUTOMATIC
				]],
				null
			],
			[
				'hostprototype.create',
				[[
					'host' => '{#TEST.HOST.0003}',
					'ruleid' => $ruleid,
					'groupLinks' => [['groupid' => '5']],
					'inventory_mode' => HOST_INVENTORY_DISABLED
				]],
				null
			],
			[
				'hostprototype.create',
				[[
					'host' => '{#TEST.HOST.0003}',
					'ruleid' => $ruleid,
					'groupLinks' => [['groupid' => '5']],
					'inventory_mode' => null
				]],
				'Invalid parameter "/1/inventory_mode": an integer is expected.'
			],
			[
				'hostprototype.create',
				[[
					'host' => '{#TEST.HOST.0003}',
					'ruleid' => $ruleid,
					'groupLinks' => [['groupid' => '5']],
					'inventory_mode' => 5
				]],
				'Invalid parameter "/1/inventory_mode": value must be one of -1, 0, 1.'
			],
			// Assert that inventory_mode is not accepted as inventory object field.
			[
				'hostprototype.create',
				[[
					'host' => '{#TEST.HOST.0004}',
					'ruleid' => $ruleid,
					'groupLinks' => [['groupid' => '5']],
					'inventory' => ['inventory_mode' => HOST_INVENTORY_AUTOMATIC]
				]],
				'Invalid parameter "/1": unexpected parameter "inventory".'
			]
		];
	}

	public static function dataProviderUpdate() {
		$hostid = 50011;

		return [
			[
				'hostprototype.update',
				[[
					'hostid' => $hostid,
					'inventory_mode' => HOST_INVENTORY_MANUAL
				]],
				null
			],
			[
				'hostprototype.update',
				[[
					'hostid' => $hostid,
					'inventory_mode' => HOST_INVENTORY_AUTOMATIC
				]],
				null
			],
			[
				'hostprototype.update',
				[[
					'hostid' => $hostid,
					'inventory_mode' => HOST_INVENTORY_DISABLED
				]],
				null
			],
			[
				'hostprototype.update',
				[[
					'hostid' => $hostid,
					'inventory_mode' => null
				]],
				'Invalid parameter "/1/inventory_mode": an integer is expected.'
			],
			[
				'hostprototype.update',
				[[
					'hostid' => $hostid,
					'inventory_mode' => -2
				]],
				'Invalid parameter "/1/inventory_mode": value must be one of -1, 0, 1.'
			],
			// Assert that inventory_mode is not accepted as inventory object field.
			[
				'hostprototype.update',
				[[
					'hostid' => $hostid,
					'inventory' => ['inventory_mode' => HOST_INVENTORY_AUTOMATIC]
				]],
				'Invalid parameter "/1": unexpected parameter "inventory".'
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

					// Test hostprototype.get method.
					$response = $this->call('hostprototype.get', [
						'output' => ['inventory_mode'],
						'hostids' => $hostid
					], null);

					$this->assertEquals(HOST_INVENTORY_DISABLED,
						CTestArrayHelper::get($response, 'result.0.inventory_mode')
					);

					// Test filtering in hostprototype.get method.
					$response = $this->call('hostprototype.get', [
						'output' => [],
						'hostids' => $hostid,
						'filter' => ['inventory_mode' => [HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]]
					]);
					$this->assertNull(CTestArrayHelper::get($response, 'result.0'));

					$response = $this->call('hostprototype.get', [
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
						$this->assertSame('', $value);
					}

					// Test hostprototype.get method.
					$response = $this->call('hostprototype.get', [
						'output' => ['inventory_mode'],
						'hostids' => $hostid
					], null);

					$this->assertEquals($params[$index]['inventory_mode'],
						CTestArrayHelper::get($response, 'result.0.inventory_mode')
					);

					// Test filtering in hostprototype.get method.
					$response = $this->call('hostprototype.get', [
						'output' => [],
						'hostids' => $hostid,
						'filter' => ['inventory_mode' => [HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]]
					]);
					$this->assertNotNull(CTestArrayHelper::get($response, 'result.0'));

					$response = $this->call('hostprototype.get', [
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

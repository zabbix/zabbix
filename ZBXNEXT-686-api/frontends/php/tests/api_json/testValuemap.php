<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.czabbixtest.php';

class testValuemap extends CZabbixTest {

	public function testValuemap_backup() {
		DBsave_tables('valuemaps');
	}

	public static function valuemap_create_data() {
		return [
			[
				'valuemap' => [
					'name' => '',
					'mappings' =>[
						[
							'value' => '0',
							'newvalue' => 'Down'
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'valuemap' => [
					'name' => 'HTTP response status code',
					'mappings' =>[
						[
							'value' => '0',
							'newvalue' => 'Down'
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Value map "HTTP response status code" already exists.'
			],
			[
				'valuemap' => [
					'name' => 'non existent parametr',
					'valuemapid' => 4,
					'mappings' =>[
						[
							'value' => '0',
							'newvalue' => 'Down'
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "valuemapid".'
			],
			[
				'valuemap' => [
					'name' => 'without mappings'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": the parameter "mappings" is missing.'
			],
			[
				'valuemap' => [
					'name' => 'without mapping parametrs',
					'mappings' =>[
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/mappings": cannot be empty.'
			],
			[
				'valuemap' => [
					'name' => 'without mapping newvalue',
					'mappings' =>[
						[
							'value' => '0'
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/mappings/1": the parameter "newvalue" is missing.'
			],
			[
				'valuemap' => [
					'name' => 'without mapping value',
					'mappings' =>[
						[
							'newvalue' => 'Down'
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/mappings/1": the parameter "value" is missing.'
			],
			[
				'valuemap' => [
					'name' => 'non existent parametr',
					'mappings' =>[
						[
							'value' => '0',
							'newvalue' => 'Down',
							'mappingid' => 4
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/mappings/1": unexpected parameter "mappingid".'
			],
			[
				'valuemap' => [
					'name' => 'the same mapping values',
					'mappings' =>[
						[
							'value' => '0',
							'newvalue' => 'Down'
						],
						[
							'value' => '0',
							'newvalue' => 'Up'
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/mappings/2": value (value)=(0) already exists.'
			],
			[
				'valuemap' => [
					[
					'name' => 'Valuemaps with the same names',
						'mappings' =>[
							[
								'value' => '0',
								'newvalue' => 'Down'
							]
						]
					],
					[
					'name' => 'Valuemaps with the same names',
						'mappings' =>[
							[
								'value' => '0',
								'newvalue' => 'Down'
							]
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (name)=(Valuemaps with the same names) already exists.'
			],
			[
				'valuemap' => [
					[
						'name' => 'Api value map created',
						'mappings' =>[
							[
								'value' => '123',
								'newvalue' => 'api_value'
							]
						]
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'valuemap' => [
					[
						'name' => 'Api УТФ-8',
						'mappings' =>[
							[
								'value' => 'один',
								'newvalue' => 'два'
							]
						]
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'valuemap' => [
					[
						'name' => 'Api create value map with two values',
						'mappings' =>[
							[
								'value' => '0',
								'newvalue' => 'Down'
							],
							[
								'value' => '1',
								'newvalue' => 'Up'
							]
						]
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'valuemap' => [
					[
					'name' => 'Api create valuemap one',
						'mappings' =>[
							[
								'value' => '0',
								'newvalue' => 'Down'
							]
						]
					],
					[
					'name' => 'Api create valuemap two',
						'mappings' =>[
							[
								'value' => '1',
								'newvalue' => 'Up'
							]
						]
					]
				],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider valuemap_create_data
	*/
	public function testValuemap_create($valuemap, $success_expected, $expected_error) {
		$result = $this->api_acall('valuemap.create', $valuemap, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['valuemapids'] as $key => $id) {
				$dbResult = DBSelect('select * from valuemaps where valuemapid='.$id);
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['name'], $valuemap[$key]['name']);

				foreach ($valuemap[$key]['mappings'] as $values) {
					$sql = "select * from mappings where valuemapid=".$id.
							" and value='".$values['value']."' and newvalue='".$values['newvalue']."'";
					$this->assertEquals(1, DBcount($sql));
				}
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertSame($expected_error, $result['error']['data']);
		}
	}

	public static function valuemap_update_data() {
		return [
			[
				'valuemap' => [
					'valuemapid' => '',
					'name' => 'Api valuemap updated'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/valuemapid": a number is expected.'
			],
			[
				'valuemap' => [
					'valuemapid' => '123456',
					'name' => 'Api valuemap udated'
				],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'valuemap' => [
					'valuemapid' => 'abc',
					'name' => 'Api valuemap updated'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/valuemapid": a number is expected.'
			],
			[
				'valuemap' => [
					'valuemapid' => '.',
					'name' => 'Api valuemap udated'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/valuemapid": a number is expected.'
			],
			[
				'valuemap' => [
					'valuemapid' => '18',
					'name' => ''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'valuemap' => [
					'valuemapid' => '18',
					'name' => 'APC Battery Replacement Status'
				],
				'success_expected' => false,
				'expected_error' => 'Value map "APC Battery Replacement Status" already exists.'
			],
			[
				'valuemap' => [
					'valuemapid' => '18',
					'name' => 'Api update non existent parametr',
					'value' => 4
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "value".'
			],
			[
				'valuemap' => [
					'valuemapid' => '18',
					'name' => 'Api update without mappings',
					'mappings' =>[
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/mappings": cannot be empty.'
			],
			[
				'valuemap' => [
					'valuemapid' => '18',
					'name' => 'Api update without mapping newvalue',
					'mappings' =>[
						[
							'value' => '0'
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/mappings/1": the parameter "newvalue" is missing.'
			],
			[
				'valuemap' => [
					'valuemapid' => '18',
					'name' => 'Api update without mapping value',
					'mappings' =>[
						[
							'newvalue' => 'Down'
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/mappings/1": the parameter "value" is missing.'
			],
			[
				'valuemap' => [
					'valuemapid' => '18',
					'name' => 'Api update non existent parametr',
					'mappings' =>[
						[
							'value' => '0',
							'newvalue' => 'Down',
							'mappingid' => 4
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/mappings/1": unexpected parameter "mappingid".'
			],
			[
				'valuemap' => [
					'valuemapid' => '18',
					'name' => 'Api update with the same mapping values',
					'mappings' =>[
						[
							'value' => '0',
							'newvalue' => 'Down'
						],
						[
							'value' => '0',
							'newvalue' => 'Up'
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/mappings/2": value (value)=(0) already exists.'
			],
			[
				'valuemap' => [
					[
						'valuemapid' => '18',
						'name' => 'Valuemaps with the same names'
					],
					[
						'valuemapid' => '19',
						'name' => 'Valuemaps with the same names'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (name)=(Valuemaps with the same names) already exists.'
			],
			[
				'valuemap' => [
					[
						'valuemapid' => '19',
						'name' => 'Api valuemap with the same ids1',
					],
					[
						'valuemapid' => '19',
						'name' => 'Api valuemap with the same ids2',
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (valuemapid)=(19) already exists.'
			],
			[
				'valuemap' => [
					[
						'valuemapid' => '19',
						'name' => 'Api valuemap name updated',
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'valuemap' => [
					[
						'valuemapid' => '18',
						'name' => 'Api mappings updated',
						'mappings' =>[
							[
								'value' => '123',
								'newvalue' => 'api_value'
							]
						]
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'valuemap' => [
					[
						'valuemapid' => '18',
						'name' => 'Апи обновление',
						'mappings' =>[
							[
								'value' => 'параметр',
								'newvalue' => 'значение'
							]
						]
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'valuemap' => [
					[
						'valuemapid' => '18',
						'name' => 'Api update valuemap with two values',
						'mappings' =>[
							[
								'value' => '0',
								'newvalue' => 'Down'
							],
							[
								'value' => '1',
								'newvalue' => 'Up'
							]
						]
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'valuemap' => [
					[
					'valuemapid' => '18',
					'name' => 'Api update valuemap one',
						'mappings' =>[
							[
								'value' => 'abc',
								'newvalue' => '123'
							]
						]
					],
					[
					'valuemapid' => '19',
					'name' => 'Api update valuemap two',
						'mappings' =>[
							[
								'value' => 'def',
								'newvalue' => '456'
							]
						]
					]
				],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider valuemap_update_data
	*/
	public function testValuemap_update($valuemap, $success_expected, $expected_error) {
		$result = $this->api_acall('valuemap.update', $valuemap, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['valuemapids'] as $key => $id) {
				$dbResult = DBSelect('select * from valuemaps where valuemapid='.$id);
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['name'], $valuemap[$key]['name']);

				if (isset($valuemap[$key]['mappings'])){
					foreach ($valuemap[$key]['mappings'] as $values) {
						$sql = "select * from mappings where valuemapid=".$id.
								" and value='".$values['value']."' and newvalue='".$values['newvalue']."'";
						$this->assertEquals(1, DBcount($sql));
					}
				}
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertSame($expected_error, $result['error']['data']);
				if (isset($valuemap['name'])){
					$dbResult = "select * from valuemaps where valuemapid=".$valuemap['valuemapid'].
							" and name='".$valuemap['name']."'";
					$this->assertEquals(0, DBcount($dbResult));
				}
		}
	}

	public static function valuemap_delete_data() {
		return [
			[
				'valuemap' => [
					''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'valuemap' => [
					'123456'
				],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'valuemap' => [
					'abc'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'valuemap' => [
					'.'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'valuemap' => [
					'20',
					'123456'
				],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'valuemap' => [
					'20',
					'abc'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'valuemap' => [
					'20',
					''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'valuemap' => [
					'20',
					'20'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (20) already exists.'
			],
			[
				'valuemap' => [
					'21'
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'valuemap' => [
					'22',
					'23'
				],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider valuemap_delete_data
	*/
	public function testValuemap_delete($valuemap, $success_expected, $expected_error) {
		$result = $this->api_acall('valuemap.delete', $valuemap, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['valuemapids'] as $id) {
				$dbResult = 'select * from valuemaps where valuemapid='.$id;
				$this->assertEquals(0, DBcount($dbResult));
				$dbResultMappings = 'select * from mappings where valuemapid='.$id;
				$this->assertEquals(0, DBcount($dbResultMappings));
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertEquals($expected_error, $result['error']['data']);
		}
	}

	public static function valuemap_user_data() {
		return [
			[
				'method' => 'valuemap.create',
				'user' => ['user' => 'test-admin', 'password' => 'zabbix'],
				'valuemap' => [
					'name' => 'Api value create as admin user',
					'mappings' =>[
						[
							'value' => '123',
							'newvalue' => 'api_value'
						]
					]
				],
				'expected_error' => 'Only super admins can create value maps.'
			],
			[
				'method' => 'valuemap.update',
				'user' => ['user' => 'test-admin', 'password' => 'zabbix'],
				'valuemap' => [
					'valuemapid' => '19',
					'name' => 'Api value update as admin user',
				],
				'expected_error' => 'Only super admins can update value maps.'
			],
			[
				'method' => 'valuemap.delete',
				'user' => ['user' => 'test-admin', 'password' => 'zabbix'],
				'valuemap' => [
					'20'
				],
				'expected_error' => 'Only super admins can delete value maps.'
			],
			[
				'method' => 'valuemap.create',
				'user' => ['user' => 'test-user', 'password' => 'zabbix'],
				'valuemap' => [
					'name' => 'Api value create as zabbix user',
					'mappings' =>[
						[
							'value' => '123',
							'newvalue' => 'api_value'
						]
					]
				],
				'expected_error' => 'Only super admins can create value maps.'
			],
			[
				'method' => 'valuemap.update',
				'user' => ['user' => 'test-user', 'password' => 'zabbix'],
				'valuemap' => [
					'valuemapid' => '19',
					'name' => 'Api value update as zabbix user',
				],
				'expected_error' => 'Only super admins can update value maps.'
			],
			[
				'method' => 'valuemap.delete',
				'user' => ['user' => 'test-user', 'password' => 'zabbix'],
				'valuemap' => [
					'20'
				],
				'expected_error' => 'Only super admins can delete value maps.'
			]
		];
	}

	/**
	* @dataProvider valuemap_user_data
	*/
	public function testValuemap_user_permissions($method, $user, $valuemap, $expected_error) {
		$result = $this->api_call_with_user($method, $user, $valuemap, $debug);

		$this->assertFalse(array_key_exists('result', $result));
		$this->assertTrue(array_key_exists('error', $result));

		$this->assertEquals($expected_error, $result['error']['data']);
	}

	public function testValuemap_restore() {
		DBrestore_tables('valuemaps');
	}
}

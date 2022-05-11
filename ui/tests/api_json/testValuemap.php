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
 * @backup valuemap
 */
class testValuemap extends CAPITest {

	public static function valuemap_create() {
		return [
			[
				'valuemap' => [
					'name' => 'non existent parameter',
					'valuemapid' => 4,
					'mappings' => [
						[
							'value' => '0',
							'newvalue' => 'Down'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "valuemapid".'
			],
			// Check valuemap name.
			[
				'valuemap' => [
					'mappings' => [
						[
							'value' => '0',
							'newvalue' => 'Down'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "hostid" is missing.'
			],
			[
				'valuemap' => [
					'hostid' => '',
					'mappings' => [
						[
							'value' => '0',
							'newvalue' => 'Down'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/hostid": a number is expected.'
			],
			[
				'valuemap' => [
					'hostid' => '123123123',
					'name' => 'unknown hostid',
					'mappings' => [
						[
							'value' => '0',
							'newvalue' => 'Down'
						]
					]
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Check valuemap name.
			[
				'valuemap' => [
					'hostid' => '1',
					'mappings' => [
						[
							'value' => '0',
							'newvalue' => 'Down'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "name" is missing.'
			],
			[
				'valuemap' => [
					'hostid' => '1',
					'name' => '',
					'mappings' => [
						[
							'value' => '0',
							'newvalue' => 'Down'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'valuemap' => [
					'hostid' => '1',
					'name' => 'Pellentesque rutrum, odio at imperdiet venenatis, mauris mauris65',
					'mappings' => [
						[
							'value' => '0',
							'newvalue' => 'Down'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			[
				'valuemap' => [
					'hostid' => '50009',
					'name' => 'API value duplicate',
					'mappings' => [
						[
							'value' => '0',
							'newvalue' => 'Down'
						]
					]
				],
				'expected_error' => 'Value map "API value duplicate" already exists.'
			],
			[
				'valuemap' => [
					[
						'hostid' => '50009',
						'name' => 'Valuemaps with the same names',
						'mappings' => [
							[
								'value' => '0',
								'newvalue' => 'Down'
							]
						]
					],
					[
						'hostid' => '50009',
						'name' => 'Valuemaps with the same names',
						'mappings' => [
							[
								'value' => '0',
								'newvalue' => 'Down'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (hostid, name)=(50009, Valuemaps with the same names) already exists.'
			],
			// Check valuemap mappings.
			[
				'valuemap' => [
					'hostid' => '1',
					'name' => 'non existent parameter',
					'mappings' => [
						[
							'value' => '0',
							'newvalue' => 'Down',
							'mappingid' => '4'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1": unexpected parameter "mappingid".'
			],
			[
				'valuemap' => [
					'hostid' => '1',
					'name' => 'without mappings'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "mappings" is missing.'
			],
			[
				'valuemap' => [
					'hostid' => '1',
					'name' => 'without mapping parameters',
					'mappings' => []
				],
				'expected_error' => 'Invalid parameter "/1/mappings": cannot be empty.'
			],
			[
				'valuemap' => [
					'hostid' => '1',
					'name' => 'without mapping value',
					'mappings' => [
						[
							'newvalue' => 'Down'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1": the parameter "value" is missing.'
			],
			[
				'valuemap' => [
					'hostid' => '1',
					'name' => 'without mapping newvalue',
					'mappings' => [
						[
							'value' => '0'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1": the parameter "newvalue" is missing.'
			],
			[
				'valuemap' => [
					'hostid' => '1',
					'name' => 'long value',
					'mappings' => [
						[
							'value' => 'Pellentesque rutrum, odio at imperdiet venenatis, mauris mauris65',
							'newvalue' => 'test'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1/value": value is too long.'
			],
			[
				'valuemap' => [
					'hostid' => '1',
					'name' => 'long newvalue',
					'mappings' => [
						[
							'value' => '0',
							'newvalue' => 'Pellentesque rutrum, odio at imperdiet venenatis, mauris mauris65'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1/newvalue": value is too long.'
			],
			[
				'valuemap' => [
					'hostid' => '1',
					'name' => 'the same mapping values',
					'mappings' => [
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
				'expected_error' => 'Invalid parameter "/1/mappings/2": value (value)=(0) already exists.'
			],
			[
				'valuemap' => [
					[
						'hostid' => '50009',
						'name' => 'API value is required for type equal',
						'mappings' => [
							[
								'type' => VALUEMAP_MAPPING_TYPE_EQUAL,
								'newvalue' => 'fail'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1": the parameter "value" is missing.'
			],
			[
				'valuemap' => [
					[
						'hostid' => '50009',
						'name' => 'API value is required for type greater or equal',
						'mappings' => [
							[
								'type' => VALUEMAP_MAPPING_TYPE_GREATER_EQUAL,
								'newvalue' => 'fail'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1": the parameter "value" is missing.'
			],
			[
				'valuemap' => [
					[
						'hostid' => '50009',
						'name' => 'API value is required for type less or equal',
						'mappings' => [
							[
								'type' => VALUEMAP_MAPPING_TYPE_LESS_EQUAL,
								'newvalue' => 'fail'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1": the parameter "value" is missing.'
			],
			[
				'valuemap' => [
					[
						'hostid' => '50009',
						'name' => 'API value is required for type range',
						'mappings' => [
							[
								'type' => VALUEMAP_MAPPING_TYPE_IN_RANGE,
								'newvalue' => 'fail'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1": the parameter "value" is missing.'
			],
			[
				'valuemap' => [
					[
						'hostid' => '50009',
						'name' => 'API value is required for type greater or equal',
						'mappings' => [
							[
								'type' => VALUEMAP_MAPPING_TYPE_REGEXP,
								'newvalue' => 'fail'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1": the parameter "value" is missing.'
			],
			[
				'valuemap' => [
					[
						'hostid' => '50009',
						'name' => 'API value should be empty for type default',
						'mappings' => [
							[
								'type' => VALUEMAP_MAPPING_TYPE_DEFAULT,
								'value' => 'fail',
								'newvalue' => 'fail'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1/value": should be empty.'
			],
			[
				'valuemap' => [
					[
						'hostid' => '50009',
						'name' => 'type and value combination should be unique',
						'mappings' => [
							[
								'type' => VALUEMAP_MAPPING_TYPE_EQUAL,
								'value' => '10',
								'newvalue' => '1 fail'
							],
							[
								'type' => VALUEMAP_MAPPING_TYPE_EQUAL,
								'value' => '10',
								'newvalue' => '2 fail'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/2": value (value)=(10) already exists.'
			],
			[
				'valuemap' => [
					[
						'hostid' => '50009',
						'name' => 'API only one type default is allowed',
						'mappings' => [
							[
								'type' => VALUEMAP_MAPPING_TYPE_DEFAULT,
								'newvalue' => '1 fail'
							],
							[
								'type' => VALUEMAP_MAPPING_TYPE_DEFAULT,
								'value' => '',
								'newvalue' => '2 fail'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/2": value (type)=(5) already exists.'
			],
			[
				'valuemap' => [
					[
						'hostid' => '50009',
						'name' => 'API float value with zero suffix check for geq mapping',
						'mappings' => [
							[
								'type' => VALUEMAP_MAPPING_TYPE_GREATER_EQUAL,
								'value' => '7',
								'newvalue' => '1 fail'
							],
							[
								'type' => VALUEMAP_MAPPING_TYPE_GREATER_EQUAL,
								'value' => '7.00',
								'newvalue' => '2 fail'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/2": value (value)=(7) already exists.'
			],
			// Successfully create.
			[
				'valuemap' => [
					[
						'hostid' => '50009',
						'name' => 'API equal value uniqueness check as string for eq mapping',
						'mappings' => [
							[
								'type' => VALUEMAP_MAPPING_TYPE_EQUAL,
								'value' => '5',
								'newvalue' => '5'
							],
							[
								'type' => VALUEMAP_MAPPING_TYPE_EQUAL,
								'value' => '5.00',
								'newvalue' => '5.00'
							]
						]
					]
				],
				'expected_error' => null
			],
			[
				'valuemap' => [
					[
						'hostid' => '50009',
						'name' => 'API float value uniqueness validation for geq mapping',
						'mappings' => [
							[
								'type' => VALUEMAP_MAPPING_TYPE_GREATER_EQUAL,
								'value' => '5',
								'newvalue' => '5'
							],
							[
								'type' => VALUEMAP_MAPPING_TYPE_GREATER_EQUAL,
								'value' => '5.001',
								'newvalue' => '5.001'
							]
						]
					]
				],
				'expected_error' => null
			],
			[
				'valuemap' => [
					[
						'hostid' => '50009',
						'name' => 'API value map created',
						'mappings' => [
							[
								'value' => '123',
								'newvalue' => 'api_value'
							]
						]
					]
				],
				'expected_error' => null
			],
			[
				'valuemap' => [
					[
						'hostid' => '50009',
						'name' => 'АПИ УТФ-8',
						'mappings' => [
							[
								'value' => 'один',
								'newvalue' => 'два'
							]
						]
					]
				],
				'expected_error' => null
			],
			[
				'valuemap' => [
					[
						'hostid' => '50009',
						'name' => 'API create value map with two values',
						'mappings' => [
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
				'expected_error' => null
			],
			[
				'valuemap' => [
					[
						'hostid' => '50009',
						'name' => 'API create valuemap one',
						'mappings' => [
							[
								'value' => '0',
								'newvalue' => 'Down'
							]
						]
					],
					[
						'hostid' => '50009',
						'name' => 'æų',
						'mappings' => [
							[
								'value' => 'æų',
								'newvalue' => 'æų'
							]
						]
					]
				],
				'expected_error' => null
			],
			[
				'valuemap' => [
					[
						'hostid' => '50009',
						'name' => 'API create value map with mapping types',
						'mappings' => [
							[
								'type' => VALUEMAP_MAPPING_TYPE_DEFAULT,
								'newvalue' => 'default'
							],
							[
								'type' => VALUEMAP_MAPPING_TYPE_REGEXP,
								'value' => '/[0-9]{3,}/',
								'newvalue' => 'regexp'
							],
							[
								'type' => VALUEMAP_MAPPING_TYPE_IN_RANGE,
								'value' => '10-20,-20--10,20,0.5-0.7,-0.7--0.5',
								'newvalue' => 'range'
							],
							[
								'type' => VALUEMAP_MAPPING_TYPE_LESS_EQUAL,
								'value' => '10',
								'newvalue' => '<=10'
							],
							[
								'type' => VALUEMAP_MAPPING_TYPE_GREATER_EQUAL,
								'value' => '10',
								'newvalue' => '>=10'
							],
							[
								'type' => VALUEMAP_MAPPING_TYPE_EQUAL,
								'value' => '10',
								'newvalue' => '10'
							],
							[
								'type' => VALUEMAP_MAPPING_TYPE_EQUAL,
								'value' => 'A',
								'newvalue' => 'A'
							],
							[
								'type' => VALUEMAP_MAPPING_TYPE_EQUAL,
								'value' => '0.5',
								'newvalue' => '0.5'
							]
						]
					]
				],
				'expected_error' => null
			],
			[
				'valuemap' => [
					[
						'hostid' => '50009',
						'name' => 'API mapping type default value can be empty when defined',
						'mappings' => [
							[
								'type' => VALUEMAP_MAPPING_TYPE_DEFAULT,
								'value' => '',
								'newvalue' => 'default'
							]
						]
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider valuemap_create
	*/
	public function testValuemap_Create($valuemap, $expected_error) {
		$result = $this->call('valuemap.create', $valuemap, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['valuemapids'] as $key => $id) {
				$dbResult = DBSelect('select * from valuemap where valuemapid='.zbx_dbstr($id));
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['name'], $valuemap[$key]['name']);

				foreach ($valuemap[$key]['mappings'] as $values) {
					$values += ['value' => ''];
					$this->assertEquals(1, CDBHelper::getCount('select * from valuemap_mapping where valuemapid='.zbx_dbstr($id).
							' and value='.zbx_dbstr($values['value']).' and newvalue='.zbx_dbstr($values['newvalue']))
					);
				}
			}
		}
	}

	public static function valuemap_update() {
		return [
			[
				'valuemap' => [[
					'valuemapid' => '18',
					'name' => 'API update non existent parameter',
					'value' => 4
				]],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "value".'
			],
			// Check valuemap id.
			[
				'valuemap' => [[
					'name' => 'API valuemap updated'
				]],
				'expected_error' => 'Invalid parameter "/1": the parameter "valuemapid" is missing.'
			],
			[
				'valuemap' => [[
					'valuemapid' => '',
					'name' => 'API valuemap updated'
				]],
				'expected_error' => 'Invalid parameter "/1/valuemapid": a number is expected.'
			],
			[
				'valuemap' => [[
					'valuemapid' => '123456',
					'hostid' => '',
					'name' => 'API valuemap updated'
				]],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "hostid".'
			],
			[
				'valuemap' => [[
					'valuemapid' => '123456',
					'name' => 'API valuemap updated'
				]],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'valuemap' => [[
					'valuemapid' => 'abc',
					'name' => 'API valuemap updated'
				]],
				'expected_error' => 'Invalid parameter "/1/valuemapid": a number is expected.'
			],
			[
				'valuemap' => [[
					'valuemapid' => '0.0',
					'name' => 'API valuemap udated'
				]],
				'expected_error' => 'Invalid parameter "/1/valuemapid": a number is expected.'
			],
			[
				'valuemap' => [
					[
						'valuemapid' => '19',
						'name' => 'API valuemap with the same ids1'
					],
					[
						'valuemapid' => '19',
						'name' => 'API valuemap with the same ids2'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (valuemapid)=(19) already exists.'
			],
			// Check valuemap name.
			[
				'valuemap' => [[
					'valuemapid' => '18',
					'name' => ''
				]],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'valuemap' => [[
					'valuemapid' => '18',
					'name' => 'Pellentesque rutrum, odio at imperdiet venenatis, mauris mauris65',
					'mappings' => [
						[
							'value' => '0',
							'newvalue' => 'Down'
						]
					]
				]],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			// Check valuemap mappings.
			[
				'valuemap' => [[
					'valuemapid' => '18',
					'name' => 'API update without mappings',
					'mappings' => []
				]],
				'expected_error' => 'Invalid parameter "/1/mappings": cannot be empty.'
			],
			[
				'valuemap' => [[
					'valuemapid' => '18',
					'name' => 'API update without mapping newvalue',
					'mappings' => [
						[
							'value' => '0'
						]
					]
				]],
				'expected_error' => 'Invalid parameter "/1/mappings/1": the parameter "newvalue" is missing.'
			],
			[
				'valuemap' => [[
					'valuemapid' => '18',
					'name' => 'API update long newvalue',
					'mappings' => [
						[
							'value' => '0',
							'newvalue' => 'Pellentesque rutrum, odio at imperdiet venenatis, mauris mauris65'
						]
					]
				]],
				'expected_error' => 'Invalid parameter "/1/mappings/1/newvalue": value is too long.'
			],
			[
				'valuemap' => [[
					'valuemapid' => '18',
					'name' => 'API update without mapping value',
					'mappings' => [
						[
							'newvalue' => 'Down'
						]
					]
				]],
				'expected_error' => 'Invalid parameter "/1/mappings/1": the parameter "value" is missing.'
			],
			[
				'valuemap' => [[
					'valuemapid' => '18',
					'name' => 'API update long value',
					'mappings' => [
						[
							'value' => 'Pellentesque rutrum, odio at imperdiet venenatis, mauris mauris65',
							'newvalue' => 'Up'
						]
					]
				]],
				'expected_error' => 'Invalid parameter "/1/mappings/1/value": value is too long.'
			],
			[
				'valuemap' => [[
					'valuemapid' => '18',
					'name' => 'API update non existent parameter',
					'mappings' => [
						[
							'value' => '0',
							'newvalue' => 'Down',
							'mappingid' => 4
						]
					]
				]],
				'expected_error' => 'Invalid parameter "/1/mappings/1": unexpected parameter "mappingid".'
			],
			[
				'valuemap' => [[
					'valuemapid' => '18',
					'name' => 'API update with the same mapping values',
					'mappings' => [
						[
							'value' => '0',
							'newvalue' => 'Down'
						],
						[
							'value' => '0',
							'newvalue' => 'Up'
						]
					]
				]],
				'expected_error' => 'Invalid parameter "/1/mappings/2": value (value)=(0) already exists.'
			],
			// Check successfully update.
			[
				'valuemap' => [
					[
						'valuemapid' => '19',
						'name' => 'API valuemap name updated'
					]
				],
				'expected_error' => null
			],
			[
				'valuemap' => [
					[
						'valuemapid' => '18',
						'name' => 'API mappings updated',
						'mappings' => [
							[
								'value' => '123',
								'newvalue' => 'api_value'
							]
						]
					]
				],
				'expected_error' => null
			],
			[
				'valuemap' => [
					[
						'valuemapid' => '18',
						'name' => 'Апи обновление',
						'mappings' => [
							[
								'value' => 'параметр',
								'newvalue' => 'значение'
							]
						]
					]
				],
				'expected_error' => null
			],
			[
				'valuemap' => [
					[
						'valuemapid' => '18',
						'name' => 'API update valuemap with two values',
						'mappings' => [
							[
								'value' => 'æų',
								'newvalue' => 'æų'
							],
							[
								'value' => '1',
								'newvalue' => 'Up'
							]
						]
					]
				],
				'expected_error' => null
			],
			[
				'valuemap' => [
					[
						'valuemapid' => '18',
						'name' => 'API update valuemap one',
						'mappings' => [
							[
								'value' => 'abc',
								'newvalue' => '123'
							]
						]
					],
					[
						'valuemapid' => '19',
						'name' => 'API update valuemap two',
						'mappings' => [
							[
								'value' => 'def',
								'newvalue' => '456'
							]
						]
					]
				],
				'expected_error' => null
			],
			[
				'valuemap' => [
					[
						'valuemapid' => '18',
						'name' => 'API update valuemap one',
						'mappings' => [
							[
								'type' => VALUEMAP_MAPPING_TYPE_DEFAULT,
								'newvalue' => 'default'
							],
							[
								'type' => VALUEMAP_MAPPING_TYPE_REGEXP,
								'value' => '/[0-9]{3,}/',
								'newvalue' => 'regexp'
							],
							[
								'type' => VALUEMAP_MAPPING_TYPE_IN_RANGE,
								'value' => '10-20,-20--10,20,0.5-0.7,-0.7--0.5',
								'newvalue' => 'range'
							],
							[
								'type' => VALUEMAP_MAPPING_TYPE_LESS_EQUAL,
								'value' => '10.5',
								'newvalue' => '<=10.5'
							],
							[
								'type' => VALUEMAP_MAPPING_TYPE_GREATER_EQUAL,
								'value' => '20.5',
								'newvalue' => '>=20.5'
							],
							[
								'type' => VALUEMAP_MAPPING_TYPE_EQUAL,
								'value' => 'A',
								'newvalue' => 'A'
							],
							[
								'type' => VALUEMAP_MAPPING_TYPE_EQUAL,
								'value' => '10',
								'newvalue' => '10'
							],
							[
								'type' => VALUEMAP_MAPPING_TYPE_EQUAL,
								'value' => '0.5',
								'newvalue' => '0.5'
							]
						]
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider valuemap_update
	*/
	public function testValuemap_Update($valuemaps, $expected_error) {
		$result = $this->call('valuemap.update', $valuemaps, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['valuemapids'] as $key => $id) {
				$dbResult = DBSelect('select * from valuemap where valuemapid='.zbx_dbstr($id));
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['name'], $valuemaps[$key]['name']);

				if (array_key_exists('mappings', $valuemaps[$key])){
					$db_mappings = CDBHelper::getAll(
						'SELECT type,value,newvalue'.
						' FROM valuemap_mapping'.
						' WHERE valuemapid='.zbx_dbstr($id).
						' ORDER BY sortorder ASC'
					);

					foreach ($valuemaps[$key]['mappings'] as $i => $mapping) {
						$mapping = $mapping + ['type' => VALUEMAP_MAPPING_TYPE_EQUAL, 'value' => ''];
						$this->assertEquals($mapping, $db_mappings[$i]);
					}
				}
			}
		}
		else {
			foreach ($valuemaps as $valuemap) {
				if (array_key_exists('name', $valuemap) && $valuemap['name'] !== 'APC Battery Replacement Status'){
					$this->assertEquals(0, CDBHelper::getCount('select * from valuemap where name='.zbx_dbstr($valuemap['name'])));
				}
			}
		}
	}

	public static function valuemap_delete() {
		return [
			[
				'valuemap' => [],
				'expected_error' => 'Invalid parameter "/": cannot be empty.'
			],
			[
				'valuemap' => [
					''
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'valuemap' => [
					'123456'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'valuemap' => [
					'abc'
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'valuemap' => [
					'.'
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'valuemap' => [
					'20',
					'123456'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'valuemap' => [
					'20',
					'abc'
				],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'valuemap' => [
					'20',
					''
				],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'valuemap' => [
					'20',
					'20'
				],
				'expected_error' => 'Invalid parameter "/2": value (20) already exists.'
			],
			[
				'valuemap' => [
					'21'
				],
				'expected_error' => null
			],
			[
				'valuemap' => [
					'22',
					'23'
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider valuemap_delete
	*/
	public function testValuemap_Delete($valuemap, $expected_error) {
		$result = $this->call('valuemap.delete', $valuemap, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['valuemapids'] as $id) {
				$this->assertEquals(0, CDBHelper::getCount('select * from valuemap where valuemapid='.zbx_dbstr($id)));
				$this->assertEquals(0, CDBHelper::getCount('select * from valuemap_mapping where valuemapid='.zbx_dbstr($id)));
			}
		}
	}

	public static function valuemap_user_permission() {
		return [
			[
				'method' => 'valuemap.create',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'valuemap' => [
					'hostid' => '50009',
					'name' => 'API value create as zabbix user',
					'mappings' =>[
						[
							'value' => '123',
							'newvalue' => 'api_value'
						]
					]
				],
				'expected_error' => 'No permissions to call "valuemap.create".'
			],
			[
				'method' => 'valuemap.update',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'valuemap' => [
					'valuemapid' => '19',
					'name' => 'API value update as zabbix user'
				],
				'expected_error' => 'No permissions to call "valuemap.update".'
			],
			[
				'method' => 'valuemap.delete',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'valuemap' => [
					'20'
				],
				'expected_error' => 'No permissions to call "valuemap.delete".'
			]
		];
	}

	/**
	* @dataProvider valuemap_user_permission
	*/
	public function testValuemap_UserPermissions($method, $user, $valuemap, $expected_error) {
		$this->authorize($user['user'], $user['password']);
		$this->call($method, $valuemap, $expected_error);
	}
}

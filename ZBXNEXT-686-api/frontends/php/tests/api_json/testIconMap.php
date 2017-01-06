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

class testIconMap extends CZabbixTest {

	public static function iconmap_create() {
		return [
			[
				'iconmap' => [
					'iconmapid' => 1,
					'name' => 'non existent parametr',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '1',
							'expression' => 'test',
							'iconid' => '2'
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "iconmapid".'
			],
			// Check iconmap name.
			[
				'iconmap' => [
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '1',
							'expression' => 'test',
							'iconid' => '2'
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": the parameter "name" is missing.'
			],
			[
				'iconmap' => [
					'name' => '',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '1',
							'expression' => 'test',
							'iconid' => '2'
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'iconmap' => [
					'name' => 'LongNameqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwerr',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '1',
							'expression' => 'test',
							'iconid' => '2'
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			[
				'iconmap' => [
					'name' => 'Api icon map',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '1',
							'expression' => 'test',
							'iconid' => '2'
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Icon map "Api icon map" already exists.'
			],
			[
				'iconmap' => [
					[
					'name' => 'Api icon map the same name',
					'default_iconid' => '2',
					'mappings' =>[
							[
								'inventory_link' => '1',
								'expression' => 'test',
								'iconid' => '2'
							]
						]
					],
					[
					'name' => 'Api icon map the same name',
					'default_iconid' => '2',
					'mappings' =>[
							[
								'inventory_link' => '1',
								'expression' => 'test',
								'iconid' => '2'
							]
						]
					],
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (name)=(Api icon map the same name) already exists.'
			],
			// Check iconmap default_iconid.
			[
				'iconmap' => [
					'name' => 'Api icon map without default_iconid',
					'mappings' =>[
						[
							'inventory_link' => '1',
							'expression' => 'test',
							'iconid' => '2'
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": the parameter "default_iconid" is missing.'
			],
			[
				'iconmap' => [
					'name' => 'Api icon map with empty default_iconid',
					'default_iconid' => '',
					'mappings' =>[
						[
							'inventory_link' => '1',
							'expression' => 'test',
							'iconid' => '2'
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/default_iconid": a number is expected.'
			],
			[
				'iconmap' => [
					'name' => 'Api icon map with string default_iconid',
					'default_iconid' => 'abc',
					'mappings' =>[
						[
							'inventory_link' => '1',
							'expression' => 'test',
							'iconid' => '2'
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/default_iconid": a number is expected.'
			],
			[
				'iconmap' => [
					'name' => '☺',
					'default_iconid' => '0.0',
					'mappings' =>[
						[
							'inventory_link' => '1',
							'expression' => 'test',
							'iconid' => '2'
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/default_iconid": a number is expected.'
			],
			[
				'iconmap' => [
					'name' => 'Api icon map nonexistent default_iconid',
					'default_iconid' => '123456',
					'mappings' =>[
						[
							'inventory_link' => '1',
							'expression' => 'test',
							'iconid' => '2'
						]
					]
				],
				'success_expected' => false,
				'expected_error' => 'Icon with ID "123456" is not available.'
			],
			// Check mappings.
			[
				'iconmap' => [
					'name' => 'Api icon map without mappings',
					'default_iconid' => '2',
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": the parameter "mappings" is missing.'
			],
			// Check successfully creation.
			[
				'iconmap' => [
					[
						'name' => 'АПИ утф 8',
						'default_iconid' => '2',
						'mappings' =>[
							[
								'inventory_link' => '1',
								'expression' => 'сервер',
								'iconid' => '2'
							]
						]
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'iconmap' => [
					[
						'name' => 'Api create value map with two mappings',
						'default_iconid' => '2',
						'mappings' =>[
							[
								'inventory_link' => '1',
								'expression' => '@File systems for discovery',
								'iconid' => '1'
							],
							[
								'inventory_link' => '2',
								'expression' => 'two',
								'iconid' => '2'
							]
						]
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'iconmap' => [
					[
					'name' => 'æų',
					'default_iconid' => '1',
						'mappings' =>[
							[
								'inventory_link' => '2',
								'expression' => 'æų',
								'iconid' => '2'
							]
						]
					],
					[
					'name' => 'Api create iconmap two',
					'default_iconid' => '2',
						'mappings' =>[
							[
								'inventory_link' => '2',
								'expression' => '222',
								'iconid' => '2'
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
	* @dataProvider iconmap_create
	*/
	public function testIconMap_Create($iconmap, $success_expected, $expected_error) {
		$result = $this->api_acall('iconmap.create', $iconmap, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['iconmapids'] as $key => $id) {
				$dbResult = DBSelect('select * from icon_map where iconmapid='.$id);
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['name'], $iconmap[$key]['name']);
				$this->assertEquals($dbRow['default_iconid'], $iconmap[$key]['default_iconid']);

				foreach ($iconmap[$key]['mappings'] as $values) {
					$sql = "select * from icon_mapping where iconmapid='".$id."' and iconid='".$values['iconid']."'".
							" and inventory_link='".$values['inventory_link']."' and expression='".$values['expression']."'";
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

	public static function iconmap_mappings() {
		return [
			// Check mappings.
			[
				'iconmap' => [
					'name' => 'Api icon map without mapping parametrs',
					'default_iconid' => '2',
					'mappings' =>[
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings": cannot be empty.'
			],
			[
				'iconmap' => [
					'name' => 'unexpected parameter',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '2',
							'expression' => '☺',
							'iconid' => '1',
							'iconmapid' => '1'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1": unexpected parameter "iconmapid".'
			],
			// Check mappings, inventory_link
			[
				'iconmap' => [
					'name' => 'without mapping inventory_link',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'expression' => 'test',
							'iconid' => '2'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1": the parameter "inventory_link" is missing.'
			],
			[
				'iconmap' => [
					'name' => 'with empty mapping inventory_link',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '',
							'expression' => 'test',
							'iconid' => '2'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1/inventory_link": a number is expected.'
			],
			[
				'iconmap' => [
					'name' => 'with invalid inventory_link',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '0.0',
							'expression' => 'test',
							'iconid' => '2'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1/inventory_link": a number is expected.'
			],
			[
				'iconmap' => [
					'name' => 'nonexistent inventory_link',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '0',
							'expression' => 'test',
							'iconid' => '2'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1/inventory_link": value must be one of 1-70.'
			],
			[
				'iconmap' => [
					'name' => 'nonexistent inventory_link',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '71',
							'expression' => 'test',
							'iconid' => '2'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1/inventory_link": value must be one of 1-70.'
			],
			// Check mappings, expression
			[
				'iconmap' => [
					'name' => 'without mapping expression',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '2',
							'iconid' => '1'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1": the parameter "expression" is missing.'
			],
			[
				'iconmap' => [
					'name' => 'with empty mapping expression',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '2',
							'expression' => '',
							'iconid' => '1'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1/expression": cannot be empty.'
			],
			[
				'iconmap' => [
					'name' => 'long expression',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '2',
							'expression' => 'LongExpressionuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwerr',
							'iconid' => '1'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1/expression": value is too long.'
			],
			[
				'iconmap' => [
					'name' => 'global expression',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '2',
							'expression' => '@regexpnotexist',
							'iconid' => '1'
						]
					]
				],
				'expected_error' => 'Global regular expression "regexpnotexist" does not exist.'
			],
			[
				'iconmap' => [
					'name' => 'empty global expression',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '2',
							'expression' => '@',
							'iconid' => '1'
						]
					]
				],
				'expected_error' => 'Global regular expression "" does not exist.'
			],
			[
				'iconmap' => [
					'name' => 'invalid regular expression',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '2',
							'expression' => '\//',
							'iconid' => '1'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
			],
			[
				'iconmap' => [
					'name' => 'invalid regular expression',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '2',
							'expression' => '*',
							'iconid' => '1'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
			],
			[
				'iconmap' => [
					'name' => 'invalid regular expression',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '2',
							'expression' => '(',
							'iconid' => '1'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
			],
			[
				'iconmap' => [
					'name' => 'invalid regular expression',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '2',
							'expression' => '+',
							'iconid' => '1'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
			],
			[
				'iconmap' => [
					'name' => 'The same mapping values',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '2',
							'expression' => 'the same mapping',
							'iconid' => '1'
						],
						[
							'inventory_link' => '2',
							'expression' => 'the same mapping',
							'iconid' => '1'
						],
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/2": value (inventory_link, expression)=(2, the same mapping) already exists.'
			],
			// Check mappings, iconid
			[
				'iconmap' => [
					'name' => 'without mapping iconid',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '2',
							'expression' => 'test'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1": the parameter "iconid" is missing.'
			],
			[
				'iconmap' => [
					'name' => 'with empty mapping iconid',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '2',
							'expression' => 'test',
							'iconid' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1/iconid": a number is expected.'
			],
			[
				'iconmap' => [
					'name' => 'with invalid iconid',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '2',
							'expression' => 'test',
							'iconid' => '0.0'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings/1/iconid": a number is expected.'
			],
			[
				'iconmap' => [
					'name' => 'nonexistent iconid',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '2',
							'expression' => 'test',
							'iconid' => '132456'
						]
					]
				],
				'expected_error' => 'Icon with ID "132456" is not available.'
			]
		];
	}

	/**
	* @dataProvider iconmap_mappings
	*/
	public function testIconMap_MappingsCreateUpdate($iconmap, $expected_error) {
		$methods = ['iconmap.create', 'iconmap.update'];

		foreach ($methods as $method) {
			if ($method == 'iconmap.update') {
				$iconmap['iconmapid'] = '2';
				$iconmap['name'] = 'Update '.$iconmap['name'];
			}
			$result = $this->api_acall($method, $iconmap, $debug);

			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));
			$this->assertSame($expected_error, $result['error']['data']);
			$dbResult = 'select * from icon_mapping where name='.$iconmap['name'];
			$this->assertEquals(0, DBcount($dbResult));
		}
	}

	public static function iconmap_update() {
		return [
			[
				'iconmap' => [[
					'iconmappingid' => 2,
					'name' => 'non existent parametr',
					'default_iconid' => '2',
				]],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "iconmappingid".'
			],
			// Check iconmap id.
			[
				'iconmap' => [[
					'name' => 'without iconmap id'
				]],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": the parameter "iconmapid" is missing.'
			],
			[
				'iconmap' => [[
					'iconmapid' => '',
					'name' => 'empty iconmap id'
				]],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/iconmapid": a number is expected.'
			],
			[
				'iconmap' => [[
					'iconmapid' => '123456',
					'name' => 'non existent iconmap id'
				]],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'iconmap' => [[
					'iconmapid' => 'æų',
					'name' => 'æų'
				]],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/iconmapid": a number is expected.'
			],
			[
				'iconmap' => [[
					'iconmapid' => '1.1',
					'name' => 'invalid iconmap id'
				]],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/iconmapid": a number is expected.'
			],
			[
				'iconmap' => [
					[
						'iconmapid' => 2,
						'name' => 'the same iconmap id1'
					],
					[
						'iconmapid' => 2,
						'name' => 'the same iconmap id2'
					]
				],
				'success_expected' => false,
				'expected_error' => ' Invalid parameter "/2": value (iconmapid)=(2) already exists.'
			],
			// Check iconmap name.
			[
				'iconmap' => [[
					'iconmapid' => 2,
					'name' => ''
				]],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'iconmap' => [[
					'iconmapid' => 2,
					'name' => 'LongNameqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwerr'
				]],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			[
				'iconmap' => [[
					'iconmapid' => 2,
					'name' => 'Api icon map'
				]],
				'success_expected' => false,
				'expected_error' => 'Icon map "Api icon map" already exists.'
			],
			[
				'iconmap' => [
					[
						'iconmapid' => 2,
						'name' => 'Api icon map the same name'
					],
					[
						'iconmapid' => 3,
						'name' => 'Api icon map the same name'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (name)=(Api icon map the same name) already exists.'
			],
			// Check iconmap default_iconid.
			[
				'iconmap' => [[
					'iconmapid' => 2,
					'name' => 'Api icon map with empty default_iconid',
					'default_iconid' => '',
				]],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/default_iconid": a number is expected.'
			],
			[
				'iconmap' => [[
					'iconmapid' => 2,
					'name' => 'Api icon map with string default_iconid',
					'default_iconid' => 'abc',
				]],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/default_iconid": a number is expected.'
			],
			[
				'iconmap' => [[
					'iconmapid' => 2,
					'name' => '☺',
					'default_iconid' => '0.0',
				]],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/default_iconid": a number is expected.'
			],
			[
				'iconmap' => [[
					'iconmapid' => 2,
					'name' => 'Api icon map nonexistent default_iconid',
					'default_iconid' => '123456',
				]],
				'success_expected' => false,
				'expected_error' => 'Icon with ID "123456" is not available.'
			],
			// Check successfully update.
			[
				'iconmap' => [
					[
						'iconmapid' => 2,
						'name' => 'АПИ утф 8 обновлён',
						'default_iconid' => '3',
						'mappings' =>[
							[
								'inventory_link' => '2',
								'expression' => 'сервер обновлён',
								'iconid' => '3'
							]
						]
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'iconmap' => [
					[
						'iconmapid' => 2,
						'name' => 'Api value map with two mappings updated æų',
						'default_iconid' => '3',
						'mappings' =>[
							[
								'inventory_link' => '3',
								'expression' => '@Network interfaces for discovery',
								'iconid' => '1'
							],
							[
								'inventory_link' => '4',
								'expression' => 'æų',
								'iconid' => '2'
							]
						]
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'iconmap' => [
					[
					'iconmapid' => 2,
					'name' => 'Api iconmap one updated',
					'default_iconid' => '1',
						'mappings' =>[
							[
								'inventory_link' => '2',
								'expression' => '111',
								'iconid' => '2'
							]
						]
					],
					[
					'iconmapid' => 3,
					'name' => 'Api iconmap two updated',
					'default_iconid' => '2',
						'mappings' =>[
							[
								'inventory_link' => '2',
								'expression' => '222',
								'iconid' => '2'
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
	* @dataProvider iconmap_update
	*/
	public function testIconMap_Update($iconmaps, $success_expected, $expected_error) {
		$result = $this->api_acall('iconmap.update', $iconmaps, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['iconmapids'] as $key => $id) {
				$dbResult = DBSelect('select * from icon_map where iconmapid='.$id);
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['name'], $iconmaps[$key]['name']);
				$this->assertEquals($dbRow['default_iconid'], $iconmaps[$key]['default_iconid']);

				foreach ($iconmaps[$key]['mappings'] as $values) {
					$sql = "select * from icon_mapping where iconmapid='".$id."' and iconid='".$values['iconid']."'".
							" and inventory_link='".$values['inventory_link']."' and expression='".$values['expression']."'";
					$this->assertEquals(1, DBcount($sql));
				}
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));
			$this->assertSame($expected_error, $result['error']['data']);

			foreach ($iconmaps as $iconmap) {
				if (array_key_exists('name', $iconmap) && array_key_exists('iconmapid', $iconmap)){
					$dbResult = "select * from icon_map where iconmapid=".$iconmap['iconmapid']." and name='".$iconmap['name']."'";
					$this->assertEquals(0, DBcount($dbResult));
				}
			}
		}
	}

	public static function iconmap_delete() {
		return [
			[
				'iconmap' => [
					''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'iconmap' => [
					'123456'
				],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'iconmap' => [
					'abc'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'iconmap' => [
					'.'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'iconmap' => [
					'4',
					'123456'
				],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'iconmap' => [
					'4',
					'abc'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'iconmap' => [
					'4',
					''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'iconmap' => [
					'4',
					'4'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (4) already exists.'
			],
			[
				'iconmap' => [
					'7'
				],
				'success_expected' => false,
				'expected_error' => 'Icon map "Api iconmap in map" cannot be deleted. Used in map "Map with iconmap".'
			],
			[
				'iconmap' => [
					'4'
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'iconmap' => [
					'5',
					'6'
				],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider iconmap_delete
	*/
	public function testIconMap_Delete($iconmap, $success_expected, $expected_error) {
		$result = $this->api_acall('iconmap.delete', $iconmap, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['iconmapids'] as $id) {
				$dbResult = 'select * from icon_map where iconmapid='.$id;
				$this->assertEquals(0, DBcount($dbResult));
				$dbResultMappings = 'select * from icon_mapping where iconmapid='.$id;
				$this->assertEquals(0, DBcount($dbResultMappings));
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertEquals($expected_error, $result['error']['data']);
		}
	}

	public static function iconmap_user_permissions() {
		return [
			[
				'method' => 'iconmap.create',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'iconmap' => [
					[
						'name' => 'Api iconmap create as zabbix admin',
						'default_iconid' => '1',
						'mappings' =>[
							[
								'inventory_link' => '1',
								'expression' => 'admin',
								'iconid' => '1'
							]
						]
					]
				],
				'expected_error' => 'You do not have permission to perform this operation.'
			],
			[
				'method' => 'iconmap.update',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'iconmap' => [
					'iconmapid' => '2',
					'name' => 'Api iconmap update as zabbix admin'
				],
				'expected_error' => 'You do not have permission to perform this operation.'
			],
			[
				'method' => 'iconmap.delete',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'iconmap' => ['2'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'method' => 'iconmap.create',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'iconmap' => [
					'name' => 'Api iconmap create as zabbix user',
					'default_iconid' => '1',
					'mappings' =>[
							[
								'inventory_link' => '1',
								'expression' => 'admin',
								'iconid' => '1'
							]
					]
				],
				'expected_error' => 'You do not have permission to perform this operation.'
			],
			[
				'method' => 'iconmap.update',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'iconmap' => [
					'iconmapid' => '19',
					'name' => 'Api iconmap update as zabbix user',
				],
				'expected_error' => 'You do not have permission to perform this operation.'
			],
			[
				'method' => 'iconmap.delete',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'iconmap' => ['2'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			]
		];
	}

	/**
	* @dataProvider iconmap_user_permissions
	*/
	public function testIconMap_UserPermissions($method, $user, $valuemap, $expected_error) {
		$result = $this->api_call_with_user($method, $user, $valuemap, $debug);

		$this->assertFalse(array_key_exists('result', $result));
		$this->assertTrue(array_key_exists('error', $result));

		$this->assertEquals($expected_error, $result['error']['data']);
	}
}

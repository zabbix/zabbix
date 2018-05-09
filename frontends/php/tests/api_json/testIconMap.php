<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

/**
 * @backup icon_map
 */
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
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			[
				'iconmap' => [
					'name' => 'API icon map',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '1',
							'expression' => 'test',
							'iconid' => '2'
						]
					]
				],
				'expected_error' => 'Icon map "API icon map" already exists.'
			],
			[
				'iconmap' => [
					[
					'name' => 'API icon map the same name',
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
					'name' => 'API icon map the same name',
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
				'expected_error' => 'Invalid parameter "/2": value (name)=(API icon map the same name) already exists.'
			],
			// Check iconmap default_iconid.
			[
				'iconmap' => [
					'name' => 'API icon map without default_iconid',
					'mappings' =>[
						[
							'inventory_link' => '1',
							'expression' => 'test',
							'iconid' => '2'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "default_iconid" is missing.'
			],
			[
				'iconmap' => [
					'name' => 'API icon map with empty default_iconid',
					'default_iconid' => '',
					'mappings' =>[
						[
							'inventory_link' => '1',
							'expression' => 'test',
							'iconid' => '2'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/default_iconid": a number is expected.'
			],
			[
				'iconmap' => [
					'name' => 'API icon map with string default_iconid',
					'default_iconid' => 'abc',
					'mappings' =>[
						[
							'inventory_link' => '1',
							'expression' => 'test',
							'iconid' => '2'
						]
					]
				],
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
				'expected_error' => 'Invalid parameter "/1/default_iconid": a number is expected.'
			],
			[
				'iconmap' => [
					'name' => 'API icon map nonexistent default_iconid',
					'default_iconid' => '123456',
					'mappings' =>[
						[
							'inventory_link' => '1',
							'expression' => 'test',
							'iconid' => '2'
						]
					]
				],
				'expected_error' => 'Icon with ID "123456" is not available.'
			],
			// Check mappings.
			[
				'iconmap' => [
					'name' => 'API icon map without mappings',
					'default_iconid' => '2',
				],
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
				'expected_error' => null
			],
			[
				'iconmap' => [
					[
						'name' => 'API create value map with two mappings',
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
					'name' => 'API create iconmap two',
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
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider iconmap_create
	*/
	public function testIconMap_Create($iconmap, $expected_error) {
		$result = $this->call('iconmap.create', $iconmap, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['iconmapids'] as $key => $id) {
				$dbResult = DBSelect('select * from icon_map where iconmapid='.zbx_dbstr($id));
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['name'], $iconmap[$key]['name']);
				$this->assertEquals($dbRow['default_iconid'], $iconmap[$key]['default_iconid']);

				foreach ($iconmap[$key]['mappings'] as $values) {
					$this->assertEquals(1, DBcount('select * from icon_mapping where iconmapid='.zbx_dbstr($id).
							' and iconid='.zbx_dbstr($values['iconid']).
							' and inventory_link='.zbx_dbstr($values['inventory_link']).
							' and expression='.zbx_dbstr($values['expression']))
					);
				}
			}
		}
	}

	public static function iconmap_mappings() {
		return [
			// Check mappings.
			[[
				'iconmap' => [
					'name' => 'API icon map without mapping parametrs',
					'default_iconid' => '2',
					'mappings' =>[
					]
				],
				'expected_error' => 'Invalid parameter "/1/mappings": cannot be empty.'
			]],
			[[
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
			]],
			// Check mappings, inventory_link
			[[
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
			]],
			[[
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
			]],
			[[
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
			]],
			[[
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
			]],
			[[
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
			]],
			// Check mappings, expression
			[[
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
			]],
			[[
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
			]],
			[[
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
			]],
			[[
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
			]],
			[[
				'iconmap' => [
					'name' => 'Global regular expression does not exist',
					'default_iconid' => '2',
					'mappings' =>[
						[
							'inventory_link' => '2',
							'expression' => '@',
							'iconid' => '1'
						]
					]
				],
				// can be different error message text
				'expected_error_pattern' => '/Global regular expression ".*" does not exist\./'
			]],
			[[
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
			]],
			[[
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
			]],
			[[
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
			]],
			[[
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
			]],
			[[
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
			]],
			// Check mappings, iconid
			[[
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
			]],
			[[
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
			]],
			[[
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
			]],
			[[
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
			]]
		];
	}

	/**
	* @dataProvider iconmap_mappings
	*/
	public function testIconMap_MappingsCreateUpdate($data) {
		$methods = ['iconmap.create', 'iconmap.update'];

		foreach ($methods as $method) {
			if ($method == 'iconmap.update') {
				$data['iconmap']['iconmapid'] = '2';
				$data['iconmap']['name'] = 'Update '.$data['iconmap']['name'];
			}
			$result = $this->call($method, $data['iconmap'], true);

			// condition for one test case, because of the different error message text
			if (array_key_exists('expected_error_pattern', $data)) {
				$this->assertRegExp($data['expected_error_pattern'], $result['error']['data']);
			}
			else {
				$this->assertSame($data['expected_error'], $result['error']['data']);
			}

			$this->assertEquals(0, DBcount('select * from icon_map where name='.zbx_dbstr($data['iconmap']['name'])));
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
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "iconmappingid".'
			],
			// Check iconmap id.
			[
				'iconmap' => [[
					'name' => 'without iconmap id'
				]],
				'expected_error' => 'Invalid parameter "/1": the parameter "iconmapid" is missing.'
			],
			[
				'iconmap' => [[
					'iconmapid' => '',
					'name' => 'empty iconmap id'
				]],
				'expected_error' => 'Invalid parameter "/1/iconmapid": a number is expected.'
			],
			[
				'iconmap' => [[
					'iconmapid' => '123456',
					'name' => 'non existent iconmap id'
				]],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'iconmap' => [[
					'iconmapid' => 'æų',
					'name' => 'æųæų'
				]],
				'expected_error' => 'Invalid parameter "/1/iconmapid": a number is expected.'
			],
			[
				'iconmap' => [[
					'iconmapid' => '1.1',
					'name' => 'invalid iconmap id'
				]],
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
				'expected_error' => 'Invalid parameter "/2": value (iconmapid)=(2) already exists.'
			],
			// Check iconmap name.
			[
				'iconmap' => [[
					'iconmapid' => 2,
					'name' => ''
				]],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'iconmap' => [[
					'iconmapid' => 2,
					'name' => 'LongNameqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwerr'
				]],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			[
				'iconmap' => [[
					'iconmapid' => 2,
					'name' => 'API icon map'
				]],
				'expected_error' => 'Icon map "API icon map" already exists.'
			],
			[
				'iconmap' => [
					[
						'iconmapid' => 2,
						'name' => 'API icon map the same name'
					],
					[
						'iconmapid' => 3,
						'name' => 'API icon map the same name'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (name)=(API icon map the same name) already exists.'
			],
			// Check iconmap default_iconid.
			[
				'iconmap' => [[
					'iconmapid' => 2,
					'name' => 'API icon map with empty default_iconid',
					'default_iconid' => '',
				]],
				'expected_error' => 'Invalid parameter "/1/default_iconid": a number is expected.'
			],
			[
				'iconmap' => [[
					'iconmapid' => 2,
					'name' => 'API icon map with string default_iconid',
					'default_iconid' => 'abc',
				]],
				'expected_error' => 'Invalid parameter "/1/default_iconid": a number is expected.'
			],
			[
				'iconmap' => [[
					'iconmapid' => 2,
					'name' => '☺',
					'default_iconid' => '0.0',
				]],
				'expected_error' => 'Invalid parameter "/1/default_iconid": a number is expected.'
			],
			[
				'iconmap' => [[
					'iconmapid' => 2,
					'name' => 'API icon map nonexistent default_iconid',
					'default_iconid' => '123456',
				]],
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
				'expected_error' => null
			],
			[
				'iconmap' => [
					[
						'iconmapid' => 2,
						'name' => 'API value map with two mappings updated æų',
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
				'expected_error' => null
			],
			[
				'iconmap' => [
					[
					'iconmapid' => 2,
					'name' => 'API iconmap one updated',
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
					'name' => 'API iconmap two updated',
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
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider iconmap_update
	*/
	public function testIconMap_Update($iconmaps, $expected_error) {
		$result = $this->call('iconmap.update', $iconmaps, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['iconmapids'] as $key => $id) {
				$dbResult = DBSelect('select * from icon_map where iconmapid='.zbx_dbstr($id));
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['name'], $iconmaps[$key]['name']);
				$this->assertEquals($dbRow['default_iconid'], $iconmaps[$key]['default_iconid']);

				foreach ($iconmaps[$key]['mappings'] as $values) {
					$this->assertEquals(1, DBcount('select * from icon_mapping where iconmapid='.zbx_dbstr($id).
							' and iconid='.zbx_dbstr($values['iconid']).
							' and inventory_link='.zbx_dbstr($values['inventory_link']).
							' and expression='.zbx_dbstr($values['expression']))
					);
				}
			}
		}
		else {
			foreach ($iconmaps as $iconmap) {
				if (array_key_exists('name', $iconmap) && $iconmap['name'] !== 'API icon map'){
					$this->assertEquals(0, DBcount('select * from icon_map where name='.zbx_dbstr($iconmap['name'])));
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
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'iconmap' => [
					'123456'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'iconmap' => [
					'abc'
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'iconmap' => [
					'.'
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'iconmap' => [
					'4',
					'123456'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'iconmap' => [
					'4',
					'abc'
				],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'iconmap' => [
					'4',
					''
				],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'iconmap' => [
					'4',
					'4'
				],
				'expected_error' => 'Invalid parameter "/2": value (4) already exists.'
			],
			[
				'iconmap' => [
					'7'
				],
				'expected_error' => 'Icon map "API iconmap in map" cannot be deleted. Used in map "Map with iconmap".'
			],
			[
				'iconmap' => [
					'4'
				],
				'expected_error' => null
			],
			[
				'iconmap' => [
					'5',
					'6'
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider iconmap_delete
	*/
	public function testIconMap_Delete($iconmap, $expected_error) {
		$result = $this->call('iconmap.delete', $iconmap, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['iconmapids'] as $id) {
				$this->assertEquals(0, DBcount('select * from icon_map where iconmapid='.zbx_dbstr($id)));
				$this->assertEquals(0, DBcount('select * from icon_mapping where iconmapid='.zbx_dbstr($id)));
			}
		}
	}

	public static function iconmap_user_permissions() {
		return [
			[
				'method' => 'iconmap.create',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'iconmap' => [
					[
						'name' => 'API iconmap create as zabbix admin',
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
					'name' => 'API iconmap update as zabbix admin'
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
					'name' => 'API iconmap create as zabbix user',
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
					'name' => 'API iconmap update as zabbix user',
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
		$this->authorize($user['user'], $user['password']);
		$this->call($method, $valuemap, $expected_error);
	}
}

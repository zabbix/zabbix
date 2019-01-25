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

class testDiscoveryRule extends CAPITest {
	public static function discoveryrule_create_data() {
		return [
			// Check permissions to host.
			[
				'discoveryrule' => [
					'name' => 'API LLD rule 5',
					'key_' => 'apilldrule5',
					'hostid' => '1',
					'type' => '0',
					'interfaceid' => '50022',
					'delay' => '30s'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Check if correct interface ID.
			[
				'discoveryrule' => [
					'name' => 'API LLD rule 5',
					'key_' => 'apilldrule5',
					'hostid' => '50009',
					'type' => '0',
					'interfaceid' => '1',
					'delay' => '30s'
				],
				'expected_error' => 'Item uses host interface from non-parent host.'
			],
			// Check if LLD rule name and key already exists.
			[
				'discoveryrule' => [
					'name' => 'API LLD rule 4',
					'key_' => 'apilldrule4',
					'hostid' => '50009',
					'type' => '0',
					'interfaceid' => '50022',
					'delay' => '30s'
				],
				'expected_error' => 'Item with key "apilldrule4" already exists on "API Host".'
			],
			// Create a LLD rule with default properties.
			[
				'discoveryrule' => [
					'name' => 'API LLD rule 5',
					'key_' => 'apilldrule5',
					'hostid' => '50009',
					'type' => '0',
					'interfaceid' => '50022',
					'delay' => '30s'
				],
				'expected_error' => null
			]
		];

		// TODO: add other properties, multiple rules, duplicates etc.
	}

	/**
	 * @dataProvider discoveryrule_create_data
	 * @backup items
	 */
	public function testDiscoveryRule_Create(array $discoveryrules, $expected_error) {
		$result = $this->call('discoveryrule.create', $discoveryrules, $expected_error);

		// Accept single and multiple LLD rules just like API method. Work with multi-dimensional array in result.
		if (!array_key_exists(0, $discoveryrules)) {
			$discoveryrules = zbx_toArray($discoveryrules);
		}

		if ($expected_error === null) {
			foreach ($result['result']['itemids'] as $num => $id) {
				$db_discoveryrule = CDBHelper::getRow(
					'SELECT i.hostid,i.name,i.key_,i.type,i.delay'.
					' FROM items i'.
					' WHERE i.itemid='.zbx_dbstr($id)
				);

				$this->assertSame($db_discoveryrule['hostid'], $discoveryrules[$num]['hostid']);
				$this->assertSame($db_discoveryrule['name'], $discoveryrules[$num]['name']);
				$this->assertSame($db_discoveryrule['key_'], $discoveryrules[$num]['key_']);
				$this->assertSame($db_discoveryrule['type'], $discoveryrules[$num]['type']);
				$this->assertSame($db_discoveryrule['delay'], $discoveryrules[$num]['delay']);
			}
		}

		// TODO: perform advanced checks and other fields.
	}

	public static function discoveryrule_lld_macro_paths_create_data() {
		$def_options = [
			'name' => 'API LLD rule 5',
			'key_' => 'apilldrule5',
			'hostid' => '50009',
			'type' => '0',
			'interfaceid' => '50022',
			'delay' => '30s'
		];

		return [
			// Check LLD macro paths: incorrect parameter type.
			[
				'discoveryrule' => $def_options + [
					'lld_macro_paths' => ''
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths": an array is expected.'
			],
			// Check LLD macro paths: incorrect parameter type (multiple rules index).
			[
				'discoveryrule' => [
					$def_options + [
						'lld_macro_paths' => [
							[
								'lld_macro' => '{#A}',
								'path' => '$.list[:1].type'
							]
						]
					],
					[
						'name' => 'API LLD rule 6',
						'key_' => 'apilldrule6',
						'hostid' => '50009',
						'type' => '0',
						'interfaceid' => '50022',
						'delay' => '30s',
						'lld_macro_paths' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/2/lld_macro_paths": an array is expected.'
			],
			// Check LLD macro paths: empty array.
			[
				'discoveryrule' => $def_options + [
					'lld_macro_paths' => []
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths": cannot be empty.'
			],
			// Check LLD macro paths: empty array of arrays.
			[
				'discoveryrule' => $def_options + [
					'lld_macro_paths' => [[]]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1": the parameter "lld_macro" is missing.'
			],
			// Check LLD macro paths: incorrect type for "lld_macro".
			[
				'discoveryrule' => $def_options + [
					'lld_macro_paths' => [
						[
							'lld_macro' => false
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1/lld_macro": a character string is expected.'
			],
			// Check LLD macro paths: incorrect type for "lld_macro" (multiple macro path index).
			[
				'discoveryrule' => $def_options + [
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type'
						],
						[
							'lld_macro' => '{#B}',
							'path' => '$.list[:2].type'
						],
						[
							'lld_macro' => false
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/3/lld_macro": a character string is expected.'
			],
			// Check LLD macro paths: empty "lld_macro".
			[
				'discoveryrule' => $def_options + [
					'lld_macro_paths' => [
						[
							'lld_macro' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1/lld_macro": cannot be empty.'
			],
			// Check LLD macro paths: incorrect value for "lld_macro".
			[
				'discoveryrule' => $def_options + [
					'lld_macro_paths' => [
						[
							'lld_macro' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1/lld_macro": a low-level discovery macro is expected.'
			],
			// Check LLD macro paths: missing "path" parameter.
			[
				'discoveryrule' => $def_options + [
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1": the parameter "path" is missing.'
			],
			// Check LLD macro paths: incorrect type for "path".
			[
				'discoveryrule' => $def_options + [
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}',
							'path' => false
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1/path": a character string is expected.'
			],
			// Check LLD macro paths: empty "path".
			[
				'discoveryrule' => $def_options + [
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}',
							'path' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1/path": cannot be empty.'
			],
			// Check LLD macro paths: duplicate LLD macro paths entries.
			[
				'discoveryrule' => $def_options + [
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type'
						],
						[
							'lld_macro' => '{#B}',
							'path' => '$.list[:2].type'
						],
						[
							'lld_macro' => '{#B}',
							'path' => '$.list[:2].type'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/3/lld_macro": value "{#B}" already exists.'
			],
			// Check LLD macro paths: check unexpected parameters.
			[
				'discoveryrule' => $def_options + [
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type',
							'param' => 'value'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1": unexpected parameter "param".'
			],
			// Check successful creation of LLD rule with LLD macro paths on a host.
			[
				'discoveryrule' => $def_options + [
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type'
						],
						[
							'lld_macro' => '{#B}',
							'path' => '$.list[:2].type'
						],
						[
							'lld_macro' => '{#C}',
							'path' => '$.list[:3].type'
						]
					]
				],
				'expected_error' => null
			],
			// Check successful creation of LLD rule with LLD macro paths on a template.
			[
				'discoveryrule' => [
					'name' => 'API LLD rule 5',
					'key_' => 'apilldrule5',
					'hostid' => '50010',
					'type' => '0',
					'delay' => '30s',
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type'
						],
						[
							'lld_macro' => '{#B}',
							'path' => '$.list[:2].type'
						],
						[
							'lld_macro' => '{#C}',
							'path' => '$.list[:3].type'
						]
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider discoveryrule_lld_macro_paths_create_data
	 * @backup items
	 */
	public function testDiscoveryRuleLLDMacroPaths_Create(array $discoveryrules, $expected_error) {
		$result = $this->call('discoveryrule.create', $discoveryrules, $expected_error);

		// Accept single and multiple LLD rules just like API method. Work with multi-dimensional array in result.
		if (!array_key_exists(0, $discoveryrules)) {
			$discoveryrules = zbx_toArray($discoveryrules);
		}

		if ($expected_error === null) {
			foreach ($result['result']['itemids'] as $num => $id) {
				if (array_key_exists('lld_macro_paths', $discoveryrules[$num])) {
					// "lld_macro" and "itemid" is a unique combination in the table.
					foreach ($discoveryrules[$num]['lld_macro_paths'] as $lld_macro_path) {
						$db_lld_macro_path = CDBHelper::getRow(
							'SELECT lmp.lld_macro,lmp.path'.
							' FROM lld_macro_path lmp'.
							' WHERE lmp.itemid='.zbx_dbstr($id).
								' AND lmp.lld_macro='.zbx_dbstr($lld_macro_path['lld_macro'])
						);

						$this->assertSame($db_lld_macro_path['lld_macro'], $lld_macro_path['lld_macro']);
						$this->assertSame($db_lld_macro_path['path'], $lld_macro_path['path']);
					}
				}
			}
		}
	}

	// TODO: create a separate test to perform updates on discovery rules and its properties.

	public static function discoveryrule_lld_macro_paths_update_data() {
		return [
			// Check LLD macro paths: incorrect parameter type.
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'lld_macro_paths' => ''
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths": an array is expected.'
			],
			// Check LLD macro paths: incorrect parameter type (multiple rules index).
			[
				'discoveryrule' => [
					[
						'itemid' => '110006',
						'lld_macro_paths' => []
					],
					[
						'itemid' => '110007',
						'lld_macro_paths' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/2/lld_macro_paths": an array is expected.'
			],
			// Check LLD macro paths: empty array of arrays.
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'lld_macro_paths' => [[]]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1": cannot be empty.'
			],
			// Check LLD macro paths: incorrect type for "lld_macro".
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'lld_macro_paths' => [
						[
							'lld_macro' => false
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1/lld_macro": a character string is expected.'
			],
			// Check LLD macro paths: incorrect type for "lld_macro" (multiple macro path index).
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type'
						],
						[
							'lld_macro' => '{#B}',
							'path' => '$.list[:2].type'
						],
						[
							'lld_macro' => false
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/3/lld_macro": a character string is expected.'
			],
			// Check LLD macro paths: empty "lld_macro".
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'lld_macro_paths' => [
						[
							'lld_macro' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1/lld_macro": cannot be empty.'
			],
			// Check LLD macro paths: incorrect value for "lld_macro".
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'lld_macro_paths' => [
						[
							'lld_macro' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1/lld_macro": a low-level discovery macro is expected.'
			],
			// Check LLD macro paths: missing "path" parameter.
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1": the parameter "path" is missing.'
			],
			// Check LLD macro paths: incorrect type for "path".
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}',
							'path' => false
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1/path": a character string is expected.'
			],
			// Check LLD macro paths: empty "path".
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}',
							'path' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1/path": cannot be empty.'
			],
			// Check LLD macro paths: duplicate LLD macro paths entries.
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type'
						],
						[
							'lld_macro' => '{#B}',
							'path' => '$.list[:2].type'
						],
						[
							'lld_macro' => '{#B}',
							'path' => '$.list[:2].type'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/3/lld_macro": value "{#B}" already exists.'
			],
			// Check LLD macro paths: check unexpected parameters.
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type',
							'param' => 'value'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1": unexpected parameter "param".'
			],
			// Check LLD macro paths: incorrect "lld_macro_pathid".
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'lld_macro_paths' => [
						[
							'lld_macro_pathid' => '999999',
						]
					]
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Check LLD macro paths: duplicate LLD macro paths entries by giving "lld_macro_pathid" and existing "lld_macro".
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'lld_macro_paths' => [
						[
							'lld_macro_pathid' => '2',
						],
						[
							'lld_macro' => '{#B}',
							'path' => '$.list[:2].type'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/2/lld_macro": value "{#B}" already exists.'
			],
			// Try to delete LLD macro paths on templated discovery rule and fail.
			[
				'discoveryrule' => [
					'itemid' => '110011',
					'lld_macro_paths' => []
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths": cannot update property for templated discovery rule.'
			],
			// Check successful update of LLD rule by clearing the records on host.
			[
				'discoveryrule' => [
					'itemid' => '110008',
					'lld_macro_paths' => []
				],
				'expected_error' => null
			],
			// Check successful update of LLD rule by clearing the records on template. Make sure inheritance works.
			[
				'discoveryrule' => [
					'itemid' => '110010',
					'lld_macro_paths' => []
				],
				'expected_error' => null
			],
			// Check successful update of LLD rule by updating (doing nothing) existing records by "lld_macro_pathid".
			[
				'discoveryrule' => [
					'itemid' => '110007',
					'lld_macro_paths' => [
						[
							'lld_macro_pathid' => '6'
						],
						[
							'lld_macro_pathid' => '7'
						],
						[
							'lld_macro_pathid' => '8'
						]
					]
				],
				'expected_error' => null
			],
			// Check successful update of LLD rule by updating (doing nothing) existing records by "lld_macro_pathid" and same value "lld_macro".
			[
				'discoveryrule' => [
					'itemid' => '110007',
					'lld_macro_paths' => [
						[
							'lld_macro_pathid' => '6',
							'lld_macro' => '{#A}'
						],
						[
							'lld_macro_pathid' => '7',
							'lld_macro' => '{#B}'
						],
						[
							'lld_macro_pathid' => '8',
							'lld_macro' => '{#C}'
						]
					]
				],
				'expected_error' => null
			],
			// Check successful update of LLD rule by updating "path" for existing records by "lld_macro_pathid".
			[
				'discoveryrule' => [
					'itemid' => '110007',
					'lld_macro_paths' => [
						[
							'lld_macro_pathid' => '6',
							'path' => '$.list[:6].type'
						],
						[
							'lld_macro_pathid' => '7',
							'path' => '$.list[:7].type'
						],
						[
							'lld_macro_pathid' => '8',
							'path' => '$.list[:8].type'
						]
					]
				],
				'expected_error' => null
			],
			// Check successful update of LLD rule by updating both "lld_macro" and "path" for existing records by "lld_macro_pathid".
			[
				'discoveryrule' => [
					'itemid' => '110007',
					'lld_macro_paths' => [
						[
							'lld_macro_pathid' => '6',
							'lld_macro' => '{#X}',
							'path' => '$.list[:9].type'
						],
						[
							'lld_macro_pathid' => '7',
							'lld_macro' => '{#Y}',
							'path' => '$.list[:10].type'
						],
						[
							'lld_macro_pathid' => '8',
							'lld_macro' => '{#Z}',
							'path' => '$.list[:11].type'
						]
					]
				],
				'expected_error' => null
			],
			// Check success update of LLD macro paths: add new records.
			[
				'discoveryrule' => [
					'itemid' => '110007',
					'lld_macro_paths' => [
						[
							'lld_macro_pathid' => '6'
						],
						[
							'lld_macro_pathid' => '7'
						],
						[
							'lld_macro_pathid' => '8'
						],
						[
							'lld_macro' => '{#Q}',
							'path' => '$.list[:13].type'
						]
					]
				],
				'expected_error' => null
			],
			// Check success update of LLD macro paths: partial replace, delete and add new records all in one go.
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'lld_macro_paths' => [
						[
							'lld_macro_pathid' => '1',
							'lld_macro' => '{#V}',
						],
						[
							'lld_macro_pathid' => '2',
							'lld_macro' => '{#E}',
							'path' => '$.list[:6].type'
						],
						[
							'lld_macro_pathid' => '3',
							'lld_macro' => '{#G}',
							'path' => '$.list[:7].type'
						],
						[
							'lld_macro' => '{#A}',
							'path' => '$.list[:8].type'
						],
						[
							'lld_macro' => '{#N}',
							'path' => '$.list[:9].type'
						]
					]
				],
				'expected_error' => null
			],
			// Check success update of LLD macro paths: partial replace. Similar to previous, but with minor changes.
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'lld_macro_paths' => [
						[
							'lld_macro_pathid' => '1',
							'lld_macro' => '{#V}',
						],
						[
							'lld_macro_pathid' => '2',
							'lld_macro' => '{#E}',
							'path' => '$.list[:6].type'
						],
						[
							'lld_macro_pathid' => '3',
							'lld_macro' => '{#G}',
							'path' => '$.list[:7].type'
						],
						[
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type'
						],
						[
							'lld_macro' => '{#N}',
							'path' => '$.list[:9].type'
						]
					]
				],
				'expected_error' => null
			],
			// Check success update of LLD macro paths: replace with exact values
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type'
						],
						[
							'lld_macro' => '{#B}',
							'path' => '$.list[:2].type'
						],
						[
							'lld_macro' => '{#C}',
							'path' => '$.list[:3].type'
						],
						[
							'lld_macro' => '{#D}',
							'path' => '$.list[:4].type'
						],
						[
							'lld_macro' => '{#E}',
							'path' => '$.list[:5].type'
						]
					]
				],
				'expected_error' => null
			],
			// Check success update of LLD macro paths: replace only one with new value, leaving rest the same.
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type'
						],
						[
							'lld_macro' => '{#B}',
							'path' => '$.list[:2].type'
						],
						[
							'lld_macro' => '{#C}',
							'path' => '$.list[:3].type'
						],
						[
							'lld_macro' => '{#D}',
							'path' => '$.list[:4].type'
						],
						[
							'lld_macro' => '{#Z}',
							'path' => '$.list[:10].type'
						]
					]
				],
				'expected_error' => null
			],
			// Check success update of LLD macro paths: replace only one record with new path.
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type'
						],
						[
							'lld_macro' => '{#B}',
							'path' => '$.list[:2].type'
						],
						[
							'lld_macro' => '{#C}',
							'path' => '$.list[:3].type'
						],
						[
							'lld_macro' => '{#D}',
							'path' => '$.list[:4].type'
						],
						[
							'lld_macro' => '{#E}',
							'path' => '$.list[:10].type'
						]
					]
				],
				'expected_error' => null
			],
			// Check success update of LLD macro paths: delete one record.
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type'
						],
						[
							'lld_macro' => '{#B}',
							'path' => '$.list[:2].type'
						],
						[
							'lld_macro' => '{#C}',
							'path' => '$.list[:3].type'
						],
						[
							'lld_macro' => '{#D}',
							'path' => '$.list[:4].type'
						]
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider discoveryrule_lld_macro_paths_update_data
	 * @backup items
	 */
	public function testDiscoveryRuleLLDMacroPaths_Update($discoveryrules, $expected_error) {
		if ($expected_error === null) {
			// Before updating, collect old data for given discovery rules.
			$itemids = [];

			if (array_key_exists(0, $discoveryrules)) {
				foreach ($discoveryrules as $discoveryrule) {
					$itemids[$discoveryrule['itemid']] = true;
				}
			}
			else {
				$itemids[$discoveryrules['itemid']] = true;
			}

			$db_lld_macro_paths = CDBHelper::getAll(
				'SELECT lmp.lld_macro_pathid,lmp.itemid,lmp.lld_macro,lmp.path'.
				' FROM lld_macro_path lmp'.
				' WHERE '.dbConditionId('lmp.itemid', array_keys($itemids)).
				' ORDER BY lmp.lld_macro_pathid ASC'
			);

			$this->call('discoveryrule.update', $discoveryrules, $expected_error);

			$db_upd_lld_macro_paths = CDBHelper::getAll(
				'SELECT lmp.lld_macro_pathid,lmp.itemid,lmp.lld_macro,lmp.path'.
				' FROM lld_macro_path lmp'.
				' WHERE '.dbConditionId('lmp.itemid', array_keys($itemids)).
				' ORDER BY lmp.lld_macro_pathid ASC'
			);

			// Accept single and multiple LLD rules just like API method. Work with multi-dimensional array in result.
			if (!array_key_exists(0, $discoveryrules)) {
				$discoveryrules = zbx_toArray($discoveryrules);
			}

			// Compare records from DB before and after API call.
			foreach ($discoveryrules as $discoveryrule) {
				$old_lld_macro_paths = [];
				$new_lld_macro_paths = [];

				if ($db_lld_macro_paths) {
					foreach ($db_lld_macro_paths as $db_lld_macro_path) {
						if (bccomp($db_lld_macro_path['itemid'], $discoveryrule['itemid']) == 0) {
							unset($db_lld_macro_path['templateid']);
							$old_lld_macro_paths[$db_lld_macro_path['lld_macro_pathid']] = $db_lld_macro_path;
						}
					}
				}

				if ($db_upd_lld_macro_paths) {
					foreach ($db_upd_lld_macro_paths as $db_upd_lld_macro_path) {
						if (bccomp($db_upd_lld_macro_path['itemid'], $discoveryrule['itemid']) == 0) {
							$new_lld_macro_paths[$db_upd_lld_macro_path['lld_macro_pathid']] = $db_upd_lld_macro_path;
						}
					}
				}

				if (array_key_exists('lld_macro_paths', $discoveryrule)) {
					foreach ($discoveryrule['lld_macro_paths'] as $lld_macro_path) {
						// If only "lld_macro_pathid" is given, nothing should change for existing fields.
						if (array_key_exists('lld_macro_pathid', $lld_macro_path)
								&& !array_key_exists('lld_macro', $lld_macro_path)
								&& !array_key_exists('path', $lld_macro_path)) {
							$old_lld_macro_path = $old_lld_macro_paths[$lld_macro_path['lld_macro_pathid']];
							$new_lld_macro_path = $new_lld_macro_paths[$lld_macro_path['lld_macro_pathid']];

							$this->assertSame($old_lld_macro_path['lld_macro'], $new_lld_macro_path['lld_macro']);
							$this->assertSame($old_lld_macro_path['path'], $new_lld_macro_path['path']);
						}

						// If "lld_macro_pathid" is given, but same "lld_macro", nothing should change for "path".
						if (array_key_exists('lld_macro_pathid', $lld_macro_path)
								&& array_key_exists('lld_macro', $lld_macro_path)
								&& !array_key_exists('path', $lld_macro_path)) {
							$old_lld_macro_path = $old_lld_macro_paths[$lld_macro_path['lld_macro_pathid']];
							$new_lld_macro_path = $new_lld_macro_paths[$lld_macro_path['lld_macro_pathid']];

							if ($old_lld_macro_path['lld_macro'] === $lld_macro_path['lld_macro']) {
								$this->assertSame($old_lld_macro_path['lld_macro'], $new_lld_macro_path['lld_macro']);
								$this->assertSame($old_lld_macro_path['path'], $new_lld_macro_path['path']);
							}
							else {
								$this->assertNotSame($old_lld_macro_path['lld_macro'],
									$new_lld_macro_path['lld_macro']
								);
								$this->assertSame($old_lld_macro_path['path'], $new_lld_macro_path['path']);
							}
						}

						// If "lld_macro_pathid" is given, but same "path", nothing should change for "lld_macro".
						if (array_key_exists('lld_macro_pathid', $lld_macro_path)
								&& !array_key_exists('lld_macro', $lld_macro_path)
								&& array_key_exists('path', $lld_macro_path)) {
							$old_lld_macro_path = $old_lld_macro_paths[$lld_macro_path['lld_macro_pathid']];
							$new_lld_macro_path = $new_lld_macro_paths[$lld_macro_path['lld_macro_pathid']];

							if ($old_lld_macro_path['path'] === $lld_macro_path['path']) {
								$this->assertSame($old_lld_macro_path['lld_macro'], $new_lld_macro_path['lld_macro']);
								$this->assertSame($old_lld_macro_path['path'], $new_lld_macro_path['path']);
							}
							else {
								$this->assertSame($old_lld_macro_path['lld_macro'], $new_lld_macro_path['lld_macro']);
								$this->assertNotSame($old_lld_macro_path['path'], $new_lld_macro_path['path']);
							}
						}

						// If "lld_macro_pathid" is not given, compare by "itemid" and "lld_macro" (unique combo).
						if (!array_key_exists('lld_macro_pathid', $lld_macro_path)) {
							// Keys "lld_macro" and "path" should exist at this point.
							if ($old_lld_macro_paths) {
								foreach ($old_lld_macro_paths as $old_lld_macro_path) {
									if (bccomp($old_lld_macro_path['itemid'], $discoveryrule['itemid']) == 0
											&& $old_lld_macro_path['lld_macro'] === $lld_macro_path['lld_macro']) {
										/*
										 * There are two situations:
										 * 1) Previous DB record is replaced with new "lld_macro" by given "lld_macroid"
										 * and new record with that same "lld_macro" is added as new with new ID.
										 * 2) Previous records are replaced with same "lld_macro" and "path", leaving
										 * records intact with same IDs.
										 */
										$replaced_old = false;

										foreach ($discoveryrule['lld_macro_paths'] as $_lld_macro_path) {
											if (array_key_exists('lld_macro_pathid', $_lld_macro_path)
													&& bccomp($_lld_macro_path['lld_macro_pathid'],
														$old_lld_macro_path['lld_macro_pathid']) == 0) {
												$replaced_old = true;

												break;
											}
										}

										foreach ($new_lld_macro_paths as $new_lld_macro_path) {
											if (bccomp($new_lld_macro_path['itemid'], $discoveryrule['itemid']) == 0
													&& $new_lld_macro_path['lld_macro']
														=== $lld_macro_path['lld_macro']) {
												break;
											}
										}

										if ($replaced_old) {
											/*
											 * There was an old record, but it was replaced by ID with new value.
											 * The ID for that macro is different.
											 */
											$this->assertNotSame($old_lld_macro_path['lld_macro_pathid'],
												$new_lld_macro_path['lld_macro_pathid']
											);

											$this->assertSame($old_lld_macro_path['lld_macro'],
												$new_lld_macro_path['lld_macro']
											);

											if ($old_lld_macro_path['path'] === $lld_macro_path['path']) {
												$this->assertSame($old_lld_macro_path['path'],
													$new_lld_macro_path['path']
												);
											}
											else {
												$this->assertNotSame($old_lld_macro_path['path'],
													$new_lld_macro_path['path']
												);
											}
										}
										else {
											// There was an old record found, but it was replaced by same "lld_macro".

											$this->assertSame($old_lld_macro_path['lld_macro_pathid'],
												$new_lld_macro_path['lld_macro_pathid']
											);

											$this->assertSame($old_lld_macro_path['lld_macro'],
												$new_lld_macro_path['lld_macro']
											);

											if ($old_lld_macro_path['path'] === $lld_macro_path['path']) {
												$this->assertSame($old_lld_macro_path['path'],
													$new_lld_macro_path['path']
												);
											}
											else {
												$this->assertNotSame($old_lld_macro_path['path'],
													$new_lld_macro_path['path']
												);
											}
										}
									}
								}

								if (count($old_lld_macro_paths) != count($discoveryrule['lld_macro_paths'])) {
									$this->assertNotSame($old_lld_macro_paths, $new_lld_macro_paths);
								}
							}
							else {
								/*
								 * No old records found, so only new records are inserted. Comparing with posted records
								 * is not advisable, since the order of records might be different. So the
								 * $old_lld_macro_paths should be empty in comparison to $new_lld_macro_paths.
								 */
								$this->assertNotSame($old_lld_macro_paths, $new_lld_macro_paths);
							}
						}
					}
				}
				else {
					// No new LLD macro paths are set, so nothing should change at all. Arrays should be the same
					$this->assertSame($old_lld_macro_paths, $new_lld_macro_paths);
				}
			}
		}
		else {
			// Call method and make sure it really returns the error.
			$this->call('discoveryrule.update', $discoveryrules, $expected_error);
		}
	}

	public static function discoveryrule_delete_data() {
		return [
			// Check successful delete of discovery rule and related objects.
			[
				'discoveryrule' => [
					'110009'
				],
				'expected_error' => null
			]
		];

		// TODO: add templated discovery rules.
	}

	/**
	 * @dataProvider discoveryrule_delete_data
	 * @backup items
	 */
	public function testDiscoveryRule_Delete($discoveryrule, $expected_error) {
		$result = $this->call('discoveryrule.delete', $discoveryrule, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['ruleids'] as $id) {
				$this->assertEquals(0, CDBHelper::getCount(
					'SELECT i.itemid FROM items i WHERE i.itemid='.zbx_dbstr($id)
				));

				// Check related tables.
				$this->assertEquals(0, CDBHelper::getCount(
					'SELECT lmp.lld_macro_pathid'.
					' FROM lld_macro_path lmp'.
					' WHERE lmp.itemid='.zbx_dbstr($id)
				));
			}
		}

		// TODO: add templated discovery rules and check on errors.
	}

	public static function discoveryrule_get_data() {
		return [
			[
				'discoveryrule' => [
					'itemids' => '123456'
				],
				'get_result' =>[
				],
				'expected_error' => true,
			],
			[
				'discoveryrule' => [
					'output' => ['itemid'],
					'itemids' => '110006',
					'selectLLDMacroPaths' => ['lld_macro', 'path']
				],
				'get_result' => [
					'itemid' => '110006',
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type'
						],
						[
							'lld_macro' => '{#B}',
							'path' => '$.list[:2].type'
						],
						[
							'lld_macro' => '{#C}',
							'path' => '$.list[:3].type'
						],
						[
							'lld_macro' => '{#D}',
							'path' => '$.list[:4].type'
						],
						[
							'lld_macro' => '{#E}',
							'path' => '$.list[:5].type'
						]
					]
				],
				'expected_error' => false
			],
			[
				'discoveryrule' => [
					'output' => ['itemid'],
					'itemids' => '110006',
					'selectLLDMacroPaths' => ['lld_macro_pathid', 'lld_macro', 'path']
				],
				'get_result' => [
					'itemid' => '110006',
					'lld_macro_paths' => [
						[
							'lld_macro_pathid' => '1',
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type'
						],
						[
							'lld_macro_pathid' => '2',
							'lld_macro' => '{#B}',
							'path' => '$.list[:2].type'
						],
						[
							'lld_macro_pathid' => '3',
							'lld_macro' => '{#C}',
							'path' => '$.list[:3].type'
						],
						[
							'lld_macro_pathid' => '4',
							'lld_macro' => '{#D}',
							'path' => '$.list[:4].type'
						],
						[
							'lld_macro_pathid' => '5',
							'lld_macro' => '{#E}',
							'path' => '$.list[:5].type'
						]
					]
				],
				'expected_error' => false
			],
			[
				'discoveryrule' => [
					'output' => ['itemid'],
					'itemids' => '110006',
					'selectLLDMacroPaths' => ['extend']
				],
				'get_result' => [
					'itemid' => '110006',
					'lld_macro_paths' => [
						[
							'lld_macro_pathid' => '1',
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type'
						],
						[
							'lld_macro_pathid' => '2',
							'lld_macro' => '{#B}',
							'path' => '$.list[:2].type'
						],
						[
							'lld_macro_pathid' => '3',
							'lld_macro' => '{#C}',
							'path' => '$.list[:3].type'
						],
						[
							'lld_macro_pathid' => '4',
							'lld_macro' => '{#D}',
							'path' => '$.list[:4].type'
						],
						[
							'lld_macro_pathid' => '5',
							'lld_macro' => '{#E}',
							'path' => '$.list[:5].type'
						]
					]
				],
				'expected_error' => false
			]
		];

		// TODO: add other discovery rule properties.
	}

	/**
	 * @dataProvider discoveryrule_get_data
	 */
	public function testDiscoveryRule_Get($discoveryrule, $get_result, $expected_error) {
		$result = $this->call('application.get', $discoveryrule);

		if ($expected_error === false) {
			foreach ($result['result'] as $entry) {
				$this->assertSame($entry['itemid'], $get_result['itemid']);

				// Check related objects.
				if (array_key_exists('selectLLDMacroPaths', $discoveryrule)) {
					$this->assertArrayHasKey('lld_macro_paths', $get_result);

					if (array_key_exists('lld_macro_paths', $get_result)) {
						$this->assertSame($entry['lld_macro_paths'], $get_result['lld_macro_paths']);
					}
				}
				else {
					$this->assertArrayNotHasKey('lld_macro_paths', $get_result);
				}
			}
		}
		else {
			$this->assertSame($result['result'], $get_result);
		}
	}

	// TODO: add more tests to check other related discovery rule properties and perfom more tests on templates and templated objects.
}

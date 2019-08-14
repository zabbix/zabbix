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
 * @backup items
 */
class testDiscoveryRule extends CAPITest {
	public static function discoveryrule_create_data_invalid() {
		return [
			'Test invalid permissions to host' => [
				'discoveryrule' => [
					'name' => 'API LLD rule invalid permissions',
					'key_' => 'apilldruleinvalidpermissions',
					'hostid' => '1',
					'type' => '0',
					'interfaceid' => '50022',
					'delay' => '30s'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test invalid interface ID' => [
				'discoveryrule' => [
					'name' => 'API LLD rule invalid interface',
					'key_' => 'apilldruleinvalidinterface',
					'hostid' => '50009',
					'type' => '0',
					'interfaceid' => '1',
					'delay' => '30s'
				],
				'expected_error' => 'Item uses host interface from non-parent host.'
			],
			'Test if LLD rule name and key already exists' => [
				'discoveryrule' => [
					'name' => 'API LLD rule 4',
					'key_' => 'apilldrule4',
					'hostid' => '50009',
					'type' => '0',
					'interfaceid' => '50022',
					'delay' => '30s'
				],
				'expected_error' => 'Item with key "apilldrule4" already exists on "API Host".'
			]
		];

		// TODO: add other properties, multiple rules, duplicates etc.
	}

	public static function discoveryrule_create_data_valid() {
		return [
			'Test valid LLD rule with default properties' => [
				'discoveryrule' => [
					'name' => 'API LLD rule default',
					'key_' => 'apilldruledefault',
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
	 * @dataProvider discoveryrule_create_data_invalid
	 * @dataProvider discoveryrule_create_data_valid
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

	public static function discoveryrule_preprocessing_create_data_invalid() {
		$test_data = self::discoveryrule_preprocessing_data_invalid();

		$default_options = [
			'name' => 'API LLD rule with preprocessing invalid',
			'key_' => 'apilldrulewithpreprocessinginvalid',
			'hostid' => '50009',
			'type' => '0',
			'interfaceid' => '50022',
			'delay' => '30s'
		];

		foreach ($test_data as &$test) {
			$test['discoveryrule'] += $default_options;
		}
		unset($test);

		return $test_data;
	}

	public static function discoveryrule_preprocessing_create_data_valid() {
		$test_data = self::discoveryrule_preprocessing_data_valid();
		$default_options = [
			'hostid' => '50009',
			'type' => '0',
			'interfaceid' => '50022',
			'delay' => '30s'
		];
		$i = 1;

		foreach ($test_data as &$test) {
			$test['discoveryrule'] += $default_options + [
				'name' => 'API LLD rule with preprocessing valid '.$i,
				'key_' => 'apilldrulewithpreprocessingvalid'.$i
			];
			$i++;
		}
		unset($test);

		return $test_data;
	}

	/**
	 * @dataProvider discoveryrule_preprocessing_create_data_invalid
	 * @dataProvider discoveryrule_preprocessing_create_data_valid
	 */
	public function testDiscoveryRulePreprocessing_Create(array $discoveryrules, $expected_error) {
		$result = $this->call('discoveryrule.create', $discoveryrules, $expected_error);

		// Accept single and multiple LLD rules just like API method. Work with multi-dimensional array in result.
		if (!array_key_exists(0, $discoveryrules)) {
			$discoveryrules = zbx_toArray($discoveryrules);
		}

		if ($expected_error === null) {
			foreach ($result['result']['itemids'] as $num => $id) {
				if (array_key_exists('preprocessing', $discoveryrules[$num])) {
					foreach ($discoveryrules[$num]['preprocessing'] as $idx => $preprocessing) {
						// Collect one step at a time. Steps should match the order in which they were given.
						$db_preprocessing = CDBHelper::getRow(
							'SELECT ip.type,ip.params,ip.error_handler,ip.error_handler_params'.
							' FROM item_preproc ip'.
							' WHERE ip.itemid='.zbx_dbstr($id).
								' AND ip.step='.zbx_dbstr($idx + 1)
						);

						$this->assertEquals($db_preprocessing['type'], $preprocessing['type']);
						$this->assertSame($db_preprocessing['params'], $preprocessing['params']);
						$this->assertEquals($db_preprocessing['error_handler'], $preprocessing['error_handler']);
						$this->assertSame($db_preprocessing['error_handler_params'],
							$preprocessing['error_handler_params']
						);
					}
				}
			}
		}

		// TODO: Create a test to check if preprocessing steps are inherited on host.
	}

	// TODO: Create API tests for items and item prototypes. It uses the same function to validate pre-processing fields.

	public static function discoveryrule_lld_macro_paths_data_invalid() {
		return [
			'Test incorrect parameter type for lld_macro_paths' => [
				'discoveryrule' => [
					'lld_macro_paths' => ''
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths": an array is expected.'
			],
			'Test incorrect parameter type for lld_macro' => [
				'discoveryrule' => [
					'lld_macro_paths' => [
						[
							'lld_macro' => false
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1/lld_macro": a character string is expected.'
			],
			'Test incorrect type for lld_macro (multiple macro path index)' => [
				'discoveryrule' => [
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
			'Test empty lld_macro' => [
				'discoveryrule' => [
					'lld_macro_paths' => [
						[
							'lld_macro' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1/lld_macro": cannot be empty.'
			],
			'Test incorrect value for lld_macro' => [
				'discoveryrule' => [
					'lld_macro_paths' => [
						[
							'lld_macro' => 'abc'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1/lld_macro": a low-level discovery macro is expected.'
			],
			'Test missing path parameter for lld_macro_paths' => [
				'discoveryrule' => [
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1": the parameter "path" is missing.'
			],
			'Test incorrect type for path parameter in lld_macro_paths' => [
				'discoveryrule' => [
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}',
							'path' => false
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1/path": a character string is expected.'
			],
			'Test empty path parameter in lld_macro_paths' => [
				'discoveryrule' => [
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}',
							'path' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1/path": cannot be empty.'
			],
			'Test duplicate lld_macro entries' => [
				'discoveryrule' => [
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
			'Test unexpected parameters lld_macro_paths' => [
				'discoveryrule' => [
					'lld_macro_paths' => [
						[
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type',
							'param' => 'value'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1": unexpected parameter "param".'
			]
		];
	}

	public static function discoveryrule_lld_macro_paths_create_data_invalid() {
		$test_data = self::discoveryrule_lld_macro_paths_data_invalid();
		$default_options = [
			'name' => 'API LLD rule with LLD macros invalid',
			'key_' => 'apilldrulewithlldmacrosinvalid',
			'hostid' => '50009',
			'type' => '0',
			'interfaceid' => '50022',
			'delay' => '30s'
		];

		foreach ($test_data as &$test) {
			$test['discoveryrule'] += $default_options;
		}
		unset($test);

		return $test_data + [
			'Test multiple discovery rules and one is broken' => [
				'discoveryrule' => [
					$default_options + [
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
			'Test empty lld_macro_paths' => [
				'discoveryrule' => $default_options + [
					'lld_macro_paths' => []
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths": cannot be empty.'
			],
			'Test no parameters in lld_macro_paths (create)' => [
				'discoveryrule' => $default_options + [
					'lld_macro_paths' => [[]]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1": the parameter "lld_macro" is missing.'
			]
		];
	}

	public static function discoveryrule_lld_macro_paths_update_data_invalid() {
		$test_data = self::discoveryrule_lld_macro_paths_data_invalid();
		$default_options = ['itemid' => '110006'];

		foreach ($test_data as &$test) {
			$test['discoveryrule'] += $default_options;
		}
		unset($test);

		return $test_data + [
			'Test incorrect second lld_macro_paths type' => [
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
			'Test no parameters in lld_macro_paths (update)' => [
				'discoveryrule' => $default_options + [
					'lld_macro_paths' => [[]]
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths/1": cannot be empty.'
			],
			'Test incorrect lld_macro_pathid' => [
				'discoveryrule' => $default_options + [
					'lld_macro_paths' => [
						[
							'lld_macro_pathid' => '999999',
						]
					]
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test duplicate LLD macro paths entries by giving lld_macro_pathid and existing lld_macro' => [
				'discoveryrule' => $default_options + [
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
			'Test removal of LLD macro paths on templated discovery rule' => [
				'discoveryrule' => [
					'itemid' => '110011',
					'lld_macro_paths' => []
				],
				'expected_error' => 'Invalid parameter "/1/lld_macro_paths": cannot update property for templated discovery rule.'
			]
		];
	}

	public static function discoveryrule_lld_macro_paths_create_data_valid() {
		$default_options = [
			'name' => 'API LLD rule with LLD macro paths on a host',
			'key_' => 'apilldrulewithlldmacropathsonahost',
			'hostid' => '50009',
			'type' => '0',
			'interfaceid' => '50022',
			'delay' => '30s'
		];

		return [
			'Test successful creation of LLD rule with LLD macro paths on a host' => [
				'discoveryrule' => $default_options + [
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
			'Test successful creation of LLD rule with LLD macro paths on a template' => [
				'discoveryrule' => [
					'name' => 'API LLD rule with LLD macro paths on a template',
					'key_' => 'apilldrulewithlldmacropathsonatemplate',
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
	 * @dataProvider discoveryrule_lld_macro_paths_create_data_invalid
	 * @dataProvider discoveryrule_lld_macro_paths_create_data_valid
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

		// TODO: Create a test to check if LLD macro paths are inherited on host.
	}

	// TODO: create a separate test to perform updates on discovery rules and its properties.

	public static function discoveryrule_preprocessing_data_invalid() {
		// Check preprocessing fields.
		return [
			'Test incorrect preprocessing type' => [
				'discoveryrule' => [
					'preprocessing' => ''
				],
				'expected_error' => 'Incorrect arguments passed to function.'
			],
			'Test no preprocessing fields' => [
				'discoveryrule' => [
					'preprocessing' => [
						[]
					]
				],
				'expected_error' => 'Item pre-processing is missing parameters: type, params, error_handler, error_handler_params'
			],
			'Test empty preprocessing fields (null)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => null,
							'params' => null,
							'error_handler' => null,
							'error_handler_params' => null
						]
					]
				],
				'expected_error' => 'Incorrect value for field "type": cannot be empty.'
			],
			'Test empty preprocessing fields (bool)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => false,
							'params' => null,
							'error_handler' => null,
							'error_handler_params' => null
						]
					]
				],
				'expected_error' => 'Incorrect value for field "type": cannot be empty.'
			],
			'Test empty preprocessing fields (string)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => '',
							'params' => null,
							'error_handler' => null,
							'error_handler_params' => null
						]
					]
				],
				'expected_error' => 'Incorrect value for field "type": cannot be empty.'
			],
			'Test invalid preprocessing type (array)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => [],
							'params' => null,
							'error_handler' => null,
							'error_handler_params' => null
						]
					]
				],
				'expected_error' => 'Incorrect arguments passed to function.'
			],
			'Test invalid preprocessing type (string)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => 'abc',
							'params' => '',
							'error_handler' => '',
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "type": unexpected value "abc".'
			],
			'Test invalid preprocessing type (integer)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => 666,
							'params' => '',
							'error_handler' => '',
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "type": unexpected value "666".'
			],
			'Test unallowed preprocessing type (integer)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_OCT2DEC,
							'params' => '',
							'error_handler' => '',
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "type": unexpected value "'.ZBX_PREPROC_OCT2DEC.'".'
			],
			'Test valid type but empty preprocessing params (null)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => null,
							'error_handler' => '',
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "params": cannot be empty.'
			],
			'Test valid type but empty preprocessing params (bool)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => false,
							'error_handler' => '',
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "params": cannot be empty.'
			],
			'Test valid type but empty preprocessing params (string)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => '',
							'error_handler' => '',
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "params": cannot be empty.'
			],
			'Test valid type but incorrect preprocessing params (array)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => [],
							'error_handler' => '',
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect arguments passed to function.'
			],
			'Test preprocessing params second parameter' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => '^abc$',
							'error_handler' => '',
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "params": second parameter is expected.'
			],
			'Test empty preprocessing error handler (null)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => "^abc$\n123",
							'error_handler' => null,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler": unexpected value "".'
			],
			'Test empty preprocessing error handler (bool)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => "^abc$\n123",
							'error_handler' => false,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler": unexpected value "".'
			],
			'Test empty preprocessing error handler (string)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => "^abc$\n123",
							'error_handler' => '',
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler": unexpected value "".'
			],
			'Test incorrect preprocessing error handler (array)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => "^abc$\n123",
							'error_handler' => [],
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect arguments passed to function.'
			],
			'Test incorrect preprocessing error handler (string)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => "^abc$\n123",
							'error_handler' => 'abc',
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler": unexpected value "abc".'
			],
			'Test incorrect preprocessing error handler (integer)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => "^abc$\n123",
							'error_handler' => 666,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler": unexpected value "666".'
			],
			'Test empty preprocessing error handler params (null)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => "^abc$\n123",
							'error_handler' => ZBX_PREPROC_FAIL_SET_ERROR,
							'error_handler_params' => null
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler_params": cannot be empty.'
			],
			'Test empty preprocessing error handler params (bool)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => "^abc$\n123",
							'error_handler' => ZBX_PREPROC_FAIL_SET_ERROR,
							'error_handler_params' => false
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler_params": cannot be empty.'
			],
			'Test empty preprocessing error handler params (string)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => "^abc$\n123",
							'error_handler' => ZBX_PREPROC_FAIL_SET_ERROR,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler_params": cannot be empty.'
			],
			'Test incorrect preprocessing error handler params (array)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => "^abc$\n123",
							'error_handler' => ZBX_PREPROC_FAIL_SET_ERROR,
							'error_handler_params' => []
						]
					]
				],
				'expected_error' => 'Incorrect arguments passed to function.'
			],
			'Test filled preprocessing error handler params (ZBX_PREPROC_REGSUB + ZBX_PREPROC_FAIL_DEFAULT)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => "^abc$\n123",
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => 'abc'
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler_params": should be empty.'
			],
			'Test filled preprocessing error handler params (ZBX_PREPROC_REGSUB + ZBX_PREPROC_FAIL_DISCARD_VALUE)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => "^abc$\n123",
							'error_handler' => ZBX_PREPROC_FAIL_DISCARD_VALUE,
							'error_handler_params' => 'abc'
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler_params": should be empty.'
			],
			'Test filled preprocessing error handler params (ZBX_PREPROC_JSONPATH + ZBX_PREPROC_FAIL_DEFAULT)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_JSONPATH,
							'params' => '$.path.to.node',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => 'Error param'
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler_params": should be empty.'
			],
			'Test filled preprocessing error handler params (ZBX_PREPROC_JSONPATH + ZBX_PREPROC_FAIL_DISCARD_VALUE)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_JSONPATH,
							'params' => '$.path.to.node',
							'error_handler' => ZBX_PREPROC_FAIL_DISCARD_VALUE,
							'error_handler_params' => 'Error param'
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler_params": should be empty.'
			],
			'Test empty preprocessing error handler params (ZBX_PREPROC_VALIDATE_NOT_REGEX + ZBX_PREPROC_FAIL_SET_ERROR)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_VALIDATE_NOT_REGEX,
							'params' => 'abc',
							'error_handler' => ZBX_PREPROC_FAIL_SET_ERROR,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler_params": cannot be empty.'
			],
			'Test filled preprocessing error handler params (ZBX_PREPROC_VALIDATE_NOT_REGEX + ZBX_PREPROC_FAIL_DEFAULT)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_VALIDATE_NOT_REGEX,
							'params' => 'abc',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => 'Error param'
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler_params": should be empty.'
			],
			'Test filled preprocessing error handler params (ZBX_PREPROC_VALIDATE_NOT_REGEX + ZBX_PREPROC_FAIL_DISCARD_VALUE)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_VALIDATE_NOT_REGEX,
							'params' => 'abc',
							'error_handler' => ZBX_PREPROC_FAIL_DISCARD_VALUE,
							'error_handler_params' => 'Error param'
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler_params": should be empty.'
			],
			'Test filled preprocessing error handler params (ZBX_PREPROC_ERROR_FIELD_JSON + ZBX_PREPROC_FAIL_DEFAULT)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_ERROR_FIELD_JSON,
							'params' => 'abc',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => 'Error param'
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler_params": should be empty.'
			],
			'Test incorrect preprocessing params for type ZBX_PREPROC_THROTTLE_TIMED_VALUE' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_THROTTLE_TIMED_VALUE,
							'params' => 'abc',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "params": a time unit is expected.'
			],
			'Test unallowed preprocessing error handler (ZBX_PREPROC_THROTTLE_TIMED_VALUE + ZBX_PREPROC_FAIL_DISCARD_VALUE)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_THROTTLE_TIMED_VALUE,
							'params' => '{#MACRO}',
							'error_handler' => ZBX_PREPROC_FAIL_DISCARD_VALUE,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler": unexpected value "'.ZBX_PREPROC_FAIL_DISCARD_VALUE.'".'
			],
			'Test unallowed preprocessing error handler (ZBX_PREPROC_THROTTLE_TIMED_VALUE + ZBX_PREPROC_FAIL_SET_VALUE)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_THROTTLE_TIMED_VALUE,
							'params' => '{#MACRO}',
							'error_handler' => ZBX_PREPROC_FAIL_SET_VALUE,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler": unexpected value "'.ZBX_PREPROC_FAIL_SET_VALUE.'".'
			],
			'Test unallowed preprocessing error handler (ZBX_PREPROC_THROTTLE_TIMED_VALUE + ZBX_PREPROC_FAIL_SET_ERROR)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_THROTTLE_TIMED_VALUE,
							'params' => '1h',
							'error_handler' => ZBX_PREPROC_FAIL_SET_ERROR,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler": unexpected value "'.ZBX_PREPROC_FAIL_SET_ERROR.'".'
			],
			'Test two preprocessing steps for type ZBX_PREPROC_THROTTLE_TIMED_VALUE' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_THROTTLE_TIMED_VALUE,
							'params' => '1h',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						],
						[
							'type' => ZBX_PREPROC_THROTTLE_TIMED_VALUE,
							'params' => '1h',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Only one throttling step is allowed.'
			],
			'Test filled preprocessing error handler params (ZBX_PREPROC_PROMETHEUS_TO_JSON + ZBX_PREPROC_FAIL_DEFAULT)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_PROMETHEUS_TO_JSON,
							'params' => 'wmi_service_state{name="dhcp",state="running"} == 1',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => 'Error param'
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler_params": should be empty.'
			],
			'Test filled preprocessing error handler params (ZBX_PREPROC_PROMETHEUS_TO_JSON + ZBX_PREPROC_FAIL_DISCARD_VALUE)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_PROMETHEUS_TO_JSON,
							'params' => 'wmi_service_state{name="dhcp",state="running"} == 1',
							'error_handler' => ZBX_PREPROC_FAIL_DISCARD_VALUE,
							'error_handler_params' => 'Error param'
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler_params": should be empty.'
			],
			'Test two preprocessing steps for type ZBX_PREPROC_PROMETHEUS_TO_JSON' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_PROMETHEUS_TO_JSON,
							'params' => 'wmi_service_state{name="dhcp",state="running"} == 1',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						],
						[
							'type' => ZBX_PREPROC_PROMETHEUS_TO_JSON,
							'params' => 'wmi_service_state{name="dhcp",state="running"} == 1',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Only one Prometheus step is allowed.'
			]
		];
	}

	public static function discoveryrule_preprocessing_data_valid() {
		return [
			'Test two valid preprocessing steps (same)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_VALIDATE_NOT_REGEX,
							'params' => 'abc',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						],
						[
							'type' => ZBX_PREPROC_VALIDATE_NOT_REGEX,
							'params' => 'def',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => null
			],
			'Test two valid preprocessing steps (different)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_VALIDATE_NOT_REGEX,
							'params' => 'abc',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						],
						[
							'type' => ZBX_PREPROC_JSONPATH,
							'params' => '$.path.to.node',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => null
			],
			'Test valid preprocessing (ZBX_PREPROC_REGSUB + ZBX_PREPROC_FAIL_SET_ERROR)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => "^abc\n123$",
							'error_handler' => ZBX_PREPROC_FAIL_SET_ERROR,
							'error_handler_params' => 'Error param'
						]
					]
				],
				'expected_error' => null
			],
			'Test valid preprocessing (ZBX_PREPROC_JSONPATH + ZBX_PREPROC_FAIL_DISCARD_VALUE)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_JSONPATH,
							'params' => '$.path.to.node',
							'error_handler' => ZBX_PREPROC_FAIL_DISCARD_VALUE,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => null
			],
			'Test valid preprocessing (ZBX_PREPROC_JSONPATH + ZBX_PREPROC_FAIL_SET_VALUE)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_JSONPATH,
							'params' => '$.path.to.node',
							'error_handler' => ZBX_PREPROC_FAIL_SET_VALUE,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => null
			],
			'Test valid preprocessing (ZBX_PREPROC_JSONPATH + ZBX_PREPROC_FAIL_DEFAULT)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_JSONPATH,
							'params' => '$.path.to.node',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => null
			],
			'Test valid preprocessing (ZBX_PREPROC_VALIDATE_NOT_REGEX + ZBX_PREPROC_FAIL_DEFAULT)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_VALIDATE_NOT_REGEX,
							'params' => 'abc',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => null
			],
			'Test valid preprocessing (ZBX_PREPROC_ERROR_FIELD_JSON + ZBX_PREPROC_FAIL_DEFAULT)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_ERROR_FIELD_JSON,
							'params' => 'abc',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => null
			],
			'Test valid preprocessing (ZBX_PREPROC_ERROR_FIELD_JSON + ZBX_PREPROC_FAIL_DISCARD_VALUE)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_ERROR_FIELD_JSON,
							'params' => 'abc',
							'error_handler' => ZBX_PREPROC_FAIL_DISCARD_VALUE,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => null
			],
			'Test valid preprocessing (ZBX_PREPROC_ERROR_FIELD_JSON + ZBX_PREPROC_FAIL_SET_VALUE)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_ERROR_FIELD_JSON,
							'params' => 'abc',
							'error_handler' => ZBX_PREPROC_FAIL_SET_VALUE,
							'error_handler_params' => 'abc'
						]
					]
				],
				'expected_error' => null
			],
			'Test valid preprocessing (ZBX_PREPROC_ERROR_FIELD_JSON + ZBX_PREPROC_FAIL_SET_ERROR)' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_ERROR_FIELD_JSON,
							'params' => 'abc',
							'error_handler' => ZBX_PREPROC_FAIL_SET_ERROR,
							'error_handler_params' => 'abc'
						]
					]
				],
				'expected_error' => null
			],
			'Test valid preprocessing with user macro for type ZBX_PREPROC_THROTTLE_TIMED_VALUE' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_THROTTLE_TIMED_VALUE,
							'params' => '{$MACRO}',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => null
			],
			'Test valid preprocessing with LLD macro for type ZBX_PREPROC_THROTTLE_TIMED_VALUE' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_THROTTLE_TIMED_VALUE,
							'params' => '{#MACRO}',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => null
			],
			'Test valid preprocessing with time unit for type ZBX_PREPROC_THROTTLE_TIMED_VALUE' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_THROTTLE_TIMED_VALUE,
							'params' => '1h',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => null
			],
			'Test valid preprocessing with type ZBX_PREPROC_PROMETHEUS_TO_JSON' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_PROMETHEUS_TO_JSON,
							'params' => 'wmi_service_state{name="dhcp",state="running"} == 1',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => null
			],
			'Test valid empty preprocessing' => [
				'discoveryrule' => [
					'preprocessing' => []
				],
				'expected_error' => null
			]
		];
	}

	public static function discoveryrule_preprocessing_update_data_invalid() {
		$test_data = self::discoveryrule_preprocessing_data_invalid();
		$default_options = ['itemid' => '110006'];

		foreach ($test_data as &$test) {
			$test['discoveryrule'] += $default_options;
		}
		unset($test);

		return $test_data + [
			'Test individual preprocessing step update with only one parameter' => [
				'discoveryrule' => $default_options + [
					'preprocessing' => [
						[
							'item_preprocid' => '5716',
							'params' => '2h'
						]
					]
				],
				'expected_error' => 'Item pre-processing is missing parameters: type, error_handler, error_handler_params'
			]
		];
	}

	public static function discoveryrule_preprocessing_update_data_valid() {
		$test_data = self::discoveryrule_preprocessing_data_valid();
		$default_options = ['itemid' => '110006'];

		foreach ($test_data as &$test) {
			$test['discoveryrule'] += $default_options;
		}
		unset($test);

		return $test_data + [
			'Test replacing preprocessing steps by ID' => [
				'discoveryrule' => $default_options + [
					'preprocessing' => [
						[
							'item_preprocid' => '5536',
							'type' => ZBX_PREPROC_REGSUB,
							'params' => "^abc\n123$",
							'error_handler' => ZBX_PREPROC_FAIL_SET_ERROR,
							'error_handler_params' => 'Error param'
						]
					]
				],
				'expected_error' => null
			],
			'Test templated discovery rule preprocessing step update' => [
				'discoveryrule' => [
					'itemid' => '110011',
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => "^abc\n123$",
							'error_handler' => ZBX_PREPROC_FAIL_SET_ERROR,
							'error_handler_params' => 'Error param'
						]
					]
				],
				// After ZBX-3783 (112) is fixed, this will fail with error.
				'expected_error' => null
			],
			'Test valid update by adding new preprocessing steps' => [
				'discoveryrule' => [
					'itemid' => '110009',
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_THROTTLE_TIMED_VALUE,
							'params' => '1h',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider discoveryrule_preprocessing_update_data_invalid
	 * @dataProvider discoveryrule_preprocessing_update_data_valid
	 */
	public function testDiscoveryRulePreprocessing_Update($discoveryrules, $expected_error) {
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

			$db_preprocessing = CDBHelper::getAll(
				'SELECT ip.item_preprocid,ip.itemid,ip.step,i.templateid,ip.type,ip.params,ip.error_handler,'.
						'ip.error_handler_params'.
				' FROM item_preproc ip,items i'.
				' WHERE '.dbConditionId('ip.itemid', array_keys($itemids)).
					' AND ip.itemid=i.itemid'.
				' ORDER BY ip.itemid ASC,ip.step ASC'
			);

			$this->call('discoveryrule.update', $discoveryrules, $expected_error);

			$db_upd_preprocessing = CDBHelper::getAll(
				'SELECT ip.item_preprocid,ip.itemid,ip.step,i.templateid,ip.type,ip.params,ip.error_handler,'.
						'ip.error_handler_params'.
				' FROM item_preproc ip,items i'.
				' WHERE '.dbConditionId('ip.itemid', array_keys($itemids)).
					' AND ip.itemid=i.itemid'.
				' ORDER BY ip.itemid ASC,ip.step ASC'
			);

			// Accept single and multiple LLD rules just like API method. Work with multi-dimensional array in result.
			if (!array_key_exists(0, $discoveryrules)) {
				$discoveryrules = zbx_toArray($discoveryrules);
			}

			// Compare records from DB before and after API call.
			foreach ($discoveryrules as $discoveryrule) {
				$old_preprocessing = [];
				$new_preprocessing = [];

				if ($db_preprocessing) {
					foreach ($db_preprocessing as $db_preproc_step) {
						$itemid = $db_preproc_step['itemid'];
						if (bccomp($itemid, $discoveryrule['itemid']) == 0) {
							$old_preprocessing[$itemid][$db_preproc_step['step']] = $db_preproc_step;
						}
					}
				}

				if ($db_upd_preprocessing) {
					foreach ($db_upd_preprocessing as $db_upd_preproc_step) {
						$itemid = $db_upd_preproc_step['itemid'];
						if (bccomp($itemid, $discoveryrule['itemid']) == 0) {
							$new_preprocessing[$itemid][$db_upd_preproc_step['step']] = $db_upd_preproc_step;
						}
					}
				}

				// If new pre-processing steps are set.
				if (array_key_exists('preprocessing', $discoveryrule)) {
					if ($discoveryrule['preprocessing']) {
						foreach ($discoveryrule['preprocessing'] as $num => $preprocessing_step) {
							if ($old_preprocessing) {
								$old_preproc_step = $old_preprocessing[$discoveryrule['itemid']][$num + 1];

								if ($old_preproc_step['templateid'] == 0) {
									// If not templated discovery rule, it's allowed to change steps.
									$this->assertNotEmpty($new_preprocessing);

									// New steps must exist.
									$new_preproc_step = $new_preprocessing[$discoveryrule['itemid']][$num + 1];

									$this->assertEquals($preprocessing_step['type'], $new_preproc_step['type']);
									$this->assertSame($preprocessing_step['params'], $new_preproc_step['params']);
									$this->assertEquals($preprocessing_step['error_handler'],
										$new_preproc_step['error_handler']
									);
									$this->assertSame($preprocessing_step['error_handler_params'],
										$new_preproc_step['error_handler_params']
									);
								}
								else {
									// Pre-processing steps for templated discovery rule should stay the same.
									$this->assertSame($old_preprocessing, $new_preprocessing);
								}
							}
							else {
								/*
								 * If this is not a templated discovery rule, check if there are steps created and check
								 * each step.
								 */
								$this->assertNotEmpty($new_preprocessing);

								// New steps must exist.
								$new_preproc_step = $new_preprocessing[$discoveryrule['itemid']][$num + 1];

								$this->assertEquals($preprocessing_step['type'], $new_preproc_step['type']);
								$this->assertSame($preprocessing_step['params'], $new_preproc_step['params']);
								$this->assertEquals($preprocessing_step['error_handler'],
									$new_preproc_step['error_handler']
								);
								$this->assertSame($preprocessing_step['error_handler_params'],
									$new_preproc_step['error_handler_params']
								);
							}
						}
					}
					else {
						// No steps are set, so old records should be cleared.
						$this->assertEmpty($new_preprocessing);
					}
				}
				else {
					// No new pre-processing steps are set, so nothing should change at all. Arrays should be the same.
					$this->assertSame($old_preprocessing, $new_preprocessing);
				}
			}
		}
		else {
			// Call method and make sure it really returns the error.
			$this->call('discoveryrule.update', $discoveryrules, $expected_error);
		}
	}

	public static function discoveryrule_lld_macro_paths_update_data_valid() {
		return [
			'Test successful clearing of records for lld_macro_paths on host' => [
				'discoveryrule' => [
					'itemid' => '110008',
					'lld_macro_paths' => []
				],
				'expected_error' => null
			],
			'Test successful clearing of records for lld_macro_paths on template' => [
				'discoveryrule' => [
					'itemid' => '110010',
					'lld_macro_paths' => []
				],
				'expected_error' => null
			],
			'Test successful update by not changing existing records by giving lld_macro_pathid' => [
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
			'Test successful update by not chaning existing records by giving lld_macro_pathid and same lld_macro' => [
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
			'Test successful update of path for existing records by giving lld_macro_pathid' => [
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
			'Test successful update of lld_macro and path for existing records by giving lld_macro_pathid' => [
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
			'Test successful update of lld_macro_paths by adding new records' => [
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
			'Test successful update of lld_macro_paths with partial replace, delete and adding new records in one request' => [
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
			'Test successful update of lld_macro_paths with partial replace' => [
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
			'Test successful update of lld_macro_paths by replaceing them with exact values' => [
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
			'Test successful update of lld_macro_paths by replacing only one with new value and leaving rest the same' => [
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
			'Test successful update of lld_macro_paths by replace only one record with new path' => [
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
			'Test successful update of lld_macro_paths by deleting one record' => [
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
	 * @dataProvider discoveryrule_lld_macro_paths_update_data_invalid
	 * @dataProvider discoveryrule_lld_macro_paths_update_data_valid
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
					// No new LLD macro paths are set, so nothing should change at all. Arrays should be the same.
					$this->assertSame($old_lld_macro_paths, $new_lld_macro_paths);
				}
			}
		}
		else {
			// Call method and make sure it really returns the error.
			$this->call('discoveryrule.update', $discoveryrules, $expected_error);
		}
	}

	public static function discoveryrule_get_data_invalid() {
		return [
			'Test getting non-existing LLD rule' => [
				'discoveryrule' => [
					'itemids' => '123456'
				],
				'get_result' => [
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			]
		];

		// TODO: add other discovery rule properties.
	}

	public static function discoveryrule_get_data_valid() {
		return [
			'Test getting existing LLD rule' => [
				'discoveryrule' => [
					'output' => ['itemid'],
					'itemids' => ['110006']
				],
				'get_result' => [
					'itemid' => '110006'
				],
				'expected_error' => null
			]
		];

		// TODO: add other discovery rule properties.
	}

	/**
	 * @dataProvider discoveryrule_get_data_invalid
	 * @dataProvider discoveryrule_get_data_valid
	 */
	public function testDiscoveryRule_Get($discoveryrule, $get_result, $expected_error) {
		// TODO: fill this test with more fields to check.

		$result = $this->call('discoveryrule.get', $discoveryrule);

		if ($expected_error === null) {
			foreach ($result['result'] as $entry) {
				$this->assertSame($entry['itemid'], $get_result['itemid']);
			}
		}
		else {
			$this->assertSame($result['result'], $get_result);
		}
	}

	public static function discoveryrule_lld_macro_paths_get_data_valid() {
		$itemid = '110012';

		return [
			'Test getting lld_macro and path' => [
				'discoveryrule' => [
					'output' => ['itemid'],
					'itemids' => [$itemid],
					'selectLLDMacroPaths' => ['lld_macro', 'path']
				],
				'get_result' => [
					'itemid' => $itemid,
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
			'Test getting lld_macro_pathid, lld_macro and path' => [
				'discoveryrule' => [
					'output' => ['itemid'],
					'itemids' => [$itemid],
					'selectLLDMacroPaths' => ['lld_macro_pathid', 'lld_macro', 'path']
				],
				'get_result' => [
					'itemid' => $itemid,
					'lld_macro_paths' => [
						[
							'lld_macro_pathid' => '18',
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type'
						],
						[
							'lld_macro_pathid' => '19',
							'lld_macro' => '{#B}',
							'path' => '$.list[:2].type'
						],
						[
							'lld_macro_pathid' => '20',
							'lld_macro' => '{#C}',
							'path' => '$.list[:3].type'
						],
						[
							'lld_macro_pathid' => '21',
							'lld_macro' => '{#D}',
							'path' => '$.list[:4].type'
						],
						[
							'lld_macro_pathid' => '22',
							'lld_macro' => '{#E}',
							'path' => '$.list[:5].type'
						]
					]
				],
				'expected_error' => null
			],
			'Test getting all LLD macro path fields' => [
				'discoveryrule' => [
					'output' => ['itemid'],
					'itemids' => [$itemid],
					'selectLLDMacroPaths' => 'extend'
				],
				'get_result' => [
					'itemid' => $itemid,
					'lld_macro_paths' => [
						[
							'lld_macro_pathid' => '18',
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type'
						],
						[
							'lld_macro_pathid' => '19',
							'lld_macro' => '{#B}',
							'path' => '$.list[:2].type'
						],
						[
							'lld_macro_pathid' => '20',
							'lld_macro' => '{#C}',
							'path' => '$.list[:3].type'
						],
						[
							'lld_macro_pathid' => '21',
							'lld_macro' => '{#D}',
							'path' => '$.list[:4].type'
						],
						[
							'lld_macro_pathid' => '22',
							'lld_macro' => '{#E}',
							'path' => '$.list[:5].type'
						]
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider discoveryrule_lld_macro_paths_get_data_valid
	 */
	public function testDiscoveryRuleLLDMacroPaths_Get($discoveryrule, $get_result, $expected_error) {
		$result = $this->call('discoveryrule.get', $discoveryrule);

		if ($expected_error === null) {
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

	public static function discoveryrule_preprocessing_get_data_valid() {
		return [
			'Test getting type, params, error_handler and error_handler_params from preprocessing' => [
				'discoveryrule' => [
					'output' => ['itemid'],
					'itemids' => ['110013'],
					'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params']
				],
				'get_result' => [
					'itemid' => '110013',
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => "^abc$\n123",
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						],
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => "^def$\n123",
							'error_handler' => ZBX_PREPROC_FAIL_DISCARD_VALUE,
							'error_handler_params' => ''
						],
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => "^ghi$\n123",
							'error_handler' => ZBX_PREPROC_FAIL_SET_VALUE,
							'error_handler_params' => 'xxx'
						],
						[
							'type' => ZBX_PREPROC_REGSUB,
							'params' => "^jkl$\n123",
							'error_handler' => ZBX_PREPROC_FAIL_SET_ERROR,
							'error_handler_params' => 'error'
						]
					]
				],
				'expected_error' => null
			],
			'Test getting params from preprocessing' => [
				'discoveryrule' => [
					'output' => ['itemid'],
					'itemids' => ['110010'],
					'selectPreprocessing' => ['params']
				],
				'get_result' => [
					'itemid' => '110010',
					'preprocessing' => [
						[
							'params' => '$.path.to.node1'
						],
						[
							'params' => '$.path.to.node2'
						],
						[
							'params' => '$.path.to.node3'
						],
						[
							'params' => '$.path.to.node4'
						]
					]
				],
				'expected_error' => null
			],
			'Test getting all preprocessing fields' => [
				'discoveryrule' => [
					'output' => ['itemid'],
					'itemids' => ['110011'],
					'selectPreprocessing' => 'extend'
				],
				'get_result' => [
					'itemid' => '110011',
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_JSONPATH,
							'params' => '$.path.to.node1',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						],
						[
							'type' => ZBX_PREPROC_JSONPATH,
							'params' => '$.path.to.node2',
							'error_handler' => ZBX_PREPROC_FAIL_DISCARD_VALUE,
							'error_handler_params' => ''
						],
						[
							'type' => ZBX_PREPROC_JSONPATH,
							'params' => '$.path.to.node3',
							'error_handler' => ZBX_PREPROC_FAIL_SET_VALUE,
							'error_handler_params' => 'xxx'
						],
						[
							'type' => ZBX_PREPROC_JSONPATH,
							'params' => '$.path.to.node4',
							'error_handler' => ZBX_PREPROC_FAIL_SET_ERROR,
							'error_handler_params' => 'error'
						]
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider discoveryrule_preprocessing_get_data_valid
	 */
	public function testDiscoveryRulePreprocessing_Get($discoveryrule, $get_result, $expected_error) {
		$result = $this->call('discoveryrule.get', $discoveryrule);

		if ($expected_error === null) {
			foreach ($result['result'] as $entry) {
				$this->assertSame($entry['itemid'], $get_result['itemid']);

				// Check related objects.
				if (array_key_exists('selectPreprocessing', $discoveryrule)) {
					$this->assertArrayHasKey('preprocessing', $get_result);

					if (array_key_exists('preprocessing', $get_result)) {
						$this->assertEquals($entry['preprocessing'], $get_result['preprocessing']);
					}
				}
				else {
					$this->assertArrayNotHasKey('preprocessing', $get_result);
				}
			}
		}
		else {
			$this->assertSame($result['result'], $get_result);
		}
	}

	public static function discoveryrule_copy_data_invalid() {
		return [
			'Test no discoveryids given when copying LLD rule' => [
				'params' => [
					'hostids' => ['50009']
				],
				'expected_error' => 'No discovery rule IDs given.'
			],
			'Test empty discoveryids when copying LLD rule' => [
				'params' => [
					'discoveryids' => '',
					'hostids' => ['50009']
				],
				'expected_error' => 'No discovery rule IDs given.'
			],
			'Test incorrect discoveryids type when copying LLD rule' => [
				'params' => [
					'discoveryids' => [],
					'hostids' => ['50009']
				],
				'expected_error' => 'No discovery rule IDs given.'
			],
			'Test no hostids given when copying LLD rule' => [
				'params' => [
					'discoveryids' => ['110006']
				],
				'expected_error' => 'No host IDs given.'
			],
			'Test empty hostids when copying LLD rule' => [
				'params' => [
					'discoveryids' => ['110006'],
					'hostids' => ''
				],
				'expected_error' => 'No host IDs given.'
			],
			'Test incorrect hostids type when copying LLD rule' => [
				'params' => [
					'discoveryids' => ['110006'],
					'hostids' => []
				],
				'expected_error' => 'No host IDs given.'
			],
			'Test copying on same host or when destination host already has that key' => [
				'params' => [
					'discoveryids' => ['110006'],
					'hostids' => ['50009']
				],
				'expected_error' => 'Item with key "apilldrule1" already exists on "API Host".'
			],
			'Test copying on non-existing host' => [
				'params' => [
					'discoveryids' => ['110006'],
					'hostids' => ['1']
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test copying a non-existing LLD rule' => [
				'params' => [
					'discoveryids' => ['1'],
					'hostids' => ['50012']
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test copying LLD rule to a template' => [
				'params' => [
					'discoveryids' => ['110006'],
					'hostids' => ['50010']
				],
				'expected_error' => 'Cannot find host interface on "API Template" for item key "apilldrule1".'
			],
			'Test duplicate hosts in request' => [
				'params' => [
					'discoveryids' => ['110006'],
					'hostids' => ['50012', '50012']
				],
				'expected_error' => 'Item with key "apilldrule1" already exists on "API Host for read permissions".'
			],
			'Test duplicate LLD rules in request' => [
				'params' => [
					'discoveryids' => ['110006', '110006'],
					'hostids' => ['50012']
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
				// TODO: Error is very strange and API should be checked for bugs.
			],
			'Test LLD dependent on master which does not exists on destination host.' => [
				'params' => [
					// test.discovery.rule.1:dependent.lld.3
					'discoveryids' => ['2605'],
					// test.discovery.rule.2
					'hostids' => ['1018']
				],
				'expected_error' => 'Discovery rule "dependent.lld.3" cannot be copied without its master item.'
			],
			'Test LLD dependent on master having max dependency levels.' => [
				'params' => [
					// test.discovery.rule.1:dependent.lld.1
					'discoveryids' => ['2601'],
					// test.discovery.rule.2
					'hostids' => ['1018']
				],
				'expected_error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.'
			]
		];
	}

	public static function discoveryrule_copy_data_valid() {
		return [
			'Test successful LLD rule copy to two hosts' => [
				'params' => [
					'discoveryids' => ['110006'],
					'hostids' => ['50012', '50013']
				],
				'expected_error' => null
			],
			'Test copy LLD dependent to host having master item with same key_' => [
				'params' => [
					// test.discovery.rule.1:dependent.lld.2
					'discoveryids' => ['2603'],
					// test.discovery.rule.2
					'hostids' => ['1018']
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider discoveryrule_copy_data_invalid
	 * @dataProvider discoveryrule_copy_data_valid
	 */
	public function testDiscoveryRule_Copy($params, $expected_error) {
		$result = $this->call('discoveryrule.copy', $params, $expected_error);

		if ($expected_error === null) {
			$this->assertTrue($result['result']);

			// Get all discovery rule fields.
			$src_items = CDBHelper::getAll(
				'SELECT i.type,i.snmp_community,i.snmp_oid,i.name,i.key_,i.delay,i.history,i.trends,'.
						'i.status,i.value_type,i.trapper_hosts,i.units,i.snmpv3_securityname,i.snmpv3_securitylevel,'.
						'i.snmpv3_authpassphrase,i.snmpv3_privpassphrase,i.logtimefmt,i.valuemapid,'.
						'i.params,i.ipmi_sensor,i.authtype,i.username,i.password,i.publickey,i.privatekey,'.
						'i.flags,i.port,i.description,i.inventory_link,i.lifetime,i.snmpv3_authprotocol,'.
						'i.snmpv3_privprotocol,i.snmpv3_contextname,i.jmx_endpoint,i.url,i.query_fields,i.timeout,'.
						'i.posts,i.status_codes,i.follow_redirects,i.post_type,i.http_proxy,i.headers,i.retrieve_mode,'.
						'i.request_method,i.ssl_cert_file,i.ssl_key_file,i.ssl_key_password,i.verify_peer,'.
						'i.verify_host,i.allow_traps'.
				' FROM items i'.
				' WHERE '.dbConditionId('i.itemid', $params['discoveryids'])
			);
			$src_items = zbx_toHash($src_items, 'key_');
			/*
			 * NOTE: Metadata like lastlogsize, mtime should not be copied. Fields like hostid, interfaceid, itemid
			 * are not selected, since they will be different.
			 */

			// Find same items on destination hosts.
			foreach ($params['discoveryids'] as $itemid) {
				$dst_items = CDBHelper::getAll(
					'SELECT src.type,src.snmp_community,src.snmp_oid,src.name,src.key_,'.
						'src.delay,src.history,src.trends,src.status,src.value_type,src.trapper_hosts,src.units,'.
						'src.snmpv3_securityname,src.snmpv3_securitylevel,src.snmpv3_authpassphrase,'.
						'src.snmpv3_privpassphrase,src.logtimefmt,src.valuemapid,src.params,'.
						'src.ipmi_sensor,src.authtype,src.username,src.password,src.publickey,src.privatekey,'.
						'src.flags,src.port,src.description,src.inventory_link,src.lifetime,'.
						'src.snmpv3_authprotocol,src.snmpv3_privprotocol,src.snmpv3_contextname,src.jmx_endpoint,'.
						'src.url,src.query_fields,src.timeout,src.posts,src.status_codes,src.follow_redirects,'.
						'src.post_type,src.http_proxy,src.headers,src.retrieve_mode,src.request_method,'.
						'src.ssl_cert_file,src.ssl_key_file,src.ssl_key_password,src.verify_peer,src.verify_host,'.
						'src.allow_traps'.
					' FROM items src,items dest'.
					' WHERE dest.itemid='.zbx_dbstr($itemid).
						' AND src.key_=dest.key_'.
						' AND '.dbConditionInt('src.hostid', $params['hostids'])
				);

				foreach ($dst_items as $dst_item) {
					$this->assertSame($src_items[$dst_item['key_']], $dst_item);
				}
			}
		}
	}

	public static function discoveryrule_lld_macro_paths_copy_data_valid() {
		return [
			'Test successful LLD rule with macro paths copy to two hosts' => [
				'params' => [
					'discoveryids' => ['110012'],
					'hostids' => ['50012', '50013']
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider discoveryrule_copy_data_invalid
	 * @dataProvider discoveryrule_lld_macro_paths_copy_data_valid
	 */
	public function testDiscoveryRuleLLDMacroPaths_Copy($params, $expected_error) {
		$result = $this->call('discoveryrule.copy', $params, $expected_error);

		if ($expected_error === null) {
			$this->assertTrue($result['result']);

			// Get discovery rule and LLD macro path fields.
			$src_lld_macro_paths = CDBHelper::getAll(
				'SELECT lmp.lld_macro,lmp.path,i.key_'.
				' FROM lld_macro_path lmp,items i'.
				' WHERE i.itemid=lmp.itemid'.
					' AND '.dbConditionId('i.itemid', $params['discoveryids'])
			);

			$src = [];
			foreach ($src_lld_macro_paths as $src_lld_macro_path) {
				$src[$src_lld_macro_path['key_']][] = $src_lld_macro_path;
			}

			// Find same items on destination hosts.
			foreach ($params['discoveryids'] as $itemid) {
				$dst_lld_macro_paths = CDBHelper::getAll(
					'SELECT lmp.lld_macro,lmp.path,src.key_,src.hostid'.
					' FROM lld_macro_path lmp,items src,items dest'.
					' WHERE dest.itemid='.zbx_dbstr($itemid).
						' AND src.key_=dest.key_'.
						' AND lmp.itemid=dest.itemid'.
						' AND '.dbConditionInt('src.hostid', $params['hostids'])
				);

				$dst = [];
				foreach ($dst_lld_macro_paths as $dst_lld_macro_path) {
					$dst[$dst_lld_macro_path['hostid']][$dst_lld_macro_path['key_']][] = $dst_lld_macro_path;
				}

				foreach ($dst as $discoveryrules) {
					foreach ($discoveryrules as $key => $lld_macro_paths) {
						foreach ($lld_macro_paths as &$lld_macro_path) {
							unset($lld_macro_path['hostid']);
						}
						unset($lld_macro_path);

						$this->assertSame($src[$key], $lld_macro_paths);
					}
				}
			}
		}
	}

	public static function discoveryrule_preprocessing_copy_data_valid() {
		return [
			'Test successful LLD rule with preprocessing copy to two hosts' => [
				'params' => [
					'discoveryids' => ['110013'],
					'hostids' => ['50012', '50013']
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider discoveryrule_copy_data_invalid
	 * @dataProvider discoveryrule_preprocessing_copy_data_valid
	 */
	public function testDiscoveryRulePreprocessing_Copy($params, $expected_error) {
		$result = $this->call('discoveryrule.copy', $params, $expected_error);

		if ($expected_error === null) {
			$this->assertTrue($result['result']);

			// Get discovery rule and pre-processing fields.
			$src_preprocessing = CDBHelper::getAll(
				'SELECT ip.step,ip.type,ip.params,ip.error_handler,ip.error_handler_params,i.key_'.
				' FROM item_preproc ip,items i'.
				' WHERE i.itemid=ip.itemid'.
					' AND '.dbConditionId('i.itemid', $params['discoveryids'])
			);

			$src = [];
			foreach ($src_preprocessing as $src_preproc_step) {
				$src[$src_preproc_step['key_']][$src_preproc_step['step']] = $src_preproc_step;
			}

			// Find same items on destination hosts.
			foreach ($params['discoveryids'] as $itemid) {
				$dst_preprocessing = CDBHelper::getAll(
					'SELECT ip.step,ip.type,ip.params,ip.error_handler,ip.error_handler_params,src.key_,src.hostid'.
					' FROM item_preproc ip,items src,items dest'.
					' WHERE dest.itemid='.zbx_dbstr($itemid).
						' AND src.key_=dest.key_'.
						' AND ip.itemid=dest.itemid'.
						' AND '.dbConditionInt('src.hostid', $params['hostids'])
				);

				$dst = [];
				foreach ($dst_preprocessing as $dst_preproc_step) {
					$dst[$dst_preproc_step['hostid']][$dst_preproc_step['key_']][$dst_preproc_step['step']] =
						$dst_preproc_step;
				}

				foreach ($dst as $discoveryrules) {
					foreach ($discoveryrules as $key => $preprocessing) {
						foreach ($preprocessing as &$preprocessing_step) {
							unset($preprocessing_step['hostid']);
						}
						unset($preprocessing_step);

						$this->assertSame($src[$key], $preprocessing);
					}
				}
			}
		}
	}

	public static function discoveryrule_preprocessing_delete_data() {
		return [
			'Test successful delete of LLD rule and preprocessing data' => [
				'discoveryrule' => [
					'110006'
				],
				'expected_error' => null
			]
		];

		// TODO: add templated discovery rules.
	}

	/**
	 * @dataProvider discoveryrule_preprocessing_delete_data
	 */
	public function testDiscoveryRulePreprocessing_Delete($discoveryrule, $expected_error) {
		$result = $this->call('discoveryrule.delete', $discoveryrule, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['ruleids'] as $id) {
				$this->assertEquals(0, CDBHelper::getCount(
					'SELECT i.itemid FROM items i WHERE i.itemid='.zbx_dbstr($id)
				));

				// Check related tables - preprocessing.
				$this->assertEquals(0, CDBHelper::getCount(
					'SELECT ip.item_preprocid'.
					' FROM item_preproc ip'.
					' WHERE ip.itemid='.zbx_dbstr($id)
				));
			}
		}

		// TODO: add templated discovery rules and check on errors.
	}

	public static function discoveryrule_lld_macro_paths_delete_data() {
		return [
			'Test successful delete of LLD rule and LLD macro paths' => [
				'discoveryrule' => [
					'110009'
				],
				'expected_error' => null
			]
		];

		// TODO: add templated discovery rules.
	}

	/**
	 * @dataProvider discoveryrule_lld_macro_paths_delete_data
	 */
	public function testDiscoveryRuleLLDMacroPaths_Delete($discoveryrule, $expected_error) {
		$result = $this->call('discoveryrule.delete', $discoveryrule, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['ruleids'] as $id) {
				$this->assertEquals(0, CDBHelper::getCount(
					'SELECT i.itemid FROM items i WHERE i.itemid='.zbx_dbstr($id)
				));

				// Check related tables - LLD macro paths.
				$this->assertEquals(0, CDBHelper::getCount(
					'SELECT lmp.lld_macro_pathid'.
					' FROM lld_macro_path lmp'.
					' WHERE lmp.itemid='.zbx_dbstr($id)
				));
			}
		}

		// TODO: add templated discovery rules and check on errors.
	}

	// TODO: add more tests to check other related discovery rule properties and perfom more tests on templates and templated objects.
}

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

	public static function discoveryrule_preprocessing_create_data() {
		$def_options = [
			'name' => 'API LLD rule 5',
			'key_' => 'apilldrule5',
			'hostid' => '50009',
			'type' => '0',
			'interfaceid' => '50022',
			'delay' => '30s'
		];

		// Check preprocessing fields.
		return [
			// Check incorrect preprocessing type.
			[
				'discoveryrule' => $def_options + [
					'preprocessing' => ''
				],
				'expected_error' => 'Incorrect arguments passed to function.'
			],
			// Check no fields given.
			[
				'discoveryrule' => $def_options + [
					'preprocessing' => [
						[]
					]
				],
				'expected_error' => 'Item pre-processing is missing parameters: type, params, error_handler, error_handler_params'
			],
			// Check empty fields.
			[
				'discoveryrule' => $def_options + [
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
			// Check invalid type (array).
			[
				'discoveryrule' => $def_options + [
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
			// Check invalid type (string).
			[
				'discoveryrule' => $def_options + [
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
			// Check invalid type (integer).
			[
				'discoveryrule' => $def_options + [
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
			// Check unallowed type (integer).
			[
				'discoveryrule' => $def_options + [
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
			// Check empty params, but valid type.
			[
				'discoveryrule' => $def_options + [
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
			// Check invalid params, but valid type.
			[
				'discoveryrule' => $def_options + [
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
			// Check second parameter for this type.
			[
				'discoveryrule' => $def_options + [
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
			// Check if error handler is empty.
			[
				'discoveryrule' => $def_options + [
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
			// Check if error handler is valid (array).
			[
				'discoveryrule' => $def_options + [
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
			// Check if error handler is valid (string).
			[
				'discoveryrule' => $def_options + [
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
			// Check if error handler is valid (integer).
			[
				'discoveryrule' => $def_options + [
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
			// Check if error handler params is empty (should not be).
			[
				'discoveryrule' => $def_options + [
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
			// Check if error handler params is valid (array).
			[
				'discoveryrule' => $def_options + [
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
			// Check if error handler params is empty (should be).
			[
				'discoveryrule' => $def_options + [
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
			// Check if error handler params is empty (should be).
			[
				'discoveryrule' => $def_options + [
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
			// Check ather types.
			[
				'discoveryrule' => $def_options + [
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
			[
				'discoveryrule' => $def_options + [
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
			[
				'discoveryrule' => $def_options + [
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
			[
				'discoveryrule' => $def_options + [
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
			[
				'discoveryrule' => $def_options + [
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
			[
				'discoveryrule' => $def_options + [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_ERROR_FIELD_JSON,
							'params' => 'abc',
							'error_handler' => ZBX_PREPROC_FAIL_SET_ERROR,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler": unexpected value "'.ZBX_PREPROC_FAIL_SET_ERROR.'".'
			],
			[
				'discoveryrule' => $def_options + [
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
			[
				'discoveryrule' => $def_options + [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_ERROR_FIELD_JSON,
							'params' => 'abc',
							'error_handler' => ZBX_PREPROC_FAIL_DISCARD_VALUE,
							'error_handler_params' => 'Error param'
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler": unexpected value "'.ZBX_PREPROC_FAIL_DISCARD_VALUE.'".'
			],
			[
				'discoveryrule' => $def_options + [
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
			[
				'discoveryrule' => $def_options + [
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
			[
				'discoveryrule' => $def_options + [
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
			[
				'discoveryrule' => $def_options + [
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
			[
				'discoveryrule' => $def_options + [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_ERROR_FIELD_JSON,
							'params' => 'abc',
							'error_handler' => ZBX_PREPROC_FAIL_DISCARD_VALUE,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler": unexpected value "'.ZBX_PREPROC_FAIL_DISCARD_VALUE.'".'
			],
			// Check two steps.
			[
				'discoveryrule' => $def_options + [
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
			// Check valid LLD rules.
			[
				'discoveryrule' => $def_options + [
					'preprocessing' => []
				],
				'expected_error' => null
			],
			[
				'discoveryrule' => $def_options + [
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
			[
				'discoveryrule' => $def_options + [
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
			[
				'discoveryrule' => $def_options + [
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
			[
				'discoveryrule' => $def_options + [
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
			[
				'discoveryrule' => $def_options + [
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
			[
				'discoveryrule' => $def_options + [
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
			[
				'discoveryrule' => $def_options + [
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
			[
				'discoveryrule' => $def_options + [
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
			[
				'discoveryrule' => $def_options + [
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
			[
				'discoveryrule' => $def_options + [
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

		// TODO: Create tests for items and item prototypes. It uses the same function to validate pre-processing fields.
	}

	/**
	 * @dataProvider discoveryrule_preprocessing_create_data
	 * @backup items
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

		// TODO: Create a test to check if LLD macro paths are inherited on host.
	}

	// TODO: create a separate test to perform updates on discovery rules and its properties.

	public static function discoveryrule_preprocessing_update_data() {
		/*
		 * Test data mostly just duplicates the data from create() test, but with few exceptions because:
		 *   1) create and update both allow "preprocessing" to be empty.
		 *   2) No individual step update, so it's just replacing everything with the new array.
		 */

		return [
			// Check invalid preprocessing type.
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'preprocessing' => ''
				],
				'expected_error' => 'Incorrect arguments passed to function.'
			],
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'preprocessing' => [
						[]
					]
				],
				'expected_error' => 'Item pre-processing is missing parameters: type, params, error_handler, error_handler_params'
			],
			// Check empty fields.
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			// Check invalid type (array).
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			// Check invalid type (string).
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			// Check invalid type (integer).
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			// Check unallowed type (integer).
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			// Check empty params, but valid type.
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			// Check invalid params, but valid type.
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			// Check second parameter for this type.
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			// Check if error handler is empty.
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			// Check if error handler is valid (array).
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			// Check if error handler is valid (string).
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			// Check if error handler is valid (integer).
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			// Check if error handler params is empty (should not be).
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			// Check if error handler params is valid (array).
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			// Check if error handler params is empty (should be).
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			// Check if error handler params is empty (should be).
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			// Check ather types.
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_ERROR_FIELD_JSON,
							'params' => 'abc',
							'error_handler' => ZBX_PREPROC_FAIL_SET_ERROR,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler": unexpected value "'.ZBX_PREPROC_FAIL_SET_ERROR.'".'
			],
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_ERROR_FIELD_JSON,
							'params' => 'abc',
							'error_handler' => ZBX_PREPROC_FAIL_DISCARD_VALUE,
							'error_handler_params' => 'Error param'
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler": unexpected value "'.ZBX_PREPROC_FAIL_DISCARD_VALUE.'".'
			],
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_ERROR_FIELD_JSON,
							'params' => 'abc',
							'error_handler' => ZBX_PREPROC_FAIL_DISCARD_VALUE,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler": unexpected value "'.ZBX_PREPROC_FAIL_DISCARD_VALUE.'".'
			],
			// Check two steps.
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			// Check individual step update with only one parameter.
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'preprocessing' => [
						[
							'item_preprocid' => '5716',
							'params' => '2h'
						]
					]
				],
				'expected_error' => 'Item pre-processing is missing parameters: type, error_handler, error_handler_params'
			],
			// Check templated steps update.
			[
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
			// Check replacing of steps, but also give specific step ID (which is ignored).
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			// Check valid LLD rules.
			[
				'discoveryrule' => [
					'itemid' => '110006',
					'preprocessing' => []
				],
				'expected_error' => null
			],
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			[
				'discoveryrule' => [
					'itemid' => '110006',
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
			// Check different discovery rule that has no old steps set.
			[
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
	 * @dataProvider discoveryrule_preprocessing_update_data
	 * @backup items
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

	public static function discoveryrule_preprocessing_delete_data() {
		return [
			// Check successful delete of discovery rule and pre-processing data.
			[
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
	 * @backup items
	 */
	public function testDiscoveryRulePreprocessing_Delete($discoveryrule, $expected_error) {
		$result = $this->call('discoveryrule.delete', $discoveryrule, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['ruleids'] as $id) {
				$this->assertEquals(0, CDBHelper::getCount(
					'SELECT i.itemid FROM items i WHERE i.itemid='.zbx_dbstr($id)
				));

				// Check related tables.
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
			// Check successful delete of discovery rule and LLD macro paths.
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
	 * @dataProvider discoveryrule_lld_macro_paths_delete_data
	 * @backup items
	 */
	public function testDiscoveryRuleLLDMacroPaths_Delete($discoveryrule, $expected_error) {
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
				'expected_error' => true
			]
		];

		// TODO: add other discovery rule properties.
	}

	/**
	 * @dataProvider discoveryrule_get_data
	 */
	public function testDiscoveryRule_Get($discoveryrule, $get_result, $expected_error) {
		// TODO: fill this test with more fields to check.

		$result = $this->call('discoveryrule.get', $discoveryrule);

		if ($expected_error === false) {
			foreach ($result['result'] as $entry) {
				$this->assertSame($entry['itemid'], $get_result['itemid']);
			}
		}
		else {
			$this->assertSame($result['result'], $get_result);
		}
	}

	public static function discoveryrule_lld_macro_paths_get_data() {
		return [
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
					'selectLLDMacroPaths' => 'extend'
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
	}

	/**
	 * @dataProvider discoveryrule_lld_macro_paths_get_data
	 */
	public function testDiscoveryRuleLLDMacroPaths_Get($discoveryrule, $get_result, $expected_error) {
		$result = $this->call('discoveryrule.get', $discoveryrule);

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

	public static function discoveryrule_preprocessing_get_data() {
		return [
			[
				'discoveryrule' => [
					'output' => ['itemid'],
					'itemids' => '110006',
					'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params']
				],
				'get_result' => [
					'itemid' => '110006',
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
				'expected_error' => false
			],
			[
				'discoveryrule' => [
					'output' => ['itemid'],
					'itemids' => '110010',
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
				'expected_error' => false
			],
			[
				'discoveryrule' => [
					'output' => ['itemid'],
					'itemids' => '110011',
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
				'expected_error' => false
			]
		];
	}

	/**
	 * @dataProvider discoveryrule_preprocessing_get_data
	 */
	public function testDiscoveryRulePreprocessing_Get($discoveryrule, $get_result, $expected_error) {
		$result = $this->call('discoveryrule.get', $discoveryrule);

		if ($expected_error === false) {
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

	public static function discoveryrule_copy_data() {
		return [
			// Check empty LLD rule IDs.
			[
				'params' => [
					'hostids' => ['50009']
				],
				'expected_error' => 'No discovery rule IDs given.'
			],
			[
				'params' => [
					'discoveryids' => '',
					'hostids' => ['50009']
				],
				'expected_error' => 'No discovery rule IDs given.'
			],
			[
				'params' => [
					'discoveryids' => [],
					'hostids' => ['50009']
				],
				'expected_error' => 'No discovery rule IDs given.'
			],
			// Check empty host IDs.
			[
				'params' => [
					'discoveryids' => ['110006']
				],
				'expected_error' => 'No host IDs given.'
			],
			[
				'params' => [
					'discoveryids' => ['110006'],
					'hostids' => ''
				],
				'expected_error' => 'No host IDs given.'
			],
			[
				'params' => [
					'discoveryids' => ['110006'],
					'hostids' => []
				],
				'expected_error' => 'No host IDs given.'
			],
			// Check same host.
			[
				'params' => [
					'discoveryids' => ['110006'],
					'hostids' => ['50009']
				],
				'expected_error' => 'Item with key "apilldrule1" already exists on "API Host".'
			],
			// Check invalid host.
			[
				'params' => [
					'discoveryids' => ['110006'],
					'hostids' => ['1']
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Check invalid rule.
			[
				'params' => [
					'discoveryids' => ['1'],
					'hostids' => ['50012']
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Copy from host to template.
			[
				'params' => [
					'discoveryids' => ['110006'],
					'hostids' => ['50010']
				],
				'expected_error' => 'Cannot find host interface on "API Template" for item key "apilldrule1".'
			],
			// Check duplicate hosts.
			[
				'params' => [
					'discoveryids' => ['110006'],
					'hostids' => ['50012', '50012']
				],
				'expected_error' => 'Item with key "apilldrule1" already exists on "API Host for read permissions".'
			],
			// Check duplicate rules.
			[
				'params' => [
					'discoveryids' => ['110006', '110006'],
					'hostids' => ['50012']
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
				// TODO: Error is very strange and API should be checked for bugs.
			],
			// Check successful copy to two hosts.
			[
				'params' => [
					'discoveryids' => ['110006'],
					'hostids' => ['50012', '50013']
				],
				'expected_error' => null
			]
		];

		// NOTE: There are no discovered hosts to check copying to them.
	}

	/**
	 * @dataProvider discoveryrule_copy_data
	 * @backup items
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

	/**
	 * @dataProvider discoveryrule_copy_data
	 * @backup items
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

	/**
	 * @dataProvider discoveryrule_copy_data
	 * @backup items
	 */
	public function testDiscoveryRulePreprocessing_Copy($params, $expected_error) {
		$result = $this->call('discoveryrule.copy', $params, $expected_error);

		if ($expected_error === null) {
			$this->assertTrue($result['result']);

			// Get discovery rule and pre-processign fields.
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

	// TODO: add more tests to check other related discovery rule properties and perfom more tests on templates and templated objects.
}

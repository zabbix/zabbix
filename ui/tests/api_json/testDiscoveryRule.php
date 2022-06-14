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
require_once dirname(__FILE__).'/../../include/classes/parsers/CConditionFormula.php';
require_once dirname(__FILE__).'/../../include/classes/helpers/CConditionHelper.php';

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
					'type' => ITEM_TYPE_ZABBIX,
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
					'type' => ITEM_TYPE_ZABBIX,
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
					'type' => ITEM_TYPE_ZABBIX,
					'interfaceid' => '50022',
					'delay' => '30s'
				],
				'expected_error' => 'Item with key "apilldrule4" already exists on "API Host".'
			],
			'Test without update interval for mqtt.get key of Agent type' => [
				'discoveryrule' => [
					'name' => 'API mqtt.get',
					'key_' => 'mqtt.get[test]',
					'hostid' => '50009',
					'type' => ITEM_TYPE_ZABBIX,
					'interfaceid' => '50022'
				],
				'expected_error' => 'Incorrect arguments passed to function.'
			],
			'Test 0 update interval for mqtt.get key of Agent type' => [
				'discoveryrule' => [
					'name' => 'API mqtt.get',
					'key_' => 'mqtt.get[test]',
					'hostid' => '50009',
					'type' => ITEM_TYPE_ZABBIX,
					'interfaceid' => '50022',
					'delay' => '0'
				],
				'expected_error' => 'Item will not be refreshed. Specified update interval requires having at least one either flexible or scheduling interval.'
			],
			'Test 0 update interval for wrong mqtt key of Active agent type' => [
				'discoveryrule' => [
					'name' => 'API mqtt.get',
					'key_' => 'mqt.get[test]',
					'hostid' => '50009',
					'type' => ITEM_TYPE_ZABBIX_ACTIVE,
					'delay' => '0'
				],
				'expected_error' => 'Item will not be refreshed. Specified update interval requires having at least one either flexible or scheduling interval.'
			],
			'Test  LLD rule with unsupported item type' => [
				'discoveryrule' => [
					'name' => 'API LLD rule with unsupported item type',
					'key_' => 'api_lld_rule_with_unsupported_item_type',
					'hostid' => '50009',
					'type' => '100',
					'interfaceid' => '50022',
					'delay' => '30s'
				],
				'expected_error' => 'Invalid parameter "/1/type": value must be one of '.implode(', ', [
					ITEM_TYPE_ZABBIX, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE,
					ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET,
					ITEM_TYPE_JMX, ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT
				]).'.'
			]
		];

		// TODO: add other properties, multiple rules, duplicates etc.
	}

	public static function discoveryrule_create_data_valid() {
		$valid_item_types = [
			ITEM_TYPE_ZABBIX => '50022',
			ITEM_TYPE_TRAPPER => null,
			ITEM_TYPE_SIMPLE => '50022',
			ITEM_TYPE_INTERNAL => null,
			ITEM_TYPE_ZABBIX_ACTIVE => null,
			ITEM_TYPE_EXTERNAL => '50022',
			ITEM_TYPE_DB_MONITOR => null,
			ITEM_TYPE_IPMI => '50031',
			ITEM_TYPE_SSH => '50022',
			ITEM_TYPE_TELNET => '50022',
			ITEM_TYPE_JMX => '50030',
			ITEM_TYPE_DEPENDENT => null,
			ITEM_TYPE_HTTPAGENT => '50022',
			ITEM_TYPE_SNMP => '50029'
		];

		$item_type_tests = [];
		foreach ($valid_item_types as $type => $interfaceid) {
			switch ($type) {
				case ITEM_TYPE_IPMI:
					$params = [
						'ipmi_sensor' => '1.2.3'
					];
					break;

				case ITEM_TYPE_TRAPPER:
					$params = [
						'delay' => '0'
					];
					break;

				case ITEM_TYPE_TELNET:
				case ITEM_TYPE_SSH:
					$params = [
						'username' => 'username',
						'authtype' => ITEM_AUTHTYPE_PASSWORD
					];
					break;

				case ITEM_TYPE_DEPENDENT:
					$params = [
						'master_itemid' => '150151',
						'delay' => '0'
					];
					break;

				case ITEM_TYPE_JMX:
					$params = [
						'username' => 'username',
						'password' => 'password'
					];
					break;

				case ITEM_TYPE_HTTPAGENT:
					$params = [
						'url' => 'http://0.0.0.0'
					];
					break;

				case ITEM_TYPE_SNMP:
					$params = [
						'snmp_oid' => '1.2.3'
					];
					break;

				default:
					$params = [];
					break;
			}

			if ($interfaceid) {
				$params['interfaceid'] = $interfaceid;
			}

			$item_type_tests['Test valid LLD rule with item type '.$type] = [
				'discoveryrule' => $params + [
					'name' => 'API LLD rule of type '.$type,
					'key_' => 'api_lld_rule_of_type_'.$type,
					'hostid' => '50009',
					'type' => (string) $type,
					'delay' => '30s'
				],
				'expected_error' => null
			];
		}

		return [
			'Test 0 update interval for mqtt.get key of Active agent type' => [
				'discoveryrule' => [
					'name' => 'API LLD rule mqtt',
					'key_' => 'mqtt.get[0]',
					'hostid' => '50009',
					'type' => ITEM_TYPE_ZABBIX_ACTIVE,
					'delay' => '0'
				],
				'expected_error' => null
			],
			'Test without update interval for mqtt.get key of Active agent type' => [
				'discoveryrule' => [
					'name' => 'API LLD rule mqtt',
					'key_' => 'mqtt.get[1]',
					'hostid' => '50009',
					'type' => ITEM_TYPE_ZABBIX_ACTIVE
				],
				'expected_error' => null
			]
		] + $item_type_tests;

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

				if ($discoveryrules[$num]['type'] === ITEM_TYPE_ZABBIX_ACTIVE && substr($discoveryrules[$num]['key_'], 0, 8) === 'mqtt.get') {
					$discoveryrules[$num]['delay'] = CTestArrayHelper::get($discoveryrules[$num], 'delay', '0');
				}
				$this->assertSame($db_discoveryrule['hostid'], $discoveryrules[$num]['hostid']);
				$this->assertSame($db_discoveryrule['name'], $discoveryrules[$num]['name']);
				$this->assertSame($db_discoveryrule['key_'], $discoveryrules[$num]['key_']);
				$this->assertSame($db_discoveryrule['type'], strval($discoveryrules[$num]['type']));
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
							'lld_macro_pathid' => '999999'
						]
					]
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test duplicate LLD macro paths entries by giving lld_macro_pathid and existing lld_macro' => [
				'discoveryrule' => $default_options + [
					'lld_macro_paths' => [
						[
							'lld_macro_pathid' => '992'
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
			],
			'Test empty preprocessing parameters for ZBX_PREPROC_CSV_TO_JSON type' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_CSV_TO_JSON,
							'params' => '',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "params": cannot be empty.'
			],
			'Test invalid (null) preprocessing parameters for ZBX_PREPROC_CSV_TO_JSON type' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_CSV_TO_JSON,
							'params' => null,
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "params": cannot be empty.'
			],
			'Test invalid (false) preprocessing parameters for ZBX_PREPROC_CSV_TO_JSON type' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_CSV_TO_JSON,
							'params' => false,
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "params": cannot be empty.'
			],
			'Test invalid (array) preprocessing parameters for ZBX_PREPROC_CSV_TO_JSON type' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_CSV_TO_JSON,
							'params' => [],
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect arguments passed to function.'
			],
			'Test invalid (too many) preprocessing parameters for ZBX_PREPROC_CSV_TO_JSON type' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_CSV_TO_JSON,
							'params' => "\n\n\n",
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect arguments passed to function.'
			],
			'Test missing third preprocessing parameter for ZBX_PREPROC_CSV_TO_JSON type' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_CSV_TO_JSON,
							'params' => "\n",
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "params": third parameter is expected.'
			],
			'Test first preprocessing parameter (too long) for ZBX_PREPROC_CSV_TO_JSON type' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_CSV_TO_JSON,
							'params' => "xx\n\n1",
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "params": value of first parameter is too long.'
			],
			'Test second preprocessing parameter (too long) for ZBX_PREPROC_CSV_TO_JSON type' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_CSV_TO_JSON,
							'params' => ",\nyy\n1",
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "params": value of second parameter is too long.'
			],
			'Test third preprocessing parameter (incorrect value) for ZBX_PREPROC_CSV_TO_JSON type' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_CSV_TO_JSON,
							'params' => "\n\n",
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "params": value of third parameter must be one of '.ZBX_PREPROC_CSV_NO_HEADER.', '.ZBX_PREPROC_CSV_HEADER.'.'
			],
			'Test non-empty preprocessing parameters for ZBX_PREPROC_XML_TO_JSON type' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_XML_TO_JSON,
							'params' => 'abc',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "params": should be empty.'
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
			],
			'Test valid preprocessing with type ZBX_PREPROC_CSV_TO_JSON having empty first two parameters' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_CSV_TO_JSON,
							'params' => "\n\n1",
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => null
			],
			'Test valid preprocessing with type ZBX_PREPROC_CSV_TO_JSON having empty first parameter' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_CSV_TO_JSON,
							'params' => "\ny\n1",
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => null
			],
			'Test valid preprocessing with type ZBX_PREPROC_CSV_TO_JSON having empty second parameter' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_CSV_TO_JSON,
							'params' => "x\n\n1",
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => null
			],
			'Test valid preprocessing with type ZBX_PREPROC_CSV_TO_JSON having all parameters' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_CSV_TO_JSON,
							'params' => ",\n\"\n0",
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => null
			],
			'Test valid preprocessing with type ZBX_PREPROC_XML_TO_JSON having empty parameters' => [
				'discoveryrule' => [
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_XML_TO_JSON,
							'params' => '',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
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
							'lld_macro_pathid' => '996'
						],
						[
							'lld_macro_pathid' => '997'
						],
						[
							'lld_macro_pathid' => '998'
						]
					]
				],
				'expected_error' => null
			],
			'Test successful update by not changing existing records by giving lld_macro_pathid and same lld_macro' => [
				'discoveryrule' => [
					'itemid' => '110007',
					'lld_macro_paths' => [
						[
							'lld_macro_pathid' => '996',
							'lld_macro' => '{#A}'
						],
						[
							'lld_macro_pathid' => '997',
							'lld_macro' => '{#B}'
						],
						[
							'lld_macro_pathid' => '998',
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
							'lld_macro_pathid' => '996',
							'path' => '$.list[:6].type'
						],
						[
							'lld_macro_pathid' => '997',
							'path' => '$.list[:7].type'
						],
						[
							'lld_macro_pathid' => '998',
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
							'lld_macro_pathid' => '996',
							'lld_macro' => '{#X}',
							'path' => '$.list[:9].type'
						],
						[
							'lld_macro_pathid' => '997',
							'lld_macro' => '{#Y}',
							'path' => '$.list[:10].type'
						],
						[
							'lld_macro_pathid' => '998',
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
							'lld_macro_pathid' => '996'
						],
						[
							'lld_macro_pathid' => '997'
						],
						[
							'lld_macro_pathid' => '998'
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
							'lld_macro_pathid' => '991',
							'lld_macro' => '{#V}'
						],
						[
							'lld_macro_pathid' => '992',
							'lld_macro' => '{#E}',
							'path' => '$.list[:6].type'
						],
						[
							'lld_macro_pathid' => '993',
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
							'lld_macro_pathid' => '991',
							'lld_macro' => '{#V}'
						],
						[
							'lld_macro_pathid' => '992',
							'lld_macro' => '{#E}',
							'path' => '$.list[:6].type'
						],
						[
							'lld_macro_pathid' => '993',
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

						// If "lld_macro_pathid" is not given, compare by "itemid" and "lld_macro" (unique combination).
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
				'expected_result' => [
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
				'expected_result' => [
					'itemid' => $itemid,
					'lld_macro_paths' => [
						[
							'lld_macro_pathid' => '1008',
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type'
						],
						[
							'lld_macro_pathid' => '1009',
							'lld_macro' => '{#B}',
							'path' => '$.list[:2].type'
						],
						[
							'lld_macro_pathid' => '1010',
							'lld_macro' => '{#C}',
							'path' => '$.list[:3].type'
						],
						[
							'lld_macro_pathid' => '1011',
							'lld_macro' => '{#D}',
							'path' => '$.list[:4].type'
						],
						[
							'lld_macro_pathid' => '1012',
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
				'expected_result' => [
					'itemid' => $itemid,
					'lld_macro_paths' => [
						[
							'lld_macro_pathid' => '1008',
							'lld_macro' => '{#A}',
							'path' => '$.list[:1].type'
						],
						[
							'lld_macro_pathid' => '1009',
							'lld_macro' => '{#B}',
							'path' => '$.list[:2].type'
						],
						[
							'lld_macro_pathid' => '1010',
							'lld_macro' => '{#C}',
							'path' => '$.list[:3].type'
						],
						[
							'lld_macro_pathid' => '1011',
							'lld_macro' => '{#D}',
							'path' => '$.list[:4].type'
						],
						[
							'lld_macro_pathid' => '1012',
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
	public function testDiscoveryRuleLLDMacroPaths_Get($discoveryrule, $expected_result, $expected_error) {
		$result = $this->call('discoveryrule.get', $discoveryrule);

		if ($expected_error === null) {
			foreach ($result['result'] as $entry) {
				$this->assertSame($expected_result['itemid'], $entry['itemid']);

				// Check related objects.
				if (array_key_exists('selectLLDMacroPaths', $discoveryrule)) {
					$this->assertArrayHasKey('lld_macro_paths', $entry);
					CTestArrayHelper::usort($entry['lld_macro_paths'], ['lld_macro']);

					$this->assertSame($expected_result['lld_macro_paths'], $entry['lld_macro_paths']);
				}
				else {
					$this->assertArrayNotHasKey('lld_macro_paths', $entry);
				}
			}
		}
		else {
			$this->assertSame($result['result'], $expected_result);
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
				'SELECT i.type,i.snmp_oid,i.name,i.key_,i.delay,i.history,i.trends,'.
						'i.status,i.value_type,i.trapper_hosts,i.units,i.logtimefmt,i.valuemapid,'.
						'i.params,i.ipmi_sensor,i.authtype,i.username,i.password,i.publickey,i.privatekey,'.
						'i.flags,i.description,i.inventory_link,i.lifetime,i.jmx_endpoint,i.url,i.query_fields,i.timeout,'.
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
					'SELECT src.type,src.snmp_oid,src.name,src.key_,'.
						'src.delay,src.history,src.trends,src.status,src.value_type,src.trapper_hosts,src.units,'.
						'src.logtimefmt,src.valuemapid,src.params,'.
						'src.ipmi_sensor,src.authtype,src.username,src.password,src.publickey,src.privatekey,'.
						'src.flags,src.description,src.inventory_link,src.lifetime,src.jmx_endpoint,'.
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

	public static function discoveryrule_overrides_delete_data() {
		return [
			'Test cannot delete nothing.' => [
				[],
				[],
				[],
				'Invalid parameter "/": cannot be empty.'
			],
			'Test cannot delete what does not exist.' => [
				['9999999999'],
				[],
				[],
				'No permissions to referred object or it does not exist!'
			],
			'Test overrides and override operations are deleted.' => [
				['133763'],
				['10001', '10002'],
				['10001', '10002', '10003', '10004', '10005', '10006'],
				null
			]
		];
	}

	/**
	 * @dataProvider discoveryrule_overrides_delete_data
	 */
	public function testDiscoveryRuleOverrides_Delete(array $itemids, array $overrideids, array $operationids, $error) {
		$result = $this->call('discoveryrule.delete', $itemids, $error);

		if ($error === null) {
			$this->assertEquals($result['result']['ruleids'], $itemids);

			$db_lld_overrides = CDBHelper::getAll('SELECT * from lld_override WHERE '.
				dbConditionId('lld_overrideid', $overrideids)
			);
			$this->assertEmpty($db_lld_overrides);

			$lld_override_conditions = CDBHelper::getAll('SELECT * from lld_override_condition WHERE '.
				dbConditionId('lld_overrideid', $overrideids)
			);
			$this->assertEmpty($lld_override_conditions);

			$lld_override_operations = CDBHelper::getAll('SELECT * from lld_override_operation WHERE '.
				dbConditionId('lld_overrideid', $overrideids)
			);
			$this->assertEmpty($lld_override_operations);

			$lld_override_opdiscover = CDBHelper::getAll('SELECT * from lld_override_opdiscover WHERE '.
				dbConditionId('lld_override_operationid', $operationids)
			);
			$this->assertEmpty($lld_override_opdiscover);

			$lld_override_opstatus = CDBHelper::getAll('SELECT * from lld_override_opstatus WHERE '.
				dbConditionId('lld_override_operationid', $operationids)
			);
			$this->assertEmpty($lld_override_opstatus);

			$lld_override_ophistory = CDBHelper::getAll('SELECT * from lld_override_ophistory WHERE '.
				dbConditionId('lld_override_operationid', $operationids)
			);
			$this->assertEmpty($lld_override_ophistory);

			$lld_override_opinventory = CDBHelper::getAll('SELECT * from lld_override_opinventory WHERE '.
				dbConditionId('lld_override_operationid', $operationids)
			);
			$this->assertEmpty($lld_override_opinventory);

			$lld_override_opperiod = CDBHelper::getAll('SELECT * from lld_override_opperiod WHERE '.
				dbConditionId('lld_override_operationid', $operationids)
			);
			$this->assertEmpty($lld_override_opperiod);

			$lld_override_opseverity = CDBHelper::getAll('SELECT * from lld_override_opseverity WHERE '.
				dbConditionId('lld_override_operationid', $operationids)
			);
			$this->assertEmpty($lld_override_opseverity);

			$lld_override_optag = CDBHelper::getAll('SELECT * from lld_override_optag WHERE '.
				dbConditionId('lld_override_operationid', $operationids)
			);
			$this->assertEmpty($lld_override_optag);

			$lld_override_optemplate = CDBHelper::getAll('SELECT * from lld_override_optemplate WHERE '.
				dbConditionId('lld_override_operationid', $operationids)
			);
			$this->assertEmpty($lld_override_optemplate);

			$lld_override_optrends = CDBHelper::getAll('SELECT * from lld_override_optrends WHERE '.
				dbConditionId('lld_override_operationid', $operationids)
			);
			$this->assertEmpty($lld_override_optrends);
		}
	}

	public static function discoveryrule_overrides_create_data_invalid() {
		$num = 0;
		$new_lld_overrides = function(array $overrides) use (&$num) {
			return [
				'name' => 'Overrides (invalid)',
				'key_' => 'invalid.lld.with.overrides.'.($num ++),
				'hostid' => '50009',
				'type' => ITEM_TYPE_TRAPPER,
				'overrides' => $overrides
			];
		};

		return [
			// LLD rule overrides
			'Test /1/overrides/2/name is mandatory.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 2
						],
						[
							'step' => 1
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/2": the parameter "name" is missing.'
			],
			'Test /1/overrides/2/step must be numeric.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 'A'
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/step": an integer is expected.'
			],
			'Test /1/overrides/2/step is mandatory.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 2
						],
						[
							'name' => 'override 2'
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/2": the parameter "step" is missing.'
			],
			'Test /1/overrides/2/step must be unique.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 2
						],
						[
							'name' => 'override 2',
							'step' => 2
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/2": value (step)=(2) already exists.'
			],
			'Test /1/overrides/2/name must be unique.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 4
						],
						[
							'name' => 'override',
							'step' => 2
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/2": value (name)=(override) already exists.'
			],
			'Test /1/overrides/1/stop field is validated.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'stop' => 2
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/stop": value must be one of '.implode(', ', [ZBX_LLD_OVERRIDE_STOP_NO, ZBX_LLD_OVERRIDE_STOP_YES]).'.'
			],
			// LLD rule override filter
			'Test /1/overrides/1/filter/evaltype is mandatory.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'filter' => []
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/filter": the parameter "evaltype" is missing.'
			],
			'Test /1/overrides/1/filter/evaltype is validated.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'filter' => [
								'evaltype' => 4
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/filter/evaltype": value must be one of '.implode(', ', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION]).'.'
			],
			'Test /1/overrides/1/filter/formula is required if /1/overrides/1/filter/evaltype == 3 (custom expression).' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
								'conditions' => [
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_NOT_REGEXP,
										'value' => ''
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Formula missing for override "override".'
			],
			'Test /1/overrides/1/filter/formula cannot be empty.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
								'formula' => '',
								'conditions' => [
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_NOT_REGEXP,
										'value' => ''
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Incorrect custom expression "" for override "override": expression is empty.'
			],
			'Test /1/overrides/1/filter/formula cannot be incorrect.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
								'formula' => 'x',
								'conditions' => [
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_NOT_REGEXP,
										'value' => ''
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Incorrect custom expression "x" for override "override": check expression starting from "x".'
			],
			'Test /1/overrides/1/filter/formula refers to undefined condition (missing formulaid field).' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
								'formula' => 'B or A',
								'conditions' => [
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_NOT_REGEXP,
										'value' => ''
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Condition "B" used in formula "B or A" for override "override" is not defined.'
			],
			'Test /1/overrides/1/filter/formula refers to undefined condition (missing another condition).' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
								'formula' => 'B or A',
								'conditions' => [
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_NOT_REGEXP,
										'value' => '',
										'formulaid' => 'B'
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Condition "A" used in formula "B or A" for override "override" is not defined.'
			],
			'Test /1/overrides/1/filter/eval_formula is read_only.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
								'eval_formula' => 'A',
								'formula' => 'A',
								'conditions' => [
									[
										'macro' => '{#CORRECT}',
										'operator' => CONDITION_OPERATOR_NOT_REGEXP,
										'value' => '',
										'formulaid' => 'A'
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/filter": unexpected parameter "eval_formula".'
			],
			// LLD rule override filter conditions
			'Test /1/overrides/1/filter/conditions field is mandatory.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/filter": the parameter "conditions" is missing.'
			],
			'Test /1/overrides/1/filter/conditions object cannot be empty.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
								'conditions' => []
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/filter/conditions": cannot be empty.'
			],
			'Test /1/overrides/1/filter/conditions/1/macro field is mandatory.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
								'conditions' => [
									[]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/filter/conditions/1": the parameter "macro" is missing.'
			],
			'Test /1/overrides/1/filter/conditions/1/macro is validated.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
								'formula' => 'A',
								'conditions' => [
									[
										'macro' => '{##INCORRECT}',
										'operator' => CONDITION_OPERATOR_NOT_REGEXP,
										'value' => '',
										'formulaid' => 'A'
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Incorrect filter condition macro for override "override".'
			],
			'Test /1/overrides/1/filter/conditions/1/value is mandatory.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
								'conditions' => [
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_NOT_REGEXP
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/filter/conditions/1": the parameter "value" is missing.'
			],
			'Test /1/overrides/1/filter/conditions/1/operator type is validated.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
								'conditions' => [
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_YES
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/filter/conditions/1/operator": value must be one of '.implode(', ', [CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP, CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_NOT_EXISTS]).'.'
			],
			// LLD rule override operation
			'Test /1/overrides/1/operations/1/operationobject type is validated.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => 4
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/operationobject": value must be one of '.implode(', ', [OPERATION_OBJECT_ITEM_PROTOTYPE, OPERATION_OBJECT_TRIGGER_PROTOTYPE, OPERATION_OBJECT_GRAPH_PROTOTYPE, OPERATION_OBJECT_HOST_PROTOTYPE]).'.'
			],
			'Test /1/overrides/1/operations/1/operator type is validated.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_YES
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/operator": value must be one of '.implode(', ', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP]).'.'
			],
			'Test /1/overrides/1/operations/1 at least one action is mandatory.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1": value must be one of opstatus, opdiscover, opperiod, ophistory, optrends, optag.'
			],
			// LLD rule override operation status
			'Test /1/overrides/1/operations/1/opstatus/status is mandatory.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opstatus' => []
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/opstatus": the parameter "status" is missing.'
			],
			'Test /1/overrides/1/operations/1/opstatus/status is validated.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opstatus' => [
										'status' => 2
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/opstatus/status": value must be one of '.implode(', ', [ZBX_PROTOTYPE_STATUS_ENABLED, ZBX_PROTOTYPE_STATUS_DISABLED]).'.'
			],
			'Test /1/overrides/1/operations/1/opstatus is not supported for graph prototype object.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_GRAPH_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opstatus' => [
										'status' => ZBX_PROTOTYPE_STATUS_ENABLED
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1": unexpected parameter "opstatus".'
			],
			// LLD rule override operation discover
			'Test /1/overrides/1/operations/1/opdiscover/discover is mandatory.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opdiscover' => []
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/opdiscover": the parameter "discover" is missing.'
			],
			'Test /1/overrides/1/operations/1/opdiscover/discover is validated.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opdiscover' => [
										'discover' => 2
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/opdiscover/discover": value must be one of '.implode(', ', [ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER]).'.'
			],
			// LLD rule override operation period
			'Test /1/overrides/1/operations/1/opperiod/delay is mandatory.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opperiod' => []
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/opperiod": the parameter "delay" is missing.'
			],
			'Test /1/overrides/1/operations/1/opperiod/delay is validated.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opperiod' => [
										'delay' => 'www'
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Incorrect value for field "delay": invalid delay.'
			],
			'Test /1/overrides/1/operations/1/opperiod/delay cannot be 0.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opperiod' => [
										'delay' => '0'
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Item will not be refreshed. Specified update interval requires having at least one either flexible or scheduling interval.'
			],
			'Test /1/overrides/1/operations/1/opperiod/delay has to be correct update interval.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opperiod' => [
										'delay' => '2w'
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Item will not be refreshed. Update interval should be between 1s and 1d. Also Scheduled/Flexible intervals can be used.'
			],
			'Test /1/overrides/1/operations/1/opperiod/delay has to be correct flexible interval.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opperiod' => [
										'delay' => '0;0/1,00:00-23:00'
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Item will not be refreshed. Please enter a correct update interval.'
			],
			'Test /1/overrides/1/operations/1/opperiod is not supported for trigger prototype object.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_TRIGGER_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opperiod' => [
										'delay' => '1d'
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1": unexpected parameter "opperiod".'
			],
			'Test /1/overrides/1/operations/1/opperiod is not supported for graph prototype object.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_GRAPH_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opperiod' => [
										'delay' => '1d'
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1": unexpected parameter "opperiod".'
			],
			'Test /1/overrides/1/operations/1/opperiod is not supported for host prototype object.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_HOST_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opperiod' => [
										'delay' => '1d'
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1": unexpected parameter "opperiod".'
			],
			// LLD rule override operation history
			'Test /1/overrides/1/operations/1/ophistory/history is mandatory.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'ophistory' => []
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/ophistory": the parameter "history" is missing.'
			],
			'Test /1/overrides/1/operations/1/ophistory/history is validated.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'ophistory' => [
										'history' => 'www'
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/ophistory/history": a time unit is expected.'
			],
			'Test /1/overrides/1/operations/1/ophistory/history max value is validated.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'ophistory' => [
										'history' => 25 * SEC_PER_YEAR + 1
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/ophistory/history": value must be one of 0, '.SEC_PER_HOUR.'-'.(25 * SEC_PER_YEAR).'.'
			],
			'Test /1/overrides/1/operations/1/ophistory/history min value is validated.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'ophistory' => [
										'history' => SEC_PER_HOUR - 1
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/ophistory/history": value must be one of 0, '.SEC_PER_HOUR.'-'.(25 * SEC_PER_YEAR).'.'
			],
			'Test /1/overrides/1/operations/1/ophistory is not supported for trigger prototype object.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_TRIGGER_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'ophistory' => [
										'history' => '1d'
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1": unexpected parameter "ophistory".'
			],
			'Test /1/overrides/1/operations/1/ophistory is not supported for graph prototype object.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_GRAPH_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'ophistory' => [
										'history' => '1d'
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1": unexpected parameter "ophistory".'
			],
			'Test /1/overrides/1/operations/1/ophistory is not supported for host prototype object.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_HOST_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'ophistory' => [
										'history' => '1d'
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1": unexpected parameter "ophistory".'
			],
			// LLD rule override operation trends
			'Test /1/overrides/1/operations/1/optrends/trends is mandatory.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'optrends' => []
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/optrends": the parameter "trends" is missing.'
			],
			'Test /1/overrides/1/operations/1/optrends/trends is validated.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'optrends' => [
										'trends' => 'www'
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/optrends/trends": a time unit is expected.'
			],
			'Test /1/overrides/1/operations/1/optrends/trends max value is validated.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'optrends' => [
										'trends' => 25 * SEC_PER_YEAR + 1
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/optrends/trends": value must be one of 0, '.SEC_PER_HOUR.'-'.(25 * SEC_PER_YEAR).'.'
			],
			'Test /1/overrides/1/operations/1/optrends/trends min value is validated.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'optrends' => [
										'trends' => SEC_PER_HOUR - 1
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/optrends/trends": value must be one of 0, '.SEC_PER_HOUR.'-'.(25 * SEC_PER_YEAR).'.'
			],
			'Test /1/overrides/1/operations/1/optrends is not supported for trigger prototype object.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_TRIGGER_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'optrends' => [
										'trends' => '1d'
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1": unexpected parameter "optrends".'
			],
			'Test /1/overrides/1/operations/1/optrends is not supported for graph prototype object.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_GRAPH_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'optrends' => [
										'trends' => '1d'
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1": unexpected parameter "optrends".'
			],
			'Test /1/overrides/1/operations/1/optrends is not supported for host prototype object.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_HOST_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'optrends' => [
										'trends' => '1d'
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1": unexpected parameter "optrends".'
			],
			// LLD rule override operation severity
			'Test /1/overrides/1/operations/1/opseverity/severity is mandatory.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opseverity' => []
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/opseverity": the parameter "severity" is missing.'
			],
			'Test /1/overrides/1/operations/1/opseverity/severity is validated.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opseverity' => [
										'severity' => 999
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/opseverity/severity": value must be one of '.implode(', ', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)).'.'
			],
			'Test /1/overrides/1/operations/1/opseverity is not supported for item prototype object.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opseverity' => [
										'severity' => TRIGGER_SEVERITY_INFORMATION
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1": unexpected parameter "opseverity".'
			],
			'Test /1/overrides/1/operations/1/opseverity is not supported for graph prototype object.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_GRAPH_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opseverity' => [
										'severity' => TRIGGER_SEVERITY_WARNING
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1": unexpected parameter "opseverity".'
			],
			'Test /1/overrides/1/operations/1/opseverity is not supported for host prototype object.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_HOST_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opseverity' => [
										'severity' => TRIGGER_SEVERITY_WARNING
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1": unexpected parameter "opseverity".'
			],
			// LLD rule override operation tag
			'Test /1/overrides/1/operations/1/optag cannot be empty.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'optag' => []
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/optag": cannot be empty.'
			],
			'Test /1/overrides/1/operations/1/optag/1/tag is mandatory.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'optag' => [
										[]
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/optag/1": the parameter "tag" is missing.'
			],
			'Test /1/overrides/1/operations/1/optag/1/tag cannot be empty.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'optag' => [
										[
											'tag' => ''
										]
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/optag/1/tag": cannot be empty.'
			],
			'Test /1/overrides/1/operations/1/optag is not supported for graph prototype object.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_GRAPH_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'optag' => [
										['tag' => 'www']
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1": unexpected parameter "optag".'
			],
			// LLD rule override operation template
			'Test /1/overrides/1/operations/1/optemplate cannot be empty.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_HOST_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'optemplate' => []
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/optemplate": cannot be empty.'
			],
			'Test /1/overrides/1/operations/1/optemplate/1/templateid is mandatory.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_HOST_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'optemplate' => [
										[]
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/optemplate/1": the parameter "templateid" is missing.'
			],
			'Test /1/overrides/1/operations/1/optemplate/1/templateid type is validated.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_HOST_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'optemplate' => [
										[
											'templateid' => ''
										]
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/optemplate/1/templateid": a number is expected.'
			],
			'Test /1/overrides/1/operations/1/optemplate/1/templateid must exist.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_HOST_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'optemplate' => [
										[
											'templateid' => '1'
										]
									]
								]
							]
						]
					])
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test /1/overrides/1/operations/1/optemplate/1/templateid cannot exist twice.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_HOST_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'optemplate' => [
										[
											'templateid' => '50010'
										],
										[
											'templateid' => '50010'
										]
									]
								]
							]
						]
					])
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test /1/overrides/1/operations/1/optemplate is not supported for item prototype object.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'optemplate' => [
										['template' => '1']
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/optemplate/1": unexpected parameter "template".'
			],
			'Test /1/overrides/1/operations/1/optemplate is not supported for trigger prototype object.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_TRIGGER_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'optemplate' => [
										['template' => '1']
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/optemplate/1": unexpected parameter "template".'
			],
			'Test /1/overrides/1/operations/1/optemplate is not supported for graph prototype object.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_GRAPH_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'optemplate' => [
										['template' => '1']
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/optemplate/1": unexpected parameter "template".'
			],
			// LLD rule override operation inventory
			'Test /1/overrides/1/operations/1/opinventory/inventory_mode is mandatory.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_HOST_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opinventory' => []
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/opinventory": the parameter "inventory_mode" is missing.'
			],
			'Test /1/overrides/1/operations/1/opinventory/inventory_mode is validated.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_HOST_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opinventory' => [
										'inventory_mode' => -2
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1/opinventory/inventory_mode": value must be one of '.implode(', ', [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]).'.'
			],
			'Test /1/overrides/1/operations/1/opinventory is not supported for item prototype object.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opinventory' => [
										'inventory_mode' => HOST_INVENTORY_MANUAL
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1": unexpected parameter "opinventory".'
			],
			'Test /1/overrides/1/operations/1/opinventory is not supported for trigger prototype object.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_TRIGGER_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opinventory' => [
										'inventory_mode' => HOST_INVENTORY_MANUAL
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1": unexpected parameter "opinventory".'
			],
			'Test /1/overrides/1/operations/1/opinventory is not supported for graph prototype object.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_GRAPH_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opinventory' => [
										'inventory_mode' => HOST_INVENTORY_MANUAL
									]
								]
							]
						]
					])
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1/operations/1": unexpected parameter "opinventory".'
			]
		];
	}

	public static function discoveryrule_overrides_create_data_valid() {
		$num = 0;
		$new_lld_overrides = function(array $overrides) use (&$num) {
			return [
				'name' => 'Overrides (valid)',
				'key_' => 'valid.lld.with.overrides.'.($num ++),
				'hostid' => '50009',
				'type' => ITEM_TYPE_TRAPPER,
				'overrides' => $overrides
			];
		};

		$data = [
			// LLD rule overrides
			'Test /1/overrides/1/filter and /1/overrides/1/operations are not mandatory.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override 1',
							'step' => 2
						]
					])
				],
				'expected_error' => null
			],
			'Test /1/overrides/1/operations can be empty.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override 1',
							'step' => 2,
							'operations' => []
						]
					])
				],
				'expected_error' => null
			],
			'Test /1/overrides/1/stop default value is set correctly.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override 1',
							'step' => 2
						],
						[
							'name' => 'override 2',
							'step' => 3,
							'stop' => ZBX_LLD_OVERRIDE_STOP_NO
						],
						[
							'name' => 'override 3',
							'step' => 1,
							'stop' => ZBX_LLD_OVERRIDE_STOP_YES
						]
					])
				],
				'expected_error' => null
			],
			// LLD rule override filter
			'Test /1/overrides/1/filter/evaltype with three conditions where two are unique.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
								'conditions' => [
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_NOT_REGEXP,
										'value' => ''
									],
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_REGEXP,
										'value' => ''
									],
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_REGEXP,
										'value' => ''
									]
								]
							]
						]
					])
				],
				'expected_error' => null
			],
			// LLD rule override filter condition
			'Test /1/overrides/1/filter/conditions/3/operator default value is set correctly.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
								'formula' => 'B or A or C',
								'conditions' => [
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_NOT_REGEXP,
										'value' => '',
										'formulaid' => 'B'
									],
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_REGEXP,
										'value' => '',
										'formulaid' => 'C'
									],
									[
										'macro' => '{#MACRO}',
										'value' => '',
										'formulaid' => 'A'
									]
								]
							]
						]
					])
				],
				'expected_error' => null
			],
			'Test /1/overrides/1/filter/conditions/3/operator default value is set correctly (field ordering issue #2).' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
								'formula' => 'B or A or C',
								'conditions' => [
									[
										'macro' => '{#MACRO}',
										'value' => '',
										'formulaid' => 'B'
									],
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_REGEXP,
										'value' => '',
										'formulaid' => 'C'
									],
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_NOT_REGEXP,
										'value' => '',
										'formulaid' => 'A'
									]
								]
							]
						]
					])
				],
				'expected_error' => null
			],
			'Test /1/overrides/1/filter/conditions/3/operator default value is set correctly (field ordering issue #3).' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'step' => 1,
							'name' => 'override',
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
								'formula' => 'B or A or C',
								'conditions' => [
									[
										'macro' => '{#MACRO}',
										'value' => '',
										'formulaid' => 'B',
										'operator' => CONDITION_OPERATOR_REGEXP
									],
									[
										'operator' => CONDITION_OPERATOR_REGEXP,
										'macro' => '{#MACRO}',
										'value' => '',
										'formulaid' => 'C'
									],
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_NOT_REGEXP,
										'value' => '',
										'formulaid' => 'A'
									]
								]
							]
						]
					])
				],
				'expected_error' => null
			],
			'Test /1/overrides/1/filter/formula is set correctly if ./evaltype is custom_expression.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
								'formula' => 'B or (A or C)',
								'conditions' => [
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_NOT_REGEXP,
										'value' => '',
										'formulaid' => 'B'
									],
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_REGEXP,
										'value' => '',
										'formulaid' => 'C'
									],
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_REGEXP,
										'value' => '',
										'formulaid' => 'A'
									]
								]
							]
						]
					])
				],
				'expected_error' => null
			],
			'Test /1/overrides/1/filter/formula and ./formulaid are silently ignored if ./evaltype is not custom_expression.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_OR,
								'formula' => 'X or Y or Z',
								'conditions' => [
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_NOT_REGEXP,
										'value' => '',
										'formulaid' => 'B'
									],
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_REGEXP,
										'value' => '',
										'formulaid' => 'C'
									],
									[
										'macro' => '{#MACRO}',
										'operator' => CONDITION_OPERATOR_NOT_REGEXP,
										'value' => '',
										'formulaid' => 'A'
									]
								]
							]
						]
					])
				],
				'expected_error' => null
			],
			// LLD rule override operation
			'Test /1/overrides/1/operations ./operator and ./value defaults are set.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'opstatus' => [
										'status' => ZBX_PROTOTYPE_STATUS_ENABLED
									]
								]
							]
						]
					])
				],
				'expected_error' => null
			],
			'Test /1/overrides/1/operations two similar operations can be set.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_REGEXP,
									'opstatus' => [
										'status' => ZBX_PROTOTYPE_STATUS_ENABLED
									]
								],
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_LIKE,
									'opstatus' => [
										'status' => ZBX_PROTOTYPE_STATUS_ENABLED
									]
								]
							]
						]
					])
				],
				'expected_error' => null
			],
			'Test /1/overrides/1/operations (ordering issue #2).' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'opstatus' => [
										'status' => ZBX_PROTOTYPE_STATUS_ENABLED
									]
								],
								[
									'opstatus' => [
										'status' => ZBX_PROTOTYPE_STATUS_ENABLED
									],
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_LIKE
								]
							]
						]
					])
				],
				'expected_error' => null
			],
			'Test /1/overrides/1/operations/1/ all operation objects are set correctly for item_prototype.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'opstatus' => [
										'status' => ZBX_PROTOTYPE_STATUS_DISABLED
									],
									'opdiscover' => [
										'discover' => ZBX_PROTOTYPE_DISCOVER
									],
									'opperiod' => [
										'delay' => '1d'
									],
									'ophistory' => [
										'history' => '1d'
									],
									'optrends' => [
										'trends' => '1d'
									]
								]
							]
						]
					])
				],
				'expected_error' => null
			],
			'Test /1/overrides/1/operations/1/ all operation objects are set correctly for item_prototype.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_TRIGGER_PROTOTYPE,
									'opstatus' => [
										'status' => ZBX_PROTOTYPE_STATUS_DISABLED
									],
									'opdiscover' => [
										'discover' => ZBX_PROTOTYPE_DISCOVER
									],
									'opseverity' => [
										'severity' => TRIGGER_SEVERITY_WARNING
									],
									'optag' => [
										[
											'tag' => 'tag',
											'value' => 'tag value'
										],
										[
											'tag' => 'tag 2'
										]
									]
								]
							]
						]
					])
				],
				'expected_error' => null
			],
			'Test /1/overrides/1/operations/1/ all operation objects are set correctly for graph_prototype.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_GRAPH_PROTOTYPE,
									'opdiscover' => [
										'discover' => ZBX_PROTOTYPE_NO_DISCOVER
									]
								]
							]
						]
					])
				],
				'expected_error' => null
			],
			'Test /1/overrides/1/operations/1/ all operation objects are set correctly for host_prototype.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_HOST_PROTOTYPE,
									'opstatus' => [
										'status' => ZBX_PROTOTYPE_STATUS_DISABLED
									],
									'opdiscover' => [
										'discover' => ZBX_PROTOTYPE_DISCOVER
									],
									'optemplate' => [
										[
											'templateid' => '50010'
										]
									],
									'optag' => [
										[
											'tag' => 'tag1',
											'value' => 'value1'
										],
										[
											'tag' => 'tag2',
											'value' => 'value2'
										]
									],
									'opinventory' => [
										'inventory_mode' => HOST_INVENTORY_AUTOMATIC
									]
								]
							]
						]
					])
				],
				'expected_error' => null
			],
			'Test /1/overrides/1/operations/ multiple operations are set correctly.' => [
				'discoveryrules' => [
					$new_lld_overrides([
						[
							'name' => 'override',
							'step' => 1,
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'opstatus' => [
										'status' => ZBX_PROTOTYPE_STATUS_DISABLED
									],
									'opdiscover' => [
										'discover' => ZBX_PROTOTYPE_DISCOVER
									],
									'opperiod' => [
										'delay' => '1d'
									],
									'ophistory' => [
										'history' => '1d'
									],
									'optrends' => [
										'trends' => '1d'
									]
								],
								[
									'operationobject' => OPERATION_OBJECT_TRIGGER_PROTOTYPE,
									'opstatus' => [
										'status' => ZBX_PROTOTYPE_STATUS_DISABLED
									],
									'opdiscover' => [
										'discover' => ZBX_PROTOTYPE_DISCOVER
									],
									'opseverity' => [
										'severity' => TRIGGER_SEVERITY_WARNING
									],
									'optag' => [
										[
											'tag' => 'tag',
											'value' => 'tag value'
										],
										[
											'tag' => 'tag 2'
										]
									]
								],
								[
									'operationobject' => OPERATION_OBJECT_GRAPH_PROTOTYPE,
									'opdiscover' => [
										'discover' => ZBX_PROTOTYPE_NO_DISCOVER
									]
								],
								[
									'operationobject' => OPERATION_OBJECT_HOST_PROTOTYPE,
									'opstatus' => [
										'status' => ZBX_PROTOTYPE_STATUS_DISABLED
									],
									'opdiscover' => [
										'discover' => ZBX_PROTOTYPE_DISCOVER
									],
									'optemplate' => [
										[
											'templateid' => '50010'
										]
									],
									'optag' => [
										[
											'tag' => 'tag1',
											'value' => 'value1'
										],
										[
											'tag' => 'tag2',
											'value' => 'value2'
										]
									],
									'opinventory' => [
										'inventory_mode' => HOST_INVENTORY_AUTOMATIC
									]
								]
							]
						]
					])
				],
				'expected_error' => null
			]
		];

		return $data;
	}

	/**
	 * @dataProvider discoveryrule_overrides_create_data_invalid
	 * @dataProvider discoveryrule_overrides_create_data_valid
	 */
	public function testDiscoveryRuleOverrides_Create(array $request, $expected_error) {
		$result = $this->call('discoveryrule.create', $request, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['itemids'] as $num => $itemid) {
				$db_lld_overrides = CDBHelper::getAll('SELECT * from lld_override WHERE '.
					dbConditionId('itemid', (array) $itemid)
				);

				$request_lld_overrides = $request[$num]['overrides'];
				foreach ($request_lld_overrides as $override_num => $request_lld_override) {
					$this->assertLLDOverride($db_lld_overrides[$override_num], $request_lld_override);
				}
			}
		}
	}

	/**
	 * @param array $db_lld_override                     Table "lld_override" row (all fields).
	 * @param array $db_lld_override['lld_overrideid']
	 * @param array $db_lld_override['itemid']
	 * @param array $db_lld_override['name']
	 * @param array $db_lld_override['step']
	 * @param array $db_lld_override['evaltype']
	 * @param array $db_lld_override['formula']
	 * @param array $db_lld_override['stop']
	 * @param array $request_lld_override
	 * @param array $request_lld_override['name']
	 * @param array $request_lld_override['step']
	 * @param array $request_lld_override['stop']        (optional)
	 * @param array $request_lld_override['filter']      (optional)
	 * @param array $request_lld_override['operations']  (optional)
	 */
	private function assertLLDOverride(array $db_lld_override, array $request_lld_override) {
		$this->assertEquals($db_lld_override['name'], $request_lld_override['name']);
		$this->assertEquals($db_lld_override['step'], $request_lld_override['step'], 'Override step value.');

		$stop = array_key_exists('stop', $request_lld_override)
			? $request_lld_override['stop']
			: ZBX_LLD_OVERRIDE_STOP_NO;
		$this->assertEquals($db_lld_override['stop'], $stop, 'Override stop value.');

		if (array_key_exists('filter', $request_lld_override)) {
			$this->assertLLDOverrideFilter($db_lld_override, $request_lld_override['filter']);
		}
		else {
			$this->assertEmpty($db_lld_override['formula']);
			$this->assertEquals($db_lld_override['evaltype'], CONDITION_EVAL_TYPE_AND_OR);
		}

		$db_lld_operations_count = CDBHelper::getCount('SELECT * from lld_override_operation WHERE '.
			dbConditionId('lld_overrideid', (array) $db_lld_override['lld_overrideid'])
		);

		if (array_key_exists('operations', $request_lld_override)) {
			$this->assertEquals(count($request_lld_override['operations']), $db_lld_operations_count,
				'Expected count of operations.'
			);

			foreach ($request_lld_override['operations'] as $num => $operation) {
				$db_lld_operations = CDBHelper::getAll('SELECT * from lld_override_operation WHERE '.
					dbConditionId('lld_overrideid', (array) $db_lld_override['lld_overrideid'])
				);
				CTestArrayHelper::usort($db_lld_operations, ['lld_override_operationid']);

				$this->assertLLDOverrideOperation($db_lld_operations[$num], $operation);
			}
		}
		else {
			$this->assertEquals(0, $db_lld_operations_count, 'Expected no operations.');
		}
	}

	/**
	 * @param array $db_lld_override                            Table "lld_override" row (all fields).
	 * @param array $filter                                     LLD rule override filter request object.
	 * @param array $filter['evaltype']
	 * @param array $filter['eval_formula']                     (optional)
	 * @param array $filter['formula']                          (optional) if evaltype is CONDITION_EVAL_TYPE_EXPRESSION
	 * @param array $filter['conditions']
	 * @param array $filter['conditions'][]                     LLD rule override filter condition object.
	 * @param array $filter['conditions'][]['macro']
	 * @param array $filter['conditions'][]['value']
	 * @param array $filter['conditions'][]['formulaid']        (optional) if evaltype is CONDITION_EVAL_TYPE_EXPRESSION
	 * @param array $filter['conditions'][]['operator']         (optional)
	 */
	private function assertLLDOverrideFilter(array $db_lld_override, array $filter) {
		$db_lld_conditions = CDBHelper::getAll('SELECT * from lld_override_condition WHERE '.
			dbConditionId('lld_overrideid', (array) $db_lld_override['lld_overrideid'])
		);
		CTestArrayHelper::usort($db_lld_conditions, ['lld_override_conditionid']);

		$this->assertEquals($db_lld_override['evaltype'], $filter['evaltype'], 'Override evaltype value.');

		if ($filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
			$conditionid_by_formulaid = array_combine(
				array_column($filter['conditions'], 'formulaid'),
				array_column($db_lld_conditions, 'lld_override_conditionid')
			);
			$formula = CConditionHelper::replaceLetterIds($filter['formula'], $conditionid_by_formulaid);
			$this->assertEquals($db_lld_override['formula'], $formula);
		}

		foreach ($filter['conditions'] as $num => $condition) {
			$this->assertEquals($db_lld_conditions[$num]['macro'], $condition['macro']);
			$this->assertEquals($db_lld_conditions[$num]['value'], $condition['value']);

			$operator = array_key_exists('operator', $condition)
				? $condition['operator']
				: CONDITION_OPERATOR_REGEXP;

			$this->assertEquals($db_lld_conditions[$num]['operator'], $operator);
		}
	}

	/**
	 * @param array  $db_lld_override_op                              Table "lld_override_operation" row (all fields).
	 * @param string $db_lld_override_op['lld_override_operationid']
	 * @param string $db_lld_override_op['lld_overrideid']
	 * @param string $db_lld_override_op['operationobject']
	 * @param string $db_lld_override_op['operator']
	 * @param array  $operation                                        LLD rule override operation object.
	 * @param string $operation['operationobject']
	 * @param string $operation['operator']                            (optional)
	 * @param string $operation['value']                               (optional)
	 * @param string $operation['opstatus']                            (optional)
	 * @param string $operation['opdiscover']                          (optional)
	 * @param string $operation['opperiod']                            (optional)
	 * @param string $operation['ophistory']                           (optional)
	 * @param string $operation['optrends']                            (optional)
	 * @param string $operation['opseverity']                          (optional)
	 * @param string $operation['optag']                               (optional)
	 * @param string $operation['optemplate']                          (optional)
	 * @param string $operation['opinventory']                         (optional)
	 */
	private function assertLLDOverrideOperation(array $db_lld_override_op, array $operation) {
		$this->assertEquals($db_lld_override_op['operationobject'], $operation['operationobject'], 'Operation object.');

		$condition_operator = array_key_exists('operator', $operation)
			? $operation['operator']
			: CONDITION_OPERATOR_EQUAL;
		$this->assertEquals($db_lld_override_op['operator'], $condition_operator, 'Operation operator.');

		$condition_value = array_key_exists('value', $operation)
			? $operation['value']
			: '';
		$this->assertEquals($db_lld_override_op['value'], $condition_value);

		$this->assertLLDOverrideOperationStatus($db_lld_override_op, $operation);
		$this->assertLLDOverrideOperationDiscover($db_lld_override_op, $operation);
		$this->assertLLDOverrideOperationHistory($db_lld_override_op, $operation);
		$this->assertLLDOverrideOperationPeriod($db_lld_override_op, $operation);
		$this->assertLLDOverrideOperationSeverity($db_lld_override_op, $operation);
		$this->assertLLDOverrideOperationTags($db_lld_override_op, $operation);
		$this->assertLLDOverrideOperationTemplates($db_lld_override_op, $operation);
		$this->assertLLDOverrideOperationTrends($db_lld_override_op, $operation);
		$this->assertLLDOverrideOperationInventory($db_lld_override_op, $operation);
	}

	/**
	 * @param array  $db_lld_override_op
	 * @param array  $operation
	 */
	private function assertLLDOverrideOperationStatus(array $db_lld_override_op, array $operation) {
		$operationid = $db_lld_override_op['lld_override_operationid'];

		$db_opstatus = CDBHelper::getRow('SELECT * from lld_override_opstatus WHERE '.
			dbConditionId('lld_override_operationid', (array) $operationid)
		);
		if (array_key_exists('opstatus', $operation)) {
			$this->assertEquals($db_opstatus['status'], $operation['opstatus']['status'], 'Operation status.');
		}
		else {
			$this->assertEmpty($db_opstatus, 'Expected opstatus.');
		}
	}

	/**
	 * @param array  $db_lld_override_op
	 * @param array  $operation
	 */
	private function assertLLDOverrideOperationDiscover(array $db_lld_override_op, array $operation) {
		$operationid = $db_lld_override_op['lld_override_operationid'];

		$db_opdiscover = CDBHelper::getRow('SELECT * from lld_override_opdiscover WHERE '.
			dbConditionId('lld_override_operationid', (array) $operationid)
		);
		if (array_key_exists('opdiscover', $operation)) {
			$this->assertEquals($db_opdiscover['discover'], $operation['opdiscover']['discover']);
		}
		else {
			$this->assertEmpty($db_opdiscover, 'Discovery operation.');
		}
	}

	/**
	 * @param array  $db_lld_override_op
	 * @param array  $operation
	 */
	private function assertLLDOverrideOperationHistory(array $db_lld_override_op, array $operation) {
		$operationid = $db_lld_override_op['lld_override_operationid'];

		$db_ophistory = CDBHelper::getRow('SELECT * from lld_override_ophistory WHERE '.
			dbConditionId('lld_override_operationid', (array) $operationid)
		);
		if (array_key_exists('ophistory', $operation)) {
			$this->assertEquals($db_ophistory['history'], $operation['ophistory']['history']);
		}
		else {
			$this->assertEmpty($db_ophistory, 'ophistory.');
		}
	}

	/**
	 * @param array  $db_lld_override_op
	 * @param array  $operation
	 */
	private function assertLLDOverrideOperationPeriod(array $db_lld_override_op, array $operation) {
		$operationid = $db_lld_override_op['lld_override_operationid'];

		$db_opperiod = CDBHelper::getRow('SELECT * from lld_override_opperiod WHERE '.
			dbConditionId('lld_override_operationid', (array) $operationid)
		);
		if (array_key_exists('opperiod', $operation)) {
			$this->assertEquals($db_opperiod['delay'], $operation['opperiod']['delay']);
		}
		else {
			$this->assertEmpty($db_opperiod);
		}
	}

	/**
	 * @param array  $db_lld_override_op
	 * @param array  $operation
	 */
	private function assertLLDOverrideOperationSeverity(array $db_lld_override_op, array $operation) {
		$operationid = $db_lld_override_op['lld_override_operationid'];

		$db_opseverity = CDBHelper::getRow('SELECT * from lld_override_opseverity WHERE '.
			dbConditionId('lld_override_operationid', (array) $operationid)
		);
		if (array_key_exists('opseverity', $operation)) {
			$this->assertEquals($db_opseverity['severity'], $operation['opseverity']['severity']);
		}
		else {
			$this->assertEmpty($db_opseverity);
		}
	}

	/**
	 * @param array  $db_lld_override_op
	 * @param array  $operation
	 */
	private function assertLLDOverrideOperationTags(array $db_lld_override_op, array $operation) {
		$db_optags = CDBHelper::getAll(
			'SELECT tag,value'.
			' FROM lld_override_optag'.
			' WHERE '.dbConditionId('lld_override_operationid', [$db_lld_override_op['lld_override_operationid']])
		);
		CTestArrayHelper::usort($db_optags, ['tag', 'value']);

		$operation['optag'] = array_key_exists('optag', $operation)
			? array_map(function($a) {
				if (!array_key_exists('value', $a)) {
					$a['value'] = '';
				}
				return $a;
			}, $operation['optag'])
			: [];

		CTestArrayHelper::usort($operation['optag'], ['tag', 'value']);

		$this->assertSame($db_optags, $operation['optag']);
	}

	/**
	 * @param array  $db_lld_override_op
	 * @param array  $operation
	 */
	private function assertLLDOverrideOperationTemplates(array $db_lld_override_op, array $operation) {
		$db_optemplates = CDBHelper::getAll(
			'SELECT templateid'.
			' FROM lld_override_optemplate'.
			' WHERE '.dbConditionId('lld_override_operationid', [$db_lld_override_op['lld_override_operationid']])
		);
		CTestArrayHelper::usort($db_optemplates, ['templateid']);

		if (!array_key_exists('optemplate', $operation)) {
			$operation['optemplate'] = [];
		}
		CTestArrayHelper::usort($operation['optemplate'], ['templateid']);

		$this->assertSame($db_optemplates, $operation['optemplate']);
	}

	/**
	 * @param array  $db_lld_override_op
	 * @param array  $operation
	 */
	private function assertLLDOverrideOperationTrends(array $db_lld_override_op, array $operation) {
		$operationid = $db_lld_override_op['lld_override_operationid'];

		$db_optrends = CDBHelper::getRow('SELECT * from lld_override_optrends WHERE '.
			dbConditionId('lld_override_operationid', (array) $operationid)
		);
		if (array_key_exists('optrends', $operation)) {
			$this->assertEquals($db_optrends['trends'], $operation['optrends']['trends']);
		}
		else {
			$this->assertEmpty($db_optrends, 'optrends');
		}
	}

	/**
	 * @param array  $db_lld_override_op
	 * @param array  $operation
	 */
	private function assertLLDOverrideOperationInventory(array $db_lld_override_op, array $operation) {
		$operationid = $db_lld_override_op['lld_override_operationid'];

		$db_opinventory = CDBHelper::getRow('SELECT * from lld_override_opinventory WHERE '.
			dbConditionId('lld_override_operationid', (array) $operationid)
		);
		if (array_key_exists('opinventory', $operation)) {
			$this->assertEquals($db_opinventory['inventory_mode'], $operation['opinventory']['inventory_mode']);
		}
		else {
			$this->assertEmpty($db_opinventory);
		}
	}

	public function testDiscoveryRuleOverrides_TemplateConstraint() {
		$templateid = '131001';
		$itemid = '133766';
		$request_lld_overrides = [
			[
				'stop' => ZBX_LLD_OVERRIDE_STOP_NO,
				'name' => 'Only template operation',
				'step' => 1,
				'operations' => [
					[
						'operationobject' => OPERATION_OBJECT_HOST_PROTOTYPE,
						'optemplate' => [
							['templateid' => $templateid]
						]
					]
				]
			],
			[
				'stop' => ZBX_LLD_OVERRIDE_STOP_NO,
				'name' => 'Not only template operation',
				'step' => 2,
				'operations' => [
					[
						'operationobject' => OPERATION_OBJECT_HOST_PROTOTYPE,
						'opinventory' => [
							'inventory_mode' => HOST_INVENTORY_MANUAL
						],
						'optemplate' => [
							['templateid' => $templateid]
						]
					]
				]
			]
		];

		$db_lld_overrides = CDBHelper::getAll('SELECT * from lld_override WHERE '.
			dbConditionId('itemid', (array) $itemid)
		);
		CTestArrayHelper::usort($db_lld_overrides, ['lld_overrideid']);

		// Assertion confirms existing request.
		foreach ($request_lld_overrides as $override_num => $request_lld_override) {
			$this->assertLLDOverride($db_lld_overrides[$override_num], $request_lld_override);
		}

		$result = $this->call('template.delete', [131001]);
		$this->assertEquals($result['result'], ['templateids' => [$templateid]]);

		$db_lld_overrides = CDBHelper::getAll('SELECT * from lld_override WHERE '.
			dbConditionId('itemid', (array) $itemid)
		);
		CTestArrayHelper::usort($db_lld_overrides, ['lld_overrideid']);

		// Operation that had only optamplate is deleted.
		unset($request_lld_overrides[0]['operations']);

		// Operation that had not only optamplate is not deleted.
		unset($request_lld_overrides[1]['operations'][0]['optemplate']);

		foreach ($request_lld_overrides as $override_num => $request_lld_override) {
			$this->assertLLDOverride($db_lld_overrides[$override_num], $request_lld_override);
		}
	}

	public function testDiscoveryRuleOverrides_Copy() {
		$itemid = '133764';
		$hostids = ['90020', '90021'];

		$result = $this->call('discoveryrule.copy', [
			'discoveryids' => [$itemid],
			'hostids' => $hostids
		]);

		$this->assertTrue($result['result']);

		$result = $this->call('discoveryrule.get', [
			'output' => [],
			'selectOverrides' => 'extend',
			'itemids' => $itemid
		]);

		$expected_overrides = $result['result'][0]['overrides'];
		CTestArrayHelper::usort($expected_overrides, ['step']);

		foreach ($expected_overrides as &$override) {
			CTestArrayHelper::usort($override['filter']['conditions'], ['formulaid']);
			CTestArrayHelper::usort($override['operations'], ['operationobject', 'operator', 'value']);

			foreach ($override['operations'] as &$operation) {
				if (array_key_exists('optag', $operation)) {
					CTestArrayHelper::usort($operation['optag'], ['tag']);
				}
				if (array_key_exists('optemplate', $operation)) {
					CTestArrayHelper::usort($operation['optemplate'], ['templateid']);
				}
			}
			unset($operation);
		}
		unset($override);

		$db_lld_ruleids = array_column(CDBHelper::getAll(
			'SELECT itemid FROM items WHERE flags=1 AND '.dbConditionId('hostid', $hostids).' ORDER BY itemid'
		), 'itemid');
		$this->assertSame(count($db_lld_ruleids), count($hostids));

		$result = $this->call('discoveryrule.get', [
			'output' => [],
			'selectOverrides' => 'extend',
			'itemids' => $db_lld_ruleids
		]);

		foreach ($result['result'] as $lld_rule) {
			CTestArrayHelper::usort($lld_rule['overrides'], ['step']);

			foreach ($lld_rule['overrides'] as &$override) {
				CTestArrayHelper::usort($override['filter']['conditions'], ['formulaid']);
				CTestArrayHelper::usort($override['operations'], ['operationobject', 'operator', 'value']);

				foreach ($override['operations'] as &$operation) {
					if (array_key_exists('optag', $operation)) {
						CTestArrayHelper::usort($operation['optag'], ['tag']);
					}
					if (array_key_exists('optemplate', $operation)) {
						CTestArrayHelper::usort($operation['optemplate'], ['templateid']);
					}
				}
				unset($operation);
			}
			unset($override);

			$this->assertSame($expected_overrides, $lld_rule['overrides']);
		}
	}

	public static function discoveryrule_overrides_get_data_valid() {
		$itemid = '133763';

		return [
			'Test getting lld_overrides extended output.' => [
				'discoveryrule' => [
					'output' => ['itemid'],
					'selectOverrides' => ['name', 'step', 'stop', 'operations'],
					'itemids' => [$itemid]
				],
				'get_result' => [
					'itemid' => $itemid,
					'overrides' => [
						[
							'name' => 'override',
							'step' => '1',
							'stop' => ZBX_LLD_OVERRIDE_STOP_YES,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
								'formula' => 'A or B or C',
								'conditions' => [
									[
										'macro' => '{#MACRO1}',
										'value' => 'd{3}$',
										'operator' => CONDITION_OPERATOR_REGEXP,
										'formulaid' => 'A'
									],
									[
										'macro' => '{#MACRO2}',
										'value' => 'd{2}$',
										'operator' => CONDITION_OPERATOR_REGEXP,
										'formulaid' => 'B'
									],
									[
										'macro' => '{#MACRO3}',
										'value' => 'd{1}$',
										'operator' => CONDITION_OPERATOR_REGEXP,
										'formulaid' => 'C'
									]
								],
								'eval_formula' => 'A or B or C'
							],
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_LIKE,
									'value' => '8',
									'opstatus' => [
										'status' => ZBX_PROTOTYPE_STATUS_DISABLED
									]
								],
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_EQUAL,
									'value' => 'wW',
									'opstatus' => [
										'status' => ZBX_PROTOTYPE_STATUS_ENABLED
									],
									'opdiscover' => [
										'discover' => ZBX_PROTOTYPE_NO_DISCOVER
									],
									'ophistory' => [
										'history' => '92d'
									],
									'optrends' => [
										'trends' => '36d'
									],
									'opperiod' => [
										'delay' => '1m;wd1-3h4-16;10s/1-5,00:00-20:00;5s/5-7,00:00-24:00'
									]
								],
								[
									'operationobject' => OPERATION_OBJECT_TRIGGER_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_REGEXP,
									'value' => '^c+$',
									'opstatus' => [
										'status' => ZBX_PROTOTYPE_STATUS_DISABLED
									],
									'opdiscover' => [
										'discover' => ZBX_PROTOTYPE_NO_DISCOVER
									],
									'opseverity' => [
										'severity' => TRIGGER_SEVERITY_AVERAGE
									],
									'optag' => [
										[
											'tag' => 'tag1',
											'value' => 'value1'
										],
										[
											'tag' => 'tag2',
											'value' => 'value2'
										]
									]
								],
								[
									'operationobject' => OPERATION_OBJECT_GRAPH_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_LIKE,
									'value' => '123',
									'opdiscover' => [
										'discover' => ZBX_PROTOTYPE_NO_DISCOVER
									]
								],
								[
									'operationobject' => OPERATION_OBJECT_HOST_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_EQUAL,
									'value' => '',
									'opstatus' => [
										'status' => ZBX_PROTOTYPE_STATUS_DISABLED
									],
									'opdiscover' => [
										'discover' => ZBX_PROTOTYPE_NO_DISCOVER
									],
									'optemplate' => [
										[
											'templateid' => '10264'
										],
										[
											'templateid' => '10265'
										],
										[
											'templateid' => '50010'
										]
									],
									'optag' => [
										[
											'tag' => 'tag1',
											'value' => 'value1'
										],
										[
											'tag' => 'tag2',
											'value' => 'value2'
										]
									],
									'opinventory' => [
										'inventory_mode' => HOST_INVENTORY_AUTOMATIC
									]
								]
							]
						],
						[
							'name' => 'override 2',
							'step' => '2',
							'stop' => ZBX_LLD_OVERRIDE_STOP_YES,
							'filter' => [
								'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
								'formula' => '',
								'conditions' => [],
								'eval_formula' => ''
							],
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_EQUAL,
									'value' => '',
									'optrends' => [
										'trends' => '5d'
									]
								]
							]
						]
					]
				],
				'expected_error' => null
			],
			'Test getting lld_overrides queried output.' => [
				'discoveryrule' => [
					'output' => ['itemid'],
					'selectOverrides' => ['step', 'operations'],
					'itemids' => [$itemid]
				],
				'get_result' => [
					'itemid' => $itemid,
					'overrides' => [
						[
							'step' => '1',
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_LIKE,
									'value' => '8',
									'opstatus' => [
										'status' => ZBX_PROTOTYPE_STATUS_DISABLED
									]
								],
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_NOT_EQUAL,
									'value' => 'wW',
									'opstatus' => [
										'status' => ZBX_PROTOTYPE_STATUS_ENABLED
									],
									'opdiscover' => [
										'discover' => ZBX_PROTOTYPE_NO_DISCOVER
									],
									'ophistory' => [
										'history' => '92d'
									],
									'optrends' => [
										'trends' => '36d'
									],
									'opperiod' => [
										'delay' => '1m;wd1-3h4-16;10s/1-5,00:00-20:00;5s/5-7,00:00-24:00'
									]
								],
								[
									'operationobject' => OPERATION_OBJECT_TRIGGER_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_REGEXP,
									'value' => '^c+$',
									'opstatus' => [
										'status' => ZBX_PROTOTYPE_STATUS_DISABLED
									],
									'opdiscover' => [
										'discover' => ZBX_PROTOTYPE_NO_DISCOVER
									],
									'opseverity' => [
										'severity' => TRIGGER_SEVERITY_AVERAGE
									],
									'optag' => [
										[
											'tag' => 'tag1',
											'value' => 'value1'
										],
										[
											'tag' => 'tag2',
											'value' => 'value2'
										]
									]
								],
								[
									'operationobject' => OPERATION_OBJECT_GRAPH_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_LIKE,
									'value' => '123',
									'opdiscover' => [
										'discover' => ZBX_PROTOTYPE_NO_DISCOVER
									]
								],
								[
									'operationobject' => OPERATION_OBJECT_HOST_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_EQUAL,
									'value' => '',
									'opstatus' => [
										'status' => ZBX_PROTOTYPE_STATUS_DISABLED
									],
									'opdiscover' => [
										'discover' => ZBX_PROTOTYPE_NO_DISCOVER
									],
									'optemplate' => [
										[
											'templateid' => '10264'
										],
										[
											'templateid' => '10265'
										],
										[
											'templateid' => '50010'
										]
									],
									'optag' => [
										[
											'tag' => 'tag1',
											'value' => 'value1'
										],
										[
											'tag' => 'tag2',
											'value' => 'value2'
										]
									],
									'opinventory' => [
										'inventory_mode' => HOST_INVENTORY_AUTOMATIC
									]
								]
							]
						],
						[
							'step' => '2',
							'operations' => [
								[
									'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
									'operator' => CONDITION_OPERATOR_EQUAL,
									'value' => '',
									'optrends' => [
										'trends' => '5d'
									]
								]
							]
						]
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider discoveryrule_overrides_get_data_valid
	 */
	public function testDiscoveryRuleOverrides_Get($discoveryrule, $get_result, $expected_error) {
		$result = $this->call('discoveryrule.get', $discoveryrule);

		if ($expected_error === null) {
			foreach ($result['result'] as $entry) {
				$this->assertSame($entry['itemid'], $get_result['itemid']);

				if (array_key_exists('selectOverrides', $discoveryrule)) {
					$this->assertArrayHasKey('overrides', $get_result);
					$this->assertSame($entry['overrides'], $get_result['overrides']);
				}
				else {
					$this->assertArrayNotHasKey('overrides', $get_result);
				}
			}
		}
	}

	public static function discoveryrule_overrides_update_data() {
		$itemid = '133765';
		$initial_overrides = [
			[
				'name' => 'override',
				'step' => '1',
				'stop' => ZBX_LLD_OVERRIDE_STOP_YES,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
					'formula' => 'A or B or C',
					'conditions' => [
						[
							'macro' => '{#MACRO1}',
							'value' => 'd{3}$',
							'operator' => CONDITION_OPERATOR_REGEXP,
							'formulaid' => 'A'
						],
						[
							'macro' => '{#MACRO2}',
							'value' => 'd{2}$',
							'operator' => CONDITION_OPERATOR_REGEXP,
							'formulaid' => 'B'
						],
						[
							'macro' => '{#MACRO3}',
							'value' => 'd{1}$',
							'operator' => CONDITION_OPERATOR_REGEXP,
							'formulaid' => 'C'
						]
					]
				],
				'operations' => [
					[
						'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
						'operator' => CONDITION_OPERATOR_NOT_LIKE,
						'value' => '8',
						'opstatus' => [
							'status' => ZBX_PROTOTYPE_STATUS_DISABLED
						]
					],
					[
						'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
						'operator' => CONDITION_OPERATOR_NOT_EQUAL,
						'value' => 'wW',
						'opstatus' => [
							'status' => ZBX_PROTOTYPE_STATUS_ENABLED
						],
						'opdiscover' => [
							'discover' => ZBX_PROTOTYPE_NO_DISCOVER
						],
						'ophistory' => [
							'history' => '92d'
						],
						'optrends' => [
							'trends' => '36d'
						],
						'opperiod' => [
							'delay' => '1m;wd1-3h4-16;10s/1-5,00:00-20:00;5s/5-7,00:00-24:00'
						]
					],
					[
						'operationobject' => OPERATION_OBJECT_TRIGGER_PROTOTYPE,
						'operator' => CONDITION_OPERATOR_REGEXP,
						'value' => '^c+$',
						'opstatus' => [
							'status' => ZBX_PROTOTYPE_STATUS_DISABLED
						],
						'opdiscover' => [
							'discover' => ZBX_PROTOTYPE_NO_DISCOVER
						],
						'opseverity' => [
							'severity' => TRIGGER_SEVERITY_AVERAGE
						],
						'optag' => [
							[
								'tag' => 'tag1',
								'value' => 'value1'
							],
							[
								'tag' => 'tag2',
								'value' => 'value2'
							]
						]
					],
					[
						'operationobject' => OPERATION_OBJECT_GRAPH_PROTOTYPE,
						'operator' => CONDITION_OPERATOR_LIKE,
						'value' => '123',
						'opdiscover' => [
							'discover' => ZBX_PROTOTYPE_NO_DISCOVER
						]
					],
					[
						'operationobject' => OPERATION_OBJECT_HOST_PROTOTYPE,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => '',
						'opstatus' => [
							'status' => ZBX_PROTOTYPE_STATUS_DISABLED
						],
						'opdiscover' => [
							'discover' => ZBX_PROTOTYPE_NO_DISCOVER
						],
						'optemplate' => [
							[
								'templateid' => '10264'
							],
							[
								'templateid' => '10265'
							],
							[
								'templateid' => '50010'
							]
						],
						'optag' => [
							[
								'tag' => 'tag1',
								'value' => 'value1'
							],
							[
								'tag' => 'tag2',
								'value' => 'value2'
							]
						],
						'opinventory' => [
							'inventory_mode' => HOST_INVENTORY_AUTOMATIC
						]
					]
				]
			],
			[
				'name' => 'override 2',
				'step' => '2',
				'stop' => ZBX_LLD_OVERRIDE_STOP_YES,
				'operations' => [
					[
						'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => '',
						'optrends' => [
							'trends' => '5d'
						]
					]
				]
			]
		];

		$edited_overrides = $initial_overrides;
		unset($edited_overrides[1]);
		$edited_overrides[0]['name'] = 'edited override';

		$edited_invalid_overrides = $initial_overrides;
		$edited_invalid_overrides[1]['operations'][0]['optrends']['trends'] = 'incorrect date value';

		return [
			'Test update override expects array.' => [
				'request' => [
					'itemid' => $itemid,
					'overrides' => [
						'incorrect' => '123'
					]
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1": an array is expected.',
				'current_overrides' => null
			],
			'Test override object is validated.' => [
				'request' => [
					'itemid' => $itemid,
					'overrides' => [
						[]
					]
				],
				'expected_error' => 'Invalid parameter "/1/overrides/1": the parameter "step" is missing.',
				'current_overrides' => null
			],
			'Test that overrides remain untouched if update request omits overrides field.' => [
				'request' => [
					'itemid' => $itemid
				],
				'expected_error' => null,
				'current_overrides' => $initial_overrides
			],
			'Test all overrides array can only be completely rewritten.' => [
				'request' => [
					'itemid' => $itemid,
					'overrides' => $edited_overrides
				],
				'expected_error' => null,
				'current_overrides' => $edited_overrides
			],
			'Test overrides/2/operations/1/optrends/trends is validated.' => [
				'request' => [
					'itemid' => $itemid,
					'overrides' => $edited_invalid_overrides
				],
				'expected_error' => 'Invalid parameter "/1/overrides/2/operations/1/optrends/trends": a time unit is expected.',
				'current_overrides' => null
			],
			'Test all overrides can deleted.' => [
				'request' => [
					'itemid' => $itemid,
					'overrides' => []
				],
				'expected_error' => null,
				'current_overrides' => []
			],
			'Test all overrides can be recreated.' => [
				'request' => [
					'itemid' => $itemid,
					'overrides' => $initial_overrides
				],
				'expected_error' => null,
				'current_overrides' => $initial_overrides
			]
		];
	}

	/**
	 * @dataProvider discoveryrule_overrides_update_data
	 */
	public function testDiscoveryRuleOverrides_Update($request, $expected_error, $current_overrides) {
		$this->call('discoveryrule.update', $request, $expected_error);

		if ($expected_error === null) {
			$itemid = $request['itemid'];

			$db_lld_overrides = CDBHelper::getAll('SELECT * from lld_override WHERE '.
				dbConditionId('itemid', (array) $itemid)
			);
			CTestArrayHelper::usort($db_lld_overrides, ['lld_overrideid']);

			if (array_key_exists('overrides', $request)) {
				$this->assertEquals(count($current_overrides), count($request['overrides']));
			}

			foreach ($current_overrides as $override_num => $override) {
				$this->assertLLDOverride($db_lld_overrides[$override_num], $override);
			}
		}
	}

	// TODO: add more tests to check other related discovery rule properties and perform more tests on templates and templated objects.
}

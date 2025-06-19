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
 * @backup globalmacro
 * @backup hostmacro
 */
class testUserMacro extends CAPITest {

	public static function hostmacroCreateData() {
		return [
			[
				'hostmacro' => [
					'macro' => '{$ADD_1}',
					'value' => 'test',
					'type' => '0',
					'hostid' => '90020',
					'description' => 'text'
				],
				'expected_error' => null
			],
			[
				'hostmacro' => [
					[
						'macro' => '{$MACRO}',
						'value' => '',
						'hostid' => 90020
					],
					[
						'macro' => '{$MACRO}',
						'value' => '',
						'hostid' => 90020
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (hostid, macro)=(90020, {$MACRO}) already exists.'
			],
			[
				'hostmacro' => [
					[
						'macro' => '{$MACRO: /var}',
						'value' => '',
						'hostid' => 90020
					],
					[
						'macro' => '{$MACRO: /var}',
						'value' => '',
						'hostid' => 90021
					],
					[
						'macro' => '{$MACRO: "/tmp"}',
						'value' => '',
						'hostid' => 90020
					],
					[
						'macro' => '{$MACRO: "/var"}',
						'value' => '',
						'hostid' => 90020
					]
				],
				'expected_error' => 'Invalid parameter "/4": value (hostid, macro)=(90020, {$MACRO: "/var"}) already exists.'
			],
			[
				'hostmacro' => [
					'macro' => '{$ADD_2}',
					'value' => 'test',
					'type' => '0',
					'hostid' => '90020',
					'description' => ''
				],
				'expected_error' => null,
				'expect_db_row' => [
					'macro' => '{$ADD_2}',
					'value' => 'test',
					'type' => '0',
					'hostid' => '90020',
					'description' => '',
					'automatic' => '0'
				]
			],
			[
				'hostmacro' => [
					'macro' => '{$VAULT}',
					'value' => 'a/b:c',
					'type' => '2',
					'hostid' => '90020'
				],
				'expected_error' => null
			],
			[
				'hostmacro' => [
					'macro' => '{$VAULT: "context"}',
					'value' => 'a/b:c',
					'type' => '2',
					'hostid' => '90020'
				],
				'expected_error' => null
			],
			[
				'hostmacro' => [
					'macro' => '{$VAULT: "empty"}',
					'value' => '',
					'type' => '2',
					'hostid' => '90020'
				],
				'expected_error' => 'Invalid parameter "/1/value": cannot be empty.'
			],
			[
				'hostmacro' => [
					'macro' => '{$VAULT: "invalid"}',
					'value' => '/',
					'type' => '2',
					'hostid' => '90020'
				],
				'expected_error' => 'Invalid parameter "/1/value": incorrect syntax near "/".'
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG}',
					'value' => 'invalid',
					'hostid' => '90020',
					'config' => []
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "config".'
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG1}',
					'value' => 'valid',
					'hostid' => '50010',
					'config' => []
				],
				'expected_error' => null
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG}',
					'value' => 'invalid',
					'hostid' => '50010',
					'config' => [
						'label' => 'Config'
					]
				],
				'expected_error' => 'Invalid parameter "/1/config": the parameter "type" is missing.'
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG2}',
					'value' => 'valid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_NOCONF
					]
				],
				'expected_error' => null
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG}',
					'value' => 'invalid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_TEXT
					]
				],
				'expected_error' => 'Invalid parameter "/1/config": the parameter "label" is missing.'
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG3}',
					'value' => 'valid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_TEXT,
						'label' => 'Config'
					]
				],
				'expected_error' => null
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG4}',
					'value' => 'valid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_TEXT,
						'label' => 'Config',
						'description' => 'Config',
						'required' => ZBX_WIZARD_FIELD_REQUIRED,
						'regex' => '/^[a-zA-Z0-9_]+$/',
						'options' => []
					]
				],
				'expected_error' => null
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG}',
					'value' => 'invalid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_TEXT,
						'label' => 'Config',
						'regex' => '/^[a-z(A-Z0-9_]+$/'
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/regex": invalid regular expression.'
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG}',
					'value' => 'invalid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_LIST,
						'label' => 'Config'
					]
				],
				'expected_error' => 'Invalid parameter "/1/config": the parameter "options" is missing.'
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG5}',
					'value' => 'valid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_LIST,
						'label' => 'Config',
						'options' => [
							[
								'value' => 'option1',
								'text' => 'Option 1'
							],
							[
								'value' => 'option2',
								'text' => 'Option 2'
							]
						]
					]
				],
				'expected_error' => null
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG}',
					'value' => 'invalid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_LIST,
						'label' => 'Config',
						'options' => [
							[
								'value' => 'option1',
								'text' => 'Option 1'
							],
							[
								'value' => 'option1',
								'text' => 'Option 1'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options/2": value (value, text)=(option1, Option 1) already exists.'
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG}',
					'value' => 'invalid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_LIST,
						'label' => 'Config',
						'options' => [
							[
								'checked' => 'option1',
								'unchecked' => 'option2'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options/1": unexpected parameter "checked".'
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG}',
					'value' => 'invalid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_LIST,
						'label' => 'Config',
						'regex' => '/^[a-zA-Z0-9_]+$/',
						'options' => [
							[
								'value' => 'option1',
								'text' => 'Option 1'
							],
							[
								'value' => 'option2',
								'text' => 'Option 2'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/regex": value must be empty.'
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG}',
					'value' => 'invalid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_CHECKBOX,
						'label' => 'Config'
					]
				],
				'expected_error' => 'Invalid parameter "/1/config": the parameter "options" is missing.'
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG6}',
					'value' => 'valid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_CHECKBOX,
						'label' => 'Config',
						'options' => [[
							'checked' => 'option1',
							'unchecked' => 'option2'
						]]
					]
				],
				'expected_error' => null
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG}',
					'value' => 'invalid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_CHECKBOX,
						'label' => 'Config',
						'options' => [['unchecked' => 'option2']]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options/1": the parameter "checked" is missing.'
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG}',
					'value' => 'invalid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_CHECKBOX,
						'label' => 'Config',
						'options' => [
							[
								'checked' => 'option1',
								'unchecked' => 'option2'
							],
							[
								'checked' => 'option3',
								'unchecked' => 'option4'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options": maximum number of array elements is 1.'
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG}',
					'value' => 'invalid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_CHECKBOX,
						'label' => 'Config',
						'regex' => '/^[a-zA-Z0-9_]+$/',
						'options' => [[
							'checked' => 'option1',
							'unchecked' => 'option2'
						]]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/regex": value must be empty.'
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG}',
					'value' => 'invalid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_CHECKBOX,
						'label' => 'Config',
						'required' => ZBX_WIZARD_FIELD_REQUIRED,
						'options' => [[
							'checked' => 'option1',
							'unchecked' => 'option2'
						]]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/required": value must be '.DB::getDefault('hostmacro_config', 'required').'.'
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG7}',
					'value' => 'valid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_CHECKBOX,
						'priority' => 0,
						'label' => 'Config',
						'options' => [[
							'checked' => 'option1',
							'unchecked' => 'option2'
						]]
					]
				],
				'expected_error' => null
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG8}',
					'value' => 'valid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_TEXT,
						'priority' => ZBX_MAX_INT32,
						'label' => 'Config'
					]
				],
				'expected_error' => null
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG}',
					'value' => 'invalid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_TEXT,
						'priority' => -1,
						'label' => 'Config'
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/priority": value must be one of 0-2147483647.'
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG}',
					'value' => 'invalid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_NOCONF,
						'priority' => 8
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/priority": value must be 0.'
			],
			[
				'hostmacro' => [
					'macro' => '{$CONFIG9}',
					'value' => 'valid',
					'hostid' => '50010',
					'config' => [
						'type' => ZBX_WIZARD_FIELD_TEXT,
						'section_name' => 'Advanced',
						'label' => 'Config'
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider hostmacroCreateData
	 */
	public function testUserMacro_Create($hostmacro, $expected_error, $expect = []) {
		$result = $this->call('usermacro.create', $hostmacro, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['hostmacroids'] as $key => $id) {
				$dbResult = DBSelect('select * from hostmacro where hostmacroid='.zbx_dbstr($id));
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['macro'], $hostmacro['macro']);
				$this->assertEquals($dbRow['value'], $hostmacro['value']);

				if (array_key_exists('type', $hostmacro)) {
					$this->assertEquals($dbRow['type'], $hostmacro['type']);
				}

				if (array_key_exists('description', $hostmacro)) {
					$this->assertEquals($dbRow['description'], $hostmacro['description']);
				}

				if ($expect) {
					$expect['hostmacroid'] = $id;
					$this->assertEquals($dbRow, $expect);
				}

				if (array_key_exists('config', $hostmacro)) {
					$dbResult = DBSelect('select * from hostmacro_config where hostmacroid='.zbx_dbstr($id));
					$dbRow = DBFetch($dbResult);

					if (!$hostmacro['config'] || $hostmacro['config']['type'] == ZBX_WIZARD_FIELD_NOCONF) {
						$this->assertFalse($dbRow);
					}
					else {
						$hostmacro['config'] += DB::getDefaults('hostmacro_config');
						$hostmacro['config']['options'] = $hostmacro['config']['options']
							? json_encode($hostmacro['config']['options'])
							: '';

						foreach (array_keys($hostmacro['config']) as $config_key) {
							$this->assertEquals($dbRow[$config_key], $hostmacro['config'][$config_key]);
						}
					}
				}
			}
		}
	}

	public static function globalmacroCreateData() {
		return [
			// Check unexpected parameter
			[
				'globalmacro' => [
					'macro' => '{$HOSTID}',
					'value' => 'test',
					'hostid' => '100084'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "hostid".'
			],
			// Check macro.
			[
				'globalmacro' => [
					'value' => 'test'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "macro" is missing.'
			],
			// Check existing macro
			[
				'globalmacro' => [
					'macro' => '{$SNMP_COMMUNITY}',
					'value' => 'test'
				],
				'expected_error' => 'Macro "{$SNMP_COMMUNITY}" already exists.'
			],
			[
				'globalmacro' => [
					[
					'macro' => '{$THESAMEMACRO}',
					'value' => 'test'
					],
					[
					'macro' => '{$THESAMEMACRO}',
					'value' => 'test'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (macro)=({$THESAMEMACRO}) already exists.'
			],
			// Check value
			[
				'globalmacro' => [
					'macro' => '{$CHECKVALUE}'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "value" is missing.'
			],
			// Check successfully creation of global macro.
			[
				'globalmacro' => [
					[
						'macro' => '{$ABC123}',
						'value' => 'test'
					]
				],
				'expected_error' => null
			],
			[
				'globalmacro' => [
					[
						'macro' => '{$MACRO:context}',
						'value' => 'test'
					]
				],
				'expected_error' => null
			],
			[
				'globalmacro' => [
					[
						'macro' => '{$MACRO:"A"}',
						'value' => 'test'
					]
				],
				'expected_error' => null
			],
			[
				'globalmacro' => [
					[
						'macro' => '{$VAULT}',
						'value' => 'a/b:c',
						'type' => '2'
					]
				],
				'expected_error' => null
			],
			[
				'globalmacro' => [
					[
						'macro' => '{$ONE_MACRO}',
						'value' => 'one'
					],
					[
						'macro' => '{$TWO.MACRO}',
						'value' => 'æų'
					]
				],
				'expected_error' => null
			],
			// Description field.
			[
				'globalmacro' => [
					[
						'macro' => '{$ONE_MACRO_DESC}',
						'value' => 'one',
						'description' => 'one'
					],
					[
						'macro' => '{$TWO.MACRO_DESC}',
						'value' => 'æų',
						'description' => 'æų'
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider globalmacroCreateData
	 */
	public function testUserMacro_CreateGlobal($globalmacro, $expected_error) {
		$result = $this->call('usermacro.createglobal', $globalmacro, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['globalmacroids'] as $key => $id) {
				$dbResult = DBSelect('select * from globalmacro where globalmacroid='.zbx_dbstr($id));
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['macro'], $globalmacro[$key]['macro']);
				$this->assertEquals($dbRow['value'], $globalmacro[$key]['value']);

				if (array_key_exists('description', $globalmacro[$key])) {
					$this->assertEquals($dbRow['description'], $globalmacro[$key]['description']);
				}
			}
		}
	}

	public static function globalmacroFailedData() {
		return [
			// Check unexpected parameter
			[
				'globalmacro' => [
					'macro' => '{$HOSTID}',
					'value' => 'test',
					'hostid ' => '100084'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "hostid ".'
			],
			// Check macro.
			[
				'globalmacro' => [
					'macro' => '',
					'value' => 'test'
				],
				'expected_error' => 'Invalid parameter "/1/macro": cannot be empty.'
			],
			[
				'globalmacro' => [
					'macro' => 'test',
					'value' => 'test'
				],
				'expected_error' => 'Invalid parameter "/1/macro": incorrect syntax near "test".'
			],
			[
				'globalmacro' => [
					'macro' => '{$globalmacro}',
					'value' => 'test'
				],
				'expected_error' => 'Invalid parameter "/1/macro": incorrect syntax near "globalmacro}".'
			],
			[
				'globalmacro' => [
					'macro' => '☺',
					'value' => 'test'
				],
				'expected_error' => 'Invalid parameter "/1/macro": incorrect syntax near "☺".'
			],
			[
				'globalmacro' => [
					'macro' => '{GlOBALMACRO}',
					'value' => 'test'
				],
				'expected_error' => 'Invalid parameter "/1/macro": incorrect syntax near "GlOBALMACRO}".'
			],
			[
				'globalmacro' => [
					'macro' => '{$GlOBALMACRO',
					'value' => 'test'
				],
				'expected_error' => 'Invalid parameter "/1/macro": incorrect syntax near "lOBALMACRO".'
			],
			[
				'globalmacro' => [
					'macro' => '{$GlOBALMACRO}}',
					'value' => 'test'
				],
				'expected_error' => 'Invalid parameter "/1/macro": incorrect syntax near "lOBALMACRO}}".'
			],
			[
				'globalmacro' => [
					'macro' => '{{$GlOBALMACRO}}',
					'value' => 'test'
				],
				'expected_error' => 'Invalid parameter "/1/macro": incorrect syntax near "{$GlOBALMACRO}}".'
			],
			[
				'globalmacro' => [
					'macro' => '{$УТФ8}',
					'value' => 'test'
				],
				'expected_error' => 'Invalid parameter "/1/macro": incorrect syntax near "УТФ8}".'
			],
			[
				'globalmacro' => [
					'macro' => '{$!@#$%^&*()-=<>}',
					'value' => 'test'
				],
				'expected_error' => 'Invalid parameter "/1/macro": incorrect syntax near "!@#$%^&*()-=<>}".'
			],
			[
				'globalmacro' => [
					'macro' => '{$SUSPENDISSE_CONDIMENTUM_VELIT_EU_SAPIENAPELLENTESQUEFPRETIUMTVELHACAAUGUEU_FFUSCE_ET_ANTE_IN_SEM_PHARETRA_PRETIUMMMMAURIS_DAPIBUS_FERMENTUM_URNA_SSCELERISQUE_ACCUMSAN_NULL_GCOMMODO_SIT_AMET_NNULLA_DAPIBUS_ID_PURUS_VITAE_MOLLIS_UPROIN_ET_SAPIEN_ET_TELLUS1}',
					'value' => 'long macro'
				],
				'expected_error' => 'Invalid parameter "/1/macro": value is too long.'
			],
			[
				'globalmacro' => [
					'macro' => '{$LONG_VALUE}',
					'value' => str_repeat('a', 2049)
				],
				'expected_error' => 'Invalid parameter "/1/value": value is too long.'
			],
			[
				'globalmacro' => [
					'macro' => '{$GLOBALMACRO_WITH_LONG_2_BYTE_CHARACTER_VALUE}',
					'value' => str_repeat('ö', 2049)
				],
				'expected_error' => 'Invalid parameter "/1/value": value is too long.'
			],
			[
				'globalmacro' => [
					'macro' => '{$GLOBALMACRO_WITH_LONG_3_BYTE_CHARACTER_VALUE}',
					'value' => str_repeat('坏', 2049)
				],
				'expected_error' => 'Invalid parameter "/1/value": value is too long.'
			],
			[
				'globalmacro' => [
					'macro' => '{$VAULT: "empty"}',
					'value' => '',
					'type' => '2'
				],
				'expected_error' => 'Invalid parameter "/1/value": cannot be empty.'
			],
			[
				'globalmacro' => [
					'macro' => '{$VAULT: "cute"}',
					'value' => ':)',
					'type' => '2'
				],
				'expected_error' => 'Invalid parameter "/1/value": incorrect syntax near ":)".'
			]
		];
	}

	/**
	 * @dataProvider globalmacroFailedData
	 */
	public function testUserMacro_FailedCreateUpdateGlobal($globalmacro, $expected_error) {
		$methods = ['usermacro.createglobal', 'usermacro.updateglobal'];

		foreach ($methods as $method) {
			if ($method == 'usermacro.updateglobal') {
				$globalmacro['globalmacroid'] = '13';
			}

			$this->call($method, $globalmacro, $expected_error);
			if (array_key_exists('macro', $globalmacro)) {
				$dbResult = 'select * from globalmacro where macro='.zbx_dbstr($globalmacro['macro']);
				$this->assertEquals(0, CDBHelper::getCount($dbResult));
			}
		}
	}

	public static function hostmacroUpdateData() {
		return [
			[
				'hostmacro' => [
					[
						'hostmacroid' => '1',
						'value' => 'test'
					],
					[
						'hostmacroid' => '2',
						'value' => 'test',
						'description' => 'notes'
					]
				],
				'expected_error' => null,
				'expect_db_rows' => [
					[
						'hostmacroid' => '1',
						'value' => 'test',
						'description' => 'description'
					],
					[
						'hostmacroid' => '2',
						'value' => 'test',
						'description' => 'notes'
					]
				]
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10001',
						'config' => []
					]
				],
				'expected_error' => null,
				'expect_db_rows' => [
					[
						'hostmacroid' => '10001'
					]
				]
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10000',
						'config' => [
							'label' => 'update label for noconf'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/label": value must be empty.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10000',
						'config' => [
							'description' => 'update description for noconf'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/description": value must be empty.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10000',
						'config' => [
							'required' => ZBX_WIZARD_FIELD_REQUIRED
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/required": value must be '.DB::getDefault('hostmacro_config', 'required').'.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10000',
						'config' => [
							'regex' => '/^[a-zA-Z0-9]*$/'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/regex": value must be empty.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10000',
						'config' => [
							'options' => [[
								'value' => 'option1',
								'text' => 'Option 1'
							]]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options": should be empty.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10001',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_NOCONF
						]
					]
				],
				'expected_error' => null,
				'expect_db_rows' => [[
					'hostmacroid' => '10001'
				]]
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10002',
						'config' => [
							'label' => 'label_2_upd',
							'description' => 'description_2_upd',
							'required' => ZBX_WIZARD_FIELD_NOT_REQUIRED,
							'regex' => ''
						]
					]
				],
				'expected_error' => null,
				'expect_db_rows' => [
					[
						'hostmacroid' => '10002',
						'config' => [
							'type' => (string) ZBX_WIZARD_FIELD_TEXT,
							'priority' => DB::getDefault('hostmacro_config', 'priority'),
							'section_name' => DB::getDefault('hostmacro_config', 'section_name'),
							'label' => 'label_2_upd',
							'description' => 'description_2_upd',
							'required' => (string) ZBX_WIZARD_FIELD_NOT_REQUIRED,
							'regex' => '',
							'options' => ''
						]
					]
				]
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10002',
						'config' => [
							'regex' => '/^([a-zA-Z0-9]*$/'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/regex": invalid regular expression.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10002',
						'config' => [
							'label' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/label": cannot be empty.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10002',
						'config' => [
							'required' => 9999
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/required": value must be one of '.
					implode(', ', [ZBX_WIZARD_FIELD_NOT_REQUIRED, ZBX_WIZARD_FIELD_REQUIRED]).'.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10002',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_LIST
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options": cannot be empty.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10002',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_CHECKBOX
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options": cannot be empty.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10002',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_CHECKBOX
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options": cannot be empty.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10002',
						'config' => [
							'options' => [[
								'value' => 'option1',
								'text' => 'Option 1'
							]]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options": should be empty.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10002',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_LIST,
							'options' => [[
								'value' => 'option1',
								'text' => 'Option 1'
							]]
						]
					]
				],
				'expected_error' => null,
				'expect_db_rows' => [
					[
						'hostmacroid' => '10002',
						'config' => [
							'type' => (string) ZBX_WIZARD_FIELD_LIST,
							'priority' => DB::getDefault('hostmacro_config', 'priority'),
							'section_name' => DB::getDefault('hostmacro_config', 'section_name'),
							'label' => 'label_2_upd',
							'description' => 'description_2_upd',
							'required' => (string) ZBX_WIZARD_FIELD_NOT_REQUIRED,
							'regex' => '',
							'options' => '[{"value":"option1","text":"Option 1"}]'
						]
					]
				]
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10003',
						'config' => [
							'options' => [
								[
									'value' => 'option4',
									'text' => 'Option 4'
								],
								[
									'value' => 'option5',
									'text' => 'Option 5'
								]
							]
						]
					]
				],
				'expected_error' => null,
				'expect_db_rows' => [
					[
						'hostmacroid' => '10003',
						'config' => [
							'type' => (string) ZBX_WIZARD_FIELD_LIST,
							'priority' => DB::getDefault('hostmacro_config', 'priority'),
							'section_name' => DB::getDefault('hostmacro_config', 'section_name'),
							'label' => 'label_3',
							'description' => '',
							'required' => (string) ZBX_WIZARD_FIELD_NOT_REQUIRED,
							'regex' => '',
							'options' => '[{"value":"option4","text":"Option 4"},{"value":"option5","text":"Option 5"}]'
						]
					]
				]
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10003',
						'config' => [
							'regex' => '/^[a-zA-Z0-9]*$/'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/regex": value must be empty.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10003',
						'config' => [
							'options' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options": cannot be empty.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10003',
						'config' => [
							'options' => [[]]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options/1": the parameter "value" is missing.',
				'expect_db_rows' => []
			],
			[
					'hostmacro' => [
						[
							'hostmacroid' => '10003',
							'config' => [
								'options' => [[
									'value' => 'option1'
								]]
							]
						]
					],
					'expected_error' => 'Invalid parameter "/1/config/options/1": the parameter "text" is missing.',
					'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10003',
						'config' => [
							'options' => [[
								'checked' => 1,
								'unchecked' => 0
							]]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options/1": unexpected parameter "checked".',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10003',
						'config' => [
							'options' => [
								[
									'value' => 'option1',
									'text' => 'Option 1'
								],
								[
									'value' => 'option1',
									'text' => 'Option 1'
								]
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options/2": value (value, text)=(option1, Option 1) already exists.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10004',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_CHECKBOX
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options/1": unexpected parameter "value".',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10004',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_CHECKBOX,
							'required' => ZBX_WIZARD_FIELD_REQUIRED
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/required": value must be '.DB::getDefault('hostmacro_config', 'required').'.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10004',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_CHECKBOX,
							'regex' => '/^[a-zA-Z0-9]*$/'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/regex": value must be empty.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10004',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_CHECKBOX,
							'options' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options": cannot be empty.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10004',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_CHECKBOX,
							'options' => [[]]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options/1": the parameter "checked" is missing.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10004',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_CHECKBOX,
							'options' => [['checked' => '1']]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options/1": the parameter "unchecked" is missing.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10004',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_CHECKBOX,
							'options' => [
								[
									'checked' => 1,
									'unchecked' => 0
								]
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options/1/checked": a character string is expected.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10004',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_CHECKBOX,
							'options' => [
								[
									'checked' => 1,
									'unchecked' => 0
								],
								[
									'checked' => 'option1',
									'unchecked' => 'option2'
								]
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options": maximum number of array elements is 1.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10004',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_CHECKBOX,
							'options' => [[
								'value' => 'option1',
								'text' => 'Option 1'
							]]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options/1": unexpected parameter "value".',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10004',
						'config' => [
							'required' => ZBX_WIZARD_FIELD_NOT_REQUIRED,
							'description' => 'description_4_upd'
						]
					]
				],
				'expected_error' => null,
				'expect_db_rows' => [
					[
						'hostmacroid' => '10004',
						'config' => [
							'type' => (string) ZBX_WIZARD_FIELD_LIST,
							'priority' => DB::getDefault('hostmacro_config', 'priority'),
							'section_name' => DB::getDefault('hostmacro_config', 'section_name'),
							'label' => 'label_4',
							'description' => 'description_4_upd',
							'required' => (string) ZBX_WIZARD_FIELD_NOT_REQUIRED,
							'regex' => '',
							'options' => '[{"value":"option1","text":"Option 1"}]'
						]
					]
				]
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10004',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_TEXT
						]
					]
				],
				'expected_error' => null,
				'expect_db_rows' => [
					[
						'hostmacroid' => '10004',
						'config' => [
							'type' => (string) ZBX_WIZARD_FIELD_TEXT,
							'priority' => DB::getDefault('hostmacro_config', 'priority'),
							'section_name' => DB::getDefault('hostmacro_config', 'section_name'),
							'label' => 'label_4',
							'description' => 'description_4_upd',
							'required' => (string) ZBX_WIZARD_FIELD_NOT_REQUIRED,
							'regex' => '',
							'options' => ''
						]
					]
				]
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10004',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_NOCONF
						]
					]
				],
				'expected_error' => null,
				'expect_db_rows' => [[
					'hostmacroid' => '10004'
				]]
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10005',
						'config' => [
							'required' => ZBX_WIZARD_FIELD_REQUIRED
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/required": value must be '.DB::getDefault('hostmacro_config', 'required').'.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10005',
						'config' => [
							'regex' => '/^[a-zA-Z0-9]*$/'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/regex": value must be empty.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10005',
						'config' => [
							'options' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options": cannot be empty.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10005',
						'config' => [
							'options' => [[]]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options/1": the parameter "checked" is missing.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10005',
						'config' => [
							'options' => [['checked' => '1']]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options/1": the parameter "unchecked" is missing.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10005',
						'config' => [
							'options' => [
								[
									'checked' => 1,
									'unchecked' => 0
								],
								[
									'checked' => 'option1',
									'unchecked' => 'option2'
								]
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options": maximum number of array elements is 1.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10005',
						'config' => [
							'options' => [[
								'value' => 'option1',
								'text' => 'Option 1'
							]]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options/1": unexpected parameter "value".',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10005',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_LIST
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options/1": unexpected parameter "checked".',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10005',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_LIST,
							'options' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options": cannot be empty.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10005',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_LIST,
							'options' => [[]]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options/1": the parameter "value" is missing.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10005',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_LIST,
							'options' => [['value' => 'option1']]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options/1": the parameter "text" is missing.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10005',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_LIST,
							'options' => [[
								'checked' => 1,
								'unchecked' => 0
							]]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options/1": unexpected parameter "checked".',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10005',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_LIST,
							'options' => [
								[
									'value' => 'option1',
									'text' => 'Option 1'
								],
								[
									'value' => 'option1',
									'text' => 'Option 1'
								]
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/options/2": value (value, text)=(option1, Option 1) already exists.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10005',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_LIST,
							'options' => [[
								'value' => 'option1',
								'text' => 'Option 1'
							]]
						]
					]
				],
				'expected_error' => null,
				'expect_db_rows' => [
					[
						'hostmacroid' => '10005',
						'config' => [
							'type' => (string) ZBX_WIZARD_FIELD_LIST,
							'priority' => DB::getDefault('hostmacro_config', 'priority'),
							'section_name' => DB::getDefault('hostmacro_config', 'section_name'),
							'label' => 'label_5',
							'description' => '',
							'required' => DB::getDefault('hostmacro_config', 'required'),
							'regex' => '',
							'options' => '[{"value":"option1","text":"Option 1"}]'
						]
					]
				]
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10006',
						'config' => [
							'type' => ZBX_WIZARD_FIELD_TEXT,
							'label' => 'label_6_upd',
							'description' => 'description_6_upd'
						]
					]
				],
				'expected_error' => null,
				'expect_db_rows' => [
					[
						'hostmacroid' => '10006',
						'config' => [
							'type' => (string) ZBX_WIZARD_FIELD_TEXT,
							'priority' => DB::getDefault('hostmacro_config', 'priority'),
							'section_name' => DB::getDefault('hostmacro_config', 'section_name'),
							'label' => 'label_6_upd',
							'description' => 'description_6_upd',
							'required' => DB::getDefault('hostmacro_config', 'required'),
							'regex' => '',
							'options' => ''
						]
					]
				]
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10008',
						'config' => [
							'priority' => 5
						]
					]
				],
				'expected_error' => null,
				'expect_db_rows' => [
					[
						'hostmacroid' => '10008',
						'config' => [
							'type' => (string) ZBX_WIZARD_FIELD_TEXT,
							'priority' => 5,
							'section_name' => DB::getDefault('hostmacro_config', 'section_name'),
							'label' => 'label_8',
							'description' => 'description_8',
							'required' => DB::getDefault('hostmacro_config', 'required'),
							'regex' => '',
							'options' => ''
						]
					]
				]
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10008',
						'config' => [
							'section_name' => 'advanced config'
						]
					]
				],
				'expected_error' => null,
				'expect_db_rows' => [
					[
						'hostmacroid' => '10008',
						'config' => [
							'type' => (string) ZBX_WIZARD_FIELD_TEXT,
							'priority' => 5,
							'section_name' => 'advanced config',
							'label' => 'label_8',
							'description' => 'description_8',
							'required' => DB::getDefault('hostmacro_config', 'required'),
							'regex' => '',
							'options' => ''
						]
					]
				]
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10008',
						'config' => [
							'priority' => -1
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/config/priority": value must be one of 0-2147483647.',
				'expect_db_rows' => []
			],
			[
				'hostmacro' => [
					[
						'hostmacroid' => '10009',
						'config' => [
							'section_name' => 'Advanced'
						]
					]
				],
				'expected_error' => null,
				'expect_db_rows' => [
					[
						'hostmacroid' => '10009',
						'config' => [
							'type' => (string) ZBX_WIZARD_FIELD_TEXT,
							'priority' => DB::getDefault('hostmacro_config', 'priority'),
							'section_name' => 'Advanced',
							'label' => 'label_9',
							'description' => 'description_9',
							'required' => DB::getDefault('hostmacro_config', 'required'),
							'regex' => '',
							'options' => ''
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider hostmacroUpdateData
	 */
	public function testUserMacro_Update($hostmacros, $expected_error, $expect) {
		$result = $this->call('usermacro.update', $hostmacros, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['hostmacroids'] as $key => $id) {
				$dbResult = DBSelect('SELECT * FROM hostmacro WHERE hostmacroid='.zbx_dbstr($id));
				$dbRow = DBFetch($dbResult);

				if (array_key_exists('config', $hostmacros[$key])) {
					$db_result_config = DBSelect(
						'SELECT type,priority,section_name,label,description,required,regex,options'.
						' FROM hostmacro_config'.
						' WHERE hostmacroid='.zbx_dbstr($id)
					);
					$db_row_config = DBFetch($db_result_config);

					if ($db_row_config !== false && !array_key_exists('type', $hostmacros[$key]['config'])) {
						$hostmacros[$key]['config']['type'] = $db_row_config['type'];
					}

					if ($hostmacros[$key]['config']['type'] == ZBX_WIZARD_FIELD_NOCONF) {
						$this->assertFalse($db_row_config);
					}
					else {
						$this->assertNotEmpty($db_row_config['label']);

						if ($hostmacros[$key]['config']['type'] == ZBX_WIZARD_FIELD_TEXT) {
							$this->assertEmpty($db_row_config['options']);
						}

						if ($hostmacros[$key]['config']['type'] == ZBX_WIZARD_FIELD_LIST) {
							$this->assertEmpty($db_row_config['regex']);
							$this->assertNotEmpty($db_row_config['options']);
						}

						if ($hostmacros[$key]['config']['type'] == ZBX_WIZARD_FIELD_CHECKBOX) {
							$this->assertEmpty($db_row_config['regex']);
							$this->assertEquals($db_row_config['required'],
								DB::getDefault('hostmacro_config', 'required')
							);
							$this->assertNotEmpty($db_row_config['options']);
						}

						$dbRow['config'] = $db_row_config;
					}
				}

				foreach ($expect[$key] as $field => $value) {
					$this->assertEquals($dbRow[$field], $expect[$key][$field]);
				}
			}
		}
	}

	public static function hostmacroDeleteData() {
		return [
			[
				'hostmacro' => [
					''
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'hostmacro' => [
					'123456'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'hostmacro' => [
					'abc'
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'hostmacro' => [
					'.'
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'hostmacro' => [
					'10007',
					'123456'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'hostmacro' => [
					'10007',
					'abc'
				],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'hostmacro' => [
					'10007',
					''
				],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'hostmacro' => [
					'10007',
					'10007'
				],
				'expected_error' => 'Invalid parameter "/2": value (10007) already exists.'
			],
			[
				'hostmacro' => [
					'10007'
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider hostmacroDeleteData
	 */
	public function testUserMacro_Delete($hostmacro, $expected_error) {
		$result = $this->call('usermacro.delete', $hostmacro, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['hostmacroids'] as $id) {
				$db_result = 'SELECT * FROM hostmacro WHERE hostmacroid='.zbx_dbstr($id);
				$this->assertEquals(0, CDBHelper::getCount($db_result));

				$db_result = 'SELECT * FROM hostmacro_config WHERE hostmacroid='.zbx_dbstr($id);
				$this->assertEquals(0, CDBHelper::getCount($db_result));
			}
		}
	}

	public static function globalmacroUpdateData() {
		return [
			// Check macro id
			[
				'globalmacro' => [[
					'value' => 'test'
				]],
				'expected_error' => 'Invalid parameter "/1": the parameter "globalmacroid" is missing.'
			],
			[
				'globalmacro' => [[
					'globalmacroid' => '',
					'value' => 'test'
				]],
				'expected_error' => 'Invalid parameter "/1/globalmacroid": a number is expected.'
			],
			[
				'globalmacro' => [[
					'globalmacroid' => 'abc',
					'value' => 'test'
				]],
				'expected_error' => 'Invalid parameter "/1/globalmacroid": a number is expected.'
			],
			[
				'globalmacro' => [[
					'globalmacroid' => '123456',
					'value' => 'test'
				]],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Check existing macro
			[
				'globalmacro' => [[
					'globalmacroid' => '13',
					'macro' => '{$SNMP_COMMUNITY}'
				]],
				'expected_error' => 'Macro "{$SNMP_COMMUNITY}" already exists.'
			],
			[
				'globalmacro' => [
					[
					'globalmacroid' => '13',
					'macro' => '{$THESAMEMACROID1}'
					],
					[
					'globalmacroid' => '13',
					'macro' => '{$THESAMEMACROID2}'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (globalmacroid)=(13) already exists.'
			],
			// Check successfully update of global macro.
			[
				'globalmacro' => [
					[
						'globalmacroid' => '13',
						'macro' => '{$MACRO_UPDATED}',
						'value' => 'updated'
					]
				],
				'expected_error' => null
			],
			[
				'globalmacro' => [
					[
						'globalmacroid' => '13',
						'macro' => '{$MACRO_UPDATED1}',
						'value' => 'updated1'
					],
					[
						'globalmacroid' => '14',
						'macro' => '{$MACRO_UPDATED2}',
						'value' => 'updated2',
						'description' => 'æų'
					]
				],
				'expected_error' => null,
				'expect_db_rows' => [
					[
						'globalmacroid' => '13',
						'macro' => '{$MACRO_UPDATED1}',
						'value' => 'updated1',
						'description' => 'desc',
						'type' => '0'
					],
					[
						'globalmacroid' => '14',
						'macro' => '{$MACRO_UPDATED2}',
						'value' => 'updated2',
						'description' => 'æų',
						'type' => '0'
					]
				]
			],
			[
				'globalmacro' => [
					[
						'globalmacroid' => '13',
						'macro' => '{$MACRO_UPDATED1}',
						'value' => 'updated1',
						'description' => ''
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider globalmacroUpdateData
	 */
	public function testUserMacro_UpdateGlobal($globalmacros, $expected_error, $expect = []) {
		$result = $this->call('usermacro.updateglobal', $globalmacros, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['globalmacroids'] as $key => $id) {
				$dbResult = DBSelect('select * from globalmacro where globalmacroid='.zbx_dbstr($id));
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['macro'], $globalmacros[$key]['macro']);
				$this->assertEquals($dbRow['value'], $globalmacros[$key]['value']);

				if (array_key_exists('description', $globalmacros[$key])) {
					$this->assertEquals($dbRow['description'], $globalmacros[$key]['description']);
				}

				if ($expect) {
					$this->assertEquals($dbRow, $expect[$key]);
				}
			}
		}
		else {
			foreach ($globalmacros as $globalmacro) {
				if (array_key_exists('macro', $globalmacro) && $globalmacro['macro'] != '{$SNMP_COMMUNITY}') {
					$dbResult = "select * from globalmacro where macro=".zbx_dbstr($globalmacro['macro']);
					$this->assertEquals(0, CDBHelper::getCount($dbResult));
				}
			}
		}
	}

	public static function globalmacroDeleteData() {
		return [
			[
				'globalmacro' => [
					''
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'globalmacro' => [
					'123456'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'globalmacro' => [
					'abc'
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'globalmacro' => [
					'.'
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'globalmacro' => [
					'15',
					'123456'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'globalmacro' => [
					'15',
					'abc'
				],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'globalmacro' => [
					'15',
					''
				],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'globalmacro' => [
					'15',
					'15'
				],
				'expected_error' => 'Invalid parameter "/2": value (15) already exists.'
			],
			[
				'globalmacro' => [
					'15'
				],
				'expected_error' => null
			],
			[
				'globalmacro' => [
					'16',
					'17'
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider globalmacroDeleteData
	 */
	public function testUserMacro_DeleteGlobal($globalmacro, $expected_error) {
		$result = $this->call('usermacro.deleteglobal', $globalmacro, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['globalmacroids'] as $id) {
				$dbResult = 'SELECT * FROM globalmacro WHERE globalmacroid='.zbx_dbstr($id);
				$this->assertEquals(0, CDBHelper::getCount($dbResult));
			}
		}
	}

	public static function globalmacroPermissionsData() {
		return [
			// Check zabbix admin permissions to create, update and delete global macro.
			[
				'method' => 'usermacro.createglobal',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'globalmacro' => [
					'macro' => '{$MACRO_ADMIN}',
					'value' => 'admin'
				],
				'expected_error' => 'No permissions to call "usermacro.createglobal".'
			],
			[
				'method' => 'usermacro.updateglobal',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'globalmacro' => [
					'globalmacroid' => '13',
					'macro' => '{$MACRO_UPDATE_ADMIN}'
				],
				'expected_error' => 'No permissions to call "usermacro.updateglobal".'
			],
			[
				'method' => 'usermacro.deleteglobal',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'globalmacro' => ['13'],
				'expected_error' => 'No permissions to call "usermacro.deleteglobal".'
			],
			// Check zabbix user permissions to create, update and delete global macro.
			[
				'method' => 'usermacro.createglobal',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'globalmacro' => [
					'macro' => '{$MACRO_USER}',
					'value' => 'USER'
				],
				'expected_error' => 'No permissions to call "usermacro.createglobal".'
			],
			[
				'method' => 'usermacro.updateglobal',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'globalmacro' => [
					'globalmacroid' => '14',
					'macro' => '{$MACRO_UPDATE_USER}'
				],
				'expected_error' => 'No permissions to call "usermacro.updateglobal".'
			],
			[
				'method' => 'usermacro.deleteglobal',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'globalmacro' => ['14'],
				'expected_error' => 'No permissions to call "usermacro.deleteglobal".'
			],
			// Check guset permissions to create, update and delete global macro.
			[
				'method' => 'usermacro.createglobal',
				'user' => ['user' => 'guest', 'password' => ''],
				'globalmacro' => [
					'macro' => '{$MACRO_GUEST}',
					'value' => 'GUEST'
				],
				'expected_error' => 'No permissions to call "usermacro.createglobal".'
			],
			[
				'method' => 'usermacro.updateglobal',
				'user' => ['user' => 'guest', 'password' => ''],
				'globalmacro' => [
					'globalmacroid' => '14',
					'macro' => '{$MACRO_UPDATE_GUEST}'
				],
				'expected_error' => 'No permissions to call "usermacro.updateglobal".'
			],
			[
				'method' => 'usermacro.deleteglobal',
				'user' => ['user' => 'guest', 'password' => ''],
				'globalmacro' => ['14'],
				'expected_error' => 'No permissions to call "usermacro.deleteglobal".'
			]
		];
	}

	/**
	 * @onBefore removeGuestFromDisabledGroup
	 * @onAfter addGuestToDisabledGroup
	 *
	 * @dataProvider globalmacroPermissionsData
	 */
	public function testUserMacro_UserPermissionsGlobal($method, $user, $globalmacro, $expected_error) {
		$this->authorize($user['user'], $user['password']);
		$this->call($method, $globalmacro, $expected_error);
	}

	/**
	 * Guest user needs to be out of "Disabled" group to have access to frontend.
	 */
	public static function removeGuestFromDisabledGroup() {
		DBexecute('DELETE FROM users_groups WHERE userid=2 AND usrgrpid=9');
	}

	public function addGuestToDisabledGroup() {
		DBexecute('INSERT INTO users_groups (id, usrgrpid, userid) VALUES (150, 9, 2)');
	}
}

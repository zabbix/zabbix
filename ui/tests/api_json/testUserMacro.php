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

	public static function hostmacro_create() {
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
			]
		];
	}

	/**
	 * @dataProvider hostmacro_create
	 */
	public function testUserMacro_Create($hostmacro, $expected_error, $expect = []) {
		$result = $this->call('usermacro.create', $hostmacro, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['hostmacroids'] as $key => $id) {
				$dbResult = DBSelect('select * from hostmacro where hostmacroid='.zbx_dbstr($id));
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['macro'], $hostmacro['macro']);
				$this->assertEquals($dbRow['value'], $hostmacro['value']);
				$this->assertEquals($dbRow['type'], $hostmacro['type']);

				if (array_key_exists('description', $hostmacro)) {
					$this->assertEquals($dbRow['description'], $hostmacro['description']);
				}

				if ($expect) {
					$expect['hostmacroid'] = $id;
					$this->assertEquals($dbRow, $expect);
				}
			}
		}
	}

	public static function globalmacro_create() {
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
	* @dataProvider globalmacro_create
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

	public static function globalmacro_failed() {
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
	* @dataProvider globalmacro_failed
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

	public static function hostmacro_update() {
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
			]
		];
	}

	/**
	 * @dataProvider hostmacro_update
	 */
	public function testUserMacro_Update($hostmacros, $expected_error, $expect) {
		$result = $this->call('usermacro.update', $hostmacros, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['hostmacroids'] as $key => $id) {
				$dbResult = DBSelect('select * from hostmacro where hostmacroid='.zbx_dbstr($id));
				$dbRow = DBFetch($dbResult);

				foreach ($expect[$key] as $field => $value) {
					$this->assertEquals($dbRow[$field], $expect[$key][$field]);
				}
			}
		}
	}

	public static function globalmacro_update() {
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
	 * @dataProvider globalmacro_update
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

	public static function globalmacro_delete() {
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
	* @dataProvider globalmacro_delete
	*/
	public function testUserMacro_DeleteGlobal($globalmacro, $expected_error) {
		$result = $this->call('usermacro.deleteglobal', $globalmacro, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['globalmacroids'] as $id) {
				$dbResult = 'select * from globalmacro where globalmacroid='.zbx_dbstr($id);
				$this->assertEquals(0, CDBHelper::getCount($dbResult));
			}
		}
	}

	public static function globalmacro_permissions() {
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
	 * @dataProvider globalmacro_permissions
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

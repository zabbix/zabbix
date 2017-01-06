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

class testUserMacro extends CZabbixTest {

	public static function globalmacro_create() {
		return [
			// Check unexpected parametr
			[
				'globalmacro' => [
					'macro' => '{$HOSTID}',
					'value' => 'test',
					'hostid ' => '100084'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "hostid ".'
			],
			// Check macro.
			[
				'globalmacro' => [
					'value' => 'test'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": the parameter "macro" is missing.'
			],
			// Check existing macro
			[
				'globalmacro' => [
					'macro' => '{$SNMP_COMMUNITY}',
					'value' => 'test'
				],
				'success_expected' => false,
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
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (macro)=({$THESAMEMACRO}) already exists.'
			],
			// Check value
			[
				'globalmacro' => [
					'macro' => '{$CHECKVALUE}'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": the parameter "value" is missing.'
			],
			// Check successfully creation of global macro.
			[
				'globalmacro' => [
					[
						'macro' => '{$ABC123}',
						'value' => 'test'
					],
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'globalmacro' => [
					[
						'macro' => '{$MACRO:context}',
						'value' => 'test'
					],
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'globalmacro' => [
					[
						'macro' => '{$MACRO:"A"}',
						'value' => 'test'
					],
				],
				'success_expected' => true,
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
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider globalmacro_create
	*/
	public function testUserMacro_CreateGlobal($globalmacro, $success_expected, $expected_error) {
		$result = $this->api_acall('usermacro.createglobal', $globalmacro, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['globalmacroids'] as $key => $id) {
				$dbResult = DBSelect('select * from globalmacro where globalmacroid='.$id);
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['macro'], $globalmacro[$key]['macro']);
				$this->assertEquals($dbRow['value'], $globalmacro[$key]['value']);
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertSame($expected_error, $result['error']['data']);
		}
	}

	public static function globalmacro_failed() {
		return [
			// Check unexpected parametr
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
				'expected_error' => 'Invalid parameter "/1/macro": a user macro is expected.'
			],
			[
				'globalmacro' => [
					'macro' => '{$globalmacro}',
					'value' => 'test'
				],
				'expected_error' => 'Invalid parameter "/1/macro": a user macro is expected.'
			],
			[
				'globalmacro' => [
					'macro' => '☺',
					'value' => 'test'
				],
				'expected_error' => 'Invalid parameter "/1/macro": a user macro is expected.'
			],
			[
				'globalmacro' => [
					'macro' => '{GlOBALMACRO}',
					'value' => 'test'
				],
				'expected_error' => 'Invalid parameter "/1/macro": a user macro is expected.'
			],
			[
				'globalmacro' => [
					'macro' => '{$GlOBALMACRO',
					'value' => 'test'
				],
				'expected_error' => 'Invalid parameter "/1/macro": a user macro is expected.'
			],
			[
				'globalmacro' => [
					'macro' => '{$GlOBALMACRO}}',
					'value' => 'test'
				],
				'expected_error' => 'Invalid parameter "/1/macro": a user macro is expected.'
			],
			[
				'globalmacro' => [
					'macro' => '{{$GlOBALMACRO}}',
					'value' => 'test'
				],
				'expected_error' => 'Invalid parameter "/1/macro": a user macro is expected.'
			],
			[
				'globalmacro' => [
					'macro' => '{$УТФ8}',
					'value' => 'test'
				],
				'expected_error' => 'Invalid parameter "/1/macro": a user macro is expected.'
			],
			[
				'globalmacro' => [
					'macro' => '{$!@#$%^&*()-=<>}',
					'value' => 'test'
				],
				'expected_error' => 'Invalid parameter "/1/macro": a user macro is expected.'
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
					'value' => 'Aliquam erat volutpat. Suspendisse lorem libero, efficitur a ornare non, interdum et nulla. Maecenas at massa at lacus aliquam pretium sit amet vel ligula. In ultricies dignissim sapien sit amet eleifend. Nullam consectetur sem eget arcu interdum, at so256'
				],
				'expected_error' => 'Invalid parameter "/1/value": value is too long.'
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

			$result = $this->api_acall($method, $globalmacro, $debug);
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertEquals($expected_error, $result['error']['data']);
			if (array_key_exists('macro', $globalmacro)) {
				$dbResult = 'select * from globalmacro where macro='.$globalmacro['macro'];
				$this->assertEquals(0, DBcount($dbResult));
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
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": the parameter "globalmacroid" is missing.'
			],
			[
				'globalmacro' => [[
					'globalmacroid' => '',
					'value' => 'test'
				]],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/globalmacroid": a number is expected.'
			],
			[
				'globalmacro' => [[
					'globalmacroid' => 'abc',
					'value' => 'test'
				]],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/globalmacroid": a number is expected.'
			],
			[
				'globalmacro' => [[
					'globalmacroid' => '123456',
					'value' => 'test'
				]],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Check existing macro
			[
				'globalmacro' => [[
					'globalmacroid' => '13',
					'macro' => '{$SNMP_COMMUNITY}'
				]],
				'success_expected' => false,
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
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (globalmacroid)=(13) already exists.'
			],
			// Check successfully update of global macro.
			[
				'globalmacro' => [
					[
						'globalmacroid' => '13',
						'macro' => '{$MACRO_UPDATED}',
						'value' => 'updated'
					],
				],
				'success_expected' => true,
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
						'value' => 'updated2'
					]
				],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider globalmacro_update
	*/
	public function testUserMacro_UpdateGlobal($globalmacros, $success_expected, $expected_error) {
		$result = $this->api_acall('usermacro.updateglobal', $globalmacros, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['globalmacroids'] as $key => $id) {
				$dbResult = DBSelect('select * from globalmacro where globalmacroid='.$id);
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['macro'], $globalmacros[$key]['macro']);
				$this->assertEquals($dbRow['value'], $globalmacros[$key]['value']);
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));
			$this->assertSame($expected_error, $result['error']['data']);

			foreach ($globalmacros as $globalmacro) {
				if (array_key_exists('macro', $globalmacro)) {
					$dbResult = 'select * from globalmacro where macro='.$globalmacro['macro'];
					$this->assertEquals(0, DBcount($dbResult));
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
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'globalmacro' => [
					'123456'
				],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'globalmacro' => [
					'abc'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'globalmacro' => [
					'.'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'globalmacro' => [
					'15',
					'123456'
				],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'globalmacro' => [
					'15',
					'abc'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'globalmacro' => [
					'15',
					''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'globalmacro' => [
					'15',
					'15'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (15) already exists.'
			],
			[
				'globalmacro' => [
					'15'
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'globalmacro' => [
					'16',
					'17'
				],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider globalmacro_delete
	*/
	public function testUserMacro_DeleteGlobal($globalmacro, $success_expected, $expected_error) {
		$result = $this->api_acall('usermacro.deleteglobal', $globalmacro, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['globalmacroids'] as $id) {
				$dbResult = 'select * from globalmacro where globalmacroid='.$id;
				$this->assertEquals(0, DBcount($dbResult));
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertEquals($expected_error, $result['error']['data']);
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
				'expected_error' => 'You do not have permission to perform this operation.'
			],
			[
				'method' => 'usermacro.updateglobal',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'globalmacro' => [
					'globalmacroid' => '13',
					'macro' => '{$MACRO_UPDATE_ADMIN}',
				],
				'expected_error' => 'You do not have permission to perform this operation.'
			],
			[
				'method' => 'usermacro.deleteglobal',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'globalmacro' => ['13'],
				'expected_error' => 'You do not have permission to perform this operation.'
			],
			// Check zabbix user permissions to create, update and delete global macro.
			[
				'method' => 'usermacro.createglobal',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'globalmacro' => [
					'macro' => '{$MACRO_USER}',
					'value' => 'USER'
				],
				'expected_error' => 'You do not have permission to perform this operation.'
			],
			[
				'method' => 'usermacro.updateglobal',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'globalmacro' => [
					'globalmacroid' => '14',
					'macro' => '{$MACRO_UPDATE_USER}',
				],
				'expected_error' => 'You do not have permission to perform this operation.'
			],
			[
				'method' => 'usermacro.deleteglobal',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'globalmacro' => ['14'],
				'expected_error' => 'You do not have permission to perform this operation.'
			],
			// Check guset permissions to create, update and delete global macro.
			[
				'method' => 'usermacro.createglobal',
				'user' => ['user' => 'guest', 'password' => ''],
				'globalmacro' => [
					'macro' => '{$MACRO_GUEST}',
					'value' => 'GUEST'
				],
				'expected_error' => 'You do not have permission to perform this operation.'
			],
			[
				'method' => 'usermacro.updateglobal',
				'user' => ['user' => 'guest', 'password' => ''],
				'globalmacro' => [
					'globalmacroid' => '14',
					'macro' => '{$MACRO_UPDATE_GUEST}',
				],
				'expected_error' => 'You do not have permission to perform this operation.'
			],
			[
				'method' => 'usermacro.deleteglobal',
				'user' => ['user' => 'guest', 'password' => ''],
				'globalmacro' => ['14'],
				'expected_error' => 'You do not have permission to perform this operation.'
			]
		];
	}

	/**
	* @dataProvider globalmacro_permissions
	*/
	public function testUserMacro_UserPermissionsGlobal($method, $user, $globalmacro, $expected_error) {
		$result = $this->api_call_with_user($method, $user, $globalmacro, $debug);

		$this->assertFalse(array_key_exists('result', $result));
		$this->assertTrue(array_key_exists('error', $result));

		$this->assertEquals($expected_error, $result['error']['data']);
	}
}

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

class testScripts extends CZabbixTest {

	public static function script_create() {
		return [
			// Check script command.
			[
				'script' => [
					'name' => 'API create script'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": the parameter "command" is missing.'
			],
			[
				'script' => [
					'name' => 'API create script',
					'command' => ''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/command": cannot be empty.'
			],
			// Check script name.
			[
				'script' => [
					'command' => 'reboot server'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": the parameter "name" is missing.'
			],
			[
				'script' => [
					'name' => '',
					'command' => 'reboot server'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'script' => [
					'name' => 'API/Script/',
					'command' => 'reboot server'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/name": directory or script name cannot be empty.'
			],
			[
				'script' => [
					'name' => 'Ping',
					'command' => 'reboot server'
				],
				'success_expected' => false,
				'expected_error' => 'Script "Ping" already exists.'
			],
			[
				'script' => [
					'name' => 'Ping/test',
					'command' => 'reboot server'
				],
				'success_expected' => false,
				'expected_error' => 'Script menu path "Ping/test" already used in script name "Ping".'
			],
			[
				'script' => [
					[
						'name' => 'Scripts with the same name',
						'command' => 'reboot server'
					],
					[
						'name' => 'Scripts with the same name',
						'command' => 'reboot server'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (name)=(Scripts with the same name) already exists.'
			],
			[
				'script' => [
					[
						'name' => 'test',
						'command' => 'reboot server'
					],
					[
						'name' => 'test/test/test test',
						'command' => 'reboot server'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Script menu path "test/test/test test" already used in script name "test".'
			],
			[
				'script' => [
					[
						'name' => 'test/test',
						'command' => 'reboot server'
					],
					[
						'name' => 'test',
						'command' => 'reboot server'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Script name "test" already used in menu path for script "test/test".'
			],
			// Check successfully creation of script.
			[
				'script' => [
					[
						'name' => 'Апи скрипт создан утф-8',
						'command' => 'reboot server 1'
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'script' => [
					[
						'name' => 'API create one script',
						'command' => 'reboot server 1'
					],
					[
						'name' => 'æų',
						'command' => 'æų'
					]
				],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider script_create
	*/
	public function testScripts_Create($script, $success_expected, $expected_error) {
		$result = $this->api_acall('script.create', $script, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['scriptids'] as $key => $id) {
				$dbResultUser = DBSelect('select * from scripts where scriptid='.$id);
				$dbRowUser = DBFetch($dbResultUser);
				$this->assertEquals($dbRowUser['name'], $script[$key]['name']);
				$this->assertEquals($dbRowUser['command'], $script[$key]['command']);
				$this->assertEquals($dbRowUser['host_access'], 2);
				$this->assertEquals($dbRowUser['usrgrpid'], 0);
				$this->assertEquals($dbRowUser['groupid'], 0);
				$this->assertEquals($dbRowUser['description'], '');
				$this->assertEquals($dbRowUser['confirmation'], '');
				$this->assertEquals($dbRowUser['type'], 0);
				$this->assertEquals($dbRowUser['execute_on'], 2);
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertSame($expected_error, $result['error']['data']);
		}
	}

	public static function script_update() {
		return [
			// Check script id.
			[
				'script' => [[
					'name' => 'API updated script',
					'command' => 'reboot'
				]],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": the parameter "scriptid" is missing.'
			],
			[
				'script' => [[
					'name' => 'API updated script',
					'command' => 'reboot',
					'scriptid' => ''
				]],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/scriptid": a number is expected.'
			],
			[
				'script' => [[
					'name' => 'API updated script',
					'command' => 'reboot',
					'scriptid' => 'abc'
				]],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/scriptid": a number is expected.'
			],
			[
				'script' => [[
					'name' => 'API updated script',
					'command' => 'reboot',
					'scriptid' => '1.1'
				]],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/scriptid": a number is expected.'
			],
			[
				'script' => [[
					'name' => 'API updated script',
					'command' => 'reboot',
					'scriptid' => '123456'
				]],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'script' => [
					[
						'scriptid' => '6',
						'name' => 'Scripts with the same id 1'
					],
					[
						'scriptid' => '6',
						'name' => 'Scripts with the same id 2'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (scriptid)=(6) already exists.'
			],
			// Check script command.
			[
				'script' => [[
					'scriptid' => '6',
					'name' => 'API updated script',
					'command' => ''
				]],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/command": cannot be empty.'
			],
			// Check script name.
			[
				'script' => [[
					'scriptid' => '6',
					'name' => '',
					'command' => 'reboot server'
				]],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'script' => [[
					'scriptid' => '6',
					'name' => 'API/Update/',
					'command' => 'reboot server'
				]],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/name": directory or script name cannot be empty.'
			],
			[
				'script' => [[
					'scriptid' => '6',
					'name' => 'Ping',
					'command' => 'reboot server'
				]],
				'success_expected' => false,
				'expected_error' => 'Script "Ping" already exists.'
			],
			[
				'script' => [[
					'scriptid' => '6',
					'name' => 'Ping/test',
					'command' => 'reboot server'
				]],
				'success_expected' => false,
				'expected_error' => 'Script menu path "Ping/test" already used in script name "Ping".'
			],
			[
				'script' => [
					[
						'scriptid' => '6',
						'name' => 'Scripts with the same name',
						'command' => 'reboot server'
					],
					[
						'scriptid' => '7',
						'name' => 'Scripts with the same name',
						'command' => 'reboot server'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (name)=(Scripts with the same name) already exists.'
			],
			[
				'script' => [
					[
						'scriptid' => '6',
						'name' => 'test',
					],
					[
						'scriptid' => '7',
						'name' => 'test/test/test test',
					]
				],
				'success_expected' => false,
				'expected_error' => 'Script menu path "test/test/test test" already used in script name "test".'
			],
			[
				'script' => [
					[
						'scriptid' => '6',
						'name' => 'test/test',
					],
					[
						'scriptid' => '7',
						'name' => 'test',
					]
				],
				'success_expected' => false,
				'expected_error' => 'Script name "test" already used in menu path for script "test/test".'
			],
			// Check successfully script update.
			[
				'script' => [
					[
						'scriptid' => '6',
						'name' => 'Апи скрипт обнавлён утф-8',
						'command' => 'reboot server'
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'script' => [
					[
						'scriptid' => '6',
						'name' => 'API updated one script',
						'command' => 'reboot server 1'
					],
					[
						'scriptid' => '7',
						'name' => 'API updated two script',
						'command' => 'reboot server 2'
					]
				],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider script_update
	*/
	public function testScripts_Update($scripts, $success_expected, $expected_error) {
		$result = $this->api_acall('script.update', $scripts, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['scriptids'] as $key => $id) {
				$dbResult = DBSelect('select * from scripts where scriptid='.$id);
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['name'], $scripts[$key]['name']);
				$this->assertEquals($dbRow['command'], $scripts[$key]['command']);
				$this->assertEquals($dbRow['host_access'], 2);
				$this->assertEquals($dbRow['usrgrpid'], 0);
				$this->assertEquals($dbRow['groupid'], 0);
				$this->assertEquals($dbRow['description'], '');
				$this->assertEquals($dbRow['confirmation'], '');
				$this->assertEquals($dbRow['type'], 0);
				$this->assertEquals($dbRow['execute_on'], 2);
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));
			$this->assertSame($expected_error, $result['error']['data']);

			foreach ($scripts as $script) {
				if (array_key_exists('name', $script) && $script['name'] != 'Ping'){
					$dbResult = "select * from scripts where name='".$script['name']."'";
					$this->assertEquals(0, DBcount($dbResult));
				}
			}
		}
	}

	public static function script_properties() {
		return [
			// Check host_access.
			[
				'script' => [
					'name' => 'API empty host_access',
					'command' => 'reboot server',
					'host_access' => ''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/host_access": a number is expected.'
			],
			[
				'script' => [
					'name' => 'API host_access string',
					'command' => 'reboot server',
					'host_access' => 'abc'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/host_access": a number is expected.'
			],
			[
				'script' => [
					'name' => 'API invalid host_access',
					'command' => 'reboot server',
					'host_access' => '0'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/host_access": value must be one of 2, 3.'
			],
			[
				'script' => [
					'name' => 'API invalid host_access ',
					'command' => 'reboot server',
					'host_access' => '1'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/host_access": value must be one of 2, 3.'
			],
			// Check usrgrpid.
			[
				'script' => [
					'name' => 'API empty usrgrpid',
					'command' => 'reboot server',
					'usrgrpid' => ''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			[
				'script' => [
					'name' => 'API usrgrpid string',
					'command' => 'reboot server',
					'usrgrpid' => 'abc'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			[
				'script' => [
					'name' => 'API invalid usrgrpid',
					'command' => 'reboot server',
					'usrgrpid' => '1.1'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			[
				'script' => [
					'name' => 'API nonexistent usrgrpid ',
					'command' => 'reboot server',
					'usrgrpid' => '123456'
				],
				'success_expected' => false,
				'expected_error' => 'User group with ID "123456" is not available.'
			],
			// Check groupid.
			[
				'script' => [
					'name' => 'API empty groupid',
					'command' => 'reboot server',
					'groupid' => ''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/groupid": a number is expected.'
			],
			[
				'script' => [
					'name' => 'API groupid string',
					'command' => 'reboot server',
					'groupid' => 'abc'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/groupid": a number is expected.'
			],
			[
				'script' => [
					'name' => 'API invalid groupid',
					'command' => 'reboot server',
					'groupid' => '1.1'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/groupid": a number is expected.'
			],
			[
				'script' => [
					'name' => 'API nonexistent groupid',
					'command' => 'reboot server',
					'groupid' => '123456'
				],
				'success_expected' => false,
				'expected_error' => 'Host group with ID "123456" is not available.'
			],
			// Check type.
			[
				'script' => [
					'name' => 'API empty type',
					'command' => 'reboot server',
					'type' => ''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/type": a number is expected.'
			],
			[
				'script' => [
					'name' => 'API type string',
					'command' => 'reboot server',
					'type' => 'abc'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/type": a number is expected.'
			],
			[
				'script' => [
					'name' => 'API invalid type',
					'command' => 'reboot server',
					'type' => '1.1'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/type": a number is expected.'
			],
			[
				'script' => [
					'name' => 'API nonexistent type',
					'command' => 'reboot server',
					'type' => '2'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/type": value must be one of 0, 1.'
			],
			// Check execute_on.
			[
				'script' => [
					'name' => 'API empty execute_on',
					'command' => 'reboot server',
					'execute_on' => ''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/execute_on": a number is expected.'
			],
			[
				'script' => [
					'name' => 'API execute_on string',
					'command' => 'reboot server',
					'execute_on' => 'abc'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/execute_on": a number is expected.'
			],
			[
				'script' => [
					'name' => 'API invalid execute_on',
					'command' => 'reboot server',
					'execute_on' => '1.1'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/execute_on": a number is expected.'
			],
			[
				'script' => [
					'name' => 'API nonexistent execute_on',
					'command' => 'reboot server',
					'execute_on' => '3'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/execute_on": value must be one of 0, 1, 2.'
			],
			[
				'script' => [
					'name' => 'API IPMI execute_on agent',
					'command' => 'reboot server',
					'type' => '1',
					'execute_on' => '0'
				],
				'success_expected' => false,
				'expected_error' => 'IPMI scripts can be executed only by server.'
			],
			// Check successfully creation and update with all properties.
			[
				'script' => [
						'name' => 'API script with all properties',
						'command' => 'reboot agent',
						'host_access' => '3',
						'usrgrpid' => '13',
						'groupid' => '50005',
						'description' => 'Check successfully creation or update with all properties',
						'confirmation' => 'Do you want to reboot it?',
						'type' => '0',
						'execute_on' => '0'
				],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider script_properties
	*/
	public function testScripts_NotRequiredProperties($script, $success_expected, $expected_error) {
		$methods = ['script.create', 'script.update'];

		foreach ($methods as $method) {
			if ($method == 'script.update') {
				$script['scriptid'] = '6';
				$script['name'] = 'Update '.$script['name'];
			}
			$result = $this->api_acall($method, $script, $debug);

			if ($success_expected) {
				$this->assertTrue(array_key_exists('result', $result));
				$this->assertFalse(array_key_exists('error', $result));

				$dbResult = DBSelect('select * from scripts where scriptid='.$result['result']['scriptids'][0]);
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['name'], $script['name']);
				$this->assertEquals($dbRow['command'], $script['command']);
				$this->assertEquals($dbRow['host_access'], $script['host_access']);
				$this->assertEquals($dbRow['usrgrpid'], $script['usrgrpid']);
				$this->assertEquals($dbRow['groupid'], $script['groupid']);
				$this->assertEquals($dbRow['description'], $script['description']);
				$this->assertEquals($dbRow['confirmation'], $script['confirmation']);
				$this->assertEquals($dbRow['type'], $script['type']);
				$this->assertEquals($dbRow['execute_on'], $script['execute_on']);
			}
			else {
				$this->assertFalse(array_key_exists('result', $result));
				$this->assertTrue(array_key_exists('error', $result));

				$this->assertSame($expected_error, $result['error']['data']);
				$dbResult = "select * from scripts where name='".$script['name']."'";
				$this->assertEquals(0, DBcount($dbResult));
			}
		}
	}

	public static function script_delete() {
		return [
			// Check script id.
			[
				'script' => [''],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'script' => ['abc'],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'script' => ['1.1'],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'script' => ['123456'],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'script' => ['8', '8'],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (8) already exists.'
			],
			// Check if deleted scripts used in actions.
			[
				'script' => ['11'],
				'success_expected' => false,
				'expected_error' => 'Cannot delete scripts. Script "API script in action" is used in action operation "API action with script".'
			],
			// Successfully delete scripts.
			[
				'script' => ['8'],
				'success_expected' => true,
				'expected_error' => null
			],
						[
				'script' => ['9', '10'],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider script_delete
	*/
	public function testScripts_Delete($script, $success_expected, $expected_error) {
		$result = $this->api_acall('script.delete', $script, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['scriptids'] as $id) {
				$dbResult = 'select * from scripts where scriptid='.$id;
				$this->assertEquals(0, DBcount($dbResult));
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertEquals($expected_error, $result['error']['data']);
		}
	}

	public static function script_execute() {
		return [
			[
				'script' => [
					'scriptid' => '1',
					'hostid' => '10084',
					'value' => 'test'
					],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/": unexpected parameter "value".'
			],
			// Check script id.
			[
				'script' => [
					'hostid' => '10084'
					],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/": the parameter "scriptid" is missing.'
			],
			[
				'script' => [
					'scriptid' => '',
					'hostid' => '10084'
					],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/scriptid": a number is expected.'
			],
			[
				'script' => [
					'scriptid' => 'abc',
					'hostid' => '10084'
					],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/scriptid": a number is expected.'
			],
			[
				'script' => [
					'scriptid' => '1.1',
					'hostid' => '10084'
					],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/scriptid": a number is expected.'
			],
			[
				'script' => [
					'scriptid' => 'æų',
					'hostid' => '10084'
					],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/scriptid": a number is expected.'
			],
			[
				'script' => [
					'scriptid' => '123456',
					'hostid' => '10084'
					],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Check host id.
			[
				'script' => [
					'scriptid' => '1'
					],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/": the parameter "hostid" is missing.'
			],
			[
				'script' => [
					'scriptid' => '1',
					'hostid' => ''
					],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/hostid": a number is expected.'
			],
			[
				'script' => [
					'scriptid' => '1',
					'hostid' => 'abc'
					],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/hostid": a number is expected.'
			],
			[
				'script' => [
					'scriptid' => '1',
					'hostid' => '1.1'
					],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/hostid": a number is expected.'
			],
			[
				'script' => [
					'scriptid' => '1',
					'hostid' => 'æų'
					],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/hostid": a number is expected.'
			],
			[
				'script' => [
					'scriptid' => '1',
					'hostid' => '123456'
					],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Check script peremissions for host group. Host belongs to the host group that hasn't permission to execute current script
			[
				'script' => [
					'scriptid' => '4',
					'hostid' => '50009'
					],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			]
		];
	}

	/**
	* @dataProvider script_execute
	*/
	public function testScripts_Execute($script, $success_expected, $expected_error) {
		$result = $this->api_acall('script.execute', $script, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));
			$this->assertEquals('success', $result['result']['response']);
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertEquals($expected_error, $result['error']['data']);
		}
	}

	public static function script_permissions() {
		return [
			// User have permissions to host, but not to script (script can execute only specific user group).
			[
				'method' => 'script.execute',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'script' => [
							'scriptid' => '12',
							'hostid' => '50009'
						],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// User have permissions to script, but not to host (script can execute only on specific host group).
			[
				'method' => 'script.execute',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'script' => [
							'scriptid' => '13',
							'hostid' => '10084'
						],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// User have deny permissions to host, but script required read permissions for the host.
			[
				'method' => 'script.execute',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'script' => [
							'scriptid' => '1',
							'hostid' => '50014'
						],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Check zabbix admin permissions to create, update, delete and execute script.
			[
				'method' => 'script.create',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'script' => [
							'name' => 'API script create as zabbix admin',
							'command' => 'reboot server 1'
						],
				'expected_error' => 'You do not have permission to perform this operation.'
			],
			[
				'method' => 'script.update',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'script' => [
							'scriptid' => '6',
							'name' => 'API script update as zabbix admin',
						],
				'expected_error' => 'You do not have permission to perform this operation.'
			],
			[
				'method' => 'script.delete',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'script' => ['7'],
				'expected_error' => 'You do not have permission to perform this operation.'
			],
			[
				'method' => 'script.execute',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'script' => [
							'scriptid' => '1',
							'hostid' => '10084'
						],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Check zabbix user permissions to create, update, delete and execute script.
			[
				'method' => 'script.create',
				'login' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'script' => [
							'name' => 'API script create as zabbix user',
							'command' => 'reboot server 1'
						],
				'expected_error' => 'You do not have permission to perform this operation.'
			],
			[
				'method' => 'script.update',
				'login' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'script' => [
							'scriptid' => '6',
							'name' => 'API script update as zabbix user',
						],
				'expected_error' => 'You do not have permission to perform this operation.'
			],
			[
				'method' => 'script.delete',
				'login' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'script' => ['7'],
				'expected_error' => 'You do not have permission to perform this operation.'
			],
			[
				'method' => 'script.execute',
				'login' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'script' => [
							'scriptid' => '1',
							'hostid' => '10084'
						],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			]
		];
	}

	/**
	* @dataProvider script_permissions
	*/
	public function testScripts_UserPermissions($method, $login, $user, $expected_error) {
		$result = $this->api_call_with_user($method, $login, $user, $debug);

		$this->assertFalse(array_key_exists('result', $result));
		$this->assertTrue(array_key_exists('error', $result));

		$this->assertEquals($expected_error, $result['error']['data']);
	}
}

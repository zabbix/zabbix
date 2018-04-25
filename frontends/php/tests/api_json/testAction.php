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

class testAction extends CZabbixTest {

	public function testActions_backup() {
		DBsave_tables('actions');
	}

	public static function getActionUserPermissionsData() {
		return [
			[
				'login' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'action' => ['16'],
				'success_expected' => false,
			],
			[
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'action' => ['16'],
				'success_expected' => false,
			],
			[
				'login' => ['user' => 'guest', 'password' => ''],
				'action' => ['16'],
				'success_expected' => false
			],
			[
				'login' => ['user' => 'guest', 'password' => ''],
				'action' => ['18'],
				'success_expected' => true
			]
		];
	}

	/**
	 * @dataProvider getActionUserPermissionsData
	 */
	public function testCorrelationDelete_Permissions($login, $user, $success_expected) {
		$actions = 'SELECT * FROM actions ORDER BY actionid';
		$old_actions=DBhash($actions);
		$result = $this->api_call_with_user('action.delete', $login, $user, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['actionids'] as $id) {
				$dbResult = 'SELECT * FROM actions WHERE actionid='.$id;
				$this->assertEquals(0, DBcount($dbResult));
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));
			$new_actions=DBhash($actions);
			$this->assertEquals($old_actions, $new_actions);
			$this->assertEquals('No permissions to referred object or it does not exist!', $result['error']['data']);
		}
	}

	public static function getActionDeleteData() {
		return [
			// Check action id validation.
			[
				'action' => [''],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'action' => ['abc'],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'action' => ['1.1'],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'action' => ['123456'],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'action' => ['99000', '99000'],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (99000) already exists.'
			],
			[
				'action' => ['99000', 'abcd'],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'action' => ['99000'],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Successfully delete action.
			// Trigger action.
			[
				'action' => ['17'],
				'success_expected' => true,
				'expected_error' => null
			],
			// Discovery action
			[
				'action' => ['90'],
				'success_expected' => true,
				'expected_error' => null
			],
			// Auto registration action
			[
				'action' => ['91'],
				'success_expected' => true,
				'expected_error' => null
			],
			// Internal action
			[
				'action' => ['6'],
				'success_expected' => true,
				'expected_error' => null
			],
			// Delete action with guest user
			[
				'action' => ['16'],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider getActionDeleteData
	*/
	public function testAction_Delete($action, $success_expected, $expected_error) {
		$result = $this->api_acall('action.delete', $action, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['actionids'] as $id) {
				$dbResult = 'SELECT * FROM actions WHERE actionid='.$id;
				$this->assertEquals(0, DBcount($dbResult));
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertEquals($expected_error, $result['error']['data']);
		}
	}

		public function testActions_restore() {
		DBrestore_tables('actions');
	}
}

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

/**
 * @backup actions
 */
class testAction extends CAPITest {

	public static function getActionDeleteData() {
		return [
			[
				'action' => [],
				'expected_error' => 'Invalid parameter "/": cannot be empty.'
			],
			// Check action id validation.
			[
				'action' => [''],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'action' => ['abc'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'action' => ['1.1'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'action' => ['123456'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'action' => ['17', '17'],
				'expected_error' => 'Invalid parameter "/2": value (17) already exists.'
			],
			[
				'action' => ['17', 'abcd'],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			// Successfully delete action.
			// Trigger action.
			[
				'action' => ['17'],
				'expected_error' => null
			],
			// Discovery action
			[
				'action' => ['90'],
				'expected_error' => null
			],
			// Autoregistration action
			[
				'action' => ['91'],
				'expected_error' => null
			],
			// Internal action
			[
				'action' => ['6'],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider getActionDeleteData
	*/
	public function testAction_Delete($action, $expected_error) {
		$result = $this->call('action.delete', $action, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['actionids'] as $id) {
				$dbResult = 'SELECT * FROM actions WHERE actionid='.$id;
				$this->assertEquals(0, CDBHelper::getCount($dbResult));
			}
		}
	}

	public static function getActionUserPermissionsData() {
		return [
			[
				'login' => ['user' => 'action-user', 'password' => 'zabbix'],
				'action' => ['16'],
				'expected_error' => 'No permissions to call "action.delete".'
			],
			[
				'login' => ['user' => 'action-admin', 'password' => 'zabbix'],
				'action' => ['16'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'login' => ['user' => 'action-admin', 'password' => 'zabbix'],
				'action' => ['93'],
				'expected_error' => null
			],
			[
				'login' => ['user' => 'guest', 'password' => ''],
				'action' => ['16'],
				'expected_error' => 'No permissions to call "action.delete".'
			]
		];
	}

	/**
	 * @onBefore removeGuestFromDisabledGroup
	 * @onAfter addGuestToDisabledGroup
	 *
	 * @dataProvider getActionUserPermissionsData
	 */
	public function testAction_Permissions($login, $action, $expected_error) {
		$actions = 'SELECT * FROM actions ORDER BY actionid';
		$old_actions=CDBHelper::getHash($actions);

		$this->authorize($login['user'], $login['password']);
		$result = $this->call('action.delete', $action, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['actionids'] as $id) {
				$dbResult = 'SELECT * FROM actions WHERE actionid='.$id;
				$this->assertEquals(0, CDBHelper::getCount($dbResult));
			}
		}
		else {
			$this->assertEquals($old_actions, CDBHelper::getHash($actions));
		}
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

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

/**
 * @backup drules
 */
class testDRule extends CZabbixTest {

	public static function getDRuleDeleteData() {
		return [
			[
				'drule' => [],
				'expected_error' => 'Invalid parameter "/": cannot be empty.'
			],
			// Check action id validation.
			[
				'drule' => [''],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'drule' => ['abc'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'drule' => ['1.1'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'drule' => ['123456'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'drule' => ['3', '3'],
				'expected_error' => 'Invalid parameter "/2": value (3) already exists.'
			],
			[
				'drule' => ['3', 'abcd'],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'drule' => [9],
				'expected_error' => 'Discovery rule "API discovery rule used in action" is used in "API action for Discovery check" action.'
			],
			// Successfully delete action.
			// Discovery rule without proxy
			[
				'drule' => ['3'],
				'expected_error' => null
			],
			// Discovery rule with proxy
			[
				'drule' => ['4'],
				'expected_error' => null
			],
			// Delete two Discovery rules
			[
				'drule' => ['5','6'],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider getDRuleDeleteData
	*/
	public function testDRule_Delete($drule, $expected_error) {
		$result = $this->call('drule.delete', $drule, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['druleids'] as $id) {
				$dbResult = 'SELECT * FROM drules WHERE druleid='.$id;
				$this->assertEquals(0, DBcount($dbResult));
			}
		}
	}

	public static function getActionUserPermissionsData() {
		return [
			[
				'login' => ['user' => 'action-user', 'password' => 'zabbix'],
				'action' => ['7'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'login' => ['user' => 'action-admin', 'password' => 'zabbix'],
				'action' => ['1'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'login' => ['user' => 'action-admin', 'password' => 'zabbix'],
				'action' => ['7'],
				'expected_error' => null
			],
			[
				'login' => ['user' => 'guest', 'password' => ''],
				'action' => ['8'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
		];
	}

	/**
	 * @dataProvider getActionUserPermissionsData
	 */
	public function testAction_Permissions($login, $action, $expected_error) {
		$drule = 'SELECT * FROM drules ORDER BY actionid';
		$old_drule=DBhash($drule);

		$this->authorize($login['user'], $login['password']);
		$result = $this->call('drule.delete', $action, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['druleids'] as $id) {
				$dbResult = 'SELECT * FROM drules WHERE druleid='.$id;
				$this->assertEquals(0, DBcount($dbResult));
			}
		}
		else {
			$this->assertEquals($old_drule, DBhash($drule));
		}
	}
}

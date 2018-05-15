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
				'drule' => ['10', '10'],
				'expected_error' => 'Invalid parameter "/2": value (10) already exists.'
			],
			[
				'drule' => ['10', 'abcd'],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'drule' => ['15'],
				'expected_error' => 'Discovery rule "API discovery rule used in action" is used in "API action for Discovery check" action.'
			],
			// Successfully delete action.
			// Discovery rule without proxy
			[
				'drule' => ['10'],
				'expected_error' => null
			],
			// Discovery rule with proxy
			[
				'drule' => ['11'],
				'expected_error' => null
			],
			// Delete two Discovery rules
			[
				'drule' => ['12','13'],
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
				$this->assertEquals(0, DBcount('SELECT NULL FROM drules WHERE druleid='.zbx_dbstr($id)));
			}
		}
	}

	public static function getDRuleUserPermissionsData() {
		return [
			[
				'login' => ['user' => 'guest', 'password' => ''],
				'drule' => ['14'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'login' => ['user' => 'action-user', 'password' => 'zabbix'],
				'drule' => ['14'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'login' => ['user' => 'action-admin', 'password' => 'zabbix'],
				'drule' => ['1'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'login' => ['user' => 'action-admin', 'password' => 'zabbix'],
				'drule' => ['14'],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider getDRuleUserPermissionsData
	 */
	public function testDRule_Permissions($login, $drule, $expected_error) {
		$sql = 'SELECT * FROM drules ORDER BY actionid';
		$old_drule=DBhash($sql);

		$this->authorize($login['user'], $login['password']);
		$result = $this->call('drule.delete', $drule, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['druleids'] as $id) {
				$this->assertEquals(0, DBcount('SELECT * FROM drules WHERE druleid='.zbx_dbstr($id)));
			}
		}
		else {
			$this->assertEquals($old_drule, DBhash($sql));
		}
	}
}

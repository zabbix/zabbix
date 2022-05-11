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


require_once dirname(__FILE__) . '/../include/CAPITest.php';

/**
 * @backup correlation
 */
class testCorrelation extends CAPITest {

	public static function getCorrelationDeleteData() {
		return [
			// Check correlation id validation.
			[
				'correlation' => [''],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'correlation' => ['abc'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'correlation' => ['1.1'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'correlation' => ['123456'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'correlation' => ['99000', '99000'],
				'expected_error' => 'Invalid parameter "/2": value (99000) already exists.'
			],
			[
				'correlation' => ['99000', 'abcd'],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'correlation' => ['99000', '91234567'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'correlation' => ['99000', ''],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			// Successfully delete correlation.
			[
				'correlation' => ['99000'],
				'expected_error' => null
			],
			[
				'correlation' => ['99001', '99002'],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider getCorrelationDeleteData
	 */
	public function testCorrelation_Delete($correlation, $expected_error) {
		$result = $this->call('correlation.delete', $correlation, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['correlationids'] as $id) {
				$this->assertEquals(0, CDBHelper::getCount('SELECT NULL from correlation where correlationid='. $id));
			}
		}
	}

	public static function getCorrelationDeletePermissionsData() {
		return [
			[
				'login' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'correlation' => ['99003'],
				'expected_error'=> 'No permissions to call "correlation.delete".'
			],
			[
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'correlation' => ['99003'],
				'expected_error'=> 'No permissions to call "correlation.delete".'
			],
			[
				'login' => ['user' => 'guest', 'password' => ''],
				'correlation' => ['99003'],
				'expected_error'=> 'No permissions to call "correlation.delete".'
			]
		];
	}

	/**
	 * @onBefore removeGuestFromDisabledGroup
	 * @onAfter addGuestToDisabledGroup
	 *
	 * @dataProvider getCorrelationDeletePermissionsData
	 */
	public function testCorrelation_DeletePermissions($login, $correlation, $expected_error) {
		$sql_correlation = 'SELECT * FROM correlation ORDER BY correlationid';
		$old_hash_correlation = CDBHelper::getHash($sql_correlation);

		$this->authorize($login['user'], $login['password']);
		$this->call('correlation.delete', $correlation, $expected_error);

		$this->assertEquals($old_hash_correlation, CDBHelper::getHash($sql_correlation));
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

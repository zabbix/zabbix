<?php

/*
 * * Zabbix
 * * Copyright (C) 2001-2018 Zabbix SIA
 * *
 * * This program is free software; you can redistribute it and/or modify
 * * it under the terms of the GNU General Public License as published by
 * * the Free Software Foundation; either version 2 of the License, or
 * * (at your option) any later version.
 * *
 * * This program is distributed in the hope that it will be useful,
 * * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * * GNU General Public License for more details.
 * *
 * * You should have received a copy of the GNU General Public License
 * * along with this program; if not, write to the Free Software
 * * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 * */

require_once dirname(__FILE__) . '/../include/class.czabbixtest.php';

class testCorrelation extends CZabbixTest {

	public function testUsers_backup() {
		DBsave_tables('correlation');
	}

	public static function delete_permissions() {
		return [
			[
				'login' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'correlation' => ['99000']
			],
			[
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'correlation' => ['99000']
			],
			[
				'login' => ['user' => 'guest', 'password' => ''],
				'correlation' => ['99000']
			]
		];
	}

	/**
	 * @dataProvider delete_permissions
	 */
	public function testCorrelationDelete_Permissions($login, $user) {
		$result = $this->api_call_with_user('correlation.delete', $login, $user, $debug);

		if (false) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['correlation ids'] as $id) {
				$dbResult = 'select * from correlation where correlationid=' . $id;
				$this->assertEquals(1, DBcount($dbResult));
			}
		} else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertEquals('You do not have permission to perform this operation.', $result['error']['data']);
		}
	}

	public static function correlation_delete() {
		return [
			// Check correlation id validation.
			[
				'correlation' => [''],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'correlation' => ['abc'],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'correlation' => ['1.1'],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'correlation' => ['123456'],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'correlation' => ['99000', '99000'],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (99000) already exists.'
			],
			[
				'correlation' => ['99000', 'abcd'],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'correlation' => ['99000', '91234567'],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'correlation' => ['99000', ''],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			// Successfully delete correlation.
			[
				'correlation' => ['99000'],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'correlation' => ['99001', '99002'],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider correlation_delete
	 */
	public function testCorrelation_Delete($correlation, $success_expected, $expected_error) {
		$result = $this->api_acall('correlation.delete', $correlation, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['correlationids'] as $id) {
				$dbResult = 'select * from correlation where correlationid=' . $id;
				$this->assertEquals(0, DBcount($dbResult));
			}
		} else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertEquals($expected_error, $result['error']['data']);
		}
	}

	public function testCorrelation_restore() {
		DBrestore_tables('correlation');
	}
}

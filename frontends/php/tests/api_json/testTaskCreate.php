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

class testTaskCreate extends CZabbixTest {

	public static function tasks() {
		return [
			[
				'task' => [
					'type' => '6',
					'itemids' => ['40068'],
					'flag' => true
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/": unexpected parameter "flag".'
			],
			// Check type validation
			[
				'task' => [
					'itemids' => ['40068']
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/": the parameter "type" is missing.'
			],
			[
				'task' => [
					'type' => '',
					'itemids' => ['40068']
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/type": a number is expected.'
			],
			[
				'task' => [
					'type' => 'æų',
					'itemids' => ['40068']
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/type": a number is expected.'
			],
			[
				'task' => [
					'type' => '1',
					'itemids' => ['40068']
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/type": value must be one of 6.'
			],
			// Check itemids validation
			[
				'task' => [
					'type' => '6'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/": the parameter "itemids" is missing.'
			],
			[
				'task' => [
					'type' => '6',
					'itemids' => ''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/itemids": an array is expected.'
			],
			[
				'task' => [
					'type' => '6',
					'itemids' => ['']
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/itemids/1": a number is expected.'
			],
			[
				'task' => [
					'type' => '6',
					'itemids' => ['123456']
				],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// One itemid correct, one wrong
			[
				'task' => [
					'type' => '6',
					'itemids' => ['40068', '']
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/itemids/2": a number is expected.'
			],
			[
				'task' => [
					'type' => '6',
					'itemids' => ['40068', '123456']
				],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Host disabled (check item, LLD rule)
			[
				'task' => [
					'type' => '6',
					'itemids' => ['90002']
				],
				'success_expected' => false,
				'expected_error' => 'Cannot send request: host is not monitored.'
			],
			[
				'task' => [
					'type' => '6',
					'itemids' => ['90003']
				],
				'success_expected' => false,
				'expected_error' => 'Cannot send request: host is not monitored.'
			],
			[
				'task' => [
					'type' => '6',
					'itemids' => ['23287', '90002']
				],
				'success_expected' => false,
				'expected_error' => 'Cannot send request: host is not monitored.'
			],
			// Item disabled
			[
				'task' => [
					'type' => '6',
					'itemids' => ['90000']
				],
				'success_expected' => false,
				'expected_error' => 'Cannot send request: item is disabled.'
			],
			[
				'task' => [
					'type' => '6',
					'itemids' => ['23287', '90000']
				],
				'success_expected' => false,
				'expected_error' => 'Cannot send request: item is disabled.'
			],
			// LLD rule disabled
			[
				'task' => [
					'type' => '6',
					'itemids' => ['90001']
				],
				'success_expected' => false,
				'expected_error' => 'Cannot send request: discovery rule is disabled.'
			],
			[
				'task' => [
					'type' => '6',
					'itemids' => ['23287', '90001']
				],
				'success_expected' => false,
				'expected_error' => 'Cannot send request: discovery rule is disabled.'
			],
			[
				'task' => [
					'type' => '6',
					'itemids' => ['23279', '90001']
				],
				'success_expected' => false,
				'expected_error' => 'Cannot send request: discovery rule is disabled.'
			],
			// Success item check now
			[
				'task' => [
					'type' => '6',
					'itemids' => ['90004']
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'task' => [
					'type' => '6',
					'itemids' => ['90004', '23287']
				],
				'success_expected' => true,
				'expected_error' => null
			],
			// Success LLD rule check now
			[
				'task' => [
					'type' => '6',
					'itemids' => ['23279']
				],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	 * Test parameters validation, disabled host/item/lld rule and successful scenarios
	 *
	 * @dataProvider tasks
	 */
	public function testTaskCreate_CheckNow($task, $success_expected, $expected_error) {
		$sqlTask = "select NULL from task_check_now";
		$oldHashTasks = DBhash($sqlTask);

		$result = $this->api_acall('task.create', $task, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['taskids'] as $key => $id) {
				$dbResult = DBSelect('select * from task_check_now where taskid='.$id);
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['itemid'], $task['itemids'][$key]);
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertSame($expected_error, $result['error']['data']);
			$this->assertEquals($oldHashTasks, DBhash($sqlTask));
		}
	}

	public static function types() {
		return [
			// Item type: Zabbix agent (active)
			[
				'method' => 'item.update',
				'item' => [
					'itemid' => '90004',
					'type' => '7'
				],
				'expected_error' => 'Cannot send request: wrong item type.'
			],
			// Item type: SNMP trap
			[
				'method' => 'item.update',
				'item' => [
					'itemid' => '90004',
					'type' => '17',
					'key_' => 'snmptrap.fallback',
					'interfaceid' => '99004'
				],
				'expected_error' => 'Cannot send request: wrong item type.'
			],
			// Item type: Zabbix trapper
			[
				'method' => 'item.update',
				'item' => [
					'itemid' => '90004',
					'type' => '2'
				],
				'expected_error' => 'Cannot send request: wrong item type.'
			],
			// Item type: Dependent item
			[
				'method' => 'item.update',
				'item' => [
					'itemid' => '90004',
					'type' => '18',
					'master_itemid' => '23287',
				],
				'expected_error' => 'Cannot send request: wrong item type.'
			],
			// LLD rule type: Zabbix agent (active)
			[
				'method' => 'discoveryrule.update',
				'lld' => [
					'itemid' => '90005',
					'type' => '7'
				],
				'expected_error' => 'Cannot send request: wrong discovery rule type.'
			],
			// LLD rule type: Zabbix trapper
			[
				'method' => 'discoveryrule.update',
				'lld' => [
					'itemid' => '90005',
					'type' => '2'
				],
				'expected_error' => 'Cannot send request: wrong discovery rule type.'
			]
		];
	}

	/**
	 * Test item/lld rule types that not allow "check now" functionality
	 *
	 * @dataProvider types
	 */
	public function testTaskCreate_DifferentItemTypes($method, $object, $expected_error) {
		$sqlTask = "select NULL from task_check_now";
		$oldHashTasks = DBhash($sqlTask);

		// Change item/LLD rule type to not allowed for check now
		$resultItem = $this->api_acall($method, $object, $debug);
		$this->assertTrue(array_key_exists('result', $resultItem));
		$this->assertFalse(array_key_exists('error', $resultItem));

		// Create task for check now
		$task = [
			'type' => '6',
			'itemids' => [$object['itemid']],
		];
		$resultTask = $this->api_acall('task.create', $task, $debug);

		$this->assertFalse(array_key_exists('result', $resultTask));
		$this->assertTrue(array_key_exists('error', $resultTask));

		$this->assertSame($expected_error, $resultTask['error']['data']);
		$this->assertEquals($oldHashTasks, DBhash($sqlTask));
	}

	public static function user_permissions() {
		return [
			[
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'task' => [
						'type' => '6',
						'itemids' => ['23287']
					],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'task' => [
						'type' => '6',
						'itemids' => ['23279']
					],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'task' => [
						'type' => '6',
						'itemids' => ['23287']
					],
				'expected_error' => 'You do not have permission to perform this operation.'
			],
			[
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'task' => [
						'type' => '6',
						'itemids' => ['23279']
					],
				'expected_error' => 'You do not have permission to perform this operation.'
			]
		];
	}

	/**
	 * Test user permissions on "check now" functionality
	 *
	 * @dataProvider user_permissions
	 */
	public function testTaskCreate_UserPermissions($user, $task, $expected_error) {
		$sqlTask = "select NULL from task_check_now";
		$oldHashTasks = DBhash($sqlTask);

		$result = $this->api_call_with_user('task.create', $user, $task, $debug);

		$this->assertFalse(array_key_exists('result', $result));
		$this->assertTrue(array_key_exists('error', $result));

		$this->assertEquals($expected_error, $result['error']['data']);
		$this->assertEquals($oldHashTasks, DBhash($sqlTask));
	}
}

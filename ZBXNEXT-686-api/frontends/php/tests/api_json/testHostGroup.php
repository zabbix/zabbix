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

class testHostGroup extends CZabbixTest {

	public function testHostGroup_backup() {
		DBsave_tables('groups');
	}

	public static function hostgroup_create_data() {
		return [
			[
				'hostgroup' => [
					'name' => '',
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'hostgroup' => [
					'name' => 'non existent parametr',
					'flags' => '4'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "flags".'
			],
			[
				'hostgroup' => [
					'name' => 'Templates',
				],
				'success_expected' => false,
				'expected_error' => 'Host group "Templates" already exists.'
			],
			[
				'hostgroup' => [
					[
						'name' => 'One host group with existing name',
					],
					[
						'name' => 'Templates',
					]
				],
				'success_expected' => false,
				'expected_error' => 'Host group "Templates" already exists.'
			],
			[
				'hostgroup' => [
					[
						'name' => 'Host groups with two identical name',
					],
					[
						'name' => 'Host groups with two identical name',
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (name)=(Host groups with two identical name) already exists.'
			],
			[
				'hostgroup' => [
					[
						'name' => 'Api host group create',
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'hostgroup' => [
					[
						'name' => 'Api host group create one',
					],
					[
						'name' => 'Api host group create two',
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'hostgroup' => [
					[
						'name' => 'АПИ хост группа УТФ-8'
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'hostgroup' => [
					[
						'name' => 'Api'
					],
					[
						'name' => 'Api/Nested'
					],
				],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider hostgroup_create_data
	*/
	public function testHostGroup_create($hostgroup, $success_expected, $expected_error) {
		$result = $this->api_acall('hostgroup.create', $hostgroup, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['groupids'] as $key => $id) {
				$dbResult = DBSelect('select * from groups where groupid='.$id);
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['name'], $hostgroup[$key]['name']);
				$this->assertEquals($dbRow['flags'], 0);
				$this->assertEquals($dbRow['internal'], 0);
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertSame($expected_error, $result['error']['data']);
		}
	}

	public static function hostgroup_update_data() {
		return [
			[
				'hostgroup' => [
					[
					'groupid' => '',
					'name' => 'empty groupid'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/groupid": a number is expected.'
			],
			[
				'hostgroup' => [
					[
					'groupid' => '50005',
					'name' => ''
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'hostgroup' => [
					[
					'groupid' => '123456',
					'name' => 'groupid with not existing id'
					]
				],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'hostgroup' => [
					[
					'groupid' => '50005',
					'name' => 'non existent parametr',
					'flags' => '4'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "flags".'
			],
			[
				'hostgroup' => [
					[
					'groupid' => 'abc',
					'name' => 'id not number'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/groupid": a number is expected.'
			],
			[
				'hostgroup' => [
					[
					'groupid' => '.',
					'name' => 'id not number'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/groupid": a number is expected.'
			],
			[
				'hostgroup' => [
					[
					'groupid' => '50005',
					'name' => 'Templates'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Host group "Templates" already exists.'
			],
			[
				'hostgroup' => [
					[
					'groupid' => '50005',
					'name' => 'Api update two host group with the same names'
					],
					[
					'groupid' => '50006',
					'name' => 'Api update two host group with the same names'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (name)=(Api update two host group with the same names) already exists.'
			],
			[
				'hostgroup' => [
					[
					'groupid' => '50005',
					'name' => 'update host group twice1'
					],
					[
					'groupid' => '50005',
					'name' => 'update host group twice2'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (groupid)=(50005) already exists.'
			],
			[
				'hostgroup' => [
					[
					'groupid' => '50015',
					'name' => 'Api updated discovered group'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Cannot update a discovered host group.'
			],
			[
				'hostgroup' => [
					[
					'groupid' => '50005',
					'name' => 'Api host group updated'
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'hostgroup' => [
					[
					'groupid' => '50006',
					'name' => 'Api internal host group updated'
					],
					[
					'groupid' => '50005',
					'name' => 'Апи УТФ-8 обновлённый'
					],
				],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider hostgroup_update_data
	*/
	public function testHostGroup_update($hostgroups, $success_expected, $expected_error) {
		$result = $this->api_acall('hostgroup.update', $hostgroups, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['groupids'] as $key => $id) {
				$dbResult = DBSelect('select * from groups where groupid='.$id);
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['name'], $hostgroups[$key]['name']);
				$this->assertEquals($dbRow['flags'], 0);
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertEquals($expected_error, $result['error']['data']);
			foreach ($hostgroups as $hostgroup) {
				if (isset($hostgroup['name'])){
					$dbResult = "select * from groups where groupid=".$hostgroup['groupid'].
							" and name='".$hostgroup['name']."'";
					$this->assertEquals(0, DBcount($dbResult));
				}
			}
		}
	}

	public static function hostgroup_delete_data() {
		return [
			[
				'hostgroup' => [
					''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'hostgroup' => [
					'123456'
				],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'hostgroup' => [
					'abc'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'hostgroup' => [
					'.'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'hostgroup' => [
					'50008',
					'123456'
				],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'hostgroup' => [
					'50008',
					'abc'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'hostgroup' => [
					'5008',
					''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'hostgroup' => [
					'50008',
					'50008'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (50008) already exists.'
			],
			[
				'hostgroup' => [
					'50007'
				],
				'success_expected' => false,
				'expected_error' => 'Host group "Api host group delete internal" is internal and can not be deleted.'
			],
			[
				'hostgroup' => [
					'50014'
				],
				'success_expected' => false,
				'expected_error' => 'Group "Api group for host prototype" cannot be deleted, because it is used by a host prototype.'
			],
			[
				'hostgroup' => [
					'50013'
				],
				'success_expected' => false,
				'expected_error' => 'Template "API Template" cannot be without host group.'
			],
			[
				'hostgroup' => [
					'50005',
					'50012'
				],
				'success_expected' => false,
				'expected_error' => 'Host "API Host" cannot be without host group.'
			],
			[
				'hostgroup' => [
					'50009'
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'hostgroup' => [
					'50010',
					'50011'
				],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider hostgroup_delete_data
	*/
	public function testHostGroup_delete($hostgroups, $success_expected, $expected_error) {
		$result = $this->api_acall('hostgroup.delete', $hostgroups, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['groupids'] as $id) {
				$dbResult = 'select * from groups where groupid='.$id;
				$this->assertEquals(0, DBcount($dbResult));
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertEquals($expected_error, $result['error']['data']);
		}
	}

	public static function hostgroup_user_data() {
		return [
			[
				'method' => 'hostgroup.create',
				'user' => ['user' => 'test-admin', 'password' => 'zabbix'],
				'hostgroup' => [
					'name' => 'Api host group create as admin user'
				],
				'expected_error' => 'Only Super Admins can create host groups.'
			],
			[
				'method' => 'hostgroup.update',
				'user' => ['user' => 'test-admin', 'password' => 'zabbix'],
				'hostgroup' => [
					'groupid' => '50005',
					'name' => 'Api host group update as admin user without peremissions'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'method' => 'hostgroup.delete',
				'user' => ['user' => 'test-admin', 'password' => 'zabbix'],
				'hostgroup' => [
					'50008'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'method' => 'hostgroup.create',
				'user' => ['user' => 'test-user', 'password' => 'zabbix'],
				'valuemap' => [
					'name' => 'Api host group create as zabbix user'
				],
				'expected_error' => 'Only Super Admins can create host groups.'
			]
		];
	}

	/**
	* @dataProvider hostgroup_user_data
	*/
	public function testHostGroup_user_permissions($method, $user, $hostgroups, $expected_error) {
		$result = $this->api_call_with_user($method, $user, $hostgroups, $debug);

		$this->assertFalse(array_key_exists('result', $result));
		$this->assertTrue(array_key_exists('error', $result));

		$this->assertEquals($expected_error, $result['error']['data']);
	}

	public function testHostGroup_restore() {
		DBrestore_tables('groups');
	}
}

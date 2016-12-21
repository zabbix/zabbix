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

class testUserGroup extends CZabbixTest {

	public function testUserGroup_backup() {
		DBsave_tables('usrgrp');
	}

	public static function usergroup_create() {
		return [
			// Check user group name.
			[
				'group' => [
					'name' => '',
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'group' => [
					'name' => 'Zabbix administrators'
				],
				'success_expected' => false,
				'expected_error' => 'User group "Zabbix administrators" already exists.'
			],
			[
				'group' => [
					[
					'name' => 'One user group with existing name',
					],
					[
					'name' => 'Zabbix administrators',
					]
				],
				'success_expected' => false,
				'expected_error' => 'User group "Zabbix administrators" already exists.'
			],
			[
				'group' => [
					[
					'name' => 'User group with two identical name',
					],
					[
					'name' => 'User group with two identical name',
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (name)=(User group with two identical name) already exists.'
			],
			[
				'group' => [
					'name' => 'non existent parametr',
					'usrgrpid' => '7'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "usrgrpid".'
			],
			// Check user group settings.
			[
				'group' => [
					'name' => 'gui_access non existent value',
					'gui_access' => 3,
					'users_status' => 0,
					'debug_mode' => 0
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/gui_access": value must be one of 0, 1, 2.'
			],
			[
				'group' => [
					'name' => 'gui_access not valid value',
					'gui_access' => '1.2',
					'users_status' => 0,
					'debug_mode' => 0
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/gui_access": a number is expected.'
			],
			[
				'group' => [
					'name' => 'users_status non existent value',
					'gui_access' => 0,
					'users_status' => 2,
					'debug_mode' => 0
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/users_status": value must be one of 0, 1.'
			],
			[
				'group' => [
					'name' => 'users_status not valid value',
					'gui_access' => 0,
					'users_status' => 'abc',
					'debug_mode' => 0
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/users_status": a number is expected.'
			],
			[
				'group' => [
					'name' => 'debug_mode non existent value',
					'gui_access' => 0,
					'users_status' => 0,
					'debug_mode' => 2
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/debug_mode": value must be one of 0, 1.'
			],
			[
				'group' => [
					'name' => 'debug_mode not valid value',
					'gui_access' => 0,
					'users_status' => 0,
					'debug_mode' => '0.1'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/debug_mode": a number is expected.'
			],
			// Check group users.
			[
				'group' => [
					'name' => 'Admin in group with disabled GUI access',
					'gui_access' => 2,
					'userids' => 1
				],
				'success_expected' => false,
				'expected_error' => 'User cannot add himself to a disabled group or a group with disabled GUI access.'
			],
			[
				'group' => [
					'name' => 'Admin in disabled group',
					'users_status' => 1,
					'userids' => 1
				],
				'success_expected' => false,
				'expected_error' => 'User cannot add himself to a disabled group or a group with disabled GUI access.'
			],
			[
				'group' => [
					'name' => 'Non existent user',
					'userids' => '123456'
				],
				'success_expected' => false,
				'expected_error' => 'User with ID "123456" is not available.'
			],
			[
				'group' => [
					'name' => 'Non existent user, string',
					'userids' => 'abc'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/userids": an array is expected.'
			],
			[
				'group' => [
					'name' => 'Empty user id',
					'userids' => ''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/userids": an array is expected.'
			],
			// Check user group rights, host group id.
			[
				'group' => [
					'name' => 'Check rights, without host group id',
					'rights' => [
						'permission' => '0'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/rights/1": the parameter "id" is missing.'
			],
			[
				'group' => [
					'name' => 'Check rights, with empty host group id',
					'rights' => [
						'id' => '',
						'permission' => '0'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/rights/1/id": a number is expected.'
			],
			[
				'group' => [
					'name' => 'Check rights, id not number',
					'rights' => [
						'id' => 'abc',
						'permission' => '0'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/rights/1/id": a number is expected.'
			],
			[
				'group' => [
					'name' => 'Check rights, id not valid',
					'rights' => [
						'id' => '1.1',
						'permission' => '0'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/rights/1/id": a number is expected.'
			],
			[
				'group' => [
					'name' => 'Check rights, non existen host group id',
					'rights' => [
						'id' => '123456',
						'permission' => '0'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Host group with ID "123456" is not available.'
			],
			[
				'group' => [
					'name' => 'Check rights, unexpected parameter',
					'rights' => [
						'id' => '4',
						'permission' => '0',
						'usrgrpid' => '7'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/rights/1": unexpected parameter "usrgrpid".'
			],
			// Check user group rights, host group permission.
			[
				'group' => [
					'name' => 'Check rights, without permission',
					'rights' => [
						'id' => '4',
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/rights/1": the parameter "permission" is missing.'
			],
			[
				'group' => [
					'name' => 'Check rights, with empty permission',
					'rights' => [
						'id' => '4',
						'permission' => '',
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/rights/1/permission": a number is expected.'
			],
			[
				'group' => [
					'name' => 'Check rights, permission not valid number',
					'rights' => [
						'id' => '4',
						'permission' => '1.1',
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/rights/1/permission": a number is expected.'
			],
			[
				'group' => [
					'name' => 'Check rights, incorrect permission value',
					'rights' => [
						'id' => '4',
						'permission' => '1',
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/rights/1/permission": value must be one of 0, 2, 3.'
			],
			// Check successfully creation of user group.
			[
				'group' => [
					[
					'name' => 'Api user group create one',
					],
					[
					'name' => 'Api user group create two',
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'group' => [
					[
					'name' => 'Апи группа УТФ-8',
					]
				],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider usergroup_create
	*/
	public function testUserGroup_SimpleCreate($group, $success_expected, $expected_error) {
		$result = $this->api_acall('usergroup.create', $group, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['usrgrpids'] as $key => $id) {
				$dbResult = DBSelect('select * from usrgrp where usrgrpid='.$id);
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['name'], $group[$key]['name']);
				$this->assertEquals($dbRow['gui_access'], 0);
				$this->assertEquals($dbRow['users_status'], 0);
				$this->assertEquals($dbRow['debug_mode'], 0);
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertSame($expected_error, $result['error']['data']);
		}
	}

	public function testUserGroup_FullCreate() {
		$group = [
					'name' => 'Api user group with users and rights',
					'gui_access' => 1,
					'users_status' => 1,
					'debug_mode' => 1,
					'rights' => [
						'id' => '50012',
						'permission' => '3',
					],
					'userids' => ['2', '8']
				];
		$result = $this->api_acall('usergroup.create', $group, $debug);

		$this->assertTrue(array_key_exists('result', $result));
		$this->assertFalse(array_key_exists('error', $result));

		$dbResult = DBSelect('select * from usrgrp where usrgrpid='.$result['result']['usrgrpids'][0]);
		$dbRow = DBFetch($dbResult);
		$this->assertEquals($dbRow['name'], $group['name']);
		$this->assertEquals($dbRow['gui_access'], $group['gui_access']);
		$this->assertEquals($dbRow['users_status'], $group['users_status']);
		$this->assertEquals($dbRow['debug_mode'], $group['debug_mode']);

		$sqlUsersGroups1 = "select * from users_groups where userid='".$group['userids'][0]."' and usrgrpid=".$result['result']['usrgrpids'][0];
		$sqlUsersGroups2 = "select * from users_groups where userid='".$group['userids'][1]."' and usrgrpid=".$result['result']['usrgrpids'][0];
		$this->assertEquals(1, DBcount($sqlUsersGroups1));
		$this->assertEquals(1, DBcount($sqlUsersGroups2));

		$dbRight = DBSelect('select * from rights where groupid='.$result['result']['usrgrpids'][0]);
		$dbRowRight = DBFetch($dbRight);
		$this->assertEquals($dbRowRight['id'], $group['rights']['id']);
		$this->assertEquals($dbRowRight['permission'], $group['rights']['permission']);
	}

	public static function usergroup_update() {
		return [
			// Check user group id.
			[
				'group' => [
					'name' => 'Api user group updated'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": the parameter "usrgrpid" is missing.'
			],
			[
				'group' => [
					'usrgrpid' => '',
					'name' => 'Api user group udated'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			[
				'group' => [
					'usrgrpid' => '123456',
					'name' => 'Api user group udated'
				],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'group' => [
					'usrgrpid' => 'abc',
					'name' => 'Api user group updated'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			[
				'group' => [
					'usrgrpid' => '1.1',
					'name' => 'Api user group udated'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			// Check user group name.
			[
				'group' => [
					'usrgrpid' => '13',
					'name' => ''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'group' => [
					'usrgrpid' => '13',
					'name' => 'Zabbix administrators'
				],
				'success_expected' => false,
				'expected_error' => 'User group "Zabbix administrators" already exists.'
			],
			[
				'valuemap' => [
					'usrgrpid' => '13',
					'name' => 'Api update with non existent parametr',
					'value' => '4'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "value".'
			],
			// Check user group settings.
			[
				'group' => [
					'usrgrpid' => '13',
					'gui_access' => 3,
					'users_status' => 0,
					'debug_mode' => 0
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/gui_access": value must be one of 0, 1, 2.'
			],
			[
				'group' => [
					'usrgrpid' => '13',
					'name' => 'gui_access not valid value',
					'gui_access' => '1.2',
					'users_status' => 0,
					'debug_mode' => 0
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/gui_access": a number is expected.'
			],
			[
				'group' => [
					'usrgrpid' => '13',
					'name' => 'users_status non existent value',
					'gui_access' => 0,
					'users_status' => 2,
					'debug_mode' => 0
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/users_status": value must be one of 0, 1.'
			],
			[
				'group' => [
					'usrgrpid' => '13',
					'name' => 'users_status not valid value',
					'gui_access' => 0,
					'users_status' => 'abc',
					'debug_mode' => 0
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/users_status": a number is expected.'
			],
			[
				'group' => [
					'usrgrpid' => '13',
					'name' => 'debug_mode non existent value',
					'gui_access' => 0,
					'users_status' => 0,
					'debug_mode' => 2
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/debug_mode": value must be one of 0, 1.'
			],
			[
				'group' => [
					'usrgrpid' => '13',
					'name' => 'debug_mode not valid value',
					'gui_access' => 0,
					'users_status' => 0,
					'debug_mode' => '0.1'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/debug_mode": a number is expected.'
			],
			// Check group users.
			[
				'group' => [
					'usrgrpid' => '13',
					'name' => 'Api non existent user',
					'userids' => '123456'
				],
				'success_expected' => false,
				'expected_error' => 'User with ID "123456" is not available.'
			],
			[
				'group' => [
					'usrgrpid' => '13',
					'name' => 'Api non existent user, string',
					'userids' => 'abc'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/userids": an array is expected.'
			],
			[
				'group' => [
					'usrgrpid' => '13',
					'name' => 'Api empty user id',
					'userids' => ''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/userids": an array is expected.'
			],
			[
				'group' => [
					'name' => 'Disable group with admin',
					'usrgrpid' => '7',
					'users_status' => 1,
				],
				'success_expected' => false,
				'expected_error' => 'User cannot add himself to a disabled group or a group with disabled GUI access.'
			],
			[
				'group' => [
					'name' => 'Disable group GUI access with admin',
					'usrgrpid' => '7',
					'gui_access' => 2,
				],
				'success_expected' => false,
				'expected_error' => 'User cannot add himself to a disabled group or a group with disabled GUI access.'
			],
			[
				'group' => [
					'usrgrpid' => '14',
					'name' => 'Admin in group with disabled GUI access',
					'gui_access' => 2,
					'userids' => 1
				],
				'success_expected' => false,
				'expected_error' => 'User cannot add himself to a disabled group or a group with disabled GUI access.'
			],
			[
				'group' => [
					'usrgrpid' => '14',
					'name' => 'Admin in disabled group',
					'users_status' => 1,
					'userids' => 1
				],
				'success_expected' => false,
				'expected_error' => 'User cannot add himself to a disabled group or a group with disabled GUI access.'
			],
			[
				'group' => [
					'usrgrpid' => '15',
					'name' => 'User without user group',
					'userids' => 1
				],
				'success_expected' => false,
				'expected_error' => 'User "user-in-one-group" cannot be without user group.'
			],
			// Check user group rights, host group id.
			[
				'group' => [
					'usrgrpid' => '13',
					'name' => 'Check rights, without host group id',
					'rights' => [
						'permission' => '0'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/rights/1": the parameter "id" is missing.'
			],
			[
				'group' => [
					'usrgrpid' => '13',
					'name' => 'Check rights, with empty host group id',
					'rights' => [
						'id' => '',
						'permission' => '0'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/rights/1/id": a number is expected.'
			],
			[
				'group' => [
					'usrgrpid' => '13',
					'name' => 'Check rights, host group id not number',
					'rights' => [
						'id' => 'abc',
						'permission' => '0'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/rights/1/id": a number is expected.'
			],
			[
				'group' => [
					'usrgrpid' => '13',
					'name' => 'Check rights, host group id not valid',
					'rights' => [
						'id' => '1.1',
						'permission' => '0'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/rights/1/id": a number is expected.'
			],
			[
				'group' => [
					'usrgrpid' => '13',
					'name' => 'Check rights, non existen host group',
					'rights' => [
						'id' => '123456',
						'permission' => '0'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Host group with ID "123456" is not available.'
			],
			[
				'group' => [
					'usrgrpid' => '13',
					'name' => 'rights, unexpected parameter',
					'rights' => [
						'id' => '4',
						'permission' => '0',
						'usrgrpid' => '7'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/rights/1": unexpected parameter "usrgrpid".'
			],
			// Check user group rights, host group permission.
			[
				'group' => [
					'usrgrpid' => '13',
					'name' => 'Check rights, without permission',
					'rights' => [
						'id' => '4',
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/rights/1": the parameter "permission" is missing.'
			],
			[
				'group' => [
					'usrgrpid' => '13',
					'name' => 'Check rights, with empty permission',
					'rights' => [
						'id' => '4',
						'permission' => '',
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/rights/1/permission": a number is expected.'
			],
			[
				'group' => [
					'usrgrpid' => '13',
					'name' => 'Check rights, permission not valid number',
					'rights' => [
						'id' => '4',
						'permission' => '1.1',
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/rights/1/permission": a number is expected.'
			],
			[
				'group' => [
					'usrgrpid' => '13',
					'name' => 'Check rights, with incorrect permission value',
					'rights' => [
						'id' => '4',
						'permission' => '1',
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/rights/1/permission": value must be one of 0, 2, 3.'
			],
			// Check two user group for update.
			[
				'group' => [
					[
						'usrgrpid' => '13',
						'name' => 'User group with the same names'
					],
					[
						'usrgrpid' => '14',
						'name' => 'User group with the same names'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (name)=(User group with the same names) already exists.'
			],
			[
				'group' => [
					[
						'usrgrpid' => '13',
						'name' => 'Api user group with the same ids1',
					],
					[
						'usrgrpid' => '13',
						'name' => 'Api user group with the same ids2',
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (usrgrpid)=(13) already exists.'
			],
			// Check successfully update of user group.
			[
				'group' => [
					[
						'usrgrpid' => '13',
						'name' => 'Апи группа пользователей обновленна УТФ-8',
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'group' => [
					[
						'usrgrpid' => '14',
						'name' => 'Api user group updated with rights',
						'rights' =>[
							[
								'id' => '50013',
								'permission' => '2'
							]
						]
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'group' => [
					[
					'usrgrpid' => '13',
					'name' => 'Api update user group one',
						'rights' =>[
							[
								'id' => '50013',
								'permission' => '2'
							]
						]
					],
					[
					'usrgrpid' => '14',
					'name' => 'Api update user group two',
						'rights' =>[
							[
								'id' => '50012',
								'permission' => '0'
							]
						]
					]
				],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider usergroup_update
	*/
	public function testUserGroup_SimpleUpdate($group, $success_expected, $expected_error) {
		$result = $this->api_acall('usergroup.update', $group, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['usrgrpids'] as $key => $id) {
				$dbResult = DBSelect('select * from usrgrp where usrgrpid='.$id);
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['name'], $group[$key]['name']);
				$this->assertEquals($dbRow['gui_access'], 0);
				$this->assertEquals($dbRow['users_status'], 0);
				$this->assertEquals($dbRow['debug_mode'], 0);

				if (array_key_exists('rights', $group[$key])){
					foreach ($group[$key]['rights'] as $rights) {
						$dbRight = DBSelect('select * from rights where groupid='.$id);
						$dbRowRight = DBFetch($dbRight);
						$this->assertEquals($dbRowRight['id'], $rights['id']);
						$this->assertEquals($dbRowRight['permission'], $rights['permission']);
					}
				}
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertSame($expected_error, $result['error']['data']);
			if (array_key_exists('name', $group) && array_key_exists('usrgrpid', $group)){
				$dbResult = "select * from usrgrp where usrgrpid=".$group['usrgrpid'].
						" and name='".$group['name']."'";
				$this->assertEquals(0, DBcount($dbResult));
			}
		}
	}

	public function testUserGroup_UpdateUsersAndGroupProperty() {
		$group = [
					'usrgrpid' => '14',
					'name' => 'Api user group with users updated',
					'gui_access' => 1,
					'users_status' => 1,
					'debug_mode' => 1,
					'userids' => '2'
				];
		$result = $this->api_acall('usergroup.update', $group, $debug);

		$this->assertTrue(array_key_exists('result', $result));
		$this->assertFalse(array_key_exists('error', $result));

		$dbResult = DBSelect('select * from usrgrp where usrgrpid='.$result['result']['usrgrpids'][0]);
		$dbRow = DBFetch($dbResult);
		$this->assertEquals($dbRow['name'], $group['name']);
		$this->assertEquals($dbRow['gui_access'], $group['gui_access']);
		$this->assertEquals($dbRow['users_status'], $group['users_status']);
		$this->assertEquals($dbRow['debug_mode'], $group['debug_mode']);

		$sqlUsersGroup = "select * from users_groups where userid='".$group['userids']."' and usrgrpid=".$result['result']['usrgrpids'][0];
		$this->assertEquals(1, DBcount($sqlUsersGroup));
	}

	public static function usergroup_delete() {
		return [
			// Check user group id for one group.
			[
				'usergroup' => [''],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'usergroup' => ['123456'],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'usergroup' => ['abc'],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'usergroup' => ['1.1'],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			// Check user group id for two groups.
			[
				'usergroup' => ['16', '123456'],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'usergroup' => ['16', 'abc'],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'usergroup' => ['16', ''],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'usergroup' => ['16', '16'],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (16) already exists.'
			],
			// Check users without groups
			[
				'usergroup' => ['15'],
				'success_expected' => false,
				'expected_error' => 'User "user-in-one-group" cannot be without user group.'
			],
			[
				'usergroup' => ['16','17'],
				'success_expected' => false,
				'expected_error' => 'User "user-in-two-groups" cannot be without user group.'
			],
			// Check user group used in actions
			[
				'usergroup' => ['20'],
				'success_expected' => false,
				'expected_error' => 'User group "Api user group in actions" is used in "Api action" action.'
			],
			// Check user group used in scripts
			[
				'usergroup' => ['21'],
				'success_expected' => false,
				'expected_error' => 'User group "Api user group in scripts" is used in script "Api script".'
			],
			// Check user group used in configuration
			[
				'usergroup' => ['22'],
				'success_expected' => false,
				'expected_error' => 'User group "Api user group in configuration" is used in configuration for database down messages.'
			],
			// Check successfully delete of user group.
			[
				'usergroup' => ['17'],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'usergroup' => ['18', '19'],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider usergroup_delete
	*/
	public function testUserGroup_Delete($group, $success_expected, $expected_error) {
		$result = $this->api_acall('usergroup.delete', $group, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['usrgrpids'] as $id) {
				$dbResult = 'select * from usrgrp where usrgrpid='.$id;
				$this->assertEquals(0, DBcount($dbResult));
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertEquals($expected_error, $result['error']['data']);
		}
	}

	public static function usergroup_users() {
		return [
			[
				'method' => 'usergroup.create',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'usergroup' => ['name' => 'Api user group create as admin user'],
				'expected_error' => 'Only Super Admins can create user groups.'
			],
			[
				'method' => 'usergroup.update',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'usergroup' => [
					'usrgrpid' => '13',
					'name' => 'Api user group update as admin user without peremissions'
				],
				'expected_error' => 'Only Super Admins can update user groups.'
			],
			[
				'method' => 'usergroup.delete',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'usergroup' => ['16'],
				'expected_error' => 'Only Super Admins can delete user groups.'
			],
			[
				'method' => 'usergroup.create',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'usergroup' => ['name' => 'Api host group create as zabbix user'],
				'expected_error' => 'Only Super Admins can create user groups.'
			],
			[
				'method' => 'usergroup.update',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'usergroup' => [
					'usrgrpid' => '13',
					'name' => 'Api user group update as zabbix user without peremissions'
				],
				'expected_error' => 'Only Super Admins can update user groups.'
			],
			[
				'method' => 'usergroup.delete',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'usergroup' => ['16'],
				'expected_error' => 'Only Super Admins can delete user groups.'
			],
		];
	}

	/**
	* @dataProvider usergroup_users
	*/
	public function testUserGroup_UserPermissions($method, $user, $group, $expected_error) {
		$result = $this->api_call_with_user($method, $user, $group, $debug);

		$this->assertFalse(array_key_exists('result', $result));
		$this->assertTrue(array_key_exists('error', $result));

		$this->assertEquals($expected_error, $result['error']['data']);
	}

	public function testUserGroup_restore() {
		DBrestore_tables('usrgrp');
	}

}

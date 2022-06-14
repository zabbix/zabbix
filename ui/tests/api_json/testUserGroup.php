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
 * @backup usrgrp
 */
class testUserGroup extends CAPITest {

	public static function usergroup_create() {
		return [
			[
				'group' => [
					'name' => 'non existent parameter',
					'usrgrpid' => '7'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "usrgrpid".'
			],
			// Check user group name.
			[
				'group' => [
					'gui_access' => GROUP_GUI_ACCESS_SYSTEM
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "name" is missing.'
			],
			[
				'group' => [
					'name' => ''
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'group' => [
					'name' => 'Zabbix administrators'
				],
				'expected_error' => 'User group "Zabbix administrators" already exists.'
			],
			[
				'group' => [
					[
						'name' => 'One user group with existing name'
					],
					[
						'name' => 'Zabbix administrators'
					]
				],
				'expected_error' => 'User group "Zabbix administrators" already exists.'
			],
			[
				'group' => [
					[
						'name' => 'User group with two identical name'
					],
					[
						'name' => 'User group with two identical name'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (name)=(User group with two identical name) already exists.'
			],
			// Check Super Admin user in group.
			[
				'group' => [
					'name' => 'Admin in group with disabled GUI access',
					'gui_access' => GROUP_GUI_ACCESS_DISABLED,
					'users' => ['userid' => 1]
				],
				'expected_error' => 'User cannot add himself to a disabled group or a group with disabled GUI access.'
			],
			[
				'group' => [
					'name' => 'Admin in disabled group',
					'users_status' => 1,
					'users' => ['userid' => 1]
				],
				'expected_error' => 'User cannot add himself to a disabled group or a group with disabled GUI access.'
			],
			// Check successfully creation of user group.
			[
				'group' => [
					[
						'name' => 'API user group create one'
					],
					[
						'name' => 'æų'
					]
				],
				'expected_error' => null
			],
			[
				'group' => [
					[
						'name' => 'Апи группа УТФ-8'
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider usergroup_create
	*/
	public function testUserGroup_Create($group, $expected_error) {
		$result = $this->call('usergroup.create', $group, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['usrgrpids'] as $key => $id) {
				$dbResult = DBSelect('select * from usrgrp where usrgrpid='.zbx_dbstr($id));
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['name'], $group[$key]['name']);
				$this->assertEquals($dbRow['gui_access'], GROUP_GUI_ACCESS_SYSTEM);
				$this->assertEquals($dbRow['users_status'], 0);
				$this->assertEquals($dbRow['debug_mode'], 0);
			}
		}
	}

	public static function usergroup_update() {
		return [
			[
				'group' => [[
					'usrgrpid' => '13',
					'name' => 'API update with non existent parameter',
					'value' => '4'
				]],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "value".'
			],
			// Check user group id.
			[
				'group' => [[
					'name' => 'API user group updated'
				]],
				'expected_error' => 'Invalid parameter "/1": the parameter "usrgrpid" is missing.'
			],
			[
				'group' => [[
					'usrgrpid' => '',
					'name' => 'API user group udated'
				]],
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			[
				'group' => [[
					'usrgrpid' => '123456',
					'name' => 'API user group udated'
				]],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'group' => [[
					'usrgrpid' => 'abc',
					'name' => 'API user group updated'
				]],
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			[
				'group' => [[
					'usrgrpid' => '1.1',
					'name' => 'API user group udated'
				]],
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			// Check user group name.
			[
				'group' => [[
					'usrgrpid' => '13',
					'name' => ''
				]],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'group' => [[
					'usrgrpid' => '13',
					'name' => 'Zabbix administrators'
				]],
				'expected_error' => 'User group "Zabbix administrators" already exists.'
			],
			// Check Super Admin user in group.
			[
				'group' => [[
					'name' => 'Disable group with admin',
					'usrgrpid' => '7',
					'users_status' => 1
				]],
				'expected_error' => 'User cannot add himself to a disabled group or a group with disabled GUI access.'
			],
			[
				'group' => [[
					'name' => 'Disable group GUI access with admin',
					'usrgrpid' => '7',
					'gui_access' => GROUP_GUI_ACCESS_DISABLED
				]],
				'expected_error' => 'User cannot add himself to a disabled group or a group with disabled GUI access.'
			],
			[
				'group' => [[
					'usrgrpid' => '14',
					'name' => 'Admin in group with disabled GUI access',
					'gui_access' => GROUP_GUI_ACCESS_DISABLED,
					'users' => ['userid' => 1]
				]],
				'expected_error' => 'User cannot add himself to a disabled group or a group with disabled GUI access.'
			],
			[
				'group' => [[
					'usrgrpid' => '14',
					'name' => 'Admin in disabled group',
					'users_status' => 1,
					'users' => ['userid' => 1]
				]],
				'expected_error' => 'User cannot add himself to a disabled group or a group with disabled GUI access.'
			],
			[
				'group' => [[
					'usrgrpid' => '15',
					'name' => 'User without user group',
					'users' => ['userid' => 1]
				]],
				'expected_error' => 'User "user-in-one-group" cannot be without user group.'
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
				'expected_error' => 'Invalid parameter "/2": value (name)=(User group with the same names) already exists.'
			],
			[
				'group' => [
					[
						'usrgrpid' => '13',
						'name' => 'API user group with the same ids1'
					],
					[
						'usrgrpid' => '13',
						'name' => 'API user group with the same ids2'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (usrgrpid)=(13) already exists.'
			],
			// Check successfully update of user group.
			[
				'group' => [
					[
						'usrgrpid' => '13',
						'name' => 'Апи группа пользователей обновленна УТФ-8'
					]
				],
				'expected_error' => null
			],
			[
				'group' => [
					[
						'usrgrpid' => '14',
						'name' => 'API user group updated with rights',
						'rights' =>[
							[
								'id' => '50013',
								'permission' => '2'
							]
						]
					]
				],
				'expected_error' => null
			],
			[
				'group' => [
					[
					'usrgrpid' => '13',
					'name' => 'API update user group one',
						'rights' =>[
							[
								'id' => '50013',
								'permission' => '2'
							]
						]
					],
					[
					'usrgrpid' => '14',
					'name' => 'API update user group two',
						'rights' =>[
							[
								'id' => '50012',
								'permission' => '0'
							]
						]
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider usergroup_update
	*/
	public function testUserGroup_Update($groups, $expected_error) {
		$result = $this->call('usergroup.update', $groups, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['usrgrpids'] as $key => $id) {
				$dbResult = DBSelect('select * from usrgrp where usrgrpid='.zbx_dbstr($id));
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['name'], $groups[$key]['name']);
				$this->assertEquals($dbRow['gui_access'], GROUP_GUI_ACCESS_SYSTEM);
				$this->assertEquals($dbRow['users_status'], 0);
				$this->assertEquals($dbRow['debug_mode'], 0);

				if (array_key_exists('rights', $groups[$key])){
					foreach ($groups[$key]['rights'] as $rights) {
						$dbRight = DBSelect('select * from rights where groupid='.zbx_dbstr($id));
						$dbRowRight = DBFetch($dbRight);
						$this->assertEquals($dbRowRight['id'], $rights['id']);
						$this->assertEquals($dbRowRight['permission'], $rights['permission']);
					}
				}
			}
		}
		else {
			foreach ($groups as $group) {
				if (array_key_exists('name', $group) && $group['name'] != 'Zabbix administrators'){
					$dbResult = 'select * from usrgrp where name='.zbx_dbstr($group['name']);
					$this->assertEquals(0, CDBHelper::getCount($dbResult));
				}
			}
		}
	}

	public static function usergroup_properties() {
		return [
			// Check user group not required properties.
			[
				'group' => [
					'name' => 'gui_access non existent value',
					'gui_access' => 65535
				],
				'expected_error' => sprintf('Invalid parameter "/1/gui_access": value must be one of %s.',
					implode(', ', [
						GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_INTERNAL, GROUP_GUI_ACCESS_LDAP,
						GROUP_GUI_ACCESS_DISABLED
				]))
			],
			[
				'group' => [
					'name' => 'gui_access not valid value',
					'gui_access' => 1.2
				],
				'expected_error' => 'Invalid parameter "/1/gui_access": an integer is expected.'
			],
			[
				'group' => [
					'name' => 'users_status non existent value',
					'users_status' => 2
				],
				'expected_error' => 'Invalid parameter "/1/users_status": value must be one of 0, 1.'
			],
			[
				'group' => [
					'name' => 'users_status not valid value',
					'users_status' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/users_status": an integer is expected.'
			],
			[
				'group' => [
					'name' => 'debug_mode non existent value',
					'debug_mode' => 2
				],
				'expected_error' => 'Invalid parameter "/1/debug_mode": value must be one of 0, 1.'
			],
			[
				'group' => [
					'name' => 'debug_mode not valid value',
					'debug_mode' => 0.1
				],
				'expected_error' => 'Invalid parameter "/1/debug_mode": an integer is expected.'
			],
			// Check group users.
			[
				'group' => [
					'name' => 'Empty user id',
					'users' => ''
				],
				'expected_error' => 'Invalid parameter "/1/users": an array is expected.'
			],
			[
				'group' => [
					'name' => 'Empty user id',
					'users' => ['']
				],
				'expected_error' => 'Invalid parameter "/1/users/1": an array is expected.'
			],
			[
				'group' => [
					'name' => 'Non existent user',
					'users' => [
						'userid' => '123456'
					]
				],
				'expected_error' => 'User with ID "123456" is not available.'
			],
			// Check user group permissions, host group id.
			[
				'group' => [
					'name' => 'Check rights, without host group id',
					'rights' => [
						'permission' => '0'
					]
				],
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
				'expected_error' => 'Invalid parameter "/1/rights/1": unexpected parameter "usrgrpid".'
			],
			// Check user group permissions, host group permission.
			[
				'group' => [
					'name' => 'Check rights, without permission',
					'rights' => [
						'id' => '4'
					]
				],
				'expected_error' => 'Invalid parameter "/1/rights/1": the parameter "permission" is missing.'
			],
			[
				'group' => [
					'name' => 'Check rights, with empty permission',
					'rights' => [
						'id' => '4',
						'permission' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/rights/1/permission": an integer is expected.'
			],
			[
				'group' => [
					'name' => 'Check rights, permission not valid number',
					'rights' => [
						'id' => '4',
						'permission' => '1.1'
					]
				],
				'expected_error' => 'Invalid parameter "/1/rights/1/permission": an integer is expected.'
			],
			[
				'group' => [
					'name' => 'Check rights, incorrect permission value',
					'rights' => [
						'id' => '4',
						'permission' => '1'
					]
				],
				'expected_error' => 'Invalid parameter "/1/rights/1/permission": value must be one of 0, 2, 3.'
			],
			[
				'group' => [
					'name' => 'Check rights, incorrect permission value',
					'rights' => [
						'id' => '4',
						'permission' => '4'
					]
				],
				'expected_error' => 'Invalid parameter "/1/rights/1/permission": value must be one of 0, 2, 3.'
			]
		];
	}

	/**
	* @dataProvider usergroup_properties
	*/
	public function testUserGroups_Properties($group, $expected_error) {
		$methods = ['usergroup.create', 'usergroup.update'];

		foreach ($methods as $method) {
			if ($method == 'usergroup.update') {
				$group['usrgrpid'] = '13';
				$group['name'] = 'Updated '.$group['name'];
			}
			$result = $this->call($method, $group, $expected_error);

			if ($expected_error === null) {
				$db_group = CDBHelper::getRow(
					'SELECT * FROM usrgrp WHERE usrgrpid='.zbx_dbstr($result['result']['usrgrpids'][0])
				);
				$this->assertSame($group['name'], $db_group['name']);
				$this->assertEquals($group['gui_access'], $db_group['gui_access']);
				$this->assertEquals($group['users_status'], $db_group['users_status']);
				$this->assertEquals($group['debug_mode'], $db_group['debug_mode']);

				$this->assertEquals(count($group['users']), CDBHelper::getCount(
					'SELECT NULL'.
					' FROM users_groups'.
					' WHERE usrgrpid='.zbx_dbstr($result['result']['usrgrpids'][0])
				));

				$db_right = CDBHelper::getRow('SELECT * FROM rights WHERE groupid='.$result['result']['usrgrpids'][0]);
				$this->assertEquals($group['rights']['id'], $db_right['id']);
				$this->assertEquals($group['rights']['permission'], $db_right['permission']);
			}
			else {
				if (array_key_exists('name', $group) && array_key_exists('usrgrpid', $group)) {
					$this->assertEquals(0, CDBHelper::getCount(
						'SELECT NULL'.
						' FROM usrgrp'.
						' WHERE usrgrpid='.zbx_dbstr($group['usrgrpid']).
							' AND name='.zbx_dbstr($group['name'])
					));
				}
			}
		}
	}

	public static function usergroup_delete() {
		return [
			// Check user group id for one group.
			[
				'usergroup' => [''],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'usergroup' => ['123456'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'usergroup' => ['abc'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'usergroup' => ['1.1'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			// Check user group id for two groups.
			[
				'usergroup' => ['16', '123456'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'usergroup' => ['16', 'abc'],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'usergroup' => ['16', ''],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'usergroup' => ['16', '16'],
				'expected_error' => 'Invalid parameter "/2": value (16) already exists.'
			],
			// Check users without groups
			[
				'usergroup' => ['15'],
				'expected_error' => 'User "user-in-one-group" cannot be without user group.'
			],
			[
				'usergroup' => ['16','17'],
				'expected_error' => 'User "user-in-two-groups" cannot be without user group.'
			],
			// Check user group used in actions
			[
				'usergroup' => ['20'],
				'expected_error' => 'User group "API user group in actions" is used in "API action" action.'
			],
			// Check user group used in scripts
			[
				'usergroup' => ['21'],
				'expected_error' => 'User group "API user group in scripts" is used in script "API script".'
			],
			// Check user group used in configuration
			[
				'usergroup' => ['22'],
				'expected_error' => 'User group "API user group in configuration" is used in configuration for database down messages.'
			],
			// Check successfully delete of user group.
			[
				'usergroup' => ['17'],
				'expected_error' => null
			],
			[
				'usergroup' => ['18', '19'],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider usergroup_delete
	*/
	public function testUserGroup_Delete($group, $expected_error) {
		$result = $this->call('usergroup.delete', $group, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['usrgrpids'] as $id) {
				$dbResult = 'select * from usrgrp where usrgrpid='.zbx_dbstr($id);
				$this->assertEquals(0, CDBHelper::getCount($dbResult));
			}
		}
	}

	public static function usergroup_users() {
		return [
			[
				'method' => 'usergroup.create',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'usergroup' => ['name' => 'API user group create as admin user'],
				'expected_error' => 'No permissions to call "usergroup.create".'
			],
			[
				'method' => 'usergroup.update',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'usergroup' => [
					'usrgrpid' => '13',
					'name' => 'API user group update as admin user without peremissions'
				],
				'expected_error' => 'No permissions to call "usergroup.update".'
			],
			[
				'method' => 'usergroup.delete',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'usergroup' => ['16'],
				'expected_error' => 'No permissions to call "usergroup.delete".'
			],
			[
				'method' => 'usergroup.create',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'usergroup' => ['name' => 'API host group create as zabbix user'],
				'expected_error' => 'No permissions to call "usergroup.create".'
			],
			[
				'method' => 'usergroup.update',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'usergroup' => [
					'usrgrpid' => '13',
					'name' => 'API user group update as zabbix user without peremissions'
				],
				'expected_error' => 'No permissions to call "usergroup.update".'
			],
			[
				'method' => 'usergroup.delete',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'usergroup' => ['16'],
				'expected_error' => 'No permissions to call "usergroup.delete".'
			]
		];
	}

	/**
	* @dataProvider usergroup_users
	*/
	public function testUserGroup_UserPermissions($method, $user, $group, $expected_error) {
		$this->authorize($user['user'], $user['password']);
		$this->call($method, $group, $expected_error);
	}
}

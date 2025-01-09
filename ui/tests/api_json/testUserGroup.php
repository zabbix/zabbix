<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/../include/CAPITest.php';
require_once __DIR__.'/../include/helpers/CTestDataHelper.php';

/**
 * @onBefore  prepareTestData
 * @onAfter   cleanTestData
 *
 * @backup usrgrp, userdirectory, mfa
 */
class testUserGroup extends CAPITest {

	public static $data = [
		'usrgrpid' => [],
		'userdirectoryid' => [],
		'mfaid' => []
	];

	/**
	 * Create data to be used in tests.
	 */
	public function prepareTestData(): void {
		$response = CDataHelper::call('userdirectory.create', [[
			'name' => 'API LDAP #1',
			'idp_type' => IDP_TYPE_LDAP,
			'host' => 'ldap.forumsys.com',
			'port' => 389,
			'base_dn' => 'dc=example,dc=com',
			'search_attribute' => 'uid'
		]]);

		$this->assertArrayHasKey('userdirectoryids', $response);
		self::$data['userdirectoryid'] = array_combine(['API LDAP #1'], $response['userdirectoryids']);

		$mfa = CDataHelper::call('mfa.create', [[
			'type' => MFA_TYPE_TOTP,
			'name' => 'MFA TOTP method',
			'hash_function' => TOTP_HASH_SHA1,
			'code_length' => TOTP_CODE_LENGTH_8
		]]);

		$this->assertArrayHasKey('mfaids', $mfa);
		self::$data['mfaid'] = array_combine(['MFA TOTP method'], $mfa['mfaids']);

		// usergroup.update
		CTestDataHelper::createObjects([
			'user_groups' => [
				['name' => 'user group 1']
			],
			'roles' => [
				['name' => 'api admin role', 'type' => USER_TYPE_ZABBIX_ADMIN]
			],
			'users' => [
				[
					'username' => 'single_group_user',
					'roleid' => ':role:api admin role',
					'passwd' => 'zabbix123456',
					'usrgrps' => [
						['usrgrpid' => ':user_group:user group 1']
					]
				]
			]
		]);

		// usergroup.delete
		CTestDataHelper::createObjects([
			'user_groups' => [
				['name' => 'user group 2'],
				['name' => 'ldap provision group'],
				['name' => 'saml provision group']
			],
			'users' => [
				[
					'username' => 'usergroup_delete_single_group_user',
					'roleid' => ':role:api admin role',
					'passwd' => 'zabbix123456',
					'usrgrps' => [
						['usrgrpid' => ':user_group:user group 2']
					]
				]
			]
		]);
		CDataHelper::call('userdirectory.create', [
			[
				'name' => 'ldap provision',
				'idp_type' => IDP_TYPE_LDAP,
				'host' => 'provision',
				'port' => 389,
				'base_dn' => 'provision',
				'search_attribute' => 'provision',
				'provision_status' => JIT_PROVISIONING_ENABLED,
				'provision_groups' => [
					[
						'name' => '*',
						'roleid' => CTestDataHelper::getConvertedValueReference(':role:api admin role'),
						'user_groups' => [
							['usrgrpid' => CTestDataHelper::getConvertedValueReference(':user_group:ldap provision group')]
						]
					]
				]
			],
			[
				'idp_type' => IDP_TYPE_SAML,
				'idp_entityid' => 'provision',
				'sso_url' => 'http://127.0.0.1',
				'username_attribute' => 'provision',
				'group_name' => 'provision',
				'sp_entityid' => 'provision',
				'provision_status' => JIT_PROVISIONING_ENABLED,
				'provision_groups' => [
					[
						'name' => '*',
						'roleid' => CTestDataHelper::getConvertedValueReference(':role:api admin role'),
						'user_groups' => [
							['usrgrpid' => CTestDataHelper::getConvertedValueReference(':user_group:saml provision group')]
						]
					]
				]
			]
		]);
	}

	public static function cleanTestData(): void {
		CTestDataHelper::cleanUp();
	}

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
				'expected_error' => 'User cannot add oneself to a disabled group or a group with disabled GUI access.'
			],
			[
				'group' => [
					'name' => 'Admin in disabled group',
					'users_status' => 1,
					'users' => ['userid' => 1]
				],
				'expected_error' => 'User cannot add oneself to a disabled group or a group with disabled GUI access.'
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
			foreach ($result['result']['usrgrpids'] as $key => $usrgrpid) {
				$dbRow = CDBHelper::getRow(
					'SELECT name,gui_access,users_status,debug_mode'.
					' FROM usrgrp'.
					' WHERE usrgrpid='.$usrgrpid);
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
					'usrgrpid' => '23',
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
					'name' => 'API user group updated'
				]],
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			[
				'group' => [[
					'usrgrpid' => '123456',
					'name' => 'API user group updated'
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
					'name' => 'API user group updated'
				]],
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			// Check user group name.
			[
				'group' => [[
					'usrgrpid' => '23',
					'name' => ''
				]],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'group' => [[
					'usrgrpid' => '23',
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
				'expected_error' => 'User cannot add oneself to a disabled group or a group with disabled GUI access.'
			],
			[
				'group' => [[
					'name' => 'Disable group GUI access with admin',
					'usrgrpid' => '7',
					'gui_access' => GROUP_GUI_ACCESS_DISABLED
				]],
				'expected_error' => 'User cannot add oneself to a disabled group or a group with disabled GUI access.'
			],
			[
				'group' => [[
					'usrgrpid' => '14',
					'name' => 'Admin in group with disabled GUI access',
					'gui_access' => GROUP_GUI_ACCESS_DISABLED,
					'users' => ['userid' => 1]
				]],
				'expected_error' => 'User cannot add oneself to a disabled group or a group with disabled GUI access.'
			],
			[
				'group' => [[
					'usrgrpid' => '14',
					'name' => 'Admin in disabled group',
					'users_status' => 1,
					'users' => ['userid' => 1]
				]],
				'expected_error' => 'User cannot add oneself to a disabled group or a group with disabled GUI access.'
			],
			'Can remove user with one group from group users' => [
				'group' => [[
					'usrgrpid' => ':user_group:user group 1',
					'name' => 'User without user group',
					'users' => ['userid' => 1]
				]],
				'expected_error' => null
			],
			// Check two user group for update.
			[
				'group' => [
					[
						'usrgrpid' => '23',
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
						'usrgrpid' => '23',
						'name' => 'API user group with the same ids1'
					],
					[
						'usrgrpid' => '23',
						'name' => 'API user group with the same ids2'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (usrgrpid)=(23) already exists.'
			],
			// Check successfully update of user group.
			[
				'group' => [
					[
						'usrgrpid' => '23',
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
						'templategroup_rights' =>[
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
					'usrgrpid' => '23',
					'name' => 'API update user group one',
						'templategroup_rights' =>[
							[
								'id' => '50013',
								'permission' => '2'
							]
						]
					],
					[
					'usrgrpid' => '14',
					'name' => 'API update user group two',
						'hostgroup_rights' =>[
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
		CTestDataHelper::convertUserGroupReferences($groups);
		$result = $this->call('usergroup.update', $groups, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['usrgrpids'] as $key => $usrgrpid) {
				$db_usrgrp = CDBHelper::getRow(
					'SELECT name,gui_access,users_status,debug_mode'.
					' FROM usrgrp'.
					' WHERE usrgrpid='.$usrgrpid
				);
				$this->assertSame($db_usrgrp['name'], $groups[$key]['name']);
				$this->assertSame($db_usrgrp['gui_access'], (string) GROUP_GUI_ACCESS_SYSTEM);
				$this->assertSame($db_usrgrp['users_status'], '0');
				$this->assertSame($db_usrgrp['debug_mode'], '0');

				if (array_key_exists('hostgroup_rights', $groups[$key])){
					foreach ($groups[$key]['hostgroup_rights'] as $rights) {
						$db_right = CDBHelper::getRow(
							'SELECT r.id,r.permission'.
							' FROM rights r,hstgrp hg'.
							' WHERE r.id=hg.groupid'.
								' AND r.groupid='.$usrgrpid.
								' AND hg.type='.HOST_GROUP_TYPE_HOST_GROUP
						);
						$this->assertSame($db_right['id'], $rights['id']);
						$this->assertSame($db_right['permission'], $rights['permission']);
					}
				}

				if (array_key_exists('templategroup_rights', $groups[$key])){
					foreach ($groups[$key]['templategroup_rights'] as $rights) {
						$db_right = CDBHelper::getRow(
							'SELECT r.id,r.permission'.
							' FROM rights r,hstgrp hg'.
							' WHERE r.id=hg.groupid'.
								' AND r.groupid='.$usrgrpid.
								' AND hg.type='.HOST_GROUP_TYPE_TEMPLATE_GROUP
						);
						$this->assertSame($db_right['id'], $rights['id']);
						$this->assertSame($db_right['permission'], $rights['permission']);
					}
				}
			}
		}
		else {
			foreach ($groups as $group) {
				if (array_key_exists('name', $group) && $group['name'] != 'Zabbix administrators'){
					$this->assertEquals(0,
						CDBHelper::getCount('SELECT * FROM usrgrp WHERE name='.zbx_dbstr($group['name']))
					);
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
				'expected_error' => 'Invalid parameter "/1/users/1/userid": object does not exist.'
			],
			// Check user group permissions, host group id.
			[
				'group' => [
					'name' => 'Check rights, without host group id',
					'hostgroup_rights' => [
						'permission' => '0'
					]
				],
				'expected_error' => 'Invalid parameter "/1/hostgroup_rights/1": the parameter "id" is missing.'
			],
			[
				'group' => [
					'name' => 'Check rights, without host group id',
					'templategroup_rights' => [
						'permission' => '0'
					]
				],
				'expected_error' => 'Invalid parameter "/1/templategroup_rights/1": the parameter "id" is missing.'
			],
			[
				'group' => [
					'name' => 'Check rights, with empty host group id',
					'hostgroup_rights' => [
						'id' => '',
						'permission' => '0'
					]
				],
				'expected_error' => 'Invalid parameter "/1/hostgroup_rights/1/id": a number is expected.'
			],
			[
				'group' => [
					'name' => 'Check rights, with empty host group id',
					'templategroup_rights' => [
						'id' => '',
						'permission' => '0'
					]
				],
				'expected_error' => 'Invalid parameter "/1/templategroup_rights/1/id": a number is expected.'
			],
			[
				'group' => [
					'name' => 'Check rights, id not number',
					'hostgroup_rights' => [
						'id' => 'abc',
						'permission' => '0'
					]
				],
				'expected_error' => 'Invalid parameter "/1/hostgroup_rights/1/id": a number is expected.'
			],
			[
				'group' => [
					'name' => 'Check rights, id not valid',
					'hostgroup_rights' => [
						'id' => '1.1',
						'permission' => '0'
					]
				],
				'expected_error' => 'Invalid parameter "/1/hostgroup_rights/1/id": a number is expected.'
			],
			[
				'group' => [
					'name' => 'Check rights, non existen host group id',
					'hostgroup_rights' => [
						'id' => '123456',
						'permission' => '0'
					]
				],
				'expected_error' => 'Host group with ID "123456" is not available.'
			],
			[
				'group' => [
					'name' => 'Check rights, unexpected parameter',
					'hostgroup_rights' => [
						'id' => '4',
						'permission' => '0',
						'usrgrpid' => '7'
					]
				],
				'expected_error' => 'Invalid parameter "/1/hostgroup_rights/1": unexpected parameter "usrgrpid".'
			],
			// Check user group permissions, host group permission.
			[
				'group' => [
					'name' => 'Check rights, without permission',
					'hostgroup_rights' => [
						'id' => '4'
					]
				],
				'expected_error' => 'Invalid parameter "/1/hostgroup_rights/1": the parameter "permission" is missing.'
			],
			[
				'group' => [
					'name' => 'Check rights, with empty permission',
					'hostgroup_rights' => [
						'id' => '4',
						'permission' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/hostgroup_rights/1/permission": an integer is expected.'
			],
			[
				'group' => [
					'name' => 'Check rights, permission not valid number',
					'hostgroup_rights' => [
						'id' => '4',
						'permission' => '1.1'
					]
				],
				'expected_error' => 'Invalid parameter "/1/hostgroup_rights/1/permission": an integer is expected.'
			],
			[
				'group' => [
					'name' => 'Check rights, incorrect permission value',
					'hostgroup_rights' => [
						'id' => '4',
						'permission' => '1'
					]
				],
				'expected_error' => 'Invalid parameter "/1/hostgroup_rights/1/permission": value must be one of 0, 2, 3.'
			],
			[
				'group' => [
					'name' => 'Check rights, incorrect permission value',
					'templategroup_rights' => [
						'id' => '4',
						'permission' => '4'
					]
				],
				'expected_error' => 'Invalid parameter "/1/templategroup_rights/1/permission": value must be one of 0, 2, 3.'
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
					'SELECT * FROM usrgrp WHERE usrgrpid='.$result['result']['usrgrpids'][0]
				);
				$this->assertSame($group['name'], $db_group['name']);
				$this->assertEquals($group['gui_access'], $db_group['gui_access']);
				$this->assertEquals($group['users_status'], $db_group['users_status']);
				$this->assertEquals($group['debug_mode'], $db_group['debug_mode']);

				$this->assertEquals(count($group['users']), CDBHelper::getCount(
					'SELECT NULL'.
					' FROM users_groups'.
					' WHERE usrgrpid='.$result['result']['usrgrpids'][0]
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
						' WHERE usrgrpid='.$group['usrgrpid'].
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
			// Check user group used in LDAP userdirectory provision
			[
				'usergroup'	=> [':user_group:ldap provision group'],
				'expected_error' => 'Cannot delete user group "ldap provision group", because it is used by LDAP userdirectory "ldap provision".'
			],
			// Check user group used in SAML userdirectory provision
			[
				'usergroup'	=> [':user_group:saml provision group'],
				'expected_error' => 'Cannot delete user group "saml provision group", because it is used by SAML userdirectory.'
			],
			// Check successfully delete of user group.
			[
				'usergroup' => ['18', '19'],
				'expected_error' => null
			],
			'Can delete group havig user with single group' => [
				'usergroup' => [':user_group:user group 2'],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider usergroup_delete
	*/
	public function testUserGroup_Delete($groupids, $expected_error) {
		$groupids = CTestDataHelper::getConvertedValueReferences($groupids);
		$result = $this->call('usergroup.delete', $groupids, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['usrgrpids'] as $usrgrpid) {
				$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM usrgrp WHERE usrgrpid='.$usrgrpid));
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
					'usrgrpid' => '23',
					'name' => 'API user group update as admin user without permissions'
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
					'usrgrpid' => '23',
					'name' => 'API user group update as zabbix user without permissions'
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

	public static function crateValidDataProvider() {
		return [
			'Create group with userdirectory ldap' => [
				'group' => [
					[
						'name' => 'API group ldap #1',
						'gui_access' =>  GROUP_GUI_ACCESS_LDAP,
						'userdirectoryid' => 'API LDAP #1'
					]
				],
				'expected_error' => null
			],
			'Create group with default userdirectory ldap' => [
				'group' => [
					[
						'name' => 'API group ldap #2',
						'gui_access' =>  GROUP_GUI_ACCESS_LDAP,
						'userdirectoryid' => 0
					]
				],
				'expected_error' => null
			],
			'Create group with userdirectory system' => [
				'group' => [
					[
						'name' => 'API group ldap #3',
						'gui_access' =>  GROUP_GUI_ACCESS_SYSTEM,
						'userdirectoryid' => 'API LDAP #1'
					]
				],
				'expected_error' => null
			],
			'Create group with default userdirectory system' => [
				'group' => [
					[
						'name' => 'API group ldap #4',
						'gui_access' =>  GROUP_GUI_ACCESS_SYSTEM,
						'userdirectoryid' => 0
					]
				],
				'expected_error' => null
			]
		];
	}

	public static function crateInvalidDataProvider() {
		return [
			'Create group with userdirectory disabled' => [
				'group' => [
					[
						'name' => 'API group ldap #5',
						'gui_access' =>  GROUP_GUI_ACCESS_DISABLED,
						'userdirectoryid' => 'API LDAP #1'
					]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "userdirectoryid".'
			],
			'Create group with default userdirectory internal' => [
				'group' => [
					[
						'name' => 'API group ldap #5',
						'gui_access' =>  GROUP_GUI_ACCESS_INTERNAL,
						'userdirectoryid' => 'API LDAP #1'
					]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "userdirectoryid".'
			]
		];
	}

	/**
	* @dataProvider crateValidDataProvider
	* @dataProvider crateInvalidDataProvider
	*/
	public function testCreateWithUserdirectory(array $groups, $expected_error) {
		$response = $this->call('usergroup.create', self::resolveIds($groups), $expected_error);

		if ($expected_error === null) {
			$this->assertArrayHasKey('usrgrpids', $response['result']);
			self::$data['usrgrpid'] += array_combine(array_column($groups, 'name'), $response['result']['usrgrpids']);
		}
	}

	public static function updateValidDataProvider() {
		return [
			'Update group to gui internal' => [
				'group' => [
					[
						'usrgrpid' => 'API group ldap #1',
						'gui_access' =>  GROUP_GUI_ACCESS_INTERNAL
					]
				],
				'expected_error' => null
			],
			'Update group to gui ldap with userdirectory' => [
				'group' => [
					[
						'usrgrpid' => 'API group ldap #1',
						'gui_access' =>  GROUP_GUI_ACCESS_LDAP,
						'userdirectoryid' => 'API LDAP #1'
					]
				],
				'expected_error' => null
			]
		];
	}

	public static function updateInvalidDataProvider() {
		return [
			'Update group with gui internal' => [
				'group' => [
					[
						'usrgrpid' => 'API group ldap #1',
						'gui_access' =>  GROUP_GUI_ACCESS_INTERNAL,
						'userdirectoryid' => 'API LDAP #1'
					]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "userdirectoryid".'
			],
			'Update group with gui disabled' => [
				'group' => [
					[
						'usrgrpid' => 'API group ldap #1',
						'gui_access' =>  GROUP_GUI_ACCESS_DISABLED,
						'userdirectoryid' => 'API LDAP #1'
					]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "userdirectoryid".'
			]
		];
	}

	/**
	 * @dataProvider updateValidDataProvider
	 * @dataProvider updateInvalidDataProvider
	 */
	public function testUpdateWithUserdirectory(array $groups, $expected_error) {
		$this->call('usergroup.update', self::resolveIds($groups), $expected_error);
	}

	public static function crateValidMfaDataProvider(): array {
		return [
			'Create group with a specific MFA method' => [
				'group' => [
					[
						'name' => 'API group mfa #1',
						'mfa_status' => GROUP_MFA_ENABLED,
						'mfaid' => 'MFA TOTP method'
					]
				],
				'expected_error' => null
			],
			'Create group with default MFA method' => [
				'group' => [
					[
						'name' => 'API group mfa #2',
						'mfa_status' => GROUP_MFA_ENABLED,
						'mfaid' => 0
					]
				],
				'expected_error' => null
			]
		];
	}

	public static function crateInvalidMfaDataProvider(): array {
		return [
			'Create group with invalid MFA method' => [
				'group' => [
					[
						'name' => 'API group mfa #3',
						'mfa_status' => GROUP_MFA_ENABLED,
						'mfaid' => 999
					]
				],
				'expected_error' => 'Invalid parameter "/1/mfaid": object does not exist.'
			]
		];
	}

	/**
	 * @dataProvider crateValidMfaDataProvider
	 * @dataProvider crateInvalidMfaDataProvider
	 */
	public function testCreateWithMfaMethod(array $groups, $expected_error): void {
		$response = $this->call('usergroup.create', self::resolveIds($groups), $expected_error);

		if ($expected_error === null) {
			$this->assertArrayHasKey('usrgrpids', $response['result']);
			self::$data['mfaid'] += array_combine(array_column($groups, 'name'), $response['result']['usrgrpids']);
		}
	}

	public static function updateValidMfaDataProvider(): array {
		return [
			'Update group to specific mfa method ' => [
				'group' => [
					[
						'usrgrpid' => 'API group ldap #1',
						'mfa_status' => GROUP_MFA_ENABLED,
						'mfaid' => 'MFA TOTP method'
					]
				],
				'expected_error' => null
			],
			'Update group to default mfa method ' => [
				'group' => [
					[
						'usrgrpid' => 'API group ldap #2',
						'mfa_status' => GROUP_MFA_ENABLED,
						'mfaid' => 0
					]
				],
				'expected_error' => null
			]
		];
	}

	public static function updateInvalidMfaDataProvider(): array {
		return [
			'Update group with invalid mfaid' => [
				'group' => [
					[
						'usrgrpid' => 'API group ldap #3',
						'mfa_status' => GROUP_MFA_ENABLED,
						'mfaid' => 999
					]
				],
				'expected_error' => 'Invalid parameter "/1/mfaid": object does not exist.'
			]
		];
	}

	/**
	 * @dataProvider updateValidMfaDataProvider
	 * @dataProvider updateInvalidMfaDataProvider
	 */
	public function testUpdateWithMfaMethod(array $groups, $expected_error): void {
		$this->call('usergroup.update', self::resolveIds($groups), $expected_error);
	}

	/**
	 * Replace name by value for property names in self::$data.
	 *
	 * @param array $rows
	 */
	public static function resolveIds(array $rows): array {
		$result = [];

		foreach ($rows as $row) {
			foreach (array_intersect_key(self::$data, $row) as $key => $ids) {
				if (array_key_exists($row[$key], $ids)) {
					$row[$key] = $ids[$row[$key]];
				}
			}

			$result[] = $row;
		}

		return $result;
	}
}

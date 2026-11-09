<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


require_once __DIR__.'/../include/CAPITest.php';
require_once __DIR__.'/../include/helpers/CTestDataHelper.php';

/**
 * @onBefore prepareTestData
 * @onAfter  cleanTestData
 */
class testUserMacroPermissions extends CAPITest {

	public static function prepareTestData(): void {
		CTestDataHelper::createObjects([
			'template_groups' => [
				['name' => 'usermacro.access.template.group']
			],
			'host_groups' => [
				['name' => 'usermacro.access.group1'],
				['name' => 'usermacro.access.group2']
			],
			'templates' => [
				[
					'host' => 'usermacro.access.template',
					'groups' => ['groupid' => ':template_group:usermacro.access.template.group'],
					'macros' => [
						['macro' => '{$UM0}', 'value' => '']
					]
				]
			],
			'hosts' => [
				[
					'host' => 'usermacro.access.host1',
					'groups' => ['groupid' => ':host_group:usermacro.access.group1'],
					'macros' => [
						['macro' => '{$UM1}', 'value' => '']
					]
				],
				[
					'host' => 'usermacro.access.host2',
					'groups' => ['groupid' => ':host_group:usermacro.access.group2'],
					'templates' => [['templateid' => ':template:usermacro.access.template']],
					'macros' => [
						['macro' => '{$UM2}', 'value' => '']
					]
				]
			],
			'roles' => [
				['name' => 'usermacro.access.user.role', 'type' => USER_TYPE_ZABBIX_USER, 'rules' => ['api.access' => ZBX_ROLE_RULE_ENABLED]],
				['name' => 'usermacro.access.admin.role', 'type' => USER_TYPE_ZABBIX_ADMIN, 'rules' => ['api.access' => ZBX_ROLE_RULE_ENABLED]]
			],
			'user_groups' => [
				[
					'name' => 'usermacro.access.usergroup1',
					'hostgroup_rights' => [
						'id' => ':host_group:usermacro.access.group1',
						'permission' => PERM_READ_WRITE
					]
				],
				[
					'name' => 'usermacro.access.usergroup2',
					'hostgroup_rights' => [
						'id' => ':host_group:usermacro.access.group2',
						'permission' => PERM_READ_WRITE
					],
					'templategroup_rights' => [
						'id' => ':template_group:usermacro.access.template.group',
						'permission' => PERM_READ_WRITE
					]
				],
				[
					'name' => 'usermacro.access.usergroup3',
					'hostgroup_rights' => [
						'id' => ':host_group:usermacro.access.group1',
						'permission' => PERM_READ
					]
				]
			],
			'users' => [
				[
					'username' => 'usermacro.access.user.1',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:usermacro.access.usergroup1']
					],
					'roleid' => ':role:usermacro.access.user.role'
				],
				[
					'username' => 'usermacro.access.user.2',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:usermacro.access.usergroup2']
					],
					'roleid' => ':role:usermacro.access.admin.role'
				],
				[
					'username' => 'usermacro.access.user.3',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:usermacro.access.usergroup3']
					],
					'roleid' => ':role:usermacro.access.admin.role'
				],
				[
					'username' => 'usermacro.access.user.4',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [],
					'roleid' => ':role:usermacro.access.user.role'
				]
			]
		]);
	}

	public static function cleanTestData(): void {
		CTestDataHelper::cleanUp();
	}

	public static function getProvider(): iterable {
		yield 'no filtering for "usermacro.access.user.1" return macros only for host user have access' => [
			['username' => 'usermacro.access.user.1', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value']
			],
			[
				['hostmacroid' => ':user_macro:{$UM1}:host:usermacro.access.host1', 'macro' => '{$UM1}', 'value' => '']
			]
		];

		yield 'user without host permissions gets no macros' => [
			['username' => 'usermacro.access.user.4', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value']
			],
			[]
		];

		yield 'user without host permissions gets zero macros' => [
			['username' => 'usermacro.access.user.4', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'countOutput' => true
			],
			'0'
		];

		yield 'filter by hostids for "usermacro.access.user.1" return macros only for host user have access' => [
			['username' => 'usermacro.access.user.1', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value'],
				'hostids' => [':host:usermacro.access.host1', ':host:usermacro.access.host2', ':template:usermacro.access.template']
			],
			[
				['hostmacroid' => ':user_macro:{$UM1}:host:usermacro.access.host1', 'macro' => '{$UM1}', 'value' => '']
			]
		];

		yield 'filter by hostids for "usermacro.access.user.1" return no macros for host user do not have access' => [
			['username' => 'usermacro.access.user.1', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value'],
				'hostids' => [':host:usermacro.access.host2']
			],
			[]
		];

		yield 'search for "usermacro.access.user.1" return macros only for host user have access' => [
			['username' => 'usermacro.access.user.1', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value'],
				'search' => ['macro' => 'UM'],
				'searchByAny' => true
			],
			[
				['hostmacroid' => ':user_macro:{$UM1}:host:usermacro.access.host1', 'macro' => '{$UM1}', 'value' => '']
			]
		];

		yield 'filter for "usermacro.access.user.1" return no macros for host user do not have access' => [
			['username' => 'usermacro.access.user.1', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value'],
				'filter' => ['macro' => '{$UM2}']
			],
			[]
		];

		yield 'filter "{$UM1}" for "usermacro.access.user.1" return macro for host user have access' => [
			['username' => 'usermacro.access.user.1', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value'],
				'filter' => ['macro' => '{$UM1}']
			],
			[
				['hostmacroid' => ':user_macro:{$UM1}:host:usermacro.access.host1', 'macro' => '{$UM1}', 'value' => '']
			]
		];

		yield 'filter by hostids for "usermacro.access.user.2" return macros only for host user have access' => [
			['username' => 'usermacro.access.user.2', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value'],
				'hostids' => [':host:usermacro.access.host1', ':host:usermacro.access.host2', ':template:usermacro.access.template']
			],
			[
				['hostmacroid' => ':user_macro:{$UM0}:template:usermacro.access.template', 'macro' => '{$UM0}', 'value' => ''],
				['hostmacroid' => ':user_macro:{$UM2}:host:usermacro.access.host2', 'macro' => '{$UM2}', 'value' => '']
			]
		];

		yield 'search for "usermacro.access.user.2" return macros only for host user have access' => [
			['username' => 'usermacro.access.user.2', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value'],
				'search' => ['macro' => 'UM'],
				'searchByAny' => true
			],
			[
				['hostmacroid' => ':user_macro:{$UM0}:template:usermacro.access.template', 'macro' => '{$UM0}', 'value' => ''],
				['hostmacroid' => ':user_macro:{$UM2}:host:usermacro.access.host2', 'macro' => '{$UM2}', 'value' => '']
			]
		];

		yield 'filter for "usermacro.access.user.2" return no macros for host user do not have access' => [
			['username' => 'usermacro.access.user.2', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value'],
				'filter' => ['macro' => '{$UM1}']
			],
			[]
		];

		yield 'filter "{$UM2}" for "usermacro.access.user.2" return macro' => [
			['username' => 'usermacro.access.user.2', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value'],
				'filter' => ['macro' => '{$UM2}']
			],
			[
				['hostmacroid' => ':user_macro:{$UM2}:host:usermacro.access.host2', 'macro' => '{$UM2}', 'value' => '']
			]
		];

		yield 'filter "{$UM1}" for "usermacro.access.user.3" with read permission return macro' => [
			['username' => 'usermacro.access.user.3', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value'],
				'filter' => ['macro' => '{$UM1}']
			],
			[
				['hostmacroid' => ':user_macro:{$UM1}:host:usermacro.access.host1', 'macro' => '{$UM1}', 'value' => '']
			]
		];

		yield 'editable returns macros from writable hosts' => [
			['username' => 'usermacro.access.user.1', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value'],
				'editable' => true
			],
			[
				['hostmacroid' => ':user_macro:{$UM1}:host:usermacro.access.host1', 'macro' => '{$UM1}', 'value' => '']
			]
		];

		yield 'editable excludes macros from read-only hosts' => [
			['username' => 'usermacro.access.user.3', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value'],
				'editable' => true
			],
			[]
		];

		yield 'countOutput excludes macros from inaccessible hosts' => [
			['username' => 'usermacro.access.user.1', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'hostids' => [':host:usermacro.access.host1', ':host:usermacro.access.host2'],
				'countOutput' => true
			],
			'1'
		];
	}

	/**
	 * @dataProvider getProvider
	 */
	public function testUserMacroPermissions_Get(array $login, array $request, array|string $expected): void {
		if (is_array($expected)) {
			CTestDataHelper::convertUserMacroReferences($expected);
		}

		if (array_key_exists('hostids', $request)) {
			CTestDataHelper::convertValueReferences($request['hostids']);
		}

		$this->authorize($login['username'], $login['password']);
		$result = $this->call('usermacro.get', $request);

		// Result order is not guaranteed without sortfield, compare regardless of order.
		if (is_array($expected) && $expected) {
			CTestArrayHelper::usort($expected, ['hostmacroid']);
			CTestArrayHelper::usort($result['result'], ['hostmacroid']);
		}

		$this->assertSame($expected, $result['result']);
	}

	public static function updateProvider(): iterable {
		yield 'user permission "usermacro.access.user.1" fail' => [
			['username' => 'usermacro.access.user.1', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'hostmacroid' => ':user_macro:{$UM1}:host:usermacro.access.host1',
				'value' => 'abc'
			],
			[],
			'No permissions to call "usermacro.update".'
		];

		yield 'admin permission "usermacro.access.user.2" update macros' => [
			['username' => 'usermacro.access.user.2', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'hostmacroid' => ':user_macro:{$UM2}:host:usermacro.access.host2',
				'value' => 'abc'
			],
			[
				'hostmacroids' => [':user_macro:{$UM2}:host:usermacro.access.host2']
			],
			null
		];

		yield 'admin permission "usermacro.access.user.2" change for non accessible hosts fail' => [
			['username' => 'usermacro.access.user.2', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'hostmacroid' => ':user_macro:{$UM1}:host:usermacro.access.host1',
				'value' => 'abc'
			],
			[],
			'No permissions to referred object or it does not exist!'
		];

		yield 'admin read only permission "usermacro.access.user.3" update macro fail' => [
			['username' => 'usermacro.access.user.3', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'hostmacroid' => ':user_macro:{$UM1}:host:usermacro.access.host1',
				'value' => 'abc'
			],
			[],
			'No permissions to referred object or it does not exist!'
		];
	}

	/**
	 * @dataProvider updateProvider
	 */
	public function testUserMacroPermissions_Update(array $login, array $request, array $expected, ?string $error): void {
		if (array_key_exists('hostmacroid', $request)) {
			$request['hostmacroid'] = CTestDataHelper::getConvertedValueReference($request['hostmacroid']);
		}

		if (array_key_exists('hostmacroids', $expected)) {
			CTestDataHelper::convertValueReferences($expected['hostmacroids']);
		}

		$this->authorize($login['username'], $login['password']);
		$result = $this->call('usermacro.update', $request, $error);

		if ($error === null) {
			$this->assertSame($expected, $result['result']);
		}
	}
}

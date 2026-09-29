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
class testUserMacroAccess extends CAPITest {

	/**
	 * User.1 and admin.1 have RW access to macros of host.1 via user_group.1 and host_group.1.
	 * Similarly, there's a second ".2" branch. Each should have access to their branch macros only.
	 */
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
				['name' => 'usermacro.access.user.role', 'type' => USER_TYPE_ZABBIX_USER],
				['name' => 'usermacro.access.admin.role', 'type' => USER_TYPE_ZABBIX_ADMIN]
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
					'roleid' => ':role:usermacro.access.user.role'
				],
				[
					'username' => 'usermacro.access.admin.1',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:usermacro.access.usergroup1']
					],
					'roleid' => ':role:usermacro.access.admin.role'
				],
				[
					'username' => 'usermacro.access.admin.2',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:usermacro.access.usergroup2']
					],
					'roleid' => ':role:usermacro.access.admin.role'
				]
			]
		]);

		$macros = CDataHelper::call('usermacro.get', [
			'output' => ['hostmacroid', 'macro'],
			'hostids' => CTestDataHelper::getConvertedValueReferences([
				':host:usermacro.access.host1',
				':host:usermacro.access.host2',
				':template:usermacro.access.template'
			])
		]);

		foreach ($macros as $macro) {
			CTestDataHelper::addCustomReference('macro', $macro['macro'], $macro['hostmacroid']);
		}
	}

	public static function cleanTestData(): void {
		CTestDataHelper::cleanUp();
	}

	public static function getProvider() {
		yield 'no filtering for "usermacro.access.user.1" return macros only for host "usermacro.access.host1"' => [
			['username' => 'usermacro.access.user.1', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value']
			],
			[
				['hostmacroid' => ':macro:{$UM1}', 'macro' => '{$UM1}', 'value' => '']
			]
		];

		yield 'filter by hostids for "usermacro.access.user.1" return macros only for host user have access' => [
			['username' => 'usermacro.access.user.1', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value'],
				'hostids' => [':host:usermacro.access.host1', ':host:usermacro.access.host2', ':template:usermacro.access.template'],
			],
			[
				['hostmacroid' => ':macro:{$UM1}', 'macro' => '{$UM1}', 'value' => '']
			]
		];

		yield 'filter by hostids for "usermacro.access.user.1" return no macros for host user do not have access' => [
			['username' => 'usermacro.access.user.1', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value'],
				'hostids' => [':host:usermacro.access.host2'],
			],
			[]
		];

		yield 'search for "usermacro.access.user.1" return macros only for host "usermacro.access.host1"' => [
			['username' => 'usermacro.access.user.1', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value'],
				'search' => ['macro' => 'UM'],
				'searchByAny' => true
			],
			[
				['hostmacroid' => ':macro:{$UM1}', 'macro' => '{$UM1}', 'value' => '']
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

		yield 'filter "{$UM1}" for "usermacro.access.user.1" return macro' => [
			['username' => 'usermacro.access.user.1', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value'],
				'filter' => ['macro' => '{$UM1}']
			],
			[
				['hostmacroid' => ':macro:{$UM1}', 'macro' => '{$UM1}', 'value' => '']
			]
		];

		yield 'filter by hostids for "usermacro.access.user.2" return macros only for host user have access' => [
			['username' => 'usermacro.access.user.2', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value'],
				'hostids' => [':host:usermacro.access.host1', ':host:usermacro.access.host2', ':template:usermacro.access.template'],
			],
			[
				['hostmacroid' => ':macro:{$UM0}', 'macro' => '{$UM0}', 'value' => ''],
				['hostmacroid' => ':macro:{$UM2}', 'macro' => '{$UM2}', 'value' => '']
			]
		];

		yield 'search for "usermacro.access.user.2" return macros only for host "usermacro.access.host2" and "usermacro.access.template"' => [
			['username' => 'usermacro.access.user.2', 'password' => '|-|e!1@ (/\)0rLD!'],
			[
				'output' => ['hostmacroid', 'macro', 'value'],
				'search' => ['macro' => 'UM'],
				'searchByAny' => true
			],
			[
				['hostmacroid' => ':macro:{$UM0}', 'macro' => '{$UM0}', 'value' => ''],
				['hostmacroid' => ':macro:{$UM2}', 'macro' => '{$UM2}', 'value' => '']
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
				['hostmacroid' => ':macro:{$UM2}', 'macro' => '{$UM2}', 'value' => '']
			]
		];
	}

	/**
	 * @dataProvider getProvider
	 */
	public function testUserMacroAccessGet(array $login, array $request, array $expected_result,
			?string $expected_error = null): void {
		CTestDataHelper::convertCustomObjectReference($request, 'hostids', true);
		CTestDataHelper::convertCustomObjectReference($request, 'hostmacroids', true);

		foreach ($expected_result as &$macro) {
			CTestDataHelper::convertCustomObjectReference($macro, 'hostmacroid');
		}
		unset($macro);

		$this->authorize($login['username'], $login['password']);
		$result = $this->call('usermacro.get', $request, $expected_error);

		if ($expected_error !== null) {
			return;
		}

		$this->assertArrayHasKey('result', $result);
		$this->assertSame($expected_result, $result['result']);
	}

	public static function updateProvider() {
		// yield 'with "usermacro.access.user.1"'

		// return;
		return [
			'User cannot update any macro' => [
				'login' => ['username' => 'usermacro.access.user.1', 'password' => '|-|e!1@ (/\)0rLD!'],
				'request' => [
					'hostmacroid' => ':macro:{$UM2}',
					'value' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'No permissions to call "usermacro.update".'
			],
			'Admin can update owned macro' => [
				'login' => ['username' => 'usermacro.access.admin.1', 'password' => '|-|e!1@ (/\)0rLD!'],
				'request' => [
					'hostmacroid' => ':macro:{$UM1}',
					'value' => 'abc'
				],
				'expected_result' => [
					'hostmacroids' => [':macro:{$UM1}']
				]
			],
			'Admin cannot update other macro' => [
				'login' => ['username' => 'usermacro.access.admin.1', 'password' => '|-|e!1@ (/\)0rLD!'],
				'request' => [
					'hostmacroid' => ':macro:{$UM2}',
					'value' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
		];
	}

	/**
	 * @dataProvider updateProvider
	 */
	public function testUserMacroAccessUpdate(array $login, array $request, array $expected_result,
			?string $expected_error = null): void {
		CTestDataHelper::convertCustomObjectReference($request, 'hostmacroid');
		CTestDataHelper::convertCustomObjectReference($expected_result, 'hostmacroids', true);

		$this->authorize($login['username'], $login['password']);
		$result = $this->call('usermacro.update', $request, $expected_error);

		if ($expected_error !== null) {
			return;
		}

		$this->assertArrayHasKey('result', $result);
		$this->assertSame($expected_result, $result['result']);
	}
}

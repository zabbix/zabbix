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
	public function prepareTestData(): void {
		CTestDataHelper::createObjects([
			'host_groups' => [
				['name' => 'usermacro.access.group1'],
				['name' => 'usermacro.access.group2']
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
					]
				]
			]
		]);

		$actors = [
			':user:usermacro.access.user.1'=> [
				'username' => 'usermacro.access.user.1',
				'passwd' => '|-|e!1@ (/\)0rLD!',
				'usrgrps' => [
					['usrgrpid' => ':user_group:usermacro.access.usergroup1']
				]
			],
			':user:usermacro.access.user.2' => [
				'username' => 'usermacro.access.user.2',
				'passwd' => '|-|e!1@ (/\)0rLD!',
				'usrgrps' => [
					['usrgrpid' => ':user_group:usermacro.access.usergroup2']
				]
			],
			':user:usermacro.access.admin.1' => [
				'username' => 'usermacro.access.admin.1',
				'passwd' => '|-|e!1@ (/\)0rLD!',
				'usrgrps' => [
					['usrgrpid' => ':user_group:usermacro.access.usergroup1']
				]
			],
			':user:usermacro.access.admin.2' => [
				'username' => 'usermacro.access.admin.2',
				'passwd' => '|-|e!1@ (/\)0rLD!',
				'usrgrps' => [
					['usrgrpid' => ':user_group:usermacro.access.usergroup2']
				]
			]
		];

		CTestDataHelper::createObjects([
			'users' => array_values($actors)
		]);

		$macros = CDataHelper::call('usermacro.get', [
			'output' => ['hostmacroid', 'macro'],
			'hostids' => CTestDataHelper::getConvertedValueReferences([
				':host:usermacro.access.host1',
				':host:usermacro.access.host2'
			])
		]);

		foreach ($macros as $macro) {
			CTestDataHelper::addCustomReference('macro', $macro['macro'], $macro['hostmacroid']);
		}

		CTestDataHelper::authorizeActors($actors);
	}

	public static function cleanTestData(): void {
		CTestDataHelper::cleanUp();
	}

	public static function getProvider() {
		return [
			'User gets own macros only' => [
				'actor' => ':user:usermacro.access.user.1',
				'request' => [
					'output' => ['hostmacroid', 'macro', 'value']
				],
				'expected_result' => [
					[
						'hostmacroid' => ':macro:{$UM1}',
						'macro' => '{$UM1}',
						'value' => ''
					]
				]
			],
			'Other user get own macros only' => [
				'actor' => ':user:usermacro.access.user.2',
				'request' => [
					'output' => ['hostmacroid', 'macro', 'value']
				],
				'expected_result' => [
					[
						'hostmacroid' => ':macro:{$UM2}',
						'macro' => '{$UM2}',
						'value' => ''
					]
				]
			],
			'Admin get own macros only' => [
				'actor' => ':user:usermacro.access.admin.1',
				'request' => [
					'output' => ['hostmacroid', 'macro', 'value']
				],
				'expected_result' => [
					[
						'hostmacroid' => ':macro:{$UM1}',
						'macro' => '{$UM1}',
						'value' => ''
					]
				]
			],
			'Other admin get own macros only' => [
				'actor' => ':user:usermacro.access.admin.2',
				'request' => [
					'output' => ['hostmacroid', 'macro', 'value']
				],
				'expected_result' => [
					[
						'hostmacroid' => ':macro:{$UM2}',
						'macro' => '{$UM2}',
						'value' => ''
					]
				]
			],
			'User cannot request non-allowed macros via host filter' => [
				'actor' => ':user:usermacro.access.user.1',
				'request' => [
					'output' => 'extend',
					'hostids' => [':host:usermacro.access.host2']
				],
				'expected_result' => []
			],
			'Admin from second group cannot request non-allowed macro by ID' => [
				'actor' => ':user:usermacro.access.admin.2',
				'request' => [
					'output' => 'extend',
					'hostmacroids' => [':macro:{$UM1}']
				],
				'expected_result' => []
			],
			'Admin from second group cannot request non-allowed macro via filter' => [
				'actor' => ':user:usermacro.access.admin.2',
				'request' => [
					'output' => 'extend',
					'filter' => [
						'macro' => '{$UM1}'
					]
				],
				'expected_result' => []
			],
			'Admin from first group can request allowed macro via filter' => [
				'actor' => ':user:usermacro.access.admin.1',
				'request' => [
					'output' => ['hostmacroid'],
					'filter' => [
						'macro' => '{$UM1}'
					]
				],
				'expected_result' => [
					['hostmacroid' => ':macro:{$UM1}']
				]
			],
			'Admin from second group cannot request non-allowed macro via search' => [
				'actor' => ':user:usermacro.access.admin.2',
				'request' => [
					'output' => 'extend',
					'search' => [
						'macro' => '{$UM1}'
					],
					'searchByAny' => true
				],
				'expected_result' => []
			],
			'Admin from first group can request allowed macro via search' => [
				'actor' => ':user:usermacro.access.admin.1',
				'request' => [
					'output' => ['hostmacroid'],
					'search' => [
						'macro' => '{$UM1}'
					],
					'searchByAny' => true
				],
				'expected_result' => [
					['hostmacroid' => ':macro:{$UM1}']
				]
			]
		];
	}

	/**
	 * @dataProvider getProvider
	 */
	public function testUserMacroAccessGet(string $actor, array $request, array $expected_result,
			?string $expected_error = null): void {
		CTestDataHelper::convertCustomObjectReference($request, 'hostids', true);
		CTestDataHelper::convertCustomObjectReference($request, 'hostmacroids', true);

		foreach ($expected_result as &$macro) {
			CTestDataHelper::convertCustomObjectReference($macro, 'hostmacroid');
		}
		unset($macro);

		$this->method_test('usermacro.get', $actor, $request, $expected_result, $expected_error);
	}

	private function method_test(string $method, string $actor, array $request, array $expected_result,
			$expected_error): void {
		CTestDataHelper::setActor($actor);

		$result = $this->call($method, $request, $expected_error);

		if ($expected_error !== null) {
			return;
		}

		$this->assertArrayHasKey('result', $result);

		$this->assertSame($expected_result, $result['result']);
	}

	public static function updateProvider() {
		return [
			'User can update owned macro' => [
				'actor' => ':user:usermacro.access.user.1',
				'request' => [
					'hostmacroid' => ':macro:{$UM1}',
					'value' => 'abc'
				],
				'expected_result' => [
					'hostmacroids' => [':macro:{$UM1}']
				]
			],
			'User cannot update other macro' => [
				'actor' => ':user:usermacro.access.user.1',
				'request' => [
					'hostmacroid' => ':macro:{$UM2}',
					'value' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Admin can update owned macro' => [
				'actor' => ':user:usermacro.access.admin.1',
				'request' => [
					'hostmacroid' => ':macro:{$UM1}',
					'value' => 'abc'
				],
				'expected_result' => [
					'hostmacroids' => [':macro:{$UM1}']
				]
			],
			'Admin cannot update other macro' => [
				'actor' => ':user:usermacro.access.admin.1',
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
	public function testUserMacroAccessUpdate(string $actor, array $request, array $expected_result,
			?string $expected_error = null): void {
		CTestDataHelper::convertCustomObjectReference($request, 'hostmacroid');
		CTestDataHelper::convertCustomObjectReference($expected_result, 'hostmacroids', true);

		$this->method_test('usermacro.update', $actor, $request, $expected_result, $expected_error);
	}
}

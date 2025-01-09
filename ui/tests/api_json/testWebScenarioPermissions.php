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


require_once __DIR__.'/../include/CAPITest.php';
require_once __DIR__.'/../include/helpers/CTestDataHelper.php';


/**
 * @onBefore prepareTestData
 * @onAfter  cleanTestData
 */
class testWebScenarioPermissions extends CAPITest {

	public static function prepareTestData(): void {
		$steps = ['steps' => [
			[
				'name' => 'Homepage',
				'url' => 'http://example.com',
				'no' => '0'
			]
		]];

		CTestDataHelper::createObjects([
			'host_groups' => [
				['name' => 'perm.ht.hosts.rw'],
				['name' => 'perm.ht.hosts.r'],
				['name' => 'perm.ht.hosts.d'],
				['name' => 'perm.ht.hosts.n']
			],
			'hosts' => [
				[
					'host' => 'perm.ht.host.rw',
					'description' => 'Read-Write host',
					'groups' => ['groupid' => ':host_group:perm.ht.hosts.rw'],
					'httptests' => [
						['name' => 'perm.ht.super-admin.del.rw'] + $steps,
						['name' => 'perm.ht.admin.del.rw'] + $steps,
						['name' => 'perm.ht.user.del.rw'] + $steps,
						['name' => 'perm.ht.super-admin.upd.rw'] + $steps,
						['name' => 'perm.ht.admin.upd.rw'] + $steps,
						['name' => 'perm.ht.user.upd.rw'] + $steps,
						['name' => 'perm.ht.super-admin.upd.mixed.rw'] + $steps,
						['name' => 'perm.ht.admin.upd.mixed.rw'] + $steps,
						['name' => 'perm.ht.user.upd.mixed.rw'] + $steps
					]
				],
				[
					'host' => 'perm.ht.host.r',
					'description' => 'Read-only host',
					'groups' => ['groupid' => ':host_group:perm.ht.hosts.r'],
					'httptests' => [
						['name' => 'perm.ht.super-admin.del.r'] + $steps,
						['name' => 'perm.ht.admin.del.r'] + $steps,
						['name' => 'perm.ht.user.del.r'] + $steps,
						['name' => 'perm.ht.super-admin.upd.r'] + $steps,
						['name' => 'perm.ht.admin.upd.r'] + $steps,
						['name' => 'perm.ht.user.upd.r'] + $steps,
						['name' => 'perm.ht.admin.upd.mixed.r'] + $steps,
						['name' => 'perm.ht.user.upd.mixed.r'] + $steps
					]
				],
				[
					'host' => 'perm.ht.host.d',
					'description' => 'Denied host',
					'groups' => ['groupid' => ':host_group:perm.ht.hosts.d'],
					'httptests' => [
						['name' => 'perm.ht.super-admin.del.d'] + $steps,
						['name' => 'perm.ht.admin.del.d'] + $steps,
						['name' => 'perm.ht.user.del.d'] + $steps,
						['name' => 'perm.ht.super-admin.upd.d'] + $steps,
						['name' => 'perm.ht.admin.upd.d'] + $steps,
						['name' => 'perm.ht.user.upd.d'] + $steps
					]
				],
				[
					'host' => 'perm.ht.host.n',
					'description' => 'Host not linked to user groups',
					'groups' => ['groupid' => ':host_group:perm.ht.hosts.n'],
					'httptests' => [
						['name' => 'perm.ht.super-admin.del.n'] + $steps,
						['name' => 'perm.ht.admin.del.n'] + $steps,
						['name' => 'perm.ht.user.del.n'] + $steps,
						['name' => 'perm.ht.super-admin.upd.n'] + $steps,
						['name' => 'perm.ht.admin.upd.n'] + $steps,
						['name' => 'perm.ht.user.upd.n'] + $steps,
						['name' => 'perm.ht.super-admin.upd.mixed.n'] + $steps
					]
				]
			],
			'user_groups' => [
				[
					'name' => 'perm.ht.mixed',
					'hostgroup_rights' => [
						[
							'id' => ':host_group:perm.ht.hosts.rw',
							'permission' => PERM_READ_WRITE
						],
						[
							'id' => ':host_group:perm.ht.hosts.r',
							'permission' => PERM_READ
						],
						[
							'id' => ':host_group:perm.ht.hosts.d',
							'permission' => PERM_DENY
						]
					]
				]
			],
			'roles' => [
				['name' => 'perm.ht.super-admin.role', 'type' => USER_TYPE_SUPER_ADMIN],
				['name' => 'perm.ht.admin.role', 'type' => USER_TYPE_ZABBIX_ADMIN],
				['name' => 'perm.ht.user.role', 'type' => USER_TYPE_ZABBIX_USER]
			],
			'users' => [
				[
					'username' => 'perm.ht.super-admin',
					'passwd' => 'perm.ht.password',
					'roleid' => ':role:perm.ht.super-admin.role',
					'usrgrps' => [
						['usrgrpid' => ':user_group:perm.ht.mixed']
					]
				],
				[
					'username' => 'perm.ht.admin',
					'passwd' => 'perm.ht.password',
					'roleid' => ':role:perm.ht.admin.role',
					'usrgrps' => [
						['usrgrpid' => ':user_group:perm.ht.mixed']
					]
				],
				[
					'username' => 'perm.ht.user',
					'passwd' => 'perm.ht.password',
					'roleid' => ':role:perm.ht.user.role',
					'usrgrps' => [
						['usrgrpid' => ':user_group:perm.ht.mixed']
					]
				]
			]
		]);
	}

	public static function cleanTestData(): void {
		CTestDataHelper::cleanUp();
	}

	public static function getCreatePermissionChecks() {
		$steps = ['steps' => [
			[
				'name' => 'First step',
				'url' => 'http://example.com',
				'status_codes' => '200',
				'no' => '0'
			],
			[
				'name' => 'Second step',
				'url' => 'http://example.com/login',
				'status_codes' => '404',
				'no' => '1'
			]
		]];

		return [
			'Super-admin create httptest on RW host' => [
				'login' => ['username' => 'perm.ht.super-admin', 'password' => 'perm.ht.password'],
				'request' => [
					'name' => 'ht.sa.rw',
					'hostid' => ':host:perm.ht.host.rw'
				] + $steps,
				'expected_error' => null
			],
			'Super-admin create httptest on R host' => [
				'login' => ['username' => 'perm.ht.super-admin', 'password' => 'perm.ht.password'],
				'request' => [
					'name' => 'ht.sa.r',
					'hostid' => ':host:perm.ht.host.r'
				] + $steps,
				'expected_error' => null
			],
			'Super-admin create httptest on D host' => [
				'login' => ['username' => 'perm.ht.super-admin', 'password' => 'perm.ht.password'],
				'request' => [
					'name' => 'ht.sa.d',
					'hostid' => ':host:perm.ht.host.d'
				] + $steps,
				'expected_error' => null
			],
			'Super-admin create httptest on other host' => [
				'login' => ['username' => 'perm.ht.super-admin', 'password' => 'perm.ht.password'],
				'request' => [
					'name' => 'ht.sa.n',
					'hostid' => ':host:perm.ht.host.n'
				] + $steps,
				'expected_error' => null
			],
			'Super-admin create httptests on mixed (min=none) access hosts' => [
				'login' => ['username' => 'perm.ht.super-admin', 'password' => 'perm.ht.password'],
				'request' => [
					[
						'name' => 'ht.sa.n1',
						'hostid' => ':host:perm.ht.host.rw'
					] + $steps,
					[
						'name' => 'ht.sa.n2',
						'hostid' => ':host:perm.ht.host.n'
					] + $steps
				],
				'expected_error' => null
			],
			'Admin create httptest on RW host' => [
				'login' => ['username' => 'perm.ht.admin', 'password' => 'perm.ht.password'],
				'request' => [
					'name' => 'ht.a.rw',
					'hostid' => ':host:perm.ht.host.rw'
				] + $steps,
				'expected_error' => null
			],
			'Admin create httptest on R host' => [
				'login' => ['username' => 'perm.ht.admin', 'password' => 'perm.ht.password'],
				'request' => [
					'name' => 'ht.a.r',
					'hostid' => ':host:perm.ht.host.r'
				] + $steps,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Admin create httptest on D host' => [
				'login' => ['username' => 'perm.ht.admin', 'password' => 'perm.ht.password'],
				'request' => [
					'name' => 'ht.a.d',
					'hostid' => ':host:perm.ht.host.d'
				] + $steps,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Admin create httptest on N host' => [
				'login' => ['username' => 'perm.ht.admin', 'password' => 'perm.ht.password'],
				'request' => [
					'name' => 'ht.a.n',
					'hostid' => ':host:perm.ht.host.n'
				] + $steps,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Admin create httptest on mixed (min=read-only) access hosts' => [
				'login' => ['username' => 'perm.ht.admin', 'password' => 'perm.ht.password'],
				'request' => [
					[
						'name' => 'ht.a.rw1',
						'hostid' => ':host:perm.ht.host.rw'
					] + $steps,
					[
						'name' => 'ht.a.r1',
						'hostid' => ':host:perm.ht.host.r'
					] + $steps
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'User create httptest on RW host' => [
				'login' => ['username' => 'perm.ht.user', 'password' => 'perm.ht.password'],
				'request' => [
					'name' => 'ht.u.rw',
					'hostid' => ':host:perm.ht.host.rw'
				] + $steps,
				'expected_error' => 'No permissions to call "httptest.create".'
			],
			'User create httptest on R host' => [
				'login' => ['username' => 'perm.ht.user', 'password' => 'perm.ht.password'],
				'request' => [
					'name' => 'ht.u.r',
					'hostid' => ':host:perm.ht.host.r'
				] + $steps,
				'expected_error' => 'No permissions to call "httptest.create".'
			],
			'User create httptest on D host' => [
				'login' => ['username' => 'perm.ht.user', 'password' => 'perm.ht.password'],
				'request' => [
					'name' => 'ht.u.d',
					'hostid' => ':host:perm.ht.host.d'
				] + $steps,
				'expected_error' => 'No permissions to call "httptest.create".'
			],
			'User create httptest on N host' => [
				'login' => ['username' => 'perm.ht.user', 'password' => 'perm.ht.password'],
				'request' => [
					'name' => 'ht.u.n',
					'hostid' => ':host:perm.ht.host.n'
				] + $steps,
				'expected_error' => 'No permissions to call "httptest.create".'
			],
			'User create httptests on mixed (min=read-only) access hosts' => [
				'login' => ['username' => 'perm.ht.user', 'password' => 'perm.ht.password'],
				'request' => [
					[
						'name' => 'ht.u.rw1',
						'hostid' => ':host:perm.ht.host.rw'
					] + $steps,
					[
						'name' => 'ht.u.r1',
						'hostid' => ':host:perm.ht.host.r'
					] + $steps
				],
				'expected_error' => 'No permissions to call "httptest.create".'
			]
		];
	}

	/**
	 * @dataProvider getCreatePermissionChecks
	 */
	public function testWebScenarioPermissions_CreatePermissions(array $login, array $request,
			?string $expected_error): void {
		$this->authorize($login['username'], $login['password']);

		$httptests = array_key_exists(0, $request) ? $request : [$request];

		foreach ($httptests as &$httptest) {
			CTestDataHelper::convertHttptestReferences($httptest);
		}
		unset($httptest);

		$result = $this->call('httptest.create', $httptests, $expected_error);

		foreach ($httptests as $httptest) {
			$options = $expected_error === null
				? [
					'output' => ['name', 'hostid'],
					'selectSteps' => ['name', 'url', 'status_codes', 'no'],
					'httptestids' => array_shift($result['result']['httptestids'])
				]
				: [
					'output' => ['name', 'hostid'],
					'hostids' => $httptest['hostid'],
					'selectSteps' => ['name', 'url', 'status_codes', 'no'],
					'filter' => [
						'name' => $httptest['name']
					]
				];

			$verify = $this->call('httptest.get', $options, null);

			if ($expected_error === null) {
				$this->assertSame(array_diff_key($verify['result'][0], array_flip(['httptestid'])), $httptest);
			}
			else {
				$this->assertEquals([], $verify['result'], 'Web scenario '.$httptest['name'].' should not be created.');
			}
		}
	}

	public static function getUpdatePermissionChecks() {
		return [
			'Super-admin update httptest on RW host' => [
				'login' => ['username' => 'perm.ht.super-admin', 'password' => 'perm.ht.password'],
				'request' => [
					'httptestid' => ':httptest:perm.ht.super-admin.upd.rw',
					'name' => 'ht.sa.rw.upd'
				],
				'expected_error' => null
			],
			'Super-admin update httptest on R host' => [
				'login' => ['username' => 'perm.ht.super-admin', 'password' => 'perm.ht.password'],
				'request' => [
					'httptestid' => ':httptest:perm.ht.super-admin.upd.r',
					'name' => 'ht.sa.r.upd'
				],
				'expected_error' => null
			],
			'Super-admin update httptest on D host' => [
				'login' => ['username' => 'perm.ht.super-admin', 'password' => 'perm.ht.password'],
				'request' => [
					'httptestid' => ':httptest:perm.ht.super-admin.upd.d',
					'name' => 'ht.sa.d.upd'
				],
				'expected_error' => null
			],
			'Super-admin update httptest on other host' => [
				'login' => ['username' => 'perm.ht.super-admin', 'password' => 'perm.ht.password'],
				'request' => [
					'httptestid' => ':httptest:perm.ht.super-admin.upd.n',
					'name' => 'ht.sa.n.upd'
				],
				'expected_error' => null
			],
			'Super-admin update httptests on mixed (min=none) access hosts' => [
				'login' => ['username' => 'perm.ht.super-admin', 'password' => 'perm.ht.password'],
				'request' => [
					[
						'httptestid' => ':httptest:perm.ht.super-admin.upd.mixed.rw',
						'name' => 'ht.sa.rw.upd1'
					],
					[
						'httptestid' => ':httptest:perm.ht.super-admin.upd.mixed.n',
						'name' => 'ht.sa.n.upd1'
					]
				],
				'expected_error' => null
			],
			'Admin update httptest on RW host' => [
				'login' => ['username' => 'perm.ht.admin', 'password' => 'perm.ht.password'],
				'request' => [
					'httptestid' => ':httptest:perm.ht.admin.upd.rw',
					'name' => 'ht.a.rw.upd'
				],
				'expected_error' => null
			],
			'Admin update httptest on R host' => [
				'login' => ['username' => 'perm.ht.admin', 'password' => 'perm.ht.password'],
				'request' => [
					'httptestid' => ':httptest:perm.ht.admin.upd.r',
					'name' => 'ht.a.r.upd'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Admin update httptest on D host' => [
				'login' => ['username' => 'perm.ht.admin', 'password' => 'perm.ht.password'],
				'request' => [
					'httptestid' => ':httptest:perm.ht.admin.upd.d',
					'name' => 'ht.a.d.upd'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Admin update httptest on N host' => [
				'login' => ['username' => 'perm.ht.admin', 'password' => 'perm.ht.password'],
				'request' => [
					'httptestid' => ':httptest:perm.ht.admin.upd.n',
					'name' => 'ht.a.n.upd'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Admin update httptests on mixed (min=read-only) access hosts' => [
				'login' => ['username' => 'perm.ht.admin', 'password' => 'perm.ht.password'],
				'request' => [
					[
						'httptestid' => ':httptest:perm.ht.admin.upd.mixed.rw',
						'name' => 'ht.a.rw.upd1'
					],
					[
						'httptestid' => ':httptest:perm.ht.admin.upd.mixed.r',
						'name' => 'ht.a.r.upd1'
					]
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'User update httptest on RW host' => [
				'login' => ['username' => 'perm.ht.user', 'password' => 'perm.ht.password'],
				'request' => [
					'httptestid' => ':httptest:perm.ht.user.upd.rw',
					'name' => 'ht.u.rw.upd'
				],
				'expected_error' => 'No permissions to call "httptest.update".'
			],
			'User update httptest on R host' => [
				'login' => ['username' => 'perm.ht.user', 'password' => 'perm.ht.password'],
				'request' => [
					'httptestid' => ':httptest:perm.ht.user.upd.r',
					'name' => 'ht.u.r.upd'
				],
				'expected_error' => 'No permissions to call "httptest.update".'
			],
			'User update httptest on D host' => [
				'login' => ['username' => 'perm.ht.user', 'password' => 'perm.ht.password'],
				'request' => [
					'httptestid' => ':httptest:perm.ht.user.upd.d',
					'name' => 'ht.u.d.upd'
				],
				'expected_error' => 'No permissions to call "httptest.update".'
			],
			'User update httptest on N host' => [
				'login' => ['username' => 'perm.ht.user', 'password' => 'perm.ht.password'],
				'request' => [
					'httptestid' => ':httptest:perm.ht.user.upd.n',
					'name' => 'ht.u.n.upd'
				],
				'expected_error' => 'No permissions to call "httptest.update".'
			],
			'User update httptests on mixed (min=read-only) access hosts' => [
				'login' => ['username' => 'perm.ht.user', 'password' => 'perm.ht.password'],
				'request' => [
					[
						'httptestid' => ':httptest:perm.ht.user.upd.mixed.rw',
						'name' => 'ht.u.rw.upd1'
					],
					[
						'httptestid' => ':httptest:perm.ht.user.upd.mixed.r',
						'name' => 'ht.u.r.upd1'
					]
				],
				'expected_error' => 'No permissions to call "httptest.update".'
			]
		];
	}

	/**
	 * @dataProvider getUpdatePermissionChecks
	 */
	public function testWebScenarioPermissions_UpdatePermissions(array $login, array $request,
			?string $expected_error): void {
		$this->authorize($login['username'], $login['password']);

		$httptests = array_key_exists(0, $request) ? $request : [$request];

		foreach ($httptests as &$httptest) {
			CTestDataHelper::convertHttptestReferences($httptest);
		}
		unset($httptest);

		$result = $this->call('httptest.update', $httptests, $expected_error);

		foreach ($httptests as $httptest) {
			$options = $expected_error === null
				? [
					'output' => ['name'],
					'httptestids' => array_shift($result['result']['httptestids'])
				]
				: [
					'output' => ['name'],
					'filter' => [
						'name' => $httptest['name']
					]
				];

			$verify = $this->call('httptest.get', $options, null);

			if ($expected_error === null) {
				$this->assertSame($verify['result'][0], $httptest);
			}
			else {
				$this->assertEquals([], $verify['result'], 'Web scenario '.$httptest['name'].' should not exist.');
			}
		}
	}


	public static function getHttptestDeleteData() {
		return [
			'Super-admin delete RW httptest' => [
				'login' => ['username' => 'perm.ht.super-admin', 'password' => 'perm.ht.password'],
				'httptestids' => [':httptest:perm.ht.super-admin.del.rw'],
				'expected_error' => null
			],
			'Super-admin delete R httptest' => [
				'login' => ['username' => 'perm.ht.super-admin', 'password' => 'perm.ht.password'],
				'httptestids' => [':httptest:perm.ht.super-admin.del.r'],
				'expected_error' => null
			],
			'Super-admin delete D httptest' => [
				'login' => ['username' => 'perm.ht.super-admin', 'password' => 'perm.ht.password'],
				'httptestids' => [':httptest:perm.ht.super-admin.del.d'],
				'expected_error' => null
			],
			'Super-admin delete other httptest' => [
				'login' => ['username' => 'perm.ht.super-admin', 'password' => 'perm.ht.password'],
				'httptestids' => [':httptest:perm.ht.super-admin.del.n'],
				'expected_error' => null
			],
			'Admin delete RW httptest' => [
				'login' => ['username' => 'perm.ht.admin', 'password' => 'perm.ht.password'],
				'httptestids' => [':httptest:perm.ht.admin.del.rw'],
				'expected_error' => null
			],
			'Admin delete R httptest' => [
				'login' => ['username' => 'perm.ht.admin', 'password' => 'perm.ht.password'],
				'httptestids' => [':httptest:perm.ht.admin.del.r'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Admin delete D httptest' => [
				'login' => ['username' => 'perm.ht.admin', 'password' => 'perm.ht.password'],
				'httptestids' => [':httptest:perm.ht.admin.del.d'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Admin delete other httptest' => [
				'login' => ['username' => 'perm.ht.admin', 'password' => 'perm.ht.password'],
				'httptestids' => [':httptest:perm.ht.admin.del.n'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'User delete RW httptest' => [
				'login' => ['username' => 'perm.ht.user', 'password' => 'perm.ht.password'],
				'httptestids' => [':httptest:perm.ht.user.del.rw'],
				'expected_error' => 'No permissions to call "httptest.delete".'
			],
			'User delete R httptest' => [
				'login' => ['username' => 'perm.ht.user', 'password' => 'perm.ht.password'],
				'httptestids' => [':httptest:perm.ht.user.del.r'],
				'expected_error' => 'No permissions to call "httptest.delete".'
			],
			'User delete D httptest' => [
				'login' => ['username' => 'perm.ht.user', 'password' => 'perm.ht.password'],
				'httptestids' => [':httptest:perm.ht.user.del.d'],
				'expected_error' => 'No permissions to call "httptest.delete".'
			],
			'User delete other httptest' => [
				'login' => ['username' => 'perm.ht.user', 'password' => 'perm.ht.password'],
				'httptestids' => [':httptest:perm.ht.user.del.n'],
				'expected_error' => 'No permissions to call "httptest.delete".'
			]
		];
	}

	/**
	* @dataProvider getHttptestDeleteData
	*/
	public function testWebScenarioPermissions_Delete(array $login, array $httptestids, ?string $expected_error) {
		$this->authorize($login['username'], $login['password']);

		$converted_httptestids = CTestDataHelper::getConvertedValueReferences($httptestids);

		$this->call('httptest.delete', $converted_httptestids, $expected_error);

		if ($expected_error === null) {
			CTestDataHelper::unsetDeletedObjectIds(array_diff($httptestids, $converted_httptestids));

			$db_httptestids = array_keys(CAPIHelper::call('httptest.get', [
				'output' => [],
				'httptestids' => $converted_httptestids,
				'preservekeys' => true
			]));

			$this->assertSame([],
				array_intersect_key($httptestids, array_intersect($converted_httptestids, $db_httptestids))
			);
		}
	}
}

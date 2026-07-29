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

require_once dirname(__FILE__).'/../include/CAPITest.php';
require_once __DIR__.'/../include/helpers/CTestDataHelper.php';

/**
 * @onBefore prepareTestData
 * @onAfter  cleanTestData
 */
class testDRule extends CAPITest {

	public static function prepareTestData(): void {
		CTestDataHelper::enableGuestUser();

		CTestDataHelper::createObjects([
			'template_groups' => [
				['name' => 'drule.template.group']
			],
			'templates' => [
				['host' => 'drule.template']
			],
			'proxies' => [
				['name' => 'drule.proxy'],
				['name' => 'drule.inaccessible.proxy']
			],
			'drules' => [
				['name' => 'drule.used.in.action.1'],
				['name' => 'drule.used.in.action.2'],
				['name' => 'drule.del.1'],
				['name' => 'drule.del.2'],
				['name' => 'drule.del.3'],
				['name' => 'drule.perm.del'],
				[
					'name' => 'drule.with.proxy',
					'proxyid' => ':proxy:drule.proxy'
				],
				[
					'name' => 'drule.with.inaccessible.proxy',
					'proxyid' => ':proxy:drule.inaccessible.proxy'
				],
				[
					'name' => 'drule.with.accessible.proxy',
					'proxyid' => ':proxy:drule.proxy'
				]
			],
			'actions' => [
				[
					'name' => 'drule.discovery.action',
					'eventsource' => EVENT_SOURCE_DISCOVERY,
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_OR,
						'conditions' => [
							[
								'conditiontype' => ZBX_CONDITION_TYPE_DRULE,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'value' => ':drule:drule.used.in.action.1'
							],
							[
								'conditiontype' => ZBX_CONDITION_TYPE_DRULE,
								'operator' => CONDITION_OPERATOR_NOT_EQUAL,
								'value' => ':drule:drule.used.in.action.2'
							]
						]
					],
					'operations' => [
						[
							'operationtype' => OPERATION_TYPE_TEMPLATE_ADD,
							'optemplate' => [
								['templateid' => ':template:drule.template']
							]
						]
					]
				]
			]
		]);

		CTestDataHelper::createObjects([
			'user_groups' => [
				[
					'name' => 'perm.users.enabled',
					'users_status' => GROUP_STATUS_ENABLED
				],
				[
					'name' => 'user with inaccessible proxy',
					'proxies' => ['proxyid' => ':proxy:drule.inaccessible.proxy']
				],
				[
					'name' => 'user with accessible proxy',
					'proxy_mode' => PROXY_MODE_ALLOW,
					'proxies' => ['proxyid' => ':proxy:drule.proxy']
				]
			],
			'roles' => [
				['name' => 'perm.user.role', 'type' => USER_TYPE_ZABBIX_USER],
				['name' => 'perm.admin.role', 'type' => USER_TYPE_ZABBIX_ADMIN]
			],
			'users' => [
				[
					'username' => 'perm.user',
					'passwd' => 'zabbix!password',
					'roleid' => ':role:perm.user.role',
					'usrgrps' => [['usrgrpid' => ':user_group:perm.users.enabled']]
				],
				[
					'username' => 'perm.admin',
					'passwd' => 'zabbix!password',
					'roleid' => ':role:perm.admin.role',
					'usrgrps' => [['usrgrpid' => ':user_group:perm.users.enabled']]
				],
				[
					'username' => 'admin.with.inaccessible.proxy',
					'passwd' => 'zabbix!password',
					'roleid' => ':role:perm.admin.role',
					'usrgrps' => [
						['usrgrpid' => ':user_group:user with inaccessible proxy']
					]
				],
				[
					'username' => 'admin.with.proxy',
					'passwd' => 'zabbix!password',
					'roleid' => ':role:perm.admin.role',
					'usrgrps' => [
						['usrgrpid' => ':user_group:user with accessible proxy']
					]
				]
			]
		]);
	}

	public static function cleanTestData(): void {
		CTestDataHelper::disableGuestUser();

		CTestDataHelper::cleanUp();
	}

	public static function dataProviderInvalidProxyForDRuleCreate() {
		return [
			'Create drule with inaccessible proxy' => [
				'login' => ['user' => 'admin.with.inaccessible.proxy', 'password' => 'zabbix!password'],
				'drule' => [
					[
						'name' => 'drule with inaccessible proxy',
						'iprange' => ZBX_MONITORED_BY_PROXY,
						'proxyid' => ':proxy:drule.inaccessible.proxy',
						'dchecks' => [
							[
							'type' => 9,
							'key_' => 'system.uname',
							'ports' => '10050'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/proxyid": object does not exist, or you have no permissions to it.'
			],
			'Create drule with non-existent proxy' => [
				'login' => ['user' => 'admin.with.proxy', 'password' => 'zabbix!password'],
				'drule' => [
					[
						'name' => 'drule with existing proxy',
						'iprange' => ZBX_MONITORED_BY_PROXY,
						'proxyid' => ':proxy:drule.proxy',
						'dchecks' => [
							[
							'type' => 9,
							'key_' => 'system.uname',
							'ports' => '10050'
							]
						]
					],
					[
						'name' => 'drule with non-existing proxy',
						'iprange' => ZBX_MONITORED_BY_PROXY,
						'proxyid' => 999,
						'dchecks' => [
							[
							'type' => 9,
							'key_' => 'system.uname',
							'ports' => '10050'
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/2/proxyid": object does not exist, or you have no permissions to it.'
			]
		];
	}

	public static function dataProviderValidProxyForDRuleCreate() {
		return [
			'Create drule with accessible proxy' => [
				'login' => ['user' => 'admin.with.inaccessible.proxy', 'password' => 'zabbix!password'],
				'drule' => [
					[
						'name' => 'drule with accessible proxy',
						'iprange' => ZBX_MONITORED_BY_PROXY,
						'proxyid' => ':proxy:drule.proxy',
						'dchecks' => [
							[
							'type' => 9,
							'key_' => 'system.uname',
							'ports' => '10050'
							]
						]
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider dataProviderInvalidProxyForDRuleCreate
	 * @dataProvider dataProviderValidProxyForDRuleCreate
	 */
	public function testDRule_Create(array $login, array $drules, ?string $expected_error): void {
		foreach ($drules as &$drule) {
			if (array_key_exists('proxyid', $drule)) {
				$drule['proxyid'] = CTestDataHelper::getConvertedValueReference($drule['proxyid']);
			}
		}
		unset($drule);

		if ($login) {
			$this->authorize($login['user'], $login['password']);
		}

		$result = $this->call('drule.create', $drules, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['druleids'] as $druleid) {
				$this->assertEquals(
					1, CDBHelper::getCount('SELECT NULL FROM drules WHERE druleid='.zbx_dbstr($druleid))
				);
			}
		}
	}

	public static function dataProviderInvalidProxyForDRuleUpdate() {
		return [
			'Update drule with inaccessible proxy' => [
				'login' => ['user' => 'admin.with.inaccessible.proxy', 'password' => 'zabbix!password'],
				'drule' => [
					[
						'druleid' => ':drule:drule.with.accessible.proxy',
						'proxyid' => ':proxy:drule.inaccessible.proxy'
					]
				],
				'expected_error' => 'Invalid parameter "/1/proxyid": object does not exist, or you have no permissions to it.'
			]
		];
	}

	public static function dataProviderValidProxyForDRuleUpdate() {
		return [
			'Create drule with accessible proxy' => [
				'login' => ['user' => 'admin.with.inaccessible.proxy', 'password' => 'zabbix!password'],
				'drule' => [
					[
						'druleid' => ':drule:drule.with.accessible.proxy',
						'proxyid' => ':proxy:drule.proxy'
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider dataProviderInvalidProxyForDRuleUpdate
	 * @dataProvider dataProviderValidProxyForDRuleUpdate
	 */
	public function testDRule_Update(array $login, array $drules, ?string $expected_error): void {
		foreach ($drules as &$drule) {
			if (array_key_exists('druleid', $drule)) {
				$drule['druleid'] = CTestDataHelper::getConvertedValueReference($drule['druleid']);
			}

			if (array_key_exists('proxyid', $drule)) {
				$drule['proxyid'] = CTestDataHelper::getConvertedValueReference($drule['proxyid']);
			}
		}
		unset($drule);

		if ($login) {
			$this->authorize($login['user'], $login['password']);
		}

		$result = $this->call('drule.update', $drules, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['druleids'] as $druleid) {
				$this->assertEquals(
					1, CDBHelper::getCount('SELECT NULL FROM drules WHERE druleid='.zbx_dbstr($druleid))
				);
			}
		}
	}

	public static function getDRuleDeleteData() {
		return [
			'No IDs' => [
				'drule' => [],
				'expected_error' => 'Invalid parameter "/": cannot be empty.'
			],
			'Empty string ID' => [
				'drule' => [''],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			'Non-numeric ID' => [
				'drule' => ['abc'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			'Float ID' => [
				'drule' => ['1.1'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			'Non-exist ID' => [
				'drule' => ['123456'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Non-unique ID' => [
				'drule' => ['10', '10'],
				'expected_error' => 'Invalid parameter "/2": value (10) already exists.'
			],
			'One of IDs non-numeric' => [
				'drule' => ['10', 'abcd'],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			'DRule used in action' => [
				'drule' => [':drule:drule.used.in.action.1'],
				'expected_error' => 'Cannot delete discovery rule "drule.used.in.action.1": action "drule.discovery.action" uses this discovery rule.'
			],
			'DRule used in another action' => [
				'drule' => [':drule:drule.used.in.action.2'],
				'expected_error' => 'Cannot delete discovery rule "drule.used.in.action.2": action "drule.discovery.action" uses this discovery rule.'
			],
			'Delete discovery rule without proxy' => [
				'drule' => [':drule:drule.del.1'],
				'expected_error' => null
			],
			'Delete discovery rule with proxy' => [
				'drule' => [':drule:drule.with.proxy'],
				'expected_error' => null
			],
			'Delete two Discovery rules' => [
				'drule' => [':drule:drule.del.2',':drule:drule.del.3'],
				'expected_error' => null
			],
			'Delete discovery rule with inaccessible proxy' => [
				'drule' => [':drule:drule.with.inaccessible.proxy'],
				'expected_error' => 'No permissions to referred object or it does not exist!',
				'login' => ['user' => 'admin.with.inaccessible.proxy', 'password' => 'zabbix!password']
			]
		];
	}

	/**
	* @dataProvider getDRuleDeleteData
	*/
	public function testDRule_Delete(array $druleids, ?string $expected_error, ?array $login = null) {
		$converted_druleids = CTestDataHelper::getConvertedValueReferences($druleids);

		if ($login) {
			$this->authorize($login['user'], $login['password']);
		}

		$this->call('drule.delete', $converted_druleids, $expected_error);

		if ($expected_error === null) {
			CTestDataHelper::unsetDeletedObjectIds(array_diff($druleids, $converted_druleids));

			$db_druleids = array_keys(CAPIHelper::call('drule.get', [
				'output' => [],
				'actionids' => $converted_druleids,
				'preservekeys' => true
			]));

			$this->assertSame([],
				array_intersect_key($druleids, array_intersect($converted_druleids, $db_druleids))
			);
		}
	}

	public static function getDRuleUserPermissionsData() {
		return [
			[
				'login' => ['user' => 'guest', 'password' => ''],
				'drule' => [':drule:drule.perm.del'],
				'expected_error' => 'No permissions to call "drule.delete".'
			],
			[
				'login' => ['user' => 'perm.user', 'password' => 'zabbix!password'],
				'drule' => [':drule:drule.perm.del'],
				'expected_error' => 'No permissions to call "drule.delete".'
			],
			[
				'login' => ['user' => 'perm.admin', 'password' => 'zabbix!password'],
				'drule' => ['123456'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'login' => ['user' => 'perm.admin', 'password' => 'zabbix!password'],
				'drule' => [':drule:drule.perm.del'],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider getDRuleUserPermissionsData
	 */
	public function testDRule_Permissions(array $login, array $druleids, ?string $expected_error) {
		$sql = 'SELECT * FROM drules ORDER BY druleid';
		$old_drule = CDBHelper::getHash($sql);

		$this->authorize($login['user'], $login['password']);
		$converted_druleids = CTestDataHelper::getConvertedValueReferences($druleids);

		$this->call('drule.delete', $converted_druleids, $expected_error);

		if ($expected_error === null) {
			CTestDataHelper::unsetDeletedObjectIds(array_diff($druleids, $converted_druleids));

			$db_druleids = array_keys(CAPIHelper::call('drule.get', [
				'output' => [],
				'actionids' => $converted_druleids,
				'preservekeys' => true
			]));

			$this->assertSame([],
				array_intersect_key($druleids, array_intersect($converted_druleids, $db_druleids))
			);
		}
		else {
			$this->assertEquals($old_drule, CDBHelper::getHash($sql));
		}
	}
}

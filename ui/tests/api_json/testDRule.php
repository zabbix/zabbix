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
				['name' => 'drule.proxy']
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
				['name' => 'perm.users.enabled', 'users_status' => GROUP_STATUS_ENABLED]
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
				]
			]
		]);
	}

	public static function cleanTestData(): void {
		CTestDataHelper::disableGuestUser();

		CTestDataHelper::cleanUp();
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
				'expected_error' => 'Discovery rule "drule.used.in.action.1" is used in "drule.discovery.action" action.'
			],
			'DRule used in another action' => [
				'drule' => [':drule:drule.used.in.action.2'],
				'expected_error' => 'Discovery rule "drule.used.in.action.2" is used in "drule.discovery.action" action.'
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
			]
		];
	}

	/**
	* @dataProvider getDRuleDeleteData
	*/
	public function testDRule_Delete(array $druleids, ?string $expected_error) {
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

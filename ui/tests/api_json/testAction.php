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
class testAction extends CAPITest {

	public static function prepareTestData(): void {
		CTestDataHelper::createObjects([
			'template_groups' => [
				['name' => 'perm.tg1'],
				['name' => 'perm.tg2'],
				['name' => 'perm.tg3']
			],
			'host_groups' => [
				['name' => 'del.hg1'],
				['name' => 'perm.filter.condition.hg1'],
				['name' => 'perm.hg1'],
				['name' => 'perm.hg2'],
				['name' => 'perm.hg3'],
				['name' => 'perm.hg4'],
				['name' => 'perm.hg5'],
				['name' => 'perm.opcommand_grp.hg1'],
				['name' => 'perm.hg6'],
				['name' => 'perm.opgroup.hg1'],
				['name' => 'perm.opgroup.hg2']
			],
			'proxies' => [
				[
					'name' => 'del.p1',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
				],
				[
					'name' => 'inaccessible.p1',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
				],
				[
					'name' => 'accessible.p1',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
				],
				[
					'name' => 'accessible.p2',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
				]
			],
			'templates' => [
				[
					'host' => 'perm.filter.condition.t1',
					'groups' => ['groupid' => ':template_group:perm.tg1']
				],
				[
					'host' => 'perm.optemplate.t1',
					'groups' => ['groupid' => ':template_group:perm.tg2']
				],
				[
					'host' => 'perm.optemplate.t2',
					'groups' => ['groupid' => ':template_group:perm.tg3']
				]
			],
			'hosts' => [
				[
					'host' => 'perm.filter.condition.h1',
					'groups' => ['groupid' => ':host_group:perm.hg1']
				],
				[
					'host' => 'perm.h1',
					'groups' => ['groupid' => ':host_group:perm.hg2'],
					'items' => [
						['key_' => 'i1']
					]
				],
				[
					'host' => 'perm.opcommand_hst.h1',
					'groups' => ['groupid' => ':host_group:perm.hg6']
				]
			],
			'triggers' => [
				'perm.filter.condition.tg1(perm.h1(i1))' => [
					'description' => 'perm.filter.condition.tg1(perm.h1(i1))',
					'expression' => 'last(/perm.h1/i1)=0'
				]
			],
			'roles' => [
				['name' => 'r1', 'type' => USER_TYPE_ZABBIX_ADMIN]
			],
			'user_groups' => [
				[
					'name' => 'del.ug1',
					'hostgroup_rights' => [
						'id' => ':host_group:del.hg1',
						'permission' => PERM_READ
					]
				],
				[
					'name' => 'perm.opmessage_grp.ug1',
					'hostgroup_rights' => [
						'id' => ':host_group:perm.hg3',
						'permission' => PERM_READ
					]
				],
				[
					'name' => 'perm.ug1',
					'hostgroup_rights' => [
						'id' => ':host_group:perm.hg4',
						'permission' => PERM_READ
					]
				],
				[
					'name' => 'create.pr1',
					'proxies' => ['proxyid' => ':proxy:inaccessible.p1']
				],
				[
					'name' => 'create.pr2',
					'proxies' => [['proxyid' => ':proxy:accessible.p1'], ['proxyid' => ':proxy:accessible.p2']],
					'proxy_mode' => PROXY_MODE_ALLOW
				]
			],
			'users' => [
				[
					'username' => 'del.u1',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:del.ug1']
					]
				],
				[
					'username' => 'perm.opmessage_usr.u1',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:perm.ug1']
					]
				],
				[
					'username' => 'admin.with.inaccessible.proxy',
					'passwd' => 'zabbix!password',
					'usrgrps' => [
						['usrgrpid' => ':user_group:create.pr1']
					]
				],
				[
					'username' => 'admin.with.accessible.proxy',
					'passwd' => 'zabbix!password',
					'usrgrps' => [
						['usrgrpid' => ':user_group:create.pr2']
					]
				]
			],
			'scripts' => [
				[
					'name' => 'perm.opcommand.s1',
					'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
					'scope' => ZBX_SCRIPT_SCOPE_ACTION,
					'command' => 'return value;',
					'groupid' => ':host_group:perm.hg5'
				]
			],
			'actions' => [
				[
					'name'=> 'del.trigger.action.1',
					'eventsource' => EVENT_SOURCE_TRIGGERS,
					'operations' => [
						[
							'operationtype' => OPERATION_TYPE_MESSAGE,
							'opmessage' => [],
							'opmessage_usr' => [
								['userid' => ':user:del.u1']
							]
						]
					]
				],
				[
					'name' => 'del.discovery.action.1',
					'eventsource' => EVENT_SOURCE_DISCOVERY,
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'conditiontype' => ZBX_CONDITION_TYPE_PROXY,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'value' => ':proxy:del.p1'
							]
						]
					],
					'operations' => [
						[
							'operationtype' => OPERATION_TYPE_MESSAGE,
							'opmessage' => [],
							'opmessage_grp' => [
								['usrgrpid' => ':user_group:del.ug1']
							]
						]
					]
				],
				[
					'name' => 'del.autoreg.action.1',
					'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
					'operations' => [
						[
							'operationtype' => OPERATION_TYPE_MESSAGE,
							'opmessage' => [],
							'opmessage_grp' => [
								['usrgrpid' => ':user_group:del.ug1']
							]
						]
					]
				],
				[
					'name' => 'del.internal.action.1',
					'eventsource' => EVENT_SOURCE_INTERNAL,
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'conditiontype' => ZBX_CONDITION_TYPE_EVENT_TYPE,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'value' => EVENT_TYPE_TRIGGER_UNKNOWN
							]
						]
					],
					'operations' => [
						[
							'operationtype' => OPERATION_TYPE_MESSAGE,
							'opmessage' => [],
							'opmessage_grp' => [
								['usrgrpid' => ':user_group:del.ug1']
							]
						]
					],
					'recovery_operations' => [
						[
							'operationtype' => OPERATION_TYPE_RECOVERY_MESSAGE,
							'opmessage' => []
						]
					]
				],
				[
					'name' => 'perm.trigger.action.1',
					'eventsource' => EVENT_SOURCE_TRIGGERS,
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'conditiontype' => ZBX_CONDITION_TYPE_HOST_GROUP,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'value' => ':host_group:perm.filter.condition.hg1'
							],
							[
								'conditiontype' => ZBX_CONDITION_TYPE_HOST,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'value' => ':host:perm.filter.condition.h1'
							],
							[
								'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'value' => ':trigger:perm.filter.condition.tg1(perm.h1(i1))'
							],
							[
								'conditiontype' => ZBX_CONDITION_TYPE_TEMPLATE,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'value' => ':template:perm.filter.condition.t1'
							]
						]
					],
					'operations' => [
						[
							'operationtype' => OPERATION_TYPE_MESSAGE,
							'opmessage' => [],
							'opmessage_grp' => [
								['usrgrpid' => ':user_group:perm.opmessage_grp.ug1']
							],
							'opmessage_usr' => [
								['userid' => ':user:perm.opmessage_usr.u1']
							]
						],
						[
							'operationtype' => OPERATION_TYPE_COMMAND,
							'opcommand' => ['scriptid' => ':script:perm.opcommand.s1'],
							'opcommand_grp' => [
								['groupid' => ':host_group:perm.opcommand_grp.hg1']
							],
							'opcommand_hst' => [
								['hostid' => ':host:perm.opcommand_hst.h1']
							]
						]
					]
				],
				[
					'name' => 'perm.discovery.action.1',
					'eventsource' => EVENT_SOURCE_DISCOVERY,
					'operations' => [
						[
							'operationtype' => OPERATION_TYPE_GROUP_ADD,
							'opgroup' => [
								['groupid' => ':host_group:perm.opgroup.hg1']
							]
						],
						[
							'operationtype' => OPERATION_TYPE_GROUP_REMOVE,
							'opgroup' => [
								['groupid' => ':host_group:perm.opgroup.hg2']
							]
						],
						[
							'operationtype' => OPERATION_TYPE_TEMPLATE_ADD,
							'optemplate' => [
								['templateid' => ':template:perm.optemplate.t1']
							]
						],
						[
							'operationtype' => OPERATION_TYPE_TEMPLATE_REMOVE,
							'optemplate' => [
								['templateid' => ':template:perm.optemplate.t2']
							]
						]
					]
				],
				[
					'name' => 'action with proxy',
					'eventsource' => EVENT_SOURCE_DISCOVERY,
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'conditiontype' => ZBX_CONDITION_TYPE_PROXY,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'value' => ':proxy:accessible.p1'
							]
						]
					],
					'operations' => [
						[
							'operationtype' => OPERATION_TYPE_HOST_ADD
						]
					]
				],
				[
					'name' => 'delete action with proxy',
					'eventsource' => EVENT_SOURCE_DISCOVERY,
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							[
								'conditiontype' => ZBX_CONDITION_TYPE_PROXY,
								'operator' => CONDITION_OPERATOR_EQUAL,
								'value' => ':proxy:inaccessible.p1'
							]
						]
					],
					'operations' => [
						[
							'operationtype' => OPERATION_TYPE_HOST_ADD
						]
					]
				]
			]
		]);

		CTestDataHelper::createObjects([
			'template_groups' => [
				['name' => 'completely.no.access.to.perm.trigger.action.1']
			],
			'host_groups' => [
				['name' => 'completely.no.access.to.perm.trigger.action.1']
			],
			'user_groups' => [
				[
					'name' => 'full.access.to.perm.trigger.action.1',
					'templategroup_rights' => [
						['id' => ':template_group:perm.tg1', 'permission' => PERM_READ]
					],
					'hostgroup_rights' => [
						['id' => ':host_group:perm.filter.condition.hg1', 'permission' => PERM_READ],
						['id' => ':host_group:perm.hg1', 'permission' => PERM_READ],
						['id' => ':host_group:perm.hg2', 'permission' => PERM_READ],
						['id' => ':host_group:perm.hg5', 'permission' => PERM_READ],
						['id' => ':host_group:perm.opcommand_grp.hg1', 'permission' => PERM_READ],
						['id' => ':host_group:perm.hg6', 'permission' => PERM_READ]
					]
				],
				[
					'name' => 'completely.no.access.to.perm.trigger.action.1',
					'templategroup_rights' => [
						['id' => ':template_group:completely.no.access.to.perm.trigger.action.1', 'permission' => PERM_READ]
					],
					'hostgroup_rights' => [
						['id' => ':host_group:completely.no.access.to.perm.trigger.action.1', 'permission' => PERM_READ]
					]
				],
				[
					'name' => 'full.access.to.perm.discovery.action.1',
					'templategroup_rights' => [
						['id' => ':template_group:perm.tg2', 'permission' => PERM_READ],
						['id' => ':template_group:perm.tg3', 'permission' => PERM_READ]
					],
					'hostgroup_rights' => [
						['id' => ':host_group:perm.opgroup.hg1', 'permission' => PERM_READ],
						['id' => ':host_group:perm.opgroup.hg2', 'permission' => PERM_READ]
					]
				],
				[
					'name' => 'partial.access.to.perm.discovery.action.1',
					'templategroup_rights' => [
						['id' => ':template_group:perm.tg2', 'permission' => PERM_READ]
					],
					'hostgroup_rights' => [
						['id' => ':host_group:perm.opgroup.hg2', 'permission' => PERM_READ]
					]
				]
			],
			'users' => [
				[
					'username' => 'full.access.to.perm.trigger.action.1',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:full.access.to.perm.trigger.action.1'],
						['usrgrpid' => ':user_group:perm.opmessage_grp.ug1'],
						['usrgrpid' => ':user_group:perm.ug1']
					]
				],
				[
					'username' => 'completely.no.access.to.perm.trigger.action.1',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:completely.no.access.to.perm.trigger.action.1']
					]
				],
				[
					'username' => 'partial.access.to.perm.trigger.action.1',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:full.access.to.perm.trigger.action.1']
					]
				],
				[
					'username' => 'full.access.to.perm.discovery.action.1',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:full.access.to.perm.discovery.action.1']
					]
				],
				[
					'username' => 'completely.no.access.to.perm.discovery.action.1',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:completely.no.access.to.perm.trigger.action.1']
					]
				],
				[
					'username' => 'partial.access.to.perm.discovery.action.1',
					'passwd' => '|-|e!1@ (/\)0rLD!',
					'usrgrps' => [
						['usrgrpid' => ':user_group:partial.access.to.perm.discovery.action.1']
					]
				]
			]
		]);
	}

	public static function cleanTestData(): void {
		CTestDataHelper::cleanUp();
	}

	public static function dataProviderInvalidActionCreate() {
		return [
			'Create action with inaccessible proxy' => [
				'login' => ['user' => 'admin.with.inaccessible.proxy', 'password' => 'zabbix!password'],
				'action' => [
					[
						'name' => 'action with inaccessible proxy',
						'eventsource' => EVENT_SOURCE_DISCOVERY,
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								[
									'conditiontype' => ZBX_CONDITION_TYPE_PROXY,
									'operator' => CONDITION_OPERATOR_EQUAL,
									'value' => ':proxy:inaccessible.p1'
								]
							]
						],
						'operations' => [
							[
								'operationtype' => OPERATION_TYPE_HOST_ADD
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/value": object does not exist, or you have no permissions to it.'
			]
		];
	}

	public static function dataProviderValidActionCreate() {
		return [
			'Create action with accessible proxy' => [
				'login' => ['user' => 'admin.with.accessible.proxy', 'password' => 'zabbix!password'],
				'action' => [
					[
						'name' => 'action with accessible proxy',
						'eventsource' => EVENT_SOURCE_DISCOVERY,
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								[
									'conditiontype' => ZBX_CONDITION_TYPE_PROXY,
									'operator' => CONDITION_OPERATOR_EQUAL,
									'value' => ':proxy:accessible.p1'
								]
							]
						],
						'operations' => [
							[
								'operationtype' => OPERATION_TYPE_HOST_ADD
							]
						]
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider dataProviderInvalidActionCreate
	* @dataProvider dataProviderValidActionCreate
	*/
	public function testAction_Create(array $login, $actions, $expected_error) {
		CTestDataHelper::convertActionReferences($actions);

		if ($login) {
			$this->authorize($login['user'], $login['password']);
		}

		$result = $this->call('action.create', $actions, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['actionids'] as $actionid) {
				$this->assertEquals(
					1, CDBHelper::getCount('SELECT NULL FROM actions WHERE actionid='.zbx_dbstr($actionid))
				);
			}
		}
	}

	public static function dataProviderInvalidActionUpdate() {
		return [
			'Update action with inaccessible proxy' => [
				'login' => ['user' => 'admin.with.inaccessible.proxy', 'password' => 'zabbix!password'],
				'action' => [
					[
						'actionid' => ':action:action with proxy',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								[
									'conditiontype' => ZBX_CONDITION_TYPE_PROXY,
									'operator' => CONDITION_OPERATOR_EQUAL,
									'value' => ':proxy:inaccessible.p1'
								]
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/filter/conditions/1/value": object does not exist, or you have no permissions to it.'
			]
		];
	}

	public static function dataProviderValidActionUpdate() {
		return [
			'Create action with accessible proxy' => [
				'login' => ['user' => 'admin.with.accessible.proxy', 'password' => 'zabbix!password'],
				'action' => [
					[
						'actionid' => ':action:action with proxy',
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'conditions' => [
								[
									'conditiontype' => ZBX_CONDITION_TYPE_PROXY,
									'operator' => CONDITION_OPERATOR_EQUAL,
									'value' => ':proxy:accessible.p2'
								]
							]
						]
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider dataProviderInvalidActionUpdate
	* @dataProvider dataProviderValidActionUpdate
	*/
	public function testAction_Update(array $login, $actions, $expected_error) {
		CTestDataHelper::convertActionReferences($actions);

		if ($login) {
			$this->authorize($login['user'], $login['password']);
		}

		$result = $this->call('action.update', $actions, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['actionids'] as $actionid) {
				$this->assertEquals(
					1, CDBHelper::getCount('SELECT NULL FROM actions WHERE actionid='.zbx_dbstr($actionid))
				);
			}
		}
	}

	public static function getActionDeleteData() {
		return [
			[
				'actionids' => [],
				'expected_error' => 'Invalid parameter "/": cannot be empty.'
			],
			[
				'actionids' => [''],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'actionids' => ['abc'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'actionids' => ['1.1'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'actionids' => ['123456'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'actionids' => [':action:del.trigger.action.1', ':action:del.trigger.action.1'],
				'expected_error' => static fn (): string => 'Invalid parameter "/2": value ('.CTestDataHelper::getConvertedValueReference(':action:del.trigger.action.1').') already exists.'
			],
			[
				'actionids' => [':action:del.trigger.action.1', 'abcd'],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'actionids' => [':action:del.trigger.action.1'],
				'expected_error' => null
			],
			[
				'actionids' => [':action:del.discovery.action.1'],
				'expected_error' => null
			],
			[
				'actionids' => [':action:del.autoreg.action.1'],
				'expected_error' => null
			],
			[
				'actionids' => [':action:del.internal.action.1'],
				'expected_error' => null
			],
			[
				'actionids' => [':action:delete action with proxy'],
				'expected_error' => 'No permissions to referred object or it does not exist!',
				'login' => ['user' => 'admin.with.inaccessible.proxy', 'password' => 'zabbix!password']
			]
		];
	}

	/**
	* @dataProvider getActionDeleteData
	*/
	public function testAction_Delete($actionids, $expected_error, ?array $login = null) {
		$converted_actionids = CTestDataHelper::getConvertedValueReferences($actionids);

		if ($login) {
			$this->authorize($login['user'], $login['password']);
		}

		$this->call('action.delete', $converted_actionids, $expected_error);

		if ($expected_error === null) {
			CTestDataHelper::unsetDeletedObjectIds(array_diff($actionids, $converted_actionids));

			$db_actionids = array_keys(CAPIHelper::call('action.get', [
				'output' => [],
				'actionids' => $converted_actionids,
				'preservekeys' => true
			]));

			$this->assertSame([],
				array_intersect_key($actionids, array_intersect($converted_actionids, $db_actionids))
			);
		}
	}

	public static function getActionUserPermissionsData() {
		return [
			'User has permissions on all entities specified in trigger action' => [
				'login' => ['username' => 'full.access.to.perm.trigger.action.1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'actionids' => [':action:perm.trigger.action.1'],
				'expected_actionids' => [':action:perm.trigger.action.1']
			],
			'User has no permissions on any entity specified in trigger action' => [
				'login' => ['username' => 'completely.no.access.to.perm.trigger.action.1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'actionids' => [':action:perm.trigger.action.1'],
				'expected_actionids' => []
			],
			'User has permissions on some of the entities specified in trigger action' => [
				'login' => ['username' => 'partial.access.to.perm.trigger.action.1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'actionids' => [':action:perm.trigger.action.1'],
				'expected_actionids' => []
			],
			'User has permissions on all entities specified in discovery action' => [
				'login' => ['username' => 'full.access.to.perm.discovery.action.1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'actionids' => [':action:perm.discovery.action.1'],
				'expected_actionids' => [':action:perm.discovery.action.1']
			],
			'User has no permissions on any entity specified in discovery action' => [
				'login' => ['username' => 'completely.no.access.to.perm.discovery.action.1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'actionids' => [':action:perm.discovery.action.1'],
				'expected_actionids' => []
			],
			'User has permissions on some of the entities specified in discovery action' => [
				'login' => ['username' => 'partial.access.to.perm.discovery.action.1', 'password'=> '|-|e!1@ (/\)0rLD!'],
				'actionids' => [':action:perm.discovery.action.1'],
				'expected_actionids' => []
			]
		];
	}

	/**
	 * @dataProvider getActionUserPermissionsData
	 */
	public function testAction_Permissions(array $login, array $actionids, array $expected_actionids): void {
		$this->authorize($login['username'], $login['password']);

		CTestDataHelper::convertValueReferences($actionids);

		$result = $this->call('action.get', [
			'output' => [],
			'actionids' => $actionids,
			'preservekeys' => true
		], null);

		$converted_expected_actionids = CTestDataHelper::getConvertedValueReferences($expected_actionids);

		$db_actionids = array_keys($result['result']);

		foreach ($db_actionids as &$db_actionid) {
			$i = array_search($db_actionid, $converted_expected_actionids);

			if ($i !== false) {
				$db_actionid = $expected_actionids[$i];
			}
		}
		unset($db_actionid);

		sort($expected_actionids);
		sort($db_actionids);

		$this->assertSame($expected_actionids, $db_actionids);
	}
}

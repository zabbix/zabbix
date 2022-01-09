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

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';

/**
 * Test suite for alerting for services.
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_service_roles
 * @backup history,users,role_rule,role,triggers,alerts,actions
 */
class testServiceRoles extends CIntegrationTest {
	const HOSTNAME = 'test_service_roles';
	const TRAPPER_ITEM_NAME = 'test_service_trapper';

	private static $hostid;
	private static $parent_serviceid;
	private static $actionid;
	private static $child1_serviceid;
	private static $child2_serviceid;
	private static $child1_1_serviceid;
	private static $itemid;
	private static $triggerid;
	private static $roleid;
	private static $userid;

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "test_service_roles"
		$response = $this->call('host.create', [
			[
				'host' => self::HOSTNAME,
				'interfaces' => [
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		// Create trapper item
		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => self::TRAPPER_ITEM_NAME,
			'key_' => self::TRAPPER_ITEM_NAME,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		// Create trigger
		$trigger_desc = 'Sample trigger';
		$trigger_expr = 'last(/' . self::HOSTNAME . '/' . self::TRAPPER_ITEM_NAME . ')=1';
		$response = $this->call('trigger.create', [
			'description' => $trigger_desc,
			'priority' => TRIGGER_SEVERITY_HIGH,
			'status' => TRIGGER_STATUS_ENABLED,
			'type' => 0,
			'recovery_mode' => 0,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'expression' => $trigger_expr
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);
		self::$triggerid = $response['result']['triggerids'][0];

		$response = $this->call('trigger.update', [
			'triggerid' => self::$triggerid,
			'tags' => [
				[
					'tag' => 'ServiceLink',
					'value' => self::$triggerid . ':' . $trigger_desc
				]
			]
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);

		// Create aservice
		$response = $this->call('service.create', [
			'name' => 'Parent',
			'algorithm' => 1,
			'weight' => 0,
			'sortorder' => 0
		]);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['serviceids']);
		self::$parent_serviceid = $response['result']['serviceids'][0];

		// Create child service
		$response = $this->call('service.create', [
			'name' => 'Child 1',
			'algorithm' => 1,
			'weight' => 0,
			'sortorder' => 0,
			'tags' => [
				[
					'tag' => 'c1tag1',
					'value' => 'c1tag1value'
				],
				[
					'tag' => 'c1tagwovalue',
					'value' => ''
				]
			],
			'parents' => [
				['serviceid' => self::$parent_serviceid]
			]
		]);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['serviceids']);
		self::$child1_serviceid = $response['result']['serviceids'][0];

		// Create child service
		$response = $this->call('service.create', [
			'name' => 'Child 2',
			'algorithm' => 1,
			'weight' => 0,
			'sortorder' => 0,
			'parents' => [
				['serviceid' => self::$parent_serviceid]
			]
		]);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['serviceids']);
		self::$child2_serviceid = $response['result']['serviceids'][0];

		// Create child service
		$response = $this->call('service.create', [
			'name' => 'Child 1-1',
			'algorithm' => 1,
			'weight' => 0,
			'sortorder' => 0,
			'parents' => [
				['serviceid' => self::$child1_serviceid]
			],
			'tags' => [
				[
					'tag' => 'c11tag',
					'value' => 'c11value'
				],
				[
					'tag' => 'c11tagwovalue'
				]
			],
			'problem_tags' => [
				[
					'tag' => 'ServiceLink',
					'operator' => 0,
					'value' => self::$triggerid . ':' . $trigger_desc
				]
			]
		]);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['serviceids']);
		self::$child1_1_serviceid = $response['result']['serviceids'][0];

		// Create a role
		$response = $this->call('role.create', [
			"name" => "test role",
			"type" => USER_TYPE_ZABBIX_USER,
			"rules" => [
				'services.read.mode' => 0,
				'services.write.mode' => 0
			]
		]);
		$this->assertArrayHasKey('roleids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['roleids']);
		self::$roleid = $response['result']['roleids'][0];

		// Create a user
		$response = $this->call('user.create', [
			'username' => 'John',
			'passwd' => 'Doe123123',
			'roleid' => self::$roleid,
			'usrgrps' => [['usrgrpid' => 8]]

		]);
		$this->assertArrayHasKey('userids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['userids']);
		self::$userid = $response['result']['userids'][0];

		// Create an action
		$response = $this->call('action.create', [
			'name' => 'Sample service status changed',
			'eventsource' => EVENT_SOURCE_SERVICE,
			'status' => 0,
			'esc_period' => '1h',
			'filter' => [
				'evaltype' => 0,
				'conditions' => [
					[
						'conditiontype' => 27,
						'operator' => 0,
						'value' => self::$child1_1_serviceid
					]
				]
			],
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 0,
						'message' => 'Problem',
						'subject' => 'Problem'
					],
					'opmessage_grp' => [['usrgrpid' => 7]],
					'opmessage_usr' => [['userid' => self::$userid]]
				]
			],
			'recovery_operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 0,
						'message' => 'Recovery',
						'subject' => 'Recovery'
					],
					'opmessage_grp' => [['usrgrpid' => 7]],
					'opmessage_usr' => [['userid' => self::$userid]]
				]
			],
			'update_operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 0,
						'message' => 'Update',
						'subject' => 'Update'
					],
					'opmessage_grp' => [['usrgrpid' => 7]],
					'opmessage_usr' => [['userid' => self::$userid]]
				]
			]
		]
		);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['actionids']);
		self::$actionid = $response['result']['actionids'][0];

		return true;
	}

	/**
	 * Component configuration provider for agent related tests.
	 *
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20
			]
		];
	}

	/**
	 * services.read.0 / services.read.1 check
	 *
	 * @backup alerts, history, history_uint, role_rule, events, problem
	 */
	public function testServiceRoles_case1() {
		$this->clearLog(self::COMPONENT_SERVER);
		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOSTNAME, self::TRAPPER_ITEM_NAME, 1);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 60, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 30, 3);

		$response = $this->call('alert.get', [
			'output' => 'extend',
			'actionsids' => self::$actionid,
			'eventsource' => EVENT_SOURCE_SERVICE,
			'eventobject' => EVENT_OBJECT_SERVICE,
			'userids' => [self::$userid]
		]);
		$this->assertEmpty($response['result']);

		$response = $this->call('role.update', [
			'roleid' => self::$roleid,
			"rules" => [
				'services.read.mode' => 1,
				'services.write.mode' => 0
			]
		]);
		$this->reloadConfigurationCache();
		$this->sendSenderValue(self::HOSTNAME, self::TRAPPER_ITEM_NAME, 0);
		$this->sendSenderValue(self::HOSTNAME, self::TRAPPER_ITEM_NAME, 1);

		$response = $this->call('alert.get', [
			'output' => 'extend',
			'actionsids' => self::$actionid,
			'eventsource' => EVENT_SOURCE_SERVICE,
			'eventobject' => EVENT_OBJECT_SERVICE,
			'userids' => [self::$userid]
		]);
		$this->assertCount(2, $response['result']);

		return true;
	}

	/**
	 * service.read.tag - check own tag (with value) of a service
	 *
	 * @backup alerts, history, history_uint, role_rule, events, problem
	 */
	public function testServiceRoles_case2() {
		$this->clearLog(self::COMPONENT_SERVER);
		$response = $this->call('role.update', [
			'roleid' => self::$roleid,
			"rules" => [
				'services.read.mode' => 0,
				'services.read.tag' => [
					'tag' => 'c11tag',
					'value' => 'c11value'
				]
			]
		]);
		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOSTNAME, self::TRAPPER_ITEM_NAME, 1);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 60, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 30, 3);

		$response = $this->call('alert.get', [
			'output' => 'extend',
			'actionsids' => self::$actionid,
			'eventsource' => EVENT_SOURCE_SERVICE,
			'eventobject' => EVENT_OBJECT_SERVICE,
			'userids' => [self::$userid]
		]);
		$this->assertCount(1, $response['result']);

		return true;
	}

	/**
	 * service.read.tag - check own tag (without value) of a service
	 *
	 * @backup alerts, history, history_uint, role_rule, events, problem
	 */
	public function testServiceRoles_case3() {
		$this->clearLog(self::COMPONENT_SERVER);
		$response = $this->call('role.update', [
			'roleid' => self::$roleid,
			"rules" => [
				'services.read.mode' => 0,
				'services.read.tag' => [
					'tag' => 'c11tagwovalue'
				]
			]
		]);
		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOSTNAME, self::TRAPPER_ITEM_NAME, 1);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 60, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 30, 3);

		$response = $this->call('alert.get', [
			'output' => 'extend',
			'actionsids' => self::$actionid,
			'eventsource' => EVENT_SOURCE_SERVICE,
			'eventobject' => EVENT_OBJECT_SERVICE,
			'userids' => [self::$userid]
		]);
		$this->assertCount(1, $response['result']);

		return true;
	}

	/**
	 * service.read.tag - check tag (with value) of a parent service
	 *
	 * @backup alerts, history, history_uint, role_rule, events, problem
	 */
	public function testServiceRoles_case4() {
		$this->clearLog(self::COMPONENT_SERVER);
		$response = $this->call('role.update', [
			'roleid' => self::$roleid,
			"rules" => [
				'services.read.mode' => 0,
				'services.read.tag' => [
					'tag' => 'c1tag1',
					'value' => 'c1tag1value'
				]
			]
		]);
		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOSTNAME, self::TRAPPER_ITEM_NAME, 1);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 60, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 30, 3);

		$response = $this->call('alert.get', [
			'output' => 'extend',
			'actionsids' => self::$actionid,
			'eventsource' => EVENT_SOURCE_SERVICE,
			'eventobject' => EVENT_OBJECT_SERVICE,
			'userids' => [self::$userid]
		]);
		$this->assertCount(1, $response['result']);

		return true;
	}

	/**
	 * service.read.tag - check tag (without value) of a parent service
	 *
	 * @backup alerts,history,history_uint,role_rule,events,problem
	 */
	public function testServiceRoles_case5() {
		$this->clearLog(self::COMPONENT_SERVER);
		$response = $this->call('role.update', [
			'roleid' => self::$roleid,
			"rules" => [
				'services.read.mode' => 0,
				'services.read.tag' => [
					'tag' => 'c1tagwovalue',
					'value' => ''
				]
			]
		]);
		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOSTNAME, self::TRAPPER_ITEM_NAME, 1);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 60, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 30, 3);

		$response = $this->call('alert.get', [
			'output' => 'extend',
			'actionsids' => self::$actionid,
			'eventsource' => EVENT_SOURCE_SERVICE,
			'eventobject' => EVENT_OBJECT_SERVICE,
			'userids' => [self::$userid]
		]);
		$this->assertCount(1, $response['result']);

		return true;
	}

	/**
	 * service.read.list - check distant parent service
	 *
	 * @backup alerts,history,history_uint,role_rule,events,problem
	 */
	public function testServiceRoles_case6() {
		$this->clearLog(self::COMPONENT_SERVER);
		$response = $this->call('role.update', [
			'roleid' => self::$roleid,
			"rules" => [
				'services.read.mode' => 0,
				'services.read.tag' => [
					'tag' => '',
					'value' => ''
				],
				'services.read.list' => [
					['serviceid' => self::$child1_serviceid]
				]
			]
		]);
		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOSTNAME, self::TRAPPER_ITEM_NAME, 1);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 60, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 30, 3);

		$response = $this->call('alert.get', [
			'output' => 'extend',
			'actionsids' => self::$actionid,
			'eventsource' => EVENT_SOURCE_SERVICE,
			'eventobject' => EVENT_OBJECT_SERVICE,
			'userids' => [self::$userid]
		]);
		$this->assertCount(1, $response['result']);

		return true;
	}

	/**
	 * service.read.list / services.write.list - check retrieving and editing
	 *
	 * @backup role_rule,services
	 */
	public function testServiceRoles_case7() {
		// Read enabled, write enabled

		$response = $this->call('role.update', [
			'roleid' => self::$roleid,
			"rules" => [
				'services.read.mode' => 0,
				'services.write.mode' => 0,
				'services.read.list' => [
					['serviceid' => self::$parent_serviceid]
				],
				'services.write.list' => [
					['serviceid' => self::$parent_serviceid]
				]
			]
		]);

		$response = $this->call('user.logout', []);
		$this->authorize('John', 'Doe123123');

		$response = $this->call('service.update', [
			'serviceid' => self::$parent_serviceid,
			'sortorder' => 1
		]);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertCount(1, $response['result']['serviceids']);

		$response = $this->call('user.logout', []);

		// Read enabled, write disabled
		$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		$response = $this->call('role.update', [
			'roleid' => self::$roleid,
			"rules" => [
				'services.read.mode' => 0,
				'services.write.mode' => 0,
				'services.read.list' => [
					['serviceid' => self::$parent_serviceid]
				],
				'services.write.list' => []
			]
		]);

		$response = $this->call('user.logout', []);
		$this->authorize('John', 'Doe123123');

		$response = $this->call('service.update', [
			'serviceid' => self::$parent_serviceid,
			'sortorder' => 1
		], 'Cannot update service "Parent": read-write access to the service is required.');

		$response = $this->call('service.get', [
			'serviceids' => [self::$parent_serviceid]
		]);
		$this->assertArrayHasKey(0, $response['result']);

		$response = $this->call('user.logout', []);

		// Read disabled, write disabled
		$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		$response = $this->call('role.update', [
			'roleid' => self::$roleid,
			"rules" => [
				'services.read.mode' => 0,
				'services.write.mode' => 0,
				'services.read.list' => [],
				'services.write.list' => []
			]
		]);

		$response = $this->call('user.logout', []);
		$this->authorize('John', 'Doe123123');

		$response = $this->call('service.get', [
			'serviceids' => [self::$parent_serviceid]
		]);
		$this->assertEmpty($response['result']);

		return true;
	}

	/**
	 * service.read.mode / services.write.mode - check retrieving and editing
	 *
	 * @backup role_rule,services
	 */
	public function testServiceRoles_case8() {
		// Read enabled, write enabled

		$response = $this->call('role.update', [
			'roleid' => self::$roleid,
			"rules" => [
				'services.read.mode' => 1,
				'services.write.mode' => 1
			]
		]);

		$response = $this->call('user.logout', []);
		$this->authorize('John', 'Doe123123');

		$response = $this->call('service.update', [
			'serviceid' => self::$parent_serviceid,
			'sortorder' => 1
		]);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertCount(1, $response['result']['serviceids']);

		$response = $this->call('user.logout', []);

		// Read enabled, write disabled
		$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		$response = $this->call('role.update', [
			'roleid' => self::$roleid,
			"rules" => [
				'services.read.mode' => 1,
				'services.write.mode' => 0
			]
		]);

		$response = $this->call('user.logout', []);
		$this->authorize('John', 'Doe123123');

		$response = $this->call('service.update', [
			'serviceid' => self::$parent_serviceid,
			'sortorder' => 1
		], 'Cannot update service "Parent": read-write access to the service is required.');

		$response = $this->call('service.get', [
			'serviceids' => [self::$parent_serviceid]
		]);
		$this->assertArrayHasKey(0, $response['result']);

		$response = $this->call('user.logout', []);

		// Read disabled, write disabled
		$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		$response = $this->call('role.update', [
			'roleid' => self::$roleid,
			"rules" => [
				'services.read.mode' => 0,
				'services.write.mode' => 0,
				'services.read.list' => [],
				'services.write.list' => []
			]
		]);

		$response = $this->call('user.logout', []);
		$this->authorize('John', 'Doe123123');

		$response = $this->call('service.get', [
			'serviceids' => [self::$parent_serviceid]
		]);
		$this->assertEmpty($response['result']);

		return true;
	}

	/**
	 * service.read.tag / services.write.tag - check retrieving and editing
	 *
	 * @backup role_rule,services
	 */
	public function testServiceRoles_case9() {
		// Read enabled, write enabled

		$response = $this->call('role.update', [
			'roleid' => self::$roleid,
			"rules" => [
				'services.read.mode' => 0,
				'services.write.mode' => 0,
				'services.read.tag' => [
					'tag' => 'c1tag1',
					'value' => 'c1tag1value'
				],
				'services.write.tag' => [
					'tag' => 'c1tag1',
					'value' => 'c1tag1value'
				]
			]
		]);

		$response = $this->call('service.update', [
			'serviceid' => self::$child1_1_serviceid,
			'sortorder' => 1
		]);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertCount(1, $response['result']['serviceids']);

		// Read enabled, write disabled
		$response = $this->call('role.update', [
			'roleid' => self::$roleid,
			"rules" => [
				'services.read.mode' => 0,
				'services.write.mode' => 0,
				'services.read.tag' => [
					'tag' => 'c1tag1',
					'value' => 'c1tag1value'
				],
				'services.write.tag' => [
					'tag' => '',
					'value' => ''
				]
			]
		]);

		$response = $this->call('user.logout', []);
		$this->authorize('John', 'Doe123123');

		$response = $this->call('service.update', [
			'serviceid' => self::$child1_1_serviceid,
			'sortorder' => 1
		], 'Cannot update service "Child 1-1": read-write access to the service is required.');

		$response = $this->call('service.get', [
			'serviceids' => [self::$child1_1_serviceid]
		]);
		$this->assertArrayHasKey(0, $response['result']);

		$response = $this->call('user.logout', []);

		// Read enabled, write disabled (tag without value)
		$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		$response = $this->call('role.update', [
			'roleid' => self::$roleid,
			"rules" => [
				'services.read.mode' => 0,
				'services.write.mode' => 0,
				'services.read.tag' => [
					'tag' => 'c1tagwovalue',
					'value' => ''
				],
				'services.write.tag' => [
					'tag' => '',
					'value' => ''
				]
			]
		]);

		$response = $this->call('user.logout', []);
		$this->authorize('John', 'Doe123123');

		$response = $this->call('service.update', [
			'serviceid' => self::$child1_1_serviceid,
			'sortorder' => 1
		], 'Cannot update service "Child 1-1": read-write access to the service is required.');

		$response = $this->call('service.get', [
			'serviceids' => [self::$child1_1_serviceid]
		]);
		$this->assertArrayHasKey(0, $response['result']);

		$response = $this->call('user.logout', []);

		// Read disabled, write disabled
		$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		$response = $this->call('role.update', [
			'roleid' => self::$roleid,
			"rules" => [
				'services.read.mode' => 0,
				'services.write.mode' => 0,
				'services.read.tag' => [
					'tag' => '',
					'value' => ''
				],
				'services.write.tag' => [
					'tag' => '',
					'value' => ''
				]
			]
		]);

		$response = $this->call('user.logout', []);
		$this->authorize('John', 'Doe123123');

		$response = $this->call('service.get', [
			'serviceids' => [self::$child1_1_serviceid]
		]);
		$this->assertEmpty($response['result']);

		return true;
	}
}

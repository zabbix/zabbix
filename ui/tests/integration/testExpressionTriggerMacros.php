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

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';

/**
 * Test suite for action notifications
 *
 * @required-components server
 * @backup items,actions,triggers,alerts,hosts,users,scripts,history_uint
 * @hosts test_actions
 */
class testExpressionTriggerMacros extends CIntegrationTest {

	private static $hostid;
	private static $triggerid;
	private static $trigger_actionid;
	private static $eventid;
	private static $userid;
	private static $scriptid;
	private static $scriptid_recovery;
	private static $macroid;
	private static $interfaceid;

	const ITEM_NAME_1 = 'trap1';
	const ITEM_NAME_2 = 'trap2';
	const HOST_NAME = 'test_etmacros';

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "testhost".
		$response = $this->call('host.create', [
			'host' => self::HOST_NAME,
			'interfaces' => [
				[
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				]
			],
			'groups' => [
				[
					'groupid' => 4
				]
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		$response = $this->call('usermacro.createglobal', [
			'macro' => '{$GLOBMACRO}',
			'value' => 'abc'
		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('globalmacroids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['globalmacroids']);
		self::$macroid =  $response['result']['globalmacroids'][0];


		// Create trapper item
		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => self::ITEM_NAME_1,
			'key_' => self::ITEM_NAME_1,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => self::ITEM_NAME_2,
			'key_' => self::ITEM_NAME_2,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		// Create trigger
		$expr_last1 = 'last(/' . self::HOST_NAME . '/' . self::ITEM_NAME_1 . ')';
		$expr_last2 = 'last(/' . self::HOST_NAME . '/' . self::ITEM_NAME_2 . ')';
		$response = $this->call('trigger.create', [
			'description' => 'Fired {TRIGGER.EXPRESSION.EXPLAIN} {FUNCTION.VALUE1} {FUNCTION.VALUE2} {{TRIGGER.EXPRESSION.EXPLAIN}.regsub(max,\0)} {{$GLOBMACRO}.regsub(b,\0)}',
			'expression' => '(' . $expr_last1 . '+' . $expr_last2 . ')>max(10,100)',
			'recovery_mode' => 1,
			'recovery_expression' => '(' . $expr_last1 . '+' . $expr_last2 . ')<10'
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertEquals(1, count($response['result']['triggerids']));
		self::$triggerid = $response['result']['triggerids'][0];

		// Create trigger action
		$response = $this->call('action.create', [
			'esc_period' => '1m',
			'eventsource' => EVENT_SOURCE_TRIGGERS,
			'status' => 0,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => self::$triggerid
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'name' => 'action_trigger_trap',
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 1,
						'subject' => '{TRIGGER.EXPRESSION.EXPLAIN}',
						'message' => '{FUNCTION.VALUE1} {FUNCTION.VALUE2} {{TRIGGER.EXPRESSION.EXPLAIN}.regsub(max,\0)} {{$GLOBMACRO}.regsub(b,\0)}'
						],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			],
			'pause_suppressed' => 0,
			'update_operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 0,
						'subject' => 'Update {TRIGGER.EXPRESSION.EXPLAIN}',
						'message' => 'Update {FUNCTION.VALUE1} {FUNCTION.VALUE2} {{TRIGGER.EXPRESSION.EXPLAIN}.regsub(max,\0)} {{$GLOBMACRO}.regsub(b,\0)}'
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			],
			'recovery_operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 0,
						'subject' => '{TRIGGER.EXPRESSION.EXPLAIN}',
						'message' => '{FUNCTION.VALUE1} {FUNCTION.VALUE2} {{TRIGGER.EXPRESSION.EXPLAIN}.regsub(max,\0)} {{$GLOBMACRO}.regsub(b,\0)}'
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
		self::$trigger_actionid = $response['result']['actionids'][0];

		$response = $this->call('user.create', [
			'username' => 'test',
			'passwd' => 'p@sSw0rd',
			'usrgrps' => [
				["usrgrpid" => 7]
			],
			'roleid' => 3
		]);
		$this->assertArrayHasKey('userids', $response['result']);
		self::$userid = $response['result']['userids'][0];

		return true;
	}

	private function createScripts() {
		$response = $this->call('script.create', [
			'name' => 'explain/function value test script',
			'command' => 'echo -n "{TRIGGER.EXPRESSION.EXPLAIN} {FUNCTION.VALUE1} {FUNCTION.VALUE2} {{TRIGGER.EXPRESSION.EXPLAIN}.regsub(max,\0)} {{$GLOBMACRO}.regsub(b,\0)}"',
			'execute_on' => 1,
			'scope' => 4,
			'type' => 0
		]);
		$this->assertArrayHasKey('scriptids', $response['result']);
		self::$scriptid = $response['result']['scriptids'][0];

		$response = $this->call('script.create', [
			'name' => 'explain/function value test script (recovery)',
			'command' => 'echo -n "{TRIGGER.EXPRESSION.EXPLAIN} {FUNCTION.VALUE1} {FUNCTION.VALUE2} {{TRIGGER.EXPRESSION.EXPLAIN}.regsub(max,\0)} {{$GLOBMACRO}.regsub(b,\0)}"',
			'execute_on' => 1,
			'scope' => 4,
			'type' => 0
		]);
		$this->assertArrayHasKey('scriptids', $response['result']);
		self::$scriptid_recovery = $response['result']['scriptids'][0];

	}

	/**
	 * Component configuration provider for server.
	 *
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'EnableGlobalScripts' => 1
			]
		];
	}

	/**
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testExpressionTriggerMacros_testOperation() {
		$this->createScripts();
		$this->clearLog(self::COMPONENT_SERVER);
		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOST_NAME, self::ITEM_NAME_1, 51);
		$this->sendSenderValue(self::HOST_NAME, self::ITEM_NAME_2, 50);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 120);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 10, 3);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'actionids' => [self::$trigger_actionid],
			'output' => 'extend'
		], 5, 2);
		$this->assertArrayHasKey(0, $response['result']);
		self::$eventid = $response['result'][0]['eventid'];
		$this->assertEquals('(51+50)>max(10,100)', $response['result'][0]['subject']);
		$this->assertEquals('51 50 max b', $response['result'][0]['message']);
	}

	/**
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testExpressionTriggerMacros_checkManualEventActionScript() {
		$response = $this->callUntilDataIsPresent('script.execute', [
			'scriptid' => self::$scriptid,
			'eventid' => self::$eventid
		], 5, 2);
		$this->assertArrayHasKey('value', $response['result']);
		$this->assertEquals('(51+50)>max(10,100) 51 50 max b', $response['result']['value']);
	}

	/**
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testExpressionTriggerMacros_checkEventName() {
		$response = $this->call('event.get', [
			'eventids' => [self::$eventid]
		]);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals('Fired (51+50)>max(10,100) 51 50 max b', $response['result'][0]['name']);
	}

	/**
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testExpressionTriggerMacros_testUpdateOperation() {
		$this->clearLog(self::COMPONENT_SERVER);

		$response = $this->callUntilDataIsPresent('event.acknowledge', [
			'eventids' => [self::$eventid],
			'action' => ZBX_PROBLEM_UPDATE_ACKNOWLEDGE
		], 5, 2);
		$this->assertArrayHasKey('eventids', $response['result']);
		$this->assertEquals(self::$eventid, $response['result']['eventids'][0]);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute_update_operations()', true, 120);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute_update_operations()', true, 10, 3);

		$response = $this->call('alert.get', [
			'output' => 'extend',
			'userids' => [self::$userid]
		]);
		$this->assertArrayHasKey(1, $response['result']);
		$this->assertEquals('Update (51+50)>max(10,100)', $response['result'][1]['subject']);
		$this->assertEquals('Update 51 50 max b', $response['result'][1]['message']);
	}

	/**
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testExpressionTriggerMacros_testRecoveryOperation() {
		$this->clearLog(self::COMPONENT_SERVER);
		$this->sendSenderValue(self::HOST_NAME, self::ITEM_NAME_1, 1);
		$this->sendSenderValue(self::HOST_NAME, self::ITEM_NAME_2, 2);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute_recovery_operations()', true, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute_recovery_operations()', true, 3, 3);

		$response = $this->call('alert.get', [
			'actionids' => [self::$trigger_actionid],
			'output' => 'extend'
		]);
		$this->assertArrayHasKey(2, $response['result']);

		$this->assertEquals('(1+2)>max(10,100)', $response['result'][4]['subject']);
		$this->assertEquals('1 2 max b', $response['result'][4]['message']);
	}

	/**
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testExpressionTriggerMacros_checkManualEventActionScript_recovery() {
		$response = $this->callUntilDataIsPresent('script.execute', [
			'scriptid' => self::$scriptid_recovery,
			'eventid' => self::$eventid
		], 5, 2);
		$this->assertArrayHasKey('value', $response['result']);
		$this->assertEquals('(1+2)>max(10,100) 1 2 max b', $response['result']['value']);

		$response = $this->call('usermacro.deleteglobal', (array)self::$macroid );
	}
}

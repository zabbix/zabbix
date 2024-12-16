<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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
 * @configurationDataProvider serverConfigurationProvider
 * @backup items,actions,triggers,alerts
 * @hosts test_actions
 */
class testEscalations extends CIntegrationTest {

	private static $hostid;
	private static $triggerid;
	private static $maint_start_tm;
	private static $trigger_actionid;
	private static $scriptid_problem;
	private static $scriptid_recovery;

	const TRAPPER_ITEM_NAME = 'trap';
	const HOST_NAME = 'test_actions';
	const COMMAND_PROBLEM = 'echo "problem"';
	const COMMAND_RECOVERY = 'echo "recovery"';

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "testhost".
		$response = $this->call('host.create', [
			'host' => self::HOST_NAME,
			'interfaces' => [
				'type' => 1,
				'main' => 1,
				'useip' => 1,
				'ip' => '127.0.0.1',
				'dns' => '',
				'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
			],
			'groups' => ['groupid' => 4]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		// Get host interface ids.
		$response = $this->call('host.get', [
			'output' => ['host'],
			'hostids' => [self::$hostid],
			'selectInterfaces' => ['interfaceid']
		]);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('interfaces', $response['result'][0]);
		$this->assertArrayHasKey(0, $response['result'][0]['interfaces']);

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
		$response = $this->call('trigger.create', [
			'description' => 'Trapper received 1',
			'expression' => 'last(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_NAME.')>0'
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertEquals(1, count($response['result']['triggerids']));
		self::$triggerid = $response['result']['triggerids'][0];

		// Create trigger action
		$response = $this->call('action.create', [
			'esc_period' => '1h',
			'eventsource' => EVENT_SOURCE_TRIGGERS,
			'status' => ACTION_STATUS_ENABLED,
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
			'name' => 'Trapper received 1 (problem) clone',
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => ['default_msg' => 1,
									'mediatypeid' => 0
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			],
			'pause_suppressed' => 0,
			'recovery_operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 1,
						'mediatypeid' => 0
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

		// Enable mediatypes
		$response = $this->call('mediatype.update', [
			'mediatypeid' => 1,
			'status' => 0
		]);
		$this->assertArrayHasKey('mediatypeids', $response['result']);
		$this->assertEquals(1, count($response['result']['mediatypeids']));

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
	 * Component configuration provider for remote command related tests.
	 *
	 * @return array
	 */
	public function serverConfigurationProviderRemote() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'Timeout' => 30
			],
			self::COMPONENT_AGENT => [
				'Hostname' => self::HOST_NAME,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER,
						'ListenPort', 10051),
				'AllowKey' => 'system.run[*]',
				'LogRemoteCommands' => 1
			]
		];
	}

	/**
	 * @backup actions,alerts,history_uint,history,problem,events
	 */
	public function testEscalations_disabledAction() {
		$this->clearLog(self::COMPONENT_SERVER);
		$response = $this->call('action.update', [
			'actionid' => self::$trigger_actionid,
			'status' => 1
		]);

		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));

		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 1);

		// Check if there are no alerts for this action
		$response = $this->call('alert.get', [
			'actionids' => [self::$trigger_actionid]
		]);
		$this->assertEmpty($response['result']);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);
	}

	/**
	 * @backup alerts,triggers,history_uint,history,problem,events
	 */
	public function testEscalations_disabledTrigger() {
		$this->clearLog(self::COMPONENT_SERVER);
		$response = $this->call('trigger.update', [
			'triggerid' => self::$triggerid,
			'status' => 1
		]);

		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertEquals(1, count($response['result']['triggerids']));

		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 2);

		// Check if there are no alerts for this action
		$response = $this->call('alert.get', [
			'actionids' => [self::$trigger_actionid]
		]);
		$this->assertEmpty($response['result']);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);
	}

	/**
	 * Test maintenance scenario:
	 *   disable pause_suppressed
	 *   maintenance on
	 *   event -> alert
	 *   recovery -> alert
	 *
	 * @backup alerts,history,history_uint,maintenances,events,problem
	 */
	public function testEscalations_checkScenario1() {
		$this->clearLog(self::COMPONENT_SERVER);
		$this->reloadConfigurationCache();
		// Create maintenance period
		self::$maint_start_tm = time();
		$maint_end_tm = self::$maint_start_tm + 60 * 2;

		$response = $this->call('maintenance.create', [
			'name' => 'Test maintenance',
			'hosts' => ['hostid' => self::$hostid],
			'active_since' => self::$maint_start_tm,
			'active_till' => $maint_end_tm,
			'tags_evaltype' => MAINTENANCE_TAG_EVAL_TYPE_AND_OR,
			'timeperiods' => [
				'timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
				'period' => 300,
				'start_date' => self::$maint_start_tm
			]
		]);
		$this->assertArrayHasKey('maintenanceids', $response['result']);
		$this->assertEquals(1, count($response['result']['maintenanceids']));
		$maintenance_id = $response['result']['maintenanceids'][0];

		$this->reloadConfigurationCache();
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_dc_update_maintenances()', true);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 3);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'actionids' => [self::$trigger_actionid]
		], 5, 2);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(0, $response['result'][0]['p_eventid']);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 120);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 10, 3);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'actionids' => [self::$trigger_actionid],
			'sortfield' => 'alertid'
		], 5, 2);
		$this->assertArrayHasKey(1, $response['result']);
		$this->assertNotEquals(0, $response['result'][1]['p_eventid']);
	}

	/**
	 * Test maintenance scenario:
	 *   event -> alert
	 *   maintenance on
	 *   recovery -> alert
	 *
	 * @backup actions,alerts,history_uint,maintenances
	 */
	public function testEscalations_checkScenario2() {
		$this->clearLog(self::COMPONENT_SERVER);
		$response = $this->call('action.update', [
			'actionid' => self::$trigger_actionid,
			'pause_suppressed' => 1
		]);

		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 4);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 120);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 10, 3);

		// Create maintenance period
		self::$maint_start_tm = time() + 10;
		$maint_end_tm = self::$maint_start_tm + 60 * 2;

		$response = $this->call('maintenance.create', [
			'name' => 'Test maintenance',
			'hosts' => ['hostid' => self::$hostid],
			'active_since' => self::$maint_start_tm,
			'active_till' => $maint_end_tm,
			'tags_evaltype' => MAINTENANCE_TAG_EVAL_TYPE_AND_OR,
			'timeperiods' => [
				'timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
				'period' => 300,
				'start_date' => self::$maint_start_tm
			]
		]);
		$this->assertArrayHasKey('maintenanceids', $response['result']);
		$this->assertEquals(1, count($response['result']['maintenanceids']));
		$maintenance_id = $response['result']['maintenanceids'][0];

		$this->reloadConfigurationCache();

		$response = $this->callUntilDataIsPresent('alert.get', [
			'actionids' => [self::$trigger_actionid]
		], 5, 2);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(0, $response['result'][0]['p_eventid']);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_dc_update_maintenances() started:1 stopped:0 running:1', true);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_recover()', true, 120);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_recover()', true);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'actionids' => [self::$trigger_actionid],
			'sortfield' => 'alertid'
		], 5, 2);
		$this->assertArrayHasKey(1, $response['result']);
		$this->assertNotEquals(0, $response['result'][1]['p_eventid']);
	}

	/**
	 * Test maintenance scenario:
	 *   maintenance on
	 *   event -> nothing
	 *   maintenance off -> alert
	 *   recovery -> alert
	 *
	 * @backup actions,alerts,history_uint,maintenances
	 */
	public function testEscalations_checkScenario3() {
		$this->clearLog(self::COMPONENT_SERVER);
		$response = $this->call('action.update', [
			'actionid' => self::$trigger_actionid,
			'pause_suppressed' => 1
		]);
		// Create maintenance period
		self::$maint_start_tm = time();
		$maint_end_tm = self::$maint_start_tm + 60 * 2;

		$response = $this->call('maintenance.create', [
			'name' => 'Test maintenance',
			'hosts' => ['hostid' => self::$hostid],
			'active_since' => self::$maint_start_tm,
			'active_till' => $maint_end_tm,
			'tags_evaltype' => MAINTENANCE_TAG_EVAL_TYPE_AND_OR,
			'timeperiods' => [
				'timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
				'period' => 300,
				'start_date' => self::$maint_start_tm
			]
		]);
		$this->assertArrayHasKey('maintenanceids', $response['result']);
		$this->assertEquals(1, count($response['result']['maintenanceids']));
		$maintenance_id = $response['result']['maintenanceids'][0];

		$this->reloadConfigurationCache();
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_dc_update_maintenances() started:1 stopped:0 running:1', true);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 5);

		$response = $this->call('alert.get', [
			'actionids' => [self::$trigger_actionid]
		]);
		$this->assertEmpty($response['result']);

		$response = $this->call('maintenance.delete', [
			$maintenance_id
		]);
		$this->assertArrayHasKey('maintenanceids', $response['result']);
		$this->assertEquals($maintenance_id, $response['result']['maintenanceids'][0]);
		$this->reloadConfigurationCache();

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In zbx_dc_update_maintenances()|In escalation_execute()', true, 120, null, true);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_dc_update_maintenances()|End of escalation_execute()', true, null, 3, true);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()|In zbx_dc_update_maintenances()', true, 120, null, true);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()|End of zbx_dc_update_maintenances()', true, null, 3, true);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'actionids' => [self::$trigger_actionid]
		], 15, 5);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(0, $response['result'][0]['p_eventid']);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_recover()', true, 120);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_recover()', true);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'actionids' => [self::$trigger_actionid],
			'sortfield' => 'alertid'
		], 5, 2);
		$this->assertArrayHasKey(1, $response['result']);
		$this->assertNotEquals(0, $response['result'][1]['p_eventid']);
	}

	/**
	 * Test cancelled escalation (disabled trigger)
	 *
	 * @backup actions,alerts,events,problem,history_uint,hosts,users
	 */
	public function testEscalations_checkScenario4() {
		$this->clearLog(self::COMPONENT_SERVER);
		$response = $this->call('action.update', [
			'actionid' => self::$trigger_actionid,
			'operations' => [
				[
					'esc_period' => '1m',
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'evaltype' => 0,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 1,
						'message' => 'Problem',
						'subject' => 'Problem'
					],
					'opmessage_grp' => [['usrgrpid' => 7]]
				],
				[
					'esc_period' => '1m',
					'esc_step_from' => 2,
					'esc_step_to' => 2,
					'evaltype' => 0,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 1,
						'message' => 'Problem',
						'subject' => 'Problem'
					],
					'opmessage_grp' => [['usrgrpid' => 7]]
				]
			]
		]);

		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['actionids']);
		$response = $this->call('user.update', [
			'userid' => 1,
			'medias' => [
				[
					'mediatypeid' => 1,
					'sendto' => 'test@local.local'
				]
			]
		]);
		$this->assertArrayHasKey('userids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['userids']);

		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 6);

		$response = $this->call('trigger.update', [
			'triggerid' => self::$triggerid,
			'status' => 1
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertEquals(1, count($response['result']['triggerids']));

		$this->reloadConfigurationCache();

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_cancel()', true, 120);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'actionids' => [self::$trigger_actionid],
			'sortfield' => 'alertid'
		], 5, 2);
		$esc_msg = 'NOTE: Escalation canceled';
		$this->assertArrayHasKey(1, $response['result']);
		$this->assertEquals(0, strncmp($esc_msg, $response['result'][1]['message'], strlen($esc_msg)));

		// trigger value is not updated during configuration cache sync (only initialized)
		// therefore need to restore it manually by sending OK value
		$response = $this->call('trigger.update', [
			'triggerid' => self::$triggerid,
			'status' => 0
		]);

		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);

		// test ability to disable notifications about cancelled escalations
		$response = $this->call('action.update', [
			'actionid' => self::$trigger_actionid,
			'notify_if_canceled' => 0
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['actionids']);

		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 10);

		$response = $this->call('trigger.update', [
			'triggerid' => self::$triggerid,
			'status' => 1
		]);

		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertEquals(1, count($response['result']['triggerids']));

		$this->reloadConfigurationCache();

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_cancel()', true, 120);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'actionids' => [self::$trigger_actionid],
			'sortfield' => 'alertid',
			'sortorder' => 'DESC'
		], 5, 2);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertNotEquals(0, strncmp($esc_msg, $response['result'][0]['message'], strlen($esc_msg)));

		// revert to defaults, restore trigger status and value
		$response = $this->call('action.update', [
			'actionid' => self::$trigger_actionid,
			'notify_if_canceled' => 1
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['actionids']);

		$response = $this->call('trigger.update', [
			'triggerid' => self::$triggerid,
			'status' => 0
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertEquals(1, count($response['result']['triggerids']));

		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);
	}

	/**
	 * Test normal escalation with multiple escalations steps
	 *
	 * @backup alerts,actions
	 */
	public function testEscalations_checkScenario5() {
		$this->clearLog(self::COMPONENT_SERVER);
		$this->reloadConfigurationCache();

		$response = $this->call('action.update', [
			'actionid' => self::$trigger_actionid,
			'esc_period' => '1m',
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'evaltype' => 0,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 0,
						'message' => 'Cause problem 1',
						'subject' => 'Cause problem 1'
					],
					'opmessage_grp' => [['usrgrpid' => 7]]
				],
				[
					'esc_period' => 0,
					'esc_step_from' => 2,
					'esc_step_to' => 2,
					'evaltype' => 0,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 0,
						'message' => 'Cause problem 2',
						'subject' => 'Cause problem 2'
					],
					'opmessage_grp' => [['usrgrpid' => 7]]
				]
			]
		]);

		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['actionids']);

		// create items, triggers, actions for testing symptom events
		$pause_symptoms = [
			1 => ACTION_PAUSE_SYMPTOMS_TRUE,
			2 => ACTION_PAUSE_SYMPTOMS_FALSE
		];
		$trapper_keys = [];
		$symptom_triggerids = [];
		$symptom_actionids = [];

		foreach ([1, 2] as $i) {
			// Create trapper item
			$name = $key = self::TRAPPER_ITEM_NAME."_s".(string)$i;
			$trapper_keys[$i] = $key;
			$response = $this->call('item.create', [
				'hostid' => self::$hostid,
				'name' => $name,
				'key_' => $key,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			]);
			$this->assertArrayHasKey('itemids', $response['result']);
			$this->assertCount(1, $response['result']['itemids']);

			// Create trigger
			$response = $this->call('trigger.create', [
				'description' => 'Mandatory description',
				'expression' => 'last(/'.self::HOST_NAME.'/'.$key.')>0'
			]);
			$this->assertArrayHasKey('triggerids', $response['result']);
			$this->assertCount(1, $response['result']['triggerids']);
			$symptom_triggerids[$i] = $response['result']['triggerids'][0];

			// Create action
			$response = $this->call('action.create', [
				'esc_period' => '1m',
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'status' => ACTION_STATUS_ENABLED,
				'pause_symptoms' => $pause_symptoms[$i],
				'filter' => [
					'conditions' => [
						[
							'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => $symptom_triggerids[$i]
						]
					],
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR
				],
				'name' => 'Symptom action '.(string)$i,
				'operations' => [
					[
						'esc_period' => 0,
						'esc_step_from' => 1,
						'esc_step_to' => 1,
						'evaltype' => 0,
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'opmessage' => [
							'default_msg' => 0,
							'mediatypeid' => 0,
							'message' => 'Symptom problem 1',
							'subject' => 'Symptom problem 1'
						],
						'opmessage_grp' => [['usrgrpid' => 7]]
					],
					[
						'esc_period' => 0,
						'esc_step_from' => 2,
						'esc_step_to' => 2,
						'evaltype' => 0,
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'opmessage' => [
							'default_msg' => 0,
							'mediatypeid' => 0,
							'message' => 'Symptom problem 2',
							'subject' => 'Symptom problem 2'
						],
						'opmessage_grp' => [['usrgrpid' => 7]]
					]
				]
			]);

			$this->assertArrayHasKey('actionids', $response['result']);
			$this->assertCount(1, $response['result']['actionids']);
			$symptom_actionids[$i] = $response['result']['actionids'][0];
		}

		$this->reloadConfigurationCache();

		// start all events as causes, because only existing events can be ranked as symptoms
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 7);
		$this->sendSenderValue(self::HOST_NAME, $trapper_keys[1], 7);
		$this->sendSenderValue(self::HOST_NAME, $trapper_keys[2], 7);

		$response = $this->callUntilDataIsPresent('problem.get', [
			'output' => ['eventid'],
			'objectids' => [self::$triggerid, $symptom_triggerids[1], $symptom_triggerids[2]]
		]);

		$this->assertCount(3, $response['result']);
		$this->assertArrayHasKey('eventid', $response['result'][0]);
		$this->assertArrayHasKey('eventid', $response['result'][1]);
		$this->assertArrayHasKey('eventid', $response['result'][2]);

		$cause_eventid = $response['result'][0]['eventid'];

		// these events are causes at this point and will be ranked as symptoms later
		$symptom_eventids[1] = $response['result'][1]['eventid'];
		$symptom_eventids[2] = $response['result'][2]['eventid'];

		// wait until escalation step 1 is completed for all three events
		for ($i = 0; $i < 3; $i++) {
			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 95, 3);
			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 10, 3);
		}

		// rank 2 events as symptom events
		$response = $this->call('event.acknowledge', [
			'eventids' => $symptom_eventids,
			'action' => ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM,
			'cause_eventid' => $cause_eventid
		]);

		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('eventids', $response['result']);
		$this->assertCount(2, $response['result']['eventids']);

		// Escalations are expected for 2 events:
		//    1. the cause event;
		//    2. one symptom event with ACTION_PAUSE_SYMPTOMS_FALSE.
		for ($i = 0; $i < 2; $i++) {
			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 95, 3);
			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 10, 3);
		}

		// check alerts for the cause event
		$response = $this->callUntilDataIsPresent('alert.get', [
				'output' => 'extend',
				'actionids' => [self::$trigger_actionid],
				'sortfield' => 'alertid'
		], 5, 2);

		// 2 escalations are expected
		$this->assertCount(2, $response['result']);
		$this->assertEquals(1, $response['result'][0]['esc_step']);
		$this->assertEquals(2, $response['result'][1]['esc_step']);

		// check alerts for the symptom event with ACTION_PAUSE_SYMPTOMS_TRUE
		$response = $this->callUntilDataIsPresent('alert.get', [
			'output' => 'extend',
			'actionids' => $symptom_actionids[1],
			'sortfield' => 'alertid'
		], 5, 2);

		// 1 escalation is expected
		$this->assertCount(1, $response['result']);
		$this->assertEquals(1, $response['result'][0]['esc_step']);

		// check alerts for the symptom event with ACTION_PAUSE_SYMPTOMS_FALSE
		$response = $this->callUntilDataIsPresent('alert.get', [
			'output' => 'extend',
			'actionids' => $symptom_actionids[2],
			'sortfield' => 'alertid'
		], 5, 2);

		// 2 escalations are expected
		$this->assertCount(2, $response['result']);
		$this->assertEquals(1, $response['result'][0]['esc_step']);
		$this->assertEquals(2, $response['result'][1]['esc_step']);

		// stop events
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);
		$this->sendSenderValue(self::HOST_NAME, $trapper_keys[1], 0);
		$this->sendSenderValue(self::HOST_NAME, $trapper_keys[2], 0);
	}

	/**
	 * Test unfinished webhook
	 *testEscalations_checkUnfinishedAlerts
	 * @backup actions, alerts, history_uint, media_type, users, media, events, problem
	 */
	public function testEscalations_checkUnfinishedAlerts() {
		$this->clearLog(self::COMPONENT_SERVER);
		$this->reloadConfigurationCache();

		// Create webhook mediatype
		$script_code = <<<HEREDOC
var params = JSON.parse(value);

if (!(params.event_value === '0' || params.event_update_status === '1')) {
	var now = new Date().getTime();
	while (new Date().getTime() < now + 11000) { /* do nothing */ }
}

return {};
HEREDOC;
		$response = $this->call('mediatype.create', [
			'script' => $script_code,
			'name' => 'Long executing webhook',
			'timeout' => '30s',
			'type' => MEDIA_TYPE_WEBHOOK,
			'parameters' => [
				[
					'name' => 'event_value',
					'value' => '{EVENT.VALUE}'
				],
				[
					'name' => 'event_update_status',
					'value' => '{EVENT.SOURCE}'
				]
			],
			'status' => 0
		]);
		$this->assertArrayHasKey('mediatypeids', $response['result']);
		$this->assertEquals(1, count($response['result']['mediatypeids']));
		$mediatypeid = $response['result']['mediatypeids'][0];

		$response = $this->call('user.update', [
			'userid' => 1,
			'medias' => [
				[
					'mediatypeid' => $mediatypeid,
					'sendto' => 'q'
				]
			]
		]);
		$this->assertArrayHasKey('userids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['userids']);

		// Create action
		$response = $this->call('action.create', [
			'esc_period' => '1h',
			'eventsource' => 0,
			'status' => 0,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => self::$triggerid
					]
				],
				'evaltype' => 0
			],
			'name' => 'Trapper received 1 (unfinished alert check)',
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => $mediatypeid,
						'subject' => 's',
						'message' => 's'
					],
					'opmessage_grp' => [['usrgrpid' => 7]]
				]
			],
			'pause_suppressed' => 0,
			'recovery_operations' => [
				[
					'operationtype' => OPERATION_TYPE_RECOVERY_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'subject' => 'R',
						'message' => 'R'
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
		$actionid = $response['result']['actionids'];

		$response = $this->call('action.update', [
			'actionid' => self::$trigger_actionid,
			'status' => 1
		]);

		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 8);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 95, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 10, 3);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'actionids' => $actionid
		], 5, 2);

		$this->assertCount(1, $response['result']);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_recover()', true, 200);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_recover()', true);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'actionids' => $actionid
		], 5, 2);
		$this->assertCount(2, $response['result']);
	}

	/**
	 * Test active remote commands
	 *testEscalations_checkActiveCommands
	 * @required-components server, agent
	 * @configurationDataProvider serverConfigurationProviderRemote
	 * @backup actions, alerts, history_uint, media_type, users, media, events, problem
	 */
	public function testEscalations_checkActiveCommands() {
		$this->clearLog(self::COMPONENT_SERVER);
		$this->reloadConfigurationCache();

		// Create remote commands
		$response = $this->call('script.create', [
			'name' => 'Test remote command problem',
			'command' => self::COMMAND_PROBLEM,
			'execute_on' => 0,
			'scope' => 1,
			'type' => 0
		]);
		$this->assertArrayHasKey('scriptids', $response['result']);
		self::$scriptid_problem = $response['result']['scriptids'][0];

		$response = $this->call('script.create', [
			'name' => 'Test remote command recovery',
			'command' => self::COMMAND_RECOVERY,
			'execute_on' => 0,
			'scope' => 1,
			'type' => 0
		]);
		$this->assertArrayHasKey('scriptids', $response['result']);
		self::$scriptid_recovery = $response['result']['scriptids'][0];

		// Create active item
		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => 'Agent variant',
			'key_' => 'agent.variant',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'delay' => '1s'
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		// Create action
		$response = $this->call('action.create', [
			'esc_period' => '1h',
			'eventsource' => 0,
			'status' => 0,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => self::$triggerid
					]
				],
				'evaltype' => 0
			],
			'name' => 'Remote command action',
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'operationtype' => OPERATION_TYPE_COMMAND,
					'opcommand' => [
						'scriptid' => self::$scriptid_problem
					],
					'opcommand_hst' => [
						[
							'hostid'=> self::$hostid
						]
					]
				]
			],
			'pause_suppressed' => 0,
			'recovery_operations' => [
				[
					'operationtype' => OPERATION_TYPE_COMMAND,
					'opcommand' => [
						'scriptid' => self::$scriptid_recovery
					],
					'opcommand_hst' => [
						[
							'hostid'=> self::$hostid
						]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
		$actionid = $response['result']['actionids'];

		$response = $this->call('action.update', [
			'actionid' => self::$trigger_actionid,
			'status' => 1
		]);

		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 8);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 95, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_AGENT, "Executing command '".self::COMMAND_PROBLEM."'",
				true, 10, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_process_command_results(), parsed 1',
				true);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 10, 3);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'actionids' => $actionid
		], 5, 2);
		$this->assertCount(1, $response['result']);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_recover()', true, 200);
		$this->waitForLogLineToBePresent(self::COMPONENT_AGENT, "Executing command '".self::COMMAND_RECOVERY."'",
				true, 10, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_process_command_results(), parsed 1',
				true);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_recover()', true);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'actionids' => $actionid
		], 5, 2);
		$this->assertCount(2, $response['result']);
	}

	/**
	 * @backup actions, alerts, history_uint
	 */
	public function testEscalations_triggerDependency() {
		$this->clearLog(self::COMPONENT_SERVER);
		// Create trigger
		$response = $this->call('trigger.create', [
			'description' => 'Dependent trigger',
			'expression' => 'last(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_NAME.')>0',
			'dependencies' => [
				['triggerid' => self::$triggerid]
			]
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertEquals(1, count($response['result']['triggerids']));
		$dep_triggerid = $response['result']['triggerids'][0];

		$response = $this->call('action.update', [
			'actionid' => self::$trigger_actionid,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => '2',
						'operator' => 0,
						'value' => $dep_triggerid
					]
				],
				'evaltype' => 0
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));

		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 9);

		$response = $this->call('alert.get', [
			'actionids' => self::$trigger_actionid
		]);
		$this->assertEmpty($response['result']);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);
	}

}

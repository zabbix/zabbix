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

	const TRAPPER_ITEM_NAME = 'trap';
	const HOST_NAME = 'test_actions';

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
			'status' => 0,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => CONDITION_TYPE_TRIGGER,
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

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 4);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 120);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 10, 3);

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

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In zbx_dc_update_maintenances()', true, 120);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_dc_update_maintenances()', true);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 120);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 10, 3);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'actionids' => [self::$trigger_actionid]
		], 5, 2);
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
			'operations' => [
				[
					'esc_period' => '1m',
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'evaltype' => 0,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 0,
						'message' => 'Problem',
						'subject' => 'Problem'
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
						'message' => 'Problem',
						'subject' => 'Problem'
					],
					'opmessage_grp' => [['usrgrpid' => 7]]
				]
			]
		]);

		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['actionids']);

		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 7);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 95, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 10, 3);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 95, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 10, 3);

		$response = $this->callUntilDataIsPresent('alert.get', [
				'output' => 'extend',
				'actionids' => [self::$trigger_actionid],
				'sortfield' => 'alertid'
		], 5, 2);
		$this->assertCount(2, $response['result']);
		$this->assertEquals(1, $response['result'][0]['esc_step']);
		$this->assertEquals(2, $response['result'][1]['esc_step']);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);
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
			'content_type' => 1
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
						'conditiontype' => CONDITION_TYPE_TRIGGER,
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
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 95, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true, 10, 3);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'actionids' => $actionid
		], 5, 2);
		$this->assertCount(1, $response['result']);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_recover()', true, 200);
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

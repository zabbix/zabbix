<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * @required-components server, agent
 * @backup items,actions,triggers,alerts
 * @hosts test_actions
 */
class testEscalations extends CIntegrationTest {

	private static $hostid;
	private static $trapper_itemid;
	private static $vfs_itemid;
	private static $maintenanceid;
	private static $triggerid;
	private static $maint_start_tm;
	private static $trigger_actionid;
	private static $internal_actionid;

	const TRAPPER_ITEM_NAME = 'trap';
	const HOST_NAME = 'test_actions';
	const ITEM_UNSUPP_FILENAME = '/tmp/item_unsupported_test';

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

		// Get host interface ids.
		$response = $this->call('host.get', [
			'output' => ['host'],
			'hostids' => [self::$hostid],
			'selectInterfaces' => ['interfaceid']
		]);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('interfaces', $response['result'][0]);
		$this->assertArrayHasKey(0, $response['result'][0]['interfaces']);
		$interfaceid = $response['result'][0]['interfaces'][0]['interfaceid'];

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
		self::$trapper_itemid = $response['result']['itemids'][0];

		// Create trigger
		$response = $this->call('trigger.create', [
			'description' => 'Trapper received 1',
			'expression' => 'last(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_NAME.')=1'
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertEquals(1, count($response['result']['triggerids']));
		self::$triggerid = $response['result']['triggerids'][0];

		// Create item for testing alerting on internal event
		$this->assertTrue(@file_put_contents(self::ITEM_UNSUPP_FILENAME, 'text') !== false);
		$response = $this->call('item.create', [
			'name' => 'File contents of'.self::ITEM_UNSUPP_FILENAME,
			'key_' => 'vfs.file.contents['.self::ITEM_UNSUPP_FILENAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'hostid' => self::$hostid,
			'interfaceid' => $interfaceid,
			'value_type' => ITEM_VALUE_TYPE_TEXT,
			'delay' => '4s',
			'status' => ITEM_STATUS_ACTIVE
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		self::$vfs_itemid = $response['result']['itemids'][0];

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
						'value' => self::$triggerid,
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
			],
			'name' => 'Trapper received 1 (problem) clone',
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'evaltype' => 0,
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
					'evaltype' => 0,
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

		// Create internal action
		$response = $this->call('action.create', [
			'esc_period' => '1m',
			'eventsource' => 3,
			'status' => 0,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => CONDITION_TYPE_HOST,
						'formulaid' => 'B',
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => self::$hostid
					],
					[
						'conditiontype' => CONDITION_TYPE_EVENT_TYPE,
						'formulaid' => 'A',
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => '0'
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND,
			],
			'name' => 'Not supported item on '.self::HOST_NAME,
			'operations' => [
						[
						'esc_period' => 0,
						'esc_step_from' => 1,
						'esc_step_to' => 1,
						'evaltype' => 0,
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'opmessage' => [
							'default_msg' => 1,
							'mediatypeid' => 0
						],
						'opmessage_grp' => [
							['usrgrpid' => 7]
						]
			]],
			'recovery_operations' => [
				[
					'evaltype' => 0,
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
		self::$internal_actionid = $response['result']['actionids'];

		return true;
	}

	/**
	 * @backup actions,alerts,history_uint,history,problem,events
	 */
	public function testEscalations_disabledAction() {
		$response = $this->call('action.update', [
			'actionid' => self::$trigger_actionid,
			'status' => 1
		]);

		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));

		$this->reloadConfigurationCache();
		sleep(1);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 1);
		sleep(2);

		// Check if there are no alerts for this action
		$response = $this->call('alert.get', [
			'actionids' => [self::$trigger_actionid]
		]);
		$this->assertEmpty($response['result']);
	}

	/**
	 * @backup alerts,triggers,history_uint,history,problem,events
	 */
	public function testEscalations_disabledTrigger() {
		$response = $this->call('trigger.update', [
			'triggerid' => self::$triggerid,
			'status' => 1
		]);

		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertEquals(1, count($response['result']['triggerids']));

		$this->reloadConfigurationCache();
		sleep(1);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 1);
		sleep(2);

		// Check if there are no alerts for this action
		$response = $this->call('alert.get', [
			'actionids' => [self::$trigger_actionid]
		]);
		$this->assertEmpty($response['result']);
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
		$this->reloadConfigurationCache();
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);
		// Create maintenance period
		self::$maint_start_tm = time();
		$maint_end_tm = self::$maint_start_tm + 60 * 2;

		$response = $this->call('maintenance.create', [
			'name' => 'Test maintenance',
			'groupids' => [],
			'hostids' => [self::$hostid],
			'active_since' => self::$maint_start_tm,
			'active_till' => $maint_end_tm,
			'tags_evaltype' => 0,
			'timeperiods' => [
				[
					'day' => '1',
					'dayofweek' => '0',
					'every' => '1',
					'month' => '0',
					'period' => '300',
					'start_date' => self::$maint_start_tm,
					'start_time' => '0',
					'timeperiod_type' => '0'
				]
			]
		]);
		$this->assertArrayHasKey('maintenanceids', $response['result']);
		$this->assertEquals(1, count($response['result']['maintenanceids']));
		$maintenance_id = $response['result']['maintenanceids'][0];

		$this->reloadConfigurationCache();
		sleep(60);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 1);

		$response = $this->call('alert.get', [
			'actionids' => [self::$trigger_actionid]
		]);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(0, $response['result'][0]['p_eventid']);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);
		sleep(2);

		$response = $this->call('alert.get', [
			'actionids' => [self::$trigger_actionid]
		]);
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
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);
		$response = $this->call('action.update', [
			'actionid' => self::$trigger_actionid,
			'pause_suppressed' => 1
		]);
		// Create maintenance period
		self::$maint_start_tm = time() + 10;
		$maint_end_tm = self::$maint_start_tm + 60 * 2;

		$response = $this->call('maintenance.create', [
			'name' => 'Test maintenance',
			'groupids' => [],
			'hostids' => [self::$hostid],
			'active_since' => self::$maint_start_tm,
			'active_till' => $maint_end_tm,
			'tags_evaltype' => 0,
			'timeperiods' => [
				[
					'day' => '1',
					'dayofweek' => '0',
					'every' => '1',
					'month' => '0',
					'period' => '300',
					'start_date' => self::$maint_start_tm,
					'start_time' => '0',
					'timeperiod_type' => '0'
				]
			]
		]);
		$this->assertArrayHasKey('maintenanceids', $response['result']);
		$this->assertEquals(1, count($response['result']['maintenanceids']));
		$maintenance_id = $response['result']['maintenanceids'][0];

		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 1);
		sleep(2);

		$response = $this->call('alert.get', [
			'actionids' => [self::$trigger_actionid]
		]);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(0, $response['result'][0]['p_eventid']);

		sleep(60);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);
		sleep(2);

		$response = $this->call('alert.get', [
			'actionids' => [self::$trigger_actionid]
		]);
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
		$response = $this->call('action.update', [
			'actionid' => self::$trigger_actionid,
			'pause_suppressed' => 1
		]);
		// Create maintenance period
		self::$maint_start_tm = time();
		$maint_end_tm = self::$maint_start_tm + 60 * 2;

		$response = $this->call('maintenance.create', [
			'name' => 'Test maintenance',
			'groupids' => [],
			'hostids' => [self::$hostid],
			'active_since' => self::$maint_start_tm,
			'active_till' => $maint_end_tm,
			'tags_evaltype' => 0,
			'timeperiods' => [
				[
					'day' => '1',
					'dayofweek' => '0',
					'every' => '1',
					'month' => '0',
					'period' => '300',
					'start_date' => self::$maint_start_tm,
					'start_time' => '0',
					'timeperiod_type' => '0'
				]
			]
		]);
		$this->assertArrayHasKey('maintenanceids', $response['result']);
		$this->assertEquals(1, count($response['result']['maintenanceids']));
		$maintenance_id = $response['result']['maintenanceids'][0];

		$this->reloadConfigurationCache();
		sleep(60);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 1);
		sleep(2);

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

		sleep(60);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);
		sleep(2);

		$response = $this->call('alert.get', [
			'actionids' => [self::$trigger_actionid]
		]);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(0, $response['result'][0]['p_eventid']);

		sleep(2);

		$response = $this->call('alert.get', [
			'actionids' => [self::$trigger_actionid]
		]);
		$this->assertArrayHasKey(1, $response['result']);
		$this->assertNotEquals(0, $response['result'][1]['p_eventid']);
	}

	/**
	 * Test cancelled escalation (disabled trigger)
	 *
	 * @backup actions,alerts,events,problem,history_uint,hosts,users
	 */
	public function testEscalations_checkScenario4() {
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
			],
		]);

		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['actionids']);
		$response = $this->call('user.update', [
			'userid' => 1,
			'user_medias' => [
				[
					'mediatypeid' => 1,
					'sendto' => 'test@local.local'
				]
			]
		]);
		$this->assertArrayHasKey('userids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['userids']);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 1);

		$response = $this->call('trigger.update', [
			'triggerid' => self::$triggerid,
			'status' => 1
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertEquals(1, count($response['result']['triggerids']));

		$this->reloadConfigurationCache();

		sleep(60);

		$response = $this->call('alert.get', [
			'actionids' => [self::$trigger_actionid]
		]);
		$esc_msg = 'NOTE: Escalation cancelled';
		$this->assertArrayHasKey(1, $response['result']);
		$this->assertEquals(0, strncmp($esc_msg, $response['result'][1]['message'], strlen($esc_msg)));

	}

	/**
	 * Test normal escalation with multiple escalations steps
	 *
	 * @backup alerts,actions
	 */
	public function testEscalations_checkScenario5() {
		$this->reloadConfigurationCache();
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);

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
			],
		]);

		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['actionids']);

		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 1);
		sleep(61);

		$response = $this->call('alert.get', [
			'output' => 'extend',
			'actionsids' => [self::$trigger_actionid],
			]
		);
		$this->assertCount(2, $response['result']);
		$this->assertEquals(1, $response['result'][0]['esc_step']);
		$this->assertEquals(2, $response['result'][1]['esc_step']);
	}

	/**
	 * Test unfinished webhook
	 *
	 * @backup actions, alerts, history_uint, media_type, users, media, events, problem
	 */
	public function testEscalations_checkUnfinishedAlerts() {
		$this->reloadConfigurationCache();
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);

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
					'value' => '{EVENT.VALUE}',
				],
				[
					'name' => 'event_update_status',
					'value' => '{EVENT.SOURCE}',
				]
			],
			'content_type' => 1
		]);
		$this->assertArrayHasKey('mediatypeids', $response['result']);
		$this->assertEquals(1, count($response['result']['mediatypeids']));
		$mediatypeid = $response['result']['mediatypeids'][0];

		$response = $this->call('user.update', [
			'userid' => 1,
			'user_medias' => [
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
						'value' => self::$triggerid,
					]
				],
				'evaltype' => 0,
			],
			'name' => 'Trapper received 1 (unfinished alert check)',
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'evaltype' => 0,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => $mediatypeid,
						'subject' => 's',
						'message' => 's',
					],
					'opmessage_grp' => [['usrgrpid' => 7]],
				]
			],
			'pause_suppressed' => 0,
			'recovery_operations' => [
				[
					'evaltype' => 0,
					'operationtype' => OPERATION_TYPE_RECOVERY_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 0,
						'subject' => 'R',
						'message' => 'R',
					],
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
		$actionid = $response['result']['actionids'];

		$this->reloadConfigurationCache();
		sleep(1);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 1);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);

		$response = $this->call('alert.get', [
			'actionids' => $actionid
		]);
		$this->assertCount(1, $response['result']);

		sleep(8);

		$response = $this->call('alert.get', [
			'actionids' => $actionid
		]);
		$this->assertCount(2, $response['result']);
	}

	/**
	 * @backup actions, alerts, history_uint
	 */
	public function testEscalations_triggerDependency() {
		// Create trigger
		$response = $this->call('trigger.create', [
			'description' => 'Dependent trigger',
			'expression' => 'last(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_NAME.')=1',
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
						'value' => $dep_triggerid,
					]
				],
				'evaltype' => 0,
			],
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));

		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 1);
		sleep(1);

		// Check if there are 2 alerts for this action
		$response = $this->call('alert.get', [
			'actionids' => self::$trigger_actionid
		]);
		$this->assertEmpty($response['result']);
	}
}

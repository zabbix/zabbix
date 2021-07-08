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
 * @hosts test_actions
 */
class testActions extends CIntegrationTest {

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
			'expression' => '{'.self::HOST_NAME.':'.self::TRAPPER_ITEM_NAME.'.last()}=1'
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
			'esc_period' => '1h',
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
	 * @backup actions,alerts,history
	 */
	public function testActions_disabledAction() {
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
	 * @backup alerts,triggers,history
	 */
	public function testActions_disabledTrigger() {
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
	 * @backup actions,alerts,history,maintenances
	 */
	public function testActions_checkSuppressedProblem() {
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
		sleep(61);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 1);
		sleep(2);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);
		sleep(2);

		// Check if there are no alerts for this action
		$response = $this->call('alert.get', [
			'actionids' => [self::$trigger_actionid]
		]);
		$this->assertEmpty($response['result']);
	}

	/**
	 * @backup actions,alerts,history,items,maintenances
	 */
	public function testActions_checkNonSuppressedProblem() {
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

		$this->reloadConfigurationCache();

		sleep(61);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 1);
		sleep(2);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);
		sleep(2);

		// Check if there are 2 alerts for this action
		$response = $this->call('alert.get', [
			'actionids' => [self::$trigger_actionid]
		]);
		$this->assertCount(2, $response['result']);
	}

	/**
	 * @backup actions, alerts, history, items
	 */
	public function testActions_checkInternalDisabledItem() {
		$this->reloadConfigurationCache();

		$this->assertTrue(@unlink(self::ITEM_UNSUPP_FILENAME) !== false);

		// Disable item
		$response = $this->call('item.update', [
			"itemid" => self::$trapper_itemid,
			"status" => 1
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		$this->reloadConfigurationCache();

		sleep(2);

		// Check if there are 2 alerts for this action
		$response = $this->call('alert.get', [
			'actionids' => self::$internal_actionid
		]);
		$this->assertEmpty($response['result']);
	}

	/**
	 * @backup actions, alerts, history, items
	 */
	public function testActions_checkInternalDeletedItem() {
		$this->assertTrue(@file_put_contents(self::ITEM_UNSUPP_FILENAME, 'text') !== false);
		// Create action
		$this->reloadConfigurationCache();
		$this->assertTrue(@unlink(self::ITEM_UNSUPP_FILENAME) !== false);

		// Delete item
		$response = $this->call('item.update', [
			"itemid" => self::$vfs_itemid,
			"status" => 1
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		$this->reloadConfigurationCache();

		sleep(2);

		// Check if there are no alerts for this action
		$response = $this->call('alert.get', [
			'actionids' => self::$internal_actionid
		]);
		$this->assertEmpty($response['result']);
	}

	/**
	 * @backup actions, alerts, history
	 */
	public function testActions_checkUnfinishedAlerts() {
		// Create script
		$response = $this->call('script.create', ['command' => 'sleep 5',
			'execute_on' => '1',
			'groupid' => '0',
			'host_access' => '2',
			'name' => 'Long executing script',
			'type' => '0',
			'usrgrpid' => '7'
		]);
		$this->assertArrayHasKey('scriptids', $response['result']);
		$this->assertEquals(1, count($response['result']['scriptids']));
		$scriptid = $response['result']['scriptids'][0];

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
					'operationtype' => OPERATION_TYPE_COMMAND,
					'opcommand' => [
						'authtype' => ITEM_AUTHTYPE_PASSWORD,
						'execute_on' => ZBX_SCRIPT_EXECUTE_ON_AGENT,
						'scriptid' => $scriptid,
						'type' => ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT
					],
					'opcommand_grp' => [],
					'opcommand_hst' => [
						[
							'hostid' => '0',
							'opcommand_hstid' => '1'
						]
					]
				]
			],
			'pause_suppressed' => 0,
			'recovery_operations' => [
				[
					'evaltype' => 0,
					'operationtype' => OPERATION_TYPE_COMMAND,
					'opcommand' => [
						'authtype' => ITEM_AUTHTYPE_PASSWORD,
						'execute_on' => ZBX_SCRIPT_EXECUTE_ON_AGENT,
						'scriptid' => $scriptid,
						'type' => ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT
					],
					'opcommand_grp' => [],
					'opcommand_hst' => [
						[
							'hostid' => '0',
							'opcommand_hstid' => '1'
						]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
		$actionid = $response['result']['actionids'];

		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 1);
		sleep(2);

		// Action is not yet completed
		$response = $this->call('alert.get', [
			'actionids' => $actionid
		]);
		$this->assertEmpty($response['result']);

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);
		sleep(4);

		// Check if there are 2 alerts for this action
		$response = $this->call('alert.get', [
			'actionids' => $actionid
		]);
		$this->assertCount(2, $response['result']);
	}

	/**
	 * @backup actions, alerts, history
	 */
	public function testActions_triggerDependency() {
		// Create trigger
		$response = $this->call('trigger.create', [
			'description' => 'Dependent trigger',
			'expression' => '{'.self::HOST_NAME.':'.self::TRAPPER_ITEM_NAME.'.last()}=1',
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

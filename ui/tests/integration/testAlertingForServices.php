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
 * Test suite for alerting for services.
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_services_alerting
 * @backup history,items,triggers,alerts,services
 */
class testAlertingForServices extends CIntegrationTest {
	const HOSTNAME = 'test_services_alerting';
	const TRAPPER_KEY = 'test_trapper';
	const SERVICENAME = 'Service 1';
	const TRIGGER_DESC = 'Sample trigger';

	private static $hostid;
	private static $serviceid;
	private static $actionid;
	private static $triggerid;
	private static $problem_tags;

	private function createServiceWithProblemTags($name, $problem_tags) {
		$response = $this->call('service.create', [
			'name' => $name,
			'algorithm' => ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ALL,
			'sortorder' => 1,
			'problem_tags' => $problem_tags
		]);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['serviceids']);

		return $response['result']['serviceids'][0];
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "test_services_alerting"
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
			[
				'name' => self::TRAPPER_KEY,
				'key_' => self::TRAPPER_KEY,
				'type' => ITEM_TYPE_TRAPPER,
				'hostid' => self::$hostid,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		// Create trigger
		$response = $this->call('trigger.create', [
			'description' => self::TRIGGER_DESC,
			'priority' => TRIGGER_SEVERITY_HIGH,
			'status' => TRIGGER_STATUS_ENABLED,
			'type' => 0,
			'recovery_mode' => 0,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'expression' => 'last(/' . self::HOSTNAME . '/' . self::TRAPPER_KEY . ')=1'
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);
		self::$triggerid = $response['result']['triggerids'][0];

		$response = $this->call('trigger.update', [
			'triggerid' => self::$triggerid,
			'tags' => [
				[
					'tag' => 'ServiceLink',
					'value' => self::$triggerid . ':' . self::TRIGGER_DESC
				]
			]
		]);
		self::$problem_tags = 'ServiceLink:' . self::$triggerid . ':' . self::TRIGGER_DESC;

		// Create service
		self::$serviceid = $this->createServiceWithProblemTags(self::SERVICENAME, [
			[
				'tag' => 'ServiceLink',
				'operator' => 0,
				'value' => self::$triggerid . ':' . self::TRIGGER_DESC
			]
		]);

		// Create action
		$response = $this->call('action.create', [
			'name' => 'Sample service status changed',
			'eventsource' => EVENT_SOURCE_SERVICE,
			'status' => 0,
			'esc_period' => '1h',
			'filter' => [
				'evaltype' => 0,
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_SERVICE,
						'operator' => 0,
						'value' => self::$serviceid
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
					'opmessage_grp' => [['usrgrpid' => 7]]
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
					'opmessage_grp' => [['usrgrpid' => 7]]
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
					'opmessage_grp' => [['usrgrpid' => 7]]
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
	 * Check alerting based on service action.
	 *
	 * @backup actions,alerts,history_uint,events,problem,service_problem,triggers,escalations
	 */
	public function testAlertingForServices_checkServiceStatusChange() {
		$this->sendSenderValue(self::HOSTNAME, self::TRAPPER_KEY, 1);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'output' => 'extend',
			'actionids' => self::$actionid,
			'eventsource' => EVENT_SOURCE_SERVICE,
			'eventobject' => EVENT_OBJECT_SERVICE
		], 25, 2);
		$this->assertCount(1, $response['result']);
		$this->assertEquals('Problem', $response['result'][0]['message']);
		$this->assertEquals('Problem', $response['result'][0]['subject']);
		$this->assertEquals(0, $response['result'][0]['p_eventid']);

		$response = $this->callUntilDataIsPresent('event.get', [
			'objectids' => self::$triggerid
		], 25, 2);
		$this->assertArrayHasKey(0, $response['result']);
		$eventid = $response['result'][0]['eventid'];

		$response = $this->call('event.acknowledge', [
			'eventids' => $eventid,
			'action' => ZBX_PROBLEM_UPDATE_SEVERITY,
			'message' => 'disaster',
			'severity' => TRIGGER_SEVERITY_DISASTER
		]);
		$this->assertArrayHasKey('eventids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['eventids']);

		sleep(12);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'output' => 'extend',
			'actionids' => self::$actionid,
			'eventsource' => EVENT_SOURCE_SERVICE,
			'eventobject' => EVENT_OBJECT_SERVICE
		], 25, 2);
		$this->assertCount(2, $response['result']);

		// Check if new problem event was added
		$response = $this->call('problem.get', [
			'output' => 'extend',
			'source' => EVENT_SOURCE_SERVICE,
			'object' => EVENT_OBJECT_SERVICE
		]
		);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(TRIGGER_SEVERITY_HIGH, $response['result'][0]['severity']);
		$expected_eventname = 'Status of service "' . self::SERVICENAME . '" changed to High';
		$this->assertEquals($expected_eventname, $response['result'][0]['name']);

		$this->sendSenderValue(self::HOSTNAME, self::TRAPPER_KEY, 0);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_recover()', true, 120);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_recover()', true);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'output' => 'extend',
			'actionids' => self::$actionid,
			'eventsource' => EVENT_SOURCE_SERVICE,
			'eventobject' => EVENT_OBJECT_SERVICE,
			'sortfield' => 'alertid'
		], 25, 3);
		$this->assertCount(3, $response['result']);
		$this->assertEquals('Recovery', $response['result'][2]['message']);
		$this->assertEquals('Recovery', $response['result'][2]['subject']);
		$this->assertNotEquals(0, $response['result'][2]['p_eventid']);

		// Check recovery event
		$response = $this->callUntilDataIsPresent('event.get', [
			'output' => 'extend',
			'source' => EVENT_SOURCE_SERVICE,
			'object' => EVENT_OBJECT_SERVICE,
			'value' => 0
		], 25, 3);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(ZBX_SEVERITY_OK, $response['result'][0]['severity']);
		$expected_eventname = 'Status of service "' . self::SERVICENAME . '" changed to OK';
		$this->assertEquals($expected_eventname, $response['result'][0]['name']);

		return true;
	}

	/**
	 * Check alerting based on service action (without update operation, but with updated severity).
	 *
	 * @backup actions,alerts,history_uint,events,problem,service_problem,triggers,escalations
	 */
	public function testAlertingForServices_checkServiceStatusChangeWoUpdate() {
		$response = $this->call('action.update', [
			'actionid' => self::$actionid,
			'esc_period' => '1m',
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
					'opmessage_grp' => [['usrgrpid' => 7]]
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
					'opmessage_grp' => [['usrgrpid' => 7]]
				]
			],
			'update_operations' => [
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['actionids']);

		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOSTNAME, self::TRAPPER_KEY, 1);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'output' => 'extend',
			'actionids' => self::$actionid,
			'eventsource' => EVENT_SOURCE_SERVICE,
			'eventobject' => EVENT_OBJECT_SERVICE
		], 25, 2);
		$this->assertCount(1, $response['result']);
		$this->assertEquals('Problem', $response['result'][0]['message']);
		$this->assertEquals('Problem', $response['result'][0]['subject']);
		$this->assertEquals(0, $response['result'][0]['p_eventid']);

		$response = $this->callUntilDataIsPresent('event.get', [
			'objectids' => self::$triggerid
		], 25, 2);
		$this->assertArrayHasKey(0, $response['result']);
		$eventid = $response['result'][0]['eventid'];

		$response = $this->call('event.acknowledge', [
			'eventids' => $eventid,
			'action' => ZBX_PROBLEM_UPDATE_SEVERITY,
			'message' => 'disaster',
			'severity' => TRIGGER_SEVERITY_DISASTER
		]);
		$this->assertArrayHasKey('eventids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['eventids']);

		sleep(10);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'output' => 'extend',
			'actionids' => self::$actionid,
			'eventsource' => EVENT_SOURCE_SERVICE,
			'eventobject' => EVENT_OBJECT_SERVICE
		], 25, 2);
		$this->assertCount(1, $response['result']);

		// Check if new problem event was added
		$response = $this->call('problem.get', [
			'output' => 'extend',
			'source' => EVENT_SOURCE_SERVICE,
			'object' => EVENT_OBJECT_SERVICE
		]
		);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(TRIGGER_SEVERITY_HIGH, $response['result'][0]['severity']);
		$expected_eventname = 'Status of service "' . self::SERVICENAME . '" changed to High';
		$this->assertEquals($expected_eventname, $response['result'][0]['name']);

		$this->sendSenderValue(self::HOSTNAME, self::TRAPPER_KEY, 0);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_recover()', true, 120);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_recover()', true);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'output' => 'extend',
			'actionids' => self::$actionid,
			'eventsource' => EVENT_SOURCE_SERVICE,
			'eventobject' => EVENT_OBJECT_SERVICE,
			'sortfield' => 'alertid'
		], 25, 3);
		$this->assertCount(2, $response['result']);
		$this->assertEquals('Recovery', $response['result'][1]['message']);
		$this->assertEquals('Recovery', $response['result'][1]['subject']);
		$this->assertNotEquals(0, $response['result'][1]['p_eventid']);

		// Check recovery event
		$response = $this->callUntilDataIsPresent('event.get', [
			'output' => 'extend',
			'source' => EVENT_SOURCE_SERVICE,
			'object' => EVENT_OBJECT_SERVICE,
			'value' => 0
		], 25, 3);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(ZBX_SEVERITY_OK, $response['result'][0]['severity']);
		$expected_eventname = 'Status of service "' . self::SERVICENAME . '" changed to OK';
		$this->assertEquals($expected_eventname, $response['result'][0]['name']);

		return true;
	}

	/**
	 * Check SERVICE.* macros.
	 *
	 * @backup alerts,history,events,triggers,services
	 */
	public function testAlertingForServices_checkMacros() {
		$this->sendSenderValue(self::HOSTNAME, self::TRAPPER_KEY, 0);
		$this->reloadConfigurationCache();

		$response = $this->call('action.update', [
			'actionid' => self::$actionid,
			'esc_period' => '1m',
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 0,
						'message' => '{SERVICE.NAME}|{SERVICE.TAGS}|{SERVICE.TAGSJSON}|{SERVICE.ROOTCAUSE}|{SERVICE.ID}',
						'subject' => 'Problem'
					],
					'opmessage_grp' => [['usrgrpid' => 7]]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['actionids']);

		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOSTNAME, self::TRAPPER_KEY, 1);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 120);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true);

		$response = $this->call('alert.get', [
			'output' => 'extend',
			'actionids' => self::$actionid,
			'eventsource' => EVENT_SOURCE_SERVICE,
			'eventobject' => EVENT_OBJECT_SERVICE
		]);
		$this->assertArrayHasKey(0, $response['result']);

		$service_macros = explode('|', $response['result'][0]['message']);

		$rootcause = '/Host: "' . self::HOSTNAME . '" Problem name: "' . self::TRIGGER_DESC .'" Severity: "' .
				'High" Age: [0-9]+s Problem tags: "' . self::$problem_tags . '"/ ';

		$this->assertEquals(self::SERVICENAME, $service_macros[0]);
		$this->assertEmpty($service_macros[1]);
		$this->assertEquals('[]', $service_macros[2]);

		$this->assertEquals(1, preg_match($rootcause, $service_macros[3], $matches));
		$this->assertEquals(self::$serviceid, $service_macros[4]);
	}

	/**
	 * Check escalation steps.
	 *
	 * @backup alerts,history,events,triggers,services
	 */
	public function testAlertingForServices_checkEscalationSteps() {
		$response = $this->call('action.update', [
			'actionid' => self::$actionid,
			'esc_period' => '1m',
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
					'opmessage_grp' => [['usrgrpid' => 7]]
				],
				[
					'esc_period' => 0,
					'esc_step_from' => 2,
					'esc_step_to' => 2,
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

		$this->sendSenderValue(self::HOSTNAME, self::TRAPPER_KEY, 1);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 120);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'In escalation_execute()', true, 120);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of escalation_execute()', true);

		$response = $this->call('alert.get', [
			'output' => 'extend',
			'actionids' => self::$actionid,
			'eventsource' => EVENT_SOURCE_SERVICE,
			'eventobject' => EVENT_OBJECT_SERVICE,
			'sortfield' => 'alertid'
		]);
		$this->assertCount(2, $response['result']);
		$this->assertEquals(1, $response['result'][0]['esc_step']);
		$this->assertEquals(2, $response['result'][1]['esc_step']);

		return true;
	}

	/**
	 * Test suite for alerting for services.
	 *
	 * @backup alerts,history,events,triggers,services
	 */
	public function testAlertingForServices_checkTwoTriggers() {
		$this->reloadConfigurationCache();
		// Create trigger
		$trigger_desc_2 = self::TRIGGER_DESC . '2';
		$response = $this->call('trigger.create', [
			'description' => $trigger_desc_2,
			'priority' => TRIGGER_SEVERITY_DISASTER,
			'status' => TRIGGER_STATUS_ENABLED,
			'type' => 0,
			'recovery_mode' => 0,
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'expression' => 'last(/' . self::HOSTNAME . '/' . self::TRAPPER_KEY . ')=1'
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);
		$trigger_id_2 = $response['result']['triggerids'][0];

		$response = $this->call('trigger.update', [
			'triggerid' => $trigger_id_2,
			'tags' => [
				[
					'tag' => 'ServiceLink',
					'value' => $trigger_id_2 . ':' . $trigger_desc_2
				]
			]
		]);

		$serviceid_child1 = $this->createServiceWithProblemTags('Child service (disaster)', [[
			'tag' => 'ServiceLink',
			'operator' => 0,
			'value' => $trigger_id_2 . ':' . $trigger_desc_2
		]]);

		$serviceid_child2 = $this->createServiceWithProblemTags('Child service (high)', [[
			'tag' => 'ServiceLink',
			'operator' => 0,
			'value' => self::$triggerid . ':' . self::TRIGGER_DESC
		]]);

		// Update service
		$response = $this->call('service.update', [
			'serviceid' => self::$serviceid,
			'children' => [
				['serviceid' => $serviceid_child1],
				['serviceid' => $serviceid_child2]
			],
			'problem_tags' => []
		]);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['serviceids']);

		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOSTNAME, self::TRAPPER_KEY, 1);

		$response = $this->callUntilDataIsPresent('service.get', [
			'output' => 'extend',
			'serviceids' => self::$serviceid
		], 25, 3);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('status', $response['result'][0]);
		$this->assertEquals(TRIGGER_SEVERITY_DISASTER, $response['result'][0]['status']);

		return true;
	}
}

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
 * Test suite for testing changing rank of trigger-based events/problems
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @backup items,actions,triggers,task,task_data,acknowledges,event_symptom,events,problem
 * @hosts host_cause_and_symptoms
 */
class testEventsCauseAndSymptoms extends CIntegrationTest {
	private static $hostid;
	private static $trigger_ids = [];
	private static $trigger_actionids = [];

	const HOST_NAME = 'host_cause_and_symptoms';
	const TRAPPER_ITEM_NAME_PREFIX = 'Trapper item ';
	const TRAPPER_ITEM_KEY_PREFIX = 'trap';
	const UPDATE_MESSAGE_TEMPLATE = 'Message update (n) {EVENT.NAME}|{EVENT.ID}|{EVENT.CAUSE.NAME}|{EVENT.CAUSE.ID}|{EVENT.CAUSE.SOURCE}|{EVENT.CAUSE.OBJECT}|{EVENT.CAUSE.VALUE}';
	const EVENT_START = 1;

	private function expandNumberInTemplate($in_str, $n) {
		return str_replace('(n)', (string) $n, $in_str);
	}

	private function expandMacros($in_str, $event_n, $cause_n = null) {
		$replacements = [
			'{EVENT.NAME}' => 'Trigger trap '.$event_n,
			'{EVENT.ID}' => $event_n
		];

		if (null != $cause_n) {
			$replacements = array_merge($replacements, [
				'{EVENT.CAUSE.NAME}' => 'Trigger trap '.$cause_n,
				'{EVENT.CAUSE.ID}' => $cause_n,
				'{EVENT.CAUSE.SOURCE}' => EVENT_SOURCE_TRIGGERS,
				'{EVENT.CAUSE.OBJECT}' => EVENT_OBJECT_TRIGGER,
				'{EVENT.CAUSE.VALUE}' => self::EVENT_START,
			]);
		}

		$from = [];
		$to = [];

		foreach ($replacements as $key => $value) {
			$from[] = $key;
			$to[] = $value;
		}

		return str_replace($from, $to, $in_str);
	}

	private function markAsSymptoms($rank_requests) {
		foreach ($rank_requests as $rank_request) {
			$response = $this->call('event.acknowledge', [
				'eventids' => $rank_request['eventids'],
				'action' => ZBX_PROBLEM_UPDATE_EVENT_RANK_TO_SYMPTOM,
				'cause_eventid' => $rank_request['cause_eventid'],
			]);

			$this->assertArrayHasKey('result', $response);
			$this->assertArrayHasKey('eventids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['eventids']);
		}
	}

	private function checkSymptoms($expected_events) {
		foreach (['problem.get', 'event.get'] as $request) {
			// get events/problems
			$response = $this->call($request, [
				'output' => [
					'eventid',
					'objectid',
					'cause_eventid',
				],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER
			]);

			$this->assertArrayHasKey('result', $response);

			$events = [];
			foreach ($response['result'] as $event) {
				$this->assertArrayHasKey('eventid', $event);
				$this->assertArrayHasKey('objectid', $event);
				$this->assertArrayHasKey('cause_eventid', $event);
				$events[$event['eventid']] = $event;
			}

			// check if actual cause eventid matches the expected cause eventid
			foreach ($expected_events as $expected_event) {
				$expected_event_id = $expected_event['eventid'];
				$expected_cause_eventid = $expected_event['cause_eventid'];
				$this->assertEquals($expected_cause_eventid,
						$events[$expected_event_id]['cause_eventid']);
			}
		}
	}

	private function waitForEventRankUpdate($events) {
		foreach ($events as $i) {
			$line = "End substitute_simple_macros_impl() data:'Message update ".$i;
			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $line, true);
		}
	}

	private function checkUpdateAlertsForNewSymptoms($expected_alerts) {
		foreach ($expected_alerts as $expected_alert) {
			$cause = $expected_alert['cause_eventid'];
			$symptom = $expected_alert['eventid'];
			$response = $this->call('alert.get', [
				'output' => 'extend',
				'eventids' => $symptom,
				'sortfield' => 'alertid',
				'sortorder' => 'DESC',
				'limit' => 1

			]);

			$this->assertArrayHasKey('result', $response);
			$this->assertCount(1, $response['result']);
			$this->assertArrayHasKey(0, $response['result']);

			// Check {EVENT*} and {EVENT.CAUSE*} macro expansion
			$this->assertArrayHasKey('message', $response['result'][0]);
			$update_message = $this->expandNumberInTemplate(self::UPDATE_MESSAGE_TEMPLATE, $symptom);
			$update_message = $this->expandMacros($update_message, $symptom, $cause);
			$this->assertEquals($update_message, $response['result'][0]['message']);
		}
	}

	private function checkUpdateAlertsForNewCauses($expected_causes) {
		foreach ($expected_causes as $cause) {
			$response = $this->call('alert.get', [
				'output' => 'extend',
				'eventids' => $cause,
				'sortfield' => 'alertid',
				'sortorder' => 'DESC',
				'limit' => 1

			]);

			$this->assertArrayHasKey('result', $response);
			$this->assertCount(1, $response['result']);
			$this->assertArrayHasKey(0, $response['result']);

			// Check {EVENT*} and {EVENT.CAUSE*} macro expansion
			$this->assertArrayHasKey('message', $response['result'][0]);
			$update_message = $this->expandNumberInTemplate(self::UPDATE_MESSAGE_TEMPLATE, $cause);
			$update_message = $this->expandMacros($update_message, $cause);
			$this->assertEquals($update_message, $response['result'][0]['message']);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// create host
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

		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		// get host interface ids
		$response = $this->call('host.get', [
			'output' => ['host'],
			'hostids' => [self::$hostid],
			'selectInterfaces' => ['interfaceid']
		]);

		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('interfaces', $response['result'][0]);
		$this->assertArrayHasKey(0, $response['result'][0]['interfaces']);

		for ($i = 1; $i <= 5; $i++) {
			$item_name = self::TRAPPER_ITEM_NAME_PREFIX . (string) $i;
			$item_key = self::TRAPPER_ITEM_KEY_PREFIX . (string) $i;

			// create trapper item
			$response = $this->call('item.create', [
				'hostid' => self::$hostid,
				'name' => $item_name,
				'key_' => $item_key,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			]);

			$this->assertArrayHasKey('result', $response);
			$this->assertArrayHasKey('itemids', $response['result']);
			$this->assertEquals(1, count($response['result']['itemids']));

			// create trigger
			$response = $this->call('trigger.create', [
				'description' => 'Trigger trap '.(string) $i,
				'expression' => 'last(/'.self::HOST_NAME.'/'.$item_key.')='.self::EVENT_START,
				// 'event_name' => 'event_'.(string) $i
			]);

			$this->assertArrayHasKey('result', $response);
			$this->assertArrayHasKey('triggerids', $response['result']);
			$this->assertEquals(1, count($response['result']['triggerids']));

			$trigger_id = $response['result']['triggerids'][0];
			self::$trigger_ids[$i] = $trigger_id;

			// create trigger action
			$response = $this->call('action.create', [
				'esc_period' => '1m',
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'status' => ACTION_STATUS_ENABLED,
				'filter' => [
					'conditions' => [
						[
							'conditiontype' => CONDITION_TYPE_TRIGGER,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => $trigger_id
						]
					],
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR
				],
				'name' => 'Action trigger trap '.(string) $i,
				'operations' => [
					[
						'esc_period' => '0',
						'esc_step_from' => 1,
						'esc_step_to' => 1,
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'opmessage' => [
							'default_msg' => 0,
							'mediatypeid' => 1,
							'subject' => 'Subject operations '.(string) $i.' [{EVENT.NAME}]',
							'message' => 'Message operations '.(string) $i.' [{EVENT.NAME}]'
						],
						'opmessage_grp' => [
							['usrgrpid' => 7]
						]
					]
				],
				'pause_suppressed' => 0,
				'pause_symptoms' => 0,
				'update_operations' => [
					[
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'opmessage' => [
							'default_msg' => 0,
							'mediatypeid' => 1,
							'subject' => 'Subject update '.(string) $i.' [{EVENT.NAME}]',
							'message' => $this->expandNumberInTemplate(
									self::UPDATE_MESSAGE_TEMPLATE, $i),
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
							'mediatypeid' => 1,
							'subject' => 'Subject recovery '.(string) $i,
							'message' => 'Message recovery '.(string) $i
						],
						'opmessage_grp' => [
							['usrgrpid' => 7]
						]
					]
				]
			]);

			$this->assertArrayHasKey('actionids', $response['result']);
			$this->assertCount(1, $response['result']['actionids']);
			self::$trigger_actionids[$i] = $response['result']['actionids'][0];
		}

		$response = $this->call('user.create', [
			'username' => 'test',
			'passwd' => 'p@sSw0rd',
			'usrgrps' => [
				["usrgrpid" => 7]
			],
			'roleid' => USER_TYPE_SUPER_ADMIN
		]);
		$this->assertArrayHasKey('userids', $response['result']);
	}

	/**
	 * Component configuration provider.
	 *
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'AllowUnsupportedDBVersions' => 1
			]
		];
	}

	/**
	 * Start 5 events/problems. All events/problems are expected to be causes with no symptoms.
	 * There are no events at the beginning of the test, so event ids would be 1, 2, 3, 4, 5.
	 *
	 * Expected result:
	 *
	 * [C] (1)
	 * [C] (2)
	 * [C] (3)
	 * [C] (4)
	 * [C] (5)
	 */
	public function testStartEvents()
	{
		// start events/problems
		for ($i = 1; $i <= 5; $i++) {
			$item_key = self::TRAPPER_ITEM_KEY_PREFIX . (string) $i;
			$this->sendSenderValue(self::HOST_NAME, $item_key, self::EVENT_START);
		}

		// make sure events are started
		for ($i = 1; $i <= 5; $i++) {
			$line = "End substitute_simple_macros_impl() data:'Message operations ".$i;
			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $line, true);
		}

		foreach (['problem.get', 'event.get'] as $request) {
			// get events/problems
			$response = $this->call($request, [
				'output' => [
					'eventid',
					'objectid',
					'cause_eventid',
				],
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER
			]);

			$this->assertArrayHasKey('result', $response);

			$events = [];
			foreach ($response['result'] as $event) {
				$this->assertArrayHasKey('objectid', $event);
				$events[$event['objectid']] = $event;
			}

			// make sure an event is started for each trigger and it is cause with no symptoms
			foreach (self::$trigger_ids as $trigger_id) {
				$this->assertArrayHasKey($trigger_id, $events);
				$this->assertArrayHasKey('eventid', $events[$trigger_id]);
				$this->assertArrayHasKey('cause_eventid', $events[$trigger_id]);
				$this->assertEquals(0, $events[$trigger_id]['cause_eventid']);
			}
		}
	}

	/**
	 * Rank events 2, 3 as symptoms of event 1. Rank event 5 as symptom of event 4.
	 *
	 * Initial position:
	 *
	 * [C] (1)
	 * [C] (2)
	 * [C] (3)
	 * [C] (4)
	 * [C] (5)
	 *
	 * Expected result:
	 *
	 * [C] (1)
	 *  |----[S] (2)
	 *  |----[S] (3)
	 *
	 * [C] (4)
	 * |----[S] (5)
	 *
	 * @depends testStartEvents
	 */
	public function testRankAsSymptom() {
		$this->markAsSymptoms([
			['eventids' => [2, 3], 'cause_eventid' => 1],
			['eventids' => 5, 'cause_eventid' => 4],
		]);
		$this->waitForEventRankUpdate([2, 3, 5]);
		$this->checkUpdateAlertsForNewSymptoms([
			['eventid' => 2, 'cause_eventid' => 1],
			['eventid' => 3, 'cause_eventid' => 1],
			['eventid' => 5, 'cause_eventid' => 4],

		]);
		$this->checkSymptoms([
			['eventid' => 1, 'cause_eventid' => 0],
			['eventid' => 2, 'cause_eventid' => 1],
			['eventid' => 3, 'cause_eventid' => 1],
			['eventid' => 4, 'cause_eventid' => 0],
			['eventid' => 5, 'cause_eventid' => 4],
		]);
	}

	/**
	 * Swap cause and symptom: rank cause to be it's symptom's symptom
	 *
	 * Initial position:
	 *
	 * [C] (1)
	 *  |----[S] (2)
	 *  |----[S] (3)
	 *
	 * [C] (4)
	 * |----[S] (5)
	 *
	 * Expected result:
	 *
	 * [C] (3)          <-- swap is expected here
	 *  |----[S] (1)    <--
	 *  |----[S] (2)
	 *
	 * [C] (4)          <-- no change is expected here
	 * |----[S] (5)
	 *
	 * @depends testRankAsSymptom
	 *
	 */
	public function testSwapCauseAndSymptom() {
		$this->markAsSymptoms([
			['eventids' => [1], 'cause_eventid' => 3],
		]);
		$this->waitForEventRankUpdate([1]);
		$this->checkUpdateAlertsForNewSymptoms([
			['eventid' => 1, 'cause_eventid' => 3],
		]);

		$this->checkSymptoms([
			['eventid' => 1, 'cause_eventid' => 3],
			['eventid' => 2, 'cause_eventid' => 3],
			['eventid' => 3, 'cause_eventid' => 0],
			['eventid' => 4, 'cause_eventid' => 0],
			['eventid' => 5, 'cause_eventid' => 4],
		]);
	}

	/**
	 * Rank cause event 4 (which has one symptom 5) as a symptom of a symptom 2. Events 4 and 5 are expected to
	 * become symptoms of event's 2 cause 3.
	 *
	 * Initial position:
	 *
	 * [C] (3)
	 *  |----[S] (1)
	 *  |----[S] (2)
	 *
	 * [C] (4)
	 * |----[S] (5)
	 *
	 * Expected result:
	 *
	 * [C] (3)
	 *  |----[S] (1)
	 *  |----[S] (2)
	 *  |----[S] (4)
	 *  |----[S] (5)
	 *
	 * @depends testSwapCauseAndSymptom
	 *
	 */
	public function testRankCauseAsSymptomOfSymptom() {
		$this->markAsSymptoms([
			['eventids' => [4], 'cause_eventid' => 2],
		]);
		$this->waitForEventRankUpdate([4]);
		$this->checkUpdateAlertsForNewSymptoms([
			['eventid' => 4, 'cause_eventid' => 3],
		]);
		$this->checkSymptoms([
			['eventid' => 1, 'cause_eventid' => 3],
			['eventid' => 2, 'cause_eventid' => 3],
			['eventid' => 3, 'cause_eventid' => 0],
			['eventid' => 4, 'cause_eventid' => 3],
			['eventid' => 5, 'cause_eventid' => 3],
		]);
	}

	/**
	 * Rank symptom events as causes.
	 *
	 * Initial position:
	 *
	 * [C] (3)
	 *  |----[S] (1)
	 *  |----[S] (2)
	 *  |----[S] (4)
	 *  |----[S] (5)
	 *
	 * Expected result:
	 *
	 * [C] (1)
	 * [C] (2)
	 * [C] (3)
	 * [C] (4)
	 * [C] (5)
	 *
	 * @depends testRankCauseAsSymptomOfSymptom
	 *
	 */
	public function testRankAsCause() {
		// request event/problem ranking
		$response = $this->call('event.acknowledge', [
			'eventids' => [1, 2, 4, 5],
			'action' => ZBX_PROBLEM_UPDATE_EVENT_RANK_TO_CAUSE
		]);

		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('eventids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['eventids']);

		$this->waitForEventRankUpdate([1, 2, 4, 5]);
		$this->checkUpdateAlertsForNewCauses([1, 2, 4, 5]);
		$this->checkSymptoms([
			['eventid' => 1, 'cause_eventid' => 0],
			['eventid' => 2, 'cause_eventid' => 0],
			['eventid' => 3, 'cause_eventid' => 0],
			['eventid' => 4, 'cause_eventid' => 0],
			['eventid' => 5, 'cause_eventid' => 0],
		]);
	}
}

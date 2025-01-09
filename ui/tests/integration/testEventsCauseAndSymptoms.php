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
 * Test suite for testing changing rank of trigger-based events/problems
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @backup hosts,items,triggers,task,task_data,acknowledges,event_symptom,events,problem
 */
class testEventsCauseAndSymptoms extends CIntegrationTest {
	private static $hostid;
	private static $trigger_ids = [];
	private static $event_ids = [];
	private static $cause_events_test_scriptid;
	private static $symptom_events_test_scriptid;
	private static $test_simplemacro;

	const HOST_NAME = 'host cause and symptoms';
	const TRAPPER_ITEM_NAME_PREFIX = 'Trapper item ';
	const TRAPPER_ITEM_KEY_PREFIX = 'trap';
	const EVENT_CAUSE_MACRO_TEMPLATE = 'Macros to test: {EVENT.NAME}|{EVENT.ID}|{EVENT.CAUSE.NAME}|{EVENT.CAUSE.ID}|{EVENT.CAUSE.SOURCE}|{EVENT.CAUSE.OBJECT}|{EVENT.CAUSE.VALUE}';
	const EVENT_SYMPTOMS_MACRO_TEMPLATE = '{EVENT.SYMPTOMS}';
	const TEST_CAUSE_EVENTS_SCRIPT_NAME = 'script test cause events';
	const TEST_SYMPTOM_EVENTS_SCRIPT_NAME = 'script test symptom events';
	const EVENT_COUNT = 5;
	const EVENT_START = 1;

	private function expandMacros($in_str, $event_n, $cause_n = null) {
		$replacements = [
			'{EVENT.NAME}' => 'Trigger trap '.$event_n,
			'{EVENT.ID}' => $this->eventNumToId($event_n)
		];

		if (null != $cause_n) {
			$replacements = array_merge($replacements, [
				'{EVENT.CAUSE.NAME}' => 'Trigger trap '.$cause_n,
				'{EVENT.CAUSE.ID}' => $this->eventNumToId($cause_n),
				'{EVENT.CAUSE.SOURCE}' => EVENT_SOURCE_TRIGGERS,
				'{EVENT.CAUSE.OBJECT}' => EVENT_OBJECT_TRIGGER,
				'{EVENT.CAUSE.VALUE}' => self::EVENT_START
			]);
		}
		else {
			$replacements = array_merge($replacements, [
				'{EVENT.CAUSE.NAME}' => '*UNKNOWN*',
				'{EVENT.CAUSE.ID}' => '*UNKNOWN*',
				'{EVENT.CAUSE.SOURCE}' => '*UNKNOWN*',
				'{EVENT.CAUSE.OBJECT}' => '*UNKNOWN*',
				'{EVENT.CAUSE.VALUE}' => '*UNKNOWN*'
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

	private function eventNumToId($num) {
		if (!is_int($num) && !is_array($num)) {
			throw new Exception("Test error: eventNumToId() arguments must be int or array, got '%s'",
				gettype($num));
		}

		if (is_array($num)) {
			$a = [];

			foreach ($num as $n)
				$a[] = $this->eventNumToId($n);

			return $a;
		}

		if (0 == $num)
			return 0;
		return self::$event_ids[$num];
	}

	private function markAsSymptoms($rank_as_symptom_requests) {
		foreach ($rank_as_symptom_requests as $request) {
			$event_ids = $this->eventNumToId($request['event_nums']);
			$cause_event_ids = $this->eventNumToId($request['cause_event_num']);

			$response = $this->call('event.acknowledge', [
				'eventids' => $event_ids,
				'action' => ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM,
				'cause_eventid' => $cause_event_ids
			]);

			$this->assertArrayHasKey('result', $response);
			$this->assertArrayHasKey('eventids', $response['result']);
			if (is_array($event_ids))
				$this->assertCount(count($event_ids), $response['result']['eventids']);
			else
				$this->assertArrayHasKey(0, $response['result']['eventids']);
		}
	}

	private function checkSymptomsUntilSuccessOrTimeout($expected_events) {
		$max_attempts = 10;
		$sleep_time = 1;

		for ($i = 0; $i < $max_attempts - 1; $i++) {
			try {
				$this->checkSymptoms($expected_events);
				return;
			} catch (Exception $e) {
				sleep($sleep_time);
			}
		}

		$this->checkSymptoms($expected_events);
	}

	private function checkSymptoms($expected_events) {
		foreach (['problem.get', 'event.get'] as $request_type) {
			// get events/problems
			$response = $this->call($request_type, [
				'output' => [
					'eventid',
					'cause_eventid'
				],
				'objectids' => self::$trigger_ids,
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER
			]);

			$this->assertArrayHasKey('result', $response);
			$this->assertCount(self::EVENT_COUNT, $response['result']);

			$events = [];
			foreach ($response['result'] as $event) {
				$this->assertArrayHasKey('eventid', $event);
				$this->assertArrayHasKey('cause_eventid', $event);
				$events[$event['eventid']] = $event;
			}

			// check if actual cause eventid matches the expected cause eventid
			foreach ($expected_events as $expected_event) {
				$expected_event_id = $this->eventNumToId($expected_event['event_num']);
				$expected_cause_eventid = $this->eventNumToId($expected_event['cause_event_num']);
				$this->assertEquals($expected_cause_eventid,
						$events[$expected_event_id]['cause_eventid']);
			}
		}
	}

	private function checkEventsStartUntilSuccessOrTimeout() {
		$max_attempts = 5;
		$sleep_time = 1;

		for ($i = 0; $i < $max_attempts - 1; $i++) {
			try {
				return $this->checkEventsStart();
			} catch (Exception $e) {
				sleep($sleep_time);
			}
		}

		return $this->checkEventsStart();
	}

	private function checkEventsStart() {
		foreach (['problem.get', 'event.get'] as $request_type) {
			// get events/problems
			$response = $this->callUntilDataIsPresent($request_type, [
				'output' => [
					'eventid',
					'objectid',
					'cause_eventid'
				],
				'objectids' => self::$trigger_ids,
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER
			]);

			$this->assertArrayHasKey('result', $response);
			$this->assertCount(self::EVENT_COUNT, $response['result']);

			$events = [];
			foreach ($response['result'] as $event) {
				$this->assertArrayHasKey('eventid', $event);
				$this->assertArrayHasKey('objectid', $event);
				$this->assertArrayHasKey('cause_eventid', $event);
				$this->assertEquals(0, $event['cause_eventid']);
				$events[$event['objectid']] = $event;
			}

			// make sure an event is started for each trigger and it is cause with no symptoms
			foreach (self::$trigger_ids as $trigger_id) {
				$this->assertArrayHasKey($trigger_id, $events);
			}
		}
		return $events;
	}

	private function checkEventCauseMacros($expected_events) {
		foreach ($expected_events as $expected_event) {
			$eventid = $this->eventNumToId($expected_event['event_num']);
			$expected_value = $this->expandMacros(self::EVENT_CAUSE_MACRO_TEMPLATE, $expected_event['event_num'],
					$expected_event['cause_event_num']);

			$response = $this->callUntilDataIsPresent('script.execute', [
				'scriptid' => self::$cause_events_test_scriptid,
				'eventid' => $eventid
			], 10, 1);
			$this->assertArrayHasKey('result', $response);
			$this->assertArrayHasKey('value', $response['result']);
			$this->assertEquals($expected_value, $response['result']['value']);
		}
	}

	private function checkEventSymptomMacros($expected_events) {
		$expected_symptoms = [];

		foreach ($expected_events as $expected_event) {
			$cause_event_num = $expected_event['cause_event_num'];
			if (0 != $cause_event_num) {
				if (!array_key_exists($cause_event_num, $expected_symptoms))
					$expected_symptoms[$cause_event_num] = [];

				$symptom_event_num = $expected_event['event_num'];
				$expected_symptoms[$cause_event_num][] = $symptom_event_num;
			}
		}

		foreach ($expected_symptoms as $cause_event_num => $symptom_events_nums) {
			$response = $this->callUntilDataIsPresent('script.execute', [
				'scriptid' => self::$symptom_events_test_scriptid,
				'eventid' => $this->eventNumToId($cause_event_num)
			], 10, 1);
			$this->assertArrayHasKey('result', $response);
			$this->assertArrayHasKey('value', $response['result']);

			$expanded_macro = $response['result']['value'];
			$lines = explode("\n", $expanded_macro);
			$this->assertCount(count($symptom_events_nums), $lines);

			// for all expected symptoms
			for ($i = 0; $i < count($symptom_events_nums); $i++) {
				$match_found = false;
				$needle = sprintf("Host: %s Problem name: %s Severity: Not classified Age:",
						self::HOST_NAME, 'Trigger trap '.(string)$symptom_events_nums[$i]);

				// search for the expected symptom in the expanded macro line by line
				foreach ($lines as $haystack) {
					$cmp_result = substr_compare($haystack, $needle, 0, strlen($needle));
					if (0 == $cmp_result) {
						$match_found = true;
						break;
					}
				}

				$this->assertTrue($match_found, sprintf("A symptom which starts with:\n'%s'\nis not found in the expanded macro '%s':\n\n%s\n",
						$needle, self::EVENT_SYMPTOMS_MACRO_TEMPLATE, $expanded_macro));
			}
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

		for ($i = 1; $i <= self::EVENT_COUNT; $i++) {
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
				'expression' => 'last(/'.self::HOST_NAME.'/'.$item_key.')='.self::EVENT_START
			]);

			$this->assertArrayHasKey('result', $response);
			$this->assertArrayHasKey('triggerids', $response['result']);
			$this->assertEquals(1, count($response['result']['triggerids']));

			$trigger_id = $response['result']['triggerids'][0];
			self::$trigger_ids[$i] = $trigger_id;
		}
	}

	/**
	 * Component configuration provider for server.
	 *
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'EnableGlobalScripts' => 1
			]
		];
	}

	private function createScripts() {
		// create a script for testing cause events
		$response = $this->call('script.create', [
			'name' => self::TEST_CAUSE_EVENTS_SCRIPT_NAME,
			'command' => sprintf('echo -n "%s"',  self::EVENT_CAUSE_MACRO_TEMPLATE),
			'execute_on' => ZBX_SCRIPT_EXECUTE_ON_SERVER,
			'scope' => ZBX_SCRIPT_SCOPE_EVENT,
			'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT
		]);
		$this->assertArrayHasKey('scriptids', $response['result']);
		self::$cause_events_test_scriptid = $response['result']['scriptids'][0];

		// create a script for testing symptom events
		$response = $this->call('script.create', [
			'name' => self::TEST_SYMPTOM_EVENTS_SCRIPT_NAME,
			'command' => sprintf('echo -n "%s"',  self::EVENT_SYMPTOMS_MACRO_TEMPLATE),
			'execute_on' => ZBX_SCRIPT_EXECUTE_ON_SERVER,
			'scope' => ZBX_SCRIPT_SCOPE_EVENT,
			'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT
		]);
		$this->assertArrayHasKey('scriptids', $response['result']);
		self::$symptom_events_test_scriptid = $response['result']['scriptids'][0];

		// create a script for testing simple build in macro
		$response = $this->call('script.create', [
			'name' => 'simple macro',
			'command' => 'echo -n "{EVENT.TIMESTAMP}"',
			'execute_on' => ZBX_SCRIPT_EXECUTE_ON_SERVER,
			'scope' => ZBX_SCRIPT_SCOPE_EVENT,
			'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT
		]);
		$this->assertArrayHasKey('scriptids', $response['result']);
		self::$test_simplemacro = $response['result']['scriptids'][0];

	}

	/**
	 * Start 5 events/problems. All events/problems are expected to be causes with no symptoms.
	 *
	 * Expected result:
	 *
	 * [C] (1)
	 * [C] (2)
	 * [C] (3)
	 * [C] (4)
	 * [C] (5)
	 *
	 * @configurationDataProvider serverConfigurationProvider
	 *
	 */
	public function testEventsCauseAndSymptoms_startEvents()
	{
		$this->createScripts();
		// start events/problems
		for ($i = 1; $i <= self::EVENT_COUNT ; $i++) {
			$item_key = self::TRAPPER_ITEM_KEY_PREFIX . (string) $i;
			$this->sendSenderValue(self::HOST_NAME, $item_key, self::EVENT_START);
		}

		$events = $this->checkEventsStartUntilSuccessOrTimeout();

		// save event ids
		for ($i = 1; $i <= self::EVENT_COUNT; $i++) {
			$trigger_id = self::$trigger_ids[$i];
			self::$event_ids[$i] = $events[$trigger_id]['eventid'];
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
	 * @configurationDataProvider serverConfigurationProvider
	 * @depends testEventsCauseAndSymptoms_startEvents
	 *
	 */
	public function testEventsCauseAndSymptoms_rankAsSymptom() {
		$this->markAsSymptoms([
			['event_nums' => [2, 3], 'cause_event_num' => 1],
			['event_nums' => 5, 'cause_event_num' => 4]
		]);

		$expected = [
			['event_num' => 1, 'cause_event_num' => 0],
			['event_num' => 2, 'cause_event_num' => 1],
			['event_num' => 3, 'cause_event_num' => 1],
			['event_num' => 4, 'cause_event_num' => 0],
			['event_num' => 5, 'cause_event_num' => 4]
		];

		$this->checkSymptomsUntilSuccessOrTimeout($expected);
		$this->checkEventCauseMacros($expected);
		$this->checkEventSymptomMacros($expected);
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
	 * @configurationDataProvider serverConfigurationProvider
	 * @depends testEventsCauseAndSymptoms_rankAsSymptom
	 *
	 */
	public function testEventsCauseAndSymptoms_swapCauseAndSymptom() {
		$this->markAsSymptoms([
			['event_nums' => 1, 'cause_event_num' => 3]
		]);

		$expected = [
			['event_num' => 1, 'cause_event_num' => 3],
			['event_num' => 2, 'cause_event_num' => 3],
			['event_num' => 3, 'cause_event_num' => 0],
			['event_num' => 4, 'cause_event_num' => 0],
			['event_num' => 5, 'cause_event_num' => 4]
		];

		$this->checkSymptomsUntilSuccessOrTimeout($expected);
		$this->checkEventCauseMacros($expected);
		$this->checkEventSymptomMacros($expected);
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
	 * @configurationDataProvider serverConfigurationProvider
	 * @depends testEventsCauseAndSymptoms_swapCauseAndSymptom
	 *
	 */
	public function testEventsCauseAndSymptoms_rankCauseAsSymptomOfSymptom() {
		$this->markAsSymptoms([
			['event_nums' => 4, 'cause_event_num' => 2]
		]);

		$expected = [
			['event_num' => 1, 'cause_event_num' => 3],
			['event_num' => 2, 'cause_event_num' => 3],
			['event_num' => 3, 'cause_event_num' => 0],
			['event_num' => 4, 'cause_event_num' => 3],
			['event_num' => 5, 'cause_event_num' => 3]
		];

		$this->checkSymptomsUntilSuccessOrTimeout($expected);
		$this->checkEventCauseMacros($expected);
		$this->checkEventSymptomMacros($expected);
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
	 *
	 * [C] (3)
	 *  |----[S] (4)
	 *  |----[S] (5)
	 *
	 * @configurationDataProvider serverConfigurationProvider
	 * @depends testEventsCauseAndSymptoms_rankCauseAsSymptomOfSymptom
	 *
	 */
	public function testEventsCauseAndSymptoms_rankAsCause1() {
		// request event/problem ranking
		$response = $this->call('event.acknowledge', [
			'eventids' => $this->eventNumToId([1, 2]),
			'action' => ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE
		]);

		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('eventids', $response['result']);
		$this->assertCount(2,  $response['result']['eventids']);

		$expected = [
			['event_num' => 1, 'cause_event_num' => 0],
			['event_num' => 2, 'cause_event_num' => 0],
			['event_num' => 3, 'cause_event_num' => 0],
			['event_num' => 4, 'cause_event_num' => 3],
			['event_num' => 5, 'cause_event_num' => 3]
		];

		$this->checkSymptomsUntilSuccessOrTimeout($expected);
		$this->checkEventCauseMacros($expected);
		$this->checkEventSymptomMacros($expected);
	}

	/**
	 * Rank symptom events as causes.
	 *
	 * Initial position:
	 *
	 * [C] (1)
	 * [C] (2)
	 *
	 * [C] (3)
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
	 * @configurationDataProvider serverConfigurationProvider
	 * @depends testEventsCauseAndSymptoms_rankAsCause1
	 *
	 */
	public function testEventsCauseAndSymptoms_rankAsCause2() {
		// request event/problem ranking
		$response = $this->call('event.acknowledge', [
			'eventids' => $this->eventNumToId([4, 5]),
			'action' => ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE
		]);

		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('eventids', $response['result']);
		$this->assertCount(2,  $response['result']['eventids']);

		$expected = [
			['event_num' => 1, 'cause_event_num' => 0],
			['event_num' => 2, 'cause_event_num' => 0],
			['event_num' => 3, 'cause_event_num' => 0],
			['event_num' => 4, 'cause_event_num' => 0],
			['event_num' => 5, 'cause_event_num' => 0]
		];

		$this->checkSymptomsUntilSuccessOrTimeout($expected);
		$this->checkEventCauseMacros($expected);
	}

	/**
	 * Rank symptom events as causes to check {EVENT.TIMESTAMP} macro.
	 *
	 * @configurationDataProvider serverConfigurationProvider
	 * @depends testEventsCauseAndSymptoms_rankAsCause1
	 *
	 */
	public function testEventsSimpleMacro() {
		// request event/problem ranking
		$response = $this->call('event.acknowledge', [
			'eventids' => $this->eventNumToId([1]),
			'action' => ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE
		]);

		$response = $this->callUntilDataIsPresent('script.execute', [
			'scriptid' => self::$test_simplemacro,
			'eventid' =>  1
		], 10, 1);

		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('value', $response['result']);

		$expanded_macro = $response['result']['value'];
		$this->assertLessThanOrEqual(10000, abs(intval($expanded_macro) - microtime(true)), json_encode($response['result']));
	}

}

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

require_once dirname(__FILE__) . '/../include/CIntegrationTest.php';


/**
 * Test suite for trigger functions.
 *
 * @required-components server
 * @suite-components-reuse true
 * @configurationDataProvider serverConfigurationProvider
 * @onAfterOnce clearData
 *
 */
class testFunctions extends CIntegrationTest{

	const HOSTNAME_MAIN = "function_host";
	const LASTCLOCK = 1762430060;
	const FIRSTCLOCK = 1762430300;

	private static $hostid;
	private static $templateid;
	private static $timeOffset = 0;

	/**
	 *
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 0
			]
		];
	}

	// Import host / template.
	private function importData($name) {
		$data = file_get_contents('integration/data/functions/' . $name . '.yaml');

		$response = $this->call('configuration.import', [
			'format' => 'yaml',
			'source' => $data,
			'rules' => [
				'hosts' =>
				[
					'updateExisting' => true,
					'createMissing' => true
				],
				'valueMaps' =>
				[
					'updateExisting' => true,
					'createMissing' => true,
					'deleteMissing' => false
				],
				'templateLinkage' =>
				[
					'createMissing' => true,
					'deleteMissing' => false
				],
				'items' =>
				[
					'updateExisting' => true,
					'createMissing' => true,
					'deleteMissing' => false
				],
				'discoveryRules' =>
				[
					'updateExisting' => true,
					'createMissing' => true,
					'deleteMissing' => false
				],
				'triggers' =>
				[
					'updateExisting' => true,
					'createMissing' => true,
					'deleteMissing' => false
				],
				'graphs' =>
				[
					'updateExisting' => true,
					'createMissing' => true,
					'deleteMissing' => false
				],
				'httptests' =>
				[
					'updateExisting' => true,
					'createMissing' => true,
					'deleteMissing' => false
				],
				'templates' =>
				[
					'updateExisting' => true,
					'createMissing' => true
				]
			]
		]);
		$this->assertEquals(true, $response['result']);
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		$this->importData("template_functions");

		$response = $this->call('template.get', [
			'output' => ['templateid'],
			'filter' => [
				'host' => ['Integration function test']
			]
		]);

		$this->assertCount(1, $response['result']);
		self::$templateid = $response['result'][0]['templateid'];

		$response = $this->call('hostgroup.get', [
			'filter' => ['name' => ['Zabbix servers']],
			'output' => ['groupid']
		]);
		$this->assertNotEmpty($response['result'], 'Host group "Zabbix servers" not found.');
		$groupid = $response['result'][0]['groupid'];

		$response = $this->call('host.create', [
			'host' => self::HOSTNAME_MAIN,
			'interfaces' => [],
			'groups' => [
				['groupid' => $groupid]
			],
			'templates' => [
				'templateid' => self::$templateid
			]
		]);

		$this->assertCount(1, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		$this->getTimeOffset('values1');

		$this->call('usermacro.create', [
			[
				'hostid' => self::$hostid,
				'macro' => '{$FIRSTCLOCK}',
				'value' => (string)(self::FIRSTCLOCK + self::$timeOffset)
			],
			[
				'hostid' => self::$hostid,
				'macro' => '{$LASTCLOCK}',
				'value' => (string)(self::LASTCLOCK + self::$timeOffset)
			]
		]);
	}

	private function getTimeOffset($filename) {
		$data = file_get_contents('integration/data/functions/' . $filename);
		$lines = explode("\n", trim($data));

		$maxClock = 0;

		foreach ($lines as $line) {
			if (trim($line) === '') {
				continue;
			}

			$parts = preg_split('/\s+/', trim($line));

			if (count($parts) >= 4) {
				$clockValue = (int)$parts[2];

				if ($clockValue > $maxClock) {
					$maxClock = $clockValue;
				}
			}
		}

		$currentTime = time();
		$timeDiff = $currentTime - $maxClock;
		$daysToAdd = floor($timeDiff / 86400);
		self::$timeOffset = $daysToAdd * 86400;
	}

	private function getSenderData($filename) {
		$data = file_get_contents('integration/data/functions/' . $filename);
		$lines = explode("\n", trim($data));

		$data = [];

		foreach ($lines as $line) {
			if (trim($line) === '') {
				continue;
			}

			$parts = preg_split('/\s+/', trim($line));

			if (count($parts) >= 4) {
				$data[] = [
					'host' => self::HOSTNAME_MAIN,
					'key' => $parts[1],
					'value' => $parts[3],
					'clock' => (int)$parts[2]
				];
			}
		}

		foreach ($data as &$value) {
			$value['clock'] += self::$timeOffset;
		}

		return $data;
	}

	private function sendValues($filename) {
		$data = $this->getSenderData($filename);
		$this->sendSenderValues($data, null, 0);
	}

	private function processStep1() {
		$this->sendValues('values1');

		$this->callUntilDataIsPresent('trigger.get', [
			'output' => ['description', 'state', 'value', 'error'],
			'selectFunctions' => 'extend',
			'hostids' => self::$hostid
		], null, null, function($response) {
			return $this->assertStep1TriggerExpectations($response);
		});
	}

	private function assertStep1TriggerExpectations($response) {
		$triggers_expected = [
			'Item 01 min(#5) gt 10' => ['state' => 0, 'value' => 0],
			'Item 01 min(5m) gt 10' => ['state' => 0, 'value' => 0],
			'Item 02 max(#5) gt 10' => ['state' => 0, 'value' => 0],
			'Item 02 max(5m) gt 10' => ['state' => 0, 'value' => 0],
			'Item 03 avg(#5) gt 10' => ['state' => 0, 'value' => 0],
			'Item 03 avg(5m) gt 10' => ['state' => 0, 'value' => 0],
			'Item 04 sum(#5) gt 10' => ['state' => 0, 'value' => 0],
			'Item 04 sum(5m) gt 10' => ['state' => 0, 'value' => 0],
			'Item 05 percentile(#5, 90) gt 10' => ['state' => 0, 'value' => 0],
			'Item 05 percentile(5m, 90) gt 10' => ['state' => 0, 'value' => 0],
			'Item 06 count(#5, "eq", 10) eq 2' => ['state' => 0, 'value' => 0],
			'Item 06 count(5m, "eq", 10) eq 2' => ['state' => 0, 'value' => 0],
			'Item 07 countunique(#5, ) eq 2' => ['state' => 0, 'value' => 0],
			'Item 07 countunique(5m, ) eq 2' => ['state' => 0, 'value' => 0],
			'Item 08 nodata(30s) eq 1' => ['state' => 0, 'value' => 0],
			'Item 09 change() eq 1' => ['state' => 1, 'value' => 0],
			'Item 10 find(#5, "eq", 10) eq 1' => ['state' => 0, 'value' => 0],
			'Item 11 fuzzytime(1) eq 0' => ['state' => 0, 'value' => 1],
			'Item 12 logeventid(10) eq 1' => ['state' => 0, 'value' => 0],
			'Item 13 logseverity() eq 1' => ['state' => 0, 'value' => 0],
			'Item 14 logsource(xyz) eq 1' => ['state' => 0, 'value' => 0],
			'Item 15 forecast(#5) gt 1000' => ['state' => 0, 'value' => 0],
			'Item 15 forecast(5m) gt 1000' => ['state' => 0, 'value' => 0],
			'Item 16 timeleft(#5) gt 0' => ['state' => 0, 'value' => 0],
			'Item 16 timeleft(5m) gt 0' => ['state' => 0, 'value' => 0],
			'Item 17 first(30s) eq 1' => ['state' => 0, 'value' => 0],
			'Item 18 kurtosis(#5) eq  1' => ['state' => 1, 'value' => 0],
			'Item 18 kurtosis(5m) eq  1' => ['state' => 1, 'value' => 0],
			'Item 19 mad(#5) eq 1' => ['state' => 0, 'value' => 0],
			'Item 19 mad(5m) eq 1' => ['state' => 0, 'value' => 0],
			'Item 20 skewness(#5) eq 0' => ['state' => 1, 'value' => 0],
			'Item 20 skewness(5m) eq 0' => ['state' => 1, 'value' => 0],
			'Item 21 stddevpop(#5) eq 1' => ['state' => 0, 'value' => 0],
			'Item 21 stddevpop(5m) eq 1' => ['state' => 0, 'value' => 0],
			'Item 22 stddevsamp(#5) lt 1' => ['state' => 1, 'value' => 0],
			'Item 22 stddevsamp(5m) lt 1' => ['state' => 1, 'value' => 0],
			'Item 23 sumofsquares(#5) eq 1' => ['state' => 0, 'value' => 0],
			'Item 23 sumofsquares(5m) eq 1' => ['state' => 0, 'value' => 0],
			'Item 24 varpop(#5) eq 1' => ['state' => 0, 'value' => 0],
			'Item 24 varpop(5m) eq 1' => ['state' => 0, 'value' => 0],
			'Item 25 varsamp(#5) lt 1' => ['state' => 1, 'value' => 0],
			'Item 25 varsamp(5m) lt 1' => ['state' => 1, 'value' => 0],
			'Item 26 monoinc(#5) eq 0' => ['state' => 0, 'value' => 0],
			'Item 26 monoinc(5m) eq 0' => ['state' => 0, 'value' => 0],
			'Item 27 monodec(#5) eq 0' => ['state' => 0, 'value' => 0],
			'Item 27 monodec(5m) eq 0' => ['state' => 0, 'value' => 0],
			'Item 28 rate(5m) eq 1' => ['state' => 1, 'value' => 0],
			'Item 29 changecount(#5) eq 1' => ['state' => 1, 'value' => 0],
			'Item 29 changecount(5m) eq 1' => ['state' => 1, 'value' => 0],
			'Item 30 lastclock() eq {$LASTCLOCK}' => ['state' => 0, 'value' => 0],
			'Item 31 logtimestamp() eq 0' => ['state' => 1, 'value' => 0],
			'Item 32 firstclock(5m) eq {$FIRSTCLOCK}' => ['state' => 0, 'value' => 0],
			'Item 52 trendcount eq 3' => ['state' => 1, 'value' => 0], /* last 0 not synced to database yet */
			'Item 52 trendmin eq 2' => ['state' => 1, 'value' => 0],
			'Item 52 trendmax eq 8' => ['state' => 1, 'value' => 0],
			'Item 52 trendsum eq 14' => ['state' => 1, 'value' => 0],
			'Item 52 trendavg eq 4.6' => ['state' => 1, 'value' => 0],
			'Item 53 trendstl eq 0' => ['state' => 1, 'value' => 0],
			'Item 54 baselinedev eq 0' => ['state' => 0, 'value' => 0],
			'Item 55 baselinewma eq 0' => ['state' => 0, 'value' => 0]
		];

		$failures = [];
		foreach ($response['result'] as $trigger) {
			$description = $trigger['description'];

			if (!array_key_exists($description, $triggers_expected)) {
				$failures[] = "[1] Unexpected trigger: $description";
				continue;
			}
			$expected = $triggers_expected[$description];

			if ($expected['state'] != $trigger['state']) {
				$failures[] = "[1] State mismatch for trigger: $description: ".json_encode($trigger);
			}
			if ($expected['value'] != $trigger['value']) {
				$failures[] = "[1] Value mismatch for trigger: $description";
			}
		}
		return $failures === [] ? true : implode("\n", $failures);
	}

	private function processStep2() {
		$this->sendValues('values2');

		$this->callUntilDataIsPresent('trigger.get', [
			'output' => ['description', 'state', 'value', 'error'],
			'selectFunctions' => 'extend',
			'hostids' => self::$hostid
		], null, null, function($response) {
			return $this->assertStep2TriggerExpectations($response);
		});
	}

	private function assertStep2TriggerExpectations($response) {
		$triggers_expected = [
			'Item 01 min(#5) gt 10' => ['state' => 0, 'value' => 0],
			'Item 01 min(5m) gt 10' => ['state' => 0, 'value' => 1],
			'Item 02 max(#5) gt 10' => ['state' => 0, 'value' => 1],
			'Item 02 max(5m) gt 10' => ['state' => 0, 'value' => 1],
			'Item 03 avg(#5) gt 10' => ['state' => 0, 'value' => 1],
			'Item 03 avg(5m) gt 10' => ['state' => 0, 'value' => 1],
			'Item 04 sum(#5) gt 10' => ['state' => 0, 'value' => 1],
			'Item 04 sum(5m) gt 10' => ['state' => 0, 'value' => 1],
			'Item 05 percentile(#5, 90) gt 10' => ['state' => 0, 'value' => 1],
			'Item 05 percentile(5m, 90) gt 10' => ['state' => 0, 'value' => 1],
			'Item 06 count(#5, "eq", 10) eq 2' => ['state' => 0, 'value' => 1],
			'Item 06 count(5m, "eq", 10) eq 2' => ['state' => 0, 'value' => 1],
			'Item 07 countunique(#5, ) eq 2' => ['state' => 0, 'value' => 1],
			'Item 07 countunique(5m, ) eq 2' => ['state' => 0, 'value' => 1],
			'Item 08 nodata(30s) eq 1' => ['state' => 0, 'value' => 0],
			'Item 09 change() eq 1' => ['state' => 0, 'value' => 1],
			'Item 10 find(#5, "eq", 10) eq 1' => ['state' => 0, 'value' => 1],
			'Item 11 fuzzytime(1) eq 0' => ['state' => 0, 'value' => 1],
			'Item 12 logeventid(10) eq 1' => ['state' => 0, 'value' => 0],
			'Item 13 logseverity() eq 1' => ['state' => 0, 'value' => 0],
			'Item 14 logsource(xyz) eq 1' => ['state' => 0, 'value' => 0],
			'Item 15 forecast(#5) gt 1000' => ['state' => 0, 'value' => 1],
			'Item 15 forecast(5m) gt 1000' => ['state' => 0, 'value' => 1],
			'Item 16 timeleft(#5) gt 0' => ['state' => 0, 'value' => 1],
			'Item 16 timeleft(5m) gt 0' => ['state' => 0, 'value' => 1],
			'Item 17 first(30s) eq 1' => ['state' => 0, 'value' => 1],
			'Item 18 kurtosis(#5) eq  1' => ['state' => 0, 'value' => 1],
			'Item 18 kurtosis(5m) eq  1' => ['state' => 0, 'value' => 1],
			'Item 19 mad(#5) eq 1' => ['state' => 0, 'value' => 1],
			'Item 19 mad(5m) eq 1' => ['state' => 0, 'value' => 1],
			'Item 20 skewness(#5) eq 0' => ['state' => 0, 'value' => 1],
			'Item 20 skewness(5m) eq 0' => ['state' => 0, 'value' => 1],
			'Item 21 stddevpop(#5) eq 1' => ['state' => 0, 'value' => 1],
			'Item 21 stddevpop(5m) eq 1' => ['state' => 0, 'value' => 1],
			'Item 22 stddevsamp(#5) lt 1' => ['state' => 0, 'value' => 1],
			'Item 22 stddevsamp(5m) lt 1' => ['state' => 0, 'value' => 1],
			'Item 23 sumofsquares(#5) eq 1' => ['state' => 0, 'value' => 1],
			'Item 23 sumofsquares(5m) eq 1' => ['state' => 0, 'value' => 1],
			'Item 24 varpop(#5) eq 1' => ['state' => 0, 'value' => 1],
			'Item 24 varpop(5m) eq 1' => ['state' => 0, 'value' => 1],
			'Item 25 varsamp(#5) lt 1' => ['state' => 0, 'value' => 1],
			'Item 25 varsamp(5m) lt 1' => ['state' => 0, 'value' => 1],
			'Item 26 monoinc(#5) eq 0' => ['state' => 0, 'value' => 1],
			'Item 26 monoinc(5m) eq 0' => ['state' => 0, 'value' => 1],
			'Item 27 monodec(#5) eq 0' => ['state' => 0, 'value' => 1],
			'Item 27 monodec(5m) eq 0' => ['state' => 0, 'value' => 1],
			'Item 28 rate(5m) eq 1' => ['state' => 0, 'value' => 1],
			'Item 29 changecount(#5) eq 1' => ['state' => 0, 'value' => 1],
			'Item 29 changecount(5m) eq 1' => ['state' => 0, 'value' => 1],
			'Item 30 lastclock() eq {$LASTCLOCK}' => ['state' => 0, 'value' => 1],
			'Item 31 logtimestamp() eq 0' => ['state' => 1, 'value' => 0],
			'Item 32 firstclock(5m) eq {$FIRSTCLOCK}' => ['state' => 0, 'value' => 1],
			'Item 52 trendcount eq 3' => ['state' => 0, 'value' => 1],
			'Item 52 trendmin eq 2' => ['state' => 0, 'value' => 1],
			'Item 52 trendmax eq 8' => ['state' => 0, 'value' => 1],
			'Item 52 trendsum eq 14' => ['state' => 0, 'value' => 1],
			'Item 52 trendavg eq 4.6' => ['state' => 0, 'value' => 1],
			'Item 53 trendstl eq 0' => ['state' => 0, 'value' => 1],
			'Item 54 baselinedev eq 0' => ['state' => 0, 'value' => 0],
			'Item 55 baselinewma eq 0' => ['state' => 0, 'value' => 0]
		];

		$failures = [];
		foreach ($response['result'] as $trigger) {
			$description = $trigger['description'];

			if (!array_key_exists($description, $triggers_expected)) {
				$failures[] = "[2] Unexpected trigger: $description";
				continue;
			}
			$expected = $triggers_expected[$description];

			if ($expected['state'] != $trigger['state']) {
				$failures[] = "[2] State mismatch for trigger: $description: ".json_encode($trigger);
			}
			if ($expected['value'] != $trigger['value']) {
				$failures[] = "[2] Value mismatch for trigger: $description".json_encode($trigger);
			}
		}
		return $failures === [] ? true : implode("\n", $failures);
	}

	private function processStep3() {
		$this->sendValues('values3');

		$this->callUntilDataIsPresent('trigger.get', [
			'output' => ['description', 'state', 'value', 'error'],
			'selectFunctions' => 'extend',
			'hostids' => self::$hostid
		], null, null, function($response) {
			return $this->assertStep3TriggerExpectations($response);
		});
	}

	private function assertStep3TriggerExpectations($response) {
		$triggers_expected = [
			'Item 01 min(#5) gt 10' => ['state' => 0, 'value' => 0],
			'Item 01 min(5m) gt 10' => ['state' => 0, 'value' => 0],
			'Item 02 max(#5) gt 10' => ['state' => 0, 'value' => 1],
			'Item 02 max(5m) gt 10' => ['state' => 0, 'value' => 0],
			'Item 03 avg(#5) gt 10' => ['state' => 0, 'value' => 0],
			'Item 03 avg(5m) gt 10' => ['state' => 0, 'value' => 0],
			'Item 04 sum(#5) gt 10' => ['state' => 0, 'value' => 1],
			'Item 04 sum(5m) gt 10' => ['state' => 0, 'value' => 0],
			'Item 05 percentile(#5, 90) gt 10' => ['state' => 0, 'value' => 1],
			'Item 05 percentile(5m, 90) gt 10' => ['state' => 0, 'value' => 0],
			'Item 06 count(#5, "eq", 10) eq 2' => ['state' => 0, 'value' => 0],
			'Item 06 count(5m, "eq", 10) eq 2' => ['state' => 0, 'value' => 0],
			'Item 07 countunique(#5, ) eq 2' => ['state' => 0, 'value' => 0],
			'Item 07 countunique(5m, ) eq 2' => ['state' => 0, 'value' => 0],
			'Item 08 nodata(30s) eq 1' => ['state' => 0, 'value' => 0],
			'Item 09 change() eq 1' => ['state' => 0, 'value' => 0],
			'Item 10 find(#5, "eq", 10) eq 1' => ['state' => 0, 'value' => 1],
			'Item 11 fuzzytime(1) eq 0' => ['state' => 0, 'value' => 1],
			'Item 12 logeventid(10) eq 1' => ['state' => 0, 'value' => 0],
			'Item 13 logseverity() eq 1' => ['state' => 0, 'value' => 0],
			'Item 14 logsource(xyz) eq 1' => ['state' => 0, 'value' => 0],
			'Item 15 forecast(#5) gt 1000' => ['state' => 0, 'value' => 0],
			'Item 15 forecast(5m) gt 1000' => ['state' => 0, 'value' => 0],
			'Item 16 timeleft(#5) gt 0' => ['state' => 0, 'value' => 0],
			'Item 16 timeleft(5m) gt 0' => ['state' => 0, 'value' => 0],
			'Item 17 first(30s) eq 1' => ['state' => 0, 'value' => 0],
			'Item 18 kurtosis(#5) eq  1' => ['state' => 0, 'value' => 0],
			'Item 18 kurtosis(5m) eq  1' => ['state' => 0, 'value' => 0],
			'Item 19 mad(#5) eq 1' => ['state' => 0, 'value' => 0],
			'Item 19 mad(5m) eq 1' => ['state' => 0, 'value' => 0],
			'Item 20 skewness(#5) eq 0' => ['state' => 0, 'value' => 0],
			'Item 20 skewness(5m) eq 0' => ['state' => 0, 'value' => 0],
			'Item 21 stddevpop(#5) eq 1' => ['state' => 0, 'value' => 0],
			'Item 21 stddevpop(5m) eq 1' => ['state' => 0, 'value' => 0],
			'Item 22 stddevsamp(#5) lt 1' => ['state' => 0, 'value' => 0],
			'Item 22 stddevsamp(5m) lt 1' => ['state' => 0, 'value' => 0],
			'Item 23 sumofsquares(#5) eq 1' => ['state' => 0, 'value' => 0],
			'Item 23 sumofsquares(5m) eq 1' => ['state' => 0, 'value' => 0],
			'Item 24 varpop(#5) eq 1' => ['state' => 0, 'value' => 0],
			'Item 24 varpop(5m) eq 1' => ['state' => 0, 'value' => 0],
			'Item 25 varsamp(#5) lt 1' => ['state' => 0, 'value' => 0],
			'Item 25 varsamp(5m) lt 1' => ['state' => 0, 'value' => 0],
			'Item 26 monoinc(#5) eq 0' => ['state' => 0, 'value' => 1],
			'Item 26 monoinc(5m) eq 0' => ['state' => 0, 'value' => 0],
			'Item 27 monodec(#5) eq 0' => ['state' => 0, 'value' => 1],
			'Item 27 monodec(5m) eq 0' => ['state' => 0, 'value' => 0],
			'Item 28 rate(5m) eq 1' => ['state' => 0, 'value' => 0],
			'Item 29 changecount(#5) eq 1' => ['state' => 0, 'value' => 0],
			'Item 29 changecount(5m) eq 1' => ['state' => 0, 'value' => 0],
			'Item 30 lastclock() eq {$LASTCLOCK}' => ['state' => 0, 'value' => 0],
			'Item 31 logtimestamp() eq 0' => ['state' => 1, 'value' => 0],
			'Item 32 firstclock(5m) eq {$FIRSTCLOCK}' => ['state' => 0, 'value' => 0],
			'Item 52 trendcount eq 3' => ['state' => 0, 'value' => 1],
			'Item 52 trendmin eq 2' => ['state' => 0, 'value' => 1],
			'Item 52 trendmax eq 8' => ['state' => 0, 'value' => 1],
			'Item 52 trendsum eq 14' => ['state' => 0, 'value' => 1],
			'Item 52 trendavg eq 4.6' => ['state' => 0, 'value' => 1],
			'Item 53 trendstl eq 0' => ['state' => 0, 'value' => 1],
			'Item 54 baselinedev eq 0' => ['state' => 0, 'value' => 0],
			'Item 55 baselinewma eq 0' => ['state' => 0, 'value' => 0]
		];

		$failures = [];
		foreach ($response['result'] as $trigger) {
			$description = $trigger['description'];

			if (!array_key_exists($description, $triggers_expected)) {
				$failures[] = "[3] Unexpected trigger: $description";
				continue;
			}
			$expected = $triggers_expected[$description];

			if ($expected['state'] != $trigger['state']) {
				$failures[] = "[3] State mismatch for trigger: $description: ".json_encode($trigger);
			}
			if ($expected['value'] != $trigger['value']) {
				$failures[] = "[3] Value mismatch for trigger: $description";
			}
		}
		return $failures === [] ? true : implode("\n", $failures);
	}

	/**
	 */
	public function testFunctions_Step1() {
		$this->processStep1();
	}

	/**
	 * @depends testFunctions_Step1
	 */
	public function testFunctions_Step2() {
		$this->processStep2();
	}

	/**
	 * @depends testFunctions_Step2
	 */
	public function testFunctions_Step3() {
		$this->processStep3();
	}

	/**
	 * @depends testFunctions_Step3
	 */
	public function testFunctions_Step4_clearData() {
		self::clearData();
	}

	public static function clearData(): void {
		CDataHelper::call('host.delete', [self::$hostid]);
		CDataHelper::call('template.delete', [self::$templateid]);
	}
}

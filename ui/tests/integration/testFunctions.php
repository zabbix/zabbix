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

	private function importData() {
		$response = $this->call('templategroup.get', [
			'output' => ['groupid'],
			'filter' => ['name' => ['Templates']]
		]);

		if (!empty($response['result'])) {
			$groupid = $response['result'][0]['groupid'];
		}
		else {
			$response = $this->call('templategroup.create', ['name' => 'Templates']);
			$this->assertArrayHasKey('groupids', $response['result']);
			$groupid = $response['result']['groupids'][0];
		}

		$response = $this->call('template.create', [
			'host' => 'Integration function test',
			'name' => 'Integration function test',
			'groups' => [['groupid' => $groupid]],
			'macros' => [
				['macro' => '{$FIRSTCLOCK}', 'value' => '1762430060'],
				['macro' => '{$LASTCLOCK}', 'value' => '1762430300']
			]
		]);
		$this->assertArrayHasKey('templateids', $response['result']);
		$templateid = $response['result']['templateids'][0];

		$this->call('item.create', [
			['hostid' => $templateid, 'name' => 'Item 01', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[01]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 02', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[02]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 03', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[03]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 04', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[04]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 05', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[05]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 06', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[06]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 07', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[07]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 08', 'type' => ITEM_TYPE_SCRIPT, 'key_' => 'item[08]', 'value_type' => ITEM_VALUE_TYPE_FLOAT, 'delay' => '5s', 'params' => 'return 1'],
			['hostid' => $templateid, 'name' => 'Item 09', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[09]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 10', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[10]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 11', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[11]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 12', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[12]', 'value_type' => ITEM_VALUE_TYPE_LOG],
			['hostid' => $templateid, 'name' => 'Item 13', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[13]', 'value_type' => ITEM_VALUE_TYPE_LOG],
			['hostid' => $templateid, 'name' => 'Item 14', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[14]', 'value_type' => ITEM_VALUE_TYPE_LOG],
			['hostid' => $templateid, 'name' => 'Item 15', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[15]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 16', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[16]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 17', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[17]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 18', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[18]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 19', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[19]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 20', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[20]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 21', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[21]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 22', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[22]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 23', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[23]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 24', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[24]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 25', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[25]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 26', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[26]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 27', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[27]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 28', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[28]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 29', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[29]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 30', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[30]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 31', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[31]', 'value_type' => ITEM_VALUE_TYPE_LOG],
			['hostid' => $templateid, 'name' => 'Item 32', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[32]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 51', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[51]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 52', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[52]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 53', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[53]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 54', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[54]', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['hostid' => $templateid, 'name' => 'Item 55', 'type' => ITEM_TYPE_TRAPPER, 'key_' => 'item[55]', 'value_type' => ITEM_VALUE_TYPE_FLOAT]
		]);

		$this->call('trigger.create', [
			['expression' => 'min(/Integration function test/item[01], #5) > 10', 'description' => 'Item 01 min(#5) gt 10'],
			['expression' => 'min(/Integration function test/item[01], 5m) > 10', 'description' => 'Item 01 min(5m) gt 10'],
			['expression' => 'max(/Integration function test/item[02], #5) > 10', 'description' => 'Item 02 max(#5) gt 10'],
			['expression' => 'max(/Integration function test/item[02], 5m) > 10', 'description' => 'Item 02 max(5m) gt 10'],
			['expression' => 'avg(/Integration function test/item[03], #5) > 10', 'description' => 'Item 03 avg(#5) gt 10'],
			['expression' => 'avg(/Integration function test/item[03], 5m) > 10', 'description' => 'Item 03 avg(5m) gt 10'],
			['expression' => 'sum(/Integration function test/item[04], #5) > 10', 'description' => 'Item 04 sum(#5) gt 10'],
			['expression' => 'sum(/Integration function test/item[04], 5m) > 10', 'description' => 'Item 04 sum(5m) gt 10'],
			['expression' => 'percentile(/Integration function test/item[05], #5, 90) > 10', 'description' => 'Item 05 percentile(#5, 90) gt 10'],
			['expression' => 'percentile(/Integration function test/item[05], 5m, 90) > 10', 'description' => 'Item 05 percentile(5m, 90) gt 10'],
			['expression' => 'count(/Integration function test/item[06], #5, "eq", 10) = 2', 'description' => 'Item 06 count(#5, "eq", 10) eq 2'],
			['expression' => 'count(/Integration function test/item[06], 5m, "eq", 10) = 2', 'description' => 'Item 06 count(5m, "eq", 10) eq 2'],
			['expression' => 'countunique(/Integration function test/item[07], #5) = 2', 'description' => 'Item 07 countunique(#5, ) eq 2'],
			['expression' => 'countunique(/Integration function test/item[07], 5m) = 2', 'description' => 'Item 07 countunique(5m, ) eq 2'],
			['expression' => 'nodata(/Integration function test/item[08], 30s) = 1', 'description' => 'Item 08 nodata(30s) eq 1'],
			['expression' => 'change(/Integration function test/item[09]) = 1', 'description' => 'Item 09 change() eq 1'],
			['expression' => 'find(/Integration function test/item[10], #5, "eq", 10) = 1', 'description' => 'Item 10 find(#5, "eq", 10) eq 1'],
			['expression' => 'fuzzytime(/Integration function test/item[11],1) = 0', 'description' => 'Item 11 fuzzytime(1) eq 0'],
			['expression' => 'logeventid(/Integration function test/item[12],, 10) = 1', 'description' => 'Item 12 logeventid(10) eq 1'],
			['expression' => 'logseverity(/Integration function test/item[13]) = 1', 'description' => 'Item 13 logseverity() eq 1'],
			['expression' => 'logsource(/Integration function test/item[14],,"xyz") = 1', 'description' => 'Item 14 logsource(xyz) eq 1'],
			['expression' => 'forecast(/Integration function test/item[15], #5, 60) > 1000', 'description' => 'Item 15 forecast(#5) gt 1000'],
			['expression' => 'forecast(/Integration function test/item[15], 5m, 60) > 1000', 'description' => 'Item 15 forecast(5m) gt 1000'],
			['expression' => 'timeleft(/Integration function test/item[16], #5, 10) > 0 and timeleft(/Integration function test/item[16], #5, 10) < 1000000000', 'description' => 'Item 16 timeleft(#5) gt 0'],
			['expression' => 'timeleft(/Integration function test/item[16], 5m, 10) > 0 and timeleft(/Integration function test/item[16], 5m, 10) < 1000000000', 'description' => 'Item 16 timeleft(5m) gt 0'],
			['expression' => 'first(/Integration function test/item[17], 30) = 1', 'description' => 'Item 17 first(30s) eq 1'],
			['expression' => 'kurtosis(/Integration function test/item[18], #5) = 1', 'description' => 'Item 18 kurtosis(#5) eq  1'],
			['expression' => 'kurtosis(/Integration function test/item[18], 5m) = 1', 'description' => 'Item 18 kurtosis(5m) eq  1'],
			['expression' => 'mad(/Integration function test/item[19], #5) = 1', 'description' => 'Item 19 mad(#5) eq 1'],
			['expression' => 'mad(/Integration function test/item[19], 5m) = 1', 'description' => 'Item 19 mad(5m) eq 1'],
			['expression' => 'skewness(/Integration function test/item[20], #5) = 0', 'description' => 'Item 20 skewness(#5) eq 0'],
			['expression' => 'skewness(/Integration function test/item[20], 5m) = 0', 'description' => 'Item 20 skewness(5m) eq 0'],
			['expression' => 'stddevpop(/Integration function test/item[21], #5) = 1', 'description' => 'Item 21 stddevpop(#5) eq 1'],
			['expression' => 'stddevpop(/Integration function test/item[21], 5m) = 1', 'description' => 'Item 21 stddevpop(5m) eq 1'],
			['expression' => 'stddevsamp(/Integration function test/item[22], #5) < 1', 'description' => 'Item 22 stddevsamp(#5) lt 1'],
			['expression' => 'stddevsamp(/Integration function test/item[22], 5m) < 1', 'description' => 'Item 22 stddevsamp(5m) lt 1'],
			['expression' => 'sumofsquares(/Integration function test/item[23], #5) = 1', 'description' => 'Item 23 sumofsquares(#5) eq 1'],
			['expression' => 'sumofsquares(/Integration function test/item[23], 5m) = 1', 'description' => 'Item 23 sumofsquares(5m) eq 1'],
			['expression' => 'varpop(/Integration function test/item[24], #5) = 1', 'description' => 'Item 24 varpop(#5) eq 1'],
			['expression' => 'varpop(/Integration function test/item[24], 5m) = 1', 'description' => 'Item 24 varpop(5m) eq 1'],
			['expression' => 'varsamp(/Integration function test/item[25], #5) < 1', 'description' => 'Item 25 varsamp(#5) lt 1'],
			['expression' => 'varsamp(/Integration function test/item[25], 5m) < 1', 'description' => 'Item 25 varsamp(5m) lt 1'],
			['expression' => 'monoinc(/Integration function test/item[26], #5) = 0', 'description' => 'Item 26 monoinc(#5) eq 0'],
			['expression' => 'monoinc(/Integration function test/item[26], 5m) = 0', 'description' => 'Item 26 monoinc(5m) eq 0'],
			['expression' => 'monodec(/Integration function test/item[27], #5) = 0', 'description' => 'Item 27 monodec(#5) eq 0'],
			['expression' => 'monodec(/Integration function test/item[27], 5m) = 0', 'description' => 'Item 27 monodec(5m) eq 0'],
			['expression' => 'rate(/Integration function test/item[28], 5m) = 1', 'description' => 'Item 28 rate(5m) eq 1'],
			['expression' => 'changecount(/Integration function test/item[29], #5) = 1', 'description' => 'Item 29 changecount(#5) eq 1'],
			['expression' => 'changecount(/Integration function test/item[29], 5m) = 1', 'description' => 'Item 29 changecount(5m) eq 1'],
			['expression' => 'lastclock(/Integration function test/item[30]) = {$LASTCLOCK}', 'description' => 'Item 30 lastclock() eq {$LASTCLOCK}'],
			['expression' => 'logtimestamp(/Integration function test/item[31]) = 0', 'description' => 'Item 31 logtimestamp() eq 0'],
			['expression' => 'firstclock(/Integration function test/item[32], 5m) = {$FIRSTCLOCK}', 'description' => 'Item 32 firstclock(5m) eq {$FIRSTCLOCK}'],
			['expression' => 'truncate(trendavg(/Integration function test/item[52], 1d:now/d),1) = 4.6 and last(/Integration function test/item[51]) > 0', 'description' => 'Item 52 trendavg eq 4.6'],
			['expression' => 'trendcount(/Integration function test/item[52], 1d:now/d) = 3 and last(/Integration function test/item[51]) > 0', 'description' => 'Item 52 trendcount eq 3'],
			['expression' => 'trendmax(/Integration function test/item[52], 1d:now/d) = 8 and last(/Integration function test/item[51]) > 0', 'description' => 'Item 52 trendmax eq 8'],
			['expression' => 'trendmin(/Integration function test/item[52], 1d:now/d) = 2 and last(/Integration function test/item[51]) > 0', 'description' => 'Item 52 trendmin eq 2'],
			['expression' => 'trendsum(/Integration function test/item[52], 1d:now/d) = 14 and last(/Integration function test/item[51]) > 0', 'description' => 'Item 52 trendsum eq 14'],
			['expression' => 'trendstl(/Integration function test/item[53], 1d:now/d, 1h, 2h) = 0 and last(/Integration function test/item[51]) > 0', 'description' => 'Item 53 trendstl eq 0'],
			['expression' => 'baselinedev(/Integration function test/item[54], 1d:now/d, "d", 1) = 0 and last(/Integration function test/item[51]) > 0', 'description' => 'Item 54 baselinedev eq 0'],
			['expression' => 'baselinewma(/Integration function test/item[55], 1d:now/d, "d", 1) = 0 and last(/Integration function test/item[51]) > 0', 'description' => 'Item 55 baselinewma eq 0']
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		$this->importData();

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
		global $HISTORY_PROVIDERS;
		$has_history_provider = isset($HISTORY_PROVIDERS);

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
			'Item 54 baselinedev eq 0' => ['state' => $has_history_provider ? 1 : 0, 'value' => 0],
			'Item 55 baselinewma eq 0' => ['state' => $has_history_provider ? 1 : 0, 'value' => 0]
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
		global $HISTORY_PROVIDERS;
		$has_history_provider = isset($HISTORY_PROVIDERS);

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
			'Item 52 trendcount eq 3' => ['state' => $has_history_provider ? 1 : 0, 'value' => $has_history_provider ? 0 : 1],
			'Item 52 trendmin eq 2' => ['state' => $has_history_provider ? 1 : 0, 'value' => $has_history_provider ? 0 : 1],
			'Item 52 trendmax eq 8' => ['state' => $has_history_provider ? 1 : 0, 'value' => $has_history_provider ? 0 : 1],
			'Item 52 trendsum eq 14' => ['state' => $has_history_provider ? 1 : 0, 'value' => $has_history_provider ? 0 : 1],
			'Item 52 trendavg eq 4.6' => ['state' => $has_history_provider ? 1 : 0, 'value' => $has_history_provider ? 0 : 1],
			'Item 53 trendstl eq 0' => ['state' => $has_history_provider ? 1 : 0, 'value' => $has_history_provider ? 0 : 1],
			'Item 54 baselinedev eq 0' => ['state' => $has_history_provider ? 1 : 0, 'value' => 0],
			'Item 55 baselinewma eq 0' => ['state' => $has_history_provider ? 1 : 0, 'value' => 0]
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
		global $HISTORY_PROVIDERS;
		$has_history_provider = isset($HISTORY_PROVIDERS);

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
			'Item 52 trendcount eq 3' => ['state' => $has_history_provider ? 1 : 0, 'value' => $has_history_provider ? 0 : 1],
			'Item 52 trendmin eq 2' => ['state' => $has_history_provider ? 1 : 0, 'value' => $has_history_provider ? 0 : 1],
			'Item 52 trendmax eq 8' => ['state' => $has_history_provider ? 1 : 0, 'value' => $has_history_provider ? 0 : 1],
			'Item 52 trendsum eq 14' => ['state' => $has_history_provider ? 1 : 0, 'value' => $has_history_provider ? 0 : 1],
			'Item 52 trendavg eq 4.6' => ['state' => $has_history_provider ? 1 : 0, 'value' => $has_history_provider ? 0 : 1],
			'Item 53 trendstl eq 0' => ['state' => $has_history_provider ? 1 : 0, 'value' => $has_history_provider ? 0 : 1],
			'Item 54 baselinedev eq 0' => ['state' => $has_history_provider ? 1 : 0, 'value' => 0],
			'Item 55 baselinewma eq 0' => ['state' => $has_history_provider ? 1 : 0, 'value' => 0]
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

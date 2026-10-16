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

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';

/**
 * Test suite for:
 * - calculated item with historical data
 *
 * @required-components server
 * @suite-components-reuse true
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_calc
 * @onAfter clearData
 */
class testCalculatedExpression extends CIntegrationTest {

	private static $hostid;

	const HOST_NAME = 'test_calc';
	const TRAPPER_ITEM_KEY = 'test.calc.trapper';
	const CALCULATED_ITEM_KEY = 'test.calc.calculated';

	const TAG_NAME = 'env';
	const TAG_VALUE = 'prod';

	const DBL_MAX = '1.7976931348623157e308';
	const MINUS_DBL_MAX = '-1.7976931348623157e308';

	/**
	 * Component configuration provider.
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
	 * @inheritdoc
	 */
	public function prepareData() {

		// Create host.
		$response = $this->call('host.create', [
			'host' => self::HOST_NAME,
			'interfaces' => [
				[
					'type' => INTERFACE_TYPE_AGENT,
					'main' => INTERFACE_PRIMARY,
					'useip' => INTERFACE_USE_IP,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				]
			],
			'groups' => [
				[
					'groupid' => 4 // Zabbix servers
				]
			],
			'macros' => [
				[
					'macro' => '{$TAG_NAME}',
					'value' => self::TAG_NAME
				],
				[
					'macro' => '{$TAG_VALUE}',
					'value' => self::TAG_VALUE
				]
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];
	}

	private function createItem(array $params): string {
		$response = $this->call('item.create', $params);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		$itemid = $response['result']['itemids'][0];

		/*
		 * The trigger is needed to ensure that the value cache receives data immediately when history is added.
		 * This prevents issues caused by Elasticsearch not returning newly added history data immediately.
		 */
		$response = $this->call('trigger.create', [
			'description' => 'trigger '.$params['key_'],
			'expression' => 'last(/'.self::HOST_NAME.'/'.$params['key_'].')'
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertEquals(1, count($response['result']['triggerids']));

		return $itemid;
	}

	private function createTrap(string $keySuffix): string {
		$itemid = $this->createItem([
			'hostid'		=> self::$hostid,
			'name'			=> self::TRAPPER_ITEM_KEY.'.'.$keySuffix,
			'key_'			=> self::TRAPPER_ITEM_KEY.'.'.$keySuffix,
			'type'			=> ITEM_TYPE_TRAPPER,
			'value_type'	=> ITEM_VALUE_TYPE_FLOAT,
			'trapper_hosts' => '{$TRAPPER.ALLOWED_HOSTS}',
			'tags'			=> [
				['tag' => self::TAG_NAME, 'value' => self::TAG_VALUE]
			],
			'preprocessing' => [
				[
					'type'					=> ZBX_PREPROC_TRIM,
					'params'				=> ' ',
					'error_handler'			=> ZBX_PREPROC_FAIL_DEFAULT,
					'error_handler_params'	=> ''
				]
			]
		]);

		return $itemid;
	}

	// create calculated item with given formula and return its itemid
	private function createCalculatedItemWithFormula(string $formula, string $keySuffix, string $delay = '1s',
			int $value_type = ITEM_VALUE_TYPE_FLOAT): string {
		$itemid = $this->createItem([
			'name'			=> self::CALCULATED_ITEM_KEY.'.'.$keySuffix,
			'key_'			=> self::CALCULATED_ITEM_KEY.'.'.$keySuffix,
			'type'			=> ITEM_TYPE_CALCULATED,
			'params'		=> $formula,
			'hostid'		=> self::$hostid,
			'delay'			=> $delay,
			'value_type'	=> $value_type
		]);

		return $itemid;
	}

	private function sendIncrementingSequence($n, $itemkey)
	{
		for ($i = 1; $i <= $n; $i++) {
			$this->sendSenderValue(self::HOST_NAME, $itemkey, $i, null, 1);
		}
		sleep(1);
	}

	private function sendSupportedExtremeValues($sendMax, $sendMin, $itemkey)
	{
		for ($i = 1; $i <= $sendMax; $i++) {
			$this->sendSenderValue(self::HOST_NAME, $itemkey, (float)self::DBL_MAX, null, 1);
		}

		for ($i = 1; $i <= $sendMin; $i++) {
			$this->sendSenderValue(self::HOST_NAME, $itemkey, (float)self::MINUS_DBL_MAX, null, 1);
		}
		sleep(1);
	}

	private function sendScaledSequenceToSecondItem($itemkey, $n)
	{
		for ($i = 1; $i <= $n; $i++) {
			$this->sendSenderValue(self::HOST_NAME, $itemkey, $i * 10, null, 1);
		}
		sleep(1);
	}

	private function getItemLastValue($itemid)
	{
		$response = $this->call('item.get', [
			'output'	=> ['lastvalue'],
			'itemids'	=> $itemid,
			'preservekeys'	=> true
		]);
		$this->assertArrayHasKey('result', $response);
		return $response['result'][$itemid]['lastvalue'];
	}

	/**
	 * Wait until the item has a last value (equal to the expected one, if given). A value reaches the history storage
	 * some time after it is calculated (history syncer, Elasticsearch index refresh), so a result seen in the server
	 * log may not be available through the API yet.
	 */
	private function waitForItemLastValue(string $itemid, ?string $expected_value = null): void {
		$this->callUntilDataIsPresent('item.get', [
			'output' => ['lastclock', 'lastvalue'],
			'itemids' => $itemid
		], 60, 1, static fn(array $response) => $response['result'][0]['lastclock'] !== '0'
			&& ($expected_value === null || $response['result'][0]['lastvalue'] === $expected_value)
		);
	}

	private function historyGet($itemid)
	{
		$params = [
				'itemids'   => $itemid,
				'history'   => ITEM_VALUE_TYPE_FLOAT,
				'sortorder' => ZBX_SORT_UP
		];

		$data = $this->call('history.get', $params);

		return $data;
	}

	private function extractHistoryValues(array $history): array
	{
		$this->assertArrayHasKey('result', $history, 'History response has no result key');

		$values = array_map(
			static fn(array $row) => $row['value'],
			$history['result']
		);

		usort(
			$values,
			static fn($a, $b) => (float)$a <=> (float)$b
		);

		return $values;
	}

	public function testCalculatedExpression_AvgOfLast5()
	{
		$trapId = $this->createTrap('avg5');

		$formula = 'avg(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'.avg5,#5)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'avg5');

		$this->reloadConfigurationCache(self::COMPONENT_SERVER, 1);
		$this->sendIncrementingSequence(5, self::TRAPPER_ITEM_KEY.'.avg5'); // 1..5 -> avg = 3
		$history = $this->historyGet($trapId);

		$values = $this->extractHistoryValues($history);

		$this->assertSame(
			['1', '2', '3', '4', '5'],
			$values
		);

		$this->assertEquals('3', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_AvgOfLast5MaxMinValue()
	{
		$trapId = $this->createTrap('avg5MaxValue');

		$formula = 'avg(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'.avg5MaxValue,#5)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'avg5MaxValue');

		$this->reloadConfigurationCache(self::COMPONENT_SERVER, 1);

		$this->sendSupportedExtremeValues(5, 0, self::TRAPPER_ITEM_KEY.'.avg5MaxValue');
		$this->checkItemState($itemid, ITEM_STATE_NORMAL);
		$history = $this->historyGet($trapId);
		$values = $this->extractHistoryValues($history);

		$this->assertSame(
			[
				(float)self::DBL_MAX,
				(float)self::DBL_MAX,
				(float)self::DBL_MAX,
				(float)self::DBL_MAX,
				(float)self::DBL_MAX
			],
			array_map('floatval', $values)
		);

		$this->assertEquals((float)self::DBL_MAX, $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_MaxOfLast4()
	{
		$trapId = $this->createTrap('max4');

		$formula = 'max(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'.max4,#4)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'max4');

		$this->reloadConfigurationCache(self::COMPONENT_SERVER, 1);
		$this->sendIncrementingSequence(4, self::TRAPPER_ITEM_KEY.'.max4'); // last 4 are 1,2,3,4 -> max = 4

		$history = $this->historyGet($trapId);
		$values = $this->extractHistoryValues($history);

		$this->assertSame(
			['1', '2', '3', '4'],
			$values
		);

		$this->assertEquals('4', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_MaxOfLast4MaxMinValue()
	{
		$trapId = $this->createTrap('max4MaxValue');

		$formula = 'max(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'.max4MaxValue,#4)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'max4MaxValue');

		$this->reloadConfigurationCache(self::COMPONENT_SERVER, 1);

		// supported - 2 max and 2 min
		$this->sendSupportedExtremeValues(2, 2, self::TRAPPER_ITEM_KEY.'.max4MaxValue');
		$this->checkItemState($itemid, ITEM_STATE_NORMAL);

		$history = $this->historyGet($trapId);
		$values = $this->extractHistoryValues($history);

		$this->assertSame(
			[
				(float)self::MINUS_DBL_MAX,
				(float)self::MINUS_DBL_MAX,
				(float)self::DBL_MAX,
				(float)self::DBL_MAX
			],
			array_map('floatval', $values)
		);

		$this->assertEquals((float)self::DBL_MAX, $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_MinOfLast3()
	{
		$trapId = $this->createTrap('min3');

		$formula = 'min(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'.min3,#3)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'min3');

		$this->reloadConfigurationCache(self::COMPONENT_SERVER, 1);
		$this->sendIncrementingSequence(5, self::TRAPPER_ITEM_KEY.'.min3'); // last 3 are 3,4,5 -> min = 3

		$history = $this->historyGet($trapId);
		$values = $this->extractHistoryValues($history);

		$this->assertSame(
			['1', '2', '3', '4', '5'],
			$values
		);

		$this->assertEquals('3', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_MinOfLast5MaxMinValue()
	{
		$trapId = $this->createTrap('min3MaxValue');

		$formula = 'min(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'.min3MaxValue,#5)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'min3MaxValue');

		$this->reloadConfigurationCache(self::COMPONENT_SERVER, 1);

		$this->sendSupportedExtremeValues(3, 2, self::TRAPPER_ITEM_KEY.'.min3MaxValue'); // last 3 are max values
		$this->checkItemState($itemid, ITEM_STATE_NORMAL);

		$history = $this->historyGet($trapId);
		$values = $this->extractHistoryValues($history);

		$this->assertSame(
			[
				(float)self::MINUS_DBL_MAX,
				(float)self::MINUS_DBL_MAX,
				(float)self::DBL_MAX,
				(float)self::DBL_MAX,
				(float)self::DBL_MAX
			],
			array_map('floatval', $values)
		);

		$this->assertEquals((float)self::MINUS_DBL_MAX, $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_LastValue()
	{
		$trapId = $this->createTrap('last1');

		$formula = 'last(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'.last1,#1)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'last1');

		$this->reloadConfigurationCache(self::COMPONENT_SERVER, 1);
		$this->sendIncrementingSequence(3, self::TRAPPER_ITEM_KEY.'.last1'); // last = 3

		$history = $this->historyGet($trapId);
		$values = $this->extractHistoryValues($history);

		$this->assertSame(
			['1', '2', '3'],
			$values
		);

		$this->assertEquals('3', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_LastValueMaxValue()
	{
		$trapId = $this->createTrap('last1MaxValue');

		$formula = 'last(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'.last1MaxValue,#1)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'last1MaxValue');

		$this->reloadConfigurationCache(self::COMPONENT_SERVER, 1);

		$this->sendSupportedExtremeValues(3, 0, self::TRAPPER_ITEM_KEY.'.last1MaxValue');
		$this->checkItemState($itemid, ITEM_STATE_NORMAL);

		$history = $this->historyGet($trapId);
		$values = $this->extractHistoryValues($history);

		$this->assertSame(
			[
				(float)self::DBL_MAX,
				(float)self::DBL_MAX,
				(float)self::DBL_MAX
			],
			array_map('floatval', $values)
		);

		$this->assertEquals((float)self::DBL_MAX, $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_TimeleftForecastOverflow()
	{
		/* test timeleft */

		$trapId = $this->createTrap('timeleft_overflow');

		$formula = 'timeleft(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'.timeleft_overflow,#3, -1)';
		$timeleft_itemid = $this->createCalculatedItemWithFormula($formula, 'timeleft_overflow');

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$his1 = 1.0;
		$his2 = 2.0;
		$his3 = 3.0;

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY.'.timeleft_overflow', $his1);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY.'.timeleft_overflow', $his2);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY.'.timeleft_overflow', $his3);

		$history = $this->historyGet($trapId);
		$values = $this->extractHistoryValues($history);

		$this->assertSame(
			[
				$his1,
				$his2,
				$his3
			],
			array_map('floatval', $values)
		);

		/* timeleft of course cannot reach -1, so test that it is cropped to DBL_MAX */
		$this->assertEquals((float)self::DBL_MAX, $this->getItemLastValue($timeleft_itemid));

		/* test forecast */

		$his4 = 1.79e+10;
		$his5 = 1.79e+300;

		$trapId = $this->createTrap('forecast_overflow');
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY.'.forecast_overflow', $his4);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY.'.forecast_overflow', $his5);

		$history = $this->historyGet($trapId);
		$values = $this->extractHistoryValues($history);

		$this->assertSame(
			[
				$his4,
				$his5
			],
			array_map('floatval', $values)
		);
		$formula = 'forecast(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'.forecast_overflow,#2,3000w,"exponential")';
		$forecast_itemid = $this->createCalculatedItemWithFormula($formula, 'forecast_overflow', '10s');

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of evaluate_FORECAST():SUCCEED");

		$res = null;
		for ($i = 0; $i < 100; $i++)
		{
			$res = $this->historyGet($forecast_itemid);

			if (!empty($res['result']))
			{
				break;
			}

			usleep(100000);
		}

		/* expected exponential value will be so large it is cropped to DBL_MAX */
		$this->assertEquals((float)self::DBL_MAX, $res['result'][0]['value']);
	}

	public function testCalculatedExpression_ArithmeticAndScaling()
	{
		$trapId = $this->createTrap('avg5_mul2');

		$formula = '(avg(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'.avg5_mul2,#5)*2)+1';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'avg5_mul2');

		$this->reloadConfigurationCache(self::COMPONENT_SERVER, 1);
		$this->sendIncrementingSequence(5, self::TRAPPER_ITEM_KEY.'.avg5_mul2'); // last5:1,2,3,4,5 avg=3

		$history = $this->historyGet($trapId);
		$values = $this->extractHistoryValues($history);

		$this->assertSame(
			['1', '2', '3', '4', '5'],
			$values
		);

		$this->assertEquals('7', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_CombinedFunctions()
	{
		$trapId = $this->createTrap('sum_minus_avg5');

		// formula: sum(last5) - avg(last5)
		$formula = 'sum(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'.sum_minus_avg5,#5)-'.
			'avg(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'.sum_minus_avg5,#5)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'sum_minus_avg5');

		$this->reloadConfigurationCache(self::COMPONENT_SERVER, 1);
		// sum(1,2,3,4,5)-avg(1,2,3,4,5) = 15-3=12
		$this->sendIncrementingSequence(5, self::TRAPPER_ITEM_KEY.'.sum_minus_avg5');

		$history = $this->historyGet($trapId);
		$values = $this->extractHistoryValues($history);

		$this->assertSame(
			['1', '2', '3', '4', '5'],
			$values
		);

		$this->assertEquals('12', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_MultiItemAverage()
	{
		$trapIdFirst = $this->createTrap('multi_avg');
		$trapIdSecond = $this->createTrap('multi_avg2');

		// formula averaging two items' last values
		$formula = 'avg(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'.multi_avg,#1)+'.
			'avg(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'.multi_avg2,#1)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'multi_avg');

		$this->reloadConfigurationCache(self::COMPONENT_SERVER, 1);
		$this->sendIncrementingSequence(3, self::TRAPPER_ITEM_KEY.'.multi_avg');
		$this->sendScaledSequenceToSecondItem(self::TRAPPER_ITEM_KEY.'.multi_avg2', 3);

		$history = $this->historyGet($trapIdFirst);
		$values = $this->extractHistoryValues($history);

		$this->assertSame(
			['1', '2', '3'],
			$values
		);

		$history = $this->historyGet($trapIdSecond);
		$values = $this->extractHistoryValues($history);

		$this->assertSame(
			['10', '20', '30'],
			$values
		);

		// last values: 3 and 30 -> sum = 33
		$this->assertEquals('33', $this->getItemLastValue($itemid));
	}

	/**
	 * @depends testCalculatedExpression_AvgOfLast5
	 * @depends testCalculatedExpression_AvgOfLast5MaxMinValue
	 * @depends testCalculatedExpression_MaxOfLast4
	 * @depends testCalculatedExpression_MaxOfLast4MaxMinValue
	 * @depends testCalculatedExpression_MinOfLast3
	 * @depends testCalculatedExpression_MinOfLast5MaxMinValue
	 * @depends testCalculatedExpression_LastValue
	 * @depends testCalculatedExpression_LastValueMaxValue
	 * @depends testCalculatedExpression_ArithmeticAndScaling
	 * @depends testCalculatedExpression_CombinedFunctions
	 * @depends testCalculatedExpression_MultiItemAverage
	 */
	public function testCalculatedExpression_ItemCount_TagFilter() {
		$formula = 'item_count(/'.self::HOST_NAME.'/*?[tag="{$TAG_NAME}:{$TAG_VALUE}"])';

		$calcItemId = $this->createCalculatedItemWithFormula($formula, 'itemcount_tag', '1s', ITEM_VALUE_TYPE_UINT64);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of expression_eval_many():SUCCEED" .
			" value:14 flags:uint64", true, 120);
		$this->waitForItemLastValue($calcItemId);
		$this->assertEquals('14', $this->getItemLastValue($calcItemId));
	}

	public function testCalculatedExpression_HistogramQuantile()
	{
		$itemids = [];
		// create a histogram bucket item (simulate with a trapper item for test)
		foreach ([0.1, 0.5, 1, 2, 'Inf'] as $le) {
			$trapperItemId = $this->createTrap('bucket['.$le.']');
			$itemids = array_merge($itemids, [$trapperItemId]);
		}

		$formula = 'histogram_quantile(0.25,' .
			'0.1,last(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . '.bucket[0.1]),' .
			'0.5,last(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . '.bucket[0.5]),' .
			'1.0,last(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . '.bucket[1]),' .
			'2.0,last(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . '.bucket[2]),' .
			'"+Inf",last(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . '.bucket[Inf])' .
		')';

		// create a calculated item using bucket_percentile
		$calcItemId = $this->createCalculatedItemWithFormula($formula, 'histogram_quantile');

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "In zbx_substitute_item_key_params():" .
			" data:test.calc.calculated.histogram_quantile", true, 120);

		$this->reloadConfigurationCacheAndWaitForLogLine();

		// send values to the bucket item (simulate histogram bucket values)
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY . '.bucket[0.1]', 10, null, 1);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY . '.bucket[0.5]', 25, null, 1);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY . '.bucket[1]', 30, null, 1);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY . '.bucket[2]', 32, null, 1);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY . '.bucket[Inf]', 35, null, 1);

		$history = $this->callUntilDataIsPresent('history.get', [
			'itemids' => $itemids,
			'history' => ITEM_VALUE_TYPE_FLOAT
		], 60, 1, static fn(array $response) => count($response['result']) === count($itemids));
		$values = $this->extractHistoryValues($history);

		$this->assertSame(
			['10', '25', '30', '32', '35'],
			$values
		);

		for ($i = 0; $i < 120; $i++) {
			$item = $this->call('item.get', [
				'output' => ['state'],
				'itemids' => [$calcItemId]
			])['result'][0];

			if ((int)$item['state'] === 0) {
				break;
			}

			usleep(500000);
		}

		// wait for calculated item to process
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "zbx_expression_eval_execute() expression:" .
			"'histogram_quantile(0.25,0.1,last(0),0.5,last(1),1.0,last(2),2.0,last(3),\"+Inf\",last(4))'",
			true, 120);
		$this->waitForItemLastValue($calcItemId);

		/* Histogram_quantile(0.25, ...) calculates the 25th percentile (quantile φ=0.25) from the histogram buckets. */
		/* Bucket boundaries and cumulative counts: */
		/*   0.1: 10 */
		/*   0.5: 25 */
		/*   1.0: 30 */
		/*   2.0: 32 */
		/*   +Inf: 35 */
		/* Total count = 35, so the quantile position is 0.25 * 35 = 8.75. */
		/* The 25th percentile falls within the first bucket (0.1), and Zabbix interpolates the value as 0.0875. */
		$this->assertEqualsWithDelta(0.0875, (float)$this->getItemLastValue($calcItemId), 0.0001);
	}

	public function testCalculatedExpression_CountForeach()
	{
		$itemids = [];
		// create several trapper items simulating disk usage for different filesystems
		foreach( ['fs1', 'fs2', 'fs3', 'fs4'] as $fs) {
			$itemid = $this->createTrap('disk.pused['.$fs.']');
			$itemids = array_merge($itemids, [$itemid]);
		}

		$formula = 'count(last_foreach(/*/test.calc.trapper.disk.pused[*]),"gt",95)';

		$calcItemId = $this->createCalculatedItemWithFormula($formula, 'count_disk_pused_gt_95', '1s',
			ITEM_VALUE_TYPE_UINT64);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "In zbx_substitute_item_key_params():" .
			" data:test.calc.calculated.count_disk_pused_gt_95", true, 120);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER, 1);

		/* Send values to the trapper items: fs1=90, fs2=96, fs3=97, fs4=80. */
		$senderValues = [
			['host' => self::HOST_NAME, 'key' => self::TRAPPER_ITEM_KEY . '.disk.pused[fs1]', 'value' => 90],
			['host' => self::HOST_NAME, 'key' => self::TRAPPER_ITEM_KEY . '.disk.pused[fs2]', 'value' => 96],
			['host' => self::HOST_NAME, 'key' => self::TRAPPER_ITEM_KEY . '.disk.pused[fs3]', 'value' => 97],
			['host' => self::HOST_NAME, 'key' => self::TRAPPER_ITEM_KEY . '.disk.pused[fs4]', 'value' => 80]
		];

		$this->sendSenderValues($senderValues);
		// wait for calculated item to process
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of expression_eval_many():SUCCEED" .
			" value:var vector[0:4] flags:vector", true, 120);

		$history = $this->callUntilDataIsPresent('history.get', [
			'itemids' => $itemids,
			'history' => ITEM_VALUE_TYPE_FLOAT
		], 60, 1, static fn(array $response) => count($response['result']) === count($itemids));
		$values = $this->extractHistoryValues($history);

		$this->assertSame(
			['80', '90', '96', '97'],
			$values
		);

		/* Values calculated before all trapper values arrived are in history too (an empty vector gives 0). */
		$this->waitForItemLastValue($calcItemId, '2');

		/* We have 4 items: [90, 96, 97, 80]. The formula counts how many have last value greater than 95. */
		/* So expected result is 2. */
		$this->assertEquals('2', $this->getItemLastValue($calcItemId));
	}

	public function testCalculatedExpression_TrendAvg()
	{
		global $HISTORY_PROVIDERS;

		// Skip this test if ClickHouse or Elasticsearch is used as a history provider.
		if (isset($HISTORY_PROVIDERS) && $HISTORY_PROVIDERS) {
			$this->markTestSkipped('Zabbix configured with external history provider.');
		}

		/* Trying to store Inf values into DB can result in failed transactions.   */
		/* (Jenkins checks for failed transactions and turns yellow in that case). */
		/* Note, that although history trends and new trends are DBL_MAX the       */
		/* resulting trendAvg is actually smaller than DBL_MAX due to rounding     */
		/* errors appearing as a result of scaling introduced to safeguard against */
		/* overflow of double into 'inf'.                                          */

		$trapId = $this->createTrap('trendAvg5MaxValue');

		$formula = 'trendavg(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_KEY.'.trendAvg5MaxValue,3h:now/h)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'trendAvg5MaxValue');

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		/* There seems to be no way to calculate historical trends. */
		$this->assertTrue(DBexecute(
			'INSERT INTO trends (itemid,clock,num,value_min,value_avg,value_max)
				VALUES ('.$trapId.','.(time() - (3600 * 2)).',1,'.self::DBL_MAX.','.self::DBL_MAX.','.self::DBL_MAX.')'
		));

		for ($i = 0; $i <= 3; $i++) {
			$response = $this->call('history.push', [
				'itemid' => $trapId,
				'value' => (float)self::DBL_MAX,
				'clock' => time() - 3600 - 70 - $i,
				'ns' => 255
			]);
		}

		$response = $this->callUntilDataIsPresent('history.get', [
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'limit' => 1,
			'itemids' => $trapId,
			'history' => 0
		], 60, 1);

		self::stopComponent(self::COMPONENT_SERVER);
		self::startComponent(self::COMPONENT_SERVER);

		$response = $this->callUntilDataIsPresent('history.get', [
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'limit' => 1,
			'itemids' => [$itemid],
			'history' => 0
		], 60, 1);

		$this->assertEqualsWithDelta((float)self::DBL_MAX, (float)$this->getItemLastValue($itemid), 1e294);

		self::stopComponent(self::COMPONENT_SERVER);
		/* All values calculated before the restart have clock not later than the current second. */
		$time_from = time() + 1;
		self::startComponent(self::COMPONENT_SERVER);

		$this->callUntilDataIsPresent('history.get', [
			'itemids' => $itemid,
			'history' => ITEM_VALUE_TYPE_FLOAT,
			'time_from' => $time_from
		], 60, 1);

		$this->assertEqualsWithDelta((float)self::DBL_MAX, (float)$this->getItemLastValue($itemid), 1e294);
	}

	public static function clearData(): void {
		CDataHelper::call('host.delete', [self::$hostid]);
	}

	protected function checkItemState($itemid, $state) {
		$wait_iterations = 4;
		$wait_iteration_delay = 1;

		for ($r = 0; $r < $wait_iterations; $r++) {
			$item = $this->call('item.get', [
				'output' => ['state', 'lastvalue'],
				'itemids' => [$itemid]
			])['result'][0];

			if ($item['state'] == $state) {
				break;
			}

			sleep($wait_iteration_delay);
		}

		$this->assertEquals($state, $item['state'], 'Unexpected item state: '. $state . ' for itemid: ' . $itemid);
	}
}

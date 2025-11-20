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
 * Test suite for:
 * - calculated item with historical data
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_calc
 * @onAfter clearData
 */
class testCalculatedExpression extends CIntegrationTest {

	private static $hostid;
	private static $itemIds = [];

	const HOST_NAME = 'test_calc';
	const TRAPPER_ITEM_KEY = 'test.calc.trapper';
	const TRAPPER_ITEM_KEY_2 = 'test.calc.trapper2';
	const CALCULATED_ITEM_KEY = 'test.calc.calculated';

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
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		// create trapper item 1
		$response = $this->call('item.create', [
			'hostid'		=> self::$hostid,
			'name'			=> self::TRAPPER_ITEM_KEY,
			'key_'			=> self::TRAPPER_ITEM_KEY,
			'type'			=> ITEM_TYPE_TRAPPER,
			'value_type'		=> ITEM_VALUE_TYPE_FLOAT,
			'tags'		=> [
				['tag' => 'env', 'value' => 'prod']
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		self::$itemIds = array_merge(self::$itemIds, $response['result']['itemids']);

		// create trapper item 2
		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => self::TRAPPER_ITEM_KEY_2,
			'key_' => self::TRAPPER_ITEM_KEY_2,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'tags'		=> [
				['tag' => 'env', 'value' => 'prod']
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		self::$itemIds = array_merge(self::$itemIds, $response['result']['itemids']);
	}

	// create calculated item with given formula and return its itemid
	private function createCalculatedItemWithFormula($formula, $keySuffix)
	{
		$response = $this->call('item.create', [
			'name'		=> self::CALCULATED_ITEM_KEY . '.' . $keySuffix,
			'key_'		=> self::CALCULATED_ITEM_KEY . '.' . $keySuffix,
			'type'		=> ITEM_TYPE_CALCULATED,
			'params'	=> $formula,
			'hostid'	=> self::$hostid,
			'delay'		=> '1s',
			'value_type' => ITEM_VALUE_TYPE_FLOAT
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		return $response['result']['itemids'][0];
	}

	private function sendIncrementingSequence($n, $itemkey)
	{
		for ($i = 1; $i <= $n; $i++) {
			$this->sendSenderValue(self::HOST_NAME, $itemkey, $i);
		}
	}

	private function sendExtremeValues($sendMax, $sendMin, $itemkey)
	{
		for ($i = 1; $i <= $sendMax; $i++) {
			$this->sendSenderValue(self::HOST_NAME, $itemkey, 1.7976931348623157e308);
		}

		for ($i = 1; $i <= $sendMin; $i++) {
			$this->sendSenderValue(self::HOST_NAME, $itemkey, -1.7976931348623157e308);
		}
	}

	private function sendScaledSequenceToSecondItem($n)
	{
		for ($i = 1; $i <= $n; $i++) {
			$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY_2, $i * 10);
		}
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

	public function testCalculatedExpression_AvgOfLast5()
	{
		$formula = 'avg(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . ',#5)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'avg5');
		self::$itemIds = array_merge(self::$itemIds, [$itemid]);
		$this->sendIncrementingSequence(5, self::TRAPPER_ITEM_KEY); // 1..5 -> avg = 3
		$this->assertEquals('3', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_AvgOfLast5MaxMinValue()
	{
		$formula = 'avg(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . ',#5)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'avg5MaxValue');
		self::$itemIds = array_merge(self::$itemIds, [$itemid]);
		$this->sendExtremeValues(5, 0, self::TRAPPER_ITEM_KEY); // last 5 are max values
		$this->assertEquals((float)'1.7976931348623157e308', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_MaxOfLast4()
	{
		$formula = 'max(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . ',#4)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'max4');
		self::$itemIds = array_merge(self::$itemIds, [$itemid]);
		$this->sendIncrementingSequence(4, self::TRAPPER_ITEM_KEY); // last 4 are 1,2,3,4 -> max = 4
		$this->assertEquals('4', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_MaxOfLast4MaxMinValue()
	{
		$formula = 'max(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . ',#4)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'max4MaxValue');
		self::$itemIds = array_merge(self::$itemIds, [$itemid]);
		$this->sendExtremeValues(2, 2, self::TRAPPER_ITEM_KEY); // 2 max and 2 min
		$this->assertEquals((float)'1.7976931348623157e308', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_MinOfLast3()
	{
		$formula = 'min(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . ',#3)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'min3');
		self::$itemIds = array_merge(self::$itemIds, [$itemid]);
		$this->sendIncrementingSequence(5, self::TRAPPER_ITEM_KEY); // last 3 are 3,4,5 -> min = 3
		$this->assertEquals('3', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_MinOfLast5MaxMinValue()
	{
		$formula = 'min(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . ',#5)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'min3MaxValue');
		self::$itemIds = array_merge(self::$itemIds, [$itemid]);
		$this->sendExtremeValues(3, 2, self::TRAPPER_ITEM_KEY); // last 3 are max values
		$this->assertEquals((float)'-1.7976931348623157e308', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_LastValue()
	{
		$formula = 'last(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . ',#1)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'last1');
		self::$itemIds = array_merge(self::$itemIds, [$itemid]);
		$this->sendIncrementingSequence(3, self::TRAPPER_ITEM_KEY); // last = 3
		$this->assertEquals('3', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_LastValueMaxValue()
	{
		$formula = 'last(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . ',#1)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'last1MaxValue');
		self::$itemIds = array_merge(self::$itemIds, [$itemid]);
		$this->sendExtremeValues(3, 0, self::TRAPPER_ITEM_KEY);
		$this->assertEquals((float)'1.7976931348623157e308', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_ArithmeticAndScaling()
	{
		$formula = '(avg(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . ',#5) * 2) + 1';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'avg5_mul2');
		self::$itemIds = array_merge(self::$itemIds, [$itemid]);
		$this->sendIncrementingSequence(5, self::TRAPPER_ITEM_KEY); // last5:1,2,3,4,5 avg=3
		$this->assertEquals('7', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_CombinedFunctions()
	{
		// formula: sum(last5) - avg(last5)
		$formula = 'sum(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . ',#5) - avg(/'
			. self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . ',#5)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'sum_minus_avg5');
		self::$itemIds = array_merge(self::$itemIds, [$itemid]);
		$this->sendIncrementingSequence(5, self::TRAPPER_ITEM_KEY); // sum(1,2,3,4,5)-avg(1,2,3,4,5) = 15-3=12
		$this->assertEquals('12', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_MultiItemAverage()
	{
		// formula averaging two items' last values
		$formula = 'avg(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . ',#1) + avg(/' . self::HOST_NAME
			. '/' . self::TRAPPER_ITEM_KEY_2 . ',#1)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'multi_avg');
		self::$itemIds = array_merge(self::$itemIds, [$itemid]);
		$this->sendIncrementingSequence(3, self::TRAPPER_ITEM_KEY);
		$this->sendScaledSequenceToSecondItem(3);
		// last values: 3 and 30 -> sum = 33
		$this->assertEquals('33', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_ItemCount_TagFilter(){

		$formula = 'item_count(/test_calc/*?[tag="env:prod"])';

		$response = $this->call('item.create', [
			'name'		=> self::CALCULATED_ITEM_KEY . '.itemcount_tag',
			'key_'		=> self::CALCULATED_ITEM_KEY . '.itemcount_tag',
			'type'		=> ITEM_TYPE_CALCULATED,
			'params'	=> $formula,
			'hostid'	=> self::$hostid,
			'delay'		=> '1s',
			'value_type'	=> ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$calcItemId = $response['result']['itemids'][0];
		self::$itemIds = array_merge(self::$itemIds, [$calcItemId]);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of expression_eval_many():SUCCEED" .
			" value:2 flags:uint64", true, 120);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of expression_eval_many():SUCCEED" .
			" value:2 flags:uint64", true, 120);
		$this->assertEquals('2', $this->getItemLastValue($calcItemId));
	}

	public function testCalculatedExpression_HistogramQuantile() {

		// create a histogram bucket item (simulate with a trapper item for test)
		foreach ([0.1, 0.5, 1, 2, 'Inf'] as $le) {
			$response = $this->call('item.create', [
				'hostid'	=> self::$hostid,
				'name'		=> "bucket[$le]",
				'key_'		=> self::TRAPPER_ITEM_KEY . ".bucket[$le]",
				'type'		=> ITEM_TYPE_TRAPPER,
				'value_type'	=> ITEM_VALUE_TYPE_UINT64
			]);
			$itemid = $response['result']['itemids'][0];
			$this->assertEquals(1, count($response['result']['itemids']));
			self::$itemIds = array_merge(self::$itemIds, [$itemid]);
		}

		$formula = 'histogram_quantile(0.25,' .
			'0.1,last(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . '.bucket[0.1]),' .
			'0.5,last(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . '.bucket[0.5]),' .
			'1.0,last(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . '.bucket[1]),' .
			'2.0,last(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . '.bucket[2]),' .
			'"+Inf",last(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . '.bucket[Inf])' .
		')';

		// create a calculated item using bucket_percentile
		$response = $this->call('item.create', [
			'name'		=> self::CALCULATED_ITEM_KEY . '.histogram_quantile',
			'key_'		=> self::CALCULATED_ITEM_KEY . '.histogram_quantile',
			'type'		=> ITEM_TYPE_CALCULATED,
			'params'	=> $formula,
			'hostid'	=> self::$hostid,
			'delay'		=> '1s',
			'value_type'	=> ITEM_VALUE_TYPE_FLOAT
		]);
		$calcItemId = $response['result']['itemids'][0];
		$this->assertEquals(1, count($response['result']['itemids']));
		self::$itemIds = array_merge(self::$itemIds, [$calcItemId]);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "In zbx_substitute_item_key_params():" .
			" data:test.calc.calculated.histogram_quantile", true, 120);

		// send values to the bucket item (simulate histogram bucket values)
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY . '.bucket[0.1]', 10);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY . '.bucket[0.5]', 25);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY . '.bucket[1]', 30);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY . '.bucket[2]',  32);
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY . '.bucket[Inf]',  35);

		// wait for calculated item to process
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "zbx_expression_eval_execute() expression:" .
			"'histogram_quantile(0.25,0.1,last(0),0.5,last(1),1.0,last(2),2.0,last(3),\"+Inf\",last(4))'",
			true, 120);

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

	public function testCalculatedExpression_CountForeach() {
		// create several trapper items simulating disk usage for different filesystems
		foreach ( ['fs1', 'fs2', 'fs3', 'fs4'] as $i => $fs) {
			$response = $this->call('item.create', [
				'hostid'	=> self::$hostid,
				'name'		=> "disk.pused[$fs]",
				'key_'		=> self::TRAPPER_ITEM_KEY . ".disk.pused[$fs]",
				'type'		=> ITEM_TYPE_TRAPPER,
				'value_type'	=> ITEM_VALUE_TYPE_FLOAT
			]);
			$itemid = $response['result']['itemids'][0];
			$this->assertEquals(1, count($response['result']['itemids']));
			self::$itemIds = array_merge(self::$itemIds, [$itemid]);
		}

		$formula = 'count(last_foreach(/*/test.calc.trapper.disk.pused[*]),"gt",95)';

		$response = $this->call('item.create', [
			'name'		=> self::CALCULATED_ITEM_KEY . '.count_disk_pused_gt_95',
			'key_'		=> self::CALCULATED_ITEM_KEY . '.count_disk_pused_gt_95',
			'type'		=> ITEM_TYPE_CALCULATED,
			'params'	=> $formula,
			'hostid'	=> self::$hostid,
			'delay'		=> '1s',
			'value_type'	=> ITEM_VALUE_TYPE_UINT64
		]);
		$calcItemId = $response['result']['itemids'][0];
		$this->assertEquals(1, count($response['result']['itemids']));
		self::$itemIds = array_merge(self::$itemIds, [$calcItemId]);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "In zbx_substitute_item_key_params():" .
			" data:test.calc.calculated.count_disk_pused_gt_95", true, 120);

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

		// We have 4 items: [90, 96, 97, 80]. The formula counts how many have last value greater than 95. So expected result is 2.
		$this->assertEquals('2', $this->getItemLastValue($calcItemId));
	}

	public static function clearData(): void {

		if (!empty(self::$itemIds)) {
			CDataHelper::call('item.delete', self::$itemIds);
			self::$itemIds = [];
		}

		CDataHelper::call('host.delete', [self::$hostid]);
	}
}

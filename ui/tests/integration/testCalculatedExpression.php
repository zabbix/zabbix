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
				'LogFileSize' => 20,
				'AllowUnsupportedDBVersions' => 1
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
					'groupid' => 4
				]
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		// Create trapper item.
		$response = $this->call('item.create', [
			'hostid'		=> self::$hostid,
			'name'			=> self::TRAPPER_ITEM_KEY,
			'key_'			=> self::TRAPPER_ITEM_KEY,
			'type'			=> ITEM_TYPE_TRAPPER,
			'value_type'		=> ITEM_VALUE_TYPE_UINT64,
			'tags'		=> [
				['tag' => 'env', 'value' => 'prod']
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		self::$itemIds = array_merge(self::$itemIds, $response['result']['itemids']);

		// Create trapper item 2.
		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => self::TRAPPER_ITEM_KEY_2,
			'key_' => self::TRAPPER_ITEM_KEY_2,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'tags'		=> [
				['tag' => 'env', 'value' => 'prod']
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		self::$itemIds = array_merge(self::$itemIds, $response['result']['itemids']);
	}


	/**
	 * Helper: create calculated item with given formula and return its itemid
	 */
	private function createCalculatedItemWithFormula($formula, $keySuffix)
	{
		$response = $this->call('item.create', [
			'name'		=> self::CALCULATED_ITEM_KEY . '.' . $keySuffix,
			'key_'		=> self::CALCULATED_ITEM_KEY . '.' . $keySuffix,
			'type'		=> ITEM_TYPE_CALCULATED,
			'params'	=> $formula,
			'hostid'	=> self::$hostid,
			'delay'		=> '1s',
			'value_type' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		return $response['result']['itemids'][0];
	}


	private function sendSequence($n, $itemkey)
	{
		for ($i = 1; $i <= $n; $i++) {
			$this->sendSenderValue(self::HOST_NAME, $itemkey, $i);
			sleep(2);
		}
	}

	private function sendToSecondSequence($n)
	{
		for ($i = 1; $i <= $n; $i++) {
			$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY_2, $i * 10);
			sleep(2);
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
		$this->sendSequence(5, self::TRAPPER_ITEM_KEY); // 1..5 -> avg = 3
		$this->assertEquals('3', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_MaxOfLast4()
	{
		$formula = 'max(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . ',#4)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'max4');
		self::$itemIds = array_merge(self::$itemIds, [$itemid]);
		$this->sendSequence(5); // last 4 are 2,3,4,5 -> max = 5
		$this->assertEquals('5', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_MinOfLast3()
	{
		$formula = 'min(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . ',#3)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'min3');
		self::$itemIds = array_merge(self::$itemIds, [$itemid]);
		$this->sendSequence(5); // last 3 are 3,4,5 -> min = 3
		$this->assertEquals('3', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_LastValue()
	{
		$formula = 'last(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . ',#1)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'last1');
		self::$itemIds = array_merge(self::$itemIds, [$itemid]);
		$this->sendSequence(3); // last = 3
		$this->assertEquals('3', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_ArithmeticAndScaling()
	{
		$formula = '(avg(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . ',#5) * 2) + 1';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'avg5_mul2');
		self::$itemIds = array_merge(self::$itemIds, [$itemid]);
		$this->sendSequence(5); // last5:1,2,3,4,5 avg=3
		$this->assertEquals('7', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_CombinedFunctions()
	{
		// formula: sum(last5) - avg(last5)
		$formula = 'sum(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . ',#5) - avg(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . ',#5)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'sum_minus_avg5');
		self::$itemIds = array_merge(self::$itemIds, [$itemid]);
		$this->sendSequence(5); // sum=15 avg=3 -> 12
		$this->assertEquals('12', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_MultiItemAverage()
	{
		// formula averaging two items' last values
		$formula = 'avg(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . ',#1) + avg(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY_2 . ',#1)';
		$itemid = $this->createCalculatedItemWithFormula($formula, 'multi_avg');
		self::$itemIds = array_merge(self::$itemIds, [$itemid]);
		$this->sendSequence(3);
		$this->sendToSecondSequence(3);
		// last values: 3 and 30 -> sum = 33
		$this->assertEquals('33', $this->getItemLastValue($itemid));
	}

	public function testCalculatedExpression_ItemCount_TagFilter(){

		// Create a calculated item using item_count with a tag filter
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

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "In get_value() key:'test.calc.calculated.itemcount_tag'", true, 120);
		$this->assertEquals('2', $this->getItemLastValue($calcItemId));
	}

	public function testCalculatedExpression_BucketPercentile() {
		// Create a histogram bucket item (simulate with a trapper item for test, but real use needs histogram type)
		$response = $this->call('item.create', [
			'hostid'    => self::$hostid,
			'name'      => self::TRAPPER_ITEM_KEY . '.bucket',
			'key_'      => self::TRAPPER_ITEM_KEY . '.bucket',
			'type'      => ITEM_TYPE_TRAPPER,
			'value_type'=> ITEM_VALUE_TYPE_TEXT
		]);
		$itemid = $response['result']['itemids'][0];
		self::$itemIds = array_merge(self::$itemIds, [$itemid]);

		// Create a calculated item using bucket_percentile
		$formula = 'bucket_percentile(/' . self::HOST_NAME . '/' . self::TRAPPER_ITEM_KEY . '.bucket,1h,90)';
		$response = $this->call('item.create', [
			'name'      => self::CALCULATED_ITEM_KEY . '.bucket_percentile',
			'key_'      => self::CALCULATED_ITEM_KEY . '.bucket_percentile',
			'type'      => ITEM_TYPE_CALCULATED,
			'params'    => $formula,
			'hostid'    => self::$hostid,
			'delay'     => '1s',
			'value_type'=> ITEM_VALUE_TYPE_UINT64
		]);
		$calcItemId = $response['result']['itemids'][0];
		self::$itemIds = array_merge(self::$itemIds, [$calcItemId]);

		sleep(20);
		// Send values to the bucket item (simulate histogram bucket values)
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_KEY . '.bucket[1.0]', '{"0.1":5,"0.5":20,"1":40,"2":10}');

		sleep(20); // wait for calculated item to process
		// The calculated item should return a percentile value (for test, just assert it's numeric)
		$this->assertEquals('2', $this->getItemLastValue($calcItemId));
	}

	public static function clearData(): void {

		// if (!empty(self::$itemIds)) {
		// 	CDataHelper::call('item.delete', self::$itemIds);
		// 	self::$itemIds = [];
		// }

	 	// CDataHelper::call('host.delete', [self::$hostid]);
	}
}


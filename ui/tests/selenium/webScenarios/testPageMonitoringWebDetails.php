<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../traits/TableTrait.php';

define('HTTPSTEP_ITEM_TYPE_RSPCODE', 0); // Response code.
define('HTTPSTEP_ITEM_TYPE_TIME', 1); // Response time, in seconds.
define('HTTPSTEP_ITEM_TYPE_IN', 2); // Download speed, in bytes per second.
define('HTTPSTEP_ITEM_TYPE_LASTSTEP', 3); // Download speed, in bytes per second.
define('HTTPSTEP_ITEM_TYPE_LASTERROR', 4); // Download speed, in bytes per second.

/**
 * @backup hosts
 *
 * @onBefore prepareData
 */
class testPageMonitoringWebDetails extends CWebTest {

	use TableTrait;

	protected const HOST_NAME = 'Host with web scenarios';

	protected static $host_id;
	protected static $httptest_id;

	public function prepareData() {
		$response = CDataHelper::createHosts([
			[
				'host' => self::HOST_NAME,
				'groups' => [
					'groupid' => '6'
				]
			]
		]);
		self::$host_id = $response['hostids'][self::HOST_NAME];

		$response = CDataHelper::call('httptest.create', [
			[
				'name' => 'Web scenario',
				'hostid' => self::$host_id,
				'steps' => [
					[
						'name' => 'Step 1',
						'url' => 'http://example.com',
						'no' => 1
					]
				]
			]
		]);
		self::$httptest_id = $response['httptestids'][0];
	}

	/**
	 * Test the general layout.
	 */
	public function testPageMonitoringWebDetails_Layout() {
		$this->page->login()->open('httpdetails.php?httptestid='.self::$web_scenario_id)->waitUntilReady();

		// Assert filter.
		/*$form = $this->query('name:zbx_filter')->asForm()->one();
		$this->assertEquals(['Host groups', 'Hosts', 'Name', 'Tags', 'Show tags', 'Tag display priority', 'State', 'Show details'],
			$form->getLabels()->asText()
		);*/
	}

	public function getWebScenarioData()
	{
		return [
			[
				[
					'name' => 'Many steps',
					'steps' => $this->getGeneratedStepNames(50),
					'item_data' => [HTTPSTEP_ITEM_TYPE_LASTSTEP => 0]
				]
			],
			[
				[
					'name' => 'TEST ЗАББИКС !@#$%-() 🙂🙃 <br/> &nbsp;',
					'steps' => [
						['name' => 'test ēõšŗ тест 测试 テスト δοκιμή'],
						['name' => '!@#$%^&*_+\\/()[]{}<>🙂🙃'],
						['name' => '<script>window.onload=function(){alert("hi!");}</script>']
					]
				]
			],
			[
				[
					'name' => ' 	Test whitespace 	',
					'steps' => [
						['name' => '	Tabs	'],
						['name' => ' Spaces '],
						['name' => "\nNewline\n"]
					]
				]
			]
		];
	}

	/**
	 * Test the display of data in the table.
	 *
	 * @dataProvider getWebScenarioData
	 */
	public function testPageMonitoringWebDetails_DataDisplay($data) {
		// Fill in step data so that they can be created with API.
		$api_steps = [];
		foreach ($data['steps'] as $i => $step){
			$api_step = [];
			$api_step['name'] = $step['name'];
			$api_step['url'] = 'http://example.com';
			$api_step['no'] = $i;
			$api_steps[] = $api_step;
		}

		// Create a new web scenario.
		$response = CDataHelper::call('httptest.create', [
			[
				'name' => $data['name'],
				'hostid' => self::$host_id,
				'steps' => $api_steps
			]
		]);
		$httptest_id = $response['httptestids'][0];

		CTestArrayHelper::get();

		// Generate item data for the table. For the entire web scenario.
		foreach ($data['item_data'] as $data_type => $data_value){
			$sql = 'SELECT ti.itemid FROM httptestitem ti '.
				'JOIN items i ON ti.itemid=i.itemid '.
				'WHERE ti.httptestid = '.$httptest_id.' '.
				'AND ti.type = '.$data_type;
			$item_id = CDBHelper::getValue($sql);
			CDataHelper::addItemData($item_id, $data_value);
		}

		// Generate item data for the table. For each step.
		foreach ($data['steps'] as $i => $step){
			// Each step can have different types of data.
			foreach ($step['item_data'] as $data_type => $data_value){
				$sql = 'SELECT si.itemid FROM httpstepitem si '.
						'JOIN httpstep s ON si.httpstepid=s.httpstepid '.
						'JOIN httptest t ON s.httptestid=t.httptestid '.
						'WHERE t.httptestid = '.$httptest_id.' '.
						'AND s.no = '.$i.' '.
						'AND si.type = '.$data_type;
				$item_id = CDBHelper::getValue($sql);
				CDataHelper::addItemData($item_id, $data_value);
			}
		}

		$this->page->login()->open('httpdetails.php?httptestid='.$httptest_id)->waitUntilReady();

		// Assert title.
		$this->assertEquals('Details of web scenario: '.trim($data['name']), $this->query('id:page-title-general')->one()->getText());

		// Assert data table.
		$expected_steps = [];
		foreach ($data['steps'] as $step){
			$expected_steps[] = ['Step' => trim($step['name'])];
		}
		// The table contains an additional TOTAL row.
		$expected_steps[] = ['Step' => 'TOTAL'];
		$this->assertTableData($expected_steps);

	}

	/**
	 * Generates an array of steps with a length of $count.
	 */
	protected  function getGeneratedStepNames($count) {
		$result = [];
		for ($i = 1; $i <= $count; $i++) {
			$result[] = [
				'name' => 'Step-'.$i,
				'item_data' => [
					HTTPSTEP_ITEM_TYPE_RSPCODE => 200,
					HTTPSTEP_ITEM_TYPE_TIME => 0.123456,
					HTTPSTEP_ITEM_TYPE_IN => 3000
				],
				'expected_data' => [
					HTTPSTEP_ITEM_TYPE_RSPCODE => '200',
					HTTPSTEP_ITEM_TYPE_TIME => '123.46ms',
					HTTPSTEP_ITEM_TYPE_IN => '3000'
				]
			];
		}
		return $result;
	}
}

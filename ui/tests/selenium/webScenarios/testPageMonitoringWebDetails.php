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

/**
 * @backup hosts
 *
 * @onBefore prepareData
 */
class testPageMonitoringWebDetails extends CWebTest {

	use TableTrait;

	protected static $host_id;
	protected static $httptest_id;

	public function prepareData() {
		$response = CDataHelper::createHosts([
			[
				'host' => 'Host for web scenarios',
				'groups' => [
					'groupid' => '6'
				]
			]
		]);
		self::$host_id = $response['hostids']['Host for web scenarios'];

		$response = CDataHelper::call('httptest.create', [
			[
				'name' => 'Layout',
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
		$this->page->login()->open('httpdetails.php?httptestid='.self::$httptest_id)->waitUntilReady();

		// Close Kiosk mode if opened.
		if ($this->query('xpath://button[@title="Normal view"]')->exists()) {
			$this->query('xpath://button[@title="Normal view"]')->one()->click();
			$this->page->waitUntilReady();
		}

		// If the time selector is not visible - enable it.
		$filter_button = $this->query('id:ui-id-1')->one();
		$filter_button_minimized = $this->query('xpath://li[@aria-labelledby="ui-id-1" and @aria-selected="false"]');
		if ($filter_button_minimized->exists()) {
			$filter_button->click();
		}

		// Assert title and header.
		$this->page->assertTitle('Details of web scenario');
		$this->page->assertHeader('Details of web scenario: Layout');

		// Assert table column names.
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertEquals(['Step', 'Speed', 'Response time', 'Response code', 'Status'], $table->getHeadersText());

		// Check filter layout and values.
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$this->assertEquals('now-1h', $form->query('id:from')->one()->getValue());
		$this->assertEquals('now', $form->query('id:to')->one()->getValue());
		$this->assertEquals('selected', $form->query('xpath://a[@data-from="now-1h"]')->one()->getAttribute('class'));
		$this->assertTrue($this->query('xpath://button[@class="btn-time-left"]')->one()->isEnabled());
		$this->assertTrue($this->query('button:Zoom out')->one()->isEnabled());
		$this->assertFalse($this->query('xpath://button[@class="btn-time-right"]')->one()->isEnabled());

		// Set custom time filter.
		$form->fill(['id:from' => 'now-2h', 'id:to' => 'now-1h']);
		$form->query('id:apply')->one()->click();
		$this->assertGraphSrcContains('from=now-2h&to=now-1h');
		$this->assertTrue($this->query('xpath://button[@class="btn-time-right"]')->one()->isEnabled());

		// Use time filter preset button.
		$form->query('xpath://a[@data-from="now-30d"]')->one()->click();
		$this->assertGraphSrcContains('from=now-30d&to=now');

		// Minimize filter section.
		$filter_button->click();
		$this->assertTrue($filter_button_minimized->exists());

		// Test Kiosk mode.
		$this->query('xpath://button[@title="Kiosk mode"]')->one()->click();
		$this->page->waitUntilReady();

		// Check that Header and Filter disappeared.
		$this->query('xpath://h1[@id="page-title-general"]')->waitUntilNotVisible();
		$this->assertFalse($this->query('xpath://div[@aria-label="Filter"]')->exists());
		$this->assertTrue($this->query('class:list-table')->exists());

		$this->query('xpath://button[@title="Normal view"]')->waitUntilPresent()->one()->click(true);
		$this->page->waitUntilReady();

		// Check that Header and Filter are visible again.
		$this->query('xpath://h1[@id="page-title-general"]')->waitUntilVisible();
		$this->assertTrue($this->query('xpath://div[@aria-label="Filter"]')->exists());
		$this->assertTrue($this->query('class:list-table')->exists());
	}

	public function getDataDisplayData() {
		return [
			[
				[
					'name' => 'Many steps',
					'steps' => array_fill(0, 50, [])
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
			],
			[
				[
					'name' => STRING_64,
					'steps' => [
						['name' => STRING_64]
					]
				]
			],
			[
				[
					'name' => 'Result - OK',
					'global_item_data' => [
						HTTPSTEP_ITEM_TYPE_LASTSTEP => 0
					],
					'expected_totals' => ['Response time' => '16m 39s 123.46ms', 'Status' => 'OK'],
					'steps' => [
						[
							'item_data' => [
								HTTPSTEP_ITEM_TYPE_RSPCODE => 200,
								HTTPSTEP_ITEM_TYPE_TIME => 0.123456,
								HTTPSTEP_ITEM_TYPE_IN => 3000
							],
							'expected_data' => [
								'Speed' => '2.93 KBps',
								'Response time' => '123.46ms',
								'Response code' => '200',
								'Status' => 'OK'
							]
						],
						[
							'item_data' => [
								HTTPSTEP_ITEM_TYPE_RSPCODE => 404,
								HTTPSTEP_ITEM_TYPE_TIME => 999,
								HTTPSTEP_ITEM_TYPE_IN => 1099511627776
							],
							'expected_data' => [
								'Speed' => '1 TBps',
								'Response time' => '16m 39s',
								'Response code' => '404',
								'Status' => 'OK'
							]
						]
					]
				]
			],
			[
				[
					'name' => 'Result - Empty',
					'expected_totals' => ['Status' => ''],
					'steps' => [
						['expected_data' => ['Status' => '']]
					]
				]
			],
			[
				[
					'name' => 'Result - Unknown error',
					'global_item_data' => [
						HTTPSTEP_ITEM_TYPE_LASTSTEP => 1
					],
					'expected_totals' => ['Status' => 'Unknown error'],
					'steps' => [
						['expected_data' => ['Status' => 'OK']],
						['expected_data' => ['Status' => 'Unknown error']],
						['expected_data' => ['Status' => 'Unknown']],
						['expected_data' => ['Status' => 'Unknown']]
					]
				]
			],
			[
				[
					'name' => 'Result - Error',
					'global_item_data' => [
						HTTPSTEP_ITEM_TYPE_LASTSTEP => 1,
						HTTPSTEP_ITEM_TYPE_LASTERROR => 'TEST ERROR TEXT 🙂🙃'
					],
					'expected_totals' => ['Status' => 'Error: TEST ERROR TEXT 🙂🙃'],
					'steps' => [
						['expected_data' => ['Status' => 'OK']],
						['expected_data' => ['Status' => 'Error: TEST ERROR TEXT 🙂🙃']],
						['expected_data' => ['Status' => 'Unknown']],
						['expected_data' => ['Status' => 'Unknown']]
					]
				]
			]
		];
	}

	/**
	 * Test the display of data in the table.
	 * Additional complexity comes from the Status column, as the displayed values there are calculated on the fly.
	 *
	 * @dataProvider getDataDisplayData
	 */
	public function testPageMonitoringWebDetails_DataDisplay($data) {
		// Fill in step data so that a web scenario can be created with API.
		$api_steps = [];
		foreach ($data['steps'] as $i => $step){
			$api_step['name'] = $step['name'] ?? 'Step '.$i + 1;
			$api_step['url'] = 'http://example.com';
			$api_step['no'] = $i;
			$api_steps[] = $api_step;
		}

		// Create the web scenario with API.
		$response = CDataHelper::call('httptest.create', [
			[
				'name' => $data['name'],
				'hostid' => self::$host_id,
				'steps' => $api_steps
			]
		]);
		$httptest_id = $response['httptestids'][0];

		// Generate data for global web scenario items.
		foreach ($data['global_item_data'] ?? [] as $data_type => $data_value) {
			// Gets id of the correct item.
			$sql = 'SELECT ti.itemid FROM httptestitem ti'.
					' JOIN items i ON ti.itemid=i.itemid'.
					' WHERE ti.httptestid='.$httptest_id.
						' AND ti.type='.$data_type;
			$item_id = CDBHelper::getValue($sql);
			CDataHelper::addItemData($item_id, $data_value);
		}

		// Generate data for step items.
		foreach ($data['steps'] as $i => $step) {
			// Each step has several item types.
			foreach ($step['item_data'] ?? [] as $data_type => $data_value) {
				// Gets id of the correct item.
				$sql = 'SELECT si.itemid FROM httpstepitem si'.
						' JOIN httpstep s ON si.httpstepid=s.httpstepid'.
						' JOIN httptest t ON s.httptestid=t.httptestid'.
						' WHERE t.httptestid='.$httptest_id.
							' AND s.no='.$i.
							' AND si.type='.$data_type;
				$item_id = CDBHelper::getValue($sql);
				CDataHelper::addItemData($item_id, $data_value);
			}
		}

		$this->page->login()->open('httpdetails.php?httptestid='.$httptest_id)->waitUntilReady();

		// Assert header.
		$this->page->assertHeader('Details of web scenario: '.trim($data['name']));

		// Assert data table.
		$expected_rows = [];
		foreach ($data['steps'] as $i => $step) {
			// Whitespace at the beginning and end should not be displayed.
			$expected_row = ['Step' => trim($step['name'] ?? 'Step '.$i + 1)];
			$expected_row = array_merge($expected_row, $step['expected_data'] ?? []);
			$expected_rows[] = $expected_row;
		}

		// The table contains an additional TOTAL row.
		$expected_rows[] = array_merge(['Step' => 'TOTAL'], $data['expected_totals'] ?? []);
		$this->assertTableData($expected_rows);
	}

	/**
	 * Waits for both graphs to reload after a filter change and asserts that their src strings contain some value.
	 *
	 * @param string $expected_src    the value that should be contained within the src parameter
	 */
	protected function assertGraphSrcContains($expected_src) {
		foreach (['graph_in', 'graph_time'] as $i => $graph_id) {
			$graph = $this->query('id', $graph_id)->one();

			// Only wait for reload once.
			if ($i == 0) {
				$graph->waitUntilReloaded();
			}

			$this->assertStringContainsString($expected_src, $graph->getAttribute('src'));
		}
	}
}

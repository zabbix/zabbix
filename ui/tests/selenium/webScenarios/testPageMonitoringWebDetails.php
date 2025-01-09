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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';

/**
 * @backup hosts
 *
 * @onBefore prepareData
 */
class testPageMonitoringWebDetails extends CWebTest {

	/**
	 * Attach TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CTableBehavior::class];
	}

	protected static $host_id;
	protected static $httptest_id;

	public function prepareData() {
		$response = CDataHelper::createHosts([
			[
				'host' => 'Host for web scenarios',
				'groups' => [
					'groupid' => 6
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

		// Assert title and header.
		$this->page->assertTitle('Details of web scenario');
		$this->page->assertHeader('Details of web scenario: Layout');

		// Assert table column names.
		$this->assertEquals(['Step', 'Speed', 'Response time', 'Response code', 'Status'],
				$this->query('class:list-table')->asTable()->one()->getHeadersText()
		);

		// Check filter layout and values.
		$form = $this->query('class:filter-container')->asForm(['normalized' => true])->one();
		$form->checkValue(['id:from' => 'now-1h', 'id:to' => 'now']);
		$this->assertEquals('selected', $form->query('link:Last 1 hour')->one()->getAttribute('class'));
		$buttons = [
			'xpath://button[contains(@class, "js-btn-time-left")]' => true,
			'xpath://button[contains(@class, "js-btn-time-right")]' => false,
			'button:Zoom out' => true,
			'button:Apply' => true,
			'id:from_calendar' => true,
			'id:to_calendar' => true
		];

		foreach ($buttons as $selector => $enabled) {
			$this->assertTrue($this->query($selector)->one()->isEnabled($enabled));
		}

		// Check that filter is expanded by default.
		$filter_tab = CFilterElement::find()->one()->setContext(CFilterElement::CONTEXT_RIGHT);
		$this->assertTrue($filter_tab->isExpanded());

		// Check that filter is collapsing/expanding on click.
		foreach ([false, true] as $status) {
			$filter_tab->expand($status);
			$this->assertTrue($filter_tab->isExpanded($status));
		}
	}

	public function getCheckFiltersData() {
		return [
			[
				[
					'fields' => ['id:from' => 'now-2h', 'id:to' => 'now-1h'],
					'expected' => 'from=now-2h&to=now-1h',
					'zoom_buttons' => [
						'js-btn-time-left' => true,
						'btn-time-zoomout' => true,
						'js-btn-time-right' => true
					]
				]
			],
			[
				[
					'fields' => ['id:from' => 'now-2y', 'id:to' => 'now-1y'],
					'expected' => 'from=now-2y&to=now-1y',
					'zoom_buttons' => [
						'js-btn-time-left' => true,
						'btn-time-zoomout' => true,
						'js-btn-time-right' => true
					]
				]
			],
			[
				[
					'link' => 'Last 30 days',
					'expected' => 'from=now-30d&to=now',
					'zoom_buttons' => [
						'js-btn-time-left' => true,
						'btn-time-zoomout' => true,
						'js-btn-time-right' => false
					]
				]
			],
			[
				[
					'link' => 'Last 2 years',
					'expected' => 'from=now-2y&to=now',
					'zoom_buttons' => [
						'js-btn-time-left' => true,
						'btn-time-zoomout' => false,
						'js-btn-time-right' => false
					]
				]
			]
		];
	}

	/**
	 * Change values in the filter section and check the resulting changes in graphs.
	 *
	 * @dataProvider getCheckFiltersData
	 */
	public function testPageMonitoringWebDetails_CheckFilters($data) {
		$this->page->login()->open('httpdetails.php?httptestid='.self::$httptest_id)->waitUntilReady();
		$form = $this->query('class:filter-container')->asForm(['normalized' => true])->one();

		// Set custom time filter.
		if (CTestArrayHelper::get($data, 'fields')) {
			$form->fill($data['fields']);
		}
		else {
			$form->query('link', $data['link'])->waitUntilClickable()->one()->click();
		}

		$graph = $this->query('id:graph_in')->one();
		$form->fill(CTestArrayHelper::get($data, 'fields', 'link'));
		$form->query('id:apply')->one()->click();
		$graph->waitUntilReloaded();

		foreach (['graph_in', 'graph_time'] as $graph_id) {
			$this->assertStringContainsString($data['expected'], $this->query('id', $graph_id)
					->one()->getAttribute('src')
			);
		}

		// Check Zoom buttons.
		foreach ($data['zoom_buttons'] as $button => $state) {
			$this->assertTrue($this->query('xpath://button[contains(@class, '.CXPathHelper::escapeQuotes($button).
					')]')->one()->isEnabled($state)
			);
		}
	}

	/**
	 * Open and test the Kiosk mode.
	 */
	public function testPageMonitoringWebDetails_CheckKioskMode() {
		$this->page->login()->open('httpdetails.php?httptestid='.self::$httptest_id)->waitUntilReady();

		// Test Kiosk mode.
		$this->query('xpath://button[@title="Kiosk mode"]')->one()->hoverMouse()->click();
		$this->query('xpath://button[@title="Normal view"]')->waitUntilPresent();
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

	public function getDisplayTableData() {
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
	 * @dataProvider getDisplayTableData
	 */
	public function testPageMonitoringWebDetails_DisplayTable($data) {
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
}

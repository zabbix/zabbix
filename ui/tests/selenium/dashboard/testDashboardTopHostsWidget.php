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

require_once dirname(__FILE__) . '/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * @backup widget, profiles, dashboard
 *
 * @onBefore prepareDashboardPageData
 */
class testDashboardTopHostsWidget extends CWebTest {

	/**
	 * Id of dashboard for Top Hosts widget update.
	 *
	 * @var integer
	 */
	protected static $updateid;

	/**
	 * Id of dashboard for Top Hosts widget creation.
	 *
	 * @var integer
	 */
	protected static $createid;

	/**
	 * Id of dashboard for Top Hosts widget delete.
	 *
	 * @var integer
	 */
	protected static $deleteid;

	/**
	 * Id of dashboard page for Top Hosts widget update.
	 *
	 * @var integer
	 */
	protected static $update_pageid;

	/**
	 * Id of dashboard page for Top Hosts widget creation.
	 *
	 * @var integer
	 */
	protected static $create_pageid;

	/**
	 * Id widget for update.
	 *
	 * @var integer
	 */
	protected static $update_widgetid;

	const WIDGET_UPDATE_NAME = 'Top hosts update';

	private static $updated_name = self::WIDGET_UPDATE_NAME;

	/**
	 * Create new dashboards for autotest.
	 */
	public function prepareDashboardPageData() {
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for Top Hosts widget update',
				'display_period' => 30,
				'auto_start' => 1,
				'pages' => [
					[
						'name' => '',
						'widgets' => [
							[
								'type' => 'tophosts',
								'name' => 'Top hosts update',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 8,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'columns.name.0',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'columns.data.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.item.0',
										'value' => 'Available memory'
									],
									[
										'type' => 1,
										'name' => 'columns.timeshift.0',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'columns.aggregate_function.0',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'columns.display.0',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'columns.history.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.base_color.0',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'column',
										'value' => 0
									],
									[
										'type' => 1,
										'name' => 'columns.name.1',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'columns.data.1',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.item.1',
										'value' => 'Available memory in %'
									],
									[
										'type' => 1,
										'name' => 'columns.timeshift.1',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'columns.aggregate_function.1',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'columns.display.1',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'columns.history.1',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.base_color.1',
										'value' => ''
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.color.1.0',
										'value' => 'FF465C'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.threshold.1.0',
										'value' => '100'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.color.1.1',
										'value' => 'B0AF07'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.threshold.1.1',
										'value' => '600'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.color.0.0',
										'value' => 'FF465C'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.threshold.0.0',
										'value' => '100'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.color.0.1',
										'value' => 'B0AF07'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.threshold.0.1',
										'value' => '600'
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for Top Hosts widget creation',
				'display_period' => 30,
				'auto_start' => 1,
				'pages' => [
					[]
				]
			],
			[
				'name' => 'Dashboard for Top Hosts widget delete',
				'display_period' => 30,
				'auto_start' => 1,
				'pages' => [
					[
						'name' => '',
						'widgets' => [
							[
								'type' => 'tophosts',
								'name' => 'Top hosts delete',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 8,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'columns.name.0',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'columns.data.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.item.0',
										'value' => 'Available memory'
									],
									[
										'type' => 1,
										'name' => 'columns.timeshift.0',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'columns.aggregate_function.0',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'columns.display.0',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'columns.history.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.base_color.0',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'column',
										'value' => 0
									]
								]
							]
						]
					]
				]
			]
		]);

		$this->assertArrayHasKey('dashboardids', $response);
		self::$updateid = $response['dashboardids'][0];
		self::$createid = $response['dashboardids'][1];
		self::$deleteid = $response['dashboardids'][2];

		self::$update_pageid = CDBHelper::getValue('SELECT dashboard_pageid FROM dashboard_page WHERE dashboardid='
				.zbx_dbstr(self::$updateid));
		self::$create_pageid = CDBHelper::getValue('SELECT dashboard_pageid FROM dashboard_page WHERE dashboardid='
				.zbx_dbstr(self::$createid));

		self::$update_widgetid = CDBHelper::getValue('SELECT widgetid FROM widget WHERE dashboard_pageid='
				.zbx_dbstr(self::$update_pageid));
	}

	public static function getCreateTopHostsData() {
		return [
			// #0 minimum needed values to create and submit widget.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory'
						]
					]
				]
			],
			// #1 all fields filled for main form.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Name of Top hosts widget',
						'Refresh interval' => 'Default (1 minute)',
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'ЗАББИКС Сервер',
						'Order' => 'Bottom N',
						'Host count' => '99'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory'
						]
					]
				]
			],
			// #2 change order column for several items.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Several item columns',
						'Order column' => 'Available memory in %'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory'
						],
						[
							'Data' => 'Item value',
							'Item' => 'Available memory in %'
						]
					]
				]
			],
			// #3 several item columns with different Aggregation function
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'All available aggregatino function'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Name' => 'min',
							'Aggregation function' => 'min',
							'Aggregation interval' => '20s',
							'Item' => 'Available memory'
						],
						[
							'Data' => 'Item value',
							'Name' => 'max',
							'Aggregation function' => 'max',
							'Aggregation interval' => '20m',
							'Item' => 'Available memory'
						],
						[
							'Data' => 'Item value',
							'Name' => 'avg',
							'Aggregation function' => 'avg',
							'Aggregation interval' => '20h',
							'Item' => 'Available memory'
						],
						[
							'Data' => 'Item value',
							'Name' => 'count',
							'Aggregation function' => 'count',
							'Aggregation interval' => '20d',
							'Item' => 'Available memory'
						],
						[
							'Data' => 'Item value',
							'Name' => 'sum',
							'Aggregation function' => 'sum',
							'Aggregation interval' => '20w',
							'Item' => 'Available memory'
						],
						[
							'Data' => 'Item value',
							'Name' => 'first',
							'Aggregation function' => 'first',
							'Aggregation interval' => '20M',
							'Item' => 'Available memory'
						],
						[
							'Data' => 'Item value',
							'Name' => 'last',
							'Aggregation function' => 'last',
							'Aggregation interval' => '20y',
							'Item' => 'Available memory'
						]
					]
				]
			],
			// #4 several item columns with different display, time shift, min/max and history data.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Different display and history data fields',
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'As is',
							'History data' => 'History',
							'Time shift' => '1'
						],
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'As is',
							'History data' => 'Trends'
						],
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Bar',
							'History data' => 'Auto',
							'Min' => '2',
							'Max' => ''
						],
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Bar',
							'History data' => 'History',
							'Min' => '',
							'Max' => '100'
						],
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Bar',
							'History data' => 'Trends',
							'Min' => '50',
							'Max' => '100'
						],
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Indicators',
							'History data' => 'Auto',
							'Min' => '2',
							'Max' => ''
						],
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Indicators',
							'History data' => 'History',
							'Min' => '',
							'Max' => '100'
						],
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Indicators',
							'History data' => 'Trends',
							'Min' => '50',
							'Max' => '100'
						]
					]
				]
			],
			// #5 add column with different Base color.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Another base color'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Base color' => [
								'id:lbl_base_color' => '039BE5'
							]
						]
					]
				]
			],
			// #6 add column with Threshold without color change.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'One Threshold'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Thresholds' => [
								[
									'value' => '5'
								]
							]
						]
					]
				]
			],
			// #7 add several columns with Threshold without color change.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Several Threshold'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Thresholds' => [
								[
									'value' => '1'
								],
								[
									'value' => '100'
								],
								[
									'value' => '1000'
								]
							]
						]
					]
				]
			],
			// #8 add several columns with Threshold with color change and without color.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Several Thresholds with colors'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Thresholds' => [
								[
									'value' => '1',
									'color' => 'FFEB3B'
								],
								[
									'value' => '100',
									'color' => 'AAAAAA'
								],
								[
									'value' => '1000',
									'color' => 'AAAAAA'
								],
								[
									'value' => '10000',
									'color' => ''
								]
							]
						]
					]
				]
			],
			// #9 add Host name columns.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Host name columns'
					],
					'column_fields' => [
						[
							'Data' => 'Host name',
							'Name' => 'This is host name',
							'Base color' => [
								'id:lbl_base_color' => '039BE5'
							]
						],
						[
							'Data' => 'Host name'
						],
						[
							'Data' => 'Item value',
							'Item' => 'Available memory'
						]
					]
				]
			],
			// #10 add Text columns.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Text columns'
					],
					'column_fields' => [
						[
							'Data' => 'Text',
							'Text' => 'Here is some text'
						],
						[
							'Data' => 'Text',
							'Text' => 'Here is some text 2',
							'Name' => 'Text column name'
						],
						[
							'Data' => 'Text',
							'Text' => 'Here is some text 3',
							'Name' => 'Text column name 2',
							'Base color' => [
								'id:lbl_base_color' => '039BE5'
							]
						],
						[
							'Data' => 'Item value',
							'Item' => 'Available memory'
						]
					]
				]
			],
			// #11 error message adding widget without any column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Widget without columns'
					],
					'main_error' => [
						'Invalid parameter "Columns": an array is expected.',
						'Invalid parameter "Order column": an integer is expected.'
					]
				]
			],
			// #12 error message adding widget without item column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Widget without item column'
					],
					'column_fields' => [
						[
							'Data' => 'Host name'
						]
					],
					'main_error' => [
						'Invalid parameter "Order column": an integer is expected.'
					]
				]
			],
			// #13 add characters in host count field.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Host count error with item column',
						'Host count' => 'zzz'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory'
						]
					],
					'main_error' => [
						'Invalid parameter "Host count": value must be one of 1-100.'
					]
				]
			],
			// #14 add incorrect value to host count field without item column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Host count error without item column',
						'Host count' => '333'
					],
					'column_fields' => [
						[
							'Data' => 'Host name'
						]
					],
					'main_error' => [
						'Invalid parameter "Order column": an integer is expected.',
						'Invalid parameter "Host count": value must be one of 1-100.'
					]
				]
			],
			// #15 color error in host name column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Color error in Host name column'
					],
					'column_fields' => [
						[
							'Data' => 'Host name',
							'Base color' => [
								'id:lbl_base_color' => '!@#$%^'
							]
						]
					],
					'column_error' => [
						'Invalid parameter "/1/base_color": a hexadecimal color code (6 symbols) is expected.'
					]
				]
			],
			// #16 check error adding text column without any value.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Error in empty text column'
					],
					'column_fields' => [
						[
							'Data' => 'Text'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/text": cannot be empty.'
					]
				]
			],
			// #17 color error in text column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Error in text column color'
					],
					'column_fields' => [
						[
							'Data' => 'Text',
							'Text' => 'Here is some text',
							'Base color' => [
								'id:lbl_base_color' => '!@#$%^'
							]
						]
					],
					'column_error' => [
						'Invalid parameter "/1/base_color": a hexadecimal color code (6 symbols) is expected.'
					]
				]
			],
			// #18 error when there is no item in item column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Error without item in item column'
					],
					'column_fields' => [
						[
							'Data' => 'Item value'
						]
					],
					'column_error' => [
						'Invalid parameter "/1": the parameter "item" is missing.'
					]
				]
			],
			// #19 error when incorrect time shift added.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Incorrect time shift'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Time shift' => 'zzz'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/timeshift": a time unit is expected.'
					]
				]
			],
			// #20 error when incorrect aggregation function added.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Incorrect aggregation function'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'count',
							'Aggregation interval' => 'zzz'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/aggregate_interval": a time unit is expected.'
					]
				]
			],
			// #21 error when incorrect min value added.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Incorrect min value'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Bar',
							'Min' => 'zzz'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/min": a number is expected.'
					]
				]
			],
			// #22 error when incorrect max value added.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Incorrect max value'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Bar',
							'Max' => 'zzz'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/max": a number is expected.'
					]
				]
			],
			// #23 color error in item column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Error in item column color'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Base color' => [
								'id:lbl_base_color' => '!@#$%^'
							]
						]
					],
					'column_error' => [
						'Invalid parameter "/1/base_color": a hexadecimal color code (6 symbols) is expected.'
					]
				]
			],
			// #24 color error when incorrect hexadecimal added in first threshold.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Error in item column threshold color'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Thresholds' => [
								[
									'value' => '1',
									'color' => '!@#$%^'
								]
							]
						]
					],
					'column_error' => [
						'Invalid parameter "/1/thresholds/1/color": a hexadecimal color code (6 symbols) is expected.'
					]
				]
			],
			// #25 color error when incorrect hexadecimal added in second threshold.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Error in item column second threshold color'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Thresholds' => [
								[
									'value' => '1',
									'color' => '4000FF'
								],
								[
									'value' => '2',
									'color' => '!@#$%^'
								]
							]
						]
					],
					'column_error' => [
						'Invalid parameter "/1/thresholds/2/color": a hexadecimal color code (6 symbols) is expected.'
					]
				]
			],
			// #26 error message when incorrect value added to threshold.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Error in item column second threshold color'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Thresholds' => [
								[
									'value' => 'zzz',
									'color' => '4000FF'
								]
							]
						]
					],
					'column_error' => [
						'Invalid parameter "/1/thresholds/1/threshold": a number is expected.'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateTopHostsData
	 */
	public function testDashboardTopHostsWidget_Create($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$createid);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();
		$form = $dashboard->edit()->addWidget()->asForm();
		$form->fill(['Type' => 'Top hosts']);
		COverlayDialogElement::find()->waitUntilReady()->one();

		// Add new column.
		if (array_key_exists('column_fields', $data)) {
			$this->columnCreateUpdate($data, 'create');
		}

		$form->fill($data['main_fields']);
		COverlayDialogElement::find()->waitUntilReady()->one();

		$form->submit();

		// Check error message in main widget form.
		if (array_key_exists('main_error', $data)) {
			$message = CMessageElement::find()->waitUntilVisible()->one();
			$this->assertEquals($data['main_error'], $message->getLines()->asText());
		}

		$this->page->waitUntilReady();

		if ($data['expected'] === TEST_GOOD) {
			// Make sure that the widget is present before saving the dashboard.
			$header = CTestArrayHelper::get($data['main_fields'], 'Name', 'Top hosts');
			$dashboard->getWidget($header);
			$dashboard->save();

			// Check message that widget added.
			$this->checkDashboardUpdateMessage();

			// Check widget amount that it is added.
			$this->assertEquals($old_widget_count + 1, $dashboard->getWidgets()->count());
			$this->checkWidget($header, $data);
		}
		else {
			$dashboard->save();
			$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());
		}
	}

	public function testDashboardTopHostsWidget_SimpleUpdate() {
		// Hash before simple update.
		$old_hash = CDBHelper::getHash('SELECT * FROM widget_field WHERE widgetid='.zbx_dbstr(self::$update_widgetid)
				.' ORDER BY widget_fieldid');

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$updateid);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->getWidget(self::$updated_name)->edit();
		$form->submit();
		$dashboard->save();
		$this->page->waitUntilReady();

		// Hash after simple update.
		$new_hash = CDBHelper::getHash('SELECT * FROM widget_field WHERE widgetid='.zbx_dbstr(self::$update_widgetid)
				.' ORDER BY widget_fieldid');

		// Compare old hash and new one.
		$this->assertEquals($old_hash, $new_hash);
	}

	public static function getUpdateTopHostsData() {
		return [
			// #0 incorrecct threshold color.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Thresholds' => [
								[
									'value' => '100',
									'color' => '#$@#$@'
								]
							]
						]
					],
					'column_error' => [
						'Invalid parameter "/1/thresholds/1/color": a hexadecimal color code (6 symbols) is expected.'
					]
				]
			],
			// #1 incorrecct threshold value.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Thresholds' => [
								[
									'value' => '     '
								]
							]
						]
					],
					'column_error' => [
						'Invalid parameter "/1/thresholds/1/threshold": a number is expected.'
					]
				]
			],
			// #2 error message when update Host count incorrectly.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Host count' => '0'
					],
					'main_error' => [
						'Invalid parameter "Host count": value must be one of 1-100.'
					]
				]
			],
			// #3 error message when there is no item column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [],
					'column_fields' => [
						[
							'Data' => 'Host name'
						],
						[
							'Data' => 'Host name'
						]
					],
					'main_error' => [
						'Invalid parameter "Order column": an integer is expected.'
					]
				]
			],
			// #4 time shift error in column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Time shift' => 'zzz'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/timeshift": a time unit is expected.'
					]
				]
			],
			// #5 aggregation interval error in column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Aggregation function' => 'max',
							'Aggregation interval' => 'zzz'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/aggregate_interval": a time unit is expected.'
					]
				]
			],
			// #6 no item error in column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => ''
						]
					],
					'column_error' => [
						'Invalid parameter "/1": the parameter "item" is missing.'
					]
				]
			],
			// #7 incorrecct base color.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Base color' => [
								'id:lbl_base_color' => '#$%$@@'
							]
						]
					],
					'column_error' => [
						'Invalid parameter "/1/base_color": a hexadecimal color code (6 symbols) is expected.'
					]
				]
			],
			// #8 update all main fields.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Updated main fields',
						'Refresh interval' => '2 minutes',
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'ЗАББИКС Сервер',
						'Order' => 'Bottom N',
						'Order column' => 'Available memory in %',
						'Host count' => '2'
					]
				]
			],
			// #9 update first item column to Text column and add some values.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Updated column type to text'
					],
					'column_fields' => [
						[
							'Name' => 'Text column changed',
							'Data' => 'Text',
							'Text' => 'some text',
							'Base color' => [
								'id:lbl_base_color' => '039BE5'
							]
						]
					]
				]
			],
			// #10 update first column to Host name column and add some values.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Updated column type to host name'
					],
					'column_fields' => [
						[
							'Name' => 'Host name column update',
							'Data' => 'Host name',
							'Base color' => [
								'id:lbl_base_color' => 'FF8F00'
							]
						]
					]
				]
			],
			// #11 update item column adding new values and fields.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Updated values for item column'
					],
					'column_fields' => [
						[
							'Name' => 'Only name changed'
						],
						[
							'Item' => 'Available memory',
							'Time shift' => '1',
							'Display' => 'Indicators',
							'History data' => 'Trends',
							'Min' => '50',
							'Max' => '100',
							'Aggregation function' => 'avg',
							'Aggregation interval' => '20h',
							'Base color' => [
								'id:lbl_base_color' => '039BE5'
							],
							'Thresholds' => [
								[
									'value' => '1',
									'color' => 'FFEB3B'
								],
								[
									'value' => '100',
									'color' => 'AAAAAA'
								]
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateTopHostsData
	 */
	public function testDashboardTopHostsWidget_Update($data) {
		if ($data['expected'] === TEST_BAD) {
			// Hash before update.
			$old_hash = CDBHelper::getHash('SELECT * FROM widget_field WHERE widgetid='.zbx_dbstr(self::$update_widgetid)
					.' ORDER BY widget_fieldid');
		}
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$updateid);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->getWidget(self::$updated_name)->edit();

		// Add new column.
		if (array_key_exists('column_fields', $data)) {
			$this->columnCreateUpdate($data, 'update');
		}

		$form->fill($data['main_fields']);
		COverlayDialogElement::find()->waitUntilReady()->one();

		$form->submit();

		// Check error message in main widget form.
		if (array_key_exists('main_error', $data)) {
			$message = CMessageElement::find()->waitUntilVisible()->one();
			$this->assertEquals($data['main_error'], $message->getLines()->asText());
		}

		$this->page->waitUntilReady();

		if ($data['expected'] === TEST_GOOD) {
			self::$updated_name = (array_key_exists('Name', $data['main_fields']))
					? $data['main_fields']['Name']
					: self::$updated_name;

			// Make sure that the widget is present before saving the dashboard.
			$header = CTestArrayHelper::get($data['main_fields'], 'Name', self::$updated_name);
			$dashboard->getWidget($header);
			$dashboard->save();

			// Check message that widget added.
			$this->checkDashboardUpdateMessage();
		}
		else {
			$dashboard->save();

			// Hash after update.
			$new_hash = CDBHelper::getHash('SELECT * FROM widget_field WHERE widgetid='.zbx_dbstr(self::$update_widgetid)
					.' ORDER BY widget_fieldid');

			// Compare old hash and new one.
			$this->assertEquals($old_hash, $new_hash);
		}
	}

	public function testDashboardTopHostsWidget_Delete() {
		$name = 'Top hosts delete';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$deleteid);
		$dashboard = CDashboardElement::find()->one()->edit();
		$dashboard->deleteWidget($name);
		$this->page->waitUntilReady();
		$dashboard->save();

		// Check that Dashboard has been saved.
		$this->checkDashboardUpdateMessage();

		// Confirm that widget is not present on dashboard.
		$this->assertFalse($dashboard->getWidget($name, false)->isValid());

		// Check that widget is removed from DB.
		$widget_sql = 'SELECT * FROM widget_field wf LEFT JOIN widget w ON w.widgetid=wf.widgetid WHERE w.name='.zbx_dbstr($name);
		$this->assertEquals(0, CDBHelper::getCount($widget_sql));
	}

	private function checkWidget($header, $data) {
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->getWidget($header)->edit();
		$form->checkValue($data['main_fields']);

		if (array_key_exists('column_fields', $data)) {
			// Count column amount from data provider.
			$column_amount = count($data['column_fields']);
			$table = $form->query('id:list_columns')->one()->asTable();

			// Count row amount from column table and compare with column amount from data provider.
			$row_amount = $table->getRows()->count()-1;
			$this->assertEquals($column_amount, $row_amount);

			// Check values from column form.
			$row_number = 1;
			foreach ($data['column_fields'] as $values) {
				// check that column table has correct names for added columns.
				$table_name = (array_key_exists('Name', $values)) ? $values['Name'] : '';
				$table->getRow($row_number-1)->getColumnData('Name', $table_name);

				// Check that column table has correct data.
				if ($values['Data'] === 'Item value') {
					$table_name = $values['Item'];
				}
				elseif ($values['Data'] === 'Host name') {
					$table_name = $values['Data'];
				}
				elseif ($values['Data'] === 'Text') {
					$table_name = $values['Text'];
				}
				$table->getRow($row_number-1)->getColumnData('Data', $table_name);

				$form->query('xpath:(//button[@name="edit"])['.$row_number.']')->one()->click();
				$column_form = COverlayDialogElement::find()->asForm()->all()->last()->waitUntilReady();
				$form_header = $this->query('xpath://div[@class="overlay-dialogue modal modal-popup"]//h4')->one()->getText();
				$this->assertEquals('Update column', $form_header);

				// Check base color.
				if (array_key_exists('Base color', $values)) {
					foreach ($values['Base color'] as $selector => $color) {
						$this->assertEquals('#'.$color, $this->query($selector)->one()->getAttribute('title'));
					}

					unset($values['Base color']);
				}

				// Check Thresholds values.
				if (array_key_exists('Thresholds', $values)) {
					$threshold_amount = count($values['Thresholds'])-1;
					$threshold_order = 0;
					foreach ($values['Thresholds'] as $threshold) {
						$this->assertEquals($threshold['value'], $column_form->query('id:thresholds_'.$threshold_order.'_threshold')
								->one()->getAttribute('value'));

						// Check color in Thresholds.
						if (array_key_exists('color', $threshold)) {
							$color_hex = ($threshold['color'] !== '') ? '#'.$threshold['color'] : 'Use default';
							$this->assertEquals($color_hex, $column_form->query('id:lbl_thresholds_'.$threshold_order.'_color')
									->one()->getAttribute('title'));
						}

						// Id number of threshold.
						if ($threshold_order < $threshold_amount) {
							$threshold_order++;
						}
					}

					unset($values['Thresholds']);
				}

				$column_form->checkValue($values);
				$column_form->query('xpath:(//button[text()="Cancel"])[2]')->one()->click();

				// Check next row in a column table.
				if ($row_number < $row_amount) {
					$row_number++;
				}
			}
		}
	}

	private function checkDashboardUpdateMessage() {
		$message = CMessageElement::find()->waitUntilVisible()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Dashboard updated', $message->getTitle());
	}

	private function columnCreateUpdate($data, $action){
		// Starting counting column amount from 1 for xpath.
		if ($action === 'update') {
			$column_count = 1;
		}

		foreach ($data['column_fields'] as $values) {
			$form = $this->query('id:widget-dialogue-form')->one()->asForm();

			// What should be pressed - Add or Edit.
			$selector = ($action === 'create') ? 'id:add' : 'xpath:(//button[@name="edit"])['.$column_count.']';
			$form->query($selector)->one()->waitUntilVisible()->click();
			$column_form = COverlayDialogElement::find()->asForm()->all()->last()->waitUntilReady();

			// Fill Base color.
			if (array_key_exists('Base color', $values)) {
				foreach ($values['Base color'] as $selector => $color) {
					$column_form->query($selector)->one()->click();
					$this->query('xpath://div[@id="color_picker"]')->asColorPicker()->one()->fill($color);
				}

				unset($values['Base color']);
			}

			// Fill Thresholds values.
			if (array_key_exists('Thresholds', $values)) {
				$threshold_amount = count($values['Thresholds'])-1;
				$threshold_order = 0;

				foreach ($values['Thresholds'] as $threshold) {
					if ($action === 'create') {
						$column_form->query('button:Add')->one()->click();
					}

					$column_form->query('id:thresholds_'.$threshold_order.'_threshold')->one()->fill($threshold['value']);

					// Fill Threshold colors.
					if (array_key_exists('color', $threshold)) {
						$column_form->query('id:lbl_thresholds_'.$threshold_order.'_color')->one()->click();
						$this->query('xpath://div[@id="color_picker"]')->asColorPicker()->one()->fill($threshold['color']);
					}

					// Id number of threshold.
					if ($threshold_order < $threshold_amount) {
						$threshold_order++;
					}
				}

				unset($values['Thresholds']);
			}

			$column_form->fill($values);
			$column_form->submit();

			// updating top host several columns, change it order number.
			if ($action === 'update') {
				$column_count++;
			}

			// Check error message in column form.
			if (array_key_exists('column_error', $data)) {
				$message = CMessageElement::find()->waitUntilVisible()->one();
				$this->assertEquals($data['column_error'], $message->getLines()->asText());
				$selector = ($action === 'update') ? 'Update column' : 'New column';
				$column_form->query('xpath://div/h4[text()="'.$selector.'"]/../preceding-sibling::button[@title="Close"]')->one()->click();
			}

			sleep(1);
		}
	}
}

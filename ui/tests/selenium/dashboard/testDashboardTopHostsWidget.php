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

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../traits/TagTrait.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * @dataSource TopHostsWidget
 *
 * @backup widget, profiles, dashboard, items
 */
class testDashboardTopHostsWidget extends CWebTest {

	use TagTrait;

	/**
	 * Widget name for update.
	 */
	private static $updated_name = 'Top hosts update';

	/**
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	private $sql = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class
		];
	}

	/**
	 * Get threshold table element with mapping set.
	 *
	 * @return CMultifieldTable
	 */
	protected function getTreshholdTable() {
		return $this->query('id:thresholds_table')->asMultifieldTable([
			'mapping' => [
				'' => [
					'name' => 'color',
					'selector' => 'class:color-picker',
					'class' => 'CColorPickerElement'
				],
				'Threshold' => [
					'name' => 'threshold',
					'selector' => 'xpath:./input',
					'class' => 'CElement'
				]
			]
		])->waitUntilVisible()->one();
	}

	public static function getCreateData() {
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
			// #1 all fields filled for main form with all tags.
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
					'tags' => [
						['name' => 'value', 'value' => '12345', 'operator' => 'Contains'],
						['name' => '@#$%@', 'value' => 'a1b2c3d4', 'operator' => 'Equals'],
						['name' => 'AvF%21', 'operator' => 'Exists'],
						['name' => '_', 'operator' => 'Does not exist'],
						['name' => 'кириллица', 'value' => 'BcDa', 'operator' => 'Does not equal'],
						['name' => 'aaa6', 'value' => 'bbb6', 'operator' => 'Does not contain']
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
						'Name' => 'Different display and history data fields'
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
							'Base color' => '039BE5'
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
									'threshold' => '5'
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
									'threshold' => '1'
								],
								[
									'threshold' => '100'
								],
								[
									'threshold' => '1000'
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
									'threshold' => '1',
									'color' => 'FFEB3B'
								],
								[
									'threshold' => '100',
									'color' => 'AAAAAA'
								],
								[
									'threshold' => '1000',
									'color' => 'AAAAAA'
								],
								[
									'threshold' => '10000',
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
							'Base color' => '039BE5'
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
							'Base color' => '039BE5'
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
							'Base color' => '!@#$%^'
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
							'Base color' => '!@#$%^'
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
			// #20 error when 1M time shift added.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => '1M time shift'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Time shift' => '1M'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/timeshift": a time unit is expected.'
					]
				]
			],
			// #21 error when 1y time shift added.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => '1y time shift'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Time shift' => '1y'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/timeshift": a time unit is expected.'
					]
				]
			],
			// #22 error when incorrect aggregation interval added.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Incorrect aggregation interval'
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
			// #23 error when empty aggregation function added.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Empty aggregation interval'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'count',
							'Aggregation interval' => ''
						]
					],
					'column_error' => [
						'Invalid parameter "/1/aggregate_interval": cannot be empty.'
					]
				]
			],
			// #24 error when incorrect min value added.
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
			// #25 error when incorrect max value added.
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
			// #26 color error in item column.
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
							'Base color' => '!@#$%^'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/base_color": a hexadecimal color code (6 symbols) is expected.'
					]
				]
			],
			// #27 color error when incorrect hexadecimal added in first threshold.
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
									'threshold' => '1',
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
			// #28 color error when incorrect hexadecimal added in second threshold.
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
									'threshold' => '1',
									'color' => '4000FF'
								],
								[
									'threshold' => '2',
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
			// #29 error message when incorrect value added to threshold.
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
									'threshold' => 'zzz',
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
	 * Create Top Hosts widget.
	 *
	 * @dataProvider getCreateData
	 */
	public function testDashboardTopHostsWidget_Create($data) {
		$dashboardid = CDataHelper::get('TopHostsWidget.dashboardids.top_host_create');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();
		$form = $dashboard->edit()->addWidget()->asForm();
		$form->fill(['Type' => 'Top hosts']);
		COverlayDialogElement::find()->waitUntilReady()->one();

		// Add new column.
		if (array_key_exists('column_fields', $data)) {
			$this->fillColumnForm($data, 'create');
		}

		if (array_key_exists('tags', $data)) {
			$this->setTagSelector('id:tags_table_tags');
			$this->setTags($data['tags']);
		}

		$form->fill($data['main_fields']);
		$form->submit();
		$this->page->waitUntilReady();

		// Check error message in main widget form.
		if (array_key_exists('main_error', $data)) {
			$this->assertMessage(TEST_BAD, null, $data['main_error']);
		}

		if ($data['expected'] === TEST_GOOD) {
			// Make sure that the widget is present before saving the dashboard.
			$header = CTestArrayHelper::get($data['main_fields'], 'Name', 'Top hosts');
			$dashboard->getWidget($header);
			$dashboard->save();

			// Check message that widget added.
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check widget amount that it is added.
			$this->assertEquals($old_widget_count + 1, $dashboard->getWidgets()->count());
			$this->checkWidget($header, $data, 'create');
		}
		else {
			COverlayDialogElement::find()->one()->close();
			$dashboard->save();

			// Check message that widget added.
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
			$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());
		}
	}

	/**
	 * Top Hosts widget simple update without any field change.
	 */
	public function testDashboardTopHostsWidget_SimpleUpdate() {
		// Hash before simple update.
		$old_hash = CDBHelper::getHash($this->sql);

		$dashboardid = CDataHelper::get('TopHostsWidget.dashboardids.top_host_update');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit()->getWidget(self::$updated_name)->edit()->submit();
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Compare old hash and new one.
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	public static function getUpdateData() {
		return [
			// #0 incorrecct threshold color.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Thresholds' => [
								[
									'action' => USER_ACTION_UPDATE,
									'index' => 0,
									'threshold' => '100',
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
			// #1 incorrecct min value.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Indicators',
							'History data' => 'Trends',
							'Min' => 'zzz'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/min": a number is expected.'
					]
				]
			],
			// #2 incorrecct max value.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Indicators',
							'History data' => 'Trends',
							'Max' => 'zzz'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/max": a number is expected.'
					]
				]
			],
			// #3 incorrecct threshold value.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Thresholds' => [
								[
									'threshold' => '     '
								]
							]
						]
					],
					'column_error' => [
						'Invalid parameter "/1/thresholds/3/threshold": a number is expected.'
					]
				]
			],
			// #4 error message when update Host count incorrectly.
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
			// #5 error message when there is no item column.
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
			// #6 time shift error in column.
			[
				[
					'expected' => TEST_BAD,
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
			// #7 time shift error in column when add 1M.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Time shift' => '1M'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/timeshift": a time unit is expected.'
					]
				]
			],
			// #8 time shift error in column when add 1y.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Time shift' => '1y'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/timeshift": a time unit is expected.'
					]
				]
			],
			// #9 aggregation interval error in column.
			[
				[
					'expected' => TEST_BAD,
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
			// #10 empty aggregation interval error in column.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Aggregation function' => 'max',
							'Aggregation interval' => ''
						]
					],
					'column_error' => [
						'Invalid parameter "/1/aggregate_interval": cannot be empty.'
					]
				]
			],
			// #11 no item error in column.
			[
				[
					'expected' => TEST_BAD,
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
			// #12 incorrecct base color.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Base color' => '#$%$@@'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/base_color": a hexadecimal color code (6 symbols) is expected.'
					]
				]
			],
			// #13 update all main fields.
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
			// #14 update first item column to Text column and add some values.
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
							'Base color' => '039BE5'
						]
					]
				]
			],
			// #15 update first column to Host name column and add some values.
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
							'Base color' => 'FF8F00'
						]
					]
				]
			],
			// #16 update first column to Item column and check time suffix - seconds.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Time shift 10s'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Time shift' => '10s'
						]
					]
				]
			],
			// #17 time suffix "minutes" is checked in this case.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Time shift 10m'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Time shift' => '10m'
						]
					]
				]
			],
			// #18 time suffix "hours" is checked in this case.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Time shift 10h'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Time shift' => '10h'
						]
					]
				]
			],
			// #19 time suffix "weeks" is checked in this case.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Time shift 10w'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Time shift' => '10w'
						]
					]
				]
			],
			// #20 update item column adding new values and fields.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Updated values for item column'
					],
					'column_fields' => [
						[
							'Data' => 'Host name',
							'Name' => 'Only name changed'
						],
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Time shift' => '1',
							'Display' => 'Indicators',
							'History data' => 'Trends',
							'Min' => '50',
							'Max' => '100',
							'Aggregation function' => 'avg',
							'Aggregation interval' => '20h',
							'Base color' => '039BE5',
							'Thresholds' => [
								[
									'action' => USER_ACTION_UPDATE,
									'index' => 0,
									'threshold' => '1',
									'color' => 'FFEB3B'
								],
								[
									'action' => USER_ACTION_UPDATE,
									'index' => 1,
									'threshold' => '100',
									'color' => 'AAAAAA'
								]
							]
						]
					],
					'tags' => [
						['name' => 'value', 'value' => '12345', 'operator' => 'Contains'],
						['name' => '@#$%@', 'value' => 'a1b2c3d4', 'operator' => 'Equals'],
						['name' => 'AvF%21', 'operator' => 'Exists'],
						['name' => '_', 'operator' => 'Does not exist'],
						['name' => 'кириллица', 'value' => 'BcDa', 'operator' => 'Does not equal'],
						['name' => 'aaa6', 'value' => 'bbb6', 'operator' => 'Does not contain']
					]
				]
			]
		];
	}

	/**
	 * Update Top Hosts widget.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testDashboardTopHostsWidget_Update($data) {
		if ($data['expected'] === TEST_BAD) {
			// Hash before update.
			$old_hash = CDBHelper::getHash($this->sql);
		}

		$dashboardid = CDataHelper::get('TopHostsWidget.dashboardids.top_host_update');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->getWidget(self::$updated_name)->edit();

		// Update column.
		if (array_key_exists('column_fields', $data)) {
			$this->fillColumnForm($data, 'update');
		}

		if (array_key_exists('tags', $data)) {
			$this->setTagSelector('id:tags_table_tags');
			$this->setTags($data['tags']);
		}

		if (array_key_exists('main_fields', $data)) {
			$form->fill($data['main_fields']);
			$form->submit();
			$this->page->waitUntilReady();
		}

		// Check error message in main widget form.
		if (array_key_exists('main_error', $data)) {
			$this->assertMessage(TEST_BAD, null, $data['main_error']);
		}

		if ($data['expected'] === TEST_GOOD) {
			self::$updated_name = (array_key_exists('Name', $data['main_fields']))
					? $data['main_fields']['Name']
					: self::$updated_name;

			// Make sure that the widget is present before saving the dashboard.
			$header = CTestArrayHelper::get($data['main_fields'], 'Name', self::$updated_name);
			$dashboard->getWidget($header);
			$dashboard->save();

			// Check message that widget added.
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			$this->checkWidget(self::$updated_name, $data, 'update');
		}
		else {
			COverlayDialogElement::find()->one()->close();
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Compare old hash and new one.
			$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
		}
	}

	/**
	 * Delete top hosts widget.
	 */
	public function testDashboardTopHostsWidget_Delete() {
		$name = 'Top hosts delete';

		$dashboardid = CDataHelper::get('TopHostsWidget.dashboardids.top_host_delete');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one()->edit();
		$dashboard->deleteWidget($name);
		$this->page->waitUntilReady();
		$dashboard->save();

		// Check that Dashboard has been saved.
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Confirm that widget is not present on dashboard.
		$this->assertFalse($dashboard->getWidget($name, false)->isValid());

		// Check that widget is removed from DB.
		$widget_sql = 'SELECT * FROM widget_field wf LEFT JOIN widget w ON w.widgetid=wf.widgetid WHERE w.name='.zbx_dbstr($name);
		$this->assertEquals(0, CDBHelper::getCount($widget_sql));
	}

	public static function getRemoveData() {
		return [
			// #0 remove column.
			[
				[
					'table_id' => 'id:list_columns',
					'remove_selector' => 'xpath:(.//button[@name="remove"])[2]'
				]
			],
			// #1 remove tag.
			[
				[
					'table_id' => 'id:tags_table_tags',
					'remove_selector' => 'id:tags_0_remove'
				]
			],
			// #2 remove threshold.
			[
				[
					'table_id' => 'id:thresholds_table',
					'remove_selector' => 'id:thresholds_0_remove'
				]
			]
		];
	}

	/**
	 * Remove tag, column, threshold.
	 *
	 * @dataProvider getRemoveData
	 */
	public function testDashboardTopHostsWidget_Remove($data) {
		$dashboardid = CDataHelper::get('TopHostsWidget.dashboardids.top_host_remove');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->getWidget('Top hosts for remove')->edit();

		// Find and count tag/column/threshold before remove.
		if ($data['table_id'] !== 'id:thresholds_table') {
			$table = $form->query($data['table_id'])->one()->asTable();
			$amount_before = $table->getRows()->count();
			$table->query($data['remove_selector'])->one()->click();
		}
		else {
			$form->query('xpath:(.//button[@name="edit"])[1]')->one()->waitUntilVisible()->click();
			$column_form = COverlayDialogElement::find()->waitUntilReady()->asForm()->all()->last();
			$table = $column_form->query($data['table_id'])->one()->asTable();
			$amount_before = $table->getRows()->count();
			$table->query($data['remove_selector'])->one()->click();
			$column_form->submit();
		}

		// After remove column and threshold, form is reloaded.
		if ($data['table_id'] !== 'id:tags_table_tags') {
			$form->waitUntilReloaded()->submit();
		}
		else {
			$form->submit();
		}

		$dashboard->save();

		// Check that Dashboard has been saved.
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		$dashboard->edit()->getWidget('Top hosts for remove')->edit();

		if ($data['table_id'] === 'id:thresholds_table') {
			$form->query('xpath:(.//button[@name="edit"])[1]')->one()->waitUntilVisible()->click();
			$column_form = COverlayDialogElement::find()->waitUntilReady()->asForm()->all()->last();
			$table = $column_form->query($data['table_id'])->one()->asTable();
		}

		// Check that tag/column/threshold removed.
		$this->assertEquals($amount_before - 1, $table->getRows()->count());
	}

	/**
	 * Check widget after update/creation.
	 *
	 * @param string $header		widget name
	 * @param array	 $data			values from dataprovider
	 * @param string $action		check after creation or update
	 */
	private function checkWidget($header, $data, $action) {
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->getWidget($header)->edit();
		$form->checkValue($data['main_fields']);

		if (array_key_exists('tags', $data)) {
			$this->assertTags($data['tags']);
		}

		if (array_key_exists('column_fields', $data)) {
			// Count column amount from data provider.
			$column_amount = count($data['column_fields']);
			$table = $form->query('id:list_columns')->one()->asTable();

			// It is required to subtract 1 to ignore header row.
			$row_amount = $table->getRows()->count() - 1;

			if ($action === 'create') {
				// Count row amount from column table and compare with column amount from data provider.
				$this->assertEquals($column_amount, $row_amount);
			}

			// Check values from column form.
			$row_number = 1;
			foreach ($data['column_fields'] as $values) {
				// Check that column table has correct names for added columns.
				$table_name = (array_key_exists('Name', $values)) ? $values['Name'] : '';
				$table->getRow($row_number - 1)->getColumnData('Name', $table_name);

				// Check that column table has correct data.
				if ($values['Data'] === 'Item value') {
					$table_name = $values['Item'];
				}
				elseif ($values['Data'] === 'Host name') {
					$table_name = $values['Data'];
				}
				else {
					$table_name = $values['Text'];
				}
				$table->getRow($row_number - 1)->getColumnData('Data', $table_name);

				$form->query('xpath:(.//button[@name="edit"])['.$row_number.']')->one()->click();
				$column_form = COverlayDialogElement::find()->waitUntilReady()->asForm()->all()->last();
				$form_header = $this->query('xpath://div[@class="overlay-dialogue modal modal-popup"]//h4')->one()->getText();
				$this->assertEquals('Update column', $form_header);

				// Check Thresholds values.
				if (array_key_exists('Thresholds', $values)) {
					foreach($values['Thresholds'] as &$threshold) {
						unset($threshold['action'], $threshold['index']);
					}
					unset($threshold);

					$this->getTreshholdTable()->checkValue($values['Thresholds']);
					unset($values['Thresholds']);
				}

				$column_form->checkValue($values);
				$this->query('xpath:(//button[text()="Cancel"])[2]')->one()->click();

				// Check next row in a column table.
				if ($row_number < $row_amount) {
					$row_number++;
				}
			}
		}
	}

	/**
	 * Create or update top hosts widget.
	 *
	 * @param array  $data			values from dataprovider
	 * @param string $action		create or update action
	 */
	private function fillColumnForm($data, $action) {
		// Starting counting column amount from 1 for xpath.
		if ($action === 'update') {
			$column_count = 1;
		}

		$form = $this->query('id:widget-dialogue-form')->one()->asForm();
		foreach ($data['column_fields'] as $values) {
			// Open the Column configuration add or column update dialog depending on the action type.
			$selector = ($action === 'create') ? 'id:add' : 'xpath:(.//button[@name="edit"])['.$column_count.']';
			$form->query($selector)->waitUntilClickable()->one()->click();
			$column_form = COverlayDialogElement::find()->waitUntilReady()->asForm()->all()->last();

			// Fill Thresholds values.
			if (array_key_exists('Thresholds', $values)) {
				$this->getTreshholdTable()->fill($values['Thresholds']);
				unset($values['Thresholds']);
			}

			$column_form->fill($values);
			$column_form->submit();

			// Updating top host several columns, change it count number.
			if ($action === 'update') {
				$column_count++;
			}

			// Check error message in column form.
			if (array_key_exists('column_error', $data)) {
				$this->assertMessage(TEST_BAD, null, $data['column_error']);
				$selector = ($action === 'update') ? 'Update column' : 'New column';
				$this->query('xpath://div/h4[text()="'.$selector.'"]/../button[@title="Close"]')
						->one()->click();
			}

			$column_form->waitUntilNotVisible();
			COverlayDialogElement::find()->waitUntilReady()->one();
		}
	}

	public static function getBarScreenshotsData() {
		return [
			// #0 As is.
			[
				[
					'main_fields' =>  [
						'Name' => 'Simple'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => '1_item'
						]
					],
					'screen_name' => 'as_is'
				]
			],
			// #1 Bar.
			[
				[
					'main_fields' =>  [
						'Name' => 'Bar'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => '1_item',
							'Display' => 'Bar',
							'Min' => '0',
							'Max' => '2000',
							'Thresholds' => [
								[
									'threshold' => ''
								]
							]
						]
					],
					'screen_name' => 'bar'
				]
			],
			// #2 Bar with threshold.
			[
				[
					'main_fields' =>  [
						'Name' => 'Bar threshold'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => '1_item',
							'Display' => 'Bar',
							'Min' => '0',
							'Max' => '2000',
							'Thresholds' => [
								[
									'threshold' => '500'
								]
							]
						]
					],
					'screen_name' => 'bar_thre'
				]
			],
			// #3 Indicators.
			[
				[
					'main_fields' =>  [
						'Name' => 'Indicators'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => '1_item',
							'Display' => 'Indicators',
							'Min' => '0',
							'Max' => '2000',
							'Thresholds' => [
								[
									'threshold' => ''
								]
							]
						]
					],
					'screen_name' => 'indi'
				]
			],
			// #4 Indicators with threshold.
			[
				[
					'main_fields' =>  [
						'Name' => 'Indicators threshold'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => '1_item',
							'Display' => 'Indicators',
							'Min' => '500',
							'Max' => '2000',
							'Thresholds' => [
								[
									'threshold' => '1500'
								]
							]
						]
					],
					'screen_name' => 'indi_thre'
				]
			],
			// #5 All 5 types visually.
			[
				[
					'main_fields' =>   [
						'Name' => 'All three'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => '1_item'
						],
						[
							'Data' => 'Item value',
							'Item' => '1_item',
							'Display' => 'Indicators',
							'Min' => '500',
							'Max' => '2000',
							'Thresholds' => [
								[
									'threshold' => '1500'
								]
							]
						],
						[
							'Data' => 'Item value',
							'Item' => '1_item',
							'Display' => 'Indicators',
							'Min' => '0',
							'Max' => '2000',
							'Thresholds' => [
								[
									'threshold' => ''
								]
							]
						],
						[
							'Data' => 'Item value',
							'Item' => '1_item',
							'Display' => 'Bar',
							'Min' => '0',
							'Max' => '2000',
							'Thresholds' => [
								[
									'threshold' => '500'
								]
							]
						],
						[
							'Data' => 'Item value',
							'Item' => '1_item',
							'Display' => 'Bar',
							'Min' => '0',
							'Max' => '2000',
							'Thresholds' => [
								[
									'threshold' => ''
								]
							]
						]
					],
					'screen_name' => 'all_types'
				]
			]
		];
	}

	/**
	 * Check widget bars with screenshots.
	 *
	 * @dataProvider getBarScreenshotsData
	 */
	public function testDashboardTopHostsWidget_WidgetAppearance($data) {
		$this->createTopHostsWidget($data, 'top_host_screenshots');

		// Check widget added and assert screenshots.
		$element = CDashboardElement::find()->one()->getWidget($data['main_fields']['Name'])
				->query('class:list-table')->one();
		$this->assertScreenshot($element, $data['screen_name']);
	}

	/**
	 * Check warning and info messages.
	 */
	public function testDashboardTopHostsWidget_CheckInfoMessages() {
		$warnings = ['.//span[@id="tophosts-column-aggregate-function-warning"]', './/span[@id="tophosts-column-display-warning"]',
				'.//span[@id="tophosts-column-thresholds-warning"]'];
		$info = '//label[@for="history"]';
		$dashboardid = CDataHelper::get('TopHostsWidget.dashboardids.top_host_create');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		$form->fill(['Type' => 'Top hosts']);
		COverlayDialogElement::find()->waitUntilReady()->one();

		// Add column.
		$form->query('id:add')->waitUntilClickable()->one()->click();
		$column_form = COverlayDialogElement::find()->waitUntilReady()->asForm()->all()->last();

		// Check that no warning icon displayed before adding fields.
		foreach ($warnings as $warning) {
			$this->assertFalse($column_form->query('xpath:'.$warning)->one()->isVisible());
		}


		// Check that History data hint ID is visible.
		$this->assertTrue($column_form->query('xpath:'.$info.'/a')->one()->isVisible());

		// Adding those fields new info icons appear.
		$column_form->fill(['Aggregation function' => 'min', 'Display' => 'Bar']);
		$column_form->query('button:Add')->one()->click();
		$warnings = array_merge($warnings, [$info]);

		// Check warning and info icon message.
		foreach ($warnings as $warning) {
			$column_form->query('xpath:'.$warning.'/a')->one()->click();

			// Check hint-box.
			$hint = $column_form->query('xpath://div[@class="overlay-dialogue"]')->waitUntilPresent();
			$hintbox = ($warning === $info)
					? 'This setting applies only to numeric data. Non-numeric data will always be taken from history.'
					: 'With this setting only numeric items will be displayed in this column.';
			$this->assertEquals($hintbox, $hint->one()->getText());

			// Close the hint-box.
			$hint->query('xpath://button[@class="overlay-close-btn"]')->one()->click();
			$hint->waitUntilNotPresent();
		}
	}

	public static function getCheckTextItemsData() {
		return [
			// #0 text item - value displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Text value item'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'trap_text'
						]
					],
					'text' => 'Text for text item'
				]
			],
			// #1 text item, history data Trends - value displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Text trends history'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'trap_text',
							'History data' => 'Trends'
						]
					],
					'text' => 'Text for text item'
				]
			],
			// #2 text item, display Bar - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Text display bar'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'trap_text',
							'Display' => 'Bar'
						]
					]
				]
			],
			// #3 text item, display Indicators - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Text display indicators'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'trap_text',
							'Display' => 'Indicators'
						]
					]
				]
			],
			// #4 text item, Aggregation function max - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Text aggregation function'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'trap_text',
							'Aggregation function' => 'max'
						]
					]
				]
			],
			// #5 text item, Threshold - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Text threshold'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'trap_text',
							'Thresholds' => [
								[
									'threshold' => '10'
								]
							]
						]
					]
				]
			],
			// #6 log item - value displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Log value item'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'trap_log'
						]
					],
					'text' => 'Logs for text item'
				]
			],
			// #7 log item, history data Trends - value displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Log trends history'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'trap_log',
							'History data' => 'Trends'
						]
					],
					'text' => 'Logs for text item'
				]
			],
			// #8 log item, display Bar - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Log display bar'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'trap_log',
							'Display' => 'Bar'
						]
					]
				]
			],
			// #9 log item, display Indicators - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Log display indicators'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'trap_log',
							'Display' => 'Indicators'
						]
					]
				]
			],
			// #10 log item, Aggregation function max - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Log aggregation function'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'trap_log',
							'Aggregation function' => 'max'
						]
					]
				]
			],
			// #11 log item, Threshold - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Log threshold'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'trap_log',
							'Thresholds' => [
								[
									'threshold' => '10'
								]
							]
						]
					]
				]
			],
			// #12 char item - value displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Char value item'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'trap_char'
						]
					],
					'text' => 'characters_here'
				]
			],
			// #13 char item, history data Trends - value displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Char trends history'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'trap_char',
							'History data' => 'Trends'
						]
					],
					'text' => 'characters_here'
				]
			],
			// #14 char item, display Bar - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Char display bar'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'trap_char',
							'Display' => 'Bar'
						]
					]
				]
			],
			// #15 char item, display Indicators - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Char display indicators'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'trap_char',
							'Display' => 'Indicators'
						]
					]
				]
			],
			// #16 char item, Aggregation function max - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Char aggregation function'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'trap_char',
							'Aggregation function' => 'max'
						]
					]
				]
			],
			// #17 char item, Threshold - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Char threshold'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'trap_char',
							'Thresholds' => [
								[
									'threshold' => '10'
								]
							]
						]
					]
				]
			]
		];
	}

	/**
	 * Changing column parameters, check that text value is visible/non-visible.
	 *
	 * @dataProvider getCheckTextItemsData
	 */
	public function testDashboardTopHostsWidget_CheckTextItems($data) {
		$this->createTopHostsWidget($data, 'top_host_text_items');

		// Check if value displayed in column table.
		$value = (array_key_exists('text', $data)) ? $data['text'] : 'No data found.';
		$this->assertEquals($value, CDashboardElement::find()->one()->getWidget($data['main_fields']['Name'])
				->getContent()->getText());
	}

	/**
	 * Function used to create Top Hosts widget with special columns for CheckTextItems and WidgetAppearance scenarios.
	 *
	 * @param type $data	dataprovider values.
	 * @param type $name	name of the dashboard where to create Top Hosts widget.
	 */
	private function createTopHostsWidget($data, $name) {
		$dashboardid = CDataHelper::get('TopHostsWidget.dashboardids.'.$name);
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		$form->fill(['Type' => 'Top hosts']);
		COverlayDialogElement::find()->waitUntilReady()->one();

		// Add new column and save widget.
		$this->fillColumnForm($data, 'create');
		$form->fill($data['main_fields'])->submit();
		COverlayDialogElement::ensureNotPresent();
		$dashboard->getWidget($data['main_fields']['Name'])->waitUntilReady();
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
	}
}

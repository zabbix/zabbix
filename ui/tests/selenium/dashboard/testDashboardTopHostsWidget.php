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
			]
		]);

		$this->assertArrayHasKey('dashboardids', $response);
		self::$updateid = $response['dashboardids'][0];
		self::$createid = $response['dashboardids'][1];

		self::$create_pageid = CDBHelper::getValue('SELECT dashboard_pageid FROM dashboard_page WHERE dashboardid='
				.zbx_dbstr(self::$createid));
		self::$update_pageid = CDBHelper::getValue('SELECT dashboard_pageid FROM dashboard_page WHERE dashboardid='
				.zbx_dbstr(self::$updateid));
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
							'History data' => 'History'
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
			foreach ($data['column_fields'] as $values) {
				$form->query('id:add')->one()->waitUntilVisible()->click();
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
						$column_form->query('button:Add')->one()->click();
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
				sleep(1);
			}
		}

//		sleep(1);
		$form->fill($data['main_fields']);
		COverlayDialogElement::find()->waitUntilReady()->one();

		$form->submit();
		$this->page->waitUntilReady();

		// Make sure that the widget is present before saving the dashboard.
		$header = CTestArrayHelper::get($data['main_fields'], 'Name', 'Top hosts');
		$dashboard->getWidget($header);
		$dashboard->save();

		$this->checkDashboardMessage();
		$this->assertEquals($old_widget_count + 1, $dashboard->getWidgets()->count());
		$this->checkWidget($header, $data);
	}

	/*
	 * Check dashboard update message.
	 */
	private function checkDashboardMessage() {
		$message = CMessageElement::find()->waitUntilVisible()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Dashboard updated', $message->getTitle());
	}

	private function checkWidget($header, $data) {
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->getWidget($header)->edit();
		$form->checkValue($data['main_fields']);

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

			// Check base color
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




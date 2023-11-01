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
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup hosts, widget, profiles
 *
 * @onBefore prepareData
 */
class testDashboardPieChartWidget extends CWebTest
{
	protected static $dashboardid;
	protected static $screenshot_host_id;
	protected static $screenshot_host_item_ids;
	protected const TYPE_ITEM_PATTERN = 'Item pattern';
	protected const TYPE_ITEM_LIST = 'Item list';
	protected const HOST_NAME_ITEM_LIST = 'Host for Pie charts';
	protected const HOST_NAME_SCREENSHOTS = 'Host for Pie chart screenshots';


	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * Create the needed initial data in database and set static variables.
	 */
	public function prepareData() {
		// Set Pie chart as the default widget type.
		DBexecute('DELETE FROM profiles WHERE idx=\'web.dashboard.last_widget_type\' AND userid=\'1\'');
		DBexecute('INSERT INTO profiles (profileid, userid, idx, value_str, type)'.
				' VALUES (99999,1,\'web.dashboard.last_widget_type\',\'piechart\',3)');

		// Create a Dashboard for creating widgets.
		$dashboards = CDataHelper::call('dashboard.create', [
			'name' => 'Pie chart dashboard',
			'auto_start' => 0,
			'pages' => [['name' => 'Pie chart test page']]
		]);
		self::$dashboardid = $dashboards['dashboardids'][0];

		// Create a host for Pie chart testing.
		$response = CDataHelper::createHosts([
			[
				'host' => self::HOST_NAME_ITEM_LIST,
				'groups' => [['groupid' => '6']]
			],
			[
				'host' => self::HOST_NAME_SCREENSHOTS,
				'groups' => [['groupid' => '6']]
			]
		]);
		$host_id = $response['hostids'][self::HOST_NAME_ITEM_LIST];
		CDataHelper::call('item.create', [
			[
				'hostid' => $host_id,
				'name' => 'item-1',
				'key_' => 'key-1',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			[
				'hostid' => $host_id,
				'name' => 'item-2',
				'key_' => 'key-2',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			]
		]);

		self::$screenshot_host_id = $response['hostids'][self::HOST_NAME_SCREENSHOTS];
		$response = CDataHelper::call('item.create', [
			[
				'hostid' => self::$screenshot_host_id,
				'name' => 'item-1',
				'key_' => 'key-1',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			[
				'hostid' => self::$screenshot_host_id,
				'name' => 'item-2',
				'key_' => 'key-2',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			[
				'hostid' => self::$screenshot_host_id,
				'name' => 'item-3',
				'key_' => 'key-3',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			[
				'hostid' => self::$screenshot_host_id,
				'name' => 'item-4',
				'key_' => 'key-4',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			]
		]);
		self::$screenshot_host_item_ids = [];
		foreach([0, 1, 2, 3] as $id) {
			self::$screenshot_host_item_ids['item-'.($id + 1)] = intval($response['itemids'][$id]);
		}
	}

	public function getLayoutData() {
		return [
			[
				[
					'action' => 'create',
					'header_text' => 'Add widget',
					'primary_button_text' => 'Add'
				]
			],
			[
				[
					'action' => 'edit',
					'header_text' => 'Edit widget',
					'primary_button_text' => 'Apply'
				]
			],
		];
	}

	/**
	 * Test the elements and layout of the Pie chart create and edit form.
	 *
	 * @dataProvider getLayoutData
	 */
	public function testDashboardPieChartWidget_Layout($data) {
		// Open the correct form.
		if ($data['action'] === 'create') {
			$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
			$dashboard = CDashboardElement::find()->one();
			$form = $dashboard->edit()->addWidget()->asForm();
		}
		else if ($data['action'] === 'edit') {
			$widget_name = 'Layout widget';
			$this->createCleanWidget($widget_name);
			$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
			$dashboard = CDashboardElement::find()->one();
			$form = $dashboard->edit()->getWidget($widget_name)->edit();
		}

		$dialog = COverlayDialogElement::find()->one();
		$this->assertEquals($data['header_text'], $dialog->getTitle());

		// Check Help button.
		$help_button = $dialog->query('xpath:.//*[@title="Help"]')->one();
		$this->assertTrue($help_button->isClickable());
		$version = substr(ZABBIX_VERSION, 0, 3);
		$this->assertEquals(
				'https://www.zabbix.com/documentation/'.$version.'/en/manual/web_interface/frontend_sections/dashboards/widgets/pie_chart',
				$help_button->getAttribute('href')
		);

		// Check Close button.
		$this->assertTrue($dialog->query('xpath:.//button[@title="Close"]')->one()->isClickable());

		// Check main (generic) fields.
		$this->assertLabels(['Type', 'Name', 'Refresh interval', 'Show header'], $form);

		foreach(['id:type', 'id:name', 'id:rf_rate', 'id:show_header'] as $selector) {
			$input = $form->query($selector)->one();

			if ($selector === 'id:show_header') {
				// Checkboxes are hidden.
				$this->assertTrue($input->isEnabled());
				$this->assertTrue($input->asCheckbox()->isChecked());
			}
			else {
				$this->assertTrue($input->isClickable());
			}
		}

		// Check tabs.
		$this->assertEquals(['Data set', 'Displaying options', 'Time period', 'Legend'], $form->getTabs());

		// Check Item pattern.
		$this->assertLabels(['Data set #1', 'Aggregation function', 'Data set aggregation', 'Data set label'], $form);
		$this->assertTrue($form->query('xpath:.//li[@data-set="0"]//button[@title="Delete"]')->one()->isClickable());

		foreach(['id:ds_0_hosts_',
				'id:ds_0_items_',
				'name:ds[0][aggregate_function]',
				'name:ds[0][dataset_aggregation]',
				'name:ds[0][data_set_label]'] as $selector) {
			$this->assertTrue($form->query($selector)->one()->isClickable());
		}

		$hints = [
			'label-ds_{id}_aggregate_function' => 'Aggregates each item in the data set.',
			'label-ds_{id}_dataset_aggregation' => 'Aggregates the whole data set.',
			'ds_{id}_data_set_label' => 'Also used as legend label for aggregated data sets.',
		];

		foreach ($hints as $selector => $expected_hint) {
			$selector = str_replace('{id}', '0', $selector);
			$this->assertEquals($expected_hint, $this->query('xpath://label[@for='.CXPathHelper::escapeQuotes($selector).
					']/button')->one()->getAttribute('data-hintbox-contents'));
		}

		// Screenshot Item pattern.
		$this->screenshotLayout($dialog, 'piechart_item_pattern', $data['action']);

		// Check Add data set buttons.
		foreach (['id:dataset-add', 'id:dataset-menu'] as $selector) {
			$this->assertTrue($form->query($selector)->one()->isClickable());
		}

		// Check Item list.
		$this->addNewDataSet(self::TYPE_ITEM_LIST, $form);
		$this->page->waitUntilReady();
		$form->invalidate();

		foreach (['data-indicator' => 'count', 'data-indicator-value' => 2] as $attribute => $expected_value) {
			$this->assertEquals($expected_value, $form->query('id:tab_data_set')->one()->getAttribute($attribute));
		}

		$this->assertLabels(['Data set #2', 'Aggregation function', 'Data set aggregation', 'Data set label'], $form);

		foreach(['name:ds[1][aggregate_function]',
					'name:ds[1][dataset_aggregation]',
					'name:ds[1][data_set_label]'] as $selector) {
			$this->assertTrue($form->query($selector)->one()->isClickable());
		}

		foreach ($hints as $selector => $expected_hint) {
			$selector = str_replace('{id}', '1', $selector);
			$this->assertEquals($expected_hint, $this->query('xpath://label[@for='.CXPathHelper::escapeQuotes($selector).
					']/button')->one()->getAttribute('data-hintbox-contents'));
		}

		// Screenshot Item list.
		$this->screenshotLayout($dialog, 'piechart_item_list', $data['action']);

		// Displaying options tab.
		$form->selectTab('Displaying options');
		$this->page->waitUntilReady();
		$form->invalidate();
		$this->assertLabels(['History data selection', 'Draw', 'Space between sectors', 'Merge sectors smaller than '], $form);

		foreach (['Auto', 'History', 'Trends'] as $label) {
			$this->assertTrue($form->getField('History data selection')->
					query('xpath:.//label[text()='.CXPathHelper::escapeQuotes($label).']')->one()->isClickable());
		}

		foreach (['Pie', 'Doughnut'] as $label) {
			$this->assertTrue($form->getField('Draw')->
					query('xpath:.//label[text()='.CXPathHelper::escapeQuotes($label).']')->one()->isClickable());
		}

		$this->assertRangeLayout('Space between sectors', 'space', $form, ['min' => '0', 'max' => '10', 'step' => '1', 'value' => '1']);

		foreach (['merge' => true, 'merge_percent' => false, 'merge_color' => false] as $id => $enabled) {
			$this->assertTrue($form->query('id', $id)->one()->isEnabled($enabled));
		}

		$form->fill(['id:merge' => true]);
		$form->invalidate();
		foreach (['data-indicator' => 'mark', 'data-indicator-value' => 1] as $attribute => $expected_value) {
			$this->assertEquals($expected_value, $form->query('id:tab_displaying_options')->one()->getAttribute($attribute));
		}

		foreach (['merge_percent', 'merge_color'] as $id) {
			$this->assertTrue($form->query('id', $id)->one()->isEnabled());
		}

		$form->fill(['Draw' => 'Doughnut']);
		$this->page->waitUntilReady();
		$form->invalidate();

		foreach(['Size', 'Decimal places', 'Units', 'Bold', 'Colour'] as $label) {
			$this->assertTrue($form->getField($label)->isEnabled(false));
		}

		$form->fill(['Show total value' => true]);

		foreach(['Size', 'Decimal places', 'Bold', 'Colour'] as $label) {
			$this->assertTrue($form->getField($label)->isEnabled());
		}

		// Screenshot Displaying options.
		$this->screenshotLayout($dialog, 'piechart_displaying_options', $data['action']);

		// Time period tab.
		$form->selectTab('Time period');
		$this->page->waitUntilReady();
		$form->invalidate();

		foreach (['Dashboard', 'Widget', 'Custom'] as $label) {
			$this->assertTrue($form->getField('Time period')->
					query('xpath:.//label[text()='.CXPathHelper::escapeQuotes($label).']')->one()->isClickable());
		}

		// Screenshot Time period.
		$this->screenshotLayout($dialog, 'piechart_time_period', $data['action']);

		// Legend tab.
		$form->selectTab('Legend');
		$this->page->waitUntilReady();
		$form->invalidate();
		$this->assertLabels(['Show legend', 'Show aggregation function', 'Number of rows', 'Number of columns'], $form);
		$this->assertTrue($form->getField('Show legend')->isEnabled());
		$this->assertTrue($form->getField('Show legend')->isChecked());
		$this->assertTrue($form->getField('Show aggregation function')->isEnabled());
		$this->assertTrue($form->getField('Show aggregation function')->isChecked(false));

		$this->assertRangeLayout('Number of rows', 'legend_lines', $form, ['min' => '1', 'max' => '10', 'step' => '1', 'value' => '1']);
		$this->assertRangeLayout('Number of columns', 'legend_columns', $form, ['min' => '1', 'max' => '4', 'step' => '1', 'value' => '4']);

		// Screenshot Legend.
		$this->screenshotLayout($dialog, 'piechart_legend', $data['action']);

		// Check footer buttons.
		$footer = $dialog->query('class:overlay-dialogue-footer')->one();

		foreach([$data['primary_button_text'], 'Cancel'] as $button) {
			$this->assertTrue($footer->query('button', $button)->one()->isClickable());
		}
	}

	public function getCreateData() {
		return [
			// Mandatory fields only.
			[
				[
					'fields' => []
				]
			],
			// Mandatory fields only - Data set type Item list.
			[
				[
					'fields' => [
						'Data set' => [
							'type' => self::TYPE_ITEM_LIST,
							'host' => self::HOST_NAME_ITEM_LIST,
							'items' => [
								['name' =>'item-1']
							]
						]
					]
				]
			],
			// Largest number of fields possible. Data set aggregation has to be 'none' because of Total type item.
			[
				[
					'fields' => [
						'main_fields' => [
							'Name' => 'Test all possible fields',
							'Show header' => false,
							'Refresh interval' => '30 seconds'
						],
						'Data set' => [
							[
								'host' => ['Host*', 'one', 'two'],
								'item' => ['Item*', 'one', 'two'],
								'color' => '00BCD4',
								'Aggregation function' => 'min',
								'Data set aggregation' => 'none',
								'Data set label' => 'Label 1'
							],
							[
								'type' => self::TYPE_ITEM_LIST,
								'host' => self::HOST_NAME_ITEM_LIST,
								'Aggregation function' => 'max',
								'Data set aggregation' => 'none',
								'Data set label' => 'Label 2',
								'items' => [
									[
										'name' => 'item-1',
										'il_color' => '000000',
										'il_type' => 'Total'
									],
									[
										'name' => 'item-2'
									]
								]
							]
						],
						'Displaying options' => [
							'History data selection' => 'History',
							'Draw' => 'Doughnut',
							'Width' => '40',
							'Space between sectors' => '2',
							'id:merge' => true,
							'id:merge_percent' => '10',
							'Show total value' => true,
							'id:value_size_type' => 'Custom',
							'id:value_size_custom_input' => '25',
							'Decimal places' => '1',
							'id:units_show' => true,
							'id:units' => 'GG',
							'Bold' => true,
							'Colour' => '4FC3F7'
						],
						'Time period' => [
							'Time period' => 'Custom',
							'From' => 'now-3h',
							'To' => 'now-2h'
						],
						'Legend' => [
							'Show legend' => true,
							'Show aggregation function' => true,
							'Number of rows' => 2,
							'Number of columns' => 3
						]
					]
				]
			],
			// Several data sets.
			[
				[
					'fields' => [
						'Data set' => [[], [], [], [], []]
					]
				]
			],
			// Missing Data set.
			[
				[
					'fields' => [
						'delete_data_set' => true
					],
					'result' => TEST_BAD,
					'error' => 'Invalid parameter "Data set": cannot be empty.'
				]
			],
			// Missing Host pattern.
			[
				[
					'fields' => [
						'remake_data_set' => true,
						'Data set' => ['item' => '*']
					],
					'result' => TEST_BAD,
					'error' => 'Invalid parameter "Data set/1/hosts": cannot be empty.'
				]
			],
			// Missing Item pattern.
			[
				[
					'fields' => [
						'remake_data_set' => true,
						'Data set' => ['host' => '*']
					],
					'result' => TEST_BAD,
					'error' => 'Invalid parameter "Data set/1/items": cannot be empty.'
				]
			],
			// Missing Item list.
			[
				[
					'fields' => [
						'Data set' => [
							'type' => self::TYPE_ITEM_LIST
							]
					],
					'result' => TEST_BAD,
					'error' => 'Invalid parameter "Data set/1/itemids": cannot be empty.'
				]
			],
			// Unicode values.
			[
				[
					'fields' => [
						'main_fields' => [
							'Name' => '🙂🙃 &nbsp; <script>alert("hi!");</script>'
						],
						'Data set' => [
							'host' => '&nbsp; <script>alert("host");</script>',
							'item' => '&nbsp; <script>alert("item");</script>',
							'Data set label' => '🙂🙃 &nbsp; <script>alert("hi!");</script>'
						],
						'Displaying options' => [
							'Draw' => 'Doughnut',
							'Show total value' => true,
							'id:units_show' => true,
							'id:units' => '🙂🙃 &nbsp; <script>alert("hi!");</script>'
						]
					]
				]
			],
			// Merge sector % value too small.
			[
				[
					'fields' => [
						'Displaying options' => [
							'id:merge' => true,
							'id:merge_percent' => '0'
						]
					],
					'result' => TEST_BAD,
					'error' => 'Invalid parameter "merge_percent": value must be one of 1-10.'
				]
			],
			// Merge sector % value too big.
			[
				[
					'fields' => [
						'Displaying options' => [
							'id:merge' => true,
							'id:merge_percent' => '11'
						]
					],
					'result' => TEST_BAD,
					'error' => 'Invalid parameter "merge_percent": value must be one of 1-10.'
				]
			],
			// Decimal places value too big.
			[
				[
					'fields' => [
						'Displaying options' => [
							'Draw' => 'Doughnut',
							'Show total value' => true,
							'Decimal places' => '7'
						]
					],
					'result' => TEST_BAD,
					'error' => 'Invalid parameter "Decimal places": value must be one of 0-6.'
				]
			],
			// Empty Time period (Widget).
			[
				[
					'fields' => [
						'Time period' => [
							'Time period' => 'Widget'
						]
					],
					'result' => TEST_BAD,
					'error' => 'Invalid parameter "Time period/Widget": cannot be empty.'
				]
			],
			// Empty Time period (Custom).
			[
				[
					'fields' => [
						'Time period' => [
							'Time period' => 'Custom',
							'From' => '',
							'To' => ''
						]
					],
					'result' => TEST_BAD,
					'error' => [
						'Invalid parameter "Time period/From": cannot be empty.',
						'Invalid parameter "Time period/To": cannot be empty.'
					]
				]
			],
			// Invalid Time period (Custom).
			[
				[
					'fields' => [
						'Time period' => [
							'Time period' => 'Custom',
							'From' => '0',
							'To' => '2020-13-32'
						]
					],
					'result' => TEST_BAD,
					'error' => [
						'Invalid parameter "Time period/From": a time is expected.',
						'Invalid parameter "Time period/To": a time is expected.'
					]
				]
			],
			// Different Time period formats.
			[
				[
					'fields' => [
						'Time period' => [
							'Time period' => 'Custom',
							'From' => '2020',
							'To' => '2020-10-10 00:00:00'
						]
					]
				]
			]
		];
	}

	/**
	 * Test creation of Pie chart.
	 *
	 * @dataProvider getCreateData
	 */
	public function testDashboardPieChartWidget_Create($data){
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();

		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();
		$form = $dashboard->edit()->addWidget()->asForm();

		// Fill data and submit.
		$this->fillForm($data['fields'], $form);
		$form->submit();

		$this->assertEditFormAfterSave($data);

		// Check total Widget count.
		$this->assertEquals(
			$old_widget_count + (int) $this->isTestGood($data),
			$dashboard->getWidgets()->count()
		);
	}

	/**
	 * Test updating of Pie chart.
	 *
	 * @dataProvider getCreateData
	 */
	public function testDashboardPieChartWidget_Update($data){
		// Create a Pie chart widget for editing.
		$widget_name = 'Edit widget';
		$this->createCleanWidget($widget_name);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();

		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();
		$form = $dashboard->edit()->getWidget($widget_name)->edit();

		// Fill data and submit.
		$this->fillForm($data['fields'], $form);
		$form->submit();

		// Assert result.
		$this->assertEditFormAfterSave($data);

		// Check total Widget count.
		$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());
	}

	public function getPieChartDisplayData() {
		return [
			// No data.
			[
				[
					'widget_fields' => [
						['name' => 'ds.0.hosts.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => self::HOST_NAME_SCREENSHOTS],
						['name' => 'ds.0.items.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'item-1']
					],
					'item_data' => [],
					'screenshot_id' => 'no_data'
				]
			],
			// One item, custom data set name.
			[
				[
					'widget_fields' => [
						['name' => 'ds.0.hosts.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => self::HOST_NAME_SCREENSHOTS],
						['name' => 'ds.0.items.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'item-1'],
						['name' => 'ds.0.data_set_label', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'TEST SET ☺'],
						['name' => 'ds.0.dataset_aggregation', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 2]
					],
					'item_data' => [
						'item-1' => [
							['value' => 99]
						]
					],
					'expected_dataset_name' => 'TEST SET ☺',
					'expected_sectors' => [
						'item-1' => ['value' => '99', 'color' => 'rgb(255, 70, 92)']
					],
					'screenshot_id' => 'one_item'
				]
			],
			// Two items, data set aggregate function, very small item values.
			[
				[
					'widget_name' => '2 items',
					'widget_fields' => [
						['name' => 'ds.0.hosts.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => self::HOST_NAME_SCREENSHOTS],
						['name' => 'ds.0.items.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'item-3'],
						['name' => 'ds.0.items.1', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'item-4'],
						['name' => 'ds.0.aggregate_function', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 2],
						['name' => 'legend_aggregation', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1]
					],
					'item_data' => [
						'item-3' => [
							['value' => 0.00000000000000004]
						],
						'item-4' => [
							['value' => 0.00000000000000001]
						]
					],
					'expected_legend_function' => 'max',
					'expected_sectors' => [
						'item-3' => ['value' => '4E-17', 'color' => 'rgb(255, 70, 92)'],
						'item-4' => ['value' => '1E-17', 'color' => 'rgb(255, 197, 219)']
					],
					'screenshot_id' => 'two_items'
				]
			],
			// Four items, host and item pattern, mixed value types, custom color, hide legend and header.
			[
				[
					'view_mode' => 1,
					'widget_fields' => [
						['name' => 'ds.0.hosts.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'Host for Pie chart screen*'],
						['name' => 'ds.0.items.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'item-*'],
						['name' => 'ds.0.color', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'FFA726'],
						['name' => 'legend', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 0]
					],
					'item_data' => [
						'item-1' => [
							['value' => 1]
						],
						'item-2' => [
							['value' => 2]
						],
						'item-3' => [
							['value' => 3.0]
						],
						'item-4' => [
							['value' => 4.4]
						]
					],
					'expected_sectors' => [
						'item-1' => ['value' => '1', 'color' => 'rgb(191, 103, 0)'],
						'item-2' => ['value' => '2', 'color' => 'rgb(255, 167, 38)'],
						'item-3' => ['value' => '3', 'color' => 'rgb(255, 230, 101)'],
						'item-4' => ['value' => '4.4', 'color' => 'rgb(255, 255, 165)']
					],
					'screenshot_id' => 'four_items'
				]
			],
			// Data set type Item list, Total item, merging enabled, doughnut with total value, custom legend display.
			[
				[
					'widget_fields' => [
						// Items and their colors.
						['name' => 'ds.0.dataset_type', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 0],
						['name' => 'ds.0.itemids.0', 'type' => ZBX_WIDGET_FIELD_TYPE_ITEM, 'value' => 'item-1'],
						['name' => 'ds.0.type.0', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'ds.0.color.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'FFEBEE'],
						['name' => 'ds.0.itemids.1', 'type' => ZBX_WIDGET_FIELD_TYPE_ITEM, 'value' => 'item-2'],
						['name' => 'ds.0.type.1', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 0],
						['name' => 'ds.0.color.1', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'E53935'],
						['name' => 'ds.0.itemids.2', 'type' => ZBX_WIDGET_FIELD_TYPE_ITEM, 'value' => 'item-3'],
						['name' => 'ds.0.type.2', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 0],
						['name' => 'ds.0.color.2', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => '546E7A'],
						['name' => 'ds.0.itemids.3', 'type' => ZBX_WIDGET_FIELD_TYPE_ITEM, 'value' => 'item-4'],
						['name' => 'ds.0.type.3', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 0],
						['name' => 'ds.0.color.3', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => '546EAA'],
						// Drawing and total value options.
						['name' => 'draw_type', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'width', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 30],
						['name' => 'total_show', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'value_size_type', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'value_size', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 30],
						['name' => 'decimal_places', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 0],
						['name' => 'units_show', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'units', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => '♥'],
						['name' => 'value_bold', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'value_color', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => '7CB342'],
						['name' => 'space', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 2],
						['name' => 'merge', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'merge_percent', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 10],
						['name' => 'merge_color', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'B71C1C'],
						['name' => 'legend_lines', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 2],
						['name' => 'legend_columns', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 2]
					],
					'item_data' => [
						'item-1' => [
							['value' => 100]
						],
						'item-2' => [
							['value' => 82]
						],
						'item-3' => [
							['value' => 4]
						],
						'item-4' => [
							['value' => 5]
						]
					],
					'expected_total' => '100 ♥',
					'expected_sectors' => [
						'item-1' => ['value' => '100 ♥', 'color' => 'rgb(255, 235, 238)'],
						'item-2' => ['value' => '82 ♥', 'color' => 'rgb(229, 57, 53)'],
						'Other' => ['value' => '9 ♥', 'color' => 'rgb(183, 28, 28)']
					],
					'screenshot_id' => 'doughnut'
				]
			],
		];
	}

	/**
	 * Generate different Pie charts and assert the display.
	 *
	 * @dataProvider getPieChartDisplayData
	 */
	public function testDashboardPieChartWidget_PieChartDisplay($data) {
		// Delete item history data in DB.
		foreach ([1, 2, 3, 4] as $id) {
			CDataHelper::removeItemData(self::$screenshot_host_item_ids['item-'.$id]);
		}

		// Set the new item history data.
		foreach($data['item_data'] as $item_key => $item_data) {
			// One item may have more than one history record.
			foreach ($item_data as $record) {
				// Always minus 10 seconds for safety.
				$time = time() - 10 + CTestArrayHelper::get($record, 'time');
				CDataHelper::addItemData(self::$screenshot_host_item_ids[$item_key], $record['value'], $time);
			}
		}

		// Fill in the actual Item ids into the data set data (this only applies to Item list data sets).
		foreach ($data['widget_fields'] as $id => $field) {
			if (preg_match('/^ds\.[0-9]\.itemids\.[0-9]$/', $field['name'])) {
				$field['value'] = self::$screenshot_host_item_ids[$field['value']];
				$data['widget_fields'][$id] = $field;
			}
		}

		// Recreate the dashboard and create the widget.
		CDataHelper::call('dashboard.update',
			[
				'dashboardid' => self::$dashboardid,
				'pages' => [
					[
						'widgets' => [
							[
								'name' => CTestArrayHelper::get($data, 'widget_name', ''),
								'view_mode' => CTestArrayHelper::get($data, 'view_mode', 0),
								'type' => 'piechart',
								'width' => 12,
								'height' => 8,
								'fields' => $data['widget_fields']
							]
						]
					]
				]
			]
		);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidgets()->first();
		$sectors = $widget->query('class:svg-pie-chart-arc')->waitUntilReady()->all()->asArray();

		// Assert expected sectors.
		foreach (CTestArrayHelper::get($data, 'expected_sectors', []) as $item_name => $expected_sector) {
			// The name that should be shown in the legend and in the hintbox.
			$legend_name = self::HOST_NAME_SCREENSHOTS.': '.$item_name;

			// Special case - legend name includes aggregation function.
			if (CTestArrayHelper::get($data, 'expected_legend_function')) {
				$legend_name = CTestArrayHelper::get($data, 'expected_legend_function').'('.$legend_name.')';
			}

			// Special case - legend name set in UI.
			if (CTestArrayHelper::get($data, 'expected_dataset_name')) {
				$legend_name = $data['expected_dataset_name'];
			}

			// Special case - legend name is "Other" because sectors were merged.
			if ($item_name === 'Other') {
				$legend_name = 'Other: ';
			}

			// Check if any of the sectors matches the expected sector.
			foreach ($sectors as $sector) {
				$hintbox_html = $sector->getAttribute('data-hintbox-contents');

				// Find matching sector by inspecting the 'data-hintbox-contents'.
				if (strpos($hintbox_html, $legend_name)) {
					// Assert hintbox value.
					$matches = [];
					preg_match('/<span class="svg-pie-chart-hintbox-value">(.*?)<\/span>/', $hintbox_html, $matches);
					$this->assertEquals($expected_sector['value'], $matches[1]);

					// Assert hintbox legend color.
					preg_match('/background-color: (.*?);/', $hintbox_html, $matches);
					$this->assertEquals($expected_sector['color'], $matches[1]);

					// Assert sector fill color.
					preg_match('/fill: (.*?);/', $sector->getAttribute('style'), $matches);
					$this->assertEquals($expected_sector['color'], $matches[1]);

					// Match successful, continue to the next expected sector.
					continue 2;
				}
			}

			// Fail test if no match found.
			throw new Exception('Expected sector for '.$item_name.' not found.');
		}

		// Assert expected Total value.
		if (CTestArrayHelper::get($data, 'expected_total')) {
			$this->assertEquals($data['expected_total'], $widget->query('class:svg-pie-chart-total-value')->one()->getText());
		}

		// Wait for the sector animation to end before taking a screenshot (animation length 1 second).
		sleep(2);

		// Screenshot the widget.
		$this->page->removeFocus();
		$this->assertScreenshot($widget, 'piechart_display_'.$data['screenshot_id']);
	}

	/**
	 * Take screenshot of the edit form, but only in the 'create' test to avoid excessive screenshots.
	 *
	 * @param $dialog
	 * @param $id
	 * @param $action
	 */
	protected function screenshotLayout($dialog, $id, $action) {
		if ($action !== 'create') {
			return;
		}

		// Screenshot Item pattern.
		$this->page->removeFocus();
		$this->assertScreenshot($dialog, $id);
	}

	/**
	 * Asserts that a range/slider input is displayed as expected.
	 *
	 * @param $label              label of the range input
	 * @param $input_id           id for the input field right next to the slider
	 * @param $form               parent form
	 * @param $expected_values    the attribute values expected
	 */
	protected function assertRangeLayout($label, $input_id, $form, $expected_values) {
		$range = $form->getField($label)->query('xpath:.//input[@type="range"]')->one();
		foreach ($expected_values as $attribute => $expected_value) {
			$this->assertEquals($expected_value, $range->getAttribute($attribute));
		}

		$input = $form->getField($label)->query('id', $input_id)->one();
		$this->assertTrue($input->isClickable());
		$this->assertEquals($expected_values['value'], $input->getValue());
	}


	/**
	 * Checks that given labels exist in a form.
	 *
	 * @param array $labels           labels to assert
	 * @param CFormElement $form      form that these labels should exist in.
	 */
	protected function assertLabels($labels, $form) {
		foreach ($labels as $label) {
			try {
				$this->assertTrue($form->query('xpath:.//label[text()='.CXPathHelper::escapeQuotes($label).']')->exists());
			}
			catch (PHPUnit\Framework\ExpectationFailedException $e) {
				// Throw a more detailed error.
				throw new Exception("Failed to find label: ".$label);
			}
		}
	}

	/**
	 * Resets the dashboard and creates a single Pie chart widget.
	 *
	 * @param string $widget_name    name of the widget to be created
	 */
	protected function createCleanWidget($widget_name){
		CDataHelper::call('dashboard.update',
			[
				'dashboardid' => self::$dashboardid,
				'pages' => [
					[
						'widgets' => [
							[
								'name' => $widget_name,
								'type' => 'piechart',
								'fields' => [
									['name' => 'ds.0.hosts.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'Test Host'],
									['name' => 'ds.0.items.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'Test Items'],
									['name' => 'ds.0.color', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'FF465C'],
								]
							]
						]
					]
				]
			]
		);
	}

	/**
	 * Calculates if the expected test result is TEST_GOOD.
	 *
	 * @param $data    data from data provider
	 * @return bool    TRUE if TEST_GOOD expected, else FALSE
	 */
	protected function isTestGood($data) {
		return CTestArrayHelper::get($data, 'result', TEST_GOOD) === TEST_GOOD;
	}

	/**
	 * Waits for a widget to stop loading and to show the pie chart.
	 *
	 * @param CWidgetElement $widget    widget element to wait
	 */
	protected function waitForWidgetToLoad($widget) {
		$widget->query('xpath:.//div[contains(@class, "is-loading")]')->waitUntilNotPresent();
		$widget->getContent()->query('class:svg-pie-chart')->waitUntilVisible();
	}

	/**
	 * Calculates widget name from field data in data provider.
	 * If no name is provided, then use an MD5 as the name so that it is unique.
	 *
	 * @param array $fields    field data for calculating the widget name
	 */
	protected function calculateWidgetName($fields) {
		return (array_key_exists('main_fields', $fields) && array_key_exists('Name', $fields['main_fields']))
				? $fields['main_fields']['Name']
				: md5(serialize($fields));
	}

	/**
	 * Asserts that the data is saved and displayed as expected in the Edit form.
	 *
	 * @param $data    data from data provider
	 */
	protected function assertEditFormAfterSave($data) {
		$dashboard = CDashboardElement::find()->one();

		if ($this->isTestGood($data)) {
			COverlayDialogElement::ensureNotPresent();

			// Save Dashboard.
			$widget = $dashboard->getWidget($this->calculateWidgetName($data['fields']));
			$this->waitForWidgetToLoad($widget);
			$dashboard->save();

			// Assert successful save.
			$message = CMessageElement::find()->waitUntilPresent()->one();
			$this->assertTrue($message->isGood());
			$this->assertEquals('Dashboard updated', $message->getTitle());

			// Assert data in edit form.
			$form = $widget->edit();

			// Check main fields
			$main_fields = CTestArrayHelper::get($data['fields'], 'main_fields', []);
			$main_fields['Name'] = $this->calculateWidgetName($data['fields']);
			$form->checkValue($main_fields);

			$data_sets = $this->extractDataSets($data['fields']);
			$last = count($data_sets) - 1;

			// Check Data set tab.
			foreach ($data_sets as $i => $data_set) {
				$type = CTestArrayHelper::get($data_set, 'type', self::TYPE_ITEM_PATTERN);
				unset($data_set['type']);

				// Additional steps for Item list.
				if ($type === self::TYPE_ITEM_LIST) {
					// Check Host and all Items.
					foreach ($data_set['items'] as $item) {
						$this->assertTrue($form->query('link', $data_set['host'].': '.$item['name'])->exists());
					}

					unset($data_set['host']);
				}
				$data_set = $this->remapDataSet($data_set, $i);
				$form->checkValue($data_set);

				// Open the next data set, if it exists.
				if ($i !== $last) {
					$form->query('xpath:.//li[contains(@class, "list-accordion-item")]['.
						($i + 2).']//button[contains(@class, "list-accordion-item-toggle")]')->one()->click();
					$form->invalidate();
				}
			}

			// Check values in other tabs
			$tabs = ['Displaying options', 'Time period', 'Legend'];
			foreach ($tabs as $tab) {
				if (array_key_exists($tab, $data['fields'])) {
					$form->selectTab($tab);
					$form->checkValue($data['fields'][$tab]);
				}
			}
		}
		else {
			// Assert error message.
			$message = CMessageElement::find()->waitUntilPresent()->one();
			$this->assertTrue($message->isBad());

			$errors = is_array($data['error']) ? $data['error'] : [$data['error']];

			foreach ($errors as $i => $error) {
				$this->assertEquals($error, $message->getLines()->get($i)->getText());
			}
		}
	}

	/**
	 * Fill Pie chart widget edit form with provided data.
	 *
	 * @param array $fields         field data to fill
	 * @param CFormElement $form    form to be filled
	 */
	protected function fillForm($fields, $form) {
		// Fill main fields.
		$main_fields = CTestArrayHelper::get($fields, 'main_fields', []);
		$main_fields['Name'] = $this->calculateWidgetName($fields);
		$form->fill($main_fields);

		// Fill datasets.
		$delete = CTestArrayHelper::get($fields, 'delete_data_set');
		$remake = CTestArrayHelper::get($fields, 'remake_data_set');

		if ($delete || $remake) {
			$form->query('xpath:.//button[@title="Delete"]')->one()->click();

			if ($remake) {
				$form->query('button:Add new data set')->one()->click();
				$form->invalidate();
			}
		}

		if (!$delete) {
			$this->fillDatasets($this->extractDataSets($fields), $form);
		}

		// Fill the other tabs.
		$tabs = ['Displaying options', 'Time period', 'Legend'];

		foreach ($tabs as $tab) {
			if (!array_key_exists($tab, $fields)) {
				continue;
			}

			$form->selectTab($tab);
			$form->fill($fields[$tab]);
		}
	}

	/**
	 * Fill "Data sets" tab with field data.
	 *
	 * @param array $data_sets      array of data sets to be filled
	 * @param CFormElement $form    CFormElement to be filled
	 */
	protected function fillDatasets($data_sets, $form) {
		// Count of data sets that already exist (needed for updating).
		$count_sets = $form->query('xpath:.//li[contains(@class, "list-accordion-item")]')->all()->count();

		foreach ($data_sets as $i => $data_set) {
			$type = CTestArrayHelper::get($data_set, 'type', self::TYPE_ITEM_PATTERN);
			unset($data_set['type']);

			// Special case: the first Data set is of type Item list.
			$deleted_first_set = false;
			if ($i === 0 && $type === self::TYPE_ITEM_LIST && $count_sets === 1) {
				$form->query('xpath:.//button[@title="Delete"]')->one()->click();
				$deleted_first_set = true;
			}

			// Open the Data set or create a new one.
			if ($i + 1 < $count_sets) {
				$form->query('xpath:.//li[contains(@class, "list-accordion-item")]['.
						($i + 1).']//button[contains(@class, "list-accordion-item-toggle")]')->one()->click();
			}
			else if ($i !== 0 || $deleted_first_set) {
				// Only add a new Data set if it is not the first one or the first one was deleted.
				$this->addNewDataSet($type, $form);
			}

			$form->invalidate();

			// Need additional steps when Data set type is Item list, but only if Host is set at all.
			if ($type === self::TYPE_ITEM_LIST && array_key_exists('host', $data_set)) {
				// Select Host.
				$form->query('button:Add')->one()->click();
				$dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$select = $dialog->query('class:multiselect-control')->asMultiselect()->one();
				$select->fill($data_set['host']);
				unset($data_set['host']);

				// Select Items.
				$table = $dialog->query('class:list-table')->asTable()->waitUntilReady()->one();
				foreach ($data_set['items'] as $item) {
					$table->findRow('Name', $item['name'])->select();
				}
				$dialog->query('xpath:.//div[@class="overlay-dialogue-footer"]//button[text()="Select"]')->one()->click();
				$dialog->waitUntilNotVisible();
			}

			$data_set = $this->remapDataSet($data_set, $i);
			$form->fill($data_set);
		}
	}

	/**
	 * Adds a new Data set of the correct type.
	 *
	 * @param $type    type of the data set
	 * @param $form    widget edit form element
	 */
	protected function addNewDataSet($type, $form) {
		if ($type === self::TYPE_ITEM_PATTERN) {
			$form->query('button:Add new data set')->one()->click();
		}
		else {
			$dropdown_button = $form->query('id:dataset-menu')->one();
			$dropdown_button->click();
			$dropdown_button->asPopupButton()->getMenu()->select(self::TYPE_ITEM_LIST);
		}
	}

	/**
	 * Exchanges generic field names for the actual field selectors in a Data set form.
	 *
	 * @param $data_set    Data set data
	 * @param $number      the position of this data set in UI
	 * @return array       remapped Data set
	 */
	protected function remapDataSet($data_set, $number) {
		// Key - selector mapping.
		$mapping = [
			'host' => 'xpath:.//div[@id="ds_'.$number.'_hosts_"]/..',
			'item' => 'xpath:.//div[@id="ds_'.$number.'_items_"]/..',
			'color' => 'xpath:.//input[@id="ds_'.$number.'_color"]/..',
			'il_color' => 'xpath:.//input[@id="items_'.$number.'_{id}_color"]/..',
			'il_type' => 'xpath:.//z-select[@id="items_'.$number.'_{id}_type"]'
		];

		// Exchange the keys for the actual selectors and clear the old key.
		foreach ($data_set as $data_set_key => $data_set_value) {
			if (!array_key_exists($data_set_key, $mapping)) {
				// Only change mapped keys.
				continue;
			}

			$data_set += [$mapping[$data_set_key] => $data_set_value];
			unset($data_set[$data_set_key]);
		}

		// Also map item fields for Item list.
		if (array_key_exists('items', $data_set)) {
			// An Item list can have several items.
			foreach ($data_set['items'] as $item_id => $item) {

				// An item can have several fields.
				foreach ($item as $field_key => $field_value) {
					if (array_key_exists($field_key, $mapping)) {
						// Set the item ID in selector, it starts at 1.
						$mapped_value = str_replace('{id}', $item_id + 1, $mapping[$field_key]);
						$data_set += [$mapped_value => $field_value];
					}
				}
			}

			unset($data_set['items']);
		}

		return $data_set;
	}

	/**
	 * Takes field data from a data provider and sets the defaults for Data sets.
	 *
	 * @param $fields    field data from data provider
	 * @returns array    normalised
	 */
	protected function extractDataSets($fields) {
		$data_sets = array_key_exists('Data set', $fields)
			? $fields['Data set']
			: ['host' => 'Test Host', 'item' => 'Test Item'];

		if (CTestArrayHelper::isAssociative($data_sets)) {
			$data_sets = [$data_sets];
		}

		foreach ($data_sets as $i => $data_set) {
			if ($data_set === []) {
				$data_sets[$i] = ['host' => 'Test Host '.$i, 'item' => 'Test Item '.$i];
			}
		}

		return $data_sets;
	}
}

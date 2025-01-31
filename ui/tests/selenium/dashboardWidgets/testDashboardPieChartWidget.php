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
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

define('LOGIN', true);
define('WITHOUT_LOGIN', false);
define('DEFAULT_PAGE', null);

/**
 * @backup widget
 *
 * @dataSource AllItemValueTypes
 *
 * @onBefore prepareData
 */
class testDashboardPieChartWidget extends testWidgets {
	protected static $dashboard_id;
	protected static $disposable_dashboard_id;
	protected static $item_ids;
	const TYPE_ITEM_PATTERN = 'Item pattern';
	const TYPE_ITEM_LIST = 'Item list';
	const TYPE_DATA_SET_CLONE = 'Clone';
	const HOST_NAME_ITEM_LIST = 'pie-chart-item-list';
	const HOST_NAME_SCREENSHOTS = 'pie-chart-display';
	const PAGE_1 = 'Page 1';
	const PAGE_2 = 'Page 2';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	/**
	 * Create the initial data and set static variables.
	 */
	public function prepareData() {
		// For faster tests set Pie chart as the default widget type.
		DB::delete('profiles', ['idx' => 'web.dashboard.last_widget_type', 'userid' => 1]);
		DB::insert('profiles', [
			[
				'profileid' => 99999,
				'userid' => 1,
				'idx' => 'web.dashboard.last_widget_type',
				'value_str' => 'piechart',
				'type' => 3
			]
		]);

		// Create a Dashboard for widgets.
		$fields = [
			['name' => 'ds.0.hosts.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'Test Host'],
			['name' => 'ds.0.items.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'Test Items'],
			['name' => 'ds.0.color', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'FF465C']
		];
		$dashboards = CDataHelper::call('dashboard.create', [
			'name' => 'Pie chart dashboard',
			'auto_start' => 0,
			'pages' => [
				[
					'name' => self::PAGE_1
				],
				[
					'name' => self::PAGE_2,
					'widgets' => [
						[
							'name' => 'Pie chart for simple update',
							'type' => 'piechart',
							'x' => 0,
							'y' => 0,
							'width' => 18,
							'height' => 4,
							'fields' => $fields
						],
						[
							'name' => 'Pie chart for delete',
							'type' => 'piechart',
							'x' => 18,
							'y' => 0,
							'width' => 18,
							'height' => 4,
							'fields' => $fields
						],
						[
							'name' => 'Pie chart for cancel',
							'type' => 'piechart',
							'x' => 36,
							'y' => 0,
							'width' => 18,
							'height' => 4,
							'fields' => $fields
						]
					]
				]
			]
		]);
		self::$dashboard_id = $dashboards['dashboardids'][0];

		// Create a host for Pie chart testing.
		$response = CDataHelper::createHosts([
			[
				'host' => self::HOST_NAME_ITEM_LIST,
				'groups' => [['groupid' => 6]], // Virtual machines
				'items' => [
					[
						'name' => 'item-1',
						'key_' => 'key-1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'item-2',
						'key_' => 'key-2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => self::HOST_NAME_SCREENSHOTS,
				'groups' => [['groupid' => 6]], // Virtual machines
				'items' => [
					[
						'name' => 'item-1',
						'key_' => 'item-1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'item-2',
						'key_' => 'item-2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'item-3',
						'key_' => 'item-3',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'item-4',
						'key_' => 'item-4',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'item-5',
						'key_' => 'item-5',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					]
				]
			]
		]);
		// Get itemids from host self::HOST_NAME_SCREENSHOTS as array item_key => item_value
		self::$item_ids = array_slice(CDataHelper::getIds('key_'), 2);
	}

	/**
	 * Test the elements and layout of the Pie chart create form.
	 */
	public function testDashboardPieChartWidget_Layout() {
		// Open the create form.
		$dashboard = $this->openDashboard();
		$form = $dashboard->edit()->addWidget()->asForm();
		$dialog = COverlayDialogElement::find()->one();
		$this->assertEquals('Add widget', $dialog->getTitle());

		// Check modal Help and Close buttons.
		foreach (['xpath:.//*[@title="Help"]', 'xpath:.//button[@title="Close"]'] as $selector) {
			$this->assertTrue($dialog->query($selector)->one()->isClickable());
		}

		// Assert that the generic widget Type field works as expected.
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Clock')]);
		$this->assertFalse($form->query('id:data_set')->exists());
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Pie chart')]);
		$this->assertTrue($form->query('id:data_set')->exists());

		// Check other generic widget fields.
		$expected_values = [
			'Type' => 'Pie chart',
			'Show header' => true,
			'Name' => '',
			'Refresh interval' => 'Default (1 minute)'
		];
		$form->checkValue($expected_values);
		$this->assertFieldAttributes($form, 'Name', ['placeholder' => 'default', 'maxlength' => 255]);
		$this->assertEquals(array_keys($expected_values), $form->getLabels(CElementFilter::CLICKABLE)->asText());

		foreach (array_keys($expected_values) as $field) {
			$this->assertTrue($form->getField($field)->isEnabled());
		}

		// Check tabs.
		$this->assertEquals(['Data set', 'Displaying options', 'Time period', 'Legend'], $form->getTabs());

		// Check Data set - Item pattern.
		$expected_values = [
			'xpath:.//input[@id="ds_0_color"]/..' => 'FF465C', // data set color
			'xpath:.//div[@id="ds_0_hosts_"]/..' => '',        // host pattern
			'xpath:.//div[@id="ds_0_items_"]/..' => '',        // item pattern
			'Aggregation function' => 'last',
			'Data set aggregation' => 'not used',
			'Data set label' => ''
		];
		$form->checkValue($expected_values);
		$expected_labels = ['Data set #1', 'Aggregation function', 'Data set aggregation', 'Data set label'];
		$data_set_tab = $form->query('id:data_set')->one();
		$this->assertAllVisibleLabels($data_set_tab, $expected_labels);
		$this->validateDataSetHintboxes($form);

		$buttons = [
			'id:ds_0_hosts_',                                      // host multiselect
			'id:ds_0_items_',                                      // item multiselect
			'xpath:.//li[@data-set="0"]//button[@title="Delete"]', // first data set delete icon
			'id:dataset-add',                                      // button 'Add new data set'
			'id:dataset-menu'                                      // context menu of button 'Add new data set'
		];
		foreach ($buttons as $selector) {
			$this->assertTrue($form->query($selector)->one()->isClickable());
		}

		$options = [
			'Aggregation function' => ['last', 'min', 'max', 'avg', 'count', 'sum', 'first'],
			'Data set aggregation' => ['not used', 'min', 'max', 'avg', 'count', 'sum']
		];
		foreach ($options as $dropdown => $expected_options) {
			$this->assertEquals($expected_options, $form->getField($dropdown)->getOptions()->asText());
		}

		foreach (['id:ds_0_hosts_' => 'host patterns','id:ds_0_items_' => 'item patterns'] as $selector => $placeholder) {
			$this->assertFieldAttributes($form, $selector, ['placeholder' => $placeholder], true);
		}

		$this->assertFieldAttributes($form, 'Data set label', ['placeholder' => 'Data set #1', 'maxlength' => 255]);

		// Check Data set - Item list.
		$this->addNewDataSet($form, self::TYPE_ITEM_LIST);
		$form->invalidate();
		$expected_values = [
			'Aggregation function' => 'last',
			'Data set aggregation' => 'not used',
			'Data set label' => ''
		];
		$form->checkValue($expected_values);
		$expected_labels = ['Data set #1', 'Data set #2', 'Aggregation function', 'Data set aggregation', 'Data set label'];
		$this->assertAllVisibleLabels($data_set_tab, $expected_labels);
		$this->validateDataSetHintboxes($form);

		$buttons = [
			'xpath:.//li[@data-set="1"]//button[@title="Delete"]', // second data set delete icon
			'id:dataset-add',                                      // button 'Add new data set'
			'id:dataset-menu'                                      // context menu of button 'Add new data set'
		];
		foreach ($buttons as $selector) {
			$this->assertTrue($form->query($selector)->one()->isClickable());
		}

		foreach ($options as $dropdown => $expected_options) {
			$this->assertEquals($expected_options, $form->getField($dropdown)->getOptions()->asText());
		}

		$this->assertFieldAttributes($form, 'Data set label', ['placeholder' => 'Data set #2', 'maxlength' => 255]);

		// Displaying options tab.
		$form->selectTab('Displaying options');
		$displaying_options_tab = $this->query('id:displaying_options')->one()->waitUntilVisible();
		$form->invalidate();
		$expected_values = [
			'History data selection' => 'Auto',
			'Draw' => 'Pie',
			'Space between sectors' => '1',
			'id:merge' => false,         // 'Merge sectors smaller than' checkbox
			'id:merge_percent' => '1',   // 'Merge sectors smaller than' input
			'id:merge_color' => '768D99' // 'Merge sectors smaller than' color picker
		];
		$form->checkValue($expected_values);
		$expected_labels = ['History data selection', 'Draw', 'Space between sectors', 'Merge sectors smaller than'];
		$this->assertAllVisibleLabels($displaying_options_tab, $expected_labels);

		$radios = ['History data selection' => ['Auto', 'History', 'Trends'], 'Draw' => ['Pie', 'Doughnut']];
		foreach ($radios as $radio => $labels) {
			$radio_element = $form->getField($radio);
			$radio_element->isEnabled();
			$this->assertEquals($labels, $radio_element->getLabels()->asText());
		}

		$this->assertRangeSliderParameters($form, 'Space between sectors', ['min' => '0', 'max' => '10', 'step' => '1']);

		// Check states of the checkbox and input elements for 'Merge sectors smaller than' field.
		foreach ([false, true] as $state) {
			$form->fill(['id:merge' => $state]);
			$form->invalidate();
			$this->assertTrue($form->query('id:merge_percent')->one()->isEnabled($state));
			$this->assertTrue($form->query('id:merge_color')->one()->isEnabled($state));
		}

		$form->fill(['Draw' => 'Doughnut']);
		$this->query('id:show_total_fields')->one()->waitUntilVisible();
		$form->invalidate();
		$inputs_enabled = [
			'Width' => true,
			'Stroke width' => true,
			'Show total value' => true,
			'Size' => false,
			'Decimal places' => false,
			'Units' => false,
			'Bold' => false,
			'Colour' => false
		];
		$expected_labels = array_merge($expected_labels, array_keys($inputs_enabled));
		$this->assertAllVisibleLabels($displaying_options_tab, $expected_labels);
		$this->assertRangeSliderParameters($form, 'Width', ['min' => '20', 'max' => '50', 'step' => '10']);
		$this->assertRangeSliderParameters($form, 'Stroke width', ['min' => '0', 'max' => '10', 'step' => '1']);
		$form->checkValue(['Space between sectors' => 1, 'Width' => 50, 'Stroke width' => 0]);
		$expected_values = [
			'Show total value' => false,
			'Size' => 'Auto',
			'Decimal places' => '2',
			'Units' => false, // 'Units' enable checkbox
			'id:units' => '', // 'Units' input
			'Bold' => false,
			'Colour' => null
		];
		$form->checkValue($expected_values);

		foreach ($inputs_enabled as $label => $enabled) {
			$this->assertEquals($enabled, $form->getField($label)->isEnabled());
		}

		$field_maxlengths = [
			'id:space' => 2,
			'id:merge_percent' => 2,
			'id:width' => 2,
			'Decimal places' => 1,
			'id:units' => 255
		];
		foreach ($field_maxlengths as $field_selector => $maxlength) {
			$this->assertFieldAttributes($form, $field_selector, ['maxlength' => $maxlength]);
		}

		$form->fill(['Show total value' => true]);
		$inputs_enabled = [
			'Size' => true,
			'Decimal places' => true,
			'Units' => false,
			'Bold' => true,
			'Colour' => true
		];
		foreach($inputs_enabled as $label => $enabled) {
			$this->assertEquals($enabled, $form->getField($label)->isEnabled());
		}

		$form->fill(['Size' => 'Custom']);
		$value_size = $form->getField('id:value_size_custom_input');
		$this->assertTrue($value_size->isVisible() && $value_size->isEnabled(),
			'The "Units" field input is not visible or not enabled'
		);
		$this->assertEquals('20', $value_size->getValue());

		$form->fill(['id:units_show' => true]);
		$this->assertTrue($form->getField('Units')->isEnabled());

		// Time period tab.
		$form->selectTab('Time period');
		$time_period_tab = $this->query('id:time_period')->one()->waitUntilVisible();
		$form->invalidate();
		$this->assertAllVisibleLabels($time_period_tab, ['Time period']);
		$form->checkValue(['Time period' => 'Dashboard']);
		$time_period = $form->getField('Time period');
		$this->assertTrue($time_period->isEnabled());
		$this->assertEquals(['Dashboard', 'Widget', 'Custom'], $time_period->getLabels()->asText());

		$form->fill(['Time period' => 'Widget']);
		$form->checkValue(['Widget' => '']);
		$widget_field = $form->getField('Widget');
		$this->assertTrue($widget_field->isVisible(), $widget_field->isEnabled(), $form->isRequired('Widget'),
				'Widget field is not interactable or is not required'
		);
		$this->assertAllVisibleLabels($time_period_tab, ['Time period', 'Widget']);
		$this->assertFieldAttributes($form, 'Widget', ['placeholder' => 'type here to search'], true);
		$widget_field->query('button:Select')->waitUntilClickable()->one()->click();
		$widget_dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
		$this->assertEquals('Widget', $widget_dialog->getTitle());
		$widget_dialog->close();

		$form->fill(['Time period' => 'Custom']);
		$this->assertAllVisibleLabels($time_period_tab, ['Time period', 'From', 'To']);
		$form->checkValue(['From' => 'now-1h', 'To' => 'now']);

		foreach (['From', 'To'] as $label) {
			$field = $form->getField($label);
			$this->assertTrue($field->isVisible() && $field->isEnabled() && $form->isRequired($label),
					'Field "'.$label.'" not all of these: visible, enabled, required.'
			);
			$this->assertTrue($field->query('id', 'time_period_'.strtolower($label).'_calendar')->one()->isClickable());
			$this->assertFieldAttributes($form, $label, ['placeholder' => 'YYYY-MM-DD hh:mm:ss', 'maxlength' => 255], true);
		}

		// Legend tab.
		$form->selectTab('Legend');
		$legend_tab = $this->query('id:legend_tab')->one()->waitUntilVisible();
		$form->invalidate();
		$expected_values = [
			'Show legend' => true,
			'Show value' => false,
			'Show aggregation function' => false,
			'Rows' => 'Fixed',
			'Number of rows' => 1,
			'Number of columns' => 4
		];
		$form->checkValue($expected_values);
		$this->assertAllVisibleLabels($legend_tab, array_keys($expected_values));
		$this->assertEquals(['Fixed', 'Variable'], $form->getField('Rows')->getLabels()->asText());
		$this->assertRangeSliderParameters($form, 'Number of rows', ['min' => '1', 'max' => '10', 'step' => '1']);
		$this->assertRangeSliderParameters($form, 'Number of columns', ['min' => '1', 'max' => '4', 'step' => '1']);

		$form->fill(['Show legend' => false]);

		foreach (['Show value', 'Show aggregation function', 'Rows', 'Number of rows', 'Number of columns'] as $label) {
			$field = $form->getField($label);
			$this->assertFalse($field->isEnabled());
			$this->assertTrue($field->isVisible());
		}

		// Footer buttons.
		$this->assertEquals(['Add', 'Cancel'],
				$dialog->getFooter()->query('button')->all()->filter(CElementFilter::CLICKABLE)->asText()
		);

		$dialog->close();
	}

	public function getPieChartData() {
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
							'Name' => 'Test most fields possible',
							'Show header' => false,
							'Refresh interval' => '30 seconds'
						],
						'Data set' => [
							[
								'host' => ['Host*', 'one', 'two'],
								'item' => ['Item*', 'one', 'two'],
								'color' => '00BCD4',
								'Aggregation function' => 'min',
								'Data set aggregation' => 'not used',
								'Data set label' => 'Label 1'
							],
							[
								'type' => self::TYPE_ITEM_LIST,
								'host' => self::HOST_NAME_ITEM_LIST,
								'Aggregation function' => 'max',
								'Data set aggregation' => 'not used',
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
							'Stroke width' => '5',
							'Space between sectors' => '2',
							'id:merge' => true,
							'id:merge_percent' => '10',
							'xpath:.//input[@id="merge_color"]/..' => 'EEFF22',
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
							'Show value' => true,
							'Show aggregation function' => true,
							'Rows' => 'Variable',
							'Maximum number of rows' => 2
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
			],
			// Missing Data set.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'delete_data_set' => true
					],
					'error' => 'Invalid parameter "Data set": cannot be empty.'
				]
			],
			// Missing Host pattern.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'remake_data_set' => true,
						'Data set' => ['item' => '*']
					],
					'error' => 'Invalid parameter "Data set/1/hosts": cannot be empty.'
				]
			],
			// Missing Item pattern.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'remake_data_set' => true,
						'Data set' => ['host' => '*']
					],
					'error' => 'Invalid parameter "Data set/1/items": cannot be empty.'
				]
			],
			// Missing Host AND Item pattern.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'remake_data_set' => true,
						'Data set' => ['item' => '', 'host' => '']
					],
					'error' => 'Invalid parameter "Data set/1/hosts": cannot be empty.'
				]
			],
			// Missing Item list.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Data set' => [
							'type' => self::TYPE_ITEM_LIST
						]
					],
					'error' => 'Invalid parameter "Data set/1/itemids": cannot be empty.'
				]
			],
			// Merge sector % value too small.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Displaying options' => [
							'id:merge' => true,
							'id:merge_percent' => '0'
						]
					],
					'error' => 'Invalid parameter "merge_percent": value must be one of 1-10.'
				]
			],
			// Merge sector % value too big.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Displaying options' => [
							'id:merge' => true,
							'id:merge_percent' => '11'
						]
					],
					'error' => 'Invalid parameter "merge_percent": value must be one of 1-10.'
				]
			],
			// Total value custom size missing.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Displaying options' => [
							'Draw' => 'Doughnut',
							'Show total value' => true,
							'id:value_size_type' => 'Custom',
							'id:value_size_custom_input' => ''
						]
					],
					'error' => 'Invalid parameter "value_size": value must be one of 1-100.'
				]
			],
			// Total value custom size out of range.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Displaying options' => [
							'Draw' => 'Doughnut',
							'Show total value' => true,
							'id:value_size_type' => 'Custom',
							'id:value_size_custom_input' => '101'
						]
					],
					'error' => 'Invalid parameter "value_size": value must be one of 1-100.'
				]
			],
			// Decimal places value too big.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Displaying options' => [
							'Draw' => 'Doughnut',
							'Show total value' => true,
							'Decimal places' => '7'
						]
					],
					'error' => 'Invalid parameter "Decimal places": value must be one of 0-6.'
				]
			],
			// Empty Time period (Widget).
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Time period' => [
							'Time period' => 'Widget'
						]
					],
					'error' => 'Invalid parameter "Time period/Widget": cannot be empty.'
				]
			],
			// Empty Time period (Custom).
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Time period' => [
							'Time period' => 'Custom',
							'From' => '',
							'To' => ''
						]
					],
					'error' => [
						'Invalid parameter "Time period/From": cannot be empty.',
						'Invalid parameter "Time period/To": cannot be empty.'
					]
				]
			],
			// Invalid Time period (Custom).
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Time period' => [
							'Time period' => 'Custom',
							'From' => '0',
							'To' => '2020-13-32'
						]
					],
					'error' => [
						'Invalid parameter "Time period/From": a time is expected.',
						'Invalid parameter "Time period/To": a time is expected.'
					]
				]
			],
			// Bad color values.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Data set' => [
							'host' => 'Host*',
							'item' => 'Item*',
							'color' => 'FFFFFG'
						],
						'Displaying options' => [
							'id:merge' => true,
							'xpath:.//input[@id="merge_color"]/..' => 'FFFFFG',
							'Draw' => 'Doughnut',
							'Show total value' => true,
							'Colour' => 'FFFFFG'
						]
					],
					'error' => [
						'Invalid parameter "Data set/1/color": a hexadecimal colour code (6 symbols) is expected.',
						'Invalid parameter "merge_color": a hexadecimal colour code (6 symbols) is expected.',
						'Invalid parameter "Colour": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			]
		];
	}

	/**
	 * Test creation of Pie chart.
	 *
	 * @dataProvider getPieChartData
	 */
	public function testDashboardPieChartWidget_Create($data){
		$this->createUpdatePieChart($data);
	}

	/**
	 * Creates the base widget used for the update scenario.
	 */
	public function prepareUpdatePieChart(){
		$providedData = $this->getProvidedData();
		$data = reset($providedData);

		// Create a dashboard with the widget for updating.
		$response = CDataHelper::call('dashboard.create', [
			'name' => 'Pie chart dashboard '.md5(serialize($data)),
			'pages' => [
				[
					'widgets' => [
						[
							'name' => 'Edit widget',
							'type' => 'piechart',
							'width' => 36,
							'height' => 8,
							'fields' => [
								['name' => 'ds.0.hosts.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'Test Host'],
								['name' => 'ds.0.items.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'Test Items'],
								['name' => 'ds.0.color', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'FF465C']
							]
						]
					]
				]
			]
		]);
		self::$disposable_dashboard_id = $response['dashboardids'][0];
	}

	/**
	 * Test updating of Pie chart.
	 *
	 * @onBefore     prepareUpdatePieChart
	 * @dataProvider getPieChartData
	 */
	public function testDashboardPieChartWidget_Update($data){
		$this->createUpdatePieChart($data, 'Edit widget');
	}

	/**
	 * Test opening Pie chart form and saving with no changes made.
	 */
	public  function testDashboardPieChartWidget_SimpleUpdate() {
		$old_hash = CDBHelper::getHash(self::SQL);
		$dashboard = $this->openDashboard(LOGIN, self::PAGE_2);
		$old_widget_count = $dashboard->getWidgets()->count();
		$form = $dashboard->edit()->getWidget('Pie chart for simple update')->edit();
		$form->submit();
		$dashboard->save();

		// Assert that nothing changed.
		$dashboard = $this->openDashboard(WITHOUT_LOGIN, self::PAGE_2);
		$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	/**
	 * Test data set cloning.
	 */
	public function testDashboardPieChartWidget_CloneDataSets() {
		$dashboard = $this->openDashboard();
		$form = $dashboard->edit()->addWidget()->asForm();

		// Data to input.
		$fields = [
			'main_fields' => [
				'Name' => 'Pie chart for data set cloning'
			],
			'Data set' => [
				[
					'host' => ['Host*', 'one', 'two'],
					'item' => ['Item*', 'one', 'two'],
					'color' => '00BCD4',
					'Aggregation function' => 'min',
					'Data set aggregation' => 'not used',
					'Data set label' => 'Label 1'
				],
				[
					'type' => self::TYPE_DATA_SET_CLONE
				],
				[
					'type' => self::TYPE_ITEM_LIST,
					'host' => self::HOST_NAME_ITEM_LIST,
					'Aggregation function' => 'max',
					'Data set aggregation' => 'not used',
					'Data set label' => 'Label 2',
					'items' => [
						[
							'name' => 'item-1',
							'il_color' => '000000'
						],
						[
							'name' => 'item-2'
						]
					]
				],
				[
					'type' => self::TYPE_DATA_SET_CLONE
				]
			]
		];
		$this->fillForm($form, $fields);
		$form->submit();

		// Transform input data to expected data. The colors are expected to change.
		$fields['Data set'][1] = $fields['Data set'][0];
		$fields['Data set'][1]['color'] = 'FF465C';
		$fields['Data set'][3] = $fields['Data set'][2];
		$fields['Data set'][3]['items'][0]['il_color'] = 'FFD54F';

		// Assert the result.
		$this->assertEditFormAfterSave($dashboard, ['fields' => $fields]);
	}

	public function getCancelData() {
		return [
			// Cancel creation, save dashboard.
			[
				[
					'save_widget' => false,
					'save_dashboard' => true
				]
			],
			// Create the widget, cancel saving the dashboard.
			[
				[
					'save_widget' => true,
					'save_dashboard' => false
				]
			],
			// Cancel update, save dashboard.
			[
				[
					'update' => true,
					'save_widget' => false,
					'save_dashboard' => true
				]
			],
			// Update but cancel saving the dashboard.
			[
				[
					'update' => true,
					'save_widget' => true,
					'save_dashboard' => false
				]
			]
		];
	}

	/**
	 * Test cancelling Pie chart creation and update.
	 *
	 * @dataProvider getCancelData
	 */
	public  function testDashboardPieChartWidget_Cancel($data) {
		// Get DB hash and widget count.
		$old_hash = CDBHelper::getHash(self::SQL);
		$dashboard = $this->openDashboard(LOGIN, self::PAGE_2);
		$old_widget_count = $dashboard->getWidgets()->count();

		// Edit data in widget's form.
		$form = CTestArrayHelper::get($data, 'update')
			? $dashboard->edit()->getWidget('Pie chart for cancel')->edit()
			: $dashboard->edit()->addWidget()->asForm();
		$fields = [
			'main_fields' => [
				'Name' => 'This name should not be saved',
				'Show header' => false,
				'Refresh interval' => '10 seconds'
			],
			'Data set' => [
				[
					'host' => ['Cancel host'],
					'item' => ['Cancel items'],
					'Aggregation function' => 'min',
					'Data set label' => 'Cancel label'
				]
			],
			'Displaying options' => [
				'Draw' => 'Doughnut'
			]
		];
		$this->fillForm($form, $fields);

		// Save the widget OR cancel.
		if (CTestArrayHelper::get($data, 'save_widget')) {
			$form->submit();
		}
		else {
			COverlayDialogElement::find()->one()->query('button:Cancel')->one()->click();
		}

		// Save the dashboard if needed.
		if (CTestArrayHelper::get($data, 'save_dashboard')) {
			$dashboard->save();
		}

		$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_id);

		// TODO: temporarily commented out due webdriver issue #351858989, alert is not displayed while leaving page during test execution
		// Check that alert is present in case of saving the widget without saving the dashboard.
//		if ($data['save_widget'] == true && $data['save_dashboard'] == false) {
//			$this->page->acceptAlert();
//		}

		$this->page->waitUntilReady();
		$dashboard->selectPage(self::PAGE_2);

		// Assert that widget count and DB data has not changed.
		$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	/**
	 * Test the deletion of the Pie chart widget.
	 */
	public function testDashboardPieChartWidget_Delete(){
		$widget_name = 'Pie chart for delete';
		$widget_id = CDBHelper::getValue('SELECT widgetid FROM widget WHERE name='.zbx_dbstr($widget_name));

		// Delete the widget and save the dashboard.
		$dashboard = $this->openDashboard(LOGIN, self::PAGE_2);
		$old_widget_count = $dashboard->getWidgets()->count();
		$dashboard->edit()->deleteWidget($widget_name)->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Assert that the widget has been deleted.
		$this->assertEquals($old_widget_count - 1, $dashboard->getWidgets()->count());
		$this->assertFalse($dashboard->getWidget($widget_name, false)->isValid());

		// Check DB tables widget and widget_field.
		$sql = 'SELECT NULL FROM widget w CROSS JOIN widget_field wf'.
				' WHERE w.widgetid='.zbx_dbstr($widget_id).' OR wf.widgetid='.zbx_dbstr($widget_id);
		$this->assertEquals(0, CDBHelper::getCount($sql));
		$this->assertEquals(0, CDBHelper::getCount('SELECT null FROM widget WHERE name='.zbx_dbstr($widget_name)));
	}

	/**
	 * Prepare a widget for the Pie chart display scenario.
	 */
	public function preparePieChartDisplayData() {
		$providedData = $this->getProvidedData();
		$data = reset($providedData);
		CDataHelper::removeItemData(array_values(self::$item_ids));

		// Minus 10 seconds for safety.
		$time = time() - 10;

		// Set item history.
		foreach($data['item_data'] as $key => $value) {
			CDataHelper::addItemData(self::$item_ids[$key], $value, $time);
		}

		// Fill in Item ids (this only applies to Item list data sets).
		foreach ($data['widget_fields'] as $id => $field) {
			if (preg_match('/^ds\.[0-9]\.itemids\.[0-9]$/', $field['name'])) {
				$field['value'] = self::$item_ids[$field['value']];
				$data['widget_fields'][$id] = $field;
			}
		}

		// Set the disposable dashboard to contain the needed widget.
		$response = CDataHelper::call('dashboard.create', [
			'name' => 'Pie chart dashboard '.md5(serialize($data)),
			'pages' => [
				[
					'widgets' => [
						[
							'name' => $data['widget_name'],
							'view_mode' => CTestArrayHelper::get($data, 'view_mode', 0),
							'type' => 'piechart',
							'width' => 24,
							'height' => 6,
							'fields' => $data['widget_fields']
						]
					]
				]
			]
		]);
		self::$disposable_dashboard_id = $response['dashboardids'][0];
	}

	public function getPieChartDisplayData() {
		return [
			// Empty Pie chart (no items or data).
			[
				[
					'widget_name' => 'no-data',
					'widget_fields' => [
						['name' => 'ds.0.hosts.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => self::HOST_NAME_SCREENSHOTS],
						['name' => 'ds.0.items.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'item-1']
					],
					'item_data' => []
				]
			],
			// One item, custom data set name.
			[
				[
					'widget_name' => 'one-item',
					'widget_fields' => [
						['name' => 'ds.0.hosts.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => self::HOST_NAME_SCREENSHOTS],
						['name' => 'ds.0.items.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'item-1'],
						['name' => 'ds.0.data_set_label', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'TEST SET ☺'],
						['name' => 'ds.0.dataset_aggregation', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 2]
					],
					'item_data' => [
						'item-1' => 99
					],
					'expected_dataset_name' => 'TEST SET ☺',
					'expected_sectors' => [
						'item-1' => ['value' => '99', 'color' => '255, 70, 92']
					]
				]
			],
			// Two items, data set aggregate function, very small item values.
			[
				[
					'widget_name' => 'two-items',
					'widget_fields' => [
						['name' => 'ds.0.hosts.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => self::HOST_NAME_SCREENSHOTS],
						['name' => 'ds.0.items.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'item-3'],
						['name' => 'ds.0.items.1', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'item-4'],
						['name' => 'ds.0.aggregate_function', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 2],
						['name' => 'legend_aggregation', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1]
					],
					'item_data' => [
						'item-3' => 0.00000000000000003,
						'item-4' => 0.00000000000000002
					],
					'expected_legend_function' => 'max',
					'expected_sectors' => [
						'item-3' => ['value' => '3E-17', 'color' => '255, 70, 92'],
						'item-4' => ['value' => '2E-17', 'color' => '255, 197, 219']
					]
				]
			],
			// Five items, host and item pattern, custom colors, hide legend and header.
			[
				[
					'widget_name' => 'five-items',
					'view_mode' => 1,
					'widget_fields' => [
						['name' => 'ds.0.hosts.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'pie-chart-display'],
						['name' => 'ds.0.items.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'item-*'],
						['name' => 'ds.0.color', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'FFA726'],
						['name' => 'legend', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 0]
					],
					'item_data' => [
						'item-1' => 1,
						'item-2' => 2,
						'item-3' => 3.0,
						'item-4' => 4.4,
						'item-5' => 5.55
					],
					'expected_sectors' => [
						'item-1' => ['value' => '1', 'color' => '127, 39, 0'],
						'item-2' => ['value' => '2', 'color' => '178, 90, 0'],
						'item-3' => ['value' => '3', 'color' => '229, 141, 12'],
						'item-4' => ['value' => '4.4', 'color' => '255, 192, 63'],
						'item-5' => ['value' => '5.55', 'color' => '255, 243, 114']
					]
				]
			],
			// Doughnut, data set type 'Item list', Total item, merging enabled, total value display, custom legends.
			[
				[
					'widget_name' => 'doughnut',
					'widget_fields' => [
						// Items and their colors.
						['name' => 'ds.0.dataset_type', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 0],
						['name' => 'ds.0.itemids.0', 'type' => ZBX_WIDGET_FIELD_TYPE_ITEM, 'value' => 'item-1'],
						['name' => 'ds.0.type.0', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'ds.0.color.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'FF002F'],
						['name' => 'ds.0.itemids.1', 'type' => ZBX_WIDGET_FIELD_TYPE_ITEM, 'value' => 'item-2'],
						['name' => 'ds.0.type.1', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 0],
						['name' => 'ds.0.color.1', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'FF4265'],
						['name' => 'ds.0.itemids.2', 'type' => ZBX_WIDGET_FIELD_TYPE_ITEM, 'value' => 'item-3'],
						['name' => 'ds.0.type.2', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 0],
						['name' => 'ds.0.color.2', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'FA6984'],
						['name' => 'ds.0.itemids.3', 'type' => ZBX_WIDGET_FIELD_TYPE_ITEM, 'value' => 'item-4'],
						['name' => 'ds.0.type.3', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 0],
						['name' => 'ds.0.color.3', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => '525252'],
						['name' => 'ds.0.itemids.4', 'type' => ZBX_WIDGET_FIELD_TYPE_ITEM, 'value' => 'item-5'],
						['name' => 'ds.0.type.4', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 0],
						['name' => 'ds.0.color.4', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => '525252'],
						// Drawing and total value options.
						['name' => 'draw_type', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'width', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 50],
						['name' => 'total_show', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'value_size_type', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'value_size', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 25],
						['name' => 'decimal_places', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 0],
						['name' => 'units_show', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'units', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => '♥'],
						['name' => 'value_bold', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'value_color', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => '7CB342'],
						['name' => 'stroke', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 8],
						['name' => 'space', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 6],
						['name' => 'merge', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'merge_percent', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 10],
						['name' => 'merge_color', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => '69B4FA'],
						// Legend with values.
						['name' => 'legend_value', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'legend_lines_mode', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'legend_lines', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 2]
					],
					'item_data' => [
						'item-1' => 100,
						'item-2' => 25,
						'item-3' => 35,
						'item-4' => 4,
						'item-5' => 5
					],
					'expected_total' => '100 ♥',
					'expected_sectors' => [
						'item-1' => ['value' => '100 ♥', 'color' => '255, 0, 47'],
						'item-2' => ['value' => '25 ♥', 'color' => '255, 66, 101'],
						'item-3' => ['value' => '35 ♥', 'color' => '250, 105, 132'],
						'Other' => ['value' => '9 ♥', 'color' => '105, 180, 250']
					]
				]
			]
		];
	}

	/**
	 * Generate different Pie charts and assert their display.
	 *
	 * @onBefore     preparePieChartDisplayData
	 * @dataProvider getPieChartDisplayData
	 */
	public function testDashboardPieChartWidget_PieChartDisplay($data) {
		$this->openDashboard(LOGIN, DEFAULT_PAGE, self::$disposable_dashboard_id);
		$widget = CDashboardElement::find()->one()->getWidget($data['widget_name']);

		// Wait for the sector animation to end.
		sleep(1);

		// Assert Pie chart sectors.
		$expected_sectors = CTestArrayHelper::get($data, 'expected_sectors', []);

		foreach ($expected_sectors as $item_name => $expected_sector) {
			// The name shown in the legend and in the hintbox.
			$legend_name = self::HOST_NAME_SCREENSHOTS.': '.$item_name;

			// Special case - legend name includes aggregation function.
			if (CTestArrayHelper::get($data, 'expected_legend_function')) {
				$legend_name = CTestArrayHelper::get($data, 'expected_legend_function').'('.$legend_name.')';
			}

			// Special case - legend name is 'Other' because sectors were merged.
			if ($item_name === 'Other') {
				$legend_name = 'Other';
			}

			// Special case - custom legend name.
			$legend_name = CTestArrayHelper::get($data, 'expected_dataset_name', $legend_name);

			// Locate sector for checking.
			$sector = $widget->query('xpath:.//*[contains(@data-hintbox-contents, '.
					CXPathHelper::escapeQuotes($legend_name).')]/*[@class="svg-pie-chart-arc"]')->one();

			// Assert sector fill color.
			$this->assertEquals('rgb('.$expected_sector['color'].')', $sector->getCSSValue('fill'));

			// Open and assert the hintbox.
			$sector->click();
			$hintbox = $this->query('class:overlay-dialogue')->asOverlayDialog()->waitUntilReady()->all()->last();
			$this->assertEquals($legend_name.': '."\n".$expected_sector['value'], $hintbox->getText());
			$this->assertEquals('rgba('.$expected_sector['color'].', 1)',
					$hintbox->query('class:svg-pie-chart-hintbox-color')->one()->getCSSValue('background-color')
			);
			$hintbox->close();
		}

		// Assert the total count of sectors.
		$this->assertEquals(count($expected_sectors), $widget->query('class:svg-pie-chart-arc')->all()->count());

		// Assert expected Total value.
		if (CTestArrayHelper::get($data, 'expected_total')) {
			$this->assertEquals($data['expected_total'], $widget->query('class:svg-pie-chart-total-value')->one()->getText());
		}

		// Make sure none of the sectors are hovered.
		$this->query('id:page-title-general')->one()->hoverMouse();

		// Wait for the sector hover animation to end before taking a screenshot.
		sleep(1);

		// Screenshot the widget.
		$this->page->removeFocus();
		$this->assertScreenshot($widget, $data['widget_name']);
	}

	/**
	 * Tests that only the correct item types can be used in the Pie chart widget.
	 */
	public function testDashboardPieChartWidget_CheckAvailableItems() {
		$this->checkAvailableItems('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_id, 'Pie chart');
	}

	/**
	 * Creates or updates a widget according to data from data provider.
	 *
	 * @param array  $data                  data from data provider
	 * @param string $update_widget_name    if this is set, then a widget named like this is updated
	 */
	protected function createUpdatePieChart($data, $update_widget_name = null) {
		$dashboard_id = $update_widget_name ? self::$disposable_dashboard_id : self::$dashboard_id;
		$dashboard = $this->openDashboard(LOGIN, DEFAULT_PAGE, $dashboard_id);
		$old_widget_count = $dashboard->getWidgets()->count();
		$form = $update_widget_name
			? $dashboard->edit()->getWidget($update_widget_name)->edit()
			: $dashboard->edit()->addWidget()->asForm();
		$this->fillForm($form, $data['fields'], $update_widget_name !== null);
		$form->submit();

		// Assert the result.
		$this->assertEditFormAfterSave($dashboard, $data, isset($update_widget_name));

		// Check total Widget count.
		$count_added = (!$update_widget_name && CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_GOOD) ? 1 : 0;
		$this->assertEquals($old_widget_count + $count_added, $dashboard->getWidgets()->count());
	}

	/**
	 * Checks the hintboxes in the Data set tab.
	 *
	 * @param CFormElement $form    data set form
	 */
	protected function validateDataSetHintboxes($form) {
		$hints = [
			'Aggregation function' => 'Aggregates each item in the data set.',
			'Data set aggregation' => 'Aggregates the whole data set.',
			'Data set label' => 'Also used as legend label for aggregated data sets.'
		];

		// For each hintbox - open, assert text, close.
		foreach ($hints as $field => $text) {
			$form->getLabel($field)->query('xpath:./button[@data-hintbox]')->one()->waitUntilClickable()->click();
			$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->asOverlayDialog()->waitUntilPresent()->one();
			$this->assertEquals($text, $hint->getText());
			$hint->query('xpath:./button')->one()->click();
		}
	}

	/**
	 * Asserts that data is saved and displayed as expected in the edit form.
	 *
	 * @param CDashboardElement $dashboard    dashboard element
	 * @param array             $data         data from data provider
	 * @param bool              $update       add additional string to the 'Name' field, so it is unique
	 */
	protected function assertEditFormAfterSave($dashboard, $data, $update = null) {
		$widget_name = CTestArrayHelper::get($data['fields'], 'main_fields.Name', md5(serialize($data['fields'])));
		$widget_name = $update ? $widget_name.' updated' : $widget_name;
		$count_sql = 'SELECT NULL FROM widget WHERE name='.zbx_dbstr($widget_name);

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_GOOD) {
			COverlayDialogElement::ensureNotPresent();

			// Save Dashboard.
			$widget = $dashboard->getWidget($widget_name);
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Assert data in edit form.
			$form = $widget->edit();

			// Check main fields
			$main_fields = CTestArrayHelper::get($data['fields'], 'main_fields', []);
			$main_fields['Name'] = $widget_name;
			$form->checkValue($main_fields);

			// Get expected data set data.
			$expected_data_sets = $this->extractDataSets($data['fields']);
			$last_data_set = count($expected_data_sets) - 1;

			// For each expected data set.
			foreach ($expected_data_sets as $i => $data_set) {
				$type = CTestArrayHelper::get($data_set, 'type', self::TYPE_ITEM_PATTERN);
				// There is no actual 'Type' field in the form.
				unset($data_set['type']);

				// Additional steps for Item list.
				if ($type === self::TYPE_ITEM_LIST) {
					// Check Host and all Items.
					foreach ($data_set['items'] as $item) {
						$this->assertTrue($form->query('link', $data_set['host'].': '.$item['name'])->exists());
					}
					// There is no 'Host' field for Item lists.
					unset($data_set['host']);
				}

				// Check data set form.
				$data_set = $this->remapDataSet($data_set, $i);
				$form->checkValue($data_set);

				// Check data set label.
				$label = CTestArrayHelper::get($data_set, 'Data set label', 'Data set #'.$i + 1);
				$this->assertEquals($label,
						$form->query('xpath:.//li[@data-set="'.$i.'"]//label[contains(@class, "js-dataset-label")]')->
						one()->getText()
				);

				// Open the next data set, if it exists.
				if ($i !== $last_data_set) {
					$form->query('xpath:.//li[contains(@class, "list-accordion-item")]['.
							($i + 2).']//button[contains(@class, "list-accordion-item-toggle")]')->one()->click();
					$form->invalidate();
				}
			}

			// Check forms of other tabs.
			$tabs = ['Displaying options', 'Time period', 'Legend'];
			foreach ($tabs as $tab) {
				if (array_key_exists($tab, $data['fields'])) {
					$form->selectTab($tab);
					$form->checkValue($data['fields'][$tab]);
				}
			}

			// Assert that DB record exists.
			$this->assertEquals(1, CDBHelper::getCount($count_sql));
		}
		else {
			// When error expected.

			$this->assertMessage(TEST_BAD, null, $data['error']);
			$this->assertEquals(0, CDBHelper::getCount($count_sql));
		}

		COverlayDialogElement::find()->one()->close();
	}

	/**
	 * Fill Pie chart widget edit form with provided data.
	 *
	 * @param CFormElement $form      form to be filled
	 * @param array        $fields    field data to fill
	 * @param bool         $update    add additional string to the 'Name' field, so it is unique
	 */
	protected function fillForm($form, $fields, $update = false) {
		// Fill main fields.
		$main_fields = CTestArrayHelper::get($fields, 'main_fields', []);
		$main_fields['Name'] = CTestArrayHelper::get($fields, 'main_fields.Name', md5(serialize($fields)));
		$main_fields['Name'] = $update ? $main_fields['Name'].' updated' : $main_fields['Name'];
		$form->fill($main_fields);

		// Fill datasets.
		$delete = CTestArrayHelper::get($fields, 'delete_data_set');
		$remake = CTestArrayHelper::get($fields, 'remake_data_set');

		// Index of data set.
		$j = 0;
		if ($delete || $remake) {
			$form->query('xpath:.//button[@title="Delete"]')->one()->click();
			if ($remake) {
				// Increment index of data set if it is deleted and remade.
				$j = 1;
			}
		}

		if (!$delete) {
			$this->fillDatasets($form, $this->extractDataSets($fields), $j);
		}

		// Fill the other tabs.
		foreach (['Displaying options', 'Time period', 'Legend'] as $tab) {
			if (array_key_exists($tab, $fields)) {
				$form->selectTab($tab);
				$form->fill($fields[$tab]);
			}
		}
	}

	/**
	 * Fill 'Data sets' tab with field data.
	 *
	 * @param CFormElement $form         CFormElement to be filled
	 * @param array        $data_sets    array of data sets to be filled
	 * @param integer      $j            increment for data set index
	 */
	protected function fillDatasets($form, $data_sets, $j = 0) {
		// Count of data sets that already exist (needed for updating).
		$count_sets = $form->query('xpath:.//li[contains(@class, "list-accordion-item")]')->all()->count();

		// For each data set to fill.
		foreach ($data_sets as $i => $data_set) {
			// If data set was remade index is incremented.
			$i = $i + $j;
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
				$this->addNewDataSet($form, $type);
			}

			$form->invalidate();

			// Need additional steps when Data set type is Item list, but only if Host is set at all.
			if ($type === self::TYPE_ITEM_LIST && array_key_exists('host', $data_set)) {
				// Select Host.
				$form->query('button:Add item')->one()->click();
				$dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$select = $dialog->query('class:multiselect-control')->asMultiselect()->one();
				$select->fill($data_set['host']);
				unset($data_set['host']);

				// Select Items.
				$table = $dialog->query('class:list-table')->waitUntilVisible()->asTable()->one();

				foreach ($data_set['items'] as $item) {
					// Get the correct checkbox in table by knowing only link text;
					$checkbox_xpath = 'xpath:.//a[text()='.CXPathHelper::escapeQuotes($item['name']).
							']/../../preceding-sibling::td/input[@type="checkbox"]';
					$table->query($checkbox_xpath)->waitUntilPresent()->asCheckbox()->one()->check();
				}

				$dialog->getFooter()->query('button:Select')->one()->click();
				$dialog->waitUntilNotVisible();
			}

			$data_set = $this->remapDataSet($data_set, $i);
			$form->fill($data_set);
		}
	}

	/**
	 * Clicks the correct 'Add new data set' button.
	 *
	 * @param CFormElement $form      widget edit form
	 * @param string       $type      type of data set
	 * @param boolean      $button    for 'Item pattern' only: use 'Add new data set' button if true, or select from context menu if false
	 */
	protected function addNewDataSet($form, $type = null, $button = true) {
		if (($type === self::TYPE_ITEM_PATTERN || $type === null) && $button) {
			$form->query('button:Add new data set')->one()->click();
		}
		else {
			$this->query('id:dataset-menu')->asPopupButton()->one()->select($type);
		}
	}

	/**
	 * Exchanges generic field names for the actual field selectors in a Data set form.
	 *
	 * @param array $data_set    Data set data
	 * @param int   $number      the position of this data set in UI
	 *
	 * @return array             remapped Data set
	 */
	protected function remapDataSet($data_set, $number) {
		// Simple selector to the actual selector mapping.
		$mapping = [
			'host' => 'xpath:.//div[@id="ds_'.$number.'_hosts_"]/..',
			'item' => 'xpath:.//div[@id="ds_'.$number.'_items_"]/..',
			'color' => 'xpath:.//input[@id="ds_'.$number.'_color"]/..',
			'il_color' => 'xpath:.//input[@id="items_'.$number.'_{id}_color"]/..',
			'il_type' => 'xpath:.//z-select[@id="items_'.$number.'_{id}_type"]'
		];

		// Exchange the keys for the actual selectors and clear the old key.
		foreach ($data_set as $data_set_key => $data_set_value) {
			// Only change mapped selectors.
			if (array_key_exists($data_set_key, $mapping)) {
				$data_set += [$mapping[$data_set_key] => $data_set_value];
				unset($data_set[$data_set_key]);
			}
		}

		// Also map item fields for Item list.
		if (array_key_exists('items', $data_set)) {
			// An Item list can have several items.
			foreach ($data_set['items'] as $item_id => $item) {
				// An item can have several fields.
				foreach ($item as $field_key => $field_value) {
					// Only change mapped selectors.
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
	 * @param array $fields    field data from data provider
	 *
	 * @return array           field data with default values set
	 */
	protected function extractDataSets($fields) {
		// Add one default data set if none defined.
		$data_sets = array_key_exists('Data set', $fields)
			? $fields['Data set']
			: ['host' => 'Test Host', 'item' => 'Test Item'];

		// Set as an array of data sets if needed.
		if (CTestArrayHelper::isAssociative($data_sets)) {
			$data_sets = [$data_sets];
		}

		// Set default values for any empty data sets.
		foreach ($data_sets as $i => $data_set) {
			if ($data_set === []) {
				$data_sets[$i] = ['host' => 'Test Host '.$i, 'item' => 'Test Item '.$i];
			}
		}

		return $data_sets;
	}

	/**
	 * Checks HTML attributes of a field.
	 *
	 * @param CFormElement $form                form element of the field
	 * @param string       $name                name (or selector) of the field
	 * @param array        $attributes          the expected attributes
	 * @param bool         $find_in_children    true if the needed input field is actually a child of the form field element
	 */
	protected function assertFieldAttributes($form, $name, $attributes, $find_in_children = false) {
		$input = $form->getField($name);

		if ($find_in_children) {
			$input = $input->query('tag:input')->one();
		}

		foreach ($attributes as $attribute => $expected_value) {
			$this->assertEquals($expected_value, $input->getAttribute($attribute));
		}
	}

	/**
	 * Opens the Pie chart dashboard.
	 *
	 * @param bool   $login          skips logging in if set to false
	 * @param string $page           opens a page of this name if set
	 * @param int    $dashboard_id    opens dashboard with this id
	 *
	 * @return CDashboardElement     dashboard element of the Pie chart dashboard
	 */
	protected function openDashboard($login = true, $page = null, $dashboard_id = null) {
		if ($login) {
			$this->page->login();
		}

		$id = $dashboard_id === null ? self::$dashboard_id : $dashboard_id;

		$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.$id)->waitUntilReady();

		$dashboard = CDashboardElement::find()->one();
		if ($page) {
			$dashboard->selectPage($page);
		}

		return $dashboard;
	}

	/**
	 * Checks visible labels inside an element (form). Fails if a label is missing or if there are unexpected labels.
	 *
	 * @param CElement $element            element to check
	 * @param array    $expected_labels    list of all expected visible labels
	 */
	protected function assertAllVisibleLabels($element, $expected_labels) {
		// There are weird labels in this form but at the same time we don't need to match all labels, for example radio buttons.
		$label_selector = 'xpath:.//div[@class="form-grid"]/label'.  // standard case
				' | .//div[@class="form-field"]/label'.              // when the label is a child of the field
				' | .//label[contains(@class, "js-dataset-label")]'; // this matches data set labels
		$actual_labels = $element->query($label_selector)->all()->filter(CElementFilter::VISIBLE)->asText();

		// Remove empty labels (checkbox styling is a label) from the list.
		$actual_labels = array_filter($actual_labels);

		// Make sure expected and actual labels match, but ignore the order.
		$this->assertEqualsCanonicalizing($expected_labels, $actual_labels,
				'The expected visible labels and the actual visible labels are different.'
		);
	}
}

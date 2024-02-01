<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
require_once dirname(__FILE__).'/../behaviors/CTagBehavior.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../common/testWidgets.php';

/**
 * @dataSource TopHostsWidget, ItemValueWidget, AllItemValueTypes
 *
 * @backup dashboard, profiles
 *
 * @onBefore prepareData
 */
class testDashboardTopHostsWidget extends testWidgets {

	/**
	 * Attach MessageBehavior and TagBehavior to the test.
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			[
				'class' => CTagBehavior::class,
				'tag_selector' => 'id:tags_table_tags'
			],
			CTableBehavior::class
		];
	}

	/**
	 * Widget name for update.
	 */
	protected static $updated_name = 'Top hosts update';
	protected static $aggregation_itemids;
	protected static $top_hosts_itemids;
	protected static $dashboard_update;
	protected static $dashboard_create;
	protected static $dashboard_delete;
	protected static $dashboard_remove;
	protected static $dashboard_screenshots;
	protected static $dashboard_text_items;
	protected static $dashboard_zoom;
	protected static $dashboard_threshold;
	protected static $dashboard_aggregation;
	const DEFAULT_WIDGET_NAME = 'Top hosts';

	/**
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	protected $sql = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid';

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

	public static function prepareData() {
		self::$dashboard_update = CDataHelper::get('TopHostsWidget.top_host_update');
		self::$dashboard_create = CDataHelper::get('TopHostsWidget.top_host_create');
		self::$dashboard_remove = CDataHelper::get('TopHostsWidget.top_host_remove');
		self::$dashboard_delete = CDataHelper::get('TopHostsWidget.top_host_delete');
		self::$dashboard_screenshots = CDataHelper::get('TopHostsWidget.top_host_screenshots');
		self::$dashboard_text_items = CDataHelper::get('TopHostsWidget.top_host_text_items');
		self::$dashboard_zoom = CDataHelper::get('ItemValueWidget.dashboard_zoom');
		self::$dashboard_threshold = CDataHelper::get('ItemValueWidget.dashboard_threshold');
		self::$dashboard_aggregation = CDataHelper::get('ItemValueWidget.dashboard_aggregation');
		self::$aggregation_itemids = CDataHelper::get('ItemValueWidget.itemids');
		self::$top_hosts_itemids = CDataHelper::get('TopHostsWidget.itemids');

		// Add value to items for CheckTextItems test.
		CDataHelper::addItemData(99086, 1000); // 1_item.
		CDataHelper::addItemData(self::$top_hosts_itemids['top_hosts_trap_text'], 'Text for text item');
		CDataHelper::addItemData(self::$top_hosts_itemids['top_hosts_trap_log'], 'Logs for text item');
		CDataHelper::addItemData(self::$top_hosts_itemids['top_hosts_trap_char'], 'characters_here');
	}

	public function testDashboardTopHostsWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_create);
		$dialog = CDashboardElement::find()->one()->edit()->addWidget();
		$form = $dialog->asForm();
		$this->assertEquals('Add widget', $dialog->getTitle());
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Top hosts')]);
		$this->assertEquals(['Type', 'Show header', 'Name', 'Refresh interval', 'Host groups', 'Hosts', 'Host tags',
				'Show hosts in maintenance', 'Columns', 'Order by', 'Order', 'Host limit'], $form->getLabels()->asText()
		);
		$form->getRequiredLabels(['Columns', 'Order by', 'Host limit']);

		// Check default fields.
		$fields = [
			'Name' => ['value' => '', 'placeholder' => 'default', 'maxlength' => 255],
			'Refresh interval' => ['value' => 'Default (1 minute)'],
			'Show header' => ['value' => true],
			'id:groupids__ms' => ['value' => '', 'placeholder' => 'type here to search'],
			'id:evaltype' => ['value' => 'And/Or', 'labels' => ['And/Or', 'Or']],
			'id:tags_0_tag' => ['value' => '', 'placeholder' => 'tag', 'maxlength' => 255],
			'id:tags_0_operator' => ['value' => 'Contains', 'options' => ['Exists', 'Equals', 'Contains',
					'Does not exist', 'Does not equal', 'Does not contain']
			],
			'id:tags_0_value' => ['value' => '', 'placeholder' => 'value', 'maxlength' => 255],
			'Order' => ['value' => 'Top N', 'labels' => ['Top N', 'Bottom N']],
			'Host limit' => ['value' => 10, 'maxlength' => 3]
		];
		$this->checkFieldsAttributes($fields, $form);

		// Check Columns table.
		$this->assertEquals(['', 'Name', 'Data', 'Action'], $form->getFieldContainer('Columns')->asTable()->getHeadersText());

		// Check clickable buttons.
		$dialog_buttons = [
			['count' => 2, 'query' => $dialog->getFooter()->query('button', ['Add', 'Cancel'])],
			['count' => 2, 'query' => $form->query('id:tags_table_tags')->one()->query('button', ['Add', 'Remove'])],
			['count' => 1, 'query' => $form->getFieldContainer('Columns')->query('button:Add')]
		];

		foreach ($dialog_buttons as $field) {
			$this->assertEquals($field['count'], $field['query']->all()->filter(CElementFilter::CLICKABLE)->count());
		}

		// Check Columns popup.
		$form->getFieldContainer('Columns')->query('button:Add')->one()->waitUntilClickable()->click();
		$column_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$column_form = $column_dialog->asForm();

		$this->assertEquals('New column', $column_dialog->getTitle());
		$this->assertEquals(['Name', 'Data', 'Text', 'Item', 'Display', 'Min', 'Max','Base colour', 'Thresholds',
				'Decimal places', 'Aggregation function', 'Time period', 'Widget', 'From', 'To', 'History data'],
				$column_form->getLabels()->asText()
		);
		$form->getRequiredLabels(['Name', 'Item', 'Aggregation interval']);

		$column_default_fields = [
			'Name' => ['value' => '', 'maxlength' => 255],
			'Data' => ['value' => 'Item value', 'options' => ['Item value', 'Host name', 'Text']],
			'Text' => ['value' => '', 'placeholder' => 'Text, supports {INVENTORY.*}, {HOST.*} macros','maxlength' => 255,
					'visible' => false, 'enabled' => false
			],
			'Item' => ['value' => ''],
			'Display' => ['value' => 'As is', 'labels' => ['As is', 'Bar', 'Indicators']],
			'Min' => ['value' => '', 'placeholder' => 'calculated', 'maxlength' => 255, 'visible' => false, 'enabled' => false],
			'Max' => ['value' => '', 'placeholder' => 'calculated', 'maxlength' => 255, 'visible' => false, 'enabled' => false],
			'xpath:.//input[@id="base_color"]/..' => ['color' => ''],
			'Thresholds' => ['visible' => true],
			'Decimal places' => ['value' => 2, 'maxlength' => 2],
			'Aggregation function' => ['value' => 'not used', 'options' => ['not used', 'min', 'max', 'avg', 'count', 'sum',
					'first', 'last']
			],
			'Time period' => ['value' => 'Dashboard', 'labels' => ['Dashboard', 'Widget', 'Custom'], 'visible' => false, 'enabled' => false],
			'Widget' => ['value' => '', 'visible' => false, 'enabled' => false],
			'id:time_period_from' => ['value' => 'now-1h', 'placeholder' => 'YYYY-MM-DD hh:mm:ss', 'maxlength' => 255, 'visible' => false, 'enabled' => false],
			'id:time_period_to' => ['value' => 'now', 'placeholder' => 'YYYY-MM-DD hh:mm:ss', 'maxlength' => 255, 'visible' => false, 'enabled' => false],
			'History data' => ['value' => 'Auto', 'labels' => ['Auto', 'History', 'Trends']]
		];
		$this->checkFieldsAttributes($column_default_fields, $column_form);

		// Reassign new fields' values for comparing them in other 'Data' values.
		foreach (['Aggregation function', 'Item', 'Display', 'History data', 'Min',
				'Max', 'Decimal places', 'Thresholds' ] as $field) {
			$column_default_fields[$field]['visible'] = false;
			$column_default_fields[$field]['enabled'] = false;
		}

		foreach (['Host name', 'Text'] as $data) {
			$column_form->fill(['Data' => CFormElement::RELOADABLE_FILL($data)]);

			$column_default_fields['Data']['value'] = ($data === 'Host name') ? 'Host name' : 'Text';
			$column_default_fields['Text']['visible'] = $data === 'Text';
			$column_default_fields['Text']['enabled'] = $data === 'Text';
			$this->checkFieldsAttributes($column_default_fields, $column_form);
		}

		// Check hintboxes.
		$column_form->fill(['Data' => CFormElement::RELOADABLE_FILL('Item value')]);

		// Adding those fields new info icons appear.
		$warning_visibility = [
			'Aggregation function' => ['not used' => false, 'min' => true, 'max' => true, 'avg' => true, 'count' => false,
					'sum' => true, 'first' => false, 'last' => false
			],
			'Display' => ['As is' => false, 'Bar' => true, 'Indicators' => true],
			'History data' => ['Auto' => false, 'History' => false, 'Trends' => true]
		];

		// Check warning and hintbox message, as well as Aggregation function, Min/Max and Thresholds fields visibility.
		foreach ($warning_visibility as $warning_label => $options) {
			if ($warning_label === 'History data' || $warning_label === 'Display') {
				$hint_text = ($warning_label === 'History data')
					? 'This setting applies only to numeric data. Non-numeric data will always be taken from history.'
					: 'With this setting only numeric data will be displayed.';
			}
			else {
				$hint_text = 'With this setting only numeric items will be displayed.';
			}

			$warning_button = $column_form->getLabel($warning_label)->query('xpath:.//button[@data-hintbox]')->one();

			foreach ($options as $option => $visible) {
				$column_form->fill([$warning_label => $option]);
				$this->assertTrue($warning_button->isVisible($visible));

				if ($visible) {
					$warning_button->click();

					// Check hintbox text.
					$hint_dialog = $this->query('xpath://div[@class="overlay-dialogue"]')->one()->waitUntilVisible();
					$this->assertEquals($hint_text, $hint_dialog->getText());

					// Close the hintbox.
					$hint_dialog->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click();
					$hint_dialog->waitUntilNotPresent();
				}

				if ($warning_label === 'Aggregation function' && $option !== 'not used') {
					$this->assertTrue($column_form->getLabel('Time period')->isDisplayed());
				}

				if ($warning_label === 'Display') {
					foreach (['Min', 'Max'] as $field) {
						$this->assertTrue($column_form->getField($field)->isVisible($visible));
					}
				}
			}
		}

		// Check Thresholds table.
		$thresholds_container = $column_form->getFieldContainer('Thresholds');
		$this->assertEquals(['', 'Threshold', 'Action'], $thresholds_container->asTable()->getHeadersText());
		$thresholds_icon = $column_form->getLabel('Thresholds')->query('xpath:.//button[@data-hintbox]')->one();
		$this->assertTrue($thresholds_icon->isVisible());
		$thresholds_container->query('button:Add')->one()->waitUntilClickable()->click();

		$this->checkFieldsAttributes([
				'xpath:.//input[@id="thresholds_0_color"]/..' => ['color' => 'FF465C'],
				'id:thresholds_0_threshold' => ['value' => '', 'maxlength' => 255]
				], $column_form
		);

		$this->assertEquals(2, $thresholds_container->query('button', ['Add', 'Remove'])->all()
				->filter(CElementFilter::CLICKABLE)->count()
		);

		$thresholds_icon->click();
		$hint_dialog = $this->query('xpath://div[@class="overlay-dialogue"]')->one()->waitUntilVisible();
		$this->assertEquals('This setting applies only to numeric data.', $hint_dialog->getText());
		$hint_dialog->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click();
		$hint_dialog->waitUntilNotPresent();
	}

	public static function getCreateData() {
		return [
			// #0 Minimum needed values to create and submit widget.
			[
				[
					'main_fields' => [],
					'column_fields' => [
						[
							'Name' => 'Min values',
							'Data' => 'Item value',
							'Item' => 'Available memory'
						]
					]
				]
			],
			// #1 All fields filled for main form with all tags.
			[
				[
					'main_fields' => [
						'Name' => 'Name of Top hosts widget 😅',
						'Refresh interval' => 'Default (1 minute)',
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'ЗАББИКС Сервер',
						'Show hosts in maintenance' => true,
						'Order' => 'Bottom N',
						'Host limit' => '99'
					],
					'tags' => [
						['name' => 'value', 'value' => '12345', 'operator' => 'Contains'],
						['name' => '@#$%@', 'value' => 'a1b2c3d4', 'operator' => 'Equals'],
						['name' => 'AvF%21', 'operator' => 'Exists'],
						['name' => '_', 'operator' => 'Does not exist'],
						['name' => 'кириллица', 'value' => 'BcDa', 'operator' => 'Does not equal'],
						['name' => 'aaa6 😅', 'value' => 'bbb6 😅', 'operator' => 'Does not contain']
					],
					'column_fields' => [
						[
							'Name' => 'All fields 😅',
							'Data' => 'Item value',
							'Item' => 'Available memory'
						]
					]
				]
			],
			// #2 Change order column for several items.
			[
				[
					'main_fields' => [
						'Name' => 'Several item columns',
						'Order by' => 'duplicated column name'
					],
					'column_fields' => [
						[
							'Name' => 'duplicated column name',
							'Data' => 'Item value',
							'Item' => 'Available memory'
						],
						[
							'Name' => 'duplicated column name',
							'Data' => 'Item value',
							'Item' => 'Available memory in %'
						]
					]
				]
			],
			// #3 Several item columns with different Aggregation function and custom "From" time period.
			[
				[
					'main_fields' => [
						'Name' => 'All available aggregation function'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Name' => 'min',
							'Aggregation function' => 'min',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1m', // minimum time period to display is 1 minute.
							'Item' => 'Available memory'
						],
						[
							'Data' => 'Item value',
							'Name' => 'max',
							'Aggregation function' => 'max',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-20m',
							'Item' => 'Available memory'
						],
						[
							'Data' => 'Item value',
							'Name' => 'avg',
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-20h',
							'Item' => 'Available memory'
						],
						[
							'Data' => 'Item value',
							'Name' => 'count',
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-20d',
							'Item' => 'Available memory'
						],
						[
							'Data' => 'Item value',
							'Name' => 'sum',
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-20w',
							'Item' => 'Available memory'
						],
						[
							'Data' => 'Item value',
							'Name' => 'first',
							'Aggregation function' => 'first',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-20M',
							'Item' => 'Available memory'
						],
						[
							'Data' => 'Item value',
							'Name' => 'last',
							'Aggregation function' => 'last',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-730d', // maximum time period to display is 730 days.
							'Item' => 'Available memory'
						]
					],
					'screenshot' => true
				]
			],
			// #4 Several item columns with different display, custom "From" time period, min/max and history data.
			[
				[
					'main_fields' => [
						'Name' => 'Different display and history data fields'
					],
					'column_fields' => [
						[
							'Name' => 'Column_1',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'As is',
							'History data' => 'History',
							'Aggregation function' => 'min',
							'Time period' => 'Custom',
							'id:time_period_to' => 'now-30m'
						],
						[
							'Name' => 'Column_2',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'As is',
							'Aggregation function' => 'max',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2h',
							'id:time_period_to' => 'now-1h',
							'History data' => 'Trends'
						],
						[
							'Name' => 'Column_3',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Bar',
							'History data' => 'Auto',
							'Min' => '2',
							'Max' => ''
						],
						[
							'Name' => 'Column_4',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Bar',
							'History data' => 'History',
							'Min' => '',
							'Max' => '100'
						],
						[
							'Name' => 'Column_5',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Bar',
							'History data' => 'Trends',
							'Min' => '50',
							'Max' => '100'
						],
						[
							'Name' => 'Column_6',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Indicators',
							'History data' => 'Auto',
							'Min' => '2',
							'Max' => ''
						],
						[
							'Name' => 'Column_7',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Indicators',
							'History data' => 'History',
							'Min' => '',
							'Max' => '100'
						],
						[
							'Name' => 'Column_8',
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
			// #5 Add column with different Base color.
			[
				[
					'main_fields' => [
						'Name' => 'Another base color'
					],
					'column_fields' => [
						[
							'Name' => 'Column name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Base colour' => '039BE5'
						]
					]
				]
			],
			// #6 Add column with Threshold without color change.
			[
				[
					'main_fields' => [
						'Name' => 'One Threshold'
					],
					'column_fields' => [
						[
							'Name' => 'Column with threshold',
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
			// #7 Add several columns with Threshold without color change.
			[
				[
					'main_fields' => [
						'Name' => 'Several Threshold'
					],
					'column_fields' => [
						[
							'Name' => 'Column with some thresholds',
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
			// #8 Add several columns with Threshold with color change and without color.
			[
				[
					'main_fields' => [
						'Name' => 'Several Thresholds with colors'
					],
					'column_fields' => [
						[
							'Name' => 'Thresholds with colors',
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
			// #9 Add Host name columns.
			[
				[
					'main_fields' => [
						'Name' => 'Host name columns'
					],
					'column_fields' => [
						[
							'Data' => 'Host name',
							'Name' => 'This is host name',
							'Base colour' => '039BE5'
						],
						[
							'Name' => 'Host name column 2',
							'Data' => 'Host name'
						],
						[
							'Name' => 'Host name column 3',
							'Data' => 'Item value',
							'Item' => 'Available memory'
						]
					]
				]
			],
			// #10 Add Text columns.
			[
				[
					'main_fields' => [
						'Name' => 'Text columns'
					],
					'column_fields' => [
						[
							'Name' => 'Text column name 1',
							'Data' => 'Text',
							'Text' => 'Here is some text 😅'
						],
						[
							'Data' => 'Text',
							'Text' => 'Here is some text 2',
							'Name' => 'Text column name 2'
						],
						[
							'Data' => 'Text',
							'Text' => 'Here is some text 3',
							'Name' => 'Text column name 3',
							'Base colour' => '039BE5'
						],
						[
							'Name' => 'Text column name 4',
							'Data' => 'Item value',
							'Item' => 'Available memory'
						]
					]
				]
			],
			// #11 Error message adding widget without any column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Widget without columns'
					],
					'main_error' => [
						'Invalid parameter "Columns": cannot be empty.',
						'Invalid parameter "Order by": an integer is expected.'
					]
				]
			],
			// #12 error message adding widget without item column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Widget without item column name'
					],
					'column_fields' => [
						[
							'Data' => 'Host name'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/name": cannot be empty.'
					]
				]
			],
			// #13 Add characters in host limit field.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Host limit error with item column',
						'Host limit' => 'zzz'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory'
						]
					],
					'main_error' => [
						'Invalid parameter "Host limit": value must be one of 1-100.'
					]
				]
			],
			// #14 Add incorrect value to host limit field without item column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Host limit error without item column',
						'Host limit' => '333'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Host name'
						]
					],
					'main_error' => [
						'Invalid parameter "Host limit": value must be one of 1-100.'
					]
				]
			],
			// #15 Color error in host name column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Color error in Host name column'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Host name',
							'Base colour' => '!@#$%^'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/base_color": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #16 Check error adding text column without any value.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Error in empty text column'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Text'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/text": cannot be empty.'
					]
				]
			],
			// #17 Color error in text column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Error in text column color'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Text',
							'Text' => 'Here is some text',
							'Base colour' => '!@#$%^'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/base_color": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #18 Error when there is no item in item column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Error without item in item column'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value'
						]
					],
					'column_error' => [
						'Invalid parameter "/1": the parameter "item" is missing.'
					]
				]
			],
			// #19 Error when time period "From" is below minimum time period.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Incorrect time period'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'min',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-58s'
						]
					],
					'column_error' => [
						'Minimum time period to display is 1 minute.'
					]
				]
			],
			// #20 Error when time period "From" is above maximum time period.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Maximum time period in From field'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'max',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2y-2s'
						]
					],
					'column_error' => [
						'Maximum time period to display is 730 days.'
					]
				]
			],
			// #21 Error when time period "From" is empty.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'From field is empty'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_from' => ''
						]
					],
					'column_error' => [
						'Invalid parameter "/1/From": cannot be empty.'
					]
				]
			],
			// #22 Incorrect value in "From" field.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'From field is with incorrect value'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'a'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/From": a time is expected.'
					]
				]
			],
			// #23 Error when time period "To" is below minimum time period.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Incorrect time period'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_to' => 'now-59m-2s'
						]
					],
					'column_error' => [
						'Minimum time period to display is 1 minute.'
					]
				]
			],
			// #24 Error when time period fields values "From" and "To" are changed and maximum time period is > 730 days.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Incorrect time period'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'first',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-3y-2s',
							'id:time_period_to' => 'now-1y'
						]
					],
					'column_error' => [
						'Maximum time period to display is 730 days.'
					]
				]
			],
			// #25 Error when time period "To" is empty.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'To field is empty'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'last',
							'Time period' => 'Custom',
							'id:time_period_to' => ''
						]
					],
					'column_error' => [
						'Invalid parameter "/1/To": cannot be empty.'
					]
				]
			],
			// #26 Incorrect value passed in "To" field.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'To field is with incorrect value'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_to' => 'b'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/To": a time is expected.'
					]
				]
			],
			// #27 Error when both time period selectors have invalid values.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Both time selectors have invalid values'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_from' => 'b',
							'id:time_period_to' => 'b'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/From": a time is expected.',
						'Invalid parameter "/1/To": a time is expected.'
					]
				]
			],
			// #28 Error when both time period selectors are empty.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Both time selectors have invalid values'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_from' => '',
							'id:time_period_to' => ''
						]
					],
					'column_error' => [
						'Invalid parameter "/1/From": cannot be empty.',
						'Invalid parameter "/1/To": cannot be empty.'
					]
				]
			],
			// #29 Error when widget field is empty.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Both time selectors have invalid values'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'sum',
							'Time period' => 'Widget'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/Widget": cannot be empty.'
					]
				]
			],
			// #30 Error when incorrect min value added.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Incorrect min value'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
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
			// #31 Error when incorrect max value added.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Incorrect max value'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
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
			// #32 Color error in item column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Error in item column color'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Base colour' => '!@#$%^'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/base_color": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #33 Color error when incorrect hexadecimal added in first threshold.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Error in item column threshold color'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
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
						'Invalid parameter "/1/thresholds/1/color": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #34 Color error when incorrect hexadecimal added in second threshold.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Error in item column second threshold color'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
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
						'Invalid parameter "/1/thresholds/2/color": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #35 Error message when incorrect value added to threshold.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Error in item column second threshold color'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
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
			],
			// #36 Spaces in fields' values.
			[
				[
					'trim' => true,
					'main_fields' => [
						'Name' => '            Spaces            ',
						'Host limit' => ' 1 '
					],
					'tags' => [
						['name' => '   tag     ', 'value' => '     value       ', 'operator' => 'Equals']
					],
					'column_fields' => [
						[
							'Name' => '     Text column name with spaces 1     ',
							'Data' => 'Text',
							'Text' => '          Spaces in text          ',
							'Base colour' => 'A5D6A7'
						],
						[
							'Name' => '     Text column name with spaces 2     ',
							'Data' => 'Host name',
							'Base colour' => '0040FF'
						],
						[
							'Name' => '     Text column name with spaces 3     ',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_from' => '         now-2h         ',
							'id:time_period_to' => '         now-1h         ',
							'Display' => 'Bar',
							'Min' => '         2       ',
							'Max' => '         200     ',
							'Decimal places' => ' 2',
							'Thresholds' => [
								[
									'threshold' => '    5       '
								]
							],
							'History data' => 'Trends'
						]
					]
				]
			],
			// #37 User macros in fields' values.
			[
				[
					'main_fields' => [
						'Name' => '{$USERMACRO}'
					],
					'tags' => [
						['name' => '{$USERMACRO}', 'value' => '{$USERMACRO}', 'operator' => 'Equals']
					],
					'column_fields' => [
						[
							'Name' => '{$USERMACRO1}',
							'Data' => 'Text',
							'Text' => '{$USERMACRO2}'
						],
						[
							'Name' => '{$USERMACRO2}',
							'Data' => 'Host name',
							'Base colour' => '0040DD'
						],
						[
							'Name' => '{$USERMACRO3}',
							'Data' => 'Item value',
							'Item' => 'Available memory'
						]
					]
				]
			],
			// #38 Global macros in fields' values.
			[
				[
					'main_fields' => [
						'Name' => '{HOST.HOST}'
					],
					'tags' => [
						['name' => '{HOST.NAME}', 'value' => '{ITEM.NAME}', 'operator' => 'Equals']
					],
					'column_fields' => [
						[
							'Name' => '{INVENTORY.ALIAS}',
							'Data' => 'Text',
							'Text' => '{HOST.DNS}'
						],
						[
							'Name' => '{INVENTORY.ALIAS}',
							'Data' => 'Host name',
							'Base colour' => '0040DD'
						],
						[
							'Name' => '{HOST.IP}',
							'Data' => 'Item value',
							'Item' => 'Available memory'
						]
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
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_create);
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
			$this->setTags($data['tags']);
		}

		// Take a screenshot to test draggable object position of columns.
		if (array_key_exists('screenshot', $data)) {
			$this->page->removeFocus();
			$this->assertScreenshot($form->query('id:list_columns')->waitUntilPresent()->one(), 'Top hosts columns');
		}

		$form->fill($data['main_fields']);
		$form->submit();
		$this->page->waitUntilReady();

		// Trim trailing and leading spaces in expected values before comparison.
		if (CTestArrayHelper::get($data, 'trim', false)) {
			$data = CTestArrayHelper::trim($data);
		}

		// Check error message in main widget form.
		if (array_key_exists('main_error', $data)) {
			$this->assertMessage(TEST_BAD, null, $data['main_error']);
		}

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			COverlayDialogElement::find()->one()->close();
			$dashboard->save();

			// Check message that widget added.
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
			$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());
		}
		else {
			// Make sure that the widget is present before saving the dashboard.
			$header = CTestArrayHelper::get($data['main_fields'], 'Name', self::DEFAULT_WIDGET_NAME);
			$dashboard->getWidget($header);
			$dashboard->save();

			// Check message that dashboard saved.
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check widget amount that it is added.
			$this->assertEquals($old_widget_count + 1, $dashboard->getWidgets()->count());
			$this->checkWidget($header, $data, 'create');
		}
	}

	/**
	 * Top Hosts widget simple update without any field change.
	 */
	public function testDashboardTopHostsWidget_SimpleUpdate() {
		// Hash before simple update.
		$old_hash = CDBHelper::getHash($this->sql);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_update);
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
			// #0 Incorrect threshold color.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Name' => 'Incorrect threshold color',
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
						'Invalid parameter "/1/thresholds/1/color": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #1 Incorrect min value.
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
			// #2 Incorrect max value.
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
			// #3 Error message when update Host limit incorrectly.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Host limit' => '0'
					],
					'main_error' => [
						'Invalid parameter "Host limit": value must be one of 1-100.'
					]
				]
			],
			// #4 Time period "From" is below minimum time period.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Aggregation function' => 'min',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-58s'
						]
					],
					'column_error' => [
						'Minimum time period to display is 1 minute.'
					]
				]
			],
			// #5 Time period "From" is above maximum time period.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Aggregation function' => 'max',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-63072002'
						]
					],
					'column_error' => [
						'Maximum time period to display is 730 days.'
					]
				]
			],
			// #6 Empty "From" field.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_from' => ''
						]
					],
					'column_error' => [
						'Invalid parameter "/1/From": cannot be empty.'
					]
				]
			],
			// #7 Error when time period "From" is invalid.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'a'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/From": a time is expected.'
					]
				]
			],
			// #8 Error when time period "To" is below minimum time period.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_to' => 'now-3542s'
						]
					],
					'column_error' => [
						'Minimum time period to display is 1 minute.'
					]
				]
			],
			// #9 Error when time period fields values "From" and "To" are changed and maximum time period is > 730 days.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Aggregation function' => 'first',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-26M',
							'id:time_period_to' => 'now-1M'
						]
					],
					'column_error' => [
						'Maximum time period to display is 730 days.'
					]
				]
			],
			// #10 Error when time period "To" is empty.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_to' => ''
						]
					],
					'column_error' => [
						'Invalid parameter "/1/To": cannot be empty.'
					]
				]
			],
			// #11 Error when time period "To" is invalid.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_to' => 'b'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/To": a time is expected.'
					]
				]
			],
			// #12 Error when both time period selectors have invalid values.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_from' => 'a',
							'id:time_period_to' => 'b'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/From": a time is expected.',
						'Invalid parameter "/1/To": a time is expected.'
					]
				]
			],
			// #13 Error when both time period selectors are empty.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_from' => '',
							'id:time_period_to' => ''
						]
					],
					'column_error' => [
						'Invalid parameter "/1/From": cannot be empty.',
						'Invalid parameter "/1/To": cannot be empty.'
					]
				]
			],
			// #14 Error when widget field is empty.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Aggregation function' => 'sum',
							'Time period' => 'Widget'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/Widget": cannot be empty.'
					]
				]
			],
			// #15 No item error in column.
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
			// #16 Incorrect base color.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Base colour' => '#$%$@@'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/base_color": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #17 Update all main fields.
			[
				[
					'main_fields' => [
						'Name' => 'Updated main fields',
						'Refresh interval' => '2 minutes',
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'ЗАББИКС Сервер',
						'Show hosts in maintenance' => true,
						'Order' => 'Bottom N',
						'Order by' => 'test update column 2',
						'Host limit' => '2'
					]
				]
			],
			// #18 Update first item column to Text column and add some values.
			[
				[
					'main_fields' => [
						'Name' => 'Updated column type to text',
						'Show hosts in maintenance' => false
					],
					'column_fields' => [
						[
							'Name' => 'Text column changed',
							'Data' => 'Text',
							'Text' => 'some text 😅',
							'Base colour' => '039BE5'
						]
					]
				]
			],
			// #19 Update first column to Host name column and add some values.
			[
				[
					'main_fields' => [
						'Name' => 'Updated column type to host name'
					],
					'column_fields' => [
						[
							'Name' => 'Host name column update',
							'Data' => 'Host name',
							'Base colour' => 'FF8F00'
						]
					]
				]
			],
			// #20 Update first column to Item column and check time From/To.
			[
				[
					'main_fields' => [
						'Name' => 'Time From/To'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'min',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-120s',
							'id:time_period_to' => 'now-1m'
						]
					]
				]
			],
			// #21 Update time From/To.
			[
				[
					'main_fields' => [
						'Name' => 'Time From/To'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'max',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1w',
							'id:time_period_to' => 'now-1d'
						]
					]
				]
			],
			// #22 Update time From/To (day before yesterday).
			[
				[
					'main_fields' => [
						'Name' => 'Time shift 10h'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2d/d',
							'id:time_period_to' => 'now-2d/d'
						]
					]
				]
			],
			// #23 Update time From/To.
			[
				[
					'main_fields' => [
						'Name' => 'Time shift 10w'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-3M',
							'id:time_period_to' => 'now-1M'
						]
					]
				]
			],
			// #24 Spaces in fields' values.
			[
				[
					'trim' => true,
					'main_fields' => [
						'Name' => '            Updated Spaces            ',
						'Host limit' => ' 1 '
					],
					'tags' => [
						['name' => '   tag     ', 'value' => '     value       ', 'operator' => 'Equals']
					],
					'column_fields' => [
						[
							'Name' => '     Text column name with spaces 1     ',
							'Data' => 'Text',
							'Text' => '         Spaces in text         ',
							'Base colour' => 'A5D6A7'
						],
						[
							'Name' => '     Text column name with spaces2      ',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_from' => '  now-2d/d  ',
							'id:time_period_to' => '  now-2d/d  ',
							'Display' => 'Bar',
							'Min' => '         2       ',
							'Max' => '         200     ',
							'Decimal places' => ' 2',
							'Thresholds' => [
								[
									'action' => USER_ACTION_UPDATE,
									'index' => 0,
									'threshold' => '    5       '
								],
								[
									'action' => USER_ACTION_UPDATE,
									'index' => 1,
									'threshold' => '    10      '
								]
							],
							'History data' => 'Trends'
						]
					]
				]
			],
			// #25 User macros in fields' values.
			[
				[
					'main_fields' => [
						'Name' => '{$UPDATED_USERMACRO}'
					],
					'tags' => [
						['name' => '{$USERMACRO}', 'value' => '{$USERMACRO}', 'operator' => 'Equals']
					],
					'column_fields' => [
						[
							'Name' => '{$USERMACRO1}',
							'Data' => 'Text',
							'Text' => '{$USERMACRO3}'
						],
						[
							'Name' => '{$USERMACRO2}',
							'Data' => 'Item value',
							'Item' => 'Available memory'
						]
					]
				]
			],
			// #26 Global macros in fields' values.
			[
				[
					'main_fields' => [
						'Name' => '{HOST.HOST} updated'
					],
					'tags' => [
						['name' => '{HOST.NAME}', 'value' => '{ITEM.NAME}', 'operator' => 'Equals']
					],
					'column_fields' => [
						[
							'Name' => '{INVENTORY.ALIAS}',
							'Data' => 'Text',
							'Text' => '{HOST.DNS}'
						],
						[
							'Name' => '{HOST.IP}',
							'Data' => 'Item value',
							'Item' => 'Available memory'
						]
					]
				]
			],
			// #27 Update item column adding new values and fields.
			[
				[
					'main_fields' => [
						'Name' => 'Updated values for item column 😅'
					],
					'column_fields' => [
						[
							'Data' => 'Host name',
							'Name' => 'Only name changed'
						],
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Indicators',
							'History data' => 'Trends',
							'Min' => '50',
							'Max' => '100',
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-3d',
							'id:time_period_to' => 'now-1d',
							'Base colour' => '039BE5',
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
						['name' => 'aaa6 😅', 'value' => 'bbb6 😅', 'operator' => 'Does not contain']
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
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			// Hash before update.
			$old_hash = CDBHelper::getHash($this->sql);
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_update);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->getWidget(self::$updated_name)->edit();

		// Update column.
		if (array_key_exists('column_fields', $data)) {
			$this->fillColumnForm($data, 'update');
		}

		if (array_key_exists('tags', $data)) {
			$this->setTags($data['tags']);
		}

		if (array_key_exists('main_fields', $data)) {
			$form->fill($data['main_fields']);
			$form->submit();
			$this->page->waitUntilReady();
		}

		// Trim trailing and leading spaces in expected values before comparison.
		if (CTestArrayHelper::get($data, 'trim', false)) {
			$data = CTestArrayHelper::trim($data);
		}

		// Check error message in main widget form.
		if (array_key_exists('main_error', $data)) {
			$this->assertMessage(TEST_BAD, null, $data['main_error']);
		}

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			COverlayDialogElement::find()->one()->close();
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Compare old hash and new one.
			$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
		}
		else {
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
	}

	/**
	 * Delete top hosts widget.
	 */
	public function testDashboardTopHostsWidget_Delete() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_delete);
		$dashboard = CDashboardElement::find()->one()->edit();
		$name = 'Top hosts delete';
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
			// #0 Remove column.
			[
				[
					'table_id' => 'id:list_columns',
					'remove_selector' => 'xpath:(.//button[@name="remove"])[2]'
				]
			],
			// #1 Remove tag.
			[
				[
					'table_id' => 'id:tags_table_tags',
					'remove_selector' => 'id:tags_0_remove'
				]
			],
			// #2 Remove threshold.
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
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_remove);
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
	 * @param string    $header        widget name
	 * @param array     $data          values from data provider
	 * @param string    $action        check after creation or update
	 */
	protected function checkWidget($header, $data, $action) {
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
	 * Create or update "Top hosts" widget.
	 *
	 * @param array     $data      values from data provider
	 * @param string    $action    create or update action
	 */
	protected function fillColumnForm($data, $action) {
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
				$this->query('xpath://div/h4[text()="'.$selector.'"]/../button[@title="Close"]')->one()->click();
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
					'main_fields' => [
						'Name' => 'Simple'
					],
					'column_fields' => [
						[
							'Name' => 'test column 1',
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
					'main_fields' => [
						'Name' => 'Bar'
					],
					'column_fields' => [
						[
							'Name' => 'test column 1',
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
					'main_fields' => [
						'Name' => 'Bar threshold'
					],
					'column_fields' => [
						[
							'Name' => 'test column 1',
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
					'main_fields' => [
						'Name' => 'Indicators'
					],
					'column_fields' => [
						[
							'Name' => 'test column 1',
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
					'main_fields' => [
						'Name' => 'Indicators threshold'
					],
					'column_fields' => [
						[
							'Name' => 'test column 1',
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
					'main_fields' => [
						'Name' => 'All three'
					],
					'column_fields' => [
						[
							'Name' => 'column 0',
							'Data' => 'Item value',
							'Item' => '1_item'
						],
						[
							'Name' => 'column 1',
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
							'Name' => 'column 2',
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
							'Name' => 'column 3',
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
							'Name' => 'column 4',
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
		$this->createTopHostsWidget($data, self::$dashboard_screenshots);

		// Check widget added and assert screenshots.
		$element = CDashboardElement::find()->one()->getWidget($data['main_fields']['Name']);
		$this->assertScreenshot($element, $data['screen_name']);
	}

	public static function getCheckTextItemsData() {
		return [
			// #0 Text item - value displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Text value item'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_text'
						]
					],
					'text' => "column1\nText for text item"
				]
			],
			// #1 Text item, history data Trends - value displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Text trends history'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_text',
							'History data' => 'Trends'
						]
					],
					'text' => "column1\nText for text item"
				]
			],
			// #2 Text item, display Bar - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Text display bar'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_text',
							'Display' => 'Bar'
						]
					],
					'text' => "column1\nNo data found."
				]
			],
			// #3 Text item, display Indicators - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Text display indicators'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_text',
							'Display' => 'Indicators'
						]
					],
					'text' => "column1\nNo data found."
				]
			],
			// #4 Text item, Aggregation function max - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Text aggregation function'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_text',
							'Aggregation function' => 'max'
						]
					],
					'text' => "column1\nNo data found."
				]
			],
			// #5 Text item, Threshold - value is displayed ignoring thresholds.
			[
				[
					'main_fields' => [
						'Name' => 'Text threshold'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_text',
							'Thresholds' => [
								[
									'threshold' => '10'
								]
							]
						]
					],
					'text' => "column1\nText for text item"
				]
			],
			// #6 Log item - value displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Log value item'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_log'
						]
					],
					'text' => "column1\nLogs for text item"
				]
			],
			// #7 Log item, history data Trends - value displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Log trends history'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_log',
							'History data' => 'Trends'
						]
					],
					'text' => "column1\nLogs for text item"
				]
			],
			// #8 Log item, display Bar - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Log display bar'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_log',
							'Display' => 'Bar'
						]
					],
					'text' => "column1\nNo data found."
				]
			],
			// #9 Log item, display Indicators - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Log display indicators'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_log',
							'Display' => 'Indicators'
						]
					],
					'text' => "column1\nNo data found."
				]
			],
			// #10 Log item, Aggregation function max - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Log aggregation function'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_log',
							'Aggregation function' => 'max'
						]
					],
					'text' => "column1\nNo data found."
				]
			],
			// #11 Log item, Threshold - value is displayed ignoring thresholds.
			[
				[
					'main_fields' => [
						'Name' => 'Log threshold'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_log',
							'Thresholds' => [
								[
									'threshold' => '10'
								]
							]
						]
					],
					'text' => "column1\nLogs for text item"
				]
			],
			// #12 Char item - value displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Char value item'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_char'
						]
					],
					'text' => "column1\ncharacters_here"
				]
			],
			// #13 Char item, history data Trends - value displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Char trends history'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_char',
							'History data' => 'Trends'
						]
					],
					'text' => "column1\ncharacters_here"
				]
			],
			// #14 Char item, display Bar - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Char display bar'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_char',
							'Display' => 'Bar'
						]
					],
					'text' => "column1\nNo data found."
				]
			],
			// #15 Char item, display Indicators - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Char display indicators'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_char',
							'Display' => 'Indicators'
						]
					],
					'text' => "column1\nNo data found."
				]
			],
			// #16 Char item, Aggregation function max - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Char aggregation function'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_char',
							'Aggregation function' => 'max'
						]
					],
					'text' => "column1\nNo data found."
				]
			],
			// #17 Char item, Threshold - value is displayed ignoring thresholds.
			[
				[
					'main_fields' => [
						'Name' => 'Char threshold'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_char',
							'Thresholds' => [
								[
									'threshold' => '10'
								]
							]
						]
					],
					'text' => "column1\ncharacters_here"
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
		$this->createTopHostsWidget($data, self::$dashboard_text_items);

		// Check if value displayed in column table.
		$this->assertEquals($data['text'], CDashboardElement::find()->one()->getWidget($data['main_fields']['Name'])
				->getContent()->getText()
		);
	}

	public static function getWidgetTimePeriodData() {
		return [
			// Widget with default configuration.
			[
				[
					'widgets' => [
						[
							'widget_type' => 'Top hosts',
							'main_fields' => [
								'Name' => 'Default widget'
							],
							'column_fields' => [
								[
									'Name' => 'Column default',
									'Item' => 'Available memory'
								]
							]
						]
					]
				]
			],
			// Widget with "Custom" time period configuration.
			[
				[
					'widgets' => [
						[
							'widget_type' => 'Top hosts',
							'main_fields' => [
								'Name' => 'Item widget with "Custom" time period'
							],
							'column_fields' => [
								[
									'Name' => 'Column with "Custom" time period',
									'Item' => 'Available memory',
									'Aggregation function' => 'min',
									'Time period' => 'Custom'
								]
							]
						]
					]
				]
			],
			// Two widgets with "Widget" and "Custom" time period configuration.
			[
				[
					'widgets' => [
						[
							'widget_type' => 'Graph (classic)',
							'main_fields' => [
								'Name' => 'Graph widget with "Custom" time period',
								'Graph' => 'Linux: System load',
								'Time period' => 'Custom',
								'id:time_period_from' => 'now-5400',
								'id:time_period_to' => 'now-1800'
							]
						],
						[
							'widget_type' => 'Top hosts',
							'main_fields' => [
								'Name' => 'Item widget with "Widget" time period'
							],
							'column_fields' => [
								[
									'Name' => 'Column with "Widget" time period',
									'Item' => 'Available memory',
									'Aggregation function' => 'max',
									'Time period' => 'Widget',
									'Widget' => 'Graph widget with "Custom" time period'
								]
							]
						]
					]
				]
			],
			[
				[
					'widgets' => [
						[
							'widget_type' => 'Graph (classic)',
							'main_fields' => [
								'Name' => 'Graph widget with "Custom" time period',
								'Graph' => 'Linux: System load',
								'Time period' => 'Custom',
								'id:time_period_from' => 'now-5400',
								'id:time_period_to' => 'now-1800'
							]
						],
						[
							'widget_type' => 'Top hosts',
							'main_fields' => [
								'Name' => 'Item widget with "Widget" time period'
							],
							'column_fields' => [
								[
									'Name' => 'Column default',
									'Item' => 'Available memory'
								],
								[
									'Name' => 'Column with "Widget" time period',
									'Item' => 'Available memory',
									'Aggregation function' => 'avg',
									'Time period' => 'Widget',
									'Widget' => 'Graph widget with "Custom" time period'
								],
								[
									'Name' => 'Column with "Custom" time period',
									'Item' => 'Available memory',
									'Aggregation function' => 'count',
									'Time period' => 'Custom'
								]
							]
						]
					]
				]
			],
			// Top hosts widget with time period "Dashboard" (enabled zoom filter).
			[
				[
					'widgets' => [
						[
							'widget_type' => 'Top hosts',
							'main_fields' => [
								'Name' => 'Top hosts widget with "Dashboard" time period'
							],
							'column_fields' => [
								[
									'Name' => 'Column with "Dashboard" time period',
									'Item' => 'Available memory in %',
									'Aggregation function' => 'sum',
									'Time period' => 'Dashboard'
								]
							]
						]
					],
					'zoom_filter' => true,
					'filter_layout' => true
				]
			],
			// Two widgets with time period "Dashboard" and "Custom" time period configuration.
			[
				[
					'widgets' => [
						[
							'widget_type' => 'Top hosts',
							'main_fields' => [
								'Name' => 'Top hosts widget with "Custom" time period'
							],
							'column_fields' => [
								[
									'Name' => 'Column with "Custom" time period',
									'Item' => 'Available memory in %',
									'Aggregation function' => 'first',
									'Time period' => 'Custom',
									'id:time_period_from' => 'now-2y',
									'id:time_period_to' => 'now-1y'
								]
							]
						],
						[
							'widget_type' => 'Action log',
							'main_fields' => [
								'Name' => 'Action log widget with Dashboard time period' // time period default state.
							]
						]
					],
					'zoom_filter' => true
				]
			],
			[
				[
					'widgets' => [
						[
							'widget_type' => 'Graph (classic)',
							'main_fields' => [
								'Name' => 'Graph widget with "Custom" time period',
								'Graph' => 'Linux: System load',
								'Time period' => 'Custom',
								'id:time_period_from' => 'now-5400',
								'id:time_period_to' => 'now-1800'
							]
						],
						[
							'widget_type' => 'Top hosts',
							'main_fields' => [
								'Name' => 'Top hosts widget with "Custom" time period'
							],
							'column_fields' => [
								[
									'Name' => 'Column with "Custom" time period',
									'Item' => 'Available memory in %',
									'Aggregation function' => 'first',
									'Time period' => 'Custom',
									'id:time_period_from' => 'now-2y',
									'id:time_period_to' => 'now-1y'
								],
								[
									'Name' => 'Column with "Dashboard" time period',
									'Item' => 'Available memory in %',
									'Aggregation function' => 'last',
									'Time period' => 'Dashboard'
								],
								[
									'Name' => 'Column default',
									'Item' => 'Available memory'
								],
								[
									'Name' => 'Column with "Widget" time period',
									'Item' => 'Available memory',
									'Aggregation function' => 'min',
									'Time period' => 'Widget',
									'Widget' => 'Graph widget with "Custom" time period'
								]
							]
						]
					],
					'zoom_filter' => true
				]
			]
		];
	}

	/**
	 * Check that dashboard time period filter appears regarding widget configuration.
	 *
	 * @dataProvider getWidgetTimePeriodData
	 */
	public function testDashboardTopHostsWidget_TimePeriodFilter($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_zoom);
		$dashboard = CDashboardElement::find()->one();

		foreach ($data['widgets'] as $widget) {
			$form = $dashboard->edit()->addWidget()->asForm();
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL($widget['widget_type'])]);

			// Add new column.
			if (array_key_exists('column_fields', $widget)) {
				$this->fillColumnForm($widget, 'create');
			}

			$form->fill($widget['main_fields']);
			$form->submit();
			COverlayDialogElement::ensureNotPresent();
			$this->page->waitUntilReady();
			$dashboard->save();
		}

		$dashboard->waitUntilReady();
		$this->assertMessage('Dashboard updated');

		if (array_key_exists('zoom_filter', $data)) {
			// Check that zoom filter tab link is valid.
			$this->assertTrue($this->query('xpath:.//a[@href="#tab_1"]')->one()->isValid());

			// Check zoom filter layout.
			if (array_key_exists('filter_layout', $data)) {
				$filter = CFilterElement::find()->one();
				$this->assertEquals('Last 1 hour', $filter->getSelectedTabName());
				$this->assertEquals('Last 1 hour', $filter->query('link:Last 1 hour')->one()->getText());

				// Check time selector fields layout.
				foreach (['id:from' => 'now-1h', 'id:to' => 'now'] as $selector => $value) {
					$input = $this->query($selector)->one();
					$this->assertEquals($value, $input->getValue());
					$this->assertEquals(255, $input->getAttribute('maxlength'));
				}

				$buttons = [
					'xpath://button[contains(@class, "btn-time-left")]' => true,
					'xpath://button[contains(@class, "btn-time-right")]' => false,
					'button:Zoom out' => true,
					'button:Apply' => true,
					'id:from_calendar' => true,
					'id:to_calendar' => true
				];
				foreach ($buttons as $selector => $enabled) {
					$this->assertTrue($this->query($selector)->one()->isEnabled($enabled));
				}

				$this->assertEquals(1, $this->query('button:Apply')->all()->filter(CElementFilter::CLICKABLE)->count());
				$this->assertTrue($filter->isExpanded());
			}
		}
		else {
			$this->assertFalse($this->query('xpath:.//a[@href="#tab_1"]')->one(false)->isValid());
		}

		// Clear particular dashboard for next test case.
		DBexecute('DELETE FROM widget'.
				' WHERE dashboard_pageid'.
				' IN (SELECT dashboard_pageid'.
					' FROM dashboard_page'.
					' WHERE dashboardid='.self::$dashboard_zoom.
				')'
		);
	}

	public static function getThresholdData() {
		return [
			// Numeric (unsigned) item without data.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Threshold and numeric item but without data',
							'Item' => 'Item with type of information - numeric (unsigned)',
							'Thresholds' => [
								['color' => 'AABBCC', 'threshold' => '1'],
								['color' => 'CCBBAA', 'threshold' => '2']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Numeric (float) item without data.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Two thresholds and numeric item without data',
							'Item' => 'Item with type of information - numeric (float)',
							'Thresholds' => [
								['color' => 'AABBCC', 'threshold' => '0'],
								['color' => 'CCDDAA', 'threshold' => '1.01']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Log) item without data and with aggregation function min.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (log) item without data and with aggregation function min',
							'Item' => 'Item with type of information - Log',
							'Aggregation function' => 'min',
							'Thresholds' => [
								['color' => '7E57C2', 'threshold' => '0']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Character) item without data and with aggregation function max.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Character) item without data and with aggregation function max',
							'Item' => 'Item with type of information - Character',
							'Aggregation function' => 'max',
							'Thresholds' => [
								['color' => '7E57C2', 'threshold' => '1']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Text) item without data and with aggregation function avg.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Text) item without data and with aggregation function avg',
							'Item' => 'Item with type of information - Text',
							'Aggregation function' => 'avg',
							'Thresholds' => [
								['color' => '7E57C2', 'threshold' => '-1']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Log) item without data and with aggregation function sum.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (log) item without data and with aggregation function sum',
							'Item' => 'Item with type of information - Log',
							'Aggregation function' => 'sum',
							'Thresholds' => [
								['color' => '7E57C2', 'threshold' => '0']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Character) item without data and with aggregation function first.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Character) item without data and with aggregation function first',
							'Item' => 'Item with type of information - Character',
							'Aggregation function' => 'first',
							'Thresholds' => [
								['color' => '7E57C2', 'threshold' => '-1']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Text) item without data and with aggregation function last.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Text) item without data and with aggregation function last',
							'Item' => 'Item with type of information - Text',
							'Aggregation function' => 'last',
							'Thresholds' => [
								['color' => '7E57C2', 'threshold' => '0.00']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Log) item without data and with aggregation function not used.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (log) item without data and with aggregation function not used',
							'Item' => 'Item with type of information - Log',
							'Thresholds' => [
								['color' => '7E57C2', 'threshold' => '0']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Log) item without data but with aggregation function count (return 0).
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Log) item without data but with aggregation function count',
							'Item' => 'Item with type of information - Character',
							'Aggregation function' => 'count',
							'Thresholds' => [
								['color' => '7E57C2', 'threshold' => '1']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Character) item without data but with aggregation function count (return 0).
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Character) item without data but with aggregation function count',
							'Item' => 'Item with type of information - Character',
							'Aggregation function' => 'count',
							'Thresholds' => [
								['color' => '7E57C2', 'threshold' => '1']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Text) item without data but with aggregation function count (return 0).
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Text) item without data but with aggregation function count',
							'Item' => 'Item with type of information - Text',
							'Aggregation function' => 'count',
							'Thresholds' => [
								['color' => '7E57C2', 'threshold' => '1']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Log) item without data but with aggregation function count (return 0) and threshold equals 0.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (log) item without data but with aggregation function count and threshold that match 0',
							'Item' => 'Item with type of information - Log',
							'Aggregation function' => 'count',
							'Thresholds' => [
								['color' => '7E57C2', 'threshold' => '0']
							]
						]
					]
				]
			],
			// Non-numeric (Character) item without data but with aggregation function count (return 0) and threshold equals 0.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Character) item without data but with aggregation function count and threshold that match 0',
							'Item' => 'Item with type of information - Character',
							'Aggregation function' => 'count',
							'Thresholds' => [
								['color' => '7E57C2', 'threshold' => '0']
							]
						]
					]
				]
			],
			// Non-numeric (Text) item without data but with aggregation function count (return 0) and threshold equals 0.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Text) item without data but with aggregation function count and threshold that match 0',
							'Item' => 'Item with type of information - Text',
							'Aggregation function' => 'count',
							'Thresholds' => [
								['color' => '7E57C2', 'threshold' => '0']
							]
						]
					]
				]
			],
			// Numeric (unsigned) item with data and aggregation function not used.
			[
				[
					'numeric' => true,
					'column_fields' => [
						[
							'Name' => 'Thresholds and numeric (unsigned) item',
							'Item' => 'Item with type of information - numeric (unsigned)',
							'Thresholds' => [
								['color' => 'AABBCC', 'threshold' => '1'],
								['color' => 'CCDDAA', 'threshold' => '2']
							]
						]
					],
					'value' => '1'
				]
			],
			// Numeric (float) item with data and aggregation function not used.
			[
				[
					'numeric' => true,
					'column_fields' => [
						[
							'Name' => 'Thresholds and numeric (float) item',
							'Item' => 'Item with type of information - numeric (float)',
							'Thresholds' => [
								['color' => 'AABBCC', 'threshold' => '1.01'],
								['color' => 'CCDDAA', 'threshold' => '2.01']
							]
						]
					],
					'value' => '1.02'
				]
			],
			// Numeric (unsigned) item with data and aggregation function count.
			[
				[
					'numeric' => true,
					'column_fields' => [
						[
							'Name' => 'Numeric (unsigned) item with thresholds and aggregation function count',
							'Item' => 'Item with type of information - numeric (unsigned)',
							'Aggregation function' => 'count',
							'Thresholds' => [
								['color' => 'AABBCC', 'threshold' => '1'],
								['color' => 'CCDDAA', 'threshold' => '2']
							]
						]
					],
					'value' => '1'
				]
			],
			// Numeric (float) item with data and aggregation function count.
			[
				[
					'numeric' => true,
					'column_fields' => [
						[
							'Name' => 'Numeric (float) item with thresholds and aggregation function count',
							'Item' => 'Item with type of information - numeric (float)',
							'Aggregation function' => 'count',
							'Thresholds' => [
								['color' => 'AABBCC', 'threshold' => '0.99'],
								['color' => 'CCDDAA', 'threshold' => '1.99']
							]
						]
					],
					'value' => '1.02'
				]
			],
			// Non-numeric (Text) item with data and aggregation function count.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Thresholds and non-nmeric (Text) item',
							'Item' => 'Item with type of information - Text',
							'Aggregation function' => 'count',
							'Thresholds' => [
								['color' => 'DDAAFF', 'threshold' => '1'],
								['color' => 'FFDDAA', 'threshold' => '2']
							]
						]
					],
					'value' => 'test'
				]
			],
			// Non-numeric (Log) item with data and aggregation function count.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Thresholds and non-nmeric (Log) item',
							'Item' => 'Item with type of information - Log',
							'Aggregation function' => 'count',
							'Thresholds' => [
								['color' => 'DDAAFF', 'threshold' => '1'],
								['color' => 'FFDDAA', 'threshold' => '2']
							]
						]
					],
					'value' => 'test'
				]
			],
			// Non-numeric (Character) item with data and aggregation function count.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Thresholds and non-nmeric (Character) item',
							'Item' => 'Item with type of information - Character',
							'Aggregation function' => 'count',
							'Thresholds' => [
								['color' => 'DDAAFF', 'threshold' => '1'],
								['color' => 'FFDDAA', 'threshold' => '2']
							]
						]
					],
					'value' => 'test'
				]
			],
			// Numeric (unsigned) item with data and aggregation function min.
			[
				[
					'numeric' => true,
					'column_fields' => [
						[
							'Name' => 'Numeric (unsigned) item with threshold and aggregation function min',
							'Item' => 'Item with type of information - numeric (unsigned)',
							'Aggregation function' => 'min',
							'Thresholds' => [
								['color' => 'AABBCC', 'threshold' => '1'],
								['color' => 'CCDDAA', 'threshold' => '2']
							]
						]
					],
					'expected_color' => 'AABBCC',
					'value' => '1'
				]
			],
			// Numeric (float) item with data and aggregation function max.
			[
				[
					'numeric' => true,
					'column_fields' => [
						[
							'Name' => 'Numeric (float) item with threshold and aggregation function max',
							'Item' => 'Item with type of information - numeric (float)',
							'Aggregation function' => 'max',
							'Thresholds' => [
								['color' => '7CB342', 'threshold' => '0.00'],
								['color' => 'FFF9C4', 'threshold' => '1.01']
							]
						]
					],
					'expected_color' => 'FFF9C4',
					'value' => '1.01'
				]
			],
			// Numeric (unsigned) item with data and aggregation function avg.
			[
				[
					'numeric' => true,
					'column_fields' => [
						[
							'Name' => 'Numeric (unsigned) item with threshold and aggregation function avg',
							'Item' => 'Item with type of information - numeric (unsigned)',
							'Aggregation function' => 'avg',
							'Thresholds' => [
								['color' => '7CB342', 'threshold' => '1'],
								['color' => 'FFF9C4', 'threshold' => '2']
							]
						]
					],
					'expected_color' => '7CB342',
					'value' => '1'
				]
			],
			// Numeric (float) item with data and aggregation function sum.
			[
				[
					'numeric' => true,
					'column_fields' => [
						[
							'Name' => 'Numeric (float) item with threshold and aggregation function sum',
							'Item' => 'Item with type of information - numeric (float)',
							'Aggregation function' => 'sum',
							'Thresholds' => [
								['color' => 'D32F2F', 'threshold' => '1.11'],
								['color' => '8BC34A', 'threshold' => '2.22']
							]
						]
					],
					'expected_color' => '8BC34A',
					'value' => '2.22'
				]
			],
			// Numeric (unsigned) item with data and aggregation function first.
			[
				[
					'numeric' => true,
					'column_fields' => [
						[
							'Name' => 'Numeric (unsigned) item with threshold and aggregation function first',
							'Item' => 'Item with type of information - numeric (unsigned)',
							'Aggregation function' => 'first',
							'Thresholds' => [
								['color' => 'D32F2F', 'threshold' => '0'],
								['color' => '8BC34A', 'threshold' => '1']
							]
						]
					],
					'expected_color' => 'D32F2F',
					'value' => '0'
				]
			],
			// Numeric (float) item with data and aggregation function last.
			[
				[
					'numeric' => true,
					'column_fields' => [
						[
							'Name' => 'Numeric (float) item with threshold and aggregation function last',
							'Item' => 'Item with type of information - numeric (float)',
							'Aggregation function' => 'last',
							'Thresholds' => [
								['color' => 'D32F2F', 'threshold' => '-1.00'],
								['color' => '8BC34A', 'threshold' => '0.00']
							]
						]
					],
					'expected_color' => '8BC34A',
					'value' => '0'
				]
			],
			// Non-numeric (Log) item with data and aggregation function min.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Thresholds and non-nmeric (Log) item with aggregation function min',
							'Item' => 'Item with type of information - Log',
							'Aggregation function' => 'min',
							'Thresholds' => [
								['color' => 'DDAAFF', 'threshold' => '0']
							]
						]
					],
					'value' => 'test',
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Character) item with data and aggregation function max.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Thresholds and non-nmeric (Character) item with aggregation function max',
							'Item' => 'Item with type of information - Character',
							'Aggregation function' => 'max',
							'Thresholds' => [
								['color' => 'D32F2F', 'threshold' => '-1'],
								['color' => '8BC34A', 'threshold' => '0']
							]
						]
					],
					'value' => 'test',
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Text) item with data and aggregation function avg.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Thresholds and non-nmeric (Text) item with aggregation function avg',
							'Item' => 'Item with type of information - Text',
							'Aggregation function' => 'avg',
							'Thresholds' => [
								['color' => 'D1C4E9', 'threshold' => '1'],
								['color' => '80CBC4', 'threshold' => '2']
							]
						]
					],
					'value' => 'test',
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Log) item with data and aggregation function sum.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Thresholds and non-nmeric (Log) item with aggregation function sum',
							'Item' => 'Item with type of information - Log',
							'Aggregation function' => 'sum',
							'Thresholds' => [
								['color' => 'D1C4E9', 'threshold' => '1'],
								['color' => '80CBC4', 'threshold' => '2']
							]
						]
					],
					'value' => 'test',
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Character) item with data and aggregation function first.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Thresholds and non-nmeric (Character) item with aggregation function first',
							'Item' => 'Item with type of information - Character',
							'Aggregation function' => 'first',
							'Thresholds' => [
								['color' => 'D1C4E9', 'threshold' => '0']
							]
						]
					],
					'value' => 'test',
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Text) item with data and aggregation function last.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Thresholds and non-nmeric (Text) item with aggregation function last',
							'Item' => 'Item with type of information - Text',
							'Aggregation function' => 'last',
							'Thresholds' => [
								['color' => 'D1C4E9', 'threshold' => '0']
							]
						]
					],
					'value' => 'test',
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Log) item with data and aggregation function not used.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Thresholds and non-nmeric (Log) item with aggregation function not used',
							'Item' => 'Item with type of information - Log',
							'Thresholds' => [
								['color' => 'D1C4E9', 'threshold' => '0']
							]
						]
					],
					'value' => 'test',
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			]
		];
	}

	/**
	 * @backup !history, !history_log, !history_str, !history_text, !history_uint
	 *
	 * @dataProvider getThresholdData
	 */
	public function testDashboardTopHostsWidget_ThresholdColor($data) {
		$time = strtotime('now');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_threshold);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Top hosts')]);

		// Create widget with column(s).
		$this->fillColumnForm($data, 'create');
		$form->submit();
		COverlayDialogElement::ensureNotPresent();
		$this->page->waitUntilReady();
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		foreach ($data['column_fields'] as $fields) {
			foreach ($fields['Thresholds'] as $threshold) {
				// Insert item data.
				if (array_key_exists('value', $data)) {
					CDataHelper::addItemData(self::$aggregation_itemids[$fields['Item']], $data['value'], $time);

					if (array_key_exists('numeric', $data)) {
						$data['value']++;
					}

					$time++;
				}
				$this->page->refresh()->waitUntilReady();

				$rgb = (array_key_exists('expected_color', $data))
					? implode(', ', sscanf($data['expected_color'], "%02x%02x%02x"))
					: implode(', ', sscanf($threshold['color'], "%02x%02x%02x"));

				$opacity = (array_key_exists('opacity', $data)) ? '0' : '1';
				$this->assertEquals('rgba('.$rgb.', '.$opacity.')', $dashboard->getWidget(self::DEFAULT_WIDGET_NAME)
						->query('xpath:.//div[contains(@class, "dashboard-widget-tophosts")]/../..//td')->one()
						->getCSSValue('background-color')
				);
			}
		}

		// Necessary for test stability.
		$dashboard->edit()->deleteWidget(self::DEFAULT_WIDGET_NAME)->save();
	}

	public static function getAggregationFunctionData() {
		return [
			// Widget with several columns with common item that used value mapping, different aggregation functions and time periods.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Min',
							'Item' => 'Value mapping',
							'Aggregation function' => 'min',
							'Time period' => 'Custom'
						],
						[
							'Name' => 'Max',
							'Item' => 'Value mapping',
							'Aggregation function' => 'max',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2h',
							'id:time_period_to' => 'now-1h'
						],
						[
							'Name' => 'Avg',
							'Item' => 'Value mapping',
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-7d',
							'id:time_period_to' => 'now-5d'
						],
						[
							'Name' => 'Avg 2',
							'Item' => 'Value mapping',
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-4d',
							'id:time_period_to' => 'now-2d'
						],
						[
							'Name' => 'Count',
							'Item' => 'Value mapping',
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-5h-30m',
							'id:time_period_to' => 'now-14400' // -4 hours.
						],
						[
							'Name' => 'Sum',
							'Item' => 'Value mapping',
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-360m',
							'id:time_period_to' => 'now-240m'
						],
						[
							'Name' => 'First',
							'Item' => 'Value mapping',
							'Aggregation function' => 'first',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1h-30m',
							'id:time_period_to' => 'now-30m'
						],
						[
							'Name' => 'Last',
							'Item' => 'Value mapping',
							'Aggregation function' => 'last',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1h-20m-600s',
							'id:time_period_to' => 'now-1800s'
						]
					],
					'item_data' => [
						[
							'name' => 'Value mapping',
							'value' => '0',
							'time' => 'now'
						],
						[
							'name' => 'Value mapping',
							'value' => '0',
							'time' => '-45 minutes'
						],
						[
							'name' => 'Value mapping',
							'value' => '1',
							'time' => '-61 minute'
						],
						[
							'name' => 'Value mapping',
							'value' => '0',
							'time' => '-122 minutes'
						],
						[
							'name' => 'Value mapping',
							'value' => '1',
							'time' => '-270 minutes'
						],
						[
							'name' => 'Value mapping',
							'value' => '0',
							'time' => '-275 minutes'
						],
						[
							'name' => 'Value mapping',
							'value' => '1',
							'time' => '-276 minutes'
						],
						[
							'name' => 'Value mapping',
							'value' => '0',
							'time' => '-63 hours'
						],
						[
							'name' => 'Value mapping',
							'value' => '1',
							'time' => '-3 days'
						],
						[
							'name' => 'Value mapping',
							'value' => '1',
							'time' => '-6 days'
						]
					],
					'result' => [
						[
							'Min' => 'Down (0)',
							'Max' => 'Up (1)',
							'Avg' => 'Up (1)',
							'Avg 2' => '0.50', // Value mapping is ignored if value doesn't equals 0 or 1.
							'Count' => '3.00', // Mapping is not used if aggregation function is 'sum' or 'count'.
							'Sum' => '2.00',
							'First' => 'Up (1)',
							'Last' => 'Down (0)'
						]
					]
				]
			],
			// Value mapping with aggregation function 'not used'.
			[
				[
					'column_fields' => [
						[
							// Not used is default value for aggregation function field.
							'Name' => 'Value mapping with aggregation function not used',
							'Item' => 'Value mapping'
						]
					],
					'item_data' => [
						[
							'name' => 'Value mapping',
							'value' => '1',
							'time' => '-15 minutes'
						],
						[
							'name' => 'Value mapping',
							'value' => '0',
							'time' => '-45 minutes'
						],
						[
							'name' => 'Value mapping',
							'value' => '1',
							'time' => '-50 minutes'
						]
					],
					'result' => [
						[
							'Value mapping with aggregation function not used' => 'Up (1)'
						]
					]

				]
			],
			// Numeric items with aggregation function 'min' and 'max', decimal places and Custom time period.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Numeric (unsigned)',
							'Item' => 'Item with type of information - numeric (unsigned)',
							'Decimal places' => '9',
							'Aggregation function' => 'min',
							'Time period' => 'Custom'
						],
						[
							'Name' => 'Numeric (float)',
							'Item' => 'Item with type of information - numeric (float)',
							'Decimal places' => '3',
							'Aggregation function' => 'max',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-3h',
							'id:time_period_to' => 'now'
						]
					],
					'item_data' => [
							[
								'name' => 'Item with type of information - numeric (unsigned)',
								'value' => '5',
								'time' => '-5 minutes'
							],
							[
								'name' => 'Item with type of information - numeric (float)',
								'value' => '7.76',
								'time' => '-6 minutes'
							],
							[
								'name' => 'Item with type of information - numeric (unsigned)',
								'value' => '4',
								'time' => '-30 minutes'
							],
							[
								'name' => 'Item with type of information - numeric (unsigned)',
								'value' => '10',
								'time' => '-61 minute'
							],
							[
								'name' => 'Item with type of information - numeric (float)',
								'value' => '7.77',
								'time' => '-90 minutes'
							],
							[
								'name' => 'Item with type of information - numeric (float)',
								'value' => '7.78',
								'time' => '-5 hours'
							]
					],
					'result' => [
						[
							'Numeric (unsigned)' => '4.000000000',
							'Numeric (float)' => '7.770'
						]
					]
				]
			],
			// Numeric (unsigned) item with aggregation function 'avg', default decimal places and Custom time period.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Numeric (unsigned) with avg',
							'Item' => 'Item with type of information - numeric (unsigned)',
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-30m',
							'id:time_period_to' => 'now'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => '2',
							'time' => '-30 seconds'
						],
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => '3',
							'time' => '-45 seconds'
						],
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => '10',
							'time' => '-60 seconds'
						],
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => '15',
							'time' => '-90 seconds'
						]
					],
					'result' => [
						[
							'Numeric (unsigned) with avg' => '7.50'
						]
					]
				]
			],
			// Item with units, aggregation function 'count' and Custom time period.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Units should not appear',
							'Item' => 'Item with units',
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1h-20m-30s',
							'id:time_period_to' => 'now'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with units',
							'value' => '2',
							'time' => '-10 minutes'
						],
						[
							'name' => 'Item with units',
							'value' => '95',
							'time' => '-15 minutes'
						]
					],
					'result' => [
						[
							'Units should not appear' => '2.00' // Item units are not shown if aggregation function is 'count'.
						]
					]
				]
			],
			// Item with units, aggregation function 'sum' and Custom time period.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Units should appear',
							'Item' => 'Item with units',
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1h-20m-30s',
							'id:time_period_to' => 'now'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with units',
							'value' => '2',
							'time' => '-10 minutes'
						],
						[
							'name' => 'Item with units',
							'value' => '95',
							'time' => '-15 minutes'
						]
					],
					'result' => [
						[
							'Units should appear' => '97.00 %'
						]
					]
				]
			],
			// Numeric (float) item with aggregation function 'first' and Custom time period.
			[
				[
					'column_fields' => [
						[
							'Name' => 'First',
							'Item' => 'Item with type of information - numeric (float)',
							'Aggregation function' => 'first',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2M',
							'id:time_period_to' => 'now-1M'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '11.11',
							'time' => '-10 days'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '12.55',
							'time' => '-40 days'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '12.01',
							'time' => '-45 days'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '12.99',
							'time' => '-50 days'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '121.12',
							'time' => '-70 days'
						]
					],
					'result' => [
						[
							'First' => '12.99'
						]
					]
				]
			],
			// Numeric (float) item with aggregation function 'last' and Custom time period with absolute time.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Last',
							'Item' => 'Item with type of information - numeric (float)',
							'Aggregation function' => 'last',
							'Time period' => 'Custom',
							'id:time_period_from' => '2024-01-17 00:00:00',
							'id:time_period_to' => '2024-01-18 00:00:00'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '12.33',
							'time' => '2024-01-17 04:00:00'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '12.55',
							'time' => '2024-01-17 08:00:00'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '12.99',
							'time' => '2024-01-17 11:00:00'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '11.99',
							'time' => '2024-01-17 12:00:00'
						]
					],
					'result' => [
						[
							'Last' => '11.99'
						]
					]
				]
			],
			// Non-numeric (Text) item with aggregation function 'count' and Custom time period with relative time.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Text) item with aggregation function count',
							'Item' => 'Item with type of information - Text',
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2y-1M-2w-1d-10h-30m-20s',
							'id:time_period_to' => 'now-1y-1M-2w-1d-10h-30m-20s'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - Text',
							'value' => 'text 1',
							'time' => '-1 year -1 month -2 weeks -2 days -10 hours -30 minutes -20 seconds'
						],
						[
							'name' => 'Item with type of information - Text',
							'value' => 'text 2',
							'time' => '-1 year -1 month -2 weeks -1 day -20 hours -30 minutes -20 seconds'
						],
						[
							'name' => 'Item with type of information - Text',
							'value' => 'text 3',
							'time' => '-1 year -1 month -3 weeks -2 days -10 hours -30 minutes -20 seconds'
						],
						[
							'name' => 'Item with type of information - Text',
							'value' => 'text 4',
							'time' => '-1 year -2 month -2 weeks -2 days -10 hours -30 minutes -20 seconds'
						]
					],
					'result' => [
						[
							'Non-numeric (Text) item with aggregation function count' => '4.00'
						]
					]
				]
			],
			// Non-numeric items with aggregation function 'min'/'max'/'avg'/'sum' and Custom time period.
			[
				[
					'no_data_found' => true,
					'column_fields' => [
						[
							'Name' => 'log item with  aggregation function min',
							'Item' => 'Item with type of information - Log',
							'Aggregation function' => 'min', // only numeric items will be displayed.
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2y',
							'id:time_period_to' => 'now-1y'
						],
						[
							'Name' => 'Character item with  aggregation function max',
							'Item' => 'Item with type of information - Character',
							'Aggregation function' => 'max', // only numeric items will be displayed.
							'Time period' => 'Custom',
							'id:time_period_from' => '2023-12-12 00:00:00',
							'id:time_period_to' => '2023-12-12 10:00:00'
						],
						[
							'Name' => 'Text item with aggregation function avg',
							'Item' => 'Item with type of information - Text',
							'Aggregation function' => 'avg', // only numeric items will be displayed.
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1d',
							'id:time_period_to' => 'now'
						],
						[
							'Name' => 'Log item with  aggregation function sum',
							'Item' => 'Item with type of information - Log',
							'Aggregation function' => 'sum', // only numeric items will be displayed.
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1d',
							'id:time_period_to' => 'now'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - Text',
							'value' => 'Text 1',
							'time' => '-1 hour'
						],
						[
							'name' => 'Item with type of information - Log',
							'value' => 'Log 1',
							'time' => '-2 hours'
						],
						[
							'name' => 'Item with type of information - Text',
							'value' => 'Text 2',
							'time' => '-1 day -1 hour'
						],
						[
							'name' => 'Item with type of information - Log',
							'value' => 'Log 2',
							'time' => '-1 day -2 hours'
						],
						[
							'name' => 'Item with type of information - Character',
							'value' => 'Character 1',
							'time' => '2023-12-12 05:00:00'
						],
						[
							'name' => 'Item with type of information - Log',
							'value' => 'log 1',
							'time' => '-15 month'
						]
					]
				]
			],
			// Non-numeric (Character) item with aggregation function 'first' and Custom time period.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Character) item with aggregation function first',
							'Item' => 'Item with type of information - Character',
							'Aggregation function' => 'first',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1d',
							'id:time_period_to' => 'now'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - Character',
							'value' => 'Character 1',
							'time' => '-1 hour'
						],
						[
							'name' => 'Item with type of information - Character',
							'value' => 'Character 2',
							'time' => '-10 hours'
						],
						[
							'name' => 'Item with type of information - Character',
							'value' => 'Character 3',
							'time' => '-1 day -1 hour'
						]
					],
					'result' => [
						[
							'Non-numeric (Character) item with aggregation function first' => 'Character 2'
						]
					]
				]
			],
			// Non-numeric (Text) item with aggregation function 'last' and Custom time period.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Text) item with aggregation function last',
							'Item' => 'Item with type of information - Text',
							'Aggregation function' => 'last',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1d',
							'id:time_period_to' => 'now'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - Text',
							'value' => 'text 2',
							'time' => '-1 hour'
						],
						[
							'name' => 'Item with type of information - Text',
							'value' => 'text 1',
							'time' => '-1 hour -1 minute'
						],
						[
							'name' => 'Item with type of information - Text',
							'value' => 'text 3',
							'time' => '-8 days'
						]
					],
					'result' => [
						[
							'Non-numeric (Text) item with aggregation function last' => 'text 2'
						]
					]
				]
			],
			// Numeric (unsigned) item with aggregation function 'avg', trends history data and Custom time period.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Numeric (unsigned) item with trends and aggregation function avg',
							'Item' => 'Item with type of information - numeric (unsigned)',
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1h',
							'id:time_period_to' => 'now',
							'History data' => 'Trends'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => [
								[
									'num' => '3',
									'avg' => '4',
									'min' => '2',
									'max' => '7'
								]
							],
							'time' => 'now'
						],
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => [
								[
									'num' => '5',
									'avg' => '5',
									'min' => '1',
									'max' => '8'
								]
							],
							'time' => '-1 hour'
						]
					],
					'result' => [
						[
							'Numeric (unsigned) item with trends and aggregation function avg' => '4.00'
						]
					]
				]
			],
			// Numeric (float) item with aggregation function 'min', trends history data and Custom time period.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Numeric (float) item with trends and aggregation function min',
							'Item' => 'Item with type of information - numeric (float)',
							'Aggregation function' => 'min',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2h',
							'id:time_period_to' => 'now-1h',
							'History data' => 'Trends'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => [
								[
									'num' => '10',
									'avg' => '3.33',
									'min' => '1.11',
									'max' => '5.55'
								]
							],
							'time' => 'now'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => [
								[
									'num' => '11',
									'avg' => '2.22',
									'min' => '1.51',
									'max' => '3.33'
								]
							],
							'time' => '-1 hour'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => [
								[
									'num' => '51',
									'avg' => '5.55',
									'min' => '1.09',
									'max' => '8.88'
								]
							],
							'time' => '-2 hours'
						]
					],
					'result' => [
						[
							'Numeric (float) item with trends and aggregation function min' => '1.51'
						]
					]
				]
			],
			// Numeric (float) item with aggregation function 'max', trends history data and Custom time period.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Numeric (float) item with trends and aggregation function max',
							'Item' => 'Item with type of information - numeric (float)',
							'Aggregation function' => 'max',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-3h',
							'id:time_period_to' => 'now-2h',
							'History data' => 'Trends'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => [
								[
									'num' => '101',
									'avg' => '5.89',
									'min' => '1.77',
									'max' => '11.10'
								]
							],
							'time' => '-2 hours'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => [
								[
									'num' => '101',
									'avg' => '5.87',
									'min' => '1.05',
									'max' => '11.11'
								]
							],
							'time' => '-3 hours'
						]
					],
					'result' => [
						[
							'Numeric (float) item with trends and aggregation function max' => '11.10'
						]
					]
				]
			],
			// Numeric (unsigned) item with aggregation function 'count', trends history data and Custom time period.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Numeric (unsigned) item with trends and aggregation function count',
							'Item' => 'Item with type of information - numeric (unsigned)',
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1h',
							'id:time_period_to' => 'now',
							'History data' => 'Trends'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => [
								[
									'num' => '7',
									'avg' => '5',
									'min' => '1',
									'max' => '8'
								]
							],
							'time' => 'now'
						],
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => [
								[
									'num' => '9',
									'avg' => '3',
									'min' => '2',
									'max' => '7'
								]
							],
							'time' => '-1 hour'
						]
					],
					'result' => [
						[
							'Numeric (unsigned) item with trends and aggregation function count' => '7.00' // num result.
						]
					]
				]
			],
			// Numeric (float) item with aggregation function 'sum', trends history data and Custom time period.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Numeric (float) item with trends and aggregation function sum',
							'Item' => 'Item with type of information - numeric (float)',
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2d',
							'id:time_period_to' => 'now-1d',
							'History data' => 'Trends'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => [
								[
									'num' => '5',
									'avg' => '3.33',
									'min' => '1.11',
									'max' => '55.55'
								]
							],
							'time' => 'now'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => [
								[
									'num' => '7',
									'avg' => '7.77',
									'min' => '3.33',
									'max' => '11.11'
								]
							],
							'time' => '-1 day'
						]
					],
					'result' => [
						[
							'Numeric (float) item with trends and aggregation function sum' => '54.39' // num * avg result.
						]
					]
				]
			],
			// Numeric (unsigned) item with aggregation function 'first', trends history data and Custom time period.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Numeric (unsigned) item with trends and aggregation function first',
							'Item' => 'Item with type of information - numeric (unsigned)',
							'Aggregation function' => 'first',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2w',
							'id:time_period_to' => 'now-1w',
							'History data' => 'Trends'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => [
								[
									'num' => '168',
									'avg' => '8',
									'min' => '2',
									'max' => '14'
								]
							],
							'time' => 'now'
						],
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => [
								[
									'num' => '336',
									'avg' => '6',
									'min' => '4',
									'max' => '8'
								]
							],
							'time' => '-1 week'
						]
					],
					'result' => [
						[
							'Numeric (unsigned) item with trends and aggregation function first' => '6.00' // avg result.
						]
					]
				]
			],
			// Numeric (float) item with aggregation function 'last', trends history data and Custom time period.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Numeric (float) item with trends and aggregation function last',
							'Item' => 'Item with type of information - numeric (float)',
							'Aggregation function' => 'last',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1w',
							'id:time_period_to' => 'now',
							'History data' => 'Trends'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => [
								[
									'num' => '168',
									'avg' => '8.11',
									'min' => '2.58',
									'max' => '17.89'
								]
							],
							'time' => 'now'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => [
								[
									'num' => '336',
									'avg' => '6.78',
									'min' => '4.13',
									'max' => '8.09'
								]
							],
							'time' => '-1 week'
						]
					],
					'result' => [
						[
							'Numeric (float) item with trends and aggregation function last' => '8.11' // avg result.
						]
					]
				]
			],
			// Check that widget with bar/indicators return 'No data found' if non-numeric data is selected.
			[
				[
					'no_data_found' => true,
					'column_fields' => [
						[
							'Name' => 'Min',
							'Item' => 'Item with type of information - Log',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10',
							'Aggregation function' => 'min',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2h',
							'id:time_period_to' => 'now-1h'
						],
						[
							'Name' => 'Max',
							'Item' => 'Item with type of information - Character',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10',
							'Aggregation function' => 'max',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1w',
							'id:time_period_to' => 'now-1d'
						],
						[
							'Name' => 'Avg',
							'Item' => 'Item with type of information - Text',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10',
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_from' => '2023-12-12 00:00:00',
							'id:time_period_to' => '2023-12-12 10:00:00'
						],
						[
							'Name' => 'Sum',
							'Item' => 'Item with type of information - Log',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10',
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1h-30m',
							'id:time_period_to' => 'now-30m'
						],
						[
							'Name' => 'First',
							'Item' => 'Item with type of information - Character',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10',
							'Aggregation function' => 'first',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-30m',
							'id:time_period_to' => 'now'
						],
						[
							'Name' => 'Last',
							'Item' => 'Item with type of information - Text',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10',
							'Aggregation function' => 'last',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2d',
							'id:time_period_to' => 'now-1d'
						],
						[
							'Name' => 'Not used',
							'Item' => 'Item with type of information - Log',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - Character',
							'value' => 'Character 1',
							'time' => '-15 minutes'
						],
						[
							'name' => 'Item with type of information - Log',
							'value' => 'Log 1',
							'time' => '-45 minutes'
						],
						[
							'name' => 'Item with type of information - Log',
							'value' => 'Log 2',
							'time' => '-1 hour -10 minutes'
						],
						[
							'name' => 'Item with type of information - Text',
							'value' => 'Text 1',
							'time' => '-1 day -1 hour'
						],
						[
							'name' => 'Item with type of information - Character',
							'value' => 'Character 2',
							'time' => '-3 days'
						],
						[
							'name' => 'Item with type of information - Text',
							'value' => 'Text 2',
							'time' => '2023-12-12 05:00:00'
						]
					]
				]
			],
			// Check that widget displays bar/idnicators when aggregation function 'count' is used for non-numeric item.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Count Log',
							'Item' => 'Item with type of information - Log',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '1',
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2h',
							'id:time_period_to' => 'now-1h'
						],
						[
							'Name' => 'Count Character',
							'Item' => 'Item with type of information - Character',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '1',
							'Thresholds' => [
								['color' => '4000FF', 'threshold' => '0'],
								['color' => 'BFFF00', 'threshold' => '0.5']
							],
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1w',
							'id:time_period_to' => 'now-1d'
						],
						[
							'Name' => 'Count Text',
							'Item' => 'Item with type of information - Text',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '1',
							'Thresholds' => [
								['color' => '4000FF', 'threshold' => '0'],
								['color' => 'D1C4E9', 'threshold' => '1']
							],
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => '2023-12-12 00:00:00',
							'id:time_period_to' => '2023-12-12 10:00:00'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - Log',
							'value' => 'Log 1',
							'time' => '-1 hour -15 minutes'
						],
						[
							'name' => 'Item with type of information - Character',
							'value' => 'Character',
							'time' => '-3 days'
						],
						[
							'name' => 'Item with type of information - Text',
							'value' => 'Text',
							'time' => '2023-12-12 05:00:00'
						]
					],
					'screen_name' => 'mixed_non_numeric'
				]
			],
			// Non-numeric items without data but with aggregation function count (return 0) that are displayed as bar/indicators.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Count Log',
							'Item' => 'Item with type of information - Log',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '1',
							'Aggregation function' => 'count'
						],
						[
							'Name' => 'Count Character',
							'Item' => 'Item with type of information - Character',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '-1',
							'Max' => '1',
							'Thresholds' => [
								['color' => 'BFFF00', 'threshold' => '0'],
								['color' => '4000FF', 'threshold' => '1']
							],
							'Aggregation function' => 'count'
						],
						[
							'Name' => 'Count Text',
							'Item' => 'Item with type of information - Text',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '1',
							'Thresholds' => [
								['color' => '4000FF', 'threshold' => '0'],
								['color' => 'D1C4E9', 'threshold' => '1']
							],
							'Aggregation function' => 'count'
						]
					],
					'screen_name' => 'mixed_non_numeric_without_item_data'
				]
			],
			// Numeric items without data that are displayed as bar/indicators.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Min',
							'Item' => 'Item with type of information - numeric (unsigned)',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '1',
							'Aggregation function' => 'min'
						],
						[
							'Name' => 'Max',
							'Item' => 'Item with type of information - numeric (float)',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '-1',
							'Max' => '1',
							'Aggregation function' => 'max'
						],
						[
							'Name' => 'Avg',
							'Item' => 'Item with type of information - numeric (unsigned)',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '1',
							'Aggregation function' => 'avg'
						],
						[
							'Name' => 'Sum',
							'Item' => 'Item with type of information - numeric (float)',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '1',
							'Aggregation function' => 'sum'
						],
						[
							'Name' => 'First',
							'Item' => 'Item with type of information - numeric (unsigned)',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '1',
							'Aggregation function' => 'first'
						],
						[
							'Name' => 'Last',
							'Item' => 'Item with type of information - numeric (float)',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '1',
							'Thresholds' => [
								['color' => '4000FF', 'threshold' => '0'],
								['color' => 'D1C4E9', 'threshold' => '1']
							],
							'Aggregation function' => 'last'
						]
					],
					'result' => [
						[
							'Min' => '',
							'Max' => '',
							'Avg' => '',
							'Sum' => '',
							'First' => '',
							'Last' => ''
						]
					]
				]
			],
			// Numeric items with data and aggregation function min/max/avg/count that are displayed as bar/indicators.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Min',
							'Item' => 'Item with type of information - numeric (unsigned)',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '-1',
							'Max' => '10',
							'Thresholds' => [
								['color' => 'FFF9C4', 'threshold' => '-1']
							],
							'Aggregation function' => 'min',
							'Time period' => 'Custom'
						],
						[
							'Name' => 'Max',
							'Item' => 'Item with type of information - numeric (float)',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '-1.00',
							'Max' => '10.00',
							'Aggregation function' => 'max',
							'Time period' => 'Custom'
						],
						[
							'Name' => 'Avg',
							'Item' => 'Item with type of information - numeric (unsigned)',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10',
							'Aggregation function' => 'avg',
							'Time period' => 'Custom'
						],
						[
							'Name' => 'Count',
							'Item' => 'Item with type of information - numeric (float)',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10.00',
							'Thresholds' => [
								['color' => '4000FF', 'threshold' => '3.33'],
								['color' => 'D1C4E9', 'threshold' => '6.55']
							],
							'Aggregation function' => 'sum',
							'Time period' => 'Custom'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => '1',
							'time' => '-15 minutes'
						],
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => '5',
							'time' => '-30 minutes'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '1.11',
							'time' => '-20 minutes'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '5.44',
							'time' => '-40 minutes'
						]
					],
					'screen_name' => 'mixed_numeric'
				]
			],
			// Numeric items with data and aggregation function sum/first/last that are displayed as bar/indicators.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Sum',
							'Item' => 'Item with type of information - numeric (unsigned)',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10',
							'Aggregation function' => 'sum'
						],
						[
							'Name' => 'First',
							'Item' => 'Item with type of information - numeric (float)',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10.00',
							'Thresholds' => [
								['color' => '4000FF', 'threshold' => '-1.11'],
								['color' => 'D1C4E9', 'threshold' => '1.11']
							],
							'Aggregation function' => 'first'
						],
						[
							'Name' => 'Last',
							'Item' => 'Item with type of information - numeric (unsigned)',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10',
							'Thresholds' => [
								['color' => '4000FF', 'threshold' => '0'],
								['color' => 'D1C4E9', 'threshold' => '1']
							],
							'Aggregation function' => 'last'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => '1',
							'time' => '-15 minutes'
						],
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => '5',
							'time' => '-30 minutes'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '1.11',
							'time' => '-20 minutes'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '5.55',
							'time' => '-40 minutes'
						]
					],
					'screen_name' => 'mixed_numeric2'
				]
			]
		];
	}

	/**
	 * @backup !history, !history_log, !history_str, !history_text, !history_uint, !trends_uint, !trends
	 *
	 * @dataProvider getAggregationFunctionData
	 */
	public function testDashboardTopHostsWidget_AggregationFunctionData($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_aggregation)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dashboard->waitUntilReady();

		if (array_key_exists('item_data', $data)) {
			foreach ($data['item_data'] as $params) {
				$params['time'] = strtotime($params['time']);
				CDataHelper::addItemData(self::$aggregation_itemids[$params['name']], $params['value'], $params['time']);
			}
		}

		// Create widget with column(s).
		$form = $dashboard->edit()->addWidget()->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Top hosts')]);
		$this->fillColumnForm($data, 'create');
		$form->submit();
		$dashboard->save();
		$dashboard->waitUntilReady();

		if (array_key_exists('screen_name', $data)) {
			$this->assertScreenshot($dashboard->getWidget(self::DEFAULT_WIDGET_NAME), $data['screen_name']);
		}
		else {
			$table_data = (array_key_exists('no_data_found', $data)) ? '' : $data['result'];
			$this->assertTableData($table_data);
		}

		// Necessary for test stability.
		$dashboard->edit()->deleteWidget(self::DEFAULT_WIDGET_NAME)->save();
	}

	/**
	 * Function used to create Top Hosts widget with special columns for CheckTextItems and WidgetAppearance scenarios.
	 *
	 * @param array     $data    data provider values
	 * @param string    $name    name of the dashboard where to create Top Hosts widget
	 */
	protected function createTopHostsWidget($data, $name) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$name);
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

	/**
	 * Check fields attributes for given form.
	 *
	 * @param array           $data    provided data
	 * @param CFormElement    $form    form to be checked
	 */
	protected function checkFieldsAttributes($data, $form) {
		foreach ($data as $label => $attributes) {
			$field = $form->getField($label);
			$this->assertTrue($field->isVisible(CTestArrayHelper::get($attributes, 'visible', true)));
			$this->assertTrue($field->isEnabled(CTestArrayHelper::get($attributes, 'enabled', true)));

			if (array_key_exists('value', $attributes)) {
				$this->assertEquals($attributes['value'], $field->getValue());
			}

			if (array_key_exists('maxlength', $attributes)) {
				$this->assertEquals($attributes['maxlength'], $field->getAttribute('maxlength'));
			}

			if (array_key_exists('placeholder', $attributes)) {
				$this->assertEquals($attributes['placeholder'], $field->getAttribute('placeholder'));
			}

			if (array_key_exists('labels', $attributes)) {
				$this->assertEquals($attributes['labels'], $field->asSegmentedRadio()->getLabels()->asText());
			}

			if (array_key_exists('options', $attributes)) {
				$this->assertEquals($attributes['options'], $field->asDropdown()->getOptions()->asText());
			}

			if (array_key_exists('color', $attributes)) {
				$this->assertEquals($attributes['color'], $form->query($label)->asColorPicker()->one()->getValue());
			}
		}
	}

	/**
	 * Test function for assuring that binary items are not available in Top hosts widget.
	 */
	public function testDashboardTopHostsWidget_CheckAvailableItems() {
		$this->checkAvailableItems('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_create, self::DEFAULT_WIDGET_NAME);
	}
}

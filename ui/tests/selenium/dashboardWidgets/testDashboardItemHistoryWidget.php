<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';
require_once dirname(__FILE__).'/../common/testWidgets.php';

/**
 * @backup dashboard
 *
 * @onBefore prepareData
 *
 * @dataSource AllItemValueTypes
 */
class testDashboardItemHistoryWidget extends testWidgets {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	protected static $dashboardid;
	protected static $dashboard_create;
	protected static $dashboard_data;
	protected static $update_widget = 'Update Item history Widget';
	const DEFAULT_WIDGET = 'Default Item history Widget';
	const DELETE_WIDGET = 'Widget for delete';
	const DATA_WIDET = 'Widget for data check';

	/**
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	protected $sql = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid'.
			' ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid,'.
			' wf.value_itemid, wf.value_graphid';

	public static function prepareData() {
		// Create host for widget header and data tests.
		CDataHelper::createHosts([
			[
				'host' => 'Simple host with item for Item history widget',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.9.1',
						'dns' => '',
						'port' => '10077'
					]
				],
				'groups' => [
					'groupid' => '4'
				],
				'items' => [
					[
						'name' => 'Test Item history',
						'key_' => 'plain_text',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_FLOAT,
						'delay' => '30'
					]
				]
			]
		]);
		$itemids = CDataHelper::getIds('name');

		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for Item history Widget test',
				'pages' => [
					[
						'name' => 'Page with default widgets',
						'widgets' => [
							[
								'type' => 'itemhistory',
								'name' => self::DEFAULT_WIDGET,
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.name',
										'value' => 'Column_1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.0.itemid',
										'value' => '42227' // item name in widget 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running'.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'itemhistory',
								'name' => self::DELETE_WIDGET,
								'x' => 0,
								'y' => 5,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.name',
										'value' => 'Column_1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.0.itemid',
										'value' => '42227' // item name in widget 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running'.
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for Item history Widget create/update test',
				'pages' => [
					[
						'name' => 'Page with created/updated widgets',
						'widgets' => [
							[
								'type' => 'itemhistory',
								'name' => self::$update_widget,
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.name',
										'value' => 'Update column 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.0.itemid',
										'value' => '42243' // item name in widget 'ЗАББИКС Сервер: Available memory'.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'EDCBA'
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for checking data',
				'pages' => [
					[
						'name' => 'Page with Item history widget',
						'widgets' => [
							[
								'type' => 'itemhistory',
								'name' => self::DATA_WIDET,
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 6,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show_timestamp',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.name',
										'value' => 'Host name'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.0.itemid',
										'value' => '42227' // item name in widget 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running'.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.1.name',
										'value' => 'Available memory'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.1.itemid',
										'value' => '42243' // item name in widget 'ЗАББИКС Сервер: Linux: Available memory'.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.2.name',
										'value' => 'Available memory in %'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.2.itemid',
										'value' => '42244' // item name in widget 'ЗАББИКС Сервер: Linux: Available memory in %'.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.3.name',
										'value' => 'Test Item history'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.3.itemid',
										'value' => $itemids['Test Item history'] // item name in widget 'Simple host with item for Item history widget: Test Item history'.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.4.name',
										'value' => 'Master item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.4.itemid',
										'value' => '99142' // item name in widget 'Test item host: Master item'.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							]
						]
					]
				]
			]
		]);
		self::$dashboardid = $response['dashboardids'][0];
		self::$dashboard_create = $response['dashboardids'][1];
		self::$dashboard_data = $response['dashboardids'][2];
	}

	public function testDashboardItemHistoryWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dialog = $dashboard->edit()->addWidget();
		$this->assertEquals('Add widget', $dialog->getTitle());
		$form = $dialog->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item history')]);

		// Check default state.
		$default_state = [
			'Type' => 'Item history',
			'Name' => '',
			'Show header' => true,
			'Refresh interval' => 'Default (1 minute)',
			'Columns' => [],
			'Show lines' => '25',
			'New values' => 'Top',
			'Show timestamp' => false,
			'Show column header' => 'Vertical',
			'Override host' => ''
		];
		$form->checkValue($default_state);

		// Check required fields.
		$this->assertEquals(['Columns', 'Show lines'], $form->getRequiredLabels());

		// Check attributes of input elements.
		$inputs = [
			'Name' => [
				'maxlength' => '255',
				'placeholder' => 'default'
			],
			'Show lines' => [
				'maxlength' => '3'
			],
			'id:override_hostid_ms' => [
				'placeholder' => 'type here to search'
			]
		];

		foreach ($inputs as $field => $attributes) {
			$this->assertTrue($form->getField($field)->isAttributePresent($attributes));
		}

		// Check radio buttons.
		$this->assertEquals(['Top', 'Bottom'], $form->getField('New values')->getLabels()->asText());

		$refresh_interval = ['Default (1 minute)', 'No refresh', '10 seconds', '30 seconds', '1 minute',
				'2 minutes', '10 minutes', '15 minutes'];
		$this->assertEquals($refresh_interval, $form->getField('Refresh interval')->getOptions()->asText());

		// Check Column popup.
		$form->getFieldContainer('Columns')->query('button:Add')->waitUntilClickable()->one()->click();
		$column_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$this->assertEquals('New column', $column_overlay->getTitle());
		$column_form = $column_overlay->asForm();
		$this->assertEquals(['Name', 'Item', 'Base colour', 'Highlights', 'Display', 'Min', 'Max', 'Thresholds',
				'History data', 'Use monospace font', 'Display local time', 'Show thumbnail'],
				$column_form->getLabels()->asText()
		);
		$this->assertEquals(['Name', 'Item'], $column_form->getRequiredLabels());

		$defaults = [
			'Name' => ['value' => '', 'maxlength' => 255],
			'Item' => ['value' => ''],
			'xpath://input[@id="base_color"]/..' => ['value' => ''],
			'Display' => ['value' => 'As is', 'lables' => ['As is', 'HTML', 'Single line']],
			'Min' => ['value' => '', 'placeholder' => 'calculated', 'maxlength' => 255],
			'Max' => ['value' => '', 'placeholder' => 'calculated', 'maxlength' => 255],
			'History data' => ['value' => 'Auto', 'lables' => ['Auto', 'History', 'Trends']],
			'Use monospace font' => ['value' => false],
			'Display local time' => ['value' => false],
			'Show thumbnail' => ['value' => false]
		];

		foreach ($defaults as $label => $attributes) {
			$field = $column_form->getField($label);
			$this->assertEquals($attributes['value'], $field->getValue());

			if (array_key_exists('maxlength', $attributes)) {
				$this->assertEquals($attributes['maxlength'], $field->getAttribute('maxlength'));
			}

			if (array_key_exists('placeholder', $attributes)) {
				$this->assertEquals($attributes['placeholder'], $field->getAttribute('placeholder'));
			}

			if (array_key_exists('labels', $attributes)) {
				$this->assertEquals($attributes['labels'], $field->asSegmentedRadio()->getLabels()->asText());
			}
		}

		// Check buttons in column dialog.
		$this->assertEquals(['Add', 'Cancel'], $column_overlay->getFooter()->query('button')->all()
				->filter(CElementFilter::CLICKABLE)->asText()
		);

		// Check initial visible fields.
		foreach (['Name', 'Item', 'Base colour'] as $label) {
			$field = $column_form->getField($label);
			$this->assertTrue($field->isEnabled());
			$this->assertTrue($field->isVisible());
		}

		// Check fields for binary item.
		$column_form->fill(['Item' => 'Binary item']);

		// Check that name is filled automatically.
		$this->assertEquals('Binary item', $column_form->getField('Name')->getValue());
		$this->assertEquals(['Name', 'Item', 'Base colour', 'Show thumbnail'],
				array_values($column_form->getLabels()->filter(CElementFilter::VISIBLE)->asText())
		);

		// Check fields for character, text and log items.
		foreach (['Character item', 'Text item', 'Log item'] as $i => $item) {
			$column_form->fill(['Item' => $item]);
			$this->assertEquals($item, $column_form->getField('Name')->getValue());

			$labels = ($item === 'Log item')
				? ['Name', 'Item', 'Base colour', 'Highlights', 'Display', 'Use monospace font', 'Display local time']
				: ['Name', 'Item', 'Base colour', 'Highlights', 'Display', 'Use monospace font'];

			$this->assertEquals($labels, array_values($column_form->getLabels()->filter(CElementFilter::VISIBLE)->asText()));

			$highlights_container =  $column_form->getFieldContainer('Highlights');
			$highlights_container->query('button:Add')->one()->click();

			$regex_input = $column_form->query('id', 'highlights_'.$i.'_pattern')->one();
			$this->assertTrue($regex_input->isVisible());
			$this->assertEquals('FF465C', $highlights_container->query('xpath:.//div[@class="color-picker"]')
					->asColorPicker()->one()->getValue()
			);
			$column_form->query('id', 'highlights_'.$i.'_remove')->one()->click();
			$this->assertFalse($regex_input->isVisible());

			$this->checkHint($column_form, 'Display',
					'Single line - result will be displayed in a single line and truncated to specified length.'
			);

			if ($item === 'Log item') {
				$this->checkHint($column_form, 'Display local time', 'This setting will display local time '.
						'instead of the timestamp. "Show timestamp" must also be checked in the advanced configuration.'
				);
			}
		}

		// Check fields for float and unsigned item.
		foreach (['Float item', 'Unsigned item'] as $j => $item) {
			$column_form->fill(['Item' => $item]);
			$this->assertEquals(['Name', 'Item', 'Base colour', 'Display', 'Thresholds', 'History data'],
					array_values($column_form->getLabels()->filter(CElementFilter::VISIBLE)->asText())
			);

			$display_fields = [
				'Bar' => true,
				'Indicators' => true,
				'As is' => false
			];
			foreach ($display_fields as $label => $status) {
				$column_form->fill(['Display' => $label]);

				foreach (['Min', 'Max'] as $display_input) {
					$input = $column_form->getField($display_input);
					$this->assertTrue($input->isEnabled($status));
					$this->assertTrue($input->isVisible($status));
				}
			}

			$thresholds_container =  $column_form->getFieldContainer('Thresholds');
			$thresholds_container->query('button:Add')->one()->click();

			$threshold_input = $column_form->query('id', 'thresholds_'.$j.'_threshold')->one();
			$this->assertTrue($threshold_input->isVisible());
			$this->assertEquals('FF465C', $thresholds_container->query('xpath:.//div[@class="color-picker"]')
					->asColorPicker()->one()->getValue()
			);
			$column_form->query('id', 'thresholds_'.$j.'_remove')->one()->click();
			$this->assertFalse($threshold_input->isVisible());
		}

		$column_overlay->close();

		// Check if buttons present and clickable.
		$this->assertEquals(['Add', 'Cancel'], $dialog->getFooter()->query('button')->all()
				->filter(CElementFilter::CLICKABLE)->asText()
		);
		$dialog->close();
		$dashboard->save();

		// Check parameter 'Override host' true/false state.
		$host_selector = $dashboard->getControls()->query('class:multiselect-control')->asMultiselect()->one();
		$this->assertTrue($host_selector->isVisible());
		$dashboard->getWidget(self::DEFAULT_WIDGET)->edit();
		$this->assertEquals('Edit widget', $dialog->getTitle());
		$form->fill(['Override host' => ''])->submit();
		$dashboard->save();
		$this->assertFalse($host_selector->isVisible());
	}

	public static function getWidgetData() {
		return [
			// #0.
			[
				[
					'expected' => TEST_BAD,
					'error' => 'Invalid parameter "Columns": cannot be empty.'
				]
			],
			// #1.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show lines' => ''
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' =>  'Available memory in %',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						]
					],
					'error' => ['Invalid parameter "Show lines": value must be one of 1-100.']
				]
			],
			// #2.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show lines' => '0'
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' =>  'Available memory in %',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						]
					],
					'error' => 'Invalid parameter "Show lines": value must be one of 1-100.'
				]
			],
			// #3.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show lines' => '101'
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' =>  'Available memory in %',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						]
					],
					'error' => 'Invalid parameter "Show lines": value must be one of 1-100.'
				]
			],
			// #4.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show lines' => ' '
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' =>  'Available memory in %',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						]
					],
					'error' => 'Invalid parameter "Show lines": value must be one of 1-100.'
				]
			],
			// #5.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show lines' => ''
					],
					'error' => [
						'Invalid parameter "Columns": cannot be empty.',
						'Invalid parameter "Show lines": value must be one of 1-100.'
					]
				]
			],
			// #6.
			[
				[
					'flag' => true,
					'expected' => TEST_GOOD,
					'same_host' => 'ЗАББИКС Сервер',
					'fields' => [
						'Name' => '2 columns from one host'
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' =>  'Available memory in %',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						],
						[
							'Name' => 'Column2',
							'Item' => [
								'values' =>  'Available memory',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						]
					]
				]
			],
			// #7 Test case with items from two different hosts.
			[
				[
					'flag' => true,
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => ''
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' =>  'Available memory in %',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						],
						[
							'Name' => 'Column2',
							'Item' => [
								'values' => 'Test Item history',
								'context' => ['values' => 'Simple host with item for Item history widget']
							]
						]
					]
				]
			],
			// #8 Test case with items from the same host and with custom name.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Test custom name'
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' =>  'Available memory in %',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						],
						[
							'Name' => 'Column2',
							'Item' => [
								'values' =>  'Available memory',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						]
					]
				]
			],
			// #9 Test case with items from two different hosts and with custom name.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Test custom name2'
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' =>  'Available memory in %',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						],
						[
							'Name' => 'Column2',
							'Item' => [
								'values' => 'Test Item history',
								'context' => ['values' => 'Simple host with item for Item history widget']
							]
						]
					]
				]
			],
			// #10.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Custom refresh',
						'Refresh interval' => 'Default (1 minute)'
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' => 'Test Item history',
								'context' => ['values' => 'Simple host with item for Item history widget']
							]
						]
					]
				]
			],
			// #11.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Header is hidden',
						'Show header' => false,
						'Refresh interval' => 'No refresh'
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' =>  'Available memory',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						]
					]
				]
			],
			// #12.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Header appears',
						'Show header' => true,
						'Refresh interval' => '10 seconds'
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' =>  'Available memory',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						]
					]
				]
			],
			// #13.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Refresh interval' => '30 seconds'
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' =>  'Available memory',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						]
					]
				]
			],
			// #14.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Refresh interval' => '1 minute'
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' =>  'Available memory',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						]
					]
				]
			],
			// #15.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show lines' => '1',
						'Refresh interval' => '2 minutes'
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' =>  'Available memory',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						]
					]
				]
			],
			// #16.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show lines' => '100',
						'Refresh interval' => '10 minutes'
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' =>  'Available memory',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						]
					]
				]
			],
			// #17.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Refresh interval' => '15 minutes'
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' =>  'Available memory',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						]
					]
				]
			],
			// #18.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Refresh interval' => '10 minutes'
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' =>  'Available memory',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						]
					]
				]
			],
			// #19.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Override host' => 'Dashboard',
						'Refresh interval' => '2 minutes'
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' =>  'Available memory',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						]
					]
				]
			],
			// #20.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Override host' => 'Dashboard',
						'Refresh interval' => '1 minute'
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' =>  'Available memory',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						]
					]
				]
			],
			// #21.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => ' Widget with trimmed trailing and leading spaces ',
						'Refresh interval' => '30 seconds',
						'Show lines' => ' 5 '
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' => 'Test Item history',
								'context' => ['values' => 'Simple host with item for Item history widget']
							]
						]
					],
					'trim' => ['Name', 'Show lines']
				]
			],
			// #22.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => true,
						'Name' => 'Widget with updated fields',
						'Refresh interval' => '1 minute',
						'Show lines' => '50',
						'Override host' => 'Dashboard'
					],
					'Columns' => [
						[
							'Name' => 'Column1',
							'Item' => [
								'values' =>  'Available memory',
								'context' => ['values' => 'ЗАББИКС Сервер']
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getWidgetData
	 */
	public function testDashboardItemHistoryWidget_Create($data) {
		$this->checkWidgetForm($data);
	}

	public function testDashboardItemHistoryWidget_SimpleUpdate() {
		$old_hash = CDBHelper::getHash($this->sql);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_create)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dashboard->getWidget(self::$update_widget)->edit()->submit();
		$dashboard->save();
		$this->page->waitUntilReady();

		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	/**
	 * @dataProvider getWidgetData
	 */
	public function testDashboardItemHistoryWidget_Update($data) {
		$this->checkWidgetForm($data, true);
	}

	/**
	 * Perform Item history widget creation or update and verify the result.
	 *
	 * @param boolean $update	updating is performed
	 */
	protected function checkWidgetForm($data, $update = false) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash($this->sql);
		}

		$data['fields']['Name'] = CTestArrayHelper::get($data, 'fields.Name', 'Item history widget '.microtime());
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_create)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = $update
			? $dashboard->getWidget(self::$update_widget)->edit()->asForm()
			: $dashboard->edit()->addWidget()->asForm();

		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item history')]);

		if ($update) {
			$table = $form->query('id:list_columns')->asTable()->one();
			$button_remove = $table->query('button:Remove');
			$remove_count = $button_remove->count();

			for ($i = 0; $i < $remove_count; $i++) {
				$button_remove->waitUntilClickable()->one()->click();
				$form->waitUntilReloaded();
			}
		}

		// Fill Columns field.
		if (array_key_exists('Columns', $data)) {
			foreach ($data['Columns'] as $column) {
				$form->getFieldContainer('Columns')->query('button:Add')->waitUntilClickable()->one()->click();
				$column_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$column_form = $column_overlay->asForm();
				$column_form->fill($column);
				$column_overlay->getFooter()->query('button:Add')->waitUntilClickable()->one()->click();
				$column_overlay->waitUntilNotVisible();
				$form->waitUntilReloaded();
			}
		}

		$form->fill($data['fields']);
		$values = $form->getValues();
		$form->submit();

		// Trim leading and trailing spaces from expected results if necessary.
		if (array_key_exists('trim', $data)) {
			foreach ($data['trim'] as $field) {
				$data['fields'][$field] = trim($data['fields'][$field]);
				$values[$field] = trim($data['fields'][$field]);
			}
		}

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage($data['expected'], null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));

			// Check that after error and cancellation of the widget, the widget is not available on dashboard.
			COverlayDialogElement::find()->one()->close();
			$dashboard->save()->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
			$this->assertFalse($dashboard->getWidget($data['fields']['Name'], false)->isValid());
		}
		else {
			if (array_key_exists('Name', $data['fields'])) {
				$header = ($data['fields']['Name'] === '')
					? 'Item history'
					: $data['fields']['Name'];
			}
			else {
				$header = $update ? self::$update_widget : 'Item history';
			}

			COverlayDialogElement::ensureNotPresent();
			$widget = $dashboard->getWidget($header);

			// Save Dashboard to ensure that widget is correctly saved.
			$dashboard->save()->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check widgets count.
			$this->assertEquals($old_widget_count + ($update ? 0 : 1), $dashboard->getWidgets()->count());

			// Check new widget update interval.
			$refresh = (CTestArrayHelper::get($data['fields'], 'Refresh interval') === 'Default (1 minute)')
				? '1 minute'
				: (CTestArrayHelper::get($data['fields'], 'Refresh interval', '1 minute'));
			$this->assertEquals($refresh, $widget->getRefreshInterval());

			// Check new widget form fields and values in frontend.
			$saved_form = $widget->edit();
			$this->assertEquals($values, $saved_form->getValues());
			$table = $saved_form->query('id:list_columns')->asTable()->one();

			// Count is minus one row because of Add button row.
			$columns_count = $table->getRows()->count() - 1;
			$this->assertEquals(count($data['Columns']), $columns_count);

			foreach ($data['Columns'] as $i => $column) {
				$this->assertEquals($column['Name'], $table->getRow($i)->getColumn('Name')->getText());
				$this->assertEquals($column['Item']['context']['values'].': '.$column['Item']['values'],
						$table->getRow($i)->getColumn('Data')->getText()
				);
			}

			$saved_form->checkValue($data['fields']);

			// Close widget window and cancel editing the dashboard.
			COverlayDialogElement::find()->one()->close();
			$dashboard->cancelEditing();

			if ($update) {
				self::$update_widget = $header;
			}
		}
	}

	public static function getCancelData() {
		return [
			// Cancel update widget.
			[
				[
					'update' => true,
					'save_widget' => true,
					'save_dashboard' => false
				]
			],
			[
				[
					'update' => true,
					'save_widget' => false,
					'save_dashboard' => true
				]
			],
			// Cancel create widget.
			[
				[
					'save_widget' => true,
					'save_dashboard' => false
				]
			],
			[
				[
					'save_widget' => false,
					'save_dashboard' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testDashboardItemHistoryWidget_Cancel($data) {
		$old_hash = CDBHelper::getHash($this->sql);
		$new_name = 'Widget to be cancelled';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one()->edit();
		$old_widget_count = $dashboard->getWidgets()->count();

		// Start updating or creating a widget.
		if (CTestArrayHelper::get($data, 'update', false)) {
			$form = $dashboard->getWidget(self::DEFAULT_WIDGET)->edit();
		}
		else {
			$form = $dashboard->addWidget()->asForm();
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item history')]);
		}

		$form->fill([
			'Name' => $new_name,
			'Refresh interval' => '15 minutes'
		]);

		$form->getFieldContainer('Columns')->query('button:Add')->waitUntilClickable()->one()->click();
		$column_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$column_form = $column_overlay->asForm();
		$column_form->fill([
			'Item' => [
				'values' => 'Test Item history',
				'context' => ['values' => 'Simple host with item for Item history widget']
		]]);
		$column_overlay->getFooter()->query('button:Add')->waitUntilClickable()->one()->click();
		$column_overlay->waitUntilNotVisible();
		$form->waitUntilReloaded();

		// Save or cancel widget.
		if (CTestArrayHelper::get($data, 'save_widget', false)) {
			$form->submit();

			// Check that changes took place on the unsaved dashboard.
			$this->assertTrue($dashboard->getWidget($new_name)->isVisible());
		}
		else {
			$dialog = COverlayDialogElement::find()->one();
			$dialog->query('button:Cancel')->one()->click();
			$dialog->ensureNotPresent();

			if (CTestArrayHelper::get($data, 'update', false)) {
				foreach ([self::DEFAULT_WIDGET => true, $new_name => false] as $name => $valid) {
					$dashboard->getWidget($name, false)->isValid($valid);
				}
			}

			$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());
		}

		// Save or cancel dashboard update.
		if (CTestArrayHelper::get($data, 'save_dashboard', false)) {
			$dashboard->save();
		}
		else {
			$dashboard->cancelEditing();
		}

		// Check that no changes were made to the widget.
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	public function testDashboardItemHistoryWidget_Delete() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one()->edit();
		$widget = $dashboard->getWidget(self::DELETE_WIDGET);
		$dashboard->deleteWidget(self::DELETE_WIDGET);
		$widget->waitUntilNotPresent();
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Check that widget is not present on dashboard.
		$this->assertFalse($dashboard->getWidget(self::DELETE_WIDGET, false)->isValid());
		$widget_sql = 'SELECT NULL FROM widget_field wf LEFT JOIN widget w ON w.widgetid=wf.widgetid'.
				' WHERE w.name='.zbx_dbstr(self::DELETE_WIDGET);
		$this->assertEquals(0, CDBHelper::getCount($widget_sql));
	}

	public static function getTableData() {
		return [
			// #0 Simple test case with one item and one data entry.
			[
				[
					'initial_data' => [
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('now')),
							'Name' => 'Host name',
							'Value' => 'Zabbix Item history'
						]
					],
					'item_data' => [
						['itemid' => '42227', 'values' => 'Zabbix Item history', 'time' => strtotime('Now')]
					]
				]
			],
			// #1 Simple test case with one item and several data entries.
			[
				[
					'initial_data' => [
						[
							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('now')),
							'Name' => 'Host name',
							'Value'=> 'Zabbix Item history'
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-1 minute')),
							'Name' => 'Host name',
							'Value' => 'Zabbix Item history2'
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-2 minutes')),
							'Name' => 'Host name',
							'Value' => 'Zabbix plain ⓣⓔⓧⓣ'
						]
					],
					'item_data' => [
						['itemid' => '42227', 'values' => 'Zabbix Item history', 'time' => strtotime('now')],
						['itemid' => '42227', 'values' => 'Zabbix Item history2', 'time' => strtotime('-1 minute')],
						['itemid' => '42227', 'values' => 'Zabbix plain ⓣⓔⓧⓣ', 'time' => strtotime('-2 minutes')]
					]
				]
			],
			// #2 Test case with two items and several data entries.
			[
				[
					'initial_data' => [
						[
							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('now')),
							'Name' => 'Available memory',
							'Value' => '9.37 GB' // value rounding is expected.
						],
						[
							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('-30 seconds')),
							'Name' => 'Available memory in %',
							'Value'=> '82.0618 %' // value rounding is expected.
						],
						[
							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('-1 minute')),
							'Name' => 'Available memory in %',
							'Value' => '72.0618 %' // value rounding is expected.
						],
						[
							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('-1 hour')),
							'Name' => 'Available memory',
							'Value' => '8.44 GB' // value rounding is expected.
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours')),
							'Name' => 'Available memory',
							'Value'=> '7.51 GB' // value rounding is expected.
						]
					],
					'item_data' => [
						['itemid' => '42243', 'values' => '8061078528', 'time' => strtotime('-2 hours')],
						['itemid' => '42243', 'values' => '9061078528', 'time' => strtotime('-1 hour')],
						['itemid' => '42243', 'values' => '10061078528', 'time' => strtotime('now')],
						['itemid' => '42244', 'values' => '72.061797', 'time' => strtotime('-1 minute')],
						['itemid' => '42244', 'values' => '82.061797', 'time' => strtotime('-30 seconds')]
					]
				]
			],
			// #3 Test case with limited lines to show.
			[
				[
					'fields' => [
							'Show lines' => '1'
						],
					'initial_data' => [
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('today + 9 hours')),
							'Name' => 'Available memory',
							'Value'=> '9.37 GB' // value rounding is expected.
						]
						// TODO: ZBX-24488 Sub-issue (6).
//						[
//							'Timestamp' => date('Y-m-d H:i:s', strtotime('yesterday')),
//							'Available memory' => '8.44 GB' // value rounding is expected.
//						]
					],
					'result' => [
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('today + 9 hours')),
							'Name' => 'Available memory',
							'Value' => '9.37 GB' // value rounding is expected.
						]
					],
					'item_data' => [
						['itemid' => '42243', 'values' => '10061078528', 'time' => strtotime('today + 9 hours')],
						['itemid' => '42243', 'values' => '9061078528', 'time' => strtotime('yesterday')]
					]
				]
			]
			// #4 Test case for 'Items location' and 'Show text as HTML' options check.
//			[
//				[
//					'fields' => ['Layout' => 'Vertical'],
//					'edit_columns' => ['Host name' => ['Display' => 'HTML']],
//					'initial_data' => [
//						[
//							'Timestamp' => date('Y-m-d H:i:s', strtotime('now')),
//							'Master item' => '1' // value rounding is expected.
//						],
//						[
//							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('-15 hours')),
//							'Host name' => '<b>'.STRING_128.'</b>'
//						],
//						[
//							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('-16 hours')),
//							'Host name' => '<span style="text-transform:uppercase;">'.'test'.'</span>'
//						],
//                          TODO: ZBX-24488 Sub-issue (6).
////						[
////							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('-25 hours')),
////							'Host name' => STRING_255
////						]
//					],
//					'result' => [
//						[
//							'Timestamp' => date('Y-m-d H:i:s', strtotime('now')),
//							'Name' => 'Master item',
//							'Value' => '1'
//						],
//						[
//							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('-15 hours')),
//							'Name' => 'Host name',
//							'Value' => STRING_128
//						],
//						[
//							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('-16 hours')),
//							'Name' => 'Host name',
//							'Value' => 'TEST'
//						],
//                          TODO: ZBX-24488 Sub-issue (6).
////						[
////							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('-25 hours')),
////							'Name' => 'Host name',
////							'Value' => 'STRING_255'
////						]
//					],
//					'item_data' => [
//						['itemid' => '99142', 'values' => '1.00001', 'time' => strtotime('now')],
//						['itemid' => '42227', 'values' => '<b>'.STRING_128.'</b>', 'time' => strtotime('-15 hours')],
//						['itemid' => '42227', 'values' => '<span style="text-transform:uppercase;">'.'test'.'</span>',
//								'time' => strtotime('-16 hours')],
//						['itemid' => '42227', 'values' => STRING_255, 'time' => strtotime('-25 hours')]
//					]
//				]
//			],
//			// #5 Test case for host selection check.
//			[
//				[
//					'host_select' => [
//						'without_data' => 'Simple host with item for Item history widget',
//						'with_data' =>'ЗАББИКС Сервер'
//						],
//					'initial_data' => [
//						[
//							'Timestamp' => date('Y-m-d H:i:s', strtotime('now')),
//							'Name' => 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running',
//							'Value' => 'Zabbix Item history'
//						],
//						[
//							'Timestamp' => date('Y-m-d H:i:s', strtotime('-80 seconds')),
//							'Name' => 'Test item host: Master item',
//							'Value' => '7.7778' // value rounding is expected.
//						],
//						[
//							'Timestamp' => date('Y-m-d H:i:s', strtotime('-1 week')),
//							'Name' => 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running',
//							'Value' => STRING_255
//						],
//						[
//							'Timestamp' => date('Y-m-d H:i:s', strtotime('-1 month')),
//							'Name' => 'ЗАББИКС Сервер: Linux: Available memory in %',
//							'Value' => '82.0618 %' // value rounding is expected.
//						]
//					],
//					'result' => [
//						[
//							'Timestamp' => date('Y-m-d H:i:s', strtotime('now')),
//							'Name' =>  'Host name of Zabbix agent running',
//							'Value' => 'Zabbix Item history'
//						],
//						[
//							'Timestamp' => date('Y-m-d H:i:s', strtotime('-1 week')),
//							'Name' =>  'Host name of Zabbix agent running',
//							'Value' => STRING_255
//						],
//						[
//							'Timestamp' => date('Y-m-d H:i:s', strtotime('-1 month')),
//							'Name' =>  'Available memory in %',
//							'Value' => '82.0618 %' // value rounding is expected.
//						]
//					],
//					'item_data' => [
//						['itemid' => '42227', 'values' => 'Zabbix Item history', 'time' => strtotime('now')],
//						['itemid' => '99142', 'values' => '7.777777', 'time' => strtotime('-80 seconds')],
//						['itemid' => '42227', 'values' => STRING_255, 'time' => strtotime('-1 week')],
//						['itemid' => '42244', 'values' => '82.061797', 'time' => strtotime('-1 month')]
//					]
//				]
//			]
		];
	}

	/**
	 * @backup !history, !history_uint, !history_str
	 *
	 * @dataProvider getTableData
	 */
	public function testDashboardItemHistoryWidget_TableData($data) {
		foreach ($data['item_data'] as $params) {
			CDataHelper::addItemData($params['itemid'], $params['values'], $params['time']);
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_data)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dashboard->waitUntilReady();
		$this->assertTableData($data['initial_data']);

		$default_values = [
			'Layout' => 'Horizontal',
			'Show lines' => '25'
		];

		if (array_key_exists('fields', $data)) {
			$this->widgetConfigurationChange($data['fields'], $dashboard);
			$this->assertTableData($data['result']);
			$this->widgetConfigurationChange($default_values, $dashboard);
		}

		if (array_key_exists('host_select', $data)) {
			$multiselect_field = $dashboard->getControls()->query('class:multiselect-control')->asMultiselect()->one();
			$multiselect_field->fill($data['host_select']['without_data']);
			$dashboard->waitUntilReady();
			$this->assertTableData();
			$multiselect_field->fill($data['host_select']['with_data']);
			$dashboard->waitUntilReady();
			$this->assertTableData($data['result']);
			$multiselect_field->clear();
			$dashboard->waitUntilReady();
		}

		if (array_key_exists('result', $data)) {
			$this->assertTableData($data['initial_data']);
		}
	}

	/**
	 * Test function for assuring that all items are available in Item History widget.
	 */
	public function testDashboardItemHistoryWidget_CheckAvailableItems() {
		$this->checkAvailableItems('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid,
				'Item history'
		);
	}

	/**
	 * Change Item history widget configuration.
	 *
	 * @param CDashboardElement		$dashboard			dashboard element
	 * @param array 				$configuration    	widget parameter(s)
	 */
	protected function widgetConfigurationChange($configuration, $dashboard) {
		$form = $dashboard->getWidget(self::DATA_WIDET)->edit();
		$form->fill($configuration);
		$form->submit();
		$dashboard->save();
		$dashboard->waitUntilReady();
	}

	/**
	 * Check field's hint text.
	 *
	 * @param CFormElement $form         given form
	 * @param string       $label        checked field's label
	 * @param string       $hint_text    text of the hint
	 */
	protected function checkHint($form, $label, $hint_text) {
		$form->getLabel($label)->query('xpath:./button[@data-hintbox]')->one()->click();
		$hint = $this->query('xpath://div[@data-hintboxid]')->waitUntilVisible();
		$this->assertEquals($hint_text, $hint->one()->getText());
		$hint->one()->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click();
		$hint->waitUntilNotPresent();
	}
}

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

	const DEFAULT_WIDGET = 'Default Item history Widget';
	const DELETE_WIDGET = 'Widget for delete';
	const DATA_WIDGET = 'Widget for data check';

	protected static $dashboardid;
	protected static $dashboard_create;
	protected static $dashboard_data;
	protected static $update_widget = 'Update Item history Widget';

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
							],
							[
								'type' => 'graph',
								'name' => 'Classic graph for time period reference',
								'x' => 12,
								'y' => 0,
								'width' => 24,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source_type',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid.0',
										'value' => $itemids['Test Item history']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'FEDCB'
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
								'name' => self::DATA_WIDGET,
								'x' => 0,
								'y' => 0,
								'width' => 60,
								'height' => 6,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show_timestamp',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => 'now-1y'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => 'now/d'
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
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.history',
										'value' => 1
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
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.2.history',
										'value' => 1
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
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.3.history',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.4.name',
										'value' => 'Master item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.4.history',
										'value' => 1
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
			'Items' => [],
			'Show lines' => '25',
			'Override host' => '',
			'New values' => 'Top',
			'Show timestamp' => false,
			'Show column header' => 'Vertical',
			'Time period' => 'Dashboard',
			'Widget' => '',
			'id:time_period_from' => 'now-1h',
			'id:time_period_to' => 'now'
		];
		$form->checkValue($default_state);

		// Check required fields.
		$this->assertEquals(['Items', 'Show lines'], $form->getRequiredLabels());

		// Check attributes of input elements.
		$inputs = [
			'Name' => [
				'maxlength' => '255',
				'placeholder' => 'default'
			],
			'Show lines' => [
				'maxlength' => '4'
			],
			'id:override_hostid_ms' => [
				'placeholder' => 'type here to search'
			],
			'id:time_period_from' => [
				'maxlength' => '255',
				'placeholder' => 'YYYY-MM-DD hh:mm:ss'
			],
			'id:time_period_to' => [
				'maxlength' => '255',
				'placeholder' => 'YYYY-MM-DD hh:mm:ss'
			]
		];

		foreach ($inputs as $field => $attributes) {
			$this->assertTrue($form->getField($field)->isAttributePresent($attributes));
		}

		// Check radio buttons.
		$radiobuttons = [
			'Layout' => ['Horizontal', 'Vertical'],
			'New values' => ['Top', 'Bottom'],
			'Show column header' => ['Off', 'Horizontal', 'Vertical'],
			'Time period' => ['Dashboard', 'Widget', 'Custom']
		];

		foreach ($radiobuttons as $field => $labels) {
			$this->assertEquals($labels, $form->getField($field)->getLabels()->asText());
		}

		$refresh_interval = ['Default (1 minute)', 'No refresh', '10 seconds', '30 seconds', '1 minute',
				'2 minutes', '10 minutes', '15 minutes'
		];
		$this->assertEquals($refresh_interval, $form->getField('Refresh interval')->getOptions()->asText());

		// Check Column popup.
		$form->getFieldContainer('Items')->query('button:Add')->waitUntilClickable()->one()->click();
		$column_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$this->assertEquals('New column', $column_overlay->getTitle());
		$column_form = $column_overlay->asForm();
		$this->assertEquals(['Name', 'Item', 'Base colour', 'Highlights', 'Display', 'Min', 'Max', 'Thresholds',
				'History data', 'Use monospace font', 'Display log time', 'Show thumbnail'],
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
			'id:max_length' => ['value' => 100, 'maxlength' => 3],
			'Use monospace font' => ['value' => false],
			'Display log time' => ['value' => false],
			'Show thumbnail' => ['value' => false]
		];

		foreach ($defaults as $label => $attributes) {
			$field = $column_form->getField($label);
			$this->assertEquals($attributes['value'], $field->getValue());

			foreach (['maxlength', 'placeholder'] as $attribute) {
				if (array_key_exists($attribute, $attributes)) {
					$this->assertEquals($attributes[$attribute], $field->getAttribute($attribute));
				}
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
		$this->assertEquals('Host for all item value types: Binary item', $column_form->getField('Name')->getValue());
		$this->assertEquals(['Name', 'Item', 'Base colour', 'Show thumbnail'],
				array_values($column_form->getLabels()->filter(CElementFilter::VISIBLE)->asText())
		);

		// Check fields for character, text and log items.
		foreach (['Character item', 'Text item', 'Log item'] as $i => $item) {
			$column_form->fill(['Item' => $item]);
			$this->assertEquals('Host for all item value types: '.$item, $column_form->getField('Name')->getValue());

			$labels = ($item === 'Log item')
				? ['Name', 'Item', 'Base colour', 'Highlights', 'Display', 'Use monospace font', 'Display log time']
				: ['Name', 'Item', 'Base colour', 'Highlights', 'Display', 'Use monospace font'];

			$this->assertEquals($labels, array_values($column_form->getLabels()->filter(CElementFilter::VISIBLE)->asText()));
			$this->checkThresholdsHighlights($column_form, 'Highlights', $i, '_pattern');
			$this->checkHint($column_form, 'Display',
					'Single line - result will be displayed in a single line and truncated to specified length.'
			);

			$display_maxlength_dependencies = [
				'As is' => false,
				'HTML' => false,
				'Single line' => true
			];
			foreach ($display_maxlength_dependencies as $label => $status) {
				$column_form->fill(['Display' => $label]);
				$max_length = $column_form->getField('id:max_length');
				$this->assertTrue($max_length->isEnabled($status));
				$this->assertTrue($max_length->isVisible($status));
			}

			if ($item === 'Log item') {
				$this->checkHint($column_form, 'Display log time', 'This setting will display log time'.
						' instead of item\'s timestamp. "Show timestamp" must also be checked in the advanced'.
						' configuration.'
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

			$this->checkThresholdsHighlights($column_form, 'Thresholds', $j, '_threshold');
		}

		$column_overlay->close();

		// Check if buttons present and clickable.
		$this->assertEquals(['Add', 'Cancel'], $dialog->getFooter()->query('button')->all()
				->filter(CElementFilter::CLICKABLE)->asText()
		);

		$visible_labels = ['Type', 'Show header', 'Name', 'Refresh interval', 'Layout', 'Items', 'Show lines',
				'Override host', 'Advanced configuration'
		];
		$hidden_labels = ['New values', 'Show timestamp', 'Show column header', 'Time period', 'Widget', 'From', 'To'];
		$this->assertEquals($visible_labels, array_values($form->getLabels()->filter(CElementFilter::VISIBLE)->asText()));
		$this->assertEquals($hidden_labels, array_values($form->getLabels()->filter(CElementFilter::NOT_VISIBLE)->asText()));

		$expanded_labels = ['New values', 'Show timestamp', 'Show column header', 'Time period'];
		$form->fill(['Advanced configuration' => true]);
		$this->assertEquals(array_merge($visible_labels, $expanded_labels),
				array_values($form->getLabels()->filter(CElementFilter::VISIBLE)->asText())
		);

		$time_period_fields = [
			'Dashboard' => ['Widget' => false, 'From' => false, 'To' => false],
			'Widget' => ['Widget' => true, 'From' => false, 'To' => false],
			'Custom' => ['Widget' => false, 'From' => true, 'To' => true]
		];

		foreach ($time_period_fields as $label => $visibility) {
			$form->fill(['Time period' => $label]);

			foreach ($visibility as $field => $status) {
				$this->assertTrue($form->getField($field)->isEnabled($status));
			}

			// Check Widget multiselect's placeholder.
			if ($label === 'Widget') {
				$this->assertTrue($form->getField('id:time_period_reference_ms')
						->isAttributePresent(['placeholder' => 'type here to search'])
				);
			}

			// Check calendar buttons.
			if ($label === 'Custom') {
				foreach (['time_period_from_calendar', 'time_period_to_calendar'] as $button) {
					$this->assertTrue($form->query('id', $button)->one()->isClickable());
				}
			}
		}

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
					'error' => 'Invalid parameter "Items": cannot be empty.'
				]
			],
			// #1.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show lines' => ''
					],
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Available memory in %',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
							]
						]
					],
					'error' => ['Invalid parameter "Show lines": value must be one of 1-1000.']
				]
			],
			// #2.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show lines' => '0'
					],
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Available memory in %',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
							]
						]
					],
					'error' => 'Invalid parameter "Show lines": value must be one of 1-1000.'
				]
			],
			// #3.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show lines' => '1001'
					],
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Available memory in %',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
							]
						]
					],
					'error' => 'Invalid parameter "Show lines": value must be one of 1-1000.'
				]
			],
			// #4.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show lines' => ' '
					],
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Available memory in %',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
							]
						]
					],
					'error' => 'Invalid parameter "Show lines": value must be one of 1-1000.'
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
						'Invalid parameter "Items": cannot be empty.',
						'Invalid parameter "Show lines": value must be one of 1-1000.'
					]
				]
			],
			// #6.
			[
				[
					'expected' => TEST_GOOD,
					'same_host' => 'ЗАББИКС Сервер',
					'fields' => [
						'Name' => '2 items from one host'
					],
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Available memory in %',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
							]
						],
						[
							'fields' => [
								'Name' => 'Column2',
								'Item' => [
									'values' => 'Available memory',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
							]
						]
					]
				]
			],
			// #7 Test case with items from two different hosts.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => ''
					],
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Available memory in %',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
							]
						],
						[
							'fields' => [
								'Name' => 'Column2',
								'Item' => [
									'values' => 'Test Item history',
									'context' => ['values' => 'Simple host with item for Item history widget']
								]
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
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Available memory in %',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
							]
						],
						[
							'fields' => [
								'Name' => 'Column2',
								'Item' => [
									'values' => 'Available memory',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
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
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Available memory in %',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
							]
						],
						[
							'fields' => [
								'Name' => 'Column2',
								'Item' => [
									'values' => 'Test Item history',
									'context' => ['values' => 'Simple host with item for Item history widget']
								]
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
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Test Item history',
									'context' => ['values' => 'Simple host with item for Item history widget']
								]
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
						'Refresh interval' => 'No refresh',
						'Layout' => 'Vertical',
						'Advanced configuration' => true,
						'New values' => 'Bottom',
						'Show timestamp' => true,
						'Show column header' => 'Horizontal'
					],
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Available memory',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
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
						'Refresh interval' => '10 seconds',
						'Advanced configuration' => true,
						'Show column header' => 'Off'
					],
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Available memory',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
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
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Available memory',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
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
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Available memory',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
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
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Available memory',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
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
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Available memory',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
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
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Available memory',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
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
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Available memory',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
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
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Available memory',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
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
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Available memory',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
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
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Test Item history',
									'context' => ['values' => 'Simple host with item for Item history widget']
								]
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
					'Items' => [
						[
							'fields' => [
								'Name' => 'Column1',
								'Item' => [
									'values' => 'Available memory',
									'context' => ['values' => 'ЗАББИКС Сервер']
								]
							]
						]
					]
				]
			],
			// #23.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty name in column'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Available memory',
									'context' => ['values' => 'ЗАББИКС Сервер']
								],
								'Name' => ''
							]
						]
					],
					'column_error' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			// #24.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Binary column with color'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Binary item',
									'context' => ['values' => 'Host for all item value types']
								]
							],
							'xpath://input[@id="base_color"]/..' => '90CAF9',
							'Show thumbnail' => true
						]
					]
				]
			],
			// #25.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Character column with color and highlights'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Character item',
									'context' => ['values' => 'Host for all item value types']
								],
								'xpath://input[@id="base_color"]/..' => 'AFB42B',
								'Display' => 'HTML',
								'Use monospace font' => true
							],
							'Highlights' => [
								[
									'xpath://input[@id="highlights_0_color"]/..' => '00ACC1',
									'id:highlights_0_pattern' => 'pattern_1'
								],
								[
									'xpath://input[@id="highlights_1_color"]/..' => '00ACC1',
									'id:highlights_1_pattern' => 12345
								]
							]
						]
					]
				]
			],
			// #26.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Duplicated highlights'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Character item',
									'context' => ['values' => 'Host for all item value types']
								],
								'Display' => 'HTML'
							],
							'Highlights' => [
								[
									'xpath://input[@id="highlights_0_color"]/..' => '00ACC1',
									'id:highlights_0_pattern' => 'pattern_1'
								],
								[
									'xpath://input[@id="highlights_1_color"]/..' => '0288D1',
									'id:highlights_1_pattern' => 'pattern_1'
								]
							]
						]
					],
					'column_error' => 'Invalid parameter "/1/highlights/2": value (pattern)=(pattern_1) already exists.'
				]
			],
			// #27.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Character column with empty Highlight'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Character item',
									'context' => ['values' => 'Host for all item value types']
								],
								'Display' => 'HTML',
								'Use monospace font' => true
							],
							'Highlights' => [
								[
									'xpath://input[@id="highlights_0_color"]/..' => '00ACC1'
								]
							]
						]
					],
					'column_error' => 'Invalid parameter "/1/highlights/1/pattern": cannot be empty.'
				]
			],
			// #28.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Character column with 0 max length'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Character item',
									'context' => ['values' => 'Host for all item value types']
								],
								'Display' => 'Single line',
								'Use monospace font' => true,
								'id:max_length' => 0
							]
						]
					],
					'column_error' => 'Invalid parameter "/1/max_length": value must be one of 1-500.'
				]
			],
			// #29.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Character column with text in max length'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Character item',
									'context' => ['values' => 'Host for all item value types']
								],
								'Display' => 'Single line',
								'Use monospace font' => true,
								'id:max_length' => 'text'
							]
						]
					],
					'column_error' => 'Invalid parameter "/1/max_length": value must be one of 1-500.'
				]
			],
			// #30.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Character column with too large max length'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Character item',
									'context' => ['values' => 'Host for all item value types']
								],
								'Display' => 'Single line',
								'id:max_length' => 599
							]
						]
					],
					'column_error' => 'Invalid parameter "/1/max_length": value must be one of 1-500.'
				]
			],
			// #31.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Float column with text in min'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Float item',
									'context' => ['values' => 'Host for all item value types']
								],
								'Display' => 'Bar',
								'id:min' => 'min'
							]
						]
					],
					'column_error' => 'Invalid parameter "/1/min": a number is expected.'
				]
			],
			// #32.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Float column with text in max'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Float item',
									'context' => ['values' => 'Host for all item value types']
								],
								'Display' => 'Bar',
								'id:max' => 'max'
							]
						]
					],
					'column_error' => 'Invalid parameter "/1/max": a number is expected.'
				]
			],
			// #33.
			[
				[
					'fields' => [
						'Name' => 'Float column with Bar display and calculated min/max'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Float item',
									'context' => ['values' => 'Host for all item value types']
								],
								'Display' => 'Bar'
							]
						]
					]
				]
			],
			// #34.
			[
				[
					'fields' => [
						'Name' => 'Float column with Bar display and negative min/max'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Float item',
									'context' => ['values' => 'Host for all item value types']
								],
								'Display' => 'Bar',
								'id:min' => -9999,
								'id:max' => -20
							]
						]
					]
				]
			],
			// #35.
			[
				[
					'fields' => [
						'Name' => 'Float column with Bar display and float min/max'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Float item',
									'context' => ['values' => 'Host for all item value types']
								],
								'Display' => 'Bar',
								'id:min' => -1.5000009,
								'id:max' => -30
							]
						]
					]
				]
			],
			// #36.
			[
				[
					'fields' => [
						'Name' => 'Unsigned column with Indicators display and float min/max'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Float item',
									'context' => ['values' => 'Host for all item value types']
								],
								'Display' => 'Indicators',
								'id:min' => -1.5000009,
								'id:max' => -500.999
							]
						]
					]
				]
			],
			// #37.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Float column with Indicators display and text in min'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Float item',
									'context' => ['values' => 'Host for all item value types']
								],
								'Display' => 'Indicators',
								'id:min' => 'minimal'
							]
						]
					],
					'column_error' => 'Invalid parameter "/1/min": a number is expected.'
				]
			],
			// #38.
			[
				[
					'fields' => [
						'Name' => 'Unsigned column with Indicators display and calculated min/max'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Unsigned item',
									'context' => ['values' => 'Host for all item value types']
								],
								'Display' => 'Indicators'
							]
						]
					]
				]
			],
			// #39.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Duplicated Thresholds'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Float item',
									'context' => ['values' => 'Host for all item value types']
								]
							],
							'Thresholds' => [
								[
									'id:thresholds_0_threshold' => 12
								],
								[
									'id:thresholds_1_threshold' => 12
								]
							]
						]
					],
					'column_error' => 'Invalid parameter "/1/thresholds/2": value (threshold)=(12) already exists.'
				]
			],
			// #40.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Text in Thresholds'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Float item',
									'context' => ['values' => 'Host for all item value types']
								]
							],
							'Thresholds' => [
								[
									'id:thresholds_0_threshold' => 'text'
								]
							]
						]
					],
					'column_error' => 'Invalid parameter "/1/thresholds/1/threshold": a number is expected.'
				]
			],
			// #41.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Float with negative Thresholds'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Float item',
									'context' => ['values' => 'Host for all item value types']
								],
								'Display' => 'Bar',
								'id:min' => 0,
								'id:max' => 0,
								'History data' => 'History'
							],
							'Thresholds' => [
								[
									'xpath://input[@id="thresholds_0_color"]/..' => '039BE5',
									'id:thresholds_0_threshold' => -12
								],
								[
									'xpath://input[@id="thresholds_1_color"]/..' => '039BE5',
									'id:thresholds_1_threshold' => -500.99
								],
								[
									'xpath://input[@id="thresholds_2_color"]/..' => '00ACC1',
									'id:thresholds_2_threshold' => 20.0099
								]
							]
						]
					]
				]
			],
			// #42.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Float with different colors Thresholds'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Float item',
									'context' => ['values' => 'Host for all item value types']
								],
								'Display' => 'Indicators',
								'id:min' => 9999999,
								'id:max' => 9999999999999,
								'History data' => 'Trends'
							],
							'Thresholds' => [
								[
									'xpath://input[@id="thresholds_0_color"]/..' => 'E91E63',
									'id:thresholds_0_threshold' => 158
								],
								[
									'xpath://input[@id="thresholds_1_color"]/..' => '039BE5',
									'id:thresholds_1_threshold' => 19.20
								]
							]
						]
					]
				]
			],
			// #43.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Float with empty Threshold'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Float item',
									'context' => ['values' => 'Host for all item value types']
								]
							],
							'Thresholds' => [
								[
									'xpath://input[@id="thresholds_0_color"]/..' => 'E91E63'
								]
							]
						]
					]
				]
			],
			// #44.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Log item column'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Log item',
									'context' => ['values' => 'Host for all item value types']
								],
								'Use monospace font' => true,
								'Display log time' => true
							]
						]
					]
				]
			],
			// #45.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Text item column'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Text item',
									'context' => ['values' => 'Host for all item value types']
								],
								'Use monospace font' => true
							]
						]
					]
				]
			],
			// #46.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Time period = empty widget',
						'Advanced configuration' => true,
						'Time period' => 'Widget'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Text item',
									'context' => ['values' => 'Host for all item value types']
								]
							]
						]
					],
					'error' => 'Invalid parameter "Time period/Widget": cannot be empty.'
				]
			],
			// #47.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Time period = Custom, empty from/to',
						'Advanced configuration' => true,
						'Time period' => 'Custom',
						'From' => '',
						'To' => ''
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Text item',
									'context' => ['values' => 'Host for all item value types']
								]
							]
						]
					],
					'error' => [
						'Invalid parameter "Time period/From": cannot be empty.',
						'Invalid parameter "Time period/To": cannot be empty.'
					]
				]
			],
			// #48.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Time period = Custom, wrong from/to',
						'Advanced configuration' => true,
						'Time period' => 'Custom',
						'From' => 'test_1',
						'To' => 'test_2'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Text item',
									'context' => ['values' => 'Host for all item value types']
								]
							]
						]
					],
					'error' => [
						'Invalid parameter "Time period/From": a time is expected.',
						'Invalid parameter "Time period/To": a time is expected.'
					]
				]
			],
			// #49.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Time period = Custom, Default from/to',
						'Advanced configuration' => true,
						'Time period' => 'Custom'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Text item',
									'context' => ['values' => 'Host for all item value types']
								]
							]
						]
					]
				]
			],
			// #50.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Time period = Custom',
						'Advanced configuration' => true,
						'Time period' => 'Custom',
						'From' => 'now-1y',
						'To' => 'now-1M'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Text item',
									'context' => ['values' => 'Host for all item value types']
								]
							]
						]
					],
					'check_time' => 'now-1y – now-1M'
				]
			],
			// #51.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Time period = Custom, To in future',
						'Advanced configuration' => true,
						'Time period' => 'Custom'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Text item',
									'context' => ['values' => 'Host for all item value types']
								]
							]
						]
					],
					'time_shift' => true,
					'check_time' => 'future'
				]
			],
			// #52.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Time period = Custom, human readable in the past',
						'Advanced configuration' => true,
						'Time period' => 'Custom'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Text item',
									'context' => ['values' => 'Host for all item value types']
								]
							]
						]
					],
					'time_shift' => true,
					'check_time' => 'past'
				]
			],
			// #53.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Time period = Custom, To < From',
						'Advanced configuration' => true,
						'Time period' => 'Custom',
						'From' => 'now-1M',
						'To' => 'now-2M'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Text item',
									'context' => ['values' => 'Host for all item value types']
								]
							]
						]
					],
					'error' => 'Minimum time period to display is 1 minute.'
				]
			],
			// #54.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Time period = Custom, period 30s',
						'Advanced configuration' => true,
						'Time period' => 'Custom'
					],
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Text item',
									'context' => ['values' => 'Host for all item value types']
								]
							]
						]
					],
					'time_shift' => true,
					'check_time' => 'custom',
					'error' => 'Minimum time period to display is 1 minute.'
				]
			],
			// #55.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Time period = widget',
						'Advanced configuration' => true,
						'Time period' => 'Widget',
						'Widget' => 'Classic graph for time period reference'
					],
					'clear_custom' => true,
					'Items' => [
						[
							'fields' => [
								'Item' => [
									'values' => 'Text item',
									'context' => ['values' => 'Host for all item value types']
								]
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
		$old_hash = CDBHelper::getHash(self::SQL);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_create)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dashboard->getWidget(self::$update_widget)->edit()->submit();
		$dashboard->save();
		$this->page->waitUntilReady();

		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
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
	 * @param array   $data      data provider
	 * @param boolean $update    updating is performed
	 */
	protected function checkWidgetForm($data, $update = false) {
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);

		if ($expected === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::SQL);
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
			$button_remove = $form->query('id:list_columns')->asTable()->one()->query('button:Remove');
			$remove_count = $button_remove->count();

			for ($i = 0; $i < $remove_count; $i++) {
				$button_remove->waitUntilClickable()->one()->click();
				$form->waitUntilReloaded();
			}
		}

		// Reset custom period fields to defaults, because they are saved from previous cases.
		if ($update && CTestArrayHelper::get($data, 'clear_custom')) {
			$form->fill([
				'Advanced configuration' => true,
				'Time period' => 'Custom',
				'From' => 'now-1h',
				'To' => 'now'
			]);
		}

		$form->fill($data['fields']);

		// Fill time period with data From: now, To: now +/- time shift in human-readable format.
		if (CTestArrayHelper::get($data, 'time_shift', false)) {
			$time = time();
			switch ($data['check_time']) {
				case 'future':
					$period = [
						'From' => date('Y-m-d H:i:s', $time), // Now.
						'To' => date('Y-m-d H:i:s', ($time + 31536000)) // Now + 1 year.
					];
					break;

				case 'past':
					$period = [
						'From' => date('Y-m-d H:i:s', $time - 31536000), // Now - 1 Year.
						'To' => date('Y-m-d H:i:s', ($time)) // Now.
					];
					break;

				case 'custom':
					$period = [
						'From' => date('Y-m-d H:i:s', $time - 30), // Now - 30 seconds.
						'To' => date('Y-m-d H:i:s', ($time)) // Now.
					];
					break;
			}
			$form->fill($period);

			$data['check_time'] = $period['From'].' – '.$period['To'];
		}

		// Fill Items field.
		if (array_key_exists('Items', $data)) {
			foreach ($data['Items'] as $column) {
				$form->getFieldContainer('Items')->query('button:Add')->one()->waitUntilClickable()->click();
				$column_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$column_overlay_form = $column_overlay->asForm();
				$column_overlay_form->fill($column['fields']);

				foreach (['Highlights', 'Thresholds'] as $table_field) {
					if (array_key_exists($table_field, $column)) {
						foreach ($column[$table_field] as $highlight) {
							$column_overlay_form->getFieldContainer($table_field)->query('button:Add')->one()
								->waitUntilClickable()->click();
							$column_overlay_form->fill($highlight);
						}
					}
				}

				$column_overlay->getFooter()->query('button:Add')->waitUntilClickable()->one()->click();

				if (array_key_exists('column_error', $data)) {
					break;
				}

				$column_overlay->waitUntilNotVisible();
				$form->waitUntilReloaded();
			}
		}

		if (!array_key_exists('column_error', $data)) {
			$form->fill($data['fields']);
			$values = $form->getValues();
			$form->submit();
		}

		// Trim leading and trailing spaces from expected results if necessary.
		if (array_key_exists('trim', $data)) {
			foreach ($data['trim'] as $field) {
				$data['fields'][$field] = trim($data['fields'][$field]);
				$values[$field] = trim($data['fields'][$field]);
			}
		}

		if ($expected === TEST_BAD) {
			if (array_key_exists('column_error', $data)) {
				$data['error'] = $data['column_error'];
			}

			$this->assertMessage($data['expected'], null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));

			// Check that after error and cancellation of the widget, the widget is not available on dashboard.
			$dialogs = COverlayDialogElement::find()->all();
			$dialog_count = $dialogs->count();

			for ($i = $dialog_count - 1; $i >= 0; $i--) {
				$dialogs->get($i)->close(true);
			}

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

			if ($update) {
				self::$update_widget = $header;
			}

			// Check widgets count.
			$this->assertEquals($old_widget_count + ($update ? 0 : 1), $dashboard->getWidgets()->count());

			if (array_key_exists('check_time', $data)) {
				$this->assertEquals($data['check_time'], $widget->getTimeInterval());
			}

			// Check new widget update interval.
			$refresh = (CTestArrayHelper::get($data['fields'], 'Refresh interval', 'Default (1 minute)') === 'Default (1 minute)')
				? '1 minute'
				: $data['fields']['Refresh interval'];
			$this->assertEquals($refresh, $widget->getRefreshInterval());

			// Check new widget form fields and values in frontend.
			$saved_form = $widget->edit();

			// 'Advanced configuration' is always collapsed after save.
			$values['Advanced configuration'] = false;
			$data['fields']['Advanced configuration'] = false;

			$this->assertEquals($values, $saved_form->getValues());
			$table = $saved_form->query('id:list_columns')->asTable()->one();

			// Count is minus one row because of Add button row.
			$columns_count = $table->getRows()->count() - 1;
			$this->assertEquals(count($data['Items']), $columns_count);

			foreach ($data['Items'] as $i => $column) {
				$row = $table->getRow($i);

				$column_name = (array_key_exists('Name', $column['fields']))
					? $column['fields']['Name']
					: $column['fields']['Item']['context']['values'].': '.$column['fields']['Item']['values'];

				$this->assertEquals($column_name, $row->getColumn('Name')->getText());
				$this->assertEquals($column['fields']['Item']['context']['values'].': '.$column['fields']['Item']['values'],
						$row->getColumn('Item')->getText()
				);
			}

			$saved_form->checkValue($data['fields']);

			// Close widget window and cancel editing the dashboard.
			COverlayDialogElement::find()->one()->close();
			$dashboard->cancelEditing();
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
		$old_hash = CDBHelper::getHash(self::SQL);
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

		$form->getFieldContainer('Items')->query('button:Add')->waitUntilClickable()->one()->click();
		$column_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$column_overlay->asForm()->fill([
			'Item' => [
				'values' => 'Test Item history',
				'context' => ['values' => 'Simple host with item for Item history widget']
			]
		]);
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
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
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
							'Timestamp' => date('Y-m-d H:i:s', strtotime('now')),
							'Name' => 'Host name',
							'Value' => 'Zabbix Item history'
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
							'Timestamp' => date('Y-m-d H:i:s', strtotime('now')),
							'Name' => 'Available memory',
							'Value' => '9.37 GB' // value rounding is expected.
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-30 seconds')),
							'Name' => 'Available memory in %',
							'Value' => '82.0618 %' // value rounding is expected.
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-1 minute')),
							'Name' => 'Available memory in %',
							'Value' => '72.0618 %' // value rounding is expected.
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')),
							'Name' => 'Available memory',
							'Value' => '8.44 GB' // value rounding is expected.
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours')),
							'Name' => 'Available memory',
							'Value' => '7.51 GB' // value rounding is expected.
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
							'Value' => '9.37 GB' // Value rounding is expected.
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('yesterday')),
							'Name' => 'Available memory',
							'Value' => '8.44 GB' // Value rounding is expected.
						]
					],
					'result' => [
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('today + 9 hours')),
							'Name' => 'Available memory',
							'Value' => '9.37 GB' // Value rounding is expected.
						]
					],
					'item_data' => [
						['itemid' => '42243', 'values' => '10061078528', 'time' => strtotime('today + 9 hours')],
						['itemid' => '42243', 'values' => '9061078528', 'time' => strtotime('yesterday')]
					]
				]
			],
			// #4 Test case for 'Items location' and 'Show text as HTML' options check.
			[
				[
					'fields' => ['Layout' => 'Vertical'],
					'edit_columns' => [
						'Host name' => ['Display' => 'HTML']
					],
					'initial_data' => [
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('now')),
							'Name' => 'Master item',
							'Value' => '1' // Value rounding is expected.
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-15 hours')),
							'Name' => 'Host name',
							'Value' => '<b>'.STRING_128.'</b>'
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-16 hours')),
							'Name' => 'Host name',
							'Value' => '<span style="text-transform:uppercase;">'.'test'.'</span>'
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-25 hours')),
							'Name' => 'Host name',
							'Value' => STRING_255
						]
					],
					'result' => [
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('now')),
							'Master item' => '1' // Value rounding is expected.
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-15 hours')),
							'Host name' => STRING_128
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-16 hours')),
							'Host name' => 'TEST'
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-25 hours')),
							'Host name' => STRING_255
						]
					],
					'item_data' => [
						['itemid' => '99142', 'values' => '1.00001', 'time' => strtotime('now')],
						['itemid' => '42227', 'values' => '<b>'.STRING_128.'</b>', 'time' => strtotime('-15 hours')],
						['itemid' => '42227', 'values' => '<span style="text-transform:uppercase;">'.'test'.'</span>',
								'time' => strtotime('-16 hours')
						],
						['itemid' => '42227', 'values' => STRING_255, 'time' => strtotime('-25 hours')]
					]
				]
			],
			// #5 Test case for 'Items location' and 'Show text as Single line' options check.
			[
				[
					'fields' => [
						'Advanced configuration' => true,
						'New values' => 'Bottom',
						'Show timestamp' => false
					],
					'edit_columns' => [
						'Host name' => ['Display' => 'Single line', 'id:max_length' => 3]
					],
					'initial_data' => [
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('now')),
							'Name' => 'Master item',
							'Value' => '1' // Value rounding is expected.
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-15 hours')),
							'Name' => 'Host name',
							'Value' => '<b>'.STRING_128.'</b>'
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-16 hours')),
							'Name' => 'Host name',
							'Value' => '<span style="text-transform:uppercase;">'.'test'.'</span>'
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-25 hours')),
							'Name' => 'Host name',
							'Value' => STRING_255
						]
					],
					'result' => [
						[
							'Name' => 'Host name',
							'Value' => 'lon…'
						],
						[
							'Name' => 'Host name',
							'Value' => '<sp…'
						],
						[
							'Name' => 'Host name',
							'Value' => '<b>…'
						],
						[
							'Name' => 'Master item',
							'Value' => '1' // Value rounding is expected.
						]
					],
					'item_data' => [
						['itemid' => '99142', 'values' => '1.00001', 'time' => strtotime('now')],
						['itemid' => '42227', 'values' => '<b>'.STRING_128.'</b>', 'time' => strtotime('-15 hours')],
						['itemid' => '42227', 'values' => '<span style="text-transform:uppercase;">'.'test'.'</span>',
								'time' => strtotime('-16 hours')
						],
						['itemid' => '42227', 'values' => STRING_255, 'time' => strtotime('-25 hours')]
					]
				]
			],
			// #6 Test case for host selection check.
			[
				[
					'host_select' => [
						'without_data' => 'Simple host with item for Item history widget',
						'with_data' => 'ЗАББИКС Сервер'
					],
					'initial_data' => [
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('now')),
							'Name' => 'Host name',
							'Value' => 'Zabbix Item history'
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-80 seconds')),
							'Name' => 'Master item',
							'Value' => '7.7778' // Value rounding is expected.
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-1 week')),
							'Name' => 'Host name',
							'Value' => STRING_255
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-1 month')),
							'Name' => 'Available memory in %',
							'Value' => '82.0618 %' // Value rounding is expected.
						]
					],
					'result' => [
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('now')),
							'Name' => 'Host name',
							'Value' => 'Zabbix Item history'
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-1 week')),
							'Name' => 'Host name',
							'Value' => STRING_255
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-1 month')),
							'Name' => 'Available memory in %',
							'Value' => '82.0618 %' // Value rounding is expected.
						]
					],
					'item_data' => [
						['itemid' => '42227', 'values' => 'Zabbix Item history', 'time' => strtotime('now')],
						['itemid' => '99142', 'values' => '7.777777', 'time' => strtotime('-80 seconds')],
						['itemid' => '42227', 'values' => STRING_255, 'time' => strtotime('-1 week')],
						['itemid' => '42244', 'values' => '82.061797', 'time' => strtotime('-1 month')]
					]
				]
			],
			// #7 Test case for Auto/History options check.
			[
				[
					'fields' => [
						'Advanced configuration' => true,
						'Time period' => 'Custom'
					],
					'edit_columns' => [
						'Master item' => ['History data' => 'Auto']
					],
					'initial_data' => [
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('now')),
							'Name' => 'Master item',
							'Value' => '1' // Value rounding is expected.
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-15 hours')),
							'Name' => 'Host name',
							'Value' => '<b>'.STRING_128.'</b>'
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-16 hours')),
							'Name' => 'Host name',
							'Value' => '<span style="text-transform:uppercase;">'.'test'.'</span>'
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-25 hours')),
							'Name' => 'Host name',
							'Value' => STRING_255
						]
					],
					'result' => [
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-15 hours')),
							'Name' => 'Host name',
							'Value' => '<b>'.STRING_128.'</b>'
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-16 hours')),
							'Name' => 'Host name',
							'Value' => '<span style="text-transform:uppercase;">'.'test'.'</span>'
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-25 hours')),
							'Name' => 'Host name',
							'Value' => STRING_255
						]
					],
					'item_data' => [
						['itemid' => '99142', 'values' => '1.00001', 'time' => strtotime('now')],
						['itemid' => '42227', 'values' => '<b>'.STRING_128.'</b>', 'time' => strtotime('-15 hours')],
						['itemid' => '42227', 'values' => '<span style="text-transform:uppercase;">'.'test'.'</span>',
							'time' => strtotime('-16 hours')
						],
						['itemid' => '42227', 'values' => STRING_255, 'time' => strtotime('-25 hours')]
					]
				]
			],
			// #8 Test case for Time period check.
			[
				[
					'fields' => [
						'Advanced configuration' => true,
						'Time period' => 'Custom',
						'From' => date('Y-m-d H:i:s', strtotime('-5 days')),
						'To' => date('Y-m-d H:i:s', strtotime('now'))
					],
					'initial_data' => [
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('+1 hours')),
							'Name' => 'Available memory in %',
							'Value' => '82.0618 %' // Value rounding is expected.
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('now')),
							'Name' => 'Host name',
							'Value' => 'Zabbix Item history'
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-80 seconds')),
							'Name' => 'Master item',
							'Value' => '7.7778' // Value rounding is expected.
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-1 week')),
							'Name' => 'Host name',
							'Value' => STRING_255
						]
					],
					'result' => [
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('now')),
							'Name' => 'Host name',
							'Value' => 'Zabbix Item history'
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-80 seconds')),
							'Name' => 'Master item',
							'Value' => '7.7778' // Value rounding is expected.
						]
					],
					'item_data' => [
						['itemid' => '42227', 'values' => 'Zabbix Item history', 'time' => strtotime('now')],
						['itemid' => '99142', 'values' => '7.777777', 'time' => strtotime('-80 seconds')],
						['itemid' => '42227', 'values' => STRING_255, 'time' => strtotime('-1 week')],
						['itemid' => '42244', 'values' => '82.061797', 'time' => strtotime('+1 hours')]
					]
				]
			]
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
			'fields' => [
				'Layout' => 'Horizontal',
				'Show lines' => '25',
				'Advanced configuration' => true,
				'New values' => 'Top',
				'Show timestamp' => true,
				'Time period' => 'Custom',
				'From' => 'now-1y',
				'To' => 'now/d'
			],
			'items' => [
				'Host name' => ['id:display' => 'As is'],
				'Master item' => ['History data' => 'History']
			]
		];

		if (array_key_exists('fields', $data)) {
			$this->widgetConfigurationChange($data['fields'], $dashboard,
					CTestArrayHelper::get($data, 'edit_columns', [])
			);
			$this->assertTableData($data['result']);
			$this->widgetConfigurationChange($default_values['fields'], $dashboard, $default_values['items']);
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
	 * @param CDashboardElement $dashboard        dashboard element
	 * @param array             $configuration    widget parameter(s)
	 * @param array             $edit_columns     array of columns to be changed in test
	 */
	protected function widgetConfigurationChange($configuration, $dashboard, $edit_columns = []) {
		$form = $dashboard->getWidget(self::DATA_WIDGET)->edit();
		$form->fill($configuration);

		if ($edit_columns !== []) {
			foreach ($edit_columns as $name => $settings) {
				$form->getFieldContainer('Items')->asTable()->findRow('Name', $name)
						->query('button:Edit')->one()->click();
				$column_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$column_overlay->asForm()->fill($settings);
				$column_overlay->getFooter()->query('button:Update')->waitUntilClickable()->one()->click();
				$column_overlay->waitUntilNotVisible();
				$form->waitUntilReloaded();
			}
		}

		$form->submit();
		$dashboard->getWidget(self::DATA_WIDGET);
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

	/**
	 * Check Thresholds or Highlights field inputs and color-pickers.
	 *
	 * @param CFormElement $form        given form
	 * @param string       $label       checked field's label
	 * @param int          $i           input counter
	 * @param string       $selector    selector of tested input
	 */
	protected function checkThresholdsHighlights($form, $label, $i, $selector) {
		$container = $form->getFieldContainer($label);
		$container->query('button:Add')->one()->click();
		$input = $form->query('xpath:.//input[contains(@id, '.CXPathHelper::escapeQuotes($i.$selector).')]')->one();
		$this->assertTrue($input->isVisible());
		$this->assertEquals('E65660', $container->query('xpath:.//div[@class="color-picker"]')
				->asColorPicker()->one()->getValue()
		);
		$container->query('xpath:.//button[contains(@id, '.CXPathHelper::escapeQuotes($i.'_remove').')]')
				->one()->click();
		$this->assertFalse($input->isVisible());
	}
}

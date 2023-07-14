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
 * @backup dashboard
 *
 * @onBefore prepareData
 */
class testDashboardPlainTextWidget extends CWebTest {

	use TableTrait;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			'class' => CMessageBehavior::class
		];
	}

	protected static $dashboardid;
	protected static $dashboard_create;
	protected static $dashboard_data;
	protected static $default_widget = 'Default Plain text Widget';
	protected static $update_widget = 'Update Plain text Widget';
	protected static $delete_widget = 'Widget for delete';
	protected static $data_widget = 'Widget for data check';

	/**
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	protected $sql = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid,'.
			' wf.value_itemid, wf.value_graphid';

	public static function prepareData() {
		// Create host for widget header and data tests.
		CDataHelper::createHosts([
			[
				'host' => 'Simple host with item for plain text widget',
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
						'name' => 'Test plain text',
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
				'name' => 'Dashboard for Plain Text Widget test',
				'pages' => [
					[
						'name' => 'Page with default widgets',
						'widgets' => [
							[
								'type' => 'plaintext',
								'name' => self::$default_widget,
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemids',
										'value' => '42227'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'dynamic',
										'value' => '1'
									]
								]
							],
							[
								'type' => 'plaintext',
								'name' => self::$delete_widget,
								'x' => 0,
								'y' => 5,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemids',
										'value' => '42227'
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for Plain text Widget create/update test',
				'pages' => [
					[
						'name' => 'Page with created/updated widgets',
						'widgets' => [
							[
								'type' => 'plaintext',
								'name' => self::$update_widget,
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemids',
										'value' => '42243'
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
						'name' => 'Page with Plain text widget',
						'widgets' => [
							[
								'type' => 'plaintext',
								'name' => self::$data_widget,
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemids',
										'value' => '42227'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemids',
										'value' => '42243'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemids',
										'value' => '42244'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemids',
										'value' => $itemids['Test plain text']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'dynamic',
										'value' => '1'
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

	public function testDashboardPlainTextWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dialog = $dashboard->edit()->addWidget();
		$this->assertEquals('Add widget', $dialog->getTitle());
		$form = $dialog->asForm();

		if ($form->getField('Type')->getText() !== 'Plain text') {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Plain text')]);
		}

		// Check default state.
		$default_state = [
			'Type' => 'Plain text',
			'Name' => '',
			'Show header' => true,
			'Refresh interval' => 'Default (1 minute)',
			'Items' => '',
			'Items location' => 'Left',
			'Show lines' => '25',
			'Show text as HTML' => false,
			'Enable host selection' => false
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
			'id:itemids__ms' => [
				'placeholder' => 'type here to search'
			],
			'Show lines' => [
				'maxlength' => '3'
			]
		];
		foreach ($inputs as $field => $attributes) {
			$this->assertTrue($form->getField($field)->isAttributePresent($attributes));
		}

		// Check radio buttons.
		$this->assertEquals(['Left', 'Top'], $form->getField('Items location')->getLabels()->asText());

		$refresh_interval = ['Default (1 minute)', 'No refresh', '10 seconds', '30 seconds', '1 minute',
				'2 minutes', '10 minutes', '15 minutes'];
		$this->assertEquals($refresh_interval, $form->getField('Refresh interval')->getOptions()->asText());

		// Check if buttons present and clickable.
		$this->assertEquals(2, $dialog->query('button', ['Add', 'Cancel'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);
		$dialog->close();
		$dashboard->save();

		// Check parameter 'Enable host selection' true/false state.
		$host_selector = $dashboard->getControls()->query('class:multiselect-control')->asMultiselect()->one();
		$this->assertTrue($host_selector->isVisible());
		$this->assertEquals('No data found.', $dashboard->getWidget(self::$default_widget)
				->query('class:nothing-to-show')->one()->getText()
		);
		$dashboard->getWidget(self::$default_widget)->edit();
		$this->assertEquals('Edit widget', $dialog->getTitle());
		$form->fill(['Enable host selection' => false])->submit();
		$dashboard->save();
		$this->assertFalse($host_selector->isVisible());
	}

	public static function getWidgetData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Items' => ''
					],
					'error' => 'Invalid parameter "Items": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Items' => 'Linux: Available memory',
						'Show lines' => ''
					],
					'error' => 'Invalid parameter "Show lines": value must be one of 1-100.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Items' => 'Linux: Available memory',
						'Show lines' => '0'
					],
					'error' => 'Invalid parameter "Show lines": value must be one of 1-100.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Items' => 'Linux: Available memory',
						'Show lines' => '101'
					],
					'error' => 'Invalid parameter "Show lines": value must be one of 1-100.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Items' => 'Linux: Available memory',
						'Show lines' => ' '
					],
					'error' => 'Invalid parameter "Show lines": value must be one of 1-100.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Items' => '',
						'Show lines' => ''
					],
					'error' => [
						'Invalid parameter "Items": cannot be empty.',
						'Invalid parameter "Show lines": value must be one of 1-100.'
					]
				]
			],
			// Test case with items from the same host which name is used as widget header.
			[
				[
					'expected' => TEST_GOOD,
					'same_host' => true,
					'fields' => [
						'Name' => '',
						'Items' => [
							'Linux: Available memory',
							'Linux: Available memory in %'
						]
					]
				]
			],
			// Test case with one item which name is used as widget header.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '',
						'Items' => [
							'Linux: Available memory'
						]
					]
				]
			],
			// Test case with items from two different hosts.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '',
						'Refresh interval' => 'Default (1 minute)',
						'Items' => [
							'Linux: Available memory',
							'Test plain text'
							]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Header is hidden',
						'Show header' => false,
						'Refresh interval' => 'No refresh',
						'Items' => [
							'Linux: Available memory'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Header appears',
						'Show header' => true,
						'Refresh interval' => '10 seconds',
						'Items' => [
							'Linux: Available memory'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Items location' => 'Top',
						'Refresh interval' => '30 seconds',
						'Items' => [
							'Linux: Available memory'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Items location' => 'Left',
						'Refresh interval' => '1 minute',
						'Items' => [
							'Linux: Available memory'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show lines' => '1',
						'Refresh interval' => '2 minutes',
						'Items' => [
							'Linux: Available memory'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show lines' => '100',
						'Refresh interval' => '10 minutes',
						'Items' => [
							'Linux: Available memory'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show text as HTML' => true,
						'Refresh interval' => '15 minutes',
						'Items' => [
							'Linux: Available memory'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show text as HTML' => false,
						'Refresh interval' => '10 minutes',
						'Items' => [
							'Linux: Available memory'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable host selection' => true,
						'Refresh interval' => '2 minutes',
						'Items' => [
							'Linux: Available memory'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable host selection' => false,
						'Refresh interval' => '1 minute',
						'Items' => [
							'Linux: Available memory'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => ' Widget with trimmed trailing and leading spaces ',
						'Refresh interval' => '30 seconds',
						'Show lines' => ' 5 ',
						'Items' => [
							'Test plain text'
						]
					],
					'trim' => ['Name', 'Show lines']
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => true,
						'Name' => 'Widget with updated fields',
						'Refresh interval' => '1 minute',
						'Items location' => 'Top',
						'Show lines' => '50',
						'Show text as HTML' => true,
						'Enable host selection' => true,
						'Items' => [
							'Linux: Available memory'
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getWidgetData
	 */
	public function testDashboardPlainTextWidget_Create($data) {
		$this->checkWidgetForm($data);
	}

	public function testDashboardPlainTextWidget_SimpleUpdate() {
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
	public function testDashboardPlainTextWidget_Update($data) {
		$this->checkWidgetForm($data, true);
	}

	/**
	 * Perform Plain text widget creation or update and verify the result.
	 *
	 * @param boolean $update	updating is performed
	 */
	protected function checkWidgetForm($data, $update = false) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash($this->sql);
		}

		$data['fields']['Name'] = CTestArrayHelper::get($data, 'fields.Name', 'Plain text widget ' . microtime());
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_create)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = ($update)
			? $dashboard->getWidget(self::$update_widget)->edit()->asForm()
			: $dashboard->edit()->addWidget()->asForm();

		if ($form->getField('Type')->getText() !== 'Plain text') {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Plain text')]);
		}

		$form->fill($data['fields']);
		$values = $form->getValues();
		$form->submit();
		$this->page->waitUntilReady();

		// Trim leading and trailing spaces from expected results if necessary.
		if (array_key_exists('trim', $data)) {
			foreach ($data['trim'] as $field) {
				$data['fields'][$field] = trim($data['fields'][$field]);
			}
		}

		$items_count = (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) ? 0 : count($data['fields']['Items']);
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage($data['expected'], null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
			COverlayDialogElement::find()->one()->close();
			$dashboard->save();
			$this->page->waitUntilReady();
			$this->assertFalse($dashboard->getWidget($data['fields']['Name'], false)->isValid());
		}
		else {
			if ($items_count > 1) {
				$header = ((!array_key_exists('same_host', $data))
					? 'Plain text'
					: 'ЗАББИКС Сервер: '.$items_count.' items')
					?: $data['fields']['Name'];
			}
			else {
				// If name is empty string it is replaced by item name.
				$header = ($data['fields']['Name'] === '') ?
					'ЗАББИКС Сервер: '.implode($data['fields']['Items'])
					: $data['fields']['Name'];
			}

			if ($update) {
				self::$update_widget = $header;
			}

			COverlayDialogElement::ensureNotPresent();
			$widget = $dashboard->getWidget($header);

			// Save Dashboard to ensure that widget is correctly saved.
			$dashboard->save();
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check widgets count.
			$this->assertEquals($old_widget_count + ($update ? 0 : 1), $dashboard->getWidgets()->count());

			// Check new widget form fields and values in frontend.
			$saved_form = $widget->edit();
			$this->assertEquals($values, $saved_form->getValues());

			if (array_key_exists('Show header', $data['fields'])) {
				$saved_form->checkValue(['Show header' => $data['fields']['Show header']]);
			}

			$saved_form->submit();
			COverlayDialogElement::ensureNotPresent();
			$dashboard->save();
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check new widget update interval.
			$refresh = (CTestArrayHelper::get($data['fields'], 'Refresh interval') === 'Default (1 minute)')
				? '1 minute'
				: (CTestArrayHelper::get($data['fields'], 'Refresh interval', '1 minute'));
			$this->assertEquals($refresh, $widget->getRefreshInterval());
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
	public function testDashboardPlainTextWidget_Cancel($data) {
		$old_hash = CDBHelper::getHash($this->sql);
		$new_name = 'Widget to be cancelled';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one()->edit();
		$old_widget_count = $dashboard->getWidgets()->count();

		// Start updating or creating a widget.
		if (CTestArrayHelper::get($data, 'update', false)) {
			$form = $dashboard->getWidget(self::$default_widget)->edit();
		}
		else {
			$form = $dashboard->addWidget()->asForm();

			if ($form->getField('Type')->getText() !== 'Plain text') {
				$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Plain text')]);
			}
		}
		$form->fill([
			'Name' => $new_name,
			'Refresh interval' => '15 minutes',
			'Items' => 'Linux: Available memory'
		]);

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
				foreach ([self::$default_widget => true, $new_name => false] as $name => $valid) {
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

	public function testDashboardPlainTextWidget_Delete() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one()->edit();
		$widget = $dashboard->getWidget(self::$delete_widget);
		$dashboard->deleteWidget(self::$delete_widget);
		$widget->waitUntilNotPresent();
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Check that widget is not present on dashboard.
		$this->assertFalse($dashboard->getWidget(self::$delete_widget, false)->isValid());
		$widget_sql = 'SELECT NULL FROM widget_field wf LEFT JOIN widget w ON w.widgetid=wf.widgetid'.
				' WHERE w.name='.zbx_dbstr(self::$delete_widget);
		$this->assertEquals(0, CDBHelper::getCount($widget_sql));
	}

	public static function getTableData() {
		return [
			// Simple test case with one item and one data entry.
			[
				[
					'expected' => [
						[
							'Timestamp' => '2023-05-01 11:29:32',
							'Name' => 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running',
							'Value' => 'Zabbix plain text'
						]
					],
					'item_data' => [
						['itemid' => '42227', 'values' => 'Zabbix plain text', 'time' => '1682929772']
					]
				]
			],
			// Simple test case with one item and several data entries.
			[
				[
					'expected' => [
						[
							'Timestamp' => '2023-05-01 11:31:32',
							'Name' => 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running',
							'Value' => 'Zabbix plain ⓣⓔⓧⓣ'
						],
						[
							'Timestamp' => '2023-05-01 11:30:32',
							'Name' => 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running',
							'Value' => 'Zabbix plain text2'
						],
						[
							'Timestamp' => '2023-05-01 11:29:32',
							'Name' => 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running',
							'Value' => 'Zabbix plain text'
						]
					],
					'item_data' => [
						['itemid' => '42227', 'values' => 'Zabbix plain text', 'time' => '1682929772'],
						['itemid' => '42227', 'values' => 'Zabbix plain text2', 'time' => '1682929832'],
						['itemid' => '42227', 'values' => 'Zabbix plain ⓣⓔⓧⓣ', 'time' => '1682929892']
					]
				]
			],
			// Test case with two items and several data entries.
			[
				[
					'expected' => [
						[
							'Timestamp' => '1970-01-01 03:02:01',
							'Name' => 'ЗАББИКС Сервер: Linux: Available memory',
							'Value' => '9.37 GB' // value rounding is expected.
						],
						[
							'Timestamp' => '1970-01-01 03:01:01',
							'Name' => 'ЗАББИКС Сервер: Linux: Available memory',
							'Value' => '8.44 GB' // value rounding is expected.
						],
						[
							'Timestamp' => '1970-01-01 03:01:01',
							'Name' => 'ЗАББИКС Сервер: Linux: Available memory in %',
							'Value' => '82.0618 %' // value rounding is expected.
						],
						[
							'Timestamp' => '1970-01-01 03:00:01',
							'Name' => 'ЗАББИКС Сервер: Linux: Available memory',
							'Value' => '7.51 GB' // value rounding is expected.
						],
						[
							'Timestamp' => '1970-01-01 03:00:01',
							'Name' => 'ЗАББИКС Сервер: Linux: Available memory in %',
							'Value' => '72.0618 %' // value rounding is expected.
						]
					],
					'item_data' => [
						['itemid' => '42243', 'values' => '8061078528', 'time' => '1'],
						['itemid' => '42243', 'values' => '9061078528', 'time' => '61'],
						['itemid' => '42243', 'values' => '10061078528', 'time' => '121'],
						['itemid' => '42244', 'values' => '72.061797', 'time' => '1'],
						['itemid' => '42244', 'values' => '82.061797', 'time' => '61']
					]
				]
			],
			// Test case with limited lines to show.
			[
				[
					'expected' => [
						[
							'Timestamp' => '1970-01-01 03:02:01',
							'Name' => 'ЗАББИКС Сервер: Linux: Available memory',
							'Value' => '9.37 GB' // value rounding is expected.
						],
						[
							'Timestamp' => '1970-01-01 03:01:01',
							'Name' => 'ЗАББИКС Сервер: Linux: Available memory',
							'Value' => '8.44 GB' // value rounding is expected.
						]
					],
					'displayed_lines' => [
						[
							'Timestamp' => '1970-01-01 03:02:01',
							'Name' => 'ЗАББИКС Сервер: Linux: Available memory',
							'Value' => '9.37 GB' // value rounding is expected.
						]
					],
					'item_data' => [
						['itemid' => '42243', 'values' => '9061078528', 'time' => '61'],
						['itemid' => '42243', 'values' => '10061078528', 'time' => '121']
					]
				]
			],
			// Test case for 'Items location' and 'Show text as HTML' options check.
			[
				[
					'html_text' => true,
					'expected' => [
						[
							'Timestamp' => '2023-05-01 11:29:32',
							'Name' => 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running',
							'Value' => STRING_128
						],
						[
							'Timestamp' => '1970-01-01 03:00:01',
							'Name' => 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running',
							'Value' => STRING_255
						]
					],
					'expected_top' => [
						[
							'Timestamp' => '2023-05-01 11:29:32',
							'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running' => STRING_128
						],
						[
							'Timestamp' => '1970-01-01 03:00:01',
							'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running' => STRING_255
						]
					],
					'item_data' => [
						['itemid' => '42227', 'values' => STRING_128, 'time' => '1682929772'],
						['itemid' => '42227', 'values' => STRING_255, 'time' => '1']
					]
				]
			],
			// Test case for 'Enable host selection' check.
			[
				[
					'expected' => [
						[
							'Timestamp' => '2023-05-01 11:29:32',
							'Name' => 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running',
							'Value' => 'Zabbix plain text'
						],
						[
							'Timestamp' => '1970-01-01 03:00:01',
							'Name' => 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running',
							'Value' => STRING_255
						],
						[
							'Timestamp' => '1970-01-01 02:59:59',
							'Name' => 'ЗАББИКС Сервер: Linux: Available memory in %',
							'Value' => '82.0618 %' // value rounding is expected.
						]
					],
					'expected_host_data' => [
						[
							'Timestamp' => '2023-05-01 11:29:32',
							'Name' => 'Linux: Host name of Zabbix agent running',
							'Value' => 'Zabbix plain text'
						],
						[
							'Timestamp' => '1970-01-01 03:00:01',
							'Name' => 'Linux: Host name of Zabbix agent running',
							'Value' => STRING_255
						],
						[
							'Timestamp' => '1970-01-01 02:59:59',
							'Name' => 'Linux: Available memory in %',
							'Value' => '82.0618 %' // value rounding is expected.
						]
					],
					'item_data' => [
						['itemid' => '42244', 'values' => '82.061797', 'time' => '-1'],
						['itemid' => '42227', 'values' => 'Zabbix plain text', 'time' => '1682929772'],
						['itemid' => '42227', 'values' => STRING_255, 'time' => '1']
					]
				]
			]
		];

	}

	/**
	 * @dataProvider getTableData
	 */
	public function  testDashboardPlainTextWidget_TableData($data) {
		foreach ($data['item_data'] as $params) {
			CDataHelper::addItemData($params['itemid'], $params['values'], $params['time']);
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_data)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one()->waitUntilReady();
		$this->assertTableData($data['expected']);

		if (array_key_exists('displayed_lines', $data)) {
			$this->widgetConfigurationChange(['Show lines' => '1']);
			$this->assertTableData($data['displayed_lines']);
			$this->widgetConfigurationChange(['Show lines' => '25']);
		}

		if (array_key_exists('html_text', $data)) {
			$this->widgetConfigurationChange(['Show text as HTML' => true,'Items location' => 'Top']);
			$this->assertTableData($data['expected_top']);
			$this->widgetConfigurationChange(['Show text as HTML' => false,'Items location' => 'Left']);
		}

		if (array_key_exists('expected_host_data', $data)) {
			// Select host.
			$dashboard->getControls()->query('class:multiselect-control')->asMultiselect()->one()
					->fill('Simple host with item for plain text widget');
			$this->page->waitUntilReady();
			$this->assertTableData();
			$host = $dashboard->getControls()->query('class:multiselect-control')->asMultiselect()->one()
					->fill('ЗАББИКС Сервер');
			$this->page->waitUntilReady();
			$this->assertTableData($data['expected_host_data']);
			$host->clear();
			$this->page->waitUntilReady();
		}

		$this->assertTableData($data['expected']);

		foreach ($data['item_data'] as $params) {
			CDataHelper::removeItemData($params['itemid']);
		}
	}

	/**
	 * Change plain text widget configuration.
	 *
	 * @param array $configuration    widget parameter(s)
	 */
	protected function widgetConfigurationChange($configuration) {
		$dashboard = CDashboardElement::find()->one()->waitUntilReady();
		$form = $dashboard->getWidget(self::$data_widget)->edit();
		$form->fill($configuration);
		$form->submit();
		$dashboard->save();
	}
}

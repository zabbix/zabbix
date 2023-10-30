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
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';

/**
 * @backup dashboard
 *
 * @onBefore prepareData
 */
class testDashboardPlainTextWidget extends CWebTest {

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
	protected static $update_widget = 'Update Plain text Widget';
	const DEFAULT_WIDGET = 'Default Plain text Widget';
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
								'name' => self::DEFAULT_WIDGET,
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemids',
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
								'type' => 'plaintext',
								'name' => self::DELETE_WIDGET,
								'x' => 0,
								'y' => 5,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemids',
										'value' => '42227' // item name in widget 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running'.
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
										'value' => '42243' // item name in widget 'ЗАББИКС Сервер: Linux: Available memory'.
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
								'name' => self::DATA_WIDET,
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 6,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemids',
										'value' => '42227' // item name in widget 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running'.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemids',
										'value' => '42243' // item name in widget 'ЗАББИКС Сервер: Linux: Available memory'.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemids',
										'value' => '42244' // item name in widget 'ЗАББИКС Сервер: Linux: Available memory in %'.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemids',
										'value' => $itemids['Test plain text'] // item name in widget 'Simple host with item for plain text widget: Test plain text'.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemids',
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

	public function testDashboardPlainTextWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dialog = $dashboard->edit()->addWidget();
		$this->assertEquals('Add widget', $dialog->getTitle());
		$form = $dialog->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Plain text')]);

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
			'Override host' => ''
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
			[
				[
					'expected' => TEST_BAD,
					'error' => 'Invalid parameter "Items": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show lines' => ''
					],
					'items' => [
						['ЗАББИКС Сервер' => 'Linux: Available memory']
					],
					'error' => 'Invalid parameter "Show lines": value must be one of 1-100.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show lines' => '0'
					],
					'items' => [
						['ЗАББИКС Сервер' => 'Linux: Available memory']
					],
					'error' => 'Invalid parameter "Show lines": value must be one of 1-100.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show lines' => '101'
					],
					'items' => [
						['ЗАББИКС Сервер' => 'Linux: Available memory']
					],
					'error' => 'Invalid parameter "Show lines": value must be one of 1-100.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show lines' => ' '
					],
					'items' => [
						['ЗАББИКС Сервер' => 'Linux: Available memory']
					],
					'error' => 'Invalid parameter "Show lines": value must be one of 1-100.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
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
					'same_host' => 'ЗАББИКС Сервер',
					'fields' => [
						'Name' => ''
						],
					'items' => [
						['ЗАББИКС Сервер' => 'Linux: Available memory'],
						['ЗАББИКС Сервер' => 'Linux: Available memory in %']
					]
				]
			],
			// Test case with items from two different hosts.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => ''
					],
					'items' => [
						['ЗАББИКС Сервер' => 'Linux: Available memory in %'],
						['Simple host with item for plain text widget' => 'Test plain text']
					]
				]
			],
			// Test case with items from the same host and with custom name.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Test custom name'
					],
					'items' => [
						['ЗАББИКС Сервер' => 'Linux: Available memory'],
						['ЗАББИКС Сервер' => 'Linux: Available memory in %']
					]
				]
			],
			// Test case with items from two different hosts and with custom name.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Test custom name2'
					],
					'items' => [
						['ЗАББИКС Сервер' => 'Linux: Available memory'],
						['Simple host with item for plain text widget' => 'Test plain text']
					]
				]
			],
			// Test case with one item which name is used as widget header.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => ''
					],
					'items' => [
						['ЗАББИКС Сервер' => 'Linux: Available memory']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '',
						'Refresh interval' => 'Default (1 minute)'
					],
					'items' => [
						['Simple host with item for plain text widget' => 'Test plain text']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Header is hidden',
						'Show header' => false,
						'Refresh interval' => 'No refresh'
					],
					'items' => [
						['ЗАББИКС Сервер' => 'Linux: Available memory']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Header appears',
						'Show header' => true,
						'Refresh interval' => '10 seconds'
					],
					'items' => [
						['ЗАББИКС Сервер' => 'Linux: Available memory']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Items location' => 'Top',
						'Refresh interval' => '30 seconds'
					],
					'items' => [
						['ЗАББИКС Сервер' => 'Linux: Available memory']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Items location' => 'Left',
						'Refresh interval' => '1 minute'
					],
					'items' => [
						['ЗАББИКС Сервер' => 'Linux: Available memory']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show lines' => '1',
						'Refresh interval' => '2 minutes'
					],
					'items' => [
						['ЗАББИКС Сервер' => 'Linux: Available memory']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show lines' => '100',
						'Refresh interval' => '10 minutes'
					],
					'items' => [
						['ЗАББИКС Сервер' => 'Linux: Available memory']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show text as HTML' => true,
						'Refresh interval' => '15 minutes'
					],
					'items' => [
						['ЗАББИКС Сервер' => 'Linux: Available memory']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show text as HTML' => false,
						'Refresh interval' => '10 minutes'
					],
					'items' => [
						['ЗАББИКС Сервер' => 'Linux: Available memory']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Override host' => 'Dashboard',
						'Refresh interval' => '2 minutes'
					],
					'items' => [
						['ЗАББИКС Сервер' => 'Linux: Available memory']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Override host' => 'Dashboard',
						'Refresh interval' => '1 minute'
					],
					'items' => [
						['ЗАББИКС Сервер' => 'Linux: Available memory']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => ' Widget with trimmed trailing and leading spaces ',
						'Refresh interval' => '30 seconds',
						'Show lines' => ' 5 '
					],
					'items' => [
						['Simple host with item for plain text widget' => 'Test plain text']
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
						'Override host' => 'Dashboard'
					],
					'items' => [
						['ЗАББИКС Сервер' => 'Linux: Available memory']
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

		$data['fields']['Name'] = CTestArrayHelper::get($data, 'fields.Name', 'Plain text widget '.microtime());
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_create)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = ($update)
			? $dashboard->getWidget(self::$update_widget)->edit()->asForm()
			: $dashboard->edit()->addWidget()->asForm();

		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Plain text')]);

		// Prepare the data for filling in "Items" field of widget, get item names.
		if (array_key_exists('items', $data)) {
			foreach ($data['items'] as $array) {
				$data['fields']['Items'][] = implode(array_values($array));
			}
		}
		else {
			$data['fields']['Items'] = '';
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

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage($data['expected'], null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
			COverlayDialogElement::find()->one()->close();
			$dashboard->save();
			$this->page->waitUntilReady();
			$this->assertFalse($dashboard->getWidget($data['fields']['Name'], false)->isValid());
		}
		else {
			$items_count = count($data['items']);
			if ($data['fields']['Name'] === '') {
				if ($items_count > 1) {
					$header = (array_key_exists('same_host', $data))
						? $data['same_host'].': '.$items_count.' items'
						: 'Plain text';
				}
				else {
					// If name is empty string it is replaced by item name.
					$header = implode(array_keys($data['items'][0])).': '.implode($data['fields']['Items']);
				}
			}
			else {
				$header = $data['fields']['Name'];
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

			// Prepare data to check widget "Items" field, should be in the format "Host name: Item name".
			$data['fields']['Items'] = [];
			foreach ($data['items'] as $host_item) {
				foreach ($host_item as $host => $item) {
					$data['fields']['Items'][] = $host.': '. $item;
				}
			}

			$saved_form->checkValue($data['fields']);
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
			$form = $dashboard->getWidget(self::DEFAULT_WIDGET)->edit();
		}
		else {
			$form = $dashboard->addWidget()->asForm();
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Plain text')]);
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

	public function testDashboardPlainTextWidget_Delete() {
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
			// Simple test case with one item and one data entry.
			[
				[
					'initial_data' => [
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('now')),
							'Name' => 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running',
							'Value' => 'Zabbix plain text'
						]
					],
					'item_data' => [
						['itemid' => '42227', 'values' => 'Zabbix plain text', 'time' => strtotime('Now')]
					]
				]
			],
			// Simple test case with one item and several data entries.
			[
				[
					'initial_data' => [
						[
							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('now')),
							'Name' => 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running',
							'Value' => 'Zabbix plain text'
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-1 minute')),
							'Name' => 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running',
							'Value' => 'Zabbix plain text2'
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-2 minutes')),
							'Name' => 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running',
							'Value' => 'Zabbix plain ⓣⓔⓧⓣ'
						]
					],
					'item_data' => [
						['itemid' => '42227', 'values' => 'Zabbix plain text', 'time' => strtotime('now')],
						['itemid' => '42227', 'values' => 'Zabbix plain text2', 'time' => strtotime('-1 minute')],
						['itemid' => '42227', 'values' => 'Zabbix plain ⓣⓔⓧⓣ', 'time' => strtotime('-2 minutes')]
					]
				]
			],
			// Test case with two items and several data entries.
			[
				[
					'initial_data' => [
						[
							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('now')),
							'Name' => 'ЗАББИКС Сервер: Linux: Available memory',
							'Value' => '9.37 GB' // value rounding is expected.
						],
						[
							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('-30 seconds')),
							'Name' => 'ЗАББИКС Сервер: Linux: Available memory in %',
							'Value' => '82.0618 %' // value rounding is expected.
						],
						[
							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('-1 minute')),
							'Name' => 'ЗАББИКС Сервер: Linux: Available memory in %',
							'Value' => '72.0618 %' // value rounding is expected.
						],
						[
							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('-1 hour')),
							'Name' => 'ЗАББИКС Сервер: Linux: Available memory',
							'Value' => '8.44 GB' // value rounding is expected.
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours')),
							'Name' => 'ЗАББИКС Сервер: Linux: Available memory',
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
			// Test case with limited lines to show.
			[
				[
					'fields' => [
						'Show lines' => '1'
						],
					'initial_data' => [
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('today + 9 hours')),
							'Name' => 'ЗАББИКС Сервер: Linux: Available memory',
							'Value' => '9.37 GB' // value rounding is expected.
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('yesterday')),
							'Name' => 'ЗАББИКС Сервер: Linux: Available memory',
							'Value' => '8.44 GB' // value rounding is expected.
						]
					],
					'result' => [
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('today + 9 hours')),
							'Name' => 'ЗАББИКС Сервер: Linux: Available memory',
							'Value' => '9.37 GB' // value rounding is expected.
						]
					],
					'item_data' => [
						['itemid' => '42243', 'values' => '10061078528', 'time' => strtotime('today + 9 hours')],
						['itemid' => '42243', 'values' => '9061078528', 'time' => strtotime('yesterday')]
					]
				]
			],
			// Test case for 'Items location' and 'Show text as HTML' options check.
			[
				[
					'fields' => [
						'Show text as HTML' => true,
						'Items location' => 'Top'
					],
					'initial_data' => [
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('now')),
							'Name' => 'Test item host: Master item',
							'Value' => '1' // value rounding is expected.
						],
						[
							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('-15 hours')),
							'Name' => 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running',
							'Value' => '<b>'.STRING_128.'</b>'
						],
						[
							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('-16 hours')),
							'Name' => 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running',
							'Value' => '<span style="text-transform:uppercase;">'.'test'.'</span>'
						],
						[
							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('-25 hours')),
							'Name' => 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running',
							'Value' => STRING_255
						]
					],
					'result' => [
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('now')),
							'Test item host: Master item' => '1'
						],
						[
							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('-15 hours')),
							'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running' => STRING_128
						],
						[
							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('-16 hours')),
							'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running' => 'TEST'
						],
						[
							'Timestamp' =>  date('Y-m-d H:i:s', strtotime('-25 hours')),
							'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running' => STRING_255
						]
					],
					'item_data' => [
						['itemid' => '99142', 'values' => '1.00001', 'time' => strtotime('now')],
						['itemid' => '42227', 'values' => '<b>'.STRING_128.'</b>', 'time' => strtotime('-15 hours')],
						['itemid' => '42227', 'values' => '<span style="text-transform:uppercase;">'.'test'.'</span>',
								'time' => strtotime('-16 hours')],
						['itemid' => '42227', 'values' => STRING_255, 'time' => strtotime('-25 hours')]
					]
				]
			],
			// Test case for host selection check.
			[
				[
					'host_select' => [
						'without_data' => 'Simple host with item for plain text widget',
						'with_data' =>'ЗАББИКС Сервер'
						],
					'initial_data' => [
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('now')),
							'Name' => 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running',
							'Value' => 'Zabbix plain text'
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-80 seconds')),
							'Name' => 'Test item host: Master item',
							'Value' => '7.7778' // value rounding is expected.
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-1 week')),
							'Name' => 'ЗАББИКС Сервер: Linux: Host name of Zabbix agent running',
							'Value' => STRING_255
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-1 month')),
							'Name' => 'ЗАББИКС Сервер: Linux: Available memory in %',
							'Value' => '82.0618 %' // value rounding is expected.
						]
					],
					'result' => [
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('now')),
							'Name' => 'Linux: Host name of Zabbix agent running',
							'Value' => 'Zabbix plain text'
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-1 week')),
							'Name' => 'Linux: Host name of Zabbix agent running',
							'Value' => STRING_255
						],
						[
							'Timestamp' => date('Y-m-d H:i:s', strtotime('-1 month')),
							'Name' => 'Linux: Available memory in %',
							'Value' => '82.0618 %' // value rounding is expected.
						]
					],
					'item_data' => [
						['itemid' => '42227', 'values' => 'Zabbix plain text', 'time' => strtotime('now')],
						['itemid' => '99142', 'values' => '7.777777', 'time' => strtotime('-80 seconds')],
						['itemid' => '42227', 'values' => STRING_255, 'time' => strtotime('-1 week')],
						['itemid' => '42244', 'values' => '82.061797', 'time' => strtotime('-1 month')]
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
	public function  testDashboardPlainTextWidget_TableData($data) {
		foreach ($data['item_data'] as $params) {
			CDataHelper::addItemData($params['itemid'], $params['values'], $params['time']);
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_data)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dashboard->waitUntilReady();
		$this->assertTableData($data['initial_data']);

		$default_values = [
			'Show lines' => '25',
			'Show text as HTML' => false,
			'Items location' => 'Left'
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
	 * Change plain text widget configuration.
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
}

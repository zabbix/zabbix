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
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup dashboard
 *
 * @onBefore prepareDashboardData
 */
class testDashboardItemValueWidget extends CWebTest {

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
	protected static $old_name = 'New widget';
	private $sql = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid,'.
			' wf.value_itemid, wf.value_graphid';

	public static function prepareDashboardData() {
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for Single Item Widget test',
				'private' => 0,
				'pages' => [
					[
						'name' => 'Page with widgets',
						'widgets' => [
							[
								'type' => 'item',
								'name' => self::$old_name,
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 4,
								'fields' => [
									[
										'type' => 0,
										'name' => 'itemid',
										'value' => 29177
									],
									[
										'type' => 0,
										'name' => 'adv_conf',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'description',
										'value' => 'Some description here. Описание.'
									],
									[
										'type' => 0,
										'name' => 'desc_h_pos',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'desc_v_pos',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'value_h_pos',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'value_v_pos',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'time_h_pos',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'time_v_pos',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'desc_size',
										'value' => 17
									],
									[
										'type' => 0,
										'name' => 'decimal_size',
										'value' => 41
									],
									[
										'type' => 0,
										'name' => 'value_size',
										'value' => 56
									],
									[
										'type' => 0,
										'name' => 'time_size',
										'value' => 14
									]
								]
							],
							[
								'type' => 'item',
								'name' => 'Widget to delete',
								'x' => 13,
								'y' => 0,
								'width' => 4,
								'height' => 4,
								'fields' => [
									[
										'type' => 0,
										'name' => 'itemid',
										'value' => 29177
									]
								]
							]
						]
					]
				]
			]
		]);
		self::$dashboardid = $response['dashboardids'][0];
	}

	public static function getWidgetData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item value',
						'Refresh interval' => '30 seconds',
						'Item' => [
							'values' => '',
							'context' => [
								'values' => '',
								'context' => ''
							]
						]
					],
					'error' => ['Invalid parameter "Item": cannot be empty.']
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => [
							'values' => 'Available memory in %',
						],
						'Advanced configuration' => true,
						// Size of description.
						'id:desc_size' => '0',
						// Size of decimal part.
						'id:decimal_size' => '0',
						'id:value_size' => '0',
						'id:units_size' => '0',
						'id:time_size' => '0',
					],
					'error' => [
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => [
							'values' => 'Available memory in %',
						],
						'Advanced configuration' => true,
						// Size of description.
						'id:desc_size' => '101',
						// Size of decimal part.
						'id:decimal_size' => '102',
						'id:value_size' => '103',
						'id:units_size' => '104',
						'id:time_size' => '105',
					],
					'error' => [
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => [
							'values' => 'Available memory in %',
						],
						'Advanced configuration' => true,
						// Size of description.
						'id:desc_size' => '-1',
						// Size of decimal part.
						'id:decimal_size' => '-2',
						'id:value_size' => '-3',
						'id:units_size' => '-4',
						'id:time_size' => '-5',
					],
					'error' => [
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => [
							'values' => 'Available memory in %',
						],
						'Advanced configuration' => true,
						// Size of description.
						'id:desc_size' => 'aqua',
						// Size of decimal part.
						'id:decimal_size' => 'один',
						'id:value_size' => 'some',
						'id:units_size' => '@6$',
						'id:time_size' => '_+(*',
					],
					'error' => [
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => [
							'values' => 'Available memory in %',
						],
						'Advanced configuration' => true,
						'id:decimal_places' => '-1',
					],
					'error' => [
						'Invalid parameter "Decimal places": value must be one of 0-10.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => [
							'values' => 'Available memory in %',
						],
						'Advanced configuration' => true,
						'id:decimal_places' => '99',
					],
					'error' => [
						'Invalid parameter "Decimal places": value must be one of 0-10.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => [
							'values' => 'Available memory in %',
						],
						'Advanced configuration' => true,
						'id:description' => '',
					],
					'error' => [
						'Invalid parameter "Description": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Item value',
						'Name' => 'Any name',
						'Refresh interval' => 'No refresh',
						'Item' => [
							'values' => 'Available memory in %',
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Item value',
						'Name' => 'Имя виджета',
						'Refresh interval' => '10 seconds',
						'Item' => [
							'values' => 'Master item',
							'context' => [
								'values' => 'Test item host',
								'context' => 'Zabbix servers'
							]
						],
						// Description checkbox.
						'id:show_1' => true,
						// Value checkbox.
						'id:show_2' => false,
						// Time checkbox.
						'id:show_3' => true,
						// Change indicator checkbox.
						'id:show_4' => false,
						'Advanced configuration' => true,
						// Description configuration.
						'id:description' => 'Несколько слов. Dāži vārdi.',
						'id:desc_h_pos' => 'Right',
						'id:desc_v_pos' => 'Bottom',
						'id:desc_size' => '1',
						// Time configuration.
						'id:time_h_pos' => 'Right',
						'id:time_v_pos' => 'Middle',
						'id:time_size' => '21',
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Item value',
						'id:show_header' => false,
						'Name' => '#$%^&*()!@{}[]<>,.|',
						'Refresh interval' => '10 minutes',
						'Item' => [
							'values' => 'Response code for step "testFormWeb1" of scenario "testFormWeb1".',
						],
						// Description checkbox.
						'id:show_1' => false,
						// Value checkbox.
						'id:show_2' => true,
						// Time checkbox.
						'id:show_3' => false,
						// Change indicator checkbox.
						'id:show_4' => true,
						'Advanced configuration' => true,
						// Value units configuration.
						'id:units' => 'Some Units',
						'id:units_pos' => 'Below value',
						'id:units_size' => '100',
						'id:units_bold' => true
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Item value',
						'Name' => 'New Single Item Widget',
						'Refresh interval' => '2 minutes',
						'Item' => [
							'values' => 'Http agent item form',
						],
						// Description checkbox.
						'id:show_1' => true,
						// Value checkbox.
						'id:show_2' => true,
						// Time checkbox.
						'id:show_3' => true,
						// Change indicator checkbox.
						'id:show_4' => true,
						'Advanced configuration' => true,
						// Description configuration.
						'id:description' => 'Some description here.',
						'id:desc_h_pos' => 'Left',
						'id:desc_v_pos' => 'Top',
						'id:desc_size' => '11',
						'id:desc_bold' => true,
						// Value configuration.
						'id:decimal_places' => '3',
						'id:value_h_pos' => 'Right',
						'id:value_v_pos' => 'Bottom',
						'id:decimal_size' => '32',
						'id:value_size' => '46',
						'id:value_bold' => true,
						// Value units configuration.
						'id:units' => 's',
						'id:units_pos' => 'Before value',
						'id:units_size' => '36',
						'id:units_bold' => true,
						// Time configuration.
						'id:time_h_pos' => 'Left',
						'id:time_v_pos' => 'Bottom',
						'id:time_size' => '13',
						'id:time_bold' => true,
						// Dynamic item checkbox.
						'Dynamic item' => true
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Item value',
						'id:show_header' => false,
						'Name' => 'Color pick',
						'Refresh interval' => '10 minutes',
						'Item' => [
							'values' => 'Response code for step "testFormWeb1" of scenario "testFormWeb1".',
						],
						// Description checkbox.
						'id:show_1' => true,
						// Value checkbox.
						'id:show_2' => true,
						// Time checkbox.
						'id:show_3' => true,
						// Change indicator checkbox.
						'id:show_4' => true,
						'Advanced configuration' => true,
						// Value units configuration.
						'id:units' => 'B',
						'id:units_pos' => 'Below value',
						'id:units_size' => '99',
						'id:units_bold' => true
					],
					'colors' => [
						'id:lbl_desc_color' => 'AABBCC',
						'id:lbl_value_color' => 'CC11CC',
						'id:lbl_units_color' => 'BBCC55',
						'id:lbl_time_color' => '11AA00',
						'id:lbl_up_color' => '00FF00',
						'id:lbl_down_color' => 'FF0000',
						'id:lbl_updown_color' => '0000FF',
						// Background color.
						'id:lbl_bg_color' => 'FFAAAA'
					]
				]
			]
		];
	}

	/**
	 * Test to check Item Value Widget.
	 *
	 * @dataProvider getWidgetData
	 */
	public function testDashboardItemValueWidget_Create($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();
		$old_hash = CDBHelper::getHash('SELECT * FROM widget ORDER BY widgetid');
		// Add a widget.
		$dialogue = $dashboard->edit()->addWidget();
		$form = $dialogue->asForm();
		$form->fill($data['fields']);

		if (array_key_exists('colors', $data)) {
			foreach ($data['colors'] as $fieldid => $color) {
				$form->query($fieldid)->one()->click()->waitUntilReady();
				$this->query('xpath://div[@class="overlay-dialogue"]')->asColorPicker()->one()->fill($color);
			}
		}

		COverlayDialogElement::find()->waitUntilReady()->one();
		$form->submit();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage($data['expected'], null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM widget ORDER BY widgetid'));
		}
		else {
			// Make sure that the widget is present before saving the dashboard.
			$header = CTestArrayHelper::get($data['fields'], 'Name', 'Item');
			$dashboard->getWidget($header);
			$dashboard->save();
			// Check that Dashboard has been saved and that widget has been added.
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
			$this->assertEquals($old_widget_count + 1, $dashboard->getWidgets()->count());
			// Check that widget created in DB.
			$this->assertEquals(1, CDBHelper::getCount('SELECT null from widget WHERE name = '."'".$data['fields']['Name']."'"));
			// Check that widget has been added.
			$this->checkRefreshInterval($data, $header);
		}
	}

	public function testDashboardItemValueWidget_CancelCreate() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();
		$old_hash = CDBHelper::getHash($this->sql);
		$data = [
			'fields' => [
				'Type' => 'Item value',
				'Name' => 'Widget to cancel',
						'Item' => [
							'values' => 'Available memory in %',
						]
			]
		];
		$dialogue = $dashboard->edit()->addWidget();
		$form = $dialogue->asForm();
		$form->fill($data['fields']);
		$dialogue->query('button', 'Cancel')->one()->click();
		$dashboard->save();
		$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	/**
	 * @dataProvider getWidgetData
	 */
	public function testDashboardItemValueWidget_Update($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$old_widget_count = $dashboard->getWidgets()->count();
		$old_hash = CDBHelper::getHash('SELECT * FROM widget ORDER BY widgetid');
		$header = self::$old_name;
		$widget = $dashboard->getWidget($header);
		$form = $widget->edit()->asForm();
		$original_values = $form->getFields()->asValues();
		$form->fill($data['fields']);

		if (array_key_exists('colors', $data)) {
			foreach ($data['colors'] as $fieldid => $color) {
				$form->query($fieldid)->one()->click()->waitUntilReady();
				$this->query('xpath://div[@class="overlay-dialogue"]')->asColorPicker()->one()->fill($color);
			}
		}

		COverlayDialogElement::find()->waitUntilReady()->one();
		$form->submit();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage($data['expected'], null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM widget ORDER BY widgetid'));
		}
		else {
			// Make sure that the widget is present before saving the dashboard.
			$new_header = CTestArrayHelper::get($data['fields'], 'Name', 'Item');
			$dashboard->getWidget($new_header);
			$dashboard->save();
			// Check that Dashboard has been saved and that widget is in place.
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
			$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());
			// Check that widget updated in DB.
			$this->assertEquals(2, CDBHelper::getCount('SELECT null from widget WHERE name = '."'".$data['fields']['Name']."'"));
			// Check that widget is.
			$this->checkRefreshInterval($data, $new_header);
			self::$old_name = $new_header;
		}
	}

	public function testDashboardItemValueWidget_SimpleUpdate() {
		$this->checkUpdateUnchanged();
	}

	public function testDashboardItemValueWidget_CancelUpdate() {
		$this->checkUpdateUnchanged(true);
	}

	/**
	 * Check authentication form fields layout.
	 */
	public function testDashboardItemValueWidget_FormLayout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$header = self::$old_name;
		$widget = $dashboard->getWidget($header);
		$form = $widget->edit()->asForm();
		$form->query('id:adv_conf')->asCheckbox()->one()->check();
		// Summon the hint-box.
		$form->query('xpath://label[@for="description"]//span')->one()->click();
		$hint = $form->query('xpath://div[@class="wrapper"]/div[@class="overlay-dialogue"]')->waitUntilPresent();
		// Assert text.
		$this->assertEquals("Supported macros:".
			"\n{HOST.*}".
			"\n{ITEM.*}".
			"\n{INVENTORY.*}".
			"\nUser macros", $hint->one()->getText());
		// Close the hint-box.
		$hint->one()->query('xpath:.//button[@class="overlay-close-btn"]')->one()->click();
		$hint->waitUntilNotPresent();

		// Check checkboxes dependencies.
		// Description checkbox.
		$form->query('id:show_1')->asCheckbox()->one()->uncheck();
		$this->assertFalse($form->query('xpath://textarea[@id="description"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="desc_h_pos_0"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="desc_h_pos_1"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="desc_h_pos_2"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="desc_v_pos_0"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="desc_v_pos_1"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="desc_v_pos_2"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="desc_size"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="desc_bold"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="desc_color"]')->one()->isEnabled());
		$form->query('id:show_1')->asCheckbox()->one()->check();
		// Value checkbox.
		$form->query('id:show_2')->asCheckbox()->one()->uncheck();
		$this->assertFalse($form->query('xpath://input[@id="decimal_places"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="value_h_pos_0"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="value_h_pos_1"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="value_h_pos_2"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="value_v_pos_0"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="value_v_pos_1"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="value_v_pos_2"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="decimal_size"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="value_size"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="value_bold"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="value_color"]')->one()->isEnabled());
		$form->query('id:show_2')->asCheckbox()->one()->check();
		// Units checkbox.
		$this->assertTrue($form->query('xpath://input[@id="units"]')->one()->isEnabled());
		$this->assertTrue($form->query('xpath://button[@id="label-units_pos"]')->one()->isEnabled());
		$this->assertTrue($form->query('xpath://input[@id="units_size"]')->one()->isEnabled());
		$this->assertTrue($form->query('xpath://input[@id="units_bold"]')->one()->isEnabled());
		$this->assertTrue($form->query('xpath://input[@id="units_color"]')->one()->isEnabled());
		$form->query('id:units_show')->asCheckbox()->one()->uncheck();
		$this->assertFalse($form->query('xpath://input[@id="units"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://button[@id="label-units_pos"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="units_size"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="units_bold"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="units_color"]')->one()->isEnabled());
		$form->query('id:units_show')->asCheckbox()->one()->check();
		// Time checkbox.
		$form->query('id:show_3')->asCheckbox()->one()->uncheck();
		$this->assertFalse($form->query('xpath://input[@id="time_h_pos_0"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="time_h_pos_1"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="time_h_pos_2"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="time_v_pos_0"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="time_v_pos_1"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="time_v_pos_2"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="time_size"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="time_bold"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="time_color"]')->one()->isEnabled());
		$form->query('id:show_3')->asCheckbox()->one()->check();
		// Change indicator checkbox.
		$form->query('id:show_4')->asCheckbox()->one()->uncheck();
		$this->assertFalse($form->query('xpath://input[@id="up_color"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="down_color"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="updown_color"]')->one()->isEnabled());
		$form->query('id:show_4')->asCheckbox()->one()->check();
		// Advanced configuration checkbox.
		$form->query('id:adv_conf')->asCheckbox()->one()->uncheck();
		$this->assertFalse($form->query('xpath://input[@id="bg_color"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://textarea[@id="description"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="decimal_places"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="units"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="time_size"]')->one()->isEnabled());
		$this->assertFalse($form->query('xpath://input[@id="updown_color"]')->one()->isEnabled());
	}

	public function testDashboardItemValueWidget_Delete() {
		$name = 'Widget to delete';
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one()->edit();
		$old_widget_count = $dashboard->getWidgets()->count();
		$widget = $dashboard->getWidget($name);
		$this->assertEquals(true, $widget->isEditable());
		$dashboard->deleteWidget($name);
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertEquals($old_widget_count - 1, $dashboard->getWidgets()->count());
		$this->assertEquals('', CDBHelper::getRow("SELECT * from widget WHERE name = 'Widget to delete'"));
	}

	/**
	 * Function for checking widget refresh interval.
	 *
	 * @param array $data	data provider
	 * @param string $header	name of existing widget
	 */
	private function checkRefreshInterval($data, $header) {
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget($header);
		$refresh = (CTestArrayHelper::get($data['fields'], 'Refresh interval') === 'Default (1 minute)')
			? '15 minutes'
			: (CTestArrayHelper::get($data['fields'], 'Refresh interval', '1 minute'));
		$this->assertEquals($refresh, $widget->getRefreshInterval());
	}

	/**
	 * Function for checking editing widget form without changes.
	 *
	 * @param boolean $cancel	is updating canceled
	 */
	private function checkUpdateUnchanged($cancel = false) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$old_widget_count = $dashboard->getWidgets()->count();
		$old_hash = CDBHelper::getHash($this->sql);
		$header = self::$old_name;
		$widget = $dashboard->getWidget($header);
		$form = $widget->edit()->asForm();
		$original_values = $form->getFields()->asValues();

		if ($cancel) {
			$data = [
				'fields' => [
					'Type' => 'Item value',
					'Name' => 'Widget to cancel',
							'Item' => [
								'values' => 'Available memory in %',
							]
				]
			];
			$form->fill($data['fields']);
			COverlayDialogElement::find()->waitUntilReady()->one()->query('button', 'Cancel')->one()->click();
		}
		else {
			$data = [
			'fields' => [
				'Type' => 'Item value',
			]
		];
		$form->fill($data['fields']);
		COverlayDialogElement::find()->waitUntilReady()->one();
		$form->submit();
		}

		$this->page->waitUntilReady();
		$new_values = $widget->edit()->getFields()->asValues();
		$this->assertEquals($original_values, $new_values);
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}
}

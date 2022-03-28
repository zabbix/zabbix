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
										'value' => 42230
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
										'value' => 42230
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
						'Item' => 'Available memory in %',
						'Advanced configuration' => true,
						// Description size in % relative to the size of the widget.
						'id:desc_size' => '0',
						// Value decimal part's size relative in %.
						'id:decimal_size' => '0',
						// Value size in % relative to the size of the widget.
						'id:value_size' => '0',
						// Value units size in % relative to the size of the widget.
						'id:units_size' => '0',
						// Time size in % relative to the size of the widget.
						'id:time_size' => '0'
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
						'Item' => 'Available memory in %',
						'Advanced configuration' => true,
						// Description size in % relative to the size of the widget.
						'id:desc_size' => '101',
						// Value decimal part's size relative in %.
						'id:decimal_size' => '102',
						// Value size in % relative to the size of the widget.
						'id:value_size' => '103',
						// Value units size in % relative to the size of the widget.
						'id:units_size' => '104',
						// Time size in % relative to the size of the widget.
						'id:time_size' => '105'
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
						'Item' => 'Available memory in %',
						'Advanced configuration' => true,
						// Description size in % relative to the size of the widget.
						'id:desc_size' => '-1',
						// Value decimal part's size relative in %.
						'id:decimal_size' => '-2',
						// Value size in % relative to the size of the widget.
						'id:value_size' => '-3',
						// Value units size in % relative to the size of the widget.
						'id:units_size' => '-4',
						// Time size in % relative to the size of the widget.
						'id:time_size' => '-5'
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
						'Item' => 'Available memory in %',
						'Advanced configuration' => true,
						// Description size in % relative to the size of the widget.
						'id:desc_size' => 'aqua',
						// Value decimal part's size relative in %.
						'id:decimal_size' => 'один',
						// Value size in % relative to the size of the widget.
						'id:value_size' => 'some',
						// Value units size in % relative to the size of the widget.
						'id:units_size' => '@6$',
						// Time size in % relative to the size of the widget.
						'id:time_size' => '_+(*'
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
						'Item' => 'Available memory in %',
						'Advanced configuration' => true,
						'id:decimal_places' => '-1'
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
						'Item' => 'Available memory in %',
						'Advanced configuration' => true,
						'id:decimal_places' => '99'
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
						'Item' => 'Available memory in %',
						'Advanced configuration' => true,
						'id:description' => ''
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
						'Item' => 'Available memory in %'
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
						// Description field.
						'id:description' => 'Несколько слов. Dāži vārdi.',
						// Description horizontal position.
						'id:desc_h_pos' => 'Right',
						// Description vertical position.
						'id:desc_v_pos' => 'Bottom',
						// Description size in % relative to the size of the widget.
						'id:desc_size' => '1',
						// Time horizontal position.
						'id:time_h_pos' => 'Right',
						// Time vertical position.
						'id:time_v_pos' => 'Middle',
						// Time size in % relative to the size of the widget.
						'id:time_size' => '21'
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
						'Item' => 'Response code for step "testFormWeb1" of scenario "testFormWeb1".',
						// Description checkbox.
						'id:show_1' => false,
						// Value checkbox.
						'id:show_2' => true,
						// Time checkbox.
						'id:show_3' => false,
						// Change indicator checkbox.
						'id:show_4' => true,
						'Advanced configuration' => true,
						// Value units type.
						'id:units' => 'Some Units',
						// Value units position.
						'id:units_pos' => 'Below value',
						// Value units size in % relative to the size of the widget.
						'id:units_size' => '100',
						// Value units bold font checkbox.
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
						'Item' => 'Http agent item form',
						// Description checkbox.
						'id:show_1' => true,
						// Value checkbox.
						'id:show_2' => true,
						// Time checkbox.
						'id:show_3' => true,
						// Change indicator checkbox.
						'id:show_4' => true,
						'Advanced configuration' => true,
						// Description field.
						'id:description' => 'Some description here.',
						// Description horizontal position.
						'id:desc_h_pos' => 'Left',
						// Description vertical position.
						'id:desc_v_pos' => 'Top',
						// Description size in % relative to the size of the widget.
						'id:desc_size' => '11',
						// Description bold font checkbox.
						'id:desc_bold' => true,
						// Value decimal places count.
						'id:decimal_places' => '3',
						// Value horizontal position.
						'id:value_h_pos' => 'Right',
						// Value vertical position.
						'id:value_v_pos' => 'Bottom',
						// Value decimal part's size relative in %.
						'id:decimal_size' => '32',
						// Value size in % relative to the size of the widget.
						'id:value_size' => '46',
						// Value font bold checkbox.
						'id:value_bold' => true,
						// Value units type.
						'id:units' => 's',
						// Value units position.
						'id:units_pos' => 'Before value',
						// Value units size in % relative to the size of the widget.
						'id:units_size' => '36',
						// Value units bold font checkbox.
						'id:units_bold' => true,
						// Time horizontal position.
						'id:time_h_pos' => 'Left',
						// Time vertical position.
						'id:time_v_pos' => 'Bottom',
						// Time size in % relative to the size of the widget.
						'id:time_size' => '13',
						// Time bold font checkbox.
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
						'Item' => 'Response code for step "testFormWeb1" of scenario "testFormWeb1".',
						// Description checkbox.
						'id:show_1' => true,
						// Value checkbox.
						'id:show_2' => true,
						// Time checkbox.
						'id:show_3' => true,
						// Change indicator checkbox.
						'id:show_4' => true,
						'Advanced configuration' => true,
						// Value units type.
						'id:units' => 'B',
						// Value units position.
						'id:units_pos' => 'Below value',
						// Value units size in % relative to the size of the widget.
						'id:units_size' => '99',
						// Value font bold checkbox.
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
	 * Check authentication form fields layout.
	 */
	public function testDashboardItemValueWidget_FormLayout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$dialogue = $dashboard->edit()->addWidget();
		$form = $dialogue->asForm();
		$form->fill(['Type' => 'Item value']);

		// Check checkboxes dependencies.
		// Advanced configuration checkbox.
		foreach ([false, true] as $show_description) {
			$form->fill(['Advanced configuration' => $show_description]);
			$this->assertTrue($form->getField('Description')->isVisible($show_description));

			foreach (['desc_h_pos', 'desc_v_pos', 'desc_size', 'lbl_desc_color', 'decimal_places', 'decimal_size',
				'value_h_pos', 'value_size', 'value_v_pos', 'lbl_value_color', 'units', 'units_pos',
				'units_size', 'units_size', 'lbl_units_color', 'time_h_pos', 'time_size', 'time_v_pos',
				'lbl_time_color', 'lbl_up_color', 'lbl_down_color', 'lbl_updown_color', 'lbl_bg_color'] as  $id)  {
				$this->assertTrue($form->query('id', $id)->one()->isVisible($show_description));
			}

			foreach (['desc_bold', 'value_bold', 'units_show', 'time_bold'] as  $id)  {
				$this->assertTrue($form->query('id', $id)->one()->isEnabled($show_description));
			}

			if ($show_description){

				// Summon the hint-box.
				$form->query('class:icon-help-hint')->one()->click();
				$hint = $this->query('xpath:.//div[@data-hintboxid]')->waitUntilPresent();

				// Assert text.
				$this->assertEquals("Supported macros:".
						"\n{HOST.*}".
						"\n{ITEM.*}".
						"\n{INVENTORY.*}".
						"\nUser macros", $hint->one()->getText());

				// Close the hint-box.
				$hint->one()->query('xpath:.//button[@class="overlay-close-btn"]')->one()->click();
				$hint->waitUntilNotPresent();
			}
		}

		// Description checkbox.
		foreach ([false, true] as $description) {
			$form->fill(['id:show_1' => $description]);

			foreach (['description', 'desc_h_pos_0', 'desc_h_pos_1', 'desc_h_pos_2', 'desc_v_pos_0',
				'desc_v_pos_1', 'desc_v_pos_2', 'desc_size', 'desc_bold', 'desc_color'] as  $id)  {
				$this->assertTrue($form->query('id', $id)->one()->isEnabled($description));
			}
		}

		// Value checkbox.
		foreach ([false, true] as $value) {
			$form->fill(['id:show_2' => $value]);

			foreach (['decimal_places', 'decimal_size', 'value_h_pos_0', 'value_h_pos_1', 'value_h_pos_2',
				'value_size', 'value_v_pos_0', 'value_v_pos_1', 'value_v_pos_2', 'value_bold', 'value_color'] as  $id)  {
				$this->assertTrue($form->query('id', $id)->one()->isEnabled($value));
			}
		}

		// Units checkbox.
		foreach ([false, true] as $units) {
			$form->fill(['id:units_show' => $units]);

			foreach (['units', 'label-units_pos', 'units_size', 'units_bold', 'units_color'] as  $id)  {
				$this->assertTrue($form->query('id', $id)->one()->isEnabled($units));
			}
		}

		// Time checkbox.
		foreach ([false, true] as $time) {
			$form->fill(['id:show_3' => $time]);

			foreach (['time_h_pos_0', 'time_h_pos_1', 'time_h_pos_2', 'time_v_pos_0', 'time_v_pos_1', 'time_v_pos_2',
				'time_size', 'time_bold', 'time_color'] as  $id)  {
				$this->assertTrue($form->query('id', $id)->one()->isEnabled($time));
			}
		}

		// Change indicator checkbox.
		foreach ([false, true] as $indicator) {
			$form->fill(['id:show_4' => $indicator]);

			foreach (['up_color', 'down_color', 'updown_color'] as  $id)  {
				$this->assertTrue($form->query('id', $id)->one()->isEnabled($indicator));
			}
		}
	}

	/**
	 * @backupOnce dashboard
	 * @dataProvider getWidgetData
	 */
	public function testDashboardItemValueWidget_Create($data) {
		$this->checkWidgetForm($data);
	}

	public function testDashboardItemValueWidget_CancelCreate() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();
		$old_hash = CDBHelper::getHash($this->sql);
		$dialogue = $dashboard->edit()->addWidget();
		$form = $dialogue->asForm();
		$form->fill([
			'Type' => 'Item value',
			'Name' => 'Widget to cancel',
			'Item' => 'Available memory in %'
		]);
		$dialogue->query('button', 'Cancel')->one()->click();
		$dashboard->save();
		$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	/**
	 * @dataProvider getWidgetData
	 */
	public function testDashboardItemValueWidget_Update($data) {
		$this->checkWidgetForm($data, true);
	}

	public function testDashboardItemValueWidget_SimpleUpdate() {
		$this->checkUpdateUnchanged();
	}

	public function testDashboardItemValueWidget_CancelUpdate() {
		$this->checkUpdateUnchanged(true);
	}


	public function testDashboardItemValueWidget_Delete() {
		$name = 'Widget to delete';
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one()->edit();
		$old_widget_count = $dashboard->getWidgets()->count();
		$this->assertEquals(true, $dashboard->getWidget($name)->isEditable());
		$dashboard->deleteWidget($name);
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertEquals($old_widget_count - 1, $dashboard->getWidgets()->count());
		$this->assertEquals('', CDBHelper::getRow('SELECT * from widget WHERE name = '.zbx_dbstr('Widget to delete')));
	}

	/**
	 * Function for checking widget refresh interval.
	 *
	 * @param array $data	data provider
	 * @param string $header	name of existing widget
	 */
	private function checkRefreshInterval($data, $header) {
		$refresh = (CTestArrayHelper::get($data['fields'], 'Refresh interval') === 'Default (1 minute)')
			? '15 minutes'
			: (CTestArrayHelper::get($data['fields'], 'Refresh interval', '1 minute'));
		$this->assertEquals($refresh, CDashboardElement::find()->one()->getWidget($header)->getRefreshInterval());
	}

	/**
	 * Function for checking editing widget form without changes.
	 *
	 * @param boolean $cancel	is updating canceled
	 */
	private function checkUpdateUnchanged($cancel = false) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$old_hash = CDBHelper::getHash($this->sql);
		$header = self::$old_name;
		$widget = $dashboard->getWidget($header);
		$form = $widget->edit()->asForm();
		$original_values = $form->getFields()->asValues();

		if ($cancel) {
			$form->fill([
				'Type' => 'Item value',
				'Name' => 'Widget to cancel',
				'Item' => 'Available memory in %'
			]);
			COverlayDialogElement::find()->waitUntilReady()->one()->query('button', 'Cancel')->one()->click();
		}
		else {

		$form->fill(['Type' => 'Item value']);
		$form->submit();
		}

		$this->page->waitUntilReady();
		$this->assertEquals($original_values, $widget->edit()->getFields()->asValues());
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	/**
	 * Function for check the changes.
	 *
	 * @param boolean $update	updating is performed
	 */
	public function checkWidgetForm($data, $update = false) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		if ($update) {
			// Update widget.
			$widget = $dashboard->getWidget(self::$old_name);
			$form = $widget->edit()->asForm();
			$original_values = $form->getFields()->asValues();
			$form->fill($data['fields']);
		}
		else {
			// Add a widget.
			$form = $dashboard->edit()->addWidget()->asForm();
			$form->fill($data['fields']);
			$original_values = $form->getFields()->asValues();
		}

		if (array_key_exists('colors', $data)) {
			foreach ($data['colors'] as $fieldid => $color) {
				$form->query($fieldid)->one()->click()->waitUntilReady();
				$this->query('xpath://div[@class="overlay-dialogue color-picker-dialogue"]')->asColorPicker()->one()->fill($color);
			}
		}

		$form->submit();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash('SELECT * FROM widget ORDER BY widgetid');
			$this->assertMessage($data['expected'], null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM widget ORDER BY widgetid'));
		}
		else {
			// Make sure that the widget is present before saving the dashboard.
			$header = CTestArrayHelper::get($data['fields'], 'Name', 'Item');
			$dashboard->getWidget($header);

			if ($update) {
				$this->assertNotEquals($original_values, $dashboard->getWidget($header)->edit()->asForm()->getFields()->asValues());
			}
			else {
				$this->assertEquals($original_values, $dashboard->getWidget($header)->edit()->asForm()->getFields()->asValues());
			}

			$form->submit();
			$dashboard->save();

			// Check that Dashboard has been saved and that widget has been added.
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
			$this->assertEquals(($update ? $old_widget_count : $old_widget_count + 1), $dashboard->getWidgets()->count());

			// Check that widget created in DB.
			$this->assertEquals(1, CDBHelper::getCount('SELECT null from widget WHERE name = '.zbx_dbstr($data['fields']['Name'])));

			// Check that original widget was not left in DB.
			if ($update) {
				$this->assertEquals(0, CDBHelper::getCount('SELECT null from widget WHERE name = '.zbx_dbstr(self::$old_name)));
			}

			// Check that new widget interval.
			$this->checkRefreshInterval($data, $header);

			// Write new name to update widget for update scenario.
			if ($update) {
				self::$old_name = $header;
			}
		}
	}
}

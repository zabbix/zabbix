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
										'type' => 4,
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
										'type' => 4,
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

	/**
	 * Test to check Item Value Widget.
	 * Check authentication form fields layout.
	 */
	public function testDashboardItemValueWidget_FormLayout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$form = $dashboard->edit()->addWidget()->waitUntilReady()->asForm();
		$form->fill(['Type' => 'Item value']);
		$form->waitUntilReloaded();
		$form->invalidate();

		// Check default values with default Advanced configuration (false).
		$default_values = [
			'Name' => '',
			'Refresh interval' => 'Default (1 minute)',
			'id:show_header' => true,
			'id:show_1' => true,
			'id:show_2' => true,
			'id:show_3' => true,
			'id:show_4' => true,
			'Advanced configuration' => false,
			'id:dynamic' => false
		];

		foreach ($default_values as $field => $value) {
			$this->assertEquals($value, $form->getField($field)->getValue());
		}

		// Check checkboxes dependency on Advanced configuration checkbox.
		$description = [
			'id:description',
			'id:desc_h_pos',
			'id:desc_v_pos',
			'id:desc_size',
			'id:desc_bold'
			// TODO: uncomment after DEV-2154 is ready.
//			'id:desc_color'
		];

		$values = [
			'id:decimal_places',
			'id:decimal_size',
			'id:value_h_pos',
			'id:value_size',
			'id:value_v_pos',
			'id:value_bold'
			// TODO: uncomment after DEV-2154 is ready.
//			'id:value_color'
		];

		$units = [
			'id:units',
			'id:units_pos',
			'id:units_size',
			'id:units_bold'
			// TODO: uncomment after DEV-2154 is ready.
//			'id:units_color'
		];

		$time = [
			'id:time_h_pos',
			'id:time_v_pos',
			'id:time_size',
			'id:time_bold'
			// TODO: uncomment after DEV-2154 is ready.
//			'id:time_color'
		];

		$indicator_colors = [
			// TODO: uncomment after DEV-2154 is ready.
//			'id:up_color',
//			'id:down_color',
//			'id:updown_color',
		];

		$background_color = [
			// TODO: uncomment after DEV-2154 is ready.
//			'lbl_bg_color'
		];

		// Merge all Advanced fields into one array.
		$fields = array_merge($description, $values, $units, $time, $indicator_colors, $background_color);

		foreach ([false, true] as $advanced_config) {
			$form->fill(['Advanced configuration' => $advanced_config]);

			// Check that dynamic item checkbox is not depending on Advanced configuration checkbox state.
			$dynamic_field = $form->getField('Dynamic item');
			$this->assertTrue($dynamic_field->isVisible());
			$this->assertTrue($dynamic_field->isEnabled());

			// Check fields visibility depending on Advanced configuration checkbox state.
			foreach ($fields as $field) {
				$this->assertTrue($form->getField($field)->isVisible($advanced_config));
			}

			// Check advanced fields when Advanced configuration is true.
			if ($advanced_config){
				// Check hintbox.
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

				// Check default values with Advanced configuration = true.
				$default_values_advanced = [
					'id:description' => '{ITEM.NAME}',
					'id:desc_h_pos' => 'Center',
					'id:desc_v_pos' => 'Bottom',
					'id:desc_size' => 15,
					'id:desc_bold' => false,
					'id:decimal_places' => 2,
					'id:decimal_size' => 35,
					'id:value_h_pos' => 'Center',
					'id:value_v_pos' => 'Middle',
					'id:value_size' => 45,
					'id:value_bold' => true,
					'id:units' => '',
					'id:units_pos' => 'After value',
					'id:units_size' => 35,
					'id:units_bold' => true
				];

				foreach ($default_values_advanced as $field => $value) {
					$this->assertEquals($value, $form->getField($field)->getValue());
				}

				// Check fields' lengths.
				$field_lenghts = [
					'Name' =>  255,
					'id:description' => 255,
					'id:desc_size' => 3,
					'id:decimal_places' => 2,
					'id:decimal_size' => 3,
					'id:value_size' => 3,
					'id:units' => 255,
					'id:units_size' => 3
				];

				foreach ($field_lenghts as $field => $length) {
					$this->assertEquals($length, $form->getField($field)->getAttribute('maxlength'));
				}

				// Check fields editability depending on "Show" checkboxes.
				$config_editability = [
					'id:show_1' => $description,
					'id:show_2' => $values,
					'id:units_show' => $units,
					'id:show_3' => $time,
					'id:show_4' => $indicator_colors
				];

				foreach ($config_editability as $config => $elements) {
					foreach ([false, true] as $state) {
						$form->fill([$config => $state]);

						foreach ($elements as $element)  {
							$this->assertTrue($form->getField($element)->isEnabled($state));
						}
					}
				}
			}
		}
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
						'Item' => [
							'values' => 'Available memory',
							'context' => [
								'values' => 'ЗАББИКС Сервер',
								'context' => 'Zabbix servers'
							]
						]
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
						'id:value_bold' => true,
						'id:units' => 's',
						// Value units position.
						'id:units_pos' => 'Before value',
						// Value units size in % relative to the size of the widget.
						'id:units_size' => '36',
						'id:units_bold' => true,
						// Time horizontal position.
						'id:time_h_pos' => 'Left',
						// Time vertical position.
						'id:time_v_pos' => 'Bottom',
						// Time size in % relative to the size of the widget.
						'id:time_size' => '13',
						'id:time_bold' => true,
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
						'id:units' => 'B',
						// Value units position.
						'id:units_pos' => 'Below value',
						// Value units size in % relative to the size of the widget.
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
	 * @backupOnce dashboard
	 * @dataProvider getWidgetData
	 */
	public function testDashboardItemValueWidget_Create($data) {
		$this->checkWidgetForm($data);
	}

	/**
	 * @dataProvider getWidgetData
	 */
	public function testDashboardItemValueWidget_Update($data) {
		$this->checkWidgetForm($data, true);
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

		$form = ($update)
			? $dashboard->getWidget(self::$old_name)->edit()->asForm()
			: $dashboard->edit()->addWidget()->asForm();

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form->fill($data['fields']);

		if ($update && !CTestArrayHelper::get($data['fields'], 'Name')) {
			$form->fill(['Name' => '']);
		}

		if (array_key_exists('colors', $data)) {
			foreach ($data['colors'] as $fieldid => $color) {
				$form->query($fieldid)->one()->click()->waitUntilReady();
				$this->query('xpath://div[@class="overlay-dialogue color-picker-dialogue"]')->asColorPicker()->one()->fill($color);
			}
		}

		$values = $form->getFields()->asValues();

		$form->submit();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash('SELECT * FROM widget ORDER BY widgetid');
			$this->assertMessage($data['expected'], null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM widget ORDER BY widgetid'));
		}
		else {
			COverlayDialogElement::ensureNotPresent();

			$header = CTestArrayHelper::get($data['fields'], 'Name')
					? $data['fields']['Name']
					: $data['fields']['Item']['context']['values'].': '.$data['fields']['Item']['values'];

			$dashboard->getWidget($header)->waitUntilReady();

			// Save Dashboard to ensure that widget is correctly saved.
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check widget count.
			$this->assertEquals($old_widget_count + ($update ? 0 : 1), $dashboard->getWidgets()->count());

			// Check new widget form fields and values in frontend.
			$saved_form = $dashboard->getWidget($header)->edit();
			$this->assertEquals($values, $saved_form->getFields()->asValues());

			// As form is quite complex, show_header field should be checked separately.
			if (array_key_exists('show_header', $data['fields'])) {
				$saved_form->checkValue(['id:show_header' => $data['fields']['show_header']]);
			}

			// Check that widget is saved in DB for correct dashboard and correct dashboard page.
			$this->assertEquals(1,
					CDBHelper::getCount('SELECT * FROM widget w'.
						' WHERE EXISTS ('.
							'SELECT NULL'.
							' FROM dashboard_page dp'.
							' WHERE w.dashboard_pageid=dp.dashboard_pageid'.
								' AND dp.dashboardid='.self::$dashboardid.
								' AND w.name ='.zbx_dbstr(CTestArrayHelper::get($data['fields'], 'Name', '')).')'
			));

			// Check that original widget was not left in DB.
			if ($update) {
				$this->assertEquals(0, CDBHelper::getCount('SELECT null from widget WHERE name = '.zbx_dbstr(self::$old_name)));
			}

			// Close widget popup and check update interval.
			$saved_form->submit();
			COverlayDialogElement::ensureNotPresent();
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check new widget update interval.
			$refresh = (CTestArrayHelper::get($data['fields'], 'Refresh interval') === 'Default (1 minute)')
				? '15 minutes'
				: (CTestArrayHelper::get($data['fields'], 'Refresh interval', '1 minute'));
			$this->assertEquals($refresh, CDashboardElement::find()->one()->getWidget($header)->getRefreshInterval());

			// Write new name to update widget for update scenario.
			if ($update) {
				self::$old_name = $header;
			}
		}
	}

	public function testDashboardItemValueWidget_SimpleUpdate() {
		$this->checkNoChanges();
	}

	public static function getCancelData() {
		return [
			// Cancel creating widget with saving the dashboard.
			[
				[
					'cancel_form' => true,
					'create_widget' => true,
					'save_dashboard' => true
				]
			],
			// Cancel updating widget with saving the dashboard.
			[
				[
					'cancel_form' => true,
					'create_widget' => false,
					'save_dashboard' => true
				]
			],
			// Create widget without saving the dashboard.
			[
				[
					'cancel_form' => false,
					'create_widget' => true,
					'save_dashboard' => false
				]
			],
			// Update widget without saving the dashboard.
			[
				[
					'cancel_form' => false,
					'create_widget' => false,
					'save_dashboard' => false
				]
			]
		];
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testDashboardItemValueWidget_Cancel($data) {
		$this->checkNoChanges($data['cancel_form'], $data['create_widget'], $data['save_dashboard']);
	}

	/**
	 * Function for checking canceling form or submitting without any changes.
	 *
	 * @param boolean $cancel            true if cancel scenario, false if form is submitted
	 * @param boolean $create            true if create scenario, false if update
	 * @param boolean $save_dashboard    true if dashboard will be saved, false if not
	 */
	private function checkNoChanges($cancel = false, $create = false, $save_dashboard = true) {
		$old_hash = CDBHelper::getHash($this->sql);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = $create
			? $dashboard->edit()->addWidget()->asForm()
			: $dashboard->getWidget(self::$old_name)->edit();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		if (!$create) {
			$values = $form->getFields()->asValues();
		}
		else {
			$form->fill(['Type' => 'Item value']);
		}

		if ($cancel || !$save_dashboard) {
			$form->fill([
				'Name' => 'Widget to cancel',
				'Item' => 'Available memory in %'
			]);
		}

		if ($cancel) {
			$dialog->query('button:Cancel')->one()->click();
		}
		else {
			$form->submit();
		}

		COverlayDialogElement::ensureNotPresent();

		if (!$cancel) {
			$dashboard->getWidget(!$save_dashboard ? 'Widget to cancel' : self::$old_name)->waitUntilReady();
		}

		if ($save_dashboard) {
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		}
		else {
			$dashboard->cancelEditing();
		}

		$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());

		// Check that updating widget form values did not change in frontend.
		if (!$create && !$save_dashboard) {
			$this->assertEquals($values, $dashboard->getWidget(self::$old_name)->edit()->getFields()->asValues());
		}

		// Check that DB hash is not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
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
}

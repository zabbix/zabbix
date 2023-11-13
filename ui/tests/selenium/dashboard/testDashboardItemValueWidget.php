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
 * @backup dashboard
 *
 * @onBefore prepareDashboardData
 *
 * @dataSource WebScenarios
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
	protected static $threshold_widget = 'Widget with thresholds';
	private $sql = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid,'.
			' wf.value_itemid, wf.value_graphid';

	/**
	 * Get threshold table element with mapping set.
	 *
	 * @return CMultifieldTable
	 */
	protected function getThresholdTable() {
		return $this->query('id:thresholds-table')->asMultifieldTable([
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
								'name' => 'Widget with thresholds',
								'x' => 0,
								'y' => 6,
								'width' => 10,
								'height' => 3,
								'fields' => [
									[
										'type' => 4,
										'name' => 'itemid',
										'value' => 42230
									],
									[
										'type' => '1',
										'name' => 'thresholds.0.color',
										'value' => 'BF00FF'
									],
									[
										'type' => '1',
										'name' => 'thresholds.0.threshold',
										'value' => '0'
									],
									[
										'type' => '1',
										'name' => 'thresholds.1.color',
										'value' => 'FF0080'
									],
									[
										'type' => '1',
										'name' => 'thresholds.1.threshold',
										'value' => '0.01'
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

	public function testDashboardItemValueWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$form = $dashboard->edit()->addWidget()->waitUntilReady()->asForm();
		if ($form->getField('Type') !== 'Item value') {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item value')]);
		}

		// Check default values with default Advanced configuration (false).
		$default_values = [
			'Name' => '',
			'Refresh interval' => 'Default (1 minute)',
			'id:show_header' => true,
			'id:show_1' => true, // Description.
			'id:show_2' => true, // Value.
			'id:show_3' => true, // Time.
			'id:show_4' => true, // Change indicator.
			'Advanced configuration' => false,
			'id:override_hostid_ms' => ''
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
			'id:desc_bold',
			'xpath://input[@id="desc_color"]/..'
		];

		$values = [
			'id:decimal_places',
			'id:decimal_size',
			'id:value_h_pos',
			'id:value_size',
			'id:value_v_pos',
			'id:value_bold',
			'xpath://input[@id="value_color"]/..'
		];

		$units = [
			'id:units',
			'id:units_pos',
			'id:units_size',
			'id:units_bold',
			'xpath://input[@id="units_color"]/..'
		];

		$time = [
			'id:time_h_pos',
			'id:time_v_pos',
			'id:time_size',
			'id:time_bold',
			'xpath://input[@id="time_color"]/..'
		];

		$indicator_colors = [
			'xpath://input[@id="up_color"]/..',
			'xpath://input[@id="down_color"]/..',
			'xpath://input[@id="updown_color"]/..'
		];

		// Merge all Advanced fields into one array.
		$fields = array_merge($description, $values, $units, $time, $indicator_colors, ['Background colour']);

		foreach ([false, true] as $advanced_config) {
			$form->fill(['Advanced configuration' => $advanced_config]);

			// Check that dynamic item checkbox is not depending on Advanced configuration checkbox state.
			$dynamic_field = $form->getField('Override host');
			$this->assertTrue($dynamic_field->isVisible());
			$this->assertTrue($dynamic_field->isEnabled());

			// Check fields visibility depending on Advanced configuration checkbox state.
			foreach ($fields as $field) {
				$this->assertTrue($form->getField($field)->isVisible($advanced_config));
			}

			// Check advanced fields when Advanced configuration is true.
			if ($advanced_config) {
				// Check hintbox.
				$form->getLabel('Description')->query('class:zi-help-filled-small')->one()->click();
				$hint = $this->query('xpath:.//div[@data-hintboxid]')->waitUntilPresent();

				// Assert text.
				$this->assertEquals("Supported macros:".
						"\n{HOST.*}".
						"\n{ITEM.*}".
						"\n{INVENTORY.*}".
						"\nUser macros", $hint->one()->getText());

				// Close the hint-box.
				$hint->one()->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click();
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
					'id:description' => 2048,
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

		$this->assertEquals(['Item', 'Show', 'Description'], $form->getRequiredLabels());

		// Check Override host field.
		$override = $form->getField('Override host');
		$popup_menu_selector = 'xpath:.//button[contains(@class, "zi-chevron-down")]';

		foreach (['button:Select', $popup_menu_selector] as $button) {
			$this->assertTrue($override->query($button)->one()->isClickable());
		}

		$menu = $override->query($popup_menu_selector)->asPopupButton()->one()->getMenu();
		$this->assertEquals(['Widget', 'Dashboard'], $menu->getItems()->asText());

		$override->query($popup_menu_selector)->asPopupButton()->one()->getMenu()->select('Dashboard');
		$form->checkValue(['Override host' => 'Dashboard']);
		$this->assertTrue($override->query('xpath:.//span[@data-hintbox-contents="Dashboard is used as data source."]')
			->one()->isVisible()
		);

		$override->query('button:Select')->waitUntilCLickable()->one()->click();
		$dialogs = COverlayDialogElement::find()->all();
		$this->assertEquals('Widget', $dialogs->last()->getTitle());

		for ($i = $dialogs->count() - 1; $i >= 0; $i--) {
			$dialogs->get($i)->close(true);
		}
	}

	public static function getWidgetData() {
		return [
			// #0.
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
			// #1.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => 'Available memory in %',
						'id:show_1' => false, // Description.
						'id:show_2' => false, // Value.
						'id:show_3' => false, // Time.
						'id:show_4' => false  // Change indicator.
					],
					'error' => ['Invalid parameter "Show": at least one option must be selected.']
				]
			],
			// #2.
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
			// #3.
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
			// #4.
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
			// #5.
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
			// #6.
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
			// #7.
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
			// #8.
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
			// #9.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => 'Available memory in %',
						'Advanced configuration' => true
					],
					'thresholds' => [
						['threshold' => '-']
					],
					'error' => [
						'Invalid parameter "Thresholds/1/threshold": a number is expected.'
					]
				]
			],
			// #10.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => 'Available memory in %',
						'Advanced configuration' => true
					],
					'thresholds' => [
						['threshold' => 'a']
					],
					'error' => [
						'Invalid parameter "Thresholds/1/threshold": a number is expected.'
					]
				]
			],
			// #11.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => 'Available memory in %',
						'Advanced configuration' => true
					],
					'thresholds' => [
						['threshold' => '1a%?']
					],
					'error' => [
						'Invalid parameter "Thresholds/1/threshold": a number is expected.'
					]
				]
			],
			// #12.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => 'Available memory in %',
						'Advanced configuration' => true
					],
					'thresholds' => [
						['threshold' => '1.79E+400']
					],
					'error' => [
						'Invalid parameter "Thresholds/1/threshold": a number is too large.'
					]
				]
			],
			// #13.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => 'Available memory in %',
						'Advanced configuration' => true
					],
					'thresholds' => [
						['threshold' => '1'],
						['threshold' => 'a']
					],
					'error' => [
						'Invalid parameter "Thresholds/2/threshold": a number is expected.'
					]
				]
			],
			// #14.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => 'Available memory in %',
						'Advanced configuration' => true
					],
					'thresholds' => [
						['threshold' => '1'],
						['threshold' => '1']
					],
					'error' => [
						'Invalid parameter "Thresholds/2": value (threshold)=(1) already exists.'
					]
				]
			],
			// #15.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => 'Available memory in %',
						'Advanced configuration' => true
					],
					'thresholds' => [
						['threshold' => '1', 'color' => '']
					],
					'error' => [
						'Invalid parameter "Thresholds/1/color": cannot be empty.'
					]
				]
			],
			// #16.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => 'Available memory in %',
						'Advanced configuration' => true
					],
					'thresholds' => [
						['threshold' => '1', 'color' => 'AABBCC'],
						['threshold' => '2', 'color' => '']
					],
					'error' => [
						'Invalid parameter "Thresholds/2/color": cannot be empty.'
					]
				]
			],
			// #17.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => 'Available memory in %',
						'Advanced configuration' => true
					],
					'thresholds' => [
						['threshold' => 'a', 'color' => 'AABBCC']
					],
					'error' => [
						'Invalid parameter "Thresholds/1/threshold": a number is expected.'
					]
				]
			],
			// #18.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => [
							'values' => 'Linux: Available memory',
							'context' => [
								'values' => 'ЗАББИКС Сервер',
								'context' => 'Zabbix servers'
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
						'Type' => 'Item value',
						'Name' => 'Any name',
						'Refresh interval' => 'No refresh',
						'Item' => 'Available memory in %'
					]
				]
			],
			// #20.
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
			// #21.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Item value',
						'id:show_header' => false,
						'Name' => '#$%^&*()!@{}[]<>,.|',
						'Refresh interval' => '10 minutes',
						'Item' => 'Response code for step "step 1 of scenario 1" of scenario "Scenario for Update".',
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
			// #22.
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
						'Override host' => 'Dashboard'
					]
				]
			],
			// #23.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Item value',
						'id:show_header' => false,
						'Name' => 'Color pick',
						'Refresh interval' => '10 minutes',
						'Item' => 'Response code for step "step 1 of scenario 1" of scenario "Template_Web_scenario".',
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
						'id:units_bold' => true,
						'Background colour' => 'FFAAAA',
						'xpath://button[@id="lbl_desc_color"]/..' => 'AABBCC',
						'xpath://button[@id="lbl_value_color"]/..' => 'CC11CC',
						'xpath://button[@id="lbl_units_color"]/..' => 'BBCC55',
						'xpath://button[@id="lbl_time_color"]/..' => '11AA00',
						'xpath://button[@id="lbl_up_color"]/..' => '00FF00',
						'xpath://button[@id="lbl_down_color"]/..' => 'FF0000',
						'xpath://button[@id="lbl_updown_color"]/..' => '0000FF'
					]
				]
			],
			// #24.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => 'Available memory in %',
						'Name' => 'Item Widget with threshold',
						'Refresh interval' => '1 minute',
						'Advanced configuration' => true
					],
					'thresholds' => [
						['threshold' => '0.01']
					]
				]
			],
			// #25.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => 'Available memory in %',
						'Name' => 'One threshold with color',
						'Refresh interval' => '2 minutes',
						'Advanced configuration' => true
					],
					'thresholds' => [
						['color' => 'EF6C00', 'threshold' => '0.02']
					]
				]
			],
			// #26.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Item value',
						'Item' => 'Available memory in %',
						'Name' => 'Thresholds',
						'Refresh interval' => '2 minutes',
						'Advanced configuration' => true
					],
					'thresholds' => [
						['threshold' => '0.9999'],
						['color' => 'AABBCC', 'threshold' => '1'],
						['threshold' => '5K'],
						['color' => 'FFEB3B', 'threshold' => '1G'],
						['threshold' => '999999999999999']
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

	public static function getWidgetUpdateData() {
		return [
			// #27.
			[
				[
					'expected' => TEST_GOOD,
					'threshold_widget' => true,
					'fields' => [
						'Item' => 'Available memory in %',
						'Name' => 'Widget with thresholds - update',
						'Refresh interval' => '10 minutes',
						'Advanced configuration' => true
					],
					'thresholds' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'color' => 'AABBCC', 'threshold' => '1'],
						['action' => USER_ACTION_UPDATE, 'index' => 1, 'threshold' => '999999999999999']
					]
				]
			],
			// #28.
			[
				[
					'expected' => TEST_GOOD,
					'threshold_widget' => true,
					'fields' => [
						'Item' => 'Available memory in %',
						'Name' => 'Widget with thresholds - remove',
						'Refresh interval' => '10 minutes',
						'Advanced configuration' => true
					],
					'thresholds' => [
						['action' => USER_ACTION_REMOVE, 'index' => 0],
						['action' => USER_ACTION_REMOVE, 'index' => 0]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getWidgetData
	 * @dataProvider getWidgetUpdateData
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
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash('SELECT * FROM widget ORDER BY widgetid');
		}
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$name = ($update && array_key_exists('threshold_widget', $data)) ? self::$threshold_widget : self::$old_name;
		$form = ($update)
			? $dashboard->getWidget($name)->edit()->asForm()
			: $dashboard->edit()->addWidget()->asForm();

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form->fill($data['fields']);

		if ($update && !CTestArrayHelper::get($data['fields'], 'Name')) {
			$form->fill(['Name' => '']);
		}

		if (array_key_exists('thresholds', $data)) {
			$this->getThresholdTable()->fill($data['thresholds']);
		}

		$values = $form->getFields()->filter(new CElementFilter(CElementFilter::VISIBLE))->asValues();
		$form->submit();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
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

			// Open "Advanced configuration" block if it was filled with data.
			if (CTestArrayHelper::get($data, 'fields.Advanced configuration', false)) {
				// After form submit "Advanced configuration" is closed.
				$saved_form->checkValue(['Advanced configuration' => false]);
				$saved_form->fill(['Advanced configuration' => true]);
			}
			$this->assertEquals($values, $saved_form->getFields()->filter(new CElementFilter(CElementFilter::VISIBLE))->asValues());

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
				$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM widget WHERE name='.zbx_dbstr($name)));
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
				if (array_key_exists('threshold_widget', $data)) {
					self::$threshold_widget = $header;
				}
				else {
					self::$old_name = $header;
				}
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
			$dashboard->waitUntilReady()->getWidget(!$save_dashboard ? 'Widget to cancel' : self::$old_name)->waitUntilReady();
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

	public static function getWarningMessageData() {
		return [
			[
				[
					'fields' => [
							'Item' => 'System description',
							'Name' => 'Item Widget with type of information - characters',
							'Advanced configuration' => true
					]
				]
			],
			[
				[
					'fields' => [
							'Item' => 'Get filesystems',
							'Name' => 'Item Widget with type of information - text',
							'Advanced configuration' => true
					]
				]
			],
			[
				[
					'fields' => [
							'Item' => 'item_testPageHistory_CheckLayout_Log',
							'Name' => 'Item Widget with type of information - log',
							'Advanced configuration' => true
					]
				]
			],
			[
				[
					'numeric' => true,
					'fields' => [
							'Item' => 'Free swap space',
							'Name' => 'Item Widget with type of information - Numeric (unsigned)',
							'Advanced configuration' => true
					]
				]
			],
			[
				[
					'numeric' => true,
					'fields' => [
							'Item' => 'Interrupts per second',
							'Name' => 'Item Widget with type of information - Numeric (float)',
							'Advanced configuration' => true
					]
				]
			]
		];
	}

	/**
	 * Check warning message for threshold, when item type is not numeric.
	 *
	 * @dataProvider getWarningMessageData
	 */
	public function testDashboardItemValueWidget_ThresholdWarningMessage($data) {
		$warning = 'id:item-value-thresholds-warning';
		$info = 'class:zi-i-warning';
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->asForm();

		if ($form->getField('Type')->getValue() !== 'Item value') {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item value')]);
		}

		$form->fill($data['fields']);

		if (!array_key_exists('numeric', $data)) {
			// Check that warning item is displayed.
			$this->assertTrue($form->query($warning)->one()->isVisible());

			// Check that info icon is displayed.
			$this->assertTrue($form->getLabel('Thresholds')->query($info)->one()->isVisible());

			// Check hint-box.
			$form->query($warning)->one()->click();
			$hint = $form->query('xpath://div[@class="overlay-dialogue"]')->one()->waitUntilVisible();
			$this->assertEquals('This setting applies only to numeric data.', $hint->getText());

			// Close the hint-box.
			$hint->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click()->waitUntilNotVisible();
		}
		else {
			// Check that warning item is not displayed.
			$this->assertFalse($form->query($warning)->one()->isVisible());

			// Check that info icon is not displayed.
			$this->assertFalse($form->getLabel('Thresholds')->query($info)->one()->isVisible());
		}
	}

	public function testDashboardItemValueWidget_ThresholdColor() {
		$data = [
			'fields' => [
				'Item' => 'Available memory in %',
				'Name' => 'Item Widget with threshold',
				'Advanced configuration' => true
			],
			'thresholds' => [
				['color' => 'AABBCC', 'threshold' => '1'],
				['color' => 'CCDDAA', 'threshold' => '2']
			]
		];

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->asForm();

		if ($form->getField('Type')->getValue() !== 'Item value') {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item value')]);
		}

		$form->fill($data['fields']);
		$this->getThresholdTable()->fill($data['thresholds']);

		$form->submit();
		COverlayDialogElement::ensureNotPresent();
		$this->page->waitUntilReady();
		$dashboard->save();
		$this->assertMessage('Dashboard updated');

		// Value for threshold trigger.
		$index = 1;
		foreach ($data['thresholds'] as $threshold) {
			// Insert item data.
			CDataHelper::addItemData(42244, $index, time() + $index);
			$this->page->refresh()->waitUntilReady();
			$rgb = implode(', ', sscanf($threshold['color'], "%02x%02x%02x"));

			$this->assertEquals('rgba('.$rgb.', 1)', $dashboard->getWidget($data['fields']['Name'])
					->query('xpath:.//div[contains(@class, "dashboard-widget-item")]/div/div')->one()->getCSSValue('background-color')
			);
			$index++;
		}
	}
}

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

/**
 * @backup config, widget
 *
 * @dataSource AllItemValueTypes, GlobalMacros
 *
 * @onBefore prepareDashboardData
 */
class testDashboardGaugeWidget extends testWidgets {

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

	const HOST = 'Host for all item value types';
	const DELETE_GAUGE = 'Gauge for deleting';
	const GAUGE_ITEM = 'Float item';

	/**
	 * Id of the dashboard where gauge widget is created and updated.
	 *
	 * @var integer
	 */
	protected static $dashboardid;

	protected static $update_gauge = 'Gauge for updating';

	/**
	 * Get Thresholds table element with mapping set.
	 *
	 * @return CMultifieldTable
	 */
	protected function getThresholdsTable() {
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

	public function prepareDashboardData() {
		$float_item_id =CDataHelper::get('AllItemValueTypes.itemids.Float item');

		// Add item data to move needle on Gauge.
		CDataHelper::addItemData($float_item_id, 50);

		$dashboards = CDataHelper::call('dashboard.create', [
			'name' => 'Gauge widget dashboard',
			'auto_start' => 0,
			'pages' => [
				[
					'name' => 'Gauge test page',
					'widgets' => [
						[
							'type' => 'gauge',
							'name' => self::$update_gauge,
							'x' => 0,
							'y' => 0,
							'width' => 11,
							'height' => 5,
							'fields' => [
								[
									'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
									'name' => 'itemid',
									'value' => $float_item_id
								]
							]
						],
						[
							'type' => 'gauge',
							'name' => self::DELETE_GAUGE,
							'x' => 11,
							'y' => 0,
							'width' => 11,
							'height' => 5,
							'view_mode' => 0,
							'fields' => [
								[
									'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
									'name' => 'itemid',
									'value' => $float_item_id
								]
							]
						]
					]
				],
				[
					'name' => 'Screenshot page'
				]
			]
		]);

		$this->assertArrayHasKey('dashboardids', $dashboards);
		self::$dashboardid = $dashboards['dashboardids'][0];
	}

	public function testDashboardGaugeWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dialog = CDashboardElement::find()->one()->edit()->addWidget();
		$form = $dialog->asForm();
		$this->assertEquals('Add widget', $dialog->getTitle());
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Gauge')]);

		// Check default fields.
		$fields = [
			'Name' => ['value' => '', 'placeholder' => 'default', 'maxlength' => 255, 'enabled' => true, 'visible' => true],
			'Refresh interval' => ['value' => 'Default (1 minute)', 'enabled' => true, 'visible' => true],
			'Show header' => ['value' => true, 'enabled' => true, 'visible' => true],
			'id:itemid_ms' => ['value' => '', 'placeholder' => 'type here to search', 'enabled' => true, 'visible' => true],
			'Min' => ['value' => 0, 'maxlength' => 255, 'enabled' => true, 'visible' => true],
			'Max' => ['value' => 100, 'maxlength' => 255, 'enabled' => true, 'visible' => true],

			// Colors.
			'xpath:.//input[@id="value_arc_color"]/..' => ['color' => '', 'enabled' => true, 'visible' => true],
			'xpath:.//input[@id="empty_color"]/..' => ['color' => '', 'enabled' => true, 'visible' => true],
			'xpath:.//input[@id="bg_color"]/..' => ['color' => '', 'enabled' => true, 'visible' => true],

			// Show.
			'id:show_1' => ['value' => true, 'enabled' => true, 'visible' => true], // Show Description.
			'id:show_2' => ['value' => true, 'enabled' => true, 'visible' => true], // Show Value.
			'id:show_3' => ['value' => false, 'enabled' => true, 'visible' => true], // Show Needle.
			'id:show_4' => ['value' => true, 'enabled' => true, 'visible' => true], // Show Scale.
			'id:show_5' => ['value' => true, 'enabled' => true, 'visible' => true], // Show Value arc.

			'Angle' => ['value' => '180°', 'enabled' => true, 'labels' => ['180°', '270°'], 'visible' => false],

			// Description.
			'id:description' => ['value' => '{ITEM.NAME}', 'maxlength' => 2048, 'enabled' => true, 'visible' => false],
			'id:desc_size' => ['value' => '15', 'maxlength' => 3, 'enabled' => true, 'visible' => false],
			'id:desc_v_pos' => ['value' => 'Bottom', 'enabled' => true, 'labels' => ['Top', 'Bottom'], 'visible' => false],
			'id:desc_bold' => ['value' => false, 'enabled' => true, 'visible' => false],
			'xpath:.//input[@id="desc_color"]/..' =>  ['color' => '', 'enabled' => true, 'visible' => false],

			// Value.
			'id:decimal_places' => ['value' => 2, 'maxlength' => 2, 'enabled' => true, 'visible' => false],
			'id:value_bold' => ['value' => false, 'enabled' => true, 'visible' => false],
			'id:value_size' => ['value' => 25, 'maxlength' => 3, 'enabled' => true, 'visible' => false],
			'xpath:.//input[@id="value_color"]/..' => ['color' => '', 'enabled' => true, 'visible' => false],

			// Value arc.
			'id:value_arc_size' => ['value' => 20, 'maxlength' => 3, 'enabled' => true, 'visible' => false],

			// Units.
			'id:units_show' => ['value' => true, 'enabled' => true, 'visible' => false],
			'id:units' => ['value' => '', 'maxlength' => 255, 'enabled' => true, 'visible' => false],
			'id:units_size' => ['value' => 25, 'maxlength' => 3, 'enabled' => true, 'visible' => false],
			'id:units_pos' => ['value' => 'After value', 'enabled' => true, 'visible' => false],
			'id:units_bold' => ['value' => false, 'enabled' => true, 'visible' => false],
			'xpath:.//input[@id="units_color"]/..'=> ['color' => '', 'enabled' => true, 'visible' => false],

			// Needle.
			'xpath:.//input[@id="needle_color"]/..' => ['color' => '', 'enabled' => false, 'visible' => false],

			// Scale.
			'id:scale_show_units' => ['value' => true, 'enabled' => true, 'visible' => false],
			'id:scale_size' => ['value' => 15, 'maxlength' => 3, 'enabled' => true, 'visible' => false],
			'id:scale_decimal_places' => ['value' => 0, 'maxlength' => 2, 'enabled' => true, 'visible' => false],

			// Thresholds.
			'id:th_show_labels' => ['value' => false, 'enabled' => false, 'visible' => false],
			'id:th_show_arc' => ['value' => false, 'enabled' => false, 'visible' => false],
			'id:th_arc_size' => ['value' => 5, 'maxlength' => 3, 'enabled' => false, 'visible' => false],

			'id:override_hostid_ms' => [
				'value' => false, 'placeholder' => 'type here to search', 'enabled' => true, 'visible' => true
			]
		];

		$not_visible = [];
		foreach ($fields as $label => $attributes) {
			if (array_key_exists('color', $attributes)) {
				$this->assertEquals($attributes['color'], $form->query($label)->asColorPicker()->one()->getValue());
			}

			$field = $form->getField($label);
			$this->assertTrue($field->isEnabled($attributes['enabled']));
			$this->assertTrue($field->isVisible($attributes['visible']));

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

			// Show Needle is unchecked and Needle color remains invisible by default.
			if ($attributes['visible'] === false && $label !== 'xpath:.//input[@id="needle_color"]/..') {
				$not_visible[] = $label;
			}
		}

		// Check  Advanced configuration's fields visibility.
		$form->fill(['Advanced configuration' => true]);

		// Check hintboxes.
		$hints = [
			'Description' => "Supported macros:".
					"\n{HOST.*}".
					"\n{ITEM.*}".
					"\n{INVENTORY.*}".
					"\nUser macros",
			'Position' => 'Position is ignored for s, uptime and unixtime units.'
		];

		// Check Position dropdown options.
		$this->assertEquals(['Before value', 'Above value', 'After value', 'Below value'],
				$form->getField('id:units_pos')->getOptions()->asText()
		);

		foreach ($hints as $label => $text) {
			// Force click is needed because the label might be hidden under the scrolled part of the form.
			$form->getLabel($label)->query('xpath:./button[@data-hintbox]')->one()->click(true);
			$hint = $this->query('xpath://div[@data-hintboxid]')->waitUntilVisible();
			$this->assertEquals($text, $hint->one()->getText());
			$hint->one()->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click();
			$hint->waitUntilNotPresent();
		}

		// Check visible fields.
		foreach ($not_visible as $visible_field) {
			$this->assertTrue($form->getField($visible_field)->isVisible());
		}

		// Check Threshold parameters.
		$threshold_field = $form->getField('Thresholds');
		$threshold_field->query('button:Add')->one()->waitUntilClickable()->click();
		$threshold_input ='id:thresholds_0_threshold';

		$inputs = [
			'xpath:.//input[@id="thresholds_0_color"]/..',
			$threshold_input,
			'button:Add',
			'button:Remove'
		];

		foreach ($inputs as $selector) {
			$this->assertTrue($threshold_field->query($selector)->one()->waitUntilVisible()->isEnabled());
		}

		$this->assertEquals(255, $form->getField($threshold_input)->getAttribute('maxlength'));
		$form->checkValue([$threshold_input => '']);

		// Fill Threshold field to enable other Threshold options.
		$form->getField($threshold_input)->type('123');

		foreach (['id:th_show_labels' => true, 'id:th_show_arc' => true, 'id:th_arc_size' => false] as $field => $status) {
			$this->assertTrue($form->getField($field)->isEnabled($status));
		}

		// Enable Show arc.
		$form->fill(['id:th_show_arc' => true]);
		$this->assertTrue($form->getField('id:th_arc_size')->isEnabled());

		// Uncheck Show arc for further checkboxes testing.
		$form->fill(['id:th_show_arc' => false]);

		// Check fields' labels and required fields.
		$this->assertEquals(['Type', 'Show header', 'Name', 'Refresh interval', 'Item', 'Min', 'Max', 'Colours',
				'Show', 'Override host', 'Advanced configuration', 'Angle', 'Description', 'Value', 'Value arc', 'Needle', 'Scale',
				'Thresholds'],
				$form->getLabels()->asText()
		);

		$this->assertEquals(['Item', 'Min', 'Max', 'Show', 'Description'], $form->getRequiredLabels());

		// Check visible and enabled fields dependency.
		$dependent_fields = [
			'id:units_show' => [ // Unnamed checkbox under Value field.
				'status' => false,
				'depending' => [
					'editable' => [
						'id:units',
						'id:units_size',
						'id:units_pos',
						'id:units_bold',
						'xpath:.//input[@id="units_color"]/..',
						'id:scale_show_units'
					]
				]
			],
			'id:show_1' => [ // Show Description.
				'status' => false,
				'depending' =>
					[
						'visible' => [
							'id:description',
							'id:desc_size', 'id:desc_v_pos',
							'id:desc_bold',
							'xpath:.//input[@id="desc_color"]/..'
						]
					]
			],
			'id:show_2' => [ // Show Value.
				'status' => false,
				'depending' => [
					'visible' => [
						'id:decimal_places',
						'id:value_bold',
						'id:value_size',
						'xpath:.//input[@id="value_color"]/..',
						'id:units_show',
						'id:units',
						'id:units_size',
						'id:units_pos', 'id:units_bold',
						'xpath:.//input[@id="value_color"]/..'
					]
				]
			],
			'id:show_3' => [ // Show Needle.
				'status' => true,
				'depending' => [
					'visible' => ['xpath:.//input[@id="needle_color"]/..']
				]
			],
			'id:show_4' => [ // Show Scale.
				'status' => false,
				'depending' => [
					'visible' => ['id:scale_show_units', 'id:scale_decimal_places', 'id:scale_size']
				]
			],
			'id:show_5' => [ // Show Value arc.
				'status' => false,
				'depending' => [
					'editable' => ['id:show_3', 'id:show_4'],
					'visible' => ['id:value_arc_size']
				]
			]
		];

		foreach ($dependent_fields as $switch => $parameters) {
			$form->fill([$switch => $parameters['status']]);

			foreach ($parameters['depending'] as $parameter => $labels) {
				foreach ($labels as $label) {
					$field = $form->getField($label);

					if ($parameter === 'editable') {
						$this->assertTrue($field->isEnabled($parameters['status']));
					}
					else {
						$this->assertTrue($field->isVisible($parameters['status']));
					}
				}
			}
		}

		// Check Show and Show arc dependency.
		foreach ([true, false] as $show_arc) {
			$form->fill(['id:th_show_arc' => $show_arc]);

			$show = [
				'id:show_1' => true, // Description.
				'id:show_2' => true, // Value.
				'id:show_3' => true, // Needle.
				'id:show_4' => true, // Scale.
				'id:show_5' => true  // Value arc.
			];

			if (!$show_arc)  {
				$show['id:show_3'] = false;
				$show['id:show_4'] = false;
			}

			foreach ($show as $field => $enabled) {
				$this->assertTrue($form->getField($field)->isEnabled($enabled));
			}
		}

		// Check Override host field.
		$override = $form->getField('Override host');
		$popup_menu = $override->query('xpath:.//button[contains(@class, "zi-chevron-down")]')->one();

		foreach ([$override->query('button:Select')->one(), $popup_menu] as $button) {
			$this->assertTrue($button->isClickable());
		}

		$menu = $popup_menu->asPopupButton()->getMenu();
		$this->assertEquals(['Widget', 'Dashboard'], $menu->getItems()->asText());
		$menu->select('Dashboard');
		$form->checkValue(['Override host' => 'Dashboard']);
		$this->assertTrue($override->query('xpath:.//span[@data-hintbox-contents="Dashboard is used as data source."]')
				->one()->isVisible()
		);

		$override->query('button:Select')->waitUntilCLickable()->one()->click();
		$dialogs = COverlayDialogElement::find()->all();
		$this->assertEquals('Widget', $dialogs->last()->getTitle());

		$dialog_count = $dialogs->count();
		for ($i = $dialog_count - 1; $i >= 0; $i--) {
			$dialogs->get($i)->close(true);
		}
	}

	public static function getWidgetCommonData() {
		return [
			// #0 Empty item.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item' => ''
					],
					'error' => 'Invalid parameter "Item": cannot be empty.'
				]
			],
			// #1 Both min and max equal zeros.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item' => self::GAUGE_ITEM,
						'Min' => 0,
						'Max' => 0
					],
					'error' => [
						'Invalid parameter "Max": value must be greater than "0".'
					]
				]
			],
			// #2 All fields are zeros.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item' => self::GAUGE_ITEM,
						'Min' => 0,
						'Max' => 0,
						'id:desc_size' => 0,
						'id:value_size' => 0,
						'id:value_arc_size' => 0,
						'id:units_size' => 0,
						'id:scale_size' => 0,
						'id:th_show_arc' => true,
						'id:th_arc_size' => 0
					],
					'Thresholds' => [
						['threshold' => '10']
					],
					'error' => [
						'Invalid parameter "Description: Size": value must be one of 1-100.',
						'Invalid parameter "Value: Size": value must be one of 1-100.',
						'Invalid parameter "Value arc: Size": value must be one of 1-100.',
						'Invalid parameter "Units: Size": value must be one of 1-100.',
						'Invalid parameter "Scale: Size": value must be one of 1-100.',
						'Invalid parameter "Arc size": value must be one of 1-100.'
					]
				]
			],
			// #3 Min and Max are the biggest possible.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item' => self::GAUGE_ITEM,
						'Min' => str_repeat(9,255),
						'Max' => str_repeat(9,255)
					],
					'error' => 'Invalid parameter "Max": value must be greater than "1.0E+255".'
				]
			],
			// #4 Min more than Max.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item' => self::GAUGE_ITEM,
						'Min' => 10,
						'Max' => 3
					],
					'error' => 'Invalid parameter "Max": value must be greater than "10".'
				]
			],
			// #5 All fields are empty.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item' => self::GAUGE_ITEM,
						'Min' => '',
						'Max' => '',
						'id:desc_size' => '',
						'id:decimal_places' => '',
						'id:value_size' => '',
						'id:value_arc_size' => '',
						'id:units_size' => '',
						'id:scale_size' => '',
						'id:scale_decimal_places' => '',
						'id:th_show_arc' => true,
						'id:th_arc_size' => ''
					],
					'Thresholds' => [
						['threshold' => '10']
					],
					'error' => [
						'Invalid parameter "Min": cannot be empty.',
						'Invalid parameter "Max": cannot be empty.',
						'Invalid parameter "Description: Size": value must be one of 1-100.',
						'Invalid parameter "Value: Size": value must be one of 1-100.',
						'Invalid parameter "Value arc: Size": value must be one of 1-100.',
						'Invalid parameter "Units: Size": value must be one of 1-100.',
						'Invalid parameter "Scale: Size": value must be one of 1-100.',
						'Invalid parameter "Arc size": value must be one of 1-100.'
					]
				]
			],
			// #6 Text in numeric fields.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item' => self::GAUGE_ITEM,
						'Min' => 'text',
						'Max' => 'test',
						'id:desc_size' => 'abc',
						'id:decimal_places' => 'abc',
						'id:value_size' => 'abc',
						'id:value_arc_size' => 'abc',
						'id:units_size' => 'abc',
						'id:scale_size' => 'abc',
						'id:scale_decimal_places' => 'abc',
						'id:th_show_arc' => true,
						'id:th_arc_size' => 'abc'
					],
					'Thresholds' => [
						['threshold' => 'test']
					],
					'error' => [
						'Invalid parameter "Min": a number is expected.',
						'Invalid parameter "Max": a number is expected.',
						'Invalid parameter "Description: Size": value must be one of 1-100.',
						'Invalid parameter "Value: Size": value must be one of 1-100.',
						'Invalid parameter "Value arc: Size": value must be one of 1-100.',
						'Invalid parameter "Units: Size": value must be one of 1-100.',
						'Invalid parameter "Scale: Size": value must be one of 1-100.',
						'Invalid parameter "Thresholds/1/threshold": a number is expected.',
						'Invalid parameter "Arc size": value must be one of 1-100.'
					]
				]
			],
			// #7 Mixed numbers and random text in numeric fields.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item' => self::GAUGE_ITEM,
						'Min' => '2t',
						'Max' => '3y',
						'id:desc_size' => '1a',
						'id:decimal_places' => '2b',
						'id:value_size' => '1a',
						'id:value_arc_size' => '1a',
						'id:units_size' => '1a',
						'id:scale_size' => '1a',
						'id:scale_decimal_places' => '2b',
						'id:th_show_arc' => true,
						'id:th_arc_size' => '1a'
					],
					'Thresholds' => [
						['threshold' => '1', 'color' => 'ERERER']
					],
					'error' => [
						'Invalid parameter "Min": a number is expected.',
						'Invalid parameter "Max": a number is expected.',
						'Invalid parameter "Thresholds/1/color": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #8 2-bytes special characters.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item' => self::GAUGE_ITEM,
						'Min' => 'そこ',
						'Max' => 'ߘ',
						'id:desc_size' => '۞',
						'id:decimal_places' => 'Ֆ',
						'id:value_size' => '©',
						'id:value_arc_size' => 'ֈ',
						'id:units_size' => 'æ',
						'id:scale_size' => '߷',
						'id:scale_decimal_places' => '',
						'id:th_show_arc' => true,
						'id:th_arc_size' => 'Ä'
					],
					'Thresholds' => [
						['threshold' => 'ß']
					],
					'error' => [
						'Invalid parameter "Min": a number is expected.',
						'Invalid parameter "Max": a number is expected.',
						'Invalid parameter "Description: Size": value must be one of 1-100.',
						'Invalid parameter "Value: Size": value must be one of 1-100.',
						'Invalid parameter "Value arc: Size": value must be one of 1-100.',
						'Invalid parameter "Units: Size": value must be one of 1-100.',
						'Invalid parameter "Scale: Size": value must be one of 1-100.',
						'Invalid parameter "Thresholds/1/threshold": a number is expected.',
						'Invalid parameter "Arc size": value must be one of 1-100.'
					]
				]
			],
			// #9 4-bytes characters.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => '𒀐',
						'Item' => self::GAUGE_ITEM,
						'Min' => '😁',
						'Max' => '🙂',
						'id:desc_size' => '😅',
						'id:decimal_places' => '😘',
						'id:value_size' => '😒',
						'id:value_arc_size' => '😔',
						'id:units_size' => '🤢',
						'id:scale_size' => '😨',
						'id:scale_decimal_places' => '😩',
						'id:th_show_arc' => true,
						'id:th_arc_size' => '😽'
					],
					'Thresholds' => [
						['threshold' => '11']
					],
					'error' => [
						'Invalid parameter "Min": a number is expected.',
						'Invalid parameter "Max": a number is expected.',
						'Invalid parameter "Description: Size": value must be one of 1-100.',
						'Invalid parameter "Value: Size": value must be one of 1-100.',
						'Invalid parameter "Value arc: Size": value must be one of 1-100.',
						'Invalid parameter "Units: Size": value must be one of 1-100.',
						'Invalid parameter "Scale: Size": value must be one of 1-100.',
						'Invalid parameter "Arc size": value must be one of 1-100.'
					]
				]
			],
			// #10 Too big numbers in decimal places.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Character in Threshold',
						'Item' => self::GAUGE_ITEM
					],
					'Thresholds' => [
						['threshold' => '😽']
					],
					'error' => [
						'Invalid parameter "Thresholds/1/threshold": a number is expected.'
					]
				]
			],
			// #11 Too big numbers in decimal places.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item' => self::GAUGE_ITEM,
						'id:decimal_places' => '900',
						'id:scale_decimal_places' => '900'
					],
					'error' => [
						'Invalid parameter "Decimal places": value must be one of 0-10.',
						'Invalid parameter "Decimal places": value must be one of 0-10.'
					]
				]
			],
			// #12
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item' => self::GAUGE_ITEM,
						'id:show_1' => false, // Description.
						'id:show_2' => false, // Value.
						'id:show_3' => false, // Needle.
						'id:show_4' => false, // Scale.
						'id:show_5' => false  // Value arc.
					],
					'error' => [
						'Invalid parameter "Show": at least one option must be selected.'
					]
				]
			],
			// #13 All fields successful case.
			[
				[
					'fields' => [
						'Name' => '😁🙂𒀐',
						'Item' => self::GAUGE_ITEM,
						'Min' => 99,
						'Max' => 88888,
						'xpath:.//input[@id="value_arc_color"]/..' => '64B5F6',
						'xpath:.//input[@id="empty_color"]/..' => 'FFBF00',
						'xpath:.//input[@id="bg_color"]/..' => 'BA68C8',
						'Angle' => '270°',
						'id:description' => '𒀐 New test Description 😁🙂😁🙂',
						'id:desc_size' => 30,
						'id:desc_bold' => true,
						'id:desc_v_pos' => 'Top',
						'xpath:.//input[@id="desc_color"]/..' => 'FFB300',
						'id:decimal_places' => 10,
						'id:value_size' => 50,
						'id:value_bold' => true,
						'xpath:.//input[@id="value_color"]/..' => '283593',
						'id:show_5' => true, // Show Value arc.
						'id:value_arc_size' => 12,
						'id:units' => 'Bytes 𒀐  😁',
						'id:units_size' => 27,
						'id:units_bold' => true,
						'id:units_pos' => 'Above value',
						'xpath:.//input[@id="units_color"]/..' => '4E342E',
						'id:show_3' => true, // Show Needle.
						'xpath:.//input[@id="needle_color"]/..' => '4DD0E1',
						'id:scale_size' => 33,
						'id:scale_decimal_places' => 8,
						'id:th_show_arc' => true,
						'id:th_arc_size' => 85,
						'id:th_show_labels' => true,
						'Override host' => 'Dashboard'
					],
					'Thresholds' => [
						['threshold' => '555', 'color' => '1976D2']
					]
				]
			],
			// #14 Multiple thresholds.
			[
				[
					'fields' => [
						'Name' => 'Multiple thresholds',
						'Min' => 15,
						'Max' => 200,
						'Refresh interval' => '30 seconds',
						'Item' => self::GAUGE_ITEM,
						'id:units_pos' => 'Before value'
					],
					'Thresholds' => [
						['threshold' => '30', 'color' => '03A9F4'],
						['threshold' => '50', 'color' => '283593']
					]
				]
			],
			// #15 False default checkboxes.
			[
				[
					'fields' => [
						'Name' => 'False default checkboxes',
						'Item' => self::GAUGE_ITEM,
						'Show header' => false,
						'id:show_5' => true, // Show Value arc.
						'id:show_1' => false, // Show Description.
						'id:show_2' => false, // Show Value.
						'id:show_3' => false, // Show Needle.
						'id:show_4' => false // Show Scale.
					]
				]
			],
			// #16 False default checkboxes - vol.2.
			[
				[
					'fields' => [
						'Name' => 'False default checkboxes 2',
						'Item' => self::GAUGE_ITEM,
						'Show header' => false,
						'id:show_1' => true, // Show Description.
						'id:show_3' => false, // Show Needle.
						'id:show_4' => false, // Show Scale.
						'id:show_2' => false, // Show Value.
						'id:show_5' => false // Show Value arc.
					]
				]
			],
			// #17 False default checkboxes - vol.3.
			[
				[
					'fields' => [
						'Name' => 'False default checkboxes 3',
						'Item' => self::GAUGE_ITEM,
						'Show header' => false,
						'id:show_2' => true, // Show Value.
						'id:show_3' => false, // Show Needle.
						'id:show_4' => false, // Show Scale.
						'id:show_1' => false, // Show Description.
						'id:show_5' => false // Show Value arc.
					]
				]
			]
		];
	}

	public static function getWidgetCreateData() {
		return [
			// #18 Minimal required fields.
			[
				[
					'fields' => [
						'Item' => self::GAUGE_ITEM,
						'Advanced configuration' => false
					]
				]
			]
		];
	}

	/**
	 *
	 * @backupOnce widget
	 *
	 * @dataProvider getWidgetCommonData
	 * @dataProvider getWidgetCreateData
	 */
	public function testDashboardGaugeWidget_Create($data) {
		$this->checkFormGaugeWidget($data);
	}

	/**
	 * @dataProvider getWidgetCommonData
	 */
	public function testDashboardGaugeWidget_Update($data) {
		$this->checkFormGaugeWidget($data, true);
	}

	/**
	 * Function for checking Gauge widget form.
	 *
	 * @param array      $data      data provider
	 * @param boolean    $update    true if update scenario, false if create
	 */
	public function checkFormGaugeWidget($data, $update = false) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::SQL);
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = $update
			? $dashboard->getWidget(self::$update_gauge)->edit()
			: $dashboard->edit()->addWidget()->asForm();

		COverlayDialogElement::find()->one();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Gauge')]);

		if ($update && CTestArrayHelper::get($data['fields'], 'Item') === '') {
			$form->getField('Item')->clear();
		}

		$form->fill(['Advanced configuration' => true]);

		if (array_key_exists('Thresholds', $data)) {
			// To update Thresholds previously saved values should be removed.
			if ($update) {
				$this->getThresholdsTable()->clear();
			}

			$this->getThresholdsTable()->fill($data['Thresholds']);
		}

		$form->fill($data['fields']);
		$form->submit();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, null, $data['error']);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
		}
		else {
			COverlayDialogElement::ensureNotPresent();

			/**
			 *  When name is absent in create scenario it remains default: host name + item name,
			 *  if name is absent in update scenario then previous name remains.
			 *  If name is empty string in both scenarios it is replaced by host name + item name.
			 */
			if (array_key_exists('Name', $data['fields'])) {
				$header = ($data['fields']['Name'] === '')
					? self::HOST.': '.$data['fields']['Item']
					: $data['fields']['Name'];
			}
			else {
				$header = $update ? self::$update_gauge : self::HOST.': '.$data['fields']['Item'];
			}

			$dashboard->getWidget($header);
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
			$this->assertEquals($old_widget_count + ($update ? 0 : 1), $dashboard->getWidgets()->count());
			$saved_form = $dashboard->getWidget($header)->edit();

			if (array_key_exists('Item', $data['fields'])) {
				$data['fields']['Item'] = self::HOST.': '.$data['fields']['Item'];
			}

			// Check that Advanced configuration is false by default.
			$saved_form->checkValue(['Advanced configuration' => false]);

			// Open Advanced configuration if it is not defined as false in data provider.
			if (CTestArrayHelper::get($data['fields'], 'Advanced configuration', true)) {
				$saved_form->fill(['Advanced configuration' => true]);
			}

			// Check saved fields in form.
			$saved_form->checkValue($data['fields']);

			if (array_key_exists('Thresholds', $data)) {
				$this->getThresholdsTable()->checkValue($data['Thresholds']);
			}

			// Check that widget is saved in DB.
			$this->assertEquals(1,
				CDBHelper::getCount('SELECT * FROM widget w'.
					' WHERE EXISTS ('.
						'SELECT NULL'.
						' FROM dashboard_page dp'.
						' WHERE w.dashboard_pageid=dp.dashboard_pageid'.
							' AND dp.dashboardid='.self::$dashboardid.
							' AND w.name ='.zbx_dbstr(CTestArrayHelper::get($data['fields'], 'Name', '')).')'
				));

			// Write new name to the updated widget name.
			if ($update) {
				self::$update_gauge = $header;
			}
		}

		COverlayDialogElement::find()->one()->close();
	}

	public function testDashboardGaugeWidget_SimpleUpdate() {
		$this->checkNoChanges();
	}

	public static function getCancelData() {
		return [
			// #0 Cancel creating widget with saving the dashboard.
			[
				[
					'cancel_form' => true,
					'create_widget' => true,
					'save_dashboard' => true
				]
			],
			// #1 Cancel updating widget with saving the dashboard.
			[
				[
					'cancel_form' => true,
					'create_widget' => false,
					'save_dashboard' => true
				]
			],
			// #2 Create widget without saving the dashboard.
			[
				[
					'cancel_form' => false,
					'create_widget' => true,
					'save_dashboard' => false
				]
			],
			// #3 Update widget without saving the dashboard.
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
	public function testDashboardGaugeWidget_Cancel($data) {
		$this->checkNoChanges($data['cancel_form'], $data['create_widget'], $data['save_dashboard']);
	}

	/**
	 * Function for checking canceling form or submitting without any changes.
	 *
	 * @param boolean $cancel            true if cancel scenario, false if form is submitted
	 * @param boolean $create            true if create scenario, false if update
	 * @param boolean $save_dashboard    true if dashboard will be saved, false if not
	 */
	protected function checkNoChanges($cancel = false, $create = false, $save_dashboard = true) {
		$old_hash = CDBHelper::getHash(self::SQL);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();
		$dashboard->edit();

		$form = $create
			? $dashboard->addWidget()->asForm()
			: $dashboard->getWidget(self::$update_gauge)->edit();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		if ($create) {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Gauge')]);
		}
		else {
			$values = $form->getValues();
		}

		if ($cancel || !$save_dashboard) {
			$form->fill(
				[
					'Name' => 'new name',
					'Refresh interval' => '10 minutes',
					'Item' => 'testFormItem4',
					'Min' => 10,
					'Max' => 300
				]
			);
		}

		if ($cancel) {
			$dialog->query('button:Cancel')->one()->click();
		}
		else {
			$form->submit();
		}

		COverlayDialogElement::ensureNotPresent();

		if (!$cancel) {
			$dashboard->getWidget($save_dashboard ? self::$update_gauge : 'new name')->waitUntilReady();
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
			$this->assertEquals($values, $dashboard->getWidget(self::$update_gauge)->edit()->getValues());
			COverlayDialogElement::find()->one()->close();
		}

		// Check that DB hash is not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	public function testDashboardGaugeWidget_Delete() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one()->waitUntilReady()->edit();
		$widget = $dashboard->getWidget(self::DELETE_GAUGE);
		$this->assertTrue($widget->isEditable());
		$dashboard->deleteWidget(self::DELETE_GAUGE);
		$widget->waitUntilNotPresent();
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Check that widget is not present on dashboard and in DB.
		$this->assertFalse($dashboard->getWidget(self::DELETE_GAUGE, false)->isValid());
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM widget_field wf'.
			' LEFT JOIN widget w'.
			' ON w.widgetid=wf.widgetid'.
			' WHERE w.name='.zbx_dbstr(self::DELETE_GAUGE)
		));
	}

	/**
	 * Test function for assuring that text, log, binary and char items are not available in Gauge widget.
	 */
	public function testDashboardGaugeWidget_CheckAvailableItems() {
		$url = 'zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid;
		$this->checkAvailableItems($url, 'Gauge');
	}

	public static function getScreenshotsData() {
		return [
			// #0 Minimal settings with value.
			[
				[
					'screenshot_id' => 'Empty gauge with data',
					'fields' => [
						'Item' => self::GAUGE_ITEM
					]
				]
			],
			// #1 Minimal settings No data.
			[
				[
					'screenshot_id' => 'Empty gauge with no data',
					'fields' => [
						'Item' => 'Unsigned item'
					]
				]
			],
			// #2 All settings + Threshold default color.
			[
				[
					'screenshot_id' => 'Full gauge',
					'fields' => [
						'Name' => 'All settings',
						'Item' => self::GAUGE_ITEM,
						'Min' => 20,
						'Max' => 300,
						'xpath:.//input[@id="value_arc_color"]/..' => 'FFCDD2',
						'xpath:.//input[@id="empty_color"]/..' => '26C6DA',
						'xpath:.//input[@id="bg_color"]/..' => 'FFF9C4',
						'Angle' => '270°',
						'id:description' => 'Screenshot Description 😁🙂😁🙂',
						'id:desc_size' => 8,
						'id:desc_bold' => true,
						'id:desc_v_pos' => 'Top',
						'xpath:.//input[@id="desc_color"]/..' => '303F9F',
						'id:decimal_places' => 3,
						'id:value_size' => 17,
						'id:value_bold' => true,
						'xpath:.//input[@id="value_color"]/..' => '00796B',
						'id:show_5' => true, // Show Value arc.
						'id:value_arc_size' => 35,
						'id:units' => 'Bytes 😁',
						'id:units_size' => 12,
						'id:units_bold' => true,
						'id:units_pos' => 'Below value',
						'xpath:.//input[@id="units_color"]/..' => '6D4C41',
						'id:show_3' => true,
						'xpath:.//input[@id="needle_color"]/..' => 'FF0000',
						'id:scale_size' => 11,
						'id:scale_decimal_places' => 2,
						'id:th_show_arc' => true,
						'id:th_arc_size' => 40,
						'id:th_show_labels' => true
					],
					'Thresholds' => [
						['threshold' => '100']
					]
				]
			],
			// #3 Macros in description + Thresholds with color.
			[
				[
					'screenshot_id' => 'Gauge with two thresholds',
					'fields' => [
						'Name' => 'All settings + thresholds',
						'Item' => self::GAUGE_ITEM,
						'Min' => 1,
						'Max' => 300,
						'id:description' => '{HOST.NAME} {ITEM.NAME}',
						'id:desc_size' => 5,
						'id:th_show_arc' => true,
						'id:th_arc_size' => 40,
						'id:th_show_labels' => true
					],
					'Thresholds' => [
						['threshold' => '100', 'color' => '4000FF'],
						['threshold' => '200', 'color' => 'E91E63']
					]
				]
			],
			// #4 More macros in description.
			[
				[
					'screenshot_id' => 'More macros',
					'fields' => [
						'Name' => 'Macros',
						'Item' => self::GAUGE_ITEM,
						'id:description' => '{HOST.CONN} {ITEM.KEY}',
						'id:desc_size' => 5
					]
				]
			],
			// #5 User macros in description.
			[
				[
					'screenshot_id' => 'User macro',
					'fields' => [
						'Name' => 'User macro',
						'Item' => self::GAUGE_ITEM,
						'id:description' => '{$A} {INVENTORY.ALIAS}',
						'id:desc_size' => 5
					]
				]
			]
		];
	}

	/**
	 * Test function for assuring that form settings affect Gauge image.
	 *
	 * @dataProvider getScreenshotsData
	 */
	public function testDashboardGaugeWidget_Screenshots($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one()->waitUntilReady();
		$dashboard->selectPage('Screenshot page');
		$dashboard->invalidate();
		$dialog = $dashboard->edit()->addWidget()->asForm();
		$dialog->fill([
			'Type' => CFormElement::RELOADABLE_FILL('Gauge'),
			'Advanced configuration' => true
		]);

		if (array_key_exists('Thresholds', $data)) {
			$this->getThresholdsTable()->fill($data['Thresholds']);
		}

		$dialog->fill($data['fields']);
		$dialog->submit();
		COverlayDialogElement::ensureNotPresent();

		$header = array_key_exists('Name', $data['fields'])
			? $data['fields']['Name']
			: self::HOST.': '.$data['fields']['Item'];

		// Wait until widget with header appears on the Dashboard.
		$dashboard->save();
		$widget = $dashboard->waitUntilReady()->getWidget($header);
		// Without scroll down on Jenkins error - requested image region is invalid.
		$this->page->scrollDown();

		// Wait until the gauge is animated.
		$this->query('xpath://div['.CXPathHelper::fromClass('is-ready').']')->waitUntilVisible();
		$this->assertScreenshot($widget->query('class:dashboard-grid-widget-container')->one(), $data['screenshot_id']);
	}
}

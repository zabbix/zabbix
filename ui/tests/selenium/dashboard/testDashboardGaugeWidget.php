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
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup config, widget
 *
 * @onBefore prepareDashboardData
 */
class testDashboardGaugeWidget extends CWebTest {

	use TableTrait;

	CONST HOST = 'Host for gauge widget';
	CONST DELETE_GAUGE = 'Gauge for deleting';

	/**
	 * Id of the dashboard where gauge widget is created and updated.
	 *
	 * @var integer
	 */
	protected static $dashboardid;

	private static $update_gauge = 'Gauge for updating';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	private $sql = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_hostid';

	public function prepareDashboardData() {
		CDataHelper::call('host.create', [
			'host' => 'Host for gauge widget',
			'groups' => [['groupid' => 4]]
		]);
		$hostids = CDataHelper::getIds('host');

		// Create triggers based on items.
		$items_data = [];
		$value_types = [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG,
				ITEM_VALUE_TYPE_TEXT
		];

		foreach ($value_types as $i => $type) {
			$items_data[] = [
				'hostid' => $hostids['Host for gauge widget'],
				'name' => $i.' Item for gauge widget',
				'key_' => 'trap'.$i,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => $type
			];
		}

		CDataHelper::call('item.create', $items_data);
		$itemids = CDataHelper::getIds('name');

		$dashboards = CDataHelper::call('dashboard.create', [
			'name' => 'Gauge widget dashboard',
			'auto_start' => 0,
			'pages' => [
				[
					'name' => 'First Page',
					'display_period' => 3600,
					'widgets' => [
						[
							'type' => 'gauge',
							'name' => 'Gauge for updating',
							'x' => 0,
							'y' => 0,
							'width' => 11,
							'height' => 5,
							'view_mode' => 0,
							'fields' => [
								[
									'type' => '4',
									'name' => 'itemid',
									'value' => $itemids['0 Item for gauge widget']
								],
								[
									'type' => '1',
									'name' => 'min',
									'value' => '0'
								],
								[
									'type' => '1',
									'name' => 'max',
									'value' => '100'
								]
							]
						],
						[
							'type' => 'gauge',
							'name' => 'Gauge for deleting',
							'x' => 11,
							'y' => 0,
							'width' => 11,
							'height' => 5,
							'view_mode' => 0,
							'fields' => [
								[
									'type' => '4',
									'name' => 'itemid',
									'value' => $itemids['0 Item for gauge widget']
								],
								[
									'type' => '1',
									'name' => 'min',
									'value' => '0'
								],
								[
									'type' => '1',
									'name' => 'max',
									'value' => '100'
								]
							]
						]
					]
				]
			]
		]);

		$this->assertArrayHasKey('dashboardids', $dashboards);
		self::$dashboardid = $dashboards['dashboardids'][0];
	}

	public function testDashboardGaugeWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$form = CDashboardElement::find()->one()->edit()->addWidget()->asForm();

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->assertEquals('Add widget', $dialog->getTitle());

		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Gauge')]);
		$dialog->waitUntilReady();

		// Check default fields.
		$fields = [
			'Name' => ['value' => '', 'placeholder' => 'default', 'maxlength' => 255, 'enabled' => true, 'visible' => true],
			'Refresh interval' => ['value' => 'Default (1 minute)', 'enabled' => true, 'visible' => true],
			'id:show_header' => ['value' => true, 'enabled' => true, 'visible' => true, 'visible' => true],
			'id:itemid_ms' => ['value' => '', 'placeholder' => 'type here to search', 'enabled' => true, 'visible' => true],
			'Min' => ['value' => 0, 'maxlength' => 255, 'enabled' => true, 'visible' => true],
			'Max' => ['value' => 100, 'maxlength' => 255, 'enabled' => true, 'visible' => true],

			// Colors.
			'xpath:.//input[@id="value_arc_color"]/..' => ['color' => '', 'enabled' => true, 'visible' => true],
			'xpath:.//input[@id="empty_color"]/..' => ['color' => '', 'enabled' => true, 'visible' => true],
			'xpath:.//input[@id="bg_color"]/..' => ['color' => '', 'enabled' => true, 'visible' => true],

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
			'id:value_arc' => ['value' => true, 'enabled' => true, 'visible' => false],
			'id:value_size' => ['value' => 25, 'maxlength' => 3, 'enabled' => true, 'visible' => false],
			'xpath:.//input[@id="value_color"]/..' => ['color' => '', 'enabled' => true, 'visible' => false],
			'id:value_arc_size' => ['value' => 20, 'maxlength' => 3, 'enabled' => true, 'visible' => false],

			// Units.
			'id:units_show' => ['value' => true, 'enabled' => true, 'visible' => false],
			'id:units' => ['value' => '', 'maxlength' => 2048, 'enabled' => true, 'visible' => false],
			'id:units_size' => ['value' => 25, 'maxlength' => 3, 'enabled' => true, 'visible' => false],
			'id:units_pos' => ['value' => 'After value', 'enabled' => true, 'visible' => false],
			'xpath:.//input[@id="units_color"]/..'=> ['color' => '', 'enabled' => true, 'visible' => false],

			// Needle.
			'id:needle_show' => ['value' => false, 'enabled' => true, 'visible' => false],
			'xpath:.//input[@id="needle_color"]/..' => ['color' => '', 'enabled' => false, 'visible' => false],

			// Scale.
			'id:scale_show' => ['value' => true, 'enabled' => true, 'visible' => false],
			'id:scale_size' => ['value' => 10, 'maxlength' => 3, 'enabled' => true, 'visible' => false],
			'id:scale_decimal_places' => ['value' => 0, 'maxlength' => 2, 'enabled' => true, 'visible' => false],
			'id:scale_show_units' => ['value' => true, 'enabled' => true, 'visible' => false],

			// Tresholds.
			'id:th_show_labels' => ['value' => false, 'enabled' => false, 'visible' => false],
			'id:th_show_arc' => ['value' => false, 'enabled' => false, 'visible' => false],
			'id:th_arc_size' => ['value' => 10, 'maxlength' => 3, 'enabled' => false, 'visible' => false],

			'Enable host selection' => ['value' => false, 'enabled' => true, 'visible' => true]
		];

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

		foreach ($hints as $label => $text) {
			$form->getLabel($label)->query('xpath:.//button[@data-hintbox]')->one()->click(true);
			$hint = $this->query('xpath://div[@data-hintboxid]')->waitUntilVisible();
			$this->assertEquals($text, $hint->one()->getText());
			$hint->one()->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click();
			$hint->waitUntilNotPresent();
		}

		// Check visible fields.
		$visible_fields = [
			'Angle',

			// Description.
			'id:description', 'id:desc_size', 'id:desc_v_pos', 'id:desc_bold', 'xpath:.//input[@id="desc_color"]/..',

			// Value.
			'id:decimal_places', 'id:value_bold', 'id:value_arc', 'id:value_size',
			'xpath:.//input[@id="value_color"]/..', 'id:value_arc_size',

			// Units.
			'id:units_show', 'id:units', 'id:units_size', 'id:units_pos', 'id:units_bold', 'xpath:.//input[@id="units_color"]/..',

			// Needle.
			'id:needle_show', 'xpath:.//input[@id="needle_color"]/..',

			// Scale.
			'id:scale_show', 'id:scale_show_units', 'id:scale_decimal_places', 'id:scale_size',

			// Treshold.
			'id:th_show_labels', 'id:th_show_arc', 'id:th_arc_size'
		];

		foreach ($visible_fields as $visible_field) {
			$this->assertTrue($form->getField($visible_field)->isVisible());
		}

		// Check disabled/enabled fields.
		$editable_fields = [
			'id:units_show' => [
				'status' => false,
				'depending' => ['id:units', 'id:units_size', 'id:units_pos', 'id:units_bold', 'xpath:.//input[@id="units_color"]/..']
			],
			'id:needle_show' => [
				'status' => true,
				'depending' => ['xpath:.//input[@id="needle_color"]/..']
			],
			'id:scale_show' => [
				'status' => false,
				'depending' =>  ['id:scale_show_units', 'id:scale_decimal_places', 'id:scale_size']
			]
		];

		foreach ($editable_fields as $switch => $parameters) {
			$form->fill([$switch => $parameters['status']]);

			foreach ($parameters['depending'] as $visible_field) {
				$this->assertTrue($form->getField($visible_field)->isEnabled($parameters['status']));
			}
		}

		// Check Treshold parameters.
		$threshold_field = $form->getField('Thresholds');
		$threshold_field->query('button:Add')->one()->waitUntilClickable()->click();
		$threshold_input ='id:thresholds_0_threshold';

		$inputs = [
			'xpath:.//input[@id="thresholds_0_color"]/..',
			$threshold_input,
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

		// Check fields' labels and required fields.
		$this->assertEquals(['Type', 'Show header', 'Name', 'Refresh interval', 'Item', 'Min', 'Max', 'Colors',
				'Advanced configuration', 'Angle', 'Description', 'Value', 'Needle', 'Scale', 'Thresholds',
				'Enable host selection'],
				$form->getLabels()->asText()
		);

		$this->assertEquals(['Item', 'Min', 'Max'], $form->getRequiredLabels());
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
						'Item' => '1 Item for gauge widget',
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
						'Item' => '1 Item for gauge widget',
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
						['value' => '10']
					],
					'error' => [
						'Invalid parameter "Description size": value must be one of 1-100.',
						'Invalid parameter "Value size": value must be one of 1-100.',
						'Invalid parameter "Arc size": value must be one of 1-100.',
						'Invalid parameter "Units size": value must be one of 1-100.',
						'Invalid parameter "Scale size": value must be one of 1-100.',
						'Invalid parameter "Arc size": value must be one of 1-100.'
					]
				]
			],
			// #3 Min and Max are the biggest possible.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item' => '1 Item for gauge widget',
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
						'Item' => '1 Item for gauge widget',
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
						'Item' => '1 Item for gauge widget',
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
						['value' => '10']
					],
					'error' => [
						'Invalid parameter "Min": cannot be empty.',
						'Invalid parameter "Max": cannot be empty.',
						'Invalid parameter "Description size": value must be one of 1-100.',
						'Invalid parameter "Value size": value must be one of 1-100.',
						'Invalid parameter "Arc size": value must be one of 1-100.',
						'Invalid parameter "Units size": value must be one of 1-100.',
						'Invalid parameter "Scale size": value must be one of 1-100.',
						'Invalid parameter "Arc size": value must be one of 1-100.'
					]
				]
			],
			// #6 Text in numeric fields.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item' => '1 Item for gauge widget',
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
						['value' => 'test']
					],
					'error' => [
						'Invalid parameter "Min": a number is expected.',
						'Invalid parameter "Max": a number is expected.',
						'Invalid parameter "Description size": value must be one of 1-100.',
						'Invalid parameter "Value size": value must be one of 1-100.',
						'Invalid parameter "Arc size": value must be one of 1-100.',
						'Invalid parameter "Units size": value must be one of 1-100.',
						'Invalid parameter "Scale size": value must be one of 1-100.',
						'Invalid parameter "Arc size": value must be one of 1-100.'
					]
				]
			],
			// #7 Mixed numbers and random text in numeric fields.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item' => '1 Item for gauge widget',
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
						['value' => '1', 'color' => 'ERERER']
					],
					'error' => [
						'Invalid parameter "Min": a number is expected.',
						'Invalid parameter "Thresholds/1/color": a hexadecimal color code (6 symbols) is expected.'
					]
				]
			],
			// #8 2-bytes special characters.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item' => '1 Item for gauge widget',
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
						['value' => 'ß']
					],
					'error' => [
						'Invalid parameter "Min": a number is expected.',
						'Invalid parameter "Max": a number is expected.',
						'Invalid parameter "Description size": value must be one of 1-100.',
						'Invalid parameter "Value size": value must be one of 1-100.',
						'Invalid parameter "Arc size": value must be one of 1-100.',
						'Invalid parameter "Units size": value must be one of 1-100.',
						'Invalid parameter "Scale size": value must be one of 1-100.',
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
						'Item' => '1 Item for gauge widget',
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
						['value' => '𒉹']
					],
					'error' => [
						'Invalid parameter "Min": a number is expected.',
						'Invalid parameter "Max": a number is expected.',
						'Invalid parameter "Description size": value must be one of 1-100.',
						'Invalid parameter "Value size": value must be one of 1-100.',
						'Invalid parameter "Arc size": value must be one of 1-100.',
						'Invalid parameter "Units size": value must be one of 1-100.',
						'Invalid parameter "Scale size": value must be one of 1-100.',
						'Invalid parameter "Thresholds/1/threshold": a number is expected.',
						'Invalid parameter "Arc size": value must be one of 1-100.'
					]
				]
			],
			// #10 Too big numbers in decimal places.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item' => '1 Item for gauge widget',
						'id:decimal_places' => '900',
						'id:scale_decimal_places' => '900'
					],
					'error' => [
						'Invalid parameter "Decimal places": value must be one of 0-10.',
						'Invalid parameter "Decimal places": value must be one of 0-10.'
					]
				]
			],
			// #11 All fields successful case.
			[
				[
					'fields' => [
						'Name' => '😁🙂𒀐',
						'Item' => '1 Item for gauge widget',
						'Min' => 99,
						'Max' => 88888,
						'xpath:.//input[@id="value_arc_color"]/..' => '64B5F6',
						'xpath:.//input[@id="empty_color"]/..' => 'FFBF00',
						'xpath:.//input[@id="bg_color"]/..' => 'BA68C8',
						'Angle' => '270°',
						'Description' => '𒀐 New test Description 😁🙂😁🙂',
						'id:desc_size' => 30,
						'id:desc_bold' => true,
						'id:desc_v_pos' => 'Top',
						'xpath:.//input[@id="desc_color"]/..' => 'FFB300',
						'id:decimal_places' => 10,
						'id:value_size' => 50,
						'id:value_bold' => true,
						'xpath:.//input[@id="value_color"]/..' => '283593',
						'id:value_arc' => true,
						'id:value_arc_size' => 12,
						'id:units' => 'Bytes 𒀐  😁',
						'id:units_size' => 27,
						'id:units_bold' => true,
						'id:units_pos' => 'Above value',
						'xpath:.//input[@id="units_color"]/..' => '4E342E',
						'id:needle_show' => true,
						'xpath:.//input[@id="needle_color"]/..' => '4DD0E1',
						'id:scale_size' => 33,
						'id:scale_decimal_places' => 8,
						'id:th_show_arc' => true,
						'id:th_arc_size' => 85,
						'id:th_show_labels' => true,
						'id:th_show_arc' => true,
						'id:th_arc_size' => 99,
						'Enable host selection' => true
					],
					'Thresholds' => [
						['value' => '555', 'color' => '1976D2']
					]
				]
			],
			// #12 Multiple thresholds.
			[
				[
					'fields' => [
						'Name' => 'Multiple thresholds',
						'Min' => 15,
						'Max' => 200,
						'Refresh interval' => '30 seconds',
						'Item' => '1 Item for gauge widget',
						'id:units_pos' => 'Before value'
					],
					'Thresholds' => [
						['value' => '30', 'color' => '03A9F4'],
						['value' => '50', 'color' => '283593']
					]
				]
			],
			// #13 False default checkboxes.
			[
				[
					'fields' => [
						'Name' => 'False default checkboxes',
						'Item' => '1 Item for gauge widget',
						'id:show_header' => false,
						'id:value_arc' => false,
						'id:units_show' => false,
						'id:scale_show' => false
					]
				]
			]
		];
	}

	public static function getWidgetCreateData() {
		return [
			// #14 Minimal required fields.
			[
				[
					'fields' => [
						'Item' => '1 Item for gauge widget'
					]
				]
			]
		];
	}

	/**
	 *
	 * @backupOnce widget
	 *
	 * @dataProvider getWidgetCreateData
	 * @dataProvider getWidgetCommonData
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
			$old_hash = CDBHelper::getHash($this->sql);
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = $update
			? $dashboard->getWidget(self::$update_gauge)->edit()
			: $dashboard->edit()->addWidget()->asForm();

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Gauge')]);

		if ($update && CTestArrayHelper::get($data['fields'], 'Item') === '') {
			$form->getField('Item')->clear();
		}

		$form->fill(['Advanced configuration' => true]);

		if (array_key_exists('Thresholds', $data)) {
			$thresholds_field = $form->getField('Thresholds');

			// To update Thresholds previously saved values should be removed.
			$removed = false;
			if ($update) {
				$remove_buttons = $thresholds_field->query('button:Remove');

				for ($j = 0; $j < $remove_buttons->count(); $j++) {
					$thresholds_field->query('id:thresholds_'.$j.'_remove')->one()->click();
					$removed = true;
				}
			}

			foreach ($data['Thresholds'] as $i => $threshold) {
				// If Thresholds were previously removed, indexes' counts continue from the last removed number.
				if ($removed) {
					$i = $i + $j;
				}

				$thresholds_field->query('button:Add')->one()->waitUntilClickable()->click();

				if (array_key_exists('value', $threshold)) {
					$form->fill(['id:thresholds_'.$i.'_threshold' => $threshold['value']]);
				}

				if (array_key_exists('color', $threshold)) {
					$form->query('xpath:.//input[@id="thresholds_'.$i.'_color"]/..')->asColorPicker()->one()
							->fill($threshold['color']);
				}
			}
		}

		$form->fill($data['fields']);

		if (array_key_exists('show_header', $data)) {
			$form->getField('id:show_header')->fill($data['show_header']);
		}

		$values = $form->getFields()->asValues();
		$form->submit();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, null, $data['error']);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
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

			$dashboard->getWidget($header)->waitUntilReady();
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
			$this->assertEquals($old_widget_count + ($update ? 0 : 1), $dashboard->getWidgets()->count());
			$saved_form = $dashboard->getWidget($header)->edit();

			// Check widget form fields and values in frontend.
			$saved_form->fill(['Advanced configuration' => true]);
			$this->assertEquals($values, $saved_form->getFields()->asValues());

			if (array_key_exists('show_header', $data)) {
				$saved_form->checkValue(['id:show_header' => $data['show_header']]);
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

			// Write new name to updated widget name.
			if ($update) {
				self::$update_gauge = $header;
			}
		}
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
					'create_widget' => false,
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
	private function checkNoChanges($cancel = false, $create = false, $save_dashboard = true) {
		$old_hash = CDBHelper::getHash($this->sql);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();
		$dashboard->edit();

		$form = $create
			? $dashboard->addWidget()->asForm()
			: $dashboard->getWidget(self::$update_gauge)->edit();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		if (!$create) {
			$values = $form->getFields()->asValues();
		}
		else {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Gauge')]);
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
			$dashboard->getWidget(!$save_dashboard ? 'new name' : self::$update_gauge)->waitUntilReady();
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
			$this->assertEquals($values, $dashboard->getWidget(self::$update_gauge)->edit()->getFields()->asValues());
		}

		// Check that DB hash is not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	public function testDashboardGaugeWidget_Delete() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$this->assertTrue($dashboard->edit()->getWidget(self::DELETE_GAUGE)->isEditable());
		$dashboard->deleteWidget(self::DELETE_GAUGE);
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

	public function testDashboardGaugeWidget_CheckAvailableItems() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit()->addWidget()->asForm();
		$dialog = COverlayDialogElement::find()->asForm()->one()->waitUntilReady();
		$dialog->fill(['Type' => CFormElement::RELOADABLE_FILL('Gauge')]);
		$dialog->query('button:Select')->one()->waitUntilClickable()->click();
		$host_item_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$table = $host_item_dialog->query('class:list-table')->asTable()->one()->waitUntilVisible();
		$host_item_dialog->query('class:multiselect-control')->asMultiselect()->one()->fill(self::HOST);
		$table->waitUntilReloaded();

		$visible_items = [
			'0 Item for gauge widget',
			'1 Item for gauge widget'
		];

		$not_visible_items = [
			'2 Item for gauge widget',
			'3 Item for gauge widget',
			'4 Item for gauge widget'
		];

		foreach ($visible_items as $visible_item) {
			$this->assertTrue($host_item_dialog->query('link', $visible_item)->one()->isClickable());
		}

		foreach ($not_visible_items as $invisible_item) {
			$this->assertFalse($host_item_dialog->query('link', $invisible_item)->exists());
		}
	}
}

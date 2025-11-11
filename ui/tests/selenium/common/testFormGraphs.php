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


require_once __DIR__.'/../../include/CWebTest.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';
require_once __DIR__.'/../behaviors/CTableBehavior.php';
require_once __DIR__.'/../../include/helpers/CDataHelper.php';

class testFormGraphs extends CWebTest {

	const HOST = 'Simple form test host';					// Host id = 40001.
	const HOSTID = 40001;									// Simple form test host.
	const LLDID = 133800;									// testFormDiscoveryRule on Simple form test host.
	const SQL = 'SELECT * FROM graphs ORDER BY graphid';
	const LLD_WITH_ITEMS = 'LLD rule for item types';
	const HOST_WITH_ITEMS = 'Host for all item value types';

	/**
	 * Flag for graph prototype.
	 */
	protected $prototype = false;
	protected $formid;

	/**
	 * URL for opening graph or graph prototype form.
	 */
	protected $url;

	/**
	 * Name of graph for update scenario.
	 *
	 */
	protected static $update_graph;

	/**
	 * Id for item used in graph prototype.
	 *
	 * @var integer
	 */
	protected static $itemid;

	/**
	 * Ids of items for creating graphs.
	 *
	 * @var array
	 */
	protected static $items = [
		'items' => [
			'graph_trap_int' => ['value_type' => ITEM_VALUE_TYPE_UINT64, 'itemid' => null],
			'graph_trap_float' => ['value_type' => ITEM_VALUE_TYPE_FLOAT, 'itemid' => null],
			'graph_trap_text' => ['value_type' => ITEM_VALUE_TYPE_TEXT, 'itemid' => null],
			'graph_trap_log' => ['value_type' => ITEM_VALUE_TYPE_LOG, 'itemid' => null]
		],
		'item_prototypes' => [
			'graph_prototype_trap_int' => ['value_type' => ITEM_VALUE_TYPE_UINT64, 'itemid' => null],
			'graph_prototype_trap_float' => ['value_type' => ITEM_VALUE_TYPE_FLOAT, 'itemid' => null],
			'graph_prototype_trap_text' => ['value_type' => ITEM_VALUE_TYPE_TEXT, 'itemid' => null],
			'graph_prototype_trap_char' => ['value_type' => ITEM_VALUE_TYPE_STR, 'itemid' => null]
		]
	];

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

	private function getGraphSuffix() {
		return $this->prototype ? ' prototype' : '';
	}

	public function getLayoutData() {
		return [
			[
				[
					'check_defaults' => true,
					'set_fields' => [
						'Graph type' => CFormElement::RELOADABLE_FILL('Normal')
					],
					'check_fields' => [
						'id:name' => ['value' => '', 'maxlength' => 128],
						'id:width' => ['value' => '900', 'maxlength' => 5],
						'id:height' => ['value' => '200', 'maxlength' => 5],
						'id:graphtype' => ['value' => 'Normal'],
						'id:show_legend' => ['value' => true],
						'id:show_work_period' => ['value' => true],
						'id:show_triggers' => ['value' => true],
						'id:visible_percent_left' => ['value' => false], // Percentile line (left) checkbox.
						'id:visible_percent_right' => ['value' => false], // Percentile line (right) checkbox.
						'id:percent_left' => ['visible' => false], // Percentile line (left) input.
						'id:percent_right' => ['visible' => false], // Percentile line (right) input.
						'id:ymin_type' => ['value' => 'Calculated'], // Y axis MIN value dropdown.
						'id:ymax_type' => ['value' => 'Calculated'], // Y axis MAX value dropdown.
						'id:yaxismin' => ['visible' => false], // Y axis MIN fixed value input.
						'id:yaxismax' => ['visible' => false], // Y axis MAX fixed value input.
						'id:ymin_itemid' => ['visible' => false], // Y axis MIN item input.
						'id:ymax_itemid' => ['visible' => false], // Y axis MAX item input.
						'id:items-table' => ['visible' => true]
					],
					'items' => [
						'item_columns' => ['', '', 'Name', 'Function', 'Draw style', 'Y axis side', 'Colour', ''],
						'dropdowns' => [
							'calc_fnc' => ['all', 'min', 'avg', 'max'],
							'drawtype' => ['Line', 'Filled region', 'Bold line', 'Dot', 'Dashed line', 'Gradient line'],
							'yaxisside' => ['Left', 'Right']
						]
					]
				]
			],
			[
				[
					'set_fields' => [
						'Graph type' => CFormElement::RELOADABLE_FILL('Stacked')
					],
					'check_fields' => [
						'id:name' => ['value' => ''],
						'id:width' => ['value' => '900'],
						'id:height' => ['value' => '200'],
						'id:graphtype' => ['value' => 'Stacked'],
						'id:show_legend' => ['value' => true],
						'id:show_work_period' => ['value' => true],
						'id:show_triggers' => ['value' => true],
						'id:visible_percent_left' => ['exists' => true, 'visible' => false], // Percentile line (left) checkbox.
						'id:visible_percent_right' => ['exists' => true, 'visible' => false], // Percentile line (right) checkbox.
						'id:percent_left' => ['exists' => true, 'visible' => false], // Percentile line (left) input.
						'id:percent_right' => ['exists' => true, 'visible' => false], // Percentile line (right) input.
						'id:ymin_type' => ['value' => 'Calculated'], // Y axis MIN value dropdown.
						'id:ymax_type' => ['value' => 'Calculated'], // Y axis MAX value dropdown.
						'id:yaxismin' => ['visible' => false], // Y axis MIN fixed value input.
						'id:yaxismax' => ['visible' => false], // Y axis MAX fixed value input.
						'id:ymin_itemid' => ['visible' => false], // Y axis MIN item input.
						'id:ymax_itemid' => ['visible' => false], // Y axis MAX item input.
						'id:items-table' => ['visible' => true]
					],
					'items' => [
						'item_columns' => ['', '', 'Name', 'Function', 'Y axis side', 'Colour', ''],
						'dropdowns' => [
							'calc_fnc' => ['min', 'avg', 'max'],
							'yaxisside' => ['Left', 'Right']
						]
					]
				]
			],
			[
				[
					'set_fields' => [
						'Graph type' => CFormElement::RELOADABLE_FILL('Pie')
					],
					'check_fields' => [
						'id:name' => ['value' => ''],
						'id:width' => ['value' => '900'],
						'id:height' => ['value' => '200'],
						'id:graphtype' => ['value' => 'Pie'],
						'id:show_legend' => ['value' => true],
						'id:show_work_period' => ['exists' => true, 'visible' => false],
						'id:show_triggers' => ['exists' => true, 'visible' => false],
						'id:visible_percent_left' => ['exists' => true, 'visible' => false], // Percentile line (left) checkbox.
						'id:visible_percent_right' => ['exists' => true, 'visible' => false], // Percentile line (right) checkbox.
						'id:percent_left' => ['exists' => true, 'visible' => false], // Percentile line (left) input.
						'id:percent_right' => ['exists' => true, 'visible' => false], // Percentile line (right) input.
						'id:ymin_type' => ['exists' => true, 'visible' => false], // Y axis MIN value dropdown.
						'id:ymax_type' => ['exists' => true, 'visible' => false], // Y axis MAX value dropdown.
						'id:yaxismin' => ['exists' => true, 'visible' => false], // Y axis MIN fixed value input.
						'id:yaxismax' => ['exists' => true, 'visible' => false], // Y axis MAX fixed value input.
						'id:ymin_itemid' => ['visible' => false], // Y axis MIN item input.
						'id:ymax_itemid' => ['visible' => false] // Y axis MAX item input.
					],
					'items' => [
						'item_columns' => ['', '', 'Name', 'Type', 'Function', 'Colour', ''],
						'dropdowns' => [
							'type' => ['Simple', 'Graph sum'],
							'calc_fnc' => ['min', 'avg', 'max', 'last']
						]
					]
				]
			],
			[
				[
					'set_fields' => [
						'Graph type' => CFormElement::RELOADABLE_FILL('Exploded')
					],
					'check_fields' => [
						'id:name' => ['value' => ''],
						'id:width' => ['value' => '900'],
						'id:height' => ['value' => '200'],
						'id:graphtype' => ['value' => 'Exploded'],
						'id:show_legend' => ['value' => true],
						'id:show_work_period' => ['exists' => true, 'visible' => false],
						'id:show_triggers' => ['exists' => true, 'visible' => false],
						'id:visible_percent_left' => ['exists' => true, 'visible' => false], // Percentile line (left) checkbox.
						'id:visible_percent_right' => ['exists' => true, 'visible' => false], // Percentile line (right) checkbox.
						'id:percent_left' => ['exists' => true, 'visible' => false], // Percentile line (left) input.
						'id:percent_right' => ['exists' => true, 'visible' => false], // Percentile line (right) input.
						'id:ymin_type' => ['exists' => true, 'visible' => false], // Y axis MIN value dropdown.
						'id:ymax_type' => ['exists' => true, 'visible' => false], // Y axis MAX value dropdown.
						'id:yaxismin' => ['exists' => true, 'visible' => false], // Y axis MIN fixed value input.
						'id:yaxismax' => ['exists' => true, 'visible' => false], // Y axis MAX fixed value input.
						'id:ymin_itemid' => ['visible' => false], // Y axis MIN item input.
						'id:ymax_itemid' => ['visible' => false], // Y axis MAX item input.
						'id:show_3d' => ['value' => false],
						'id:items-table' => ['visible' => true]
					],
					'items' => [
						'item_columns' => ['', '', 'Name', 'Type', 'Function', 'Colour', ''],
						'dropdowns' => [
							'type' => ['Simple', 'Graph sum'],
							'calc_fnc' => ['min', 'avg', 'max', 'last']
						]
					]
				]
			],
			[
				[
					'set_fields' => [
						'Graph type' => CFormElement::RELOADABLE_FILL('Normal'),
						'id:visible_percent_left' => true, // Percentile line (left) checkbox.
						'id:visible_percent_right' => true // Percentile line (right) checkbox.
					],
					'check_fields' => [
						'id:percent_left' => ['value' => 0, 'visible' => true], // Percentile line (left) input.
						'id:percent_right' => ['value' => 0, 'visible' => true] // Percentile line (right) input.
					]
				]
			],
			[
				[
					'set_fields' => [
						'Graph type' => CFormElement::RELOADABLE_FILL('Normal'),
						'id:ymin_type' => CFormElement::RELOADABLE_FILL('Fixed'), // Y axis MIN value dropdown.
						'id:ymax_type' => CFormElement::RELOADABLE_FILL('Fixed') // Y axis MAX value dropdown.
					],
					'check_fields' => [
						'id:yaxismin' => ['value' => 0, 'visible' => true], // Y axis MIN fixed value input.
						'id:yaxismax' => ['value' => 100, 'visible' => true] // Y axis MAX fixed value input.
					]
				]
			],
			[
				[
					'set_fields' => [
						'Graph type' => CFormElement::RELOADABLE_FILL('Normal'),
						'id:ymin_type' => CFormElement::RELOADABLE_FILL('Item'), // Y axis MIN value dropdown.
						'id:ymax_type' => CFormElement::RELOADABLE_FILL('Item') // Y axis MAX value dropdown.
					],
					'check_fields' => [
						'id:ymin_itemid' => ['value' => '', 'visible' => true], // Y axis MIN item input.
						'id:ymax_itemid' => ['value' => '', 'visible' => true] // Y axis MAX item input.
					]
				]
			]
		];
	}

	public function checkGraphFormLayout($data) {
		$this->page->login()->open($this->url)->waitUntilReady();
		$object = 'Graph'.$this->getGraphSuffix();
		$this->query('button', 'Create '.lcfirst($object))->waitUntilClickable()->one()->click();
		$this->page->assertTitle('Configuration of '.lcfirst($object).'s');
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $dialog->query('id', $this->formid)->waitUntilVisible()->asForm()->one();

		// Check default fields only for first case.
		if (CTestArrayHelper::get($data, 'check_defaults', false)) {
			$this->assertEquals([$object, 'Preview'], $form->getTabs());
			$this->assertFalse($form->query('xpath:.//table[@id="items-table"]//div[@class="drag-icon"]')->exists());

			$items_container = $form->getFieldContainer('Items');
			$this->assertTrue($items_container->query('button:Add')->one()->isClickable());

			if ($this->prototype) {
				$this->assertTrue($items_container->query('button:Add prototype')->one()->isClickable());
				$discover_field = $form->getField('Discover');
				$this->assertTrue($discover_field->isVisible());
				$this->assertEquals(true, $discover_field->getValue());
			}
			else {
				$this->assertFalse($items_container->query('button:Add prototype')->exists());
				$this->assertFalse($form->query('id:discover')->exists());
			}

			$form->selectTab('Preview');
			$this->page->waitUntilReady();
			$this->assertTrue($this->query('xpath://div[@id="preview-chart"]/img')->waitUntilPresent()->one()->isVisible());

			$form->selectTab($object);
			$this->page->waitUntilReady();
		}

		$form->fill($data['set_fields']);

		foreach ($data['check_fields'] as $field => $attribute) {
			if (array_key_exists('exists', $attribute)) {
				$this->assertEquals($attribute['exists'], $form->query($field)->exists());
			}

			if (array_key_exists('visible', $attribute)) {
				$this->assertTrue($form->query($field)->one(false)->isVisible($attribute['visible']));
			}

			if (array_key_exists('value', $attribute)) {
				$this->assertEquals($attribute['value'], $form->getField($field)->getValue());
			}

			if (array_key_exists('maxlength', $attribute)) {
				$this->assertEquals($attribute['maxlength'], $form->getField($field)->getAttribute('maxlength'));
			}
		}

		// Check items functions fields depending on graph type.
		if (array_key_exists('items', $data)) {
			$form->invalidate();
			$items_container = $form->getFieldContainer('Items');

			$item = ($this->prototype)
				? ['button' => 'Add prototype', 'name' => 'testFormItemPrototype1']
				: ['button' => 'Add', 'name' => 'testFormItem'];

			$items_container->query('button', $item['button'])->waitUntilClickable()->one()->click();
			COverlayDialogElement::find()->all()->last()->waitUntilReady()->query('link', $item['name'])
					->waitUntilClickable()->one()->click();

			$visibleHeaders = $form->query('id:items-table')->asTable()->one()
					->query('xpath:.//th[not(contains(@style, "display: none"))]')->all()->asText();
			$this->assertEquals($data['items']['item_columns'], $visibleHeaders);

			// Check items functions dropdown options depending on graph type.
			foreach ($data['items']['dropdowns'] as $function => $options) {
				$dropdown = $items_container->query('xpath:.//z-select[@name="items[0]['.$function.']"]')->asDropdown()->one();
				$this->assertEquals($options, $dropdown->getOptions()->asText());

				// Check default selected option.
				$this->assertEquals($options[0], $dropdown->getValue());
			}
		}
		$dialog->close();
	}

	public function getCommonGraphData() {
		return [
			// #0.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => '',
						'Width' => '',
						'Height' => ''
					],
					'details' => [
						'Incorrect value for field "name": cannot be empty.',
						'Incorrect value for field "width": value must be no less than "20".',
						'Incorrect value "" for "height" field.',
						'Field "items" is mandatory.'
					]
				]
			],
			// #1.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Fractional width and height'.($this->prototype ? ' {#KEY}' : NULL),
						'Width' => 1.2,
						'Height' => 15.5
					],
					'details' => [
						'Incorrect value "1.2" for "width" field.',
						'Incorrect value "15.5" for "height" field.',
						'Field "items" is mandatory.'
					]
				]
			],
			// #2.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Negative and empty inputs'.($this->prototype ? ' {#KEY}' : NULL),
						'Width' => -100,
						'Height' => -1,
						'id:visible_percent_left' => true,
						'id:visible_percent_right' => true,
						'id:percent_left' => -2,
						'id:percent_right' => -200,
						'id:ymin_type' => CFormElement::RELOADABLE_FILL('Fixed'),
						'id:ymax_type' => CFormElement::RELOADABLE_FILL('Fixed'),
						'id:yaxismin' => '',
						'id:yaxismax' => ''
					],
					'details' => [
						'Incorrect value for field "width": value must be no less than "20".',
						'Incorrect value for field "height": value must be no less than "20".',
						'Field "items" is mandatory.'
					]
				]
			],
			// #3.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Too large width and height'.($this->prototype ? ' {#KEY}' : NULL),
						'Width' => 65536,
						'Height' => 65536
					],
					'items' => [
						[
							'prototype' => $this->prototype,
							'item' => 'testFormItem'.($this->prototype ? 'Prototype1' : ''),
							'color' => 'BBDEFB',
							'functions' => [
								'calc_fnc' => 'min',
								'drawtype' => 'Bold line',
								'yaxisside' => 'Right'
							]
						]
					],
					'details' => [
						'Incorrect value for field "width": value must be no greater than "65535".',
						'Incorrect value for field "height": value must be no greater than "65535".'
					]
				]
			],
			// #4.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Too low width and height'.($this->prototype ? ' {#KEY}' : NULL),
						'Width' => -65536,
						'Height' => -65536
					],
					'items' => [
						[
							'prototype' => $this->prototype,
							'item' => 'testFormItem'.($this->prototype ? 'Prototype1' : ''),
							'color' => 'BBDEFB',
							'functions' => [
								'calc_fnc' => 'min',
								'drawtype' => 'Bold line',
								'yaxisside' => 'Right'
							]
						]
					],
					'details' => [
						'Incorrect value for field "width": value must be no less than "20".',
						'Incorrect value for field "height": value must be no less than "20".'
					]
				]
			],
			// #5.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Too large value for percent_left field'.($this->prototype ? ' {#KEY}' : NULL),
						'id:visible_percent_left' => true,
						'id:percent_left' => 101
					],
					'items' => [
						[
							'prototype' => $this->prototype,
							'item' => 'testFormItem'.($this->prototype ? 'Prototype1' : ''),
							'color' => 'BBDEFB',
							'functions' => [
								'calc_fnc' => 'min',
								'drawtype' => 'Bold line',
								'yaxisside' => 'Right'
							]
						]
					],
					'details' => [
						'Incorrect value for field "percent_left": value must be between "0" and "100", '.
							'and have no more than "4" digits after the decimal point.'
					]
				]
			],
			// #6.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Negative value for percent_left field'.($this->prototype ? ' {#KEY}' : NULL),
						'id:visible_percent_left' => true,
						'id:percent_left' => -101
					],
					'items' => [
						[
							'prototype' => $this->prototype,
							'item' => 'testFormItem'.($this->prototype ? 'Prototype1' : ''),
							'color' => 'BBDEFB',
							'functions' => [
								'calc_fnc' => 'min',
								'drawtype' => 'Bold line',
								'yaxisside' => 'Right'
							]
						]
					],
					'details' => [
						'Incorrect value for field "percent_left": value must be between "0" and "100", '.
							'and have no more than "4" digits after the decimal point.'
					]
				]
			],
			// #7.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Float value in percent_left field'.($this->prototype ? ' {#KEY}' : NULL),
						'id:visible_percent_left' => true,
						'id:percent_left' => '1,3'
					],
					'items' => [
						[
							'prototype' => $this->prototype,
							'item' => 'testFormItem'.($this->prototype ? 'Prototype1' : ''),
							'color' => 'BBDEFB',
							'functions' => [
								'calc_fnc' => 'min',
								'drawtype' => 'Bold line',
								'yaxisside' => 'Right'
							]
						]
					],
					'details' => ['Incorrect value for field "percent_left": a number is expected.']
				]
			],
			// #8.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Too large value for percent_right field'.($this->prototype ? ' {#KEY}' : NULL),
						'id:visible_percent_right' => true,
						'id:percent_right' => 101
					],
					'items' => [
						[
							'prototype' => $this->prototype,
							'item' => 'testFormItem'.($this->prototype ? 'Prototype1' : ''),
							'color' => 'BBDEFB',
							'functions' => [
								'calc_fnc' => 'min',
								'drawtype' => 'Bold line',
								'yaxisside' => 'Right'
							]
						]
					],
					'details' => [
						'Incorrect value for field "percent_right": value must be between "0" and "100", '.
							'and have no more than "4" digits after the decimal point.'
					]
				]
			],
			// #9.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Too large value for percent_right field'.($this->prototype ? ' {#KEY}' : NULL),
						'id:visible_percent_right' => true,
						'id:percent_right' => -101
					],
					'items' => [
						[
							'prototype' => $this->prototype,
							'item' => 'testFormItem'.($this->prototype ? 'Prototype1' : ''),
							'color' => 'BBDEFB',
							'functions' => [
								'calc_fnc' => 'min',
								'drawtype' => 'Bold line',
								'yaxisside' => 'Right'
							]
						]
					],
					'details' => [
						'Incorrect value for field "percent_right": value must be between "0" and "100", '.
							'and have no more than "4" digits after the decimal point.'
					]
				]
			],
			// #10.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Float value in percent_right field'.($this->prototype ? ' {#KEY}' : NULL),
						'id:visible_percent_right' => true,
						'id:percent_right' => '5,9'
					],
					'items' => [
						[
							'prototype' => $this->prototype,
							'item' => 'testFormItem'.($this->prototype ? 'Prototype1' : ''),
							'color' => 'BBDEFB',
							'functions' => [
								'calc_fnc' => 'min',
								'drawtype' => 'Bold line',
								'yaxisside' => 'Right'
							]
						]
					],
					'details' => ['Incorrect value for field "percent_right": a number is expected.']
				]
			],
			// #11.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Comma in "Y axis MIN value" field'.($this->prototype ? ' {#KEY}' : NULL),
						'id:ymin_type' => CFormElement::RELOADABLE_FILL('Fixed'),
						'id:yaxismin' => '88,9'
					],
					'items' => [
						[
							'prototype' => $this->prototype,
							'item' => 'testFormItem'.($this->prototype ? 'Prototype1' : ''),
							'color' => 'BBDEFB',
							'functions' => [
								'calc_fnc' => 'min',
								'drawtype' => 'Bold line',
								'yaxisside' => 'Right'
							]
						]
					],
					'details' => ['Incorrect value for field "yaxismin": a number is expected.']
				]
			],
			// #12.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'String in "Y axis MIN value" field'.($this->prototype ? ' {#KEY}' : NULL),
						'id:ymin_type' => CFormElement::RELOADABLE_FILL('Fixed'),
						'id:yaxismin' => 'text'
					],
					'items' => [
						[
							'prototype' => $this->prototype,
							'item' => 'testFormItem'.($this->prototype ? 'Prototype1' : ''),
							'color' => 'BBDEFB',
							'functions' => [
								'calc_fnc' => 'min',
								'drawtype' => 'Bold line',
								'yaxisside' => 'Right'
							]
						]
					],
					'details' => ['Incorrect value for field "yaxismin": a number is expected.']
				]
			],
			// #13.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Comma in "Y axis MAX value" field'.($this->prototype ? ' {#KEY}' : NULL),
						'id:ymax_type' => CFormElement::RELOADABLE_FILL('Fixed'),
						'id:yaxismax' => '88,9'
					],
					'items' => [
						[
							'prototype' => $this->prototype,
							'item' => 'testFormItem'.($this->prototype ? 'Prototype1' : ''),
							'color' => 'BBDEFB',
							'functions' => [
								'calc_fnc' => 'min',
								'drawtype' => 'Bold line',
								'yaxisside' => 'Right'
							]
						]
					],
					'details' => ['Incorrect value for field "yaxismax": a number is expected.']
				]
			],
			// #14.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'String in "Y axis MAX value" field'.($this->prototype ? ' {#KEY}' : NULL),
						'id:ymax_type' => CFormElement::RELOADABLE_FILL('Fixed'),
						'id:yaxismax' => 'value'
					],
					'items' => [
						[
							'prototype' => $this->prototype,
							'item' => 'testFormItem'.($this->prototype ? 'Prototype1' : ''),
							'color' => 'BBDEFB',
							'functions' => [
								'calc_fnc' => 'min',
								'drawtype' => 'Bold line',
								'yaxisside' => 'Right'
							]
						]
					],
					'details' => ['Incorrect value for field "yaxismax": a number is expected.']
				]
			],
			// #15.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Y_min is greater than Y_max'.($this->prototype ? ' {#KEY}' : NULL),
						'id:ymin_type' => CFormElement::RELOADABLE_FILL('Fixed'),
						'id:ymax_type' => CFormElement::RELOADABLE_FILL('Fixed'),
						'id:yaxismin' => 10,
						'id:yaxismax' => 9
					],
					'items' => [
						[
							'prototype' => $this->prototype,
							'item' => 'testFormItem'.($this->prototype ? 'Prototype1' : ''),
							'color' => 'BBDEFB',
							'functions' => [
								'calc_fnc' => 'min',
								'drawtype' => 'Bold line',
								'yaxisside' => 'Right'
							]
						]
					],
					'details' => ['Y axis MAX value must be greater than Y axis MIN value.']
				]
			],
			// #16.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Text in "Percentile line (left)" field'.($this->prototype ? ' {#KEY}' : NULL),
						'id:visible_percent_left' => true,
						'id:percent_left' => 'letters'
					],
					'items' => [
						[
							'prototype' => $this->prototype,
							'item' => 'testFormItem'.($this->prototype ? 'Prototype1' : ''),
							'color' => 'BBDEFB',
							'functions' => [
								'calc_fnc' => 'min',
								'drawtype' => 'Bold line',
								'yaxisside' => 'Right'
							]
						]
					],
					'details' => ['Incorrect value for field "percent_left": a number is expected.']
				]
			],
			// #17.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Text in "Percentile line (right)" field'.($this->prototype ? ' {#KEY}' : NULL),
						'id:visible_percent_right' => true,
						'id:percent_right' => 'letters'
					],
					'items' => [
						[
							'prototype' => $this->prototype,
							'item' => 'testFormItem'.($this->prototype ? 'Prototype1' : ''),
							'color' => 'BBDEFB',
							'functions' => [
								'calc_fnc' => 'min',
								'drawtype' => 'Bold line',
								'yaxisside' => 'Right'
							]
						]
					],
					'details' => ['Incorrect value for field "percent_right": a number is expected.']
				]
			],
			// #18.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty Y MIN and MAX items',
						'id:ymin_type' => CFormElement::RELOADABLE_FILL('Item'),
						'id:ymax_type' => CFormElement::RELOADABLE_FILL('Item')
					],
					'items' => [
						[
							'prototype' => $this->prototype,
							'item' => 'testFormItem'.($this->prototype ? 'Prototype1' : ''),
							'color' => 'BBDEFB',
							'functions' => [
								'calc_fnc' => 'min',
								'drawtype' => 'Bold line',
								'yaxisside' => 'Right'
							]
						]
					],
					'details' => [
						'Field "ymin_itemid" is mandatory.',
						'Field "ymax_itemid" is mandatory.'
					]
				]
			]
		];
	}

	public function checkGraphForm($data, $update = false) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::SQL);
		}

		$this->page->login()->open($this->url)->waitUntilReady();

		if ($update) {
			$this->query('link', self::$update_graph)->waitUntilClickable()->one()->click();
		}
		else {
			$this->query('button', 'Create graph'.$this->getGraphSuffix())->waitUntilClickable()->one()->click();
		}

		$dialog = COverlayDialogElement::find()->one();
		$form = $dialog->asForm();

		// Clear all items from graph to change them to new ones from data provider.
		if ($update) {
			$items_container = $form->getFieldContainer('Items');
			$items_count = $items_container->query('xpath:.//tr[contains(@id, "items_")]')->count();

			for ($i = 0; $i < $items_count; $i++) {
				// After each deletion item buttons reset their position, so upper items locator is always 0.
				$remove_button = $items_container->query('xpath:.//button[@id="items_0_remove"]')->one();
				$remove_button->waitUntilClickable()->click();
				$remove_button->waitUntilNotPresent();
			}
		}

		if ($update && !CTestArrayHelper::get($data, 'expected')) {
			$data['fields']['Name'] = $data['fields']['Name'].' update';
		}

		$form->fill($data['fields']);
		$items_container = $form->getFieldContainer('Items');

		// Fill Y axis Item values separately.
		if (array_key_exists('yaxis_items', $data)) {
			foreach ($data['yaxis_items'] as $y => $yaxis_item) {
				if ($this->prototype) {
					$form->query('xpath:.//button[@id="yaxis_'.$y.'_prototype"]')->waitUntilClickable()->one()->click();
					$yaxis_items_dialog = COverlayDialogElement::find()->all()->waitUntilReady()->last();
					$yaxis_items_dialog->query('link', $yaxis_item)->waitUntilClickable()->one()->click();
					$yaxis_items_dialog->waitUntilNotPresent();
				}
				else {
					$form->query('xpath:.//div[@id="y'.$y.'_itemid"]/..')->asMultiselect()->one()
							->setFillMode(CMultiselectElement::MODE_TYPE)->fill($yaxis_item);
				}
			}
		}

		// Add items or item prototypes to graph.
		if (array_key_exists('items', $data)) {
			foreach ($data['items'] as $i => $item) {
				$button = (CTestArrayHelper::get($item, 'prototype')) ? 'Add prototype' : 'Add';
				$items_container->query('button', $button)->waitUntilClickable()->one()->click();

				$item_dialog = COverlayDialogElement::find()->all()->waitUntilReady()->last();
				$item_dialog->query('link', $item['item'])->waitUntilClickable()->one()->click();

				// Check that added item link appeared.
				$item_row = $items_container->query('xpath:.//tr[@id="items_'.$i.'"]')->one()->waitUntilPresent();
				$this->assertTrue($item_row->query('link', self::HOST.': '.$item['item'])->one()->isClickable());

				// Add line styling functions.
				if (array_key_exists('functions', $item)) {
					foreach ($item['functions'] as $function => $value) {
						$item_row->query('xpath:.//z-select[@name="items['.$i.']['.$function.']"]')->asDropdown()->one()->fill($value);
					}
				}

				// Add line color.
				if (array_key_exists('color', $item)) {
					$item_row->query('xpath:.//z-color-picker')->asColorPicker()->one()->fill($item['color']);
				}
			}
		}

		// Take a screenshot to test draggable object position of items list.
		if (array_key_exists('screenshot', $data)) {
			$this->page->removeFocus();
			$this->assertScreenshot($this->query('id:items-table')->one(), 'Graph'.CTestArrayHelper::get($data['items'][0], 'prototype'));
		}

		$form->submit();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$error = $update
				? 'Cannot update graph'.$this->getGraphSuffix()
				: 'Cannot add graph'.$this->getGraphSuffix();

			$this->assertMessage(TEST_BAD, $error, $data['details']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
		}
		else {
			// Write new name to update graph for next case, but if it's last case return to initial name 'Graph for update'.
			if ($update) {
				self::$update_graph = $data['fields']['Name'];
			}

			$message = $update
				? 'Graph'.$this->getGraphSuffix().' updated'
				: 'Graph'.$this->getGraphSuffix().' added';

			$this->assertMessage(TEST_GOOD, $message);
			$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM graphs WHERE name='.
					zbx_dbstr($data['fields']['Name']))
			);

			// Open just created graph and check that all fields present correctly in form.
			$this->query('xpath://form[@name="graph_form"]/table')->asTable()->one()->waitUntilPresent()
					->query('link', $data['fields']['Name'])->waitUntilClickable()->one()->click();
			$form->invalidate();
			$form->checkValue($data['fields']);

			// Check Y axis Item values multiselects.
			if (array_key_exists('yaxis_items', $data)) {
				foreach ($data['yaxis_items'] as $y => $yaxis_item) {
					$this->assertEquals([self::HOST.': '.$yaxis_item], $form->query('xpath:.//div[@id="y'.$y.'_itemid"]/..')
							->asMultiselect()->one()->getValue()
					);
				}
			}

			// Check saved items count.
			$items_container = $form->getFieldContainer('Items');
			$this->assertEquals(count($data['items']),
					$items_container->query('xpath:.//tr[@class="graph-item"]')->all()->count()
			);

			// Check saved items names.
			foreach ($data['items'] as $i => $item) {
				$item_row = $items_container->query('xpath:.//tr[@id="items_'.$i.'"]')->one()->waitUntilPresent();
				$this->assertTrue($item_row->query('link', self::HOST.': '.$item['item'])->one()->isClickable());

				// Check lines styling functions.
				if (array_key_exists('functions', $item)) {
					foreach ($item['functions'] as $function => $value) {
						$this->assertEquals($value, $item_row->query('xpath:.//z-select[@name="items['.$i.']['.$function.']"]')
								->asDropdown()->one()->getValue()
						);
					}
				}

				// Check lines color.
				if (array_key_exists('color', $item)) {
					$this->assertEquals($item['color'], $item_row->query('xpath:.//z-color-picker')->asColorPicker()
							->one()->getValue()
					);
				}
			}
		}
		$dialog->close();
	}

	public static function getCloneData() {
		return [
			[
				[
					'fields' => [
						'Name' => 'New Cloned graph name with no changes'
					],
					'check_buttons' => true
				]
			],
			[
				[
					'fields' => [
						'Name' => 'New Cloned graph name with Items in Y axis',
						'Width' => 205,
						'Height' => 399,
						'Graph type' => CFormElement::RELOADABLE_FILL('Stacked'),
						'Show legend' => false,
						'Show working time' => false,
						'Show triggers' => false,
						'id:ymin_type' => CFormElement::RELOADABLE_FILL('Item'),
						'id:ymax_type' => CFormElement::RELOADABLE_FILL('Item')
					],
					'yaxis_items' => [
						'min' => 'Failed step of scenario "Scenario for Update".',
						'max' => 'Download speed for scenario "Scenario for Delete".'
					],
					'items' => [
						[
							'color' => 'B39DDB',
							'functions' => [
								'calc_fnc' => 'min',
								'yaxisside' => 'Right'
							]
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'New Cloned graph name with Fixed Y axis',
						'id:visible_percent_left' => true,
						'id:visible_percent_right' => true,
						'id:percent_left' => 3,
						'id:percent_right' => 20,
						'id:ymin_type' => CFormElement::RELOADABLE_FILL('Fixed'),
						'id:ymax_type' => CFormElement::RELOADABLE_FILL('Fixed'),
						'id:yaxismin' => 1,
						'id:yaxismax' => 99
					],
					'items' => [
						[
							'color' => '1B5E20',
							'functions' => [
								'calc_fnc' => 'max',
								'drawtype' => 'Bold line',
								'yaxisside' => 'Right'
							]
						]
					]
				]
			]
		];
	}

	public function checkClone($data) {
		$this->page->login()->open($this->url)->waitUntilReady();
		$name = 'Graph'.$this->getGraphSuffix().' for clone';
		$this->query('link', $name)->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$dialog->query('button:Clone')->waitUntilClickable()->one()->click();
		$dialog->waitUntilReady();
		$form = $dialog->asForm();

		if (CTestArrayHelper::get($data, 'check_buttons')) {
			foreach (['Update', 'Clone', 'Delete'] as $button) {
				$this->assertTrue($dialog->query('button', $button)->one(false)->isVisible(false));
			}
		}

		$form->fill($data['fields']);

		// Fill Y axis Item values separately because field is not real multiselect.
		if (array_key_exists('yaxis_items', $data)) {
			foreach ($data['yaxis_items'] as $y => $yaxis_item) {
				$form->query('xpath:.//div[@id="y'.$y.'_itemid"]/..')->asMultiselect()->one()
						->setFillMode(CMultiselectElement::MODE_TYPE)->fill($yaxis_item);
			}
		}

		$items_container = $form->getFieldContainer('Items');

		// Add items or item prototypes to graph.
		if (array_key_exists('items', $data)) {
			// Check that added item link appeared.
			$item_row = $items_container->query('xpath:.//tr[@id="items_0"]')->one()->waitUntilPresent();

			// Add line styling functions.
			foreach ($data['items'][0]['functions'] as $function => $value) {
				$item_row->query('xpath:.//z-select[@name="items[0]['.$function.']"]')->asDropdown()->one()->fill($value);
			}

			// Add line color.
			$item_row->query('xpath:.//z-color-picker')->asColorPicker()->one()->fill($data['items'][0]['color']);
		}

		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Graph'.$this->getGraphSuffix().' added');

		// Check that both original graph and clone exist in DB.
		foreach ([$name, $data['fields']['Name']] as $graph_name) {
			$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM graphs WHERE name='.zbx_dbstr($graph_name)));
		}

		$this->query('xpath://form[@name="graph_form"]/table')->asTable()->one()->waitUntilPresent()
				->query('link', $data['fields']['Name'])->waitUntilClickable()->one()->click();

		$form->invalidate();
		$form->checkValue($data['fields']);

		// Check Y axis Item values multiselects.
		if (array_key_exists('yaxis_items', $data)) {
			foreach ($data['yaxis_items'] as $y => $yaxis_item) {
				$this->assertEquals([self::HOST.': '.$yaxis_item], $form->query('xpath:.//div[@id="y'.$y.'_itemid"]/..')
						->asMultiselect()->one()->getValue()
				);
			}
		}

		if (array_key_exists('items', $data)) {
			$item_row = $form->getFieldContainer('Items')->query('xpath:.//tr[@id="items_0"]')->one()->waitUntilPresent();

			// Check lines styling functions.
			foreach ($data['items'][0]['functions'] as $function => $value) {
				$this->assertEquals($value, $item_row->query('xpath:.//z-select[@name="items[0]['.$function.']"]')
						->asDropdown()->one()->getValue()
				);
			}

			// Check lines color.
			$this->assertEquals($data['items'][0]['color'], $item_row->query('xpath:.//z-color-picker')->asColorPicker()
					->one()->getValue()
			);
		}
	}

	public static function getNoChangesData() {
		return [
			[
				[
					'case' => 'simple_update'
				]
			],
			[
				[
					'case' => 'Create'
				]
			],
			[
				[
					'case' => 'Update'
				]
			],
			[
				[
					'case' => 'Clone'
				]
			],
			[
				[
					'case' => 'Delete'
				]
			]
		];
	}

	/**
	 * Function for checking Cancel button in all actions, as well as update graph form without any changes.
	 */
	public function checkNoChanges($data) {
		$old_hash = CDBHelper::getHash(self::SQL);
		$this->page->login()->open($this->url)->waitUntilReady();

		if ($data['case'] === 'Create') {
			$this->query('button', 'Create graph'.$this->getGraphSuffix())->waitUntilClickable()->one()->click();
		}
		else {
			$this->query('link', self::$update_graph)->waitUntilClickable()->one()->click();
		}
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();

		if ($data['case'] === 'Clone' || $data['case'] === 'Delete') {
			$dialog->getFooter()->query('button', $data['case'])->waitUntilClickable()->one()->click();
		}

		if ($data['case'] === 'Delete') {
			$this->page->dismissAlert();
		}

		if ($data['case'] === 'simple_update') {
			$dialog->query('id', $this->formid)->waitUntilVisible()->asForm()->one()->submit();
			$this->assertMessage(TEST_GOOD, 'Graph'.$this->getGraphSuffix().' updated');
		}
		else {
			// After clicking the clone button the overlay dialog is reloaded again.
			if ($data['case'] === 'Clone') {
				$dialog->waitUntilReady();
			}
			$dialog->getFooter()->query('button:Cancel')->waitUntilClickable()->one()->click();
		}

		$this->assertTrue($this->query('button', 'Create graph'.$this->getGraphSuffix())->exists());

		// Check that DB hash is not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	/**
	 * Function for checking changes functions and color in existing item only.
	 */
	public function changeItemSettings($data) {
		$this->page->login()->open($this->url)->waitUntilReady();
		$this->query('link:Graph for items change')->waitUntilClickable()->one()->click();
		$item_number = $this->prototype ? 1 : 0;
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->query('id', $this->formid)->waitUntilVisible()->asForm()->one();
		$item_row = $form->getFieldContainer('Items')->query('xpath:.//tr[@id="items_'.$item_number.'"]')
				->one()->waitUntilPresent();

		// Change line styling functions.
		if (array_key_exists('functions', $data['change'])) {
			foreach ($data['change']['functions'] as $function => $value) {
				$item_row->query('xpath:.//z-select[@name="items['.$item_number.']['.$function.']"]')
					->asDropdown()->one()->fill($value);
			}
		}

		// Change line color.
		if (array_key_exists('color', $data['change'])) {
			$item_row->query('xpath:.//z-color-picker')->asColorPicker()->one()->fill($data['change']['color']);
		}

		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Graph'.$this->getGraphSuffix().' updated');
		$this->query('xpath://form[@name="graph_form"]/table')->asTable()->one()->waitUntilPresent()
				->query('link:Graph for items change')->waitUntilClickable()->one()->click();
		$item_row = $form->getFieldContainer('Items')->query('xpath:.//tr[@id="items_'.$item_number.'"]')
				->one()->waitUntilPresent();

		// Check lines styling functions.
		foreach ($data['expected']['functions'] as $function => $value) {
			$this->assertEquals($value, $item_row->query('xpath:.//z-select[@name="items['.$item_number.']['.$function.']"]')
					->asDropdown()->one()->getValue()
			);
		}

		$this->assertEquals($data['expected']['color'], $item_row->query('xpath:.//z-color-picker')->asColorPicker()
				->one()->getValue()
		);
	}

	public function checkDelete() {
		$this->page->login()->open($this->url)->waitUntilReady();
		$name = 'Graph'.$this->getGraphSuffix().' for delete';
		$this->query('link', $name)->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$dialog->query('button:Delete')->waitUntilClickable()->one()->click();
		$this->assertEquals('Delete graph'.$this->getGraphSuffix().'?', $this->page->getAlertText());
		$this->page->acceptAlert();
		$dialog->ensureNotPresent();
		$this->assertMessage(TEST_GOOD, 'Graph'.$this->getGraphSuffix().' deleted');
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM graphs WHERE name='.zbx_dbstr($name)));
	}

	/**
	 * Function for asserting that text, log and char items are not eligible for graph creating.
	 */
	public function checkTextItems($data) {
		$this->page->login()->open($this->url)->waitUntilReady();
		$this->query('button', 'Create graph'.$this->getGraphSuffix())->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->one();
		$form = $this->query('id', $this->formid)->waitUntilVisible()->asForm()->one();
		$form->fill($data['fields']);
		$items_container = $form->getFieldContainer('Items');

		// Assert that text items are not suggested in multiselect.
		foreach ($data['yaxis_items'] as $y => $yaxis_item) {
			if ($this->prototype) {
				$form->query('xpath:.//button[@id="yaxis_'.$y.'_prototype"]')->waitUntilClickable()->one()->click();
				$axis_item_dialog = COverlayDialogElement::find()->all()->waitUntilReady()->last();
				$this->assertFalse($axis_item_dialog->query('link', $yaxis_item)->exists());
				$axis_item_dialog->close();
			}
			else {
				$form->query('xpath:.//div[@id="y'.$y.'_itemid"]/..')->asMultiselect()->one()->query('tag:input')
						->one()->type($yaxis_item);
				$this->assertTrue($this->query('xpath://div[@class="multiselect-matches" and text()="No matches found"]')
						->waitUntilVisible()->one()->isVisible()
				);
			}
		}

		$items_container->query('button', 'Add'.$this->getGraphSuffix())->waitUntilClickable()->one()->click();
		$item_dialog = COverlayDialogElement::find()->all()->waitUntilReady()->last();

		// Assert that text items are not present in dialog.
		foreach ($data['items'] as $item) {
			$this->assertFalse($item_dialog->query('link', $item)->exists());
		}

		$item_dialog->close();
		$dialog->close();
	}

	public function clearData() {
		// Delete items.
		CDataHelper::call('item.delete', [
				self::$items['graph_trap_int']['itemid'],
				self::$items['graph_trap_float']['itemid'],
				self::$items['graph_trap_text']['itemid'],
				self::$itemid
		]);

		// Delete item prototypes.
		CDataHelper::call('itemprototype.delete', [
				self::$items['graph_prototype_trap_int']['itemid'],
				self::$items['graph_prototype_trap_float']['itemid'],
				self::$items['graph_prototype_trap_text']['itemid']
		]);
	}

	/**
	 * Function which checks that only permitted item types are accessible for graph and graph prototype creation.
	 *
	 * @param string    $url	url provided which needs to be opened
	 */
	public function checkAvailableItems($url) {
		$this->page->login()->open($url)->waitUntilReady();
		$dialog = COverlayDialogElement::find()->all()->waitUntilReady()->last();
		$form = $dialog->query('id', $this->formid)->waitUntilVisible()->asForm()->one();

		foreach (['MIN', 'MAX'] as $axis) {
			$form->fill(['Y axis '.$axis.' value' => 'Item']);
			$this->checkItemsInDialog($form, 'button:Select', self::HOST_WITH_ITEMS, $axis);

			if ($this->prototype) {
				$this->checkItemsInDialog($form, 'xpath://button[@id="add_item_prototype"]', self::HOST_WITH_ITEMS, $axis);
			}
		}

		$this->checkItemsInDialog($form, 'xpath://button[@id="add_item"]', self::HOST_WITH_ITEMS);

		if ($this->prototype) {
			$this->checkItemsInDialog($form, 'xpath://button[@id="add_item_prototype"]', self::HOST_WITH_ITEMS);
		}
		$dialog->close();
	}

	/**
	 * Function checks available item types in dialog form.
	 *
	 * @param CFormElement	$form		form element of widget
	 * @param string		$button		name of a button
	 * @param string		$host		name of a host
	 * @param string		$axis		axis which is being tested.
	 */
	protected function checkItemsInDialog($form, $button, $host, $axis = null) {
		if ($axis === null) {
			$form->query($button)->waitUntilClickable()->one()->click();
		}
		else {
			$form->getFieldContainer('Y axis '.$axis.' value')->query($button)->waitUntilClickable()->one()->click();
		}

		$dialog = COverlayDialogElement::find()->all()->waitUntilReady()->last();
		$table = $dialog->query('class:list-table')->one()->waitUntilVisible();

		if (!str_contains($button, 'add_item_prototype')) {
			$dialog->query('xpath:.//div[@class="multiselect-control"]')->asMultiselect()->one()->fill($host);
			$table->waitUntilReloaded();
			// Test fails on Jenkins when change host in multiselect - Rows count does not match results count in data provider.
			$no_data = $table->query('class:no-data-message')->one(false);
			if ($no_data->isValid()) {
				$no_data->waitUntilNotVisible();
			}
			$expected_items =['Float item', 'Unsigned item', 'Unsigned_dependent item'];
		}
		else {
			$expected_items = ['Float item prototype', 'Unsigned item prototype', 'Unsigned_dependent item prototype'];
		}

		$this->assertTableDataColumn($expected_items);
		$dialog->close();
	}
}

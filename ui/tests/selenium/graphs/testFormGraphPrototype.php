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


require_once __DIR__.'/../common/testFormGraphs.php';

/**
 * @backup graphs
 *
 * @onBefore prepareGraphPrototypesData
 *
 * @onAfter clearData
 *
 * @dataSource WebScenarios, AllItemValueTypes
 */
class testFormGraphPrototype extends testFormGraphs {

	protected $prototype = true;
	protected $url = 'graphs.php?parent_discoveryid='.self::LLDID.'&context=host';

	public function prepareGraphPrototypesData() {
		self::$update_graph = 'Graph for update';

		// Create item on given host.
		$items = CDataHelper::call('item.create', [
			'hostid' => self::HOSTID,
			'name' => 'item_graph_prototype_test',
			'key_' => 'item_graph_prototype_test',
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_FLOAT
		]);
		$this->assertArrayHasKey('itemids', $items);
		self::$itemid = $items['itemids'][0];

		// Create item prototypes on given host with given LLD.
		foreach (self::$items['item_prototypes'] as $name => $fields) {
			$item_prototypes_data[] = [
				'hostid' => self::HOSTID,
				'ruleid' => self::LLDID,
				'name' => $name,
				'key_' => $name.'[{#KEY}]',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => $fields['value_type']
			];
		}

		$item_prototypes = CDataHelper::call('itemprototype.create', $item_prototypes_data);
		$this->assertArrayHasKey('itemids', $item_prototypes);
		$item_prototype_ids = CDataHelper::getIds('name');

		foreach (['int', 'float', 'text', 'char'] as $suffix) {
			$field = 'graph_prototype_trap_'.$suffix;
			self::$items[$field]['itemid'] = $item_prototype_ids[$field];
		}

		// Create graphs with previously created items.
		$prepared_graph_prototypes = [
			[
				'name' => 'Graph for update',
				'itemid' => self::$items['graph_prototype_trap_int']['itemid']
			],
			[
				'name' => 'Duplicated graph prototype',
				'itemid' => self::$items['graph_prototype_trap_int']['itemid']
			],
			[
				'name' => 'Graph prototype for delete',
				'itemid' => self::$items['graph_prototype_trap_float']['itemid']
			],
			[
				'name' => 'Graph prototype for clone',
				'itemid' => self::$items['graph_prototype_trap_int']['itemid']
			],
			[
				'name' => 'Graph for items change',
				'itemid' => self::$items['graph_prototype_trap_int']['itemid']
			]
		];

		$graph_prototypes_data = [];
		foreach ($prepared_graph_prototypes as $graph_prototype) {
			$graph_prototypes_data[] = [
				'name' => $graph_prototype['name'],
				'width' => 999,
				'height' => 222,
				'gitems' => [
					[
						'itemid' => $graph_prototype['itemid'],
						'color'=> '5C6BC0'
					],
					[
						'itemid' => self::$itemid,
						'color'=> '66BB6A'
					]
				]
			];
		}

		CDataHelper::call('graphprototype.create', $graph_prototypes_data);
	}

	/**
	 * @dataProvider getLayoutData
	 */
	public function testFormGraphPrototype_Layout($data) {
		$this->checkGraphFormLayout($data);
	}

	public function getGraphPrototypeData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty item prototype {#KEY}'
					],
					'items' => [
						[
							'item' => 'testFormItem'
						]
					],
					'details' => [
						'Graph prototype "Empty item prototype {#KEY}" must have at least one item prototype.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty color in item prototype {#KEY}'
					],
					'items' => [
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype1',
							'color' => ''
						]
					],
					'details' => [
						'Empty colour.'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Normal graph prototype without LLD macro'
					],
					'items' => [
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype1'
						]
					],
					'screenshot' => true
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Duplicated graph prototype'
					],
					'items' => [
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype1'
						]
					],
					'details' => [
						'Graph prototype "Duplicated graph prototype" already exists on the LLD rule with '.
								'key "discovery-rule-form" of the host "Simple form test host".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Exploded graph prototype duplicated Graph sum type',
						'Graph type' => 'Exploded'
					],
					'items' => [
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype1',
							'color'=> 'AB47BC',
							'functions' => [
								'type' => 'Simple',
								'calc_fnc' => 'min'
							]
						],
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype2',
							'color'=> 'FFA000',
							'functions' => [
								'type' => 'Graph sum',
								'calc_fnc' => 'avg'
							]
						],
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype3',
							'color'=> 'FFA001',
							'functions' => [
								'type' => 'Graph sum',
								'calc_fnc' => 'last'
							]
						]
					],
					'details' => [
						'Cannot add more than one item with type "Graph sum" on graph prototype "Exploded graph prototype'.
								' duplicated Graph sum type".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Exploded graph prototype duplicated Graph sum type mixed',
						'Graph type' => 'Exploded'
					],
					'items' => [
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype1',
							'color'=> 'AB47BC',
							'functions' => [
								'type' => 'Graph sum',
								'calc_fnc' => 'min'
							]
						],
						[
							'item' => 'Response code for step "step 1 of scenario 1" of scenario "Template_Web_scenario".',
							'color'=> 'FFA000',
							'functions' => [
								'type' => 'Graph sum',
								'calc_fnc' => 'avg'
							]
						]
					],
					'details' => [
						'Cannot add more than one item with type "Graph sum" on graph prototype "Exploded graph prototype '.
								'duplicated Graph sum type mixed".'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Normal graph prototype with items in Y axis values {#KEY}',
						'Width' => 65535,
						'Height' => 65535,
						'Graph type' => CFormElement::RELOADABLE_FILL('Normal'),
						'Show legend' => false,
						'Show working time' => false,
						'Show triggers' => false,
						'id:visible_percent_left' => true,
						'id:visible_percent_right' => true,
						'id:percent_left' => 5.5,
						'id:percent_right' => 99.9,
						'id:ymin_type' => CFormElement::RELOADABLE_FILL('Item'),
						'id:ymax_type' => CFormElement::RELOADABLE_FILL('Item')
					],
					'yaxis_items' => [
						'min' => 'testFormItemPrototype1',
						'max' => 'testFormItemPrototype2'
					],
					'items' => [
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype1',
							'color'=> 'BBDEFB',
							'functions' => [
								'calc_fnc' => 'min',
								'drawtype' => 'Bold line',
								'yaxisside' => 'Right'
							]
						],
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype2',
							'color'=> 'FBC02D',
							'functions' => [
								'calc_fnc' => 'avg',
								'drawtype' => 'Filled region',
								'yaxisside' => 'Left'
							]
						],
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype3',
							'color'=> '7B1FA2',
							'functions' => [
								'calc_fnc' => 'max',
								'drawtype' => 'Dot',
								'yaxisside' => 'Right'
							]
						],
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype4',
							'color'=> 'FFE082',
							'functions' => [
								'calc_fnc' => 'avg',
								'drawtype' => 'Dashed line',
								'yaxisside' => 'Right'
							]
						],
						[
							'prototype' => true,
							'item' => 'testFormItemReuse',
							'color'=> '7F3700',
							'functions' => [
								'calc_fnc' => 'avg',
								'drawtype' => 'Gradient line',
								'yaxisside' => 'Left'
							]
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Stacked graph prototype fixed Y axis values',
						'Width' => 20,
						'Height' => 20,
						'Graph type' => CFormElement::RELOADABLE_FILL('Stacked'),
						'Show legend' => true,
						'Show working time' => true,
						'Show triggers' => true,
						'id:ymin_type' => CFormElement::RELOADABLE_FILL('Fixed'),
						'id:ymax_type' => CFormElement::RELOADABLE_FILL('Fixed'),
						'id:yaxismin' => 0.1,
						'id:yaxismax' => 0.99
					],
					'items' => [
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype1',
							'color'=> '00897B',
							'functions' => [
								'calc_fnc' => 'max',
								'yaxisside' => 'Right'
							]
						],
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype2',
							'color'=> 'C3C3C3',
							'functions' => [
								'calc_fnc' => 'avg',
								'yaxisside' => 'Left'
							]
						],
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype3',
							'color'=> '00897B',
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
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Max equals min value'.($this->prototype ? ' {#KEY}' : NULL),
						'id:ymin_type' => CFormElement::RELOADABLE_FILL('Fixed'),
						'id:ymax_type' => CFormElement::RELOADABLE_FILL('Fixed'),
						'id:yaxismin' => 0.1,
						'id:yaxismax' => 0.1
					],
					'items' => [
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype1'
						]
					],
					'details' => [
						'Y axis MAX value must be greater than Y axis MIN value.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Max less than min'.($this->prototype ? ' {#KEY}' : NULL),
						'id:ymin_type' => CFormElement::RELOADABLE_FILL('Fixed'),
						'id:ymax_type' => CFormElement::RELOADABLE_FILL('Fixed'),
						'id:yaxismin' => 0.2,
						'id:yaxismax' => 0.1
					],
					'items' => [
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype1'
						]
					],
					'details' => [
						'Y axis MAX value must be greater than Y axis MIN value.'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Pie graph prototype 3D false',
						'Graph type' => CFormElement::RELOADABLE_FILL('Pie'),
						'Show legend' => true,
						'3D view' => false
					],
					'items' => [
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype1',
							'color'=> 'D2D2D2',
							'functions' => [
								'type' => 'Simple',
								'calc_fnc' => 'min'
							]
						],
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype2',
							'color'=> 'C0CA33',
							'functions' => [
								'type' => 'Graph sum',
								'calc_fnc' => 'avg'
							]
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Pie graph 3D true',
						'Graph type' => CFormElement::RELOADABLE_FILL('Pie'),
						'Show legend' => false,
						'3D view' => true
					],
					'items' => [
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype3',
							'color'=> 'AB47BC',
							'functions' => [
								'type' => 'Simple',
								'calc_fnc' => 'max'
							]
						],
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype4',
							'color'=> 'FFA000',
							'functions' => [
								'type' => 'Graph sum',
								'calc_fnc' => 'last'
							]
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Exploded graph 3D true, mixed items',
						'Graph type' => CFormElement::RELOADABLE_FILL('Exploded'),
						'Show legend' => false,
						'3D view' => true
					],
					'items' => [
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype1',
							'color'=> 'AB47BC',
							'functions' => [
								'type' => 'Simple',
								'calc_fnc' => 'min'
							]
						],
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype2',
							'color'=> 'FFA000',
							'functions' => [
								'type' => 'Graph sum',
								'calc_fnc' => 'avg'
							]
						],
						[
							'item' => 'Failed step of scenario "Scenario for Update".',
							'color'=> 'AB47BC',
							'functions' => [
								'type' => 'Simple',
								'calc_fnc' => 'max'
							]
						],
						[
							'item' => 'Failed step of scenario "Scenario for Delete".',
							'color'=> 'FFA000',
							'functions' => [
								'type' => 'Simple',
								'calc_fnc' => 'last'
							]
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Exploded graph 3D false, mixed items',
						'Graph type' => CFormElement::RELOADABLE_FILL('Exploded'),
						'Show legend' => true,
						'3D view' => false
					],
					'items' => [
						[
							'item' => 'Response code for step "step 2 of clone scenario" of scenario "Scenario for Clone".',
							'color'=> 'AB47BC',
							'functions' => [
								'type' => 'Simple',
								'calc_fnc' => 'max'
							]
						],
						[
							'item' => 'Download speed for scenario "Scenario for Update".',
							'color'=> 'FFA000',
							'functions' => [
								'type' => 'Graph sum',
								'calc_fnc' => 'last'
							]
						],
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype1',
							'color'=> 'AB47BC',
							'functions' => [
								'type' => 'Simple',
								'calc_fnc' => 'min'
							]
						],
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype2',
							'color'=> 'FFA000',
							'functions' => [
								'type' => 'Simple',
								'calc_fnc' => 'avg'
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCommonGraphData
	 * @dataProvider getGraphPrototypeData
	 */
	public function testFormGraphPrototype_Create($data) {
		$this->checkGraphForm($data);
	}

	/**
	 * @dataProvider getCommonGraphData
	 * @dataProvider getGraphPrototypeData
	 */
	public function testFormGraphPrototype_Update($data) {
		$this->checkGraphForm($data, true);
	}

	/**
	 * @dataProvider getCloneData
	 */
	public function testFormGraphPrototype_Clone($data) {
		$this->checkClone($data);
	}

	/**
	 * @dataProvider getNoChangesData
	 */
	public function testFormGraphPrototype_NoChanges($data) {
		$this->checkNoChanges($data);
	}

	public static function getItemPrototypeSettingsData() {
		return [
			[
				[
					'change' => [
						'functions' => [
							'calc_fnc' => 'avg',
							'drawtype' => 'Gradient line',
							'yaxisside' => 'Right'
						]
					],
					'expected' => [
						'functions' => [
							'calc_fnc' => 'avg',
							'drawtype' => 'Gradient line',
							'yaxisside' => 'Right'
						],
						'color' => '66BB6A'
					]
				]
			],
			[
				[
					'change' => [
						'color' => 'FFBF00'
					],
					'expected' => [
						'functions' => [
							'calc_fnc' => 'avg',
							'drawtype' => 'Gradient line',
							'yaxisside' => 'Right'
						],
						'color' => 'FFBF00'
					]
				]
			],
			[
				[
					'change' => [
						'functions' => [
							'calc_fnc' => 'max',
							'drawtype' => 'Filled region',
							'yaxisside' => 'Left'
						],
						'color' => 'D7CCC8'
					],
					'expected' => [
						'functions' => [
							'calc_fnc' => 'max',
							'drawtype' => 'Filled region',
							'yaxisside' => 'Left'
						],
						'color' => 'D7CCC8'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getItemPrototypeSettingsData
	 */
	public function testFormGraphPrototype_ChangeItemSettings($data) {
		$this->changeItemSettings($data);
	}

	public function testFormGraphPrototype_Delete() {
		$this->checkDelete();
	}

	/**
	 * Test for checking that only permitted item types are accessible for graph prototype creation.
	 */
	public function testFormGraphPrototype_CheckAvailableItems() {
		$lldid = CDBHelper::getValue('SELECT itemid FROM items WHERE name='.zbx_dbstr(self::LLD_WITH_ITEMS));
		$url = 'graphs.php?form=create&parent_discoveryid='.$lldid.'&context=host';

		$this->checkAvailableItems($url);
	}

	public static function getTextItemPrototypesData() {
		return [
			[
				[
					'fields' => [
						'Name' => 'Graph prototype of text items',
						'id:ymin_type' => CFormElement::RELOADABLE_FILL('Item'),
						'id:ymax_type' => CFormElement::RELOADABLE_FILL('Item')
					],
					'yaxis_items' => [
						'min' => 'graph_prototype_trap_text',
						'max' => 'graph_prototype_trap_char'
					],
					'items' => [
						[
							'item' => 'graph_prototype_trap_text',
							'item' => 'graph_prototype_trap_char'
						]
					]
				]
			]
		];
	}

	public function testFormGraphPrototype_TextItems() {
		$data = [
			'fields' => [
				'Name' => 'Graph prototype of text items',
				'id:ymin_type' => CFormElement::RELOADABLE_FILL('Item'),
				'id:ymax_type' => CFormElement::RELOADABLE_FILL('Item')
			],
			'yaxis_items' => [
				'min' => 'graph_prototype_trap_text',
				'max' => 'graph_prototype_trap_char'
			],
			'items' => [
				[
					'item' => 'graph_prototype_trap_text',
					'item' => 'graph_prototype_trap_char'
				]
			]
		];

		$this->checkTextItems($data);
	}
}

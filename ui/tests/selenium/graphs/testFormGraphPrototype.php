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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../common/testFormGraphs.php';

/**
 * @backup graphs
 */
class testFormGraphPrototype extends testFormGraphs {

	CONST LLDID = 133800; // testFormDiscoveryRule on Simple form test host.

	public $prototype = true;
	public $url = 'graphs.php?parent_discoveryid='.self::LLDID.'&context=host';

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
					'items' =>[
						[
							'item' => 'testFormItem'
						]
					],
					'error' => 'Cannot add graph prototype',
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
					'items' =>[
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype1',
							'color' => ''
						]
					],
					'error' => 'Cannot add graph prototype',
					'details' => [
						'Empty color.'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Normal graph prototype without LLD macro'
					],
					'items' =>[
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype1'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Normal graph prototype without LLD macro'
					],
					'items' =>[
						[
							'prototype' => true,
							'item' => 'testFormItemPrototype1'
						]
					],
					'error' => 'Cannot add graph prototype',
					'details' => [
						'Graph with name "Normal graph prototype without LLD macro" already exists in graphs or graph prototypes.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Exploded graph prototype duplicated Graph sum type',
						'Graph type' => CFormElement::RELOADABLE_FILL('Exploded'),
					],
					'items' =>[
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
					'error' => 'Cannot add graph prototype',
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
						'Name' => 'Exploded graph protoype duplicated Graph sum type mixed',
						'Graph type' => CFormElement::RELOADABLE_FILL('Exploded'),
					],
					'items' =>[
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
							'item' => 'Response code for step "testFormWeb3" of scenario "testFormWeb3".',
							'color'=> 'FFA000',
							'functions' => [
								'type' => 'Graph sum',
								'calc_fnc' => 'avg'
							]
						]
					],
					'error' => 'Cannot add graph prototype',
					'details' => [
						'Cannot add more than one item with type "Graph sum" on graph prototype "Exploded graph protoype '.
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
						'id:ymax_type' => CFormElement::RELOADABLE_FILL('Item'),
					],
					'yaxis_items' => [
						'min' => 'testFormItemPrototype1',
						'max' => 'testFormItemPrototype2'
					],
					'items' =>[
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
					'items' =>[
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
					'fields' => [
						'Name' => 'Pie graph prototype 3D false',
						'Graph type' => CFormElement::RELOADABLE_FILL('Pie'),
						'Show legend' => true,
						'3D view' => false
					],
					'items' =>[
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
					'items' =>[
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
					'items' =>[
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
							'item' => 'Response code for step "testFormWeb3" of scenario "testFormWeb3".',
							'color'=> 'AB47BC',
							'functions' => [
								'type' => 'Simple',
								'calc_fnc' => 'max'
							]
						],
						[
							'item' => 'Response code for step "testFormWeb4" of scenario "testFormWeb4".',
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
					'items' =>[
						[
							'item' => 'Response code for step "testFormWeb3" of scenario "testFormWeb3".',
							'color'=> 'AB47BC',
							'functions' => [
								'type' => 'Simple',
								'calc_fnc' => 'max'
							]
						],
						[
							'item' => 'Response code for step "testFormWeb4" of scenario "testFormWeb4".',
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
}

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
class testFormGraph extends testFormGraphs {

	const HOSTID = 40001; // Simple form test host.

	public $url = 'graphs.php?filter_set=1&filter_hostids%5B0%5D='.self::HOSTID.'&context=host';

	/**
	 * @dataProvider getLayoutData
	 */
	public function testFormGraph_Layout($data) {
		$this->checkGraphLayout($data);
	}

	public function getGraphData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty color'
					],
					'items' =>[
						[
							'host' => 'Simple form test host',
							'item' => 'testFormItem',
							'color' => ''
						]
					],
					'error' => 'Cannot add graph',
					'details' => [
						'Empty color.'
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
					'items' =>[
						[
							'host' => 'Simple form test host',
							'item' => 'testFormItem'
						]
					],
					'error' => ($this->prototype) ? 'Cannot add graph prototype' : 'Cannot add graph',
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
					'items' =>[
						[
							'host' => 'Simple form test host',
							'item' => 'testFormItem'
						]
					],
					'error' => ($this->prototype) ? 'Cannot add graph prototype' : 'Cannot add graph',
					'details' => [
						'Y axis MAX value must be greater than Y axis MIN value.'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Normal graph'
					],
					'items' =>[
						[
							'host' => 'Simple form test host',
							'item' => 'testFormItem'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Normal graph'
					],
					'items' =>[
						[
							'host' => 'Simple form test host',
							'item' => 'testFormItem'
						]
					],
					'error' => 'Cannot add graph',
					'details' => [
						'Graph with name "Normal graph" already exists in graphs or graph prototypes.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Exploded graph duplicated Graph sum type',
						'Graph type' => CFormElement::RELOADABLE_FILL('Exploded'),
						'Show legend' => false,
						'3D view' => true
					],
					'items' =>[
						[
							'host' => 'Simple form test host',
							'item' => 'Response code for step "testFormWeb1" of scenario "testFormWeb1".',
							'color'=> 'AB47BC',
							'functions' => [
								'type' => 'Simple',
								'calc_fnc' => 'min'
							]
						],
						[
							'host' => 'Simple form test host',
							'item' => 'Response code for step "testFormWeb2" of scenario "testFormWeb2".',
							'color'=> 'FFA000',
							'functions' => [
								'type' => 'Graph sum',
								'calc_fnc' => 'avg'
							]
						],
						[
							'host' => 'Simple form test host',
							'item' => 'Response code for step "testFormWeb3" of scenario "testFormWeb3".',
							'color'=> 'FFA001',
							'functions' => [
								'type' => 'Graph sum',
								'calc_fnc' => 'last'
							]
						]
					],
					'error' => 'Cannot add graph',
					'details' => [
						'Cannot add more than one item with type "Graph sum" on graph "Exploded graph duplicated Graph sum type".'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Normal graph items in Y axis values',
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
						'min' => ['host' => 'Simple form test host', 'value' => 'Failed step of scenario "testFormWeb1".'],
						'max' => ['host' => 'Simple form test host', 'value' => 'Download speed for scenario "testFormWeb1".']
					],
					'items' =>[
						[
							'host' => 'Simple form test host',
							'item' => 'testFormItem',
							'color'=> 'BBDEFB',
							'functions' => [
								'calc_fnc' => 'min',
								'drawtype' => 'Bold line',
								'yaxisside' => 'Right'
							]
						],
						[
							'host' => 'Simple form test host',
							'item' => 'testFormItem2',
							'color'=> 'FBC02D',
							'functions' => [
								'calc_fnc' => 'avg',
								'drawtype' => 'Filled region',
								'yaxisside' => 'Left'
							]
						],
						[
							'host' => 'Simple form test host',
							'item' => 'testFormItem3',
							'color'=> '7B1FA2',
							'functions' => [
								'calc_fnc' => 'max',
								'drawtype' => 'Dot',
								'yaxisside' => 'Right'
							]
						],
						[
							'host' => 'Simple form test host',
							'item' => 'testFormItem3',
							'color'=> 'FFE082',
							'functions' => [
								'calc_fnc' => 'avg',
								'drawtype' => 'Dashed line',
								'yaxisside' => 'Right'
							]
						],
						[
							'host' => 'Simple form test host',
							'item' => 'testFormItem4',
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
						'Name' => 'Stacked graph fixed Y axis values',
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
							'host' => 'Simple form test host',
							'item' => 'testFormItem',
							'color'=> '00897B',
							'functions' => [
								'calc_fnc' => 'max',
								'yaxisside' => 'Right'
							]
						],
						[
							'host' => 'Simple form test host',
							'item' => 'testFormItem',
							'color'=> 'C3C3C3',
							'functions' => [
								'calc_fnc' => 'avg',
								'yaxisside' => 'Left'
							]
						],
						[
							'host' => 'Simple form test host',
							'item' => 'testFormItem',
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
						'Name' => 'Pie graph 3D false',
						'Graph type' => CFormElement::RELOADABLE_FILL('Pie'),
						'Show legend' => true,
						'3D view' => false
					],
					'items' =>[
						[
							'host' => 'Simple form test host',
							'item' => 'Failed step of scenario "testFormWeb1".',
							'color'=> 'D2D2D2',
							'functions' => [
								'type' => 'Simple',
								'calc_fnc' => 'min'
							]
						],
						[
							'host' => 'Simple form test host',
							'item' => 'Failed step of scenario "testFormWeb2".',
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
							'host' => 'Simple form test host',
							'item' => 'Failed step of scenario "testFormWeb3".',
							'color'=> 'AB47BC',
							'functions' => [
								'type' => 'Simple',
								'calc_fnc' => 'max'
							]
						],
						[
							'host' => 'Simple form test host',
							'item' => 'Failed step of scenario "testFormWeb4".',
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
						'Name' => 'Exploded graph 3D true',
						'Graph type' => CFormElement::RELOADABLE_FILL('Exploded'),
						'Show legend' => false,
						'3D view' => true
					],
					'items' =>[
						[
							'host' => 'Simple form test host',
							'item' => 'Response code for step "testFormWeb1" of scenario "testFormWeb1".',
							'color'=> 'AB47BC',
							'functions' => [
								'type' => 'Simple',
								'calc_fnc' => 'min'
							]
						],
						[
							'host' => 'Simple form test host',
							'item' => 'Response code for step "testFormWeb2" of scenario "testFormWeb2".',
							'color'=> 'FFA000',
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
						'Name' => 'Exploded graph 3D false',
						'Graph type' => CFormElement::RELOADABLE_FILL('Exploded'),
						'Show legend' => true,
						'3D view' => false
					],
					'items' =>[
						[
							'host' => 'Simple form test host',
							'item' => 'Response code for step "testFormWeb3" of scenario "testFormWeb3".',
							'color'=> 'AB47BC',
							'functions' => [
								'type' => 'Simple',
								'calc_fnc' => 'max'
							]
						],
						[
							'host' => 'Simple form test host',
							'item' => 'Response code for step "testFormWeb4" of scenario "testFormWeb4".',
							'color'=> 'FFA000',
							'functions' => [
								'type' => 'Graph sum',
								'calc_fnc' => 'last'
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCommonGraphData
	 * @dataProvider getGraphData
	 */
	public function testFormGraph_Create($data) {
		$this->checkGraphForm($data);
	}
}

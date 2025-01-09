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


class DynamicItemWidgets {
	public static function load() {
		// Create hostgroups for hosts.
		CDataHelper::call('hostgroup.create', [
			['name' => 'Dynamic widgets HG1 (H1 and H2)'],
			['name' => 'Dynamic widgets HG2 (H3)']
		]);
		$groupids = CDataHelper::getIds('name');

		// Create hosts with items.
		$hosts = CDataHelper::createHosts([
			[
				'host' => 'Dynamic widgets H1',
				'groups' => [
					'groupid' => $groupids['Dynamic widgets HG1 (H1 and H2)']
				],
				'items' => [
					[
						'name' => 'Dynamic widgets H1I1',
						'key_' => 'dynamic[1]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'Dynamic widgets H1I2',
						'key_' => 'dynamic[2]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					]
				]
			],
			[
				'host' => 'Dynamic widgets H2',
				'groups' => [
					'groupid' => $groupids['Dynamic widgets HG1 (H1 and H2)']
				],
				'items' => [
					[
						'name' => 'Dynamic widgets H2I1',
						'key_' => 'dynamic[1]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					]
				]
			],
			[
				'host' => 'Dynamic widgets H3',
				'groups' => [
					'groupid' => $groupids['Dynamic widgets HG2 (H3)']
				],
				'items' => [
					[
						'name' => 'Dynamic widgets H3I1',
						'key_' => 'dynamic[1]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					]
				]
			]
		]);
		$itemids = CDataHelper::getIds('name');

		// Create graphs.
		CDataHelper::call('graph.create', [
			[
				'name' => 'Dynamic widgets H1 G1 (I1)',
				'width' => 900,
				'height' => 200,
				'gitems' => [
					[
						'itemid' => $itemids['Dynamic widgets H1I1'],
						'color' => '009600'
					]
				]
			],
			[
				'name' => 'Dynamic widgets H1 G2 (I2)',
				'width' => 900,
				'height' => 200,
				'gitems' => [
					[
						'itemid' => $itemids['Dynamic widgets H1I2'],
						'color' => '009601'
					]
				]
			],
			[
				'name' => 'Dynamic widgets H1 G3 (I1 and I2)',
				'width' => 900,
				'height' => 200,
				'gitems' => [
					[
						'itemid' => $itemids['Dynamic widgets H1I1'],
						'color' => '009600'
					],
					[
						'itemid' => $itemids['Dynamic widgets H1I2'],
						'color' => '009600'
					]
				]
			],
			[
				'name' => 'Dynamic widgets H1 G4 (H1I1 and H3I1)',
				'width' => 900,
				'height' => 200,
				'gitems' => [
					[
						'itemid' => $itemids['Dynamic widgets H1I2'],
						'color' => '009600'
					],
					[
						'itemid' => $itemids['Dynamic widgets H3I1'],
						'color' => '009600'
					]
				]
			],
			[
				'name' => 'Dynamic widgets H2 G1 (I1)',
				'width' => 900,
				'height' => 200,
				'gitems' => [
					[
						'itemid' => $itemids['Dynamic widgets H2I1'],
						'color' => '009600'
					]
				]
			],
			[
				'name' => 'Dynamic widgets H3 G1 (I1)',
				'width' => 900,
				'height' => 200,
				'gitems' => [
					[
						'itemid' => $itemids['Dynamic widgets H3I1'],
						'color' => '009600'
					]
				]
			]
		]);
		$graphids = CDataHelper::getIds('name');

		// Create discovery rules.
		CDataHelper::call('discoveryrule.create', [
			[
				'hostid' => $hosts['hostids']['Dynamic widgets H1'],
				'name' => 'Dynamic widgets H1D1',
				'key_' => 'dynamic.lld[1]',
				'type' => ITEM_TYPE_TRAPPER
			],
			[
				'hostid' => $hosts['hostids']['Dynamic widgets H2'],
				'name' => 'Dynamic widgets H2D1',
				'key_' => 'dynamic.lld[1]',
				'type' => ITEM_TYPE_TRAPPER
			]
		]);
		$discoveryruleids = CDataHelper::getIds('name');

		// Create item prototypes.
		CDataHelper::call('itemprototype.create', [
			[
				'name' => 'Dynamic widgets H1IP1',
				'key_' => 'dynamic.ip1[{#ID}]',
				'hostid' => $hosts['hostids']['Dynamic widgets H1'],
				'ruleid' => $discoveryruleids['Dynamic widgets H1D1'],
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			[
				'name' => 'Dynamic widgets H1IP2',
				'key_' => 'dynamic.ip2[{#ID}]',
				'hostid' => $hosts['hostids']['Dynamic widgets H1'],
				'ruleid' => $discoveryruleids['Dynamic widgets H1D1'],
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			[
				'name' => 'Dynamic widgets H2IP1',
				'key_' => 'dynamic.ip1[{#ID}]',
				'hostid' => $hosts['hostids']['Dynamic widgets H2'],
				'ruleid' => $discoveryruleids['Dynamic widgets H2D1'],
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			]
		]);
		$prototype_itemids = CDataHelper::getIds('name');

		// Create graph prototypes.
		$prototype_graphids = CDataHelper::call('graphprototype.create', [
			[
				'name' => 'Dynamic widgets GP1 (IP1)',
				'width' => 900,
				'height' => 200,
				'gitems' => [
					[
						'itemid' => $prototype_itemids['Dynamic widgets H1IP1'],
						'color' => 'BF00FF'
					]
				]
			],
			[
				'name' => 'Dynamic widgets GP2 (I1, IP1, H1I2)',
				'width' => 900,
				'height' => 200,
				'gitems' => [
					[
						'itemid' => $prototype_itemids['Dynamic widgets H1IP1'],
						'color' => 'BF00FF'
					],
					[
						'itemid' => $itemids['Dynamic widgets H1I1'],
						'color' => 'BF00FF'
					],
					[
						'itemid' => $itemids['Dynamic widgets H1I2'],
						'color' => '009600'
					]
				]
			],
			[
				'name' => 'Dynamic widgets H1 GP3 (H1IP1)',
				'width' => 900,
				'height' => 200,
				'gitems' => [
					[
						'itemid' => $prototype_itemids['Dynamic widgets H1IP1'],
						'color' => 'BF00FF'
					]
				]
			],
			[
				'name' => 'Dynamic widgets H1 GP4 (H1IP1 and H2I1)',
				'width' => 900,
				'height' => 200,
				'gitems' => [
					[
						'itemid' => $prototype_itemids['Dynamic widgets H1IP1'],
						'color' => 'BF00FF'
					],
					[
						'itemid' => $itemids['Dynamic widgets H2I1'],
						'color' => 'BF00FF'
					]
				]
			],
			[
				'name' => 'Dynamic widgets GP1 (IP1)',
				'width' => 900,
				'height' => 200,
				'gitems' => [
					[
						'itemid' => $prototype_itemids['Dynamic widgets H2IP1'],
						'color' => 'BF00FF'
					]
				]
			],
			[
				'name' => 'Dynamic widgets GP2 (I1, IP1, H1I2)',
				'width' => 900,
				'height' => 200,
				'gitems' => [
					[
						'itemid' => $itemids['Dynamic widgets H2I1'],
						'color' => 'BF00FF'
					],
					[
						'itemid' => $prototype_itemids['Dynamic widgets H2IP1'],
						'color' => 'BF00FF'
					]
				]
			],
			[
				'name' => 'Dynamic widgets H2 GP3 (H2IP1)',
				'width' => 900,
				'height' => 200,
				'gitems' => [
					[
						'itemid' => $prototype_itemids['Dynamic widgets H2IP1'],
						'color' => 'BF00FF'
					]
				]
			]
		]);

		// Create dashboard with widgets.
		CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for Dynamic item',
				'pages' => [
					[
						'name' => 'Page with dynamic widgets',
						'widgets' => [
							[
								'type' => 'graph',
								'x' => 0,
								'y' => 0,
								'width' => 24,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source_type',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid',
										'value' => $itemids['Dynamic widgets H1I2']
									]
								]
							],
							[
								'type' => 'graph',
								'x' => 24,
								'y' => 0,
								'width' => 24,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source_type',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid',
										'value' =>  $itemids['Dynamic widgets H1I1']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'graph',
								'x' => 48,
								'y' => 0,
								'width' => 24,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source_type',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid',
										'value' => $itemids['Dynamic widgets H1I2']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'graph',
								'x' => 0,
								'y' => 3,
								'width' => 24,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid',
										'value' => $graphids['Dynamic widgets H1 G2 (I2)']
									]
								]
							],
							[
								'type' => 'graph',
								'x' => 24,
								'y' => 3,
								'width' => 24,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid',
										'value' => $graphids['Dynamic widgets H1 G1 (I1)']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'graph',
								'x' => 48,
								'y' => 3,
								'width' => 24,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid',
										'value' => $graphids['Dynamic widgets H1 G2 (I2)']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'graph',
								'x' => 0,
								'y' => 6,
								'width' => 24,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid',
										'value' => $graphids['Dynamic widgets H1 G3 (I1 and I2)']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'graph',
								'x' => 24,
								'y' => 6,
								'width' => 24,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid',
										'value' => $graphids['Dynamic widgets H1 G4 (H1I1 and H3I1)']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'gauge',
								'x' => 0,
								'y' => 9,
								'width' => 24,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid',
										'value' => $itemids['Dynamic widgets H1I2']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'gauge',
								'x' => 24,
								'y' => 9,
								'width' => 24,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid',
										'value' =>  $itemids['Dynamic widgets H1I1']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'url',
								'name' => 'Dynamic URL',
								'x' => 0,
								'y' => 12,
								'width' => 24,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'url',
										'value' => 'iframe.php?name={HOST.NAME}'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'graphprototype',
								'x' => 0,
								'y' => 15,
								'width' => 24,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source_type',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE,
										'name' => 'itemid',
										'value' => $prototype_itemids['Dynamic widgets H1IP2']
									]
								]
							],
							[
								'type' => 'graphprototype',
								'x' => 24,
								'y' => 15,
								'width' => 24,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source_type',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE,
										'name' => 'itemid',
										'value' => $prototype_itemids['Dynamic widgets H1IP1']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'graphprototype',
								'x' => 48,
								'y' => 15,
								'width' => 24,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source_type',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE,
										'name' => 'itemid',
										'value' => $prototype_itemids['Dynamic widgets H1IP2']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'graphprototype',
								'x' => 0,
								'y' => 18,
								'width' => 24,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE,
										'name' => 'graphid',
										'value' => $prototype_graphids['graphids'][0] // Dynamic widgets GP1 (IP1)
									]
								]
							],
							[
								'type' => 'graphprototype',
								'x' => 24,
								'y' => 18,
								'width' => 24,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE,
										'name' => 'graphid',
										'value' => $prototype_graphids['graphids'][0] // Dynamic widgets GP1 (IP1)
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'graphprototype',
								'x' => 48,
								'y' => 18,
								'width' => 24,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE,
										'name' => 'graphid',
										'value' => $prototype_graphids['graphids'][1] // Dynamic widgets GP2 (I1, IP1, H1I2)
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'graphprototype',
								'x' => 0,
								'y' => 21,
								'width' => 24,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE,
										'name' => 'graphid',
										'value' => $prototype_graphids['graphids'][2] // Dynamic widgets H1 GP3 (H1IP1)
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'graphprototype',
								'x' => 24,
								'y' => 21,
								'width' => 24,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE,
										'name' => 'graphid',
										'value' => $prototype_graphids['graphids'][3] // Dynamic widgets H1 GP4 (H1IP1 and H2I1)
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'itemhistory',
								'x' => 0,
								'y' => 24,
								'width' => 24,
								'height' => 3,
								'name' => 'Dynamic widgets H1I2 - without host override',
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.name',
										'value' => 'Dynamic widgets H1I2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show_lines',
										'value' => '1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.0.itemid',
										'value' => $itemids['Dynamic widgets H1I2']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'EDXAQ'
									]
								]
							],
							[
								'type' => 'itemhistory',
								'x' => 24,
								'y' => 24,
								'width' => 24,
								'height' => 3,
								'name' => 'Dynamic widgets H1I1 - with host override',
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.name',
										'value' => 'Dynamic widgets H1I1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show_lines',
										'value' => '1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.0.itemid',
										'value' =>  $itemids['Dynamic widgets H1I1']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'EDXAW'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'itemhistory',
								'x' => 48,
								'y' => 24,
								'width' => 24,
								'height' => 3,
								'name' => 'Dynamic widgets H1I2 - with host override',
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.name',
										'value' => 'Dynamic widgets H1I2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show_lines',
										'value' => '1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.0.itemid',
										'value' => $itemids['Dynamic widgets H1I2']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'EDXAE'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							],
							[
								'type' => 'itemhistory',
								'x' => 0,
								'y' => 27,
								'width' => 24,
								'height' => 3,
								'name' => 'Dynamic widgets H1I1 & Dynamic widgets H1I2 - with host override',
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.name',
										'value' => 'Dynamic widgets H1I1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'show_lines',
										'value' => '2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.0.itemid',
										'value' =>  $itemids['Dynamic widgets H1I1']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.1.name',
										'value' => 'Dynamic widgets H1I2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.1.itemid',
										'value' => $itemids['Dynamic widgets H1I2']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'EDXAR'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'override_hostid._reference',
										'value' => 'DASHBOARD._hostid'
									]
								]
							]
						]
					]
				]
			]
		]);
		$dashboardids = CDataHelper::getIds('name');

		return [
			'dashboardids' => $dashboardids,
			'itemids' => $itemids
		];
	}
}

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

class DataDisplayInGraphs {
	/**
	 * Create data for data display of graphs related tests.
	 *
	 * @return array
	 */
	public static function load() {
		$responce = CDataHelper::createHosts([
			[
				'host' => 'Host for data display on graphs',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => 1,
						'useip' => 1,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => 10051
					]
				],
				'groups' => [
					'groupid' => 4 // Host group "Zabbix servers".
				],
				'status' => HOST_STATUS_MONITORED,
				'items' => [
					[
						'name' => 'Item for history data display 1',
						'key_' => 'item_key_1',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_FLOAT,
						'delay' => '1m'
					],
					[
						'name' => 'Item for history data display 2',
						'key_' => 'item_key_2',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_FLOAT,
						'delay' => '1m'
					],
					[
						'name' => 'Item for trends data display 1',
						'key_' => 'item_key_3',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_FLOAT,
						'delay' => '2h'
					],
					[
						'name' => 'Item for trends data display 2',
						'key_' => 'item_key_4',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_FLOAT,
						'delay' => '2h'
					],
					[
						'name' => 'Item for pie chart 1',
						'key_' => 'item_key_5',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_FLOAT,
						'delay' => '1m'
					],
					[
						'name' => 'Item for pie chart2',
						'key_' => 'item_key_6',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_FLOAT,
						'delay' => '1m'
					]
				]
			]
		]);

		$hostid = $responce['hostids']['Host for data display on graphs'];
		$history1_itemid = $responce['itemids']['Host for data display on graphs:item_key_1'];
		$history2_itemid = $responce['itemids']['Host for data display on graphs:item_key_2'];
		$trends1_itemid = $responce['itemids']['Host for data display on graphs:item_key_3'];
		$trends2_itemid = $responce['itemids']['Host for data display on graphs:item_key_4'];
		$pie1_itemid = $responce['itemids']['Host for data display on graphs:item_key_5'];
		$pie2_itemid = $responce['itemids']['Host for data display on graphs:item_key_6'];

		CDataHelper::call('graph.create', [
			[
				'name' => 'History graph 1',
				'width' => 800,
				'height' => 250,
				'gitems' => [
					[
						'itemid' => $history1_itemid,
						'color' => 'FF0000'
					]
				]
			],
			[
				'name' => 'History graph 2 - stacked',
				'width' => 950,
				'height' => 300,
				'yaxismin' => -5,
				'yaxismax' => 20,
				'show_work_period' => 0,
				'show_triggers' => 0,
				'graphtype' => GRAPH_TYPE_STACKED,
				'show_legend' => 0,
				'ymin_type' => 1,
				'ymax_type' => 1,
				'gitems' => [
					[
						'itemid' => $history2_itemid,
						'color' => '1A7C11',
						'calc_fnc' => 2,
						'yaxisside' => 1
					]
				]
			],
			[
				'name' => 'History graph 1 + 2',
				'width' => 1000,
				'height' => 400,
				'gitems' => [
					[
						'itemid' => $history1_itemid,
						'color' => 'FF0000'
					],
					[
						'itemid' => $history2_itemid,
						'color' => '1A7C11',
						'calc_fnc' => 2, // Should not influence the graph since only history data will be displayed.
						'yaxisside' => 1
					]
				]
			],
			[
				'name' => 'History graph 1 + 2 - stacked',
				'width' => 1000,
				'height' => 400,
				'graphtype' => GRAPH_TYPE_STACKED,
				'gitems' => [
					[
						'itemid' => $history1_itemid,
						'color' => 'FF0000'
					],
					[
						'itemid' => $history2_itemid,
						'color' => '1A7C11'
					]
				]
			],
			[
				'name' => 'Trends graph 1',
				'width' => 800,
				'height' => 250,
				'gitems' => [
					[
						'itemid' => $trends1_itemid,
						'color' => 'FF0000',
						'calc_fnc' => 7
					]
				]
			],
			[
				'name' => 'Thrends graph 2',
				'width' => 950,
				'height' => 300,
				'yaxismin' => -5,
				'yaxismax' => 20,
				'show_work_period' => 0,
				'show_triggers' => 0,
				'graphtype' => GRAPH_TYPE_STACKED,
				'show_legend' => 0,
				'ymin_type' => 1,
				'ymax_type' => 1,
				'gitems' => [
					[
						'itemid' => $trends2_itemid,
						'color' => '1A7C11',
						'calc_fnc' => 7,
						'yaxisside' => 1
					]
				]
			],
			[
				'name' => 'All: Trends graph 1 + 2',
				'width' => 1000,
				'height' => 400,
				'gitems' => [
					[
						'itemid' => $trends1_itemid,
						'color' => 'FF0000',
						'calc_fnc' => 7
					],
					[
						'itemid' => $trends2_itemid,
						'color' => '1A7C11',
						'calc_fnc' => 7,
						'yaxisside' => 1
					]
				]
			],
			[
				'name' => 'Min: Trends graph 1 + 2',
				'width' => 1000,
				'height' => 400,
				'gitems' => [
					[
						'itemid' => $trends1_itemid,
						'color' => 'FF0000',
						'calc_fnc' => 1
					],
					[
						'itemid' => $trends2_itemid,
						'color' => '1A7C11',
						'calc_fnc' => 1,
						'yaxisside' => 1
					]
				]
			],
			[
				'name' => 'Avg: Trends graph 1 + 2',
				'width' => 1000,
				'height' => 400,
				'gitems' => [
					[
						'itemid' => $trends1_itemid,
						'color' => 'FF0000',
						'calc_fnc' => 2
					],
					[
						'itemid' => $trends2_itemid,
						'color' => '1A7C11',
						'calc_fnc' => 2,
						'yaxisside' => 1
					]
				]
			],
			[
				'name' => 'Max: Trends graph 1 + 2',
				'width' => 1000,
				'height' => 400,
				'gitems' => [
					[
						'itemid' => $trends1_itemid,
						'color' => 'FF0000',
						'calc_fnc' => 4
					],
					[
						'itemid' => $trends2_itemid,
						'color' => '1A7C11',
						'calc_fnc' => 4,
						'yaxisside' => 1
					]
				]
			],
			[
				'name' => 'Pie chart - flat view',
				'graphtype' => GRAPH_TYPE_PIE,
				'width' => 400,
				'height' => 300,
				'gitems' => [
					[
						'itemid' => $pie1_itemid,
						'color' => '00FF00'
					],
					[
						'itemid' => $pie2_itemid,
						'color' => 'FF0000'
					]
				]
			],
			[
				'name' => 'Pie chart - 3D view',
				'graphtype' => GRAPH_TYPE_PIE,
				'width' => 400,
				'height' => 300,
				'show_3d' => 1,
				'gitems' => [
					[
						'itemid' => $pie1_itemid,
						'color' => '00FF00'
					],
					[
						'itemid' => $pie2_itemid,
						'color' => 'FF0000'
					]
				]
			],
			[
				'name' => 'Exploded chart - flat view',
				'graphtype' => GRAPH_TYPE_EXPLODED,
				'width' => 400,
				'height' => 300,
				'gitems' => [
					[
						'itemid' => $pie1_itemid,
						'color' => '00FF00'
					],
					[
						'itemid' => $pie2_itemid,
						'color' => 'FF0000'
					]
				]
			],
			[
				'name' => 'Exploded chart - 3D view',
				'graphtype' => GRAPH_TYPE_EXPLODED,
				'width' => 400,
				'height' => 300,
				'show_3d' => 1,
				'gitems' => [
					[
						'itemid' => $pie1_itemid,
						'color' => '00FF00'
					],
					[
						'itemid' => $pie2_itemid,
						'color' => 'FF0000'
					]
				]
			]
		]);

		// This timestamp represents the changing point between trends and history data.
		$timestamp = '07-08-2023 12:00 EET';

		$item_data = [
			// Trend data for first trend item.
			[
				'itemid' => $trends1_itemid,
				'timestamps' => [
					$timestamp.' - 5 days + 18 hours',
					$timestamp.' - 4 days',
					$timestamp.' - 4 days + 6 hours',
					$timestamp.' - 4 days + 12 hours',
					$timestamp.' - 4 days + 18 hours',
					$timestamp.' - 3 days',
					$timestamp.' - 3 days + 3 hours',
					$timestamp.' - 3 days + 6 hours',
					$timestamp.' - 3 days + 9 hours',
					$timestamp.' - 3 days + 12 hours',
					$timestamp.' - 3 days + 21 hours',
					$timestamp.' - 2 days',
					$timestamp.' - 2 days + 21 hours',
					$timestamp.' - 1 day',
					$timestamp.' - 1 day + 3 hours',
					$timestamp.' - 1 day + 6 hours',
					$timestamp.' - 1 day + 12 hours',
					$timestamp.' - 1 day + 15 hours'
				],
				'values' => [
					[
						'num' => 3,
						'avg' => 6.03,
						'min' => -4.1,
						'max' => 13.57
					],
					[
						'num' => 3,
						'avg' => 4.73,
						'min' => -0.74,
						'max' => 9.01
					],
					[
						'num' => 3,
						'avg' => 8.23,
						'min' => 3.7,
						'max' => 15.3
					],
					[
						'num' => 3,
						'avg' => 2.95,
						'min' => 0.1,
						'max' => 5.2
					],
					[
						'num' => 3,
						'avg' => 6.66,
						'min' => 1.19,
						'max' => 12.2
					],
					[
						'num' => 3,
						'avg' => 7.07,
						'min' => 2.22,
						'max' => 13.38
					],
					[
						'num' => 3,
						'avg' => 5.19,
						'min' => 2.41,
						'max' => 9.62
					],
					[
						'num' => 3,
						'avg' => 8.42,
						'min' => 3.37,
						'max' => 11.02
					],
					[
						'num' => 3,
						'avg' => 5.75,
						'min' => 2.13,
						'max' => 13.72
					],
					[
						'num' => 3,
						'avg' => 8,
						'min' => 4.32,
						'max' => 11.7
					],
					[
						'num' => 3,
						'avg' => 4.3,
						'min' => 0.97,
						'max' => 8.73
					],
					[
						'num' => 3,
						'avg' => 6.9,
						'min' => 2.81,
						'max' => 12.1
					],
					[
						'num' => 3,
						'avg' => 7.12,
						'min' => 4.04,
						'max' => 11.26
					],
					[
						'num' => 3,
						'avg' => 5.05,
						'min' => 1.92,
						'max' => 13.01
					],
					[
						'num' => 3,
						'avg' => 3.83,
						'min' => -1.13,
						'max' => 8.15
					],
					[
						'num' => 3,
						'avg' => 9.23,
						'min' => -3.7,
						'max' => 21.13
					],
					[
						'num' => 3,
						'avg' => 4.95,
						'min' => 2.1,
						'max' => 7.2
					],
					[
						'num' => 3,
						'avg' => 6.5,
						'min' => 0.1,
						'max' => 15.21
					]
				]
			],
			// History data for first history item.
			[
				'itemid' => $history1_itemid,
				'timestamps' => [
					$timestamp,
					$timestamp.' + 1 minute',
					$timestamp.' + 2 minute',
					$timestamp.' + 3 minute',
					$timestamp.' + 6 minute',
					$timestamp.' + 7 minute',
					$timestamp.' + 8 minute',
					$timestamp.' + 13 minute',
					$timestamp.' + 14 minute',
					$timestamp.' + 15 minute',
					$timestamp.' + 16 minute',
					$timestamp.' + 17 minute',
					$timestamp.' + 18 minute',
				],
				'values' => [10.5, 8.33, 12.69, 4.025, 3.1, 7, 6.66, -1.5, 0.31, 6.47, 2.11, 8.98, 10.01]
			],
			// Trends data for second trend item.
			[
				'itemid' => $trends2_itemid,
				'timestamps' => [
					$timestamp.' - 5 days + 18 hours',
					$timestamp.' - 4 days',
					$timestamp.' - 4 days + 6 hours',
					$timestamp.' - 4 days + 12 hours',
					$timestamp.' - 4 days + 18 hours',
					$timestamp.' - 3 days',
					$timestamp.' - 3 days + 6 hours',
					$timestamp.' - 2 days + 6 hours',
					$timestamp.' - 2 days + 12 hours',
					$timestamp.' - 2 days + 18 hours',
					$timestamp.' - 1 day + 6 hours',
					$timestamp.' - 1 day + 12 hours',
					$timestamp.' - 1 day + 18 hours'
				],
				'values' => [
					[
						'num' => 59,
						'avg' => 9.07,
						'min' => 4.54,
						'max' => 13.81
					],
					[
						'num' => 59,
						'avg' => 3.62,
						'min' => -2.91,
						'max' => 7.77
					],
					[
						'num' => 59,
						'avg' => 6.99,
						'min' => 0.01,
						'max' => 15.45
					],
					[
						'num' => 48,
						'avg' => 4.31,
						'min' => -2.56,
						'max' => 10.1
					],
					[
						'num' => 51,
						'avg' => 5.12,
						'min' => 1.45,
						'max' => 9.23
					],
					[
						'num' => 59,
						'avg' => 8.31,
						'min' => -1.05,
						'max' => 17.26
					],
					[
						'num' => 59,
						'avg' => 7.71,
						'min' => 2.19,
						'max' => 11.11
					],
					[
						'num' => 35,
						'avg' => 4.96,
						'min' => -4.41,
						'max' => 13.35
					],
					[
						'num' => 52,
						'avg' => 9.1,
						'min' => 3.01,
						'max' => 14.45
					],
					[
						'num' => 59,
						'avg' => 6,
						'min' => 5.5,
						'max' => 6.5
					],
					[
						'num' => 59,
						'avg' => 8.21,
						'min' => -4.1,
						'max' => 12.31
					],
					[
						'num' => 59,
						'avg' => 4.74,
						'min' => 0.05,
						'max' => 9.26
					],
					[
						'num' => 59,
						'avg' => 7.12,
						'min' => 1.55,
						'max' => 10.42
					]
				]
			],
			// History data for second history item.
			[
				'itemid' => $history2_itemid,
				'timestamps' => [
					$timestamp,
					$timestamp.' + 1 minute',
					$timestamp.' + 2 minute',
					$timestamp.' + 3 minute',
					$timestamp.' + 4 minute',
					$timestamp.' + 5 minute',
					$timestamp.' + 6 minute',
					$timestamp.' + 11 minute',
					$timestamp.' + 12 minute',
					$timestamp.' + 13 minute',
					$timestamp.' + 16 minute',
					$timestamp.' + 17 minute',
					$timestamp.' + 18 minute',
				],
				'values' => [4.31, -0.5, 7.53, 2.37, 5.55, 7.77, 9.11, 10.34, 2.23, 5.98, -3.21, 2.45, 5.1]
			]
		];

		// Add history and trend values to item displayed in graphs and widgets.
		foreach ($item_data as $data) {
			foreach ($data['timestamps'] as &$timestamp) {
				$timestamp = strtotime($timestamp);
			}

			unset($timestamp);
			CDataHelper::addItemData($data['itemid'], $data['values'], $data['timestamps']);
		}

		CDataHelper::addItemData($pie1_itemid, 0.66);
		CDataHelper::addItemData($pie2_itemid, 0.34);

		// Create dashboards for Top host widget testing.
		$dashboard_responce = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard to check data display in graphs',
				'display_period' => 60,
				'auto_start' => 1,
				'pages' => [
					[
						'name' => 'Host graphs',
						'widgets' => [
							[
								'type' => 'graph',
								'name' => '',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 6,
										'name' => 'graphid',
										'value' => 2000001
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 12,
								'y' => 0,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 6,
										'name' => 'graphid',
										'value' => 2000002
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 0,
								'y' => 4,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 6,
										'name' => 'graphid',
										'value' => 2000003
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 12,
								'y' => 4,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 6,
										'name' => 'graphid',
										'value' => 2000004
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 0,
								'y' => 8,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 6,
										'name' => 'graphid',
										'value' => 2000005
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 12,
								'y' => 8,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 6,
										'name' => 'graphid',
										'value' => 2000006
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 0,
								'y' => 12,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 6,
										'name' => 'graphid',
										'value' => 2000007
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 12,
								'y' => 12,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 6,
										'name' => 'graphid',
										'value' => 2000008
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 0,
								'y' => 16,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 6,
										'name' => 'graphid',
										'value' => 2000009
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 12,
								'y' => 16,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 6,
										'name' => 'graphid',
										'value' => 2000010
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 0,
								'y' => 20,
								'width' => 6,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 6,
										'name' => 'graphid',
										'value' => 2000011
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 6,
								'y' => 20,
								'width' => 6,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 6,
										'name' => 'graphid',
										'value' => 2000012
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 12,
								'y' => 20,
								'width' => 6,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 6,
										'name' => 'graphid',
										'value' => 2000013
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 18,
								'y' => 20,
								'width' => 6,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 6,
										'name' => 'graphid',
										'value' => 2000014
									]
								]
							]
						]
					],
					[
						'name' => 'Simple graphs',
						'widgets' => [
							[
								'type' => 'graph',
								'name' => '',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'source_type',
										'value' => 1
									],
									[
										'type' => 4,
										'name' => 'itemid',
										'value' => $history1_itemid
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 12,
								'y' => 0,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 0,
										'name' => 'source_type',
										'value' => 1
									],
									[
										'type' => 4,
										'name' => 'itemid',
										'value' => $trends1_itemid
									]
								]
							]
						]
					],
					[
						'name' => 'SVG graph types',
						'widgets' => [
							[
								'type' => 'svggraph',
								'name' => 'SVG Graph history draw = Line',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'ds.hosts.0.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.0.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => 1,
										'name' => 'ds.hosts.1.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.1.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => 1,
										'name' => 'ds.color.1',
										'value' => '0080FF'
									],
									[
										'type' => 0,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'time_from',
										'value' => '2023-08-07 12:59:00'
									],
									[
										'type' => 1,
										'name' => 'time_to',
										'value' => '2023-08-07 13:19:00'
									],
									[
										'type' => 0,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'SVG Graph history draw = Points',
								'x' => 12,
								'y' => 0,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'ds.hosts.0.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.0.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => 0,
										'name' => 'ds.type.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'ds.hosts.1.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.1.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => 0,
										'name' => 'ds.type.1',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'ds.color.1',
										'value' => '0080FF'
									],
									[
										'type' => 0,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'time_from',
										'value' => '2023-08-07 12:59:00'
									],
									[
										'type' => 1,
										'name' => 'time_to',
										'value' => '2023-08-07 13:19:00'
									],
									[
										'type' => 0,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'SVG Graph history draw = Staircase',
								'x' => 0,
								'y' => 4,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'ds.hosts.0.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.0.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => 0,
										'name' => 'ds.type.0',
										'value' => 2
									],
									[
										'type' => 1,
										'name' => 'ds.hosts.1.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.1.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => 0,
										'name' => 'ds.type.1',
										'value' => 2
									],
									[
										'type' => 1,
										'name' => 'ds.color.1',
										'value' => '0080FF'
									],
									[
										'type' => 0,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'time_from',
										'value' => '2023-08-07 12:59:00'
									],
									[
										'type' => 1,
										'name' => 'time_to',
										'value' => '2023-08-07 13:19:00'
									],
									[
										'type' => 0,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'SVG Graph history draw = Bar',
								'x' => 12,
								'y' => 4,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'ds.hosts.0.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.0.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => 0,
										'name' => 'ds.type.0',
										'value' => 3
									],
									[
										'type' => 1,
										'name' => 'ds.hosts.1.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.1.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => 0,
										'name' => 'ds.type.1',
										'value' => 3
									],
									[
										'type' => 1,
										'name' => 'ds.color.1',
										'value' => '0080FF'
									],
									[
										'type' => 0,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'time_from',
										'value' => '2023-08-07 12:59:00'
									],
									[
										'type' => 1,
										'name' => 'time_to',
										'value' => '2023-08-07 13:19:00'
									],
									[
										'type' => 0,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'SVG Graph trends draw = Line',
								'x' => 0,
								'y' => 8,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'ds.hosts.0.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.0.0',
										'value' => 'Item for trend data display 1'
									],
									[
										'type' => 1,
										'name' => 'ds.color.0',
										'value' => 'BF00FF'
									],
									[
										'type' => 1,
										'name' => 'ds.hosts.1.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.1.0',
										'value' => 'Item for trend data display 2'
									],
									[
										'type' => 1,
										'name' => 'ds.color.1',
										'value' => '009688'
									],
									[
										'type' => 0,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'time_from',
										'value' => '2023-08-03 00:00:00'
									],
									[
										'type' => 1,
										'name' => 'time_to',
										'value' => '2023-08-07 09:00:00'
									],
									[
										'type' => 0,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'SVG Graph trends draw = Points',
								'x' => 12,
								'y' => 8,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'ds.hosts.0.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.0.0',
										'value' => 'Item for trend data display 1'
									],
									[
										'type' => 1,
										'name' => 'ds.color.0',
										'value' => 'BF00FF'
									],
									[
										'type' => 0,
										'name' => 'ds.type.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'ds.hosts.1.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.1.0',
										'value' => 'Item for trend data display 2'
									],
									[
										'type' => 1,
										'name' => 'ds.color.1',
										'value' => '009688'
									],
									[
										'type' => 0,
										'name' => 'ds.type.1',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'time_from',
										'value' => '2023-08-03 00:00:00'
									],
									[
										'type' => 1,
										'name' => 'time_to',
										'value' => '2023-08-07 09:00:00'
									],
									[
										'type' => 0,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'SVG Graph trends draw = Staircase',
								'x' => 0,
								'y' => 12,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'ds.hosts.0.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.0.0',
										'value' => 'Item for trend data display 1'
									],
									[
										'type' => 1,
										'name' => 'ds.color.0',
										'value' => 'BF00FF'
									],
									[
										'type' => 0,
										'name' => 'ds.type.0',
										'value' => 2
									],
									[
										'type' => 1,
										'name' => 'ds.hosts.1.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.1.0',
										'value' => 'Item for trend data display 2'
									],
									[
										'type' => 1,
										'name' => 'ds.color.1',
										'value' => '009688'
									],
									[
										'type' => 0,
										'name' => 'ds.type.1',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'time_from',
										'value' => '2023-08-03 00:00:00'
									],
									[
										'type' => 1,
										'name' => 'time_to',
										'value' => '2023-08-07 09:00:00'
									],
									[
										'type' => 0,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'SVG Graph trends draw = Bar',
								'x' => 12,
								'y' => 12,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'ds.hosts.0.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.0.0',
										'value' => 'Item for trend data display 1'
									],
									[
										'type' => 1,
										'name' => 'ds.color.0',
										'value' => 'BF00FF'
									],
									[
										'type' => 0,
										'name' => 'ds.type.0',
										'value' => 3
									],
									[
										'type' => 1,
										'name' => 'ds.hosts.1.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.1.0',
										'value' => 'Item for trend data display 2'
									],
									[
										'type' => 1,
										'name' => 'ds.color.1',
										'value' => '009688'
									],
									[
										'type' => 0,
										'name' => 'ds.type.1',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'time_from',
										'value' => '2023-08-03 00:00:00'
									],
									[
										'type' => 1,
										'name' => 'time_to',
										'value' => '2023-08-07 09:00:00'
									],
									[
										'type' => 0,
										'name' => 'righty',
										'value' => 0
									]
								]
							]
						]
					],
					[
						'name' => 'Missing data + axes + timeshift + agg func',
						'widgets' => [
							[
								'type' => 'svggraph',
								'name' => 'history, missing data = connected',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'ds.hosts.0.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.0.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => 1,
										'name' => 'ds.hosts.1.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.1.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => 0,
										'name' => 'ds.missingdatafunc.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'ds.color.1',
										'value' => 'B0AF07'
									],
									[
										'type' => 0,
										'name' => 'ds.missingdatafunc.1',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'time_from',
										'value' => '2023-08-07 12:59:00'
									],
									[
										'type' => 1,
										'name' => 'time_to',
										'value' => '2023-08-07 13:19:00'
									],
									[
										'type' => 0,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'history, missing data = treat as 0',
								'x' => 12,
								'y' => 0,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'ds.hosts.0.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.0.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => 1,
										'name' => 'ds.hosts.1.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.1.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => 0,
										'name' => 'ds.missingdatafunc.0',
										'value' => 2
									],
									[
										'type' => 1,
										'name' => 'ds.color.1',
										'value' => 'B0AF07'
									],
									[
										'type' => 0,
										'name' => 'ds.missingdatafunc.1',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'time_from',
										'value' => '2023-08-07 12:59:00'
									],
									[
										'type' => 1,
										'name' => 'time_to',
										'value' => '2023-08-07 13:19:00'
									],
									[
										'type' => 0,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'trends, missing data = connected',
								'x' => 0,
								'y' => 4,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'ds.hosts.0.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.0.0',
										'value' => 'Item for trend data display 1'
									],
									[
										'type' => 1,
										'name' => 'ds.hosts.1.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.1.0',
										'value' => 'Item for trend data display 2'
									],
									[
										'type' => 0,
										'name' => 'ds.missingdatafunc.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'ds.color.0',
										'value' => '0040FF'
									],
									[
										'type' => 1,
										'name' => 'ds.color.1',
										'value' => 'BF00FF'
									],
									[
										'type' => 0,
										'name' => 'ds.missingdatafunc.1',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'time_from',
										'value' => '2023-08-03 03:00:00'
									],
									[
										'type' => 1,
										'name' => 'time_to',
										'value' => '2023-08-07 09:00:00'
									],
									[
										'type' => 0,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'trends, missing data = treat as 0',
								'x' => 12,
								'y' => 4,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'ds.hosts.0.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.0.0',
										'value' => 'Item for trend data display 1'
									],
									[
										'type' => 1,
										'name' => 'ds.hosts.1.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.1.0',
										'value' => 'Item for trend data display 2'
									],
									[
										'type' => 0,
										'name' => 'ds.missingdatafunc.0',
										'value' => 2
									],
									[
										'type' => 1,
										'name' => 'ds.color.0',
										'value' => '0040FF'
									],
									[
										'type' => 1,
										'name' => 'ds.color.1',
										'value' => 'BF00FF'
									],
									[
										'type' => 0,
										'name' => 'ds.missingdatafunc.1',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'time_from',
										'value' => '2023-08-03 03:00:00'
									],
									[
										'type' => 1,
										'name' => 'time_to',
										'value' => '2023-08-07 09:00:00'
									],
									[
										'type' => 0,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Axes options + time shift history',
								'x' => 0,
								'y' => 8,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'ds.hosts.0.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.0.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => 1,
										'name' => 'ds.hosts.1.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.1.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => 0,
										'name' => 'ds.axisy.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'ds.timeshift.0',
										'value' => '5m'
									],
									[
										'type' => 1,
										'name' => 'ds.color.1',
										'value' => 'B0AF07'
									],
									[
										'type' => 0,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'time_to',
										'value' => '2023-08-07 13:19:00'
									],
									[
										'type' => 1,
										'name' => 'time_from',
										'value' => '2023-08-07 12:54:00'
									],
									[
										'type' => 1,
										'name' => 'lefty_min',
										'value' => '-10'
									],
									[
										'type' => 1,
										'name' => 'lefty_max',
										'value' => '11'
									],
									[
										'type' => 1,
										'name' => 'righty_min',
										'value' => '-5'
									],
									[
										'type' => 1,
										'name' => 'righty_max',
										'value' => '30'
									],
									[
										'type' => 0,
										'name' => 'righty_units',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'righty_static_units',
										'value' => 'minions'
									],
									[
										'type' => 0,
										'name' => 'axisx',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'history, axes options + time shift',
								'x' => 12,
								'y' => 8,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'ds.hosts.0.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.0.0',
										'value' => 'Item for trend data display 1'
									],
									[
										'type' => 1,
										'name' => 'ds.hosts.1.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.1.0',
										'value' => 'Item for trend data display 2'
									],
									[
										'type' => 1,
										'name' => 'ds.color.0',
										'value' => '4000FF'
									],
									[
										'type' => 0,
										'name' => 'ds.axisy.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'ds.timeshift.0',
										'value' => '-1d'
									],
									[
										'type' => 1,
										'name' => 'ds.color.1',
										'value' => 'B0AF07'
									],
									[
										'type' => 0,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'time_from',
										'value' => '2023-08-03 03:00:00'
									],
									[
										'type' => 1,
										'name' => 'time_to',
										'value' => '2023-08-08 12:00:00'
									],
									[
										'type' => 1,
										'name' => 'lefty_min',
										'value' => '-10'
									],
									[
										'type' => 1,
										'name' => 'lefty_max',
										'value' => '11'
									],
									[
										'type' => 0,
										'name' => 'lefty_units',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'lefty_static_units',
										'value' => 'girafs'
									],
									[
										'type' => 1,
										'name' => 'righty_min',
										'value' => '-5'
									],
									[
										'type' => 1,
										'name' => 'righty_max',
										'value' => '30'
									],
									[
										'type' => 0,
										'name' => 'axisx',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'history, no legend',
								'x' => 0,
								'y' => 12,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'ds.hosts.0.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.0.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => 1,
										'name' => 'ds.hosts.1.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.1.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => 1,
										'name' => 'ds.color.1',
										'value' => 'B0AF07'
									],
									[
										'type' => 0,
										'name' => 'ds.missingdatafunc.1',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'time_from',
										'value' => '2023-08-07 12:59:00'
									],
									[
										'type' => 1,
										'name' => 'time_to',
										'value' => '2023-08-07 13:19:00'
									],
									[
										'type' => 0,
										'name' => 'righty',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'legend',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'trends, Agg func = min + max',
								'x' => 12,
								'y' => 12,
								'width' => 12,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'ds.hosts.0.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.0.0',
										'value' => 'Item for trend data display 1'
									],
									[
										'type' => 1,
										'name' => 'ds.hosts.1.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => 1,
										'name' => 'ds.items.1.0',
										'value' => 'Item for trend data display 2'
									],
									[
										'type' => 1,
										'name' => 'ds.color.0',
										'value' => '0040FF'
									],
									[
										'type' => 1,
										'name' => 'ds.color.1',
										'value' => 'BF00FF'
									],
									[
										'type' => 0,
										'name' => 'ds.aggregate_function.0',
										'value' => 2
									],
									[
										'type' => 1,
										'name' => 'ds.aggregate_interval.0',
										'value' => '12h'
									],
									[
										'type' => 0,
										'name' => 'ds.aggregate_function.1',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'ds.aggregate_interval.1',
										'value' => '12h'
									],
									[
										'type' => 0,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'time_from',
										'value' => '2023-08-03 03:00:00'
									],
									[
										'type' => 1,
										'name' => 'time_to',
										'value' => '2023-08-07 09:00:00'
									],
									[
										'type' => 0,
										'name' => 'righty',
										'value' => 0
									]
								]
							]
						]
					]
				]
			]
		]);

		$timestamps = [
			'history_start' => '2023-08-07 12:58:00',
			'history_end' => '2023-08-07 13:20:00',
			'trends_start' => '2023-08-03 00:00:00',
			'trends_end' => '2023-08-07 12:00:00',
			'pie_start' => 'now-24h',
			'pie_end' => 'now'
		];

		return [
			'hostid' => $hostid,
			'dashboardid' => $dashboard_responce['dashboardids'][0],
			'timestamps' => $timestamps
		];
	}
}

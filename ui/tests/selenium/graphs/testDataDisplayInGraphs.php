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


require_once __DIR__ . '/../../include/CWebTest.php';

/**
 * @backup profiles
 *
 * @onBefore prepareGraphData
 */
class testDataDisplayInGraphs extends CWebTest {

	protected static $hostid;
	protected static $itemids;
	protected static $dashboardid;

	const TIMESTAMPS = [
		'history' => [
			'from' => '2023-10-07 12:58:00',
			'to' => '2023-10-07 13:20:00'
		],
		'trends' => [
			'from' => '2023-10-03 00:00:00',
			'to' => '2023-10-07 12:00:00'
		],
		'pie' => [
			'from' => 'now-1h',
			'to' => 'now'
		],
		'scale_history' => [
			'from' => '2023-10-07 13:58:00',
			'to' => '2023-10-07 14:20:00'
		],
		'scale_trends' => [
			'from' => '2023-09-27 00:00:00',
			'to' => '2023-10-01 12:00:00'
		]
	];

	public static function prepareGraphData() {
		$host_responce = CDataHelper::createHosts([
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
						'delay' => '1m',
						'history' => '9125d',
						'trends' => 0
					],
					[
						'name' => 'Item for history data display 2',
						'key_' => 'item_key_2',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_FLOAT,
						'delay' => '1m',
						'history' => '9125d',
						'trends' => 0
					],
					[
						'name' => 'Item for trends data display 1',
						'key_' => 'item_key_3',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_FLOAT,
						'delay' => '2h',
						'trends' => '9125d'
					],
					[
						'name' => 'Item for trends data display 2',
						'key_' => 'item_key_4',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_FLOAT,
						'delay' => '2h',
						'trends' => '9125d'
					],
					[
						'name' => 'Item for pie chart 1',
						'key_' => 'item_key_5',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_FLOAT,
						'delay' => '1m'
					],
					[
						'name' => 'Item for pie chart 2',
						'key_' => 'item_key_6',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_FLOAT,
						'delay' => '1m'
					]
				]
			]
		]);

		self::$hostid = $host_responce['hostids']['Host for data display on graphs'];

		self::$itemids = [
			'Item for history data display 1' => $host_responce['itemids']['Host for data display on graphs:item_key_1'],
			'Item for history data display 2' => $host_responce['itemids']['Host for data display on graphs:item_key_2'],
			'Item for trends data display 1' => $host_responce['itemids']['Host for data display on graphs:item_key_3'],
			'Item for trends data display 2' => $host_responce['itemids']['Host for data display on graphs:item_key_4'],
			'Item for pie chart 1' => $host_responce['itemids']['Host for data display on graphs:item_key_5'],
			'Item for pie chart 2' => $host_responce['itemids']['Host for data display on graphs:item_key_6']
		];

		$graph_responce = CDataHelper::call('graph.create', [
			[
				'name' => 'History graph 1',
				'width' => 800,
				'height' => 250,
				'gitems' => [
					[
						'itemid' => self::$itemids['Item for history data display 1'],
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
						'itemid' => self::$itemids['Item for history data display 2'],
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
						'itemid' => self::$itemids['Item for history data display 1'],
						'color' => 'FF0000'
					],
					[
						'itemid' => self::$itemids['Item for history data display 2'],
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
						'itemid' => self::$itemids['Item for history data display 1'],
						'color' => 'FF0000'
					],
					[
						'itemid' => self::$itemids['Item for history data display 2'],
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
						'itemid' => self::$itemids['Item for trends data display 1'],
						'color' => 'FF0000',
						'calc_fnc' => 7
					]
				]
			],
			[
				'name' => 'Trends graph 2',
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
						'itemid' => self::$itemids['Item for trends data display 2'],
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
						'itemid' => self::$itemids['Item for trends data display 1'],
						'color' => 'FF0000',
						'calc_fnc' => 7
					],
					[
						'itemid' => self::$itemids['Item for trends data display 2'],
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
						'itemid' => self::$itemids['Item for trends data display 1'],
						'color' => 'FF0000',
						'calc_fnc' => 1
					],
					[
						'itemid' => self::$itemids['Item for trends data display 2'],
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
						'itemid' => self::$itemids['Item for trends data display 1'],
						'color' => 'FF0000',
						'calc_fnc' => 2
					],
					[
						'itemid' => self::$itemids['Item for trends data display 2'],
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
						'itemid' => self::$itemids['Item for trends data display 1'],
						'color' => 'FF0000',
						'calc_fnc' => 4
					],
					[
						'itemid' => self::$itemids['Item for trends data display 2'],
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
						'itemid' => self::$itemids['Item for pie chart 1'],
						'color' => '00FF00'
					],
					[
						'itemid' => self::$itemids['Item for pie chart 2'],
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
						'itemid' => self::$itemids['Item for pie chart 1'],
						'color' => '00FF00'
					],
					[
						'itemid' => self::$itemids['Item for pie chart 2'],
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
						'itemid' => self::$itemids['Item for pie chart 1'],
						'color' => '00FF00'
					],
					[
						'itemid' => self::$itemids['Item for pie chart 2'],
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
						'itemid' => self::$itemids['Item for pie chart 1'],
						'color' => '00FF00'
					],
					[
						'itemid' => self::$itemids['Item for pie chart 2'],
						'color' => 'FF0000'
					]
				]
			]
		]);

		// This timestamp represents the changing point between trends and history data.
		$timestamp = '07-10-2023 12:00 EET';

		$item_data = [
			// Trends data for first trends item.
			[
				'itemid' => self::$itemids['Item for trends data display 1'],
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
				'itemid' => self::$itemids['Item for history data display 1'],
				'timestamps' => [
					$timestamp,
					$timestamp.' + 1 minute',
					$timestamp.' + 2 minutes',
					$timestamp.' + 3 minutes',
					$timestamp.' + 6 minutes',
					$timestamp.' + 7 minutes',
					$timestamp.' + 8 minutes',
					$timestamp.' + 13 minutes',
					$timestamp.' + 14 minutes',
					$timestamp.' + 15 minutes',
					$timestamp.' + 16 minutes',
					$timestamp.' + 17 minutes',
					$timestamp.' + 18 minutes'
				],
				'values' => [10.5, 8.33, 12.69, 4.025, 3.1, 7, 6.66, -1.5, 0.31, 6.47, 2.11, 8.98, 10.01]
			],
			// Trends data for second trends item.
			[
				'itemid' => self::$itemids['Item for trends data display 2'],
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
				'itemid' => self::$itemids['Item for history data display 2'],
				'timestamps' => [
					$timestamp,
					$timestamp.' + 1 minute',
					$timestamp.' + 2 minutes',
					$timestamp.' + 3 minutes',
					$timestamp.' + 4 minutes',
					$timestamp.' + 5 minutes',
					$timestamp.' + 6 minutes',
					$timestamp.' + 11 minutes',
					$timestamp.' + 12 minutes',
					$timestamp.' + 13 minutes',
					$timestamp.' + 16 minutes',
					$timestamp.' + 17 minutes',
					$timestamp.' + 18 minutes'
				],
				'values' => [4.31, -0.5, 7.53, 2.37, 5.55, 7.77, 9.11, 10.34, 2.23, 5.98, -3.21, 2.45, 5.1]
			],
			// History data for first axes scale scenario.
			[
				'itemid' => self::$itemids['Item for history data display 1'],
				'timestamps' => [
					$timestamp.' + 1 hour',
					$timestamp.' + 1 hour 1 minute',
					$timestamp.' + 1 hour 2 minutes',
					$timestamp.' + 1 hour 3 minutes',
					$timestamp.' + 1 hour 6 minutes',
					$timestamp.' + 1 hour 7 minutes',
					$timestamp.' + 1 hour 8 minutes',
					$timestamp.' + 1 hour 13 minutes',
					$timestamp.' + 1 hour 14 minutes',
					$timestamp.' + 1 hour 15 minutes',
					$timestamp.' + 1 hour 16 minutes',
					$timestamp.' + 1 hour 17 minutes',
					$timestamp.' + 1 hour 18 minutes'
				],
				'values' => [110.5, 80.33, 120.69, 40.025, 30.1, 70, 60.66, -10.5, 10.31, 60.47, 20.11, 80.98, 130.01]
			],
			// History data for second axes scale scenario.
			[
				'itemid' => self::$itemids['Item for history data display 2'],
				'timestamps' => [
					$timestamp.' + 1 hour',
					$timestamp.' + 1 hour 1 minute',
					$timestamp.' + 1 hour 2 minutes',
					$timestamp.' + 1 hour 3 minutes',
					$timestamp.' + 1 hour 4 minutes',
					$timestamp.' + 1 hour 5 minutes',
					$timestamp.' + 1 hour 6 minutes',
					$timestamp.' + 1 hour 11 minutes',
					$timestamp.' + 1 hour 12 minutes',
					$timestamp.' + 1 hour 13 minutes',
					$timestamp.' + 1 hour 16 minutes',
					$timestamp.' + 1 hour 17 minutes',
					$timestamp.' + 1 hour 18 minutes'
				],
				'values' => [40.31, -10.5, 70.53, 20.37, 50.55, 70.77, 90.11, 199.34, 70.23, 50.98, -30.21, 20.45, 50.1]
			],
			// Trends data for third axes scale scenario.
			[
				'itemid' => self::$itemids['Item for trends data display 1'],
				'timestamps' => [
					$timestamp.' - 11 days + 18 hours',
					$timestamp.' - 10 days',
					$timestamp.' - 10 days + 6 hours',
					$timestamp.' - 10 days + 12 hours',
					$timestamp.' - 10 days + 18 hours',
					$timestamp.' - 9 days',
					$timestamp.' - 9 days + 3 hours',
					$timestamp.' - 9 days + 6 hours',
					$timestamp.' - 9 days + 9 hours',
					$timestamp.' - 9 days + 12 hours',
					$timestamp.' - 9 days + 21 hours',
					$timestamp.' - 8 days',
					$timestamp.' - 8 days + 21 hours',
					$timestamp.' - 7 day',
					$timestamp.' - 7 day + 3 hours',
					$timestamp.' - 7 day + 6 hours',
					$timestamp.' - 7 day + 12 hours',
					$timestamp.' - 7 day + 15 hours'
				],
				'values' => [
					[
						'num' => 30,
						'avg' => 60.03,
						'min' => -40.1,
						'max' => 130.57
					],
					[
						'num' => 30,
						'avg' => 140.73,
						'min' => -10.74,
						'max' => 190.01
					],
					[
						'num' => 30,
						'avg' => 80.23,
						'min' => 30.7,
						'max' => 150.3
					],
					[
						'num' => 30,
						'avg' => -20.95,
						'min' => -50.15,
						'max' => -5.2
					],
					[
						'num' => 30,
						'avg' => 60.66,
						'min' => 10.19,
						'max' => 120.2
					],
					[
						'num' => 30,
						'avg' => 70.07,
						'min' => 20.22,
						'max' => 130.38
					],
					[
						'num' => 30,
						'avg' => 50.19,
						'min' => 20.41,
						'max' => 90.62
					],
					[
						'num' => 33,
						'avg' => 122.42,
						'min' => 99.37,
						'max' => 244.02
					],
					[
						'num' => 30,
						'avg' => 50.75,
						'min' => 20.13,
						'max' => 130.72
					],
					[
						'num' => 30,
						'avg' => 80,
						'min' => 40.32,
						'max' => 110.7
					],
					[
						'num' => 30,
						'avg' => 40.3,
						'min' => 10.97,
						'max' => 80.73
					],
					[
						'num' => 30,
						'avg' => 60.9,
						'min' => 20.81,
						'max' => 120.1
					],
					[
						'num' => 30,
						'avg' => 70.12,
						'min' => 40.04,
						'max' => 110.26
					],
					[
						'num' => 30,
						'avg' => 50.05,
						'min' => 10.92,
						'max' => 130.01
					],
					[
						'num' => 30,
						'avg' => 30.83,
						'min' => -10.13,
						'max' => 80.15
					],
					[
						'num' => 30,
						'avg' => 190.23,
						'min' => -30.7,
						'max' => 350.13
					],
					[
						'num' => 30,
						'avg' => 140.95,
						'min' => 89.1,
						'max' => 211.2
					],
					[
						'num' => 30,
						'avg' => 60.5,
						'min' => 00.1,
						'max' => 150.21
					]
				]
			],
			// Trends data for fourth axes scale scenario.
			[
				'itemid' => self::$itemids['Item for trends data display 2'],
				'timestamps' => [
					$timestamp.' - 11 days + 18 hours',
					$timestamp.' - 10 days',
					$timestamp.' - 10 days + 6 hours',
					$timestamp.' - 10 days + 12 hours',
					$timestamp.' - 10 days + 18 hours',
					$timestamp.' - 9 days',
					$timestamp.' - 9 days + 6 hours',
					$timestamp.' - 8 days + 6 hours',
					$timestamp.' - 8 days + 12 hours',
					$timestamp.' - 8 days + 18 hours',
					$timestamp.' - 7 day + 6 hours',
					$timestamp.' - 7 day + 12 hours',
					$timestamp.' - 7 day + 18 hours'
				],
				'values' => [
					[
						'num' => 590,
						'avg' => 390.07,
						'min' => 111.54,
						'max' => 1130.81
					],
					[
						'num' => 590,
						'avg' => 170.62,
						'min' => 33.91,
						'max' => 333.77
					],
					[
						'num' => 590,
						'avg' => 90.99,
						'min' => 10.01,
						'max' => 190.45
					],
					[
						'num' => 480,
						'avg' => -104.31,
						'min' => -145.56,
						'max' => -55.1
					],
					[
						'num' => 510,
						'avg' => -15.12,
						'min' => -55.45,
						'max' => -10.23
					],
					[
						'num' => 590,
						'avg' => 80.31,
						'min' => -10.05,
						'max' => 170.26
					],
					[
						'num' => 590,
						'avg' => 122.71,
						'min' => 20.19,
						'max' => 210.11
					],
					[
						'num' => 350,
						'avg' => 401.96,
						'min' => 111.41,
						'max' => 703.35
					],
					[
						'num' => 520,
						'avg' => 190.1,
						'min' => 130.01,
						'max' => 340.45
					],
					[
						'num' => 590,
						'avg' => 90,
						'min' => 50.5,
						'max' => 160.5
					],
					[
						'num' => 590,
						'avg' => 40.21,
						'min' => -20.1,
						'max' => 120.31
					],
					[
						'num' => 590,
						'avg' => 00.74,
						'min' => -60.05,
						'max' => 90.26
					],
					[
						'num' => 590,
						'avg' => 70.12,
						'min' => 10.55,
						'max' => 100.42
					]
				]
			]
		];

		// Add history and trends values to item displayed in graphs and widgets.
		foreach ($item_data as $data) {
			foreach ($data['timestamps'] as &$timestamp) {
				$timestamp = strtotime($timestamp);
			}

			unset($timestamp);
			CDataHelper::addItemData($data['itemid'], $data['values'], $data['timestamps']);
		}

		CDataHelper::addItemData(self::$itemids['Item for pie chart 1'], 0.66);
		CDataHelper::addItemData(self::$itemids['Item for pie chart 2'], 0.34);

		// Create dashboard with SVG graphs and classic graph widgets.
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
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid.0',
										'value' => $graph_responce['graphids'][0]
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 36,
								'y' => 0,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid.0',
										'value' => $graph_responce['graphids'][1]
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 0,
								'y' => 4,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid.0',
										'value' => $graph_responce['graphids'][2]
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 36,
								'y' => 4,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid.0',
										'value' => $graph_responce['graphids'][3]
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 0,
								'y' => 8,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid.0',
										'value' => $graph_responce['graphids'][4]
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 36,
								'y' => 8,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid.0',
										'value' => $graph_responce['graphids'][5]
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 0,
								'y' => 12,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid.0',
										'value' => $graph_responce['graphids'][6]
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 36,
								'y' => 12,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid.0',
										'value' => $graph_responce['graphids'][7]
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 0,
								'y' => 16,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid.0',
										'value' => $graph_responce['graphids'][8]
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 36,
								'y' => 16,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid.0',
										'value' => $graph_responce['graphids'][9]
									]
								]
							]
						]
					],
					[
						'name' => 'Pie charts',
						'widgets' => [
							[
								'type' => 'graph',
								'name' => '',
								'x' => 0,
								'y' => 0,
								'width' => 18,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid.0',
										'value' => $graph_responce['graphids'][10]
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 18,
								'y' => 0,
								'width' => 18,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid.0',
										'value' => $graph_responce['graphids'][11]
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 36,
								'y' => 0,
								'width' => 18,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid.0',
										'value' => $graph_responce['graphids'][12]
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 54,
								'y' => 0,
								'width' => 18,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid.0',
										'value' => $graph_responce['graphids'][13]
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
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source_type',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid.0',
										'value' => self::$itemids['Item for history data display 1']
									]
								]
							],
							[
								'type' => 'graph',
								'name' => '',
								'x' => 36,
								'y' => 0,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source_type',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid.0',
										'value' => self::$itemids['Item for trends data display 1']
									]
								]
							]
						]
					],
					[
						'name' => 'SVG graphs with axes scale',
						'widgets' => [
							[
								'type' => 'svggraph',
								'name' => 'History data, line with linear left axis',
								'x' => 0,
								'y' => 0,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => '0080FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['scale_history']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['scale_history']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'lefty_scale',
										'value' => 0 // Linear scale (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0 // disabled Right Y (Axes tab).
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'History data, line with logarithmic right axis',
								'x' => 36,
								'y' => 0,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.axisy',
										'value' => 1 // Y-axis right (Data set tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.axisy',
										'value' => 1 // Y-axis right (Data set tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => '0080FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['scale_history']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['scale_history']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty_scale',
										'value' => 1 // Logarithmic scale (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'lefty',
										'value' => 0 // disabled Left Y (Axes tab).
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'History data, points with linear right axis',
								'x' => 0,
								'y' => 4,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.axisy',
										'value' => 1 // Y-axis right (Data set tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.type',
										'value' => 1 // Draw = Points.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.axisy',
										'value' => 1 // Y-axis right (Data set tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.type',
										'value' => 1 // Draw = Points.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => '0080FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['scale_history']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['scale_history']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty_scale',
										'value' => 0 // Linear scale (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'lefty',
										'value' => 0 // disabled Left Y (Axes tab).
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'History data, points with logarithmic left axis',
								'x' => 36,
								'y' => 4,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.type',
										'value' => 1 // Draw = Points.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.type',
										'value' => 1 // Draw = Points.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => '0080FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['scale_history']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['scale_history']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'lefty_scale',
										'value' => 1 // Logarithmic scale (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0 // disabled Right Y (Axes tab).
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'History data, staircase with linear left axis',
								'x' => 0,
								'y' => 8,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.type',
										'value' => 2 // Draw = Staircase.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.type',
										'value' => 2 // Draw = Staircase.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => '0080FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['scale_history']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['scale_history']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'lefty_scale',
										'value' => 0 // Linear scale (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0 // disabled Right Y (Axes tab).
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'History data, staircase with logarithmic right axis and static units',
								'x' => 36,
								'y' => 8,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.axisy',
										'value' => 1 // Y-axis right (Data set tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.type',
										'value' => 2 // Draw = Staircase.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.axisy',
										'value' => 1 // Y-axis right (Data set tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.type',
										'value' => 2 // Draw = Staircase.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => '0080FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['scale_history']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['scale_history']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty_scale',
										'value' => 1 // Logarithmic scale (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty_units',
										'value' => 1 // Units type (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'righty_static_units',
										'value' => 'cucumbers' // Units value (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'lefty',
										'value' => 0 // disabled Left Y (Axes tab).
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'History data, bar with linear right axis',
								'x' => 0,
								'y' => 12,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.axisy',
										'value' => 1 // Y-axis right (Data set tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.type',
										'value' => 3 // Draw = Bar.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.axisy',
										'value' => 1 // Y-axis right (Data set tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.type',
										'value' => 3 // Draw = Bar.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => '0080FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['scale_history']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['scale_history']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty_scale',
										'value' => 0 // Linear scale (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'lefty',
										'value' => 0 // disabled Left Y (Axes tab).
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'History data, bar with logarithmic left axis',
								'x' => 36,
								'y' => 12,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.type',
										'value' => 3 // Draw = Bar.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.type',
										'value' => 3 // Draw = Bar.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => '0080FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['scale_history']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['scale_history']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'lefty_scale',
										'value' => 1 // Logarithmic scale (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0 // disabled Right Y (Axes tab).
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Trends data, line with linear left axis',
								'x' => 0,
								'y' => 16,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => 'BF00FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => '009688'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['scale_trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['scale_trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'lefty_scale',
										'value' => 0 // Linear scale (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0 // disabled Right Y (Axes tab).
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Trends data, line with logarithmic right axis',
								'x' => 36,
								'y' => 16,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.axisy',
										'value' => 1 // Y-axis right (Data set tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => 'BF00FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.axisy',
										'value' => 1 // Y-axis right (Data set tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => '009688'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['scale_trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['scale_trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty_scale',
										'value' => 1 // Logarithmic scale (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'lefty',
										'value' => 0 // disabled Left Y (Axes tab).
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Trends data, points with linear right axis',
								'x' => 0,
								'y' => 20,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.axisy',
										'value' => 1 // Y-axis right (Data set tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.type',
										'value' => 1 // Draw = Points.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.axisy',
										'value' => 1 // Y-axis right (Data set tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.type',
										'value' => 1 // Draw = Points.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => '0080FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['scale_trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['scale_trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty_scale',
										'value' => 0 // Linear scale (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'lefty',
										'value' => 0 // disabled Left Y (Axes tab).
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Trends data, points with logarithmic left axis',
								'x' => 36,
								'y' => 20,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.type',
										'value' => 1 // Draw = Points.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.type',
										'value' => 1 // Draw = Points.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => '0080FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['scale_trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['scale_trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'lefty_scale',
										'value' => 1 // Linear scale (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0 // disabled Right Y (Axes tab).
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Trends data, staircase with linear left axis',
								'x' => 0,
								'y' => 24,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.type',
										'value' => 2 // Draw = Staircase.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.type',
										'value' => 2 // Draw = Staircase.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => '0080FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['scale_trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['scale_trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'lefty_scale',
										'value' => 0 // Linear scale (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0 // disabled Right Y (Axes tab).
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Trends data, staircase with logarithmic right axis',
								'x' => 36,
								'y' => 24,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.axisy',
										'value' => 1 // Y-axis right (Data set tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.type',
										'value' => 2 // Draw = Staircase.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.axisy',
										'value' => 1 // Y-axis right (Data set tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.type',
										'value' => 2 // Draw = Staircase.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => '0080FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['scale_trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['scale_trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty_scale',
										'value' => 1 // Logarithmic scale (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'lefty',
										'value' => 0 // disabled Left Y (Axes tab).
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Trends data, bar with linear right axis',
								'x' => 0,
								'y' => 28,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.axisy',
										'value' => 1 // Y-axis right (Data set tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.type',
										'value' => 3 // Draw = Bar.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.axisy',
										'value' => 1 // Y-axis right (Data set tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.type',
										'value' => 3 // Draw = Bar.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => '0080FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['scale_trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['scale_trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty_scale',
										'value' => 0 // Linear scale (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'lefty',
										'value' => 0 // disabled Left Y (Axes tab).
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Trends data, bar with logarithmic left axis',
								'x' => 36,
								'y' => 28,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.type',
										'value' => 3 // Draw = Bar.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.type',
										'value' => 3 // Draw = Bar.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => '0080FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['scale_trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['scale_trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'lefty_scale',
										'value' => 1 // Logarithmic scale (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0 // disabled Right Y (Axes tab).
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'History data, line with linear left and logarithmic right axes',
								'x' => 0,
								'y' => 32,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.axisy',
										'value' => 1 // Y-axis right (Data set tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => '0080FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['scale_history']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['scale_history']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'lefty_scale',
										'value' => 0 // Linear scale (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty_scale',
										'value' => 1 // Logarithmic scale (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 1 // Right Y (Axes tab).
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Trends data, line with logarithmic left and linear right axes',
								'x' => 36,
								'y' => 32,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.axisy',
										'value' => 1 // Y-axis right (Data set tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => '0080FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['scale_trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['scale_trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'lefty_scale',
										'value' => 1 // Logarithmic scale (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty_scale',
										'value' => 0 // Linear scale (Axes tab).
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 1 // Right Y (Axes tab).
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
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.missingdatafunc',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => 'B0AF07'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.missingdatafunc',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['history']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['history']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'history, missing data = treat as 0',
								'x' => 36,
								'y' => 0,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.missingdatafunc',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => 'B0AF07'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.missingdatafunc',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['history']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['history']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
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
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.missingdatafunc',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => 'BF00FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.missingdatafunc',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'trends, missing data = treat as 0',
								'x' => 36,
								'y' => 4,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.missingdatafunc',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => 'BF00FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.missingdatafunc',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
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
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.axisy',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.timeshift',
										'value' => '5m'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => 'B0AF07'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => '2023-10-07 12:54:00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['history']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'lefty_min',
										'value' => '-10'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'lefty_max',
										'value' => '11'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'righty_min',
										'value' => '-5'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'righty_max',
										'value' => '30'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty_units',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'righty_static_units',
										'value' => 'minions'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'axisx',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'history, axes options + time shift',
								'x' => 36,
								'y' => 8,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '4000FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.axisy',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.timeshift',
										'value' => '-1d'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => 'B0AF07'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => '2023-10-08 12:00:00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'lefty_min',
										'value' => '-10'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'lefty_max',
										'value' => '11'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'lefty_units',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'lefty_static_units',
										'value' => 'girafs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'righty_min',
										'value' => '-5'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'righty_max',
										'value' => '30'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
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
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => 'B0AF07'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.missingdatafunc',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['history']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['history']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'legend',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'trends, Agg func = min + max',
								'x' => 36,
								'y' => 12,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.items.0',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.color',
										'value' => 'BF00FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.1.aggregate_function',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.1.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							]
						]
					],
					// Pages with different aggregation and approximation functions combinations for two items in data set.
					[
						'name' => 'Aggregation+Auto+History',
						'widgets' => [
							[
								'type' => 'svggraph',
								'name' => 'Auto, Aggregation function = not used',
								'x' => 0,
								'y' => 0,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Auto, Aggregation function = min',
								'x' => 36,
								'y' => 0,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Auto, Aggregation function = max',
								'x' => 0,
								'y' => 4,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.approximation',
										'value' => 7
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Auto, Aggregation function = avg',
								'x' => 36,
								'y' => 4,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.approximation',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Auto, Aggregation function = count',
								'x' => 0,
								'y' => 8,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 4
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.approximation',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Aggregation function = sum',
								'x' => 36,
								'y' => 8,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 5
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.approximation',
										'value' => 4
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Auto, Aggregation function = first',
								'x' => 0,
								'y' => 12,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 6
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Auto, Aggregation function = last',
								'x' => 36,
								'y' => 12,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 7
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'History, Aggregation function = not used',
								'x' => 0,
								'y' => 16,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => '2023-10-06 00:00:00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => '2023-10-08 12:00:00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'History, Aggregation function = min',
								'x' => 36,
								'y' => 16,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => '2023-10-06 00:00:00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => '2023-10-08 12:00:00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'History, Aggregation function = max',
								'x' => 0,
								'y' => 20,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.approximation',
										'value' => 7
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => '2023-10-06 00:00:00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => '2023-10-08 12:00:00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'History, Aggregation function = avg',
								'x' => 36,
								'y' => 20,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.approximation',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => '2023-10-06 00:00:00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => '2023-10-08 12:00:00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'History, Aggregation function = count',
								'x' => 0,
								'y' => 24,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 4
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.approximation',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => '2023-10-06 00:00:00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => '2023-10-08 12:00:00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'History, Aggregation function = sum',
								'x' => 36,
								'y' => 24,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 5
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.approximation',
										'value' => 4
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => '2023-10-06 00:00:00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => '2023-10-08 12:00:00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'History, Aggregation function = first',
								'x' => 0,
								'y' => 28,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 6
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => '2023-10-06 00:00:00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => '2023-10-08 12:00:00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'History, Aggregation function = last',
								'x' => 36,
								'y' => 28,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for history data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for history data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 7
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => '2023-10-06 00:00:00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => '2023-10-08 12:00:00'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							]
						]
					],
					[
						'name' => 'Aggregation+Trends',
						'widgets' => [
							[
								'type' => 'svggraph',
								'name' => 'Data set, Aggregation = not used',
								'x' => 0,
								'y' => 0,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Data set, Aggregation function = min',
								'x' => 36,
								'y' => 0,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Data set, Aggregation function = max',
								'x' => 0,
								'y' => 4,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.approximation',
										'value' => 7
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Data set, Aggregation function = avg',
								'x' => 36,
								'y' => 4,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.approximation',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Data set, Aggregation function = count',
								'x' => 0,
								'y' => 8,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 4
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.approximation',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Data set, Aggregation function = sum',
								'x' => 36,
								'y' => 8,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 5
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.approximation',
										'value' => 4
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Data set, Aggregation function = first',
								'x' => 0,
								'y' => 12,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 6
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Data set, Aggregation function = last',
								'x' => 36,
								'y' => 12,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 7
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Each item, Aggregation function = min',
								'x' => 0,
								'y' => 16,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Each item, Aggregation function = max',
								'x' => 36,
								'y' => 16,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.approximation',
										'value' => 7
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Each item, Aggregation function = avg',
								'x' => 0,
								'y' => 20,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.approximation',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Each item, Aggregation function = count',
								'x' => 36,
								'y' => 20,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 4
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.approximation',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Each item, Aggregation function = sum',
								'x' => 0,
								'y' => 24,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 5
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.approximation',
										'value' => 4
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Each item, Aggregation function = first',
								'x' => 36,
								'y' => 24,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 6
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'Each item, Aggregation function = last',
								'x' => 0,
								'y' => 28,
								'width' => 36,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.hosts.0',
										'value' => 'Host for data display on graphs'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.0',
										'value' => 'Item for trends data display 1'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.items.1',
										'value' => 'Item for trends data display 2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_function',
										'value' => 7
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.aggregate_interval',
										'value' => '12h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.aggregate_grouping',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'graph_time',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => self::TIMESTAMPS['trends']['from']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => self::TIMESTAMPS['trends']['to']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
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

		self::$dashboardid = $dashboard_responce['dashboardids'][0];
	}

	public function getMonitoringGraphData() {
		return [
			[
				[
					'type' => 'history'
				]
			],
			[
				[
					'type' => 'trends'
				]
			],
			[
				[
					'type' => 'pie'
				]
			],
			[
				[
					'type' => 'history',
					'kiosk_mode' => true
				]
			],
			[
				[
					'type' => 'trends',
					'kiosk_mode' => true
				]
			],
			[
				[
					'type' => 'pie',
					'kiosk_mode' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getMonitoringGraphData
	 */
	public function testDataDisplayInGraphs_MonitoringHosts($data) {
		$this->page->login()->open('zabbix.php?action=charts.view&filter_set=1&filter_hostids%5B0%5D='.self::$hostid)
				->waitUntilReady();

		// Open the time selector tab if it's not opened yet.
		$timeselector_tab = $this->query('id:tab_1')->one();

		if ($timeselector_tab->isDisplayed(false)) {
			$this->query('xpath://a[contains(@class, "btn-time")]')->one()->click();
			$timeselector_tab->waitUntilVisible();
		}

		// Set time selector to display the time period, required for the corresponding data type.
		$this->setTimeSelector(self::TIMESTAMPS[$data['type']]);
		$this->page->waitUntilReady();

		// Switch to filter tab and fill in the name pattern to return only graphs with certain type.
		CFilterElement::find()->one()->selectTab('Filter');
		$filter_form = $this->query('name:zbx_filter')->asForm()->one();
		$filter_form->fill(['Name' => $data['type']]);

		$screenshot_string = (CTestArrayHelper::get($data, 'kiosk_mode'))
			? 'monitoring_hosts_'.$data['type'].'_kiosk_'
			: 'monitoring_hosts_'.$data['type'].'_';

		// Check screenshots of graphs for each option in 'Show' field.
		$show_data = $data['type'] === 'trends'
			? ['All graphs' => 8, 'Host graphs' => 6, 'Simple graphs' => 2]
			: ['All graphs' => 6, 'Host graphs' => 4, 'Simple graphs' => 2];
		foreach ($show_data as $show => $count) {
			// Pie widget displays data in non-fixed time period, so only host graph screenshot will not differ each time.
			if ($data['type'] === 'pie' && $show !== 'Host graphs') {
				continue;
			}

			// Select the desired value in Show field, if it is not selected already.
			$filter_form->invalidate();
			if ($filter_form->getField('Show')->getValue() !== $show) {
				$filter_form->fill(['Show' => $show]);
			}

			$filter_form->submit();
			$this->page->waitUntilReady();

			// Switch to kiosk mode if screenshot needs to be checked in Kiosk mode.
			if (CTestArrayHelper::get($data, 'kiosk_mode')) {
				$this->query('xpath://button[@title="Kiosk mode"]')->one()->click();
				$this->page->waitUntilReady();
			}

			// Wait for all graphs to load and check the screenshots of all graphs of the desired type.
			$charts_table = $this->query('id:charts')->waitUntilVisible()->one();
			foreach ($charts_table->query('class:center')->waitUntilCount($count)->all() as $graph) {
				$graph->waitUntilClassesNotPresent('is-loading', 60);
			}

			$this->assertScreenshot($charts_table, $screenshot_string.$show);

			// Switch back to normal view to avoid impacting following scenarios.
			if (CTestArrayHelper::get($data, 'kiosk_mode')) {
				$this->query('xpath://button[@title="Normal view"]')->one()->click();
				$this->page->waitUntilReady();
			}
		}
	}

	public function getLatestDataGraphData() {
		return [
			[
				[
					'type' => 'history',
					'item' => 'Item for history data display 1'
				]
			],
			[
				[
					'type' => 'trends',
					'item' => 'Item for trends data display 1'
				]
			]
		];
	}

	/**
	 * @dataProvider getLatestDataGraphData
	 */
	public function testDataDisplayInGraphs_LatestData($data) {
		$this->page->login()->open('history.php?action=showgraph&itemids%5B%5D='.self::$itemids[$data['item']])
				->waitUntilReady();

		// In Latest data the image loads longer, so need to wait for image source to change before checking the screenshot.
		$image = $this->query('id:historyGraph')->one();
		$old_source = $image->getAttribute('src');

		// Set time selector to display the time period, required for the corresponding data type.
		$this->setTimeSelector(self::TIMESTAMPS[$data['type']]);

		// Wait for the image source to change and check graph screenshot.
		$image->waitUntilAttributesNotPresent(['src' => $old_source]);
		$screenshot_id = 'latest_data_'.$data['type'];
		$this->assertScreenshot($this->query('class:center')->one(), $screenshot_id);

		$this->checkKioskMode('class:center', $screenshot_id);
	}

	public function getDashboardWidgetData() {
		return [
			[
				[
					'type' => 'history',
					'page' => 'Host graphs'
				]
			],
			[
				[
					'type' => 'trends',
					'page' => 'Host graphs'
				]
			],
			[
				[
					'type' => 'pie',
					'page' => 'Pie charts'
				]
			],
			[
				[
					'type' => 'history',
					'page' => 'Simple graphs'
				]
			],
			[
				[
					'type' => 'trends',
					'page' => 'Simple graphs'
				]
			],
			[
				[
					'page' => 'SVG graphs with axes scale'
				]
			],
			[
				[
					'page' => 'Missing data + axes + timeshift + agg func'
				]
			],
			/*
			 * Widgets with different aggregation and approximation functions combinations for two items in data set.
			 * These cases cover runtime errors detection in Aggregation and Trends settings combinations found during ZBX-22350.
			 */
			[
				[
					'page' => 'Aggregation+Auto+History'
				]
			],
			[
				[
					'page' => 'Aggregation+Trends'
				]
			]
		];
	}

	/**
	 * @dataProvider getDashboardWidgetData
	 */
	public function testDataDisplayInGraphs_DashboardWidgets($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();

		// It's required to set time selector only for pages with classic graph widgets, SVG graphs have period set in config.
		if (CTestArrayHelper::get($data, 'type')) {
			$this->setTimeSelector(self::TIMESTAMPS[$data['type']]);
		}

		$dashboard = CDashboardElement::find()->one()->waitUntilReady();

		// Open the required dashboard page and check screenshot.
		if ($data['page'] !== $dashboard->getSelectedPageName()) {
			$dashboard->selectPage($data['page']);
			$dashboard->waitUntilReady();
		}

		$screenshot_id = 'dashboard_'.$data['page'].'_page_'.CTestArrayHelper::get($data, 'type', 'svg');
		$this->assertScreenshot($dashboard, $screenshot_id);

		$this->checkKioskMode('class:dashboard-grid', $screenshot_id);
	}

	/**
	 * Check graphs screenshots in Kiosk mode.
	 *
	 * @param string	$object_locator		locator of element with graphs
	 * @param string	$id					ID of the screenshot
	 */
	protected function checkKioskMode($object_locator, $id) {
		if ($object_locator === 'class:center') {
			$image = $this->query($object_locator)->one()->query('tag:img')->one();
			$old_source = $image->getAttribute('src');
		}

		$this->query('xpath://button[@title="Kiosk mode"]')->one()->click();
		$this->page->waitUntilReady();

		$object = $this->query($object_locator)->waitUntilPresent()->one();

		// Wait for the dashboard to load widgets or wait for latest data graph to complete loading graph with new source.
		if ($object_locator === 'class:center') {
			$image->waitUntilAttributesNotPresent(['src' => $old_source]);

			$callback = function() use ($image) {
				return CElementQuery::getDriver()->executeScript('return arguments[0].complete;', [$image]);
			};

			CElementQuery::wait()->until($callback, 'Failed to wait for image to be loaded');
		}
		else {
			$object->asDashboard()->waitUntilReady();
		}

		$this->assertScreenshotExcept($object, $this->query('class:header-kioskmode-controls')->one(), $id.'_kiosk');

		$this->query('xpath://button[@title="Normal view"]')->one()->click();
		$this->page->waitUntilReady();
	}

	/**
	 * Set time selector configuration according to the desired period.
	 *
	 * @param array	$timestamps		timestamps that represent the start and the end of the desired period
	 */
	protected function setTimeSelector($timestamps) {
		$timeselector_block = $this->query('class:time-input')->one();

		foreach (['from', 'to'] as $fieldid) {
			$timeselector_block->query('id:'.$fieldid)->waitUntilVisible()->one()->fill($timestamps[$fieldid]);
		}
		$timeselector_block->query('id:apply')->one()->click();
	}
}

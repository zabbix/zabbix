<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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

require_once dirname(__FILE__) . '/../../include/CWebTest.php';

/**
 * @backup profiles, dashboard
 *
 * @onBefore prepareData

 */
class testDashboardDynamicItemWidgets extends CWebTest {

	protected static $dashboardid;

	public static function prepareData() {
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
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source_type',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid',
										'value' => '99104' // item name in widget 'Dynamic widgets H1: Dynamic widgets H1I2'.
									]
								]
							],
							[
								'type' => 'graph',
								'x' => 24,
								'y' => 0,
								'width' => 24,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source_type',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid',
										'value' => '99103' // item name in widget 'Dynamic widgets H1: Dynamic widgets H1I1'.
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
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source_type',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid',
										'value' => '99104' // item name in widget 'Dynamic widgets H1: Dynamic widgets H1I2'.
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
								'y' => 5,
								'width' => 24,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid',
										'value' => '700027' // Graph name in widget 'Dynamic widgets H1: Dynamic widgets H1I2'.
									]
								]
							],
							[
								'type' => 'graph',
								'x' => 24,
								'y' => 5,
								'width' => 24,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid',
										'value' => '700026' // Graph name in widget Dynamic widgets H1: Dynamic widgets H1 G1 (I1).
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
								'y' => 5,
								'width' => 24,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid',
										'value' => '700027' // Graph name in widget 'Dynamic widgets H1: Dynamic widgets H1I2'.
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
								'y' => 10,
								'width' => 24,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid',
										'value' => '700028' // Graph name in widget 'Dynamic widgets H1: Dynamic widgets H1 G3 (I1 and I2)'.
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
								'y' => 10,
								'width' => 24,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid',
										'value' => '700031' // Graph name in widget 'Dynamic widgets H1: Dynamic widgets H1 G4 (H1I1 and H3I1)'.
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
								'y' => 15,
								'width' => 24,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid',
										'value' => '99104' // Item name in widget 'Dynamic widgets H1: Dynamic widgets H1I2'.
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
								'y' => 15,
								'width' => 24,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid',
										'value' => '99103' // Item name in widget 'Dynamic widgets H1: Dynamic widgets H1I1'.
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
								'y' => 20,
								'width' => 24,
								'height' => 5,
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
								'y' => 25,
								'width' => 24,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source_type',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE,
										'name' => 'itemid',
										'value' => '99109' // item name in widget 'Dynamic widgets H1: Dynamic widgets H1IP2'.
									]
								]
							],
							[
								'type' => 'graphprototype',
								'x' => 24,
								'y' => 25,
								'width' => 24,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source_type',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE,
										'name' => 'itemid',
										'value' => '99108' // item name in widget 'Dynamic widgets H1: Dynamic widgets H1IP1'.
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
								'y' => 25,
								'width' => 24,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source_type',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE,
										'name' => 'itemid',
										'value' => '99109' // item name in widget 'Dynamic widgets H1: Dynamic widgets H1IP2'.
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
								'y' => 30,
								'width' => 24,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE,
										'name' => 'graphid',
										'value' => '700032' // Graph prototype name in widget 'Dynamic widgets H1: Dynamic widgets GP1 (IP1)'.
									]
								]
							],
							[
								'type' => 'graphprototype',
								'x' => 24,
								'y' => 30,
								'width' => 24,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE,
										'name' => 'graphid',
										'value' => '700032' // Graph prototype name in widget 'Dynamic widgets H1: Dynamic widgets GP1 (IP1)'.
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
								'y' => 30,
								'width' => 24,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE,
										'name' => 'graphid',
										'value' => '700033' // Graph prototype name in widget 'Dynamic widgets H1: Dynamic widgets GP2 (I1, IP1, H1I2)'.
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
								'y' => 35,
								'width' => 24,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE,
										'name' => 'graphid',
										'value' => '700034' // Graph prototype name in widget 'Dynamic widgets H1: Dynamic widgets H1 GP3 (H1IP1)'.
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
								'y' => 35,
								'width' => 24,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE,
										'name' => 'graphid',
										'value' => '700035' // Graph prototype name in widget 'Dynamic widgets H1: Dynamic widgets H1 GP4 (H1IP1 and H2I1)'.
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
		self::$dashboardid = CDataHelper::getIds('name');
	}

	public static function getWidgetsData() {
		return [
			[
				[
					'widgets' => [
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I2'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I1'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I2'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G2 (I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G1 (I1)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G2 (I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G3 (I1 and I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1 G4 (H1I1 and H3I1)'],
						// TODO: will be fixed in terms of DEV-3728.
//						[
//							'type' => 'Plain text',
//							'header' => 'Dynamic widgets H1: Dynamic widgets H1I2',
//							'expected' => ['Dynamic widgets H1I2' => '12']
//						],
//						[
//							'type' => 'Plain text',
//							'header' => 'Dynamic widgets H1: Dynamic widgets H1I1',
//							'expected' => ['Dynamic widgets H1I1' => '11']
//						],
//						[
//							'type' => 'Plain text',
//							'header' => 'Dynamic widgets H1: Dynamic widgets H1I2',
//							'expected' => ['Dynamic widgets H1I2' => '12']
//						],
//						[
//							'type' => 'Plain text',
//							'header' => 'Dynamic widgets H1: 2 items',
//							'expected' => [
//								'Dynamic widgets H1I1' => '11',
//								'Dynamic widgets H1I2' => '12'
//							]
//						],
						['type' => 'Gauge', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I2'],
						['type' => 'Gauge', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I1'],
						[
							'type' => 'URL',
							'header' => 'Dynamic URL',
							'empty' => true
						],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1IP2'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1IP1'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1IP2'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets GP1 (IP1)'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets GP1 (IP1)'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets GP2 (I1, IP1, H1I2)'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 GP3 (H1IP1)'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 GP4 (H1IP1 and H2I1)']
					]
				]
			],
			[
				[
					'host_filter' => [
						'values' => 'Dynamic widgets H1',
						'context' => 'Dynamic widgets HG1 (H1 and H2)'
					],
					'widgets' => [
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I2'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I1'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I2'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G2 (I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G1 (I1)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G2 (I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G3 (I1 and I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G4 (H1I1 and H3I1)'],
						// TODO: will be fixed in terms of DEV-3728.
//						[
//							'type' => 'Plain text',
//							'header' => 'Dynamic widgets H1: Dynamic widgets H1I2',
//							'expected' => ['Dynamic widgets H1I2' => '12']
//						],
//						[
//							'type' => 'Plain text',
//							'header' => 'Dynamic widgets H1: Dynamic widgets H1I1',
//							'expected' => ['Dynamic widgets H1I1' => '11']
//						],
//						[
//							'type' => 'Plain text',
//							'header' => 'Dynamic widgets H1: Dynamic widgets H1I2',
//							'expected' => ['Dynamic widgets H1I2' => '12']
//						],
//						[
//							'type' => 'Plain text',
//							'header' => 'Dynamic widgets H1: 2 items',
//							'expected' => [
//								'Dynamic widgets H1I1' => '11',
//								'Dynamic widgets H1I2' => '12'
//							]
//						],
						['type' => 'Gauge', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I2'],
						['type' => 'Gauge', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I1'],
						[
							'type' => 'URL',
							'header' => 'Dynamic URL',
							'host' => 'Dynamic widgets H1'
						],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1IP2'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1IP1'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1IP2'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets GP1 (IP1)'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets GP1 (IP1)'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets GP2 (I1, IP1, H1I2)'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 GP3 (H1IP1)'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 GP4 (H1IP1 and H2I1)']
					]
				]
			],
			[
				[
					'host_filter' => 'Dynamic widgets H2',
					'widgets' => [
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I2'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H2: Dynamic widgets H2I1'],
						['type' => 'Graph (classic)', 'header' => 'Graph (classic)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G2 (I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H2: Dynamic widgets H1 G1 (I1)'],
						['type' => 'Graph (classic)', 'header' => 'Graph (classic)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H2: Dynamic widgets H1 G3 (I1 and I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H2: Dynamic widgets H1 G4 (H1I1 and H3I1)'],
						// TODO: will be fixed in terms of DEV-3728.
//						[
//							'type' => 'Plain text',
//							'header' => 'Dynamic widgets H1: Dynamic widgets H1I2',
//							'expected' => ['Dynamic widgets H1I2' => '12']
//						],
//						[
//							'type' => 'Plain text',
//							'header' => 'Dynamic widgets H2: Dynamic widgets H2I1',
//							'expected' => ['Dynamic widgets H2I1' => '21']
//						],
//						[
//							'type' => 'Plain text',
//							'header' => 'Plain text'
//						],
//						[
//							'type' => 'Plain text',
//							'header' => 'Dynamic widgets H2: Dynamic widgets H2I1',
//							'expected' => ['Dynamic widgets H2I1' => '21']
//						],
						['type' => 'Gauge', 'header' => 'Gauge'],
						['type' => 'Gauge', 'header' => 'Dynamic widgets H2: Dynamic widgets H2I1'],
						[
							'type' => 'URL',
							'header' => 'Dynamic URL',
							'host' => 'Dynamic widgets H2'
						],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1IP2'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H2: Dynamic widgets H2IP1'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets GP1 (IP1)'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H2: Dynamic widgets GP1 (IP1)'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H2: Dynamic widgets GP2 (I1, IP1, H1I2)'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype']
					]
				]
			],
			[
				[
					'host_filter' => 'Dynamic widgets H3',
					'widgets' => [
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I2'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H3: Dynamic widgets H3I1'],
						['type' => 'Graph (classic)', 'header' => 'Graph (classic)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G2 (I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H3: Dynamic widgets H1 G1 (I1)'],
						['type' => 'Graph (classic)', 'header' => 'Graph (classic)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H3: Dynamic widgets H1 G3 (I1 and I2)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H3: Dynamic widgets H1 G4 (H1I1 and H3I1)'],
						// TODO: will be fixed in terms of DEV-3728.
//						[
//							'type' => 'Plain text',
//							'header' => 'Dynamic widgets H1: Dynamic widgets H1I2',
//							'expected' => ['Dynamic widgets H1I2' => '12']
//						],
//						[
//							'type' => 'Plain text',
//							'header' => 'Dynamic widgets H3: Dynamic widgets H3I1',
//							'expected' => ['Dynamic widgets H3I1' => '31']
//						],
//						[
//							'type' => 'Plain text',
//							'header' => 'Plain text'
//						],
//						[
//							'type' => 'Plain text',
//							'header' => 'Dynamic widgets H3: Dynamic widgets H3I1',
//							'expected' => ['Dynamic widgets H3I1' => '31']
//						],
						['type' => 'Gauge', 'header' => 'Gauge'],
						['type' => 'Gauge', 'header' => 'Dynamic widgets H3: Dynamic widgets H3I1'],
						[
							'type' => 'URL',
							'header' => 'Dynamic URL',
							'host' => 'Dynamic widgets H3'
						],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1IP2'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets GP1 (IP1)'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype']
					]
				]
			],
			[
				[
					'host_filter' => 'Host for suppression',
					'widgets' => [
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1I2'],
						['type' => 'Graph (classic)', 'header' => 'Graph (classic)'],
						['type' => 'Graph (classic)', 'header' => 'Graph (classic)'],
						['type' => 'Graph (classic)', 'header' => 'Dynamic widgets H1: Dynamic widgets H1 G2 (I2)'],
						['type' => 'Graph (classic)', 'header' => 'Graph (classic)'],
						['type' => 'Graph (classic)', 'header' => 'Graph (classic)'],
						['type' => 'Graph (classic)', 'header' => 'Graph (classic)'],
						['type' => 'Graph (classic)', 'header' => 'Graph (classic)'],
						// TODO: will be fixed in terms of DEV-3728.
//						[
//							'type' => 'Plain text',
//							'header' => 'Dynamic widgets H1: Dynamic widgets H1I2',
//							'expected' => ['Dynamic widgets H1I2' => '12']
//						],
//						[
//							'type' => 'Plain text',
//							'header' => 'Plain text'
//						],
//						[
//							'type' => 'Plain text',
//							'header' => 'Plain text'
//						],
//						[
//							'type' => 'Plain text',
//							'header' => 'Plain text'
//						],
						['type' => 'Gauge', 'header' => 'Gauge'],
						['type' => 'Gauge', 'header' => 'Gauge'],
						[
							'type' => 'URL',
							'header' => 'Dynamic URL',
							'host' => 'Host for suppression'
						],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets H1IP2'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Dynamic widgets H1: Dynamic widgets GP1 (IP1)'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype'],
						['type' => 'Graph prototype', 'header' => 'Graph prototype']
					]
				]
			]
		];
	}

	/**
	 * @onBefore createTestFile
	 * @onAfter removeTestFile
	 *
	 * @dataProvider getWidgetsData
	 */
	public function testDashboardDynamicItemWidgets_Layout($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for Dynamic item']);
		$dashboard = CDashboardElement::find()->one();

		if (CTestArrayHelper::get($data, 'host_filter', false)) {
			$filter = $dashboard->getControls()->waitUntilVisible();
			$host = $filter->query('class:multiselect-control')->asMultiselect()->one();
			if (is_array($data['host_filter'])) {
				$host->setFillMode(CMultiselectElement::MODE_SELECT)->fill($data['host_filter']);
			}
			else {
				$host->clear()->type($data['host_filter']);
			}
			$this->page->waitUntilReady();
		}
		$this->query('xpath://div[contains(@class, "is-loading")]')->waitUntilNotPresent();
		// Show hidden headings of graph prototype.
		$this->page->getDriver()->executeScript('var elements = document.getElementsByClassName("dashboard-grid-iterator");'.
				' for (var i = 0; i < elements.length; i++) elements[i].className+=" dashboard-grid-iterator-focus";'
		);

		$this->assertWidgetContent($data['widgets']);

		// Check that after page refresh widgets remain the same.
		$this->page->refresh();
		$this->page->waitUntilReady();
		$this->assertWidgetContent($data['widgets']);
	}

	private function assertWidgetContent($data) {
		$dashboard = CDashboardElement::find()->one();
		$widgets = $dashboard->getWidgets();
		$this->assertEquals(count($data), $widgets->count());

		foreach ($data as $key => $expected) {
			$widget = $widgets->get($key);
			$widget->waitUntilReady();
			$widget_content = $widget->getContent();
			$this->assertEquals($expected['header'], $widget->getHeaderText());

			// Check widget empty content, because the host doesn't match dynamic option criteria.
			if ($expected['header'] === '' || $expected['header'] === $expected['type']
					|| CTestArrayHelper::get($expected, 'empty', false)) {
				$content = $widget_content->query('class:no-data-message')->one()->getText();
				$message = ($expected['type'] === 'URL')
						? 'No host selected.'
						: 'No permissions to referred object or it does not exist!';
				$this->assertEquals($message, $content);
				continue;
			}

			// Check widget content when the host match dynamic option criteria.
			$this->assertFalse($widget_content->query('class:no-data-message')->one(false)->isValid());

			if ($expected['type'] === 'URL') {
				// TODO: will be fixed in terms of DEV-3728.
//				case 'Plain text':
//					$data = $widget_content->asTable()->index('Name');
//					foreach ($expected['expected'] as $item => $value) {
//						$row = $data[$item];
//						$this->assertEquals($value, $row['Value']);
//					}
//					break;
				$this->page->switchTo($widget_content->query('id:iframe')->one());
				$params = json_decode($this->query('xpath://body')->one()->getText(), true);
				$this->assertEquals($expected['host'], $params['name']);
				$this->page->switchTo();
			}
		}
	}

	public function createTestFile() {
		if (file_put_contents(PHPUNIT_BASEDIR.'/ui/iframe.php', '<?php echo json_encode($_GET);') === false) {
			throw new Exception('Failed to create iframe test file.');
		}
	}

	public function removeTestFile() {
		@unlink(PHPUNIT_BASEDIR.'/ui/iframe.php');
	}
}

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

class TopHostsWidget {

	/**
	 * Id of items by name.
	 *
	 * @var integer
	 */
	protected static $itemids;

	/**
	 * Create data for testDashboardTopHostsWidget test.
	 *
	 * @return array
	 */
	public static function load() {

		// Create items with value type - text, log, character.
		CDataHelper::call('item.create', [
			[
				'name' => 'top_hosts_trap_text',
				'key_' => 'top_hosts_trap_text',
				'hostid' => 10084,
				'type' => 2,
				'value_type' => 4
			],
			[
				'name' => 'top_hosts_trap_log',
				'key_' => 'top_hosts_trap_log',
				'hostid' => 10084,
				'type' => 2,
				'value_type' => 2
			],
			[
				'name' => 'top_hosts_trap_char',
				'key_' => 'top_hosts_trap_char',
				'hostid' => 10084,
				'type' => 2,
				'value_type' => 1
			]
		]);

		self::$itemids = CDataHelper::getIds('name');

		// Add value to item displayed in Top Hosts widget.
		CDataHelper::addItemData(99086, 1000);
		CDataHelper::addItemData(self::$itemids['top_hosts_trap_text'], 'Text for text item');
		CDataHelper::addItemData(self::$itemids['top_hosts_trap_log'], 'Logs for text item');
		CDataHelper::addItemData(self::$itemids['top_hosts_trap_char'], 'characters_here');

		// Create dashboards for Top host widget testing.
		CDataHelper::call('dashboard.create', [
			[
				'name' => 'top_host_update',
				'display_period' => 30,
				'auto_start' => 1,
				'pages' => [
					[
						'name' => '',
						'widgets' => [
							[
								'type' => 'tophosts',
								'name' => 'Top hosts update',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 8,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'columns.0.name',
										'value' => 'test update column 1'
									],
									[
										'type' => 0,
										'name' => 'columns.0.data',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.0.item',
										'value' => 'Available memory'
									],
									[
										'type' => 1,
										'name' => 'columns.0.timeshift',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'columns.0.aggregate_function',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'columns.0.decimal_places',
										'value' =>  4
									],
									[
										'type' => 0,
										'name' => 'columns.0.display',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'columns.0.history',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.0.base_color',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'column',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.1.name',
										'value' => 'test update column 2'
									],
									[
										'type' => 0,
										'name' => 'columns.1.data',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.1.item',
										'value' => 'Available memory in %'
									],
									[
										'type' => 1,
										'name' => 'columns.1.timeshift',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'columns.1.aggregate_function',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'columns.1.decimal_places',
										'value' =>  2
									],
									[
										'type' => 0,
										'name' => 'columns.1.display',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'columns.1.history',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.1.base_color',
										'value' => ''
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.1.color.0',
										'value' => 'FF465C'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.1.threshold.0',
										'value' => '100'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.1.color.1',
										'value' => 'B0AF07'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.1.threshold.1',
										'value' => '600'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.0.color.0',
										'value' => 'FF465C'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.0.threshold.0',
										'value' => '100'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.0.color.1',
										'value' => 'B0AF07'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.0.threshold.1',
										'value' => '600'
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'top_host_create',
				'display_period' => 30,
				'auto_start' => 1,
				'pages' => [[]]
			],
			[
				'name' => 'top_host_delete',
				'display_period' => 30,
				'auto_start' => 1,
				'pages' => [
					[
						'name' => '',
						'widgets' => [
							[
								'type' => 'tophosts',
								'name' => 'Top hosts delete',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 8,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'columns.0.name',
										'value' => 'delete widget column 1'
									],
									[
										'type' => 0,
										'name' => 'columns.0.data',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.0.item',
										'value' => 'Available memory'
									],
									[
										'type' => 1,
										'name' => 'columns.0.timeshift',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'columns.0.aggregate_function',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'columns.0.display',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'columns.0.history',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.0.base_color',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'column',
										'value' => 0
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'top_host_remove',
				'display_period' => 30,
				'auto_start' => 1,
				'pages' => [
					[
						'name' => '',
						'widgets' => [
							[
								'type' => 'tophosts',
								'name' => 'Top hosts for remove',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 8,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'columns.0.name',
										'value' => 'remove top hosts column 1'
									],
									[
										'type' => 0,
										'name' => 'columns.0.data',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.0.item',
										'value' => 'Available memory'
									],
									[
										'type' => 1,
										'name' => 'columns.0.timeshift',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'columns.0.aggregate_function',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'columns.0.display',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'columns.0.history',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.0.base_color',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'column',
										'value' => 0
									],
									[
										'type' => 1,
										'name' => 'columns.1.name',
										'value' => 'remove top hosts column 2'
									],
									[
										'type' => 0,
										'name' => 'columns.1.data',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'columns.1.aggregate_function',
										'value' => 0
									],
									[
										'type' => 1,
										'name' => 'columns.1.base_color',
										'value' => ''
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.0.color.0',
										'value' => 'FF465C'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.0.threshold.0',
										'value' => '100'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.0.color.1',
										'value' => '4000FF'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.0.threshold.1',
										'value' => '1000'
									],
									[
										'type' => 1,
										'name' => 'tags.0.tag',
										'value' => 'tag1'
									],
									[
										'type' => 0,
										'name' => 'tags.0.operator',
										'value' => 0
									],
									[
										'type' => 1,
										'name' => 'tags.0.value',
										'value' => 'val1'
									],
									[
										'type' => 1,
										'name' => 'tags.1.tag',
										'value' => 'tag2'
									],
									[
										'type' => 0,
										'name' => 'tags.1.operator',
										'value' => 0
									],
									[
										'type' => 1,
										'name' => 'tags.1.value',
										'value' => 'val2'
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'top_host_screenshots',
				'display_period' => 30,
				'auto_start' => 1,
				'pages' => [[]]
			],
			[
				'name' => 'top_host_text_items',
				'display_period' => 30,
				'auto_start' => 1,
				'pages' => [[]]
			]
		]);

		return [
			'dashboardids' => CDataHelper::getIds('name')
		];
	}
}

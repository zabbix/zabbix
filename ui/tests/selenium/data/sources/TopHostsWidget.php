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
				'name' => 'trap_text',
				'key_' => 'trap_text',
				'hostid' => 10084,
				'type' => 2,
				'value_type' => 4
			],
			[
				'name' => 'trap_log',
				'key_' => 'trap_log',
				'hostid' => 10084,
				'type' => 2,
				'value_type' => 2
			],
			[
				'name' => 'trap_char',
				'key_' => 'trap_char',
				'hostid' => 10084,
				'type' => 2,
				'value_type' => 1
			]
		]);

		self::$itemids = CDataHelper::getIds('name');

		// Add value to item displayed in Top Hosts widget.
		CDataHelper::addItemData(99086, 1000);
		CDataHelper::addItemData(self::$itemids['trap_text'], 'Text for text item');
		CDataHelper::addItemData(self::$itemids['trap_log'], 'Logs for text item');
		CDataHelper::addItemData(self::$itemids['trap_char'], 'characters_here');

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
										'name' => 'columns.name.0',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'columns.data.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.item.0',
										'value' => 'Available memory'
									],
									[
										'type' => 1,
										'name' => 'columns.timeshift.0',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'columns.aggregate_function.0',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'columns.display.0',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'columns.history.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.base_color.0',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'column',
										'value' => 0
									],
									[
										'type' => 1,
										'name' => 'columns.name.1',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'columns.data.1',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.item.1',
										'value' => 'Available memory in %'
									],
									[
										'type' => 1,
										'name' => 'columns.timeshift.1',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'columns.aggregate_function.1',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'columns.display.1',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'columns.history.1',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.base_color.1',
										'value' => ''
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.color.1.0',
										'value' => 'FF465C'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.threshold.1.0',
										'value' => '100'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.color.1.1',
										'value' => 'B0AF07'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.threshold.1.1',
										'value' => '600'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.color.0.0',
										'value' => 'FF465C'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.threshold.0.0',
										'value' => '100'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.color.0.1',
										'value' => 'B0AF07'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.threshold.0.1',
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
										'name' => 'columns.name.0',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'columns.data.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.item.0',
										'value' => 'Available memory'
									],
									[
										'type' => 1,
										'name' => 'columns.timeshift.0',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'columns.aggregate_function.0',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'columns.display.0',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'columns.history.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.base_color.0',
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
										'name' => 'columns.name.0',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'columns.data.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.item.0',
										'value' => 'Available memory'
									],
									[
										'type' => 1,
										'name' => 'columns.timeshift.0',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'columns.aggregate_function.0',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'columns.display.0',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'columns.history.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'columns.base_color.0',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'column',
										'value' => 0
									],
									[
										'type' => 1,
										'name' => 'columns.name.1',
										'value' => ''
									],
									[
										'type' => 0,
										'name' => 'columns.data.1',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'columns.aggregate_function.1',
										'value' => 0
									],
									[
										'type' => 1,
										'name' => 'columns.base_color.1',
										'value' => ''
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.color.0.0',
										'value' => 'FF465C'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.threshold.0.0',
										'value' => '100'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.color.0.1',
										'value' => '4000FF'
									],
									[
										'type' => 1,
										'name' => 'columnsthresholds.threshold.0.1',
										'value' => '1000'
									],
									[
										'type' => 1,
										'name' => 'tags.tag.0',
										'value' => 'tag1'
									],
									[
										'type' => 0,
										'name' => 'tags.operator.0',
										'value' => 0
									],
									[
										'type' => 1,
										'name' => 'tags.value.0',
										'value' => 'val1'
									],
									[
										'type' => 1,
										'name' => 'tags.tag.1',
										'value' => 'tag2'
									],
									[
										'type' => 0,
										'name' => 'tags.operator.1',
										'value' => 0
									],
									[
										'type' => 1,
										'name' => 'tags.value.1',
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

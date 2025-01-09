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


class ItemValueWidget {

	public static function load() {
		// Create host for aggregation data tests.
		$hosts = CDataHelper::createHosts([
			[
				'host' => 'Simple host with items for item value widget test',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.9.7',
						'dns' => '',
						'port' => '10011'
					]
				],
				'groups' => [
					'groupid' => '4' // 'Zabbix servers' group.
				],
				'items' => [
					[
						'name' => 'Item with type of information - numeric (float)',
						'key_' => 'numeric_float',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_FLOAT,
						'delay' => '30'
					],
					[
						'name' => 'Item with type of information - Character',
						'key_' => 'character',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_STR,
						'delay' => '30'
					],
					[
						'name' => 'Item with type of information - Log',
						'key_' => 'log',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_LOG,
						'delay' => '30'
					],
					[
						'name' => 'Item with type of information - numeric (unsigned)',
						'key_' => 'numeric_unsigned',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => '30'
					],
					[
						'name' => 'Item with type of information - Text',
						'key_' => 'text',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_TEXT,
						'delay' => '30'
					],
					[
						'name' => 'Item with units',
						'key_' => 'vm.memory.size[pavailable]',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_FLOAT,
						'units' => '%',
						'delay' => '30'
					]
				]
			],
			[
				'host' => 'Host for valuemapping test',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.9.8',
						'dns' => '',
						'port' => '10012'
					]
				],
				'groups' => [
					'groupid' => '4' // 'Zabbix servers' group.
				],
				'items' => [
					[
						'name' => 'Value mapping',
						'key_' => 'agent.ping',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => '30'
					]
				]
			]
		]);
		$itemids = CDataHelper::getIds('name');

		CDataHelper::call('valuemap.create', [
			[
				'name' => 'Value mapping for item value widget',
				'hostid' => $hosts['hostids']['Host for valuemapping test'],
				'mappings' => [
					[
						'type' => VALUEMAP_MAPPING_TYPE_EQUAL,
						'value' => '1',
						'newvalue' => 'Up'
					],
					[
						'type' => VALUEMAP_MAPPING_TYPE_EQUAL,
						'value' => '0',
						'newvalue' => 'Down'
					]
				]
			]
		]);
		$valuemapids = CDataHelper::getIds('name');

		CDataHelper::call('item.update', [
			[
				'itemid' => $itemids['Value mapping'],
				'valuemapid' => $valuemapids['Value mapping for item value widget']
			]
		]);

		CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for Single Item value Widget test',
				'pages' => [
					[
						'name' => 'Page with widgets',
						'widgets' => [
							[
								'type' => 'item',
								'name' => 'New widget',
								'x' => 0,
								'y' => 0,
								'width' => 24,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid.0',
										'value' => 42230 // Linux: CPU user time.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'description',
										'value' => 'Some description here. Описание.'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'desc_h_pos',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'desc_v_pos',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'time_h_pos',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'time_v_pos',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'desc_size',
										'value' => 17
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'decimal_size',
										'value' => 41
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'value_size',
										'value' => 56
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'time_size',
										'value' => 14
									]
								]
							],
							[
								'type' => 'item',
								'name' => 'Widget with thresholds',
								'x' => 0,
								'y' => 4,
								'width' => 24,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid.0',
										'value' => 42230 // Linux: CPU user time.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'thresholds.0.color',
										'value' => 'BF00FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'thresholds.0.threshold',
										'value' => '0'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'thresholds.1.color',
										'value' => 'FF0080'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'thresholds.1.threshold',
										'value' => '0.01'
									]
								]
							],
							[
								'type' => 'graph',
								'name' => 'Graph (classic) for time period',
								'x' => 24,
								'y' => 0,
								'width' => 12,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid.0',
										'value' => 2232 // Linux: CPU utilization.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.from',
										'value' => 'now-2h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'time_period.to',
										'value' => 'now-1h'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'EDTTX'
									]
								]
							],
							[
								'type' => 'item',
								'name' => 'Widget to delete',
								'x' => 36,
								'y' => 0,
								'width' => 12,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid.0',
										'value' => 42230 // Linux: CPU user time.
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for zoom filter check',
				'pages' => [
					[
						'name' => 'Page with widgets'
					]
				]
			],
			[
				'name' => 'Dashboard for threshold(s) check',
				'pages' => [
					[
						'name' => 'Page with widgets'
					]
				]
			],
			[
				'name' => 'Dashboard for aggregation function data check',
				'pages' => [
					[
						'name' => 'Page with widgets',
						'widgets' => [
							[
								'type' => 'item',
								'name' => 'Widget for aggregation function data check',
								'x' => 0,
								'y' => 0,
								'width' => 54,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid.0',
										'value' => 42230 // Linux: CPU user time.
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

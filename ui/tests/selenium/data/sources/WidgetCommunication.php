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


class WidgetCommunication {

	const FIRST_HOST_NAME = '1st host for widgets';
	const SECOND_HOST_NAME = '2nd host for widgets';
	const THIRD_HOST_NAME = '3rd host for widgets';
	const FIRST_HOSTGROUP_NAME = '1st hostgroup for widgets';
	const SECOND_HOSTGROUP_NAME = '2nd hostgroup for widgets';
	const THIRD_HOSTGROUP_NAME = '3rd hostgroup for widgets';
	const FIRST_HOST_TRIGGER = 'trigger on host 1';
	const SECOND_HOST_TRIGGER = 'trigger on host 2';
	const THIRD_HOST_TRIGGER = 'trigger on host 3';
	const MAP_NAME = 'Map for testing feedback';
	const SUBMAP_NAME = 'Map for widget communication test';

	public static function load() {
		// Create host groups.
		CDataHelper::call('hostgroup.create', [
			['name' => self::FIRST_HOSTGROUP_NAME],
			['name' => self::SECOND_HOSTGROUP_NAME],
			['name' => self::THIRD_HOSTGROUP_NAME]
		]);
		$host_groupids = CDataHelper::getIds('name');

		// Create hosts.
		$host_response = CDataHelper::createHosts([
			[
				'host' => self::FIRST_HOST_NAME,
				'interfaces' => [
					'type' => INTERFACE_TYPE_AGENT,
					'main' => INTERFACE_PRIMARY,
					'useip' => INTERFACE_USE_IP,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => 10050
				],
				'groups' => [
					'groupid' => $host_groupids[self::FIRST_HOSTGROUP_NAME]
				],
				'inventory_mode' => HOST_INVENTORY_MANUAL,
				'inventory' => [
					'location_lat' => '59.4370',
					'location_lon' => '24.7536'
				],
				'status' => HOST_STATUS_MONITORED,
				'tags' => [
					[
						'tag' => 'widget',
						'value' => 'communication host'
					]
				],
				'items' => [
					[
						'name' => 'Trapper item',
						'key_' => 'trap.widget.communication',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'tags' => [
							[
								'tag' => 'item',
								'value' => 'widget communication'
							]
						]
					]
				]
			],
			[
				'host' => self::SECOND_HOST_NAME,
				'interfaces' => [
					'type' => INTERFACE_TYPE_IPMI,
					'main' => INTERFACE_PRIMARY,
					'useip' => INTERFACE_USE_IP,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => 12345
				],
				'groups' => [
					'groupid' => $host_groupids[self::SECOND_HOSTGROUP_NAME]
				],
				'status' => HOST_STATUS_MONITORED,
				'inventory_mode' => HOST_INVENTORY_MANUAL,
				'inventory' => [
					'location_lat' => '56.95387',
					'location_lon' => '24.22067'
				],
				'tags' => [
					[
						'tag' => 'widget',
						'value' => 'communication host'
					]
				],
				'items' => [
					[
						'name' => 'Trapper item',
						'key_' => 'trap.widget.communication',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'tags' => [
							[
								'tag' => 'item',
								'value' => 'widget communication'
							]
						]
					]
				]
			],
			[
				'host' => self::THIRD_HOST_NAME,
				'interfaces' => [
					'type' => INTERFACE_TYPE_JMX,
					'main' => INTERFACE_PRIMARY,
					'useip' => INTERFACE_USE_IP,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => 623
				],
				'groups' => [
					'groupid' => $host_groupids[self::THIRD_HOSTGROUP_NAME]
				],
				'status' => HOST_STATUS_MONITORED,
				'inventory_mode' => HOST_INVENTORY_MANUAL,
				'inventory' => [
					'location_lat' => '54.6872',
					'location_lon' => '25.2797'
				],
				'tags' => [
					[
						'tag' => 'widget',
						'value' => 'communication host'
					]
				],
				'items' => [
					[
						'name' => 'Trapper item',
						'key_' => 'trap.widget.communication',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'tags' => [
							[
								'tag' => 'item',
								'value' => 'widget communication'
							]
						]
					]
				]
			]
		]);

		$hostids = $host_response['hostids'];
		$itemids = $host_response['itemids'];
		$item_data_timestamp = time();

		// Send values 3, 4 and 5 to the created items.
		foreach (array_values($itemids) as $i => $itemid) {
			CDataHelper::addItemData($itemid, [$i + 3, $i + 3], [$item_data_timestamp - 1800, $item_data_timestamp]);
		}

		// Create host triggers.
		CDataHelper::call('trigger.create', [
			[
				'description' => self::FIRST_HOST_TRIGGER,
				'expression' => 'last(/'.self::FIRST_HOST_NAME.'/trap.widget.communication)<>0',
				'priority' => TRIGGER_SEVERITY_INFORMATION
			],
			[
				'description' => self::SECOND_HOST_TRIGGER,
				'expression' => 'last(/'.self::SECOND_HOST_NAME.'/trap.widget.communication)<>0',
				'priority' => TRIGGER_SEVERITY_WARNING
			],
			[
				'description' => self::THIRD_HOST_TRIGGER,
				'expression' => 'last(/'.self::THIRD_HOST_NAME.'/trap.widget.communication)<>0',
				'priority' => TRIGGER_SEVERITY_HIGH
			]
		]);
		$triggerids = CDataHelper::getIds('description');

		// Set created triggers to problem status
		CDBHelper::setTriggerProblem(array_keys($triggerids), TRIGGER_VALUE_TRUE, ['clock' => $item_data_timestamp]);

		// Create host web scenarios.
		CDataHelper::call('httptest.create', [
			[
				'name' => 'Web scenario 1st host for widget communication',
				'hostid' => $hostids[self::FIRST_HOST_NAME],
				'steps' => [
					[
						'name' => 'Homepage 1st host',
						'url' => 'https://zabbix.com',
						'no' => 1
					]
				],
				'tags' => [
					[
						'tag' => 'widget',
						'value' => 'communication'
					]
				]
			],
			[
				'name' => 'Web scenario 2nd host for widget communication',
				'hostid' => $hostids[self::SECOND_HOST_NAME],
				'steps' => [
					[
						'name' => 'Homepage 2nd host',
						'url' => 'https://zabbix.com',
						'no' => 1
					]
				],
				'tags' => [
					[
						'tag' => 'widget',
						'value' => 'communication'
					]
				]
			],
			[
				'name' => 'Web scenario 3rd host for widget communication',
				'hostid' => $hostids[self::THIRD_HOST_NAME],
				'steps' => [
					[
						'name' => 'Homepage 3rd host',
						'url' => 'https://zabbix.com',
						'no' => 1
					]
				],
				'tags' => [
					[
						'tag' => 'widget',
						'value' => 'communication'
					]
				]
			]
		]);

		// Create a map to be displayed on the Map widget.
		$submapid = CDataHelper::call('map.create', [
			[
				'name' => self::SUBMAP_NAME,
				'height' => 400,
				'width' => 500,
				'selements' => [
					[
						'selementid' => 1,
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST_GROUP,
						'elements' => [
							['groupid' => $host_groupids[self::FIRST_HOSTGROUP_NAME]]
						],
						'label' => self::FIRST_HOSTGROUP_NAME,
						'iconid_off' => 136, // SAN_(96) element icon.
						'x' => 50,
						'y' => 30,
						'width' => 200,
						'heidght' => 200
					],
					[
						'selementid' => 2,
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST_GROUP,
						'elements' => [
							['groupid' => $host_groupids[self::SECOND_HOSTGROUP_NAME]]
						],
						'label' => self::SECOND_HOSTGROUP_NAME,
						'iconid_off' => 136, // SAN_(96) element icon.
						'x' => 190,
						'y' => 90,
						'width' => 200,
						'heidght' => 200
					],
					[
						'selementid' => 3,
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST_GROUP,
						'elements' => [
							['groupid' => $host_groupids[self::THIRD_HOSTGROUP_NAME]]
						],
						'label' => self::THIRD_HOSTGROUP_NAME,
						'iconid_off' => 136, // SAN_(96) element icon.
						'x' => 340,
						'y' => 30,
						'width' => 200,
						'heidght' => 200
					],
					[
						'selementid' => 4,
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
						'elements' => [
							['hostid' => $hostids[self::FIRST_HOST_NAME]]
						],
						'label' => self::FIRST_HOST_NAME,
						'iconid_off' => 151, // Server_(96) element icon.
						'x' => 50,
						'y' => 180,
						'width' => 200,
						'heidght' => 200
					],
					[
						'selementid' => 5,
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
						'elements' => [
							['hostid' => $hostids[self::SECOND_HOST_NAME]]
						],
						'label' => self::SECOND_HOST_NAME,
						'iconid_off' => 151, // Server_(96) element icon.
						'x' => 190,
						'y' => 230,
						'width' => 200,
						'heidght' => 200
					],
					[
						'selementid' => 6,
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
						'elements' => [
							['hostid' => $hostids[self::THIRD_HOST_NAME]]
						],
						'label' => self::THIRD_HOST_NAME,
						'iconid_off' => 151, // Server_(96) element icon.
						'x' => 340,
						'y' => 180,
						'width' => 200,
						'heidght' => 200
					]
				]
			]
		])['sysmapids'][0];

		// Create a map that contains the previously created submap.
		$mapid = CDataHelper::call('map.create', [
			[
				'name' => self::MAP_NAME,
				'height' => 400,
				'width' => 500,
				'selements' => [
					[
						'elementtype' => SYSMAP_ELEMENT_TYPE_MAP,
						'elements' => [
							['sysmapid' => $submapid]
						],
						'label' => self::SUBMAP_NAME,
						'iconid_off' => 35, // House_(96) element icon.
						'x' => 50,
						'y' => 50,
						'width' => 200,
						'heidght' => 200
					]
				]
			]
		])['sysmapids'][0];

		$dashboard_response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Widget communication test dashboard',
				'private' => PUBLIC_SHARING,
				'auto_start' => 0,
				'pages' => [
					[
						'name' => 'Hostgroups page',
						'widgets' => [
							[
								'type' => 'map',
								'name' => 'Map hostgroup broadcaster',
								'x' => 0,
								'y' => 0,
								'width' => 19,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_MAP,
										'name' => 'sysmapid.0',
										'value' => $submapid
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'NRDLG'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'problemhosts',
								'name' => 'Problem hosts hostgroup broadcaster',
								'x' => 0,
								'y' => 5,
								'width' => 19,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.0',
										'value' => $hostids[self::FIRST_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.1',
										'value' => $hostids[self::SECOND_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.2',
										'value' => $hostids[self::THIRD_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'EKBHR'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'problemsbysv',
								'name' => 'Problems by severity hostgroup broadcaster',
								'x' => 0,
								'y' => 8,
								'width' => 19,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.0',
										'value' => $hostids[self::FIRST_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.1',
										'value' => $hostids[self::SECOND_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.2',
										'value' => $hostids[self::THIRD_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'ZYWLY'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'web',
								'name' => 'Web monitoring hostgroup broadcaster',
								'x' => 0,
								'y' => 11,
								'width' => 19,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.0',
										'value' => $hostids[self::FIRST_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.1',
										'value' => $hostids[self::SECOND_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.2',
										'value' => $hostids[self::THIRD_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'XTPSV'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'topitems',
								'name' => 'Top items listener',
								'x' => 21,
								'y' => 0,
								'width' => 19,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'groupids._reference',
										'value' => 'NRDLG._hostgroupids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.item_tags.0.tag',
										'value' => 'item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.0.item_tags.0.operator',
										'value' => TAG_OPERATOR_LIKE
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.item_tags.0.value',
										'value' => 'widget communication'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.items.0',
										'value' => 'Trapper item'
									]
								]
							],
							[
								'type' => 'geomap',
								'name' => 'Geomap listener',
								'x' => 40,
								'y' => 0,
								'width' => 14,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'groupids._reference',
										'value' => 'NRDLG._hostgroupids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'default_view',
										'value' => '56.9,24.1,5'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'PANTF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'tags.0.tag',
										'value' => 'widget'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'tags.0.operator',
										'value' => TAG_OPERATOR_LIKE
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'tags.0.value',
										'value' => 'communication host'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'honeycomb',
								'name' => 'Honeycomb listener',
								'x' => 54,
								'y' => 0,
								'width' => 18,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'groupids._reference',
										'value' => 'NRDLG._hostgroupids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'items.0',
										'value' => 'Trapper item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'IFELH'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'hostavail',
								'name' => 'Host availability listener',
								'x' => 21,
								'y' => 5,
								'width' => 29,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'groupids._reference',
										'value' => 'NRDLG._hostgroupids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'problemhosts',
								'name' => 'Problem hosts listener',
								'x' => 50,
								'y' => 5,
								'width' => 22,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'groupids._reference',
										'value' => 'NRDLG._hostgroupids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.0',
										'value' => $hostids[self::FIRST_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.1',
										'value' => $hostids[self::SECOND_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.2',
										'value' => $hostids[self::THIRD_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'ZTWBF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'problemsbysv',
								'name' => 'Problems by severity listener',
								'x' => 21,
								'y' => 8,
								'width' => 26,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'groupids._reference',
										'value' => 'NRDLG._hostgroupids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.0',
										'value' => $hostids[self::FIRST_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.1',
										'value' => $hostids[self::SECOND_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.2',
										'value' => $hostids[self::THIRD_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'EFFAU'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'trigover',
								'name' => 'Trigger overview listener',
								'x' => 21,
								'y' => 11,
								'width' => 26,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'groupids._reference',
										'value' => 'NRDLG._hostgroupids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.0',
										'value' => $hostids[self::FIRST_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.1',
										'value' => $hostids[self::SECOND_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.2',
										'value' => $hostids[self::THIRD_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'layout',
										'value' => STYLE_TOP
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'tophosts',
								'name' => 'Top hosts listener',
								'x' => 47,
								'y' => 8,
								'width' => 25,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.name',
										'value' => 'Hostname'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.0.data',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.0.aggregate_function',
										'value' => AGGREGATE_NONE
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.0.decimal_places',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.base_color',
										'value' => ''
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.1.name',
										'value' => 'Item value'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.data',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.1.item',
										'value' => 'Trapper item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.aggregate_function',
										'value' => AGGREGATE_NONE
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.decimal_places',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.display',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.history',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.1.base_color',
										'value' => ''
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'column',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'SBWZE'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'groupids._reference',
										'value' => 'NRDLG._hostgroupids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'web',
								'name' => 'Web monitoring listener',
								'x' => 47,
								'y' => 11,
								'width' => 25,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'groupids._reference',
										'value' => 'NRDLG._hostgroupids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.0',
										'value' => $hostids[self::FIRST_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.1',
										'value' => $hostids[self::SECOND_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.2',
										'value' => $hostids[self::THIRD_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'CBUEA'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							]
						]
					],
					[
						'name' => 'Hosts page',
						'widgets' => [
							[
								'type' => 'geomap',
								'name' => 'Geomap host broadcaster',
								'x' => 0,
								'y' => 0,
								'width' => 20,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.0',
										'value' => $host_groupids[self::FIRST_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.1',
										'value' => $host_groupids[self::SECOND_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.2',
										'value' => $host_groupids[self::THIRD_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'JRVYU'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'default_view',
										'value' => '56.9,24.1,5'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'honeycomb',
								'name' => 'Honeycomb host broadcaster',
								'x' => 0,
								'y' => 5,
								'width' => 20,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'items.0',
										'value' => 'Trapper item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'RICVX'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'map',
								'name' => 'Map host broadcaster',
								'x' => 0,
								'y' => 8,
								'width' => 20,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_MAP,
										'name' => 'sysmapid.0',
										'value' => $submapid
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'BFSOY'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'tophosts',
								'name' => 'Top hosts host broadcaster',
								'x' => 0,
								'y' => 13,
								'width' => 25,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.0',
										'value' => $host_groupids[self::FIRST_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.1',
										'value' => $host_groupids[self::SECOND_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.2',
										'value' => $host_groupids[self::THIRD_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.name',
										'value' => 'Hostname'
									],

									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.0.data',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.0.aggregate_function',
										'value' => AGGREGATE_NONE
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.0.decimal_places',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.base_color',
										'value' => ''
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.1.name',
										'value' => 'Item value'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.data',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.1.item',
										'value' => 'Trapper item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.aggregate_function',
										'value' => AGGREGATE_NONE
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.decimal_places',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.display',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.history',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.1.base_color',
										'value' => ''
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'column',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'ACGKU'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'hostnavigator',
								'name' => 'Host navigator broadcaster',
								'x' => 0,
								'y' => 16,
								'width' => 25,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'hosts.0',
										'value' => self::FIRST_HOST_NAME
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'hosts.1',
										'value' => self::SECOND_HOST_NAME
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'hosts.2',
										'value' => self::THIRD_HOST_NAME
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'HSTNV'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'topitems',
								'name' => 'Top items listener',
								'x' => 21,
								'y' => 0,
								'width' => 15,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'hostids._reference',
										'value' => 'JRVYU._hostids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.item_tags.0.tag',
										'value' => 'item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.0.item_tags.0.operator',
										'value' => TAG_OPERATOR_LIKE
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.item_tags.0.value',
										'value' => 'widget communication'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.items.0',
										'value' => 'Trapper item'
									]
								]
							],
							[
								'type' => 'geomap',
								'name' => 'Geomap listener',
								'x' => 36,
								'y' => 0,
								'width' => 16,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'hostids._reference',
										'value' => 'JRVYU._hostids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'default_view',
										'value' => '56.9,24.1,5'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'tags.0.tag',
										'value' => 'widget'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'tags.0.operator',
										'value' => TAG_OPERATOR_LIKE
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'tags.0.value',
										'value' => 'communication host'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'PANTF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'honeycomb',
								'name' => 'Honeycomb listener',
								'x' => 52,
								'y' => 0,
								'width' => 20,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'hostids._reference',
										'value' => 'JRVYU._hostids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'items.0',
										'value' => 'Trapper item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'ARLLZ'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'problemhosts',
								'name' => 'Problem hosts listener',
								'x' => 21,
								'y' => 5,
								'width' => 15,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'hostids._reference',
										'value' => 'JRVYU._hostids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.0',
										'value' => $host_groupids[self::FIRST_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.1',
										'value' => $host_groupids[self::SECOND_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.2',
										'value' => $host_groupids[self::THIRD_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'GSOFI'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'problems',
								'name' => 'Problems listener',
								'x' => 36,
								'y' => 5,
								'width' => 16,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'hostids._reference',
										'value' => 'JRVYU._hostids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.0',
										'value' => $host_groupids[self::FIRST_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.1',
										'value' => $host_groupids[self::SECOND_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.2',
										'value' => $host_groupids[self::THIRD_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'DNQVG'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'problemsbysv',
								'name' => 'Problems by severity listener',
								'x' => 52,
								'y' => 5,
								'width' => 20,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'hostids._reference',
										'value' => 'JRVYU._hostids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.0',
										'value' => $host_groupids[self::FIRST_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.1',
										'value' => $host_groupids[self::SECOND_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.2',
										'value' => $host_groupids[self::THIRD_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'VFEIO'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'tophosts',
								'name' => 'Top hosts listener',
								'x' => 21,
								'y' => 8,
								'width' => 16,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'hostids._reference',
										'value' => 'JRVYU._hostids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.name',
										'value' => 'Hostname'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.0.data',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.0.aggregate_function',
										'value' => AGGREGATE_NONE
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.0.decimal_places',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.base_color',
										'value' => ''
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.1.name',
										'value' => 'Item value'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.data',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.1.item',
										'value' => 'Trapper item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.aggregate_function',
										'value' => AGGREGATE_NONE
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.decimal_places',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.display',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.history',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.1.base_color',
										'value' => ''
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'EOTLD'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'trigover',
								'name' => 'Trigger overview listener',
								'x' => 37,
								'y' => 8,
								'width' => 16,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'hostids._reference',
										'value' => 'JRVYU._hostids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.0',
										'value' => $host_groupids[self::FIRST_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.1',
										'value' => $host_groupids[self::SECOND_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.2',
										'value' => $host_groupids[self::THIRD_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'layout',
										'value' => STYLE_TOP
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'web',
								'name' => 'Web monitoring listener',
								'x' => 53,
								'y' => 8,
								'width' => 19,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'hostids._reference',
										'value' => 'JRVYU._hostids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.0',
										'value' => $host_groupids[self::FIRST_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.1',
										'value' => $host_groupids[self::SECOND_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.2',
										'value' => $host_groupids[self::THIRD_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'RYFLP'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'hostcard',
								'name' => 'Host card listener',
								'x' => 29,
								'y' => 12,
								'width' => 24,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'hostid._reference',
										'value' => 'JRVYU._hostid'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.0',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.1',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.2',
										'value' => 4
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							]
						]
					],
					[
						'name' => 'Items page',
						'widgets' => [
							[
								'type' => 'honeycomb',
								'name' => 'Honeycomb item broadcaster',
								'x' => 0,
								'y' => 0,
								'width' => 24,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'items.0',
										'value' => 'Trapper item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'QFWQX'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'itemhistory',
								'name' => 'Item history item broadcaster',
								'x' => 0,
								'y' => 5,
								'width' => 24,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'ZNLUI'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.name',
										'value' => self::FIRST_HOST_NAME.': Trapper item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.0.itemid',
										'value' => $itemids[self::FIRST_HOST_NAME.':trap.widget.communication']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.1.name',
										'value' => self::SECOND_HOST_NAME.': Trapper item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.1.itemid',
										'value' => $itemids[self::SECOND_HOST_NAME.':trap.widget.communication']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.2.name',
										'value' => self::THIRD_HOST_NAME.': Trapper item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.2.itemid',
										'value' => $itemids[self::THIRD_HOST_NAME.':trap.widget.communication']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'itemnavigator',
								'name' => 'Item navigator broadcaster',
								'x' => 0,
								'y' => 10,
								'width' => 24,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.0',
										'value' => $hostids[self::FIRST_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.1',
										'value' => $hostids[self::SECOND_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_HOST,
										'name' => 'hostids.2',
										'value' => $hostids[self::THIRD_HOST_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'item_tags.0.tag',
										'value' => 'item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'item_tags.0.operator',
										'value' => TAG_OPERATOR_LIKE
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'item_tags.0.value',
										'value' => 'widget communication'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'ITMNV'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'gauge',
								'name' => 'Gauge listener',
								'x' => 27,
								'y' => 0,
								'width' => 18,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'itemid._reference',
										'value' => 'QFWQX._itemid'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'min',
										'value' => '0'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'max',
										'value' => '10'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'graph',
								'name' => 'Graph (classic) listener',
								'x' => 45,
								'y' => 0,
								'width' => 27,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'source_type',
										'value' => ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'itemid._reference',
										'value' => 'QFWQX._itemid'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'JEUHW'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'item',
								'name' => 'Item value listener',
								'x' => 27,
								'y' => 4,
								'width' => 18,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'itemid._reference',
										'value' => 'QFWQX._itemid'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'decimal_places',
										'value' => 0
									]
								]
							],
							[
								'type' => 'svggraph',
								'name' => 'SVG graph listener',
								'x' => 45,
								'y' => 4,
								'width' => 27,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.itemids.0._reference',
										'value' => 'QFWQX._itemid'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color.0',
										'value' => '0040FF'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.dataset_type',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'righty',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'TJJDM'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.itemids.1._reference',
										'value' => 'ZNLUI._itemid'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color.1',
										'value' => 'FF465C'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'piechart',
								'name' => 'Pie chart listener',
								'x' => 27,
								'y' => 7,
								'width' => 18,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.dataset_type',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.itemids.0._reference',
										'value' => 'QFWQX._itemid'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color.0',
										'value' => 'FFD54F'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.type.0',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.itemids.1._reference',
										'value' => 'ZNLUI._itemid'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'ds.0.color.1',
										'value' => 'FF465C'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'ds.0.type.1',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							// Widget to check name change when clicking on item in broadcaster widget.
							[
								'type' => 'item',
								'name' => '',
								'x' => 45,
								'y' => 7,
								'width' => 18,
								'height' => 3,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'itemid._reference',
										'value' => 'QFWQX._itemid'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'decimal_places',
										'value' => 0
									]
								]
							]
						]
					],
					[
						'name' => 'Maps page',
						'widgets' => [
							[
								'type' => 'navtree',
								'name' => 'Navigation tree map broadcaster',
								'x' => 0,
								'y' => 0,
								'width' => 20,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'navtree.1.name',
										'value' => self::MAP_NAME
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_MAP,
										'name' => 'navtree.1.sysmapid',
										'value' => $mapid
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'navtree.2.name',
										'value' => self::SUBMAP_NAME
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'navtree.2.order',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_MAP,
										'name' => 'navtree.2.sysmapid',
										'value' => $submapid
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'TAPOK'
									]
								]
							],
							[
								'type' => 'map',
								'name' => 'Map listener',
								'x' => 22,
								'y' => 0,
								'width' => 20,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'sysmapid._reference',
										'value' => 'TAPOK._mapid'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'KAPOT'
									]
								]
							]
						]
					],
					[
						'name' => 'Multi-broadcasting page',
						'widgets' => [
							[
								'type' => 'map',
								'name' => 'Map mixed broadcaster',
								'x' => 0,
								'y' => 0,
								'width' => 19,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_MAP,
										'name' => 'sysmapid.0',
										'value' => $submapid
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'RLIUQ'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'honeycomb',
								'name' => 'Honeycomb mixed broadcaster',
								'x' => 0,
								'y' => 6,
								'width' => 19,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'items.0',
										'value' => 'Trapper item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'YCBJE'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'honeycomb',
								'name' => 'Both host and group from single broadcaster',
								'x' => 22,
								'y' => 0,
								'width' => 20,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'groupids._reference',
										'value' => 'RLIUQ._hostgroupids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'hostids._reference',
										'value' => 'RLIUQ._hostids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'items.0',
										'value' => 'Trapper item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'EMHYD'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'topitems',
								'name' => 'Host and group from different broadcasters',
								'x' => 22,
								'y' => 6,
								'width' => 20,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'groupids._reference',
										'value' => 'RLIUQ._hostgroupids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'hostids._reference',
										'value' => 'YCBJE._hostids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.item_tags.0.tag',
										'value' => 'item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.0.item_tags.0.operator',
										'value' => TAG_OPERATOR_LIKE
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.item_tags.0.value',
										'value' => 'widget communication'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.items.0',
										'value' => 'Trapper item'
									]
								]
							]
						]
					],
					[
						'name' => 'Copy widgets page',
						'widgets' => [
							[
								'type' => 'map',
								'name' => 'Map hostgroup broadcaster',
								'x' => 0,
								'y' => 0,
								'width' => 20,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_MAP,
										'name' => 'sysmapid.0',
										'value' => $submapid
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'MAPCP'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'honeycomb',
								'name' => 'Honeycomb hostgroup listener',
								'x' => 22,
								'y' => 0,
								'width' => 20,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'groupids._reference',
										'value' => 'MAPCP._hostgroupids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'items.0',
										'value' => 'Trapper item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'IFELH'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'tophosts',
								'name' => 'Top hosts host broadcaster',
								'x' => 0,
								'y' => 6,
								'width' => 20,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.0',
										'value' => $host_groupids[self::FIRST_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.1',
										'value' => $host_groupids[self::SECOND_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.2',
										'value' => $host_groupids[self::THIRD_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.name',
										'value' => 'Hostname'
									],

									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.0.data',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.0.aggregate_function',
										'value' => AGGREGATE_NONE
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.0.decimal_places',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.0.base_color',
										'value' => ''
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.1.name',
										'value' => 'Item value'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.data',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.1.item',
										'value' => 'Trapper item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.aggregate_function',
										'value' => AGGREGATE_NONE
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.decimal_places',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.display',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'columns.1.history',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.1.base_color',
										'value' => ''
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'column',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'HOSTR'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'problems',
								'name' => 'Problems host listener',
								'x' => 22,
								'y' => 6,
								'width' => 20,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'hostids._reference',
										'value' => 'HOSTR._hostids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.0',
										'value' => $host_groupids[self::FIRST_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.1',
										'value' => $host_groupids[self::SECOND_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GROUP,
										'name' => 'groupids.2',
										'value' => $host_groupids[self::THIRD_HOSTGROUP_NAME]
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'DNDNC'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							]
						]
					]
				]
			]
		]);

		return [
			'dashboardid' => $dashboard_response['dashboardids'][0],
			'itemids' => $itemids,
			'hostgroupids' => $host_groupids,
			'hostids' => $hostids,
			'mapids' => [
				self::SUBMAP_NAME => $submapid,
				self::MAP_NAME => $mapid
			]
		];
	}
}

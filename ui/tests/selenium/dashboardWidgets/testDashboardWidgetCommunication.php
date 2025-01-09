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


require_once dirname(__FILE__).'/../common/testWidgets.php';

/**
 * @backup profiles
 *
 * @onBefore prepareData
 */
class testDashboardWidgetCommunication extends testWidgets {

	/**
	 * Attach MessageBehavior and TagBehavior to the test.
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class,
			CWidgetBehavior::class
		];
	}

	protected static $dashboardid;
	protected static $itemids;
	protected static $current_broadcasters = [
		'Hostgroups page' => 'Map hostgroup broadcaster',
		'Hosts page' => 'Geomap host broadcaster',
		'Items page' => 'Honeycomb item broadcaster'
	];

	const FIRST_HOST_NAME = '1st host for widgets';
	const SECOND_HOST_NAME = '2nd host for widgets';
	const THIRD_HOST_NAME = '3rd host for widgets';
	const FIRST_HOSTGROUP_NAME = '1st hostgroup for widgets';
	const SECOND_HOSTGROUP_NAME = '2nd hostgroup for widgets';
	const THIRD_HOSTGROUP_NAME = '3rd hostgroup for widgets';
	const FIRST_HOST_TRIGGER = 'trigger on host 1';
	const SECOND_HOST_TRIGGER = 'trigger on host 2';
	const THIRD_HOST_TRIGGER = 'trigger on host 3';

	const BROADCASTER_REFERENCES = [
		'Map hostgroup broadcaster' => 'NRDLG._hostgroupids',
		'Problem hosts hostgroup broadcaster' => 'EKBHR._hostgroupids',
		'Problems by severity hostgroup broadcaster' => 'ZYWLY._hostgroupids',
		'Web monitoring hostgroup broadcaster' => 'XTPSV._hostgroupids',
		'Geomap host broadcaster' => 'JRVYU._hostids',
		'Honeycomb host broadcaster' => 'RICVX._hostids',
		'Map host broadcaster' => 'BFSOY._hostids',
		'Top hosts host broadcaster' => 'ACGKU._hostids',
		'Host navigator broadcaster' => 'HSTNV._hostids',
		'Honeycomb item broadcaster' => 'QFWQX._itemid',
		'Item history item broadcaster' => 'ZNLUI._itemid',
		'Item navigator broadcaster' => 'ITMNV._itemid'
	];

	const GEOMAP_ICON_INDEXES = [
		self::FIRST_HOST_NAME => 3,
		self::SECOND_HOST_NAME => 2,
		self::THIRD_HOST_NAME => 1
	];

	const GEOMAP_FILTERED_ICON_INDEX = 1;

	const DEFAULT_WIDGET_CONTENT = [
		'Hostgroups page' => [
			'Top items listener' => [
				[
					'Hosts' => self::FIRST_HOST_NAME,
					'Trapper item' => '3.00'
				],
				[
					'Hosts' => self::SECOND_HOST_NAME,
					'Trapper item' => '4.00'
				],
				[
					'Hosts' => self::THIRD_HOST_NAME,
					'Trapper item' => '5.00'
				]
			],
			'Geomap listener' => [
				self::GEOMAP_ICON_INDEXES[self::FIRST_HOST_NAME] => [
					'Host' => self::FIRST_HOST_NAME,
					'I' => '1'
				],
				self::GEOMAP_ICON_INDEXES[self::SECOND_HOST_NAME] => [
					'Host' => self::SECOND_HOST_NAME,
					'W' => '1'
				],
				self::GEOMAP_ICON_INDEXES[self::THIRD_HOST_NAME] => [
					'Host' => self::THIRD_HOST_NAME,
					'H' => '1'
				]
			],
			'Honeycomb listener' => [
				self::FIRST_HOST_NAME => 3,
				self::SECOND_HOST_NAME => 4,
				self::THIRD_HOST_NAME => 5
			],
			'Host availability listener' => [], // This widget will get data from DB.
			'Problem hosts listener' => [
				[
					'Host group' => self::FIRST_HOSTGROUP_NAME,
					'With problems' => '1',
					'Total' => '1'
				],
				[
					'Host group' => self::SECOND_HOSTGROUP_NAME,
					'With problems' => '1',
					'Total' => '1'
				],
				[
					'Host group' => self::THIRD_HOSTGROUP_NAME,
					'With problems' => '1',
					'Total' => '1'
				]
			],
			'Problems by severity listener' => [
				[
					'Host group' => self::FIRST_HOSTGROUP_NAME,
					'Information' => '1'
				],
				[
					'Host group' => self::SECOND_HOSTGROUP_NAME,
					'Warning' => '1'
				],
				[
					'Host group' => self::THIRD_HOSTGROUP_NAME,
					'High' => '1'
				]
			],
			'Top hosts listener' => [
				[
					'Hostname' => self::THIRD_HOST_NAME,
					'Item value' => '5.00'
				],
				[
					'Hostname' => self::SECOND_HOST_NAME,
					'Item value' => '4.00'
				],
				[
					'Hostname' => self::FIRST_HOST_NAME,
					'Item value' => '3.00'
				]
			],
			'Trigger overview listener' => [
				'triggers' => [self::FIRST_HOST_TRIGGER, self::SECOND_HOST_TRIGGER, self::THIRD_HOST_TRIGGER],
				'headers' => ['Triggers', self::FIRST_HOST_NAME, self::SECOND_HOST_NAME, self::THIRD_HOST_NAME]
			],
			'Web monitoring listener' => [
				[
					'Host group' => self::FIRST_HOSTGROUP_NAME,
					'Unknown' => '1'
				],
				[
					'Host group' => self::SECOND_HOSTGROUP_NAME,
					'Unknown' => '1'
				],
				[
					'Host group' => self::THIRD_HOSTGROUP_NAME,
					'Unknown' => '1'
				]
			]
		],
		'Hosts page' => [
			'Top items listener' => [
				[
					'Hosts' => self::FIRST_HOST_NAME,
					'Trapper item' => '3.00'
				],
				[
					'Hosts' => self::SECOND_HOST_NAME,
					'Trapper item' => '4.00'
				],
				[
					'Hosts' => self::THIRD_HOST_NAME,
					'Trapper item' => '5.00'
				]
			],
			'Geomap listener' => [
				self::GEOMAP_ICON_INDEXES[self::FIRST_HOST_NAME] => [
					'Host' => self::FIRST_HOST_NAME,
					'I' => '1'
				],
				self::GEOMAP_ICON_INDEXES[self::SECOND_HOST_NAME] => [
					'Host' => self::SECOND_HOST_NAME,
					'W' => '1'
				],
				self::GEOMAP_ICON_INDEXES[self::THIRD_HOST_NAME] => [
					'Host' => self::THIRD_HOST_NAME,
					'H' => '1'
				]
			],
			'Honeycomb listener' => [
				self::FIRST_HOST_NAME => 3,
				self::SECOND_HOST_NAME => 4,
				self::THIRD_HOST_NAME => 5
			],
			'Problem hosts listener' => [
				[
					'Host group' => self::FIRST_HOSTGROUP_NAME,
					'With problems' => '1',
					'Total' => '1'
				],
				[
					'Host group' => self::SECOND_HOSTGROUP_NAME,
					'With problems' => '1',
					'Total' => '1'
				],
				[
					'Host group' => self::THIRD_HOSTGROUP_NAME,
					'With problems' => '1',
					'Total' => '1'
				]
			],
			'Problems listener' => [
				[
					'Host' => self::THIRD_HOST_NAME,
					'Problem • Severity' => self::THIRD_HOST_TRIGGER
				],
				[
					'Host' => self::SECOND_HOST_NAME,
					'Problem • Severity' => self::SECOND_HOST_TRIGGER
				],
				[
					'Host' => self::FIRST_HOST_NAME,
					'Problem • Severity' => self::FIRST_HOST_TRIGGER
				]
			],
			'Problems by severity listener' => [
				[
					'Host group' => self::FIRST_HOSTGROUP_NAME,
					'Information' => '1'
				],
				[
					'Host group' => self::SECOND_HOSTGROUP_NAME,
					'Warning' => '1'
				],
				[
					'Host group' => self::THIRD_HOSTGROUP_NAME,
					'High' => '1'
				]
			],
			'Top hosts listener' => [
				[
					'Hostname' => self::FIRST_HOST_NAME,
					'Item value' => '3.00'
				],
				[
					'Hostname' => self::SECOND_HOST_NAME,
					'Item value' => '4.00'
				],
				[
					'Hostname' => self::THIRD_HOST_NAME,
					'Item value' => '5.00'
				]
			],
			'Trigger overview listener' => [
				'triggers' => [self::FIRST_HOST_TRIGGER, self::SECOND_HOST_TRIGGER, self::THIRD_HOST_TRIGGER],
				'headers' => ['Triggers', self::FIRST_HOST_NAME, self::SECOND_HOST_NAME, self::THIRD_HOST_NAME]
			],
			'Web monitoring listener' => [
				[
					'Host group' => self::FIRST_HOSTGROUP_NAME,
					'Unknown' => '1'
				],
				[
					'Host group' => self::SECOND_HOSTGROUP_NAME,
					'Unknown' => '1'
				],
				[
					'Host group' => self::THIRD_HOSTGROUP_NAME,
					'Unknown' => '1'
				]
			]
		]
	];

	/**
	 * Function creates hostgroups, hosts, items, triggers, web scenarios, map, dashboard and widgets that are involved
	 * in this test.
	 */
	public static function prepareData() {
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
				'inventory_mode' => 0,
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
				'inventory_mode' => 0,
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
				'inventory_mode' => 0,
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
		self::$itemids = $host_response['itemids'];
		$item_data_timestamp = time();

		// Send values 3, 4 and 5 to the created items.
		foreach (array_values(self::$itemids) as $i => $itemid) {
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
				'name' => 'Web scenario 1st widget communication host',
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
		CDataHelper::call('map.create', [
			[
				'name' => 'Map for widget communication test',
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
		]);
		$mapid = CDataHelper::getIds('name')['Map for widget communication test'];

		$dashboard_response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Existing widget communication test dashboard',
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
										'value' => $mapid
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
										'value' => $mapid
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
										'value' => self::$itemids[self::FIRST_HOST_NAME.':trap.widget.communication']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.1.name',
										'value' => self::SECOND_HOST_NAME.': Trapper item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.1.itemid',
										'value' => self::$itemids[self::SECOND_HOST_NAME.':trap.widget.communication']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'columns.2.name',
										'value' => self::THIRD_HOST_NAME.': Trapper item'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'columns.2.itemid',
										'value' => self::$itemids[self::THIRD_HOST_NAME.':trap.widget.communication']
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
										'value' => $mapid
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
										'value' => $mapid
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

		self::$dashboardid = $dashboard_response['dashboardids'][0];
	}

	public static function getWidgetData() {
		return [
			'Broadcasting hostgroups from map - initial selection' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Map hostgroup broadcaster',
					'select_element' => self::FIRST_HOSTGROUP_NAME,
					'expected' => [
						'Top items listener' => [
							'Hosts' => self::FIRST_HOST_NAME,
							'Trapper item' => '3.00'
						],
						'Geomap listener' => [
							self::GEOMAP_FILTERED_ICON_INDEX => [
								'Host' => self::FIRST_HOST_NAME,
								'I' => '1'
							]
						],
						'Honeycomb listener' => [
							self::FIRST_HOST_NAME => 3
						],
						'Host availability listener' => [
							'Total Hosts' => [
								'Unknown' => '1',
								'Total' => '1'
							],
							'Agent (passive)' => [
								'Unknown' => '1',
								'Total' => '1'
							],
							'JMX' => [
								'Unknown' => '0',
								'Total' => '0'
							],
							'IPMI' => [
								'Unknown' => '0',
								'Total' => '0'
							]
						],
						'Problem hosts listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'With problems' => '1',
							'Total' => '1'
						],
						'Problems by severity listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'Information' => '1'
						],
						'Top hosts listener' => [
							'Hostname' => self::FIRST_HOST_NAME,
							'Item value' => '3.00'
						],
						'Trigger overview listener' => [
							'triggers' => [self::FIRST_HOST_TRIGGER],
							'headers' => ['Triggers', self::FIRST_HOST_NAME]
						],
						'Web monitoring listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'Unknown' => '1'
						]
					]
				]
			],
			'Broadcasting hostgroups from map - selecting another value' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Map hostgroup broadcaster',
					'select_element' => self::SECOND_HOSTGROUP_NAME,
					'expected' => [
						'Top items listener' => [
							'Hosts' => self::SECOND_HOST_NAME,
							'Trapper item' => '4.00'
						],
						'Geomap listener' => [
							self::GEOMAP_FILTERED_ICON_INDEX => [
								'Host' => self::SECOND_HOST_NAME,
								'W' => '1'
							]
						],
						'Honeycomb listener' => [
							self::SECOND_HOST_NAME => 4
						],
						'Host availability listener' => [
							'Total Hosts' => [
								'Unknown' => '1',
								'Total' => '1'
							],
							'Agent (passive)' => [
								'Unknown' => '0',
								'Total' => '0'
							],
							'JMX' => [
								'Unknown' => '0',
								'Total' => '0'
							],
							'IPMI' => [
								'Unknown' => '1',
								'Total' => '1'
							]
						],
						'Problem hosts listener' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'With problems' => '1',
							'Total' => '1'
						],
						'Problems by severity listener' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'Warning' => '1'
						],
						'Top hosts listener' => [
							'Hostname' => self::SECOND_HOST_NAME,
							'Item value' => '4.00'
						],
						'Trigger overview listener' => [
							'triggers' => [self::SECOND_HOST_TRIGGER],
							'headers' => ['Triggers', self::SECOND_HOST_NAME]
						],
						'Web monitoring listener' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'Unknown' => '1'
						]
					]
				]
			],
			'Check that clicking on host in broadcasting map resets the selection in hostgroup listeners' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Map hostgroup broadcaster',
					'select_element' => self::SECOND_HOST_NAME
				]
			],
			'Broadcasting hostgroups from problem hosts widget - initial selection' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Problem hosts hostgroup broadcaster',
					'select_element' => self::FIRST_HOSTGROUP_NAME,
					'expected' => [
						'Top items listener' => [
							'Hosts' => self::FIRST_HOST_NAME,
							'Trapper item' => '3.00'
						],
						'Geomap listener' => [
							self::GEOMAP_FILTERED_ICON_INDEX => [
								'Host' => self::FIRST_HOST_NAME,
								'I' => '1'
							]
						],
						'Honeycomb listener' => [
							self::FIRST_HOST_NAME => 3
						],
						'Host availability listener' => [
							'Total Hosts' => [
								'Unknown' => '1',
								'Total' => '1'
							],
							'Agent (passive)' => [
								'Unknown' => '1',
								'Total' => '1'
							],
							'JMX' => [
								'Unknown' => '0',
								'Total' => '0'
							],
							'IPMI' => [
								'Unknown' => '0',
								'Total' => '0'
							]
						],
						'Problem hosts listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'With problems' => '1',
							'Total' => '1'
						],
						'Problems by severity listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'Information' => '1'
						],
						'Top hosts listener' => [
							'Hostname' => self::FIRST_HOST_NAME,
							'Item value' => '3.00'
						],
						'Trigger overview listener' => [
							'triggers' => [self::FIRST_HOST_TRIGGER],
							'headers' => ['Triggers', self::FIRST_HOST_NAME]
						],
						'Web monitoring listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'Unknown' => '1'
						]
					]
				]
			],
			'Broadcasting hostgroups from problem hosts widget - selecting another value' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Problem hosts hostgroup broadcaster',
					'select_element' => self::THIRD_HOSTGROUP_NAME,
					'expected' => [
						'Top items listener' => [
							'Hosts' => self::THIRD_HOST_NAME,
							'Trapper item' => '5.00'
						],
						'Geomap listener' => [
							self::GEOMAP_FILTERED_ICON_INDEX => [
								'Host' => self::THIRD_HOST_NAME,
								'H' => '1'
							]
						],
						'Honeycomb listener' => [
							self::THIRD_HOST_NAME => 5
						],
						'Host availability listener' => [
							'Total Hosts' => [
								'Unknown' => '1',
								'Total' => '1'
							],
							'Agent (passive)' => [
								'Unknown' => '0',
								'Total' => '0'
							],
							'JMX' => [
								'Unknown' => '1',
								'Total' => '1'
							],
							'IPMI' => [
								'Unknown' => '0',
								'Total' => '0'
							]
						],
						'Problem hosts listener' => [
							'Host group' => self::THIRD_HOSTGROUP_NAME,
							'With problems' => '1',
							'Total' => '1'
						],
						'Problems by severity listener' => [
							'Host group' => self::THIRD_HOSTGROUP_NAME,
							'High' => '1'
						],
						'Top hosts listener' => [
							'Hostname' => self::THIRD_HOST_NAME,
							'Item value' => '5.00'
						],
						'Trigger overview listener' => [
							'triggers' => [self::THIRD_HOST_TRIGGER],
							'headers' => ['Triggers', self::THIRD_HOST_NAME]
						],
						'Web monitoring listener' => [
							'Host group' => self::THIRD_HOSTGROUP_NAME,
							'Unknown' => '1'
						]
					]
				]
			],
			'Broadcasting hostgroups from problems by severity widget - initial selection' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Problems by severity hostgroup broadcaster',
					'select_element' => self::SECOND_HOSTGROUP_NAME,
					'expected' => [
						'Top items listener' => [
							'Hosts' => self::SECOND_HOST_NAME,
							'Trapper item' => '4.00'
						],
						'Geomap listener' => [
							self::GEOMAP_FILTERED_ICON_INDEX => [
								'Host' => self::SECOND_HOST_NAME,
								'W' => '1'
							]
						],
						'Honeycomb listener' => [
							self::SECOND_HOST_NAME => 4
						],
						'Host availability listener' => [
							'Total Hosts' => [
								'Unknown' => '1',
								'Total' => '1'
							],
							'Agent (passive)' => [
								'Unknown' => '0',
								'Total' => '0'
							],
							'JMX' => [
								'Unknown' => '0',
								'Total' => '0'
							],
							'IPMI' => [
								'Unknown' => '1',
								'Total' => '1'
							]
						],
						'Problem hosts listener' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'With problems' => '1',
							'Total' => '1'
						],
						'Problems by severity listener' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'Warning' => '1'
						],
						'Top hosts listener' => [
							'Hostname' => self::SECOND_HOST_NAME,
							'Item value' => '4.00'
						],
						'Trigger overview listener' => [
							'triggers' => [self::SECOND_HOST_TRIGGER],
							'headers' => ['Triggers', self::SECOND_HOST_NAME]
						],
						'Web monitoring listener' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'Unknown' => '1'
						]
					]
				]
			],
			'Broadcasting hostgroups from problems by severity widget - selecting another value' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Problems by severity hostgroup broadcaster',
					'select_element' => self::THIRD_HOSTGROUP_NAME,
					'expected' => [
						'Top items listener' => [
							'Hosts' => self::THIRD_HOST_NAME,
							'Trapper item' => '5.00'
						],
						'Geomap listener' => [
							self::GEOMAP_FILTERED_ICON_INDEX => [
								'Host' => self::THIRD_HOST_NAME,
								'H' => '1'
							]
						],
						'Honeycomb listener' => [
							self::THIRD_HOST_NAME => 5
						],
						'Host availability listener' => [
							'Total Hosts' => [
								'Unknown' => '1',
								'Total' => '1'
							],
							'Agent (passive)' => [
								'Unknown' => '0',
								'Total' => '0'
							],
							'JMX' => [
								'Unknown' => '1',
								'Total' => '1'
							],
							'IPMI' => [
								'Unknown' => '0',
								'Total' => '0'
							]
						],
						'Problem hosts listener' => [
							'Host group' => self::THIRD_HOSTGROUP_NAME,
							'With problems' => '1',
							'Total' => '1'
						],
						'Problems by severity listener' => [
							'Host group' => self::THIRD_HOSTGROUP_NAME,
							'High' => '1'
						],
						'Top hosts listener' => [
							'Hostname' => self::THIRD_HOST_NAME,
							'Item value' => '5.00'
						],
						'Trigger overview listener' => [
							'triggers' => [self::THIRD_HOST_TRIGGER],
							'headers' => ['Triggers', self::THIRD_HOST_NAME]
						],
						'Web monitoring listener' => [
							'Host group' => self::THIRD_HOSTGROUP_NAME,
							'Unknown' => '1'
						]
					]
				]
			],
			'Broadcasting hostgroups from web monitoring widget - initial selection' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Web monitoring hostgroup broadcaster',
					'select_element' => self::FIRST_HOSTGROUP_NAME,
					'expected' => [
						'Top items listener' => [
							'Hosts' => self::FIRST_HOST_NAME,
							'Trapper item' => '3.00'
						],
						'Geomap listener' => [
							self::GEOMAP_FILTERED_ICON_INDEX => [
								'Host' => self::FIRST_HOST_NAME,
								'I' => '1'
							]
						],
						'Honeycomb listener' => [
							self::FIRST_HOST_NAME => 3
						],
						'Host availability listener' => [
							'Total Hosts' => [
								'Unknown' => '1',
								'Total' => '1'
							],
							'Agent (passive)' => [
								'Unknown' => '1',
								'Total' => '1'
							],
							'JMX' => [
								'Unknown' => '0',
								'Total' => '0'
							],
							'IPMI' => [
								'Unknown' => '0',
								'Total' => '0'
							]
						],
						'Problem hosts listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'With problems' => '1',
							'Total' => '1'
						],
						'Problems by severity listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'Information' => '1'
						],
						'Top hosts listener' => [
							'Hostname' => self::FIRST_HOST_NAME,
							'Item value' => '3.00'
						],
						'Trigger overview listener' => [
							'triggers' => [self::FIRST_HOST_TRIGGER],
							'headers' => ['Triggers', self::FIRST_HOST_NAME]
						],
						'Web monitoring listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'Unknown' => '1'
						]
					]
				]
			],
			'Broadcasting hostgroups from web monitoring widget - selecting another value' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Web monitoring hostgroup broadcaster',
					'select_element' => self::SECOND_HOSTGROUP_NAME,
					'expected' => [
						'Top items listener' => [
							'Hosts' => self::SECOND_HOST_NAME,
							'Trapper item' => '4.00'
						],
						'Geomap listener' => [
							self::GEOMAP_FILTERED_ICON_INDEX => [
								'Host' => self::SECOND_HOST_NAME,
								'W' => '1'
							]
						],
						'Honeycomb listener' => [
							self::SECOND_HOST_NAME => 4
						],
						'Host availability listener' => [
							'Total Hosts' => [
								'Unknown' => '1',
								'Total' => '1'
							],
							'Agent (passive)' => [
								'Unknown' => '0',
								'Total' => '0'
							],
							'JMX' => [
								'Unknown' => '0',
								'Total' => '0'
							],
							'IPMI' => [
								'Unknown' => '1',
								'Total' => '1'
							]
						],
						'Problem hosts listener' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'With problems' => '1',
							'Total' => '1'
						],
						'Problems by severity listener' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'Warning' => '1'
						],
						'Top hosts listener' => [
							'Hostname' => self::SECOND_HOST_NAME,
							'Item value' => '4.00'
						],
						'Trigger overview listener' => [
							'triggers' => [self::SECOND_HOST_TRIGGER],
							'headers' => ['Triggers', self::SECOND_HOST_NAME]
						],
						'Web monitoring listener' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'Unknown' => '1'
						]
					]
				]
			],
			'Broadcasting hosts from geomap widget - initial selection' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Geomap host broadcaster',
					'select_element' => self::GEOMAP_ICON_INDEXES[self::FIRST_HOST_NAME],
					'expected' => [
						'Top items listener' => [
							'Hosts' => self::FIRST_HOST_NAME,
							'Trapper item' => '3.00'
						],
						'Geomap listener' => [
							self::GEOMAP_FILTERED_ICON_INDEX => [
								'Host' => self::FIRST_HOST_NAME,
								'I' => '1'
							]
						],
						'Honeycomb listener' => [
							self::FIRST_HOST_NAME => 3
						],
						'Problem hosts listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'With problems' => '1',
							'Total' => '1'
						],
						'Problems listener' => [
							'Host' => self::FIRST_HOST_NAME,
							'Problem • Severity' => self::FIRST_HOST_TRIGGER
						],
						'Problems by severity listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'Information' => '1'
						],
						'Top hosts listener' => [
							'Hostname' => self::FIRST_HOST_NAME,
							'Item value' => '3.00'
						],
						'Trigger overview listener' => [
							'triggers' => [self::FIRST_HOST_TRIGGER],
							'headers' => ['Triggers', self::FIRST_HOST_NAME]
						],
						'Web monitoring listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'Unknown' => '1'
						]
					]
				]
			],
			'Broadcasting hosts from geomap widget - selecting another value' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Geomap host broadcaster',
					'select_element' => self::GEOMAP_ICON_INDEXES[self::SECOND_HOST_NAME],
					'expected' => [
						'Top items listener' => [
							'Hosts' => self::SECOND_HOST_NAME,
							'Trapper item' => '4.00'
						],
						'Geomap listener' => [
							self::GEOMAP_FILTERED_ICON_INDEX => [
								'Host' => self::SECOND_HOST_NAME,
								'W' => '1'
							]
						],
						'Honeycomb listener' => [
							self::SECOND_HOST_NAME => 4
						],
						'Problem hosts listener' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'With problems' => '1',
							'Total' => '1'
						],
						'Problems listener' => [
							'Host' => self::SECOND_HOST_NAME,
							'Problem • Severity' => self::SECOND_HOST_TRIGGER
						],
						'Problems by severity listener' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'Warning' => '1'
						],
						'Top hosts listener' => [
							'Hostname' => self::SECOND_HOST_NAME,
							'Item value' => '4.00'
						],
						'Trigger overview listener' => [
							'triggers' => [self::SECOND_HOST_TRIGGER],
							'headers' => ['Triggers', self::SECOND_HOST_NAME]
						],
						'Web monitoring listener' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'Unknown' => '1'
						]
					]
				]
			],
			'Broadcasting hosts from honeycomb widget - initial selection' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Honeycomb host broadcaster',
					'select_element' => self::THIRD_HOST_NAME,
					'expected' => [
						'Top items listener' => [
							'Hosts' => self::THIRD_HOST_NAME,
							'Trapper item' => '5.00'
						],
						'Geomap listener' => [
							self::GEOMAP_FILTERED_ICON_INDEX => [
								'Host' => self::THIRD_HOST_NAME,
								'H' => '1'
							]
						],
						'Honeycomb listener' => [
							self::THIRD_HOST_NAME => 5
						],
						'Problem hosts listener' => [
							'Host group' => self::THIRD_HOSTGROUP_NAME,
							'With problems' => '1',
							'Total' => '1'
						],
						'Problems listener' => [
							'Host' => self::THIRD_HOST_NAME,
							'Problem • Severity' => self::THIRD_HOST_TRIGGER
						],
						'Problems by severity listener' => [
							'Host group' => self::THIRD_HOSTGROUP_NAME,
							'High' => '1'
						],
						'Top hosts listener' => [
							'Hostname' => self::THIRD_HOST_NAME,
							'Item value' => '5.00'
						],
						'Trigger overview listener' => [
							'triggers' => [self::THIRD_HOST_TRIGGER],
							'headers' => ['Triggers', self::THIRD_HOST_NAME]
						],
						'Web monitoring listener' => [
							'Host group' => self::THIRD_HOSTGROUP_NAME,
							'Unknown' => '1'
						]
					]
				]
			],
			'Broadcasting hosts from honeycomb widget - selecting another value' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Honeycomb host broadcaster',
					'select_element' => self::FIRST_HOST_NAME,
					'expected' => [
						'Top items listener' => [
							'Hosts' => self::FIRST_HOST_NAME,
							'Trapper item' => '3.00'
						],
						'Geomap listener' => [
							self::GEOMAP_FILTERED_ICON_INDEX => [
								'Host' => self::FIRST_HOST_NAME,
								'I' => '1'
							]
						],
						'Honeycomb listener' => [
							self::FIRST_HOST_NAME => 3
						],
						'Problem hosts listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'With problems' => '1',
							'Total' => '1'
						],
						'Problems listener' => [
							'Host' => self::FIRST_HOST_NAME,
							'Problem • Severity' => self::FIRST_HOST_TRIGGER
						],
						'Problems by severity listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'Information' => '1'
						],
						'Top hosts listener' => [
							'Hostname' => self::FIRST_HOST_NAME,
							'Item value' => '3.00'
						],
						'Trigger overview listener' => [
							'triggers' => [self::FIRST_HOST_TRIGGER],
							'headers' => ['Triggers', self::FIRST_HOST_NAME]
						],
						'Web monitoring listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'Unknown' => '1'
						]
					]
				]
			],
			'Broadcasting hosts from map widget - initial selection' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Map host broadcaster',
					'select_element' => self::SECOND_HOST_NAME,
					'expected' => [
						'Top items listener' => [
							'Hosts' => self::SECOND_HOST_NAME,
							'Trapper item' => '4.00'
						],
						'Geomap listener' => [
							self::GEOMAP_FILTERED_ICON_INDEX => [
								'Host' => self::SECOND_HOST_NAME,
								'W' => '1'
							]
						],
						'Honeycomb listener' => [
							self::SECOND_HOST_NAME => 4
						],
						'Problem hosts listener' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'With problems' => '1',
							'Total' => '1'
						],
						'Problems listener' => [
							'Host' => self::SECOND_HOST_NAME,
							'Problem • Severity' => self::SECOND_HOST_TRIGGER
						],
						'Problems by severity listener' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'Warning' => '1'
						],
						'Top hosts listener' => [
							'Hostname' => self::SECOND_HOST_NAME,
							'Item value' => '4.00'
						],
						'Trigger overview listener' => [
							'triggers' => [self::SECOND_HOST_TRIGGER],
							'headers' => ['Triggers', self::SECOND_HOST_NAME]
						],
						'Web monitoring listener' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'Unknown' => '1'
						]
					]
				]
			],
			'Broadcasting hosts from map widget - selecting another value' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Map host broadcaster',
					'select_element' => self::THIRD_HOST_NAME,
					'expected' => [
						'Top items listener' => [
							'Hosts' => self::THIRD_HOST_NAME,
							'Trapper item' => '5.00'
						],
						'Geomap listener' => [
							self::GEOMAP_FILTERED_ICON_INDEX => [
								'Host' => self::THIRD_HOST_NAME,
								'H' => '1'
							]
						],
						'Honeycomb listener' => [
							self::THIRD_HOST_NAME => 5
						],
						'Problem hosts listener' => [
							'Host group' => self::THIRD_HOSTGROUP_NAME,
							'With problems' => '1',
							'Total' => '1'
						],
						'Problems listener' => [
							'Host' => self::THIRD_HOST_NAME,
							'Problem • Severity' => self::THIRD_HOST_TRIGGER
						],
						'Problems by severity listener' => [
							'Host group' => self::THIRD_HOSTGROUP_NAME,
							'High' => '1'
						],
						'Top hosts listener' => [
							'Hostname' => self::THIRD_HOST_NAME,
							'Item value' => '5.00'
						],
						'Trigger overview listener' => [
							'triggers' => [self::THIRD_HOST_TRIGGER],
							'headers' => ['Triggers', self::THIRD_HOST_NAME]
						],
						'Web monitoring listener' => [
							'Host group' => self::THIRD_HOSTGROUP_NAME,
							'Unknown' => '1'
						]
					]
				]
			],
			'Clicking on hostgroup in broadcasting map should reset selection in host listeners' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Map host broadcaster',
					'select_element' => self::SECOND_HOSTGROUP_NAME
				]
			],
			'Broadcasting hosts from top hosts widget - initial selection' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Top hosts host broadcaster',
					'select_element' => self::FIRST_HOST_NAME,
					'expected' => [
						'Top items listener' => [
							'Hosts' => self::FIRST_HOST_NAME,
							'Trapper item' => '3.00'
						],
						'Geomap listener' => [
							self::GEOMAP_FILTERED_ICON_INDEX => [
								'Host' => self::FIRST_HOST_NAME,
								'I' => '1'
							]
						],
						'Honeycomb listener' => [
							self::FIRST_HOST_NAME => 3
						],
						'Problem hosts listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'With problems' => '1',
							'Total' => '1'
						],
						'Problems listener' => [
							'Host' => self::FIRST_HOST_NAME,
							'Problem • Severity' => self::FIRST_HOST_TRIGGER
						],
						'Problems by severity listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'Information' => '1'
						],
						'Top hosts listener' => [
							'Hostname' => self::FIRST_HOST_NAME,
							'Item value' => '3.00'
						],
						'Trigger overview listener' => [
							'triggers' => [self::FIRST_HOST_TRIGGER],
							'headers' => ['Triggers', self::FIRST_HOST_NAME]
						],
						'Web monitoring listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'Unknown' => '1'
						]
					]
				]
			],
			'Broadcasting hosts from top hosts widget - selecting another value' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Top hosts host broadcaster',
					'select_element' => self::SECOND_HOST_NAME,
					'expected' => [
						'Top items listener' => [
							'Hosts' => self::SECOND_HOST_NAME,
							'Trapper item' => '4.00'
						],
						'Geomap listener' => [
							self::GEOMAP_FILTERED_ICON_INDEX => [
								'Host' => self::SECOND_HOST_NAME,
								'W' => '1'
							]
						],
						'Honeycomb listener' => [
							self::SECOND_HOST_NAME => 4
						],
						'Problem hosts listener' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'With problems' => '1',
							'Total' => '1'
						],
						'Problems listener' => [
							'Host' => self::SECOND_HOST_NAME,
							'Problem • Severity' => self::SECOND_HOST_TRIGGER
						],
						'Problems by severity listener' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'Warning' => '1'
						],
						'Top hosts listener' => [
							'Hostname' => self::SECOND_HOST_NAME,
							'Item value' => '4.00'
						],
						'Trigger overview listener' => [
							'triggers' => [self::SECOND_HOST_TRIGGER],
							'headers' => ['Triggers', self::SECOND_HOST_NAME]
						],
						'Web monitoring listener' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'Unknown' => '1'
						]
					]
				]
			],
			'Broadcasting hosts from host navigator widget - initial selection' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Host navigator broadcaster',
					'select_element' => self::THIRD_HOST_NAME,
					'expected' => [
						'Top items listener' => [
							'Hosts' => self::THIRD_HOST_NAME,
							'Trapper item' => '5.00'
						],
						'Geomap listener' => [
							self::GEOMAP_FILTERED_ICON_INDEX => [
								'Host' => self::THIRD_HOST_NAME,
								'H' => '1'
							]
						],
						'Honeycomb listener' => [
							self::THIRD_HOST_NAME => 5
						],
						'Problem hosts listener' => [
							'Host group' => self::THIRD_HOSTGROUP_NAME,
							'With problems' => '1',
							'Total' => '1'
						],
						'Problems listener' => [
							'Host' => self::THIRD_HOST_NAME,
							'Problem • Severity' => self::THIRD_HOST_TRIGGER
						],
						'Problems by severity listener' => [
							'Host group' => self::THIRD_HOSTGROUP_NAME,
							'High' => '1'
						],
						'Top hosts listener' => [
							'Hostname' => self::THIRD_HOST_NAME,
							'Item value' => '5.00'
						],
						'Trigger overview listener' => [
							'triggers' => [self::THIRD_HOST_TRIGGER],
							'headers' => ['Triggers', self::THIRD_HOST_NAME]
						],
						'Web monitoring listener' => [
							'Host group' => self::THIRD_HOSTGROUP_NAME,
							'Unknown' => '1'
						]
					]
				]
			],
			'Broadcasting hosts from host navigator widget - selecting another value' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Host navigator broadcaster',
					'select_element' => self::FIRST_HOST_NAME,
					'expected' => [
						'Top items listener' => [
							'Hosts' => self::FIRST_HOST_NAME,
							'Trapper item' => '3.00'
						],
						'Geomap listener' => [
							self::GEOMAP_FILTERED_ICON_INDEX => [
								'Host' => self::FIRST_HOST_NAME,
								'I' => '1'
							]
						],
						'Honeycomb listener' => [
							self::FIRST_HOST_NAME => 3
						],
						'Problem hosts listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'With problems' => '1',
							'Total' => '1'
						],
						'Problems listener' => [
							'Host' => self::FIRST_HOST_NAME,
							'Problem • Severity' => self::FIRST_HOST_TRIGGER
						],
						'Problems by severity listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'Information' => '1'
						],
						'Top hosts listener' => [
							'Hostname' => self::FIRST_HOST_NAME,
							'Item value' => '3.00'
						],
						'Trigger overview listener' => [
							'triggers' => [self::FIRST_HOST_TRIGGER],
							'headers' => ['Triggers', self::FIRST_HOST_NAME]
						],
						'Web monitoring listener' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'Unknown' => '1'
						]
					]
				]
			],
			'Broadcasting items from honeycomb widget - initial selection' => [
				[
					'page' => 'Items page',
					'broadcaster' => 'Honeycomb item broadcaster',
					'select_element' => self::THIRD_HOST_NAME,
					'expected' => [
						'Gauge listener' => [
							'class' => 'svg-gauge-value',
							'value' => 5
						],
						'Graph (classic) listener' => [
							'hostname' => self::THIRD_HOST_NAME
						],
						'Item value listener' => [
							'class' => 'item-value-content',
							'value' => "5"
						],
						'SVG graph listener' => [
							'class' => 'svg-graph-legend-item',
							'value' => self::THIRD_HOST_NAME.': Trapper item'
						],
						'Pie chart listener' => [
							'name' => self::THIRD_HOST_NAME.': Trapper item',
							'value' => 5
						]
					]
				]
			],
			'Broadcasting items from honeycomb widget - selecting another value' => [
				[
					'page' => 'Items page',
					'broadcaster' => 'Honeycomb item broadcaster',
					'select_element' => self::FIRST_HOST_NAME,
					'expected' => [
						'Gauge listener' => [
							'class' => 'svg-gauge-value',
							'value' => 3
						],
						'Graph (classic) listener' => [
							'hostname' => self::FIRST_HOST_NAME
						],
						'Item value listener' => [
							'class' => 'item-value-content',
							'value' => 3
						],
						'SVG graph listener' => [
							'class' => 'svg-graph-legend-item',
							'value' => self::FIRST_HOST_NAME.': Trapper item'
						],
						'Pie chart listener' => [
							'name' => self::FIRST_HOST_NAME.': Trapper item',
							'value' => 3
						]
					]
				]
			],
			'Broadcasting items from item history widget - initial selection' => [
				[
					'page' => 'Items page',
					'broadcaster' => 'Item history item broadcaster',
					'select_element' => self::SECOND_HOST_NAME,
					'expected' => [
						'Gauge listener' => [
							'class' => 'svg-gauge-value',
							'value' => 4
						],
						'Graph (classic) listener' => [
							'hostname' => self::SECOND_HOST_NAME
						],
						'Item value listener' => [
							'class' => 'item-value-content',
							'value' => 4
						],
						'SVG graph listener' => [
							'class' => 'svg-graph-legend-item',
							'value' => self::SECOND_HOST_NAME.': Trapper item'
						],
						'Pie chart listener' => [
							'name' => self::SECOND_HOST_NAME.': Trapper item',
							'value' => 4
						]
					]
				]
			],
			'Broadcasting items from item history widget - selecting another value' => [
				[
					'page' => 'Items page',
					'broadcaster' => 'Item history item broadcaster',
					'select_element' => self::THIRD_HOST_NAME,
					'expected' => [
						'Gauge listener' => [
							'class' => 'svg-gauge-value',
							'value' => 5
						],
						'Graph (classic) listener' => [
							'hostname' => self::THIRD_HOST_NAME
						],
						'Item value listener' => [
							'class' => 'item-value-content',
							'value' => 5
						],
						'SVG graph listener' => [
							'class' => 'svg-graph-legend-item',
							'value' => self::THIRD_HOST_NAME.': Trapper item'
						],
						'Pie chart listener' => [
							'name' => self::THIRD_HOST_NAME.': Trapper item',
							'value' => 5
						]
					]
				]
			],
			'Broadcasting items from item navigator widget - initial selection' => [
				[
					'page' => 'Items page',
					'broadcaster' => 'Item navigator broadcaster',
					'select_element' => self::FIRST_HOST_NAME,
					'expected' => [
						'Gauge listener' => [
							'class' => 'svg-gauge-value',
							'value' => 3
						],
						'Graph (classic) listener' => [
							'hostname' => self::FIRST_HOST_NAME
						],
						'Item value listener' => [
							'class' => 'item-value-content',
							'value' => 3
						],
						'SVG graph listener' => [
							'class' => 'svg-graph-legend-item',
							'value' => self::FIRST_HOST_NAME.': Trapper item'
						],
						'Pie chart listener' => [
							'name' => self::FIRST_HOST_NAME.': Trapper item',
							'value' => 3
						]
					]
				]
			],
			'Broadcasting items from item history widget - selecting another value' => [
				[
					'page' => 'Items page',
					'broadcaster' => 'Item navigator broadcaster',
					'select_element' => self::SECOND_HOST_NAME,
					'expected' => [
						'Gauge listener' => [
							'class' => 'svg-gauge-value',
							'value' => 4
						],
						'Graph (classic) listener' => [
							'hostname' => self::SECOND_HOST_NAME
						],
						'Item value listener' => [
							'class' => 'item-value-content',
							'value' => 4
						],
						'SVG graph listener' => [
							'class' => 'svg-graph-legend-item',
							'value' => self::SECOND_HOST_NAME.': Trapper item'
						],
						'Pie chart listener' => [
							'name' => self::SECOND_HOST_NAME.': Trapper item',
							'value' => 4
						]
					]
				]
			]
		];
	}

	/**
	 * Check filtering of data in listener widgets based on data selected in broadcasting widget.
	 *
	 * @dataProvider getWidgetData
	 */
	public function testDashboardWidgetCommunication_CheckDataBroadcasting($data) {
		// Change broadcasting widget for listener widgets on corresponding page, if required.
		if ($data['broadcaster'] !== self::$current_broadcasters[$data['page']]) {
			DBexecute('UPDATE widget_field SET value_str='.zbx_dbstr(self::BROADCASTER_REFERENCES[$data['broadcaster']]).
					' WHERE value_str='.zbx_dbstr(self::BROADCASTER_REFERENCES[self::$current_broadcasters[$data['page']]])
			);
			self::$current_broadcasters[$data['page']] = $data['broadcaster'];
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();

		if ($dashboard->getSelectedPageName() !== $data['page']) {
			$dashboard->selectPage($data['page']);
		}

		$this->getWidgetElement($data['select_element'], $dashboard->getWidget($data['broadcaster']))->click();
		$dashboard->waitUntilReady();

		$this->closeOpenedPopup();

		if (!array_key_exists('expected', $data)) {
			$data['expected'] = self::DEFAULT_WIDGET_CONTENT[$data['page']];
		}

		$this->checkDataOnListener($data['expected']);

		/**
		 * Check that item listeners without defined name get "host name: Item name" name when element on broadcaster
		 * is selected.
		 */
		if ($data['page'] === 'Items page') {
			$this->assertTrue($dashboard->getWidget($data['select_element'].': Trapper item')->isValid());
		}
	}

	public function getMixedBroadcastingWidgetData() {
		return [
			'Broadcasting hostgroups and hosts from the same map widget - first group and then host' => [
				[
					'page' => 'Multi-broadcasting page',
					'broadcasters' => [
						'Map mixed broadcaster' => self::FIRST_HOSTGROUP_NAME,
						'Map mixed broadcaster' => self::SECOND_HOST_NAME
					],
					'expected' => [
						'Both host and group from single broadcaster' => [
							self::SECOND_HOST_NAME => 4
						],
						'Host and group from different broadcasters' => [
							[
								'Hosts' => self::FIRST_HOST_NAME,
								'Trapper item' => '3.00'
							]
						]
					]
				]
			],
			'Broadcasting hostgroups and hosts from the same map widget - first host and then group' => [
				[
					'page' => 'Multi-broadcasting page',
					'broadcasters' => [
						'Map mixed broadcaster' => self::SECOND_HOST_NAME,
						'Map mixed broadcaster' => self::THIRD_HOSTGROUP_NAME,
						'Honeycomb mixed broadcaster' => self::THIRD_HOST_NAME
					],
					'expected' => [
						'Both host and group from single broadcaster' => [
							self::THIRD_HOST_NAME => 5
						],
						'Host and group from different broadcasters' => [
							'Hosts' => self::THIRD_HOST_NAME,
							'Trapper item' => '5.00'
						]
					]
				]
			],
			'Broadcasting linked hostgroup and host from different widgets' => [
				[
					'page' => 'Multi-broadcasting page',
					'broadcasters' => [
						'Map mixed broadcaster' => self::SECOND_HOSTGROUP_NAME,
						'Honeycomb mixed broadcaster' => self::SECOND_HOST_NAME

					],
					'expected' => [
						'Both host and group from single broadcaster' => [
							self::SECOND_HOST_NAME => 4
						],
						'Host and group from different broadcasters' => [
							'Hosts' => self::SECOND_HOST_NAME,
							'Trapper item' => '4.00'
						]
					]
				]
			],
			'Broadcasting not linked hostgroups and hosts from different widgets' => [
				[
					'page' => 'Multi-broadcasting page',
					'broadcasters' => [
						'Map mixed broadcaster' => self::THIRD_HOSTGROUP_NAME,
						'Honeycomb mixed broadcaster' => self::FIRST_HOST_NAME

					],
					'expected' => [
						'Both host and group from single broadcaster' => [
							self::THIRD_HOST_NAME => 5
						],
						'Host and group from different broadcasters' => []
					]
				]
			]
		];
	}

	/**
	 * Check filtering of data in listener widgets in case if they are listening to multiple parameters.
	 *
	 * @dataProvider getMixedBroadcastingWidgetData
	 */
	public function testDashboardWidgetCommunication_CheckMixedDataBroadcasting($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$dashboard->selectPage('Multi-broadcasting page');

		foreach ($data['broadcasters'] as $broadcaster => $select_element) {
			$this->getWidgetElement($select_element, $dashboard->getWidget($broadcaster))->click();
			$dashboard->waitUntilReady();

			$this->closeOpenedPopup();
		}

		$this->checkDataOnListener($data['expected']);
	}

	/**
	 * Check listener widget behavior when broadcasting widget is deleted.
	 */
	public function testDashboardWidgetCommunication_BroadcasterDeletion() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$dashboard->edit();

		foreach (['Hostgroups page' => 'Host groups', 'Hosts page' => 'Hosts', 'Items page' => 'Item'] as $page => $field) {
			$dashboard->selectPage($page);

			// TODO: Add 'Item value listener' and 'Item value' to the below list of widget names when ZBX-25040 is fixed.
			$broadcaster = self::$current_broadcasters[$page];
			$listeners = ($page === 'Items page')
				? ['Gauge listener', 'Graph (classic) listener', 'SVG graph listener', 'Pie chart listener']
				: array_keys(self::DEFAULT_WIDGET_CONTENT[$page]);

			$dashboard->deleteWidget($broadcaster);
			$this->checkUnavailableReference($dashboard, $listeners, $field);
		}

		$dashboard->cancelEditing();
	}

	public function getCopyWidgetsData() {
		return [
			'Copy a broadcasting capable listener over a broadcaster' => [
				[
					'copy' => 'Honeycomb hostgroup listener',
					'paste' => [
						'widget' => 'Top hosts host broadcaster'
					],
					'select' => [
						'widget' => 'new',
						'element' => self::FIRST_HOST_NAME
					],
					'expected' => [
						'Problems host listener' => [
							'Host' => self::FIRST_HOST_NAME,
							'Problem • Severity' => self::FIRST_HOST_TRIGGER
						]
					]
				]
			],
			'Copy non-broadcast-capable widget over a broadcaster and check that listener is broken' => [
				[
					'copy' => 'Problems host listener',
					'paste' => [
						'widget' => 'Map hostgroup broadcaster'
					],
					'unavailable_widget' => 'Honeycomb hostgroup listener'
				]
			],
			'Copy broadcaster on the same page - there should be no impact on listener' => [
				[
					'copy' => 'Top hosts host broadcaster',
					'paste' => [
						'page' => 'Copy widgets page'
					],
					'select' => [
						'widget' => 'new',
						'element' => self::SECOND_HOST_NAME
					],
					'expected' => [
						'Problems host listener' => [
							[
								'Host' => self::THIRD_HOST_NAME,
								'Problem • Severity' => self::THIRD_HOST_TRIGGER
							]
						]
					]
				]
			],
			'Copy listener to the same page - it should continue to listen to the same broadcaster' => [
				[
					'copy' => 'Problems host listener',
					'paste' => [
						'page' => 'Copy widgets page'
					],
					'select' => [
						'widget' => 'Top hosts host broadcaster',
						'element' => self::FIRST_HOST_NAME
					],
					'expected' => [
						'new' => [
							'Host' => self::FIRST_HOST_NAME,
							'Problem • Severity' => self::FIRST_HOST_TRIGGER
						]
					]
				]
			],
			'Copy a listener to another page - pasted listener is broken' => [
				[
					'copy' => 'Problems host listener',
					'paste' => [
						'page' => 'Multi-broadcasting page'
					],
					'unavailable_widget' => 'Problems host listener'
				]
			]
		];
	}

	/**
	 * @dataProvider getCopyWidgetsData
	 */
	public function testDashboardWidgetCommunication_CopyWidgets($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$dashboard->edit()->selectPage('Copy widgets page');

		$dashboard->copyWidget($data['copy']);

		if (array_keys($data['paste'])[0] === 'page') {
			if ($dashboard->getSelectedPageName() !== $data['paste']['page']) {
				$dashboard->selectPage($data['paste']['page']);
			}

			$dashboard->pasteWidget();
		}
		else {
			$dashboard->replaceWidget($data['paste']['widget']);
		}

		$dashboard->waitUntilReady();

		if (array_key_exists('unavailable_widget', $data)) {
			$this->checkUnavailableReference($dashboard, [$data['unavailable_widget']]);
		}
		else {
			if ($data['select']['widget'] === 'new') {
				$broadcaster = $dashboard->query('class:new-widget')->waitUntilPresent()->one();
			}
			else {
				$broadcaster = $dashboard->getWidget($data['select']['widget']);
			}

			$this->getWidgetElement($data['select']['element'], $broadcaster)->click();
			$dashboard->waitUntilReady();
			$this->checkDataOnListener($data['expected']);
		}

		$dashboard->cancelEditing();
	}

	/**
	 * Close popup or dialog that is opened when clicking on element in broadcaster widget.
	 */
	protected function closeOpenedPopup() {
		if ($this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->one(false)->isValid()) {
			$this->query('class:btn-overlay-close')->all()->last()->click();
		}

		if (CPopupMenuElement::find()->one(false)->isValid()) {

			CPopupMenuElement::find()->one()->close();
		}
	}

	/**
	 * Check text in widgets that listen to deleted or unavailable widgets.
	 *
	 * @param CDashboardElement		$dashboard		dashboard that contains widgets used in test
	 * @param array					$listeners		list of listener widgets to be checked
	 * @param string				$field			name of the field that contains the unavailable reference
	 */
	protected function checkUnavailableReference($dashboard, $listeners, $field = null) {
		foreach ($listeners as $listener_name) {
			$listener_widget = $dashboard->getWidget($listener_name);
			$this->assertEquals("Referred widget is unavailable\nPlease update configuration", $listener_widget
					->query('class:zi-widget-empty-references-large')->one()->getText()
			);

			if ($field) {
				$widget_form = $listener_widget->edit();

				if (in_array($listener_name, ['SVG graph listener', 'Pie chart listener'])) {
					$this->assertEquals('Unavailable widget', $widget_form->query('xpath:.//td[contains(@class,"table-col-name")]')
							->one()->getText()
					);
				}
				else {
					$this->assertEquals(['Unavailable widget'], $widget_form->getField($field)->getValue());

					// TODO: Move the below code right before closing the dialog after ZBX-25041 is fixed.
					$widget_form->submit();
					$this->assertMessage(TEST_BAD, null, 'Invalid parameter "'.$field.'": referred widget is unavailable.');
				}

				COverlayDialogElement::find()->one()->close();
			}
		}
	}

	/**
	 * Locate the required listener widget and verify displayed data based on listener widget type.
	 *
	 * @param array $expected	expected content on listener widget
	 */
	protected function checkDataOnListener($expected) {
		$dashboard = CDashboardElement::find()->one();

		foreach ($expected as $listener_name => $values) {
			if ($listener_name === 'new') {
				$listener = $dashboard->query('class:new-widget')->waitUntilPresent()->one();
			}
			else {
				$listener = $dashboard->getWidget($listener_name);
			}

			// It takes time for listener to load data. Listener has "is-loading" class while this process is active.
			if ($listener->hasClass('is-loading')) {
				$listener->waitUntilClassesNotPresent('is-loading');
			}

			$listener_type = $this->getWidgetType($listener);

			switch ($listener_type) {
				case 'topitems':
				case 'problemhosts':
				case 'problems':
				case 'problemsbysv':
				case 'tophosts':
				case 'web':
					if (!CTestArrayHelper::isMultidimensional($values)) {
						$values = [$values];
					}

					$table_selector = ($listener_name === 'new')
						? 'xpath://div[contains(@class, "new-widget")]//table'
						: 'xpath://h4[text()='.CXPathHelper::escapeQuotes($listener_name).']/../..//table';

					$this->assertTableData($values, $table_selector);
					break;

				case 'geomap':
					foreach ($values as $icon_index => $popup_values) {
						$listener->query('xpath:.//img[contains(@class,"leaflet-marker-icon")]['.$icon_index.']')
								->one()->click();
						$this->assertTableData([$popup_values], 'xpath://div[@class="overlay-dialogue wordbreak"]');
						$this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->query('class:btn-overlay-close')
								->one()->click();
					}
					break;

				case 'honeycomb':
					$this->assertEquals(count($values), $listener->query('class:svg-honeycomb-cell')->all()->count());

					foreach ($values as $host => $value) {
						$cell_content = $listener->query('xpath:.//div[text()='.CXPathHelper::escapeQuotes($host).']/../..')
								->one();
						$this->assertEquals($value, $cell_content->query('class:svg-honeycomb-label-secondary')->one()->getText());
					}
					break;

				case 'hostavail':
					$widget_table = $listener->asTable();

					// If interface type not defined in expected results, get interface count for each interface type from DB.
					if ($values === []) {
						$interface_types = ['Agent (active)', 'Agent (passive)', 'JMX', 'IPMI'];
						$rows = $widget_table->index('');

						foreach ($interface_types as $type) {
							$values_by_type = $this->getExpectedInterfaceCountFromDB([], $type);
							$this->assertEquals($rows[$type], $values_by_type);
						}
					}
					else {
						foreach ($values as $type => $interface_states) {
							$row = $widget_table->findRow('', $type);
							$row->assertValues($interface_states);
						}
					}
					break;

				case 'trigover':
					$this->assertTableDataColumn($values['triggers'], 'Triggers', 'xpath://h4[text()='.
							CXPathHelper::escapeQuotes($listener_name).']/../..//table'
					);
					$this->assertEquals($values['headers'], $listener->asTable()->getHeadersText());
					break;

				case 'gauge':
				case 'item':
				case 'svggraph':
					$this->assertEquals($values['value'], $listener->query('class', $values['class'])->one()->getText());
					break;

				case 'graph':
					// Get graph URL parameters.
					$url_params = parse_url($listener->query('tag:img')->one()->getAttribute('src'), PHP_URL_QUERY);

					// Parse obtained URL parameters and assert itemid of the item displayed on the graph.
					parse_str($url_params, $params_array);
					$this->assertEquals(self::$itemids[$values['hostname'].':trap.widget.communication'],
							$params_array['itemids'][0]
					);
					break;

				case 'piechart':
					$listener->query('class:svg-pie-chart-arc')->one()->click();

					// Check the content in hintbox and the legend of the chart.
					$this->assertEquals($values['name'].": \n".$values['value'], $this->query('class', 'svg-pie-chart-hintbox')
							->one()->getText()
					);
					$this->assertEquals($values['name'], $listener->query('class:svg-pie-chart-legend-item')->one()->getText());
					break;
			}
		}
	}

	/**
	 * Return the element on widget that needs to be selected.
	 *
	 * @param string			$element_identifier		text or selector part that is used to locate the element
	 * @param CWidgetElement	$widget					widget where the element is located
	 *
	 * @return CElement
	 */
	protected function getWidgetElement($element_identifier, $widget) {
		$widget_type = $this->getWidgetType($widget);

		switch ($widget_type) {
			case 'map':
				$element = $widget->query('xpath:.//*[@class="map-elements"]//*[text()='
						.CXPathHelper::escapeQuotes($element_identifier).']/../../preceding::*[1]'
				);
				break;

			case 'problemhosts':
			case 'problemsbysv':
			case 'web':
			case 'tophosts':
				$element = $widget->query('xpath:.//a[text()='.CXPathHelper::escapeQuotes($element_identifier).']/../..');
				break;

			case 'geomap':
				$element = $widget->query('xpath:.//img[contains(@class,"leaflet-marker-icon")]['.$element_identifier.']');
				break;

			case 'honeycomb':
				$element = $widget->query('xpath:.//div[text()='.CXPathHelper::escapeQuotes($element_identifier).']');
				break;

			case 'itemhistory':
				$element = $widget->query('xpath:.//td[text()='.CXPathHelper::escapeQuotes($element_identifier.
						': Trapper item').']'
				);
				break;

			case 'hostnavigator':
				$element = $widget->query('xpath:.//span[@title='.CXPathHelper::escapeQuotes($element_identifier).']');
				break;

			case 'itemnavigator':
				$element = $widget->query('xpath:.//div[@data-id='.
						self::$itemids[$element_identifier.':trap.widget.communication'].']'
				);
				break;
		}

		return $element->waitUntilClickable()->one();
	}
}

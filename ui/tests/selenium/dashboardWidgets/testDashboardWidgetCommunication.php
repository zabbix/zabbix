<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


require_once __DIR__.'/../common/testWidgetCommunication.php';

/**
 * @backup profiles
 *
 * @dataSource WidgetCommunication
 *
 * @onBefore getCreatedIds
 */
class testDashboardWidgetCommunication extends testWidgetCommunication {

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

	protected static $current_broadcasters = [
		'Hostgroups page' => 'Map hostgroup broadcaster',
		'Hosts page' => 'Geomap host broadcaster',
		'Items page' => 'Honeycomb item broadcaster',
		'Maps page' => 'Navigation tree map broadcaster'
	];

	const GEOMAP_FILTERED_ICON_INDEX = 1;

	const DEFAULT_WIDGET_CONTENT = [
		'Hostgroups page' => [
			'Data overview listener' => [
				[
					'Hosts' => self::FIRST_HOST_NAME,
					'Trapper item' => '3'
				],
				[
					'Hosts' => self::SECOND_HOST_NAME,
					'Trapper item' => '4'
				],
				[
					'Hosts' => self::THIRD_HOST_NAME,
					'Trapper item' => '5'
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
			],
			'Host navigator listener' => [
				self::FIRST_HOST_NAME => [
					'severity' => 'info',
					'index' => 0,
					'count' => 1
				],
				self::SECOND_HOST_NAME => [
					'severity' => 'warning',
					'index' => 1,
					'count' => 1
				],
				self::THIRD_HOST_NAME => [
					'severity' => 'high',
					'index' => 2,
					'count' => 1
				]
			],
			'Item navigator listener' => [
				self::FIRST_HOST_NAME => [
					'severity' => 'info',
					'index' => 0,
					'count' => 1
				],
				self::SECOND_HOST_NAME => [
					'severity' => 'warning',
					'index' => 2,
					'count' => 1
				],
				self::THIRD_HOST_NAME => [
					'severity' => 'high',
					'index' => 4,
					'count' => 1
				]
			]
		],
		'Hosts page' => [
			'Data overview listener' => [
				[
					'Hosts' => self::FIRST_HOST_NAME,
					'Trapper item' => '3'
				],
				[
					'Hosts' => self::SECOND_HOST_NAME,
					'Trapper item' => '4'
				],
				[
					'Hosts' => self::THIRD_HOST_NAME,
					'Trapper item' => '5'
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
			],
			'Item navigator listener' => [
				self::FIRST_HOST_NAME => [
					'severity' => 'info',
					'index' => 0,
					'count' => 1
				],
				self::SECOND_HOST_NAME => [
					'severity' => 'warning',
					'index' => 2,
					'count' => 1
				],
				self::THIRD_HOST_NAME => [
					'severity' => 'high',
					'index' => 4,
					'count' => 1
				]
			]
		]
	];

	public static function getWidgetData() {
		return [
			'Broadcasting hostgroups from map - no selection' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Map hostgroup broadcaster',
					'autoselected' => self::FIRST_HOSTGROUP_NAME,
					'expected' => [
						'Data overview listener' => [
							'Hosts' => self::FIRST_HOST_NAME,
							'Trapper item' => '3'
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
						],
						'Host navigator listener' => [
							self::FIRST_HOST_NAME => [
								'severity' => 'info',
								'count' => 1
							]
						],
						'Item navigator listener' => [
							self::FIRST_HOST_NAME => [
								'severity' => 'info',
								'count' => 1
							]
						]
					]
				]
			],
			'Broadcasting hostgroups from map - initial selection' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Map hostgroup broadcaster',
					'select_element' => self::THIRD_HOSTGROUP_NAME,
					'expected' => [
						'Data overview listener' => [
							'Hosts' => self::THIRD_HOST_NAME,
							'Trapper item' => '5'
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
						],
						'Host navigator listener' => [
							self::THIRD_HOST_NAME => [
								'severity' => 'high',
								'count' => 1
							]
						],
						'Item navigator listener' => [
							self::THIRD_HOST_NAME => [
								'severity' => 'high',
								'count' => 1
							]
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
						'Data overview listener' => [
							'Hosts' => self::SECOND_HOST_NAME,
							'Trapper item' => '4'
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
						],
						'Host navigator listener' => [
							self::SECOND_HOST_NAME => [
								'severity' => 'warning',
								'count' => 1
							]
						],
						'Item navigator listener' => [
							self::SECOND_HOST_NAME => [
								'severity' => 'warning',
								'count' => 1
							]
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
			'Broadcasting hostgroups from problem hosts widget - no selection' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Problem hosts hostgroup broadcaster',
					'autoselected' => self::FIRST_HOSTGROUP_NAME,
					'expected' => [
						'Data overview listener' => [
							'Hosts' => self::FIRST_HOST_NAME,
							'Trapper item' => '3'
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
						],
						'Host navigator listener' => [
							self::FIRST_HOST_NAME => [
								'severity' => 'info',
								'count' => 1
							]
						],
						'Item navigator listener' => [
							self::FIRST_HOST_NAME => [
								'severity' => 'info',
								'count' => 1
							]
						]
					]
				]
			],
			'Broadcasting hostgroups from problem hosts widget - initial selection' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Problem hosts hostgroup broadcaster',
					'select_element' => self::SECOND_HOSTGROUP_NAME,
					'expected' => [
						'Data overview listener' => [
							'Hosts' => self::SECOND_HOST_NAME,
							'Trapper item' => '4'
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
						],
						'Host navigator listener' => [
							self::SECOND_HOST_NAME => [
								'severity' => 'warning',
								'count' => 1
							]
						],
						'Item navigator listener' => [
							self::SECOND_HOST_NAME => [
								'severity' => 'warning',
								'count' => 1
							]
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
						'Data overview listener' => [
							'Hosts' => self::THIRD_HOST_NAME,
							'Trapper item' => '5'
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
						],
						'Host navigator listener' => [
							self::THIRD_HOST_NAME => [
								'severity' => 'high',
								'count' => 1
							]
						],
						'Item navigator listener' => [
							self::THIRD_HOST_NAME => [
								'severity' => 'high',
								'count' => 1
							]
						]
					]
				]
			],
			'Broadcasting hostgroups from problems by severity widget - no selection' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Problems by severity hostgroup broadcaster',
					'autoselected' => self::FIRST_HOSTGROUP_NAME,
					'expected' => [
						'Data overview listener' => [
							'Hosts' => self::FIRST_HOST_NAME,
							'Trapper item' => '3'
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
						],
						'Host navigator listener' => [
							self::FIRST_HOST_NAME => [
								'severity' => 'info',
								'count' => 1
							]
						],
						'Item navigator listener' => [
							self::FIRST_HOST_NAME => [
								'severity' => 'info',
								'count' => 1
							]
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
						'Data overview listener' => [
							'Hosts' => self::SECOND_HOST_NAME,
							'Trapper item' => '4'
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
						],
						'Host navigator listener' => [
							self::SECOND_HOST_NAME => [
								'severity' => 'warning',
								'count' => 1
							]
						],
						'Item navigator listener' => [
							self::SECOND_HOST_NAME => [
								'severity' => 'warning',
								'count' => 1
							]
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
						'Data overview listener' => [
							'Hosts' => self::THIRD_HOST_NAME,
							'Trapper item' => '5'
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
						],
						'Host navigator listener' => [
							self::THIRD_HOST_NAME => [
								'severity' => 'high',
								'count' => 1
							]
						],
						'Item navigator listener' => [
							self::THIRD_HOST_NAME => [
								'severity' => 'high',
								'count' => 1
							]
						]
					]
				]
			],
			'Broadcasting hostgroups from web monitoring widget - no selection' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Web monitoring hostgroup broadcaster',
					'autoselected' => self::FIRST_HOSTGROUP_NAME,
					'expected' => [
						'Data overview listener' => [
							'Hosts' => self::FIRST_HOST_NAME,
							'Trapper item' => '3'
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
						],
						'Host navigator listener' => [
							self::FIRST_HOST_NAME => [
								'severity' => 'info',
								'count' => 1
							]
						],
						'Item navigator listener' => [
							self::FIRST_HOST_NAME => [
								'severity' => 'info',
								'count' => 1
							]
						]
					]
				]
			],
			'Broadcasting hostgroups from web monitoring widget - initial selection' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Web monitoring hostgroup broadcaster',
					'select_element' => self::THIRD_HOSTGROUP_NAME,
					'expected' => [
						'Data overview listener' => [
							'Hosts' => self::THIRD_HOST_NAME,
							'Trapper item' => '5'
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
						],
						'Host navigator listener' => [
							self::THIRD_HOST_NAME => [
								'severity' => 'high',
								'count' => 1
							]
						],
						'Item navigator listener' => [
							self::THIRD_HOST_NAME => [
								'severity' => 'high',
								'count' => 1
							]
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
						'Data overview listener' => [
							'Hosts' => self::SECOND_HOST_NAME,
							'Trapper item' => '4'
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
						],
						'Host navigator listener' => [
							self::SECOND_HOST_NAME => [
								'severity' => 'warning',
								'count' => 1
							]
						],
						'Item navigator listener' => [
							self::SECOND_HOST_NAME => [
								'severity' => 'warning',
								'count' => 1
							]
						]
					]
				]
			],
			'Broadcasting hosts from geomap widget - no selection' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Geomap host broadcaster',
					'autoselected' => self::GEOMAP_ICON_INDEXES[self::SECOND_HOST_NAME],
					'expected' => [
						'Data overview listener' => [
							'Hosts' => self::SECOND_HOST_NAME,
							'Trapper item' => '4'
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
						],
						'Item navigator listener' => [
							self::SECOND_HOST_NAME => [
								'severity' => 'warning',
								'count' => 1
							]
						]
					]
				]
			],
			'Broadcasting hosts from geomap widget - initial selection' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Geomap host broadcaster',
					'select_element' => self::GEOMAP_ICON_INDEXES[self::THIRD_HOST_NAME],
					'expected' => [
						'Data overview listener' => [
							'Hosts' => self::THIRD_HOST_NAME,
							'Trapper item' => '5'
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
						],
						'Item navigator listener' => [
							self::THIRD_HOST_NAME => [
								'severity' => 'high',
								'count' => 1
							]
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
						'Data overview listener' => [
							'Hosts' => self::SECOND_HOST_NAME,
							'Trapper item' => '4'
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
						],
						'Item navigator listener' => [
							self::SECOND_HOST_NAME => [
								'severity' => 'warning',
								'count' => 1
							]
						]
					]
				]
			],
			'Broadcasting hosts from honeycomb widget - no selection' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Honeycomb host broadcaster',
					'autoselected' => self::FIRST_HOST_NAME,
					'expected' => [
						'Data overview listener' => [
							'Hosts' => self::FIRST_HOST_NAME,
							'Trapper item' => '3'
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
						],
						'Item navigator listener' => [
							self::FIRST_HOST_NAME => [
								'severity' => 'info',
								'count' => 1
							]
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
						'Data overview listener' => [
							'Hosts' => self::THIRD_HOST_NAME,
							'Trapper item' => '5'
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
						],
						'Item navigator listener' => [
							self::THIRD_HOST_NAME => [
								'severity' => 'high',
								'count' => 1
							]
						]
					]
				]
			],
			'Broadcasting hosts from honeycomb widget - selecting another value' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Honeycomb host broadcaster',
					'select_element' => self::SECOND_HOST_NAME,
					'expected' => [
						'Data overview listener' => [
							'Hosts' => self::SECOND_HOST_NAME,
							'Trapper item' => '4'
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
						],
						'Item navigator listener' => [
							self::SECOND_HOST_NAME => [
								'severity' => 'warning',
								'count' => 1
							]
						]
					]
				]
			],
			// The first element is selected by default, that is a hostgroup, therefore listeners display infiltered data.
			'Broadcasting hosts from map widget - no selection' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Map host broadcaster',
					'autoselected' => self::FIRST_HOSTGROUP_NAME
				]
			],
			'Broadcasting hosts from map widget - initial selection' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Map host broadcaster',
					'select_element' => self::SECOND_HOST_NAME,
					'expected' => [
						'Data overview listener' => [
							'Hosts' => self::SECOND_HOST_NAME,
							'Trapper item' => '4'
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
						],
						'Item navigator listener' => [
							self::SECOND_HOST_NAME => [
								'severity' => 'warning',
								'count' => 1
							]
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
						'Data overview listener' => [
							'Hosts' => self::THIRD_HOST_NAME,
							'Trapper item' => '5'
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
						],
						'Item navigator listener' => [
							self::THIRD_HOST_NAME => [
								'severity' => 'high',
								'count' => 1
							]
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
			'Broadcasting hosts from top hosts widget - no selection' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Top hosts host broadcaster',
					'autoselected' => self::THIRD_HOST_NAME,
					'expected' => [
						'Data overview listener' => [
							'Hosts' => self::THIRD_HOST_NAME,
							'Trapper item' => '5'
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
						],
						'Item navigator listener' => [
							self::THIRD_HOST_NAME => [
								'severity' => 'high',
								'count' => 1
							]
						]
					]
				]
			],
			'Broadcasting hosts from top hosts widget - initial selection' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Top hosts host broadcaster',
					'select_element' => self::FIRST_HOST_NAME,
					'expected' => [
						'Data overview listener' => [
							'Hosts' => self::FIRST_HOST_NAME,
							'Trapper item' => '3'
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
						],
						'Item navigator listener' => [
							self::FIRST_HOST_NAME => [
								'severity' => 'info',
								'count' => 1
							]
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
						'Data overview listener' => [
							'Hosts' => self::SECOND_HOST_NAME,
							'Trapper item' => '4'
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
						],
						'Item navigator listener' => [
							self::SECOND_HOST_NAME => [
								'severity' => 'warning',
								'count' => 1
							]
						]
					]
				]
			],
			'Broadcasting hosts from host navigator widget - no selection' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Host navigator broadcaster',
					'autoselected' => self::FIRST_HOST_NAME,
					'expected' => [
						'Data overview listener' => [
							'Hosts' => self::FIRST_HOST_NAME,
							'Trapper item' => '3'
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
						],
						'Item navigator listener' => [
							self::FIRST_HOST_NAME => [
								'severity' => 'info',
								'count' => 1
							]
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
						'Data overview listener' => [
							'Hosts' => self::THIRD_HOST_NAME,
							'Trapper item' => '5'
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
						],
						'Item navigator listener' => [
							self::THIRD_HOST_NAME => [
								'severity' => 'high',
								'count' => 1
							]
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
						'Data overview listener' => [
							'Hosts' => self::FIRST_HOST_NAME,
							'Trapper item' => '3'
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
						],
						'Item navigator listener' => [
							self::FIRST_HOST_NAME => [
								'severity' => 'info',
								'count' => 1
							]
						]
					]
				]
			],
			'Broadcasting items from honeycomb widget - no selection' => [
				[
					'page' => 'Items page',
					'broadcaster' => 'Honeycomb item broadcaster',
					'autoselected' => self::FIRST_HOST_NAME,
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
			'Broadcasting items from item history widget - no selection' => [
				[
					'page' => 'Items page',
					'broadcaster' => 'Item history item broadcaster',
					'autoselected' => self::FIRST_HOST_NAME,
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
			'Broadcasting items from item navigator widget - no selection' => [
				[
					'page' => 'Items page',
					'broadcaster' => 'Item navigator broadcaster',
					'autoselected' => self::FIRST_HOST_NAME,
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
			'Broadcasting items from item navigator widget - initial selection' => [
				[
					'page' => 'Items page',
					'broadcaster' => 'Item navigator broadcaster',
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
			'Broadcasting items from item navigator widget - selecting another value' => [
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
			],
			'Broadcasting map from navigation tree widget - no selection' => [
				[
					'page' => 'Maps page',
					'broadcaster' => 'Navigation tree map broadcaster',
					'autoselected' => self::MAP_NAME,
					'expected' => [
						'Map listener' => [self::SUBMAP_NAME]
					]
				]
			],
			'Broadcasting map from navigation tree widget - initial selection' => [
				[
					'page' => 'Maps page',
					'broadcaster' => 'Navigation tree map broadcaster',
					'select_element' => self::SUBMAP_NAME,
					'expected' => [
						'Map listener' => [self::FIRST_HOSTGROUP_NAME, self::THIRD_HOST_NAME]
					]
				]
			],
			'Broadcasting map from navigation tree widget - selecting another value' => [
				[
					'page' => 'Maps page',
					'broadcaster' => 'Navigation tree map broadcaster',
					'select_element' => self::MAP_NAME,
					'expected' => [
						'Map listener' => [self::SUBMAP_NAME]
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

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$entityids['dashboardid'])
				->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();

		if ($dashboard->getSelectedPageName() !== $data['page']) {
			$dashboard->selectPage($data['page']);
		}

		$broadcaster = $dashboard->getWidget($data['broadcaster']);

		/**
		 * If nothing should be selected on the widget, check that correct element is selected automatically on the
		 * broadcaster in case if it has at least one listener.
		 * If that's not the case, select an element on the widget.
		 */
		if (CTestArrayHelper::get($data, 'autoselected')) {
			$broadcaster_type = $this->getWidgetType($broadcaster);

			/**
			 * Apart from geomap widget all broadcaster types have a certain class added to the element or its parent,
			 * when this element is selected. So to check if the element is selected, a check that a certain class
			 * is present on a certain page element is performed.
			 */
			if ($broadcaster_type !== 'geomap') {
				switch ($broadcaster_type) {
					case 'problemhosts':
					case 'problemsbysv':
					case 'tophosts':
					case 'web':
						$class = 'row-selected';
						$selected_element = $this->getWidgetElement($data['autoselected'], $broadcaster);
						break;

					case 'map':
						$class = 'selected';
						$selected_element = $this->getWidgetElement($data['autoselected'], $broadcaster);
						break;

					case 'navtree':
						$class = 'selected';
						$selected_element = $broadcaster->query('xpath:.//a[text()='.
								CXPathHelper::escapeQuotes($data['autoselected']).']/../../..'
						)->one();
						break;

					case 'itemhistory':
						$class = 'selected';
						$selected_element = $broadcaster->query('xpath:.//td[text()='.
								CXPathHelper::escapeQuotes($data['autoselected'].': Trapper item').']/..'
						)->one();
						break;

					case 'hostnavigator':
						$class = 'navigation-tree-node-is-selected';
						$selected_element = $broadcaster->query('xpath:.//span[text()='.
								CXPathHelper::escapeQuotes($data['autoselected']).']/../../..'
						)->one();
						break;

					case 'itemnavigator':
						$class = 'navigation-tree-node-is-selected';
						$itemid = CDataHelper::get('WidgetCommunication.itemids')[$data['autoselected'].':trap.widget.communication'];
						$selected_element = $broadcaster->query('xpath:.//div[@data-id='.$itemid.']')->one();
						break;

					case 'honeycomb':
						$class = 'svg-honeycomb-cell-selected';

						$selected_element = $broadcaster->query('xpath:.//div[text()='.
								CXPathHelper::escapeQuotes($data['autoselected']).']/../../../..'
						)->one();
						break;
				}

				$this->assertTrue($selected_element->hasClass($class), 'Expected element is not selected by default.');
			}
			else {
				/**
				 * The only thing that differs between a selected host and a non selected host on a geomap widget is the
				 * base64 format hash of the icon that represent this host. So to check if host is selected, the icon hash
				 * is compared to an icon hash of a selected host with warning severity problem color.
				 */
				$base64_hash = 'data:image/svg+xml;base64,CgkJCQk8c3ZnIHdpZHRoPSIyNCIgaGVpZ2h0PSIzMiIgdmlld0JveD0iMCAwI'.
					'DI0IDMyIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgoJCQkJCTxwYXRoIGZpbGw9IiN'.
					'jOWUzZmMiIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBkPSJNMTIgMzBDMTMuNjIgMzAgMjIgMTcuM'.
					'jEyNCAyMiAxMS44QzIyIDYuMzg3NjEgMTcuNTIyOCAyIDEyIDJDNi40NzcxNSAyIDIgNi4zODc2MSAyIDExLjhDMiAxNy4yMTI'.
					'0IDEwLjM4IDMwIDEyIDMwWk0xMiAxNkMxNC4yMDkxIDE2IDE2IDE0LjIwOTEgMTYgMTJDMTYgOS43OTA4NiAxNC4yMDkxIDggM'.
					'TIgOEM5Ljc5MDg2IDggOCA5Ljc5MDg2IDggMTJDOCAxNC4yMDkxIDkuNzkwODYgMTYgMTIgMTZaIi8+CgkJCQkJPHBhdGggZml'.
					'sbD0iI2NlZDdkYiIgZD0iTTIxLjUgMTEuOEMyMS41IDEzLjA1MDQgMjEuMDA5IDE0Ljc4ODggMjAuMjAzMyAxNi43MzU5QzE5L'.
					'jQwMzggMTguNjY4MiAxOC4zMTc2IDIwLjc1MjMgMTcuMTc3NSAyMi42NzQ2QzE2LjAzNzEgMjQuNTk3MSAxNC44NTAxIDI2LjM'.
					'0NTUgMTMuODUzNSAyNy42MDc1QzEzLjM1NDEgMjguMjQgMTIuOTExMyAyOC43MzkxIDEyLjU1MjggMjkuMDc1MkMxMi4zNzI5I'.
					'DI5LjI0MzkgMTIuMjI1NiAyOS4zNjA3IDEyLjExMjMgMjkuNDMyM0MxMS45ODQ0IDI5LjUxMzEgMTEuOTU2MyAyOS41IDEyIDI'.
					'5LjVWMzAuNUMxMi4yNDYyIDMwLjUgMTIuNDczNCAzMC4zODcgMTIuNjQ2MyAzMC4yNzc4QzEyLjgzMzcgMzAuMTU5NSAxMy4wM'.
					'zI1IDI5Ljk5NjMgMTMuMjM2OCAyOS44MDQ3QzEzLjY0NjcgMjkuNDIwMyAxNC4xMjQ0IDI4Ljg3ODEgMTQuNjM4NCAyOC4yMjc'.
					'yQzE1LjY2ODcgMjYuOTIyNSAxNi44ODA0IDI1LjEzNTYgMTguMDM3NiAyMy4xODQ3QzE5LjE5NDkgMjEuMjMzNSAyMC4zMDQ5I'.
					'DE5LjEwNTkgMjEuMTI3MyAxNy4xMTgzQzIxLjk0MzYgMTUuMTQ1NSAyMi41IDEzLjI1NTggMjIuNSAxMS44SDIxLjVaTTEyIDI'.
					'uNUMxNy4yNTYzIDIuNSAyMS41IDYuNjczMjMgMjEuNSAxMS44SDIyLjVDMjIuNSA2LjEwMTk5IDE3Ljc4OTQgMS41IDEyIDEuN'.
					'VYyLjVaTTIuNSAxMS44QzIuNSA2LjY3MzIzIDYuNzQzNzIgMi41IDEyIDIuNVYxLjVDNi4yMTA1OCAxLjUgMS41IDYuMTAxOTk'.
					'gMS41IDExLjhIMi41Wk0xMiAyOS41QzEyLjA0MzcgMjkuNSAxMi4wMTU2IDI5LjUxMzEgMTEuODg3NyAyOS40MzIzQzExLjc3N'.
					'DQgMjkuMzYwNyAxMS42MjcxIDI5LjI0MzkgMTEuNDQ3MiAyOS4wNzUyQzExLjA4ODcgMjguNzM5MSAxMC42NDU5IDI4LjI0IDE'.
					'wLjE0NjUgMjcuNjA3NUM5LjE0OTg4IDI2LjM0NTUgNy45NjI4NSAyNC41OTcxIDYuODIyNTMgMjIuNjc0NkM1LjY4MjM4IDIwL'.
					'jc1MjMgNC41OTYxOCAxOC42NjgyIDMuNzk2NyAxNi43MzU5QzIuOTkxMDQgMTQuNzg4OCAyLjUgMTMuMDUwNCAyLjUgMTEuOEg'.
					'xLjVDMS41IDEzLjI1NTggMi4wNTY0NSAxNS4xNDU1IDIuODcyNjcgMTcuMTE4M0MzLjY5NTA1IDE5LjEwNTkgNC44MDUwOSAyM'.
					'S4yMzM1IDUuOTYyNDQgMjMuMTg0N0M3LjExOTYxIDI1LjEzNTYgOC4zMzEzMyAyNi45MjI1IDkuMzYxNjMgMjguMjI3MkM5Ljg'.
					'3NTU5IDI4Ljg3ODEgMTAuMzUzMyAyOS40MjAzIDEwLjc2MzIgMjkuODA0N0MxMC45Njc1IDI5Ljk5NjMgMTEuMTY2MyAzMC4xN'.
					'Tk1IDExLjM1MzcgMzAuMjc3OEMxMS41MjY2IDMwLjM4NyAxMS43NTM4IDMwLjUgMTIgMzAuNVYyOS41Wk0xNS41IDEyQzE1LjU'.
					'gMTMuOTMzIDEzLjkzMyAxNS41IDEyIDE1LjVWMTYuNUMxNC40ODUzIDE2LjUgMTYuNSAxNC40ODUzIDE2LjUgMTJIMTUuNVpNM'.
					'TIgOC41QzEzLjkzMyA4LjUgMTUuNSAxMC4wNjcgMTUuNSAxMkgxNi41QzE2LjUgOS41MTQ3MiAxNC40ODUzIDcuNSAxMiA3LjV'.
					'WOC41Wk04LjUgMTJDOC41IDEwLjA2NyAxMC4wNjcgOC41IDEyIDguNVY3LjVDOS41MTQ3MiA3LjUgNy41IDkuNTE0NzIgNy41I'.
					'DEySDguNVpNMTIgMTUuNUMxMC4wNjcgMTUuNSA4LjUgMTMuOTMzIDguNSAxMkg3LjVDNy41IDE0LjQ4NTMgOS41MTQ3MiAxNi4'.
					'1IDEyIDE2LjVWMTUuNVoiLz4KCQkJCQk8cGF0aCBmaWxsPSIjRkZDODU5IiBmaWxsLXJ1bGU9ImV2ZW5vZGQiIGNsaXAtcnVsZ'.
					'T0iZXZlbm9kZCIgZD0iTTEyIDI0QzEyLjk3MiAyNCAxOCAxNS43Nzk0IDE4IDEyLjNDMTggOC44MjA2MSAxNS4zMTM3IDYgMTI'.
					'gNkM4LjY4NjI5IDYgNiA4LjgyMDYxIDYgMTIuM0M2IDE1Ljc3OTQgMTEuMDI4IDI0IDEyIDI0Wk0xMi4wMDAxIDE1LjA3NTVDM'.
					'TMuNDIwMyAxNS4wNzU1IDE0LjU3MTYgMTMuODU2NSAxNC41NzE2IDEyLjM1MjhDMTQuNTcxNiAxMC44NDkxIDEzLjQyMDMgOS4'.
					'2MzAxMSAxMi4wMDAxIDkuNjMwMTFDMTAuNTggOS42MzAxMSA5LjQyODcxIDEwLjg0OTEgOS40Mjg3MSAxMi4zNTI4QzkuNDI4N'.
					'zEgMTMuODU2NSAxMC41OCAxNS4wNzU1IDEyLjAwMDEgMTUuMDc1NVoiLz4KCQkJCTwvc3ZnPg==';

				$this->assertEquals($base64_hash, $this->getWidgetElement($data['autoselected'], $broadcaster)
						->getAttribute('src')
				);
			}
		}
		else {
			$this->getWidgetElement($data['select_element'], $broadcaster)->click();
			$dashboard->waitUntilReady();

			$this->closeOpenedPopup();
		}

		if (!array_key_exists('expected', $data)) {
			$data['expected'] = self::DEFAULT_WIDGET_CONTENT[$data['page']];
		}

		$this->checkDataOnListener($data['expected']);

		/**
		 * Check that item listeners without defined name get "host name: Item name" name when element on broadcaster
		 * is selected.
		 */
		if ($data['page'] === 'Items page') {
			$host_in_name = (array_key_exists('autoselected', $data)) ? $data['autoselected'] : $data['select_element'];
			$this->assertTrue($dashboard->getWidget($host_in_name.': Trapper item')->isValid());
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
								'Trapper item' => '3'
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
							'Trapper item' => '5'
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
							'Trapper item' => '4'
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
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$entityids['dashboardid'])
				->waitUntilReady();
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
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$entityids['dashboardid'])
				->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$dashboard->edit();

		foreach (['Hostgroups page' => 'Host groups', 'Hosts page' => 'Hosts', 'Items page' => 'Item'] as $page => $field) {
			$dashboard->selectPage($page);
			$broadcaster = self::$current_broadcasters[$page];

			if ($page === 'Items page') {
				$listeners = ['Gauge listener', 'Graph (classic) listener', 'Item value listener', 'SVG graph listener',
						'Pie chart listener', 'Item value'
				];
			}
			else {
				$listeners = array_keys(self::DEFAULT_WIDGET_CONTENT[$page]);
			}

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
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$entityids['dashboardid'])
				->waitUntilReady();
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
		// Added waitUntilReady() to check for unstable test with js 32739:4 Uncaught error.
		$dashboard->waitUntilReady();
	}

	public static function getWidgetRebroadcastingData() {
		return [
			'Re-broadcasting host via Map widget tree widget - initial selection' => [
				[
					'broadcaster' => 'Navigation tree broadcaster',
					'select_element' => self::SECOND_HOST_NAME.' map',
					'expected' => [
						'Map re-broadcaster' => [self::SECOND_HOST_NAME],
						'Item value host listener from map' => [
							'class' => 'item-value-content',
							'value' => 4
						]
					]
				]
			],
			'Re-broadcasting host via Map widget tree widget - another value' => [
				[
					'broadcaster' => 'Navigation tree broadcaster',
					'select_element' => self::FIRST_HOST_NAME.' map',
					'expected' => [
						'Map re-broadcaster' => [self::FIRST_HOST_NAME],
						'Item value host listener from map' => [
							'class' => 'item-value-content',
							'value' => 3
						]
					]
				]
			],
			'Re-broadcasting hostgroup via Map widget tree widget - initial selection' => [
				[
					'broadcaster' => 'Navigation tree broadcaster',
					'select_element' => self::SECOND_HOSTGROUP_NAME.' map',
					'expected' => [
						'Map re-broadcaster' => [self::SECOND_HOSTGROUP_NAME],
						'Honeycomb hostgroup listener from map' => [
							self::SECOND_HOST_NAME => 4
						]
					]
				]
			],
			'Re-broadcasting hostgroup via Map widget tree widget - another value' => [
				[
					'broadcaster' => 'Navigation tree broadcaster',
					'select_element' => self::FIRST_HOSTGROUP_NAME.' map',
					'expected' => [
						'Map re-broadcaster' => [self::FIRST_HOSTGROUP_NAME],
						'Honeycomb hostgroup listener from map' => [
							self::FIRST_HOST_NAME => 3
						]
					]
				]
			],
			'Re-broadcasting hosts via geomap widget - initial selection' => [
				[
					'broadcaster' => 'Honeycomb broadcaster',
					'select_element' => self::THIRD_HOST_NAME,
					'expected' => [
						'Geomap re-broadcaster' => [
							self::GEOMAP_FILTERED_ICON_INDEX => [
								'Host' => self::THIRD_HOST_NAME,
								'H' => '1'
							]
						],
						'Gauge host listener from geomap' => [
							'class' => 'svg-gauge-value',
							'value' => 5
						]
					]
				]
			],
			'Re-broadcasting hosts via geomap widget - another value' => [
				[
					'broadcaster' => 'Honeycomb broadcaster',
					'select_element' => self::SECOND_HOST_NAME,
					'expected' => [
						'Geomap re-broadcaster' => [
							self::GEOMAP_FILTERED_ICON_INDEX => [
								'Host' => self::SECOND_HOST_NAME,
								'W' => '1'
							]
						],
						'Gauge host listener from geomap' => [
							'class' => 'svg-gauge-value',
							'value' => 4
						]
					]
				]
			],
			'Re-broadcasting hostgroups via problems by severity widget - initial selection' => [
				[
					'broadcaster' => 'Honeycomb broadcaster',
					'select_element' => self::THIRD_HOST_NAME,
					'expected' => [
						'Problems by severity re-broadcaster' => [
							'Host group' => self::THIRD_HOSTGROUP_NAME,
							'High' => '1'
						],
						'Top hosts hostgroup listener from PBS' => [
							'Hostname' => self::THIRD_HOST_NAME,
							'Item value' => '5.00'
						]
					]
				]
			],
			'Re-broadcasting hostgroups via problems by severity widget - another value' => [
				[
					'broadcaster' => 'Honeycomb broadcaster',
					'select_element' => self::SECOND_HOST_NAME,
					'expected' => [
						'Problems by severity re-broadcaster' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'Warning' => '1'
						],
						'Top hosts hostgroup listener from PBS' => [
							'Hostname' => self::SECOND_HOST_NAME,
							'Item value' => '4.00'
						]
					]
				]
			],
			'Re-broadcasting items via item history widget - initial selection' => [
				[
					'broadcaster' => 'Honeycomb broadcaster',
					'select_element' => self::THIRD_HOST_NAME,
					'expected' => [
						'Problems by severity re-broadcaster' => [
							'Host group' => self::THIRD_HOSTGROUP_NAME,
							'High' => '1'
						],
						'Top hosts hostgroup listener from PBS' => [
							'Hostname' => self::THIRD_HOST_NAME,
							'Item value' => '5.00'
						]
					]
				]
			],
			'Re-broadcasting items via item history widget - another value' => [
				[
					'broadcaster' => 'Honeycomb broadcaster',
					'select_element' => self::FIRST_HOST_NAME,
					'expected' => [
						'Problems by severity re-broadcaster' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'Information' => '1'
						],
						'Top hosts hostgroup listener from PBS' => [
							'Hostname' => self::FIRST_HOST_NAME,
							'Item value' => '3.00'
						]
					]
				]
			],
			'Re-broadcasting hosts via honeycomb widget - initial selection' => [
				[
					'broadcaster' => 'Web monitoring broadcaster',
					'select_element' => self::SECOND_HOSTGROUP_NAME,
					'expected' => [
						'Honeycomb re-broadcaster' => [
							self::SECOND_HOST_NAME => 4
						],
						'Data overview host listener from honeycomb' => [
							'Hosts' => self::SECOND_HOST_NAME,
							'Trapper item' => '4'
						]
					]
				]
			],
			'Re-broadcasting hosts via honeycomb widget - another value' => [
				[
					'broadcaster' => 'Web monitoring broadcaster',
					'select_element' => self::THIRD_HOSTGROUP_NAME,
					'expected' => [
						'Honeycomb re-broadcaster' => [
							self::THIRD_HOST_NAME => 5
						],
						'Data overview host listener from honeycomb' => [
							'Hosts' => self::THIRD_HOST_NAME,
							'Trapper item' => '5'
						]
					]
				]
			],
			'Re-broadcasting items via honeycomb widget - initial selection' => [
				[
					'broadcaster' => 'Web monitoring broadcaster',
					'select_element' => self::FIRST_HOSTGROUP_NAME,
					'expected' => [
						'Honeycomb re-broadcaster' => [
							self::FIRST_HOST_NAME => 3
						],
						'Item value item listener from honeycomb' => [
							'class' => 'item-value-content',
							'value' => 3
						]
					]
				]
			],
			'Re-broadcasting items via honeycomb widget - another value' => [
				[
					'broadcaster' => 'Web monitoring broadcaster',
					'select_element' => self::SECOND_HOSTGROUP_NAME,
					'expected' => [
						'Honeycomb re-broadcaster' => [
							self::SECOND_HOST_NAME => 4
						],
						'Item value item listener from honeycomb' => [
							'class' => 'item-value-content',
							'value' => 4
						]
					]
				]
			],
			'Re-broadcasting hosts via top hosts widget - initial selection' => [
				[
					'broadcaster' => 'Web monitoring broadcaster',
					'select_element' => self::THIRD_HOSTGROUP_NAME,
					'expected' => [
						'Top hosts re-broadcaster' => [
							'Hostname' => self::THIRD_HOST_NAME,
							'Item value' => '5.00'
						],
						'Trigger overview host listener from top hosts' => [
							'triggers' => [self::THIRD_HOST_TRIGGER],
							'headers' => ['Triggers', self::THIRD_HOST_NAME]
						]
					]
				]
			],
			'Re-broadcasting hosts via top hosts widget - another value' => [
				[
					'broadcaster' => 'Web monitoring broadcaster',
					'select_element' => self::FIRST_HOSTGROUP_NAME,
					'expected' => [
						'Top hosts re-broadcaster' => [
							'Hostname' => self::FIRST_HOST_NAME,
							'Item value' => '3.00'
						],
						'Trigger overview host listener from top hosts' => [
							'triggers' => [self::FIRST_HOST_TRIGGER],
							'headers' => ['Triggers', self::FIRST_HOST_NAME]
						]
					]
				]
			],
			'Re-broadcasting hosts via host navigator widget - initial selection' => [
				[
					'broadcaster' => 'Map broadcaster',
					'select_element' => self::SECOND_HOSTGROUP_NAME,
					'expected' => [
						'Host navigator re-broadcaster' => [
							self::SECOND_HOST_NAME => [
								'severity' => 'warning',
								'count' => 1
							]
						],
						'Web monitoring hosts listener from host navigator' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'Unknown' => '1'
						]
					]
				]
			],
			'Re-broadcasting hosts via host navigator widget - another value' => [
				[
					'broadcaster' => 'Map broadcaster',
					'select_element' => self::THIRD_HOSTGROUP_NAME,
					'expected' => [
						'Host navigator re-broadcaster' => [
							self::THIRD_HOST_NAME => [
								'severity' => 'high',
								'count' => 1
							]
						],
						'Web monitoring hosts listener from host navigator' => [
							'Host group' => self::THIRD_HOSTGROUP_NAME,
							'Unknown' => '1'
						]
					]
				]
			],
			'Re-broadcasting hostgroups via problem hosts widget - initial selection' => [
				[
					'broadcaster' => 'Map broadcaster',
					'select_element' => self::FIRST_HOSTGROUP_NAME,
					'expected' => [
						'Problem hosts re-broadcaster' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'With problems' => '1',
							'Total' => '1'
						],
						'Problems by severity hostgroup listener from problem hosts' => [
							'Host group' => self::FIRST_HOSTGROUP_NAME,
							'Information' => '1'
						]
					]
				]
			],
			'Re-broadcasting hostgroups via problem hosts widget - another value' => [
				[
					'broadcaster' => 'Map broadcaster',
					'select_element' => self::SECOND_HOSTGROUP_NAME,
					'expected' => [
						'Problem hosts re-broadcaster' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'With problems' => '1',
							'Total' => '1'
						],
						'Problems by severity hostgroup listener from problem hosts' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'Warning' => '1'
						]
					]
				]
			],
			'Re-broadcasting items via item navigator widget - initial selection' => [
				[
					'broadcaster' => 'Problem hosts broadcaster',
					'select_element' => self::THIRD_HOSTGROUP_NAME,
					'expected' => [
						'Item navigator re-broadcaster' => [
							self::THIRD_HOST_NAME => [
								'severity' => 'high',
								'count' => 1
							]
						],
						'Pie chart item listener from item navigator' => [
							'name' => self::THIRD_HOST_NAME.': Trapper item',
							'value' => 5
						]
					]
				]
			],
			'Re-broadcasting items via item navigator widget - another value' => [
				[
					'broadcaster' => 'Problem hosts broadcaster',
					'select_element' => self::FIRST_HOSTGROUP_NAME,
					'expected' => [
						'Item navigator re-broadcaster' => [
							self::FIRST_HOST_NAME => [
								'severity' => 'info',
								'count' => 1
							]
						],
						'Pie chart item listener from item navigator' => [
							'name' => self::FIRST_HOST_NAME.': Trapper item',
							'value' => 3
						]
					]
				]
			],
			'Re-broadcasting hostgroups via web monitoring widget - initial selection' => [
				[
					'broadcaster' => 'Problem hosts broadcaster',
					'select_element' => self::SECOND_HOSTGROUP_NAME,
					'expected' => [
						'Web monitoring re-broadcaster' => [
							'Host group' => self::SECOND_HOSTGROUP_NAME,
							'Unknown' => '1'
						],
						'Honeycomb hostgroup listener from web monitoring' => [
							self::SECOND_HOST_NAME => 4
						]
					]
				]
			],
			'Re-broadcasting hostgroups via web monitoring widget - another value' => [
				[
					'broadcaster' => 'Problem hosts broadcaster',
					'select_element' => self::THIRD_HOSTGROUP_NAME,
					'expected' => [
						'Web monitoring re-broadcaster' => [
							'Host group' => self::THIRD_HOSTGROUP_NAME,
							'Unknown' => '1'
						],
						'Honeycomb hostgroup listener from web monitoring' => [
							self::THIRD_HOST_NAME => 5
						]
					]
				]
			]
		];
	}

	/**
	 * Check data displayed in re-broadcaster and listener widgets based on data selected in broadcaster widget.
	 *
	 * @dataProvider getWidgetRebroadcastingData
	 */
	public function testDashboardWidgetCommunication_CheckDataRebroadcasting($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$entityids['dashboardid'])
				->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();

		if ($dashboard->getSelectedPageName() !== 'Value re-broadcasting page') {
			$dashboard->selectPage('Value re-broadcasting page');
		}

		$this->getWidgetElement($data['select_element'], $dashboard->getWidget($data['broadcaster']))->click();
		$dashboard->waitUntilReady();

		$this->closeOpenedPopup();

		$this->checkDataOnListener($data['expected']);
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
					$error_field = 'Data set/1';
				}
				else {
					$this->assertEquals(['Unavailable widget'], $widget_form->getField($field)->getValue());
					$error_field = $field;
				}

				$widget_form->submit();
				$this->assertMessage(TEST_BAD, null, 'Invalid parameter "'.$error_field.'": referred widget is unavailable.');

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
				case 'dataover':
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
					$widget_table = $listener->asTable();

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
					$this->assertEquals(self::$entityids['itemids'][$values['hostname'].':trap.widget.communication'],
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

				case 'sysmap':
					// Check that map that is opened on listener contains expected map elements.
					foreach ($values as $element_name) {
						$this->assertEquals($listener->query('xpath:.//*[@class="map-elements"]//*[text()='.
								CXPathHelper::escapeQuotes($element_name).']/../../preceding::*[1]')->one()
								->isDisplayed(), 'The loaded map did not contain expected element.'
						);
					}
					break;

				case 'hostnavigator':
				case 'itemnavigator':
					$entries_count = $listener->query('class:navigation-tree-node-is-item')->all()->count();
					$this->assertEquals(count($values), $entries_count, 'More than expected entries found in '.$listener_type.' widget.');

					foreach ($values as $host_name => $details) {
						$index = (array_key_exists('index', $details)) ? $details['index'] : 0;

						$primary_info = $listener->query('class:navigation-tree-node-info-primary')->all()->asText();
						$this->assertEquals($host_name, $primary_info[$index]);

						if ($listener_type === 'itemnavigator') {
							$this->assertEquals('Trapper item', $primary_info[$index + 1]);
						}

						$this->assertEquals($details['count'], $listener->query('xpath:.//span[contains(@class, '.
								CXPathHelper::escapeQuotes('status-'.$details['severity'].'-bg').')]')->one()->getText()
						);
					}
					break;
			}
		}
	}
}

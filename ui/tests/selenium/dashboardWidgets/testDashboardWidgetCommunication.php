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


require_once dirname(__FILE__).'/../common/testWidgetCommunication.php';

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
			],
			'Host card listener' => null
		]
	];

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
						],
						'Host card listener' => [
							'Hostname' => self::FIRST_HOST_NAME
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
						],
						'Host card listener' => [
							'Hostname' => self::SECOND_HOST_NAME
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
						],
						'Host card listener' => [
							'Hostname' => self::THIRD_HOST_NAME
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
						],
						'Host card listener' => [
							'Hostname' => self::FIRST_HOST_NAME
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
						],
						'Host card listener' => [
							'Hostname' => self::SECOND_HOST_NAME
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
						],
						'Host card listener' => [
							'Hostname' => self::THIRD_HOST_NAME
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
						],
						'Host card listener' => [
							'Hostname' => self::FIRST_HOST_NAME
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
						],
						'Host card listener' => [
							'Hostname' => self::SECOND_HOST_NAME
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
						],
						'Host card listener' => [
							'Hostname' => self::THIRD_HOST_NAME
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
						],
						'Host card listener' => [
							'Hostname' => self::FIRST_HOST_NAME
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
			],
			'Broadcasting map from item history widget - initial selection' => [
				[
					'page' => 'Maps page',
					'broadcaster' => 'Navigation tree map broadcaster',
					'select_element' => self::MAP_NAME,
					'expected' => [
						'Map listener' => [self::SUBMAP_NAME]
					]
				]
			],
			'Broadcasting map from item history widget - selecting another value' => [
				[
					'page' => 'Maps page',
					'broadcaster' => 'Navigation tree map broadcaster',
					'select_element' => self::SUBMAP_NAME,
					'expected' => [
						'Map listener' => [self::FIRST_HOSTGROUP_NAME, self::THIRD_HOST_NAME]
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

			/*
			 * Hostcard widget uses reference "hostid" instead of "hostids", so for this widget the reference needs to
			 * be updated separately. For this reason the last symbol ("s") is removed from the old and new references.
			 */
			if ($data['page'] === 'Hosts page') {
				$new_reference = substr(self::BROADCASTER_REFERENCES[$data['broadcaster']], 0, -1);
				$old_reference = substr(self::BROADCASTER_REFERENCES[self::$current_broadcasters[$data['page']]], 0, -1);

				DBexecute('UPDATE widget_field SET value_str='.zbx_dbstr($new_reference).
						' WHERE value_str='.zbx_dbstr($old_reference)
				);
			}

			self::$current_broadcasters[$data['page']] = $data['broadcaster'];
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$entityids['dashboardid'])
				->waitUntilReady();
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
					if ($listener_name === 'Host card listener') {
						$field = 'Host';
					}

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

			if ($values === null) {
				$this->assertEquals('Awaiting data', $listener->query('class:no-data-message')->one()->getText());

				continue;
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

				case 'hostcard':
					$this->assertEquals($values['Hostname'], $listener->query('class:host-name')->one()->getText());

					break;
			}
		}
	}
}

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

	const GEOMAP_HASH_START = 'data:image/svg+xml;base64,CgkJCQk8c3ZnIHdpZHRoPSIyNCIgaGVpZ2h0PSIzMiIgdmlld0JveD0iMCAwIDI0IDMy'.
		'IiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgoJCQkJCTxwYXRoIGZpbGw9IiNjOWUzZmMiIGZpbGwtc'.
		'nVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBkPSJNMTIgMzBDMTMuNjIgMzAgMjIgMTcuMjEyNCAyMiAxMS44QzIyIDYuMzg3Nj'.
		'EgMTcuNTIyOCAyIDEyIDJDNi40NzcxNSAyIDIgNi4zODc2MSAyIDExLjhDMiAxNy4yMTI0IDEwLjM4IDMwIDEyIDMwWk0xMiAxNkMxNC4yMDk'.
		'xIDE2IDE2IDE0LjIwOTEgMTYgMTJDMTYgOS43OTA4NiAxNC4yMDkxIDggMTIgOEM5Ljc5MDg2IDggOCA5Ljc5MDg2IDggMTJDOCAxNC4yMDkx'.
		'IDkuNzkwODYgMTYgMTIgMTZaIi8+CgkJCQkJPHBhdGggZmlsbD0iI2NlZDdkYiIgZD0iTTIxLjUgMTEuOEMyMS41IDEzLjA1MDQgMjEuMDA5I'.
		'DE0Ljc4ODggMjAuMjAzMyAxNi43MzU5QzE5LjQwMzggMTguNjY4MiAxOC4zMTc2IDIwLjc1MjMgMTcuMTc3NSAyMi42NzQ2QzE2LjAzNzEgMj'.
		'QuNTk3MSAxNC44NTAxIDI2LjM0NTUgMTMuODUzNSAyNy42MDc1QzEzLjM1NDEgMjguMjQgMTIuOTExMyAyOC43MzkxIDEyLjU1MjggMjkuMDc'.
		'1MkMxMi4zNzI5IDI5LjI0MzkgMTIuMjI1NiAyOS4zNjA3IDEyLjExMjMgMjkuNDMyM0MxMS45ODQ0IDI5LjUxMzEgMTEuOTU2MyAyOS41IDEy'.
		'IDI5LjVWMzAuNUMxMi4yNDYyIDMwLjUgMTIuNDczNCAzMC4zODcgMTIuNjQ2MyAzMC4yNzc4QzEyLjgzMzcgMzAuMTU5NSAxMy4wMzI1IDI5L'.
		'jk5NjMgMTMuMjM2OCAyOS44MDQ3QzEzLjY0NjcgMjkuNDIwMyAxNC4xMjQ0IDI4Ljg3ODEgMTQuNjM4NCAyOC4yMjcyQzE1LjY2ODcgMjYuOT'.
		'IyNSAxNi44ODA0IDI1LjEzNTYgMTguMDM3NiAyMy4xODQ3QzE5LjE5NDkgMjEuMjMzNSAyMC4zMDQ5IDE5LjEwNTkgMjEuMTI3MyAxNy4xMTg'.
		'zQzIxLjk0MzYgMTUuMTQ1NSAyMi41IDEzLjI1NTggMjIuNSAxMS44SDIxLjVaTTEyIDIuNUMxNy4yNTYzIDIuNSAyMS41IDYuNjczMjMgMjEu'.
		'NSAxMS44SDIyLjVDMjIuNSA2LjEwMTk5IDE3Ljc4OTQgMS41IDEyIDEuNVYyLjVaTTIuNSAxMS44QzIuNSA2LjY3MzIzIDYuNzQzNzIgMi41I'.
		'DEyIDIuNVYxLjVDNi4yMTA1OCAxLjUgMS41IDYuMTAxOTkgMS41IDExLjhIMi41Wk0xMiAyOS41QzEyLjA0MzcgMjkuNSAxMi4wMTU2IDI5Lj'.
		'UxMzEgMTEuODg3NyAyOS40MzIzQzExLjc3NDQgMjkuMzYwNyAxMS42MjcxIDI5LjI0MzkgMTEuNDQ3MiAyOS4wNzUyQzExLjA4ODcgMjguNzM'.
		'5MSAxMC42NDU5IDI4LjI0IDEwLjE0NjUgMjcuNjA3NUM5LjE0OTg4IDI2LjM0NTUgNy45NjI4NSAyNC41OTcxIDYuODIyNTMgMjIuNjc0NkM1'.
		'LjY4MjM4IDIwLjc1MjMgNC41OTYxOCAxOC42NjgyIDMuNzk2NyAxNi43MzU5QzIuOTkxMDQgMTQuNzg4OCAyLjUgMTMuMDUwNCAyLjUgMTEuO'.
		'EgxLjVDMS41IDEzLjI1NTggMi4wNTY0NSAxNS4xNDU1IDIuODcyNjcgMTcuMTE4M0MzLjY5NTA1IDE5LjEwNTkgNC44MDUwOSAyMS4yMzM1ID'.
		'UuOTYyNDQgMjMuMTg0N0M3LjExOTYxIDI1LjEzNTYgOC4zMzEzMyAyNi45MjI1IDkuMzYxNjMgMjguMjI3MkM5Ljg3NTU5IDI4Ljg3ODEgMTA'.
		'uMzUzMyAyOS40MjAzIDEwLjc2MzIgMjkuODA0N0MxMC45Njc1IDI5Ljk5NjMgMTEuMTY2MyAzMC4xNTk1IDExLjM1MzcgMzAuMjc3OEMxMS41'.
		'MjY2IDMwLjM4NyAxMS43NTM4IDMwLjUgMTIgMzAuNVYyOS41Wk0xNS41IDEyQzE1LjUgMTMuOTMzIDEzLjkzMyAxNS41IDEyIDE1LjVWMTYuN'.
		'UMxNC40ODUzIDE2LjUgMTYuNSAxNC40ODUzIDE2LjUgMTJIMTUuNVpNMTIgOC41QzEzLjkzMyA4LjUgMTUuNSAxMC4wNjcgMTUuNSAxMkgxNi'.
		'41QzE2LjUgOS41MTQ3MiAxNC40ODUzIDcuNSAxMiA3LjVWOC41Wk04LjUgMTJDOC41IDEwLjA2NyAxMC4wNjcgOC41IDEyIDguNVY3LjVDOS4'.
		'1MTQ3MiA3LjUgNy41IDkuNTE0NzIgNy41IDEySDguNVpNMTIgMTUuNUMxMC4wNjcgMTUuNSA4LjUgMTMuOTMzIDguNSAxMkg3LjVDNy41IDE0L'.
		'jQ4NTMgOS41MTQ3MiAxNi41IDEyIDE2LjVWMTUuNVoiLz4KCQkJCQk8cGF0aCBmaWxsPSIj';

	const GEOMAP_HAS_END = 'IiBmaWxsLXJ1bGU9ImV2ZW5vZGQiIGNsaXAtcnVsZT0iZXZlbm9kZCIgZD0iTTEyIDI0QzEyLjk3MiAyNCAxOCAxNS4'.
		'3Nzk0IDE4IDEyLjNDMTggOC44MjA2MSAxNS4zMTM3IDYgMTIgNkM4LjY4NjI5IDYgNiA4LjgyMDYxIDYgMTIuM0M2IDE1Ljc3OTQgMTEuMDI4I'.
		'DI0IDEyIDI0Wk0xMi4wMDAxIDE1LjA3NTVDMTMuNDIwMyAxNS4wNzU1IDE0LjU3MTYgMTMuODU2NSAxNC41NzE2IDEyLjM1MjhDMTQuNTcxNiA'.
		'xMC44NDkxIDEzLjQyMDMgOS42MzAxMSAxMi4wMDAxIDkuNjMwMTFDMTAuNTggOS42MzAxMSA5LjQyODcxIDEwLjg0OTEgOS40Mjg3MSAxMi4zN'.
		'TI4QzkuNDI4NzEgMTMuODU2NSAxMC41OCAxNS4wNzU1IDEyLjAwMDEgMTUuMDc1NVoiLz4KCQkJCTwvc3ZnPg==';

	const GEOMAP_UNIQUE_HASH_PARTS = [
		'1st host for widgets' => 'NzQ5OUZG',
		'2nd host for widgets' => 'RkZDODU5',
		'3rd host for widgets' => 'RTk3NjU5'
	];

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
			],
			'Host card listener' => null,
			'Item card listener' => [
				'Hostname' => self::FIRST_HOST_NAME,
				'Last value' => 3
			],
			'Gauge listener' => [
				'class' => 'svg-gauge-value',
				'value' => 3
			],
			'Graph (classic) listener' => [
				'hostname' => self::FIRST_HOST_NAME
			],
			'Item history listener' => [
				'Name' => 'Trapper item',
				'Value' => '3'
			],
			'Item value listener' => [
				'class' => 'item-value-content',
				'value' => 3
			],
			'URL listener' => 'No data',
			'SVG graph listener' => [
				'class' => 'svg-graph-legend-item',
				'value' => self::FIRST_HOST_NAME.': Trapper item'
			]
		]
	];

	const SELECTED_CLASSES = [
		'problemhosts' => 'row-selected',
		'problemsbysv' => 'row-selected',
		'tophosts' => 'row-selected',
		'web' => 'row-selected',
		'map' => 'selected',
		'navtree' => 'selected',
		'itemhistory' => 'selected',
		'hostnavigator' => 'navigation-tree-node-is-selected',
		'itemnavigator' => 'navigation-tree-node-is-selected',
		'honeycomb' => 'svg-honeycomb-cell-selected'
	];

	public static function getWidgetData() {
		return [
			'Broadcasting hostgroups from map - default selection' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Map hostgroup broadcaster',
					'autoselected' => self::THIRD_HOSTGROUP_NAME,
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
						],
						'Problems listener' => [
							'Host' => self::THIRD_HOST_NAME,
							'Problem • Severity' => self::THIRD_HOST_TRIGGER
						]
					]
				]
			],
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
						],
						'Problems listener' => [
							'Host' => self::FIRST_HOST_NAME,
							'Problem • Severity' => self::FIRST_HOST_TRIGGER
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
						],
						'Problems listener' => [
							'Host' => self::SECOND_HOST_NAME,
							'Problem • Severity' => self::SECOND_HOST_TRIGGER
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
			'Broadcasting hostgroups from problem hosts widget - default selection' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Problem hosts hostgroup broadcaster',
					'autoselected' => self::FIRST_HOSTGROUP_NAME,
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
						],
						'Problems listener' => [
							'Host' => self::FIRST_HOST_NAME,
							'Problem • Severity' => self::FIRST_HOST_TRIGGER
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
						],
						'Problems listener' => [
							'Host' => self::SECOND_HOST_NAME,
							'Problem • Severity' => self::SECOND_HOST_TRIGGER
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
						],
						'Problems listener' => [
							'Host' => self::THIRD_HOST_NAME,
							'Problem • Severity' => self::THIRD_HOST_TRIGGER
						]
					]
				]
			],
			'Broadcasting hostgroups from problems by severity widget - default selection' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Problems by severity hostgroup broadcaster',
					'autoselected' => self::FIRST_HOSTGROUP_NAME,
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
						],
						'Problems listener' => [
							'Host' => self::FIRST_HOST_NAME,
							'Problem • Severity' => self::FIRST_HOST_TRIGGER
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
						],
						'Problems listener' => [
							'Host' => self::SECOND_HOST_NAME,
							'Problem • Severity' => self::SECOND_HOST_TRIGGER
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
						],
						'Problems listener' => [
							'Host' => self::THIRD_HOST_NAME,
							'Problem • Severity' => self::THIRD_HOST_TRIGGER
						]
					]
				]
			],
			'Broadcasting hostgroups from web monitoring widget - default selection' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Web monitoring hostgroup broadcaster',
					'autoselected' => self::FIRST_HOSTGROUP_NAME,
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
						],
						'Problems listener' => [
							'Host' => self::FIRST_HOST_NAME,
							'Problem • Severity' => self::FIRST_HOST_TRIGGER
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
						],
						'Problems listener' => [
							'Host' => self::THIRD_HOST_NAME,
							'Problem • Severity' => self::THIRD_HOST_TRIGGER
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
						],
						'Problems listener' => [
							'Host' => self::SECOND_HOST_NAME,
							'Problem • Severity' => self::SECOND_HOST_TRIGGER
						]
					]
				]
			],
			'Broadcasting hosts from geomap widget - default selection' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Geomap host broadcaster',
					'autoselected' => self::SECOND_HOST_NAME,
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
						'Item navigator listener' => [
							self::SECOND_HOST_NAME => [
								'severity' => 'warning',
								'count' => 1
							]
						],
						'Host card listener' => [
							'Hostname' => self::SECOND_HOST_NAME
						],
						'Item card listener' => [
							'Hostname' => self::SECOND_HOST_NAME,
							'Last value' => 4
						],
						'Gauge listener' => [
							'class' => 'svg-gauge-value',
							'value' => 4
						],
						'Graph (classic) listener' => [
							'hostname' => self::SECOND_HOST_NAME
						],
						'Item history listener' => [
							'Name' => 'Trapper item',
							'Value' => '4'
						],
						'Item value listener' => [
							'class' => 'item-value-content',
							'value' => 4
						],
						'URL listener' => self::SECOND_HOST_NAME
					]
				]
			],
			'Broadcasting hosts from geomap widget - initial selection' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Geomap host broadcaster',
					'select_element' => self::GEOMAP_ICON_INDEXES[self::THIRD_HOST_NAME],
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
						'Item navigator listener' => [
							self::THIRD_HOST_NAME => [
								'severity' => 'high',
								'count' => 1
							]
						],
						'Host card listener' => [
							'Hostname' => self::THIRD_HOST_NAME
						],
						'Item card listener' => [
							'Hostname' => self::THIRD_HOST_NAME,
							'Last value' => 5
						],
						'Gauge listener' => [
							'class' => 'svg-gauge-value',
							'value' => 5
						],
						'Graph (classic) listener' => [
							'hostname' => self::THIRD_HOST_NAME
						],
						'Item history listener' => [
							'Name' => 'Trapper item',
							'Value' => '5'
						],
						'Item value listener' => [
							'class' => 'item-value-content',
							'value' => 5
						],
						'URL listener' => self::THIRD_HOST_NAME
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
						'Item navigator listener' => [
							self::SECOND_HOST_NAME => [
								'severity' => 'warning',
								'count' => 1
							]
						],
						'Host card listener' => [
							'Hostname' => self::SECOND_HOST_NAME
						],
						'Item card listener' => [
							'Hostname' => self::SECOND_HOST_NAME,
							'Last value' => 4
						],
						'Gauge listener' => [
							'class' => 'svg-gauge-value',
							'value' => 4
						],
						'Graph (classic) listener' => [
							'hostname' => self::SECOND_HOST_NAME
						],
						'Item history listener' => [
							'Name' => 'Trapper item',
							'Value' => '4'
						],
						'Item value listener' => [
							'class' => 'item-value-content',
							'value' => 4
						],
						'URL listener' => self::SECOND_HOST_NAME
					]
				]
			],
			'Broadcasting hosts from honeycomb widget - default selection' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Honeycomb host broadcaster',
					'autoselected' => self::FIRST_HOST_NAME,
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
						'Item navigator listener' => [
							self::FIRST_HOST_NAME => [
								'severity' => 'info',
								'count' => 1
							]
						],
						'Host card listener' => [
							'Hostname' => self::FIRST_HOST_NAME
						],
						'Item card listener' => [
							'Hostname' => self::FIRST_HOST_NAME,
							'Last value' => 3
						],
						'Gauge listener' => [
							'class' => 'svg-gauge-value',
							'value' => 3
						],
						'Graph (classic) listener' => [
							'hostname' => self::FIRST_HOST_NAME
						],
						'Item history listener' => [
							'Name' => 'Trapper item',
							'Value' => '3'
						],
						'Item value listener' => [
							'class' => 'item-value-content',
							'value' => 3
						],
						'URL listener' => self::FIRST_HOST_NAME
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
						'Item navigator listener' => [
							self::THIRD_HOST_NAME => [
								'severity' => 'high',
								'count' => 1
							]
						],
						'Host card listener' => [
							'Hostname' => self::THIRD_HOST_NAME
						],
						'Item card listener' => [
							'Hostname' => self::THIRD_HOST_NAME,
							'Last value' => 5
						],
						'Gauge listener' => [
							'class' => 'svg-gauge-value',
							'value' => 5
						],
						'Graph (classic) listener' => [
							'hostname' => self::THIRD_HOST_NAME
						],
						'Item history listener' => [
							'Name' => 'Trapper item',
							'Value' => '5'
						],
						'Item value listener' => [
							'class' => 'item-value-content',
							'value' => 5
						],
						'URL listener' => self::THIRD_HOST_NAME
					]
				]
			],
			'Broadcasting hosts from honeycomb widget - selecting another value' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Honeycomb host broadcaster',
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
						'Item navigator listener' => [
							self::SECOND_HOST_NAME => [
								'severity' => 'warning',
								'count' => 1
							]
						],
						'Host card listener' => [
							'Hostname' => self::SECOND_HOST_NAME
						],
						'Item card listener' => [
							'Hostname' => self::SECOND_HOST_NAME,
							'Last value' => 4
						],
						'Gauge listener' => [
							'class' => 'svg-gauge-value',
							'value' => 4
						],
						'Graph (classic) listener' => [
							'hostname' => self::SECOND_HOST_NAME
						],
						'Item history listener' => [
							'Name' => 'Trapper item',
							'Value' => '4'
						],
						'Item value listener' => [
							'class' => 'item-value-content',
							'value' => 4
						],
						'URL listener' => self::SECOND_HOST_NAME
					]
				]
			],
			// The first element is selected by default, that is a hostgroup, therefore listeners display infiltered data.
			'Broadcasting hosts from map widget - default selection' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Map host broadcaster',
					'autoselected' => self::THIRD_HOSTGROUP_NAME
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
						'Item navigator listener' => [
							self::SECOND_HOST_NAME => [
								'severity' => 'warning',
								'count' => 1
							]
						],
						'Host card listener' => [
							'Hostname' => self::SECOND_HOST_NAME
						],
						'Item card listener' => [
							'Hostname' => self::SECOND_HOST_NAME,
							'Last value' => 4
						],
						'Gauge listener' => [
							'class' => 'svg-gauge-value',
							'value' => 4
						],
						'Graph (classic) listener' => [
							'hostname' => self::SECOND_HOST_NAME
						],
						'Item history listener' => [
							'Name' => 'Trapper item',
							'Value' => '4'
						],
						'Item value listener' => [
							'class' => 'item-value-content',
							'value' => 4
						],
						'URL listener' => self::SECOND_HOST_NAME
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
						'Item navigator listener' => [
							self::THIRD_HOST_NAME => [
								'severity' => 'high',
								'count' => 1
							]
						],
						'Host card listener' => [
							'Hostname' => self::THIRD_HOST_NAME
						],
						'Item card listener' => [
							'Hostname' => self::THIRD_HOST_NAME,
							'Last value' => 5
						],
						'Gauge listener' => [
							'class' => 'svg-gauge-value',
							'value' => 5
						],
						'Graph (classic) listener' => [
							'hostname' => self::THIRD_HOST_NAME
						],
						'Item history listener' => [
							'Name' => 'Trapper item',
							'Value' => '5'
						],
						'Item value listener' => [
							'class' => 'item-value-content',
							'value' => 5
						],
						'URL listener' => self::THIRD_HOST_NAME
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
			'Broadcasting hosts from top hosts widget - default selection' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Top hosts host broadcaster',
					'autoselected' => self::THIRD_HOST_NAME,
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
						'Item navigator listener' => [
							self::THIRD_HOST_NAME => [
								'severity' => 'high',
								'count' => 1
							]
						],
						'Host card listener' => [
							'Hostname' => self::THIRD_HOST_NAME
						],
						'Item card listener' => [
							'Hostname' => self::THIRD_HOST_NAME,
							'Last value' => 5
						],
						'Gauge listener' => [
							'class' => 'svg-gauge-value',
							'value' => 5
						],
						'Graph (classic) listener' => [
							'hostname' => self::THIRD_HOST_NAME
						],
						'Item history listener' => [
							'Name' => 'Trapper item',
							'Value' => '5'
						],
						'Item value listener' => [
							'class' => 'item-value-content',
							'value' => 5
						],
						'URL listener' => self::THIRD_HOST_NAME
					]
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
						'Item navigator listener' => [
							self::FIRST_HOST_NAME => [
								'severity' => 'info',
								'count' => 1
							]
						],
						'Host card listener' => [
							'Hostname' => self::FIRST_HOST_NAME
						],
						'Item card listener' => [
							'Hostname' => self::FIRST_HOST_NAME,
							'Last value' => 3
						],
						'Gauge listener' => [
							'class' => 'svg-gauge-value',
							'value' => 3
						],
						'Graph (classic) listener' => [
							'hostname' => self::FIRST_HOST_NAME
						],
						'Item history listener' => [
							'Name' => 'Trapper item',
							'Value' => '3'
						],
						'Item value listener' => [
							'class' => 'item-value-content',
							'value' => 3
						],
						'URL listener' => self::FIRST_HOST_NAME
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
						'Item navigator listener' => [
							self::SECOND_HOST_NAME => [
								'severity' => 'warning',
								'count' => 1
							]
						],
						'Host card listener' => [
							'Hostname' => self::SECOND_HOST_NAME
						],
						'Item card listener' => [
							'Hostname' => self::SECOND_HOST_NAME,
							'Last value' => 4
						],
						'Gauge listener' => [
							'class' => 'svg-gauge-value',
							'value' => 4
						],
						'Graph (classic) listener' => [
							'hostname' => self::SECOND_HOST_NAME
						],
						'Item history listener' => [
							'Name' => 'Trapper item',
							'Value' => '4'
						],
						'Item value listener' => [
							'class' => 'item-value-content',
							'value' => 4
						],
						'URL listener' => self::SECOND_HOST_NAME
					]
				]
			],
			'Broadcasting hosts from host navigator widget - default selection' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Host navigator broadcaster',
					'autoselected' => self::FIRST_HOST_NAME,
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
						'Item navigator listener' => [
							self::FIRST_HOST_NAME => [
								'severity' => 'info',
								'count' => 1
							]
						],
						'Host card listener' => [
							'Hostname' => self::FIRST_HOST_NAME
						],
						'Item card listener' => [
							'Hostname' => self::FIRST_HOST_NAME,
							'Last value' => 3
						],
						'Gauge listener' => [
							'class' => 'svg-gauge-value',
							'value' => 3
						],
						'Graph (classic) listener' => [
							'hostname' => self::FIRST_HOST_NAME
						],
						'Item history listener' => [
							'Name' => 'Trapper item',
							'Value' => '3'
						],
						'Item value listener' => [
							'class' => 'item-value-content',
							'value' => 3
						],
						'URL listener' => self::FIRST_HOST_NAME
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
						'Item navigator listener' => [
							self::THIRD_HOST_NAME => [
								'severity' => 'high',
								'count' => 1
							]
						],
						'Host card listener' => [
							'Hostname' => self::THIRD_HOST_NAME
						],
						'Item card listener' => [
							'Hostname' => self::THIRD_HOST_NAME,
							'Last value' => 5
						],
						'Gauge listener' => [
							'class' => 'svg-gauge-value',
							'value' => 5
						],
						'Graph (classic) listener' => [
							'hostname' => self::THIRD_HOST_NAME
						],
						'Item history listener' => [
							'Name' => 'Trapper item',
							'Value' => '5'
						],
						'Item value listener' => [
							'class' => 'item-value-content',
							'value' => 5
						],
						'URL listener' => self::THIRD_HOST_NAME
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
						'Item navigator listener' => [
							self::FIRST_HOST_NAME => [
								'severity' => 'info',
								'count' => 1
							]
						],
						'Host card listener' => [
							'Hostname' => self::FIRST_HOST_NAME
						],
						'Item card listener' => [
							'Hostname' => self::FIRST_HOST_NAME,
							'Last value' => 3
						],
						'Gauge listener' => [
							'class' => 'svg-gauge-value',
							'value' => 3
						],
						'Graph (classic) listener' => [
							'hostname' => self::FIRST_HOST_NAME
						],
						'Item history listener' => [
							'Name' => 'Trapper item',
							'Value' => '3'
						],
						'Item value listener' => [
							'class' => 'item-value-content',
							'value' => 3
						],
						'URL listener' => self::FIRST_HOST_NAME
					]
				]
			],
			'Broadcasting items from honeycomb widget - default selection' => [
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
						],
						'Item card listener' => [
							'Hostname' => self::FIRST_HOST_NAME,
							'Last value' => 3
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
						],
						'Item card listener' => [
							'Hostname' => self::THIRD_HOST_NAME,
							'Last value' => 5
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
						],
						'Item card listener' => [
							'Hostname' => self::SECOND_HOST_NAME,
							'Last value' => 4
						]
					]
				]
			],
			'Broadcasting items from item history widget - default selection' => [
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
						],
						'Item card listener' => [
							'Hostname' => self::FIRST_HOST_NAME,
							'Last value' => 3
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
						],
						'Item card listener' => [
							'Hostname' => self::SECOND_HOST_NAME,
							'Last value' => 4
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
						],
						'Item card listener' => [
							'Hostname' => self::THIRD_HOST_NAME,
							'Last value' => 5
						]
					]
				]
			],
			'Broadcasting items from item navigator widget - default selection' => [
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
						],
						'Item card listener' => [
							'Hostname' => self::FIRST_HOST_NAME,
							'Last value' => 3
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
						],
						'Item card listener' => [
							'Hostname' => self::THIRD_HOST_NAME,
							'Last value' => 5
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
						],
						'Item card listener' => [
							'Hostname' => self::SECOND_HOST_NAME,
							'Last value' => 4
						]
					]
				]
			],
			'Broadcasting map from navigation tree widget - default selection' => [
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
		// Change broadcasting widget, if required.
		if ($data['broadcaster'] !== self::$current_broadcasters[$data['page']]) {
			$this->changeBroadcaster($data['page'], $data['broadcaster']);
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
			$this->checkSelectedElement($broadcaster, $data['autoselected']);
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

	public static function getMisconfigurationListenerData() {
		return [
			'Misconfiguration for hostgroup listeners' => [
				[
					'page' => 'Hostgroups page',
					'broadcaster' => 'Map hostgroup broadcaster',
					'select_element' => self::FIRST_HOSTGROUP_NAME,
					'listeners_common_update' => [
						'Top items listener',
						'Geomap listener',
						'Honeycomb listener',
						'Problem hosts listener',
						'Problems by severity listener',
						'Top hosts listener',
						'Trigger overview listener',
						'Web monitoring listener',
						'Item navigator listener',
						'Problems listener'
					],
					'common_fill' => [
						'Hosts' => self::SECOND_HOST_NAME
					],
					'listeners_different_update' => [
						'Host navigator listener' => [
							'Host patterns' => self::SECOND_HOST_NAME
						]
					],
					'expected' => [
						'Top items listener' => 'No data found',
						'Geomap listener' => 'Empty map',
						'Honeycomb listener' => 'No data',
						'Problem hosts listener' => 'No data found',
						'Problems by severity listener' => 'No data found',
						'Top hosts listener' => 'No data found',
						'Host navigator listener' => 'No data found',
						'Trigger overview listener' => 'No data found',
						'Web monitoring listener' => 'No data found',
						'Item navigator listener' => 'No data found',
						'Problems listener' => 'No data found'
					]
				]
			],
			'Misconfiguration for host listeners' => [
				[
					'page' => 'Hosts page',
					'broadcaster' => 'Geomap host broadcaster',
					'select_element' => self::GEOMAP_ICON_INDEXES[self::THIRD_HOST_NAME],
					'listeners_common_update' => [
						'Top items listener',
						'Geomap listener',
						'Honeycomb listener',
						'Problem hosts listener',
						'Problems listener',
						'Problems by severity listener',
						'Top hosts listener',
						'Trigger overview listener',
						'Web monitoring listener',
						'Item navigator listener'
					],
					'common_fill' => [
						'Host groups' => self::FIRST_HOSTGROUP_NAME
					],
					'listeners_different_update' => [
						'Item card listener' => [
							'Item' => 'Unique trapper item'
						],
						'Gauge listener' => [
							'Item' => 'Unique trapper item'
						],
						'Graph (classic) listener' => [
							'Item' => 'Unique trapper item'
						],
						'Item value listener' => [
							'Item' => 'Unique trapper item'
						],
						'SVG graph listener' => [
							'xpath://div[@id="ds_0_items_"]/..' => 'Unique trapper item'
						]
					],
					'multistep_listener_update' => [
						'Item history listener' => [
							[
								'operation' => 'open_secondary_form',
								'element' => 'button:Edit'
							],
							[
								'operation' => 'fill',
								'element' => ['Item' => 'Unique trapper item']
							]
						]
					],
					'expected' => [
						'Top items listener' => 'No data found',
						'Geomap listener' => 'Empty map',
						'Honeycomb listener' => 'No data',
						'Problem hosts listener' => 'No data found',
						'Problems listener' => 'No data found',
						'Problems by severity listener' => 'No data found',
						'Top hosts listener' => 'No data found',
						'Trigger overview listener' => 'No data found',
						'Web monitoring listener' => 'No data found',
						'Item card listener' => 'No data found',
						'Item navigator listener' => 'No data found',
						'Gauge listener' => 'No permissions to referred object or it does not exist!',
						'Graph (classic) listener' => 'No permissions to referred object or it does not exist!',
						'Item history listener' => 'No data found',
						'Item value listener' => 'No permissions to referred object or it does not exist!',
						'SVG graph listener' => 'No legend'
					]
				]
			]
		];
	}

	/**
	 * Check the "No data" message in listener widgets, when misconfiguration figuration occurs due to data selected on the
	 * broadcaster widget. This test is implemented only for those widgets where such misconfiguration is possible.
	 * Scenario is implemented only for Hostgroups and Hosts page, since it is not possible to implement different
	 * misconfiguration in Items page and Maps page.
	 *
	 * @dataProvider getMisconfigurationListenerData
	 */
	public function testDashboardWidgetCommunication_CheckListenerMisconfiguration($data) {
		// Change broadcasting widget, if required.
		if ($data['broadcaster'] !== self::$current_broadcasters[$data['page']]) {
			$this->changeBroadcaster($data['page'], $data['broadcaster']);
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$entityids['dashboardid'])
				->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();

		if ($dashboard->getSelectedPageName() !== $data['page']) {
			$dashboard->selectPage($data['page']);
			$dashboard->waitUntilReady();
		}

		/**
		 * Data in listener widgets is updated in three parts:
		 * 1 - At first, update of the listeners with the most common field to be updated takes place;
		 * 2 - Then, listeners for which different fields need to be updated are modified;
		 * 3 - And then, the listeners, where the update requires multiple steps are modified.
		 */
		foreach ($data['listeners_common_update'] as $listener_name) {
			$dashboard->getWidget($listener_name)->edit()->fill($data['common_fill'])->submit();
			COverlayDialogElement::ensureNotPresent();
		}

		foreach ($data['listeners_different_update'] as $listener_name => $fields) {
			$dashboard->getWidget($listener_name)->edit()->fill($fields)->submit();
			COverlayDialogElement::ensureNotPresent();
		}

		if (array_key_exists('multistep_listener_update', $data)) {
			foreach ($data['multistep_listener_update'] as $listener_name => $steps) {
				$widget_form = $dashboard->getWidget($listener_name)->edit();

				// Currently, there are two possible steps: opening a secondary form and updating a field in this form.
				foreach ($steps as $step) {
					if ($step['operation'] === 'open_secondary_form') {
						$widget_form->query($step['element'])->one()->waitUntilClickable()->click();
						COverlayDialogElement::find(1)->waitUntilReady();
					}
					if ($step['operation'] === 'fill') {
						$secondary_dialog = COverlayDialogElement::find(1)->waitUntilReady()->asForm()->one();
						$secondary_form = $secondary_dialog->asForm();
						$secondary_form->fill($step['element']);
						$secondary_form->submit();
						$secondary_dialog->waitUntilNotPresent();
						$widget_form = COverlayDialogElement::get('Edit widget')->asForm();
					}
				}

				$widget_form->submit();
				COverlayDialogElement::ensureNotPresent();
			}
		}

		// Select an element on the broadcaster that would cause misconfiguration in the listener widgets.
		$this->getWidgetElement($data['select_element'], $dashboard->getWidget($data['broadcaster']))->click();
		$dashboard->waitUntilReady();
		$this->closeOpenedPopup();

		// Go through all updated listeners and theck that no data is available on the listener widget.
		foreach ($data['expected'] as $listener_name => $outcome) {
			$listener = $dashboard->getWidget($listener_name);

			switch ($outcome) {
				case 'No data found':
				case 'No data':
				case 'No permissions to referred object or it does not exist!':
					$class = ($outcome === 'No data') ? 'svg-honeycomb-content' : 'no-data-message';
					$no_data_message = $listener->query('class', $class)->one();
					$this->assertTrue($no_data_message->isDisplayed(), 'No data message is missing.');
					$this->assertEquals($outcome, $no_data_message->getText());
					break;

				case 'Empty map':
					$this->assertFalse($listener->query('class:leaflet-marker-icon')->one(false)->isValid(),
							'Host icon is unexpectedly present on geomap widget.'
					);
					break;

				case 'No legend':
					$this->assertFalse($listener->query('class:svg-graph-legend-item')->one(false)->isValid(),
							'Legend with selected item is unexpectedly present on graph widget.'
					);
			}
		}
	}

	/**
	 * Change broadcasting widget for listener widgets on corresponding page.
	 *
	 * @param string   $page			Name of the dashboard page for which the broadcaster needs to be changed.
	 * @param string   $broadcaster		Name of the new broadcaster widget.
	 */
	protected function changeBroadcaster($page, $broadcaster) {
		DBexecute('UPDATE widget_field SET value_str='.zbx_dbstr(self::BROADCASTER_REFERENCES[$broadcaster]).
				' WHERE value_str='.zbx_dbstr(self::BROADCASTER_REFERENCES[self::$current_broadcasters[$page]])
		);

		if (in_array($page, ['Hostgroups page', 'Hosts page'])) {
			DBexecute('UPDATE widget_field SET value_str='.zbx_dbstr(self::SINGLE_ENTITY_BROADCASTER_REFERENCES[$broadcaster]).
					' WHERE value_str='.zbx_dbstr(self::SINGLE_ENTITY_BROADCASTER_REFERENCES[self::$current_broadcasters[$page]])
			);
		}

		self::$current_broadcasters[$page] = $broadcaster;
	}

	/**
	 * Check the value that is marked as selected on the widget under attention.
	 *
	 * @param CWidgetElement   $widget         widget in which the selected element should be checked
	 * @param string           $selected       indicator of the element that should be marked as selected
	 * @param string           $override_key   used only in item navigator widget when item key differs from the common one
	 */
	protected function checkSelectedElement($widget, $selected, $override_key = null) {
		$widget_type = $this->getWidgetType($widget);

		/**
		 * Apart from geomap widget all broadcaster types have a certain class added to the element or its parent,
		 * when this element is selected. So to check if the element is selected, a check that a certain class
		 * is present on a certain page element is performed.
		 */
		if ($widget_type !== 'geomap') {
			switch ($widget_type) {
				case 'problemhosts':
				case 'problemsbysv':
				case 'tophosts':
				case 'map':
					$selected_element = $this->getWidgetElement($selected, $widget);
					break;

				case 'web':
					$selected_element = $widget->query('xpath:.//a[text()='.CXPathHelper::escapeQuotes($selected).']/../..')
							->one();
					break;

				case 'navtree':
					$selected_element = $widget->query('xpath:.//a[text()='.CXPathHelper::escapeQuotes($selected).
							']/../../..'
					)->one();
					break;

				case 'itemhistory':
					$selected_element = $widget->query('xpath:.//td[text()='.
							CXPathHelper::escapeQuotes($selected.': Trapper item').']/..'
					)->one();
					break;

				case 'hostnavigator':
					$selected_element = $widget->query('xpath:.//span[text()='.CXPathHelper::escapeQuotes($selected).
							']/../../..'
					)->one();
					break;

				case 'itemnavigator':
					if ($override_key) {
						$itemid = CDBHelper::getValue('SELECT itemid FROM items WHERE key_ = '.zbx_dbstr($override_key).
								' AND hostid = '.zbx_dbstr(CDataHelper::get('WidgetCommunication.hostids')[$selected])
						);
					}
					else {
						$itemid = CDataHelper::get('WidgetCommunication.itemids')[$selected.':trap.widget.communication'];
					}

					$selected_element = $widget->query('xpath:.//div[@data-id='.$itemid.']')->one();
					break;

				case 'honeycomb':
					$selected_element = $widget->query('xpath:.//div[text()='.CXPathHelper::escapeQuotes($selected).
							']/../../../..'
					)->waitUntilPresent()->one();
					break;
			}

			$this->assertTrue($selected_element->hasClass(self::SELECTED_CLASSES[$widget_type]),
					'Expected element is not selected in '.$widget_type.' widget.'
			);
		}
		else {
			/**
			 * The only thing that differs between a selected host and a non selected host on a geomap widget is the
			 * base64 format hash of the icon that represent this host. So to check if host is selected, the icon hash
			 * is compared to an icon hash of a selected host.
			 * The selected host hash for the 3 hosts differs only by 8 symbols so the hash is composed of 3 parts to
			 * avoid three huge hashes in the test.
			 */
			$base64_hash = self::GEOMAP_HASH_START.self::GEOMAP_UNIQUE_HASH_PARTS[$selected].self::GEOMAP_HAS_END;

			$this->assertEquals($base64_hash, $this->getWidgetElement(self::GEOMAP_ICON_INDEXES[$selected], $widget)
					->getAttribute('src')
			);
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

	public static function getNoListenersBroadcasterData() {
		return [
			'Hostgroups page with no listeners' => [
				[
					'page' => 'Hostgroups page',
					'broadcasters' => [
						'Map hostgroup broadcaster',
						'Problem hosts hostgroup broadcaster',
						'Problems by severity hostgroup broadcaster',
						'Web monitoring hostgroup broadcaster'
					]
				]
			],
			'Hosts page with no listeners' => [
				[
					'page' => 'Hosts page',
					'broadcasters' => [
						'Geomap host broadcaster',
						'Honeycomb host broadcaster',
						'Map host broadcaster',
						'Top hosts host broadcaster',
						'Host navigator broadcaster'
					]
				]
			],
			'Items page with no listeners' => [
				[
					'page' => 'Items page',
					'broadcasters' => [
						'Honeycomb item broadcaster',
						'Item history item broadcaster',
						'Item navigator broadcaster'
					]
				]
			],
			'Maps page with no listeners' => [
				[
					'page' => 'Maps page',
					'broadcasters' => [
						'Navigation tree map broadcaster'
					]
				]
			]
		];
	}

	protected function removeAllBroadcasters() {
		$reference_values = implode('\', \'', array_merge(array_values(self::BROADCASTER_REFERENCES),
				array_values(self::SINGLE_ENTITY_BROADCASTER_REFERENCES)
		));

		DBexecute('DELETE FROM widget_field WHERE value_str IN (\''.$reference_values.'\')');
	}

	/**
	 * This test scenario is designed to check the requirement, that no element should be auto-selected on the broadcaster
	 * widget when no listener widgets are present on the page.
	 *
	 * @dataProvider getNoListenersBroadcasterData
	 *
	 * @backupOnce !widget_field
	 *
	 * @onBeforeOnce removeAllBroadcasters
	 */
	public function testDashboardWidgetCommunication_BroadcastersWithNoListeners($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$entityids['dashboardid'])
				->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$dashboard->selectPage($data['page']);
		$dashboard->waitUntilReady();

		foreach ($data['broadcasters'] as $broadcaster) {
			$broadcaster_widget = $dashboard->getWidget($broadcaster);
			$broadcaster_type = $this->getWidgetType($broadcaster_widget);
			if ($broadcaster_type !== 'geomap') {
				$this->assertFalse($broadcaster_widget->query('xpath:.//*[contains(@class, '.
						CXPathHelper::escapeQuotes(self::SELECTED_CLASSES[$broadcaster_type]).')]')->one(false)->isValid(),
						'An element is unexpectedly selected in "'.$broadcaster.'" widget, which has no listeners.'
				);
			}
			else {
				/**
				 * On geomap widget, if the host is selected can be determined based on the base64 hash of the corresponding
				 * icon. To make sure that no element is selected, the test makes sure that none of the 3 selected icon
				 * hashes are present in widget.
				 * The selected host hash for the 3 hosts differs only by 8 symbols so the hash is composed of 3 parts to
				 * avoid three huge hashes in the test.
				 */
				foreach ([self::FIRST_HOST_NAME, self::SECOND_HOST_NAME, self::THIRD_HOST_NAME] as $host_name) {
					$this->assertFalse($broadcaster_widget->query('xpath:.//*[@src='.
							CXPathHelper::escapeQuotes(self::GEOMAP_HASH_START.self::GEOMAP_UNIQUE_HASH_PARTS[$host_name].
							self::GEOMAP_HAS_END).']')->one(false)->isValid()
					);
				}
			}
		}
	}

	/**
	 * Check listener widget behavior when broadcasting widget is deleted.
	 */
	public function testDashboardWidgetCommunication_BroadcasterDeletion() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$entityids['dashboardid'])
				->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$dashboard->edit();

		$widget_fields = ['Hostgroups page' => 'Host groups', 'Hosts page' => 'Hosts', 'Items page' => 'Item',
			'Maps page' => 'Map', 'Multi-broadcasting page' => 'Host groups'
		];
		foreach ($widget_fields as $page => $field) {
			$dashboard->selectPage($page);
			$broadcaster = ($page === 'Multi-broadcasting page')
				? 'Map mixed broadcaster'
				: self::$current_broadcasters[$page];

			switch ($page) {
				case 'Items page':
					$listeners = ['Gauge listener', 'Graph (classic) listener', 'Item value listener', 'SVG graph listener',
						'Pie chart listener', 'Item value', 'Item card listener'
					];
					break;

				case 'Maps page':
					$listeners = ['Map listener'];
					break;

				case 'Multi-broadcasting page':
					$listeners = ['Both host and group from single broadcaster', 'Host and group from different broadcasters'];
					break;

				default:
					$listeners = array_keys(self::DEFAULT_WIDGET_CONTENT[$page]);
					break;
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
						'element' => self::THIRD_HOST_NAME
					],
					'expected' => [
						'Problems host listener' => [
							'Host' => self::THIRD_HOST_NAME,
							'Problem • Severity' => self::THIRD_HOST_TRIGGER
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
				$broadcaster = $dashboard->query('xpath:(.//div[contains(@class, "dashboard-grid-widget-header")]'.
						'/h4[text()='.CXPathHelper::escapeQuotes($data['copy']).'])[2]/../../..')->waitUntilPresent()->one();
			}
			else {
				$broadcaster = $dashboard->getWidget($data['select']['widget']);
			}

			$this->getWidgetElement($data['select']['element'], $broadcaster)->click();
			$dashboard->waitUntilReady();
			$this->checkDataOnListener($data['expected'], array_key_exists('new', $data['expected']) ? $data['copy'] : null);
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
						'Top item host listener from honeycomb' => [
							'Hosts' => self::SECOND_HOST_NAME,
							'Trapper item' => '4.00'
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
						'Top item host listener from honeycomb' => [
							'Hosts' => self::THIRD_HOST_NAME,
							'Trapper item' => '5.00'
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

	public static function getSavedValueDuringRebroadcastingData() {
		return [
			'Item with same key preserved on item navigator' => [
				[
					're-broadcaster' => [
						'title' => 'Item navigator selected item re-broadcaster'
					],
					'initial_selection' => self::FIRST_HOST_NAME
				]
			],
			'Item with same key preserved on honeycomb' => [
				[
					're-broadcaster' => [
						'title' => 'Honeycomb selected item re-broadcaster',
						'reference' => [
							'old' => 'TQXFD._itemid',
							'new' => 'EHWTR._itemid'
						]
					],
					'initial_selection' => '3 '
				]
			]
		];
	}

	/**
	 * Item navigator or honeycomb widgets remember the item that was selected on them in case if they are listening
	 * hosts from another widget and then receive a host that has the same key as the previously selected item.
	 * However, if they receive a host that doesn't have such key, the selection gets dropped to the first item in the list.
	 * Currently, this feature exists only on item navigator and honeycomb widgets.
	 *
	 * @dataProvider getSavedValueDuringRebroadcastingData
	 */
	public function testDashboardWidgetCommunication_CheckSelectedItemRemembering($data) {
		if (array_key_exists('reference', $data['re-broadcaster'])) {
			DBexecute('UPDATE widget_field SET value_str = '.zbx_dbstr($data['re-broadcaster']['reference']['new']).
					' WHERE value_str = '.zbx_dbstr($data['re-broadcaster']['reference']['old'])
			);
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$entityids['dashboardid'])
				->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();

		if ($dashboard->getSelectedPageName() !== 'Value re-broadcasting page') {
			$dashboard->selectPage('Value re-broadcasting page');
		}

		$broadcaster = $dashboard->getWidget('Host navigator broadcaster');
		$rebroadcaster = $dashboard->getWidget($data['re-broadcaster']['title']);

		// By default the "1st host for widgets" is selected on broadcaster so we proceed with selecting the trapper item.
		$this->getWidgetElement($data['initial_selection'], $rebroadcaster)->click();
		$dashboard->waitUntilReady();

		// Check the name of the listener widget and the value that is displayed on it.
		$listener = $dashboard->getWidget(self::FIRST_HOST_NAME.': Trapper item');
		$this->assertEquals('3', $listener->query('class:item-value-content')->one()->getText());

		/**
		 * Check that selecting a host on the broadcaster that has an item with the same key as currently selected on
		 * the re-broadcaster. This should result in preserving the selected item (only the host and value changes).
		 */
		$this->checkRebroadcastedItemSelection(self::THIRD_HOST_NAME, $broadcaster, $rebroadcaster, $listener);

		// Select host with a different set of items on the broadcaster.
		$this->checkRebroadcastedItemSelection(self::FORTH_HOST_NAME, $broadcaster, $rebroadcaster, $listener);

		// Check that item key that was previously preserved is no longer remembered.
		$this->checkRebroadcastedItemSelection(self::FIRST_HOST_NAME, $broadcaster, $rebroadcaster, $listener);
	}

	/**
	 * Check which item is selected on the re-broadcaster and which item is displayed on the listener after changing
	 * host on the broadcaster.
	 *
	 * @param string          $host           name of the host to be selected on the broadcaster
	 * @param CWidgetElement  $broadcaster    broadcaster widget element
	 * @param CWidgetElement  $rebroadcaster  re-broadcaster widget element
	 * @param CWidgetElement  $listener       listener widget element
	 */
	protected function checkRebroadcastedItemSelection($host, $broadcaster, $rebroadcaster, $listener) {
		$host_items = [
			self::FIRST_HOST_NAME => [
				'name' => 'Download speed for scenario "Web scenario for '.$host.'".',
				'key' => 'web.test.in[Web scenario for '.$host.',,bps]',
				'value' => "1000\nBps",
				'honeycomb_value' => '1000 Bps'
			],
			self::THIRD_HOST_NAME => [
				'name' => 'Trapper item',
				'key' => 'trap.widget.communication',
				'value' => '5',
				'honeycomb_value' => '5 '
			],
			self::FORTH_HOST_NAME => [
				'name' => 'Another item',
				'key' => 'another.item.widget.communication',
				'value' => '6',
				'honeycomb_value' => '6 '
			]
		];

		// Select a host on the broadcaster.
		$this->getWidgetElement($host, $broadcaster)->click();
		$dashboard = CDashboardElement::find()->one()->waitUntilReady();;

		// Check the item that is selected on the re-broadcaster.
		$rebroadcaster_selected = ($this->getWidgetType($rebroadcaster) === 'honeycomb')
			? $host_items[$host]['honeycomb_value']
			: $host;

		$this->checkSelectedElement($rebroadcaster, $rebroadcaster_selected, $host_items[$host]['key']);
		$dashboard->waitUntilReady();
		$listener->invalidate();

		// Check the name of the listener widget and the value that is displayed on it.
		$this->assertEquals($host.': '.$host_items[$host]['name'], $listener->getHeaderText());
		$this->assertEquals($host_items[$host]['value'], $listener->query('class:item-value-content')->one()->getText());
	}

	/**
	 * Close popup or dialog that is opened when clicking on element in broadcaster widget.
	 */
	protected function closeOpenedPopup() {
		$dialog = $this->query('xpath://div[contains(@class, "hintbox-static")]')->one(false);
		if ($dialog->isValid()) {
			$dialog->query('class:btn-overlay-close')->one()->click();
		}

		$popup = CPopupMenuElement::find()->one(false);
		if ($popup->isValid()) {
			$popup->close();
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

				if (in_array($listener_name, ['SVG graph listener', 'Pie chart listener']) && $field === 'Item') {
					$this->assertEquals('Unavailable widget', $widget_form->query('xpath:.//td[contains(@class,"table-col-name")]')
							->one()->getText()
					);
					$error_field = 'Data set/1';
				}
				else {
					$listeners_with_override = ['Item card listener', 'Gauge listener', 'Graph (classic) listener',
						'Item history listener', 'Item value listener', 'URL listener', 'SVG graph listener'
					];
					$unavailable_field = ($field === 'Hosts' && in_array($listener_name, $listeners_with_override))
						? 'Override host'
						: (($listener_name === 'Host card listener') ? 'Host' : $field);

					$this->assertEquals(['Unavailable widget'], $widget_form->getField($unavailable_field)->getValue());

					// The below widget listens to the same broadcaster both for hosts and hostgroups.
					if ($listener_name === 'Both host and group from single broadcaster') {
						$this->assertEquals(['Unavailable widget'], $widget_form->getField('Hosts')->getValue());
					}

					$error_field = ($listener_name === 'SVG graph listener')
						? 'Data set/1/Override host'
						: $unavailable_field;
				}

				$widget_form->submit();

				// The below widget listens to the same broadcaster both for hosts and hostgroups.
				if ($listener_name === 'Both host and group from single broadcaster') {
					$this->assertMessage(TEST_BAD, null, ['Invalid parameter "Host groups": referred widget is unavailable.',
						'Invalid parameter "Hosts": referred widget is unavailable.'
					]);
				}
				else {
					$this->assertMessage(TEST_BAD, null, 'Invalid parameter "'.$error_field.'": referred widget is unavailable.');
				}

				COverlayDialogElement::find()->one()->close();
			}
		}
	}

	/**
	 * Locate the required listener widget and verify displayed data based on listener widget type.
	 *
	 * @param array $expected				expected content on listener widget
	 * @param string $new_listener_name		widget name of new listener
	 */
	protected function checkDataOnListener($expected, $new_listener_name = null) {
		$dashboard = CDashboardElement::find()->one();

		foreach ($expected as $listener_name => $values) {
			if ($listener_name === 'new') {
				$listener = $dashboard->query('xpath:(.//div[contains(@class, "dashboard-grid-widget-header")]/h4[text()='.
					CXPathHelper::escapeQuotes($new_listener_name).'])[2]/../../..')->waitUntilPresent()->one();
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
				case 'itemhistory':
					if (!CTestArrayHelper::isMultidimensional($values)) {
						$values = [$values];
					}

					$table_selector = ($listener_name === 'new')
						? 'xpath:(//h4[text()='.CXPathHelper::escapeQuotes($new_listener_name).'])[2]/../..//table'
						: 'xpath://h4[text()='.CXPathHelper::escapeQuotes($listener_name).']/../..//table';

					$this->assertTableData($values, $table_selector);
					break;

				case 'geomap':
					foreach ($values as $icon_index => $popup_values) {
						$listener->query('xpath:.//img[contains(@class,"leaflet-marker-icon")]['.$icon_index.']')
							->one()->click();
						$this->assertTableData([$popup_values], 'xpath://div[contains(@class, "hintbox-static")]');
						$this->query('xpath://div[contains(@class, "hintbox-static")]')->query('class:btn-overlay-close')
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

				case 'itemcard':
					$this->assertEquals($values['Hostname'], $listener->query('class:section-path')->one()->getText());
					$this->assertEquals('Trapper item', $listener->query('class:item-name')->one()->getText());
					$this->assertEquals($values['Last value'], $listener->query('xpath://div[contains(@class, '.
							'"section-latest-data")]//div[contains(@class,"center-column")]//'.
							'div[contains(@class,"column-value")]/span')->one()->getText()
					);
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

				case 'url':
					if ($values === 'No data') {
						$this->assertEquals('No host selected.', $listener->query('tag:table')->one()->getText());
					}
					else {
						$this->page->switchTo($listener->query('id:iframe')->one());
						$this->assertEquals($values, $this->query('xpath:(//ul[@class="breadcrumbs"]//a)[2]')->one()->getText());
						$this->page->switchTo();
					}
					break;
			}
		}
	}
}

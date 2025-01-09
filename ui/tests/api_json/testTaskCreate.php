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


require_once __DIR__.'/../include/CAPITest.php';

/**
 * @onBefore prepareItemsData
 *
 * @onAfter clearData
 */
class testTaskCreate extends CAPITest {

	private static $data = [
		'templategroupid' => null,
		'hostgroupid' => null,
		'hostids' => [
			'monitored' => null,
			'not_monitored' => null,
			'template' => null
		],
		'itemids' => [
			'not_exists' => '01'
		]
	];

	private static $clear_taskids = [];

	public function prepareItemsData() {
		// Create template group.
		$templategroups = CDataHelper::call('templategroup.create', [
			[
				'name' => 'API test task.create'
			]
		]);
		$this->assertArrayHasKey('groupids', $templategroups);
		self::$data['templategroupid'] = $templategroups['groupids'][0];

		// Create host group.
		$hostgroups = CDataHelper::call('hostgroup.create', [
			[
				'name' => 'API test task.create'
			]
		]);
		$this->assertArrayHasKey('groupids', $hostgroups);
		self::$data['hostgroupid'] = $hostgroups['groupids'][0];

		// Create monitored host and not monitored host.
		$hosts_data = [
			[
				'host' => 'api_test_task_create_monitored',
				'name' => 'API test task.create monitored',
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			],
			[
				'host' => 'api_test_task_create_not_monitored',
				'name' => 'API test task.create not monitored',
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				],
				'status' => HOST_STATUS_NOT_MONITORED
			]
		];
		$hosts = CDataHelper::call('host.create', $hosts_data);
		$this->assertArrayHasKey('hostids', $hosts);
		self::$data['hostids'] = [
			'monitored' => $hosts['hostids'][0],
			'not_monitored' => $hosts['hostids'][1]
		];

		// Create host interfaces separately.
		$interfaces_data = [
			[
				'hostid' => self::$data['hostids']['monitored'],
				'type' => INTERFACE_TYPE_AGENT,
				'main' => INTERFACE_PRIMARY,
				'useip' => INTERFACE_USE_IP,
				'ip' => '192.168.3.1',
				'dns' => '',
				'port' => '10050'
			],
			[
				'hostid' => self::$data['hostids']['not_monitored'],
				'type' => INTERFACE_TYPE_AGENT,
				'main' => INTERFACE_PRIMARY,
				'useip' => INTERFACE_USE_IP,
				'ip' => '192.168.3.2',
				'dns' => '',
				'port' => '10060'
			]
		];
		$interfaces = CDataHelper::call('hostinterface.create', $interfaces_data);
		$this->assertArrayHasKey('interfaceids', $interfaces);
		$interfaceid_monitored = $interfaces['interfaceids'][0];
		$interfaceid_not_monitored = $interfaces['interfaceids'][1];

		// Create template.
		$templates_data = [[
			'host' => 'api_test_task_create_template',
			'name' => 'API test task.create template',
			'groups' => [
				[
					'groupid' => self::$data['templategroupid']
				]
			]
		]];
		$templates = CDataHelper::call('template.create', $templates_data);
		$this->assertArrayHasKey('templateids', $templates);
		self::$data['hostids']['template'] = $templates['templateids'][0];

		// Create top level master items.
		$items_data = [
			// Host is monitored, item is monitored and is of allowed type.
			[
				'hostid' => self::$data['hostids']['monitored'],
				'name' => '1 Item (1/1/1)',
				'key_' => '1_item_111',
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'delay' => '30',
				'interfaceid' => $interfaceid_monitored
			],
			[
				'hostid' => self::$data['hostids']['monitored'],
				'name' => '2 Item (1/1/1)',
				'key_' => '2_item_111',
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'delay' => '30',
				'interfaceid' => $interfaceid_monitored
			],
			// Host is monitored, item is not monitored, but is of allowed type.
			[
				'hostid' => self::$data['hostids']['monitored'],
				'name' => '3 Item (1/0/1)',
				'key_' => '3_item_101',
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'delay' => '30',
				'interfaceid' => $interfaceid_monitored,
				'status' => ITEM_STATUS_DISABLED
			],
			// Host is monitored, item is monitored, but type is not allowed.
			[
				'hostid' => self::$data['hostids']['monitored'],
				'name' => '4 Item (1/1/0)',
				'key_' => '4_item_110',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			// Host is not monitored, item is monitored and is of allowed type.
			[
				'hostid' => self::$data['hostids']['not_monitored'],
				'name' => '5 Item (0/1/1)',
				'key_' => '5_item_011',
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'delay' => '30',
				'interfaceid' => $interfaceid_not_monitored
			],
			// Host is template, item is monitored and is of allowed type.
			[
				'hostid' => self::$data['hostids']['template'],
				'name' => '6 Item-T (0/1/1)',
				'key_' => '6_item_t_011',
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'delay' => '30'
			]
		];
		$items = CDataHelper::call('item.create', $items_data);
		self::$data['itemids'] += [
			'1_item_111' => $items['itemids'][0],
			'2_item_111' => $items['itemids'][1],
			'3_item_101' => $items['itemids'][2],
			'4_item_110' => $items['itemids'][3],
			'5_item_011' => $items['itemids'][4],
			'6_item_t_011' => $items['itemids'][5]
		];

		// Create dependent items.
		$items_data = [
			// Host is monitored, item is monitored and is of allowed type.
			[
				'hostid' => self::$data['hostids']['monitored'],
				'name' => '1.1 Item (1/1/1)',
				'key_' => '1_1_item_111',
				'type' => ITEM_TYPE_DEPENDENT,
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'master_itemid' => self::$data['itemids']['1_item_111']
			],
			// Host is monitored, item is monitored and is of allowed type (same as previous).
			[
				'hostid' => self::$data['hostids']['monitored'],
				'name' => '1.2 Item (1/1/1)',
				'key_' => '1_2_item_111',
				'type' => ITEM_TYPE_DEPENDENT,
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'master_itemid' => self::$data['itemids']['1_item_111']
			],
			// Host is monitored, item is monitored and is of allowed type (but master item is not monitored).
			[
				'hostid' => self::$data['hostids']['monitored'],
				'name' => '3.1 Item (1/1/1)',
				'key_' => '3_1_item_111',
				'type' => ITEM_TYPE_DEPENDENT,
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'master_itemid' => self::$data['itemids']['3_item_101']
			],
			// Host is monitored, item is monitored and is of allowed type (but master item is not of allowed type).
			[
				'hostid' => self::$data['hostids']['monitored'],
				'name' => '4.1 Item (1/1/1)',
				'key_' => '4_1_item_111',
				'type' => ITEM_TYPE_DEPENDENT,
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'master_itemid' => self::$data['itemids']['4_item_110']
			]
		];
		$items = CDataHelper::call('item.create', $items_data);
		self::$data['itemids'] += [
			'1_1_item_111' => $items['itemids'][0],
			'1_2_item_111' => $items['itemids'][1],
			'3_1_item_111' => $items['itemids'][2],
			'4_1_item_111' => $items['itemids'][3]
		];

		// Level 3 item that depends on another dependent item.
		$items_data = [
			[
				'hostid' => self::$data['hostids']['monitored'],
				'name' => '1.2.1 Item (1/1/1)',
				'key_' => '1_2_1_item_111',
				'type' => ITEM_TYPE_DEPENDENT,
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'master_itemid' => self::$data['itemids']['1_2_item_111']
			]
		];
		$items = CDataHelper::call('item.create', $items_data);
		self::$data['itemids'] += [
			'1_2_1_item_111' => $items['itemids'][0]
		];

		// Create top level LLD rules.
		$discovery_rules_data = [
			// Host is monitored, LLD rule is monitored and is of allowed type.
			[
				'hostid' => self::$data['hostids']['monitored'],
				'name' => '1 LLD (1/1/1)',
				'key_' => '1_lld_111',
				'type' => ITEM_TYPE_ZABBIX,
				'delay' => '30',
				'interfaceid' => $interfaceid_monitored
			],
			// Host is monitored, LLD rule is not monitored, but is of allowed type.
			[
				'hostid' => self::$data['hostids']['monitored'],
				'name' => '2 LLD (1/0/1)',
				'key_' => '2_lld_101',
				'type' => ITEM_TYPE_ZABBIX,
				'delay' => '30',
				'interfaceid' => $interfaceid_monitored,
				'status' => ITEM_STATUS_DISABLED
			],
			// Host is monitored, LLD rule is monitored, but type is not allowed.
			[
				'hostid' => self::$data['hostids']['monitored'],
				'name' => '3 LLD (1/1/0)',
				'key_' => '3_lld_110',
				'type' => ITEM_TYPE_TRAPPER
			],
			// Host is not monitored, LLD rule is monitored and is of allowed type.
			[
				'hostid' => self::$data['hostids']['not_monitored'],
				'name' => '4 LLD (0/1/1)',
				'key_' => '4_lld_011',
				'type' => ITEM_TYPE_ZABBIX,
				'delay' => '30',
				'interfaceid' => $interfaceid_not_monitored
			],
			// Host is template, LLD rule is monitored and is of allowed type.
			[
				'hostid' => self::$data['hostids']['template'],
				'name' => '5 LLD-T (0/1/1)',
				'key_' => '5_lld_t_011',
				'type' => ITEM_TYPE_ZABBIX,
				'delay' => '30'
			]
		];
		$discovery_rules = CDataHelper::call('discoveryrule.create', $discovery_rules_data);
		self::$data['itemids'] += [
			'1_lld_111' => $discovery_rules['itemids'][0],
			'2_lld_101' => $discovery_rules['itemids'][1],
			'3_lld_110' => $discovery_rules['itemids'][2],
			'4_lld_011' => $discovery_rules['itemids'][3],
			'5_lld_t_011' => $discovery_rules['itemids'][4]
		];

		// Create dependent LLD rules (they depend on other items).
		$discovery_rules_data = [
			// Host is monitored, LLD rule is monitored and is of allowed type.
			[
				'hostid' => self::$data['hostids']['monitored'],
				'name' => '1.3 LLD (1/1/1)',
				'key_' => '1_3_lld_111',
				'type' => ITEM_TYPE_DEPENDENT,
				'master_itemid' => self::$data['itemids']['1_item_111']
			],
			// Host is monitored, LLD rule is monitored and is of allowed type (but master item is not monitored).
			[
				'hostid' => self::$data['hostids']['monitored'],
				'name' => '3.2 LLD (1/1/1)',
				'key_' => '3_2_lld_111',
				'type' => ITEM_TYPE_DEPENDENT,
				'master_itemid' => self::$data['itemids']['3_item_101']
			],
			// Host is monitored, LLD rule is monitored and is of allowed type (but master item is not of allowed type).
			[
				'hostid' => self::$data['hostids']['monitored'],
				'name' => '4.2 LLD (1/1/1)',
				'key_' => '4_2_lld_111',
				'type' => ITEM_TYPE_DEPENDENT,
				'master_itemid' => self::$data['itemids']['4_item_110']
			]
		];
		$discovery_rules = CDataHelper::call('discoveryrule.create', $discovery_rules_data);
		self::$data['itemids'] += [
			'1_3_lld_111' => $discovery_rules['itemids'][0],
			'3_2_lld_111' => $discovery_rules['itemids'][1],
			'4_2_lld_111' => $discovery_rules['itemids'][2]
		];
	}

	/**
	 * Data provider for valid items and LLD rules.
	 *
	 * @return array
	 */
	public static function getItemAndLLDDataValid() {
		return	[
			// One basic item and LLD rule.
			'Test one master item' => [
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => '1_item_111'
					]
				],
				'expected_results' => [
					['itemid' => '1_item_111']
				],
				'expected_error' => null
			],
			'Test one master LLD rule' => [
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => '1_lld_111'
					]
				],
				'expected_results' => [
					['itemid' => '1_lld_111']
				],
				'expected_error' => null
			],

			// Mix master items and LLD rules together.
			'Test LLD rule and item' => [
				'task' => [
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '1_lld_111'
						]
					],
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '1_item_111'
						]
					]
				],
				'expected_results' => [
					['itemid' => '1_lld_111'],
					['itemid' => '1_item_111']
				],
				'expected_error' => null
			],

			// Check dependent items and LLD rules.
			'Test one dependent item' => [
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => '1_1_item_111'
					]
				],
				'expected_results' => [
					['itemid' => '1_item_111']
				],
				'expected_error' => null
			],
			'Test two dependent items and dependent LLD rule' => [
				'task' => [
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '1_2_item_111'
						]
					],
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '1_1_item_111'
						]
					],
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '1_3_lld_111'
						]
					]
				],
				'expected_results' => [
					['itemid' => '1_item_111'],
					['itemid' => '1_item_111'],
					['itemid' => '1_item_111']
				],
				'expected_error' => null
			],
			'Test dependent item and master item together' => [
				'task' => [
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '1_1_item_111'
						]
					],
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '1_item_111'
						]
					]
				],
				'expected_results' => [
					['itemid' => '1_item_111'],
					['itemid' => '1_item_111']
				],
				'expected_error' => null
			],
			'Test dependent item lvl3 and dependent item lvl2 of same branch' => [
				'task' => [
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '1_2_item_111'
						]
					],
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '1_2_1_item_111'
						]
					]
				],
				'expected_results' => [
					['itemid' => '1_item_111'],
					['itemid' => '1_item_111']
				],
				'expected_error' => null
			],
			'Test dependent item lvl3 and master item of different branch' => [
				'task' => [
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '1_2_1_item_111'
						]
					],
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '2_item_111'
						]
					]
				],
				'expected_results' => [
					['itemid' => '1_item_111'],
					['itemid' => '2_item_111']
				],
				'expected_error' => null
			],

			// Check diagnostic info.
			'Test diagnostic info separately' => [
				'task' => [
					[
						'type' => ZBX_TM_DATA_TYPE_DIAGINFO,
						'request' => [
							'alerting' => [
								'stats' => [
									'alerts'
								]
							]
						]
					]
				],
				'expected_results' => [
					[
						'info' => json_encode([
							'alerting' => [
								'stats' => [
									'alerts'
								]
							]
						])
					]
				],
				'expected_error' => null
			],
			'Test check now and diagnostic info (repeating)' => [
				'task' => [
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '2_item_111'
						]
					],
					[
						'type' => ZBX_TM_DATA_TYPE_DIAGINFO,
						'request' => [
							'alerting' => [
								'stats' => [
									'alerts'
								]
							]
						]
					],
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '1_2_item_111'
						]
					],
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '2_item_111'
						]
					],
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '1_2_1_item_111'
						]
					]
				],
				'expected_results' => [
					['2_item_111'],
					[
						'info' => json_encode([
							'alerting' => [
								'stats' => [
									'alerts'
								]
							]
						])
					],
					['1_item_111'],
					['2_item_111'],
					['1_item_111']
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * Data provider for testing the created tasks twice.
	 *
	 * @return array
	 */
	public static function getItemExistingDataValid() {
		return [
			'Test check now and diagnostic info (first)' => [
				'task' => [
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '2_item_111'
						]
					],
					[
						'type' => ZBX_TM_DATA_TYPE_DIAGINFO,
						'request' => [
							'alerting' => [
								'stats' => [
									'alerts'
								]
							]
						]
					],
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '1_2_item_111'
						]
					],
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '2_item_111'
						]
					],
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '1_2_1_item_111'
						]
					]
				],
				'expected_results' => [
					['2_item_111'],
					[
						'info' => json_encode([
							'alerting' => [
								'stats' => [
									'alerts'
								]
							]
						])
					],
					['1_item_111'],
					['2_item_111'],
					['1_item_111']
				],
				'expected_error' => null
			],
			'Test check now and diagnostic info (second)' => [
				'task' => [
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '2_item_111'
						]
					],
					[
						'type' => ZBX_TM_DATA_TYPE_DIAGINFO,
						'request' => [
							'alerting' => [
								'stats' => [
									'alerts'
								]
							]
						]
					],
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '1_2_item_111'
						]
					],
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '2_item_111'
						]
					],
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '1_2_1_item_111'
						]
					]
				],
				'expected_results' => [
					['2_item_111'],
					[
						'info' => json_encode([
							'alerting' => [
								'stats' => [
									'alerts'
								]
							]
						])
					],
					['1_item_111'],
					['2_item_111'],
					['1_item_111']
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * Data provider for common errors like missing fields, invalid fields, empty fields etc.
	 *
	 * @return array
	 */
	public static function getItemAndLLDDataCommonInvalid() {
		// Valid and existing item ID.
		$itemid = '1_item_111';

		return [
			// Check required fields.
			'Test empty parameters' => [
				'tasks' => [],
				'expected_results' => [],
				'expected_error' => 'Invalid parameter "/": cannot be empty.'
			],
			'Test unexpected parameters' => [
				'tasks' => [
					'type' => '6',
					'request' => [
						'itemid' => $itemid
					],
					'flag' => true
				],
				'expected_results' => [],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "flag".'
			],
			'Test missing request' => [
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW
				],
				'expected_results' => [],
				'expected_error' => 'Invalid parameter "/1": the parameter "request" is missing.'
			],

			// Check "type" field.
			'Test missing type' => [
				'task' => [
					'request' => [
						'itemid' => $itemid
					]
				],
				'expected_results' => [],
				'expected_error' => 'Invalid parameter "/1": the parameter "type" is missing.'
			],
			'Test invalid type (empty)' => [
				'task' => [
					'type' => '',
					'request' => [
						'itemid' => $itemid
					]
				],
				'expected_results' => [],
				'expected_error' => 'Invalid parameter "/1/type": an integer is expected.'
			],
			'Test invalid type (string)' => [
				'task' => [
					'type' => 'æų',
					'request' => [
						'itemid' => $itemid
					]
				],
				'expected_results' => [],
				'expected_error' => 'Invalid parameter "/1/type": an integer is expected.'
			],
			'Test invalid type (value)' => [
				'task' => [
					'type' => '3',
					'request' => [
						'itemid' => $itemid
					]
				],
				'expected_results' => [],
				'expected_error' => 'Invalid parameter "/1/type": value must be one of '.(implode(', ', [
					ZBX_TM_DATA_TYPE_DIAGINFO, ZBX_TM_DATA_TYPE_PROXYIDS, ZBX_TM_TASK_CHECK_NOW
				])).'.'
			],

			// Check "itemid" field.
			'Test missing itemid' => [
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => []
				],
				'expected_results' => [],
				'expected_error' => 'Invalid parameter "/1/request": the parameter "itemid" is missing.'
			],
			'Test invalid itemid (empty)' => [
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => ''
					]
				],
				'expected_results' => [],
				'expected_error' => 'Invalid parameter "/1/request/itemid": a number is expected.'
			],
			'Test invalid itemid (array)' => [
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => ['']
					]
				],
				'expected_results' => [],
				'expected_error' => 'Invalid parameter "/1/request/itemid": a number is expected.'
			],

			// Check invalid diagnostic info.
			'Test invalid diagnostic info (invalid parent field)' => [
				'task' => [
					[
						'type' => ZBX_TM_DATA_TYPE_DIAGINFO,
						'request' => [
							'random_field' => [
								'stats' => [
									'alerts'
								],
								'top' => [
									'media.alerts' => 10
								]
							]
						]
					]
				],
				'expected_results' => [],
				'expected_error' => 'Invalid parameter "/1/request": unexpected parameter "random_field".'
			],
			'Test invalid diagnostic info (invalid child field)' => [
				'task' => [
					[
						'type' => ZBX_TM_DATA_TYPE_DIAGINFO,
						'request' => [
							'alerting' => [
								'random_field' => [
									'alerts'
								]
							]
						]
					]
				],
				'expected_results' => [],
				'expected_error' => 'Invalid parameter "/1/request/alerting": unexpected parameter "random_field".'
			],
			'Test invalid diagnostic info (invalid stats value)' => [
				'task' => [
					[
						'type' => ZBX_TM_DATA_TYPE_DIAGINFO,
						'request' => [
							'alerting' => [
								'stats' => [
									'some_value'
								]
							]
						]
					]
				],
				'expected_results' => [],
				'expected_error' => 'Invalid parameter "/1/request/alerting/stats/1": value must be "alerts".'
			],
			'Test invalid diagnostic info (invalid media.alerts value)' => [
				'task' => [
					[
						'type' => ZBX_TM_DATA_TYPE_DIAGINFO,
						'request' => [
							'alerting' => [
								'stats' => [
									'alerts'
								],
								'top' => [
									'media.alerts' => 'abc'
								]
							]
						]
					]
				],
				'expected_results' => [],
				'expected_error' => 'Invalid parameter "/1/request/alerting/top/media.alerts": an integer is expected.'
			],
			'Test invalid diagnostic info (proxy does not exist)' => [
				'task' => [
					[
						'type' => ZBX_TM_DATA_TYPE_DIAGINFO,
						'request' => [
							'alerting' => [
								'stats' => [
									'alerts'
								],
								'top' => [
									'media.alerts' => 10
								]
							],
							'lld' => [
								'stats' => 'extend',
								'top' => [
									'values' => 5
								]
							]
						],
						'proxyid' => '01'
					]
				],
				'expected_results' => [],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			]
		];
	}

	/**
	 * Data provider for invalid items and LLD rules.
	 *
	 * @return array
	 */
	public static function getItemAndLLDDataInvalid() {
		return [
			// Test non-existent items.
			'Test one invalid item' => [
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => 'not_exists'
					]
				],
				'expected_results' => [],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test one valid and one invalid item' => [
				'task' => [
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => 'not_exists'
						]
					],
					[
						'type' => ZBX_TM_TASK_CHECK_NOW,
						'request' => [
							'itemid' => '1_item_111'
						]
					]
				],
				'expected_results' => [],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],

			// Test master items and LLD rules.
			'Test item (not monitored)' => [
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => '3_item_101'
					]
				],
				'expected_results' => [],
				'expected_error' => 'Cannot send request: item "3 Item (1/0/1)" on host "API test task.create monitored" is not monitored.'
			],
			'Test item (type not allowed)' => [
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => '4_item_110'
					]
				],
				'expected_results' => [],
				'expected_error' => 'Cannot send request: wrong item type.'
			],
			'Test item (host not monitored)' => [
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => '5_item_011'
					]
				],
				'expected_results' => [],
				'expected_error' => 'Cannot send request: item "5 Item (0/1/1)" on host "API test task.create not monitored" is not monitored.'
			],
			'Test item (host is template)' => [
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => '6_item_t_011'
					]
				],
				'expected_results' => [],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test LLD rule (not monitored)' => [
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => '2_lld_101'
					]
				],
				'expected_results' => [],
				'expected_error' => 'Cannot send request: discovery rule "2 LLD (1/0/1)" on host "API test task.create monitored" is not monitored.'
			],
			'Test LLD rule (type not allowed)' => [
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => '3_lld_110'
					]
				],
				'expected_results' => [],
				'expected_error' => 'Cannot send request: wrong discovery rule type.'
			],
			'Test LLD rule (host not monitored)' => [
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => '4_lld_011'
					]
				],
				'expected_results' => [],
				'expected_error' => 'Cannot send request: discovery rule "4 LLD (0/1/1)" on host "API test task.create not monitored" is not monitored.'
			],
			'Test LLD rule (host is template)' => [
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => '5_lld_t_011'
					]
				],
				'expected_results' => [],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],

			// Test dependent items and LLD rules.
			'Test dependent item (master item is not monitored)' => [
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => '3_1_item_111'
					]
				],
				'expected_results' => [],
				'expected_error' => 'Cannot send request: item "3 Item (1/0/1)" on host "API test task.create monitored" is not monitored.'
			],
			'Test dependent item (master item type is allowed)' => [
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => '4_1_item_111'
					]
				],
				'expected_results' => [],
				'expected_error' => 'Cannot send request: wrong master item type.'
			],
			'Test dependent LLD rule (master item is not monitored)' => [
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => '3_2_lld_111'
					]
				],
				'expected_results' => [],
				'expected_error' => 'Cannot send request: item "3 Item (1/0/1)" on host "API test task.create monitored" is not monitored.'
			],
			'Test dependent LLD rule (master item type is allowed)' => [
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => '4_2_lld_111'
					]
				],
				'expected_results' => [],
				'expected_error' => 'Cannot send request: wrong master item type.'
			]
		];
	}

	/**
	 * Test valid items, valid LLD rules, invalid items, invalid LLD rules and common errors like missing fields etc.
	 *
	 * @dataProvider getItemAndLLDDataValid
	 * @dataProvider getItemAndLLDDataCommonInvalid
	 * @dataProvider getItemAndLLDDataInvalid
	 */
	public function testTaskCreate_new($tasks, $expected_results, $expected_error) {
		// Accept single and multiple tasks.
		if (!array_key_exists(0, $tasks)) {
			$tasks = zbx_toArray($tasks);
		}

		// Replace ID placeholders with real IDs for "check now" tasks.
		foreach ($tasks as &$task) {
			// Some tests that should fail may not have the required fields or they may be damaged.
			if (array_key_exists('type', $task) && $task['type'] == ZBX_TM_TASK_CHECK_NOW
					&& array_key_exists('request', $task) && array_key_exists('itemid', $task['request'])
					&& !is_array($task['request']['itemid']) && $task['request']['itemid'] !== '') {
				$task['request']['itemid'] = self::$data['itemids'][$task['request']['itemid']];
			}
		}
		unset($task);

		$sql_check_now_tasks = 'SELECT NULL FROM task_check_now';
		$old_hash_check_now_tasks = CDBHelper::getHash($sql_check_now_tasks);

		$sql_diag_info_task = "select NULL from task_data";
		$old_hash_diag_info_tasks = CDBHelper::getHash($sql_diag_info_task);

		$result = $this->call('task.create', $tasks, $expected_error);

		if ($expected_error === null) {
			$check_now = false;
			$diag_info = false;

			foreach ($expected_results as $expected_result) {
				if (array_key_exists('itemid', $expected_result)) {
					$check_now = true;
				}

				if (array_key_exists('info', $expected_result)) {
					$diag_info = true;
				}
			}

			// Check that changes were made in corresponding tables.
			if ($check_now) {
				$this->assertNotSame($old_hash_check_now_tasks, CDBHelper::getHash($sql_check_now_tasks));
			}

			if ($diag_info) {
				$this->assertNotSame($old_hash_diag_info_tasks, CDBHelper::getHash($sql_diag_info_task));
			}

			// Check the count of expected results. Input item count should match the output task ID count.
			$this->assertEquals(count($result['result']['taskids']), count($tasks));

			foreach ($expected_results as $index => $expected_result) {
				$taskid = $result['result']['taskids'][$index];

				if (array_key_exists('itemid', $expected_result)) {
					$itemid = self::$data['itemids'][$expected_result['itemid']];

					$task_db = CDBHelper::getValue(
						'SELECT tcn.itemid'.
						' FROM task_check_now tcn'.
						' WHERE '.dbConditionId('tcn.taskid', [$taskid])
					);
					$this->assertSame($itemid, $task_db);
				}

				if (array_key_exists('info', $expected_result)) {
					$task_db = CDBHelper::getValue(
						'SELECT td.data'.
						' FROM task_data td'.
						' WHERE '.dbConditionId('td.taskid', [$taskid])
					);
					$this->assertSame($expected_result['info'], $task_db);
				}
			}

			// Clear tasks because same items are used multiple times so that hash checking works correctly.
			DBexecute('DELETE FROM task WHERE '.dbConditionId('taskid', $result['result']['taskids']));
		}
		else {
			// Check if no changes were made to DB in any of the corresponding tables.
			$this->assertSame($old_hash_check_now_tasks, CDBHelper::getHash($sql_check_now_tasks));
			$this->assertSame($old_hash_diag_info_tasks, CDBHelper::getHash($sql_diag_info_task));
		}
	}

	/**
	 * Test valid items and create tasks for them. Then create same tasks again for same items and expect task IDs to be
	 * the same. No new records should be added in for "check now". However diagnostic info does not have that check in
	 * API and will create new record for diagnostic info tasks. Those task IDs are collected and then removed
	 * when data is cleared.
	 *
	 * @dataProvider getItemExistingDataValid
	 */
	public function testTaskCreate_existing($tasks, $expected_results, $expected_error) {
		// Accept single and multiple tasks.
		if (!array_key_exists(0, $tasks)) {
			$tasks = zbx_toArray($tasks);
		}

		// Replace ID placeholders with real IDs for "check now" tasks.
		foreach ($tasks as &$task) {
			if ($task['type'] == ZBX_TM_TASK_CHECK_NOW) {
				$task['request']['itemid'] = self::$data['itemids'][$task['request']['itemid']];
			}
		}
		unset($task);

		$sql_check_now_tasks = 'SELECT NULL FROM task_check_now';
		$old_hash_check_now_tasks = CDBHelper::getHash($sql_check_now_tasks);

		$sql_diag_info_task = "select NULL from task_data";
		$old_hash_diag_info_tasks = CDBHelper::getHash($sql_diag_info_task);

		$result = $this->call('task.create', $tasks, $expected_error);

		// Check the count of expected results. Input item count should match the output task ID count.
		$this->assertEquals(count($result['result']['taskids']), count($tasks));

		foreach ($expected_results as $index => $expected_result) {
			$taskid = $result['result']['taskids'][$index];

			if (array_key_exists('itemid', $expected_result)) {
				$itemid = self::$data['itemids'][$expected_result['itemid']];

				$task_db = CDBHelper::getValue(
					'SELECT tcn.itemid'.
					' FROM task_check_now tcn'.
					' WHERE '.dbConditionId('tcn.taskid', [$taskid])
				);
				$this->assertSame($itemid, $task_db);

				if ($index == 0) {
					// On first iteration task records are created.
					$this->assertNotSame($old_hash_check_now_tasks, CDBHelper::getHash($sql_check_now_tasks));
				}
				else {
					// On second iteration task records are the same, because same items were passed.
					$this->assertSame($old_hash_check_now_tasks, CDBHelper::getHash($sql_check_now_tasks));
				}
			}

			if (array_key_exists('info', $expected_result)) {
				$task_db = CDBHelper::getValue(
					'SELECT td.data'.
					' FROM task_data td'.
					' WHERE '.dbConditionId('td.taskid', [$taskid])
				);
				$this->assertSame($expected_result['info'], $task_db);

				// For diagnostic info tasks, each time new records are created.
				$this->assertNotSame($old_hash_diag_info_tasks, CDBHelper::getHash($sql_diag_info_task));

				// Collect diagnostic info task IDs because these are not automatically deleted.
				self::$clear_taskids[$taskid] = true;
			}
		}
	}

	/**
	 * Data provider for testing permissions. Method task.create externally can only be called by super admins.
	 *
	 * @return array
	 */
	public static function getDataPermissions() {
		// Valid and existing item ID (host monitored, item monitored and type allowed).
		$itemid = '1_item_111';

		return [
			// Test check now.
			'Test check now (admin)' => [
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => $itemid
					]
				],
				'expected_error' => 'No permissions to call "task.create".'
			],
			'Test check now (user)' => [
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'task' => [
					'type' => ZBX_TM_TASK_CHECK_NOW,
					'request' => [
						'itemid' => $itemid
					]
				],
				'expected_error' => 'No permissions to call "task.create".'
			],

			// Test diagnostic info.
			'Test diagnostic info (admin)' => [
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'task' => [
					'type' => ZBX_TM_DATA_TYPE_DIAGINFO,
					'request' => [
						'alerting' => [
							'stats' => [
								'alerts'
							],
							'top' => [
								'media.alerts' => 10
							]
						],
						'lld' => [
							'stats' => 'extend',
							'top' => [
								'values' => 5
							]
						]
					],
					'proxyid' => 0
				],
				'expected_error' => 'No permissions to call "task.create".'
			],
			'Test diagnostic info (user)' => [
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'task' => [
					'type' => ZBX_TM_DATA_TYPE_DIAGINFO,
					'request' => [
						'alerting' => [
							'stats' => [
								'alerts'
							],
							'top' => [
								'media.alerts' => 10
							]
						],
						'lld' => [
							'stats' => 'extend',
							'top' => [
								'values' => 5
							]
						]
					],
					'proxyid' => 0
				],
				'expected_error' => 'No permissions to call "task.create".'
			]
		];
	}

	/**
	 * Test user permissions.
	 *
	 * @dataProvider getDataPermissions
	 */
	public function testTaskCreate_UserPermissions($user, $task, $expected_error) {
		$sql_check_now_task = "select NULL from task_check_now";
		$old_hash_check_now_tasks = CDBHelper::getHash($sql_check_now_task);

		$sql_diag_info_task = "select NULL from task_data";
		$old_hash_diag_info_tasks = CDBHelper::getHash($sql_diag_info_task);

		$this->authorize($user['user'], $user['password']);
		$this->call('task.create', $task, $expected_error);

		// Check if no changes were made in DB.
		$this->assertSame($old_hash_check_now_tasks, CDBHelper::getHash($sql_check_now_task));
		$this->assertSame($old_hash_diag_info_tasks, CDBHelper::getHash($sql_diag_info_task));
	}

	/**
	 * Delete all created data after test.
	 */
	public static function clearData() {
		// Delete hosts and templates.
		CDataHelper::call('host.delete', [
			self::$data['hostids']['monitored'],
			self::$data['hostids']['not_monitored']
		]);
		CDataHelper::call('template.delete', [
			self::$data['hostids']['template']
		]);

		// Delete host group.
		CDataHelper::call('hostgroup.delete', [
			self::$data['hostgroupid']
		]);

		// Delete template group.
		CDataHelper::call('templategroup.delete', [
			self::$data['templategroupid']
		]);

		// Once items are deleted, tasks for those items are also deleted. However diangonstic info task remain.
		if (self::$clear_taskids) {
			DBexecute('DELETE FROM task WHERE '.dbConditionId('taskid', array_keys(self::$clear_taskids)));
		}
	}
}

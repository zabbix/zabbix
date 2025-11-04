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

require_once dirname(__FILE__) . '/../include/CIntegrationTest.php';


/**
 * Test suite for LLD.
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @backup hosts,items,item_rtdata,triggers,actions,operations,graphs
 *
 */
class testNestedLLD extends CIntegrationTest{

	const HOSTNAME_MAIN = "lld_test_host";
	const HOSTNAME_ITEMTYPES = "lld_itemtype_test_host";
	const HOSTNAME_NESTED_1 = "host_db_discovery";
	const HOSTNAME_NESTED_2 = "host_server_db_discovery";
	const LLDRULE_MAIN = 'lld.test.rule';
	const LLDRULE_ITEMTYPES = 'lld.test.itemtypes.rule';
	const AUTOREG_ACTION_NAME = 'lld.action.autoreg';
	const AGENT_AUTOREG_NAME = 'agent.autoreg';
	const HOST_METADATA = 'host_lld_autoreg';
	const HOSTNAME_TEST_MACRO = "host_test_macro_in_nested_json";
	const LLDRULE_KEY_ROOT = 'lld.test.macros.rule.root';

	private static $hostid_item_types;
	private static $lld_ruleid_main;
	private static $lld_ruleid_item_types;
	private static $templateid_main;
	private static $templateid_item_types;
	private static $item_protoid_item_types;
	private static $autoreg_actionid;
	private static $hostid_macros;
	private static $lld_ruleid_macros_root;
	private static $lld_item_protoid_macros_root;
	private static $lld_item_protoid_macros_1st_level;
	private static $lld_drule_protoid_macros_1st_level;

	private static $trapper_data_nested1 = [
		[
			"database" => "db1",
			"created_at" => "2024-02-01T12:30:00Z",
			"encoding" => "UTF8",
			"tablespaces" => [
				["name" => "ts1", "max_size" => "10GB"],
				["name" => "ts2", "max_size" => "20GB"],
				["name" => "ts3", "max_size" => "15GB"]
			]
		],
		[
			"database" => "db2",
			"created_at" => "2023-11-15T08:45:00Z",
			"encoding" => "UTF16",
			"tablespaces" => [
				["name" => "ts1", "max_size" => "5GB"],
				["name" => "ts2", "max_size" => "25GB"],
				["name" => "ts3", "max_size" => "30GB"]
			]
		],
		[
			"database" => "db3",
			"created_at" => "2024-01-05T15:10:00Z",
			"encoding" => "UTF8",
			"tablespaces" => [
				["name" => "ts1", "max_size" => "12GB"],
				["name" => "ts2", "max_size" => "18GB"],
				["name" => "ts3", "max_size" => "22GB"]
			]
		]
	];

	/**
	 * Component configuration provider for agent related tests.
	 *
	 * @return array
	 */
	public function agentConfigurationProvider() {
		return [
			self::COMPONENT_AGENT => [
				'Hostname' => self::AGENT_AUTOREG_NAME,
				'ServerActive' => '127.0.0.1:' . self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'HostMetadata' => self::HOST_METADATA
			]
		];
	}

	/**
	 *
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 0
			]
		];
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		$this->importData("lld_test_dbs_template");
		$this->importData("lld_test_autoreg_main_template");

		$response = $this->call('templategroup.get', [
			'filter' => [
				'name' => 'Templates'
			]
		]);
		$this->assertCount(1, $response['result']);
		$templategroupid = $response['result'][0]['groupid'];

		$response = $this->call('template.create', [
			'host' => 'lld_test_template',
			'groups' => [
				['groupid' => $templategroupid]
			]
		]);
		$this->assertArrayHasKey('templateids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['templateids']);
		self::$templateid_main = $response['result']['templateids'][0];

		$response = $this->call('template.create', [
			'host' => 'lld_test_template_item_types',
			'groups' => [
				['groupid' => $templategroupid]
			]
		]);
		$this->assertArrayHasKey('templateids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['templateids']);
		self::$templateid_item_types = $response['result']['templateids'][0];

		$response = $this->call('discoveryrule.create', [
			'name' => 'Custom LLD Rule',
			'key_' => self::LLDRULE_MAIN,
			'hostid' => self::$templateid_main,
			'type' => ITEM_TYPE_TRAPPER,
			'delay' => 0,
			'lifetime' => '7d',
			'lld_macro_paths' => [
				[
					'lld_macro' => '{#NAME}',
					'path' => '$.name'
				],
				[
					'lld_macro' => '{#TYPE}',
					'path' => '$.type'
				],
				[
					'lld_macro' => '{#STATUS}',
					'path' => '$.status'
				],
				[
					'lld_macro' => '{#FAILFILTER}',
					'path' => '$.failfilter'
				]
			],
			'filter' => [
				'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
				'formula' => 'B and A',
				'conditions' => [
					[
						'macro' => '{#TYPE}',
						'operator' => 8,
						'value' => 'service',
						'formulaid' => 'B'
					],
					[
						'macro' => '{#FAILFILTER}',
						'operator' => CONDITION_OPERATOR_NOT_EXISTS,
						'formulaid' => 'A'
					]
				]
			],
			'overrides' => [
				[
					'name' => 'Critical override: disable items, set trigger severity to high',
					'filter' => [
						'evaltype' => 0,
						'conditions' => [
							[
								'macro' => '{#STATUS}',
								'operator' => 8,
								'value' => 'critical'
							]
						]
					],
					'operations' => [
						[
							'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => 'test',
							'opstatus' => [
								'status' => ZBX_PROTOTYPE_STATUS_DISABLED
							],
							'opdiscover' => [
								'discover' => ZBX_PROTOTYPE_DISCOVER
							]
						],
						[
							'operationobject' => OPERATION_OBJECT_TRIGGER_PROTOTYPE,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => 'test',
							'opstatus' => [
								'status' => ZBX_PROTOTYPE_STATUS_ENABLED
							],
							'opdiscover' => [
								'discover' => ZBX_PROTOTYPE_DISCOVER
							],
							'opseverity' => [
								'severity' => TRIGGER_SEVERITY_HIGH
							]
						]
					],
					'step' => 1
				],
				[
					'name' => 'No discover override',
					'filter' => [
						'evaltype' => 0,
						'conditions' => [
							[
								'macro' => '{#STATUS}',
								'operator' => 8,
								'value' => 'nodiscover'
							]
						]
					],
					'operations' => [
						[
							'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => 'test',
							'opdiscover' => ['discover' => ZBX_PROTOTYPE_NO_DISCOVER]
						],
						[
							'operationobject' => OPERATION_OBJECT_TRIGGER_PROTOTYPE,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => 'test',
							'opdiscover' => ['discover' => ZBX_PROTOTYPE_NO_DISCOVER]
						]
					],
					'step' => 2
				]
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$lld_ruleid_main = $response['result']['itemids'][0];

		$response = $this->call('itemprototype.create', [
			'name' => 'Discovered Item {#NAME}',
			'key_' => 'custom.item[{#NAME}]',
			'hostid' => self::$templateid_main,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'delay' => 0,
			'ruleid' => self::$lld_ruleid_main
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);

		$response = $this->call('triggerprototype.create', [
			'description' => 'Trigger for {#NAME}',
			'expression' => "last(/lld_test_template/custom.item[{#NAME}])=1",
			'priority' => TRIGGER_SEVERITY_AVERAGE
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);

		$response = $this->call('hostprototype.create', [
			'host' => 'Discovered Host {#NAME}',
			'ruleid' => self::$lld_ruleid_main,
			'status' => HOST_STATUS_MONITORED,
			'groupLinks' => [
				['groupid' => 4]
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);

		$response = $this->call('hostprototype.create', [
			'host' => 'Grouped Host {#NAME}',
			'ruleid' => self::$lld_ruleid_main,
			'status' => 0,
			'groupLinks' => [
				['groupid' => 4]
			],
			'groupPrototypes' => [
				['name' => 'Group for host {#NAME}']
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);

		$response = $this->call('discoveryrule.create', [
			'name' => 'Custom LLD Rule for item type change test',
			'key_' => self::LLDRULE_ITEMTYPES,
			'hostid' => self::$templateid_item_types,
			'type' => ITEM_TYPE_TRAPPER,
			'delay' => 0,
			'lifetime' => '7d'
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$lld_ruleid_item_types = $response['result']['itemids'][0];

		$response = $this->call('itemprototype.create', [
			'name' => 'Itm {#NAME}',
			'key_' => 'itemtype.test[{#NAME}]',
			'hostid' => self::$templateid_item_types,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'delay' => 0,
			'ruleid' => self::$lld_ruleid_item_types
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		self::$item_protoid_item_types = $response['result']['itemids'][0];

		$response = $this->call('host.create', [
			'host' => self::HOSTNAME_MAIN,
			'interfaces' => [],
			'groups' => [
				['groupid' => 4]
			],
			'templates' => [
				'templateid' => self::$templateid_main
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);

		$response = $this->call('host.create', [
			'host' => self::HOSTNAME_ITEMTYPES,
			'interfaces' => [],
			'groups' => [
				['groupid' => 4]
			],
			'templates' => [
				'templateid' => self::$templateid_item_types
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid_item_types = $response['result']['hostids'][0];

		$response = $this->call('hostinterface.create', [
			[
				'hostid' => self::$hostid_item_types,
				'useip' => INTERFACE_USE_DNS,
				'ip' => '',
				'dns' => 'zabbix.local',
				'main' => 1,
				'port' => '10163',
				'type' => INTERFACE_TYPE_SNMP,
				'details' => [
					'version' => 3,
					'bulk' => 1,
					'max_repetitions' => 10,
					'securityname' => 'zabbix',
					'securitylevel' => 0,
					'authprotocol' => 0,
					'privprotocol' => 0,
					'contextname' => 'zabbix'
				]
			],
			[
				'hostid' => self::$hostid_item_types,
				'type' => INTERFACE_TYPE_IPMI,
				'useip' => INTERFACE_USE_DNS,
				'ip' => '',
				'dns' => 'zabbix.local',
				'port' => '1023',
				'main' => 1
			],
			[
				'hostid' => self::$hostid_item_types,
				'type' => INTERFACE_TYPE_JMX,
				'useip' => INTERFACE_USE_DNS,
				'dns' => 'zabbix.com',
				'port' => '1234',
				'ip' => '',
				'main' => 1
			],
			[
				'hostid' => self::$hostid_item_types,
				'ip' => '127.0.0.1',
				'dns' => '',
				'main' => 1,
				'port' => '20000',
				'type' => INTERFACE_TYPE_AGENT,
				'useip' => INTERFACE_USE_IP
			]
		]);

		/* for nested macro testing ZBXNEXT-10068 */
		$response = $this->call('host.create', [
			'host' => self::HOSTNAME_TEST_MACRO,
			'interfaces' => [],
			'groups' => [
				['groupid' => 4] // 'Zabbix servers'
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid_macros = $response['result']['hostids'][0];

		$response = $this->call('discoveryrule.create', [
			'name' => 'Root level discovery rule',
			'key_' => self::LLDRULE_KEY_ROOT,
			'hostid' => self::$hostid_macros,
			'type' => ITEM_TYPE_TRAPPER,
			'lld_macro_paths' => [
				[
					'lld_macro' => '{#L0}',
					'path' => '$.db_name'
				]
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$lld_ruleid_macros_root = $response['result']['itemids'][0];

		$response = $this->call('itemprototype.create', [
			'name' => 'Root level item prototype',
			'key_' => 'trap0[{#L0}]',
			'hostid' => self::$hostid_macros,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'ruleid' => self::$lld_ruleid_macros_root
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		self::$lld_item_protoid_macros_root = $response['result']['itemids'][0];


		$response = $this->call('discoveryruleprototype.create', [
			'name' => '1-st level discovery rule',
			'key_' => 'lld_trap[{#L0}]',
			'hostid' => self::$hostid_macros,
			'type' => ITEM_TYPE_NESTED,
			'ruleid' => self::$lld_ruleid_macros_root,
			'lld_macro_paths' => [
				[
					'lld_macro' => '{#L1}',
					'path' => '$.{#L0}'
				]
			],
			'preprocessing' => [
				[
					'type' => ZBX_PREPROC_JSONPATH,
					'params' => '$.ts_data',
					'error_handler' => 0
				]
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$lld_drule_protoid_macros_1st_level = $response['result']['itemids'][0];

		$response = $this->call('itemprototype.create', [
			'name' => '1-st level item prototype',
			'key_' => 'trap1[{#L0},{#L1}]',
			'hostid' => self::$hostid_macros,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'ruleid' => self::$lld_drule_protoid_macros_1st_level
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		self::$lld_item_protoid_macros_1st_level = $response['result']['itemids'][0];

		return true;
	}

	// For comprehensive discovery regression test (DiscoveryWithFiltersOverrides)
	private function checkDiscoveredHostgroups($expected_suffix) {
		// Each expected group is present and contains one discovered host
		$response = $this->callUntilDataIsPresent('hostgroup.get', [
			'searchWildcardsEnabled' => true,
			'search' => [
				'name' => 'Group for host Service*'
			],
			'selectHosts' => ['host'],
			'sortorder' => 'ASC',
			'sortfield' => 'name',
			'output' => ['name']
		], 60, 1);
		$this->assertCount(count($expected_suffix), $response['result'],
			'expected group(s) were not discovered');

		for ($i = 0; $i < count($expected_suffix); $i++) {
			$hstgrp = $response['result'][$i];
			$groupname = "Group for host Service" . $expected_suffix[$i];
			$this->assertEquals($groupname, $hstgrp['name']);

			$hostname = "Grouped Host Service" . $expected_suffix[$i];
			$this->assertArrayHasKey('hosts', $hstgrp);

			$msg = 'discovered group does not contain expected host ' . $hostname;
			$this->assertCount(1, $hstgrp['hosts'], $msg);

			$this->assertEquals($hostname, $hstgrp['hosts'][0]['host']);
		}
	}

	// For comprehensive discovery regression test (DiscoveryWithFiltersOverrides)
	private function checkDiscoveredHosts($expected_suffix) {
		// Discovered hosts without group prototypes are present
		$response = $this->callUntilDataIsPresent('host.get', [
			'searchWildcardsEnabled' => true,
			'search' => [
				'name' => 'Discovered Host Service*'
			],
			'sortorder' => 'ASC',
			'sortfield' => 'host',
			'output' => ['host']
		], 60, 1);
		$this->assertCount(count($expected_suffix), $response['result'],
			'expected host(s) were not discovered');

		for ($i = 0; $i < count($expected_suffix); $i++) {
			$hostname = 'Discovered Host Service' . $expected_suffix[$i];
			$host = $response['result'][$i];
			$this->assertEquals($hostname, $host['host']);
		}

		// Filtered hosts
		$response = $this->call('host.get', [
			'searchByAny' => true,
			'search' => [
				'name' => ['DiskC', 'UnknownD']
			],
			'sortorder' => 'ASC',
			'sortfield' => 'host',
			'output' => ['host']
		]);
		$this->assertEmpty($response['result'], 'host(s) that should have been filtered were discovered');
	}

	// For comprehensive discovery regression test (DiscoveryWithFiltersOverrides)
	private function checkDiscoveredItemsAndTriggers($expected_items) {
		$response = $this->callUntilDataIsPresent('item.get', [
			'searchWildcardsEnabled' => true,
			'search' => [
				'name' => 'Discovered*'
			],
			'sortorder' => 'ASC',
			'sortfield' => 'name',
			'output' => ['name'],
			'selectTriggers' => ['description', 'priority']
		], 60, 1);
		$this->assertCount(count($expected_items), $response['result'],
			'expected item(s) were not discovered');

		for ($i = 0; $i < count($expected_items); $i++) {
			$item = $response['result'][$i];
			$this->assertEquals($expected_items[$i]['item_name'], $item['name']);

			$msg = 'trigger was not discovered for item ' . $expected_items[$i]['item_name'];
			$this->assertCount(1, $item['triggers'], $msg);

			$this->assertEquals($expected_items[$i]['trigger_description'], $item['triggers'][0]['description']);
			$this->assertEquals($expected_items[$i]['priority'], $item['triggers'][0]['priority']);
		}
	}

	// Test hostgroup, host, and item discovery with filters and overrides.
	public function testNestedLLD_DiscoveryWithFiltersOverrides() {
		$trapper_data = [
			["name" => "ServiceA", "type" => "service", "status" => "ok"],
			["name" => "ServiceB", "type" => "service", "status" => "critical"],
			["name" => "DiskC", "type" => "disk", "status" => "ok"],
			["name" => "UnknownD", "type" => "unknown", "status" => "warning"],
			["name" => "ServiceE", "type" => "service", "status" => "critical"],
			["name" => "ServiceF", "type" => "service", "status" => "ignored"],
			["name" => "ServiceG", "type" => "service", "status" => "nodiscover"],
			["name" => "ServiceH", "type" => "service", "status" => "nodiscover", "failfilter" => "yes"]
		];

		$this->sendSenderValue(self::HOSTNAME_MAIN, self::LLDRULE_MAIN, $trapper_data);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);

		$expected_suffix = ['A', 'B', 'E', 'F', 'G'];

		$this->checkDiscoveredHostgroups($expected_suffix);
		$this->checkDiscoveredHosts($expected_suffix);

		$expected_items = [
			[
				'item_name' => 'Discovered Item ServiceA',
				'trigger_description' => 'Trigger for ServiceA',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'item_name' => 'Discovered Item ServiceB',
				'trigger_description' => 'Trigger for ServiceB',
				'priority' => TRIGGER_SEVERITY_HIGH // Override
			],
			[
				'item_name' => 'Discovered Item ServiceE',
				'trigger_description' => 'Trigger for ServiceE',
				'priority' => TRIGGER_SEVERITY_HIGH // Override
			],
			[
				'item_name' => 'Discovered Item ServiceF',
				'trigger_description' => 'Trigger for ServiceF',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			]
		];

		$this->checkDiscoveredItemsAndTriggers($expected_items);
	}

	// Update filter formula, rediscovery.
	public function testNestedLLD_UpdateFormula() {
		$response = $this->call('discoveryrule.update', [
			'itemid' => self::$lld_ruleid_main,
			'filter' => [
				'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
				'formula' => 'B or A',
				'conditions' => [
					[
						'macro' => '{#TYPE}',
						'operator' => 8,
						'value' => 'service',
						'formulaid' => 'B'
					],
					[
						'macro' => '{#FAILFILTER}',
						'operator' => CONDITION_OPERATOR_EXISTS,
						'formulaid' => 'A'
					]
				]
			]
		]);
		$this->assertCount(1, $response['result']);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$trapper_data = [
			["name" => "ServiceA", "type" => "service", "status" => "ok"],
			["name" => "ServiceB", "type" => "service", "status" => "critical"],
			["name" => "DiskC", "type" => "disk", "status" => "ok"],
			["name" => "UnknownD", "type" => "unknown", "status" => "warning"],
			["name" => "ServiceE", "type" => "service", "status" => "critical"],
			["name" => "ServiceF", "type" => "service", "status" => "ignored"],
			["name" => "ServiceG", "type" => "service", "status" => "nodiscover"],
			["name" => "ServiceH", "type" => "service", "status" => "nodiscover", "failfilter" => "yes"]
		];

		$this->sendSenderValue(self::HOSTNAME_MAIN, self::LLDRULE_MAIN, $trapper_data);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);

		$expected_suffix = ['A', 'B', 'E', 'F', 'G', 'H'];

		$this->checkDiscoveredHostgroups($expected_suffix);
		$this->checkDiscoveredHosts($expected_suffix);

		$expected_items = [
			[
				'item_name' => 'Discovered Item ServiceA',
				'trigger_description' => 'Trigger for ServiceA',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'item_name' => 'Discovered Item ServiceB',
				'trigger_description' => 'Trigger for ServiceB',
				'priority' => TRIGGER_SEVERITY_HIGH // Override
			],
			[
				'item_name' => 'Discovered Item ServiceE',
				'trigger_description' => 'Trigger for ServiceE',
				'priority' => TRIGGER_SEVERITY_HIGH // Override
			],
			[
				'item_name' => 'Discovered Item ServiceF',
				'trigger_description' => 'Trigger for ServiceF',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			]
		];

		$this->checkDiscoveredItemsAndTriggers($expected_items);
	}

	// Send initial trapper data, make sure that nothing was rediscovered.
	public function testNestedLLD_NoUpdatesWithoutChanges() {
		$start_ts = time();

		$this->clearLog(self::COMPONENT_SERVER);

		$trapper_data = [
			["name" => "ServiceA", "type" => "service", "status" => "ok"],
			["name" => "ServiceB", "type" => "service", "status" => "critical"],
			["name" => "DiskC", "type" => "disk", "status" => "ok"],
			["name" => "UnknownD", "type" => "unknown", "status" => "warning"],
			["name" => "ServiceE", "type" => "service", "status" => "critical"],
			["name" => "ServiceF", "type" => "service", "status" => "ignored"],
			["name" => "ServiceG", "type" => "service", "status" => "nodiscover"],
			["name" => "ServiceH", "type" => "service", "status" => "nodiscover", "failfilter" => "yes"]
		];

		$this->sendSenderValue(self::HOSTNAME_MAIN, self::LLDRULE_MAIN, $trapper_data);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);

		$response = $this->call('auditlog.get', [
			'time_from' => $start_ts
		]);

		foreach ($response['result'] as $audit_entry) {
			$this->assertArrayHasKey('resourcename', $audit_entry);

			$this->assertStringNotContainsString('Service', $audit_entry['resourcename'],
				'object(s) should not have been rediscovered/updated/deleted');
		}

		return true;
	}

	// Creation of new element.
	public function testNestedLLD_NewElement() {
		$start_ts = time();

		$this->clearLog(self::COMPONENT_SERVER);

		$trapper_data = [
			["name" => "ServiceA", "type" => "service", "status" => "ok"],
			["name" => "ServiceB", "type" => "service", "status" => "critical"],
			["name" => "DiskC", "type" => "disk", "status" => "ok"],
			["name" => "UnknownD", "type" => "unknown", "status" => "warning"],
			["name" => "ServiceE", "type" => "service", "status" => "critical"],
			["name" => "ServiceF", "type" => "service", "status" => "ignored"],
			["name" => "ServiceG", "type" => "service", "status" => "nodiscover"],
			["name" => "ServiceH", "type" => "service", "status" => "nodiscover", "failfilter" => "yes"],
			["name" => "ServiceX", "type" => "service", "status" => "warning"]
		];

		$this->sendSenderValue(self::HOSTNAME_MAIN, self::LLDRULE_MAIN, $trapper_data);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);

		$response = $this->callUntilDataIsPresent('auditlog.get', [
			'time_from' => $start_ts
		], 30, 1);

		$audit_entries = [
			'Discovered Host ServiceX' => false,
			'Grouped Host ServiceX' => false,
			'Trigger for ServiceX' => false,
			'Discovered Item ServiceX' => false
		];

		foreach ($response['result'] as $audit_entry) {
			$this->assertArrayHasKey('resourcename', $audit_entry);
			$resname = $audit_entry['resourcename'];

			if (strstr($resname, 'ServiceX'))
				$audit_entries[$resname] = true;
		}

		$this->assertContainsOnly('bool', $audit_entries, true, 'new element was not discovered');

		return true;
	}

	// Rediscovery triggering override.
	public function testNestedLLD_RediscoveryTriggeringOverride() {
		$start_ts = time();

		$this->clearLog(self::COMPONENT_SERVER);

		$trapper_data = [
			["name" => "ServiceA", "type" => "service", "status" => "ok"],
			["name" => "ServiceB", "type" => "service", "status" => "critical"],
			["name" => "DiskC", "type" => "disk", "status" => "ok"],
			["name" => "UnknownD", "type" => "unknown", "status" => "warning"],
			["name" => "ServiceE", "type" => "service", "status" => "critical"],
			["name" => "ServiceF", "type" => "service", "status" => "ignored"],
			["name" => "ServiceG", "type" => "service", "status" => "nodiscover"],
			["name" => "ServiceH", "type" => "service", "status" => "nodiscover", "failfilter" => "yes"],
			["name" => "ServiceX", "type" => "service", "status" => "critical"]
		];

		$this->sendSenderValue(self::HOSTNAME_MAIN, self::LLDRULE_MAIN, $trapper_data);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);

		$response = $this->callUntilDataIsPresent('auditlog.get', [
			'time_from' => $start_ts
		], 60, 1);

		$audit_entry_found = false;
		foreach ($response['result'] as $audit_entry) {
			$this->assertArrayHasKey('resourcename', $audit_entry);

			if (strstr($audit_entry['resourcename'], 'Trigger for ServiceX')) {
				$this->assertStringContainsString('update', $audit_entry['details'],
					'incorrect operation, expected update');

				$audit_entry_found = true;
				break;
			}
		}

		$this->assertTrue($audit_entry_found, 'rediscovery did not succeed for ServiceX trigger');

		$response = $this->call('item.get', [
			'search' => [
				'name' => 'Discovered Item ServiceX'
			],
			'sortorder' => 'ASC',
			'sortfield' => 'name',
			'output' => ['name'],
			'selectTriggers' => ['description', 'priority']
		]);
		$this->assertCount(1, $response['result']);
		$this->assertCount(1, $response['result'][0]['triggers']);
		$this->assertEquals($response['result'][0]['triggers'][0]['priority'], TRIGGER_SEVERITY_HIGH);
	}

	// Update of override
	public function testNestedLLD_UpdateOverride() {
		$this->clearLog(self::COMPONENT_SERVER);

		$trapper_data = [
			["name" => "ServiceA", "type" => "service", "status" => "ok"],
			["name" => "ServiceB", "type" => "service", "status" => "critical"],
			["name" => "DiskC", "type" => "disk", "status" => "ok"],
			["name" => "UnknownD", "type" => "unknown", "status" => "warning"],
			["name" => "ServiceE", "type" => "service", "status" => "critical"],
			["name" => "ServiceF", "type" => "service", "status" => "ignored"],
			["name" => "ServiceG", "type" => "service", "status" => "nodiscover"],
			["name" => "ServiceH", "type" => "service", "status" => "nodiscover", "failfilter" => "yes"],
			["name" => "ServiceX", "type" => "service", "status" => "warning"]
		];

		$this->sendSenderValue(self::HOSTNAME_MAIN, self::LLDRULE_MAIN, $trapper_data);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);


		$response = $this->call('discoveryrule.update', [
			'itemid' => self::$lld_ruleid_main,
			'overrides' => [
				[
					'name' => 'Critical override: disable items, set trigger severity to high',
					'filter' => [
						'evaltype' => 0,
						'conditions' => [
							[
								'macro' => '{#STATUS}',
								'operator' => 8,
								'value' => 'critical'
							]
						]
					],
					'operations' => [
						[
							'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => 'test',
							'opstatus' => [
								'status' => ZBX_PROTOTYPE_STATUS_DISABLED
							],
							'opdiscover' => [
								'discover' => ZBX_PROTOTYPE_DISCOVER
							]
						],
						[
							'operationobject' => OPERATION_OBJECT_TRIGGER_PROTOTYPE,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => 'test',
							'opstatus' => [
								'status' => ZBX_PROTOTYPE_STATUS_ENABLED
							],
							'opdiscover' => [
								'discover' => ZBX_PROTOTYPE_DISCOVER
							],
							'opseverity' => [
								'severity' => TRIGGER_SEVERITY_DISASTER // Update
							]
						]
					],
					'step' => 1
				],
				[
					'name' => 'No discover override',
					'filter' => [
						'evaltype' => 0,
						'conditions' => [
							[
								'macro' => '{#STATUS}',
								'operator' => 8,
								'value' => 'nodiscover'
							]
						]
					],
					'operations' => [
						[
							'operationobject' => OPERATION_OBJECT_ITEM_PROTOTYPE,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => 'test',
							'opdiscover' => ['discover' => ZBX_PROTOTYPE_NO_DISCOVER]
						],
						[
							'operationobject' => OPERATION_OBJECT_TRIGGER_PROTOTYPE,
							'operator' => CONDITION_OPERATOR_NOT_EQUAL,
							'value' => 'test',
							'opdiscover' => ['discover' => ZBX_PROTOTYPE_NO_DISCOVER]
						]
					],
					'step' => 2
				]
			]
		]);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->clearLog(self::COMPONENT_SERVER);

		$trapper_data = [
			["name" => "ServiceA", "type" => "service", "status" => "ok"],
			["name" => "ServiceB", "type" => "service", "status" => "critical"],
			["name" => "DiskC", "type" => "disk", "status" => "ok"],
			["name" => "UnknownD", "type" => "unknown", "status" => "warning"],
			["name" => "ServiceE", "type" => "service", "status" => "critical"],
			["name" => "ServiceF", "type" => "service", "status" => "ignored"],
			["name" => "ServiceG", "type" => "service", "status" => "nodiscover"],
			["name" => "ServiceH", "type" => "service", "status" => "nodiscover", "failfilter" => "yes"],
			["name" => "ServiceX", "type" => "service", "status" => "critical"]
		];

		$this->sendSenderValue(self::HOSTNAME_MAIN, self::LLDRULE_MAIN, $trapper_data);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);

		$expected_items = [
			[
				'item_name' => 'Discovered Item ServiceA',
				'trigger_description' => 'Trigger for ServiceA',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'item_name' => 'Discovered Item ServiceB',
				'trigger_description' => 'Trigger for ServiceB',
				'priority' => TRIGGER_SEVERITY_DISASTER // Override
			],
			[
				'item_name' => 'Discovered Item ServiceE',
				'trigger_description' => 'Trigger for ServiceE',
				'priority' => TRIGGER_SEVERITY_DISASTER // Override
			],
			[
				'item_name' => 'Discovered Item ServiceF',
				'trigger_description' => 'Trigger for ServiceF',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'item_name' => 'Discovered Item ServiceX',
				'trigger_description' => 'Trigger for ServiceX',
				'priority' => TRIGGER_SEVERITY_DISASTER // Override
			]
		];

		$this->checkDiscoveredItemsAndTriggers($expected_items);
	}

	// Cycle through item prototype types.
	public function testNestedLLD_ItemPrototypeTypeUpdate() {
		$trapper_data = [
			['{#NAME}' => 'itemtypetest']
		];

		$this->sendSenderValue(self::HOSTNAME_ITEMTYPES, self::LLDRULE_ITEMTYPES, $trapper_data);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);

		$response = $this->call('item.get', [
			'search' => [
				'name' => 'Itm itemtypetest'
			]
		]);
		$this->assertCount(1, $response['result'], 'item was not discovered');
		$itemid = $response['result'][0]['itemid'];

		$param_update = [
			[
				'type' => ITEM_TYPE_ZABBIX,
				'delay' => 3
			],
			[
				'type' => ITEM_TYPE_ZABBIX_ACTIVE,
				'delay' => 3
			],
			[
				'type' => ITEM_TYPE_SIMPLE,
				'delay' => 3
			],
			[
				'type' => ITEM_TYPE_TRAPPER,
				'delay' => 0
			],
			[
				'type' => ITEM_TYPE_SNMPTRAP,
				'delay' => 0
			],
			[
				'type' => ITEM_TYPE_INTERNAL,
				'delay' => 3
			],
			[
				'type' => ITEM_TYPE_EXTERNAL,
				'delay' => 3
			],
			[
				'type' => ITEM_TYPE_SNMP,
				'delay' => 3,
				'snmp_oid' => '1.3.6.1.1'
			],
			[
				'type' => ITEM_TYPE_DB_MONITOR,
				'delay' => 3,
				'params' => 'select null from test;',
				'username' => 'username',
				'password' => 'password'
			],
			[
				'type' => ITEM_TYPE_SSH,
				'delay' => 3,
				'params' => 'select null from test;',
				'username' => 'username',
				'password' => 'password'
			],
			[
				'type' => ITEM_TYPE_TELNET,
				'delay' => 3,
				'params' => 'select null from test;',
				'username' => 'username',
				'password' => 'password'
			],
			[
				'type' => ITEM_TYPE_CALCULATED,
				'delay' => 3,
				'params' => '1'
			],
			[
				'type' => ITEM_TYPE_SCRIPT,
				'delay' => 3,
				'params' => 'return 1;'
			],
			[
				'type' => ITEM_TYPE_BROWSER,
				'delay' => 3,
				'params' => 'return 1;'
			],
			[
				'type' => ITEM_TYPE_HTTPAGENT,
				'delay' => 3,
				'url' => '127.0.0.10/test.php'
			],
			[
				'type' => ITEM_TYPE_IPMI,
				'delay' => 3,
				'ipmi_sensor' => 'Power'
			],
			[
				'type' => ITEM_TYPE_JMX,
				'delay' => 3,
				'jmx_endpoint' => 'service:jmx:rmi:///jndi/rmi://127.0.0.10:12345/jmxrmi'
			]
		];

		foreach ($param_update as $param) {
			$request = [
				'itemid' => self::$item_protoid_item_types
			];

			$response = $this->call('itemprototype.update', array_merge($request, $param));
			$this->sendSenderValue(self::HOSTNAME_ITEMTYPES, self::LLDRULE_ITEMTYPES, $trapper_data);
			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);

			$response = $this->call('item.get', [
				'itemids' => [$itemid],
				'output' => ['type']
			]);
			$this->assertCount(1, $response['result']);
			$this->assertEquals($param['type'], $response['result'][0]['type']);
		}

		return true;
	}

	// Item prototype key update.
	public function testNestedLLD_itemProtoKeyUpdate() {
		$trapper_data = [
			['{#NAME}' => 'itemtypetest']
		];

		$this->sendSenderValue(self::HOSTNAME_ITEMTYPES, self::LLDRULE_ITEMTYPES, $trapper_data);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);

		$response = $this->call('item.get', [
			'search' => [
				'name' => 'Itm itemtypetest'
			]
		]);
		$this->assertCount(1, $response['result'], 'item was not discovered');
		$itemid = $response['result'][0]['itemid'];

		$response = $this->call('itemprototype.update', [
			'itemid' => self::$item_protoid_item_types,
			'key_' => 'itemtype.test.renamed[{#NAME}]'
		]);

		$this->sendSenderValue(self::HOSTNAME_ITEMTYPES, self::LLDRULE_ITEMTYPES, $trapper_data);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);

		$response = $this->call('item.get', [
			'itemids' => $itemid
		]);
		$this->assertCount(1, $response['result']);
		$this->assertEquals('itemtype.test.renamed[itemtypetest]', $response['result'][0]['key_']);

		return true;
	}

	// Item param update by LLD macro change.
	public function testNestedLLD_itemKeyUpdateFromMacro() {
		$trapper_data = [
			['{#NAME}' => 'itemtypetest']
		];

		$this->sendSenderValue(self::HOSTNAME_ITEMTYPES, self::LLDRULE_ITEMTYPES, $trapper_data);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);

		$response = $this->call('item.get', [
			'search' => [
				'name' => 'Itm itemtypetest'
			]
		]);
		$this->assertCount(1, $response['result'], 'item was not discovered');
		$itemid = $response['result'][0]['itemid'];

		$trapper_data = [
			['{#NAME}' => 'renamedparam']
		];

		$this->sendSenderValue(self::HOSTNAME_ITEMTYPES, self::LLDRULE_ITEMTYPES, $trapper_data);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);

		$response = $this->call('item.get', [
			'itemids' => $itemid
		]);
		$this->assertCount(1, $response['result']);
		$this->assertEquals(ITEM_STATUS_DISABLED, $response['result'][0]['status']);
		$this->assertStringContainsString('itemtypetest', $response['result'][0]['key_']);

		$response = $this->call('item.get', [
			'search' => [
				'name' => 'Itm renamedparam'
			]
		]);
		$this->assertCount(1, $response['result'], 'key was not updated from macro');
		$this->assertEquals(ITEM_STATUS_ACTIVE, $response['result'][0]['status']);
		$this->assertStringContainsString('renamedparam', $response['result'][0]['key_']);

		return true;
	}

	// Field update test.
	private function rediscoverWithMacroInFields($delay, $value, $units, $hktm) {
		$trapper_data = [
			[
				'{#NAME}' => 'fld',
				'{#DELAY}' => $delay,
				'{#VALUE}' => $value,
				'{#UNITS}' => $units,
				'{#HKTM}' => $hktm
			]
		];

		$this->sendSenderValue(self::HOSTNAME_ITEMTYPES, self::LLDRULE_ITEMTYPES, $trapper_data);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);

		$expected_items = [
			'fieldtest.agent.fld' => [
				'name' => 'fieldtest.agent.fld',
				'key_' => 'fieldtest.agent[fld]',
				'timeout' => $delay,
				'history' => $hktm,
				'trends' => $hktm
			],
			'fieldtest.agentactive.fld' => [
				'name' => 'fieldtest.agentactive.fld',
				'key_' => 'fieldtest.agentactive[fld]',
				'timeout' => $delay,
				'history' => $hktm,
				'trends' => $hktm
			],
			'fieldtest.simple.fld' => [
				'name' => 'fieldtest.simple.fld',
				'key_' => 'fieldtest.simple[fld]',
				'timeout' => $delay,
				'history' => $hktm,
				'trends' => $hktm
			],
			'fieldtest.snmptrap.fld' => [
				'name' => 'fieldtest.snmptrap.fld',
				'key_' => 'fieldtest.snmptrap[fld]',
				'history' => $hktm,
				'trends' => $hktm
			],
			'fieldtest.internal.fld' => [
				'name' => 'fieldtest.internal.fld',
				'key_' => 'fieldtest.internal[fld]',
				'type' => ITEM_TYPE_INTERNAL,
				'history' => $hktm,
				'trends' => $hktm
			],
			'fieldtest.external.fld' => [
				'name' => 'fieldtest.external.fld',
				'key_' => 'fieldtest.external[fld]',
				'timeout' => $delay,
				'history' => $hktm,
				'trends' => $hktm
			],
			'fieldtest.snmp.fld' => [
				'name' => 'fieldtest.snmp.fld',
				'key_' => 'fieldtest.snmp[fld]',
				'snmp_oid' => $value,
				'history' => $hktm,
				'trends' => $hktm
			],
			'fieldtest.db.fld' => [
				'name' => 'fieldtest.db.fld',
				'key_' => 'fieldtest.db[fld]',
				'params' => $value,
				'username' => $value,
				'password' => $value,
				'timeout' => $delay,
				'history' => $hktm,
				'trends' => $hktm
			],
			'fieldtest.ssh.fld' => [
				'name' => 'fieldtest.ssh.fld',
				'key_' => 'fieldtest.ssh[fld]',
				'params' => $value,
				'username' => $value,
				'password' => $value,
				'timeout' => $delay,
				'history' => $hktm,
				'trends' => $hktm
			],
			'fieldtest.telnet.fld' => [
				'name' => 'fieldtest.telnet.fld',
				'key_' => 'fieldtest.telnet[fld]',
				'params' => $value,
				'username' => $value,
				'password' => $value,
				'timeout' => $delay,
				'history' => $hktm,
				'trends' => $hktm
			],
			'fieldtest.calc.fld' => [
				'name' => 'fieldtest.calc.fld',
				'key_' => 'fieldtest.calc[fld]',
				'params' => $value,
				'history' => $hktm,
				'trends' => $hktm
			],
			'fieldtest.script.fld' => [
				'name' => 'fieldtest.script.fld',
				'key_' => 'fieldtest.script[fld]',
				'params' => $value,
				'timeout' => $delay,
				'parameters' => [
					[
						'name' => $value,
						'value' => $value
					]
				],
				'history' => $hktm,
				'trends' => $hktm
			],
			'fieldtest.browser.fld' => [
				'name' => 'fieldtest.browser.fld',
				'key_' => 'fieldtest.browser[fld]',
				'params' => $value,
				'timeout' => $delay,
				'parameters' => [
					[
						'name' => $value,
						'value' => $value
					]
				],
				'history' => $hktm,
				'trends' => $hktm
			],
			'fieldtest.http.fld' => [
				'name' => 'fieldtest.http.fld',
				'key_' => 'fieldtest.http[fld]',
				'url' => $value,
				'timeout' => $delay,
				'headers' => [
					[
						'name' => $value,
						'value' => $value
					]
				],
				'query_fields' => [
					[
						'name' => $value,
						'value' => $value
					]
				],
				'posts' => $value,
				'ssl_cert_file' => $value,
				'ssl_key_file' => $value,
				'ssl_key_password' => $value,
				'http_proxy' => $value,
				'history' => $hktm,
				'trends' => $hktm
			],
			'fieldtest.ipmi.fld' => [
				'name' => 'fieldtest.ipmi.fld',
				'key_' => 'fieldtest.ipmi[fld]',
				'ipmi_sensor' => $value,
				'history' => $hktm,
				'trends' => $hktm
			],
			'fieldtest.jmx.fld' => [
				'name' => 'fieldtest.jmx.fld',
				'key_' => 'fieldtest.jmx[fld]',
				'jmx_endpoint' => 'http://' . $value,
				'username' => $value,
				'password' => $value,
				'history' => $hktm,
				'trends' => $hktm
			]
		];

		$response = $this->callUntilDataIsPresent('item.get', [
			'searchWildcardsEnabled' => true,
			'search' => [
				'name' => 'fieldtest.*'
			],
			'output' => 'extend'
		], 60, 1);
		$this->assertCount(count($expected_items), $response['result'], 'item(s) were not discovered');

		foreach ($response['result'] as $discovered_item) {
			$item_name = $discovered_item['name'];
			$this->assertArrayHasKey($item_name, $expected_items);

			$i1 = array_intersect_key($discovered_item, $expected_items[$item_name]);
			$i2 = array_intersect_key($expected_items[$item_name], $discovered_item);

			$this->assertEquals($i1, $i2, $item_name . ': non equal fields');
		}
	}

	// Test LLD macro updates application to fields.
	public function testNestedLLD_testLLDMacroFieldChanges() {
		$item_prototypes = [
			[
				'name' => 'fieldtest.agent.{#NAME}',
				'key_' => 'fieldtest.agent[{#NAME}]',
				'type' => ITEM_TYPE_ZABBIX,
				'timeout' => '{#DELAY}'
			],
			[
				'name' => 'fieldtest.agentactive.{#NAME}',
				'key_' => 'fieldtest.agentactive[{#NAME}]',
				'type' => ITEM_TYPE_ZABBIX_ACTIVE,
				'timeout' => '{#DELAY}'
			],
			[
				'name' => 'fieldtest.simple.{#NAME}',
				'key_' => 'fieldtest.simple[{#NAME}]',
				'type' => ITEM_TYPE_SIMPLE,
				'timeout' => '{#DELAY}'
			],
			[
				'name' => 'fieldtest.snmptrap.{#NAME}',
				'key_' => 'fieldtest.snmptrap[{#NAME}]',
				'type' => ITEM_TYPE_SNMPTRAP
			],
			[
				'name' => 'fieldtest.internal.{#NAME}',
				'key_' => 'fieldtest.internal[{#NAME}]',
				'type' => ITEM_TYPE_INTERNAL
			],
			[
				'name' => 'fieldtest.external.{#NAME}',
				'key_' => 'fieldtest.external[{#NAME}]',
				'type' => ITEM_TYPE_EXTERNAL,
				'timeout' => '{#DELAY}'
			],
			[
				'name' => 'fieldtest.snmp.{#NAME}',
				'key_' => 'fieldtest.snmp[{#NAME}]',
				'type' => ITEM_TYPE_SNMP,
				'snmp_oid' => '{#VALUE}'
			],
			[
				'name' => 'fieldtest.db.{#NAME}',
				'key_' => 'fieldtest.db[{#NAME}]',
				'type' => ITEM_TYPE_DB_MONITOR,
				'params' => '{#VALUE}',
				'username' => '{#VALUE}',
				'password' => '{#VALUE}',
				'timeout' => '{#DELAY}'
			],
			[
				'name' => 'fieldtest.ssh.{#NAME}',
				'key_' => 'fieldtest.ssh[{#NAME}]',
				'type' => ITEM_TYPE_SSH,
				'params' => '{#VALUE}',
				'username' => '{#VALUE}',
				'password' => '{#VALUE}',
				'timeout' => '{#DELAY}'
			],
			[
				'name' => 'fieldtest.telnet.{#NAME}',
				'key_' => 'fieldtest.telnet[{#NAME}]',
				'type' => ITEM_TYPE_TELNET,
				'params' => '{#VALUE}',
				'username' => '{#VALUE}',
				'password' => '{#VALUE}',
				'timeout' => '{#DELAY}'
			],
			[
				'name' => 'fieldtest.calc.{#NAME}',
				'key_' => 'fieldtest.calc[{#NAME}]',
				'type' => ITEM_TYPE_CALCULATED,
				'params' => '{#VALUE}'
			],
			[
				'name' => 'fieldtest.script.{#NAME}',
				'key_' => 'fieldtest.script[{#NAME}]',
				'type' => ITEM_TYPE_SCRIPT,
				'params' => '{#VALUE}',
				'timeout' => '{#DELAY}',
				'parameters' => [
					[
						'name' => '{#VALUE}',
						'value' => '{#VALUE}'
					]
				]
			],
			[
				'name' => 'fieldtest.browser.{#NAME}',
				'key_' => 'fieldtest.browser[{#NAME}]',
				'type' => ITEM_TYPE_BROWSER,
				'params' => '{#VALUE}',
				'timeout' => '{#DELAY}',
				'parameters' => [
					[
						'name' => '{#VALUE}',
						'value' => '{#VALUE}'
					]
				]
			],
			[
				'name' => 'fieldtest.http.{#NAME}',
				'key_' => 'fieldtest.http[{#NAME}]',
				'type' => ITEM_TYPE_HTTPAGENT,
				'url' => '{#VALUE}',
				'timeout' => '{#DELAY}',
				'headers' => [
					[
						'name' => '{#VALUE}',
						'value' => '{#VALUE}'
					]
				],
				'query_fields' => [
					[
						'name' => '{#VALUE}',
						'value' => '{#VALUE}'
					]
				],
				'posts' => '{#VALUE}',
				'ssl_cert_file' => '{#VALUE}',
				'ssl_key_file' => '{#VALUE}',
				'ssl_key_password' => '{#VALUE}',
				'http_proxy' => '{#VALUE}'
			],
			[
				'name' => 'fieldtest.ipmi.{#NAME}',
				'key_' => 'fieldtest.ipmi[{#NAME}]',
				'type' => ITEM_TYPE_IPMI,
				'ipmi_sensor' => '{#VALUE}'
			],
			[
				'name' => 'fieldtest.jmx.{#NAME}',
				'key_' => 'fieldtest.jmx[{#NAME}]',
				'type' => ITEM_TYPE_JMX,
				'jmx_endpoint' => 'http://{#VALUE}',
				'username' => '{#VALUE}',
				'password' => '{#VALUE}'
			]
		];

		foreach ($item_prototypes as $proto) {
			if ($proto['type'] != ITEM_TYPE_SNMPTRAP) {
				$proto['delay'] = '{#DELAY}';
			}

			$proto['value_type'] = ITEM_VALUE_TYPE_UINT64;
			$proto['ruleid'] = self::$lld_ruleid_item_types;
			$proto['hostid'] = self::$templateid_item_types;
			$proto['units'] = '{#UNITS}';
			$proto['history'] = '{#HKTM}';
			$proto['trends'] = '{#HKTM}';
			$proto['tags'] = [
				[
					'tag' => 'tag1',
					'value' => '{#TAGVALUE}'
				]
			];

			$response = $this->call('itemprototype.create', $proto);
			$this->assertArrayHasKey('itemids', $response['result']);
		}

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$this->rediscoverWithMacroInFields('10', '12345678', 'kb', '111d');
		$this->rediscoverWithMacroInFields('20', '87654321', 'mb', '90d');

		return true;
	}

	// Check if everything was correctly discovered for 'DB discovery' template
	private function checkNestedLLDFromTemplate($hostname) {
		$this->sendSenderValue($hostname, 'main_drule', self::$trapper_data_nested1);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$response = $this->call('host.get', [
			'output' => ['hostid'],
			'filter' => [
				'host' => $hostname
			]
		]);
		$this->assertArrayHasKey('result', $response);

		$this->assertArrayHasKey('hostid', $response['result'][0],
			'failed to find host/template ' . $hostname);

		$hostid = $response['result'][0]['hostid'];

		$expected_rules = [
			[
				"name" => "Discover tablespaces for db1",
				"key_" => "db.tablespace.discovery[db1]",
				"preprocessing" => [
					[
						"type" => "12",
						"params" => "$.tablespaces"
					]
				],
				"items" => [
					[
						"name" => "Size of tablespace {#TSNAME} for db1",
						"key_" => "db.ts.size[db1,{#TSNAME}]"
					]
				]
			],
			[
				"name" => "Discover tablespaces for db2",
				"key_" => "db.tablespace.discovery[db2]",
				"preprocessing" => [
					[
						"type" => "12",
						"params" => "$.tablespaces"
					]
				],
				"items" => [
					[
						"name" => "Size of tablespace {#TSNAME} for db2",
						"key_" => "db.ts.size[db2,{#TSNAME}]"
					]
				]
			],
			[
				"name" => "Discover tablespaces for db3",
				"key_" => "db.tablespace.discovery[db3]",
				"preprocessing" => [
					[
						"type" => "12",
						"params" => "$.tablespaces"
					]
				],
				"items" => [
					[
						"name" => "Size of tablespace {#TSNAME} for db3",
						"key_" => "db.ts.size[db3,{#TSNAME}]"
					]
				]
			]
		];

		$response = $this->callUntilDataIsPresent('discoveryrule.get', [
			'hostids' => [$hostid],
			"selectItems" => ["name", "key_"],
			"selectPreprocessing" => ["type", "params"],
			"filter" => [
				"key_" => [
					"db.tablespace.discovery[db1]",
					"db.tablespace.discovery[db2]",
					"db.tablespace.discovery[db3]"
				]
			],
			"output" => ["name", "key_", "items", "preprocessing"],
			"sortfield" => "key_",
			"sortorder" => "ASC"
		], 60, 1);

		$this->assertCount(count($expected_rules), $response['result'],
			'expected nested lld rule(s) were not discovered');

		for ($i = 0; $i < count($expected_rules); $i++) {
			$discovered_rule = $response['result'][$i];
			$expected_rule = $expected_rules[$i];

			$this->assertArrayHasKey($i, $response['result'],
				'expected lld rule was not discovered: ' . json_encode($expected_rule));

			$this->assertEquals($expected_rules[$i]['key_'], $discovered_rule['key_']);
			$this->assertEquals($expected_rules[$i]['name'], $discovered_rule['name']);

			$this->assertArrayHasKey('items', $discovered_rule);
			$this->assertArrayHasKey(0, $discovered_rule['items'],
				'expected lld rule does not have required prototype: ' . json_encode($expected_rule));

			unset($discovered_rule['items'][0]['itemid']);

			$this->assertEquals($expected_rule['items'], $discovered_rule['items'],
				'item prototypes does not match for rule ' . $discovered_rule['name']);

			$this->assertEquals($expected_rule['preprocessing'], $discovered_rule['preprocessing'],
				'preproc does not match for rule ' . $discovered_rule['name']);
		}

		$this->sendSenderValue($hostname, 'main_drule', self::$trapper_data_nested1);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$expected = [
			[
				'name' => 'Active connections to db1',
				'key_' => 'db.connections[db1]'
			],
			[
				'name' => 'Active connections to db2',
				'key_' => 'db.connections[db2]'
			],
			[
				'name' => 'Active connections to db3',
				'key_' => 'db.connections[db3]'
			],
			[
				'name' => 'Size of tablespace ts1 for db1',
				'key_' => 'db.ts.size[db1,ts1]'
			],
			[
				'name' => 'Size of tablespace ts2 for db1',
				'key_' => 'db.ts.size[db1,ts2]'
			],
			[
				'name' => 'Size of tablespace ts3 for db1',
				'key_' => 'db.ts.size[db1,ts3]'
			],
			[
				'name' => 'Size of tablespace ts1 for db2',
				'key_' => 'db.ts.size[db2,ts1]'
			],
			[
				'name' => 'Size of tablespace ts2 for db2',
				'key_' => 'db.ts.size[db2,ts2]'
			],
			[
				'name' => 'Size of tablespace ts3 for db2',
				'key_' => 'db.ts.size[db2,ts3]'
			],
			[
				'name' => 'Size of tablespace ts1 for db3',
				'key_' => 'db.ts.size[db3,ts1]'
			],
			[
				'name' => 'Size of tablespace ts2 for db3',
				'key_' => 'db.ts.size[db3,ts2]'
			],
			[
				'name' => 'Size of tablespace ts3 for db3',
				'key_' => 'db.ts.size[db3,ts3]'
			]
		];

		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => [$hostid],
			'sortfield' => 'key_',
			'sortorder' => 'ASC',
			'output' => [
				'name',
				'key_'
			]
		], 60, 1);
		$this->assertCount(count($expected), $response['result'], 'expected item(s) were not discovered');

		for ($i = 0; $i < count($expected); $i++) {
			$this->assertArrayHasKey($i, $response['result'],
				'expected item was not discovered: ' . json_encode($expected[$i]));

			$ditem = $response['result'][$i];
			$this->assertEquals($expected[$i]['key_'], $ditem['key_']);
			$this->assertEquals($expected[$i]['name'], $ditem['name']);
		}
	}

	/*
	 * @backup hosts,items,item_rtdata,triggers
	 */
	public function testNestedLLD_testNestedDRulesFromHost() {
		$this->importData("lld_test_host_dbs");
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$this->checkNestedLLDFromTemplate(self::HOSTNAME_NESTED_1);
	}

	private function checkDbServerDiscovery() {
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$dbs = ['db1', 'db2', 'db3'];

		$response = $this->callUntilDataIsPresent('host.get', [
			"selectItems" => ["name", "key_"],
			'searchWildcardsEnabled' => true,
			'search' => [
				'name' => 'Host for database db*'
			],
			'sortorder' => 'ASC',
			'sortfield' => 'host'
		], 60, 1);
		$this->assertCount(count($dbs), $response['result'], 'expected host(s) was not discovered');


		for ($i = 0; $i < count($dbs); $i++) {
			$db = $dbs[$i];
			$host = $response['result'][$i];

			// Sort items on host by 'key_'
			usort($host['items'], static fn(array $a, array $b): int => strnatcasecmp($a['key_'], $b['key_']));

			$expected_hostname = 'Host for database ' . $db;
			$this->assertEquals($expected_hostname, $host['host']);

			$expected_items = [
				[
					'name' => 'Active connections to {$DB}',
					'key_' => 'db.connections[{$DB}]'
				],
				[
					'name' => 'Size of tablespace ts1 for ' . $db,
					'key_' => 'db.ts.size[' . $db . ',ts1]'
				],
				[
					'name' => 'Size of tablespace ts2 for ' . $db,
					'key_' => 'db.ts.size[' . $db . ',ts2]'
				],
				[
					'name' => 'Size of tablespace ts3 for ' . $db,
					'key_' => 'db.ts.size[' . $db . ',ts3]'
				]
			];

			$this->assertEqualsCanonicalizing($expected_items, $host['items']);
		}
	}

	private function sendRuleData($hostname, $rule, $data) {
		$this->sendSenderValue($hostname, $rule, $data);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);
		$this->sendSenderValue($hostname, $rule, $data);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);
	}

	/*
	 * @backup hosts,items,item_rtdata,triggers
	 */
	public function testNestedLLD_testNestedDRulesFromHostWithTmplLinkage() {
		$this->importData("lld_test_server_dbs");
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$this->sendRuleData(self::HOSTNAME_NESTED_2, 'main_drule', self::$trapper_data_nested1);

		$this->checkDbServerDiscovery();
	}

	/**
	 * @required-components server, agent
	 * @configurationDataProvider agentConfigurationProvider
	 * @onAfter clearAutoregAction
	 */
	public function testNestedLLD_testNestedAutoreg() {
		$response = $this->call('template.get', [
			'output' => ['templateid'],
			'filter' => [
				'name' => 'lld_test_autoreg_main_template'
			]
		]);
		$this->assertArrayHasKey(0, $response['result'], 'failed to load template');
		$this->assertArrayHasKey('templateid', $response['result'][0]);
		$templateid_main = $response['result'][0]['templateid'];

		$response = $this->call('action.create', [
			[
				'name' => self::AUTOREG_ACTION_NAME,
				'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
				'status' => ACTION_STATUS_ENABLED,
				'filter' => [
					'conditions' => [
						[
							'conditiontype' => ZBX_CONDITION_TYPE_HOST_NAME,
							'operator' => CONDITION_OPERATOR_LIKE,
							'value' => self::AGENT_AUTOREG_NAME
						],
						[
							'conditiontype' => ZBX_CONDITION_TYPE_HOST_METADATA,
							'operator' => CONDITION_OPERATOR_LIKE,
							'value' => self::HOST_METADATA
						]
					],
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR
				],
				'operations' => [
					[
						'operationtype' => OPERATION_TYPE_TEMPLATE_ADD,
						'optemplate' => [
							[
								'templateid' => $templateid_main
							]
						]
					]
				]
			]
		]);
		$this->assertCount(1, $response['result']);
		$this->assertCount(1, $response['result']['actionids']);
		self::$autoreg_actionid = $response['result']['actionids'][0];

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => ['hostid'],
			'search' => [
				'host' => self::AGENT_AUTOREG_NAME
			],
			'sortorder' => 'ASC',
			'sortfield' => 'host'
		], 240, 2);
		$this->assertCount(1, $response['result'], 'host failed to autoregist');

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$this->sendRuleData(self::AGENT_AUTOREG_NAME, 'main_drule', self::$trapper_data_nested1);

		$this->checkDbServerDiscovery();
	}

	public static function clearAutoregAction(): void {
		CDataHelper::call('action.delete', [
			self::$autoreg_actionid
		]);
	}

	private function checkResourceRemoval($hostname, $hostid, $testcases) {
		foreach ($testcases as $tc) {
			$this->sendRuleData($hostname, 'main_drule', $tc['data']);

			$response = $this->callUntilDataIsPresent('host.get', [
				'hostids' => [$hostid],
				'selectItems' => ['name', 'key_'],
				'selectTriggers' => ['description'],
				'selectGraphs' => ['name'],
				'selectDiscoveryRules' => ['name']
			]);
			$this->assertCount(1, $response['result'], 'host ' . $hostname . 'was not discovered');

			$host = $response['result'][0];
			$this->assertCount($tc['expected']['items'], $host['items'],
				'expected discovered item count does not match');

			$this->assertCount($tc['expected']['triggers'], $host['triggers'],
				'expected discovered trigger count does not match');

			$this->assertCount($tc['expected']['graphs'], $host['graphs'],
				'expected discovered graph count does not match');

			$this->assertCount($tc['expected']['discoveryRules'], $host['discoveryRules'],
				'expected discovered lld rule count does not match');

			$response = $this->call('host.get', [
				'searchWildcardsEnabled' => true,
				'search' => [
					'name' => 'tc.removal.host*'
				],
				'countOutput' => true
			]);
			$this->assertEquals($tc['expected']['hosts'], $response['result'],
				'expected discovered host count does not match');
		}
	}

	// Test normal and nested element removal (items / triggers / graphs / hosts etc).
	public function testNestedLLD_testResourceRemoval() {
		$hostname = "lld_test_lost_resources";

		$this->importData($hostname);
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => ['hostid'],
			'filter' => [
				'host' => $hostname
			]
		], 60, 1);
		$this->assertArrayHasKey('result', $response, 'host not found');
		$this->assertArrayHasKey('hostid', $response['result'][0], json_encode($response['result']));
		$hostid = $response['result'][0]['hostid'];

		$testcases = [
			[
				'data' => [
					[
						"{#PARENTNAME}" => "aaa",
						"nested" => [
							["{#NAME}" => "bbb"]
						]
					],
					[
						"{#PARENTNAME}" => "ddd",
						"nested" => [
							["{#NAME}" => "eee"]
						]
					]
				],
				'expected' => [
					'items' => 4,
					'triggers' => 4,
					'discoveryRules' => 3,
					'graphs' => 4,
					'hosts' => 4
				]
			],
			[
				'data' => [
					[
						"{#PARENTNAME}" => "aaa",
						"nested" => [
							["{#NAME}" => "bbb"]
						]
					]
				],
				'expected' => [
					'items' => 2,
					'triggers' => 2,
					'discoveryRules' => 2,
					'graphs' => 2,
					'hosts' => 2
				]
			]
		];

		$this->checkResourceRemoval($hostname, $hostid, $testcases);
	}

	// Import host / template.
	private function importData($name) {
		$data = file_get_contents('integration/data/nested_lld/' . $name . '.yaml');

		$response = $this->call('configuration.import', [
			'format' => 'yaml',
			'source' => $data,
			'rules' => [
				'hosts' =>
				[
					'updateExisting' => true,
					'createMissing' => true
				],
				'valueMaps' =>
				[
					'updateExisting' => true,
					'createMissing' => true,
					'deleteMissing' => false
				],
				'templateLinkage' =>
				[
					'createMissing' => true,
					'deleteMissing' => false
				],
				'items' =>
				[
					'updateExisting' => true,
					'createMissing' => true,
					'deleteMissing' => false
				],
				'discoveryRules' =>
				[
					'updateExisting' => true,
					'createMissing' => true,
					'deleteMissing' => false
				],
				'triggers' =>
				[
					'updateExisting' => true,
					'createMissing' => true,
					'deleteMissing' => false
				],
				'graphs' =>
				[
					'updateExisting' => true,
					'createMissing' => true,
					'deleteMissing' => false
				],
				'httptests' =>
				[
					'updateExisting' => true,
					'createMissing' => true,
					'deleteMissing' => false
				],
				'templates' =>
				[
					'updateExisting' => true,
					'createMissing' => true
				]
			]
		]);
		$this->assertEquals(true, $response['result']);
	}

	// Test removal of multiple level nested LLD rules.
	public function testNestedLLD_testRemovalOfMultilevelNestedRules() {
		$hostname = "lld_test_multilevel";

		$this->importData($hostname);
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => ['hostid'],
			'filter' => [
				'host' => $hostname
			]
		], 60, 1);
		$this->assertArrayHasKey('result', $response, 'host not found');
		$this->assertArrayHasKey('hostid', $response['result'][0], json_encode($response['result']));
		$hostid = $response['result'][0]['hostid'];

		$data = [
			[
				"{#PARENTNAME}" => "aaa",
				"{#NAME}" => "ccc",
				"nested" => [
					[
						"{#TEST}" => "test1"
					]
				]
			],
			[
				"{#PARENTNAME}" => "xxx",
				"{#NAME}" => "zzz",
				"nested" => [
					[
						"{#TEST}" => "test2"
					]
				]
			]
		];

		$this->sendSenderValue($hostname, 'main_drule', $data);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);
		$this->sendSenderValue($hostname, 'main_drule', $data);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);

		$response = $this->call('discoveryrule.get', [
			'hostids' => [$hostid],
			'searchWildcardsEnabled' => true,
			'search' => [
				'name' => 'nested*'
			],
			'countOutput' => true
		]);
		$this->assertEquals(4, $response['result'], 'nested rule(s) were not discovered');

		$data = [
			[
				"{#PARENTNAME}" => "aaa",
				"{#TWO}" => "ccc",
				"nested" => [
					[
						"{#TEST}" => "test1"
					]
				]
			]
		];
		$this->sendRuleData($hostname, 'main_drule', $data);

		$response = $this->call('discoveryrule.get', [
			'hostids' => [$hostid],
			'searchWildcardsEnabled' => true,
			'search' => [
				'name' => 'nested*'
			],
			'countOutput' => true
		]);
		$this->assertEquals(2, $response['result'], 'nested rule(s) were not removed');
	}

	// Test overrides of nested rules, including update.
	public function testNestedLLD_Overrides() {
		$hostname = "lld_test_overrides";

		$this->importData($hostname);

		$response = $this->call('host.get', [
			'output' => ['hostid'],
			'filter' => [
				'host' => $hostname
			]
		]);
		$this->assertArrayHasKey('result', $response, 'host not found');
		$this->assertArrayHasKey('hostid', $response['result'][0], json_encode($response['result']));
		$hostid = $response['result'][0]['hostid'];

		$overrides = [
			[
				"name" => "test",
				"step" => "1",
				"stop" => "0",
				"filter" => [
					"evaltype" => "0",
					"conditions" => [
						[
							"macro" => "{#NAME}",
							"operator" => "8",
							"value" => "zzz"
						]
					]
				],
				"operations" => [
					[
						"operationobject" => OPERATION_OBJECT_LLD_RULE_PROTOTYPE,
						"operator" => "2",
						"value" => "zzz",
						"opdiscover" => [
							"discover" => "1"
						]
					]
				]
			]
		];

		$response = $this->callUntilDataIsPresent('discoveryruleprototype.get', [
			'output' => ['itemid'],
			"hostids" => [$hostid],
			"search" => [
				"name" => "nested[{#PARENTNAME}]"
			]
		], 60, 1);
		$this->assertArrayHasKey('result', $response, 'lld rule prototype was not found');
		$this->assertArrayHasKey('itemid', $response['result'][0], json_encode($response['result']));
		$druleproto_id = $response['result'][0]['itemid'];

		$response = $this->call('discoveryruleprototype.update', [
			"itemid" => $druleproto_id,
			"overrides" => $overrides
		]);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$data = [
			[
				"{#PARENTNAME}" => "aaa",
				"{#NAME}" => "ccc",
				"nested" => [
					[
						"{#TEST}" => "test1"
					]
				]
			],
			[
				"{#PARENTNAME}" => "xxx",
				"{#NAME}" => "zzz",
				"nested" => [
					[
						"{#TEST}" => "test2"
					]
				]
			]
		];

		$this->sendRuleData($hostname, 'main_drule', $data);

		$response = $this->call('discoveryrule.get', [
			'hostids' => [$hostid],
			'searchWildcardsEnabled' => true,
			'search' => [
				'name' => 'nested*'
			],
			'countOutput' => true
		]);
		$this->assertEquals(3, $response['result'], "expected discovered rule count does not match");

		$response = $this->call('discoveryrule.get', [
			'hostids' => [$hostid],
			'search' => [
				'name' => 'nested[xxx,zzz,test2]'
			],
			'countOutput' => true
		]);
		$this->assertEquals(0, $response['result'], 'rule should not have been discovered due to override');

		$overrides[0]['operations'][0]['opdiscover']['discover'] = 0;
		$response = $this->call('discoveryruleprototype.update', [
			"itemid" => $druleproto_id,
			"overrides" => $overrides
		]);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$data = [
			[
				"{#PARENTNAME}" => "aaa",
				"{#NAME}" => "ccc",
				"nested" => [
					[
						"{#TEST}" => "test1"
					]
				]
			],
			[
				"{#PARENTNAME}" => "xxx",
				"{#NAME}" => "zzz",
				"nested" => [
					[
						"{#TEST}" => "test2"
					]
				]
			]
		];

		$this->sendRuleData($hostname, 'main_drule', $data);

		$response = $this->call('discoveryrule.get', [
			'hostids' => [$hostid],
			'search' => [
				'name' => 'nested[xxx,zzz,test2]'
			],
			'countOutput' => true
		]);
		$this->assertEquals(1, $response['result'], 'rule should have been discovered');
	}

	// Test discovery rule prototype having item prototype as a master item.
	public function testNestedLLD_discoveryRuleProtoWithMasterItem() {
		$hostname = "lld_dep_proto";
		$this->importData($hostname);
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$data = [
			[
				"{#NAME}" => "dep_first"
			],
			[
				"{#NAME}" => "dep_second"
			]
		];

		$this->sendRuleData($hostname, 'main_drule', $data);

		foreach (["dep_first", "dep_second"] as $elem) {
			$rulename = 'dep_drule[' . $elem . ']';
			$itemname = 'master_item[' . $elem . ']';

			$response = $this->call('discoveryrule.get', [
				'search' => [
					'name' => $rulename
				],
				'countOutput' => true
			]);
			$this->assertEquals(1, $response['result'],
				'rule ' . $rulename . 'was not discovered');

			$response = $this->call('item.get', [
				'search' => [
					'key_' => $itemname
				],
				'countOutput' => true
			]);
			$this->assertEquals(1, $response['result'], 'item ' . $itemname . 'was not discovered');
		}
	}

	// Test updates of prototypes that are owned by second level lld rule prototype.
	public function testNestedLLD_checkUpdatesOfSecondLevelProtos() {
		$hostname = "lld_drule_proto_elem_update";
		$this->importData($hostname);
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$response = $this->call('host.get', [
			'output' => ['hostid'],
			'filter' => [
				'host' => $hostname
			]
		]);
		$this->assertArrayHasKey('result', $response, 'host not found');
		$this->assertArrayHasKey('hostid', $response['result'][0], json_encode($response['result']));
		$hostid = $response['result'][0]['hostid'];

		$data = [
			[
				"{#PARENTNAME}" => "A",
				"nested" => [
					["{#NAME}" => "B"]
				]
			]
		];

		$this->sendRuleData($hostname, 'main_drule', $data);

		$response = $this->callUntilDataIsPresent('discoveryruleprototype.get', [
			'hostids' => [$hostid],
			'search' => [
				'name' => 'nested[{#PARENTNAME}]'
			],
			'selectItems' => ['name', 'key_'],
			'selectTriggers' => ['description'],
			'selectGraphs' => ['name'],
			'selectDiscoveryRules' => ['name']
		], 60, 1);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('items', $response['result'][0]);
		$this->assertArrayHasKey(0, $response['result'][0]['items']);
		$this->assertArrayHasKey('triggers', $response['result'][0]);
		$this->assertArrayHasKey(0, $response['result'][0]['triggers']);
		$this->assertArrayHasKey('graphs', $response['result'][0]);
		$this->assertArrayHasKey(0, $response['result'][0]['graphs']);

		$itemprotoid = $response['result'][0]['items'][0]['itemid'];
		$triggerprotoid = $response['result'][0]['triggers'][0]['triggerid'];
		$graphprotoid = $response['result'][0]['graphs'][0]['graphid'];

		$response = $this->call('itemprototype.update', [
			'itemid' => $itemprotoid,
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'type' => ITEM_TYPE_SIMPLE,
			'delay' => 10
		]);
		$this->assertCount(1, $response['result']['itemids']);

		$response = $this->call('triggerprototype.update', [
			'triggerid' => $triggerprotoid,
			'manual_close' => 1,
			'type' => 1,
			'priority' => TRIGGER_SEVERITY_DISASTER
		]);
		$this->assertCount(1, $response['result']['triggerids']);

		$response = $this->call('graphprototype.update', [
			'graphid' => $graphprotoid,
			'height' => 111,
			'width' => 222
		]);
		$this->assertCount(1, $response['result']['graphids']);

		$data = [
			[
				"{#PARENTNAME}" => "A",
				"nested" => [
					["{#NAME}" => "B"]
				]
			]
		];

		$this->sendRuleData($hostname, 'main_drule', $data);

		$response = $this->callUntilDataIsPresent('item.get', [
			'hostid' => $hostid,
			'search' => [
				'name' => 'item.update.nested[A,B]'
			],
			'output' => [
				'value_type',
				'type',
				'delay'
			]
		], 60, 1);
		$this->assertArrayHasKey(0, $response['result'], 'item was not updated');
		$this->assertEquals(ITEM_VALUE_TYPE_FLOAT, $response['result'][0]['value_type']);
		$this->assertEquals(ITEM_TYPE_SIMPLE, $response['result'][0]['type']);
		$this->assertEquals(10, $response['result'][0]['delay']);

		$response = $this->callUntilDataIsPresent('trigger.get', [
			'hostids' => [$hostid],
			'search' => [
				'description' => 'item.update.nested[A,B]'
			],
			'output' => [
				'manual_close',
				'type',
				'priority'
			]
		], 60, 1);
		$this->assertArrayHasKey(0, $response['result'], 'trigger was not updated');
		$this->assertEquals(1, $response['result'][0]['manual_close']);
		$this->assertEquals(1, $response['result'][0]['type']);
		$this->assertEquals(TRIGGER_SEVERITY_DISASTER, $response['result'][0]['priority']);

		$response = $this->callUntilDataIsPresent('graph.get', [
			'search' => [
				'name' => 'graph.update.nested[A,B]'
			],
			'output' => [
				'width',
				'height'
			]
		], 60, 1);
		$this->assertArrayHasKey(0, $response['result'], 'graph was not updated');
		$this->assertEquals(222, $response['result'][0]['width']);
		$this->assertEquals(111, $response['result'][0]['height']);
	}

	// Test template tag propagation to the problem that was generated by trigger prototype from nested rule.
	public function testNestedLLD_testTmplTagPropagation() {
		$hostname = 'lld_template_tags_host';
		$this->importData('lld_test_template_tags');
		$this->importData($hostname);
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$data = [
			[
				"{#PARENTNAME}" => "A",
				"nested" => [
					["{#NAME}" => "B"]
				]
			]
		];

		$this->sendRuleData($hostname, 'main_drule', $data);
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$this->sendSenderValue($hostname, 'item[A,B]', 100);

		$expected_tag = [
			"tag" => "xxx",
			"value" => "yyy"
		];

		$response = $this->callUntilDataIsPresent('problem.get', [
			"search" => [
				"name" => "tagtrig[A,B]"
			],
			"selectTags" => "extend"
		], 30, 2);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey(0, $response['result'][0]['tags'],
			'template level tags were not found in a problem');

		$this->assertEquals($expected_tag, $response['result'][0]['tags'][0]);
	}

	/**
	 * Test nested low level discovery macros.
	 *
	 * This test is based on ZBXNEXT-10068.
	 *
	 * A discovery prototype having
	 *     name: 1-st level discovery rule
	 *     tag:  lld_trap[{#L0}]
	 * is created and tested with no filters and overrides.
	 */
	public function testNestedLLD_testNestedLLDMacros() {
		// a helper function to get and test items
		$itemsTest = function(int $hostid, array $expected_item_keys): array {
			$response = $this->call('item.get', [
				'hostids' => [$hostid],
				'selectTags' => 'extend'
			]);
			$this->assertArrayHasKey('result', $response);
			$items = [];
			foreach ($response['result'] as $item) {
				$this->assertArrayHasKey('key_', $item, 'Missing key_ in item: ' . json_encode($item));
				$items[$item['key_']] = $item;
			}
			// check for missing expected keys
			foreach ($expected_item_keys as $key) {
				$this->assertArrayHasKey($key, $items, "Missing expected item with key: '" . $key . "', items: '" . json_encode($items) . "'");
			}
			// check for unexpected keys
			foreach (array_keys($items) as $item_key) {
				$this->assertContains($item_key, $expected_item_keys, "Unexpected item with key: '" . $item_key . "', items: '" . json_encode($items) . "'");
			}
			return $items;
		};

		$trapper_value = [
			"data" => [
				[
					"db_name" => "db1",
					"ts_data" => [
						["db1" => "1MB"],
						["db1" => "1GB"],
						["db1" => "1TB"],
						["db2" => "2MB"],
						["db2" => "2GB"],
						["db2" => "2TB"],
						["common" => "5GB"]
					]
				]
			]
		];

		// After sending the trapper value the 1-st time,
		// discovery rule is expected to be created out of discovery prototype with:
		// 'name' => '1-st level discovery rule',
		// 'key_' => 'lld_trap[{#L0}]'.
		$this->sendSenderValue(self::HOSTNAME_TEST_MACRO, self::LLDRULE_KEY_ROOT, $trapper_value);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_process_discovery_rule', true, 10, 1, true);
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$response = $this->call('discoveryrule.get', [
			'hostids' => [
				self::$hostid_macros
			]
		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey(0, $response['result']);

		$discovery_rules = [];
		foreach ($response['result'] as $rule) {
			$this->assertArrayHasKey('key_', $rule, 'Missing key_ in discovery rule: ' . json_encode($rule));
			$discovery_rules[$rule['key_']] = $rule;
		}

		$expected_rule_keys = [
			'lld.test.macros.rule.root',	// created using API
			'lld_trap[db1]'			// discovered by LLD
		];
		// check for missing expected keys
		foreach ($expected_rule_keys as $key) {
			$this->assertArrayHasKey($key, $discovery_rules, "Missing expected discovery rule with key: '" . $key . "', discovery rules: '" . json_encode($discovery_rules) . "'");
		}
		// check for unexpected keys
		foreach (array_keys($discovery_rules) as $key) {
			$this->assertContains($key, $expected_rule_keys, "Unexpected discovery rule with key: '" . $key . "', discovery rules: '" . json_encode($discovery_rules) . "'");
		}

		// After sending the trapper value the 2-nd time,
		// items are expected to be created out of item prototypes.
		$this->sendSenderValue(self::HOSTNAME_TEST_MACRO, self::LLDRULE_KEY_ROOT, $trapper_value);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_process_discovery_rule', true, 10, 1, true);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_process_discovery_rule', true, 10, 1, true);
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		/* if macros are resolved correctly then items with the following keys should be discovered */
		$expected_item_keys = [
			'trap0[db1]',
			'trap1[db1,1MB]',
			'trap1[db1,1GB]',
			'trap1[db1,1TB]'
		];

		$itemsTest(self::$hostid_macros, $expected_item_keys);
	}
}

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
 * Test suite for alerting for services.
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @backup hosts, regexps, config_autoreg_tls, globalmacro, auditlog, changelog, ha_node, ids
 */
class testInitialConfSync extends CIntegrationTest
{
	private $expected_initial = [
			'config' =>
			[
				'insert' => '1',
				'update' => '0',
				'delete' => '0'
			],
			'config_autoreg_tls' =>
			[
				'insert' => '1',
				'update' => '0',
				'delete' => '0'
			],
			'autoreg_host' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			],
			'hosts' =>
			[
				'insert' => '15',
				'update' => '0',
				'delete' => '0'
			],
			'host_inventory' =>
			[
				'insert' => '3',
				'update' => '0',
				'delete' => '0'
			],
			'hosts_templates' =>
			[
				'insert' => '4',
				'update' => '0',
				'delete' => '0'
			],
			'globalmacro' =>
			[
				'insert' => '3',
				'update' => '0',
				'delete' => '0'
			],
			'hostmacro' =>
			[
				'insert' => '5',
				'update' => '0',
				'delete' => '0'
			],
			'interface' =>
			[
				'insert' => '15',
				'update' => '0',
				'delete' => '0'
			],

		/* Where the number 95 came from ?
		Need to go through the confsync_hosts.xml and confsync_tmpl.xml,
		count number of items, item prototypes and discovery rules for hosts
		and templates that get imported by those hosts.
		However, the following needs to be accounted:
		a) every httpstep in web scenarios have 6 hidden items
		b) item prototypes in discovery rules are ignored by configuration syncer
			(ZBX_FLAG_DISCOVERY_PROTOTYPE = 2)

		With that approach we get the following:

		1) HostInventoryAutomatic -> 3 items
		2) HostMultilevelTmpl -> 9 items, (1 items, also inherits bbbtmpl, which inherits
					'SampleTemplate', which has 1 item, 1 item prototype (ignored
					as it is part of the discovery rule), 1 discovery rule,
					1 httpstep(6 items)))
		3) HostWithDiscovery -> 1 items (1 item prototype(ignored as it is part of the discovery
					rule), 1 discovery rule)
		4) HostWithItems -> 28 items
		5) HostWithMacros -> 3 items
		6) HostWithTemplate -> 8 items (inherits 'SampleTemplate')
		7) HostWithWebScenario -> 7 items (1 item + httpstep(6 items))
		8) HostWithComprehensiveTemplate -> 36 items (inherits 'Comprehensive Template')
		3 + 9 + 1 + 28 + 3 + 8 + 7 + 36 = 95 */

			'items' =>
			[
				'insert' => '152',
				'update' => '0',
				'delete' => '0'
			],
			'item_discovery' =>
			[
				'insert' => '5',
				'update' => '0',
				'delete' => '0'
			],
			'triggers' =>
			[
				'insert' => '28',
				'update' => '0',
				'delete' => '0'
			],
			'trigger_depends' =>
			[
				'insert' => '7',
				'update' => '0',
				'delete' => '0'
			],
			'trigger_tag' =>
			[
				'insert' => '22',
				'update' => '0',
				'delete' => '0'
			],
			'host_tag' =>
			[
				'insert' => '3',
				'update' => '0',
				'delete' => '0'
			],
			'item_tag' =>
			[
				'insert' => '3',
				'update' => '0',
				'delete' => '0'
			],
			'functions' =>
			[
				'insert' => '28',
				'update' => '0',
				'delete' => '0'
			],
			'regexps' =>
			[
				'insert' => '1',
				'update' => '0',
				'delete' => '0'
			],
			'actions' =>
			[
				'insert' => '1',
				'update' => '0',
				'delete' => '0'
			],
			'operations' =>
			[
				'insert' => '0',
				'update' => '1',
				'delete' => '0'
			],
			'conditions' =>
			[
				'insert' => '1',
				'update' => '0',
				'delete' => '0'
			],
			'correlation' =>
			[
				'insert' => '1',
				'update' => '0',
				'delete' => '0'
			],
			'corr_condition' =>
			[
				'insert' => '1',
				'update' => '0',
				'delete' => '0'
			],
			'corr_operation' =>
			[
				'insert' => '1',
				'update' => '0',
				'delete' => '0'
			],
			'hstgrp' =>
			[
				'insert' => '3',
				'update' => '0',
				'delete' => '0'
			],
			'item_preproc' =>
			[
				'insert' => '44',
				'update' => '0',
				'delete' => '0'
			],
			'item_parameter' =>
			[
				'insert' => '2',
				'update' => '0',
				'delete' => '0'
			],
			'maintenances' =>
			[
				'insert' => '1',
				'update' => '0',
				'delete' => '0'
			],
			'drules' =>
			[
				'insert' => '1',
				'update' => '0',
				'delete' => '0'
			],
			'httptest' =>
			[
				'insert' => '5',
				'update' => '0',
				'delete' => '0'
			],
			'connector' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			],
			'connector_tag' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			],
			'proxy' =>
			[
				'insert' => '2',
				'update' => '0',
				'delete' => '0'
			],
			'proxy_group' =>
			[
				'insert' => '1',
				'update' => '0',
				'delete' => '0'
			],
			'host_proxy' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
	];

	private $expected_update =
	[
		"config" =>
		[
			"insert" =>
			"1",
			"update" =>
			"0",
			"delete" =>
			"0"
		],
		"config_autoreg_tls" =>
		[
			"insert" =>
			"0",
			"update" =>
			"1",
			"delete" =>
			"0"
		],
		'autoreg_host' =>
		[
			'insert' => '0',
			'update' => '0',
			'delete' => '0'
		],
		"hosts" =>
		[
			"insert" =>
			"0",
			"update" =>
			"14",
			"delete" =>
			"0"
		],
		"host_inventory" =>
		[
			"insert" =>
			"0",
			"update" =>
			"1",
			"delete" =>
			"0"
		],
		"hosts_templates" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"0"
		],
		"globalmacro" =>
		[
			"insert" =>
			"0",
			"update" =>
			"3",
			"delete" =>
			"0"
		],
		"hostmacro" =>
		[
			"insert" =>
			"2",
			"update" =>
			"2",
			"delete" =>
			"2"
		],
		"interface" =>
		[
			"insert" =>
			"0",
			"update" =>
			"9",
			"delete" =>
			"0"
		],
		"items" =>
		[
			"insert" =>
			"0",
			"update" =>
			"139",
			"delete" =>
			"0"
		],
		"item_discovery" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"0"
		],
		"triggers" =>
		[
			"insert" =>
			"9",
			"update" =>
			"10",
			"delete" =>
			"9"
		],
		"trigger_depends" =>
		[
			"insert" =>
			"4",
			"update" =>
			"0",
			"delete" =>
			"4"
		],
		"trigger_tag" =>
		[
			"insert" =>
			"12",
			"update" =>
			"0",
			"delete" =>
			"12"
		],
		"host_tag" =>
		[
			"insert" =>
			"2",
			"update" =>
			"0",
			"delete" =>
			"2"
		],
		"item_tag" =>
		[
			"insert" =>
			"1",
			"update" =>
			"0",
			"delete" =>
			"1"
		],
		"functions" =>
		[
			"insert" =>
			"15",
			"update" =>
			"0",
			"delete" =>
			"15"
		],
		"regexps" =>
		[
			"insert" =>
			"1",
			"update" =>
			"0",
			"delete" =>
			"1"
		],
		"actions" =>
		[
			"insert" =>
			"0",
			"update" =>
			"1",
			"delete" =>
			"0"
		],
		"operations" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"0"
		],
		"conditions" =>
		[
			"insert" =>
			"0",
			"update" =>
			"1",
			"delete" =>
			"0"
		],
		"correlation" =>
		[
			"insert" =>
			"0",
			"update" =>
			"1",
			"delete" =>
			"0"
		],
		"corr_condition" =>
		[
			"insert" =>
			"1",
			"update" =>
			"0",
			"delete" =>
			"1"
		],
		"corr_operation" =>
		[
			"insert" =>
			"1",
			"update" =>
			"0",
			"delete" =>
			"1"
		],
		"hstgrp" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"0"
		],
		"item_preproc" =>
		[
			"insert" =>
			"0",
			"update" =>
			"12",
			"delete" =>
			"0"
		],
		"item_parameter" =>
		[
			"insert" =>
			"0",
			"update" =>
			"1",
			"delete" =>
			"0"
		],
		"maintenances" =>
		[
			"insert" =>
			"0",
			"update" =>
			"1",
			"delete" =>
			"0"
		],
		"drules" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"0"
		],
		"httptest" =>
		[
			"insert" =>
			"0",
			"update" =>
			"5",
			"delete" =>
			"0"
		],
		'connector' =>
		[
			'insert' =>
			'0',
			'update' =>
			'0',
			'delete' =>
			'0'
		],
		'connector_tag' =>
		[
			'insert' =>
			'0',
			'update' =>
			'0',
			'delete' =>
			'0'
		],
		'proxy' =>
		[
			'insert' =>
			'0',
			'update' =>
			'2',
			'delete' =>
			'0'
		],
		'proxy_group' =>
		[
			'insert' => '0',
			'update' => '1',
			'delete' => '0'
		],
		'host_proxy' =>
		[
			'insert' => '0',
			'update' => '0',
			'delete' => '0'
		]
	];

	private $expected_delete = [
		"config" =>
		[
			"insert" =>
			"1",
			"update" =>
			"0",
			"delete" =>
			"0"
		],
		"config_autoreg_tls" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"0"
		],
		'autoreg_host' =>
		[
			'insert' => '0',
			'update' => '0',
			'delete' => '0'
		],
		"hosts" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"19"
		],
		"host_inventory" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"3"
		],
		"hosts_templates" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"4"
		],
		"globalmacro" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"0"
		],
		"hostmacro" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"5"
		],
		"interface" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"15"
		],
		"items" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"152"
		],
		"item_discovery" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"5"
		],
		"triggers" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"28"
		],
		"trigger_depends" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"7"
		],
		"trigger_tag" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"22"
		],
		"host_tag" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"3"
		],
		"item_tag" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"3"
		],
		"functions" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"28"
		],
		"regexps" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"1"
		],
		"actions" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"1"
		],
		"operations" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"0"
		],
		"conditions" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"1"
		],
		"correlation" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"1"
		],
		"corr_condition" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"1"
		],
		"corr_operation" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"1"
		],
		"hstgrp" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"2"
		],
		"item_preproc" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"44"
		],
		"item_parameter" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"2"
		],
		"maintenances" =>
		[
			"insert" =>
			"0",
			"update" =>
			"0",
			"delete" =>
			"1"
		],
		'drules' =>
		[
			'insert' => '0',
			'update' => '0',
			'delete' => '0'
		],
		'httptest' =>
		[
			'insert' => '0',
			'update' => '0',
			'delete' => '5'
		],
		'connector' =>
		[
			'insert' => '0',
			'update' => '0',
			'delete' => '0'
		],
		'connector_tag' =>
		[
			'insert' => '0',
			'update' => '0',
			'delete' => '0'
		],
		'proxy' =>
		[
			'insert' => '0',
			'update' => '0',
			'delete' => '2'
		],
		'proxy_group' =>
		[
			'insert' => '0',
			'update' => '0',
			'delete' => '1'
		],
		'host_proxy' =>
		[
			'insert' => '0',
			'update' => '0',
			'delete' => '0'
		]
	];

	private static $proxyid_active;
	private static $proxyid_passive;
	private static $proxy_groupid;
	private static $actionid;
	private static $triggerid;
	private static $correlationid;
	private static $maintenanceid;
	private static $regexpid;
	private static $vaultmacroid;
	private static $secretmacroid;
	private static $tlshostid;

	/**
	 * @inheritdoc
	 */
	public function prepareData()
	{
		return true;
	}

	/**
	 * Component configuration provider for server related tests.
	 *
	 * @return array
	 */
	public function serverConfigurationProvider()
	{
		return [
			self::COMPONENT_SERVER => [
				'LogFileSize' => 0,
				'DebugLevel' => 5,
				'Vault' => 'CyberArk',
				'VaultURL' => 'https://127.0.0.1:1858'
			]
		];
	}

	private function assertSyncResults($got, $expected_objects)
	{
		$diff_keys = array_diff_key($expected_objects, $got);

		$this->assertEmpty($diff_keys,
			'following objects are missing from sync log:\n'.var_export(array_keys($diff_keys), true));

		$diff_objects = [];

		foreach ($expected_objects as $obj_name => $obj_val)
		{
			$expected_values = $expected_objects[$obj_name];

			$diff = array_diff_assoc($obj_val, $expected_values);
			if (count($diff) > 0) {
				$failed_obj = [
					'expected' => $expected_values,
					'synced' => $obj_val
				];

				$diff_objects[$obj_name] = $failed_obj;
			}
		}

		$this->assertEmpty($diff_objects, 'different objects were found:\n'.var_export($diff_objects, true));
	}

	private function parseSyncResults()
	{
		$log = file_get_contents(self::getLogPath(self::COMPONENT_SERVER));
		$data = explode("\n", $log);

		$sync_lines = preg_grep('/zbx_dc_sync_configuration.*\([0-9]+\/[0-9]+\/[0-9]+\)\.$/', $data);

		$sync_lines1 = preg_replace(
			[
				"/^\s*[0-9]+:[0-9]+:[0-9]+\.[0-9]+ zbx_dc_sync_configuration\(\) /",
				"/\s+/",
				"/-?[0-9]+bytes/",
				"/:sql:[0-9]+\.[0-9]+sync:[0-9]+\.[0-9]+sec/",
				"/:sql:[0-9]+\.[0-9]+sec/"
			],
			"",
			$sync_lines
		);

		$sync_lines2 = preg_replace(
			[
				"/(\(\))|(\()/",
				"/\)\.|\./"
			],
			[
				":",
				""
			],
			$sync_lines1
		);

		$results = [];

		foreach ($sync_lines2 as $v) {
			$o = explode(":", $v);

			$subject = $o[0];
			$operations = explode("/", $o[1]);

			if (count($operations) < 3) {
				continue;
			}

			$results[$subject] = [
				'insert' => $operations[0],
				'update' => $operations[1],
				'delete' => $operations[2]
			];
		}

		return $results;
	}

	private function getStringPoolCount() {
		$log = file_get_contents(self::getLogPath(self::COMPONENT_SERVER));
		preg_match('/zbx_dc_sync_configuration\(\)\s+strings\s+:\s*(\d+)/', $log, $result);
		return $result[1];
	}

	private function purgeHostGroups()
	{
		$response = $this->call('hostgroup.get', [
			'output' => 'extend',
			'preservekeys' => true
		]);
		$this->assertArrayHasKey('result', $response);

		$filtered_groups = array_filter($response['result'], function ($obj) {
			return $obj['name'] != 'Discovered hosts';
		});

		$ids = array_keys($filtered_groups);
		if (empty($ids)) {
			return;
		}

		$response = $this->call('hostgroup.delete', $ids);
	}

	private function purgeGlobalMacros()
	{
		$response = $this->call('usermacro.get', [
			'output' => 'extend',
			'globalmacro' => true,
			'preservekeys' => true
		]);
		$this->assertArrayHasKey('result', $response);

		$ids = array_keys($response['result']);
		if (empty($ids)) {
			return;
		}

		$response = $this->call('usermacro.deleteglobal', $ids);
	}

	private function purgeExisting($method, $field_name)
	{
		$params = [
			'output' => $field_name,
			'preservekeys' => true
		];

		$response = $this->call($method . '.get', $params);
		$this->assertArrayHasKey('result', $response);

		$ids = array_keys($response['result']);

		if (empty($ids)) {
			return;
		}

		$response = $this->call($method . '.delete', $ids);
	}

	private function disableAllHosts()
	{
		$response = $this->call('host.get', [
			'output' => 'hostid',
			'preservekeys' => true
		]);
		$this->assertArrayHasKey('result', $response);

		$ids = array_keys($response['result']);

		if (empty($ids)) {
			return;
		}

		foreach ($ids as $hostid) {
			$response = $this->call('host.update', [
				'hostid' => $hostid,
				'status' => 1
			]);
		}
	}

	private function createActions()
	{
		$response = $this->call('trigger.get', [
			'output' => 'triggerids',
			'preservekeys' => true
		]);
		$this->assertArrayHasKey('result', $response);
		self::$triggerid = array_key_first($response['result']);

		$response = $this->call('action.create', [
			'esc_period' => '1h',
			'eventsource' => EVENT_SOURCE_TRIGGERS,
			'status' => 0,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_EVENT_NAME,
						'operator' => CONDITION_OPERATOR_LIKE,
						'value' => 'qqq'
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'name' => 'Trapper received 1 (problem) clone',
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 1,
						'mediatypeid' => 0
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			],
			'pause_suppressed' => 0,
			'recovery_operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 1,
						'mediatypeid' => 0
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
		self::$actionid = $response['result']['actionids'][0];
	}

	private function updateAction()
	{
		$response = $this->call('action.update', [
			'esc_period' => '5m',
			'actionid' => self::$actionid,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_EVENT_NAME,
						'operator' => CONDITION_OPERATOR_NOT_LIKE,
						'value' => 'qqq'
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_OR
			],
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => 0,
						'message' => '{SERVICE.NAME}|{SERVICE.TAGS}|{SERVICE.TAGSJSON}|{SERVICE.ROOTCAUSE}',
						'subject' => 'Problem'
					],
					'opmessage_grp' => [['usrgrpid' => 7]]
				]
			]
		]);

		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['actionids']);
	}

	private function createMaintenance()
	{
		$response = $this->call('host.get', [
			'output' => 'hostids',
			'preservekeys' => true
		]);
		$this->assertArrayHasKey('result', $response);
		$hostid = array_key_first($response['result']);

		$maint_start_tm = time();
		$maint_end_tm = $maint_start_tm + 60 * 2;

		$response = $this->call('maintenance.create', [
			'name' => 'Test maintenance',
			'hosts' => ['hostid' => $hostid],
			'active_since' => $maint_start_tm,
			'active_till' => $maint_end_tm,
			'tags_evaltype' => MAINTENANCE_TAG_EVAL_TYPE_AND_OR,
			'timeperiods' => [
				'timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
				'period' => 300,
				'start_date' => $maint_start_tm
			]
		]);
		$this->assertArrayHasKey('maintenanceids', $response['result']);
		$this->assertEquals(1, count($response['result']['maintenanceids']));
		self::$maintenanceid = $response['result']['maintenanceids'][0];
	}

	private function setupTlsForHost()
	{
		$response = $this->call('host.get', [
			'output' => 'hostids',
			'filter' => [
				'host' => ['Host1']
			],
			'preservekeys' => true
		]);
		$this->assertArrayHasKey('result', $response);
		self::$tlshostid = array_key_first($response['result']);

		$response = $this->call('host.update', [
			'hostid' => self::$tlshostid,
			'tls_connect' => HOST_ENCRYPTION_PSK,
			'tls_accept' => HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE,
			'tls_issuer' => 'iss',
			'tls_subject' => 'sub',
			'tls_psk_identity' => '2790d1e1781449f8879714a21fb706f9f008910ccf6b7339bb1975bc33e0c449',
			'tls_psk' => '1e07e499695b1c5f8fc1ccb5ee935240ae1b85d0ac0f821c7133aa17852bf7d8'
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertEquals(1, count($response['result']['hostids']));
	}

	private function updateTlsForHost()
	{
		$response = $this->call('host.update', [
			'hostid' => self::$tlshostid,
			'tls_connect' => HOST_ENCRYPTION_CERTIFICATE,
			'tls_accept' => HOST_ENCRYPTION_CERTIFICATE,
			'tls_issuer' => 'iss',
			'tls_subject' => 'sub'
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertEquals(1, count($response['result']['hostids']));
	}

	private function updateMaintenance()
	{
		$response = $this->call('maintenance.update', [
			'maintenanceid' => self::$maintenanceid,
			'active_since' => time(),
			'active_till' => time() + 86400
		]);
		$this->assertArrayHasKey('maintenanceids', $response['result']);
		$this->assertEquals(1, count($response['result']['maintenanceids']));
	}

	private function createCorrelation()
	{
		$response = $this->call('correlation.create', [
			'name' => 'new corr',
			'filter' => [
				'evaltype' => 0,
				'conditions' => [[
					'type' => 1,
					'tag' => 'ok'
				]]
			],
			'operations' => [
				['type' => 0]
			]
		]);
		$this->assertArrayHasKey("correlationids", $response['result']);
		self::$correlationid = $response['result']['correlationids'][0];
	}

	private function updateCorrelation()
	{
		$response = $this->call('correlation.update', [
			'correlationid' => self::$correlationid,
			'name' => 'cr',
			'filter' => [
				'evaltype' => 0,
				'conditions' => [[
					'type' => 3,
					'oldtag' => 'x',
					'newtag' => 'y'
				]]
			],
			'operations' => [
				['type' => 1]
			]
		]);
		$this->assertArrayHasKey("correlationids", $response['result']);
	}

	private function createRegexp()
	{
		$response = $this->call('regexp.create', [
			'name' => 'global regexp test',
			'test_string' => '/boot',
			'expressions' => [
				[
					'expression' => '.*',
					'expression_type' => EXPRESSION_TYPE_FALSE,
					'case_sensitive' => 1
				]
			]
		]);
		$this->assertArrayHasKey("regexpids", $response['result']);
		self::$regexpid = $response['result']['regexpids'][0];
	}

	private function updateRegexp()
	{
		$response = $this->call('regexp.update', [
			'regexpid' => self::$regexpid,
			'test_string' => '/tmp',
			'expressions' => [
				[
					'expression' => '.*a',
					'expression_type' => EXPRESSION_TYPE_TRUE,
					'case_sensitive' => 1
				]
			]
		]);
		$this->assertArrayHasKey("regexpids", $response['result']);
	}

	private function createProxies()
	{
		$response = $this->call('proxy.create', [
			'name' => 'ProxyA',
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
			'allowed_addresses' => '10.0.2.15,zabbix.test',
			'tls_connect' => HOST_ENCRYPTION_NONE,
			'tls_accept' => HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE,
			'tls_issuer' => 'iss',
			'tls_subject' => 'sub',
			'tls_psk_identity' => '2790d1e1781449f8879714a21fb706f9f008910ccf6b7339bb1975bc33e0c449',
			'tls_psk' => '1e07e499695b1c5f8fc1ccb5ee935240ae1b85d0ac0f821c7133aa17852bf7d8',
			'hosts' => []
		]);
		$this->assertArrayHasKey("proxyids", $response['result']);
		self::$proxyid_active = $response['result']['proxyids'][0];

		$response = $this->call('proxy.create', [
			'name' => 'ProxyP',
			'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
			'hosts' => [],
			'address' => '127.0.0.1',
			'port' => '10099',
			'tls_connect' => HOST_ENCRYPTION_PSK,
			'tls_accept' => HOST_ENCRYPTION_NONE,
			'tls_psk_identity' => '2790d1e1781449f8879714a21fb706f9f008910ccf6b7339bb1975bc33e0c449',
			'tls_psk' => '1e07e499695b1c5f8fc1ccb5ee935240ae1b85d0ac0f821c7133aa17852bf7d8'
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);
		self::$proxyid_passive = $response['result']['proxyids'][0];

		$response = $this->call('proxygroup.create', [
			'name' => 'Proxy group 1',
			'failover_delay' => '10',
			'min_online' => '1'
		]);
		$this->assertArrayHasKey('proxy_groupids', $response['result']);
		$this->assertCount(1, $response['result']['proxy_groupids']);
		self::$proxy_groupid = $response['result']['proxy_groupids'][0];
	}

	private function updateProxies()
	{
		$response = $this->call('proxy.update', [
			'proxyid' => self::$proxyid_active,
			'allowed_addresses' => '127.9.9.9'
		]);
		$this->assertArrayHasKey("proxyids", $response['result']);

		$response = $this->call('proxy.update', [
			'proxyid' => self::$proxyid_passive,
			'name' => 'ProxyP1',
			'address' => '127.1.30.2',
			'port' => '10299'
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);

		$response = $this->call('proxygroup.update', [
			'proxy_groupid' => self::$proxy_groupid,
			'failover_delay' => '20'
		]);
		$this->assertArrayHasKey('proxy_groupids', $response['result']);
	}

	private function createGlobalMacros()
	{
		$response = $this->call('usermacro.createglobal', [
			'macro' => '{$GLOBALMACRO}',
			'value' => '1'
		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('globalmacroids', $response['result']);

		$response = $this->call('usermacro.createglobal', [
			'macro' => '{$SECRETMACRO}',
			'value' => '1234567890',
			'type' => 1
		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('globalmacroids', $response['result']);
		self::$secretmacroid = $response['result']['globalmacroids'][0];

		$response = $this->call('usermacro.createglobal', [
			'macro' => '{$VAULTMACRO}',
			'value' => 'secret/zabbix:password',
			'type' => 2
		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('globalmacroids', $response['result']);
		self::$vaultmacroid = $response['result']['globalmacroids'][0];
	}

	private function updateAutoregistration()
	{
		$response = $this->call('autoregistration.update', [
			'tls_accept' => '3',
			'tls_psk_identity' => 'PSK 001',
			'tls_psk' => '11111595725ac58dd977beef14b97461a7c1045b9a1c923453302c5473193478'
		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertEquals(true, $response['result']);
	}

	private function updateGlobalMacro()
	{
		$response = $this->call('usermacro.get', [
			'output' => 'extend',
			'globalmacro' => 'true'
		]);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('globalmacroid', $response['result'][0]);

		$globalmacroid = $response['result'][0]['globalmacroid'];

		$response = $this->call('usermacro.updateglobal', [
			'globalmacroid' => $globalmacroid,
			'macro' => '{$UU}',
			'value' => 'updated'
		]);
		$this->assertArrayHasKey('globalmacroids', $response['result']);

		$response = $this->call('usermacro.updateglobal', [
			'globalmacroid' => self::$secretmacroid,
			'value' => 'qwerasdfzxcv'
		]);
		$this->assertArrayHasKey('globalmacroids', $response['result']);

		$response = $this->call('usermacro.updateglobal', [
			'globalmacroid' => self::$vaultmacroid,
			'value' => 'secret/zabbix:ZABBIX123'
		]);
		$this->assertArrayHasKey('globalmacroids', $response['result']);
	}

	private function importTemplate($filename)
	{
		$xml = file_get_contents('integration/data/' . $filename);

		$response = $this->call('configuration.import', [
			'format' => 'xml',
			'source' => $xml,
			'rules' => [
				'template_groups' =>
				[
					'updateExisting' => true,
					'createMissing' => true
				],
				'host_groups' =>
				[
					'updateExisting' => true,
					'createMissing' => true
				],
				'templates' =>
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
				'templateDashboards' =>
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
				]
			]
		]);
	}

	private function importTemplateForUpdate($filename)
	{
		$xml = file_get_contents('integration/data/' . $filename);

		$response = $this->call('configuration.import', [
			'format' => 'xml',
			'source' => $xml,
			'rules' => [
				'template_groups' =>
				[
					'updateExisting' => true,
					'createMissing' => false
				],
				'host_groups' =>
				[
					'updateExisting' => true,
					'createMissing' => false
				],
				'templates' =>
				[
					'updateExisting' => true,
					'createMissing' => false
				],
				'valueMaps' =>
				[
					'updateExisting' => true,
					'createMissing' => false,
					'deleteMissing' => false
				],
				'templateDashboards' =>
				[
					'updateExisting' => true,
					'createMissing' => false,
					'deleteMissing' => false
				],
				'templateLinkage' =>
				[
					'createMissing' => false,
					'deleteMissing' => false
				],
				'items' =>
				[
					'updateExisting' => true,
					'createMissing' => false,
					'deleteMissing' => false
				],
				'discoveryRules' =>
				[
					'updateExisting' => true,
					'createMissing' => false,
					'deleteMissing' => false
				],
				'triggers' =>
				[
					'updateExisting' => true,
					'createMissing' => false,
					'deleteMissing' => false
				],
				'graphs' =>
				[
					'updateExisting' => true,
					'createMissing' => false,
					'deleteMissing' => false
				],
				'httptests' =>
				[
					'updateExisting' => true,
					'createMissing' => false,
					'deleteMissing' => false
				]
			]
		]);
	}

	public function loadInitialConfiguration()
	{
		$this->createProxies();
		$this->createCorrelation();
		$this->createRegexp();
		$this->createGlobalMacros();
		$this->importTemplate('confsync_tmpl.xml');

		$xml = file_get_contents('integration/data/confsync_hosts.xml');

		$response = $this->call('configuration.import', [
			'format' => 'xml',
			'source' => $xml,
			'rules' => [
				'host_groups' =>
				[
				'updateExisting' => true,
				'createMissing' => true
				],
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
				]

			]
		]);

		$this->createActions();
		$this->createMaintenance();
		$this->setupTlsForHost();
	}

	/**
	 */
	public function testInitialConfSync_Insert()
	{
		$this->purgeExisting('action', 'actionids');
		$this->purgeExisting('host', 'hostids');
		$this->purgeExisting('proxy', 'extend');
		$this->purgeExisting('proxygroup', 'extend');
		$this->purgeExisting('template', 'templateids');
		$this->purgeExisting('item', 'itemids');
		$this->purgeExisting('trigger', 'triggerids');
		$this->purgeExisting('regexp', 'extend');
		$this->purgeHostGroups();
		$this->purgeGlobalMacros();

		self::stopComponent(self::COMPONENT_SERVER);
		self::clearLog(self::COMPONENT_SERVER);

		$this->loadInitialConfiguration();
		$this->disableAllHosts();

		self::startComponent(self::COMPONENT_SERVER);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of zbx_dc_sync_configuration()", true, 30, 1);

		$got = $this->parseSyncResults();
		$this->assertSyncResults($got, $this->expected_initial);

		$this->purgeExisting('correlation', 'correlationids');
		$this->purgeExisting('maintenance', 'maintenanceids');
		$this->purgeExisting('host', 'hostids');
		$this->purgeExisting('proxy', 'extend');
		$this->purgeExisting('proxygroup', 'extend');
		$this->purgeExisting('template', 'templateids');
		$this->purgeExisting('item', 'itemids');
		$this->purgeExisting('action', 'actionid');
		$this->purgeExisting('trigger', 'triggerids');
		$this->purgeExisting('regexp', 'extend');
		$this->purgeHostGroups();
		$this->purgeGlobalMacros();

		self::clearLog(self::COMPONENT_SERVER);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of zbx_dc_sync_configuration()", true, 30, 1);
		$stringpool_old = $this->getStringPoolCount();

		self::stopComponent(self::COMPONENT_SERVER);
		self::clearLog(self::COMPONENT_SERVER);

		self::startComponent(self::COMPONENT_SERVER);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of zbx_dc_sync_configuration()", true, 30, 1);
		$stringpool_new = $this->getStringPoolCount();

		$this->assertEquals($stringpool_old, $stringpool_new);

		$this->loadInitialConfiguration();
		$this->disableAllHosts();

		return true;
	}

	public function testInitialConfSync_Update()
	{
		$this->updateProxies();
		$this->updateCorrelation();
		$this->updateMaintenance();
		$this->updateRegexp();
		$this->updateAutoregistration();

		$this->importTemplateForUpdate('confsync_tmpl_updated.xml');
		$xml = file_get_contents('integration/data/confsync_hosts_updated.xml');

		$response = $this->call('configuration.import', [
			'format' => 'xml',
			'source' => $xml,
			'rules' => [
				'hosts' => [
					'createMissing' => false,
					'updateExisting' => true
				],
				'items' => [
					'createMissing' => false,
					'updateExisting' => true
				],
				'host_groups' => [
					'createMissing' => false,
					'updateExisting' => true
				],
				'discoveryRules' => [
					'createMissing' => false,
					'updateExisting' => true,
					'deleteMissing' => false
				],
				'httptests' => [
					'createMissing' => false,
					'updateExisting' => true,
					'deleteMissing' => false
				],
				'triggers' => [
					'createMissing' => false,
					'updateExisting' => true,
					'deleteMissing' => false
				],
				'templateLinkage' => [
					'createMissing' => false
				]
			]

		]);

		$this->updateGlobalMacro();
		$this->updateAction();
		$this->updateTlsForHost();

		$this->clearLog(self::COMPONENT_SERVER);
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of zbx_dc_sync_configuration()", true, 30, 1);

		$got = $this->parseSyncResults();
		$this->assertSyncResults($got, $this->expected_update);

		return true;
	}

	public function testInitialConfSync_Delete()
	{
		$this->purgeExisting('action', 'actionids');
		$this->purgeExisting('maintenance', 'maintenanceids');
		$this->purgeExisting('host', 'hostids');
		$this->purgeExisting('proxy', 'extend');
		$this->purgeExisting('proxygroup', 'extend');
		$this->purgeExisting('template', 'templateids');
		$this->purgeExisting('correlation', 'correlationids');
		$this->purgeExisting('regexp', 'extend');
		$this->purgeExisting('item', 'itemids');
		$this->purgeHostGroups();

		$this->clearLog(self::COMPONENT_SERVER);
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of zbx_dc_sync_configuration()", true, 30, 1);

		$got = $this->parseSyncResults();
		$this->assertSyncResults($got, $this->expected_delete);

		self::stopComponent(self::COMPONENT_SERVER);
		self::clearLog(self::COMPONENT_SERVER);
		self::startComponent(self::COMPONENT_SERVER);

		return true;
	}
}

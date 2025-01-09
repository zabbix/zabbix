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

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';
require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * Test suite for user macro expansion in item names.
 *
 * @required-components server
 * @backup hosts,items,item_rtname,globalmacro,hosts
 */
class testUserMacrosInItemNames extends CIntegrationTest {
	const HOSTNAME1 = 'test_user_macros_in_item_names1';
	const HOSTNAME2 = 'test_user_macros_in_item_names2';

	private static $hostid1;
	private static $hostid2;
	private static $macroid;

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		$response = $this->call('host.create', [
			[
				'host' => self::HOSTNAME1,
				'interfaces' => [],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid1 = $response['result']['hostids'][0];

		$response = $this->call('item.create', [
			'hostid' => self::$hostid1,
			'name' => 'Item {$TEST}',
			'key_' => 'item1',
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		$response = $this->call('discoveryrule.create', [
			'hostid' => self::$hostid1,
			'name' => 'Trapper discovery',
			'key_' => 'item_discovery',
			'type' => ITEM_TYPE_TRAPPER
		]);

		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		$ruleid = $response['result']['itemids'][0];

		$response = $this->call('itemprototype.create', [
			'hostid' => self::$hostid1,
			'ruleid' => $ruleid,
			'name' => 'LLD {$TEST} {#KEY}',
			'key_' => 'trap[{#KEY}]',
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_TEXT
		]);

		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);

		$response = $this->call('usermacro.createglobal', [
			'macro' => '{$TEST}',
			'value' => 'tst'
		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('globalmacroids', $response['result']);
		self::$macroid = $response['result']['globalmacroids'][0];

		$tmpl = [
			"zabbix_export" => [
				"version" => "7.0",
				"template_groups" => [
					[
						"uuid" => "7df96b18c230490a9a0a9e2307226338",
						"name" => "Templates"
					]
				],
				"templates" => [
					[
						"uuid" => "1e3947441cdf40ebb6c6f3335d2fcdbc",
						"template" => "Um1",
						"name" => "Um1",
						"groups" => [
							["name" => "Templates"]
						],
						"items" => [
							[
								"uuid" => "c8df8ff7fb15476cb2ecea02cadc447a",
								"name" => 'Template item {$TEST}',
								"type" => "TRAP",
								"key" => "tmpl.item",
								"delay" => "0"
							]
						]
					]
				]
			]
		];

		$response = $this->call('configuration.import', [
			'format' => 'json',
			'source' => json_encode($tmpl),
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

		$response = $this->callUntilDataIsPresent('template.get', [
			'output' => ['templateid'],
			'filter' => [
				'name' => 'Um1'
			]
		], 10, 1);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('templateid', $response['result'][0]);
		$templateid = $response['result'][0]['templateid'];

		$response = $this->call('host.create', [
			[
				'host' => self::HOSTNAME2,
				'interfaces' => [],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED,
				'templates' => ['templateid' => $templateid]
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid2 = $response['result']['hostids'][0];

		return true;
	}

	/**
	 * Check user macro expansion in name of normal item.
	 */
	public function testUserMacrosInItemNames_normalItem() {
		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => self::$hostid1,
			'search' => ['name_resolved' => 'Item tst']
		], 60, 1);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('name_resolved', $response['result'][0]);
		$this->assertEquals('Item tst', $response['result'][0]['name_resolved']);

		return true;
	}

	/**
	 * Check update of macro value.
	 */
	public function testUserMacrosInItemNames_normalItemUpdated() {
		$response = $this->call('usermacro.updateglobal', [
			'globalmacroid' => self::$macroid,
			'macro' => '{$TEST}',
			'value' => 'test'
		]);
		$this->assertArrayHasKey('globalmacroids', $response['result']);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => self::$hostid1,
			'search' => ['name_resolved' => 'Item test']
		], 60, 1);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('name_resolved', $response['result'][0]);
		$this->assertEquals('Item test', $response['result'][0]['name_resolved']);

		return true;
	}

	/**
	 * Check user macro expansion in name of discovered item.
	 */
	public function testUserMacrosInItemNames_lld() {
		$this->sendSenderValue(self::HOSTNAME1, 'item_discovery', ['data' => [
			[
				'{#KEY}' => '1'
			]
		]]);

		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => self::$hostid1,
			'search' => ['name_resolved' => 'LLD test 1']
		], 60, 1);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('name_resolved', $response['result'][0]);
		$this->assertEquals('LLD test 1', $response['result'][0]['name_resolved']);

		return true;
	}

	/**
	 * Check user macro expansion in name of a templated item.
	 */
	public function testUserMacrosInItemNames_templatedItem() {
		$response = $this->callUntilDataIsPresent('item.get', [
			'hostids' => self::$hostid2,
			'search' => ['name_resolved' => 'Template item test']
		], 60, 1);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('name_resolved', $response['result'][0]);
		$this->assertEquals('Template item test', $response['result'][0]['name_resolved']);

		return true;
	}
}

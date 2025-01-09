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

/**
 * Test suite for low level discovery (LLD).
 *
 * @required-components server
 * @backup items,hosts,triggers,graphs,hstgrp
 */
class testLowLevelDiscovery extends CIntegrationTest {

	const TEMPLATE_NAME_PRE = 'template';
	const NUMBER_OF_TEMPLATES = 2;

	const DRULE_LIFETIME_DEFAULT = '1_drule_default';
	const DRULE_LIFETIME_NEVER = '2_drule_never';
	const DRULE_LIFETIME_DELETE = '3_drule_delete';
	const DRULE_LIFETIME_DISABLE_AFTER = '4_drule_disable_after';
	const DRULE_LIFETIME_DELETE_DISABLED = '5_drule_test_delete_disabled';
	const HOSTNAME_LIFETIME = 'lifetime';
	const LLD_DATA_MACRO = '{#DISC.ID}';
	const LLD_DATA_MACRO_VALUE = '01';

	private static $hostid;
	private static $lifetime_hostid;
	private static $ruleid;

	private static $disable_itemid;
	private static $disable_triggerid;
	private static $disable_hostid;
	private static $delete_disabled_itemid;
	private static $delete_disabled_triggerid;
	private static $delete_disabled_hostid;

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create hosts "discovery" and "lifetime".
		$response = $this->call('host.create', [
			[
				'host' => 'discovery',
				'interfaces' => [
					[
						'type' => 1,
						'main' => 1,
						'useip' => 1,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
					]
				],
				'groups' => [
					[
						'groupid' => 4
					]
				]
			],
			[
				'host' => self::HOSTNAME_LIFETIME,
				'interfaces' => [
					[
						'type' => 1,
						'main' => 1,
						'useip' => 1,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
					]
				],
				'groups' => [
					[
						'groupid' => 4
					]
				]
			]
		]);

		$this->assertCount(2, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];
		self::$lifetime_hostid = $response['result']['hostids'][1];

		// Create discovery rule.
		$response = $this->call('discoveryrule.create', [
			'hostid' => self::$hostid,
			'name' => 'Trapper discovery',
			'key_' => 'item_discovery',
			'type' => ITEM_TYPE_TRAPPER
		]);

		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$ruleid = $response['result']['itemids'][0];

		// Create item prototype.
		$response = $this->call('itemprototype.create', [
			'hostid' => self::$hostid,
			'ruleid' => self::$ruleid,
			'name' => 'Item: {#KEY}',
			'key_' => 'trap[{#KEY}]',
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_TEXT
		]);

		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);

		// Get discovered hosts hostgroup ID
		$response = $this->call('settings.get', [
			'output' => [ 'discovery_groupid' ]
		]);

		$this->assertArrayHasKey('discovery_groupid', $response['result']);
		$hostgroupid = $response['result']['discovery_groupid'];

		// Create LLD rules with various lifetimes

		$this->createLowLevelDiscoveryRule(self::DRULE_LIFETIME_DEFAULT, [], $hostgroupid);

		$this->createLowLevelDiscoveryRule(
				self::DRULE_LIFETIME_NEVER,
				[
					'lifetime_type' => 1,
					'enabled_lifetime_type' => 1
				],
				$hostgroupid);

		$this->createLowLevelDiscoveryRule(self::DRULE_LIFETIME_DELETE, ['lifetime_type' => 2], $hostgroupid);

		$this->createLowLevelDiscoveryRule(
				self::DRULE_LIFETIME_DISABLE_AFTER,
				[
					'lifetime_type' => 0,
					'lifetime' => '2h',
					'enabled_lifetime_type' => 0,
					'enabled_lifetime' => '1h'
				],
				$hostgroupid);

		$this->createLowLevelDiscoveryRule(self::DRULE_LIFETIME_DELETE_DISABLED, ['lifetime_type' => 2], $hostgroupid);

		return true;
	}

	private function createLowLevelDiscoveryRule($rule_name, $lt, $hostgroupid) {
		$lt['name'] = $rule_name;
		$lt['key_'] = $rule_name;
		$lt['hostid'] = self::$lifetime_hostid;
		$lt['type'] = ITEM_TYPE_TRAPPER;

		$response = $this->call('discoveryrule.create', $lt);
		$this->assertCount(1, $response['result']['itemids']);
		$ruleid = $response['result']['itemids'][0];

		// Create host prototype with group prototype.
		$response = $this->call('hostprototype.create', [
			'ruleid' => $ruleid,
			'host' => $rule_name.'_h_'.self::LLD_DATA_MACRO,
			'groupLinks' => [
				[
					'groupid' => $hostgroupid
				]
			],
			'groupPrototypes' => [
				[
					'name' => $rule_name.'_hg_'.self::LLD_DATA_MACRO
				]
			]
		]);
		$this->assertCount(1, $response['result']['hostids']);

		// Create item prototype.
		$response = $this->call('itemprototype.create', [
			'hostid' => self::$lifetime_hostid,
			'ruleid' => $ruleid,
			'name' => $rule_name.'_i_'.self::LLD_DATA_MACRO,
			'key_' => $rule_name.'_trap['.self::LLD_DATA_MACRO.']',
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64
		]);

		$this->assertCount(1, $response['result']['itemids']);
		$itemprotoid = $response['result']['itemids'][0];

		// Create trigger prototype.
		$response = $this->call('triggerprototype.create', [
			'description' => $rule_name.'_t_'.self::LLD_DATA_MACRO,
			'expression' => 'last(/'.self::HOSTNAME_LIFETIME.'/'.$rule_name.'_trap['.self::LLD_DATA_MACRO.'])=1'
		]);
		$this->assertCount(1, $response['result']['triggerids']);

		// Create graph prototype.
		$response = $this->call('graphprototype.create', [
			'name' => $rule_name.'_g_'.self::LLD_DATA_MACRO,
			'width' => 900,
			'height' => 200,
			'gitems' => [
				[
					'itemid' => $itemprotoid,
					'color' => '3333FF'
				]
			]
		]);
		$this->assertCount(1, $response['result']['graphids']);
	}

	/**
	 * Test discovery by checking creation of items from item prototype.
	 */
	public function testLowLevelDiscovery_DiscoverItems() {
		$items = [];

		for ($i = 1; $i < 10; $i++) {
			$items[] = ['{#KEY}' => 'item'.$i];

			// Send value to discovery trapper.
			$this->sendSenderValue('discovery', 'item_discovery', ['data' => $items]);

			// Retrieve data from API.
			$data = $this->call('item.get', [
				'hostids'	=> self::$hostid,
				'output'	=> ['name', 'key_', 'type', 'value_type'],
				'sortfield'	=> 'key_'
			]);

			$this->assertTrue(is_array($data['result']));
			$this->assertEquals($i, count($data['result']));

			foreach ($data['result'] as $n => $item) {
				$key = 'item'.($n + 1);

				$this->assertEquals('Item: '.$key, $item['name']);
				$this->assertEquals('trap['.$key.']', $item['key_']);
				$this->assertEquals(ITEM_TYPE_TRAPPER, $item['type']);
				$this->assertEquals(ITEM_VALUE_TYPE_TEXT, $item['value_type']);
			}
		}
	}

	/**
	 * Test discovery by checking that lost resources are deleted.
	 *
	 * @depends testLowLevelDiscovery_DiscoverItems
	 */
	public function testLowLevelDiscovery_LooseItems() {
		// Update lifetime of discovery rule.
		$this->call('discoveryrule.update', [
			'itemid' => self::$ruleid,
			'lifetime' => 0
		]);

		// Reload configuration cache.
		$this->reloadConfigurationCache();

		$key = 'item5';
		// Send value to discovery trapper.
		$this->sendSenderValue('discovery', 'item_discovery', ['data' => [['{#KEY}' => $key]]]);

		// Retrieve data from API.
		$data = $this->call('item.get', [
			'hostids'	=> self::$hostid,
			'output'	=> ['itemid', 'name', 'key_', 'type', 'value_type'],
			'sortfield'	=> 'key_'
		]);

		$this->assertTrue(is_array($data['result']));
		$this->assertEquals(1, count($data['result']));

		$item = $data['result'][0];
		$this->assertEquals('Item: '.$key, $item['name']);
		$this->assertEquals('trap['.$key.']', $item['key_']);
		$this->assertEquals(ITEM_TYPE_TRAPPER, $item['type']);
		$this->assertEquals(ITEM_VALUE_TYPE_TEXT, $item['value_type']);

		// Data from API is passed as an input to testLowLevelDiscovery_CheckDiscoveredItem.
		return $item;
	}

	/**
	 * Test discovery by checking that discovered item can receive data.
	 *
	 * @depends testLowLevelDiscovery_LooseItems
	 */
	public function testLowLevelDiscovery_CheckDiscoveredItem($item) {
		$from = time();
		$value = md5($from.rand()).'-'.microtime();

		// Send value to discovery trapper.
		$this->sendSenderValue('discovery', $item['key_'], $value);

		// Retrieve history data from API as soon it is available.
		$data = $this->callUntilDataIsPresent('history.get', [
			'itemids'	=> $item['itemid'],
			'history'	=> ITEM_VALUE_TYPE_TEXT
		]);

		$item = $data['result'][0];
		$this->assertEquals($value, $item['value']);
		$this->assertGreaterThanOrEqual($from, $item['clock']);
		$this->assertLessThanOrEqual(time(), $item['clock']);
	}

	/**
	 * Test discovery by checking link type in host created from host prototype.
	 */
	public function testLowLevelDiscovery_LinkTemplates() {
		// Create templates.
		$templateids = array();

		for ($i = 0; $i < self::NUMBER_OF_TEMPLATES; $i++) {

			$response = $this->call('template.create', [
				'host' => self::TEMPLATE_NAME_PRE . "_" . $i,
				'groups' => [
					'groupid' => 1
				]]);

			$this->assertArrayHasKey('templateids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['templateids']);

			array_push($templateids, $response['result']['templateids'][0]);
		}

		// Create host prototype
		$response = $this->call('hostprototype.create', [
			'host' => 'host_{#KEY}',
			'groupLinks' => [
				[
					'groupid' => 4
				]
			],
			'ruleid' => self::$ruleid,
			'templates' => [
				[
					'templateid' => $templateids[0]
				]
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		$hostprototypeid = $response['result']['hostids'][0];

		$key = 'host0';
		// Send value to discovery trapper.
		$this->sendSenderValue('discovery', 'item_discovery', ['data' => [['{#KEY}' => $key]]]);

		// Retrieve data from API.
		$response = $this->call('host.get', [
			'filter' => ['host' => 'host_'.$key],
			'selectParentTemplates' => ['link_type']
		]);
		$this->assertArrayHasKey(0, $response['result'], json_encode($response, JSON_PRETTY_PRINT));
		$this->assertArrayHasKey('host', $response['result'][0]);
		$discoveredhostid = $response['result'][0]['hostid'];
		$this->assertEquals(1, count($response['result'][0]['parentTemplates']));
		foreach ($response['result'][0]['parentTemplates'] as $entry) {
					$this->assertEquals(1, $entry['link_type']);
		}

		// Link other templates
		$templates = array();
		for ($i = 0; $i < self::NUMBER_OF_TEMPLATES; $i++) {
			array_push($templates, [ 'templateid' => $templateids[$i]]);
		}
		// Uncomment this code when API allows manual link to discovered hosts
		$this->call('host.update', ['hostid' => $discoveredhostid, 'templates' => $templates]);
		$this->call('hostprototype.update', ['hostid' => $hostprototypeid, 'templates' => $templates]);
		$this->reloadConfigurationCache();

		// Send value to discovery trapper.
		$this->sendSenderValue('discovery', 'item_discovery', ['data' => [['{#KEY}' => $key]]]);

		// Retrieve data from API.
		$response = $this->call('host.get', [
			'filter' => ['host' => 'host_'.$key],
			'selectParentTemplates' => ['link_type']
		]);
		$this->assertArrayHasKey(0, $response['result'], json_encode($response, JSON_PRETTY_PRINT));
		$this->assertArrayHasKey('host', $response['result'][0]);
		$this->assertEquals($i, count($response['result'][0]['parentTemplates']));
		foreach ($response['result'][0]['parentTemplates'] as $entry) {
					$this->assertEquals(1, $entry['link_type']);
		}

		$this->call('hostprototype.delete', [$hostprototypeid]);

		// Reload configuration cache.
		$this->reloadConfigurationCache();
	}

	/**
	 * Test discovery by checking creation of various objects.
	 */
	public function testLowLevelDiscovery_DiscoverObjects() {
		$lld_data[] = [self::LLD_DATA_MACRO => self::LLD_DATA_MACRO_VALUE];

		$this->sendSenderValue(self::HOSTNAME_LIFETIME, self::DRULE_LIFETIME_DEFAULT, ['data' => $lld_data]);
		$this->sendSenderValue(self::HOSTNAME_LIFETIME, self::DRULE_LIFETIME_NEVER, ['data' => $lld_data]);
		$this->sendSenderValue(self::HOSTNAME_LIFETIME, self::DRULE_LIFETIME_DELETE, ['data' => $lld_data]);
		$this->sendSenderValue(self::HOSTNAME_LIFETIME, self::DRULE_LIFETIME_DISABLE_AFTER, ['data' => $lld_data]);
		$this->sendSenderValue(self::HOSTNAME_LIFETIME, self::DRULE_LIFETIME_DELETE_DISABLED, ['data' => $lld_data]);

		// Check items

		$response = $this->call('item.get', [
			'hostids'	=> self::$lifetime_hostid,
			'output'	=> ['name', 'status', 'itemid'],
			'sortfield'	=> 'name'
		]);
		$this->assertCount(5, $response['result']);

		$item = $response['result'][0];
		$this->assertEquals(self::DRULE_LIFETIME_DEFAULT.'_i_'.self::LLD_DATA_MACRO_VALUE, $item['name']);
		$this->assertEquals(ITEM_STATUS_ACTIVE, $item['status']);

		$item = $response['result'][1];
		$this->assertEquals(self::DRULE_LIFETIME_NEVER.'_i_'.self::LLD_DATA_MACRO_VALUE, $item['name']);
		$this->assertEquals(ITEM_STATUS_ACTIVE, $item['status']);

		$item = $response['result'][2];
		$this->assertEquals(self::DRULE_LIFETIME_DELETE.'_i_'.self::LLD_DATA_MACRO_VALUE, $item['name']);
		$this->assertEquals(ITEM_STATUS_ACTIVE, $item['status']);

		$item = $response['result'][3];
		$this->assertEquals(self::DRULE_LIFETIME_DISABLE_AFTER.'_i_'.self::LLD_DATA_MACRO_VALUE, $item['name']);
		$this->assertEquals(ITEM_STATUS_ACTIVE, $item['status']);
		self::$disable_itemid = $item['itemid'];

		$item = $response['result'][4];
		$this->assertEquals(self::DRULE_LIFETIME_DELETE_DISABLED.'_i_'.self::LLD_DATA_MACRO_VALUE, $item['name']);
		$this->assertEquals(ITEM_STATUS_ACTIVE, $item['status']);
		self::$delete_disabled_itemid = $item['itemid'];

		// Check triggers

		$response = $this->call('trigger.get', [
			'hostids'	=> self::$lifetime_hostid,
			'output'	=> ['description', 'status'],
			'sortfield'	=> 'description'
		]);
		$this->assertCount(5, $response['result']);

		$trigger = $response['result'][0];
		$this->assertEquals(self::DRULE_LIFETIME_DEFAULT.'_t_'.self::LLD_DATA_MACRO_VALUE, $trigger['description']);
		$this->assertEquals(TRIGGER_STATUS_ENABLED, $trigger['status']);

		$trigger = $response['result'][1];
		$this->assertEquals(self::DRULE_LIFETIME_NEVER.'_t_'.self::LLD_DATA_MACRO_VALUE, $trigger['description']);
		$this->assertEquals(TRIGGER_STATUS_ENABLED, $trigger['status']);

		$trigger = $response['result'][2];
		$this->assertEquals(self::DRULE_LIFETIME_DELETE.'_t_'.self::LLD_DATA_MACRO_VALUE, $trigger['description']);
		$this->assertEquals(TRIGGER_STATUS_ENABLED, $trigger['status']);

		$trigger = $response['result'][3];
		$this->assertEquals(self::DRULE_LIFETIME_DISABLE_AFTER.'_t_'.self::LLD_DATA_MACRO_VALUE, $trigger['description']);
		$this->assertEquals(TRIGGER_STATUS_ENABLED, $trigger['status']);
		self::$disable_triggerid = $trigger['triggerid'];

		$trigger = $response['result'][4];
		$this->assertEquals(self::DRULE_LIFETIME_DELETE_DISABLED.'_t_'.self::LLD_DATA_MACRO_VALUE, $trigger['description']);
		$this->assertEquals(TRIGGER_STATUS_ENABLED, $trigger['status']);
		self::$delete_disabled_triggerid = $trigger['triggerid'];

		// Check graphs

		$response = $this->call('graph.get', [
			'hostids'	=> self::$lifetime_hostid,
			'output'	=> ['name'],
			'sortfield'	=> 'name'
		]);
		$this->assertCount(5, $response['result']);

		$graph = $response['result'][0];
		$this->assertEquals(self::DRULE_LIFETIME_DEFAULT.'_g_'.self::LLD_DATA_MACRO_VALUE, $graph['name']);

		$graph = $response['result'][1];
		$this->assertEquals(self::DRULE_LIFETIME_NEVER.'_g_'.self::LLD_DATA_MACRO_VALUE, $graph['name']);

		$graph = $response['result'][2];
		$this->assertEquals(self::DRULE_LIFETIME_DELETE.'_g_'.self::LLD_DATA_MACRO_VALUE, $graph['name']);

		$graph = $response['result'][3];
		$this->assertEquals(self::DRULE_LIFETIME_DISABLE_AFTER.'_g_'.self::LLD_DATA_MACRO_VALUE, $graph['name']);

		$graph = $response['result'][4];
		$this->assertEquals(self::DRULE_LIFETIME_DELETE_DISABLED.'_g_'.self::LLD_DATA_MACRO_VALUE, $graph['name']);

		// Check hosts

		$response = $this->call('host.get', [
			'output' => ['host', 'hostid'],
			'sortfield' => 'host',
			'filter' => [
				'host' => [
						self::DRULE_LIFETIME_DEFAULT.'_h_'.self::LLD_DATA_MACRO_VALUE,
						self::DRULE_LIFETIME_NEVER.'_h_'.self::LLD_DATA_MACRO_VALUE,
						self::DRULE_LIFETIME_DELETE.'_h_'.self::LLD_DATA_MACRO_VALUE,
						self::DRULE_LIFETIME_DISABLE_AFTER.'_h_'.self::LLD_DATA_MACRO_VALUE,
						self::DRULE_LIFETIME_DELETE_DISABLED.'_h_'.self::LLD_DATA_MACRO_VALUE
				],
				'status' => HOST_STATUS_MONITORED
			]
		]);
		$this->assertCount(5, $response['result']);

		self::$disable_hostid = $response['result'][3]['hostid'];
		self::$delete_disabled_hostid = $response['result'][4]['hostid'];

		// Check host groups
		$response = $this->call('hostgroup.get', [
			'output' => ['name'],
			'filter' => [
				'name' => [
						self::DRULE_LIFETIME_DEFAULT.'_hg_'.self::LLD_DATA_MACRO_VALUE,
						self::DRULE_LIFETIME_NEVER.'_hg_'.self::LLD_DATA_MACRO_VALUE,
						self::DRULE_LIFETIME_DELETE.'_hg_'.self::LLD_DATA_MACRO_VALUE,
						self::DRULE_LIFETIME_DISABLE_AFTER.'_hg_'.self::LLD_DATA_MACRO_VALUE,
						self::DRULE_LIFETIME_DELETE_DISABLED.'_hg_'.self::LLD_DATA_MACRO_VALUE
				]
			]
		]);
		$this->assertCount(5, $response['result']);
	}

	/**
	 * Test discovery lifetime by checking objects presence and status.
	 *
	 * @depends testLowLevelDiscovery_DiscoverObjects
	 */
	public function testLowLevelDiscovery_LooseObjects() {
		// Disable item, trigger and host to verify that disabled objects are deleted

		$response = $this->call('item.update', [
			'itemid'	=> self::$delete_disabled_itemid,
			'status'	=> ITEM_STATUS_DISABLED
		]);
		$this->assertEquals(self::$delete_disabled_itemid, $response['result']['itemids'][0]);

		$response = $this->call('trigger.update', [
			'triggerid'	=> self::$delete_disabled_triggerid,
			'status'	=> TRIGGER_STATUS_DISABLED
		]);
		$this->assertEquals(self::$delete_disabled_triggerid, $response['result']['triggerids'][0]);

		$response = $this->call('host.update', [
			'hostid'	=> self::$delete_disabled_hostid,
			'status'	=> HOST_STATUS_NOT_MONITORED
		]);
		$this->assertEquals(self::$delete_disabled_hostid, $response['result']['hostids'][0]);

		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOSTNAME_LIFETIME, self::DRULE_LIFETIME_DEFAULT, ['data' => []]);
		$this->sendSenderValue(self::HOSTNAME_LIFETIME, self::DRULE_LIFETIME_NEVER, ['data' => []]);
		$this->sendSenderValue(self::HOSTNAME_LIFETIME, self::DRULE_LIFETIME_DELETE, ['data' => []]);
		$this->sendSenderValue(self::HOSTNAME_LIFETIME, self::DRULE_LIFETIME_DISABLE_AFTER, ['data' => []]);
		$this->sendSenderValue(self::HOSTNAME_LIFETIME, self::DRULE_LIFETIME_DELETE_DISABLED, ['data' => []]);

		// Check items

		$response = $this->call('item.get', [
			'hostids'	=> self::$lifetime_hostid,
			'output'	=> ['name', 'status'],
			'sortfield'	=> 'name'
		]);
		$this->assertCount(4, $response['result']);

		$item = $response['result'][0];
		$this->assertEquals(self::DRULE_LIFETIME_DEFAULT.'_i_'.self::LLD_DATA_MACRO_VALUE, $item['name']);
		$this->assertEquals(ITEM_STATUS_DISABLED, $item['status']);

		$item = $response['result'][1];
		$this->assertEquals(self::DRULE_LIFETIME_NEVER.'_i_'.self::LLD_DATA_MACRO_VALUE, $item['name']);
		$this->assertEquals(ITEM_STATUS_ACTIVE, $item['status']);

		$item = $response['result'][2];
		$this->assertEquals(self::DRULE_LIFETIME_DISABLE_AFTER.'_i_'.self::LLD_DATA_MACRO_VALUE, $item['name']);
		$this->assertEquals(ITEM_STATUS_ACTIVE, $item['status']);

		$item = $response['result'][3];
		$this->assertEquals(self::DRULE_LIFETIME_DELETE_DISABLED.'_i_'.self::LLD_DATA_MACRO_VALUE, $item['name']);
		$this->assertEquals(ITEM_STATUS_DISABLED, $item['status']);

		// Check triggers (deleted due to item deletion)

		$response = $this->call('trigger.get', [
			'hostids'	=> self::$lifetime_hostid,
			'output'	=> ['description', 'status'],
			'sortfield'	=> 'description'
		]);
		$this->assertCount(4, $response['result']);

		$trigger = $response['result'][0];
		$this->assertEquals(self::DRULE_LIFETIME_DEFAULT.'_t_'.self::LLD_DATA_MACRO_VALUE, $trigger['description']);
		$this->assertEquals(TRIGGER_STATUS_DISABLED, $trigger['status']);

		$trigger = $response['result'][1];
		$this->assertEquals(self::DRULE_LIFETIME_NEVER.'_t_'.self::LLD_DATA_MACRO_VALUE, $trigger['description']);
		$this->assertEquals(TRIGGER_STATUS_ENABLED, $trigger['status']);

		$trigger = $response['result'][2];
		$this->assertEquals(self::DRULE_LIFETIME_DISABLE_AFTER.'_t_'.self::LLD_DATA_MACRO_VALUE, $trigger['description']);
		$this->assertEquals(TRIGGER_STATUS_ENABLED, $trigger['status']);

		$trigger = $response['result'][3];
		$this->assertEquals(self::DRULE_LIFETIME_DELETE_DISABLED.'_t_'.self::LLD_DATA_MACRO_VALUE, $trigger['description']);
		$this->assertEquals(TRIGGER_STATUS_DISABLED, $trigger['status']);

		// Check graphs (deleted due to item deletion)

		$response = $this->call('graph.get', [
			'hostids'	=> self::$lifetime_hostid,
			'output'	=> ['name'],
			'sortfield'	=> 'name'
		]);
		$this->assertCount(3, $response['result']);

		$graph = $response['result'][0];
		$this->assertEquals(self::DRULE_LIFETIME_DEFAULT.'_g_'.self::LLD_DATA_MACRO_VALUE, $graph['name']);

		$graph = $response['result'][1];
		$this->assertEquals(self::DRULE_LIFETIME_NEVER.'_g_'.self::LLD_DATA_MACRO_VALUE, $graph['name']);

		$graph = $response['result'][2];
		$this->assertEquals(self::DRULE_LIFETIME_DISABLE_AFTER.'_g_'.self::LLD_DATA_MACRO_VALUE, $graph['name']);

		// Check hosts

		$response = $this->call('host.get', [
			'output' => ['host', 'status'],
			'sortfield' => 'host',
			'filter' => [
				'host' => [
						self::DRULE_LIFETIME_DEFAULT.'_h_'.self::LLD_DATA_MACRO_VALUE,
						self::DRULE_LIFETIME_NEVER.'_h_'.self::LLD_DATA_MACRO_VALUE,
						self::DRULE_LIFETIME_DELETE.'_h_'.self::LLD_DATA_MACRO_VALUE,
						self::DRULE_LIFETIME_DISABLE_AFTER.'_h_'.self::LLD_DATA_MACRO_VALUE,
						self::DRULE_LIFETIME_DELETE_DISABLED.'_h_'.self::LLD_DATA_MACRO_VALUE
				]
			]
		]);
		$this->assertCount(4, $response['result']);

		$host = $response['result'][0];
		$this->assertEquals(self::DRULE_LIFETIME_DEFAULT.'_h_'.self::LLD_DATA_MACRO_VALUE, $host['host']);
		$this->assertEquals(HOST_STATUS_NOT_MONITORED, $host['status']);

		$host = $response['result'][1];
		$this->assertEquals(self::DRULE_LIFETIME_NEVER.'_h_'.self::LLD_DATA_MACRO_VALUE, $host['host']);
		$this->assertEquals(HOST_STATUS_MONITORED, $host['status']);

		$host = $response['result'][2];
		$this->assertEquals(self::DRULE_LIFETIME_DISABLE_AFTER.'_h_'.self::LLD_DATA_MACRO_VALUE, $host['host']);
		$this->assertEquals(HOST_STATUS_MONITORED, $host['status']);

		$host = $response['result'][3];
		$this->assertEquals(self::DRULE_LIFETIME_DELETE_DISABLED.'_h_'.self::LLD_DATA_MACRO_VALUE, $host['host']);
		$this->assertEquals(HOST_STATUS_NOT_MONITORED, $host['status']);

		// Check host groups
		$response = $this->call('hostgroup.get', [
			'output' => ['name'],
			'sortfield' => 'name',
			'filter' => [
				'name' => [
						self::DRULE_LIFETIME_DEFAULT.'_hg_'.self::LLD_DATA_MACRO_VALUE,
						self::DRULE_LIFETIME_NEVER.'_hg_'.self::LLD_DATA_MACRO_VALUE,
						self::DRULE_LIFETIME_DELETE.'_hg_'.self::LLD_DATA_MACRO_VALUE,
						self::DRULE_LIFETIME_DISABLE_AFTER.'_hg_'.self::LLD_DATA_MACRO_VALUE,
						self::DRULE_LIFETIME_DELETE_DISABLED.'_hg_'.self::LLD_DATA_MACRO_VALUE
				]
			]
		]);
		$this->assertCount(3, $response['result']);

		$hostgroup = $response['result'][0];
		$this->assertEquals(self::DRULE_LIFETIME_DEFAULT.'_hg_'.self::LLD_DATA_MACRO_VALUE, $hostgroup['name']);

		$hostgroup = $response['result'][1];
		$this->assertEquals(self::DRULE_LIFETIME_NEVER.'_hg_'.self::LLD_DATA_MACRO_VALUE, $hostgroup['name']);

		$hostgroup = $response['result'][2];
		$this->assertEquals(self::DRULE_LIFETIME_DISABLE_AFTER.'_hg_'.self::LLD_DATA_MACRO_VALUE, $hostgroup['name']);
	}

	/**
	 * Test re-discovery by checking object status.
	 *
	 * @depends testLowLevelDiscovery_LooseObjects
	 */
	public function testLowLevelDiscovery_RediscoverObjects() {
		// Disable item, trigger and host

		$response = $this->call('item.update', [
			'itemid'	=> self::$disable_itemid,
			'status'	=> ITEM_STATUS_DISABLED
		]);
		$this->assertEquals(self::$disable_itemid, $response['result']['itemids'][0]);

		$response = $this->call('trigger.update', [
			'triggerid'	=> self::$disable_triggerid,
			'status'	=> TRIGGER_STATUS_DISABLED
		]);
		$this->assertEquals(self::$disable_triggerid, $response['result']['triggerids'][0]);

		$response = $this->call('host.update', [
			'hostid'	=> self::$disable_hostid,
			'status'	=> HOST_STATUS_NOT_MONITORED
		]);
		$this->assertEquals(self::$disable_hostid, $response['result']['hostids'][0]);

		$this->reloadConfigurationCache();

		$lld_data[] = [self::LLD_DATA_MACRO => self::LLD_DATA_MACRO_VALUE];

		$this->sendSenderValue(self::HOSTNAME_LIFETIME, self::DRULE_LIFETIME_DEFAULT, ['data' => $lld_data]);
		$this->sendSenderValue(self::HOSTNAME_LIFETIME, self::DRULE_LIFETIME_DISABLE_AFTER, ['data' => $lld_data]);

		// Check items

		$response = $this->call('item.get', [
			'hostids'	=> self::$lifetime_hostid,
			'output'	=> ['name', 'status'],
			'sortfield'	=> 'name',
			'filter' => [
				'name' => [
						self::DRULE_LIFETIME_DEFAULT.'_i_'.self::LLD_DATA_MACRO_VALUE,
						self::DRULE_LIFETIME_DISABLE_AFTER.'_i_'.self::LLD_DATA_MACRO_VALUE
				]
			]
		]);
		$this->assertCount(2, $response['result']);

		$item = $response['result'][0];
		$this->assertEquals(self::DRULE_LIFETIME_DEFAULT.'_i_'.self::LLD_DATA_MACRO_VALUE, $item['name']);
		$this->assertEquals(ITEM_STATUS_ACTIVE, $item['status']);

		$item = $response['result'][1];
		$this->assertEquals(self::DRULE_LIFETIME_DISABLE_AFTER.'_i_'.self::LLD_DATA_MACRO_VALUE, $item['name']);
		$this->assertEquals(ITEM_STATUS_DISABLED, $item['status']);

		// Check triggers (deleted due to item deletion)

		$response = $this->call('trigger.get', [
			'hostids'	=> self::$lifetime_hostid,
			'output'	=> ['description', 'status'],
			'sortfield'	=> 'description',
			'filter' => [
				'description' => [
						self::DRULE_LIFETIME_DEFAULT.'_t_'.self::LLD_DATA_MACRO_VALUE,
						self::DRULE_LIFETIME_DISABLE_AFTER.'_t_'.self::LLD_DATA_MACRO_VALUE
				]
			]
		]);
		$this->assertCount(2, $response['result']);

		$trigger = $response['result'][0];
		$this->assertEquals(self::DRULE_LIFETIME_DEFAULT.'_t_'.self::LLD_DATA_MACRO_VALUE, $trigger['description']);
		$this->assertEquals(TRIGGER_STATUS_ENABLED, $trigger['status']);

		$trigger = $response['result'][1];
		$this->assertEquals(self::DRULE_LIFETIME_DISABLE_AFTER.'_t_'.self::LLD_DATA_MACRO_VALUE, $trigger['description']);
		$this->assertEquals(TRIGGER_STATUS_DISABLED, $trigger['status']);

		// Check hosts

		$response = $this->call('host.get', [
			'output' => ['host', 'status'],
			'sortfield' => 'host',
			'filter' => [
				'host' => [
						self::DRULE_LIFETIME_DEFAULT.'_h_'.self::LLD_DATA_MACRO_VALUE,
						self::DRULE_LIFETIME_DISABLE_AFTER.'_h_'.self::LLD_DATA_MACRO_VALUE
				]
			]
		]);
		$this->assertCount(2, $response['result']);

		$host = $response['result'][0];
		$this->assertEquals(self::DRULE_LIFETIME_DEFAULT.'_h_'.self::LLD_DATA_MACRO_VALUE, $host['host']);
		$this->assertEquals(HOST_STATUS_MONITORED, $host['status']);

		$host = $response['result'][1];
		$this->assertEquals(self::DRULE_LIFETIME_DISABLE_AFTER.'_h_'.self::LLD_DATA_MACRO_VALUE, $host['host']);
		$this->assertEquals(HOST_STATUS_NOT_MONITORED, $host['status']);
	}
}

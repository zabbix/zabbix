<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__) . '/../include/CIntegrationTest.php';

/**
 * Test suite for alerting for services.
 *
 * @required-components server, proxy
 * @configurationDataProvider configurationProvider
 * @backup config, functions, globalmacro
 * @backup group_prototype, host_discovery, host_inventory, hostmacro, host_rtdata, hosts, hosts_groups, hosts_templates
 * @backup hstgrp, interface, item_condition, item_discovery, item_parameter, item_preproc, item_rtdata, items
 * @backup lld_macro_path, lld_override, lld_override_condition, lld_override_opdiscover, lld_override_operation
 * @backup lld_override_opstatus, proxy, proxy_rtdata, auditlog, changelog, proxy_history, config_autoreg_tls
 * @backup expressions, ha_node, regexps
 */
class testProxyConfSync extends CIntegrationTest
{
	private $expected_initial = [
		[
			'config' =>
			[
				'insert' => '1',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'autoreg' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'autoreghost' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'hosts' =>
			[
				'insert' => '15',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'host_invent' =>
			[
				'insert' => '3',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'templates' =>
			[
				'insert' => '4',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'globmacros' =>
			[
				'insert' => '3',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'hostmacros' =>
			[
				'insert' => '5',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'interfaces' =>
			[
				'insert' => '15',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'items' =>
			[
				'insert' => '78',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'template_items' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'prototype_items' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'item_discovery' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'triggers' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'trigdeps' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'trigtags' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'hosttags' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'itemtags' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'functions' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'expressions' =>
			[
				'insert' => '1',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'actions' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'operations' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'conditions' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'corr' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'corr_cond' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'corr_op' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'hgroups' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'itempproc' =>
			[
				'insert' => '11',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'itemscriptparam' =>
			[
				'insert' => '2',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'maintenance' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'drules' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'httptests' =>
			[
				'insert' => '3',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'connector' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'connector_tag' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'proxy' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		]
	];

	private $expected_update =
	[
		[
			"config" =>
			[
				"insert" =>
				"1",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"autoreg" =>
			[
				"insert" =>
				"0",
				"update" =>
				"1",
				"delete" =>
				"0"
			]
		],
		[
			'autoreghost' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			"hosts" =>
			[
				"insert" =>
				"0",
				"update" =>
				"15",
				"delete" =>
				"0"
			]
		],
		[
			"host_invent" =>
			[
				"insert" =>
				"0",
				"update" =>
				"1",
				"delete" =>
				"2"
			]
		],
		[
			"templates" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"globmacros" =>
			[
				"insert" =>
				"0",
				"update" =>
				"3",
				"delete" =>
				"0"
			]
		],
		[
			"hostmacros" =>
			[
				"insert" =>
				"2",
				"update" =>
				"2",
				"delete" =>
				"2"
			]
		],
		[
			"interfaces" =>
			[
				"insert" =>
				"0",
				"update" =>
				"8",
				"delete" =>
				"0"
			]
		],
		[
			"items" =>
			[
				"insert" =>
				"0",
				"update" =>
				"49",
				"delete" =>
				"0"
			]
		],
		[
			"template_items" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"prototype_items" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"item_discovery" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"triggers" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"trigdeps" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"trigtags" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"hosttags" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"itemtags" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"functions" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"expressions" =>
			[
				"insert" =>
				"1",
				"update" =>
				"0",
				"delete" =>
				"1"
			]
		],
		[
			"actions" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"operations" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"conditions" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"corr" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"corr_cond" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"corr_op" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"hgroups" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"itempproc" =>
			[
				"insert" =>
				"0",
				"update" =>
				"6",
				"delete" =>
				"0"
			]
		],
		[
			"itemscriptparam" =>
			[
				"insert" =>
				"0",
				"update" =>
				"1",
				"delete" =>
				"0"
			]
		],
		[
			"maintenance" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"drules" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"httptests" =>
			[
				"insert" =>
				"0",
				"update" =>
				"3",
				"delete" =>
				"0"
			]
		],
		[
			'connector' =>
			[
				'insert' =>
				'0',
				'update' =>
				'0',
				'delete' =>
				'0'
			]
		],
		[
			'connector_tag' =>
			[
				'insert' =>
				'0',
				'update' =>
				'0',
				'delete' =>
				'0'
			]
		],
		[
			'proxy' =>
			[
				'insert' =>
				'0',
				'update' =>
				'0',
				'delete' =>
				'0'
			]
		]
	];

	private $expected_delete = [
		[
			"config" =>
			[
				"insert" =>
				"1",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"autoreg" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			'autoreghost' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			"hosts" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"15"
			]
		],
		[
			"host_invent" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"1"
			]
		],
		[
			"templates" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"4"
			]
		],
		[
			"globmacros" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"hostmacros" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"5"
			]
		],
		[
			"interfaces" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"15"
			]
		],
		[
			"items" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"78"
			]
		],
		[
			"template_items" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"prototype_items" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"78"
			]
		],
		[
			"item_discovery" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"triggers" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"trigdeps" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"trigtags" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"hosttags" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"itemtags" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"functions" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"expressions" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"1"
			]
		],
		[
			"actions" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"operations" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"conditions" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"corr" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"corr_cond" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"corr_op" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"hgroups" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			"itempproc" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"11"
			]
		],
		[
			"itemscriptparam" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"2"
			]
		],
		[
			"maintenance" =>
			[
				"insert" =>
				"0",
				"update" =>
				"0",
				"delete" =>
				"0"
			]
		],
		[
			'drules' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'httptests' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '3'
			]
		],
		[
			'connector' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'connector_tag' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		],
		[
			'proxy' =>
			[
				'insert' => '0',
				'update' => '0',
				'delete' => '0'
			]
		]
	];

	private static $regexpid;
	private static $vaultmacroid;
	private static $secretmacroid;
	private static $tlshostid;

	/**
	 * @inheritdoc
	 */
	public function prepareData()
	{
		$this->purgeExisting('host', 'hostids');
		$this->purgeExisting('proxy', 'extend');
		$this->purgeExisting('template', 'templateids');
		$this->purgeExisting('item', 'itemids');
		$this->purgeExisting('trigger', 'triggerids');
		$this->purgeExisting('regexp', 'extend');
		$this->purgeHostGroups();
		$this->purgeGlobalMacros();

		return true;
	}

	public function createProxy()
	{
		$response = $this->call('proxy.create', [
			'name' => 'Proxy',
			'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
			'hosts' => [],
			'address' => '127.0.0.1',
			'port' => PHPUNIT_PORT_PREFIX.self::PROXY_PORT_SUFFIX
		]);
		$this->assertArrayHasKey("proxyids", $response['result']);
	}

	/**
	 * Component configuration provider for server related tests.
	 *
	 * @return array
	 */
	public function configurationProvider()
	{
		return [
			self::COMPONENT_SERVER => [
				'LogFileSize' => 0,
				'DebugLevel' => 5,
				'Vault' => 'CyberArk',
				'VaultURL' => 'https://127.0.0.1:1858'
			],
			self::COMPONENT_PROXY => [
				'ProxyMode' => PROXY_OPERATING_MODE_PASSIVE,
				'DebugLevel' => 5,
				'LogFileSize' => 0,
				'Hostname' => 'Proxy',
				'ListenPort' => PHPUNIT_PORT_PREFIX.self::PROXY_PORT_SUFFIX
			]
		];
	}

	private function parseSyncResults()
	{
		$log = file_get_contents(self::getLogPath(self::COMPONENT_PROXY));
		$data = explode("\n", $log);

		$sync_lines = preg_grep('/zbx_dc_sync_configuration.*\([0-9]+\/[0-9]+\/[0-9]+\)\.$/', $data);

		$sync_lines1 = preg_replace(
			[
				"/^\s*[0-9]+:[0-9]+:[0-9]+\.[0-9]+ zbx_dc_sync_configuration\(\) /",
				"/\s+/",
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

			$pair = [
				$subject => [
					'insert' => $operations[0],
					'update' => $operations[1],
					'delete' => $operations[2]
				]
			];

			$exists = false;

			foreach ($results as &$existing_obj) {
				if (array_key_exists($subject, $existing_obj)) {
					$exists = true;
					$existing_obj = $pair;
					break;
				}
			}

			if (!$exists)
				array_push($results, $pair);
		}

		return $results;
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

	private function createGlobalMacros()
	{
		$response = $this->call('usermacro.createglobal', [
			'macro' => '{$GLOBDELAY}',
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
			'tls_psk' => '12111595725ac58dd977beef14b97461a7c1045b9a1c923453302c5473193478'
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


	public function loadInitialConfiguration()
	{
		$this->createRegexp();
		$this->createGlobalMacros();
		$this->importTemplate('confsync_proxy_tmpl.xml');

		$xml = file_get_contents('integration/data/confsync_proxy_hosts.xml');

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

		$this->setupTlsForHost();
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

	/**
	 * @required-components server, proxy
	 */
	public function testProxyConfSync_Insert()
	{
		self::stopComponent(self::COMPONENT_SERVER);
		self::clearLog(self::COMPONENT_SERVER);
		self::stopComponent(self::COMPONENT_PROXY);
		self::clearLog(self::COMPONENT_PROXY);

		$this->purgeExisting('host', 'hostids');
		$this->purgeExisting('proxy', 'extend');
		$this->purgeExisting('template', 'templateids');
		$this->purgeExisting('item', 'itemids');
		$this->purgeExisting('trigger', 'triggerids');
		$this->purgeExisting('regexp', 'extend');
		$this->purgeHostGroups();
		$this->purgeGlobalMacros();
		$this->createProxy();

		self::startComponent(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of zbx_dc_sync_configuration()", true, 30, 1);
		self::startComponent(self::COMPONENT_PROXY);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'sending configuration data to proxy "Proxy"', true, 90, 1);
		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, "received configuration data from server", true, 90, 1);
		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, "memory statistics for configuration cache", true, 90, 1);

		self::stopComponent(self::COMPONENT_SERVER);
		self::clearLog(self::COMPONENT_SERVER);
		self::stopComponent(self::COMPONENT_PROXY);
		self::clearLog(self::COMPONENT_PROXY);

		$this->loadInitialConfiguration();

		self::startComponent(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of zbx_dc_sync_configuration()", true, 30, 1);
		self::startComponent(self::COMPONENT_PROXY);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'sending configuration data to proxy "Proxy"', true, 90, 1);
		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, "received configuration data from server", true, 90, 1);
		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, "memory statistics for configuration cache", true, 90, 1);

		$got = $this->parseSyncResults();
		$this->assertEquals($this->expected_initial, $got);

		return true;
	}

	public function testProxyConfSync_Update()
	{
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'sending configuration data to proxy "Proxy"', true, 90, 1);
		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, "received configuration data from server", true, 90, 1);
		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, "memory statistics for configuration cache", true, 90, 1);

		self::stopComponent(self::COMPONENT_SERVER);
		self::clearLog(self::COMPONENT_SERVER);
		self::clearLog(self::COMPONENT_PROXY);

		$this->updateRegexp();
		$this->updateAutoregistration();

		$this->importTemplateForUpdate('confsync_proxy_tmpl_updated.xml');
		$xml = file_get_contents('integration/data/confsync_proxy_hosts_updated.xml');

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
		$this->updateTlsForHost();
		$this->disableAllHosts();

		self::startComponent(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of zbx_dc_sync_configuration()", true, 30, 1);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'sending configuration data to proxy "Proxy"', true, 90, 1);

		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, "received configuration data from server", true, 90, 1);
		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, "memory statistics for configuration cache", true, 90, 1);

		$got = $this->parseSyncResults();

		// Count of updated interfaces can vary due to errors being treated as configuration changes
		foreach ($got as &$obj) {
			if (array_key_exists('interfaces', $obj)) {
				if ($obj['interfaces']['update'] > 8)
					$obj['interfaces']['update'] = 8;
			}
		}
		$this->assertEquals($this->expected_update, $got);
		return true;
	}

	public function testProxyConfSync_Delete()
	{
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'sending configuration data to proxy "Proxy"', true, 90, 1);
		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, "received configuration data from server", true, 90, 1);
		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, "memory statistics for configuration cache", true, 90, 1);

		self::stopComponent(self::COMPONENT_SERVER);
		self::clearLog(self::COMPONENT_SERVER);
		self::clearLog(self::COMPONENT_PROXY);

		$this->purgeExisting('maintenance', 'maintenanceids');
		$this->purgeExisting('host', 'hostids');
		$this->purgeExisting('template', 'templateids');
		$this->purgeExisting('correlation', 'correlationids');
		$this->purgeExisting('regexp', 'extend');
		$this->purgeExisting('item', 'itemids');
		$this->purgeHostGroups();

		self::startComponent(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of zbx_dc_sync_configuration()", true, 30, 1);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'sending configuration data to proxy "Proxy"', true, 90, 1);

		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, "received configuration data from server", true, 90, 1);
		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, "memory statistics for configuration cache", true, 90, 1);

		$got = $this->parseSyncResults();
		$this->assertEquals($this->expected_delete, $got);

		return true;
	}
}

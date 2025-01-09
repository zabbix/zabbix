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
 * Test suite for action notifications
 *
 * @required-components server
 * @configurationDataProvider defaultConfigurationProvider
 * @backup items,hosts,proxy,history_text
 * @hosts test_actions
 */
class testItemTimeouts extends CIntegrationTest {

	const HOSTNAME_AGENT = "agent_host_timeouts";
	const HOSTNAME_AGENT2 = "agent2_host_timeouts";
	const HOSTNAME_SNMP = "snmp_host_timeouts";
	const HOSTNAME_SSH = "ssh_host_timeouts";
	const HOSTNAME_SIMPLE = "simple_host_timeouts";

	private static $hostids;
	private static $itemids;

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "test_host"
		$response = $this->call('host.create', [
			[
				'host' => self::HOSTNAME_AGENT,
				'interfaces' => [
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED
			],
			[
				'host' => self::HOSTNAME_AGENT2,
				'interfaces' => [
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT2, 'ListenPort')
				],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);

		self::$hostids[self::HOSTNAME_AGENT] = $response['result']['hostids'][0];
		self::$hostids[self::HOSTNAME_AGENT2] = $response['result']['hostids'][1];

		// Get host interface ids.
		$response = $this->call('host.get', [
			'output' => ['host'],
			'hostids' => [
				self::$hostids[self::HOSTNAME_AGENT],
				self::$hostids[self::HOSTNAME_AGENT2]
			],
			'selectInterfaces' => ['interfaceid'],
			'sortfield' => 'hostid'
		]);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('interfaces', $response['result'][0]);
		$this->assertTrue(count($response['result']) == 2);
		$agent_interfaceid = $response['result'][0]['interfaces'][0]['interfaceid'];
		$agent2_interfaceid = $response['result'][1]['interfaces'][0]['interfaceid'];

		$response = $this->call('host.create', [
			[
				'host' => self::HOSTNAME_SSH,
				'interfaces' => [],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_NOT_MONITORED
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostids[self::HOSTNAME_SSH] = $response['result']['hostids'][0];

		$response = $this->call('host.create', [
			[
				'host' => self::HOSTNAME_SNMP,
				'interfaces' => [
					'type' => INTERFACE_TYPE_SNMP,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => '10987',
					'details' => [
						'version' => 3,
						'bulk' => 0,
						'securityname' => '',
						'contextname' => '',
						'securitylevel' => 0
					]
				],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_NOT_MONITORED
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostids[self::HOSTNAME_SNMP] = $response['result']['hostids'][0];

		$response = $this->call('host.create', [
			[
				'host' => self::HOSTNAME_SIMPLE,
				'interfaces' => [],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_NOT_MONITORED
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostids[self::HOSTNAME_SIMPLE] = $response['result']['hostids'][0];

		// Get host interface ids.
		$response = $this->call('host.get', [
			'output' => ['host'],
			'hostids' => self::$hostids[self::HOSTNAME_SNMP],
			'selectInterfaces' => ['interfaceid']
		]);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('interfaces', $response['result'][0]);
		$this->assertArrayHasKey(0, $response['result'][0]['interfaces']);
		$snmp_interfaceid = $response['result'][0]['interfaces'][0]['interfaceid'];

		$items = [
			[
				'hostid' => self::$hostids[self::HOSTNAME_AGENT],
				'name' => "userparam.test",
				'key_' => 'userparam.test',
				'interfaceid' => $agent_interfaceid,
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'delay' => '1m',
				'timeout' => '10s'
			],
			[
				'hostid' => self::$hostids[self::HOSTNAME_AGENT2],
				'name' => "userparam.test",
				'key_' => 'userparam.test',
				'interfaceid' => $agent2_interfaceid,
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'delay' => '1m',
				'timeout' => '10s'
			],
			[
				'hostid' => self::$hostids[self::HOSTNAME_AGENT],
				'name' => "system.run",
				'key_' => 'system.run[sleep 5 && echo ok]',
				'interfaceid' => $agent_interfaceid,
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'delay' => '1m',
				'timeout' => '10s'
			],
			[
				'hostid' => self::$hostids[self::HOSTNAME_AGENT2],
				'name' => "system.run",
				'key_' => 'system.run[sleep 5 && echo ok]',
				'interfaceid' => $agent2_interfaceid,
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'delay' => '1m',
				'timeout' => '10s'
			],
			[
				'hostid' => self::$hostids[self::HOSTNAME_AGENT],
				'name' => "userparam.test.active",
				'key_' => 'userparam.test.active',
				'interfaceid' => $agent_interfaceid,
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'delay' => '1m',
				'timeout' => '10s'
			],
			[
				'hostid' => self::$hostids[self::HOSTNAME_AGENT2],
				'name' => "userparam.test.active",
				'key_' => 'userparam.test.active',
				'interfaceid' => $agent2_interfaceid,
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'delay' => '1m',
				'timeout' => '10s'
			],
			[
				'hostid' => self::$hostids[self::HOSTNAME_AGENT],
				'name' => "test_external.sh",
				'key_' => 'test_external.sh',
				'interfaceid' => $agent_interfaceid,
				'type' => ITEM_TYPE_EXTERNAL,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'delay' => '1m',
				'timeout' => '10s'
			],
			[
				'hostid' => self::$hostids[self::HOSTNAME_SNMP],
				'name' => "snmp",
				'key_' => 'snmp',
				'snmp_oid' => 'walk[1.3.6.1.1]',
				'interfaceid' => $snmp_interfaceid,
				'type' => ITEM_TYPE_SNMP,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'delay' => '10s',
				'timeout' => '6s'
			],
			[
				'hostid' => self::$hostids[self::HOSTNAME_SSH],
				'name' => "ssh",
				'key_' => 'ssh.run[test,192.168.9.25,822]',
				'authtype' => 0,
				'password' => 'user12345',
				'username' => 'user12345',
				'params' => 'echo ok',
				'type' => ITEM_TYPE_SSH,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'delay' => '10s',
				'timeout' => '6s'
			],
			[
				'hostid' => self::$hostids[self::HOSTNAME_SIMPLE],
				'name' => "simple",
				'key_' => 'net.tcp.service[http,192.168.9.25,1]',
				'type' => ITEM_TYPE_SIMPLE,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'delay' => '10s',
				'timeout' => '6s'
			]
		];

		foreach ($items as $item) {
			$response = $this->call('item.create', $item);
			$this->assertArrayHasKey('itemids', $response['result']);
			$this->assertEquals(1, count($response['result']['itemids']));
			self::$itemids[$item['name']] = $response['result']['itemids'][0];
		}

		$external_script_data = <<<HEREDOC
		#!/bin/bash
		sleep 6
		echo ok
		HEREDOC;

		$this->assertTrue(@file_put_contents('/tmp/test_external.sh', $external_script_data) !== false); // TODO: const
		$this->assertTrue(@chmod('/tmp/test_external.sh', 0755) !== false); // TODO: const

		return true;
	}

	public function proxyConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 5,
				'LogFileSize' => 0
			],
			self::COMPONENT_PROXY => [
				'DebugLevel' => 5,
				'LogFileSize' => 0,
				'Hostname' => 'Proxy',
				'ProxyMode' => 1
			]
		];
	}

	public function defaultConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'ExternalScripts' => '/tmp'
			],
			self::COMPONENT_AGENT => [
				'Hostname' => self::HOSTNAME_AGENT,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER,
						'ListenPort', 10051),
				'AllowKey' => 'system.run[*]',
				'LogRemoteCommands' => 1,
				'UnsafeUserParameters' => 1,
				'UserParameter' => [
					'userparam.test,sleep 5 && echo ok',
					'userparam.test.active,sleep 5 && echo ok'
				]
			],
			self::COMPONENT_AGENT2 => [
				'Hostname' => self::HOSTNAME_AGENT2,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER,
						'ListenPort', 10051),
				'AllowKey' => 'system.run[*]',
				'UnsafeUserParameters' => 1,
				'UserParameter' => [
					'userparam.test,sleep 5 && echo ok',
					'userparam.test.active,sleep 5 && echo ok'
				]
			]
		];
	}

	public function serverConfigurationProviderTrace() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 5,
				'LogFileSize' => 0,
				'Timeout' => 30
			]
		];
	}

	private function extractSyncedTimeouts($initial_timeouts, $component) {
		$log = file_get_contents(self::getLogPath($component));
		$data = explode("\n", $log);
		$sync = preg_grep('/item timeouts:/', $data);
		$this->assertTrue(count($sync) > 0);

		/* We cannot just take $lines_expected number of lines after 'item timeouts' in log. */
		/* Another process may start writing into log here. So, we must filter lines by pid. */
		$pid = strtok(array_values($sync)[0], ':');
		$sync_idx = array_keys($sync)[0];
		$lines_expected = count($initial_timeouts);

		$synced_timeouts = array();
		for ($x = $sync_idx + 1; $x < count($data); $x++) {
			if (count($synced_timeouts) == $lines_expected) {
				break;
			}
			if (preg_match('/^'.$pid.'/', $data[$x])) {
				array_push($synced_timeouts, $data[$x]);
			}
		}

		$synced_timeouts2 = preg_replace("/^\s*[0-9]+:[0-9]+:[0-9]+\.[0-9]+\s+/", "", $synced_timeouts);

		$pairs = [];
		foreach ($synced_timeouts2 as $v) {
			$p = explode(":", $v);
			$name = $p[0];
			$value = $p[1];
			$pairs["timeout_" . $name] = $value;
		}

		return $pairs;
	}

	/**
	 * Test if both active and passive agent checks are processed.
	 *
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProviderTrace
	 * @backup config
	 */
	public function testItemTimeouts_checkConfigSync() {
		self::stopComponent(self::COMPONENT_SERVER);

		$this->clearLog(self::COMPONENT_SERVER);

		$initial_timeouts = [
			'timeout_zabbix_agent' => '4s',
			'timeout_simple_check' => '5s',
			'timeout_snmp_agent' => '6s',
			'timeout_external_check' => '7s',
			'timeout_db_monitor' => '8s',
			'timeout_http_agent' => '9s',
			'timeout_ssh_agent' => '10s',
			'timeout_telnet_agent' => '11s',
			'timeout_script' => '12s'
		];

		$response = $this->call('settings.update', $initial_timeouts);
		$this->assertEquals(count($initial_timeouts), count($response['result']));

		self::startComponent(self::COMPONENT_SERVER);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of DCdump_config()", true, 30, 1);

		$synced_timeouts = $this->extractSyncedTimeouts($initial_timeouts, self::COMPONENT_SERVER);
		$this->assertEqualsCanonicalizing($synced_timeouts, $initial_timeouts);

		$updated_timeouts = [
			'timeout_zabbix_agent' => '1m',
			'timeout_simple_check' => '1m',
			'timeout_snmp_agent' => '1m',
			'timeout_external_check' => '1m',
			'timeout_db_monitor' => '1m',
			'timeout_http_agent' => '1m',
			'timeout_ssh_agent' => '2m',
			'timeout_telnet_agent' => '2m',
			'timeout_script' => '2m'
		];

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$response = $this->call('settings.update', $updated_timeouts);
		$this->assertEquals(count($updated_timeouts), count($response['result']));

		$this->clearLog(self::COMPONENT_SERVER);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of DCdump_config()", true, 30, 1);

		$synced_timeouts = $this->extractSyncedTimeouts($updated_timeouts, self::COMPONENT_SERVER);
		$this->assertEqualsCanonicalizing($synced_timeouts, $updated_timeouts);

		return true;
	}

	/**
	 * Test timeouts for various agent checks and external check.
	 *
	 * @required-components server, agent, agent2
	 * @configurationDataProvider defaultConfigurationProvider
	 * @backup config, history_text, items, item_rtdata
	 */
	public function testItemTimeouts_checkTimeouts() {
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of zbx_dc_sync_configuration()", true, 30, 1);

		foreach (self::$itemids as $name => $id) {
			if (in_array($name, ['userparam.test.active', 'snmp', 'ssh', 'simple']))
				continue;

			$response = $this->call('task.create', [
				'type' => ZBX_TM_TASK_CHECK_NOW,
				'request' => [
					'itemid' => $id
				]
			]);
		}

		foreach (self::$itemids as $name => $id) {
			if (in_array($name, ['snmp', 'ssh', 'simple']))
				continue;

			$response = $this->callUntilDataIsPresent('history.get', [
				'output' => ['value'],
				'itemids' => [$id],
				'history' => ITEM_VALUE_TYPE_TEXT,
				'sortorder' => 'DESC',
				'sortfield' => 'clock',
				'limit' => 1
			], 30, 2);
		}

		return true;
	}

	/**
	 * Test timeout for SNMP check.
	 *
	 * @required-components server
	 * @configurationDataProvider defaultConfigurationProvider
	 * @backup config, hosts, items, item_rtdata
	 */
	public function testItemTimeouts_checkSnmp() {
		self::stopComponent(self::COMPONENT_SERVER);
		$response = $this->call('host.update', [
			'hostid' => self::$hostids[self::HOSTNAME_SNMP],
			'status' => HOST_STATUS_MONITORED
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertEquals(1, count($response['result']['hostids']));

		self::startComponent(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of zbx_dc_sync_configuration()", true, 30, 1);
		$this->clearLog(self::COMPONENT_SERVER);

		$response = $this->call('task.create', [
			'type' => ZBX_TM_TASK_CHECK_NOW,
			'request' => [
				'itemid' => self::$itemids['snmp']
			]
		]);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "In zbx_async_check_snmp", true, 90, 1, true);
		$tm1 = time();
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of process_async_result", true, 90, 1, true);
		$tm2 = time();
		$this->assertTrue($tm2 - $tm1 <= 7 * 2);
	}

	/**
	 * Test timeout for SSH check.
	 *
	 * @required-components server
	 * @configurationDataProvider defaultConfigurationProvider
	 * @backup config, hosts, items, item_rtdata
	 */
	public function testItemTimeouts_checkSsh() {
		self::stopComponent(self::COMPONENT_SERVER);

		$response = $this->call('host.update', [
			'hostid' => self::$hostids[self::HOSTNAME_SSH],
			'status' => HOST_STATUS_MONITORED
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertEquals(1, count($response['result']['hostids']));

		self::startComponent(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of zbx_dc_sync_configuration()", true, 30, 1);
		$this->clearLog(self::COMPONENT_SERVER);

		$response = $this->call('task.create', [
			'type' => ZBX_TM_TASK_CHECK_NOW,
			'request' => [
				'itemid' => self::$itemids['ssh']
			]
		]);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "In ssh_run()", true, 90, 1, true);
		$tm1 = time();
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of ssh_run().*NOTSUPPORTED", true, 90, 1, true);
		$tm2 = time();
		$this->assertTrue($tm2 - $tm1 <= 7);

		return true;
	}

	/**
	 * Test timeout for simple check.
	 *
	 * @required-components server
	 * @configurationDataProvider defaultConfigurationProvider
	 * @backup config, hosts, items, item_rtdata
	 */
	public function testItemTimeouts_checkSimple() {
		$response = $this->call('host.update', [
			'hostid' => self::$hostids[self::HOSTNAME_SIMPLE],
			'status' => HOST_STATUS_MONITORED
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertEquals(1, count($response['result']['hostids']));
		$this->clearLog(self::COMPONENT_SERVER);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of zbx_dc_sync_configuration()", true, 30, 1);
		$this->clearLog(self::COMPONENT_SERVER);

		$response = $this->call('task.create', [
			'type' => ZBX_TM_TASK_CHECK_NOW,
			'request' => [
				'itemid' => self::$itemids['simple']
			]
		]);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "In get_value_simple()", true, 90, 1, true);
		$tm1 = time();
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of get_value_simple()", true, 90, 1, true);
		$tm2 = time();
		$this->assertTrue($tm2 - $tm1 <= 7);

		return true;
	}

	/**
	 * Test if proxy receives per-proxy timeout sets from server.
	 *
	 * @required-components server, proxy
	 * @configurationDataProvider proxyConfigurationProvider
	 */
	public function testItemTimeouts_checkProxyconfig() {
		$proxy_timeouts = [
			'timeout_zabbix_agent' => '4s',
			'timeout_simple_check' => '5s',
			'timeout_snmp_agent' => '6s',
			'timeout_external_check' => '7s',
			'timeout_db_monitor' => '8s',
			'timeout_http_agent' => '9s',
			'timeout_ssh_agent' => '10s',
			'timeout_telnet_agent' => '11s',
			'timeout_script' => '12s',
			'timeout_browser' => '13s'
		];

		$request = [
			'name' => 'Proxy',
			'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
			'hosts' => [],
			'address' => '127.0.0.1',
			'port' => PHPUNIT_PORT_PREFIX.self::PROXY_PORT_SUFFIX,
			'custom_timeouts' => 1
		];

		$response = $this->call('proxy.create', array_merge($request, $proxy_timeouts));
		$this->assertArrayHasKey("proxyids", $response['result']);
		$this->assertArrayHasKey('0', $response['result']['proxyids']);
		$proxyid = $response['result']['proxyids'][0];

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of zbx_dc_sync_configuration()", true, 30, 1);
		$this->clearLog(self::COMPONENT_PROXY);
		$this->clearLog(self::COMPONENT_SERVER);

		$this->reloadConfigurationCache(self::COMPONENT_PROXY);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'sending configuration data to proxy "Proxy"', true, 90, 1);
		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, "received configuration data from server", true, 90, 1);
		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, "End of DCdump_config()", true, 30, 1);

		$synced_timeouts = $this->extractSyncedTimeouts($proxy_timeouts, self::COMPONENT_PROXY);
		$this->assertEqualsCanonicalizing($synced_timeouts, $proxy_timeouts);

		$updated_timeouts = [
			'timeout_zabbix_agent' => '1m',
			'timeout_simple_check' => '1m',
			'timeout_snmp_agent' => '1m',
			'timeout_external_check' => '1m',
			'timeout_db_monitor' => '1m',
			'timeout_http_agent' => '1m',
			'timeout_ssh_agent' => '2m',
			'timeout_telnet_agent' => '2m',
			'timeout_script' => '2m'
		];

		$response = $this->call('proxy.update', array_merge(["proxyid" => $proxyid], $updated_timeouts));
		$this->assertArrayHasKey("proxyids", $response['result']);

		$this->clearLog(self::COMPONENT_SERVER);
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of zbx_dc_sync_configuration()", true, 30, 1);
		$this->clearLog(self::COMPONENT_PROXY);
		$this->clearLog(self::COMPONENT_SERVER);

		$this->reloadConfigurationCache(self::COMPONENT_PROXY);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'sending configuration data to proxy "Proxy"', true, 90, 1);
		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, "received configuration data from server", true, 90, 1);
		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, "End of DCdump_config()", true, 30, 1);

		$synced_timeouts2 = $this->extractSyncedTimeouts($updated_timeouts, self::COMPONENT_PROXY);

		$this->assertEqualsCanonicalizing($synced_timeouts2, $updated_timeouts);

		return true;
	}
}

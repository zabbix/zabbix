<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';

/**
 * Test suite for data comparison collected by C agent and new GO agent.
 *
 * @backup history
 */
class testAgent2BackwardCompatibility extends CIntegrationTest {

	private static $metrics = [
			'agent.ping',
			'kernel.maxfiles',
			'kernel.maxproc',
			'net.dns[,www.zabbix.com]',
			'net.dns.record[,www.zabbix.com]',
			'net.if.discovery',
			'net.tcp.listen[80]',
			'net.tcp.port[,80]',
			'net.tcp.service[http]',
			'net.udp.listen[21]',
			'net.udp.service[ntp]',
			'proc.num[zabbix_server]',
			'system.cpu.discovery',
			'system.cpu.num',
			'system.hostname',
			'system.hw.cpu',
			'system.hw.chassis',
			'system.hw.devices',
			'system.hw.macaddr',
			'system.sw.arch',
			'system.sw.os',
			'system.sw.packages',
			'system.uname',
			'system.users.num',
			'vfs.dir.count[/mnt]',
			'vfs.dir.size[/mnt]',
			'vfs.file.contents[/etc/hostname]',
			'vfs.file.exists[/etc/hostname]',
			'vfs.file.md5sum[/etc/hostname]',
			'vfs.file.size[/etc/hostname]',
			'vfs.file.time[/etc/hostname]',
			'vfs.fs.discovery',
			'web.page.regexp[localhost/invalid_link_returns_404,,,404,2]'
	];

	private static $hostids = [];
	private static $itemids = [];
	private static $itemids_go = [];

	/**
	 * @inheritdoc
	 */
	public function prepareData() {

		// Create hosts "agent" and "go_agent"
		$interfaces = [
			[
				'type' => 1,
				'main' => 1,
				'useip' => 1,
				'ip' => '127.0.0.1',
				'dns' => '',
				'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
			],
			[
				'type' => 1,
				'main' => 1,
				'useip' => 1,
				'ip' => '127.0.0.1',
				'dns' => '',
				'port' => $this->getConfigurationValue(self::COMPONENT_AGENT2, 'ListenPort')
			]
		];

		$groups = [
			[
				'groupid' => 4
			]
		];

		$response = $this->call('host.create', [
			[
				'host' => 'agent',
				'interfaces' => $interfaces[0],
				'groups' => $groups,
				'status' => HOST_STATUS_NOT_MONITORED
			],
			[
				'host' => 'go_agent',
				'interfaces' => $interfaces[1],
				'groups' => $groups,
				'status' => HOST_STATUS_NOT_MONITORED
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		foreach (['agent', 'go_agent'] as $i => $name) {
			$this->assertArrayHasKey($i, $response['result']['hostids']);
			self::$hostids[$name] = $response['result']['hostids'][$i];
		}

		// Get host interface ids.
		$response = $this->call('host.get', [
			'output' => ['host'],
			'hostids' => array_values(self::$hostids),
			'selectInterfaces' => ['interfaceid']
		]);

		$interfaceids = [];
		foreach ($response['result'] as $host) {
			$interfaceids[$host['host']] = $host['interfaces'][0]['interfaceid'];
		}

		// Create items
		self::$itemids = [];
		self::$itemids_go = [];
		foreach (self::$metrics as $metric) {
			$response = $this->call('item.create', [
					'hostid' => self::$hostids['agent'],
					'name' => $metric,
					'key_' => $metric,
					'type' => ITEM_TYPE_ZABBIX,
					'value_type' => ITEM_VALUE_TYPE_TEXT,
					'delay' => '1s',
					'interfaceid' => $interfaceids['agent']
			]);

			$this->assertArrayHasKey('itemids', $response['result']);
			$this->assertEquals(1, count($response['result']['itemids']));
			self::$itemids[$metric] = $response['result']['itemids'];

			$response = $this->call('item.create', [
					'hostid' => self::$hostids['go_agent'],
					'name' => $metric,
					'key_' => $metric,
					'type' => ITEM_TYPE_ZABBIX,
					'value_type' => ITEM_VALUE_TYPE_TEXT,
					'delay' => '1s',
					'interfaceid' => $interfaceids['go_agent']
			]);

			$this->assertArrayHasKey('itemids', $response['result']);
			$this->assertEquals(1, count($response['result']['itemids']));
			self::$itemids_go[$metric] = $response['result']['itemids'];
		}

		return true;
	}

	/**
	 * Component configuration provider for agent related tests.
	 *
	 * @return array
	 */
	public function agentConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'UnreachablePeriod'	=> 5,
				'UnavailableDelay'	=> 5,
				'UnreachableDelay'	=> 1
			],
			self::COMPONENT_AGENT => [
				'Hostname'		=> 'agent',
				'ServerActive'	=> '127.0.0.1'
			],
			self::COMPONENT_AGENT2 => [
				'Hostname'		=> 'go_agent',
				'ServerActive'	=> '127.0.0.1'
			]
		];
	}

	/**
	 * Test if collected data is equal.
	 *
	 * @required-components server, agent, agent2
	 * @configurationDataProvider agentConfigurationProvider
	 * @hosts go_agent, agent
	 */
	public function testAgent2BackwardCompatibility_checkAgentData() {
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, [
				'enabling Zabbix agent checks on host "agent": host became available',
				'resuming Zabbix agent checks on host "agent": connection restored'
		]);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, [
				'enabling Zabbix agent checks on host "go_agent": host became available',
				'resuming Zabbix agent checks on host "go_agent": connection restored'
		]);

		foreach (self::$metrics as $metric) {
			$passive_data = $this->call('history.get', [
					'itemids'	=> self::$itemids[$metric],
					'history'	=> ITEM_VALUE_TYPE_TEXT
				]);

			$passive_data_go = $this->call('history.get', [
					'itemids'	=> self::$itemids_go[$metric],
					'history'	=> ITEM_VALUE_TYPE_TEXT
			]);

			// Both values empty - passed
			if (count($passive_data['result']) == 0 && count($passive_data_go['result']) == 0) {
				continue;
			}

			$this->assertTrue(count($passive_data['result']) > 0);
			$this->assertTrue(count($passive_data_go['result']) > 0);
			$this->assertEquals($passive_data['result'][0]['value'], $passive_data_go['result'][0]['value']);
		}
	}
}

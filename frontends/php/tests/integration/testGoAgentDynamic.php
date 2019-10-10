<?php
/*
 * * Zabbix
 * * Copyright (C) 2001-2019 Zabbix SIA
 * *
 * * This program is free software; you can redistribute it and/or modify
 * * it under the terms of the GNU General Public License as published by
 * * the Free Software Foundation; either version 2 of the License, or
 * * (at your option) any later version.
 * *
 * * This program is distributed in the hope that it will be useful,
 * * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * * GNU General Public License for more details.
 * *
 * * You should have received a copy of the GNU General Public License
 * * along with this program; if not, write to the Free Software
 * * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */
require_once dirname(__FILE__) . '/../include/CIntegrationTest.php';

/**
 * Test suite for angent2 (GO agent) dynamic metric collection.
 *
 * @backup history
 */
class testDataCollection extends CIntegrationTest
{
	private static $hostids = [];
	private static $itemids = [];
	private static $agent_name = 'agent';
	private static $go_agent_name = 'go_agent';

	// List of items to match
	// Item name, key, type, data type, treshold, compare average values (or last)
	private static $items = [
		['CPU utilization', 'system.cpu.util[,,avg1]', ITEM_TYPE_ZABBIX, ITEM_VALUE_TYPE_FLOAT, 100.0, false], // Result is unstable (large difference)
		['CPU load', 'system.cpu.load[,avg1]', ITEM_TYPE_ZABBIX, ITEM_VALUE_TYPE_FLOAT, 100.0, false],
		['VFS read', 'vfs.dev.read[,operations]', ITEM_TYPE_ZABBIX, ITEM_VALUE_TYPE_UINT64, 100, false],
		['VFS write', 'vfs.dev.write[,operations]', ITEM_TYPE_ZABBIX, ITEM_VALUE_TYPE_UINT64, 100, false],
		['Proc CPU util', 'proc.cpu.util[,,,,avg1]', ITEM_TYPE_ZABBIX, ITEM_VALUE_TYPE_FLOAT, 5.0, true],
		['Swap in', 'system.swap.in[,pages]', ITEM_TYPE_ZABBIX, ITEM_VALUE_TYPE_UINT64, 100, false],
		['Swap out', 'system.swap.out[,pages]', ITEM_TYPE_ZABBIX, ITEM_VALUE_TYPE_UINT64, 100, false],
		['Proc mem', 'proc.mem[,root]', ITEM_TYPE_ZABBIX, ITEM_VALUE_TYPE_UINT64, 100000000, false],
		['Web page', 'web.page.perf[https://www.zabbix.com]', ITEM_TYPE_ZABBIX, ITEM_VALUE_TYPE_FLOAT, 10.0, false],
		['Net TCP', 'net.tcp.service.perf[ssh]', ITEM_TYPE_ZABBIX, ITEM_VALUE_TYPE_FLOAT, 0.05, false],
		['Net UDP', 'net.udp.service.perf[ntp]', ITEM_TYPE_ZABBIX, ITEM_VALUE_TYPE_FLOAT, 0.05, false],
		['Swap', 'system.swap.size[,pfree]', ITEM_TYPE_ZABBIX, ITEM_VALUE_TYPE_UINT64, 100, false],
		['Vfs indoe', 'vfs.fs.inode[/,pfree]', ITEM_TYPE_ZABBIX, ITEM_VALUE_TYPE_UINT64, 100, false],
		['Vfs size', 'vfs.fs.size[/tmp,free]', ITEM_TYPE_ZABBIX, ITEM_VALUE_TYPE_UINT64, 100, false],
		['Memory', 'vm.memory.size[free]', ITEM_TYPE_ZABBIX, ITEM_VALUE_TYPE_UINT64, 1000000, false]
//		['System run', 'system.swap.size[,pfree]', ITEM_TYPE_ZABBIX, ITEM_VALUE_TYPE_TEXT] // Agent2 doesn't fill this value
//		sensor
//		zabbix.stats
	];

	/**
	 * @inheritdoc
	 */
	public function prepareData()
	{
		// Create host "agent" and "go_agent".
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

		$hosts = [
			[
				'host' => self::$agent_name,
				'interfaces' => $interfaces[0],
				'groups' => $groups,
				'status' => HOST_STATUS_NOT_MONITORED
			],
			[
				'host' => self::$go_agent_name,
				'interfaces' => $interfaces[1],
				'groups' => $groups,
				'status' => HOST_STATUS_NOT_MONITORED
			]
		];

		$response = $this->call('host.create', $hosts);

		$this->assertArrayHasKey('hostids', $response['result']);
		foreach ([self::$agent_name, self::$go_agent_name] as $i => $name) {
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

		// Create items.
		$new_items = [];
		foreach (self::$items as $i) {
			array_push($new_items, [
				'hostid' => self::$hostids[self::$agent_name],
				'name' => $i[0],
				'key_' => $i[1],
				'type' => $i[2],
				'value_type' => $i[3],
				'delay' => '1s',
				'interfaceid' => $interfaceids[self::$agent_name]
			],
			[
				'hostid' => self::$hostids[self::$go_agent_name],
				'name' => $i[0],
				'key_' => $i[1],
				'type' => $i[2],
				'value_type' => $i[3],
				'delay' => '1s',
				'interfaceid' => $interfaceids[self::$go_agent_name]
			]);
		}

// 		echo "\nitem.create request:".json_encode($new_items)."\n";
		$response = $this->call('item.create', $new_items);
// 		echo "\nitem.create response:".json_encode($response)."\n";

		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(count($new_items), count($response['result']['itemids']));

		// Get item IDs
		self::$itemids = [];
		foreach (self::$items as $index => $value) {
			$name = $value[1];
			self::$itemids[self::$agent_name.":".$name] = $response['result']['itemids'][$index*2];
			self::$itemids[self::$go_agent_name.":".$name] = $response['result']['itemids'][($index*2)+1];
		}
// 		echo "\n===>:".json_encode(self::$itemids)."\n";

		return true;
	}

	/**
	 * Component configuration provider for agent related tests.
	 *
	 * @return array
	 */
	public function agentConfigurationProvider()
	{
		return [
			self::COMPONENT_SERVER => [
				'UnreachablePeriod' => 5,
				'UnavailableDelay' => 5,
				'UnreachableDelay' => 1
			],
			self::COMPONENT_AGENT => [
				'Hostname' => self::$agent_name,
				'ServerActive' => '127.0.0.1',
				'EnableRemoteCommands' => '1'
			],
			self::COMPONENT_AGENT2 => [
				'Hostname' => self::$go_agent_name,
				'ServerActive' => '127.0.0.1',
				'EnableRemoteCommands' => '1'
			]
		];
	}

	/**
	 * Test if both active and passive go agent checks are processed.
	 *
	 * @required-components server, agent, agent2
	 * @configurationDataProvider agentConfigurationProvider
	 * @hosts agent, go_agent
	 */
	public function testDynamicMetricCollection() {
		echo "\ntestDynamicMetricCollection\n";
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, [
			'enabling Zabbix agent checks on host "'.self::$agent_name.'": host became available',
			'enabling Zabbix agent checks on host "'.self::$go_agent_name.'": host became available'
		]);

		// Go over all values and compare them
		foreach (self::$items as $i) {
			echo "= ".$i[0]." =\n";
			$name = $i[1];
			$type = $i[3];
			$agent_data = $this->call('history.get', [
				'itemids'	=> self::$itemids[self::$agent_name.":".$name],
				'history'	=> $type
			]);
			$agent2_data = $this->call('history.get', [
				'itemids'	=> self::$itemids[self::$go_agent_name.":".$name],
				'history'	=> $type
			]);
			echo "\nAgent data:".json_encode($agent_data)."\n";
			echo "\nGO Agent data:".json_encode($agent2_data)."\n";
			$a = end($agent_data['result'])['value'];
			$b = end($agent2_data['result'])['value'];
			switch ($type)
			{
				case ITEM_VALUE_TYPE_TEXT:
					echo "\n> A:".$a." B:".$b."\n";
					$this->assertEquals($value[$field], $item[$field]);
					break;
				case ITEM_VALUE_TYPE_FLOAT:
				case ITEM_VALUE_TYPE_UINT64:
					if ($i[5])
					{
						// Calculate average of all samples
						$a = 0.0;
						$records = 0;
						foreach ($agent_data['result'] as $e) {
							$records++;
							$a += $e['value'];
						}
						$a /= $records;
						$b = 0.0;
						$records = 0;
						foreach ($agent2_data['result'] as $e) {
							$records++;
							$b += $e['value'];
						}
						$b /= $records;
					}
					$diff = abs(abs($a) - abs($b));
					$treshold = $i[4];
					echo "\n> A:".$a." B:".$b." Diff:".$diff."\n";
					$this->assertTrue($diff < $treshold);
					break;
			}
		}
	}
}

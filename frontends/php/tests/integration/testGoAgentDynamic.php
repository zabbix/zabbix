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

define('COMPARE_AVERAGE', 0);
define('COMPARE_LAST', 1);

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
		[
			'name' => 'CPU utilization',
			'key' => 'system.cpu.util[,,avg1]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_FLOAT,
			'treshold' => 100.0,
			'compareType' => COMPARE_LAST
		],
		[
			'name' => 'CPU load',
			'key' => 'system.cpu.load[,avg1]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_FLOAT,
			'treshold' => 100.0,
			'compareType' => COMPARE_LAST
		],
		[
			'name' => 'VFS read',
			'key' => 'vfs.dev.read[,operations]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'treshold' => 500,
			'compareType' => COMPARE_LAST
		],
		[
			'name' => 'VFS write',
			'key' => 'vfs.dev.write[,operations]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'treshold' => 500,
			'compareType' => COMPARE_LAST
		],
		[
			'name' => 'Proc CPU util',
			'key' => 'proc.cpu.util[,,,,avg1]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_FLOAT,
			'treshold' => 5.0,
			'compareType' => COMPARE_AVERAGE
		],
		[
			'name' => 'Swap in',
			'key' => 'system.swap.in[,pages]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'treshold' => 100,
			'compareType' => COMPARE_LAST
		],
		[
			'name' => 'Swap out',
			'key' => 'system.swap.out[,pages]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'treshold' => 100,
			'compareType' => COMPARE_LAST
		],
		[
			'name' => 'Proc mem',
			'key' => 'proc.mem[,root]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'treshold' => 100000000,
			'compareType' => COMPARE_LAST
		],
		[
			'name' => 'Web page',
			'key' => 'web.page.perf[http://localhost]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_FLOAT,
			'treshold' => 10.0,
			'compareType' => COMPARE_AVERAGE
		],
		[
			'name' => 'Net TCP',
			'key' => 'net.tcp.service.perf[ssh]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_FLOAT,
			'treshold' => 0.05,
			'compareType' => COMPARE_LAST
		],
		[
			'name' => 'Net UDP',
			'key' => 'net.udp.service.perf[ntp]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_FLOAT,
			'treshold' => 0.05,
			'compareType' => COMPARE_LAST
		],
		[
			'name' => 'Swap',
			'key' => 'system.swap.size[,pfree]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'treshold' => 100,
			'compareType' => COMPARE_LAST
		],
		[
			'name' => 'Vfs inode',
			'key' => 'vfs.fs.inode[/,pfree]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_FLOAT,
			'treshold' => 0.1,
			'compareType' => COMPARE_LAST
		],
		[
			'name' => 'Vfs size',
			'key' => 'vfs.fs.size[/tmp,free]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'treshold' => 16384,
			'compareType' => COMPARE_LAST
		],
		[
			'name' => 'Memory',
			'key' => 'vm.memory.size[free]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'treshold' => 1000000,
			'compareType' => COMPARE_LAST
		],
		[	// Should be treated as a special case, since this metric returns JSON object.
			// Maybe, it should e pulled to separate test suite. At this point we just compare it as string.
			'name' => 'Stats',
			'key' => 'zabbix.stats',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'treshold' => 100,
			'compareType' => COMPARE_LAST
		]
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
				'name' => $i['name'],
				'key_' => $i['key'],
				'type' => $i['type'],
				'value_type' => $i['valueType'],
				'delay' => '1s',
				'interfaceid' => $interfaceids[self::$agent_name]
			],
			[
				'hostid' => self::$hostids[self::$go_agent_name],
				'name' => $i['name'],
				'key_' => $i['key'],
				'type' => $i['type'],
				'value_type' => $i['valueType'],
				'delay' => '1s',
				'interfaceid' => $interfaceids[self::$go_agent_name]
			]);
		}

		$response = $this->call('item.create', $new_items);

		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(count($new_items), count($response['result']['itemids']));

		// Get item IDs
		self::$itemids = [];
		foreach (self::$items as $index => $value) {
			$name = $value['key'];
			self::$itemids[self::$agent_name.":".$name] = $response['result']['itemids'][$index*2];
			self::$itemids[self::$go_agent_name.":".$name] = $response['result']['itemids'][($index*2)+1];
		}

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
				'DebugLevel' => '5'
			],
			self::COMPONENT_AGENT2 => [
				'Hostname' => self::$go_agent_name,
				'ServerActive' => '127.0.0.1',
				'Plugins.Uptime.Capacity'	=> '10'
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
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, [
			'enabling Zabbix agent checks on host "'.self::$agent_name.'": host became available',
			'enabling Zabbix agent checks on host "'.self::$go_agent_name.'": host became available'
		]);

		// Go over all values and compare them
		foreach (self::$items as $i) {
			$agent_data = $this->call('history.get', [
				'itemids'	=> self::$itemids[self::$agent_name.":".$i['key']],
				'history'	=> $i['valueType']
			]);
			$agent2_data = $this->call('history.get', [
				'itemids'	=> self::$itemids[self::$go_agent_name.":".$i['key']],
				'history'	=> $i['valueType']
			]);
			$this->assertArrayHasKey('result', $agent_data);
			$this->assertTrue(0 < sizeof($agent_data['result']), "No metrics");
			$this->assertArrayHasKey('result', $agent2_data);
			$this->assertTrue(0 < sizeof($agent2_data['result']), "No metrics");
			$a = end($agent_data['result'])['value'];
			$b = end($agent2_data['result'])['value'];
			switch ($i['valueType'])
			{
				case ITEM_VALUE_TYPE_TEXT:
					if (0 != $i['treshold'])
					{
						$a = substr($a, 0, $i['treshold']);
						$b = substr($b, 0, $i['treshold']);
					}
					$this->assertEquals($a, $b, "Strings do not match for ".$i['name']);
					break;
				case ITEM_VALUE_TYPE_FLOAT:
				case ITEM_VALUE_TYPE_UINT64:
					if ($i['compareType'] == COMPARE_AVERAGE)
					{
						// Calculate average of all samples
						$a = 0.0;
						$records = 0;
						foreach ($agent_data['result'] as $e) {
							$records++;
							$a += $e['value'];
						}
						if ($records > 1)
						{
							$a /= $records;
						}
						$b = 0.0;
						$records = 0;
						foreach ($agent2_data['result'] as $e) {
							$records++;
							$b += $e['value'];
						}
						if ($records > 1)
						{
							$b /= $records;
						}
					}
					$diff = abs(abs($a) - abs($b));
					$this->assertTrue($diff < $i['treshold'],
						"Difference for ".$i['name']." is more than defined treshold "
						.$diff." > ".$i['treshold']);
					break;
			}
		}
	}
}

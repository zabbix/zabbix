<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * Test suite for agent2 (GO agent) metric collection.
 *
 * @backup history
 */
class testGoAgentDataCollection extends CIntegrationTest {

	const COMPARE_AVERAGE = 0;
	const COMPARE_LAST = 1;

	private static $hostids = [];
	private static $itemids = [];

	// List of items to check.
	private static $items = [
		[
			'key' => 'agent.ping',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'kernel.maxfiles',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'kernel.maxproc',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'net.dns[,zabbix.com]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'net.dns.record[,zabbix.com]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'net.if.discovery',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'net.tcp.listen[80]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'net.tcp.port[,80]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'net.tcp.service[http]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'net.udp.listen[21]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'net.udp.service[ntp]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'proc.num[zabbix_server]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'system.cpu.discovery',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'system.cpu.num',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'system.hw.cpu[all,model]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'system.hw.devices',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'system.hw.macaddr',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'system.sw.arch',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'system.sw.os',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'system.sw.packages',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'system.uname',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'system.users.num',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'vfs.dir.count[/mnt]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'vfs.dir.size[/mnt]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'vfs.file.contents[/etc/hosts]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'vfs.file.exists[/etc/hosts]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'vfs.file.md5sum[/etc/hosts]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'vfs.file.size[/etc/hosts]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'vfs.file.time[/etc/hosts]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'vfs.file.regexp[/etc/hosts,localhost]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'vfs.file.cksum[/etc/hosts]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'vfs.fs.discovery',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'web.page.regexp[localhost/invalid_link_returns_404,,,404,2]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'system.run[uname]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'log['.PHPUNIT_COMPONENT_DIR.'zabbix_server.log]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_LOG
		],
		[
			'key' => 'log.count['.PHPUNIT_COMPONENT_DIR.'zabbix_server.log, server ]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'valueType' => ITEM_VALUE_TYPE_TEXT
		],
		[
			'key' => 'system.cpu.util[,,avg1]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_FLOAT,
			'threshold' => 0.5
		],
		[
			'key' => 'system.cpu.load[,avg1]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_FLOAT,
			'threshold' => 0.5
		],
		[
			'key' => 'vfs.dev.read[,operations]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'threshold' => 10
		],
		[
			'key' => 'vfs.dev.write[,operations]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'threshold' => 100
		],
		[
			'key' => 'proc.cpu.util[,,,,avg1]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_FLOAT,
			'threshold' => 10.0,
			'compareType' => self::COMPARE_AVERAGE
		],
		[
			'key' => 'system.swap.in[,pages]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'threshold' => 100
		],
		[
			'key' => 'system.swap.out[,pages]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'threshold' => 100
		],
		[
			'key' => 'proc.mem[zabbix_server,zabbix,avg]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_FLOAT,
			'threshold' => 100.0
		],
		[
			'key' => 'web.page.perf[http://localhost]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_FLOAT,
			'threshold' => 1.0,
			'compareType' => self::COMPARE_AVERAGE
		],
		[
			'key' => 'net.tcp.service.perf[ssh]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_FLOAT,
			'threshold' => 0.05
		],
		[
			'key' => 'net.udp.service.perf[ntp]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_FLOAT,
			'threshold' => 0.05
		],
		[
			'key' => 'system.swap.size[,total]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'threshold' => 100
		],
		[
			'key' => 'vfs.fs.inode[/,pfree]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_FLOAT,
			'threshold' => 0.1
		],
		[
			'key' => 'vfs.fs.size[/tmp,free]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'threshold' => 262144
		],
		[
			'key' => 'vm.memory.size[free]',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'threshold' => 10000000
		],
		[// Should be treated as a special case, since this metric returns JSON object.
			// Maybe, it should e pulled to separate test suite. At this point we just compare it as string.
			'key' => 'zabbix.stats[127.0.0.1,'.PHPUNIT_PORT_PREFIX.self::SERVER_PORT_SUFFIX.']',
			'type' => ITEM_TYPE_ZABBIX,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'threshold' => 50
		]
	];

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "agentd" and "agent2".
		$hosts = [];
		foreach ([self::COMPONENT_AGENT => self::AGENT_PORT_SUFFIX, self::COMPONENT_AGENT2 => self::AGENT2_PORT_SUFFIX] as $component => $port) {
			$hosts[] = [
				'host' => $component,
				'interfaces' => [
					[
						'type' => 1,
						'main' => 1,
						'useip' => 1,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => PHPUNIT_PORT_PREFIX.$port
					]
				],
				'groups' => [
					[
						'groupid' => 4
					]
				],
				'status' => HOST_STATUS_NOT_MONITORED
			];
		}

		$response = $this->call('host.create', $hosts);
		$this->assertArrayHasKey('hostids', $response['result']);

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $i => $name) {
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
		$items = [];
		foreach (self::$items as $item) {
			$data = [
				'name' => $item['key'],
				'key_' => $item['key'],
				'type' => $item['type'],
				'value_type' => $item['valueType'],
				'delay' => '1s'
			];

			foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
				$items[] = array_merge($data, [
					'hostid' => self::$hostids[$component],
					'interfaceid' => $interfaceids[$component]
				]);
			}
		}

		$response = $this->call('item.create', $items);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(count($items), count($response['result']['itemids']));

		// Get item IDs
		$itemids = $response['result']['itemids'];
		foreach (self::$items as $i => $value) {
			$name = $value['key'];

			self::$itemids[self::COMPONENT_AGENT.':'.$name] = $itemids[$i * 2];
			self::$itemids[self::COMPONENT_AGENT2.':'.$name] = $itemids[($i * 2) + 1];
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
				'UnreachablePeriod' => 25,
				'UnavailableDelay' => 15,
				'UnreachableDelay' => 5
			],
			self::COMPONENT_AGENT => [
				'Hostname' => self::COMPONENT_AGENT,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'AllowKey' => 'system.run[*]'
			],
			self::COMPONENT_AGENT2 => [
				'Hostname' => self::COMPONENT_AGENT2,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'ListenPort' => PHPUNIT_PORT_PREFIX.self::AGENT2_PORT_SUFFIX,
				'AllowKey' => 'system.run[*]',
				'Plugins.Uptime.Capacity' => '10'
			]
		];
	}

	/**
	 * Test if both active and passive go agent checks are processed.
	 *
	 * @required-components server, agent, agent2
	 * @configurationDataProvider agentConfigurationProvider
	 * @hosts agentd, agent2
	 */
	public function testGoAgentDataCollection_checkDataCollection() {
		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'enabling Zabbix agent checks on host "'.$component.'": interface became available', false
			);
		}

		// Delay to ensure that all metrics were collected.
		sleep(90);
	}

	/**
	 * Item data provider.
	 *
	 * @return array
	 */
	public function getItems() {
		$items = [];
		foreach (self::$items as $item) {
			$items[] = [$item];
		}

		return $items;
	}

	/**
	 * Get values of all items and store them in static variable.
	 *
	 * @return array
	 */
	public function getItemData() {
		static $data = null;

		if ($data === null) {
			$itemids = [];
			foreach (self::$items as $item) {
				$itemids[$item['valueType']][] = self::$itemids[self::COMPONENT_AGENT.':'.$item['key']];
				$itemids[$item['valueType']][] = self::$itemids[self::COMPONENT_AGENT2.':'.$item['key']];
			}

			$values = [];
			foreach ($itemids as $type => $ids) {
				$result = $this->call('history.get', [
					'output' => ['itemid', 'value', 'clock', 'ns'],
					'itemids' => $ids,
					'history' => $type
				]);
				$this->sort($result['result'], ['itemid', 'clock', 'ns']);

				foreach ($result['result'] as $item) {
					$values[$item['itemid']][] = $item['value'];
				}
			}

			$data = [];
			foreach (self::$items as $item) {
				$data[$item['key']] = [];
				foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
					$itemid = self::$itemids[$component.':'.$item['key']];

					if (array_key_exists($itemid, $values)) {
						$data[$item['key']][$component] = $values[$itemid];
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Test if both active and passive go agent checks are processed.
	 *
	 * @depends testGoAgentDataCollection_checkDataCollection
	 * @dataProvider getItems
	 */
	public function testGoAgentDataCollection_checkData($item) {
		$data = $this->getItemData();
		if (!array_key_exists($item['key'], $data)) {
			$this->fail('No metrics for item "'.$item['key'].'"');
		}

		$values = $data[$item['key']];
		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			if (!array_key_exists($component, $values)) {
				$this->fail('No metrics for item "'.$component.':'.$item['key'].'"');
			}
		}

		switch ($item['valueType']) {
			case ITEM_VALUE_TYPE_LOG:
				$count = min([count($values[self::COMPONENT_AGENT]), count($values[self::COMPONENT_AGENT2])]);

				$values_a = array_slice($values[self::COMPONENT_AGENT], 0, $count);
				$values_b = array_slice($values[self::COMPONENT_AGENT2], 0, $count);

				$this->assertSame($values_a, $values_b, 'Strings do not match for '.$item['key']);
				break;

			case ITEM_VALUE_TYPE_TEXT:
				$a = end($values[self::COMPONENT_AGENT]);
				$b = end($values[self::COMPONENT_AGENT2]);

				if (array_key_exists('threshold', $item) && $item['threshold'] !== 0) {
					$a = substr($a, 0, $item['threshold']);
					$b = substr($b, 0, $item['threshold']);
				}

				$this->assertEquals($a, $b, 'Strings do not match for '.$item['key']);
				break;

			case ITEM_VALUE_TYPE_FLOAT:
			case ITEM_VALUE_TYPE_UINT64:
				if (CTestArrayHelper::get($item, 'compareType', self::COMPARE_LAST) === self::COMPARE_AVERAGE) {
					$value = [];
					foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
						$value[$component] = 0;
						$records = count($values[$component]);

						if ($records > 0) {
							$value[$component] = array_sum($values[$component]) / $records;
						}
					}

					$a = $value[self::COMPONENT_AGENT];
					$b = $value[self::COMPONENT_AGENT2];
				}
				else {
					$a = end($values[self::COMPONENT_AGENT]);
					$b = end($values[self::COMPONENT_AGENT2]);
				}

				$diff = abs(abs($a) - abs($b));
				$this->assertTrue($diff < $item['threshold'], 'Difference for '.$item['key'].
						' is more than defined threshold '.$diff.' > '.$item['threshold']
				);
				break;
		}
	}

	/**
	 * Sort array by multiple fields.
	 *
	 * @static
	 *
	 * @param array $array  array to sort passed by reference
	 * @param array $fields fields to sort, can be either string with field name or array with 'field' and 'order' keys
	 */
	public static function sort(array &$array, array $fields) {
		foreach ($fields as $fid => $field) {
			if (!is_array($field)) {
				$fields[$fid] = ['field' => $field, 'order' => ZBX_SORT_UP];
			}
		}

		uasort($array, function($a, $b) use ($fields) {
			foreach ($fields as $field) {
				$cmp = strnatcasecmp($a[$field['field']], $b[$field['field']]);

				if ($cmp != 0) {
					return $cmp * ($field['order'] == ZBX_SORT_UP ? 1 : -1);
				}
			}

			return 0;
		});
	}
}

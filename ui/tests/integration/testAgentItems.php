<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

define('COMPARE_AVERAGE', 0);
define('COMPARE_LAST', 1);

define('JSON_COMPARE_LEFT', 1);
define('JSON_COMPARE_RIGHT', 2);
define('JSON_COMPARE_BOTH', 3);

/**
 * Test suite for agents metric collection.
 *
 * @backup history
 */
class testAgentItems extends CIntegrationTest {

	const TEST_FILE_NAME = '/tmp/test_file';
	const TEST_LINK_NAME = '/tmp/test_link';
	const TEST_MOD_TIMESTAMP = 1617019149;
	const AGENT_METADATA = 'zabbixtestagent';

	private static $hostids = [];
	private static $itemids = [];

	// List of items to check.
	private static $items = [
		[
			'key' => 'vfs.file.owner['.self::TEST_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result_exec' => 'stat -c \'%U\' '.self::TEST_FILE_NAME,
		],
		[
			'key' => 'vfs.file.owner['.self::TEST_FILE_NAME.',group,id]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result_exec' => 'stat -c \'%g\' '.self::TEST_FILE_NAME
		],
		[
			'key' => 'vfs.file.owner['.self::TEST_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result_exec' => 'stat -c \'%U\' '.self::TEST_FILE_NAME
		],
		[
			'key' => 'vfs.file.owner['.self::TEST_FILE_NAME.',group,id]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result_exec' => 'stat -c \'%g\' '.self::TEST_FILE_NAME
		],
		[
			'key' => 'kernel.openfiles',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result_exec' => 'cat /proc/sys/fs/file-nr | cut -f 1',
			'threshold' => 2000
		],
		[
			'key' => 'kernel.openfiles',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result_exec' => 'cat /proc/sys/fs/file-nr | cut -f 1',
			'threshold' => 2000
		],
		[
			'key' => 'vfs.file.size['.self::TEST_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 27
		],
		[
			'key' => 'vfs.file.size['.self::TEST_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 27
		],
		[
			'key' => 'vfs.file.size['.self::TEST_FILE_NAME.',lines]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 3
		],
		[
			'key' => 'vfs.file.size['.self::TEST_FILE_NAME.',lines]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 3
		],
		[
			'key' => 'vfs.file.permissions['.self::TEST_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result_exec' => 'stat -c \'%a\' '.self::TEST_FILE_NAME
		],
		[
			'key' => 'vfs.file.permissions['.self::TEST_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result_exec' => 'stat -c \'%a\' '.self::TEST_FILE_NAME
		],
		[
			'key' => 'agent.hostmetadata',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result' => self::AGENT_METADATA
		],
		[
			'key' => 'agent.hostmetadata',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result' => self::AGENT_METADATA
		],
		[
			'key' => 'vfs.file.cksum['.self::TEST_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 892864536
		],
		[
			'key' => 'vfs.file.cksum['.self::TEST_FILE_NAME.',md5]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result' => 'f58f72c7ef71556254f409fd7411567d'
		],
		[
			'key' => 'vfs.file.cksum['.self::TEST_FILE_NAME.',sha256]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result' => 'b73a96d498012c84fc2ffa1df3c4461689cb90456ee300654723205c26ec4988'
		],
		[
			'key' => 'vfs.file.cksum['.self::TEST_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 892864536
		],
		[
			'key' => 'vfs.file.cksum['.self::TEST_FILE_NAME.',md5]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result' => 'f58f72c7ef71556254f409fd7411567d'
		],
		[
			'key' => 'vfs.file.cksum['.self::TEST_FILE_NAME.',sha256]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result' => 'b73a96d498012c84fc2ffa1df3c4461689cb90456ee300654723205c26ec4988'
		],
		[
			'key' => 'vfs.file.get['.self::TEST_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'json' => JSON_COMPARE_LEFT,
			'fields_exec' => [
					'permissions',
					'user',
					'group',
					'uid',
					'gid',
					'access',
					'change'
				],
			'result' => [
					'type' => 'file',
					'permissions' => 'stat -c \'%a\' '.self::TEST_FILE_NAME,
					'user' => 'stat -c \'%U\' '.self::TEST_FILE_NAME,
					'group' => 'stat -c \'%G\' '.self::TEST_FILE_NAME,
					'uid' => 'stat -c \'%u\' '.self::TEST_FILE_NAME,
					'gid' => 'stat -c \'%g\' '.self::TEST_FILE_NAME,
					'size' => 27,
					'time' => [
						'modify' => '2021-03-29T14:59:09+0300'
					],
					'timestamp' => [
						'access' => 'stat -c \'%X\' '.self::TEST_FILE_NAME,
						'modify' => self::TEST_MOD_TIMESTAMP,
						'change' => 'stat -c \'%Z\' '.self::TEST_FILE_NAME
					]
				]
		],
		[
			'key' => 'vfs.file.get['.self::TEST_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'json' => JSON_COMPARE_LEFT,
			'fields_exec' => [
					'permissions',
					'user',
					'group',
					'uid',
					'gid',
					'access',
					'change'
				],
			'result' => [
					'type' => 'file',
					'permissions' => 'stat -c \'%a\' '.self::TEST_FILE_NAME,
					'user' => 'stat -c \'%U\' '.self::TEST_FILE_NAME,
					'group' => 'stat -c \'%G\' '.self::TEST_FILE_NAME,
					'uid' => 'stat -c \'%u\' '.self::TEST_FILE_NAME,
					'gid' => 'stat -c \'%g\' '.self::TEST_FILE_NAME,
					'size' => 27,
					'time' => [
						'modify' => '2021-03-29T14:59:09+03:00'
					],
					'timestamp' => [
						'access' => 'stat -c \'%X\' '.self::TEST_FILE_NAME,
						'modify' => self::TEST_MOD_TIMESTAMP,
						'change' => 'stat -c \'%Z\' '.self::TEST_FILE_NAME
					]
				]
		],
		[
			'key' => 'vfs.file.get['.self::TEST_LINK_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'json' => JSON_COMPARE_LEFT,
			'fields_exec' => [
					'permissions',
					'user',
					'group',
					'uid',
					'gid',
					'access',
					'change'
				],
			'result' => [
					'type' => 'sym',
					'permissions' => 'stat -c \'%a\' '.self::TEST_LINK_NAME,
					'user' => 'stat -c \'%U\' '.self::TEST_LINK_NAME,
					'group' => 'stat -c \'%G\' '.self::TEST_LINK_NAME,
					'uid' => 'stat -c \'%u\' '.self::TEST_LINK_NAME,
					'gid' => 'stat -c \'%g\' '.self::TEST_LINK_NAME,
					'size' => 14,
					'time' => [
						'modify' => '2021-03-29T14:59:09+0300'
					],
					'timestamp' => [
						'access' => 'stat -c \'%X\' '.self::TEST_LINK_NAME,
						'modify' => self::TEST_MOD_TIMESTAMP,
						'change' => 'stat -c \'%Z\' '.self::TEST_LINK_NAME
					]
				]
		],
		[
			'key' => 'vfs.file.get['.self::TEST_LINK_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'json' => JSON_COMPARE_LEFT,
			'fields_exec' => [
					'permissions',
					'user',
					'group',
					'uid',
					'gid',
					'access',
					'change'
				],
			'result' => [
					'type' => 'sym',
					'permissions' => 'stat -c \'%a\' '.self::TEST_LINK_NAME,
					'user' => 'stat -c \'%U\' '.self::TEST_LINK_NAME,
					'group' => 'stat -c \'%G\' '.self::TEST_LINK_NAME,
					'uid' => 'stat -c \'%u\' '.self::TEST_LINK_NAME,
					'gid' => 'stat -c \'%g\' '.self::TEST_LINK_NAME,
					'size' => 14,
					'time' => [
						'modify' => '2021-03-29T14:59:09+03:00'
					],
					'timestamp' => [
						'access' => 'stat -c \'%X\' '.self::TEST_LINK_NAME,
						'modify' => self::TEST_MOD_TIMESTAMP,
						'change' => 'stat -c \'%Z\' '.self::TEST_LINK_NAME
					]
				]
		],
		[
			'key' => 'net.tcp.socket.count[,'.PHPUNIT_PORT_PREFIX.self::SERVER_PORT_SUFFIX.',,,listen]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 1
		],
		[
			'key' => 'net.tcp.socket.count[,,127.127.127.127]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 0
		],
		[
			'key' => 'net.udp.socket.count[,ssh]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result_exec' => 'netstat -au --numeric-hosts -4 | grep ssh | wc -l'
		],
		[
			'key' => 'net.tcp.socket.count[,'.PHPUNIT_PORT_PREFIX.self::SERVER_PORT_SUFFIX.',,,listen]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 1
		],
		[
			'key' => 'net.tcp.socket.count[,,127.127.127.127]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 0
		],
		[
			'key' => 'net.udp.socket.count[,ssh]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result_exec' => 'netstat -au --numeric-hosts -4 | grep ssh | wc -l'
		]
	];

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "agentd" and "agent2".
		$hosts = [];
		foreach ([self::COMPONENT_AGENT => self::AGENT_PORT_SUFFIX, self::COMPONENT_AGENT2 => 53] as $component => $port) {
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

			$items[] = array_merge($data, [
				'hostid' => self::$hostids[$item['component']],
				'interfaceid' => $interfaceids[$item['component']]
			]);
		}

		$response = $this->call('item.create', $items);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(count($items), count($response['result']['itemids']));

		// Get item IDs
		$itemids = $response['result']['itemids'];
		foreach (self::$items as $i => $value) {
			$name = $value['key'];
			self::$itemids[$value['component'].':'.$name] = $itemids[$i];
		}

		// Write test file
		$this->assertTrue(@file_put_contents(self::TEST_FILE_NAME, "1st line\n2nd line\n3rd line\n") !== false);
		$this->assertTrue(@touch(self::TEST_FILE_NAME, self::TEST_MOD_TIMESTAMP) !== false);

		// Write test symlink
		if (!file_exists(self::TEST_LINK_NAME)) {
			$this->assertTrue(@symlink(self::TEST_FILE_NAME, self::TEST_LINK_NAME) !== false);
		}
		$this->assertTrue(@shell_exec('touch -h -a -m -t 202103291459.09 '.self::TEST_LINK_NAME) !== false);

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
				'AllowKey' => 'system.run[*]',
				'HostMetadata' => self::AGENT_METADATA
			],
			self::COMPONENT_AGENT2 => [
				'Hostname' => self::COMPONENT_AGENT2,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'ListenPort' => PHPUNIT_PORT_PREFIX.'53',
				'AllowKey' => 'system.run[*]',
				'Plugins.Uptime.Capacity' => '10',
				'HostMetadata' => self::AGENT_METADATA
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
	public function testAgentItems_checkDataCollection() {
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
				$itemids[$item['valueType']][] = self::$itemids[$item['component'].':'.$item['key']];
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
				$data[$item['component'].':'.$item['key']] = [];
				$itemid = self::$itemids[$item['component'].':'.$item['key']];

				if (array_key_exists($itemid, $values)) {
					$data[$item['component'].':'.$item['key']] = $values[$itemid];
				}
			}
		}

		return $data;
	}

	/**
	 * Test if both active and passive go agent checks are processed.
	 *
	 * @depends testAgentItems_checkDataCollection
	 * @dataProvider getItems
	 */
	public function testAgentItems_checkData($item) {
		$data = $this->getItemData();
		if (!array_key_exists($item['component'].':'.$item['key'], $data)) {
			$this->fail('No metrics for item "'.$item['component'].':'.$item['key'].'"');
		}

		$values = $data[$item['component'].':'.$item['key']];
		$component = $item['component'];

		if (array_key_exists('json', $item) && array_key_exists('fields_exec', $item)) {
			foreach ($item['fields_exec'] as $dyn) {
				$this->dynupdate($item, $dyn);
			}
		} elseif (array_key_exists('result_exec', $item)) {
			$item['result'] = exec($item['result_exec']);
		}

		switch ($item['valueType']) {
			case ITEM_VALUE_TYPE_TEXT:
				if (array_key_exists('json', $item) && $item['json'] === 1)
				{
					$jsonval = json_decode(end($values), true);

					if ($item['json'] === JSON_COMPARE_LEFT)
					{
						$this->arrcmpr($item['result'], $jsonval, $item['key']);
					} elseif ($item['json'] === JSON_COMPARE_RIGHT) {
						$this->arrcmpr($jsonval, $item['result'], $item['key']);
					}
				} else {
					$actual = end($values);

					if ($actual === false) {
						$actual = 0;
					}

					if (array_key_exists('threshold', $item) && $item['threshold'] !== 0) {
						$actual = substr($actual, 0, $item['threshold']);
						$expected = substr($item['result'], 0, $item['threshold']);
					} else {
						$expected = $item['result'];
					}

					$this->assertEquals($expected, $actual, 'Received value is not expected for '.$item['key']);
				}
				break;

			case ITEM_VALUE_TYPE_FLOAT:
			case ITEM_VALUE_TYPE_UINT64:
				if (CTestArrayHelper::get($item, 'compareType', COMPARE_LAST) === COMPARE_AVERAGE) {
					$value = 0;
					$records = count($values);

					if ($records > 0) {
						$value = array_sum($values) / $records;
					}

					$actual = $value;
				}
				else {
					$actual = end($values);
				}

				if ($actual === false) {
					$actual = 0;
				}

				if (array_key_exists('threshold', $item) && $item['threshold'] !== 0) {
					$diff = abs(abs($actual) - abs($item['result']));
					$this->assertTrue($diff <= $item['threshold'], 'Received value ('.$actual.') for '.$item['key'].
							' differs more than defined threshold '.$diff.' > '.$item['threshold']
					);
				} else {
					$this->assertEquals($item['result'], $actual, 'Received value is not expected for '.$item['key']);
				}

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

	/**
	 * Compare arrays fields.
	 *
	 * @static
	 *
	 * @param array $array  array with mandatory fields
	 * @param array $cmpr	array to compare with
	 * @param string $key	item key
	 */
	public static function arrcmpr(array $array, array $cmpr, string $key) {
		foreach ($array as $array_key => $array_value) {
			self::assertArrayHasKey($array_key, $cmpr, 'Array key "'.$array_key.'" is missing in '.$key);

			if (is_array($array_value)) {
				if (!is_array($cmpr[$array_key])) {
					self::fail('Wrong element type in '.$key);
				}

				self::arrcmpr($array_value, $cmpr[$array_key], $key);
			} else {
				if (is_array($cmpr[$array_key])) {
					self::fail('Wrong element type in '.$key);
				}

				self::assertEquals($array_value, $cmpr[$array_key], 'Value (array key: '.$array_key.') is not expected for '.$key);
			}
		}
	}

	/**
	 * Update results.
	 *
	 * @static
	 *
	 * @param array $result	reference to array with the expected results
	 * @param string $dyn	result field key
	 */
	public static function dynupdate(array & $result, string $dyn) {
		foreach ($result as $k => $res) {
			if (is_array($res)) {
				self::dynupdate($result[$k], $dyn);
			} elseif ($k === $dyn) {
				$result[$k] = exec($res);
			}
		}
	}
}


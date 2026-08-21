<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * Test suite for the vfs.dev.get item, on both classic agent and agent2.
 *
 * @onAfter clearData
 */
class testVfsDevGet extends CIntegrationTest {

	const ITEM_DELAY = '1s';

	private static $hostids = [];
	private static $itemids = [];

	// Real disk-type block devices on the host (and their partitions), discovered in
	// onBeforeTestSuite() and used as expected values - see listBlockDevices(). Keyed by device name.
	private static $disks = [];

	// List of items to check.
	private static $items = [
		['key' => 'vfs.dev.get'],
		['key' => 'vfs.dev.get[disks]'],
		['key' => 'vfs.dev.get[disk_stats]'],
		['key' => 'vfs.dev.get[devices]'],
		['key' => 'vfs.dev.get[device_stats]'],
		['key' => 'vfs.dev.get[bogus_mode]']
	];

	/**
	 * Discover the real block devices to test vfs.dev.get against. This suite never creates,
	 * attaches, or otherwise mocks a device of its own - if the host genuinely has none, that is a
	 * real gap in coverage, not an environment quirk to route around, so the suite fails outright
	 * rather than being skipped.
	 */
	protected function onBeforeTestSuite() {
		self::$disks = self::listBlockDevices();

		if (!self::$disks) {
			throw new Exception('No block devices found on this host to test vfs.dev.get against.');
		}

		parent::onBeforeTestSuite();
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "agentd" and "agent2".
		$hosts = [];
		foreach ([self::COMPONENT_AGENT => self::AGENT_PORT_SUFFIX, self::COMPONENT_AGENT2 =>
				self::AGENT2_PORT_SUFFIX] as $component => $port) {
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

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $i => $component) {
			$this->assertArrayHasKey($i, $response['result']['hostids']);
			self::$hostids[$component] = $response['result']['hostids'][$i];
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
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'delay' => self::ITEM_DELAY
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

		// Get item IDs.
		$itemids = $response['result']['itemids'];
		$i = 0;

		foreach (self::$items as $item) {
			foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
				self::$itemids[$component.':'.$item['key']] = $itemids[$i++];
			}
		}

		return true;
	}

	/**
	 * Remove the hosts (and, with them, the items and history) created for this suite.
	 */
	public static function clearData(): void {
		if (self::$hostids) {
			CDataHelper::call('host.delete', array_values(self::$hostids));
		}

		self::$hostids = [];
		self::$itemids = [];
	}

	/**
	 * List every real disk-type block device on the host that has a working /dev node, together with
	 * the values vfs.dev.get is expected to report for it. Ground truth comes straight from lsblk and
	 * sysfs, the same sources vfs.dev.get itself relies on.
	 *
	 * @return array  device name => ['type' => .., 'path' => .., 'size_bytes' => .., ..,
	 *                'partitions' => [partition name => size_bytes]]
	 */
	private static function listBlockDevices() {
		$disks = [];

		$names = trim((string) shell_exec("lsblk -dnlo NAME,TYPE | awk '\$2 == \"disk\" {print \$1}'"));

		foreach (($names === '') ? [] : explode("\n", $names) as $name) {
			$name = trim($name);

			if ($name === ''
					|| trim((string) shell_exec('[ -b '.escapeshellarg('/dev/'.$name).' ] && echo yes')) !== 'yes') {
				continue;
			}

			$partitions = [];
			$partition_names = trim((string) shell_exec('lsblk -nlo NAME,TYPE '.escapeshellarg('/dev/'.$name).
					" | awk 'NR > 1 && \$2 == \"part\" {print \$1}'"
			));

			foreach (($partition_names === '') ? [] : explode("\n", $partition_names) as $partition) {
				$partition = trim($partition);

				if ($partition !== '') {
					$partitions[$partition] = self::readSysfsSizeBytes($partition);
				}
			}

			$disks[$name] = [
				'type' => 'disk',
				'path' => '/dev/'.$name,
				'size_bytes' => self::readSysfsSizeBytes($name),
				'logical_block_size' => self::readSysfsUint($name.'/queue/logical_block_size'),
				'physical_block_size' => self::readSysfsUint($name.'/queue/physical_block_size'),
				'partitions' => $partitions
			];
		}

		return $disks;
	}

	/**
	 * Read a sysfs "size" file (in 512 byte sectors) for a block device and convert it to bytes.
	 *
	 * @param string $device  device name, e.g. "vda" or "vda1"
	 *
	 * @return int|null
	 */
	private static function readSysfsSizeBytes($device) {
		$sectors = self::readSysfsUint($device.'/size');

		return ($sectors === null) ? null : $sectors * 512;
	}

	/**
	 * Read an unsigned integer from a file under /sys/class/block/<relative path>.
	 *
	 * @param string $relative  path relative to /sys/class/block/
	 *
	 * @return int|null
	 */
	private static function readSysfsUint($relative) {
		$value = trim((string) shell_exec('cat /sys/class/block/'.$relative.' 2>/dev/null'));

		return ($value === '' || !ctype_digit($value)) ? null : (int) $value;
	}

	/**
	 * Component configuration provider for agent related tests.
	 *
	 * @return array
	 */
	public function agentConfigurationProvider() {
		return [
			self::COMPONENT_AGENT => [
				'Hostname' => self::COMPONENT_AGENT,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort')
			],
			self::COMPONENT_AGENT2 => [
				'Hostname' => self::COMPONENT_AGENT2,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'ListenPort' => PHPUNIT_PORT_PREFIX.self::AGENT2_PORT_SUFFIX
			]
		];
	}

	/**
	 * @required-components server, agent, agent2
	 * @configurationDataProvider agentConfigurationProvider
	 * @hosts agentd, agent2
	 */
	public function testVfsDevGet_checkDataCollection() {
		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'enabling Zabbix agent checks on host "'.$component.'": interface became available', false
			);
		}
	}

	/**
	 * Data provider of the two components under test.
	 *
	 * @return array
	 */
	public function componentsDataProvider() {
		return [
			[self::COMPONENT_AGENT],
			[self::COMPONENT_AGENT2]
		];
	}

	/**
	 * Poll an item until it either collects a value or becomes unsupported.
	 *
	 * @param string $component
	 * @param string $key
	 *
	 * @return array  item.get result row (lastvalue, lastclock, state)
	 */
	private function getItemResult($component, $key) {
		$itemid = self::$itemids[$component.':'.$key];

		$response = $this->callUntilDataIsPresent('item.get', [
			'output' => ['lastvalue', 'lastclock', 'state'],
			'itemids' => [$itemid]
		], 10, 2, function(array $response) {
			$item = $response['result'][0];

			return ($item['lastclock'] != 0 || $item['state'] == ITEM_STATE_NOTSUPPORTED)
				? true
				: 'no data was collected for the item yet';
		});

		return $response['result'][0];
	}

	/**
	 * Decode a vfs.dev.get item value and fail with a helpful message if it is not valid JSON.
	 *
	 * @param array  $item
	 * @param string $component
	 * @param string $key
	 *
	 * @return array
	 */
	private function decodeItemValue(array $item, $component, $key) {
		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'],
			$component.':'.$key.' is unexpectedly unsupported: '.$item['lastvalue']
		);

		$json = json_decode($item['lastvalue'], true);
		$this->assertIsArray($json, $component.':'.$key.' did not return a JSON object: '.$item['lastvalue']);

		return $json;
	}

	/**
	 * Find the config entry for a device inside a vfs.dev.get "config" array.
	 *
	 * @param array  $config
	 * @param string $name
	 *
	 * @return array|null
	 */
	private static function findConfigEntry(array $config, $name) {
		foreach ($config as $entry) {
			if (array_key_exists('name', $entry) && $entry['name'] === $name) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * @depends testVfsDevGet_checkDataCollection
	 * @dataProvider componentsDataProvider
	 */
	public function testVfsDevGet_disks($component) {
		foreach (['vfs.dev.get', 'vfs.dev.get[disks]'] as $key) {
			$json = $this->decodeItemValue($this->getItemResult($component, $key), $component, $key);

			$this->assertArrayHasKey('config', $json, $component.':'.$key);

			foreach (self::$disks as $name => $disk) {
				$found = self::findConfigEntry($json['config'], $name);
				$this->assertIsArray($found, 'Device "'.$name.'" is missing from '.$component.':'.$key);

				$this->assertEquals($disk['type'], $found['type'], $component.':'.$key.' type for "'.$name.'"');
				$this->assertEquals($disk['path'], $found['path'], $component.':'.$key.' path for "'.$name.'"');

				if ($disk['size_bytes'] !== null) {
					$this->assertEquals($disk['size_bytes'], $found['size_bytes'],
							$component.':'.$key.' size_bytes for "'.$name.'"'
					);
				}

				if ($disk['logical_block_size'] !== null) {
					$this->assertEquals($disk['logical_block_size'], $found['logical_block_size'],
							$component.':'.$key.' logical_block_size for "'.$name.'"'
					);
				}

				if ($disk['physical_block_size'] !== null) {
					$this->assertEquals($disk['physical_block_size'], $found['physical_block_size'],
							$component.':'.$key.' physical_block_size for "'.$name.'"'
					);
				}
			}
		}
	}

	/**
	 * @depends testVfsDevGet_checkDataCollection
	 * @dataProvider componentsDataProvider
	 */
	public function testVfsDevGet_devices($component) {
		$key = 'vfs.dev.get[devices]';
		$json = $this->decodeItemValue($this->getItemResult($component, $key), $component, $key);

		$this->assertArrayHasKey('config', $json, $component.':'.$key);

		foreach (self::$disks as $name => $disk) {
			$found = self::findConfigEntry($json['config'], $name);
			$this->assertIsArray($found, 'Device "'.$name.'" is missing from '.$component.':'.$key);
			$this->assertArrayHasKey('partitions', $found, $component.':'.$key.' for "'.$name.'"');

			foreach ($disk['partitions'] as $partition => $size) {
				$this->assertArrayHasKey($partition, $found['partitions'],
						'Partition "'.$partition.'" is missing from '.$component.':'.$key
				);

				if ($size !== null) {
					$this->assertEquals($size, $found['partitions'][$partition],
							'Partition "'.$partition.'" size mismatch for '.$component.':'.$key
					);
				}
			}
		}
	}

	/**
	 * @depends testVfsDevGet_checkDataCollection
	 * @dataProvider componentsDataProvider
	 */
	public function testVfsDevGet_stats($component) {
		foreach (['vfs.dev.get[disk_stats]', 'vfs.dev.get[device_stats]'] as $key) {
			$json = $this->decodeItemValue($this->getItemResult($component, $key), $component, $key);

			$this->assertArrayHasKey('values', $json, $component.':'.$key);

			foreach (array_keys(self::$disks) as $name) {
				$found = null;
				foreach ($json['values'] as $value) {
					if (array_key_exists('name', $value) && $value['name'] === $name) {
						$found = $value;
						break;
					}
				}

				$this->assertIsArray($found, 'Device "'.$name.'" is missing from '.$component.':'.$key);
				$this->assertArrayHasKey('stats', $found, $component.':'.$key);

				foreach (['reads_completed', 'bytes_read', 'writes_completed', 'bytes_written'] as $field) {
					$this->assertArrayHasKey($field, $found['stats'],
							'Field "'.$field.'" is missing from '.$component.':'.$key
					);
					$this->assertIsNumeric($found['stats'][$field],
							'Field "'.$field.'" is not numeric for '.$component.':'.$key
					);
					$this->assertGreaterThanOrEqual(0, $found['stats'][$field],
							'Field "'.$field.'" is negative for '.$component.':'.$key
					);
				}
			}
		}
	}

	/**
	 * An unknown mode must be reported as an unsupported item, on both agent and agent2.
	 *
	 * @depends testVfsDevGet_checkDataCollection
	 * @dataProvider componentsDataProvider
	 */
	public function testVfsDevGet_invalidMode($component) {
		$key = 'vfs.dev.get[bogus_mode]';
		$item = $this->getItemResult($component, $key);

		$this->assertEquals(ITEM_STATE_NOTSUPPORTED, $item['state'], $component.':'.$key.
				' should be unsupported, got value: '.$item['lastvalue']
		);
	}
}

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
 * Test suite for the vfs.dev.get item, on both Zabbix agent and agent2 on Linux.
 *
 * @onAfter clearData
 */
class testVfsDevGet extends CIntegrationTest {

	const ITEM_DELAY = '1s';

	private static $hostids = [];
	private static $itemids = [];

	// Real disk-type block devices on the host (and their partitions), discovered in
	// prepareData() and used as expected values - see listBlockDevices(). Keyed by device name.
	private static $disks = [];

	// List of items to check.
	private static $items = [
		['key' => 'vfs.dev.get'],
		['key' => 'vfs.dev.get[disks]'],
		['key' => 'vfs.dev.get[disk_stats]'],
		['key' => 'vfs.dev.get[devices]'],
		['key' => 'vfs.dev.get[device_stats]'],
		['key' => 'vfs.dev.get[bogus_mode]'],
		['key' => 'vfs.dev.get[disks,"("]'],
		['key' => 'vfs.dev.get[disks,,extra]'],
		['key' => 'vfs.dev.get[disks,"^zbx_no_such_device$"]']
	];

	// Key filtering vfs.dev.get by the name of a discovered disk, built in prepareData().
	private static $devnames_key;

	/**
	 * Discover the real block devices to test vfs.dev.get against and create the items to check.
	 *
	 * This suite never creates, attaches, or otherwise mocks a device of its own - if the host genuinely
	 * has none, that is a real gap in coverage, not an environment quirk to route around, so the first
	 * test case fails with an error instead of the whole suite being silently skipped.
	 *
	 * @inheritdoc
	 */
	public function prepareData() {
		if (trim((string) shell_exec('command -v lsblk')) === '') {
			throw new Exception('lsblk is required to discover block devices for vfs.dev.get, but was not found.');
		}

		self::$disks = self::listBlockDevices();

		if (!self::$disks) {
			throw new Exception('No block devices found on this host to test vfs.dev.get against.');
		}

		self::$devnames_key = 'vfs.dev.get[disks,"^'.preg_quote(array_key_first(self::$disks)).'$"]';
		self::$items[] = ['key' => self::$devnames_key];

		$ports = [self::COMPONENT_AGENT => self::AGENT_PORT_SUFFIX, self::COMPONENT_AGENT2 => self::AGENT2_PORT_SUFFIX];

		foreach ($ports as $component => $port) {
			$response = $this->call('host.create', [
				'host' => $component,
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => PHPUNIT_PORT_PREFIX.$port
					]
				],
				'groups' => [
					[
						'groupid' => 4 // Zabbix servers
					]
				]
			]);
			$this->assertArrayHasKey('hostids', $response['result']);
			self::$hostids[$component] = $response['result']['hostids'][0];

			$response = $this->call('hostinterface.get', [
				'output' => ['interfaceid'],
				'hostids' => self::$hostids[$component]
			]);
			$interfaceid = $response['result'][0]['interfaceid'];

			$items = [];
			foreach (self::$items as $item) {
				$items[] = [
					'hostid' => self::$hostids[$component],
					'interfaceid' => $interfaceid,
					'name' => $item['key'],
					'key_' => $item['key'],
					'type' => ITEM_TYPE_ZABBIX,
					'value_type' => ITEM_VALUE_TYPE_TEXT,
					'delay' => self::ITEM_DELAY
				];
			}

			$response = $this->call('item.create', $items);
			$this->assertArrayHasKey('itemids', $response['result']);
			$this->assertCount(count($items), $response['result']['itemids']);

			foreach (self::$items as $i => $item) {
				self::$itemids[$component.':'.$item['key']] = $response['result']['itemids'][$i];
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
	}

	/**
	 * List every real disk-type block device on the host that has a working /dev node, together with
	 * the values vfs.dev.get is expected to report for it. Ground truth comes straight from lsblk and
	 * sysfs, the same sources vfs.dev.get itself relies on.
	 *
	 * lsblk is run with --all: by default lsblk excludes RAM disks (major number 1) outright and also
	 * hides other "empty" devices (no partition table, filesystem, or LVM signature) - a RAM disk set
	 * up purely to give this suite something to test against in CI is hit by the first of those, so
	 * without --all it would silently vanish from lsblk's own output, before the disk-type filter
	 * below even sees it.
	 *
	 * @return array  device name => ['type' => .., 'path' => .., 'size_bytes' => .., ..,
	 *                'partitions' => [partition name => size_bytes]]
	 */
	private static function listBlockDevices() {
		$disks = [];

		$names = trim((string) shell_exec("lsblk --all -dnlo NAME,TYPE | awk '\$2 == \"disk\" {print \$1}'"));

		foreach (($names === '') ? [] : explode("\n", $names) as $name) {
			$name = trim($name);

			if ($name === ''
					|| trim((string) shell_exec('[ -b '.escapeshellarg('/dev/'.$name).' ] && echo yes')) !== 'yes') {
				continue;
			}

			// Same source as the agents: lsblk also reports device-mapper children (kpartx) as "part".
			$partitions = [];

			foreach (glob('/sys/class/block/'.$name.'/*/partition') ?: [] as $path) {
				$partition = basename(dirname($path));
				$partitions[$partition] = self::readSysfsSizeBytes($partition);
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

		// Stored values are read by later tests after the components stop.
		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			foreach (self::$items as $item) {
				$this->getItemResult($component, $item['key']);
			}
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
	 * @return array  item.get result row (lastvalue, lastclock, state, error)
	 */
	private function getItemResult($component, $key) {
		$itemid = self::$itemids[$component.':'.$key];

		$response = $this->callUntilDataIsPresent('item.get', [
			'output' => ['lastvalue', 'lastclock', 'state', 'error'],
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
			$component.':'.$key.' is unexpectedly unsupported: '.$item['error']
		);

		$json = json_decode($item['lastvalue'], true);
		$this->assertIsArray($json, $component.':'.$key.' did not return a JSON object: '.$item['lastvalue']);

		return $json;
	}

	/**
	 * Find the entry for a device inside a vfs.dev.get "config" or "values" array.
	 *
	 * @param array  $entries
	 * @param string $name
	 *
	 * @return array|null
	 */
	private static function findEntryByName(array $entries, $name) {
		foreach ($entries as $entry) {
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
				$found = self::findEntryByName($json['config'], $name);
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
			$found = self::findEntryByName($json['config'], $name);
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

			$this->assertArrayHasKey('config', $json, $component.':'.$key);
			$this->assertArrayHasKey('values', $json, $component.':'.$key);

			foreach (self::$disks as $name => $disk) {
				$this->checkStatsEntries($json, $component, $key, $name, 'disk', $disk['size_bytes']);

				foreach ($disk['partitions'] as $partition => $size) {
					if ($key === 'vfs.dev.get[disk_stats]') {
						foreach (['config', 'values'] as $section) {
							$this->assertNull(self::findEntryByName($json[$section], $partition),
									'Partition "'.$partition.'" must not be in '.$section.' of '.$component.':'.$key
							);
						}
					}
					// The agents discover stats devices in /dev, unlike the partitions of "devices" mode.
					elseif (file_exists('/dev/'.$partition)) {
						$this->checkStatsEntries($json, $component, $key, $partition, 'partition', $size);
					}
				}
			}
		}
	}

	/**
	 * Check the "config" and "values" entries of a device in a stats mode result.
	 *
	 * @param array    $json
	 * @param string   $component
	 * @param string   $key
	 * @param string   $name
	 * @param string   $type
	 * @param int|null $size_bytes  expected size, null if it could not be read from sysfs
	 */
	private function checkStatsEntries(array $json, $component, $key, $name, $type, $size_bytes) {
		$config = self::findEntryByName($json['config'], $name);
		$this->assertIsArray($config, 'Device "'.$name.'" is missing from config of '.$component.':'.$key);
		$this->assertEquals($type, $config['type'], $component.':'.$key.' type for "'.$name.'"');

		if ($size_bytes !== null) {
			$this->assertEquals($size_bytes, $config['size_bytes'],
					$component.':'.$key.' size_bytes for "'.$name.'"'
			);
		}

		$value = self::findEntryByName($json['values'], $name);
		$this->assertIsArray($value, 'Device "'.$name.'" is missing from values of '.$component.':'.$key);
		$this->assertArrayHasKey('stats', $value, $component.':'.$key);

		foreach (['reads_completed', 'bytes_read', 'writes_completed', 'bytes_written', 'io_time_ms'] as $field) {
			$this->assertArrayHasKey($field, $value['stats'],
					'Field "'.$field.'" is missing from '.$component.':'.$key
			);
			$this->assertIsNumeric($value['stats'][$field],
					'Field "'.$field.'" is not numeric for '.$component.':'.$key
			);
			$this->assertGreaterThanOrEqual(0, $value['stats'][$field],
					'Field "'.$field.'" is negative for '.$component.':'.$key
			);
		}
	}

	/**
	 * The second parameter must limit the result to the devices with a matching name.
	 *
	 * @depends testVfsDevGet_checkDataCollection
	 * @dataProvider componentsDataProvider
	 */
	public function testVfsDevGet_devnames($component) {
		$key = self::$devnames_key;
		$json = $this->decodeItemValue($this->getItemResult($component, $key), $component, $key);

		$this->assertArrayHasKey('config', $json, $component.':'.$key);
		$this->assertEquals([array_key_first(self::$disks)], array_column($json['config'], 'name'),
				$component.':'.$key
		);

		$key = 'vfs.dev.get[disks,"^zbx_no_such_device$"]';
		$json = $this->decodeItemValue($this->getItemResult($component, $key), $component, $key);

		$this->assertArrayHasKey('config', $json, $component.':'.$key);
		$this->assertEmpty($json['config'], $component.':'.$key);
	}

	/**
	 * Data provider of invalid parameters for the two components under test.
	 *
	 * @return array
	 */
	public function invalidParametersDataProvider() {
		$data = [];

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			$data[] = [$component, 'vfs.dev.get[bogus_mode]', 'invalid first parameter'];
			$data[] = [$component, 'vfs.dev.get[disks,"("]', 'invalid regular expression in second parameter'];
			$data[] = [$component, 'vfs.dev.get[disks,,extra]', 'too many parameters'];
		}

		return $data;
	}

	/**
	 * Invalid parameters must be reported as an unsupported item, on both agent and agent2.
	 *
	 * @depends testVfsDevGet_checkDataCollection
	 * @dataProvider invalidParametersDataProvider
	 */
	public function testVfsDevGet_invalidParameters($component, $key, $error) {
		$item = $this->getItemResult($component, $key);

		$this->assertEquals(ITEM_STATE_NOTSUPPORTED, $item['state'], $component.':'.$key.
				' should be unsupported, got value: '.$item['lastvalue']
		);
		$this->assertStringContainsStringIgnoringCase($error, $item['error'],
				$component.':'.$key.' failed for an unexpected reason: '.$item['error']
		);
	}
}

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
 * Test suite for the vfs.fs.get item key with mode and mountpoint parameters in zabbix_agentd and zabbix_agent2.
 *
 * @required-components server, agent, agent2
 * @configurationDataProvider agentConfigurationProvider
 * @hosts agentd, agent2
 * @backup history
 */
class testVfsFsGet extends CIntegrationTest {

	/**
	 * @var array
	 */
	private static $hostids = [];

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
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

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $i => $name) {
			$this->assertArrayHasKey($i, $response['result']['hostids']);
			self::$hostids[$name] = $response['result']['hostids'][$i];
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
				'ServerActive' => '127.0.0.1:'.PHPUNIT_PORT_PREFIX.self::SERVER_PORT_SUFFIX
			],
			self::COMPONENT_AGENT2 => [
				'Hostname' => self::COMPONENT_AGENT2,
				'ServerActive' => '127.0.0.1:'.PHPUNIT_PORT_PREFIX.self::SERVER_PORT_SUFFIX,
				'ListenPort' => PHPUNIT_PORT_PREFIX.self::AGENT2_PORT_SUFFIX,
				'Plugins.Uptime.Capacity' => '10'
			]
		];
	}

	/**
	 * Positive test cases for vfs.fs.get.
	 *
	 * @var array
	 */
	private static $positiveCases = [
		// Backward-compatible full aliases
		[
			'key' => 'vfs.fs.get',
			'mode' => 'full',
			'unfiltered' => true
		],
		[
			'key' => 'vfs.fs.get[full]',
			'mode' => 'full',
			'unfiltered' => true
		],
		[
			'key' => 'vfs.fs.get[,]',
			'mode' => 'full',
			'unfiltered' => true
		],
		[
			'key' => 'vfs.fs.get[full,]',
			'mode' => 'full',
			'unfiltered' => true
		],

		// Unfiltered short mode
		[
			'key' => 'vfs.fs.get[short]',
			'mode' => 'short',
			'unfiltered' => true
		],
		[
			'key' => 'vfs.fs.get[short,]',
			'mode' => 'short',
			'unfiltered' => true
		],

		// Filtered short mode (existing mountpoints)
		[
			'key' => 'vfs.fs.get[short,/]',
			'mode' => 'short',
			'mountpoint' => '/',
			'expected_count' => 1
		],
		[
			'key' => 'vfs.fs.get[short,/proc]',
			'mode' => 'short',
			'mountpoint' => '/proc',
			'expected_count' => 1
		],
		[
			'key' => 'vfs.fs.get[short,/sys]',
			'mode' => 'short',
			'mountpoint' => '/sys',
			'expected_count' => 1
		],

		// Filtered full mode (existing mountpoints)
		[
			'key' => 'vfs.fs.get[full,/]',
			'mode' => 'full',
			'mountpoint' => '/',
			'expected_count' => 1
		],
		[
			'key' => 'vfs.fs.get[full,/proc]',
			'mode' => 'full',
			'mountpoint' => '/proc',
			'expected_count' => 1
		],
		[
			'key' => 'vfs.fs.get[full,/sys]',
			'mode' => 'full',
			'mountpoint' => '/sys',
			'expected_count' => 1
		],

		// Filtered empty mode (semantically full mode)
		[
			'key' => 'vfs.fs.get[,/]',
			'mode' => 'full',
			'mountpoint' => '/',
			'expected_count' => 1
		],
		[
			'key' => 'vfs.fs.get[,/proc]',
			'mode' => 'full',
			'mountpoint' => '/proc',
			'expected_count' => 1
		],
		[
			'key' => 'vfs.fs.get[,/sys]',
			'mode' => 'full',
			'mountpoint' => '/sys',
			'expected_count' => 1
		],

		// Nonexistent mountpoint
		[
			'key' => 'vfs.fs.get[short,/this_should_not_exist]',
			'mode' => 'short',
			'mountpoint' => '/this_should_not_exist',
			'nonexistent' => true
		],
		[
			'key' => 'vfs.fs.get[full,/this_should_not_exist]',
			'mode' => 'full',
			'mountpoint' => '/this_should_not_exist',
			'nonexistent' => true
		]
	];

	/**
	 * Negative test cases for vfs.fs.get (expected to become unsupported).
	 *
	 * @var array
	 */
	private static $negativeCases = [
		[
			'key' => 'vfs.fs.get[invalid]',
			'expected_state' => ITEM_STATE_NOTSUPPORTED
		],
		[
			'key' => 'vfs.fs.get[full,/,extra]',
			'expected_state' => ITEM_STATE_NOTSUPPORTED
		],
		[
			'key' => 'vfs.fs.get[short,/,extra]',
			'expected_state' => ITEM_STATE_NOTSUPPORTED
		]
	];

	/**
	 * Test positive vfs.fs.get cases.
	 *
	 * @dataProvider getPositiveCases
	 */
	public function testVfsFsGetPositive($case) {
		$key = $case['key'];

		$agentdResult = $this->getItemValue($key, self::COMPONENT_AGENT);
		$this->validatePositiveResult($key, $agentdResult, 'agentd', $case);

		$agent2Result = $this->getItemValue($key, self::COMPONENT_AGENT2);
		$this->validatePositiveResult($key, $agent2Result, 'agent2', $case);
	}

	/**
	 * Test negative vfs.fs.get cases (unsupported).
	 *
	 * @dataProvider getNegativeCases
	 */
	public function testVfsFsGetUnsupported($case) {
		$key = $case['key'];

		$agentdState = $this->getUnsupportedItemState($key, self::COMPONENT_AGENT);
		$this->validateUnsupportedResult($key, $agentdState, 'agentd', $case);

		$agent2State = $this->getUnsupportedItemState($key, self::COMPONENT_AGENT2);
		$this->validateUnsupportedResult($key, $agent2State, 'agent2', $case);
	}

	/**
	 * Data provider for positive cases.
	 *
	 * @return array
	 */
	public function getPositiveCases() {
		$cases = [];
		foreach (self::$positiveCases as $case) {
			$cases[] = [$case];
		}
		return $cases;
	}

	/**
	 * Data provider for negative cases.
	 *
	 * @return array
	 */
	public function getNegativeCases() {
		$cases = [];
		foreach (self::$negativeCases as $case) {
			$cases[] = [$case];
		}
		return $cases;
	}

	/**
	 * Get item value from agent (positive cases only).
	 *
	 * @param string $key
	 * @param string $component
	 *
	 * @return string
	 */
	private function getItemValue($key, $component) {
		if (!array_key_exists($component, self::$hostids)) {
			$this->fail("Unknown component: $component");
		}

		$hostid = self::$hostids[$component];

		$response = $this->call('item.create', [
			[
				'hostid'      => $hostid,
				'interfaceid' => 0,
				'type'        => ITEM_TYPE_ZABBIX_ACTIVE,
				'value_type'  => ITEM_VALUE_TYPE_TEXT,
				'delay'       => '1s',
				'key_'        => $key,
				'name'        => 'vfs.fs.get test: '.$key
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$itemid = $response['result']['itemids'][0];

		try {
			$value = $this->pollHistoryForValue($itemid);
			return $value;
		} finally {
			$this->call('item.delete', [$itemid]);
		}
	}

	/**
	 * Poll history.get for a value with bounded retries.
	 *
	 * @param string $itemid
	 *
	 * @return string
	 */
	private function pollHistoryForValue($itemid) {
		for ($i = 0; $i < self::WAIT_ITERATIONS; $i++) {
			$result = $this->call('history.get', [
				'output'      => ['itemid', 'value', 'clock', 'ns'],
				'itemids'     => [$itemid],
				'history'     => ITEM_VALUE_TYPE_TEXT,
				'sortfield'   => ['clock', 'ns'],
				'sortorder'   => ZBX_SORT_DOWN,
				'limit'       => 1
			]);

			if (!empty($result['result']) && isset($result['result'][0]['value'])) {
				return $result['result'][0]['value'];
			}

			sleep(self::WAIT_ITERATION_DELAY);
		}

		$this->fail("No history value collected for itemid $itemid after ".self::WAIT_ITERATIONS." iterations");
	}

	/**
	 * Get item state for unsupported cases (negative cases only).
	 *
	 * @param string $key
	 * @param string $component
	 *
	 * @return int
	 */
	private function getUnsupportedItemState($key, $component) {
		if (!array_key_exists($component, self::$hostids)) {
			$this->fail("Unknown component: $component");
		}

		$hostid = self::$hostids[$component];

		$response = $this->call('item.create', [
			[
				'hostid'      => $hostid,
				'interfaceid' => 0,
				'type'        => ITEM_TYPE_ZABBIX_ACTIVE,
				'value_type'  => ITEM_VALUE_TYPE_TEXT,
				'delay'       => '1s',
				'key_'        => $key,
				'name'        => 'vfs.fs.get test: '.$key
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$itemid = $response['result']['itemids'][0];

		try {
			$lastState = null;

			for ($i = 0; $i < self::WAIT_ITERATIONS; $i++) {
				$result = $this->call('item.get', [
					'itemids' => [$itemid],
					'output'  => ['state']
				]);

				if (!empty($result['result']) && isset($result['result'][0]['state'])) {
					$lastState = (int)$result['result'][0]['state'];
					if ($lastState === ITEM_STATE_NOTSUPPORTED) {
						return $lastState;
					}
				}

				sleep(self::WAIT_ITERATION_DELAY);
			}

			$lastStateText = $lastState === null ? 'unknown' : $lastState;
			$this->fail("$component: $key - Item did not become unsupported after ".self::WAIT_ITERATIONS." iterations. Last observed state: $lastStateText");
		} finally {
			$this->call('item.delete', [$itemid]);
		}
	}

	/**
	 * Validate positive result for a specific key.
	 *
	 * @param string $key
	 * @param string $value
	 * @param string $agentName
	 * @param array  $case
	 */
	private function validatePositiveResult($key, $value, $agentName, $case) {
		$json = json_decode($value, true);

		$jsonError = json_last_error();
		$jsonErrorMessage = json_last_error_msg();
		if ($jsonError !== JSON_ERROR_NONE) {
			$this->fail("$agentName: $key - Invalid JSON response: $jsonErrorMessage");
		}

		if ($json === null) {
			$this->fail("$agentName: $key - Response is null, expected JSON array");
		}

		$this->assertIsArray($json, "$agentName: $key - Response is not a JSON array");

		if (!empty($case['nonexistent'])) {
			$this->assertSame([], $json, "$agentName: $key - Nonexistent mountpoint should return []");
			return;
		}

		if (!empty($case['unfiltered'])) {
			$this->assertNotEmpty($json, "$agentName: $key - Unfiltered result must not be empty");
		}

		if ($case['mode'] === 'short') {
			$this->validateShortMode($key, $json, $agentName);
		} else {
			$this->validateFullMode($key, $json, $agentName);
		}

		if (!empty($case['mountpoint']) && empty($case['nonexistent']) && empty($case['unfiltered'])) {
			$this->assertCount($case['expected_count'], $json, "$agentName: $key - Mountpoint filter should return exactly {$case['expected_count']} item(s)");
			$this->assertEquals($case['mountpoint'], $json[0]['fsname'] ?? '', "$agentName: $key - Mountpoint filter returned wrong fsname");
		}
	}

	/**
	 * Validate unsupported result for a specific key.
	 *
	 * @param string $key
	 * @param int    $state
	 * @param string $agentName
	 * @param array  $case
	 */
	private function validateUnsupportedResult($key, $state, $agentName, $case) {
		$this->assertEquals($case['expected_state'], $state, "$agentName: $key - Expected state {$case['expected_state']}, got $state");
	}

	/**
	 * Validate short mode response.
	 *
	 * @param string $key
	 * @param array  $json
	 * @param string $agentName
	 */
	private function validateShortMode($key, $json, $agentName) {
		foreach ($json as $item) {
			$this->assertArrayHasKey('fsname', $item, "$agentName: $key - Short mode item missing fsname");
			$this->assertArrayHasKey('fstype', $item, "$agentName: $key - Short mode item missing fstype");
			$this->assertArrayHasKey('options', $item, "$agentName: $key - Short mode item missing options");
			$this->assertArrayNotHasKey('bytes', $item, "$agentName: $key - Short mode should not contain bytes");
			$this->assertArrayNotHasKey('inodes', $item, "$agentName: $key - Short mode should not contain inodes");
		}
	}

	/**
	 * Validate full mode response.
	 *
	 * @param string $key
	 * @param array  $json
	 * @param string $agentName
	 */
	private function validateFullMode($key, $json, $agentName) {
		foreach ($json as $item) {
			$this->assertArrayHasKey('fsname', $item, "$agentName: $key - Full mode item missing fsname");
			$this->assertArrayHasKey('fstype', $item, "$agentName: $key - Full mode item missing fstype");
			$this->assertArrayHasKey('bytes', $item, "$agentName: $key - Full mode item missing bytes");
			$this->assertArrayHasKey('options', $item, "$agentName: $key - Full mode item missing options");

			$bytes = $item['bytes'];
			$this->assertIsArray($bytes, "$agentName: $key - Full mode bytes is not an object");
			$this->assertArrayHasKey('total', $bytes, "$agentName: $key - Full mode bytes missing total");
			$this->assertArrayHasKey('free', $bytes, "$agentName: $key - Full mode bytes missing free");
			$this->assertArrayHasKey('used', $bytes, "$agentName: $key - Full mode bytes missing used");
			$this->assertArrayHasKey('pfree', $bytes, "$agentName: $key - Full mode bytes missing pfree");
			$this->assertArrayHasKey('pused', $bytes, "$agentName: $key - Full mode bytes missing pused");

			if (isset($item['inodes'])) {
				$inodes = $item['inodes'];
				$this->assertIsArray($inodes, "$agentName: $key - Full mode inodes is not an object");
				$this->assertArrayHasKey('total', $inodes, "$agentName: $key - Full mode inodes missing total");
				$this->assertArrayHasKey('free', $inodes, "$agentName: $key - Full mode inodes missing free");
				$this->assertArrayHasKey('used', $inodes, "$agentName: $key - Full mode inodes missing used");
				$this->assertArrayHasKey('pfree', $inodes, "$agentName: $key - Full mode inodes missing pfree");
				$this->assertArrayHasKey('pused', $inodes, "$agentName: $key - Full mode inodes missing pused");
			}
		}
	}

	/**
	 * Test backward compatibility of vfs.fs.get aliases.
	 *
	 * Verifies that equivalent forms produce equivalent filesystem metadata and JSON structure.
	 */
	public function testVfsFsGetBackwardCompatibility() {
		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			$componentName = $component === self::COMPONENT_AGENT ? 'agentd' : 'agent2';

			$unfilteredAliases = [
				'vfs.fs.get',
				'vfs.fs.get[full]',
				'vfs.fs.get[,]',
				'vfs.fs.get[full,]'
			];

			$unfilteredResults = [];
			foreach ($unfilteredAliases as $key) {
				$value = $this->getItemValue($key, $component);
				$json = $this->decodeJsonResult($key, $value, $componentName);
				$unfilteredResults[$key] = $this->normalizeFsResult($json);
			}

			$firstKey = $unfilteredAliases[0];
			foreach (array_slice($unfilteredAliases, 1) as $key) {
				$this->assertEquals(
					$unfilteredResults[$firstKey],
					$unfilteredResults[$key],
					"$componentName: Backward compatibility mismatch between '$firstKey' and '$key'"
				);
			}

			$rootAliases = [
				'vfs.fs.get[full,/]',
				'vfs.fs.get[,/]'
			];

			$rootResults = [];
			foreach ($rootAliases as $key) {
				$value = $this->getItemValue($key, $component);
				$json = $this->decodeJsonResult($key, $value, $componentName);
				$rootResults[$key] = $this->normalizeFsResult($json);
			}

			$firstRootKey = $rootAliases[0];
			$secondRootKey = $rootAliases[1];
			$this->assertEquals(
				$rootResults[$firstRootKey],
				$rootResults[$secondRootKey],
				"$componentName: Backward compatibility mismatch between '$firstRootKey' and '$secondRootKey'"
			);

			foreach ($rootResults as $key => $result) {
				$this->assertCount(1, $result, "$componentName: $key - Root filter should return exactly 1 item");
				$this->assertEquals('/', $result[0]['fsname'], "$componentName: $key - Root filter fsname should be '/'");
				$this->assertArrayHasKey('has_bytes', $result[0], "$componentName: $key - Normalized root result is missing has_bytes");
				$this->assertTrue($result[0]['has_bytes'], "$componentName: $key - Root filter should have bytes");
				$this->assertArrayHasKey('options', $result[0], "$componentName: $key - Root filter should have options");
			}
		}
	}

	/**
	 * Decode JSON result with strict validation.
	 *
	 * @param string $key
	 * @param string $value
	 * @param string $componentName
	 *
	 * @return array
	 */
	private function decodeJsonResult($key, $value, $componentName) {
		$json = json_decode($value, true);

		$jsonError = json_last_error();
		$jsonErrorMessage = json_last_error_msg();
		if ($jsonError !== JSON_ERROR_NONE) {
			$this->fail("$componentName: $key - Invalid JSON response: $jsonErrorMessage");
		}

		if ($json === null) {
			$this->fail("$componentName: $key - Response is null, expected JSON array");
		}

		$this->assertIsArray($json, "$componentName: $key - Response is not a JSON array");

		return $json;
	}

	/**
	 * Normalize filesystem result for comparison.
	 *
	 * Keeps only stable fields: fsname, fstype, options, presence of bytes/inodes.
	 * Excludes volatile numeric values inside bytes and inodes.
	 *
	 * @param array $json
	 *
	 * @return array
	 */
	private function normalizeFsResult($json) {
		$normalized = [];
		foreach ($json as $item) {
			$normalizedItem = [
				'fsname'     => $item['fsname'] ?? '',
				'fstype'     => $item['fstype'] ?? '',
				'options'    => $item['options'] ?? '',
				'has_bytes'  => isset($item['bytes']),
				'has_inodes' => isset($item['inodes'])
			];
			$normalized[] = $normalizedItem;
		}

		usort($normalized, function ($a, $b) {
			$cmp = strcmp($a['fsname'], $b['fsname']);
			if ($cmp !== 0) {
				return $cmp;
			}
			$cmp = strcmp($a['fstype'], $b['fstype']);
			if ($cmp !== 0) {
				return $cmp;
			}
			return strcmp($a['options'], $b['options']);
		});

		return $normalized;
	}
}

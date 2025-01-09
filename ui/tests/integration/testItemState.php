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
 * Test suite for items state change verification.
 *
 * @required-components server, agent
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_host
 * @backup history
 */
class testItemState extends CIntegrationTest {

	const REFRESH_ACT_CHKS_INTERVAL = 60;
	const PROCESS_ACT_CHKS_DELAY = 60;
	const LOG_LINE_WAIT_TIME	 = 30;
	const PSV_FILE_NAME = '/tmp/some_temp_file_psv';
	const ACT_FILE_NAME = '/tmp/some_temp_file_act';

	private static $hostid;
	private static $interfaceid;

	private static $items = [
		'zbx_psv_01' => [
			'key' => 'vfs.file.contents['.self::PSV_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX
		],
		'zbx_act_01' => [
			'key' => 'vfs.file.contents['.self::ACT_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE
		]
	];

	private static $scenarios = [
		[
			'name' => 'zbx_psv_01',
			'delay_s' => 5
		],
		[
			'name' => 'zbx_act_01',
			'delay_s' => 5
		]
	];

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "test_host"
		$response = $this->call('host.create', [
			[
				'host' => 'test_host',
				'interfaces' => [
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_NOT_MONITORED
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		// Get host interface ids.
		$response = $this->call('host.get', [
			'output' => ['host'],
			'hostids' => [self::$hostid],
			'selectInterfaces' => ['interfaceid']
		]);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('interfaces', $response['result'][0]);
		$this->assertArrayHasKey(0, $response['result'][0]['interfaces']);
		self::$interfaceid = $response['result'][0]['interfaces'][0]['interfaceid'];

		// Create items
		foreach (self::$items as $key => $item) {
			$new_item = [
				'name' => $key,
				'key_' => $item['key'],
				'type' => $item['type'],
				'hostid' => self::$hostid,
				'interfaceid' => self::$interfaceid,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => '1s',
				'status' => ITEM_STATUS_DISABLED
			];

			if ($new_item['type'] == ITEM_TYPE_ZABBIX_ACTIVE) {
				$new_item['interfaceid'] = 0;
			} else {
				$new_item['interfaceid'] = self::$interfaceid;
			}

			$items[] = $new_item;
		}

		$response = $this->call('item.create', $items);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(count($items), count($response['result']['itemids']));
		$itemids = $response['result']['itemids'];
		$id = 0;

		foreach (self::$items as &$item) {
			$item['itemid'] = $itemids[$id++];
		}

		$this->assertTrue(@file_put_contents(self::PSV_FILE_NAME, '1') !== false);
		$this->assertTrue(@file_put_contents(self::ACT_FILE_NAME, '1') !== false);

		return true;
	}

	/**
	 * Component configuration provider for agent related tests.
	 *
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'ListenPort' => self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort', 10051)
			],
			self::COMPONENT_AGENT => [
				'Hostname' => 'test_host',
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort', 10051),
				'RefreshActiveChecks' => self::REFRESH_ACT_CHKS_INTERVAL,
				'BufferSend' => 1
			]
		];
	}

	/**
	 * Get timestamp of log last line.
	 *
	 * @param string  $line       log line
	 *
	 * @return integer|false
	 */
	protected function getTimestamp($line) {
		$matches = [];
		$regex = '/\d+:(\d+:\d+.\d+)/';

		if (preg_match($regex, $line, $matches) === 1) {
			if ($matches[1]) {
				$ts = DateTime::createFromFormat('Ymd:Gis.u', $matches[1]);
				return $ts->format('U');
			}
		}

		return false;
	}

	/**
	 * Wait until line is present in log.
	 *
	 * @param string       $component     name of the component
	 * @param string|array $lines         line(s) to look for
	 * @param integer      $iterations    iteration count
	 *
	 * @return integer
	 *
	 * @throws Exception    on failed wait or if not able to retrieve timestamp
	 */
	protected function getLogLineTimestamp($component, $lines, $iterations = null) {
		if ($iterations === null) {
			$iterations = self::LOG_LINE_WAIT_TIME;
		}

		for ($r = 0; $r < $iterations; $r++) {
			$log_content = CLogHelper::readLogUntil(self::getLogPath($component), $lines);

			if ($log_content !== null) {
				$log_content = $this->getTimestamp(strrchr(rtrim($log_content, "\n"), "\n"));

				if ($log_content === false) {
					throw new Exception('Failed to get timestamp of the log line');
				}

				return $log_content;
			}

			sleep(1);
		}

		if (is_array($lines)) {
			$quoted = [];
			foreach ($lines as $line) {
				$quoted[] = '"'.$line.'"';
			}

			$description = 'any of the lines ['.implode(', ', $quoted).']';
		}
		else {
			$description = 'line "'.$lines.'"';
		}

		throw new Exception('Failed to wait for '.$description.' to be present in '.$component.' log file.');
	}

	/**
	 * Routine to prepare item.
	 */
	protected function prepareItem($itemid, $delay) {
		// Disable all items
		foreach (self::$items as $item) {
			if ($item['itemid'] == $itemid) {
				$items[] = [
					'itemid' => $itemid,
					'status' => ITEM_STATUS_ACTIVE,
					'delay' => $delay
				];
			} else {
				$items[] = [
					'itemid' => $item['itemid'],
					'status' => ITEM_STATUS_DISABLED
				];
			}
		}

		$response = $this->call('item.update', $items);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(count($items), count($response['result']['itemids']));
		$this->reloadConfigurationCache();

		// Clear log
		$this->clearLog(self::COMPONENT_SERVER);
	}

	/**
	 * Routine to check item state and intervals.
	 */
	protected function checkItemStatePassive($scenario, $state) {
		$delay = $scenario['delay_s'];

		$wait = $delay + self::LOG_LINE_WAIT_TIME;
		$key = self::$items[$scenario['name']]['key'];

		// Wait for item to be checked
		$first_check = $this->getLogLineTimestamp(self::COMPONENT_SERVER, ["In process_async_result() key:'".$key."'"], $wait);

		// Wait for item state to be flushed (once per second in preprocessing manager and in poller)
		sleep(2);

		$response = $this->call('item.get', [
			'itemids' => self::$items[$scenario['name']]['itemid'],
			'output' => ['state']
		]);

		$this->assertEquals($state, $response['result'][0]['state'], 'Unexpected item state='.
				$response['result'][0]['state'].' (expected='.$state.')'
		);

		// Verify item checks intervals
		$check = $this->getLogLineTimestamp(self::COMPONENT_SERVER, ["In process_async_result() key:'".$key."'"], $wait);
		$this->assertTrue($check <= $first_check + $delay + 1);

		$next_check = $this->getLogLineTimestamp(self::COMPONENT_SERVER, ["In process_async_result() key:'".$key."'"], $wait);
		$this->assertTrue($next_check <= $check + $delay + 1 && $next_check >= $check + $delay - 1);
	}

	/**
	 * Routine to check item state and intervals (active agent items).
	 */
	protected function checkItemStateActive($scenario, $state, &$refresh) {
		$wait = max($scenario['delay_s'], self::REFRESH_ACT_CHKS_INTERVAL) + self::PROCESS_ACT_CHKS_DELAY
			+ self::LOG_LINE_WAIT_TIME;
		$itemid = self::$items[$scenario['name']]['itemid'];

		// Wait for first check that happens right after configuration refresh
		$check = $this->getLogLineTimestamp(self::COMPONENT_SERVER,
				[',"data":[{"itemid":'.$itemid.',"value":"'], $wait
		);

		// Wait for first scheduled check
		$check = $this->getLogLineTimestamp(self::COMPONENT_SERVER,
				[',"data":[{"itemid":'.$itemid.',"value":"'], $wait
		);

		// Update last refresh timestamp
		while ($check > $refresh + self::REFRESH_ACT_CHKS_INTERVAL) {
			$refresh += self::REFRESH_ACT_CHKS_INTERVAL;
		}

		// Check item state and read update interval
		sleep(1);

		$response = $this->call('item.get', [
			'itemids' => self::$items[$scenario['name']]['itemid'],
			'output' => ['state']
		]);

		$this->assertEquals($state, $response['result'][0]['state'],
				'Unexpected item state='.$response['result'][0]['state'].' (expected='.$state.')'
		);

		// Wait for next scheduled check and verify item checks intervals
		$next_check = $this->getLogLineTimestamp(self::COMPONENT_SERVER,
				[',"data":[{"itemid":'.$itemid.',"value":"'], $wait
		);

		while ($next_check > $refresh + self::REFRESH_ACT_CHKS_INTERVAL) {
			$refresh += self::REFRESH_ACT_CHKS_INTERVAL;
		}

		$this->assertTrue($next_check <= $check + $scenario['delay_s'] + 1
				&& $next_check >= $check + $scenario['delay_s'] - 1
		);

		return $refresh;
	}

	/**
	 * Function to get scenarios by type.
	 *
	 * @param integer      $type     type
	 *
	 * @return array
	 */
	protected function getScenariosByType($type) {
		$scenarios = [];

		foreach (self::$scenarios as $scenario) {
			if (self::$items[$scenario['name']]['type'] === $type) {
				$scenarios[] = [$scenario];
			}
		}

		return $scenarios;
	}

	/**
	 * Data provider (passive checks).
	 *
	 * @return array
	 */
	public function getDataPassive() {
		return $this->getScenariosByType(ITEM_TYPE_ZABBIX);
	}

	/**
	 * Data provider (active checks).
	 *
	 * @return array
	 */
	public function getDataActive() {
		return $this->getScenariosByType(ITEM_TYPE_ZABBIX_ACTIVE);
	}

	/**
	 * Test if item becomes supported/not supported within expected time span (passive checks).
	 *
	 * @dataProvider getDataPassive
	 */
	public function testItemState_checkPassive($data) {
		// Prepare item
		$this->prepareItem(self::$items[$data['name']]['itemid'], $data['delay_s'].'s');

		// Check item state and intervals
		$this->checkItemStatePassive($data, ITEM_STATE_NORMAL);

		// Make item not supported
		$this->assertTrue(@unlink(self::PSV_FILE_NAME) !== false);

		// Check item state and intervals
		$this->checkItemStatePassive($data, ITEM_STATE_NOTSUPPORTED);

		// Make item supported
		$this->assertTrue(@file_put_contents(self::PSV_FILE_NAME, '1') !== false);

		// Check item state and intervals
		$this->checkItemStatePassive($data, ITEM_STATE_NORMAL);
	}

	/**
	 * Test if item becomes supported/not supported within expected time span (active checks).
	 *
	 * @dataProvider getDataActive
	 */
	public function testItemState_checkActive($data) {
		// Prepare item
		$this->prepareItem(self::$items[$data['name']]['itemid'], $data['delay_s'].'s');

		// Wait for the refresh active checks
		$refresh_active = $this->getLogLineTimestamp(self::COMPONENT_SERVER,
				['trapper got \'{"request":"active checks","host":"test_host"'],
				self::REFRESH_ACT_CHKS_INTERVAL + self::LOG_LINE_WAIT_TIME
		);

		// Check item state and intervals
		$this->checkItemStateActive($data, ITEM_STATE_NORMAL, $refresh_active);

		// Make item not supported
		$this->assertTrue(@unlink(self::ACT_FILE_NAME) !== false);

		// Check item state and intervals
		$this->checkItemStateActive($data, ITEM_STATE_NOTSUPPORTED, $refresh_active);

		// Make item supported
		$this->assertTrue(@file_put_contents(self::ACT_FILE_NAME, '1') !== false);

		// Check item state and intervals
		$this->checkItemStateActive($data, ITEM_STATE_NORMAL, $refresh_active);
	}
}

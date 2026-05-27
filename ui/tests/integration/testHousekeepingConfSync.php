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
 * @required-components server, proxy
 * @configurationDataProvider configurationProvider
 * @onAfter clearData
 */
class testHousekeepingConfSync extends CIntegrationTest {
	const PROXY_NAME = 'Housekeeping proxy';
	const HOSTNAME = 'Housekeeping host';
	const AGENT_PING_KEY = 'agent.ping';
	const TRIGGER_NAME = 'Housekeeping trigger';
	const HK_MODE_DISABLED = 0;
	const HK_MODE_REGULAR = 1;
	const HK_MODE_PARTITION = 2;

	private static $proxyid = null;
	private static $hostid = null;
	private static $agent_ping_itemid = null;
	private static $triggerid = null;
	private static $eventids = [];

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		$response = $this->call('host.create', [
			'host' => self::HOSTNAME,
			'groups' => [['groupid' => 4]],
			'status' => HOST_STATUS_MONITORED
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);

		self::$hostid = $response['result']['hostids'][0];

		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => 'Agent ping',
			'key_' => self::AGENT_PING_KEY,
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'delay' => '1h',
			'history' => '90d',
			'trends' => '0'
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);

		self::$agent_ping_itemid = $response['result']['itemids'][0];

		$response = $this->call('trigger.create', [
			'description' => self::TRIGGER_NAME,
			'expression' => 'last(/'.self::HOSTNAME.'/'.self::AGENT_PING_KEY.')=0'
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);

		self::$triggerid = $response['result']['triggerids'][0];

		$response = $this->call('proxy.create', [
			'name' => self::PROXY_NAME,
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
			'hosts' => [
				['hostid' => self::$hostid]
			]
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);
		$this->assertCount(1, $response['result']['proxyids']);
		self::$proxyid = $response['result']['proxyids'][0];

		return true;
	}

	/**
	 * Delete all data created by this test suite.
	 */
	public static function clearData(): void {
		if (self::$agent_ping_itemid !== null) {
			CDataHelper::call('history.clear', [self::$agent_ping_itemid]);
		}

		if (self::$eventids) {
			DB::delete('events', ['eventid' => self::$eventids]);
		}

		if (self::$triggerid !== null) {
			CDataHelper::call('trigger.delete', [self::$triggerid]);
		}

		if (self::$hostid !== null) {
			CDataHelper::call('host.delete', [self::$hostid]);
		}

		if (self::$proxyid !== null) {
			CDataHelper::call('proxy.delete', [self::$proxyid]);
		}

		CDataHelper::call('housekeeping.update', self::defaultHousekeeping());
	}

	/**
	 * Component configuration provider.
	 *
	 * @return array
	 */
	public function configurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 5,
				'LogFileSize' => 20
			],
			self::COMPONENT_PROXY => [
				'DebugLevel' => 5,
				'LogFileSize' => 20,
				'Hostname' => self::PROXY_NAME,
				'ProxyMode' => PROXY_OPERATING_MODE_ACTIVE,
				'ListenPort' => PHPUNIT_PORT_PREFIX.self::PROXY_PORT_SUFFIX
			]
		];
	}

	/**
	 * Extract housekeeping settings dumped by DCdump_config().
	 */
	private function extractSyncedHousekeeping($component) {
		$log = file_get_contents(self::getLogPath($component));
		$data = explode("\n", $log);
		$sync = preg_grep('/housekeeping:/', $data);
		$this->assertNotEmpty($sync);

		$pid = preg_quote(strtok(array_values($sync)[0], ':'), '/');
		$sync_idx = array_keys($sync)[0];
		$housekeeping = [];

		for ($x = $sync_idx + 1; $x < count($data); $x++) {
			if (!preg_match('/^'.$pid.'/', $data[$x])) {
				continue;
			}

			$line = preg_replace('/^\s*[0-9]+:[0-9]+:[0-9]+\.[0-9]+\s+/', '', $data[$x]);
			$events_pattern = '/events, mode:(\d+) period:\[trigger:(\d+) internal:(\d+) '.
				'autoreg:(\d+) discovery:(\d+)\]/';

			if (preg_match($events_pattern, $line, $matches)) {
				$housekeeping['hk_events_mode'] = (int) $matches[1];
				$housekeeping['hk_events_trigger'] = (int) $matches[2];
				$housekeeping['hk_events_internal'] = (int) $matches[3];
				$housekeeping['hk_events_autoreg'] = (int) $matches[4];
				$housekeeping['hk_events_discovery'] = (int) $matches[5];
			}
			else if (preg_match('/audit, mode:(\d+) period:(\d+)/', $line, $matches)) {
				$housekeeping['hk_audit_mode'] = (int) $matches[1];
				$housekeeping['hk_audit'] = (int) $matches[2];
			}
			else if (preg_match('/it services, mode:(\d+) period:(\d+)/', $line, $matches)) {
				$housekeeping['hk_services_mode'] = (int) $matches[1];
				$housekeeping['hk_services'] = (int) $matches[2];
			}
			else if (preg_match('/user sessions, mode:(\d+) period:(\d+)/', $line, $matches)) {
				$housekeeping['hk_sessions_mode'] = (int) $matches[1];
				$housekeeping['hk_sessions'] = (int) $matches[2];
			}
			else if (preg_match('/history, mode:(\d+) global:(\d+) period:(\d+)/', $line, $matches)) {
				$housekeeping['hk_history_mode'] = (int) $matches[1];
				$housekeeping['hk_history_global'] = (int) $matches[2];
				$housekeeping['hk_history'] = (int) $matches[3];
			}
			else if (preg_match('/trends, mode:(\d+) global:(\d+) period:(\d+)/', $line, $matches)) {
				$housekeeping['hk_trends_mode'] = (int) $matches[1];
				$housekeeping['hk_trends_global'] = (int) $matches[2];
				$housekeeping['hk_trends'] = (int) $matches[3];
			}
			else if (str_contains($line, 'default timezone')) {
				break;
			}
		}

		return $housekeeping;
	}

	private static function defaultHousekeeping() {
		return [
			'hk_events_mode' => 1,
			'hk_events_trigger' => '365d',
			'hk_events_internal' => '1d',
			'hk_events_discovery' => '1d',
			'hk_events_autoreg' => '1d',
			'hk_events_service' => '1d',
			'hk_services_mode' => 1,
			'hk_services' => '365d',
			'hk_sessions_mode' => 1,
			'hk_sessions' => '31d',
			'hk_audit_mode' => 1,
			'hk_audit' => '31d',
			'hk_history_mode' => 1,
			'hk_history_global' => 0,
			'hk_history' => '31d',
			'hk_trends_mode' => 1,
			'hk_trends_global' => 0,
			'hk_trends' => '365d'
		];
	}

	private function expectedServerHousekeeping(array $housekeeping) {
		return [
			'hk_events_mode' => $housekeeping['hk_events_mode'],
			'hk_events_trigger' => $this->timeToSeconds($housekeeping['hk_events_trigger']),
			'hk_events_internal' => $this->timeToSeconds($housekeeping['hk_events_internal']),
			'hk_events_autoreg' => $this->timeToSeconds($housekeeping['hk_events_autoreg']),
			'hk_events_discovery' => $this->timeToSeconds($housekeeping['hk_events_discovery']),
			'hk_audit_mode' => self::expectedPartitionableMode($housekeeping['hk_audit_mode']),
			'hk_audit' => $this->timeToSeconds($housekeeping['hk_audit']),
			'hk_services_mode' => $housekeeping['hk_services_mode'],
			'hk_services' => $this->timeToSeconds($housekeeping['hk_services']),
			'hk_sessions_mode' => $housekeeping['hk_sessions_mode'],
			'hk_sessions' => $this->timeToSeconds($housekeeping['hk_sessions']),
			'hk_history_mode' => $housekeeping['hk_history_global'] == self::HK_MODE_REGULAR
				? self::expectedPartitionableMode($housekeeping['hk_history_mode'])
				: $housekeeping['hk_history_mode'],
			'hk_history_global' => $housekeeping['hk_history_global'],
			'hk_history' => $this->timeToSeconds($housekeeping['hk_history']),
			'hk_trends_mode' => $housekeeping['hk_trends_global'] == self::HK_MODE_REGULAR
				? self::expectedPartitionableMode($housekeeping['hk_trends_mode'])
				: $housekeeping['hk_trends_mode'],
			'hk_trends_global' => $housekeeping['hk_trends_global'],
			'hk_trends' => $this->timeToSeconds($housekeeping['hk_trends'])
		];
	}

	private function assertHousekeepingEquals(array $expected, array $actual) {
		foreach ($expected as $name => $value) {
			$this->assertArrayHasKey($name, $actual);

			if (is_array($value)) {
				$this->assertContains($actual[$name], $value, 'Unexpected synced value for '.$name.'.');
			}
			else {
				$this->assertEquals($value, $actual[$name], 'Unexpected synced value for '.$name.'.');
			}
		}
	}

	/**
	 * Send agent.ping history through proxy using itemid-based agent data.
	 */
	private function sendAgentPing($clock) {
		$this->sendAgentDataValues([
			[
				'itemid' => self::$agent_ping_itemid,
				'value' => '1',
				'clock' => $clock,
				'ns' => (int)(microtime(true) * 1e9) % 1000000000
			]
		], self::HOSTNAME, self::COMPONENT_SERVER, 0, self::PROXY_NAME);
	}

	private function waitForHistoryCount($expected) {
		$this->callUntilDataIsPresent('history.get', [
			'itemids' => self::$agent_ping_itemid,
			'history' => ITEM_VALUE_TYPE_UINT64
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY,
			function ($response) use ($expected) {
				return array_key_exists('result', $response) && count($response['result']) == $expected;
			}
		);
	}

	private function createEventPairs($old_clock, $new_clock) {
		$events = [];
		$pairs = [
			[
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'objectid' => self::$triggerid,
				'value' => TRIGGER_VALUE_TRUE,
				'name' => 'Old trigger event'
			],
			[
				'source' => EVENT_SOURCE_INTERNAL,
				'object' => EVENT_OBJECT_TRIGGER,
				'objectid' => self::$triggerid,
				'value' => TRIGGER_STATE_UNKNOWN,
				'name' => 'Old internal trigger event'
			],
			[
				'source' => EVENT_SOURCE_INTERNAL,
				'object' => EVENT_OBJECT_ITEM,
				'objectid' => self::$agent_ping_itemid,
				'value' => ITEM_STATE_NOTSUPPORTED,
				'name' => 'Old internal item event'
			],
			[
				'source' => EVENT_SOURCE_DISCOVERY,
				'object' => EVENT_OBJECT_DHOST,
				'objectid' => 1,
				'value' => 1,
				'name' => 'Old discovery host event'
			],
			[
				'source' => EVENT_SOURCE_DISCOVERY,
				'object' => EVENT_OBJECT_DSERVICE,
				'objectid' => 1,
				'value' => 1,
				'name' => 'Old discovery service event'
			],
			[
				'source' => EVENT_SOURCE_AUTOREGISTRATION,
				'object' => EVENT_OBJECT_AUTOREGHOST,
				'objectid' => 1,
				'value' => 0,
				'name' => 'Old autoregistration event'
			],
			[
				'source' => EVENT_SOURCE_SERVICE,
				'object' => EVENT_OBJECT_SERVICE,
				'objectid' => 1,
				'value' => 1,
				'name' => 'Old service event'
			]
		];

		foreach ($pairs as $event) {
			foreach ([$old_clock, $new_clock] as $clock) {
				$events[] = $event + [
					'clock' => $clock,
					'ns' => 0,
					'severity' => TRIGGER_SEVERITY_NOT_CLASSIFIED,
					'acknowledged' => EVENT_NOT_ACKNOWLEDGED
				];
			}
		}

		$eventids = DB::insert('events', $events);
		self::$eventids = array_merge(self::$eventids, $eventids);

		$result = ['old' => [], 'new' => []];
		foreach ($eventids as $index => $eventid) {
			$result[$index % 2 == 0 ? 'old' : 'new'][] = $eventid;
		}

		return $result;
	}

	private function assertEventsCount(array $eventids, $expected) {
		$count = CDBHelper::getCount('SELECT NULL FROM events WHERE eventid IN ('.
			implode(',', array_map('zbx_dbstr', $eventids)).')'
		);

		$this->assertEquals($expected, $count);
	}

	private function reloadServerAndAssertHousekeeping(array $housekeeping) {
		$this->clearLog(self::COMPONENT_SERVER);
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'default timezone', true, 90, 1);

		$server_hk = $this->extractSyncedHousekeeping(self::COMPONENT_SERVER);
		$this->assertHousekeepingEquals($this->expectedServerHousekeeping($housekeeping), $server_hk);
	}

	public static function housekeepingProvider() {
		return [
			'first update' => [[
				'update' => [
					'hk_events_mode' => 1,
					'hk_events_trigger' => '43d',
					'hk_events_internal' => '28d',
					'hk_events_discovery' => '33d',
					'hk_events_autoreg' => '115d',
					'hk_events_service' => '213d',
					'hk_services_mode' => 1,
					'hk_services' => '213d',
					'hk_sessions_mode' => 1,
					'hk_sessions' => '151d',
					'hk_audit_mode' => 1,
					'hk_audit' => '45d',
					'hk_history_mode' => 1,
					'hk_history_global' => 1,
					'hk_history' => '2d',
					'hk_trends_mode' => 1,
					'hk_trends_global' => 1,
					'hk_trends' => '3d'
				]
			]],
			'second update' => [[
				'update' => [
					'hk_events_mode' => 1,
					'hk_events_trigger' => '52d',
					'hk_events_internal' => '31d',
					'hk_events_discovery' => '39d',
					'hk_events_autoreg' => '121d',
					'hk_events_service' => '217d',
					'hk_services_mode' => 1,
					'hk_services' => '217d',
					'hk_sessions_mode' => 1,
					'hk_sessions' => '157d',
					'hk_audit_mode' => 1,
					'hk_audit' => '49d',
					'hk_history_mode' => 1,
					'hk_history_global' => 1,
					'hk_history' => '4d',
					'hk_trends_mode' => 1,
					'hk_trends_global' => 1,
					'hk_trends' => '5d'
				]
			]],
			'disabled modes' => [[
				'update' => [
					'hk_events_mode' => 0,
					'hk_events_trigger' => '62d',
					'hk_events_internal' => '38d',
					'hk_events_discovery' => '47d',
					'hk_events_autoreg' => '128d',
					'hk_events_service' => '222d',
					'hk_services_mode' => 0,
					'hk_services' => '222d',
					'hk_sessions_mode' => 0,
					'hk_sessions' => '164d',
					'hk_audit_mode' => 0,
					'hk_audit' => '54d',
					'hk_history_mode' => 0,
					'hk_history_global' => 1,
					'hk_history' => '8d',
					'hk_trends_mode' => 0,
					'hk_trends_global' => 1,
					'hk_trends' => '9d'
				],
				'expected' => [
					'hk_events_mode' => 0,
					'hk_events_trigger' => '59d',
					'hk_events_internal' => '35d',
					'hk_events_discovery' => '44d',
					'hk_events_autoreg' => '125d',
					'hk_events_service' => '219d',
					'hk_services_mode' => 0,
					'hk_services' => '219d',
					'hk_sessions_mode' => 0,
					'hk_sessions' => '161d',
					'hk_audit_mode' => 0,
					'hk_audit' => '51d',
					'hk_history_mode' => 0,
					'hk_history_global' => 1,
					'hk_history' => '8d',
					'hk_trends_mode' => 0,
					'hk_trends_global' => 1,
					'hk_trends' => '9d'
				],
				'precondition' => [
					'hk_events_mode' => 1,
					'hk_events_trigger' => '59d',
					'hk_events_internal' => '35d',
					'hk_events_discovery' => '44d',
					'hk_events_autoreg' => '125d',
					'hk_events_service' => '219d',
					'hk_services_mode' => 1,
					'hk_services' => '219d',
					'hk_sessions_mode' => 1,
					'hk_sessions' => '161d',
					'hk_audit_mode' => 1,
					'hk_audit' => '51d',
					'hk_history_mode' => 1,
					'hk_history_global' => 1,
					'hk_history' => '7d',
					'hk_trends_mode' => 1,
					'hk_trends_global' => 1,
					'hk_trends' => '8d'
				]
			]]
		];
	}

	private function timeToSeconds($period) {
		$units = [
			's' => 1,
			'm' => SEC_PER_MIN,
			'h' => SEC_PER_HOUR,
			'd' => SEC_PER_DAY,
			'w' => SEC_PER_WEEK
		];

		if (!preg_match('/^(\d+)([smhdw]?)$/', $period, $matches)) {
			$this->fail('Unexpected housekeeping period: '.$period);
		}

		return (int) $matches[1] * ($matches[2] === '' ? 1 : $units[$matches[2]]);
	}

	private static function expectedPartitionableMode($mode) {
		return $mode == self::HK_MODE_REGULAR ? [self::HK_MODE_REGULAR, self::HK_MODE_PARTITION] : $mode;
	}

	/**
	 * Check that housekeeping.update changes are propagated to server and proxy
	 * runtime configuration caches.
	 *
	 * @dataProvider housekeepingProvider
	 */
	public function testHousekeepingConfSync_ApiUpdate(array $data) {
		if (array_key_exists('precondition', $data)) {
			$response = $this->call('housekeeping.update', $data['precondition']);
			$this->assertArrayHasKey('result', $response);

			$this->reloadServerAndAssertHousekeeping($data['precondition']);
		}

		$response = $this->call('housekeeping.update', $data['update']);
		$this->assertArrayHasKey('result', $response);

		$expected_housekeeping = $data['expected'] ?? $data['update'];
		$this->reloadServerAndAssertHousekeeping($expected_housekeeping);

		$this->clearLog(self::COMPONENT_PROXY);
		$this->reloadConfigurationCache(self::COMPONENT_PROXY);
		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, 'received configuration data from server',
				true, 90, 1);
		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, 'memory statistics for configuration cache',
				true, 90, 1);

		$proxy_hk = $this->extractSyncedHousekeeping(self::COMPONENT_PROXY);
		$this->assertArrayHasKey('hk_history', $proxy_hk);
		$this->assertArrayHasKey('hk_history_global', $proxy_hk);
		$this->assertEquals($this->timeToSeconds($data['update']['hk_history']), $proxy_hk['hk_history']);
		$this->assertEquals($data['update']['hk_history_global'], $proxy_hk['hk_history_global']);

		return true;
	}

	/**
	 * Check that the synced global history housekeeping period is used for old
	 * history records received from proxy.
	 *
	 * @depends testHousekeepingConfSync_ApiUpdate
	 */
	public function testHousekeepingConfSync_OldHistoryCleanup() {
		$housekeeping = [
			'hk_events_mode' => 1,
			'hk_events_trigger' => '90d',
			'hk_events_internal' => '90d',
			'hk_events_discovery' => '90d',
			'hk_events_autoreg' => '90d',
			'hk_events_service' => '90d',
			'hk_services_mode' => 1,
			'hk_services' => '90d',
			'hk_sessions_mode' => 1,
			'hk_sessions' => '90d',
			'hk_audit_mode' => 1,
			'hk_audit' => '90d',
			'hk_history_mode' => 1,
			'hk_history_global' => 1,
			'hk_history' => '90d',
			'hk_trends_mode' => 1,
			'hk_trends_global' => 1,
			'hk_trends' => '90d'
		];

		$response = $this->call('housekeeping.update', $housekeeping);
		$this->assertArrayHasKey('result', $response);

		$this->reloadServerAndAssertHousekeeping($housekeeping);

		$this->reloadConfigurationCache(self::COMPONENT_PROXY);
		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, 'received configuration data from server',
				true, 90, 1);

		$eventids = $this->createEventPairs(time() - 2 * SEC_PER_DAY, time());
		$this->assertEventsCount($eventids['old'], count($eventids['old']));
		$this->assertEventsCount($eventids['new'], count($eventids['new']));

		$this->sendAgentPing(time() - 2 * SEC_PER_DAY);
		$this->sendAgentPing(time());

		$this->waitForHistoryCount(2);

		$housekeeping = [
			'hk_events_mode' => 1,
			'hk_events_trigger' => '1d',
			'hk_events_internal' => '1d',
			'hk_events_discovery' => '1d',
			'hk_events_autoreg' => '1d',
			'hk_events_service' => '1d',
			'hk_services_mode' => 1,
			'hk_services' => '1d',
			'hk_sessions_mode' => 1,
			'hk_sessions' => '1d',
			'hk_audit_mode' => 1,
			'hk_audit' => '1d',
			'hk_history_mode' => 1,
			'hk_history_global' => 1,
			'hk_history' => '1d',
			'hk_trends_mode' => 1,
			'hk_trends_global' => 1,
			'hk_trends' => '1d'
		];

		$response = $this->call('housekeeping.update', $housekeeping);
		$this->assertArrayHasKey('result', $response);

		$this->reloadServerAndAssertHousekeeping($housekeeping);

		$this->executeHousekeeper(self::COMPONENT_SERVER);

		$this->waitForHistoryCount(1);
		$this->assertEventsCount($eventids['old'], 0);
		$this->assertEventsCount($eventids['new'], count($eventids['new']));

		return true;
	}
}

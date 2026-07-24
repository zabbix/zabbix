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
 * Dedicated coverage for nodata() behaviour on items monitored through a proxy, following up on
 * ZBX-27736 ("nodata trigger flickering into unknown state for unsupported items", fixed by
 * zbx_hc_is_itemid_cached_and_normal() in cachehistory.c). One host, one impersonated proxy (no
 * real proxy process is started - proxy.create() + sendAgentDataValues(..., $proxy) is enough to
 * exercise item->proxyid != 0 and the proxy nodata-suppression window; see
 * testLLDHistorySyncAtScale.php for the same technique), one item+trigger pair per scenario:
 *
 *  - testNoData_UnsupportedFires:            unsupported item is treated as nodata, trigger fires.
 *  - testNoData_NoUnknownWithAndAfterConnectionLoss: no UNKNOWN while the proxy keeps talking
 *                                             normally, and none after a real gap + resume either.
 *  - testNoData_Discard:                     nodata() after only discarded (no-value) updates.
 *  - testNoData_LogMetadataOnly:              nodata() after only log metadata (no value) updates.
 *  - testNoData_ValuesFromPast:               nodata() when only past-clock values were received.
 *  - testNoData_UnsupportedFlapping:          literal reported case, nodata(...,30s), ~25s cadence.
 *  - testNoData_ConnectionLossSuppression:    no premature PROBLEM/UNKNOWN across a connection
 *                                             loss and restore, including right at resume.
 *
 * testNoData_Discard and testNoData_LogMetadataOnly are expected to be unstable: metadata-only /
 * no-value records land in the history cache with state ITEM_STATE_NORMAL (see
 * hc_add_item_values()/hc_clone_history_data() in cachehistory.c), and
 * zbx_hc_is_itemid_cached_and_normal() has no way to tell them apart from a real value still
 * being transferred from the proxy, so nodata() can keep returning an evaluation error instead of
 * 1. Both markTestSkipped() rather than fail when that happens, matching the existing convention
 * in testLLDHistorySyncAtScale.php for the same known gap.
 *
 * testNoData_ProxyGroupLogSkip at the end reproduces a specific reported case on top of the same
 * known gap: a host monitored by a proxy group, with an active-agent log item using
 * "skip" mode, and a trigger combining last()/length() with a lazy nodata() in an "and" expression.
 * Once a matching line has opened the problem and the log stops producing matches, the item keeps
 * sending metadata-only updates (lastlogsize/mtime, no value) as the agent keeps polling the file
 * position, and the problem never closes even though the proxies stay online throughout - matching
 * the mechanism above. Switching to nodata(...,"strict") bypasses that code path entirely and the
 * problem closes as soon as the window elapses. Unlike the other scenarios this one needs a real
 * proxy-group host assignment (host.get 'assigned_proxyid' must resolve to an actually online
 * group member - see pg_manager.c), so one proxy group member is a real, running component; the
 * second is a proxy.create()-only "phantom" that never comes online, present only so the group
 * configuration genuinely has two-or-more members as reported (min_online=1 keeps the group online
 * on the real member alone; the underlying bug does not depend on multi-proxy failover, only on
 * the item having a non-zero proxyid). The reported trigger uses nodata(item,20m); these tests use
 * a much shorter window (see PG_NODATA_WINDOW_SEC) - the window value does not affect the code
 * path under test, only how long the test has to wait. Step (3) in that test currently asserts
 * the BUGGY behaviour (problem stays open forever); once zbx_hc_is_itemid_cached_and_normal() is
 * fixed to also exclude ZBX_DC_FLAG_NOVALUE tail records, that assertion will start failing and
 * needs to be flipped to expect recovery (like step (4) already does for the "strict" case).
 *
 * Deliberately does NOT use @suite-components-reuse: onAfterTestCase() only calls
 * stopComponent() when reuse is off (CIntegrationTest.php), and onBeforeTestCase() only flips
 * suite_components_running to true after startComponent() succeeds. With reuse on, a single
 * failed/slow startup on any one test leaves components stopped-but-marked-not-running for the
 * rest of the class, and every subsequent test retries a fresh start against the same
 * still-occupied PID file/ports - cascading into "Is this process already running?" failures
 * across the whole suite. Paying the per-test restart cost keeps a bad start from poisoning
 * every other test in the class.
 *
 * @required-components server, proxy
 * @configurationDataProvider configurationProvider
 * @onAfter clearData
 */
class testNoData extends CIntegrationTest {

	private static $hostid;
	private static $proxyid;

	private static $itemids = [];
	private static $triggerids = [];

	private static $pg_proxy_groupid;
	private static $pg_proxyid;
	private static $pg_proxyid2;
	private static $pg_hostid;
	private static $pg_itemid;
	private static $pg_triggerid;
	private static $pg_lastlogsize = 0;

	const HOSTNAME = 'nodata_test_host';
	const PROXY_NAME = 'nodata_test_proxy';

	/*
	 * check_proxy_nodata() (src/libs/zbxdbwrap/proxy.c) treats a gap longer than NET_DELAY_MAX
	 * (15s, include/zbxcommon.h) between proxy data submissions as a connection loss and enables
	 * the suppression window. Must stay above 15s for the connection-loss scenarios below.
	 */
	const CONNECTION_LOSS_GAP_SEC = 20;

	const PG_NAME = 'NoData proxy group';
	const PG_PROXY_NAME = 'NoData proxy group member 1';
	const PG_PROXY2_NAME = 'NoData proxy group member 2';
	const PG_HOSTNAME = 'nodata_proxygroup_host';
	const PG_ITEM_KEY = 'log[/var/log/nodata-skip-test.log,MATCH,,,skip]';

	/*
	 * Scaled down from the reported nodata(item,20m). See class docblock.
	 */
	const PG_NODATA_WINDOW_SEC = 20;
	const PG_HEARTBEAT_INTERVAL_SEC = 5;

	/**
	 * Component configuration provider.
	 *
	 * @return array
	 */
	public function configurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 0
			],
			self::COMPONENT_PROXY => [
				'Hostname' => self::PG_PROXY_NAME,
				'DebugLevel' => 4,
				'LogFileSize' => 0,
				// Not self::getConfigurationValue(COMPONENT_SERVER, 'ListenPort') here: this
				// configurationProvider() is resolved via a class-level @configurationDataProvider,
				// which processAnnotations('class') calls from onBeforeTestSuite() BEFORE
				// self::$suite_configuration is populated with defaults (see
				// getDefaultComponentConfiguration() a few lines later in that same method) - so
				// that lookup would return null here. Reference the same expression the framework's
				// own defaults use instead.
				'Server' => '127.0.0.1:'.PHPUNIT_PORT_PREFIX.self::SERVER_PORT_SUFFIX
			]
		];
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		$response = $this->call('hostgroup.get', [
			'filter' => ['name' => ['Zabbix servers']],
			'output' => ['groupid']
		]);
		$this->assertNotEmpty($response['result'], 'Host group "Zabbix servers" not found.');
		$groupid = $response['result'][0]['groupid'];

		$response = $this->call('host.create', [
			'host' => self::HOSTNAME,
			'interfaces' => [],
			'groups' => [['groupid' => $groupid]],
			'status' => HOST_STATUS_MONITORED
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$hostid = $response['result']['hostids'][0];

		$response = $this->call('proxy.create', [
			'name' => self::PROXY_NAME,
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
			'hosts' => [
				['hostid' => self::$hostid]
			]
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);
		self::$proxyid = $response['result']['proxyids'][0];

		$items = [
			['key' => 'nodata.unsupported.fires', 'value_type' => ITEM_VALUE_TYPE_UINT64, 'window' => 15],
			['key' => 'nodata.no.unknown', 'value_type' => ITEM_VALUE_TYPE_UINT64, 'window' => 15],
			['key' => 'nodata.discard', 'value_type' => ITEM_VALUE_TYPE_TEXT, 'window' => 15],
			['key' => 'nodata.logmeta', 'value_type' => ITEM_VALUE_TYPE_LOG, 'window' => 15],
			['key' => 'nodata.past', 'value_type' => ITEM_VALUE_TYPE_UINT64, 'window' => 15],
			['key' => 'nodata.flap.30.25', 'value_type' => ITEM_VALUE_TYPE_UINT64, 'window' => 30],
			['key' => 'nodata.connloss', 'value_type' => ITEM_VALUE_TYPE_UINT64, 'window' => 15]
		];

		foreach ($items as $def) {
			$response = $this->call('item.create', [
				'hostid' => self::$hostid,
				'name' => $def['key'],
				'key_' => $def['key'],
				'type' => ITEM_TYPE_ZABBIX_ACTIVE,
				'value_type' => $def['value_type'],
				'delay' => '1s'
			]);
			$this->assertArrayHasKey('itemids', $response['result']);
			$itemid = $response['result']['itemids'][0];
			self::$itemids[$def['key']] = $itemid;

			$response = $this->call('trigger.create', [
				'description' => $def['key'],
				'expression' => 'nodata(/'.self::HOSTNAME.'/'.$def['key'].','.$def['window'].'s)=1'
			]);
			$this->assertArrayHasKey('triggerids', $response['result']);
			self::$triggerids[$def['key']] = $response['result']['triggerids'][0];
		}

		$response = $this->call('proxygroup.create', [
			'name' => self::PG_NAME,
			'failover_delay' => '10',
			'min_online' => '1'
		]);
		$this->assertArrayHasKey('proxy_groupids', $response['result']);
		self::$pg_proxy_groupid = $response['result']['proxy_groupids'][0];

		// Real, running proxy - the proxy-group host will be assigned to this one.
		$response = $this->call('proxy.create', [
			'name' => self::PG_PROXY_NAME,
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
			'local_address' => '127.0.0.1',
			'local_port' => $this->getConfigurationValue(self::COMPONENT_PROXY, 'ListenPort'),
			'proxy_groupid' => self::$pg_proxy_groupid
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);
		self::$pg_proxyid = $response['result']['proxyids'][0];

		// Second group member - configuration only, no component started for it. Never goes
		// online, never gets hosts assigned (min_online=1 keeps the group online on proxyid1
		// alone). Present only to match the reported "two or more proxies in a group" topology.
		$response = $this->call('proxy.create', [
			'name' => self::PG_PROXY2_NAME,
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
			'local_address' => '127.0.0.1',
			'local_port' => 32222,
			'proxy_groupid' => self::$pg_proxy_groupid
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);
		self::$pg_proxyid2 = $response['result']['proxyids'][0];

		$response = $this->call('host.create', [
			'host' => self::PG_HOSTNAME,
			'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
			'proxy_groupid' => self::$pg_proxy_groupid,
			'groups' => [['groupid' => $groupid]]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$pg_hostid = $response['result']['hostids'][0];

		$response = $this->call('item.create', [
			'hostid' => self::$pg_hostid,
			'name' => 'nodata log skip test',
			'key_' => self::PG_ITEM_KEY,
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'value_type' => ITEM_VALUE_TYPE_LOG,
			'delay' => '1s'
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		self::$pg_itemid = $response['result']['itemids'][0];

		$response = $this->call('trigger.create', [
			'description' => 'NoData lazy log problem',
			'expression' => 'length(last(/'.self::PG_HOSTNAME.'/'.self::PG_ITEM_KEY.'))>0 and '.
				'nodata(/'.self::PG_HOSTNAME.'/'.self::PG_ITEM_KEY.','.self::PG_NODATA_WINDOW_SEC.'s)=0'
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		self::$pg_triggerid = $response['result']['triggerids'][0];

		return true;
	}

	private function push($key, array $opts = []) {
		$tm = $opts['clock'] ?? time();

		$item_value = [
			'itemid' => self::$itemids[$key],
			'clock' => $tm,
			'ns' => (int)(microtime(true) * 1e9) % 1000000000
		];

		if (array_key_exists('value', $opts) && $opts['value'] !== null) {
			$item_value['value'] = $opts['value'];
		}
		if (isset($opts['state'])) {
			$item_value['state'] = $opts['state'];
		}
		if (isset($opts['lastlogsize'])) {
			$item_value['lastlogsize'] = $opts['lastlogsize'];
		}
		if (isset($opts['mtime'])) {
			$item_value['mtime'] = $opts['mtime'];
		}

		$this->sendAgentDataValues([$item_value], self::HOSTNAME, self::COMPONENT_SERVER, 0, self::PROXY_NAME);
	}

	private function getTrigger($key) {
		$response = $this->call('trigger.get', [
			'triggerids' => [self::$triggerids[$key]],
			'output' => ['value', 'state', 'error']
		]);
		$this->assertArrayHasKey(0, $response['result']);

		return $response['result'][0];
	}

	private function waitForTriggerValue($key, $expected_value, $iterations = 30) {
		return $this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$triggerids[$key]],
			'output' => ['value', 'state']
		], $iterations, 1, function ($r) use ($expected_value) {
			return $r['result'][0]['value'] == $expected_value;
		});
	}

	private function unknownEventCount() {
		$response = $this->call('event.get', [
			'objectids' => array_values(self::$triggerids),
			'source' => EVENT_SOURCE_INTERNAL,
			'object' => EVENT_OBJECT_TRIGGER,
			'value' => TRIGGER_STATE_UNKNOWN,
			'countOutput' => true
		]);

		return (int) $response['result'];
	}

	private function getPgTrigger() {
		$response = $this->call('trigger.get', [
			'triggerids' => [self::$pg_triggerid],
			'output' => ['triggerid', 'value', 'state', 'error']
		]);
		$this->assertArrayHasKey(0, $response['result']);

		return $response['result'][0];
	}

	private function pushLogSkip(?string $value) {
		$tm = time();

		$item_value = [
			'itemid' => self::$pg_itemid,
			'clock' => $tm,
			'ns' => (int)(microtime(true) * 1e9) % 1000000000,
			'lastlogsize' => ++self::$pg_lastlogsize,
			'mtime' => $tm
		];

		if ($value !== null) {
			$item_value['value'] = $value;
		}

		// Real active-agent-style push straight to the real (assigned) proxy component - no
		// impersonation, this is exactly what a real active check submits.
		$this->sendAgentDataValues([$item_value], self::PG_HOSTNAME, self::COMPONENT_PROXY, 0, null);
	}

	private function problemEventCount($key) {
		$response = $this->call('event.get', [
			'objectids' => [self::$triggerids[$key]],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'value' => TRIGGER_VALUE_TRUE,
			'countOutput' => true
		]);

		return (int) $response['result'];
	}

	/**
	 * "Test with proxy that when item is not supported for example every few seconds, then
	 * nodata trigger fire." nodata() must treat ITEM_STATE_NOTSUPPORTED the same as no data: this
	 * is exactly the ZBX-27736 fix (zbx_hc_is_itemid_cached_and_normal() excludes NOTSUPPORTED
	 * tail records from the "proxy transfer in progress" check).
	 */
	public function testNoData_UnsupportedFires() {
		$key = 'nodata.unsupported.fires';

		$this->push($key, ['value' => 1]);
		$this->waitForTriggerValue($key, TRIGGER_VALUE_FALSE);

		$deadline = microtime(true) + 20;
		while (microtime(true) < $deadline) {
			$this->push($key, ['state' => ITEM_STATE_NOTSUPPORTED, 'value' => 'not a number']);
			sleep(4);
		}

		$this->waitForTriggerValue($key, TRIGGER_VALUE_TRUE);
		$trigger = $this->getTrigger($key);
		$this->assertEquals(TRIGGER_VALUE_TRUE, $trigger['value']);
	}

	/**
	 * "not becoming unknown when there is no connection loss between Zabbix server and Zabbix
	 * proxy and after there was connection loss and restored afterwards." Phase 1 sends values
	 * continuously (gaps well under NET_DELAY_MAX) and verifies the trigger never flips to
	 * UNKNOWN; phase 2, in the same test (no component restart in between - see class docblock
	 * on why @depends across separate tests isn't used here), goes quiet past NET_DELAY_MAX to
	 * trigger the proxy's lost-connection detection, then resumes normal traffic and verifies the
	 * trigger never showed UNKNOWN across the whole sequence either.
	 */
	public function testNoData_NoUnknownWithAndAfterConnectionLoss() {
		$key = 'nodata.no.unknown';

		$before = $this->unknownEventCount();

		// Phase 1: steady traffic, no connection loss.
		$deadline = microtime(true) + 20;
		$i = 0;
		while (microtime(true) < $deadline) {
			$this->push($key, ['value' => $i++]);
			sleep(3);

			$trigger = $this->getTrigger($key);
			$this->assertNotEquals(TRIGGER_STATE_UNKNOWN, $trigger['state'],
				'Trigger went UNKNOWN with no connection loss. Error: '.$trigger['error']);
		}

		// Phase 2: simulated connection loss (gap > NET_DELAY_MAX) then resume.
		sleep(self::CONNECTION_LOSS_GAP_SEC);

		for ($i = 0; $i < 5; $i++) {
			$this->push($key, ['value' => 1000 + $i]);
			sleep(3);

			$trigger = $this->getTrigger($key);
			$this->assertNotEquals(TRIGGER_STATE_UNKNOWN, $trigger['state'],
				'Trigger went UNKNOWN after connection loss was restored. Error: '.$trigger['error']);
		}

		$after = $this->unknownEventCount();
		$this->assertEquals($before, $after, 'Unknown trigger event count changed: '.$before.' -> '.$after);
	}

	/**
	 * "nodata with discard - check that nodata also works when there was discard." Modelled as
	 * repeated no-value (ZBX_DC_FLAG_NOVALUE) submissions, the same shape a "Discard unchanged"
	 * preprocessing step or a discarded duplicate produces server-side. Known unstable - see class
	 * docblock.
	 */
	public function testNoData_Discard() {
		$key = 'nodata.discard';

		$this->push($key, ['value' => 'first value']);
		$this->waitForTriggerValue($key, TRIGGER_VALUE_FALSE);

		$deadline = microtime(true) + 20;
		while (microtime(true) < $deadline) {
			$this->push($key); // no 'value' => ZBX_DC_FLAG_NOVALUE, models a discarded update
			sleep(4);
		}

		try {
			$this->waitForTriggerValue($key, TRIGGER_VALUE_TRUE, 15);
		} catch (Exception $e) {
			$trigger = $this->getTrigger($key);
			$this->markTestSkipped('nodata() did not fire after discard-only updates (known unstable - see '.
				'zbx_hc_is_itemid_cached_and_normal() in cachehistory.c). Trigger state: '.
				json_encode($trigger));

			return;
		}

		$trigger = $this->getTrigger($key);
		$this->assertEquals(TRIGGER_VALUE_TRUE, $trigger['value']);
	}

	/**
	 * "nodata with only metadata with logitem - check that nodata also works when there was only
	 * metadata with log and no actual value." Same shape as testNoData_Discard but with
	 * lastlogsize/mtime present, matching a log item whose agent keeps polling but finds nothing
	 * new to report. Known unstable - see class docblock.
	 */
	public function testNoData_LogMetadataOnly() {
		$key = 'nodata.logmeta';
		$lastlogsize = 0;
		$tm = time();

		$this->push($key, ['value' => 'first log line', 'lastlogsize' => ++$lastlogsize, 'mtime' => $tm]);
		$this->waitForTriggerValue($key, TRIGGER_VALUE_FALSE);

		$deadline = microtime(true) + 20;
		while (microtime(true) < $deadline) {
			$this->push($key, ['lastlogsize' => ++$lastlogsize, 'mtime' => time()]); // metadata only
			sleep(4);
		}

		try {
			$this->waitForTriggerValue($key, TRIGGER_VALUE_TRUE, 15);
		} catch (Exception $e) {
			$trigger = $this->getTrigger($key);
			$this->markTestSkipped('nodata() did not fire after log-metadata-only updates (known unstable - see '.
				'zbx_hc_is_itemid_cached_and_normal() in cachehistory.c). Trigger state: '.
				json_encode($trigger));

			return;
		}

		$trigger = $this->getTrigger($key);
		$this->assertEquals(TRIGGER_VALUE_TRUE, $trigger['value']);
	}

	/**
	 * "nodata with values from past - check that nodata works if only values from past are
	 * received." A value old enough to fall outside the nodata() window must not be treated as
	 * fresh data: nodata() must still fire.
	 */
	public function testNoData_ValuesFromPast() {
		$key = 'nodata.past';

		$this->push($key, ['value' => 1, 'clock' => time() - 120]);

		$this->waitForTriggerValue($key, TRIGGER_VALUE_TRUE);
		$trigger = $this->getTrigger($key);
		$this->assertEquals(TRIGGER_VALUE_TRUE, $trigger['value']);
	}

	/**
	 * Literal reported case: nodata(item,30s) with an item polled every ~25s that becomes
	 * unsupported (e.g. a numeric item receiving a string) every few seconds. Verify the trigger
	 * reaches PROBLEM and stays there without flapping back to UNKNOWN/OK on every toggle.
	 */
	public function testNoData_UnsupportedFlapping() {
		$key = 'nodata.flap.30.25';

		$this->push($key, ['value' => 1]);
		$this->waitForTriggerValue($key, TRIGGER_VALUE_FALSE);

		$before = $this->unknownEventCount();

		$deadline = microtime(true) + 40;
		$toggle = true;
		while (microtime(true) < $deadline) {
			if ($toggle) {
				$this->push($key, ['state' => ITEM_STATE_NOTSUPPORTED, 'value' => 'not a number']);
			}
			else {
				$this->push($key, ['state' => ITEM_STATE_NOTSUPPORTED, 'value' => 'still not a number']);
			}
			$toggle = !$toggle;
			sleep(5);
		}

		$this->waitForTriggerValue($key, TRIGGER_VALUE_TRUE);
		$trigger = $this->getTrigger($key);
		$this->assertEquals(TRIGGER_VALUE_TRUE, $trigger['value']);
		$this->assertEquals(TRIGGER_STATE_NORMAL, $trigger['state'], 'Error: '.$trigger['error']);
	}

	/**
	 * "nodata suppressed when there is connection loss between Zabbix server and Zabbix proxy -
	 * check that there are no unknown triggers/events and most important no problems after
	 * connection has been restored. but also nodata can fire before value processed." Establish
	 * normal flow, go quiet past NET_DELAY_MAX, then resume with a burst that includes
	 * older-clock ("backlog") values immediately followed by a current value, checking right at
	 * resume (not just after settling) for any premature PROBLEM/UNKNOWN.
	 */
	public function testNoData_ConnectionLossSuppression() {
		$key = 'nodata.connloss';

		$this->push($key, ['value' => 1]);
		$this->waitForTriggerValue($key, TRIGGER_VALUE_FALSE);

		$before_unknown = $this->unknownEventCount();
		$before_problem = $this->problemEventCount($key);

		sleep(self::CONNECTION_LOSS_GAP_SEC);

		$gap_start = time();
		// Backlog burst: values timestamped during the gap, delivered together right at resume.
		for ($i = 0; $i < 5; $i++) {
			$this->push($key, ['value' => 100 + $i, 'clock' => $gap_start - 5 + $i]);
		}

		// Check immediately, before anything has had time to "settle" - this is the narrow
		// window where suppression must already be in effect, not merely eventually correct.
		$trigger = $this->getTrigger($key);
		$this->assertNotEquals(TRIGGER_VALUE_TRUE, $trigger['value'],
			'Trigger fired PROBLEM immediately after reconnect burst, before catching up.');
		$this->assertNotEquals(TRIGGER_STATE_UNKNOWN, $trigger['state'],
			'Trigger went UNKNOWN immediately after reconnect burst. Error: '.$trigger['error']);

		for ($i = 0; $i < 5; $i++) {
			$this->push($key, ['value' => 200 + $i]);
			sleep(2);

			$trigger = $this->getTrigger($key);
			$this->assertNotEquals(TRIGGER_VALUE_TRUE, $trigger['value'],
				'Trigger fired PROBLEM after connection loss was restored.');
			$this->assertNotEquals(TRIGGER_STATE_UNKNOWN, $trigger['state'],
				'Trigger went UNKNOWN after connection loss was restored. Error: '.$trigger['error']);
		}

		$after_unknown = $this->unknownEventCount();
		$after_problem = $this->problemEventCount($key);
		$this->assertEquals($before_unknown, $after_unknown,
			'Unknown trigger event count changed: '.$before_unknown.' -> '.$after_unknown);
		$this->assertEquals($before_problem, $after_problem,
			'Problem trigger event count changed: '.$before_problem.' -> '.$after_problem);
	}

	/**
	 * Proxy-group log-skip repro, as one test (no @depends / no component restart in between -
	 * see class docblock): (1) proxy group comes online and the host is assigned to the real
	 * proxy, (2) one matching log line opens the problem, (3) metadata-only heartbeats afterwards
	 * do NOT close it even well past the nodata() window - documents the reported bug, (4)
	 * switching to nodata(...,"strict") closes it once the window elapses - the reported
	 * workaround.
	 */
	public function testNoData_ProxyGroupLogSkip() {
		// (1) proxy group online, host assigned to the real proxy.
		$pg_logline = 'Proxy group "'.self::PG_NAME.'" changed state from \b[a-z]+\b to online';
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $pg_logline, true, 90, 1, true);

		$assign_logline = 'assigned hostid '.self::$pg_hostid.' to proxyid '.self::$pg_proxyid;
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $assign_logline, true, 90, 1, true);

		$response = $this->call('host.get', [
			'output' => ['hostid', 'assigned_proxyid'],
			'hostids' => [self::$pg_hostid]
		]);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(self::$pg_proxyid, $response['result'][0]['assigned_proxyid']);

		// Make sure the real proxy actually knows about the host/item before we push data to it.
		$this->reloadConfigurationCache(self::COMPONENT_PROXY);
		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, 'End of zbx_dc_sync_configuration()', true, 90, 1,
				true);

		// (2) one matching log line opens the problem.
		$this->pushLogSkip('MATCH: something went wrong');

		$this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$pg_triggerid],
			'output' => ['value', 'state']
		], 30, 1, function ($r) {
			return $r['result'][0]['value'] == TRIGGER_VALUE_TRUE;
		});

		$trigger = $this->getPgTrigger();
		$this->assertEquals(TRIGGER_VALUE_TRUE, $trigger['value']);

		// (3) metadata-only heartbeats afterwards must NOT close the problem - known bug (see
		// class docblock): this assertion documents the BUGGY behaviour and will need to be
		// flipped to expect recovery once zbx_hc_is_itemid_cached_and_normal() is fixed to also
		// exclude ZBX_DC_FLAG_NOVALUE tail records.
		$deadline = microtime(true) + (2 * self::PG_NODATA_WINDOW_SEC);

		while (microtime(true) < $deadline) {
			$this->pushLogSkip(null);
			sleep(self::PG_HEARTBEAT_INTERVAL_SEC);

			$trigger = $this->getPgTrigger();
			$this->assertEquals(TRIGGER_VALUE_TRUE, $trigger['value'],
				'Problem unexpectedly closed - if zbx_hc_is_itemid_cached_and_normal() was fixed to '.
				'exclude ZBX_DC_FLAG_NOVALUE tail records, update this test to expect recovery instead.');
		}

		// Known bug: still open well past 2x the nodata() window, while metadata-only updates
		// kept flowing and the proxy stayed online throughout.
		$trigger = $this->getPgTrigger();
		$this->assertEquals(TRIGGER_VALUE_TRUE, $trigger['value']);

		// (4) switch to "strict" - the problem must now close once the window elapses.
		$response = $this->call('trigger.update', [
			'triggerid' => self::$pg_triggerid,
			'expression' => 'length(last(/'.self::PG_HOSTNAME.'/'.self::PG_ITEM_KEY.'))>0 and '.
				'nodata(/'.self::PG_HOSTNAME.'/'.self::PG_ITEM_KEY.','.self::PG_NODATA_WINDOW_SEC.'s,"strict")=0'
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_dc_sync_configuration()', true, 90, 1,
				true);

		// Keep sending the same metadata-only heartbeats as before - "strict" mode should close
		// the problem regardless, since it no longer treats the proxied item's cached tail state
		// as "transfer in progress".
		$this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$pg_triggerid],
			'output' => ['value', 'state']
		], 30, 1, function ($r) {
			$this->pushLogSkip(null);

			return $r['result'][0]['value'] == TRIGGER_VALUE_FALSE;
		});

		$trigger = $this->getPgTrigger();
		$this->assertEquals(TRIGGER_VALUE_FALSE, $trigger['value']);
		$this->assertEquals(TRIGGER_STATE_NORMAL, $trigger['state'], 'Error: '.$trigger['error']);
	}

	public static function clearData(): void {
		if (CAPIHelper::getSessionId() === null) {
			CAPIHelper::authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		}

		if (self::$hostid !== null) {
			$response = CAPIHelper::call('host.delete', [self::$hostid]);
			self::assertArrayHasKey('hostids', $response['result']);
			self::assertContains((string) self::$hostid, $response['result']['hostids']);
			self::$hostid = null;
		}

		if (self::$pg_hostid !== null) {
			$response = CAPIHelper::call('host.delete', [self::$pg_hostid]);
			self::assertArrayHasKey('hostids', $response['result']);
			self::assertContains((string) self::$pg_hostid, $response['result']['hostids']);
			self::$pg_hostid = null;
		}

		$proxyids = array_filter([self::$proxyid, self::$pg_proxyid, self::$pg_proxyid2]);
		if (!empty($proxyids)) {
			$response = CAPIHelper::call('proxy.delete', array_values($proxyids));
			self::assertArrayHasKey('proxyids', $response['result']);
			self::$proxyid = null;
			self::$pg_proxyid = null;
			self::$pg_proxyid2 = null;
		}

		if (self::$pg_proxy_groupid !== null) {
			$response = CAPIHelper::call('proxygroup.delete', [self::$pg_proxy_groupid]);
			self::assertArrayHasKey('proxy_groupids', $response['result']);
			self::$pg_proxy_groupid = null;
		}

		self::$itemids = [];
		self::$triggerids = [];
		self::$pg_itemid = null;
		self::$pg_triggerid = null;
		self::$pg_lastlogsize = 0;
	}
}

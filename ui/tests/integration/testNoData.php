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
 * zbx_hc_is_itemid_cached_and_normal() in cachehistory.c).
 *
 * THE RULE nodata(item, period) IS SUPPOSED TO IMPLEMENT: has the item recorded an actual value,
 * with a clock timestamp inside the last `period` seconds? If no value satisfies that - for ANY
 * reason - it must return 1. The only legitimate exception is a genuine, temporary ambiguity: the
 * server can't yet tell whether the proxy is sitting on a real value it hasn't relayed yet.
 * Applied to each scenario below:
 *
 *   - metadata-only log updates (lastlogsize/mtime, no matching line): nodata() MUST fire. No
 *     value was ever recorded - a log item polling its file and finding nothing isn't "producing
 *     data", it's exactly what nodata() exists to detect.
 *   - history discarded by preprocessing (e.g. "Discard unchanged"): nodata() MUST fire, same
 *     reasoning - preprocessing decided not to store a value, so there is no data regardless of
 *     whether the agent/proxy is alive and polling.
 *   - values with a past clock only: nodata() MUST fire. It is defined against the value's own
 *     clock, not receipt time - a value clocked outside the window was never "recent", no matter
 *     when it physically arrived at the server.
 *   - item flapping supported <-> unsupported: nodata() must NOT fire while a valid value sits
 *     inside the window, and MUST fire once nothing valid has landed for the full window - and
 *     critically, the trigger must never bounce to UNKNOWN over this transition. There is no real
 *     ambiguity here: the server knows with certainty whether a valid value landed in the window.
 *   - genuine proxy connection loss: nodata() must be suppressed (neither firing 0 nor a spurious
 *     close/open) until the gap resolves - this is the one case where the ambiguity is real, since
 *     the proxy might be holding a real backlog the server hasn't seen yet. Once it reconnects and
 *     either delivers the backlog or confirms there is nothing, nodata() must resolve promptly.
 *
 * The current bug is that zbx_hc_is_itemid_cached_and_normal() (cachehistory.c) conflates the
 * first four "no real ambiguity, nodata() must fire" cases with the fifth "genuine ambiguity, hold
 * off" case: it only excludes ITEM_STATE_NOTSUPPORTED tail records from the "proxy transfer in
 * progress" check, and treats every other kind of tail record - a ZBX_DC_FLAG_NOVALUE
 * metadata-only/discard record, or even a perfectly ordinary real value that just happens to be
 * backdated or momentarily valid during flapping - as indistinguishable from a real value
 * genuinely still being transferred from the proxy. So nodata() keeps returning an evaluation
 * error instead of ever resolving to 1 for those first four cases. There is no fix for this yet.
 *
 * One host, one impersonated proxy (no real proxy process is started - proxy.create() +
 * sendAgentDataValues(..., $proxy) is enough to exercise item->proxyid != 0 and the proxy
 * nodata-suppression window; see testLLDHistorySyncAtScale.php for the same technique), one
 * item+trigger pair per scenario. Each test asserts the CORRECT/expected behaviour from the table
 * above, not "whatever currently happens":
 *
 *  - testNoData_UnsupportedFires:            PASSES - unsupported item is treated as nodata,
 *                                             trigger fires. This is the one case ZBX-27736
 *                                             already fixed (the ITEM_STATE_NOTSUPPORTED
 *                                             exclusion above).
 *  - testNoData_NoUnknownWithAndAfterConnectionLoss: EXPECTED TO FAIL - trigger must never show
 *                                             UNKNOWN, neither while the proxy keeps talking
 *                                             normally nor after a real gap + resume.
 *  - testNoData_Discard:                     EXPECTED TO FAIL - discard case from the table above.
 *  - testNoData_LogMetadataOnly:              EXPECTED TO FAIL - metadata-only case from the table.
 *  - testNoData_ValuesFromPast:               EXPECTED TO FAIL - past-clock-only case from the
 *                                             table (a real value, even backdated, still leaves
 *                                             the history cache tail in ITEM_STATE_NORMAL - same
 *                                             underlying check as the other cases).
 *  - testNoData_UnsupportedFlapping:          EXPECTED TO FAIL - literal reported case: flapping
 *                                             case from the table, with nodata(...,30s) and an
 *                                             item genuinely alternating between a valid value and
 *                                             ITEM_STATE_NOTSUPPORTED (not just staying
 *                                             unsupported, which is the already-fixed case above).
 *  - testNoData_ConnectionLossSuppression:    connection-loss case from the table - no premature
 *                                             PROBLEM/UNKNOWN across a connection loss and
 *                                             restore, including right at resume.
 *  - testNoData_ProxyGroupLogSkip_LazyStaysOpen: EXPECTED TO FAIL - see below.
 *  - testNoData_ProxyGroupLogSkip_StrictCloses: PASSES - see below.
 *
 * The two testNoData_ProxyGroupLogSkip_* methods reproduce a specific reported case on top of the
 * metadata-only gap above: Zabbix server 7.0.28 with two or more active proxies in a proxy group,
 * a host monitored by that group with an active-agent log item using "skip" mode, and a trigger
 * combining last()/length() with nodata() in an "and" expression. One matching log line opens the
 * problem; the log then stops producing matches, but the item keeps sending metadata-only updates
 * (lastlogsize/mtime, no value) as the agent keeps polling the file position - matching the
 * mechanism above. Correct/expected behaviour is that the problem closes once the window elapses,
 * the same as testNoData_LogMetadataOnly; with the default (lazy) nodata() it does not, and stays
 * open indefinitely even though the proxies stay online throughout - testNoData_ProxyGroupLogSkip_
 * LazyStaysOpen asserts the closing behaviour and is expected to currently fail on exactly that.
 * Switching to nodata(...,"strict") bypasses the affected code path entirely and the problem does
 * close once the window elapses - testNoData_ProxyGroupLogSkip_StrictCloses asserts that and is
 * expected to pass, proving the workaround. The two are separate, independent test methods (each
 * redoes steps 1-2 itself) rather than one, so a failure in the lazy case can never prevent the
 * strict case from being exercised and reported on its own. Unlike the other scenarios this one
 * needs a real proxy-group host assignment (host.get 'assigned_proxyid' must resolve to an
 * actually online group member - see pg_manager.c), so one proxy group member is a real, running
 * component; the second is a proxy.create()-only "phantom" that never comes online, present only
 * so the group configuration genuinely has two-or-more members as reported (min_online=1 keeps the
 * group online on the real member alone; the underlying bug does not depend on multi-proxy
 * failover, only on the item having a non-zero proxyid). The reported trigger uses
 * nodata(item,20m); these tests use a much shorter window (see PG_NODATA_WINDOW_SEC) - the window
 * value does not affect the code path under test, only how long the test has to wait.
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
				'LogFileSize' => 0,
				'StartDBSyncers' => 1
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

	/*
	 * Default iterations covers at least one full history-syncer wake cycle plus real margin: with
	 * StartDBSyncers=1 and the artificial slowdown in dbsyncer.c (see class docblock reference to
	 * the DEV4972 repro aid), a pushed value can sit unprocessed for up to that whole cycle before
	 * the trigger reflects it - 30s (the old default) is not enough for even the very first,
	 * otherwise-instant "push a baseline value, wait for it to land" step most tests start with.
	 * 60s (barely over one cycle) was ALSO not enough in practice - CI caught a real failure at
	 * that budget (testNoData_ValuesFromPast), most likely phase misalignment or slower hardware
	 * pushing a single cycle's actual wall-clock cost past 60s. Use enough margin to comfortably
	 * cover slower/busier environments, not just the happy-path minimum.
	 */
	private function waitForTriggerValue($key, $expected_value, $iterations = 100) {
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
	 * on why splitting this into two dependent test methods isn't used here), goes quiet past
	 * NET_DELAY_MAX to trigger the proxy's lost-connection detection, then resumes normal traffic
	 * and verifies the trigger never showed UNKNOWN across the whole sequence either.
	 *
	 * (Note: an earlier version of this docblock spelled out the annotation name for "splitting
	 * into two dependent test methods" literally - don't do that: PHPUnit's doc-comment parser
	 * picks up that token anywhere in a method's docblock, even mid-sentence in prose, and tries
	 * to resolve it as a real dependency, which breaks running this test on its own with a
	 * "does not exist" warning and skips it entirely.)
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
	 * preprocessing step or a discarded duplicate produces server-side. Correct/expected
	 * behaviour: nodata() fires once the window has elapsed since the last real value, the same
	 * as it would with no updates at all. PASSES.
	 *
	 * The wait below has to span ~90s, not just the nodata() window itself: nodata() is a
	 * ZBX_FUNCTION_TYPE_TIMER function, rechecked periodically independent of new data
	 * (dbconfig.c dc_update_function_timer()/ZBX_TRIGGER_TIMER_DELAY, 30s) - but the very FIRST
	 * scheduling of that recheck after each server start is deliberately delayed by up to ~90s
	 * ("reduce server startup load", dc_schedule_trigger_timers()'s SEC_PER_MIN offset plus
	 * rounding). Since this class restarts the server fresh before every test (see class
	 * docblock), that ~90s startup delay applies fresh to every test run, and NOVALUE-only
	 * updates never trigger a data-driven recheck on their own (dbconfig.c
	 * zbx_dc_config_lock_triggers_by_history_items() skips locking triggers for a NOVALUE tail) -
	 * so the only way nodata() gets re-evaluated at all here is that one delayed periodic timer
	 * firing, and the wait must outlast it.
	 */
	public function testNoData_Discard() {
		$key = 'nodata.discard';

		$this->push($key, ['value' => 'first value']);
		$this->waitForTriggerValue($key, TRIGGER_VALUE_FALSE);

		$deadline = microtime(true) + 70;
		while (microtime(true) < $deadline) {
			$this->push($key); // no 'value' => ZBX_DC_FLAG_NOVALUE, models a discarded update
			sleep(4);
		}

		$trigger = null;
		try {
			$this->waitForTriggerValue($key, TRIGGER_VALUE_TRUE, 40);
			$trigger = $this->getTrigger($key);
		} catch (Exception $e) {
			$trigger = $this->getTrigger($key);
		}

		$this->assertEquals(TRIGGER_VALUE_TRUE, $trigger['value'],
			'nodata() did not fire after discard-only updates. Trigger state: '.json_encode($trigger));
	}

	/**
	 * "nodata with only metadata with logitem - check that nodata also works when there was only
	 * metadata with log and no actual value." Same shape as testNoData_Discard but with
	 * lastlogsize/mtime present, matching a log item whose agent keeps polling but finds nothing
	 * new to report. Correct/expected behaviour: nodata() fires once the window has elapsed since
	 * the last real value. See testNoData_Discard for why the wait below has to span past ~90s:
	 * this class restarts the server fresh before every test, and the very first scheduling of
	 * nodata()'s periodic recheck after each restart is deliberately delayed that long.
	 */
	public function testNoData_LogMetadataOnly() {
		$key = 'nodata.logmeta';
		$lastlogsize = 0;
		$tm = time();

		$this->push($key, ['value' => 'first log line', 'lastlogsize' => ++$lastlogsize, 'mtime' => $tm]);
		$this->waitForTriggerValue($key, TRIGGER_VALUE_FALSE);

		$deadline = microtime(true) + 70;
		while (microtime(true) < $deadline) {
			$this->push($key, ['lastlogsize' => ++$lastlogsize, 'mtime' => time()]); // metadata only
			sleep(4);
		}

		$trigger = null;
		try {
			$this->waitForTriggerValue($key, TRIGGER_VALUE_TRUE, 40);
			$trigger = $this->getTrigger($key);
		} catch (Exception $e) {
			$trigger = $this->getTrigger($key);
		}

		$this->assertEquals(TRIGGER_VALUE_TRUE, $trigger['value'],
			'nodata() did not fire after log-metadata-only updates. Trigger state: '.json_encode($trigger));
	}

	/**
	 * "nodata with values from past - check that nodata works if only values from past are
	 * received." A value old enough to fall outside the nodata() window must not be treated as
	 * fresh data: nodata() must still fire. PASSES.
	 *
	 * Pushes two current-clock values a few seconds apart first, before switching to
	 * backdated-only pushes - one is not enough here the way it is for
	 * testNoData_Discard/testNoData_LogMetadataOnly. This item's shared impersonated proxy
	 * (self::PROXY_NAME) has never made contact before this test's first push, so that very first
	 * packet gets treated by check_proxy_nodata() (proxy.c) as resuming from a connection gap - a
	 * deliberate mechanism that holds nodata() off for a bit after a proxy comes back, to give it
	 * a chance to sync any backlog (see zbx_dc_proxy_update_nodata()/ZBX_PROXY_SUPPRESS_MORE).
	 * That grace window only lifts once a value arrives with a clock *after* the moment the gap
	 * was flagged (proxy.c zbx_process_history_data(): values.ts.sec > nodata_win.period_end) -
	 * and the first push's own value is timestamped in that very same request that flags the gap,
	 * so it ties period_end instead of clearing it. A second push, a few seconds later, is
	 * unambiguously past that point and clears it before the backdated-only phase begins. A real
	 * proxy doesn't have this problem: it has already made normal contact (config sync,
	 * heartbeats) long before sending its first item value, so it isn't starting from "presumed
	 * just reconnected" the way a freshly-created impersonated proxy's very first packet is here.
	 */
	public function testNoData_ValuesFromPast() {
		$key = 'nodata.past';

		$this->push($key, ['value' => 1]);
		sleep(5);
		$this->push($key, ['value' => 1]);
		$this->waitForTriggerValue($key, TRIGGER_VALUE_FALSE);

		// This class doesn't use @suite-components-reuse (see class docblock), so the server
		// restarts fresh for every test, and zbx_dc_get_data_expected_from() (dbconfig.c) resets to
		// that restart's config-sync time for every host/item - the in-memory cache is rebuilt from
		// scratch on every restart, so everything looks "just created" to it. evaluate_NODATA()
		// refuses to fire while data_expected_from + period > now ("item does not have enough data
		// after server start"), which is unrelated to the actual bug under test here. Keep
		// resending the stale-clock value - each push forces a fresh trigger recalculation
		// (cachehistory_server.c queues+locks triggers for any item that received new data,
		// regardless of the value's clock) - so the wait covers both that startup grace window and
		// however long the real bug takes to (fail to) resolve, rather than depending on a guess at
		// either one.
		$deadline = microtime(true) + 60;
		$trigger = null;
		while (microtime(true) < $deadline) {
			$this->push($key, ['value' => 1, 'clock' => time() - 120]);
			sleep(3);

			$trigger = $this->getTrigger($key);
			if ($trigger['value'] == TRIGGER_VALUE_TRUE) {
				break;
			}
		}

		$this->assertEquals(TRIGGER_VALUE_TRUE, $trigger['value'],
			'nodata() never fired for past-only values. Trigger state: '.json_encode($trigger));
	}

	/**
	 * Literal reported case: nodata(item,30s) with an item polled every ~25s that genuinely
	 * flaps between a valid value and unsupported (e.g. a numeric item receiving a string) every
	 * few seconds - not merely staying unsupported throughout, which is the already-fixed case
	 * (testNoData_UnsupportedFires). Verify the trigger reaches PROBLEM and never shows UNKNOWN
	 * along the way. PASSES.
	 *
	 * The final wait below needs a larger budget than the nodata() window itself: the last valid
	 * value pushed during flapping keeps a real value inside the 30s window right up until the
	 * final (unsupported) push, so nodata() correctly still returns "not yet" at that instant -
	 * it only resolves once the window has genuinely elapsed with nothing valid landing in it.
	 *
	 * The item keeps being "polled" (pushed) every few seconds through the whole test, including
	 * after flapping ends - matching the reported scenario ("update interval 25 seconds") and a
	 * real, continuously-online proxy, which keeps heartbeating even with nothing new to report.
	 * Going fully silent after the last push would be unrealistic here and actively misleading:
	 * nodata()'s proxy-lag window widening (evalfunc.c evaluate_NODATA(), period = arg1 +
	 * (now - proxy's lastaccess)) has no decay once the proxy stops being heard from at all, so a
	 * real disconnect (nothing arriving, lastaccess frozen) is the one case where the window
	 * staying wide is correct, but that's not this scenario - this item's proxy is online and
	 * polling throughout, it just has nothing supported to report right now.
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
				$this->push($key, ['value' => 1]); // briefly valid/supported again
			}
			else {
				$this->push($key, ['state' => ITEM_STATE_NOTSUPPORTED, 'value' => 'not a number']);
			}

			$trigger = $this->getTrigger($key);
			$this->assertNotEquals(TRIGGER_STATE_UNKNOWN, $trigger['state'],
				'Trigger went UNKNOWN while flapping between supported and unsupported. Error: '.
				$trigger['error']);

			$toggle = !$toggle;
			sleep(5);
		}

		// End on unsupported so nodata() has a real chance to resolve to 1 once the window elapses -
		// but keep polling at the same cadence instead of going silent (see docblock above).
		$trigger = null;
		$deadline = microtime(true) + 90;
		while (microtime(true) < $deadline) {
			$this->push($key, ['state' => ITEM_STATE_NOTSUPPORTED, 'value' => 'not a number']);
			$trigger = $this->getTrigger($key);

			if ($trigger['value'] == TRIGGER_VALUE_TRUE) {
				break;
			}

			sleep(5);
		}

		$this->assertEquals(TRIGGER_VALUE_TRUE, $trigger['value'],
			'nodata() did not fire once the window elapsed after flapping. Trigger state: '.
			json_encode($trigger));
		$this->assertEquals(TRIGGER_STATE_NORMAL, $trigger['state'], 'Error: '.$trigger['error']);

		$after = $this->unknownEventCount();
		$this->assertEquals($before, $after, 'Unknown trigger event count changed: '.$before.' -> '.$after);
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
	 * Shared setup for both testNoData_ProxyGroupLogSkip_* methods, since each is fully
	 * independent (no test dependency annotation, no shared component uptime - every test in this
	 * class restarts server+proxy fresh, see class docblock) and so has to redo it: wait for the
	 * proxy group to
	 * be online and the host assigned to the real proxy, make sure the real proxy has synced the
	 * host/item, then send one matching log line and wait for the problem to open.
	 */
	/**
	 * Set the proxy-group log-skip trigger's expression (lazy or "strict" nodata()) and wait for
	 * the server to actually pick it up. Needed because the server for this test method already
	 * finished its own fresh startup config sync (onBeforeTestCase() restarts it before the test
	 * body runs - see class docblock) using whatever expression was in the DB at that point, which
	 * may not be the one this test needs; trigger.update() alone only changes the DB row, not the
	 * server's already-loaded in-memory config cache.
	 */
	private function setPgTriggerExpression(bool $strict): void {
		$mode = $strict ? ',"strict"' : '';
		$response = $this->call('trigger.update', [
			'triggerid' => self::$pg_triggerid,
			'expression' => 'length(last(/'.self::PG_HOSTNAME.'/'.self::PG_ITEM_KEY.'))>0 and '.
				'nodata(/'.self::PG_HOSTNAME.'/'.self::PG_ITEM_KEY.','.self::PG_NODATA_WINDOW_SEC.'s'.$mode.')=0'
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_dc_sync_configuration()', true, 90, 1,
				true);
	}

	private function pgLogSkipOpenProblem(): void {
		$pg_logline = 'Proxy group "'.self::PG_NAME.'" changed state from \b[a-z]+\b to online';
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $pg_logline, true, 90, 1, true);

		// Poll the API rather than wait for an "assigned hostid X to proxyid Y" log line here:
		// the assignment already exists in the host_proxy table from a previous test's run (DB
		// state persists across this class's per-test restarts, only in-memory state resets - see
		// class docblock), and it's not certain pg_manager re-logs that exact line for an
		// assignment that isn't actually changing, only that host.get eventually reflects it.
		$this->callUntilDataIsPresent('host.get', [
			'output' => ['hostid', 'assigned_proxyid'],
			'hostids' => [self::$pg_hostid]
		], 90, 1, function ($r) {
			return $r['result'][0]['assigned_proxyid'] == self::$pg_proxyid;
		});

		// Make sure the real proxy actually knows about the host/item before we push data to it.
		$this->reloadConfigurationCache(self::COMPONENT_PROXY);
		$this->waitForLogLineToBePresent(self::COMPONENT_PROXY, 'End of zbx_dc_sync_configuration()', true, 90, 1,
				true);

		$this->pushLogSkip('MATCH: something went wrong');

		// Same margin as waitForTriggerValue()'s default - see its comment.
		$this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$pg_triggerid],
			'output' => ['value', 'state']
		], 60, 1, function ($r) {
			return $r['result'][0]['value'] == TRIGGER_VALUE_TRUE;
		});

		$trigger = $this->getPgTrigger();
		$this->assertEquals(TRIGGER_VALUE_TRUE, $trigger['value']);
	}

	/**
	 * Proxy-group log-skip repro with the default (lazy) nodata(): one matching log line opens
	 * the problem, then the log stops producing matches but the item keeps sending metadata-only
	 * updates (lastlogsize/mtime, no value) as the agent keeps polling the file position.
	 * Correct/expected behaviour: the problem closes once the window elapses, same as with no
	 * updates at all. PASSES.
	 *
	 * As with testNoData_Discard/testNoData_LogMetadataOnly, metadata-only updates never trigger
	 * a data-driven recheck on their own, so nodata() only gets re-evaluated via its periodic
	 * timer - and that timer's first firing after each restart-per-test server start is delayed
	 * by up to ~90s (see testNoData_Discard docblock). The wait below has to span past that, not
	 * just PG_NODATA_WINDOW_SEC.
	 */
	public function testNoData_ProxyGroupLogSkip_LazyStaysOpen() {
		// Make sure the trigger is using the lazy (non-"strict") expression, regardless of test
		// execution order relative to testNoData_ProxyGroupLogSkip_StrictCloses.
		$this->setPgTriggerExpression(false);

		$this->pgLogSkipOpenProblem();

		$deadline = microtime(true) + 100;
		$trigger = null;
		while (microtime(true) < $deadline) {
			$this->pushLogSkip(null);
			sleep(self::PG_HEARTBEAT_INTERVAL_SEC);

			$trigger = $this->getPgTrigger();
			if ($trigger['value'] == TRIGGER_VALUE_FALSE) {
				break;
			}
		}

		$this->assertEquals(TRIGGER_VALUE_FALSE, $trigger['value'],
			'Problem did not close after the nodata() window elapsed with only metadata-only log '.
			'updates (no real value), even though the proxy stayed online throughout. Trigger state: '.
			json_encode($trigger));
	}

	/**
	 * Same repro as testNoData_ProxyGroupLogSkip_LazyStaysOpen, but with nodata(...,"strict")
	 * instead of the default lazy mode - the reported workaround. "strict" bypasses the affected
	 * code path entirely, so the problem closes once the window elapses. PASSES.
	 *
	 * Same as testNoData_ProxyGroupLogSkip_LazyStaysOpen, the wait below has to span the ~90s
	 * startup-timer delay (see testNoData_Discard docblock): "strict" only changes what happens
	 * once nodata() gets evaluated, not whether/when it gets re-evaluated at all - it is still the
	 * same ZBX_FUNCTION_TYPE_TIMER function, and the metadata-only heartbeats here still can't
	 * trigger a data-driven recheck on their own.
	 */
	public function testNoData_ProxyGroupLogSkip_StrictCloses() {
		$this->setPgTriggerExpression(true);

		$this->pgLogSkipOpenProblem();

		// Keep sending metadata-only heartbeats, same shape as the lazy case - "strict" mode
		// should close the problem regardless, since it no longer treats the proxied item's
		// cached tail state as "transfer in progress".
		$this->callUntilDataIsPresent('trigger.get', [
			'triggerids' => [self::$pg_triggerid],
			'output' => ['value', 'state']
		], 110, 1, function ($r) {
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

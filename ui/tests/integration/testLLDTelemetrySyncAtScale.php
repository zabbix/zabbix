<?php declare(strict_types = 1);
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
require_once __DIR__.'/../../include/classes/api/item_types/CItemType.php';
require_once __DIR__.'/../../include/classes/api/item_types/CItemTypeTelemetryQuery.php';

/**
 * Test suite that exercises APM (OpenTelemetry) data at scale: device status gauges are sent over OTLP/gRPC
 * to the proxy APM collector, stored in ClickHouse and read back by Telemetry query items on the server.
 * HOST_COUNT hosts stand for sites of LLD_DISCOVERY_COUNT devices each, every device reports the site it belongs
 * to in the "site" resource attribute and each host has a single Telemetry query item for the devices of its site.
 * Devices are discovered from their own data by a second Telemetry query on each host, the LLD rule, which
 * returns one row per device that reported within DISCOVERY_WINDOW. Per-device status is fanned out to
 * LLD-discovered dependent items (one per device), and trigger prototypes on them must fire and recover for
 * exactly the devices whose status changed.
 *
 * Macros are not supported in the query, so the site filter cannot come from a template: every host has its own
 * query item, LLD rule and prototypes.
 *
 * Each query result row is a separate value, so every row is processed by all dependent items of its host:
 * PROBLEM_DEVICE_COUNT * LLD_DISCOVERY_COUNT JSONPath lookups per host and round (indexed, master value parsed
 * once).
 *
 * Requires: proxy built with APM support, ClickHouse with otel_* tables, grpcurl in PATH.
 *
 * @required-components server, proxy
 * @suite-components-reuse true
 * @configurationDataProvider configurationProvider
 * @onAfter clearData
 */
class testLLDTelemetrySyncAtScale extends CIntegrationTest {

	const CLICKHOUSE_TIMEOUT = 10;

	const COLLECTOR_ADDRESS = 'localhost:4317';
	const PROTO_DIR = PHPUNIT_BASEDIR.'/src/zabbix_proxy/apm/';
	const METRICS_PROTO = 'opentelemetry/proto/collector/metrics/v1/metrics_service.proto';
	const METRICS_METHOD = 'opentelemetry.proto.collector.metrics.v1.MetricsService/Export';
	const OTLP_TIMEOUT = 10;

	const HOSTNAME = 'test_lld_telemetry_sync_at_scale';
	const PROXY_NAME = 'test_lld_telemetry_sync_at_scale_proxy';
	const SERVICE_NAME = 'telemetry-sync-at-scale';
	const METRIC_NAME = 'device.status';
	const TQ_ITEM_KEY = 'apm.device.status.query';
	const LLD_RULE_KEY = 'apm.device.discovery';
	const LLD_MACRO = '{#SENSOR}';
	const ITEM_PROTO_KEY = 'apm.device.status';
	const SITE_ATTRIBUTE = 'site';
	const SENSOR_BASE = 'sensor';
	const PREFLIGHT_SITE = 'apm_preflight';
	const PREFLIGHT_DEVICE = 'apm_preflight';

	const HOST_COUNT = 4;
	/* every device is a row of the discovery query, which must not exceed ZBX_TQ_MAX_RESULT_ROWS (10000) */
	const LLD_DISCOVERY_COUNT = 8196;
	const PROBLEM_DEVICE_COUNT = 8196;
	const OTLP_BATCH_SIZE = 1000;
	const LLD_ITERATIONS = 60;
	const TRIGGER_ITERATIONS = 60;
	const CLICKHOUSE_WAIT_SECONDS = 60;
	const PREFLIGHT_RESEND_SECONDS = 10;
	const STORED_STATUS_WINDOW = '1d';
	/* ZBX_TQ_STORAGE_MODE_SINGLE: one value with all buckets, "[]" when none (multiple values mode sets no value) */
	const TQ_STORAGE_MODE_SINGLE = 1;

	/* granularity == delay keeps query windows contiguous, so each sent data point yields exactly one row */
	const TQ_GRANULARITY = '5s';
	const TQ_DELAY = '5s';
	const TQ_TIME_SHIFT = '5s';
	const TQ_LOOKBACK_LIMIT = '1h';

	/* devices that reported within the window are discovered, one bucket per window gives one row per device */
	const DISCOVERY_DELAY = '1m';
	const DISCOVERY_WINDOW = '1h';

	/* host name => hostid, host name is also the site of its devices */
	private static $hostids = [];
	private static $lld_ruleids = [];
	private static $proxyid;

	/**
	 * Component configuration provider.
	 *
	 * @return array
	 */
	public function configurationProvider() {
		// TelemetryProvider of server and proxy comes from $TELEMETRY_PROVIDERS of the frontend configuration.
		return [
			self::COMPONENT_SERVER => [
				'LogFileSize' => 8,
				'DebugLevel' => 3,
				'CacheSize' => '128M',
				'TrendCacheSize' => '128M',
				'HistoryCacheSize' => '128M',
				'HistoryIndexCacheSize' => '128M',
				'ValueCacheSize' => '128M',
				'LogSlowQueries' => '60000',
				'StartDBSyncers' => '4',
				'StartLLDProcessors' => self::HOST_COUNT,
				'StartTelemetryQueryPollers' => '1'
			],
			self::COMPONENT_PROXY => [
				'ProxyMode' => PROXY_OPERATING_MODE_ACTIVE,
				'Hostname' => self::PROXY_NAME,
				'Server' => '127.0.0.1:'.PHPUNIT_PORT_PREFIX.self::SERVER_PORT_SUFFIX,
				'ListenPort' => PHPUNIT_PORT_PREFIX.self::PROXY_PORT_SUFFIX,
				'ConfigFrequency' => 1,
				'DataSenderFrequency' => 1,
				'LogFileSize' => 8,
				'DebugLevel' => 3,
				'LogSlowQueries' => '60000',
				'StartAPMCollectors' => 1
			]
		];
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		global $TELEMETRY_PROVIDERS;

		if (empty($TELEMETRY_PROVIDERS)) {
			$this->markTestSkipped('$TELEMETRY_PROVIDERS is not set in ui/conf/zabbix.conf.php.');
		}

		$this->truncateOTData();

		$this->call('settings.update', ['auditlog_enabled' => 0, 'auditlog_mode' => 0]);

		// The proxy is only used as the APM (OTLP) collector; the hosts are monitored by the server.
		$response = $this->call('proxy.create', [
			'name' => self::PROXY_NAME,
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
			'hosts' => [],
			'apm' => [
				'data_collection_status' => 1
			]
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['proxyids']);
		self::$proxyid = $response['result']['proxyids'][0];

		return true;
	}

	/**
	 * Create a host for every site.
	 */
	private function createSiteHosts(): void {
		$response = $this->call('hostgroup.get', [
			'filter' => ['name' => ['Zabbix servers']],
			'output' => ['groupid']
		]);
		$this->assertNotEmpty($response['result'], 'Host group "Zabbix servers" not found.');
		$groupid = $response['result'][0]['groupid'];

		foreach (self::hostNames() as $host) {
			self::$hostids[$host] = $this->createSiteHost($host, $groupid);
		}
	}

	/**
	 * Create host with the Telemetry query item for devices of its site, Telemetry query LLD rule discovering the
	 * devices of its site and per-device dependent items and triggers.
	 */
	private function createSiteHost(string $host, string $groupid): string {
		$response = $this->call('host.create', [
			'host' => $host,
			'interfaces' => [],
			'groups' => [['groupid' => $groupid]],
			'status' => HOST_STATUS_MONITORED
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		$hostid = $response['result']['hostids'][0];

		// Single query for all devices of the site: one row per device that reported in the window.
		$response = $this->call('item.create', [
			'hostid' => $hostid,
			'name' => 'APM device status query',
			'key_' => self::TQ_ITEM_KEY,
			'type' => ITEM_TYPE_TELEMETRY_QUERY,
			'value_type' => ITEM_VALUE_TYPE_TEXT,
			// The value holds rows of all devices, storing it on every poll is too expensive.
			'history' => '0',
			'timeout' => '3s',
			'delay' => self::TQ_DELAY,
			'time_shift' => self::TQ_TIME_SHIFT,
			'lookback_limit' => self::TQ_LOOKBACK_LIMIT,
			'granularity' => self::TQ_GRANULARITY,
			'query' => [
				'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS,
				'metric_point_type' => CItemTypeTelemetryQuery::METRICS_POINT_GAUGE,
				'columns' => [
					['column' => 'ResourceAttributes', 'attribute_key' => 'host.name']
				],
				'aggregated_columns' => [
					['column' => 'Value', 'function' => AGGREGATE_MAX, 'parameters' => [], 'alias' => 'value']
				],
				'filter' => self::siteStatusFilter($host)
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		$tq_itemid = $response['result']['itemids'][0];

		// Granularity and lookback limit equal to the window give a single bucket, polling more often than the
		// window slides it, so every poll returns each device that reported within the last window once. Every
		// poll reads the whole window again and late data is picked up by the next one, so there is no time shift.
		$response = $this->call('discoveryrule.create', [
			'hostid' => $hostid,
			'name' => 'APM device discovery',
			'key_' => self::LLD_RULE_KEY,
			'type' => ITEM_TYPE_TELEMETRY_QUERY,
			'timeout' => '3s',
			'delay' => self::DISCOVERY_DELAY,
			'time_shift' => '0s',
			'lookback_limit' => self::DISCOVERY_WINDOW,
			'granularity' => self::DISCOVERY_WINDOW,
			'query' => [
				'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS,
				'metric_point_type' => CItemTypeTelemetryQuery::METRICS_POINT_GAUGE,
				'columns' => [
					['column' => 'ResourceAttributes', 'attribute_key' => 'host.name']
				],
				'aggregated_columns' => [
					['column' => '', 'function' => AGGREGATE_COUNT, 'parameters' => [], 'alias' => 'cnt']
				],
				'filter' => self::siteStatusFilter($host)
			],
			'lld_macro_paths' => [
				['lld_macro' => self::LLD_MACRO, 'path' => '$["ResourceAttributes.host.name"]']
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		$lld_ruleid = $response['result']['itemids'][0];
		self::$lld_ruleids[] = $lld_ruleid;

		// Each dependent item keeps only rows of its own device and extracts the aggregated status. JSONPath as
		// the first step lets all dependent items share one parsed and indexed master value, rows of other
		// devices do not match and are discarded.
		$response = $this->call('itemprototype.create', [
			'hostid' => $hostid,
			'ruleid' => $lld_ruleid,
			'name' => 'APM device status ['.self::LLD_MACRO.']',
			'key_' => self::ITEM_PROTO_KEY.'['.self::LLD_MACRO.']',
			'type' => ITEM_TYPE_DEPENDENT,
			'master_itemid' => $tq_itemid,
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'preprocessing' => [
				[
					'type' => ZBX_PREPROC_JSONPATH,
					'params' => '$[?(@["ResourceAttributes.host.name"] == "'.self::LLD_MACRO.'")].value.first()',
					'error_handler' => ZBX_PREPROC_FAIL_DISCARD_VALUE,
					'error_handler_params' => ''
				]
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);

		$response = $this->call('triggerprototype.create', [
			'description' => 'APM device '.self::LLD_MACRO.' status problem',
			'expression' => 'last(/'.$host.'/'.self::ITEM_PROTO_KEY.'['.self::LLD_MACRO.'])=1',
			'priority' => TRIGGER_SEVERITY_AVERAGE
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);

		return $hostid;
	}

	/**
	 * Query filter for status data points of devices of the site.
	 */
	private static function siteStatusFilter(string $site): array {
		return [
			'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
			'formula' => '',
			'conditions' => [
				[
					'column' => 'MetricName',
					'attribute_key' => '',
					'operator' => CONDITION_OPERATOR_EQUAL,
					'value' => self::METRIC_NAME
				],
				[
					'column' => 'ResourceAttributes',
					'attribute_key' => self::SITE_ATTRIBUTE,
					'operator' => CONDITION_OPERATOR_EQUAL,
					'value' => $site
				]
			]
		];
	}

	public static function clearData(): void {
		if (CAPIHelper::getSessionId() === null) {
			CAPIHelper::authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		}

		if (self::$hostids) {
			$response = CAPIHelper::call('host.delete', array_values(self::$hostids));
			self::assertArrayHasKey('hostids', $response['result']);
			self::$hostids = [];
			self::$lld_ruleids = [];
		}

		if (self::$proxyid !== null) {
			$response = CAPIHelper::call('proxy.delete', [self::$proxyid]);
			self::assertArrayHasKey('proxyids', $response['result']);
			self::$proxyid = null;
		}

		CAPIHelper::call('settings.update', ['auditlog_enabled' => 1, 'auditlog_mode' => 1]);
	}

	/**
	 * Make sure the proxy APM collector accepts data and writes it to ClickHouse before relying on it.
	 * The proxy receives its APM configuration from the server, so the first sends may be rejected or dropped.
	 */
	public function testLLDTelemetrySyncAtScale_IngestPreflight() {
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		// Proxy monitors no hosts, it only needs the APM configuration from the server.
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY);

		// Resend periodically, data sent before the proxy has APM configuration may be dropped.
		$baseline = $this->getStoredStatusCount(0);
		$deadline = time() + 30;
		$next_send = time();
		$count = $baseline;
		$send_error = null;
		while ($count === $baseline && time() < $deadline) {
			if (time() >= $next_send) {
				$error = null;
				if (!$this->sendDeviceStatus([self::PREFLIGHT_SITE => [self::PREFLIGHT_DEVICE]], 0, $error)) {
					$send_error = $error;
				}
				$next_send = time() + self::PREFLIGHT_RESEND_SECONDS;
			}

			sleep(self::WAIT_ITERATION_DELAY);

			$count = $this->getStoredStatusCount(0);
		}

		$this->assertGreaterThan($baseline, $count,
				'Preflight data point did not reach ClickHouse through the proxy APM collector.'.
				"\nLast send error: ".($send_error === null ? 'none' : $send_error));
	}

	/**
	 * Create the site hosts, then every device reports status=0 once and the LLD rule of every host discovers the
	 * devices of its site from the stored data. Wait until one dependent item and one trigger is discovered per
	 * device.
	 *
	 * @depends testLLDTelemetrySyncAtScale_IngestPreflight
	 */
	public function testLLDTelemetrySyncAtScale_LLDDiscovery() {
		$devices = [];
		foreach (self::hostNames() as $host) {
			$devices[$host] = self::deviceNames();
		}
		$this->sendStatusAndWaitStored($devices, 0);

		$this->createSiteHosts();
		// Data is already stored when the first poll of the LLD rule runs, so devices are discovered by it.
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		// Do not wait for the first scheduled poll of the LLD rules, it may be up to DISCOVERY_DELAY away.
		$tasks = [];
		foreach (self::$lld_ruleids as $lld_ruleid) {
			$tasks[] = ['type' => ZBX_TM_TASK_CHECK_NOW, 'request' => ['itemid' => $lld_ruleid]];
		}
		$response = $this->call('task.create', $tasks);
		$this->assertArrayHasKey('taskids', $response['result']);

		// Discovery work grows with the number of hosts.
		$this->callUntilCountIsPresent('item.get', [
			'hostids' => array_values(self::$hostids),
			'search' => ['key_' => self::ITEM_PROTO_KEY.'[']
		], self::LLD_DISCOVERY_COUNT * self::HOST_COUNT, self::LLD_ITERATIONS * self::HOST_COUNT,
				self::WAIT_ITERATION_DELAY);

		$this->callUntilCountIsPresent('trigger.get', [
			'hostids' => array_values(self::$hostids),
			'search' => ['description' => 'APM device ']
		], self::LLD_DISCOVERY_COUNT * self::HOST_COUNT, self::LLD_ITERATIONS * self::HOST_COUNT,
				self::WAIT_ITERATION_DELAY);

		// Make discovered dependent items and triggers known to preprocessing and history sync.
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
	}

	/**
	 * Send status=1 for the problem devices of all sites and wait until it is stored in ClickHouse.
	 *
	 * @depends testLLDTelemetrySyncAtScale_LLDDiscovery
	 */
	public function testLLDTelemetrySyncAtScale_TriggerFiringSend() {
		$this->sendStatusAndWaitStored(self::problemDevices(), 1);
	}

	/**
	 * Verify that exactly the triggers of the problem devices fire on every host.
	 *
	 * @depends testLLDTelemetrySyncAtScale_TriggerFiringSend
	 */
	public function testLLDTelemetrySyncAtScale_TriggerFiringVerify() {
		$this->waitForOpenProblemCount(self::PROBLEM_DEVICE_COUNT * self::HOST_COUNT);

		foreach (self::problemDevices() as $host => $devices) {
			$this->assertProblemDevices($host, $devices);
		}
	}

	/**
	 * Send status=0 for the same devices and wait until it is stored in ClickHouse.
	 *
	 * @depends testLLDTelemetrySyncAtScale_TriggerFiringVerify
	 */
	public function testLLDTelemetrySyncAtScale_TriggerRecoverySend() {
		$this->sendStatusAndWaitStored(self::problemDevices(), 0);
	}

	/**
	 * Verify that all triggers recover.
	 *
	 * @depends testLLDTelemetrySyncAtScale_TriggerRecoverySend
	 */
	public function testLLDTelemetrySyncAtScale_TriggerRecoveryVerify() {
		$this->waitForOpenProblemCount(0);
	}

	private static function hostNames(): array {
		$hosts = [];
		for ($i = 1; $i <= self::HOST_COUNT; $i++) {
			$hosts[] = self::HOSTNAME.'_'.$i;
		}

		return $hosts;
	}

	/**
	 * Device names are the same on every site, devices are told apart by the site resource attribute.
	 */
	private static function deviceNames(): array {
		$devices = [];
		for ($i = 1; $i <= self::LLD_DISCOVERY_COUNT; $i++) {
			$devices[] = self::SENSOR_BASE.$i;
		}

		return $devices;
	}

	/**
	 * Problem devices of every site.
	 *
	 * @return array  host name => device names
	 */
	private static function problemDevices(): array {
		$devices = [];
		foreach (self::hostNames() as $index => $host) {
			$devices[$host] = self::problemDeviceNames($index);
		}

		return $devices;
	}

	/**
	 * Evenly spread devices, on the first site starting with sensor1, so prefix collisions (sensor1 vs
	 * sensor10..19) are covered. Every next site starts one device later, so sites do not have the same problem
	 * devices and rows of another site leaking into the query of a host fire a trigger that must not fire.
	 */
	private static function problemDeviceNames(int $site_index): array {
		$step = intdiv(self::LLD_DISCOVERY_COUNT, self::PROBLEM_DEVICE_COUNT);
		$devices = [];
		for ($i = 0; $i < self::PROBLEM_DEVICE_COUNT; $i++) {
			$devices[] = self::SENSOR_BASE.(1 + ($i * $step + $site_index) % self::LLD_DISCOVERY_COUNT);
		}

		return $devices;
	}

	/**
	 * @param array $devices  site => device names
	 */
	private function sendStatusAndWaitStored(array $devices, int $status): void {
		$baseline = $this->getStoredStatusCount($status);

		$error = null;
		$this->assertTrue($this->sendDeviceStatus($devices, $status, $error),
				'Failed to send status='.$status.' data points: '.$error);

		$this->assertStoredStatusIncreasedBy($status, $baseline, array_sum(array_map('count', $devices)));
	}

	private function waitForOpenProblemCount(int $expected): void {
		// Preprocessing work grows with the number of hosts.
		$this->callUntilCountIsPresent('problem.get', [
			'hostids' => array_values(self::$hostids),
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS
		], $expected, self::TRIGGER_ITERATIONS * self::HOST_COUNT, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Assert that exactly the expected devices of the host have a trigger in PROBLEM state, so a matching count is
	 * not made of the wrong devices (e.g. sensor1 rows matching sensor10..19, or rows of another site).
	 */
	private function assertProblemDevices(string $host, array $expected): void {
		$response = $this->call('trigger.get', [
			'hostids' => [self::$hostids[$host]],
			'filter' => ['value' => TRIGGER_VALUE_TRUE],
			'output' => ['triggerid'],
			'selectItems' => ['key_']
		]);

		$actual = [];
		foreach ($response['result'] as $trigger) {
			$this->assertCount(1, $trigger['items'], 'Unexpected trigger: '.json_encode($trigger));
			$this->assertEquals(1, preg_match('/^'.preg_quote(self::ITEM_PROTO_KEY, '/').'\[(.+)\]$/',
					$trigger['items'][0]['key_'], $match), 'Unexpected item key: '.$trigger['items'][0]['key_']);
			$actual[] = $match[1];
		}

		sort($expected);
		sort($actual);

		$this->assertEquals($expected, $actual, 'Devices with triggers in PROBLEM state on '.$host.
				' do not match: missing ['.implode(',', array_diff($expected, $actual)).'], unexpected ['.
				implode(',', array_diff($actual, $expected)).'].');
	}

	/**
	 * Send one device.status gauge data point per device, one OTLP resource per device, batched. A batch may hold
	 * devices of several sites, as when sent by a collector.
	 *
	 * @param array $devices  site => device names
	 */
	private function sendDeviceStatus(array $devices, int $status, ?string &$error = null): bool {
		$site_devices = [];
		foreach ($devices as $site => $names) {
			foreach ($names as $device) {
				$site_devices[] = [$site, $device];
			}
		}

		foreach (array_chunk($site_devices, self::OTLP_BATCH_SIZE) as $batch) {
			// Stamp every request when it is sent like a device would, with a single stamp for all requests the
			// last ones could arrive after the query window of the stamp has been read.
			$time_ns = (string) (int) (microtime(true) * 1e9);

			$resource_metrics = [];
			foreach ($batch as [$site, $device]) {
				$resource_metrics[] = [
					'resource' => [
						'attributes' => [
							['key' => 'service.name', 'value' => ['stringValue' => self::SERVICE_NAME]],
							['key' => self::SITE_ATTRIBUTE, 'value' => ['stringValue' => (string) $site]],
							['key' => 'host.name', 'value' => ['stringValue' => $device]]
						]
					],
					'scopeMetrics' => [
						[
							'scope' => ['name' => self::SERVICE_NAME],
							'metrics' => [
								[
									'name' => self::METRIC_NAME,
									'gauge' => [
										'dataPoints' => [
											[
												'timeUnixNano' => $time_ns,
												'asDouble' => (float) $status
											]
										]
									]
								]
							]
						]
					]
				];
			}

			if (!$this->sendOTLP(json_encode(['resourceMetrics' => $resource_metrics], JSON_THROW_ON_ERROR),
					$error)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Send OTLP payload with grpcurl. Payload is passed through a file because a single command line argument
	 * is limited to 128KB.
	 */
	private function sendOTLP(string $payload, ?string &$error = null): bool {
		$file = tempnam(sys_get_temp_dir(), 'otlp');
		if ($file === false) {
			$error = 'Cannot create temporary file for OTLP payload.';

			return false;
		}

		if (file_put_contents($file, $payload) === false) {
			unlink($file);
			$error = 'Cannot write OTLP payload to '.$file;

			return false;
		}

		$cmd = 'grpcurl -plaintext'.
				' -max-time '.self::OTLP_TIMEOUT.
				' -import-path '.escapeshellarg(self::PROTO_DIR).
				' -proto '.escapeshellarg(self::METRICS_PROTO).
				' -d @ '.escapeshellarg(self::COLLECTOR_ADDRESS).' '.escapeshellarg(self::METRICS_METHOD).
				' < '.escapeshellarg($file).' 2>&1';

		$output_lines = [];
		$exit_code = 0;
		exec($cmd, $output_lines, $exit_code);

		unlink($file);

		if ($exit_code !== 0) {
			$error = 'grpcurl exit code '.$exit_code.', output: '.implode("\n", $output_lines);

			return false;
		}

		return true;
	}

	/**
	 * Telemetry query item tested on the server to read back stored status data points the same way the query
	 * item reads them. Lookback equal to granularity gives a single bucket, so the result is a single row; the OTel
	 * tables are truncated in prepareData, so a day long window holds every data point of the suite. Status is
	 * only ever 0 or 1, so the sum is the number of status=1 data points.
	 */
	private static function storedStatusItem(): array {
		$query = [
			'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS,
			'metric_point_type' => CItemTypeTelemetryQuery::METRICS_POINT_GAUGE,
			'columns' => [],
			'aggregated_columns' => [
				['column' => '', 'function' => AGGREGATE_COUNT, 'parameters' => [], 'alias' => 'cnt'],
				['column' => 'Value', 'function' => AGGREGATE_SUM, 'parameters' => [], 'alias' => 'sum']
			],
			'filter' => [
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
				'formula' => '',
				'conditions' => [
					[
						'column' => 'MetricName',
						'attribute_key' => '',
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => self::METRIC_NAME
					]
				]
			]
		];

		return [
			'type' => ITEM_TYPE_TELEMETRY_QUERY,
			'value_type' => ITEM_VALUE_TYPE_TEXT,
			'key' => self::TQ_ITEM_KEY,
			'timeout' => self::CLICKHOUSE_TIMEOUT.'s',
			'time_shift' => '0s',
			'lookback_limit' => self::STORED_STATUS_WINDOW,
			'granularity' => self::STORED_STATUS_WINDOW,
			'storage_mode' => self::TQ_STORAGE_MODE_SINGLE,
			'query' => json_encode($query, JSON_THROW_ON_ERROR)
		];
	}

	/**
	 * Number of stored data points per status from the storedStatusItem() test result, or null when the result
	 * is not usable.
	 *
	 * @return array|null  [0 => status=0 count, 1 => status=1 count]
	 */
	private static function parseStoredStatusCounts($result): ?array {
		if ($result === false || !isset($result['item']['result'])) {
			return null;
		}

		$buckets = json_decode($result['item']['result'], true);

		if ($buckets === []) {
			return [0, 0];
		}

		if (!is_array($buckets) || count($buckets) !== 1 || !isset($buckets[0]['cnt'])
				|| !isset($buckets[0]['sum'])) {
			return null;
		}

		$count = (int) $buckets[0]['cnt'];
		$ones = (int) $buckets[0]['sum'];

		return [$count - $ones, $ones];
	}

	private function getStoredStatusCount(int $status): int {
		$result = $this->callTestItemUntilCallback(self::storedStatusItem(),
			fn($result) => self::parseStoredStatusCounts($result) !== null,
			['single' => false, 'state' => 0], self::CLICKHOUSE_WAIT_SECONDS * 1000
		);

		return self::parseStoredStatusCounts($result)[$status];
	}

	private function assertStoredStatusIncreasedBy(int $status, int $baseline, int $increase): void {
		$expected = $baseline + $increase;

		$result = $this->callTestItemUntilCallback(self::storedStatusItem(),
			function ($result) use ($status, $expected) {
				$counts = self::parseStoredStatusCounts($result);

				return $counts !== null && $counts[$status] >= $expected;
			},
			['single' => false, 'state' => 0], self::CLICKHOUSE_WAIT_SECONDS * 1000
		);

		$this->assertEquals($expected, self::parseStoredStatusCounts($result)[$status],
				'Unexpected number of status='.$status.' data points stored in ClickHouse: '.
				$result['item']['result']);
	}

	/**
	 * The ClickHouse telemetry provider of the frontend configuration, the same one server and proxy are configured
	 * with (see getDefaultComponentConfiguration()).
	 */
	private function getClickHouseProvider(): array {
		global $TELEMETRY_PROVIDERS;

		if (isset($TELEMETRY_PROVIDERS)) {
			foreach ($TELEMETRY_PROVIDERS as $provider) {
				if ($provider['provider'] === 'clickhouse') {
					return $provider + ['db' => '', 'username' => '', 'password' => ''];
				}
			}
		}

		$this->fail('No ClickHouse provider in $TELEMETRY_PROVIDERS, set it in ui/conf/zabbix.conf.php.');
	}

	private function truncateOTData(): void {
		$provider = $this->getClickHouseProvider();

		$error = null;
		$this->assertNotNull(
			$this->queryClickHouse('truncate all tables from '.$provider['db']." like 'otel_%'", $error),
			'Cannot truncate OTel tables: '.$error
		);
	}

	private function queryClickHouse(string $sql, ?string &$error = null): ?string {
		$provider = $this->getClickHouseProvider();
		$handle = curl_init();

		curl_setopt_array($handle, [
			CURLOPT_URL => rtrim($provider['url'], '/').'/?database='.urlencode($provider['db']),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $sql,
			CURLOPT_HTTPHEADER => ['Content-Type: text/plain'],
			CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
			CURLOPT_USERNAME => $provider['username'],
			CURLOPT_PASSWORD => $provider['password'],
			CURLOPT_CONNECTTIMEOUT => self::CLICKHOUSE_TIMEOUT,
			CURLOPT_TIMEOUT => self::CLICKHOUSE_TIMEOUT
		]);

		$response = curl_exec($handle);
		$http_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

		if ($response === false) {
			$error = 'curl error: '.curl_error($handle).', query: '.$sql;

			return null;
		}

		if ($http_code !== 200) {
			$error = 'HTTP code '.$http_code.', response: '.trim($response).', query: '.$sql;

			return null;
		}

		return $response;
	}
}

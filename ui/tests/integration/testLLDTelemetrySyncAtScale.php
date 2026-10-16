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
 * to the proxy APM collector, stored in ClickHouse and read back by a single Telemetry query item on the
 * server. Per-device status is fanned out to LLD-discovered dependent items (one per device), and trigger
 * prototypes on them must fire and recover for exactly the devices whose status changed.
 *
 * Each query result row is a separate value, so every row is processed by all dependent items:
 * PROBLEM_DEVICE_COUNT * LLD_DISCOVERY_COUNT JSONPath lookups per round (indexed, master value parsed once).
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
	const TEMPLATE_NAME = 'test_lld_telemetry_sync_at_scale_template';
	const PROXY_NAME = 'test_lld_telemetry_sync_at_scale_proxy';
	const SERVICE_NAME = 'telemetry-sync-at-scale';
	const METRIC_NAME = 'device.status';
	const TQ_ITEM_KEY = 'apm.device.status.query';
	const LLD_RULE_KEY = 'apm.device.discovery';
	const LLD_MACRO = '{#SENSOR}';
	const ITEM_PROTO_KEY = 'apm.device.status';
	const SENSOR_BASE = 'sensor';
	const PREFLIGHT_DEVICE = 'apm_preflight';

	const LLD_DISCOVERY_COUNT = 10000;
	const PROBLEM_DEVICE_COUNT = 10;
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

	private static $hostid;
	private static $templateid;
	private static $proxyid;
	private static $tq_itemid;
	private static $lld_ruleid;

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
				'HistoryCacheSize' => '32M',
				'HistoryIndexCacheSize' => '32M',
				'ValueCacheSize' => '128M',
				'LogSlowQueries' => '60000',
				'StartDBSyncers' => '4',
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
		$this->truncateOTData();

		$this->call('settings.update', ['auditlog_enabled' => 0, 'auditlog_mode' => 0]);

		$response = $this->call('hostgroup.get', [
			'filter' => ['name' => ['Zabbix servers']],
			'output' => ['groupid']
		]);
		$this->assertNotEmpty($response['result'], 'Host group "Zabbix servers" not found.');
		$groupid = $response['result'][0]['groupid'];

		// The proxy is only used as the APM (OTLP) collector; the host is monitored by the server.
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

		$response = $this->call('templategroup.get', [
			'filter' => ['name' => ['Templates']],
			'output' => ['groupid']
		]);
		$this->assertNotEmpty($response['result'], 'Template group "Templates" not found.');
		$templategroupid = $response['result'][0]['groupid'];

		$response = $this->call('template.create', [
			'host' => self::TEMPLATE_NAME,
			'groups' => [['groupid' => $templategroupid]]
		]);
		$this->assertArrayHasKey('templateids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['templateids']);
		self::$templateid = $response['result']['templateids'][0];

		// Single query for all devices: one row per device that reported in the window.
		$response = $this->call('item.create', [
			'hostid' => self::$templateid,
			'name' => 'APM device status query',
			'key_' => self::TQ_ITEM_KEY,
			'type' => ITEM_TYPE_TELEMETRY_QUERY,
			'value_type' => ITEM_VALUE_TYPE_TEXT,
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
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$tq_itemid = $response['result']['itemids'][0];

		// Device list is pushed separately, the query item only carries status changes.
		$response = $this->call('discoveryrule.create', [
			'hostid' => self::$templateid,
			'name' => 'APM device discovery',
			'key_' => self::LLD_RULE_KEY,
			'type' => ITEM_TYPE_TRAPPER,
			'lifetime_type' => ZBX_LLD_DELETE_IMMEDIATELY
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$lld_ruleid = $response['result']['itemids'][0];

		// Each dependent item keeps only rows of its own device and extracts the aggregated status. JSONPath as
		// the first step lets all dependent items share one parsed and indexed master value, rows of other
		// devices do not match and are discarded.
		$response = $this->call('itemprototype.create', [
			'hostid' => self::$templateid,
			'ruleid' => self::$lld_ruleid,
			'name' => 'APM device status ['.self::LLD_MACRO.']',
			'key_' => self::ITEM_PROTO_KEY.'['.self::LLD_MACRO.']',
			'type' => ITEM_TYPE_DEPENDENT,
			'master_itemid' => self::$tq_itemid,
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'preprocessing' => [
				[
					'type' => ZBX_PREPROC_JSONPATH,
					'params' => '$[?(@.columns["ResourceAttributes.host.name"] == "'.self::LLD_MACRO.'")].columns.value.first()',
					'error_handler' => ZBX_PREPROC_FAIL_DISCARD_VALUE,
					'error_handler_params' => ''
				]
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);

		$response = $this->call('triggerprototype.create', [
			'description' => 'APM device '.self::LLD_MACRO.' status problem',
			'expression' => 'last(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY.'['.self::LLD_MACRO.'])=1',
			'priority' => TRIGGER_SEVERITY_AVERAGE
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);

		$response = $this->call('host.create', [
			'host' => self::HOSTNAME,
			'interfaces' => [],
			'groups' => [['groupid' => $groupid]],
			'templates' => [['templateid' => self::$templateid]],
			'status' => HOST_STATUS_MONITORED
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		return true;
	}

	public static function clearData(): void {
		if (CAPIHelper::getSessionId() === null) {
			CAPIHelper::authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		}

		if (self::$hostid !== null) {
			$response = CAPIHelper::call('host.delete', [self::$hostid]);
			self::assertArrayHasKey('hostids', $response['result']);
			self::$hostid = null;
		}

		if (self::$templateid !== null) {
			$response = CAPIHelper::call('template.delete', [self::$templateid]);
			self::assertArrayHasKey('templateids', $response['result']);
			self::$templateid = null;
		}

		if (self::$proxyid !== null) {
			$response = CAPIHelper::call('proxy.delete', [self::$proxyid]);
			self::assertArrayHasKey('proxyids', $response['result']);
			self::$proxyid = null;
		}

		CAPIHelper::call('settings.update', ['auditlog_enabled' => 1, 'auditlog_mode' => 1]);
	}

	/**
	 * Send the device list to the LLD rule and wait until one dependent item and one trigger is
	 * discovered per device.
	 */
	public function testLLDTelemetrySyncAtScale_LLDDiscovery() {
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$data = [];
		foreach (self::deviceNames() as $device) {
			$data[] = [self::LLD_MACRO => $device];
		}
		$this->sendSenderValue(self::HOSTNAME, self::LLD_RULE_KEY, ['data' => $data]);

		$this->callUntilCountIsPresent('item.get', [
			'hostids' => [self::$hostid],
			'search' => ['key_' => self::ITEM_PROTO_KEY.'[']
		], self::LLD_DISCOVERY_COUNT, self::LLD_ITERATIONS, self::WAIT_ITERATION_DELAY);

		$this->callUntilCountIsPresent('trigger.get', [
			'hostids' => [self::$hostid],
			'search' => ['description' => 'APM device ']
		], self::LLD_DISCOVERY_COUNT, self::LLD_ITERATIONS, self::WAIT_ITERATION_DELAY);

		// Make discovered dependent items and triggers known to preprocessing and history sync.
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
	}

	/**
	 * Make sure the proxy APM collector accepts data and writes it to ClickHouse before relying on it.
	 * The proxy receives its APM configuration from the server, so the first sends may be rejected or dropped.
	 *
	 * @depends testLLDTelemetrySyncAtScale_LLDDiscovery
	 */
	public function testLLDTelemetrySyncAtScale_IngestPreflight() {
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
				if (!$this->sendDeviceStatus([self::PREFLIGHT_DEVICE], 0, $error)) {
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
	 * Send status=1 for the problem devices and wait until it is stored in ClickHouse.
	 *
	 * @depends testLLDTelemetrySyncAtScale_IngestPreflight
	 */
	public function testLLDTelemetrySyncAtScale_TriggerFiringSend() {
		$this->sendStatusAndWaitStored(self::problemDeviceNames(), 1);
	}

	/**
	 * Verify that exactly the triggers of the problem devices fire.
	 *
	 * @depends testLLDTelemetrySyncAtScale_TriggerFiringSend
	 */
	public function testLLDTelemetrySyncAtScale_TriggerFiringVerify() {
		$this->waitForOpenProblemCount(self::PROBLEM_DEVICE_COUNT);
		$this->assertProblemDevices(self::problemDeviceNames());
	}

	/**
	 * Send status=0 for the same devices and wait until it is stored in ClickHouse.
	 *
	 * @depends testLLDTelemetrySyncAtScale_TriggerFiringVerify
	 */
	public function testLLDTelemetrySyncAtScale_TriggerRecoverySend() {
		$this->sendStatusAndWaitStored(self::problemDeviceNames(), 0);
	}

	/**
	 * Verify that all triggers recover.
	 *
	 * @depends testLLDTelemetrySyncAtScale_TriggerRecoverySend
	 */
	public function testLLDTelemetrySyncAtScale_TriggerRecoveryVerify() {
		$this->waitForOpenProblemCount(0);
	}

	private static function deviceNames(): array {
		$devices = [];
		for ($i = 1; $i <= self::LLD_DISCOVERY_COUNT; $i++) {
			$devices[] = self::SENSOR_BASE.$i;
		}

		return $devices;
	}

	/**
	 * Evenly spread devices starting with sensor1, so prefix collisions (sensor1 vs sensor10..19) are covered.
	 */
	private static function problemDeviceNames(): array {
		$step = intdiv(self::LLD_DISCOVERY_COUNT, self::PROBLEM_DEVICE_COUNT);
		$devices = [];
		for ($i = 0; $i < self::PROBLEM_DEVICE_COUNT; $i++) {
			$devices[] = self::SENSOR_BASE.(1 + $i * $step);
		}

		return $devices;
	}

	private function sendStatusAndWaitStored(array $devices, int $status): void {
		$baseline = $this->getStoredStatusCount($status);

		$error = null;
		$this->assertTrue($this->sendDeviceStatus($devices, $status, $error),
				'Failed to send status='.$status.' data points: '.$error);

		$this->assertStoredStatusIncreasedBy($status, $baseline, count($devices));
	}

	private function waitForOpenProblemCount(int $expected): void {
		$this->callUntilCountIsPresent('problem.get', [
			'hostids' => [self::$hostid],
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS
		], $expected, self::TRIGGER_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Assert that exactly the expected devices have a trigger in PROBLEM state, so a matching count is not made of
	 * the wrong devices (e.g. sensor1 rows matching sensor10..19).
	 */
	private function assertProblemDevices(array $expected): void {
		$response = $this->call('trigger.get', [
			'hostids' => [self::$hostid],
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

		$this->assertEquals($expected, $actual, 'Devices with triggers in PROBLEM state do not match: missing ['.
				implode(',', array_diff($expected, $actual)).'], unexpected ['.
				implode(',', array_diff($actual, $expected)).'].');
	}

	/**
	 * Send one device.status gauge data point per device, one OTLP resource per device, batched.
	 */
	private function sendDeviceStatus(array $devices, int $status, ?string &$error = null): bool {
		$time_ns = (string) (int) (microtime(true) * 1e9);

		foreach (array_chunk($devices, self::OTLP_BATCH_SIZE) as $batch) {
			$resource_metrics = [];
			foreach ($batch as $device) {
				$resource_metrics[] = [
					'resource' => [
						'attributes' => [
							['key' => 'service.name', 'value' => ['stringValue' => self::SERVICE_NAME]],
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

		if (!is_array($buckets) || count($buckets) !== 1 || !isset($buckets[0]['columns']['cnt'])
				|| !isset($buckets[0]['columns']['sum'])) {
			return null;
		}

		$count = (int) $buckets[0]['columns']['cnt'];
		$ones = (int) $buckets[0]['columns']['sum'];

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

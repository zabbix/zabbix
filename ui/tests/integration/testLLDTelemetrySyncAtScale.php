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
 * HOST_COUNT hosts stand for services of LLD_DISCOVERY_COUNT devices each, every device reports the service it belongs
 * to in the "service.name" resource attribute (ServiceName column, first in the sorting key of the OTel tables) and
 * each host has a single Telemetry query item for the devices of its service.
 * Devices are discovered from their own data by a second Telemetry query on each host, the LLD rule, which
 * returns one row per device that reported within DISCOVERY_WINDOW. Per-device status is fanned out to
 * LLD-discovered dependent items (one per device), and trigger prototypes on them must fire and recover for
 * exactly the devices whose status changed.
 *
 * The query item, LLD rule and prototypes come from a template linked to every host, the service filter of the queries
 * is the {$SERVICE} user macro, which every host sets to its own name.
 *
 * The query item returns all rows of a poll as a single JSON array value, which is processed by all dependent items
 * of its host: LLD_DISCOVERY_COUNT JSONPath lookups in a value of LLD_DISCOVERY_COUNT rows per host and round when
 * every device reports, as in the trigger rounds, where problem devices report status=1 among status=0 of all other
 * devices (master value parsed once and indexed).
 *
 * Requires: proxy built with APM support, ClickHouse with otel_* tables, grpcurl in PATH.
 *
 * For discovery, send and stored count only run as '/testLLDTelemetrySyncAtScale_(IngestPreflight|LLDDiscovery|StatusZeroSend|StatusZeroVpsWritten|StatusZeroVerify)$/'
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
	const TEMPLATE_NAME = 'test_lld_telemetry_sync_at_scale_template';
	const SERVICE_MACRO = '{$SERVICE}';
	const SCOPE_NAME = 'telemetry-sync-at-scale';
	const METRIC_NAME = 'device.status';
	const TQ_ITEM_KEY = 'apm.device.status.query';
	const LLD_RULE_KEY = 'apm.device.discovery';
	const LLD_MACRO = '{#SENSOR}';
	const ITEM_PROTO_KEY = 'apm.device.status';
	const SENSOR_BASE = 'sensor';
	const PREFLIGHT_SERVICE = 'apm_preflight';
	const PREFLIGHT_DEVICE = 'apm_preflight';

	const HOST_COUNT = 4;
	/* every device is a row of the discovery query, which must not exceed ZBX_TQ_MAX_RESULT_ROWS */
	const LLD_DISCOVERY_COUNT = 32768;
	const PROBLEM_DEVICE_COUNT = 8192;
	const OTLP_BATCH_SIZE = 1000;
	const LLD_ITERATIONS = 60;
	const TRIGGER_ITERATIONS = 60;
	const CLICKHOUSE_WAIT_SECONDS = 60;
	const PREFLIGHT_RESEND_SECONDS = 10;
	const STORED_STATUS_WINDOW = '1d';

	/* granularity == delay keeps query windows contiguous, so each sent data point yields exactly one row */
	const TQ_GRANULARITY = '5s';
	const TQ_DELAY = '5s';
	const TQ_TIME_SHIFT = '5s';
	const TQ_LOOKBACK_LIMIT = '1h';

	/* granularity smaller than delay, every poll returns several buckets of a device that reported more than once */
	const SMALL_TQ_GRANULARITY = '1s';
	/* every status is sent twice a second apart, a poll may hold both buckets of a device, keep the rows of a host */
	/* within ZBX_TQ_MAX_RESULT_ROWS */
	const SMALL_GRANULARITY_PROBLEM_DEVICE_COUNT = 4096;
	const SMALL_GRANULARITY_SEND_COUNT = 2;

	/* devices that reported within the window are discovered, one bucket per window gives one row per device */
	const DISCOVERY_DELAY = '1m';
	const DISCOVERY_WINDOW = '1h';

	/* host name => hostid, host name is also the service of its devices */
	private static $hostids = [];
	private static $templateid;
	/* itemid of the Telemetry query item on the template */
	private static $template_tq_itemid;
	/* LLD rules inherited by the hosts */
	private static $lld_ruleids = [];
	private static $proxyid;
	/* hosts monitored before the test, disabled for its duration */
	private static $disabled_hostids = [];
	/* baselines taken before status=0 of all devices is sent */
	private static $vps_last;
	private static $stored_last;

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
				'CacheSize' => '512M',
				'TrendCacheSize' => '128M',
				'HistoryCacheSize' => '128M',
				'HistoryIndexCacheSize' => '128M',
				'ValueCacheSize' => '128M',
				'LogSlowQueries' => '60000',
				'StartDBSyncers' => '4',
				'StartLLDProcessors' => self::HOST_COUNT,
				'StartTelemetryQueryPollers' => '1',
				'CacheUpdateFrequency' => '3600'
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
				'StartAPMCollector' => 1
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

		$this->deleteLeftovers();
		$this->disablePreviousHosts();
		$this->createCollector();

		return true;
	}

	/**
	 * Delete hosts, template and proxy left by a run that did not get to clearData (interrupted, or a failed
	 * cleanup step), so they can be created again. Variants of the suite share the names.
	 */
	private function deleteLeftovers(): void {
		$response = $this->call('host.get', [
			'filter' => ['host' => self::hostNames()],
			'output' => ['hostid']
		]);
		if ($response['result']) {
			$response = $this->call('host.delete', array_column($response['result'], 'hostid'));
			$this->assertArrayHasKey('hostids', $response['result']);
		}

		$response = $this->call('template.get', [
			'filter' => ['host' => self::TEMPLATE_NAME],
			'output' => ['templateid']
		]);
		if ($response['result']) {
			$response = $this->call('template.delete', array_column($response['result'], 'templateid'));
			$this->assertArrayHasKey('templateids', $response['result']);
		}

		$response = $this->call('proxy.get', [
			'filter' => ['name' => self::PROXY_NAME],
			'output' => ['proxyid']
		]);
		if ($response['result']) {
			$response = $this->call('proxy.delete', array_column($response['result'], 'proxyid'));
			$this->assertArrayHasKey('proxyids', $response['result']);
		}
	}

	/**
	 * Disable hosts left monitored by previous tests, so only the hosts of this test load the server.
	 */
	private function disablePreviousHosts(): void {
		$response = $this->call('host.get', [
			'filter' => ['status' => HOST_STATUS_MONITORED],
			'output' => ['hostid']
		]);
		self::$disabled_hostids = array_column($response['result'], 'hostid');

		if (self::$disabled_hostids) {
			$response = $this->call('host.update', array_map(
				static fn($hostid) => ['hostid' => $hostid, 'status' => HOST_STATUS_NOT_MONITORED],
				self::$disabled_hostids
			));
			$this->assertArrayHasKey('hostids', $response['result']);
		}
	}

	/**
	 * Create the APM (OTLP) collector that stores sent data points in ClickHouse.
	 */
	protected function createCollector(): void {
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
	}

	/**
	 * Make the collector receive its configuration before data is sent to it.
	 */
	protected function syncCollectorConfiguration(): void {
		// Proxy monitors no hosts, it only needs the APM configuration from the server.
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY);
	}

	/**
	 * Create the template and a host for every service linked to it, then collect the LLD rules inherited by the hosts.
	 */
	private function createServiceHosts(): void {
		$this->createServiceTemplate();

		$response = $this->call('hostgroup.get', [
			'filter' => ['name' => ['Zabbix servers']],
			'output' => ['groupid']
		]);
		$this->assertNotEmpty($response['result'], 'Host group "Zabbix servers" not found.');
		$groupid = $response['result'][0]['groupid'];

		foreach (self::hostNames() as $host) {
			$response = $this->call('host.create', [
				'host' => $host,
				'interfaces' => [],
				'groups' => [['groupid' => $groupid]],
				'templates' => [['templateid' => self::$templateid]],
				'macros' => [['macro' => self::SERVICE_MACRO, 'value' => $host]],
				'status' => HOST_STATUS_MONITORED
			]);
			$this->assertArrayHasKey('hostids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['hostids']);
			self::$hostids[$host] = $response['result']['hostids'][0];
		}

		$response = $this->call('discoveryrule.get', [
			'hostids' => array_values(self::$hostids),
			'filter' => ['key_' => self::LLD_RULE_KEY],
			'output' => ['itemid']
		]);
		$this->assertCount(self::HOST_COUNT, $response['result']);
		self::$lld_ruleids = array_column($response['result'], 'itemid');
	}

	/**
	 * Create template with the Telemetry query item for devices of the service, Telemetry query LLD rule discovering
	 * the devices of the service and per-device dependent items and triggers. The service comes from the host macro.
	 */
	private function createServiceTemplate(): void {
		$response = $this->call('templategroup.get', [
			'filter' => ['name' => ['Templates']],
			'output' => ['groupid']
		]);
		$this->assertNotEmpty($response['result'], 'Template group "Templates" not found.');

		$response = $this->call('template.create', [
			'host' => self::TEMPLATE_NAME,
			'groups' => [['groupid' => $response['result'][0]['groupid']]]
		]);
		$this->assertArrayHasKey('templateids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['templateids']);
		self::$templateid = $response['result']['templateids'][0];

		// Single query for all devices of the service: one row per device that reported in the window.
		$response = $this->call('item.create', [
			'hostid' => self::$templateid,
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
				'filter' => self::serviceStatusFilter(self::SERVICE_MACRO)
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$template_tq_itemid = $response['result']['itemids'][0];

		// Granularity and lookback limit equal to the window give a single bucket, polling more often than the
		// window slides it, so every poll returns each device that reported within the last window once. Every
		// poll reads the whole window again and late data is picked up by the next one, so there is no time shift.
		$response = $this->call('discoveryrule.create', [
			'hostid' => self::$templateid,
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
				'filter' => self::serviceStatusFilter(self::SERVICE_MACRO)
			],
			'lld_macro_paths' => [
				['lld_macro' => self::LLD_MACRO, 'path' => '$["ResourceAttributes.host.name"]']
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		$lld_ruleid = $response['result']['itemids'][0];

		$response = $this->call('itemprototype.create', [
			'hostid' => self::$templateid,
			'ruleid' => $lld_ruleid,
			'name' => 'APM device status ['.self::LLD_MACRO.']',
			'key_' => self::ITEM_PROTO_KEY.'['.self::LLD_MACRO.']',
			'type' => ITEM_TYPE_DEPENDENT,
			'master_itemid' => self::$template_tq_itemid
		] + static::deviceItemPrototype());
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);

		$response = $this->call('triggerprototype.create', [
			'description' => 'APM device '.self::LLD_MACRO.' status problem',
			'expression' => static::deviceTriggerExpression(),
			'priority' => TRIGGER_SEVERITY_AVERAGE
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);
	}

	/**
	 * Value type and preprocessing of the per-device dependent item prototype. Each dependent item keeps only rows
	 * of its own device and extracts the aggregated status. JSONPath as the first step lets all dependent items share
	 * one parsed and indexed master value, rows of other devices do not match and are discarded.
	 */
	protected static function deviceItemPrototype(): array {
		return [
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'preprocessing' => [
				[
					'type' => ZBX_PREPROC_JSONPATH,
					'params' => '$[?(@["ResourceAttributes.host.name"] == "'.self::LLD_MACRO.'")].value.first()',
					'error_handler' => ZBX_PREPROC_FAIL_DISCARD_VALUE,
					'error_handler_params' => ''
				]
			]
		];
	}

	/**
	 * Expression of the per-device trigger prototype, problem when the device reports status=1.
	 */
	protected static function deviceTriggerExpression(): string {
		return 'last(/'.self::TEMPLATE_NAME.'/'.self::ITEM_PROTO_KEY.'['.self::LLD_MACRO.'])=1';
	}

	/**
	 * Query filter for status data points of devices of the service.
	 */
	private static function serviceStatusFilter(string $service): array {
		return [
			'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
			'formula' => '',
			'conditions' => [
				[
					'column' => 'ServiceName',
					'attribute_key' => '',
					'operator' => CONDITION_OPERATOR_EQUAL,
					'value' => $service
				],
				[
					'column' => 'MetricName',
					'attribute_key' => '',
					'operator' => CONDITION_OPERATOR_EQUAL,
					'value' => self::METRIC_NAME
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

		if (self::$templateid !== null) {
			$response = CAPIHelper::call('template.delete', [self::$templateid]);
			self::assertArrayHasKey('templateids', $response['result']);
			self::$templateid = null;
			self::$template_tq_itemid = null;
		}

		if (self::$proxyid !== null) {
			$response = CAPIHelper::call('proxy.delete', [self::$proxyid]);
			self::assertArrayHasKey('proxyids', $response['result']);
			self::$proxyid = null;
		}

		if (self::$disabled_hostids) {
			$response = CAPIHelper::call('host.update', array_map(
				static fn($hostid) => ['hostid' => $hostid, 'status' => HOST_STATUS_MONITORED],
				self::$disabled_hostids
			));
			self::assertArrayHasKey('hostids', $response['result']);
			self::$disabled_hostids = [];
		}

		CAPIHelper::call('settings.update', ['auditlog_enabled' => 1, 'auditlog_mode' => 1]);
	}

	/**
	 * Make sure the proxy APM collector accepts data and writes it to ClickHouse before relying on it.
	 * The proxy receives its APM configuration from the server, so the first sends may be rejected or dropped.
	 */
	public function testLLDTelemetrySyncAtScale_IngestPreflight() {
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->syncCollectorConfiguration();

		// Resend periodically, data sent before the proxy has APM configuration may be dropped.
		$baseline = $this->getStoredStatusCounts()[0];
		$deadline = time() + 30;
		$next_send = time();
		$count = $baseline;
		$send_error = null;
		while ($count === $baseline && time() < $deadline) {
			if (time() >= $next_send) {
				$error = null;
				if (!$this->sendDeviceStatus([self::PREFLIGHT_SERVICE => [self::PREFLIGHT_DEVICE => 0]], $error)) {
					$send_error = $error;
				}
				$next_send = time() + self::PREFLIGHT_RESEND_SECONDS;
			}

			sleep(self::WAIT_ITERATION_DELAY);

			$count = $this->getStoredStatusCounts()[0];
		}

		$this->assertGreaterThan($baseline, $count,
				'Preflight data point did not reach ClickHouse through the proxy APM collector.'.
				"\nLast send error: ".($send_error === null ? 'none' : $send_error));
	}

	/**
	 * Create the service hosts, then every device reports status=0 once and the LLD rule of every host discovers the
	 * devices of its service from the stored data. Wait until one dependent item and one trigger is discovered per
	 * device.
	 *
	 * @depends testLLDTelemetrySyncAtScale_IngestPreflight
	 */
	public function testLLDTelemetrySyncAtScale_LLDDiscovery() {
		$this->sendStatusAndWaitStored(self::allDeviceStatuses([], 0));

		$this->createServiceHosts();
		// Data is already stored when the first poll of the LLD rule runs, so devices are discovered by it.
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		// Do not wait for the first scheduled poll of the LLD rules, it may be up to DISCOVERY_DELAY away.
		$tasks = [];
		foreach (self::$lld_ruleids as $lld_ruleid) {
			$tasks[] = ['type' => ZBX_TM_TASK_CHECK_NOW, 'request' => ['itemid' => $lld_ruleid]];
		}
		$response = $this->call('task.create', $tasks);
		$this->assertArrayHasKey('taskids', $response['result']);

		$this->callUntilCountIsPresent('item.get', [
			'hostids' => array_values(self::$hostids),
			'search' => ['key_' => self::ITEM_PROTO_KEY.'[']
		], self::LLD_DISCOVERY_COUNT * self::HOST_COUNT, self::LLD_ITERATIONS,
				self::WAIT_ITERATION_DELAY);

		$this->callUntilCountIsPresent('trigger.get', [
			'hostids' => array_values(self::$hostids),
			'search' => ['description' => 'APM device ']
		], self::LLD_DISCOVERY_COUNT * self::HOST_COUNT, self::LLD_ITERATIONS,
				self::WAIT_ITERATION_DELAY);

		// Make discovered dependent items and triggers known to preprocessing and history sync.
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
	}

	/**
	 * Every device of every service reports status=0 again, now that the dependent items are discovered, so they get
	 * a value without firing triggers.
	 *
	 * @depends testLLDTelemetrySyncAtScale_LLDDiscovery
	 */
	public function testLLDTelemetrySyncAtScale_StatusZeroSend() {
		self::$vps_last = $this->getVpsWritten();
		self::$stored_last = $this->getStoredStatusCounts();

		$error = null;
		$this->assertTrue($this->sendDeviceStatus(self::allDeviceStatuses([], 0), $error),
				'Failed to send status=0 data points: '.$error);
	}

	/**
	 * Verify that VPS written counter increased by a value of every dependent item, one per sent data point.
	 *
	 * @depends testLLDTelemetrySyncAtScale_StatusZeroSend
	 */
	public function testLLDTelemetrySyncAtScale_StatusZeroVpsWritten() {
		$this->assertVpsWrittenIncreasedBy(self::$vps_last, self::LLD_DISCOVERY_COUNT * self::HOST_COUNT);
	}

	/**
	 * Verify that every sent data point can be read back from ClickHouse by a Telemetry query.
	 *
	 * @depends testLLDTelemetrySyncAtScale_StatusZeroSend
	 */
	public function testLLDTelemetrySyncAtScale_StatusZeroVerify() {
		$this->assertStoredStatusIncreasedBy(self::$stored_last, [self::LLD_DISCOVERY_COUNT * self::HOST_COUNT, 0]);
	}

	/**
	 * Every device of every service reports, status=1 for the problem devices and status=0 for the rest, as devices
	 * that report periodically would, and wait until it is stored in ClickHouse. Every poll of the query item then
	 * holds rows of healthy and problem devices of the service.
	 *
	 * @depends testLLDTelemetrySyncAtScale_LLDDiscovery
	 */
	public function testLLDTelemetrySyncAtScale_TriggerFiringSend() {
		$this->sendStatusAndWaitStored(self::allDeviceStatuses(self::problemDevices(), 1));
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
	 * Every device of every service reports status=0 and wait until it is stored in ClickHouse.
	 *
	 * @depends testLLDTelemetrySyncAtScale_TriggerFiringVerify
	 */
	public function testLLDTelemetrySyncAtScale_TriggerRecoverySend() {
		$this->sendStatusAndWaitStored(self::allDeviceStatuses([], 0));
	}

	/**
	 * Verify that all triggers recover.
	 *
	 * @depends testLLDTelemetrySyncAtScale_TriggerRecoverySend
	 */
	public function testLLDTelemetrySyncAtScale_TriggerRecoveryVerify() {
		$this->waitForOpenProblemCount(0);
	}

	/**
	 * Switch the Telemetry query items to granularity smaller than their delay, so a poll returns several buckets
	 * of the same device.
	 *
	 * @depends testLLDTelemetrySyncAtScale_TriggerRecoveryVerify
	 */
	public function testLLDTelemetrySyncAtScale_SmallGranularityUpdate() {
		// The change is propagated to the items inherited by the hosts.
		$response = $this->call('item.update', [
			'itemid' => self::$template_tq_itemid,
			'granularity' => self::SMALL_TQ_GRANULARITY
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertCount(1, $response['result']['itemids']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
	}

	/**
	 * Send status=1 for the problem devices of all services, then again a second later, so every device has a bucket
	 * per send, and wait until it is stored in ClickHouse.
	 *
	 * @depends testLLDTelemetrySyncAtScale_SmallGranularityUpdate
	 */
	public function testLLDTelemetrySyncAtScale_SmallGranularityTriggerFiringSend() {
		$this->sendStatusAndWaitStored(
				self::deviceStatuses(self::problemDevices(self::SMALL_GRANULARITY_PROBLEM_DEVICE_COUNT), 1),
				self::SMALL_GRANULARITY_SEND_COUNT);
	}

	/**
	 * Verify that exactly the triggers of the problem devices fire on every host.
	 *
	 * @depends testLLDTelemetrySyncAtScale_SmallGranularityTriggerFiringSend
	 */
	public function testLLDTelemetrySyncAtScale_SmallGranularityTriggerFiringVerify() {
		$this->waitForOpenProblemCount(self::SMALL_GRANULARITY_PROBLEM_DEVICE_COUNT * self::HOST_COUNT);

		foreach (self::problemDevices(self::SMALL_GRANULARITY_PROBLEM_DEVICE_COUNT) as $host => $devices) {
			$this->assertProblemDevices($host, $devices);
		}
	}

	/**
	 * Send status=0 for the same devices, then again a second later, and wait until it is stored in ClickHouse.
	 *
	 * @depends testLLDTelemetrySyncAtScale_SmallGranularityTriggerFiringVerify
	 */
	public function testLLDTelemetrySyncAtScale_SmallGranularityTriggerRecoverySend() {
		$this->sendStatusAndWaitStored(
				self::deviceStatuses(self::problemDevices(self::SMALL_GRANULARITY_PROBLEM_DEVICE_COUNT), 0),
				self::SMALL_GRANULARITY_SEND_COUNT);
	}

	/**
	 * Verify that all triggers recover.
	 *
	 * @depends testLLDTelemetrySyncAtScale_SmallGranularityTriggerRecoverySend
	 */
	public function testLLDTelemetrySyncAtScale_SmallGranularityTriggerRecoveryVerify() {
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
	 * Device names are the same on every service, devices are told apart by the service.name resource attribute.
	 */
	private static function deviceNames(): array {
		$devices = [];
		for ($i = 1; $i <= self::LLD_DISCOVERY_COUNT; $i++) {
			$devices[] = self::SENSOR_BASE.$i;
		}

		return $devices;
	}

	/**
	 * Problem devices of every service.
	 *
	 * @return array  host name => device names
	 */
	private static function problemDevices(int $count = self::PROBLEM_DEVICE_COUNT): array {
		$devices = [];
		foreach (self::hostNames() as $index => $host) {
			$devices[$host] = self::problemDeviceNames($index, $count);
		}

		return $devices;
	}

	/**
	 * Evenly spread devices, on the first service starting with sensor1, so prefix collisions (sensor1 vs
	 * sensor10..19) are covered. Every next service starts one device later, so services do not have the same problem
	 * devices and rows of another service leaking into the query of a host fire a trigger that must not fire.
	 */
	private static function problemDeviceNames(int $service_index, int $count): array {
		$step = intdiv(self::LLD_DISCOVERY_COUNT, $count);
		$devices = [];
		for ($i = 0; $i < $count; $i++) {
			$devices[] = self::SENSOR_BASE.(1 + ($i * $step + $service_index) % self::LLD_DISCOVERY_COUNT);
		}

		return $devices;
	}

	/**
	 * Status of the given devices only.
	 *
	 * @param array $devices  service => device names
	 *
	 * @return array  service => [device name => status]
	 */
	private static function deviceStatuses(array $devices, int $status): array {
		$statuses = [];
		foreach ($devices as $service => $names) {
			$statuses[$service] = array_fill_keys($names, $status);
		}

		return $statuses;
	}

	/**
	 * Status of every device of every service, $status for the given devices and 0 for the rest.
	 *
	 * @param array $devices  service => device names
	 *
	 * @return array  service => [device name => status]
	 */
	private static function allDeviceStatuses(array $devices, int $status): array {
		$statuses = [];
		foreach (self::hostNames() as $host) {
			$statuses[$host] = array_fill_keys(self::deviceNames(), 0);

			if (array_key_exists($host, $devices)) {
				$statuses[$host] = array_fill_keys($devices[$host], $status) + $statuses[$host];
			}
		}

		return $statuses;
	}

	/**
	 * Send the statuses $send_count times, a second apart, so with granularity of a second every send of a device
	 * is a bucket of its own.
	 *
	 * @param array $statuses  service => [device name => status]
	 */
	private function sendStatusAndWaitStored(array $statuses, int $send_count = 1): void {
		$baseline = $this->getStoredStatusCounts();

		$increase = [0, 0];
		foreach ($statuses as $device_statuses) {
			foreach ($device_statuses as $status) {
				$increase[$status] += $send_count;
			}
		}

		for ($i = 1; $i <= $send_count; $i++) {
			if ($i > 1) {
				sleep(1);
			}

			$error = null;
			$this->assertTrue($this->sendDeviceStatus($statuses, $error),
					'Failed to send status data points (send '.$i.' of '.$send_count.'): '.$error);
		}

		$this->assertStoredStatusIncreasedBy($baseline, $increase);
	}

	private function waitForOpenProblemCount(int $expected): void {
		$this->callUntilCountIsPresent('problem.get', [
			'hostids' => array_values(self::$hostids),
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS
		], $expected, self::TRIGGER_ITERATIONS, self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Assert that exactly the expected devices of the host have a trigger in PROBLEM state, so a matching count is
	 * not made of the wrong devices (e.g. sensor1 rows matching sensor10..19, or rows of another service).
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
	 * devices of several services, as when sent by a collector.
	 *
	 * @param array $statuses  service => [device name => status]
	 */
	private function sendDeviceStatus(array $statuses, ?string &$error = null): bool {
		$service_devices = [];
		foreach ($statuses as $service => $device_statuses) {
			foreach ($device_statuses as $device => $status) {
				$service_devices[] = [$service, (string) $device, $status];
			}
		}

		foreach (array_chunk($service_devices, self::OTLP_BATCH_SIZE) as $batch) {
			// Stamp every request when it is sent like a device would, with a single stamp for all requests the
			// last ones could arrive after the query window of the stamp has been read.
			$time_ns = (string) (int) (microtime(true) * 1e9);

			$resource_metrics = [];
			foreach ($batch as [$service, $device, $status]) {
				$resource_metrics[] = [
					'resource' => [
						'attributes' => [
							['key' => 'service.name', 'value' => ['stringValue' => (string) $service]],
							['key' => 'host.name', 'value' => ['stringValue' => $device]]
						]
					],
					'scopeMetrics' => [
						[
							'scope' => ['name' => self::SCOPE_NAME],
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

	/**
	 * @return array  [0 => status=0 count, 1 => status=1 count]
	 */
	private function getStoredStatusCounts(): array {
		$result = $this->callTestItemUntilCallback(self::storedStatusItem(),
			fn($result) => self::parseStoredStatusCounts($result) !== null,
			['single' => false, 'state' => 0], self::CLICKHOUSE_WAIT_SECONDS * 1000
		);

		return self::parseStoredStatusCounts($result);
	}

	/**
	 * @param array $baseline  [0 => status=0 count, 1 => status=1 count]
	 * @param array $increase  [0 => status=0 count, 1 => status=1 count]
	 */
	private function assertStoredStatusIncreasedBy(array $baseline, array $increase): void {
		$expected = [$baseline[0] + $increase[0], $baseline[1] + $increase[1]];

		$result = $this->callTestItemUntilCallback(self::storedStatusItem(),
			function ($result) use ($expected) {
				$counts = self::parseStoredStatusCounts($result);

				return $counts !== null && $counts[0] >= $expected[0] && $counts[1] >= $expected[1];
			},
			['single' => false, 'state' => 0], self::CLICKHOUSE_WAIT_SECONDS * 1000
		);

		$this->assertEquals($expected, self::parseStoredStatusCounts($result),
				'Unexpected number of status=0 and status=1 data points stored in ClickHouse: '.
				$result['item']['result']);
	}

	/**
	 * Values written to history by the server, the dependent items of the hosts write one per device and poll; the
	 * Telemetry query items keep no history and are not counted.
	 */
	private function getVpsWritten(): int {
		$result = $this->callTestItemUntilCallback(
			['value_type' => ITEM_VALUE_TYPE_UINT64, 'type' => ITEM_TYPE_INTERNAL, 'key' => 'zabbix[vps,written]'],
			fn($result) => $result !== false && isset($result['item']['result'])
					&& is_numeric($result['item']['result'])
		);

		return (int) $result['item']['result'];
	}

	private function assertVpsWrittenIncreasedBy(int $baseline, int $min_increase): void {
		$expected = $baseline + $min_increase;

		$result = $this->callTestItemUntilCallback(
			['value_type' => ITEM_VALUE_TYPE_UINT64, 'type' => ITEM_TYPE_INTERNAL, 'key' => 'zabbix[vps,written]'],
			fn($result) => $result !== false && isset($result['item']['result'])
					&& is_numeric($result['item']['result']) && (int) $result['item']['result'] >= $expected
		);

		$this->assertGreaterThanOrEqual($expected, (int) $result['item']['result'],
				'VPS written did not reach expected value.');
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

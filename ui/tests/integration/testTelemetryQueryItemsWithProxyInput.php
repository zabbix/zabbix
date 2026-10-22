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

require_once dirname(__FILE__).'/testTelemetryQueryItems.php';

/**
 * Variant of testTelemetryQueryItems where the data is sent to the proxy APM collector, which stores it in ClickHouse
 * for the Telemetry query items of the server to read back.
 *
 * Requires: proxy built with APM support, ClickHouse with otel_* tables, grpcurl in PATH.
 *
 * @required-components server, proxy
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_telemetry_query_items
 * @suite-components-reuse true
 * @backup history
 * @onAfter clearData
 */
class testTelemetryQueryItemsWithProxyInput extends testTelemetryQueryItems {
	const PROXY_NAME = 'test_telemetry_query_items_proxy';

	private static $proxyid;

	public function serverConfigurationProvider(): array {
		// TelemetryProvider of server and proxy comes from $TELEMETRY_PROVIDERS of the frontend configuration.
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 0,
				'LogFile' => self::getLogPath(self::COMPONENT_SERVER)
			],
			self::COMPONENT_PROXY => [
				'ProxyMode' => PROXY_OPERATING_MODE_ACTIVE,
				'Hostname' => self::PROXY_NAME,
				'Server' => '127.0.0.1:'.PHPUNIT_PORT_PREFIX.self::SERVER_PORT_SUFFIX,
				'ListenPort' => PHPUNIT_PORT_PREFIX.self::PROXY_PORT_SUFFIX,
				'ConfigFrequency' => 1,
				'DataSenderFrequency' => 1,
				'DebugLevel' => 4,
				'LogFileSize' => 0,
				'LogFile' => self::getLogPath(self::COMPONENT_PROXY),
				'StartAPMCollectors' => 1
			]
		];
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		parent::prepareData();

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
	 * Class level callbacks are executed without an instance, so the cleanup must be static.
	 */
	public static function clearData(): void {
		if (self::$proxyid !== null) {
			CDataHelper::call('proxy.delete', [self::$proxyid]);
			self::$proxyid = null;
		}
	}

	public function executeSubcases(array $subcases): void {
		// The proxy APM collector starts listening only after the proxy receives its APM configuration from the server.
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY);

		parent::executeSubcases($subcases);
	}
}

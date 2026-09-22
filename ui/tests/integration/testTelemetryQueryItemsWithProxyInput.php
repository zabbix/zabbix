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
 * @required-components server, proxy
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_telemetry_query_items
 * @backup history
 * @onBefore initEnv
 * @onAfter clearEnv
 */
class testTelemetryQueryItemsWithProxyInput extends testTelemetryQueryItems {
	const PROXY_NAME = 'proxy';

	private static int $proxyid;

	public function serverConfigurationProvider(): array {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 0,
				'LogFile' => self::getLogPath(self::COMPONENT_SERVER),
				'TelemetryProvider' =>	'clickhouse;url="' . self::CLICKHOUSE_URL . '",' .
										'username="' . self::CLICKHOUSE_USERNAME . '",' .
										'password="' . self::CLICKHOUSE_PASSWORD . '",' .
										'db="' . self::CLICKHOUSE_DB . '"'
			],
			self::COMPONENT_PROXY => [
				'DebugLevel' => 5,
				'LogFileSize' => 0,
				'LogFile' => self::getLogPath(self::COMPONENT_PROXY),
				'ProxyMode' => PROXY_OPERATING_MODE_ACTIVE,
				'ListenPort' => PHPUNIT_PORT_PREFIX . self::PROXY_PORT_SUFFIX,
				'TelemetryProvider' =>	'clickhouse;url="' . self::CLICKHOUSE_URL . '",' .
										'username="' . self::CLICKHOUSE_USERNAME . '",' .
										'password="' . self::CLICKHOUSE_PASSWORD . '",' .
										'db="' . self::CLICKHOUSE_DB . '"',
				'StartAPMCollectors' => 1,
				'Hostname' => self::PROXY_NAME
			]
		];
	}

	public function initEnv(): void {
		parent::initEnv();

		$result = CDataHelper::call('proxy.create', [
			'name' => self::PROXY_NAME,
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
			'hosts' => [],
			'apm' => [
				'data_collection_status' => 1
			]
		]);

		$this->assertArrayHasKey('proxyids', $result);
		$this->assertArrayHasKey(0, $result['proxyids']);
		self::$proxyid = $result['proxyids'][0];
	}

	public function clearEnv(): void {
		CDataHelper::call('proxy.delete', [self::$proxyid]);
		parent::clearEnv();
	}

	public function executeSubcases(array $subcases): void {
		CDataHelper::call('task.create', [
			'type' => 2,
			'request' => [
				'proxyids' => [self::$proxyid]
			]
		]);

		parent::executeSubcases($subcases);
	}
}

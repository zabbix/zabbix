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

require_once dirname(__FILE__).'/testLLDTelemetrySyncAtScale.php';

/**
 * Variant of testLLDTelemetrySyncAtScale where the data points are sent to an external OpenTelemetry Collector with
 * the ClickHouse exporter instead of the proxy APM collector, no proxy is started.
 *
 * Requires: OpenTelemetry Collector listening on OTLP/gRPC at COLLECTOR_ADDRESS that exports to the ClickHouse
 * database of $TELEMETRY_PROVIDERS, grpcurl in PATH. The proxy APM collector listens on the same address, so the
 * external collector must be stopped when running the parent test.
 *
 * can be tested with parent as (testLLDTelemetrySyncAtScale|testLLDTelemetrySyncAtScaleClickHouseCollector)
 *
 * @required-components server
 * @suite-components-reuse true
 * @configurationDataProvider configurationProvider
 * @onAfter clearData
 */
class testLLDTelemetrySyncAtScaleClickHouseCollector extends testLLDTelemetrySyncAtScale {

	/**
	 * Component configuration provider — server settings of the parent class without the proxy.
	 */
	public function configurationProvider() {
		$config = parent::configurationProvider();
		unset($config[self::COMPONENT_PROXY]);

		return $config;
	}

	/**
	 * The external collector is not configured by Zabbix.
	 */
	protected function createCollector(): void {
	}

	/**
	 * The external collector is not configured by Zabbix.
	 */
	protected function syncCollectorConfiguration(): void {
	}
}

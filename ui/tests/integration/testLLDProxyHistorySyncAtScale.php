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

require_once dirname(__FILE__).'/testLLDHistorySyncAtScale.php';

/**
 * Variant of testLLDHistorySyncAtScale that runs a real active proxy daemon and
 * routes all item values through it instead of spoofing the proxy name when
 * sending directly to the server. The nodata-based trigger scenarios are
 * skipped — values traverse the proxy queue and arrive with proxy-introduced
 * latency, so nodata(...,30s) windows are not deterministic in this setup.
 *
 * @required-components server, proxy
 * @suite-components-reuse true
 * @configurationDataProvider configurationProvider
 * @onAfter clearData
 */
class testLLDProxyHistorySyncAtScale extends testLLDHistorySyncAtScale {

	const POST_TRENDS_SKIP_REASON = 'proxy variant: scenarios after TrendsVerify are not yet exercised';

	/**
	 * Component configuration provider — adds the proxy daemon configuration on
	 * top of the server settings inherited from the parent class.
	 */
	public function configurationProvider() {
		$config = parent::configurationProvider();

		$config[self::COMPONENT_PROXY] = [
			'ProxyMode' => PROXY_OPERATING_MODE_ACTIVE,
			'Hostname' => self::PROXY_NAME,
			'Server' => '127.0.0.1:'.PHPUNIT_PORT_PREFIX.self::SERVER_PORT_SUFFIX,
			'ListenPort' => PHPUNIT_PORT_PREFIX.self::PROXY_PORT_SUFFIX,
			'ConfigFrequency' => 1,
			'DataSenderFrequency' => 1,
			'LogFileSize' => 8,
			'DebugLevel' => 3,
			'CacheSize' => '128M',
			'HistoryCacheSize' => '32M',
			'HistoryIndexCacheSize' => '32M',
			'StartDBSyncers' => '4',
			'ProxyBufferMode' => 'hybrid',
			'ProxyMemoryBufferSize' => '64M'
		];

		return $config;
	}

	/**
	 * Override the routing hook so values are delivered to the proxy daemon
	 * (which forwards them to the server) instead of being sent to the server
	 * with the proxy name spoofed.
	 */
	protected function dispatchValues(array $values): void {
		$this->sendAgentDataValues($values, self::HOSTNAME, self::COMPONENT_PROXY, 0);
	}

	/**
	 * Reload configuration cache on both server and proxy and wait until the
	 * proxy has received the updated configuration from the server. Without
	 * this, tests that rely on freshly-discovered items/triggers would race
	 * against the proxy's periodic ConfigFrequency pull.
	 */
	protected function reloadConfigurationCacheAndWaitForLogLine($component = null, $delayOverride = 0,
			$iterations = null, $delay = null) {
		parent::reloadConfigurationCacheAndWaitForLogLine($component, $delayOverride, $iterations, $delay);
		parent::reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY, $delayOverride, $iterations, $delay);
	}

	public function testLLDHistorySyncAtScale_ValueOmittedDrainsDelay() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}

	/*public function testLLDHistorySyncAtScale_LogLastlogsizeAdvances() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}

	/*public function testLLDHistorySyncAtScale_SingleLogBurstPreTriggersSend() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_SingleLogBurstPreTriggersVpsWritten() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_PreTriggerZeroSend() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_PreTriggerZeroVpsWritten() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}
	
	public function testLLDHistorySyncAtScale_TriggerDiscovery() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerFiring() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerRecovery() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerFiringWarmupAfterRestart() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerRecoveryWarmupAfterRestart() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerUnknown() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerRecoverUnknown() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}
*/
	public function testLLDHistorySyncAtScale_TriggerNoDataDiscovery() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerNoDataFiring() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_ProxyLastaccess() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerNoDataNotSupported() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerNoDataValueOmitted() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerNoDataValueOmittedLastlogsize() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerNoDataRecoveryAfterRestart() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerNoDataSuppressedAfterConnectionLoss() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerNoDataOKAfterConnectionLossSingleLogBurst() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerNoDataFiringAfterRestart() {
		$this->markTestSkipped(self::POST_TRENDS_SKIP_REASON);
	}
}

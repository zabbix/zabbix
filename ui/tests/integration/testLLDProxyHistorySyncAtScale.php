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
 * skipped.
 *
 * can be tested with parent as (testLLDHistorySyncAtScale|testLLDProxyHistorySyncAtScale)
 *
 * @required-components server, proxy
 * @suite-components-reuse true
 * @configurationDataProvider configurationProvider
 * @onAfter clearData
 */
class testLLDProxyHistorySyncAtScale extends testLLDHistorySyncAtScale {

	const NODATA_SKIP_REASON = 'proxy variant: nodata-based trigger scenarios are not exercised';
	const DEPENDENT_CHAIN_KEY = 'dependent.chain';
	const DEPENDENT_CHAIN_DEPTH = 256;

	private static $chain_itemids = [];
	private static $chain_tm;
	private static $chain_vps_last;

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
			'LogSlowQueries' => '60000',
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

	/**
	 * Create a master item followed by a chain of DEPENDENT_CHAIN_DEPTH dependent items,
	 * each depending on the previous one.
	 */
	public function testLLDProxyHistorySyncAtScale_DependentChainCreate() {
		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => 'Dependent chain 0',
			'key_' => self::DEPENDENT_CHAIN_KEY.'[0]',
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'delay' => '1h'
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$chain_itemids = [(int) $response['result']['itemids'][0]];

		for ($i = 1; $i <= self::DEPENDENT_CHAIN_DEPTH; $i++) {
			$response = $this->call('item.create', [
				'hostid' => self::$hostid,
				'name' => 'Dependent chain '.$i,
				'key_' => self::DEPENDENT_CHAIN_KEY.'['.$i.']',
				'type' => ITEM_TYPE_DEPENDENT,
				'master_itemid' => end(self::$chain_itemids),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			]);
			$this->assertArrayHasKey('itemids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['itemids']);
			self::$chain_itemids[] = (int) $response['result']['itemids'][0];
		}

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER, null, self::LLD_ITERATIONS,
			self::WAIT_ITERATION_DELAY);
	}

	/**
	 * Send a single value to the master item of the dependent item chain.
	 *
	 * @depends testLLDProxyHistorySyncAtScale_DependentChainCreate
	 */
	public function testLLDProxyHistorySyncAtScale_DependentChainSend() {
		self::$chain_tm = time();
		self::$chain_vps_last = $this->getVpsWritten();

		$this->dispatchValues([
			[
				'itemid' => self::$chain_itemids[0],
				'value' => '1',
				'clock' => self::$chain_tm,
				'ns' => 0
			]
		]);
	}

	/**
	 * Verify that the VPS written counter increased by the master value plus one value per dependent item.
	 *
	 * @depends testLLDProxyHistorySyncAtScale_DependentChainSend
	 */
	public function testLLDProxyHistorySyncAtScale_DependentChainVpsWritten() {
		$this->assertVpsWrittenIncreasedBy(self::$chain_vps_last, self::DEPENDENT_CHAIN_DEPTH + 1);
	}

	/**
	 * Verify that every item of the dependent item chain received the value.
	 *
	 * @depends testLLDProxyHistorySyncAtScale_DependentChainSend
	 */
	public function testLLDProxyHistorySyncAtScale_DependentChainVerify() {
		$response = $this->callUntilDataIsPresent('history.get', [
			'history' => ITEM_VALUE_TYPE_UINT64,
			'itemids' => self::$chain_itemids,
			'time_from' => self::$chain_tm,
			'time_till' => self::$chain_tm,
			'limit' => self::DEPENDENT_CHAIN_DEPTH + 1
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, function ($r) {
			return count($r['result']) === self::DEPENDENT_CHAIN_DEPTH + 1;
		});

		$this->assertCount(self::DEPENDENT_CHAIN_DEPTH + 1, $response['result']);
		foreach ($response['result'] as $record) {
			$this->assertEquals('1', $record['value'], 'Unexpected value for itemid '.$record['itemid'].'.');
		}
	}

	public function testLLDHistorySyncAtScale_ValueOmittedDrainsDelay() {
		$this->markTestSkipped(self::NODATA_SKIP_REASON);
	}

	/*
	 * The following tests are inherited from testLLDHistorySyncAtScale and
	 * intentionally not overridden — they are expected to run in the proxy
	 * variant:
	 *
	 *   - testLLDHistorySyncAtScale_LogLastlogsizeAdvances
	 *   - testLLDHistorySyncAtScale_SingleLogBurstPreTriggersSend
	 *   - testLLDHistorySyncAtScale_SingleLogBurstPreTriggersVpsWritten
	 *   - testLLDHistorySyncAtScale_PreTriggerZeroSend
	 *   - testLLDHistorySyncAtScale_PreTriggerZeroVpsWritten
	 *   - testLLDHistorySyncAtScale_TriggerDiscovery
	 *   - testLLDHistorySyncAtScale_TriggerFiring
	 *   - testLLDHistorySyncAtScale_TriggerRecovery
	 *   - testLLDHistorySyncAtScale_TriggerFiringWarmupAfterRestart
	 *   - testLLDHistorySyncAtScale_TriggerRecoveryWarmupAfterRestart
	 *   - testLLDHistorySyncAtScale_TriggerUnknown
	 *   - testLLDHistorySyncAtScale_TriggerRecoverUnknown
	 */
	public function testLLDHistorySyncAtScale_TriggerNoDataDiscovery() {
		$this->markTestSkipped(self::NODATA_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerNoDataFiring() {
		$this->markTestSkipped(self::NODATA_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_ProxyLastaccess() {
		$this->markTestSkipped(self::NODATA_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerNoDataNotSupported() {
		$this->markTestSkipped(self::NODATA_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerNoDataValueOmitted() {
		$this->markTestSkipped(self::NODATA_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerNoDataValueOmittedLastlogsize() {
		$this->markTestSkipped(self::NODATA_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerNoDataRecoveryAfterRestart() {
		$this->markTestSkipped(self::NODATA_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerNoDataSuppressedAfterConnectionLoss() {
		$this->markTestSkipped(self::NODATA_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerNoDataOKAfterConnectionLossSingleLogBurst() {
		$this->markTestSkipped(self::NODATA_SKIP_REASON);
	}

	public function testLLDHistorySyncAtScale_TriggerNoDataFiringAfterRestart() {
		$this->markTestSkipped(self::NODATA_SKIP_REASON);
	}
}

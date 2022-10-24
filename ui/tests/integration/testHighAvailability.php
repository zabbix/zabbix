<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';

/**
 * Test suite for High availability
 *
 * @backup ha_node
 */
class testHighAvailability extends CIntegrationTest {

	const STANDALONE_NAME = '<standalone server>';
	const NODE1_NAME = 'node1';
	const NODE2_NAME = 'node2';

	/**
	 * @required-components server, server_ha1
	 * @inheritdoc
	 */
	public function prepareData() {
		$socketDir = $this->getConfigurationValue(self::COMPONENT_SERVER_HANODE1, 'SocketDir');

		if (file_exists($socketDir) === false) {
			mkdir($socketDir);
		}

		return true;
	}

	/**
	 * Component configuration provider for standalone mode
	 * Used to test if server quits gracefully when cache size is too low
	 *
	 * @return array
	 */
	public function serverConfigurationProvider_cacheSize() {
		return [
			self::COMPONENT_SERVER => [
				'HANodeName' => self::NODE1_NAME,
				'CacheSize' => '128K',
				'ListenPort' => PHPUNIT_PORT_PREFIX.self::SERVER_HANODE1_PORT_SUFFIX
			]
		];
	}

	/**
	 * Component configuration provider for 2 nodes (Active + standby)
	 *
	 * @return array
	 */
	public function serverConfigurationProvider_ha() {
		return [
			self::COMPONENT_SERVER => [
				'HANodeName' => self::NODE1_NAME,
				'ListenPort' => PHPUNIT_PORT_PREFIX.self::SERVER_HANODE1_PORT_SUFFIX
			],
			self::COMPONENT_SERVER_HANODE1 => [
				'HANodeName' => self::NODE2_NAME,
				'NodeAddress' => 'localhost:'.self::getConfigurationValue(self::COMPONENT_SERVER_HANODE1, 'ListenPort')
			]
		];
	}

	/**
	 * Launching Zabbix server in stand-alone mode
	 *
	 * @required-components server_ha1
	 */
	public function testHighAvailability_checkStandaloneModeStartup() {
		$this->assertFalse($this->isLogLinePresent(self::COMPONENT_SERVER_HANODE1, '"'.self::NODE1_NAME.'" node started in "active" mode'));

		return true;
	}

	/**
	 * Launching High availability cluster with 2 nodes (Active + standby)
	 *
	 * @required-components server, server_ha1
	 * @configurationDataProvider serverConfigurationProvider_ha
	 */
	public function testHighAvailability_checkHaStartup() {
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, '"'.self::NODE1_NAME.'" node started in "active" mode', true, 3, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER_HANODE1, '"'.self::NODE2_NAME.'" node started in "standby" mode', true, 3, 3);

		return true;
	}

	/**
	 * Stopping the active node (Standby node should take over)
	 * Starting stopped node (when another node is active and when there are no active nodes)
	 * Stopping a stand-by node
	 *
	 * @required-components server, server_ha1
	 * @configurationDataProvider serverConfigurationProvider_ha
	 */
	public function testHighAvailability_checkModeSwitching() {
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER_HANODE1, '"'.self::NODE2_NAME.'" node switched to "active" mode', true, 5, 15);

		$this->startComponent(self::COMPONENT_SERVER, "HA manager started");
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, '"'.self::NODE1_NAME.'" node started in "standby" mode', true, 5, 15);

		$this->stopComponent(self::COMPONENT_SERVER_HANODE1);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, '"'.self::NODE1_NAME.'" node switched to "active" mode', true, 5, 15);

		return true;
	}

	/**
	 * Stopping the active node (Standby node should take over)
	 * Starting stopped node (when another node is active and when there are no active nodes)
	 * Stopping a stand-by node
	 *
	 * @required-components server, server_ha1
	 * @configurationDataProvider serverConfigurationProvider_ha
	 */
	public function testHighAvailability_checkModeSwitching2() {
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_SERVER, "HA manager started");

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER_HANODE1, '"node2" node switched to "active" mode', true, 20, 3);
		$this->stopComponent(self::COMPONENT_SERVER_HANODE1);
		$this->stopComponent(self::COMPONENT_SERVER);

		return true;
	}

	private function verifyNodesStatus($expected_nodes) {
		$this->executeRuntimeControlCommand(self::COMPONENT_SERVER, 'ha_status');

		foreach ($expected_nodes as $node) {
			$re = $node["nodename"].".*".$node["expected_status"];
			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $re, true, 20, 3, true);
		}

		return true;
	}

	/**
	 * Retrieving ha cluster info via ha_status runtime command
	 *
	 * @required-components server, server_ha1
	 * @configurationDataProvider serverConfigurationProvider_ha
	 */
	public function testHighAvailability_haStatus() {
		$expected_nodes = [
			[
				"nodename" => self::NODE1_NAME,
				"expected_status" => "active"
			],
			[
				"nodename" => self::NODE2_NAME,
				"expected_status" => "standby"
			]
		];

		$this->assertTrue($this->verifyNodesStatus($expected_nodes));
	}

	/**
	 * Remove node
	 *
	 * @required-components server, server_ha1
	 * @configurationDataProvider serverConfigurationProvider_ha
	 */
	public function testHighAvailability_removeNode() {
		$this->stopComponent(self::COMPONENT_SERVER_HANODE1);
		$this->executeRuntimeControlCommand(self::COMPONENT_SERVER, 'ha_remove_node=node2');
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "removed node", true, 3, 5);

		$response = $this->call('hanode.get', [
			'output' => 'extend',
			'filter' => [
				'name' => self::STANDALONE_NAME
			]
		]);
		$this->assertEmpty($response['result']);
	}

	/**
	 * Updating the failover delay via ha_set_failover_delay runtime command
	 *
	 * @required-components server, server_ha1
	 * @configurationDataProvider serverConfigurationProvider_ha
	 */
	public function testHighAvailability_failover() {
		$this->executeRuntimeControlCommand(self::COMPONENT_SERVER, 'ha_set_failover_delay=10s');
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'HA failover delay set to 10s');

		$this->stopComponent(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER_HANODE1, '"'.self::NODE2_NAME.'" node switched to "active" mode');

		$this->startComponent(self::COMPONENT_SERVER, 'HA manager started in standby mode');
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER_HANODE1, 'started [trigger housekeeper');
		sleep(1);

		self::killComponent(self::COMPONENT_SERVER_HANODE1);

		$response = $this->callUntilDataIsPresent('hanode.get', [
			'output' => 'extend',
			'filter' => [
				'name' => self::NODE2_NAME,
				'status' => ZBX_NODE_STATUS_UNAVAILABLE
			]
		], 15, 2);
		$this->assertCount(1, $response['result']);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, '"'.self::NODE1_NAME.'" node switched to "active" mode');
	}

	/**
	 * Check graceful stop of server if cacheSize is too low
	 *
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider_ha
	 */
	public function testHighAvailability_cacheSize() {
		$this->stopComponent(self::COMPONENT_SERVER);

		$newConfig = [
			'server' => array_merge(self::$case_configuration[self::COMPONENT_SERVER], $this->serverConfigurationProvider_cacheSize()[self::COMPONENT_SERVER])
		];

		self::prepareComponentConfiguration(self::COMPONENT_SERVER, $newConfig);
		$this->startComponent(self::COMPONENT_SERVER, 'Zabbix Server stopped', true);
		$this->assertTrue(true); // Ignore warning for risky test, checks are performed in nested funcs and exceptions can be thrown
	}

	/**
	 * Check for static socket on mode switching
	 *
	 * @required-components server, server_ha1
	 * @configurationDataProvider serverConfigurationProvider_ha
	 */
	public function testHighAvailability_checkRtc() {
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER_HANODE1, '"'.self::NODE2_NAME.'" node switched to "active" mode', true, 5, 15);

		$this->startComponent(self::COMPONENT_SERVER, "HA manager started");
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, '"'.self::NODE1_NAME.'" node started in "standby" mode', true, 5, 15);

		$this->stopComponent(self::COMPONENT_SERVER_HANODE1);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, '"'.self::NODE1_NAME.'" node switched to "active" mode', true, 5, 15);

		$expected_nodes = [
			[
				"nodename" => self::NODE1_NAME,
				"expected_status" => "active"
			],
			[
				"nodename" => self::NODE2_NAME,
				"expected_status" => "stopped"
			]
		];

		$this->assertTrue($this->verifyNodesStatus($expected_nodes));

		$commands = [
			'config_cache_reload' => 'forced reloading of the configuration cache',
			'secrets_reload' => 'forced reloading of the secrets',
			'service_cache_reload' => 'forced reloading of the service manager cache',
			'housekeeper_execute' => 'forced execution of the housekeeper',
			'diaginfo=locks' => '== locks diagnostic information =='
		];

		foreach ($commands as $cmd => $exp) {
			$this->executeRuntimeControlCommand(self::COMPONENT_SERVER, $cmd);
			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $exp, true, 20, 3);
		}

		$this->stopComponent(self::COMPONENT_SERVER);
		$this->stopComponent(self::COMPONENT_SERVER_HANODE1);
	}
}

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
 * Test suite for internal java item verification.
 *
 * @required-components server
 * @hosts java_host
 * @onAfter clearData
 *
 */
class testInternalJavaItem extends CIntegrationTest {

	// Java gateway uses fixed pid file, log file and port, so parallel runs on the same host take turns.
	const JAVA_LOCK_FILE = '/tmp/zabbix_java_integration.lock';
	const JAVA_LOCK_TIMEOUT = 180;

	private static $hostid;
	private static $java_lock;
	private static $items = [
		'zbx_java_ping_test' => [
			'name' => 'zbx_java_ping_test',
			'key' => 'zabbix[java,,ping]',
			'type' => ITEM_TYPE_INTERNAL,
			'value_type' => ITEM_VALUE_TYPE_UINT64
		],
		'zbx_java_ver_test' => [
			'name' => 'zbx_java_ver_test',
			'key' => 'zabbix[java,,version]',
			'type' => ITEM_TYPE_INTERNAL,
			'value_type' => ITEM_VALUE_TYPE_STR
		],
		'zbx_java_invalid_test' => [
			'name' => 'zbx_java_invalid_test',
			'key' => 'zabbix[java,,param]',
			'type' => ITEM_TYPE_INTERNAL,
			'value_type' => ITEM_VALUE_TYPE_STR
		]
	];

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		self::lockJavaGateway();

		// Create host "java_host"
		$response = $this->call('host.create', [
			[
				'host' => 'java_host',
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		// Create item
		foreach (self::$items as $key => $item) {
			$new_item = [
				'name' => $key,
				'key_' => $item['key'],
				'type' => $item['type'],
				'hostid' => self::$hostid,
				'interfaceid' => 0,
				'value_type' => $item['value_type'],
				'delay' => '1s',
				'status' => ITEM_STATUS_DISABLED
			];

			$items[] = $new_item;
		}

		$response = $this->call('item.create', $items);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(count($items), count($response['result']['itemids']));
		$itemids = $response['result']['itemids'];
		$id = 0;

		foreach (self::$items as &$item) {
			$item['itemid'] = $itemids[$id++];
		}

		return true;
	}

	/**
	 * Wait until no other test run uses Java gateway.
	 *
	 * @throws Exception    on failed lock file open or wait timeout
	 */
	private static function lockJavaGateway(): void {
		self::$java_lock = @fopen(self::JAVA_LOCK_FILE, 'c') ?: @fopen(self::JAVA_LOCK_FILE, 'r');

		if (self::$java_lock === false) {
			throw new Exception('Failed to open lock file "'.self::JAVA_LOCK_FILE.'".');
		}

		$start = time();

		while (!flock(self::$java_lock, LOCK_EX | LOCK_NB)) {
			if (time() - $start >= self::JAVA_LOCK_TIMEOUT) {
				throw new Exception('Java gateway is used by another test run, waited '.self::JAVA_LOCK_TIMEOUT.
						' seconds.');
			}

			sleep(1);
		}
	}

	/**
	 * Routine to prepare item.
	 */
	public function prepareItem($name) {
		// Disable all items
		foreach (self::$items as $item) {
			if ($item['name'] == $name) {
				$items[] = [
					'itemid' => $item['itemid'],
					'status' => ITEM_STATUS_ACTIVE
				];
			} else {
				$items[] = [
					'itemid' => $item['itemid'],
					'status' => ITEM_STATUS_DISABLED
				];
			}
		}

		$response = $this->call('item.update', $items);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(count($items), count($response['result']['itemids']));
		$this->reloadConfigurationCache();

		// Clear log
		$this->clearLog(self::COMPONENT_SERVER);
	}

	/**
	 * Component configuration provider.
	 *
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'JavaGateway' => "127.0.0.1",
				'JavaGatewayPort' => 10052
			]
		];
	}

	/**
	 * Component configuration provider.
	 *
	 * @return array
	 */
	public function serverNotSetConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20
			]
		];
	}

	/**
	 * Component configuration provider wrong configuration.
	 *
	 * @return array
	 */
	public function serverWrongConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'JavaGateway' => "127.0.0.1",
				'JavaGatewayPort' => PHPUNIT_PORT_PREFIX.'99'
			]
		];
	}

	/**
	 * Delete data objects created for this test suite
	 *
	 */
	public static function clearData(): void {
		if (self::$java_lock) {
			flock(self::$java_lock, LOCK_UN);
			fclose(self::$java_lock);
			self::$java_lock = null;
		}

		// Triggers, items, and user macros should be cascade deleted.
		if (self::$hostid !== null) {
			CDataHelper::call('host.delete', [self::$hostid]);
		}
	}

	/**
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testInternalJavaItem_notStarted() {

		$this->prepareItem('zbx_java_ping_test');

		// Wait for log line about getting item value
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'In get_value() key:\'zabbix[java,,ping]\'', true, 10 , 1);

		// Wait for log line about JMX endpoint not being used
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'jmx_endpoint:\'N/A\'', true, 10 , 1);

		// Wait for log line with JSON back value
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'getting Java values failed: cannot connect to [[127.0.0.1]:10052]', true, 10, 1);

		// Wait for second log line about getting item value to make sure no freeze occurred
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'In get_value() key:\'zabbix[java,,ping]\'', true, 10 , 1);

		return true;
	}

	/**
	 * @required-components java
	 * @configurationDataProvider serverNotSetConfigurationProvider
	 */
	public function testInternalJavaItem_notConfigured() {

		$this->prepareItem('zbx_java_ver_test');

		// Wait for log line about getting item value
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'In get_value() key:\'zabbix[java,,version]\'', true, 10 , 1);

		// Wait for log line about JMX endpoint not being used
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'jmx_endpoint:\'N/A\'', true, 10 , 1);

		// Wait for log line with JSON back value
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'getting Java values failed: JavaGateway configuration parameter not set or empty', true, 10, 1);

		// Wait for second log line about getting item value to make sure no freeze occurred
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'In get_value() key:\'zabbix[java,,version]\'', true, 10 , 1);

		return true;
	}


	/**
	 * @required-components java
	 * @configurationDataProvider serverWrongConfigurationProvider
	 */
	public function testInternalJavaItem_wrongPort() {

		$this->prepareItem('zbx_java_ver_test');

		// Wait for log line about getting item value
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'In get_value() key:\'zabbix[java,,version]\'', true, 10 , 1);

		// Wait for log line about JMX endpoint not being used
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'jmx_endpoint:\'N/A\'', true, 10 , 1);

		// Wait for log line with JSON back value
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'getting Java values failed: cannot connect to [[127.0.0.1]:'.PHPUNIT_PORT_PREFIX.'99', true, 10, 1);

		// Wait for second log line about getting item value to make sure no freeze occurred
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'In get_value() key:\'zabbix[java,,version]\'', true, 10 , 1);

		return true;
	}

	/**
	 * @required-components java
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testInternalJavaItem_ping() {

		$this->prepareItem('zbx_java_ping_test');

		// Wait for log line about getting item value
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'In get_value() key:\'zabbix[java,,ping]\'', true, 10 , 1);

		// Wait for log line about JMX endpoint not being used
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'jmx_endpoint:\'N/A\'', true, 10 , 1);

		// Wait for log line with JSON back value
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, '"data":[{"value":"1"}]', true, 10, 1);

		// Wait for second log line about getting item value to make sure no freeze occurred
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'In get_value() key:\'zabbix[java,,ping]\'', true, 10 , 1);

		return true;
	}

	/**
	 *
	 * @required-components java
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testInternalJavaItem_ver() {

		$this->prepareItem('zbx_java_ver_test');

		// Wait for log line about getting item value
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'In get_value() key:\'zabbix[java,,version]\'', true, 10 , 1);

		// Wait for log line about JMX endpoint not being used
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'jmx_endpoint:\'N/A\'', true, 10 , 1);

		// Wait for log line with JSON back value
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'"data":[{"value":"'.ZABBIX_VERSION.'"}]', true, 10, 1);

		// Wait for second log line about getting item value to make sure no freeze occurred
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'In get_value() key:\'zabbix[java,,version]\'', true, 10 , 1);

		return true;
	}

	/**
	 *
	 * @required-components java
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testInternalJavaItem_invalid() {

		$this->prepareItem('zbx_java_invalid_test');

		// Wait for log line about getting item value
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'In get_value() key:\'zabbix[java,,param]\'', true, 10 , 1);

		// Wait for log line about JMX endpoint not being used
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'jmx_endpoint:\'N/A\'', true, 10 , 1);

		// Wait for log line with JSON back value
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'{"error":"third parameter \'param\' is not supported"}', true, 10, 1);

		// Wait for second log line about getting item value to make sure no freeze occurred
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'In get_value() key:\'zabbix[java,,param]\'', true, 10 , 1);

		return true;
	}
}

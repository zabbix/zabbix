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
 * @backup hosts
 *
 */
class testInternalJavaItem extends CIntegrationTest {

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
		$hostid = $response['result']['hostids'][0];

		// Create item
		foreach (self::$items as $key => $item) {
			$new_item = [
				'name' => $key,
				'key_' => $item['key'],
				'type' => $item['type'],
				'hostid' => $hostid,
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
	 * Routine to prepare item.
	 */
	public function prepareItem($name) {
		// Disable all items
		foreach (self::$items as $item) {
			if ($item['name'] == $name) {
				$items[] = [
					'itemid' => $item['itemid'],
					'name' => $name,
					'status' => ITEM_STATUS_ACTIVE
				];
			} else {
				$items[] = [
					'itemid' => $item['itemid'],
					'name' => $name,
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
				'JavaGatewayPort' => 10050
			]
		];
	}

	/**
	 *
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

		return true;
	}

	/**
	 *
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
				'getting Java values failed: cannot connect to [[127.0.0.1]:10050]', true, 10, 1);

		return true;
	}

	/**
	 *
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

		return true;
	}
}

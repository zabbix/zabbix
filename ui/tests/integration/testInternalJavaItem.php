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
 * @required-components server, java
 * @backup history
 */
class testInternalJavaItem extends CIntegrationTest {

	private static $items = [
		'zbx_java_test' => [
			'name' => 'zbx_java_test',
			'key' => 'zabbix[java,,ping]',
			'type' => ITEM_TYPE_INTERNAL
		]
	];

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "test_host"
		$response = $this->call('host.create', [
			[
				'host' => 'test_host',
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		$hostid = $response['result']['hostids'][0];

		// Create item
		$new_item = [
			'name' => self::$items['zbx_java_test']['name'],
			'key_' => self::$items['zbx_java_test']['key'],
			'type' => self::$items['zbx_java_test']['type'],
			'hostid' => $hostid,
			'interfaceid' => 0,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'delay' => '1s',
			'status' => ITEM_STATUS_ACTIVE
		];

		$items[] = $new_item;

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

	public function setItemStatus($item, $status) {
		$items_upd[] = [
			'itemid' => $item['itemid'],
			'status' => $status
		];

		$response = $this->call('item.update', $items_upd);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(count($items_upd), count($response['result']['itemids']));
		$this->reloadConfigurationCache();
	}

	/**
	 * Component configuration provider for agent related tests.
	 *
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'JavaGateway' => "127.0.0.1",
				'JavaGatewayPort' => 10053
			]
		];
	}

	/**
	 *
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testInternalJavaItem() {
		// Wait for log line about getting item value
		$this->assertFalse($this->isLogLinePresent(self::COMPONENT_SERVER, 'Failed to get timestamp of the item key line'));

		// Wait for log line about JMX endpoint not being used
		$this->assertFalse($this->isLogLinePresent(self::COMPONENT_SERVER, 'jmx_endpoint:\'N/A\''));

		// Wait for log line with JSON back value
		$this->assertFalse($this->isLogLinePresent(self::COMPONENT_SERVER, 'JSON back [{"data":{"value":"1"}},
				"response":"success"}]'));
	}
}

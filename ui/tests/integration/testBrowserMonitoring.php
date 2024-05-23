<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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
 * Test scenario to check browser monitoring.
 *
 * @backup hosts,items,history_text
 *
 */
class testBrowserMonitoring extends CIntegrationTest {
	private static $itemid;

	/**
	 * 1 host, 1 browser item.
	 * Item's parameter 'url' should contain URL to the frontend.
	 * It should be accessible by the WebDriver.
	 */
	public function prepareData() {
		$response = $this->call('host.create', [
			'host' => 'WebMonHost',
			'name' => 'WebMonHost',
			'groups' => ['groupid' => 4]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		$hostid = $response['result']['hostids'][0];

		$script = file_get_contents('integration/data/browser.js');

		$response = $this->call('item.create', [
			'hostid' => $hostid,
			'type' => ITEM_TYPE_BROWSER,
			'name' => 'WebMonItem',
			'key_' => 'webmonitem',
			'delay' => '21s',
			'timeout' => '19s',
			'value_type' => ITEM_VALUE_TYPE_TEXT,
			'params' => $script,
			'parameters' => [
				[
					'name' => 'url',
					'value' => 'http://172.17.0.1/zabbix'
				]
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['itemids']);
		self::$itemid = $response['result']['itemids'][0];
		return true;
	}

	/**
	 * Component configuration provider.
	 *
	 * @return array
	 */
	public function configurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'LogFileSize' => 0,
				'WebDriverURL' => 'localhost:4444'
			]
		];
	}


	/**
	 * Wait for successful execution of data/browser.js.
	 *
	 * @configurationDataProvider configurationProvider
	 * @required-components server
	 */
	public function testBrowserMonitoring_executeBrowserJs() {
		$response = $this->call('task.create', [
			'type' => ZBX_TM_TASK_CHECK_NOW,
			'request' => [
				'itemid' => self::$itemid
			]
		]);

		$response = $this->callUntilDataIsPresent('history.get', [
			'history' => ITEM_VALUE_TYPE_TEXT,
			'output' => 'extend',
			'itemids' => [self::$itemid]
		], 120, 2);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('value', $response['result'][0]);

		$result = json_decode($response['result'][0]['value'], true);

		$this->assertArrayHasKey('performance_data', $result);
		$this->assertArrayHasKey('details', $result['performance_data']);
		$this->assertArrayHasKey('summary', $result['performance_data']);
		$this->assertArrayHasKey('navigation', $result['performance_data']['summary']);
		$this->assertArrayHasKey('resource', $result['performance_data']['summary']);
		$this->assertArrayHasKey('marks', $result['performance_data']);
		$this->assertArrayNotHasKey('error', $result['performance_data']);

		return true;
	}
}

<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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
 * @onAfter clearData
 *
 */
class testBrowserMonitoring extends CIntegrationTest {
	private static $itemid;
	private static $hostid;

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
		self::$hostid = $response['result']['hostids'][0];

		$script = file_get_contents('integration/data/browser.js');

		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
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
					'value' => PHPUNIT_URL
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
				'DebugLevel' => 4,
				'WebDriverURL' => PHPUNIT_DRIVER_ADDRESS
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

		$this->reloadConfigurationCache();

		$response = $this->callUntilDataIsPresent('history.get', [
			'history' => ITEM_VALUE_TYPE_TEXT,
			'output' => 'extend',
			'itemids' => [self::$itemid]
		], 30, 2);

		$this->assertArrayHasKey(0, $response['result'], json_encode($response['result']));
		$this->assertArrayHasKey('value', $response['result'][0], json_encode($response['result']));

		$result = json_decode($response['result'][0]['value'], true);

		$this->assertArrayHasKey('performance_data', $result, json_encode($result));
		$this->assertArrayHasKey('details', $result['performance_data'], json_encode($result));
		$this->assertArrayHasKey('summary', $result['performance_data'], json_encode($result));
		$this->assertArrayHasKey('navigation', $result['performance_data']['summary'], json_encode($result));
		$this->assertArrayHasKey('resource', $result['performance_data']['summary'], json_encode($result));
		$this->assertArrayHasKey('marks', $result['performance_data'], json_encode($result));
		$this->assertArrayNotHasKey('error', $result['performance_data'], json_encode($result));

		return true;
	}

	/**
	 * Delete all created data after test.
	 */
	public static function clearData(): void {
		CDataHelper::call('host.delete', [self::$hostid]);
	}
}

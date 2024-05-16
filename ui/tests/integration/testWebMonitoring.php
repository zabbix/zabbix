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
 * Test case to check if an item preprocessing "custom on fail" supports
 * macros in error handler parameter. Both "set value to" and "set error to"
 * are checked in this test case.
 *
 * @backup hosts,items,history_text
 *
 */
class testWebMonitoring extends CIntegrationTest {
	private static $hostid;
	private static $itemid;
	/**
	 * Create a host with 1 macro and 3 items to test preprocessing.
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
	 * Test unassigned group assignment
	 *
	 * @configurationDataProvider configurationProvider
	 * @required-components server
	 */
	public function testWebMonitoring_tc1() {
		$response = $this->callUntilDataIsPresent('history.get', [
			'history' => ITEM_VALUE_TYPE_TEXT,
			'output' => 'extend',
			'itemids' => [self::$itemid]
		], 60, 2);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('value', $response['result'][0]);

		$value = $response['result'][0]['value'];
		$j = json_decode($value);

		$this->assertArrayHasKey('performance_data', $j);
		$this->assertArrayHasKey('details', $j['performance_data']);
		$this->assertArrayHasKey('summary', $j['performance_data']);
		$this->assertArrayHasKey('navigation', $j['performance_data']['summary']);
		$this->assertArrayHasKey('resource', $j['performance_data']['summary']);
		$this->assertArrayHasKey('marks', $j['performance_data']);
		$this->assertArrayNotHasKey('error', $j['performance_data']);

		return true;
	}
}

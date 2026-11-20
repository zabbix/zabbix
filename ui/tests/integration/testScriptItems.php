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
 * Test suite for script items.
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_script_items
 * @onAfter clearData
 */
class testScriptItems extends CIntegrationTest {
	const HOST_NAME = 'test_hostconn';
	const MACRO_PASSWORD_VALUE = 'badger_pa$$"\word';
	const MACRO_PASSWORD_VALUE_ESCAPED = 'badger_pa$$\"\\\\word';
	const LONG_260_CHAR_LINE = '11111111111111111111111111111111111111111111111111111'.
				'111111111111111111111111111111111111111111111111111111'.
				'111111111111111111111111111111111111111111111111111111'.
				'111111111111111111111111111111111111111111111111111111'.
				'111111111111111111111111111111111111111111111';
	const LONG_2340_CHAR_LINE = self::LONG_260_CHAR_LINE.self::LONG_260_CHAR_LINE.self::LONG_260_CHAR_LINE.
				self::LONG_260_CHAR_LINE.self::LONG_260_CHAR_LINE.self::LONG_260_CHAR_LINE.
				self::LONG_260_CHAR_LINE.self::LONG_260_CHAR_LINE.self::LONG_260_CHAR_LINE;

	private static $hostid;
	private static $sid;

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		self::$sid = CAPIHelper::getSessionId();
		$response = $this->call('host.create', [
			[
				'host' => self::HOST_NAME,
				'groups' => [['groupid' => 4]]
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		return true;
	}

	/**
	 * Component configuration provider for server related tests.
	 *
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 5,
				'LogFileSize' => 0,
				'LogFile' => self::getLogPath(self::COMPONENT_SERVER),
				'PidFile' => PHPUNIT_COMPONENT_DIR.'zabbix_server.pid',
				'SocketDir' => PHPUNIT_COMPONENT_DIR,
				'ListenPort' => self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort', 10051)
			]
		];
	}

	/**
	 * Delete data objects created for this test suite
	 *
	 */
	public static function clearData(): void {
		// Triggers, items, and user macros should be cascade deleted.
		if (self::$hostid !== null) {
			CDataHelper::call('host.delete', [self::$hostid]);
		}
	}

	public function testScriptItems_checkData() {
		$response = $this->call('usermacro.create', [
			'hostid' => self::$hostid,
			'macro' => '{$BADGER_PASSWORD}',
			'value' => self::MACRO_PASSWORD_VALUE
		]);

		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('hostmacroids', $response['result']);
		$hostmacroid = $response['result']['hostmacroids'][0];

		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => 'script',
			'key_' => 'script',
			'type' => ITEM_TYPE_SCRIPT,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'timeout' => '3s',
			'delay' => '1s',
			'parameters' => ['name' => "mypassword", 'value' => '{$BADGER_PASSWORD}'],
			'params'=> "var obj = JSON.parse(value); Zabbix.log(5, '[ BADGER X ] Debug auth: '+ JSON.stringify(obj.mypassword)); return 0;"]);

		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		$itemid = $response['result']['itemids'][0];

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "[ BADGER X ] Debug auth: \"".self::MACRO_PASSWORD_VALUE_ESCAPED, true, 60, 1);
	}

	public function testScriptItems_testItem() {
		$item_test_data = [
			'item' => [
				'hostid' => self::$hostid,
				'type' => ITEM_TYPE_SCRIPT,
				'key' => 'test_script_item',
				'timeout' => '3s',
				"value_type" => ITEM_VALUE_TYPE_TEXT,
				"params" => "return value;",
				"parameters" => ['111111' => "2"],
			],

		];

		$result = $this->getClient(self::COMPONENT_SERVER)->testItem($item_test_data, self::$sid);

		$this->assertFalse($result === false);
		$this->assertArrayHasKey('item', $result);
		$this->assertArrayNotHasKey('error', $result['item']);
		$this->assertArrayHasKey('result', $result['item']);
		$this->assertEquals($result['item']['result'], '{"111111":"2"}');
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'zbx_es_execute() output:\'{"111111":"2"}\'');
	}

	public function testScriptItems_testItemWithLongValue() {
		$item_test_data = [
			'item' => [
				'hostid' => self::$hostid,
				'type' => ITEM_TYPE_SCRIPT,
				'key' => 'test_script_item',
				'timeout' => '3s',
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'params' => 'return value;',
				'parameters' => ['123' => self::LONG_2340_CHAR_LINE],
			],

		];

		$result = $this->getClient(self::COMPONENT_SERVER)->testItem($item_test_data, self::$sid);

		$this->assertFalse($result === false);
		$this->assertArrayHasKey('item', $result);
		$this->assertArrayNotHasKey('error', $result['item']);
		$this->assertArrayHasKey('result', $result['item']);
		$this->assertEquals($result['item']['result'], '{"123":"'.self::LONG_2340_CHAR_LINE.'"}');
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'zbx_es_execute() output:\'{"123":"'.self::LONG_2340_CHAR_LINE.'"}\'');
	}

	public function testScriptItems_testItemWithLongName() {
		$item_test_data = [
			'item' => [
				'hostid' => self::$hostid,
				'type' => ITEM_TYPE_SCRIPT,
				'key' => 'test_script_item',
				'timeout' => '3s',
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'params' => 'return value;',
				'parameters' => [self::LONG_260_CHAR_LINE => "2"],
			],

		];

		$result = $this->getClient(self::COMPONENT_SERVER)->testItem($item_test_data, self::$sid);

		$this->assertFalse($result === false);
		$this->assertArrayHasKey('item', $result);
		$this->assertArrayNotHasKey('error', $result['item']);
		$this->assertArrayHasKey('result', $result['item']);
		$this->assertEquals($result['item']['result'], '{"'.self::LONG_260_CHAR_LINE.'":"2"}');
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'zbx_es_execute() output:\'{"'.self::LONG_260_CHAR_LINE.'":"2"}\'');
	}
}

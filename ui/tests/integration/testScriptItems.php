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
	// Maximum item_parameter name and value lengths allowed by database schema and API.
	const PARAMETER_NAME_MAX_LENGTH = 255;
	const PARAMETER_VALUE_MAX_LENGTH = 2048;

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

	/**
	 * Returns string of exact length in characters, built by repeating given pattern.
	 *
	 * @param string $pattern
	 * @param int    $length
	 *
	 * @return string
	 */
	private static function repeatToLength(string $pattern, int $length): string {
		return mb_substr(str_repeat($pattern, (int) ceil($length / mb_strlen($pattern))), 0, $length);
	}

	/**
	 * Data provider for item test with script parameters.
	 *
	 * @return array
	 */
	public static function getTestItemParametersData(): array {
		$max_name = str_repeat('1', self::PARAMETER_NAME_MAX_LENGTH);
		$max_value = str_repeat('2', self::PARAMETER_VALUE_MAX_LENGTH);

		// Characters which must be escaped in JSON (quote, backslash, control characters) and solidus.
		$json_special = "\"\\/\n\r\t\x08\x0c\x01\x1f\x7f";

		// 2, 3 and 4 byte UTF-8 characters, 4 byte ones are sent as surrogate pairs by json_encode().
		$multibyte = "Ж€中😀";

		// Characters known to cause trouble: line/paragraph separators (JS line terminators), BOM,
		// right-to-left override, zero width joiner, combining mark and noncharacter.
		$unicode_special = "\u{2028}\u{2029}\u{FEFF}\u{202E}\u{200D}e\u{0301}\u{FFFF}";

		return [
			'short parameter' => [
				['111111' => '2']
			],
			'parameter name of maximum length' => [
				[$max_name => '2']
			],
			'parameter value of maximum length' => [
				['123' => $max_value]
			],
			'parameter name and value of maximum length' => [
				[$max_name => $max_value]
			],
			'parameters following parameter with name of maximum length' => [
				[$max_name => '1', 'second' => '2', 'third' => '3']
			],
			'empty value and whitespace' => [
				[' ' => '', ' name ' => ' value ']
			],
			'JSON special characters' => [
				['name'.$json_special => 'value'.$json_special]
			],
			'JSON injection attempt' => [
				['a":"b' => '"},{"x":"y', '\\u0041\\' => '\\"']
			],
			'macro-like strings are not expanded' => [
				['{$MACRO}' => '{HOST.HOST}', '{#LLD}' => '{{HOST.HOST}.regsub("(.*)", \1)}', '$1' => '{ITEM.VALUE}']
			],
			'Unicode special characters' => [
				[$unicode_special => $unicode_special]
			],
			'multibyte name and value of maximum length' => [
				[
					self::repeatToLength($multibyte, self::PARAMETER_NAME_MAX_LENGTH) =>
						self::repeatToLength($multibyte, self::PARAMETER_VALUE_MAX_LENGTH)
				]
			],
			'4 byte characters of maximum length' => [
				[
					str_repeat('😀', self::PARAMETER_NAME_MAX_LENGTH) =>
						str_repeat('😀', self::PARAMETER_VALUE_MAX_LENGTH),
					'next' => $multibyte
				]
			]
		];
	}

	/**
	 * Test script item parameters are passed to script during item test.
	 *
	 * @dataProvider getTestItemParametersData
	 *
	 * @param array $parameters  Script parameters (name => value).
	 */
	public function testScriptItems_testItemParameters(array $parameters) {
		$item_test_data = [
			'item' => [
				'hostid' => self::$hostid,
				'type' => ITEM_TYPE_SCRIPT,
				'key' => 'test_script_item',
				'timeout' => '3s',
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'params' => 'return value;',
				'parameters' => $parameters
			]
		];

		$result = $this->getClient(self::COMPONENT_SERVER)->testItem($item_test_data, self::$sid);

		$this->assertFalse($result === false);
		$this->assertArrayHasKey('item', $result);
		$this->assertArrayNotHasKey('error', $result['item'],
			'Unexpected item test error: '.($result['item']['error'] ?? '')
		);
		$this->assertArrayHasKey('result', $result['item']);
		$this->assertSame($parameters, json_decode($result['item']['result'], true),
			'Unexpected script value: '.$result['item']['result']
		);
	}
}

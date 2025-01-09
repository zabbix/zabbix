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
 * Test case to check if an item preprocessing "custom on fail" supports
 * macros in error handler parameter. Both "set value to" and "set error to"
 * are checked in this test case.
 *
 * @backup hosts
 */
class testCustomOnFail extends CIntegrationTest {

	private static $hostid;
	private static $master_itemid;
	private static $dep1_itemid;
	private static $dep2_itemid;

	const HOST_NAME          = 'test';
	const MASTER_ITEM_NAME   = 'master';
	const DEP_ITEM1_NAME     = 'dep1';
	const DEP_ITEM2_NAME     = 'dep2';
	const TEST_MACRO_NAME    = '{$TEST.MACRO}';
	const TEST_MACRO_VALUE   = '5';

	/**
	 * Create a host with 1 macro and 3 items to test preprocessing.
	 */
	public function prepareData() {
		// Create the host
		$response = $this->call('host.create', [
			'host' => self::HOST_NAME,
			'interfaces' => [
				[
					'type'  => 1,
					'main'  => 1,
					'useip' => 1,
					'ip'    => '127.0.0.1',
					'dns'   => '',
					'port'  => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				]
			],
			'groups' => [
				[
					'groupid' => 4
				]
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		// Check host interface ids
		$response = $this->call('host.get', [
			'output'           => ['host'],
			'hostids'          => [self::$hostid],
			'selectInterfaces' => ['interfaceid']
		]);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('interfaces', $response['result'][0]);
		$this->assertArrayHasKey(0, $response['result'][0]['interfaces']);

		// Create trapper item
		$response = $this->call('item.create', [
			'hostid'     => self::$hostid,
			'name'       => self::MASTER_ITEM_NAME,
			'key_'       => self::MASTER_ITEM_NAME,
			'type'       => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_STR
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		self::$master_itemid = $response['result']['itemids'][0];

		// Create dependent item with custom on fail and "set value to"
		$response = $this->call('item.create', [
			'hostid'        => self::$hostid,
			'master_itemid' => self::$master_itemid,
			'name'          => self::DEP_ITEM1_NAME,
			'key_'          => self::DEP_ITEM1_NAME,
			'type'          => ITEM_TYPE_DEPENDENT,
			'value_type'    => ITEM_VALUE_TYPE_UINT64,
			'preprocessing' => [
				[
					'type'                 => ZBX_PREPROC_MULTIPLIER,
					'params'               => '10',
					'error_handler'        => ZBX_PREPROC_FAIL_SET_VALUE,
					'error_handler_params' => self::TEST_MACRO_NAME
				]
			]
		]);

		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		self::$dep1_itemid = $response['result']['itemids'][0];

		// Create dependent item with custom on fail and "set error to"
		$response = $this->call('item.create', [
			'hostid'        => self::$hostid,
			'master_itemid' => self::$master_itemid,
			'name'          => self::DEP_ITEM2_NAME,
			'key_'          => self::DEP_ITEM2_NAME,
			'type'          => ITEM_TYPE_DEPENDENT,
			'value_type'    => ITEM_VALUE_TYPE_UINT64,
			'preprocessing' => [
				[
					'type'                 => ZBX_PREPROC_MULTIPLIER,
					'params'               => '10',
					'error_handler'        => ZBX_PREPROC_FAIL_SET_ERROR,
					'error_handler_params' => self::TEST_MACRO_NAME
				]
			]
		]);

		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		self::$dep2_itemid = $response['result']['itemids'][0];

		// Add host macro
		$response = $this->call('usermacro.create', [
			'hostid' => self::$hostid,
			'macro'  => self::TEST_MACRO_NAME,
			'value'  => self::TEST_MACRO_VALUE
		]);

		$this->assertArrayHasKey('hostmacroids', $response['result']);
		$this->assertIsArray($response['result']['hostmacroids']);

		return true;
	}
	/**
	 * Check if macro was resolved in history of the first item and error field of the second.
	 */
	private function validateDependentItems() {
		$response = $this->call('history.get', [
			'itemids' => [self::$dep1_itemid]
		]);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(self::TEST_MACRO_VALUE, $response['result'][0]['value']);

		$response = $this->call('item.get', [
			'itemids' => [self::$dep2_itemid],
			'output' => ['error']
		]);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('error', $response['result'][0]);
		$this->assertEquals(self::TEST_MACRO_VALUE, $response['result'][0]['error']);
	}

	/**
	 * @required-components server
	 */
	public function testCustomOnFail_checkMacrosSupport() {
		// Reload configuration cache before sending values
		$this->reloadConfigurationCache();

		// Send the value to master item, it should give an error in preprocessing
		$this->sendSenderValue(self::HOST_NAME, self::MASTER_ITEM_NAME, 'abc');

		// Perform the validation
		$this->validateDependentItems();
	}
}

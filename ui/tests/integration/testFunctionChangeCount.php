<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * Test suite for changecount() function evaluation.
 *
 * @required-components server
 * @hosts test_host
 * @backup history
 */
class testFunctionChangeCount extends CIntegrationTest {

	const HOST_NAME = 'test_host';
	const WAIT_TIME = 1;
	const FUNC_NAME = 'changecount';

	private static $hostid;
	private static $interfaceid;

	private static $items = [
		'item_ui64' => [
			'key' => 'item_ui64',
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'triggers' => [
				// All values different, this case is also used to check
				// "not enough data" scenario
				'all_different' => [
					'params' => '#5',
					'expected_result' => 4
				],
				// Some values are equal
				'some_equal' => [
					'params' => '#5',
					'expected_result' => 2
				],
				// "inc" mode
				'inc' => [
					'params' => '#5,"inc"',
					'expected_result' => 2
				],
				// "dec" mode
				'dec' => [
					'params' => '#5,"dec"',
					'expected_result' => 2
				],
				// Explicit "all" mode
				'all' => [
					'params' => '#5,"all"',
					'expected_result' => 3
				],
				// Below scenarios have wrong parameters, trigger creation must fail
				[
					'params' => '#5,"inc","strict"',
					'expected_result' => 0,
					'expected_error' => true
				],
				[
					'params' => '',
					'expected_result' => 0,
					'expected_error' => true
				],
				[
					'params' => '"inc"',
					'expected_result' => 0,
					'expected_error' => true
				],
				[
					'params' => '#20,"strict"',
					'expected_result' => 0,
					'expected_error' => true
				]
			]
		],
		'item_dbl' => [
			'key' => 'item_dbl',
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'triggers' => [
				// All values different
				'all_different' => [
					'params' => '#5',
					'expected_result' => 4
				],
				// Some values are equal, explicit "all" mode
				'some_equal' => [
					'params' => '#5,"all"',
					'expected_result' => 3
				],
				// "inc" mode
				'inc' => [
					'params' => '#5,"inc"',
					'expected_result' => 3
				],
				// "dec" mode
				'dec' => [
					'params' => '#5,"dec"',
					'expected_result' => 1
				]
			]
		],
		'item_str' => [
			'key' => 'item_str',
			'value_type' => ITEM_VALUE_TYPE_STR,
			'triggers' => [
				// All values different
				'all_different' => [
					'params' => '#5',
					'expected_result' => 4
				],
				// Some values are equal
				'some_equal' => [
					'params' => '#5',
					'expected_result' => 2
				]
			]
		]
	];

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "test_host"
		$response = $this->call('host.create', [
			[
				'host' => self::HOST_NAME,
				'interfaces' => [
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_NOT_MONITORED
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		// Get host interface ids
		$response = $this->call('host.get', [
			'output' => ['host'],
			'hostids' => [self::$hostid],
			'selectInterfaces' => ['interfaceid']
		]);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('interfaces', $response['result'][0]);
		$this->assertArrayHasKey(0, $response['result'][0]['interfaces']);
		self::$interfaceid = $response['result'][0]['interfaces'][0]['interfaceid'];

		// Create trapper items
		foreach (self::$items as &$item) {
			$items[] = [
				'name' => $item['key'],
				'key_' => $item['key'],
				'value_type' => $item['value_type'],
				'type' => ITEM_TYPE_TRAPPER,
				'hostid' => self::$hostid
			];
		}

		$response = $this->call('item.create', $items);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(count($items), count($response['result']['itemids']));
		$itemids = $response['result']['itemids'];
		$id = 0;

		foreach (self::$items as &$item) {
			$item['itemid'] = $itemids[$id++];
		}

		// Create triggers
		foreach (self::$items as &$item) {
			foreach ($item['triggers'] as &$trigger) {
				$response = $this->call('trigger.create', [
					[
						'description' => self::FUNC_NAME.'('.$trigger['params'].')',
						'expression' => self::FUNC_NAME.'(/'.self::HOST_NAME.'/'.$item['key'].','.$trigger['params'].')='.$trigger['expected_result']
					]
				], $error = (array_key_exists('expected_error', $trigger)));
				// Check for scenarios when trigger creation must fail
				if (array_key_exists('expected_error', $trigger)) {
					$this->assertArrayHasKey('error', $response);
					continue;
				}

				$this->assertArrayHasKey('triggerids', $response['result']);
				$this->assertEquals(1, count($response['result']['triggerids']));
				$trigger['triggerid'] = $response['result']['triggerids'][0];
			}
		}

		return true;
	}

	/**
	 * Send values
	 */
	private function sendValues($item, $values) {
		foreach ($values as &$value) {
			$this->sendSenderValue(self::HOST_NAME, $item, $value);
		}
		sleep(self::WAIT_TIME);

		return true;
	}

	/**
	 * Check trigger value
	 */
	private function checkTriggerValue($trigger) {
		$response = $this->call('trigger.get', ['triggerids' => [$trigger['triggerid']]]);
		$this->assertEquals(1, $response['result'][0]['value']);

		return true;
	}

	public function testFunctionChangeCount_Send_NotEnoughData() {
		return $this->sendValues('item_ui64', [64]);
	}

	/**
	 * @depends testFunctionChangeCount_Send_NotEnoughData
	 */
	public function testFunctionChangeCount_Get_NotEnoughData() {
		$response = $this->call('trigger.get',
			['triggerids' => [self::$items['item_ui64']['triggers']['all_different']['triggerid']]]);

		$this->assertArrayHasKey('error', $response['result'][0]);
		$this->assertContains('not enough data', $response['result'][0]['error']);

		return true;
	}

	/**
	 * @depends testFunctionChangeCount_Get_NotEnoughData
	 */
	public function testFunctionChangeCount_Send_Ui64ImplicitAll_AllDifferent() {
		return $this->sendValues('item_ui64', [64, 24, 69, 32, 96]);
	}

	/**
	 * @depends testFunctionChangeCount_Send_Ui64ImplicitAll_AllDifferent
	 */
	public function testFunctionChangeCount_Get_Ui64ImplicitAll_AllDifferent() {
		return $this->checkTriggerValue(self::$items['item_ui64']['triggers']['all_different']);
	}

	public function testFunctionChangeCount_Send_Ui64ImplicitAll_SomeEqual() {
		return $this->sendValues('item_ui64', [97, 35, 35, 29, 29]);
	}

	/**
	 * @depends testFunctionChangeCount_Send_Ui64ImplicitAll_SomeEqual
	 */
	public function testFunctionChangeCount_Get_Ui64ImplicitAll_SomeEqual() {
		return $this->checkTriggerValue(self::$items['item_ui64']['triggers']['some_equal']);
	}

	public function testFunctionChangeCount_Send_Ui64Inc() {
		return $this->sendValues('item_ui64', [21, 34, 73, 55, 46]);
	}

	/**
	 * @depends testFunctionChangeCount_Send_Ui64Inc
	 */
	public function testFunctionChangeCount_Get_Ui64Inc() {
		return $this->checkTriggerValue(self::$items['item_ui64']['triggers']['inc']);
	}

	public function testFunctionChangeCount_Send_Ui64Dec() {
		return $this->sendValues('item_ui64', [7, 92, 67, 96, 44]);
	}

	/**
	 * @depends testFunctionChangeCount_Send_Ui64Dec
	 */
	public function testFunctionChangeCount_Get_Ui64Dec() {
		return $this->checkTriggerValue(self::$items['item_ui64']['triggers']['dec']);
	}

	public function testFunctionChangeCount_Send_Ui64ExplicitAll() {
		return $this->sendValues('item_ui64', [55, 93, 76, 76, 33]);
	}

	/**
	 * @depends testFunctionChangeCount_Send_Ui64ExplicitAll
	 */
	public function testFunctionChangeCount_Get_Ui64_ExplicitAll() {
		return $this->checkTriggerValue(self::$items['item_ui64']['triggers']['all']);
	}

	public function testFunctionChangeCount_Send_DblImplicitAll() {
		return $this->sendValues('item_dbl', [0.000005, 0.000001, 0.000007, 0.000004, 0.000002]);
	}

	/**
	 * @depends testFunctionChangeCount_Send_DblImplicitAll
	 */
	public function testFunctionChangeCount_Get_DblImplicitAll() {
		return $this->checkTriggerValue(self::$items['item_dbl']['triggers']['all_different']);
	}

	public function testFunctionChangeCount_Send_DblExplicitAll() {
		return $this->sendValues('item_dbl', [0.0001, 0.0011, 0.0011, 0.0002, 0.0005]);
	}

	/**
	 * @depends testFunctionChangeCount_Send_DblExplicitAll
	 */
	public function testFunctionChangeCount_Get_DblExplicitAll() {
		return $this->checkTriggerValue(self::$items['item_dbl']['triggers']['some_equal']);
	}

	public function testFunctionChangeCount_Send_DblInc() {
		return $this->sendValues('item_dbl', [0.0001, 0.0002, 0.0003, 0.0004, 0.0004]);
	}

	/**
	 * @depends testFunctionChangeCount_Send_DblInc
	 */
	public function testFunctionChangeCount_Get_DblInc() {
		return $this->checkTriggerValue(self::$items['item_dbl']['triggers']['inc']);
	}

	public function testFunctionChangeCount_Send_DblDec() {
		return $this->sendValues('item_dbl', [0.00001, 0.00002, 0.00003, 0.00004, 0.00003]);
	}

	/**
	 * @depends testFunctionChangeCount_Send_DblDec
	 */
	public function testFunctionChangeCount_Get_DblDec() {
		return $this->checkTriggerValue(self::$items['item_dbl']['triggers']['dec']);
	}

	public function testFunctionChangeCount_Send_StrAllDifferent() {
		return $this->sendValues('item_str', ['abc', 'def', 'ghi', 'jkl', 'mno']);
	}

	/**
	 * @depends testFunctionChangeCount_Send_StrAllDifferent
	 */
	public function testFunctionChangeCount_Get_StrAllDifferent() {
		return $this->checkTriggerValue(self::$items['item_str']['triggers']['all_different']);
	}

	public function testFunctionChangeCount_Send_StrSomeEqual() {
		return $this->sendValues('item_str', ['abc', 'abc', 'ghi', 'ghi', 'mno']);
	}

	/**
	 * @depends testFunctionChangeCount_Send_StrSomeEqual
	 */
	public function testFunctionChangeCount_Get_StrSomeEqual() {
		return $this->checkTriggerValue(self::$items['item_str']['triggers']['some_equal']);
	}
}


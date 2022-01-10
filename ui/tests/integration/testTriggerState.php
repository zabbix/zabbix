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
 * Test suite to check if trigger state is updated properly
 * when item state toggles between normal and unsupported
 *
 * @required-components server
 * @backup history
 * @hosts test
 */
class testTriggerState extends CIntegrationTest {

	private static $hostid;
	private static $triggerid;

	const TRAPPER_ITEM_NAME = 'trap';
	const HOST_NAME = 'test';

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "test"
		$response = $this->call('host.create', [
			'host' => self::HOST_NAME,
			'interfaces' => [
				[
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
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

		// Get host interface ids.
		$response = $this->call('host.get', [
			'output' => ['host'],
			'hostids' => [self::$hostid],
			'selectInterfaces' => ['interfaceid']
		]);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('interfaces', $response['result'][0]);
		$this->assertArrayHasKey(0, $response['result'][0]['interfaces']);

		// Create trapper item
		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => self::TRAPPER_ITEM_NAME,
			'key_' => self::TRAPPER_ITEM_NAME,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		// Create trigger
		$response = $this->call('trigger.create', [
			'description' => 'Trapper received 1',
			'expression' => 'last(/'.self::HOST_NAME.'/'.self::TRAPPER_ITEM_NAME.')=1'
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertEquals(1, count($response['result']['triggerids']));
		self::$triggerid = $response['result']['triggerids'][0];

		return true;
	}

	private function validateTriggerParams($expected_state, $expected_value) {
		$response = $this->call('trigger.get', [
			'triggerids' => [self::$triggerid]
		]);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals($expected_state, $response['result'][0]['state']);
		$this->assertEquals($expected_value, $response['result'][0]['value']);
	}

	private function recoverTrigger() {
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 0);
		$this->validateTriggerParams(TRIGGER_STATE_NORMAL, TRIGGER_VALUE_FALSE);
	}

	private function fireTrigger() {
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 1);
		$this->validateTriggerParams(TRIGGER_STATE_NORMAL, TRIGGER_VALUE_TRUE);
	}

	/**
	 * Scenario description:
	 * 	1. trigger state is NORMAL, trigger value is OK
	 * 	fire trigger
	 * 	expect trigger state to be NORMAL and trigger value to be PROBLEM
	 *
	 * 	2. send unsupported value type
	 * 	expect trigger state to be UNKNOWN and trigger value to be PROBLEM
	 *
	 * 	3. recover trigger
	 * 	expect trigger state to be NORMAL and trigger value to be OK
	 *
	 */
	public function testTriggerState_checkScenario1() {
		// Reload configuration cache before sending values
		$this->reloadConfigurationCache();

		// Send first value
		$this->recoverTrigger();

		$this->fireTrigger();

		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 'a');
		$this->validateTriggerParams(TRIGGER_STATE_UNKNOWN, TRIGGER_VALUE_TRUE);

		$this->recoverTrigger();
	}

	/**
	 * Scenario description:
	 * 	1. trigger state is NORMAL, trigger value is OK
	 * 	send unsupported value type
	 * 	expect trigger state to be UNKNOWN and trigger value to be OK
	 *
	 * 	2. fire trigger
	 * 	expect trigger state to be NORMAL and trigger value to be PROBLEM
	 *
	 * @depends testTriggerState_checkScenario1
	 */
	public function testTriggerState_checkScenario2() {
		$this->sendSenderValue(self::HOST_NAME, self::TRAPPER_ITEM_NAME, 'a');
		$this->validateTriggerParams(TRIGGER_STATE_UNKNOWN, TRIGGER_VALUE_FALSE);

		$this->fireTrigger();
	}

}


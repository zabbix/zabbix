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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @backup triggers
 *
 * @onBefore prepare_trigger_data
 * @onAfter cleanup_trigger_data
 */
class testTriggers extends CAPITest {

	public function prepare_trigger_data(): void {
		$result = $this->call('item.create', [
			'hostid' => '50009',
			'name' => 'master.item',
			'key_' => 'master.item',
			'type' => ITEM_TYPE_ZABBIX,
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'interfaceid' => 50022,
			'delay' => '1m'
		]);
		$master_itemid = reset($result['result']['itemids']);

		$result = $this->call('item.create', [
			'hostid' => '50009',
			'name' => 'binary.item',
			'key_' => 'binary.item',
			'type' => ITEM_TYPE_DEPENDENT,
			'master_itemid' => $master_itemid,
			'value_type' => ITEM_VALUE_TYPE_BINARY
		]);
	}

	public function cleanup_trigger_data(): void {
		$result = $this->call('item.get', [
			'filter' => [
				'key_' => 'master.item'
			]
		]);
		$master_itemid = reset($result['result']['itemids']);

		$this->call('item.delete', [$master_itemid]);
	}

	public static function trigger_get_data() {
		$triggerids = ['134118', '134000', '134001', '134002', '134003', '134004', '134005'];
		$dependent_triggerids = ['134004', '134005'];

		return [
			[
				'params' => [
					'output' => ['triggerids'],
					'triggerids' => $triggerids,
					'dependent' => null
				],
				'expect' => [
					'error' => null,
					'triggerids' => $triggerids
				]
			],
			[
				'params' => [
					'output' => ['triggerids'],
					'triggerids' => $triggerids,
					'dependent' => true
				],
				'expect' => [
					'error' => null,
					'triggerids' => $dependent_triggerids
				]
			],
			[
				'params' => [
					'output' => ['triggerids'],
					'triggerids' => $triggerids,
					'dependent' => false
				],
				'expect' => [
					'error' => null,
					'triggerids' => array_diff($triggerids, $dependent_triggerids)
				]
			],
			[
				'params' => [
					'output' => ['triggerids'],
					'hostids' => ['130000'],
					'dependent' => true
				],
				'expect' => [
					'error' => null,
					'triggerids' => $dependent_triggerids
				]
			],
			[
				'params' => [
					'output' => ['triggerids'],
					'hostids' => ['130000'],
					'dependent' => false
				],
				'expect' => [
					'error' => null,
					'triggerids' => array_diff($triggerids, $dependent_triggerids)
				]
			]
		];
	}

	/**
	* @dataProvider trigger_get_data
	*/
	public function testTrigger_Get($params, $expect) {
		$response = $this->call('trigger.get', $params, $expect['error']);

		if ($expect['error'] !== null) {
			return;
		}

		$triggerids = array_column($response['result'], 'triggerid');
		sort($triggerids);
		sort($expect['triggerids']);
		$this->assertSame($expect['triggerids'], $triggerids);
	}

	public static function trigger_create_data() {
		return [
			'Prohibit binary items in expression' => [
				'params' => [
					'description' => 'trigger.error',
					'expression' => 'last(/API Host/binary.item)=0'
				],
				'error' => 'Incorrect item value type "Binary" provided for trigger function "last".'
			],
			'Prohibit binary items in recovery expression' => [
				'params' => [
					'description' => 'trigger.error',
					'recovery_mode' => ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION,
					'expression' => 'last(/API Host/master.item)=0',
					'recovery_expression' => 'last(/API Host/binary.item)=0'
				],
				'error' => 'Incorrect item value type "Binary" provided for trigger function "last".'
			]
		];
	}

	/**
	* @dataProvider trigger_create_data
	*/
	public function testTrigger_Create($params, $expected_error) {
		$this->call('trigger.create', $params, $expected_error);
	}
}

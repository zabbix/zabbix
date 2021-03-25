<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CAPITest.php';
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';

/**
 * @backup items
 */
class testItemPrototype extends CAPITest {

		public static function getItemPrototypeCreateData() {
		return [
			// Test update interval for mqtt key of the Agent item type.
			[
				'request_data' => [
					'hostid' => '50009',
					'ruleid' => '40066',
					'name' => 'Test mqtt key',
					'key_' => 'mqtt.get[{#0}]',
					'interfaceid' => '50022',
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_ZABBIX,
					'delay' => '30s'
				],
				'expected_error' => null
			],
			[
				'request_data' => [
					'hostid' => '50009',
					'name' => 'Test mqtt key without delay',
					'key_' => 'mqtt.get[{#1}]',
					'interfaceid' => '50022',
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_ZABBIX
				],
				'expected_error' => 'Incorrect arguments passed to function.'
			],
			[
				'request_data' => [
					'hostid' => '50009',
					'ruleid' => '40066',
					'name' => 'Test mqtt key with 0 delay',
					'key_' => 'mqtt.get[{#2}]',
					'interfaceid' => '50022',
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_ZABBIX,
					'delay' => '0'
				],
				'expected_error' => 'Item will not be refreshed. Specified update interval requires having at least one either flexible or scheduling interval.'
			],
			// Test update interval for mqtt key of the Active agent type.
			[
				'request_data' => [
					'hostid' => '50009',
					'ruleid' => '40066',
					'name' => 'Test mqtt key for active agent',
					'key_' => 'mqtt.get[{#3}]',
					'interfaceid' => '50022',
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_ZABBIX_ACTIVE
				],
				'expected_error' => null
			],
			[
				'request_data' => [
					'hostid' => '50009',
					'ruleid' => '40066',
					'name' => 'Test mqtt key with 0 delay for active agent',
					'key_' => 'mqtt.get[{#4}]',
					'interfaceid' => '50022',
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_ZABBIX_ACTIVE,
					'delay' => '0'
				],
				'expected_error' => null
			],
			[
				'request_data' => [
					'hostid' => '50009',
					'ruleid' => '40066',
					'name' => 'Test mqtt with wrong key and 0 delay',
					'key_' => 'mqt.get[{#5}]',
					'interfaceid' => '50022',
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_ZABBIX_ACTIVE,
					'delay' => '0'
				],
				'expected_error' => 'Item will not be refreshed. Specified update interval requires having at least one either flexible or scheduling interval.'
			]
		];
	}

	/**
	 * @dataProvider getItemPrototypeCreateData
	 */
	public function testItemPrototype_Create($request_data, $expected_error) {
		$result = $this->call('itemprototype.create', $request_data, $expected_error);

		if ($expected_error === null) {
		if ($request_data['type'] === ITEM_TYPE_ZABBIX_ACTIVE && substr($request_data['key_'], 0, 8) === 'mqtt.get') {
			$request_data['delay'] = CTestArrayHelper::get($request_data, 'delay', '0');
		}

			foreach ($result['result']['itemids'] as $id) {
				$db_item = CDBHelper::getRow('SELECT hostid, name, key_, type, delay FROM items WHERE itemid='.zbx_dbstr($id));

				foreach (['hostid', 'name', 'key_', 'type', 'delay'] as $field) {
					$this->assertSame($db_item[$field], strval($request_data[$field]));
				}
			}
		}
	}
}

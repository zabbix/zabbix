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

require_once dirname(__FILE__).'/../include/CAPITest.php';
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';

/**
 * @backup items
 */
class testItemPrototype extends CAPITest {

	public static function getItemPrototypeCreateData() {
		$valid_item_types = [
			ITEM_TYPE_ZABBIX => '50022',
			ITEM_TYPE_TRAPPER => null,
			ITEM_TYPE_SIMPLE => '50022',
			ITEM_TYPE_INTERNAL => null,
			ITEM_TYPE_ZABBIX_ACTIVE => null,
			ITEM_TYPE_EXTERNAL => '50022',
			ITEM_TYPE_DB_MONITOR => null,
			ITEM_TYPE_IPMI => '50031',
			ITEM_TYPE_SSH => '50022',
			ITEM_TYPE_TELNET => '50022',
			ITEM_TYPE_CALCULATED => null,
			ITEM_TYPE_JMX => '50030',
			ITEM_TYPE_DEPENDENT => null,
			ITEM_TYPE_HTTPAGENT => '50022',
			ITEM_TYPE_SNMP => '50029',
			ITEM_TYPE_SCRIPT => '50022'
		];

		$item_type_tests = [];
		foreach ($valid_item_types as $type => $interfaceid) {
			switch ($type) {
				case ITEM_TYPE_IPMI:
					$params = [
						'ipmi_sensor' => '1.2.3'
					];
					break;

				case ITEM_TYPE_TRAPPER:
					$params = [
						'delay' => '0'
					];
					break;

				case ITEM_TYPE_TELNET:
				case ITEM_TYPE_SSH:
					$params = [
						'username' => 'username',
						'authtype' => ITEM_AUTHTYPE_PASSWORD
					];
					break;

				case ITEM_TYPE_DEPENDENT:
					$params = [
						'master_itemid' => '150151',
						'delay' => '0'
					];
					break;

				case ITEM_TYPE_JMX:
					$params = [
						'username' => 'username',
						'password' => 'password'
					];
					break;

				case ITEM_TYPE_HTTPAGENT:
					$params = [
						'url' => 'http://0.0.0.0'
					];
					break;

				case ITEM_TYPE_SNMP:
					$params = [
						'snmp_oid' => '1.2.3'
					];
					break;

				case ITEM_TYPE_SCRIPT:
					$params = [
						'params' => 'script',
						'timeout' => '30s'
					];
					break;

				default:
					$params = [];
					break;
			}

			if ($interfaceid) {
				$params['interfaceid'] = $interfaceid;
			}

			$item_type_tests[] = [
				'request_data' => $params + [
					'hostid' => '50009',
					'ruleid' => '400660',
					'name' => 'Test item prototype of type '.$type,
					'key_' => 'test_item_prototype_of_type_'.$type,
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => (string) $type,
					'delay' => '30s'
				],
				'expected_error' => null
			];
		}

		return [
			[
				'request_data' => [
					'hostid' => '50009',
					'ruleid' => '400660',
					'name' => 'Item prototype with invalid item type',
					'key_' => 'item_prototype_with_invalid_item_type',
					'interfaceid' => '50022',
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => '100',
					'delay' => '30s'
				],
				'expected_error' => 'Invalid parameter "/1/type": value must be one of '.implode(', ', [
					ITEM_TYPE_ZABBIX, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE,
					ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET,
					ITEM_TYPE_CALCULATED, ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT,
					ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT
				]).'.'
			],
			// Test update interval for mqtt key of the Agent item type.
			[
				'request_data' => [
					'hostid' => '50009',
					'ruleid' => '400660',
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
					'ruleid' => '400660',
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
					'ruleid' => '400660',
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
					'ruleid' => '400660',
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
					'ruleid' => '400660',
					'name' => 'Test mqtt with wrong key and 0 delay',
					'key_' => 'mqt.get[{#5}]',
					'interfaceid' => '50022',
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_ZABBIX_ACTIVE,
					'delay' => '0'
				],
				'expected_error' => 'Item will not be refreshed. Specified update interval requires having at least one either flexible or scheduling interval.'
			]
		] + $item_type_tests;
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

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
			ITEM_TYPE_SCRIPT => null,
			ITEM_TYPE_BROWSER => null
		];

		$item_type_tests = [];
		foreach ($valid_item_types as $type => $interfaceid) {
			switch ($type) {
				case ITEM_TYPE_ZABBIX:
				case ITEM_TYPE_SIMPLE:
				case ITEM_TYPE_INTERNAL:
				case ITEM_TYPE_ZABBIX_ACTIVE:
				case ITEM_TYPE_EXTERNAL:
					$params = [
						'delay' => '30s'
					];
					break;

				case ITEM_TYPE_DB_MONITOR:
					$params = [
						'params' => 'SELECT * FROM table',
						'delay' => '30s'
					];
					break;

				case ITEM_TYPE_IPMI:
					$params = [
						'ipmi_sensor' => '1.2.3',
						'delay' => '30s'
					];
					break;

				case ITEM_TYPE_SSH:
					$params = [
						'username' => 'username',
						'authtype' => ITEM_AUTHTYPE_PASSWORD,
						'params' => 'return true;',
						'delay' => '30s'
					];
					break;

				case ITEM_TYPE_TELNET:
					$params = [
						'username' => 'username',
						'params' => 'return true;',
						'delay' => '30s'
					];
					break;

				case ITEM_TYPE_CALCULATED:
					$params = [
						'params' => '1+1',
						'delay' => '30s'
					];
					break;

				case ITEM_TYPE_JMX:
					$params = [
						'username' => 'username',
						'password' => 'password',
						'delay' => '30s'
					];
					break;

				case ITEM_TYPE_DEPENDENT:
					$params = [
						'master_itemid' => '150151'
					];
					break;

				case ITEM_TYPE_HTTPAGENT:
					$params = [
						'url' => 'http://0.0.0.0',
						'delay' => '30s'
					];
					break;

				case ITEM_TYPE_SNMP:
					$params = [
						'snmp_oid' => '1.2.3',
						'delay' => '30s'
					];
					break;

				case ITEM_TYPE_SCRIPT:
					$params = [
						'params' => 'return JSON.encode({});',
						'timeout' => '30s',
						'delay' => '30s'
					];
					break;

				case ITEM_TYPE_BROWSER:
					$params = [
						'params' => 'return JSON.encode({});',
						'timeout' => '30s',
						'delay' => '30s'
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
					'key_' => 'test_item_prototype_of_type_'.$type.'[{#LLD_MACRO}]',
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => (string) $type
				],
				'expected_error' => null
			];
		}

		$interfaces_tests = [];
		$optional = [ITEM_TYPE_SIMPLE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_HTTPAGENT];
		$required = [ITEM_TYPE_SNMP, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_IPMI, ITEM_TYPE_ZABBIX, ITEM_TYPE_JMX];

		foreach ($item_type_tests as $item_type_test) {
			if (in_array($item_type_test['request_data']['type'], $optional)) {
				unset($item_type_test['request_data']['interfaceid']);

				$request_data = [
					'name' => $item_type_test['request_data']['name'].' missing',
					'key_' => substr($item_type_test['request_data']['key_'], 0, -1).', missing]'
				] + $item_type_test['request_data'];

				$interfaces_tests[] = ['request_data' => $request_data] + $item_type_test;

				$request_data = [
					'name' => $item_type_test['request_data']['name'].' zero',
					'key_' => substr($item_type_test['request_data']['key_'], 0, -1).', zero]',
					'interfaceid' => '0'
				] + $item_type_test['request_data'];

				$interfaces_tests[] = ['request_data' => $request_data] + $item_type_test;
			}
			else if (in_array($item_type_test['request_data']['type'], $required)) {
				unset($item_type_test['request_data']['interfaceid']);
				$item_type_test['expected_error'] = 'Invalid parameter "/1": the parameter "interfaceid" is missing.';
				$interfaces_tests[] = $item_type_test;
			}
		}

		return array_merge([
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
					ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER
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
				'expected_error' => 'Invalid parameter "/1": the parameter "ruleid" is missing.'
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
				'expected_error' => 'Invalid parameter "/1/delay": cannot be equal to zero without custom intervals.'
			],
			// Test update interval for mqtt key of the Active agent type.
			[
				'request_data' => [
					'hostid' => '50009',
					'ruleid' => '400660',
					'name' => 'Test mqtt key for active agent',
					'key_' => 'mqtt.get[{#3}]',
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
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_ZABBIX_ACTIVE,
					'delay' => '0'
				],
				'expected_error' => 'Invalid parameter "/1/delay": cannot be equal to zero without custom intervals.'
			]
		], $item_type_tests, $interfaces_tests);
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

			if (!array_key_exists('delay', $request_data)) {
				$request_data['delay'] = 0;
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

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
 * @onBefore prepareUpdateData
 */
class testItem extends CAPITest {

	protected static $items;

	public static function getItemCreateData() {
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
		$binary_valuetype_tests = [];

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
					'name' => 'Item of type '.$type,
					'key_' => 'item_of_type_'.$type,
					'hostid' => '50009',
					'type' => (string) $type,
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				'expected_error' => null
			];

			$binary_valuetype_tests[] = [
				'request_data' => $params + [
					'hostid' => '50009',
					'name' => 'Test binary with item type '.$type,
					'key_' => 'test.binary.'.$type,
					'type' => $type,
					'value_type' => ITEM_VALUE_TYPE_BINARY
				],
				'expected_error' => $type == ITEM_TYPE_DEPENDENT
					? null
					: 'Invalid parameter "/1/value_type": value must be one of '.implode(', ', [
						ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
						ITEM_VALUE_TYPE_TEXT
					]).'.'
			];

			// Additional type-specific cases.
			switch ($type) {
				case ITEM_TYPE_DEPENDENT:
					$rejected_fields = [
						['units', 'b', 'Invalid parameter "/1/units": value must be empty.'],
						['trends', '1h', 'Invalid parameter "/1/trends": value must be 0.'],
						['valuemapid', 123, 'Invalid parameter "/1/valuemapid": value must be 0.'],
						['inventory_link', 123, 'Invalid parameter "/1/inventory_link": value must be 0.'],
						['logtimefmt', 'x', 'Invalid parameter "/1/logtimefmt": value must be empty.']
					];

					foreach ($rejected_fields as $config) {
						[$field, $value, $error] = $config;

						$binary_valuetype_tests['Reject field '.$field.' for dependent item'] = [
							'request_data' => $params + [
								'hostid' => '50009',
								'name' => 'Test binary with item type '.$type,
								'key_' => 'test.binary.'.$type,
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_BINARY,
								$field => $value
							],
							'expected_error' => $error
						];
					}
					break;

				case ITEM_TYPE_HTTPAGENT:
					$item_type_tests += [
						'Reject too long Basic authentication username' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.reject.username',
								'name' => 'httpagent.reject.username',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'authtype' => ZBX_HTTP_AUTH_BASIC,
								'username' => str_repeat('z', 256)
							],
							'expected_error' => 'Invalid parameter "/1/username": value is too long.'
						],
						'Reject too long Basic authentication password' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.reject.password',
								'name' => 'httpagent.reject.password',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'authtype' => ZBX_HTTP_AUTH_BASIC,
								'password' => str_repeat('z', 256)
							],
							'expected_error' => 'Invalid parameter "/1/password": value is too long.'
						],
						'Accept longest Basic authentication username' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.accept.username',
								'name' => 'httpagent.accept.username',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'authtype' => ZBX_HTTP_AUTH_BASIC,
								'username' => str_repeat('z', 255)
							],
							'expected_error' => null
						],
						'Accept longest Basic authentication password' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.accept.password',
								'name' => 'httpagent.accept.password',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'authtype' => ZBX_HTTP_AUTH_BASIC,
								'password' => str_repeat('z', 255)
							],
							'expected_error' => null
						],
						'Accept query fields' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.accept.query_fields',
								'name' => 'httpagent.accept.query_fields',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'query_fields' => [
									[
										'name' => 'foo',
										'value' => 'bar'
									]
								]
							],
							'expected_error' => null
						],
						'Accept query fields repeated' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.accept.query_fields.repeated',
								'name' => 'httpagent.accept.query_fields.repeated',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'query_fields' => [
									[
										'name' => 'foo',
										'value' => 'bar'
									],
									[
										'name' => 'foo',
										'value' => 'bar'
									]
								]
							],
							'expected_error' => null
						],
						'Accept query fields empty value' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.accept.query_fields.empty.value',
								'name' => 'httpagent.accept.query_fields.empty.value',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'query_fields' => [
									[
										'name' => 'foo',
										'value' => ''
									]
								]
							],
							'expected_error' => null
						],
						'Reject query fields empty name' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.reject.query_fields.empty.name',
								'name' => 'httpagent.reject.query_fields.empty.name',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'query_fields' => [
									[
										'name' => '',
										'value' => ''
									]
								]
							],
							'expected_error' => 'Invalid parameter "/1/query_fields/1/name": cannot be empty.'
						],
						'Reject query fields unexpected property' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.reject.query_fields.unexpected',
								'name' => 'httpagent.reject.query_fields.unexpected',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'query_fields' => [
									[
										'name' => 'foo',
										'value' => 'bar',
										'sortorder' => 5
									]
								]
							],
							'expected_error' => 'Invalid parameter "/1/query_fields/1": unexpected parameter "sortorder".'
						],
						'Reject old format query fields' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.accept.query_fields.nonarray',
								'name' => 'httpagent.accept.query_fields.nonarray',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'query_fields' => json_encode(['a' => 'b'])
							],
							'expected_error' => 'Invalid parameter "/1/query_fields": an array is expected.'
						],
						'Reject query fields without name' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.reject.query_fields.no.name',
								'name' => 'httpagent.reject.query_fields.no.name',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'query_fields' => [
									[
										'value' => 'bar'
									]
								]
							],
							'expected_error' => 'Invalid parameter "/1/query_fields/1": the parameter "name" is missing.'
						],
						'Reject query fields without value' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.reject.query_fields.no.value',
								'name' => 'httpagent.reject.query_fields.no.value',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'query_fields' => [
									[
										'name' => 'foo'
									]
								]
							],
							'expected_error' => 'Invalid parameter "/1/query_fields/1": the parameter "value" is missing.'
						],
						'Accept max length for query fields' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.accept.query_fields.long',
								'name' => 'httpagent.accept.query_fields.long',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'query_fields' => [
									[
										'name' => 'a',
										'value' => str_repeat('b', DB::getFieldLength('items', 'query_fields') - strlen('[{"a":""}]'))
									]
								]
							],
							'expected_error' => null
						],
						'Reject too long query fields' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.reject.query_fields.long',
								'name' => 'httpagent.reject.query_fields.long',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'query_fields' => [
									[
										'name' => 'a',
										'value' => str_repeat('b', DB::getFieldLength('items', 'query_fields') - strlen('[{"a":""}]') + 1)
									]
								]
							],
							'expected_error' => 'Invalid parameter "/1/query_fields": value is too long.'
						],
						'Accept headers' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.accept.headers',
								'name' => 'httpagent.accept.headers',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'headers' => [
									[
										'name' => 'foo',
										'value' => 'bar'
									]
								]
							],
							'expected_error' => null
						],
						'Accept headers repeated' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.accept.headers.repeated',
								'name' => 'httpagent.accept.headers.repeated',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'headers' => [
									[
										'name' => 'foo',
										'value' => 'bar'
									],
									[
										'name' => 'foo',
										'value' => 'bar'
									]
								]
							],
							'expected_error' => null
						],
						'Accept headers empty value' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.accept.headers.empty.value',
								'name' => 'httpagent.accept.headers.empty.value',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'headers' => [
									[
										'name' => 'foo',
										'value' => ''
									]
								]
							],
							'expected_error' => null
						],
						'Reject headers empty name' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.reject.headers.empty.name',
								'name' => 'httpagent.reject.headers.empty.name',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'headers' => [
									[
										'name' => '',
										'value' => ''
									]
								]
							],
							'expected_error' => 'Invalid parameter "/1/headers/1/name": cannot be empty.'
						],
						'Reject headers unexpected property' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.reject.headers.unexpected',
								'name' => 'httpagent.reject.headers.unexpected',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'headers' => [
									[
										'name' => 'foo',
										'value' => 'bar',
										'sortorder' => 5
									]
								]
							],
							'expected_error' => 'Invalid parameter "/1/headers/1": unexpected parameter "sortorder".'
						],
						'Reject old format headers' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.reject.headers.nonarray',
								'name' => 'httpagent.reject.headers.nonarray',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'headers' => implode("\r\n", ['foo: bar', 'bar: foo'])
							],
							'expected_error' => 'Invalid parameter "/1/headers": an array is expected.'
						],
						'Reject headers without name' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.reject.headers.no.name',
								'name' => 'httpagent.reject.headers.no.name',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'headers' => [
									[
										'value' => 'bar'
									]
								]
							],
							'expected_error' => 'Invalid parameter "/1/headers/1": the parameter "name" is missing.'
						],
						'Reject headers without value' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.reject.headers.no.value',
								'name' => 'httpagent.reject.headers.no.value',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'headers' => [
									[
										'name' => 'foo'
									]
								]
							],
							'expected_error' => 'Invalid parameter "/1/headers/1": the parameter "value" is missing.'
						],
						'Accept max length headers' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.accept.headers.long',
								'name' => 'httpagent.accept.headers.long',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'headers' => [
									[
										'value' => 'a',
										'name' => str_repeat('b', DB::getFieldLength('items', 'headers') - strlen('a: '))
									]
								]
							],
							'expected_error' => null
						],
						'Reject too long headers' => [
							'request_data' => $params + [
								'hostid' => '50009',
								'key_' => 'httpagent.reject.headers.long',
								'name' => 'httpagent.reject.headers.long',
								'type' => $type,
								'value_type' => ITEM_VALUE_TYPE_TEXT,
								'headers' => [
									[
										'value' => 'a',
										'name' => str_repeat('b', DB::getFieldLength('items', 'headers') - strlen('a: ') + 1)
									]
								]
							],
							'expected_error' => 'Invalid parameter "/1/headers": value is too long.'
						]
					];
					break;
			}
		}

		$interfaces_tests = [];
		$optional = [ITEM_TYPE_SIMPLE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_HTTPAGENT];
		$required = [ITEM_TYPE_SNMP, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_IPMI, ITEM_TYPE_ZABBIX, ITEM_TYPE_JMX];

		foreach ($item_type_tests as $item_type_test) {
			if (in_array($item_type_test['request_data']['type'], $optional)) {
				unset($item_type_test['request_data']['interfaceid']);

				$request_data = [
					'name' => $item_type_test['request_data']['name'].' missing',
					'key_' => $item_type_test['request_data']['key_'].'_missing'
				] + $item_type_test['request_data'];

				$interfaces_tests[] = ['request_data' => $request_data] + $item_type_test;

				$request_data = [
					'name' => $item_type_test['request_data']['name'].' zero',
					'key_' => $item_type_test['request_data']['key_'].'_zero',
					'interfaceid' => '0'
				] + $item_type_test['request_data'];

				$interfaces_tests[] = ['request_data' => $request_data] + $item_type_test;
			}
			elseif (in_array($item_type_test['request_data']['type'], $required)) {
				unset($item_type_test['request_data']['interfaceid']);
				$item_type_test['expected_error'] = 'Invalid parameter "/1": the parameter "interfaceid" is missing.';
				$interfaces_tests[] = $item_type_test;
			}
		}

		$uuid = generateUuidV4();
		$uuid_tests = [
			'Reject item with non-empty UUID on host' => [
				'request_data' => [
					'hostid' => '50009',
					'uuid' => $uuid,
					'name' => 'UUIDItem1',
					'key_' => 'UUIDItem1',
					'interfaceid' => 0,
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_HTTPAGENT,
					'delay' => '30s',
					'url' => '192.168.0.1'
				],
				'expected_error' => 'Invalid parameter "/1/uuid": value must be empty.'
			],
			'Accept item with empty UUID on host' => [
				'request_data' => [
					'hostid' => '50009',
					'uuid' => '',
					'name' => 'UUIDItem2',
					'key_' => 'UUIDItem2',
					'interfaceid' => 0,
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_HTTPAGENT,
					'delay' => '30s',
					'url' => '192.168.0.1'
				],
				'expected_error' => null
			],
			'Accept multiple items with empty UUID on host' => [
				'request_data' => [
					[
						'hostid' => '50009',
						'uuid' => '',
						'name' => 'UUIDItem2.1',
						'key_' => 'UUIDItem2.1',
						'interfaceid' => 0,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'type' => ITEM_TYPE_HTTPAGENT,
						'delay' => '30s',
						'url' => '192.168.0.1'
					],
					[
						'hostid' => '50009',
						'uuid' => '',
						'name' => 'UUIDItem2.2',
						'key_' => 'UUIDItem2.2',
						'interfaceid' => 0,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'type' => ITEM_TYPE_HTTPAGENT,
						'delay' => '30s',
						'url' => '192.168.0.1'
					]
				],
				'expected_error' => null
			],
			'Accept item with non-empty UUID on template' => [
				'request_data' => [
					'hostid' => '50010',
					'uuid' => $uuid,
					'name' => 'UUIDItem3',
					'key_' => 'UUIDItem3',
					'interfaceid' => 0,
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_HTTPAGENT,
					'delay' => '30s',
					'url' => '192.168.0.1'
				],
				'expected_error' => null
			],
			'Reject item with empty UUID on template' => [
				'request_data' => [
					'hostid' => '50010',
					'uuid' => '',
					'name' => 'UUIDItem4',
					'key_' => 'UUIDItem4',
					'interfaceid' => 0,
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_HTTPAGENT,
					'delay' => '30s',
					'url' => '192.168.0.1'
				],
				'expected_error' => 'Invalid parameter "/1/uuid": cannot be empty.'
			],
			'Reject same UUID for two template items' => [
				'request_data' => [
					[
						'hostid' => '50010',
						'uuid' => $uuid,
						'name' => 'UUIDItem5',
						'key_' => 'UUIDItem5',
						'interfaceid' => 0,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'type' => ITEM_TYPE_HTTPAGENT,
						'delay' => '30s',
						'url' => '192.168.0.1'
					],
					[
						'hostid' => '50010',
						'uuid' => $uuid,
						'name' => 'UUIDItem6',
						'key_' => 'UUIDItem6',
						'interfaceid' => 0,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'type' => ITEM_TYPE_HTTPAGENT,
						'delay' => '30s',
						'url' => '192.168.0.1'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (uuid)=('.$uuid.') already exists.'
			]
		];

		return array_merge([
			[
				'request_data' => [
					'hostid' => '50009',
					'name' => 'Item with invalid item type',
					'key_' => 'item_with_invalid_item_type',
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
					'name' => 'Test mqtt key',
					'key_' => 'mqtt.get[0]',
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
					'key_' => 'mqtt.get[1]',
					'interfaceid' => '50022',
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_ZABBIX
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "delay" is missing.'
			],
			[
				'request_data' => [
					'hostid' => '50009',
					'name' => 'Test mqtt key with 0 delay',
					'key_' => 'mqtt.get[2]',
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
					'name' => 'Test mqtt key for active agent',
					'key_' => 'mqtt.get[3]',
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_ZABBIX_ACTIVE
				],
				'expected_error' => null
			],
			[
				'request_data' => [
					'hostid' => '50009',
					'name' => 'Test mqtt key with 0 delay for active agent',
					'key_' => 'mqtt.get[4]',
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_ZABBIX_ACTIVE
				],
				'expected_error' => null
			],
			[
				'request_data' => [
					'hostid' => '50009',
					'name' => 'Item with some tags',
					'key_' => 'trapper_item_1',
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_TRAPPER,
					'tags' => [
						[
							'tag' => 'tag',
							'value' => 'value 1'
						],
						[
							'tag' => 'tag',
							'value' => 'value 2'
						]
					]
				],
				'expected_error' => null
			],
			[
				'request_data' => [
					'hostid' => '50009',
					'name' => 'Test mqtt with wrong key and 0 delay',
					'key_' => 'mqt.get[5]',
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_ZABBIX_ACTIVE,
					'delay' => '0'
				],
				'expected_error' => 'Invalid parameter "/1/delay": cannot be equal to zero without custom intervals.'
			]
		], $item_type_tests, $interfaces_tests, $uuid_tests, $binary_valuetype_tests);
	}

	/**
	 * @dataProvider getItemCreateData
	 */
	public function testItem_Create($request_data, $expected_error) {
		$result = $this->call('item.create', $request_data, $expected_error);

		if ($expected_error !== null) {
			return;
		}

		$match_fields = ['uuid', 'hostid', 'name', 'key_', 'type', 'delay'];
		$requests = zbx_toArray($request_data);

		foreach ($requests as $request_data) {
			$id = array_shift($result['result']['itemids']);

			if ($request_data['type'] === ITEM_TYPE_ZABBIX_ACTIVE
					&& substr($request_data['key_'], 0, 8) === 'mqtt.get') {
				$request_data['delay'] = CTestArrayHelper::get($request_data, 'delay', '0');
			}

			if (!array_key_exists('delay', $request_data)) {
				$request_data['delay'] = 0;
			}

			$db_item = CDBHelper::getRow(
				'SELECT '.implode(',', $match_fields).' FROM items WHERE '.dbConditionId('itemid', [$id])
			);

			foreach ($match_fields as $field) {
				if ($field === 'uuid' && !array_key_exists($field, $request_data)) {
					continue;
				}

				$this->assertSame($db_item[$field], strval($request_data[$field]));
			}

			if (array_key_exists('tags', $request_data)) {
				$db_tags = DBFetchArray(DBSelect(
					'SELECT tag,value FROM item_tag WHERE '.dbConditionId('itemid', [$id])
				));
				uasort($request_data['tags'], function ($a, $b) {
					return strnatcasecmp($a['value'], $b['value']);
				});
				uasort($db_tags, function ($a, $b) {
					return strnatcasecmp($a['value'], $b['value']);
				});
				$this->assertEquals(array_values($db_tags), array_values($request_data['tags']), 'Tags should match');
			}
		}
	}

	public static function prepareUpdateData() {
		$interfaces = [
			[
				'type' => 1,
				'main' => 1,
				'useip' => 1,
				'ip' => '127.0.0.1',
				'dns' => '',
				'port' => '10050'
			]
		];

		$groups = [
			[
				'groupid' => 4
			]
		];

		$result = CDataHelper::createHosts([
			[
				'host' => 'testItem_Update',
				'interfaces' => $interfaces,
				'groups' => $groups,
				'status' => HOST_STATUS_MONITORED,
				'items' => [
					[
						'name' => 'Agent ping',
						'key_' => 'agent.ping',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => '1s'
					],
					[
						'name' => 'Agent version',
						'key_' => 'agent.version',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => '1m'
					],
					[
						'name' => 'httpagent.credentials.length',
						'key_' => 'httpagent.credentials.length',
						'type' => ITEM_TYPE_HTTPAGENT,
						'value_type' => ITEM_VALUE_TYPE_TEXT,
						'url' => 'test.com',
						'authtype' => ZBX_HTTP_AUTH_BASIC,
						'delay' => '1m'
					]
				]
			]
		]);

		self::$items = $result['itemids'];
	}

	public static function getItemUpdateData() {
		return [
			// Test update interval for mqtt key of the Agent item type.
			[
				'request_data' => [
					'item' => 'testItem_Update:agent.ping',
					'key_' => 'mqtt.get[00]',
					'delay' => '0'
				],
				'expected_error' => 'Invalid parameter "/1/delay": cannot be equal to zero without custom intervals.'
			],
			// Test update interval for wrong mqtt key of the Active agent item type.
			[
				'request_data' => [
					'item' => 'testItem_Update:agent.ping',
					'key_' => 'mqt.get[11]',
					'type' => ITEM_TYPE_ZABBIX,
					'delay' => '0'
				],
				'expected_error' => 'Invalid parameter "/1/delay": cannot be equal to zero without custom intervals.'
			],
			// Change type to active agent and check update interval for mqtt key.
			[
				'request_data' => [
					'item' => 'testItem_Update:agent.ping',
					'key_' => 'mqtt.get[22]',
					'type' => ITEM_TYPE_ZABBIX_ACTIVE
				],
				'expected_error' => null
			],
			[
				'request_data' => [
					'item' => 'testItem_Update:agent.version',
					'name' => 'Test mqtt key for active agent',
					'key_' => 'mqtt.get[33]',
					'type' => ITEM_TYPE_ZABBIX_ACTIVE
				],
				'expected_error' => null
			],
			'Reject too long Basic authentication username' => [
				'request_data' => [
					'item' => 'testItem_Update:httpagent.credentials.length',
					'username' => str_repeat('z', 256)
				],
				'expected_error' => 'Invalid parameter "/1/username": value is too long.'
			],
			'Reject too long Basic authentication password' => [
				'request_data' => [
					'item' => 'testItem_Update:httpagent.credentials.length',
					'password' => str_repeat('z', 256)
				],
				'expected_error' => 'Invalid parameter "/1/password": value is too long.'
			],
			'Accept longest Basic authentication username' => [
				'request_data' => [
					'item' => 'testItem_Update:httpagent.credentials.length',
					'username' => str_repeat('z', 255)
				],
				'expected_error' => null
			],
			'Accept longest Basic authentication password' => [
				'request_data' => [
					'item' => 'testItem_Update:httpagent.credentials.length',
					'password' => str_repeat('z', 255)
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider getItemUpdateData
	 */
	public function testItem_Update($request_data, $expected_error) {
		$request_data['itemid'] = self::$items[$request_data['item']];
		unset($request_data['item']);

		$result = $this->call('item.update', $request_data, $expected_error);

		if ($expected_error === null) {
			if (array_key_exists('type', $request_data)
					&& $request_data['type'] === ITEM_TYPE_ZABBIX_ACTIVE
					&& substr($request_data['key_'], 0, 8) === 'mqtt.get') {
				$request_data['delay'] = CTestArrayHelper::get($request_data, 'delay', '0');
			}

			$optional_fields = array_flip(['key_', 'type', 'delay']);

			foreach ($result['result']['itemids'] as $id) {
				$optional_updates = array_map('strval', array_intersect_key($request_data, $optional_fields));

				if (!$optional_updates) {
					continue;
				}

				$db_item = CDBHelper::getRow(
					'SELECT '.implode(',', array_keys($optional_updates)).
					' FROM items'.
					' WHERE '.dbConditionId('itemid', [$id])
				);

				ksort($db_item);
				ksort($optional_updates);

				$this->assertSame($db_item, $optional_updates, 'Should match expected update values');
			}
		}
	}

	public static function getItemDeleteData() {
		return [
			[
				'item' => ['400720'],
				'data' => [
					'discovered_triggerids' => ['30002'],
					'dependent_item' => ['400740'],
					'dependent_item_disc_triggerids' => ['30004']
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider getItemDeleteData
	*/
	public function testItem_Delete($item, $data, $expected_error) {
		$result = $this->call('item.delete', $item, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['itemids'] as $id) {
				$dbResult = 'SELECT * FROM items WHERE itemid='.zbx_dbstr($id);
				$this->assertEquals(0, CDBHelper::getCount($dbResult));
			}

			// Check that related discovered triggerid is removed with all related data.
			if (array_key_exists('discovered_triggerids', $data)) {
				foreach ($data['discovered_triggerids'] as $id) {
					$dbResult = 'SELECT * FROM triggers WHERE triggerid='.zbx_dbstr($id);
					$this->assertEquals(0, CDBHelper::getCount($dbResult));

					$dbResult = 'SELECT * FROM functions WHERE triggerid='.zbx_dbstr($id);
					$this->assertEquals(0, CDBHelper::getCount($dbResult));

					$dbResult = 'SELECT * FROM trigger_discovery WHERE triggerid='.zbx_dbstr($id);
					$this->assertEquals(0, CDBHelper::getCount($dbResult));
				}
			}

			// Check that dependent item is removed.
			if (array_key_exists('dependent_item', $data)) {
				foreach ($data['dependent_item'] as $id) {
					$dbResult = 'SELECT * FROM items WHERE itemid='.zbx_dbstr($id);
					$this->assertEquals(0, CDBHelper::getCount($dbResult));
				}
			}

			// Check that discovered trigger of dependent item is removed with all related data.
			if (array_key_exists('dependent_item_disc_triggerids', $data)) {
				foreach ($data['dependent_item_disc_triggerids'] as $id) {
					$dbResult = 'SELECT * FROM triggers WHERE triggerid='.zbx_dbstr($id);
					$this->assertEquals(0, CDBHelper::getCount($dbResult));

					$dbResult = 'SELECT * FROM functions WHERE triggerid='.zbx_dbstr($id);
					$this->assertEquals(0, CDBHelper::getCount($dbResult));

					$dbResult = 'SELECT * FROM trigger_discovery WHERE triggerid='.zbx_dbstr($id);
					$this->assertEquals(0, CDBHelper::getCount($dbResult));
				}
			}
		}
	}
}

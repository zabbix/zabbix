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
					'name' => 'Item of type '.$type,
					'key_' => 'item_of_type_'.$type,
					'hostid' => '50009',
					'type' => (string) $type,
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'delay' => '30s'
				],
				'expected_error' => null
			];
		}

		return [
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
					ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT
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
				'expected_error' => 'Incorrect arguments passed to function.'
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
				'expected_error' => 'Item will not be refreshed. Specified update interval requires having at least one either flexible or scheduling interval.'
			],
			// Test update interval for mqtt key of the Active agent type.
			[
				'request_data' => [
					'hostid' => '50009',
					'name' => 'Test mqtt key for active agent',
					'key_' => 'mqtt.get[3]',
					'interfaceid' => '50022',
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
					'name' => 'Item with some tags',
					'key_' => 'trapper_item_1',
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_TRAPPER,
					'delay' => '0',
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
					'interfaceid' => '50022',
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_ZABBIX_ACTIVE,
					'delay' => '0'
				],
				'expected_error' => 'Item will not be refreshed. Specified update interval requires having at least one either flexible or scheduling interval.'
			],
			// Item preprocessing.
			[
				'request_data' => [
					'hostid' => '50009',
					'name' => 'Test preprocessing 1',
					'key_' => 'mqtt.get[5]',
					'interfaceid' => '50022',
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_ZABBIX_ACTIVE,
					'delay' => '0',
					'preprocessing' => [
						[
							'type' => ZBX_PREPROC_VALIDATE_NOT_SUPPORTED,
							'params' => '',
							'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
							'error_handler_params' => ''
						]
					]
				],
				'expected_error' => 'Incorrect value for field "error_handler": unexpected value "0".'
			],

			'HTTP Agent item without direct interface' => [
				'request_data' => [
					'hostid' => '50009',
					'name' => 'NoInterfaceItem123',
					'key_' => '1234',
					'interfaceid' => 0,
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_HTTPAGENT,
					'delay' => '30s',
					'url' => '192.168.0.1'
				],
				'expected_error' => null
			],
			'Sample/Simple Check item requires interface' => [
				'request_data' => [
					'hostid' => '50009',
					'name' => 'NoInterfaceItem123',
					'key_' => '1234',
					'interfaceid' => 0,
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'type' => ITEM_TYPE_SIMPLE,
					'delay' => '30s',
					'url' => '192.168.0.1'
				],
				'expected_error' => 'No interface found.'
			]
		] + $item_type_tests;
	}

	/**
	 * @dataProvider getItemCreateData
	 */
	public function testItem_Create($request_data, $expected_error) {
		$result = $this->call('item.create', $request_data, $expected_error);

		if ($expected_error === null) {
			if ($request_data['type'] === ITEM_TYPE_ZABBIX_ACTIVE && substr($request_data['key_'], 0, 8) === 'mqtt.get') {
				$request_data['delay'] = CTestArrayHelper::get($request_data, 'delay', '0');
			}

			foreach ($result['result']['itemids'] as $id) {
				$db_item = CDBHelper::getRow('SELECT hostid, name, key_, type, delay FROM items WHERE itemid='.zbx_dbstr($id));

				foreach (['hostid', 'name', 'key_', 'type', 'delay'] as $field) {
					$this->assertSame($db_item[$field], strval($request_data[$field]));
				}

				if (array_key_exists('tags', $request_data)) {
					$db_tags = DBFetchArray(DBSelect('SELECT tag, value FROM item_tag WHERE itemid='.zbx_dbstr($id)));
					uasort($request_data['tags'], function ($a, $b) {
						return strnatcasecmp($a['value'], $b['value']);
					});
					uasort($db_tags, function ($a, $b) {
						return strnatcasecmp($a['value'], $b['value']);
					});
					$this->assertTrue(array_values($db_tags) === array_values($request_data['tags']));
				}
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
				'expected_error' => 'Item will not be refreshed. Specified update interval requires having at least one either flexible or scheduling interval.'
			],
			// Test update interval for wrong mqtt key of the Active agent item type.
			[
				'request_data' => [
					'item' => 'testItem_Update:agent.ping',
					'key_' => 'mqt.get[11]',
					'type' => ITEM_TYPE_ZABBIX,
					'delay' => '0'
				],
				'expected_error' => 'Item will not be refreshed. Specified update interval requires having at least one either flexible or scheduling interval.'
			],
			// Change type to active agent and check update interval for mqtt key.
			[
				'request_data' => [
					'item' => 'testItem_Update:agent.ping',
					'key_' => 'mqtt.get[22]',
					'type' => ITEM_TYPE_ZABBIX_ACTIVE,
					'delay' => '0'
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
			if ($request_data['type'] === ITEM_TYPE_ZABBIX_ACTIVE && substr($request_data['key_'], 0, 8) === 'mqtt.get') {
				$request_data['delay'] = CTestArrayHelper::get($request_data, 'delay', '0');
			}

			foreach ($result['result']['itemids'] as $id) {
				$db_item = CDBHelper::getRow('SELECT key_, type, delay FROM items WHERE itemid='.zbx_dbstr($id));

				foreach (['key_', 'type', 'delay'] as $field) {
					$this->assertSame($db_item[$field], strval($request_data[$field]));
				}
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

			// Check that related discovered trigerid is removed with all related data.
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

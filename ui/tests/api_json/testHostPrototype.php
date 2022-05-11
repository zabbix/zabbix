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

/**
 * @backup hosts
 */
class testHostPrototype extends CAPITest {

	private $interface_update_hostid = null;

	public static function hostprototype_create_data() {
		return [
			'simplest create' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 1'
					]
				],
				'expected_error' => null
			],
			'empty interfaces' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 2',
						'interfaces' => []
					]
				],
				'expected_error' => null
			],
			'inherit interfaces with empty interfaces' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 3',
						'custom_interfaces' => HOST_PROT_INTERFACES_INHERIT,
						'interfaces' => []
					]
				],
				'expected_error' => null
			],
			'custom interfaces with empty interfaces' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 4',
						'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
						'interfaces' => []
					]
				],
				'expected_error' => null
			],
			'custom interfaces with valid interface' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 5',
						'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
						'interfaces' => [
							[
								'type' => INTERFACE_TYPE_AGENT,
								'useip' => INTERFACE_USE_IP,
								'ip' => '192.168.1.1',
								'port' => '1234',
								'main' => 1
							]
						]
					]
				],
				'expected_error' => null
			],
			'non-snmp interface with empty details' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 6',
						'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
						'interfaces' => [
							[
								'type' => INTERFACE_TYPE_AGENT,
								'useip' => INTERFACE_USE_IP,
								'ip' => '127.0.0.0',
								'port' => '1234',
								'main' => 1,
								'details' => []
							]
						]
					]
				],
				'expected_error' => null
			],
			'multiple interfaces single main' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 7',
						'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
						'interfaces' => [
							[
								'type' => INTERFACE_TYPE_AGENT,
								'useip' => INTERFACE_USE_IP,
								'ip' => '127.0.0.0',
								'port' => '1234',
								'main' => 1
							],
							[
								'type' => INTERFACE_TYPE_AGENT,
								'useip' => INTERFACE_USE_IP,
								'ip' => '127.0.0.1',
								'port' => '1234',
								'main' => 0
							]
						]
					]
				],
				'expected_error' => null
			],
			'missing ruleid' => [
				'request' => [
					[
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 8'
					]
				],
				'expected_error' => "Invalid parameter \"/1\": the parameter \"ruleid\" is missing."
			],
			'invalid groupLinks format' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => ['50014'],
						'host' => 'new {#HOST} 9'
					]
				],
				'expected_error' => "Invalid parameter \"/1/groupLinks/1\": an array is expected."
			],
			'invalid interfaces format' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 10',
						'interfaces' => ''
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces\": an array is expected."
			],
			'inherit interfaces with filled interfaces' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 11',
						'custom_interfaces' => HOST_PROT_INTERFACES_INHERIT,
						'interfaces' => [
							[
								'type' => INTERFACE_TYPE_AGENT,
								'useip' => INTERFACE_USE_IP,
								'ip' => '192.168.1.1',
								'port' => '1234',
								'main' => 1
							]
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces\": should be empty."
			],
			'interfaces without type' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 12',
						'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
						'interfaces' => [
							[]
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1\": the parameter \"type\" is missing."
			],
			'interfaces without useip' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 13',
						'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
						'interfaces' => [
							[
								'type' => INTERFACE_TYPE_AGENT
							]
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1\": the parameter \"useip\" is missing."
			],
			'interfaces without port' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 14',
						'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
						'interfaces' => [
							[
								'type' => INTERFACE_TYPE_AGENT,
								'useip' => INTERFACE_USE_IP
							]
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1\": the parameter \"port\" is missing."
			],
			'interfaces without main' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 15',
						'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
						'interfaces' => [
							[
								'type' => INTERFACE_TYPE_AGENT,
								'useip' => INTERFACE_USE_IP,
								'port' => '1234'
							]
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1\": the parameter \"main\" is missing."
			],
			'interfaces without ip' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 16',
						'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
						'interfaces' => [
							[
								'type' => INTERFACE_TYPE_AGENT,
								'useip' => INTERFACE_USE_IP,
								'port' => '1234',
								'main' => 1
							]
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1\": the parameter \"ip\" is missing."
			],
			'interfaces with empty ip' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 17',
						'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
						'interfaces' => [
							[
								'type' => INTERFACE_TYPE_AGENT,
								'useip' => INTERFACE_USE_IP,
								'ip' => '',
								'port' => '1234',
								'main' => 1
							]
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1/ip\": cannot be empty."
			],
			'interfaces without dns' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 18',
						'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
						'interfaces' => [
							[
								'type' => INTERFACE_TYPE_AGENT,
								'useip' => INTERFACE_USE_DNS,
								'port' => '1234',
								'main' => 1
							]
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1\": the parameter \"dns\" is missing."
			],
			'interfaces with empty dns' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 19',
						'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
						'interfaces' => [
							[
								'type' => INTERFACE_TYPE_AGENT,
								'useip' => INTERFACE_USE_DNS,
								'dns' => '',
								'port' => '1234',
								'main' => 1
							]
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1/dns\": cannot be empty."
			],
			'snmp interface without details' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 20',
						'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
						'interfaces' => [
							[
								'type' => INTERFACE_TYPE_SNMP,
								'useip' => INTERFACE_USE_IP,
								'ip' => '127.0.0.0',
								'port' => '1234',
								'main' => 1
							]
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1\": the parameter \"details\" is missing."
			],
			'snmp interface with empty details' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 21',
						'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
						'interfaces' => [
							[
								'type' => INTERFACE_TYPE_SNMP,
								'useip' => INTERFACE_USE_IP,
								'ip' => '127.0.0.0',
								'port' => '1234',
								'main' => 1,
								'details' => []
							]
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1/details\": the parameter \"version\" is missing."
			],
			'snmp v1 interface without community' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 22',
						'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
						'interfaces' => [
							[
								'type' => INTERFACE_TYPE_SNMP,
								'useip' => INTERFACE_USE_IP,
								'ip' => '127.0.0.0',
								'port' => '1234',
								'main' => 1,
								'details' => [
									'version' => SNMP_V1
								]
							]
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1/details\": the parameter \"community\" is missing."
			],
			'snmp v1 interface with empty community' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 23',
						'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
						'interfaces' => [
							[
								'type' => INTERFACE_TYPE_SNMP,
								'useip' => INTERFACE_USE_IP,
								'ip' => '127.0.0.0',
								'port' => '1234',
								'main' => 1,
								'details' => [
									'version' => SNMP_V1,
									'community' => ''
								]
							]
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1/details/community\": cannot be empty."
			],
			'non-snmp interface with filled details' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 24',
						'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
						'interfaces' => [
							[
								'type' => INTERFACE_TYPE_AGENT,
								'useip' => INTERFACE_USE_IP,
								'ip' => '127.0.0.0',
								'port' => '1234',
								'main' => 1,
								'details' => [
									'version' => SNMP_V1
								]
							]
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1/details\": unexpected parameter \"version\"."
			],
			'multiple interfaces, all main' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 25',
						'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
						'interfaces' => [
							[
								'type' => INTERFACE_TYPE_JMX,
								'useip' => INTERFACE_USE_IP,
								'ip' => '127.0.0.0',
								'port' => '1234',
								'main' => 1
							],
							[
								'type' => INTERFACE_TYPE_JMX,
								'useip' => INTERFACE_USE_IP,
								'ip' => '127.0.0.1',
								'port' => '1234',
								'main' => 1
							]
						]
					]
				],
				'expected_error' => "Host prototype cannot have more than one default interface of the same type."
			],
			'multiple interfaces no main' => [
				'request' => [
					[
						'ruleid' => 40066,
						'groupLinks' => [[
							'groupid' => 50014
						]],
						'host' => 'new {#HOST} 26',
						'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
						'interfaces' => [
							[
								'type' => INTERFACE_TYPE_JMX,
								'useip' => INTERFACE_USE_IP,
								'ip' => '127.0.0.0',
								'port' => '1234',
								'main' => 0
							],
							[
								'type' => INTERFACE_TYPE_JMX,
								'useip' => INTERFACE_USE_IP,
								'ip' => '127.0.0.1',
								'port' => '1234',
								'main' => 0
							]
						]
					]
				],
				'expected_error' => "No default interface for \"JMX\" type on \"new {#HOST} 26\"."
			]
		];
	}

	/**
	 * @dataProvider hostprototype_create_data
	 */
	public function testHostPrototype_Create($request, $expected_error) {
		$result = $this->call('hostprototype.create', $request, $expected_error);

		if ($expected_error === null) {
			$hostprototype_keys = [
				'host' => null,
				'custom_interfaces' => HOST_PROT_INTERFACES_INHERIT
			];

			foreach ($result['result']['hostids'] as $key => $id) {
				$db_result = DBSelect('SELECT * FROM hosts WHERE hostid='.zbx_dbstr($id));
				$db_row = DBFetch($db_result);

				foreach ($hostprototype_keys as $key_to_check => $value) {
					if (array_key_exists($key_to_check, $request[$key])) {
						$value = $request[$key][$key_to_check];
					}

					$this->assertEquals($value, $db_row[$key_to_check],
						'Failed check for hostprototype field "' . $key_to_check . '"'
					);
				}

				$db_result = DBSelect('SELECT parent_itemid FROM host_discovery WHERE hostid='.zbx_dbstr($id));
				$db_row = DBFetch($db_result);

				$this->assertEquals($request[$key]['ruleid'], $db_row['parent_itemid'],
					'Failed check for link with discovery rule'
				);

				$db_result = DBSelect('SELECT interfaceid, type FROM interface WHERE hostid='.zbx_dbstr($id));
				$db_interfaces = DBfetchArray($db_result);
				$expected_interface_count = array_key_exists('interfaces', $request[$key])
					? count($request[$key]['interfaces'])
					: 0;

				$this->assertCount($expected_interface_count, $db_interfaces,
					'Incorrect number of interfaces in database.'
				);

				foreach ($db_interfaces as $interface) {
					$this->assertEquals(
						($interface['type'] == INTERFACE_TYPE_SNMP) ? 1 : 0,
						CDBHelper::getCount(
							'SELECT interfaceid'.
							' FROM interface_snmp'.
							' WHERE interfaceid='.zbx_dbstr($interface['interfaceid'])
						),
						'Incorrect number of SNMP defails in database.'
					);
				}
			}
		}
	}

	public static function hostprototype_update_interfaces_data() {
		return [
			'no changes' => [
				'create_interfaces' => [
					'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_JMX,
							'useip' => INTERFACE_USE_IP,
							'ip' => '127.0.0.0',
							'port' => '1234',
							'main' => 1
						]
					]
				],
				'update_interfaces' => [
					'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_JMX,
							'useip' => INTERFACE_USE_IP,
							'ip' => '127.0.0.0',
							'port' => '1234',
							'main' => 1
						]
					]
				],
				'expected_error' => null
			],
			'less interfaces' => [
				'create_interfaces' => [
					'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_JMX,
							'useip' => INTERFACE_USE_IP,
							'ip' => '127.0.0.0',
							'port' => '1234',
							'main' => 1
						],
						[
							'type' => INTERFACE_TYPE_JMX,
							'useip' => INTERFACE_USE_IP,
							'ip' => '127.0.0.1',
							'port' => '1234',
							'main' => 0
						]
					]
				],
				'update_interfaces' => [
					'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_JMX,
							'useip' => INTERFACE_USE_IP,
							'ip' => '127.0.0.0',
							'port' => '1234',
							'main' => 1
						]
					]
				],
				'expected_error' => null
			],
			'more interfaces' => [
				'create_interfaces' => [
					'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_JMX,
							'useip' => INTERFACE_USE_IP,
							'ip' => '127.0.0.0',
							'port' => '1234',
							'main' => 1
						]
					]
				],
				'update_interfaces' => [
					'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_JMX,
							'useip' => INTERFACE_USE_IP,
							'ip' => '127.0.0.0',
							'port' => '1234',
							'main' => 1
						],
						[
							'type' => INTERFACE_TYPE_JMX,
							'useip' => INTERFACE_USE_IP,
							'ip' => '127.0.0.1',
							'port' => '1234',
							'main' => 0
						]
					]
				],
				'expected_error' => null
			],
			'update in same interfaces' => [
				'create_interfaces' => [
					'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_JMX,
							'useip' => INTERFACE_USE_IP,
							'ip' => '127.0.0.0',
							'port' => '1234',
							'main' => 1
						],
						[
							'type' => INTERFACE_TYPE_SNMP,
							'useip' => INTERFACE_USE_IP,
							'ip' => '127.0.0.1',
							'port' => '1234',
							'main' => 1,
							'details' => [
								'version' => SNMP_V1,
								'community' => 'community'
							]
						]
					]
				],
				'update_interfaces' => [
					'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_JMX,
							'useip' => INTERFACE_USE_IP,
							'ip' => '127.0.0.3',
							'port' => '1234',
							'main' => 1
						],
						[
							'type' => INTERFACE_TYPE_SNMP,
							'useip' => INTERFACE_USE_IP,
							'ip' => '127.0.0.1',
							'port' => '1234',
							'main' => 1,
							'details' => [
								'version' => SNMP_V2C,
								'community' => 'community'
							]
						]
					]
				],
				'expected_error' => null
			],
			'change to inherit; delete snmp details' => [
				'create_interfaces' => [
					'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_JMX,
							'useip' => INTERFACE_USE_IP,
							'ip' => '127.0.0.0',
							'port' => '1234',
							'main' => 1
						],
						[
							'type' => INTERFACE_TYPE_SNMP,
							'useip' => INTERFACE_USE_IP,
							'ip' => '127.0.0.1',
							'port' => '1234',
							'main' => 1,
							'details' => [
								'version' => SNMP_V1,
								'community' => 'community'
							]
						]
					]
				],
				'update_interfaces' => [
					'custom_interfaces' => HOST_PROT_INTERFACES_INHERIT,
					'interfaces' => []
				],
				'expected_error' => null
			]
		];
	}

	public function after_update_interfaces() {
		if ($this->interface_update_hostid !== null) {
			$this->call('hostprototype.delete', [$this->interface_update_hostid]);
			$this->interface_update_hostid = null;
		}
	}

	/**
	 * @dataProvider hostprototype_update_interfaces_data
	 * @onAfter after_update_interfaces
	 */
	public function testHostPrototype_Update_Interfaces($create_interfaces, $update_interfaces, $expected_error) {
		$host_prototype = $this->call('hostprototype.create', [
			'ruleid' => 40066,
			'groupLinks' => [[
				'groupid' => 50014
			]],
			'host' => 'update_interfaces {#HOST} 1',
			'custom_interfaces' => $create_interfaces['custom_interfaces'],
			'interfaces' => $create_interfaces['interfaces']
		]);
		$this->interface_update_hostid = reset($host_prototype['result']['hostids']);

		$old_interfaces = $this->call('hostinterface.get', [
			'output' => ['interfaceid'],
			'hostids' => $this->interface_update_hostid
		]);
		$old_interfaces = $old_interfaces['result'];

		$update = [
			'hostid' => $this->interface_update_hostid
		];

		if ($update_interfaces['custom_interfaces'] !== null) {
			$update['custom_interfaces'] = $update_interfaces['custom_interfaces'];
		}

		if ($update_interfaces['interfaces'] !== null) {
			$update['interfaces'] = $update_interfaces['interfaces'];
		}

		$this->call('hostprototype.update', $update, $expected_error);

		$new_interfaces = $this->call('hostinterface.get', [
			'output' => ['interfaceid', 'type'],
			'hostids' => $this->interface_update_hostid
		]);
		$new_interfaces = $new_interfaces['result'];

		$expected_old_interface_count = ($create_interfaces['custom_interfaces'] === HOST_PROT_INTERFACES_CUSTOM)
			? count($create_interfaces['interfaces'])
			: 0;
		$expected_new_interface_count = ($update_interfaces['custom_interfaces'] === HOST_PROT_INTERFACES_CUSTOM)
			? count($update_interfaces['interfaces'])
			: 0;

		$this->assertCount($expected_new_interface_count, $new_interfaces, 'Incorrect number of updated interfaces.');

		$old_interfaceids = array_column($old_interfaces, 'interfaceid');
		$new_interfaceids = array_column($new_interfaces, 'interfaceid');

		$this->assertEquals(
			min($expected_old_interface_count, $expected_new_interface_count),
			count(array_intersect($old_interfaceids, $new_interfaceids)),
			'Interfaceid\'s not preserved, when updating interfaces.'
		);

		foreach ($new_interfaces as $interface) {
			$this->assertEquals(
				($interface['type'] == INTERFACE_TYPE_SNMP) ? 1 : 0,
				CDBHelper::getCount(
					'SELECT interfaceid'.
					' FROM interface_snmp'.
					' WHERE interfaceid='.zbx_dbstr($interface['interfaceid'])
				),
				'Incorrect number of SNMP defails in database.'
			);
		}
	}

	public static function hostprototype_delete_data() {
		return [
			'Test successful delete of host prototype' => [
				'host_prototype' => [
					'50015'
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider hostprototype_delete_data
	 */
	public function testHostPrototype_Delete($host_prototype, $expected_error) {
		if ($host_prototype) {
			$db_interfaces = DBSelect(
				'SELECT interfaceid'.
				' FROM interface'.
				' WHERE hostid IN ('.zbx_dbstr(implode(',', $host_prototype)).')'
			);
			$db_interfaceids = DBfetchColumn($db_interfaces, 'interfaceid');
		}

		$result = $this->call('hostprototype.delete', $host_prototype, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['hostids'] as $id) {
				$this->assertEquals(0, CDBHelper::getCount(
					'SELECT hostid FROM hosts WHERE hostid='.zbx_dbstr($id)
				));

				$this->assertEquals(0, CDBHelper::getCount(
					'SELECT hostid'.
					' FROM host_discovery'.
					' WHERE hostid='.zbx_dbstr($id)
				));

				$this->assertEquals(0, CDBHelper::getCount(
					'SELECT hostid'.
					' FROM group_prototype'.
					' WHERE hostid='.zbx_dbstr($id)
				));

				$this->assertEquals(0, CDBHelper::getCount(
					'SELECT hostid'.
					' FROM interface'.
					' WHERE hostid='.zbx_dbstr($id)
				));

				if ($db_interfaceids) {
					$this->assertEquals(0, CDBHelper::getCount(
						'SELECT interfaceid'.
						' FROM interface_snmp'.
						' WHERE interfaceid IN ('.zbx_dbstr(implode(',', $db_interfaceids)).')'
					));
				}
			}
		}
	}
}

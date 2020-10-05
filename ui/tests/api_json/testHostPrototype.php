<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

	public static function hostprototype_create() {
		return [
			[
				'request' => [
					'ruleid' => 40066,
					'groupLinks' => [[
						'groupid' => 50014
					]],
					'host' => 'new {#HOST} 1'
				],
				'expected_error' => null
			],
			[
				'request' => [
					'groupLinks' => [[
						'groupid' => 50014
					]],
					'host' => 'new {#HOST} 2'
				],
				'expected_error' => "Invalid parameter \"/1\": the parameter \"ruleid\" is missing."
			],
			[
				'request' => [
					'ruleid' => 40066,
					'groupLinks' => ['50014'],
					'host' => 'new {#HOST} 3'
				],
				'expected_error' => "Invalid parameter \"/1/groupLinks/1\": an array is expected."
			],
			[
				'request' => [
					'ruleid' => 40066,
					'groupLinks' => [[
						'groupid' => 50014
					]],
					'host' => 'new {#HOST} 4',
					'interfaces' => []
				],
				'expected_error' => null
			],
			[
				'request' => [
					'ruleid' => 40066,
					'groupLinks' => [[
						'groupid' => 50014
					]],
					'host' => 'new {#HOST} 5',
					'interfaces' => ''
				],
				'expected_error' => "Invalid parameter \"/1/interfaces\": an array is expected."
			],
			[
				'request' => [
					'ruleid' => 40066,
					'groupLinks' => [[
						'groupid' => 50014
					]],
					'host' => 'new {#HOST} 6',
					'custom_interfaces' => HOST_PROT_INTERFACES_INHERIT,
					'interfaces' => []
				],
				'expected_error' => null
			],
			[
				'request' => [
					'ruleid' => 40066,
					'groupLinks' => [[
						'groupid' => 50014
					]],
					'host' => 'new {#HOST} 7',
					'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
					'interfaces' => []
				],
				'expected_error' => null
			],
			[
				'request' => [
					'ruleid' => 40066,
					'groupLinks' => [[
						'groupid' => 50014
					]],
					'host' => 'new {#HOST} 8',
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
				],
				'expected_error' => "Invalid parameter \"/1/interfaces\": should be empty."
			],
			[
				'request' => [
					'ruleid' => 40066,
					'groupLinks' => [[
						'groupid' => 50014
					]],
					'host' => 'new {#HOST} 9',
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
				],
				'expected_error' => null
			],
			[
				'request' => [
					'ruleid' => 40066,
					'groupLinks' => [[
						'groupid' => 50014
					]],
					'host' => 'new {#HOST} 10',
					'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
					'interfaces' => [
						[]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1\": the parameter \"type\" is missing."
			],
			[
				'request' => [
					'ruleid' => 40066,
					'groupLinks' => [[
						'groupid' => 50014
					]],
					'host' => 'new {#HOST} 11',
					'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_AGENT
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1\": the parameter \"useip\" is missing."
			],
			[
				'request' => [
					'ruleid' => 40066,
					'groupLinks' => [[
						'groupid' => 50014
					]],
					'host' => 'new {#HOST} 12',
					'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_AGENT,
							'useip' => INTERFACE_USE_IP
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1\": the parameter \"port\" is missing."
			],
			[
				'request' => [
					'ruleid' => 40066,
					'groupLinks' => [[
						'groupid' => 50014
					]],
					'host' => 'new {#HOST} 13',
					'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_AGENT,
							'useip' => INTERFACE_USE_IP,
							'port' => '1234'
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1\": the parameter \"main\" is missing."
			],
			[
				'request' => [
					'ruleid' => 40066,
					'groupLinks' => [[
						'groupid' => 50014
					]],
					'host' => 'new {#HOST} 14',
					'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_AGENT,
							'useip' => INTERFACE_USE_IP,
							'port' => '1234',
							'main' => 1
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1\": the parameter \"ip\" is missing."
			],
			[
				'request' => [
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
							'ip' => '',
							'port' => '1234',
							'main' => 1
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1/ip\": cannot be empty."
			],
			[
				'request' => [
					'ruleid' => 40066,
					'groupLinks' => [[
						'groupid' => 50014
					]],
					'host' => 'new {#HOST} 16',
					'custom_interfaces' => HOST_PROT_INTERFACES_CUSTOM,
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_AGENT,
							'useip' => INTERFACE_USE_DNS,
							'port' => '1234',
							'main' => 1
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1\": the parameter \"dns\" is missing."
			],
			[
				'request' => [
					'ruleid' => 40066,
					'groupLinks' => [[
						'groupid' => 50014
					]],
					'host' => 'new {#HOST} 17',
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
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1/dns\": cannot be empty."
			],
			[
				'request' => [
					'ruleid' => 40066,
					'groupLinks' => [[
						'groupid' => 50014
					]],
					'host' => 'new {#HOST} 18',
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
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1\": the parameter \"details\" is missing."
			],
			[
				'request' => [
					'ruleid' => 40066,
					'groupLinks' => [[
						'groupid' => 50014
					]],
					'host' => 'new {#HOST} 19',
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
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1/details\": the parameter \"version\" is missing."
			],
			[
				'request' => [
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
							'main' => 1,
							'details' => [
								'version' => SNMP_V1
							]
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1/details\": the parameter \"community\" is missing."
			],
			[
				'request' => [
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
							'details' => [
								'version' => SNMP_V1,
								'community' => ''
							]
						]
					]
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1/details/community\": cannot be empty."
			],
			[
				'request' => [
					'ruleid' => 40066,
					'groupLinks' => [[
						'groupid' => 50014
					]],
					'host' => 'new {#HOST} 22',
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
				],
				'expected_error' => "Invalid parameter \"/1/interfaces/1\": unexpected parameter \"details\"."
			],
			[
				'request' => [
					'ruleid' => 40066,
					'groupLinks' => [[
						'groupid' => 50014
					]],
					'host' => 'new {#HOST} 23',
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
				],
				'expected_error' => null
			],
			[
				'request' => [
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
				],
				'expected_error' => null
			],
			[
				'request' => [
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
				],
				'expected_error' => "Host prototype cannot have more than one default interface of the same type."
			],
			[
				'request' => [
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
				],
				'expected_error' => "No default interface for \"JMX\" type on \"new {#HOST} 25\"."
			],
		];
	}

	/**
	 * @dataProvider hostprototype_create
	 */
	public function testHostPrototype_Create($request, $expected_error) {
		$this->call('hostprototype.create', $request, $expected_error);
	}
}

<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class C10ImportConverterTest extends CImportConverterTest {

	public function testTemplateSeparation() {
		$this->assertConvert(
			$this->createExpectedResult([]),
			$this->createSource()
		);
		$this->assertConvert(
			$this->createExpectedResult(['hosts' => '']),
			$this->createSource(['hosts' => ''])
		);

		$source = $this->createSource([
			'hosts' => [
				[
					'name' => 'Template 1',
				],
				[
					'name' => 'Template 2',
					'status' => HOST_STATUS_TEMPLATE
				],
				[
					'name' => 'Host 1',
					'status' => HOST_STATUS_NOT_MONITORED
				],
				[
					'name' => 'Host 2',
					'status' => HOST_STATUS_MONITORED
				],
			],
		]);

		$expectedResult = $this->createExpectedResult([
			'hosts' => [
				[
					'host' => 'Host 1',
					'status' => HOST_STATUS_NOT_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					]
				],
				[
					'host' => 'Host 2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					]
				],
			],
			'templates' => [
				[
					'template' => 'Template 1',
				],
				[
					'template' => 'Template 2'
				],
			]
		]);

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertGroups() {
		$source = $this->createSource([
			'hosts' => [
				[
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED
				],
				[
					'name' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'groups' => [
						'Zabbix server',
						'Linux server',
					],
				],
				[
					'name' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'groups' => [
						'Zabbix server',
						'My group',
					],
				],
				[
					'name' => 'template',
					'status' => HOST_STATUS_TEMPLATE,
					'groups' => [
						'Templates',
					],
				],
			],
		]);

		$result = $this->createExpectedResult([
			'groups' => [
				[
					'name' => 'Zabbix server'
				],
				[
					'name' => 'Linux server'
				],
				[
					'name' => 'My group'
				],
				[
					'name' => 'Templates'
				],
			],
			'hosts' => [
				[
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					]
				],
				[
					'host' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
					'groups' => [
						[
							'name' => 'Zabbix server'
						],
						[
							'name' => 'Linux server'
						],
					],
				],
				[
					'host' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
					'groups' => [
						[
							'name' => 'Zabbix server'
						],
						[
							'name' => 'My group'
						],
					],
				],
			],
			'templates' => [
				[
					'template' => 'template',
					'groups' => [
						[
							'name' => 'Templates'
						]
					]
				]
			]
		]);

		$this->assertConvert($result, $source);
	}

	public function testConvertHosts() {
		$this->assertConvert(
			$this->createExpectedResult([]),
			$this->createSource()
		);
		$this->assertConvert(
			$this->createExpectedResult(['hosts' => '']),
			$this->createSource(['hosts' => ''])
		);

		$source = $this->createSource([
			'hosts' => [
				[
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
				],
				[
					'name' => 'host2',
					'status' => HOST_STATUS_MONITORED,
				],
			]
		]);
		$result = $this->createExpectedResult([
			'hosts' => [
				[
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					]
				],
				[
					'host' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					]
				],
			]
		]);
		$this->assertConvert($result, $source);
	}

	public function testConvertHostInterfaces() {
		$source = $this->createSource([
			'hosts' => [
				[
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
				],
				// host with an agent interface
				[
					'name' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => 'http://zabbix.com',
					'port' => '123',
					'items' => [
						[
							'key' => 'item1',
							'type' => ITEM_TYPE_ZABBIX,
						],
						[
							'key' => 'item2',
							'type' => ITEM_TYPE_SIMPLE,
						]
					]
				],
				// missing interface data
				[
					'name' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'useip' => 1,
					'ip' => '127.0.0.1',
				],
				// host with an IPMI interface
				[
					'name' => 'host4',
					'status' => HOST_STATUS_MONITORED,
					'ip' => '127.0.0.1',
					'ipmi_ip' => '127.0.0.2',
					'ipmi_port' => '123',
					'items' => [
						// an IPMI item to test
						[
							'key' => 'item1',
							'type' => ITEM_TYPE_IPMI,
						]
					]
				],
				// host with an IPMI interface, fallback to "ip"
				[
					'name' => 'host5',
					'status' => HOST_STATUS_MONITORED,
					'ip' => '127.0.0.1',
					'ipmi_port' => '123',
					'items' => [
						// an IPMI item to test
						[
							'key' => 'item1',
							'type' => ITEM_TYPE_IPMI,
						]
					]
				],
				// host with SNMP interfaces
				[
					'name' => 'host6',
					'status' => HOST_STATUS_MONITORED,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => 'http://zabbix.com',
					'items' => [
						[
							'key' => 'item1',
							'type' => ITEM_TYPE_SNMPV1,
							'snmp_port' => '1'
						],
						[
							'key' => 'item2',
							'type' => ITEM_TYPE_SNMPV2C,
							'snmp_port' => '2'
						],
						[
							'key' => 'item3',
							'type' => ITEM_TYPE_SNMPV3,
							'snmp_port' => '3'
						],
						[
							'key' => 'item4',
							'type' => ITEM_TYPE_SNMPV1,
							'snmp_port' => '1'
						],
						[
							'key' => 'item5',
							'type' => ITEM_TYPE_SNMPV3,
						],
					]
				],
				// missing item type
				[
					'name' => 'host7',
					'status' => HOST_STATUS_MONITORED,
					'items' => [
						[
							'key' => 'item1',
						]
					]
				]
			]
		]);
		$expectedResult = $this->createExpectedResult([
			'hosts' => [
				[
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					]
				],
				// host with an agent interface
				[
					'host' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_AGENT,
							'useip' => 1,
							'ip' => '127.0.0.1',
							'dns' => 'http://zabbix.com',
							'port' => '123',
							'default' => INTERFACE_PRIMARY,
							'interface_ref' => 'if0'
						]
					],
					'items' => [
						[
							'key' => 'item1',
							'type' => ITEM_TYPE_ZABBIX,
							'interface_ref' => 'if0'
						],
						[
							'key' => 'item2',
							'type' => ITEM_TYPE_SIMPLE,
							'interface_ref' => 'if0'
						]
					]
				],
				// missing interface data
				[
					'host' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
				],
				// host with an IPMI interface
				[
					'host' => 'host4',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_IPMI,
							'useip' => 1,
							'ip' => '127.0.0.2',
							'dns' => '',
							'port' => '123',
							'default' => INTERFACE_PRIMARY,
							'interface_ref' => 'if0'
						],
					],
					'items' => [
						[
							'key' => 'item1',
							'type' => ITEM_TYPE_IPMI,
							'interface_ref' => 'if0'
						]
					]
				],
				// host with an IPMI interface, fallback to "ip"
				[
					'host' => 'host5',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_IPMI,
							'useip' => 1,
							'ip' => '127.0.0.1',
							'dns' => '',
							'port' => '123',
							'default' => INTERFACE_PRIMARY,
							'interface_ref' => 'if0'
						],
					],
					'items' => [
						[
							'key' => 'item1',
							'type' => ITEM_TYPE_IPMI,
							'interface_ref' => 'if0'
						]
					]
				],
				// host with SNMP interfaces
				[
					'host' => 'host6',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_SNMP,
							'useip' => 1,
							'ip' => '127.0.0.1',
							'dns' => 'http://zabbix.com',
							'port' => '1',
							'default' => INTERFACE_PRIMARY,
							'interface_ref' => 'if0'
						],
						[
							'type' => INTERFACE_TYPE_SNMP,
							'useip' => 1,
							'ip' => '127.0.0.1',
							'dns' => 'http://zabbix.com',
							'port' => '2',
							'default' => INTERFACE_SECONDARY,
							'interface_ref' => 'if1'
						],
						[
							'type' => INTERFACE_TYPE_SNMP,
							'useip' => 1,
							'ip' => '127.0.0.1',
							'dns' => 'http://zabbix.com',
							'port' => '3',
							'default' => INTERFACE_SECONDARY,
							'interface_ref' => 'if2'
						],
					],
					'items' => [
						[
							'key' => 'item1',
							'type' => ITEM_TYPE_SNMPV1,
							'snmp_port' => '1',
							'interface_ref' => 'if0'
						],
						[
							'key' => 'item2',
							'type' => ITEM_TYPE_SNMPV2C,
							'snmp_port' => '2',
							'interface_ref' => 'if1'
						],
						[
							'key' => 'item3',
							'type' => ITEM_TYPE_SNMPV3,
							'snmp_port' => '3',
							'interface_ref' => 'if2'
						],
						[
							'key' => 'item4',
							'type' => ITEM_TYPE_SNMPV1,
							'snmp_port' => '1',
							'interface_ref' => 'if0'
						],
						[
							'key' => 'item5',
							'type' => ITEM_TYPE_SNMPV3,
						],
					]
				],
				// missing item type
				[
					'host' => 'host7',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
					'items' => [
						[
							'key' => 'item1',
						]
					]
				]
			]
		]);

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertHostProfiles() {
		$source = $this->createSource([
			'hosts' => [
				[
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
				],
				[
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'host_profile' => [],
					'host_profiles_ext' => [],
				],
				[
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'host_profile' => [
						'devicetype' => 'device type',
						'name' => 'name',
						'os' => 'os',
						'serialno' => 'serial no',
						'tag' => 'tag',
						'macaddress' => 'mac address',
						'hardware' => 'hardware',
						'software' => 'software',
						'contact' => 'contact',
						'location' => 'location',
						'notes' => 'notes',
					],
					'host_profiles_ext' => [
						'device_alias' => 'device alias',
						'device_type' => 'device type',
						'device_chassis' => 'device chassis',
						'device_os' => 'device os',
						'device_os_short' => 'device os short',
						'device_hw_arch' => 'device hw arch',
						'device_serial' => 'device serial',
						'device_model' => 'device model',
						'device_tag' => 'device tag',
						'device_vendor' => 'device vendor',
						'device_contract' => 'device contract',
						'device_who' => 'device who',
						'device_status' => 'device status',
						'device_app_01' => 'device app 01',
						'device_app_02' => 'device app 02',
						'device_app_03' => 'device app 03',
						'device_app_04' => 'device app 04',
						'device_app_05' => 'device app 05',
						'device_url_1' => 'device url 1',
						'device_url_2' => 'device url 2',
						'device_url_3' => 'device url 3',
						'device_networks' => 'device networks',
						'device_notes' => 'device notes',
						'device_hardware' => 'device hardware',
						'device_software' => 'device software',
						'ip_subnet_mask' => 'ip subnet mask',
						'ip_router' => 'ip router',
						'ip_macaddress' => 'ip macaddress',
						'oob_ip' => 'oob ip',
						'oob_subnet_mask' => 'oob subnet mask',
						'oob_router' => 'oob router',
						'date_hw_buy' => 'date hw buy',
						'date_hw_install' => 'date hw install',
						'date_hw_expiry' => 'date hw expiry',
						'date_hw_decomm' => 'date hw decomm',
						'site_street_1' => 'site street 1',
						'site_street_2' => 'site street 2',
						'site_street_3' => 'site street 3',
						'site_city' => 'site city',
						'site_state' => 'site state',
						'site_country' => 'site country',
						'site_zip' => 'site zip',
						'site_rack' => 'site rack',
						'site_notes' => 'site notes',
						'poc_1_name' => 'poc 1 name',
						'poc_1_email' => 'poc 1 email',
						'poc_1_phone_1' => 'poc 1 phone 1',
						'poc_1_phone_2' => 'poc 1 phone 2',
						'poc_1_cell' => 'poc 1 cell',
						'poc_1_screen' => 'poc 1 screen',
						'poc_1_notes' => 'poc 1 notes',
						'poc_2_name' => 'poc 2 name',
						'poc_2_email' => 'poc 2 email',
						'poc_2_phone_1' => 'poc 2 phone 1',
						'poc_2_phone_2' => 'poc 2 phone 2',
						'poc_2_cell' => 'poc 2 cell',
						'poc_2_screen' => 'poc 2 screen',
						'poc_2_notes' => 'poc 2 notes',
					]
				]
			]
		]);

		$expectedResult = $this->createExpectedResult([
			'hosts' => [
				[
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					]
				],
				[
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_MANUAL
					]
				],
				[
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_MANUAL,
						'type' => 'device type',
						'name' => 'name',
						'os' => 'os',
						'serialno_a' => 'serial no',
						'tag' => 'tag',
						'macaddress_a' => 'mac address',
						'hardware_full' => 'hardware',
						'software_full' => 'software',
						'contact' => 'contact',
						'location' => 'location',
						'notes' => 'notes'."\r\n\r\n".'device notes',
						'alias' => 'device alias',
						'type_full' => 'device type',
						'chassis' => 'device chassis',
						'os_full' => 'device os',
						'os_short' => 'device os short',
						'hw_arch' => 'device hw arch',
						'serialno_b' => 'device serial',
						'model' => 'device model',
						'asset_tag' => 'device tag',
						'vendor' => 'device vendor',
						'contract_number' => 'device contract',
						'installer_name' => 'device who',
						'deployment_status' => 'device status',
						'software_app_a' => 'device app 01',
						'software_app_b' => 'device app 02',
						'software_app_c' => 'device app 03',
						'software_app_d' => 'device app 04',
						'software_app_e' => 'device app 05',
						'url_a' => 'device url 1',
						'url_b' => 'device url 2',
						'url_c' => 'device url 3',
						'host_networks' => 'device networks',
						'hardware' => 'device hardware',
						'software' => 'device software',
						'host_netmask' => 'ip subnet mask',
						'host_router' => 'ip router',
						'macaddress_b' => 'ip macaddress',
						'oob_ip' => 'oob ip',
						'oob_netmask' => 'oob subnet mask',
						'oob_router' => 'oob router',
						'date_hw_purchase' => 'date hw buy',
						'date_hw_install' => 'date hw install',
						'date_hw_expiry' => 'date hw expiry',
						'date_hw_decomm' => 'date hw decomm',
						'site_address_a' => 'site street 1',
						'site_address_b' => 'site street 2',
						'site_address_c' => 'site street 3',
						'site_city' => 'site city',
						'site_state' => 'site state',
						'site_country' => 'site country',
						'site_zip' => 'site zip',
						'site_rack' => 'site rack',
						'site_notes' => 'site notes',
						'poc_1_name' => 'poc 1 name',
						'poc_1_email' => 'poc 1 email',
						'poc_1_phone_a' => 'poc 1 phone 1',
						'poc_1_phone_b' => 'poc 1 phone 2',
						'poc_1_cell' => 'poc 1 cell',
						'poc_1_screen' => 'poc 1 screen',
						'poc_1_notes' => 'poc 1 notes',
						'poc_2_name' => 'poc 2 name',
						'poc_2_email' => 'poc 2 email',
						'poc_2_phone_a' => 'poc 2 phone 1',
						'poc_2_phone_b' => 'poc 2 phone 2',
						'poc_2_cell' => 'poc 2 cell',
						'poc_2_screen' => 'poc 2 screen',
						'poc_2_notes' => 'poc 2 notes',
					]
				]
			]
		]);

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertItems() {
		$source = $this->createSource([
			'hosts' => [
				[
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
				],
				[
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'items' => []
				],
				[
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'items' => [
						[
							'key' => 'item',
						],
						[
							'key' => 'item',
							'applications' => []
						],
						[
							'description' => 'My item',
							'key' => 'ftp,1',
							'applications' => [
								'Application 1',
								'Application 2',
							]
						]
					]
				],
				[
					'name' => 'template',
					'status' => HOST_STATUS_TEMPLATE,
					'items' => [
						[
							'key' => 'item',
						],
						[
							'description' => 'My item',
							'key' => 'ftp,1',
							'applications' => [
								'Application 1',
								'Application 2',
							]
						]
					]
				]
			]
		]);

		$expectedResult = $this->createExpectedResult([
			'hosts' => [
				[
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
				],
				[
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
					'items' => []
				],
				[
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
					'items' => [
						[
							'key' => 'item',
						],
						[
							'key' => 'item',
							'applications' => []
						],
						[
							'name' => 'My item',
							'key' => 'net.tcp.service[ftp,,1]',
							'applications' => [
								[
									'name' => 'Application 1'
								],
								[
									'name' => 'Application 2'
								]
							]
						]
					],
					'applications' => [
						[
							'name' => 'Application 1'
						],
						[
							'name' => 'Application 2'
						]
					]
				]
			],
			'templates' => [
				[
					'template' => 'template',
					'items' => [
						[
							'key' => 'item',
						],
						[
							'name' => 'My item',
							'key' => 'net.tcp.service[ftp,,1]',
							'applications' => [
								[
									'name' => 'Application 1'
								],
								[
									'name' => 'Application 2'
								]
							]
						]
					],
					'applications' => [
						[
							'name' => 'Application 1'
						],
						[
							'name' => 'Application 2'
						]
					]
				]
			]
		]);

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertTriggers() {
		$source = $this->createSource([
			'hosts' => [
				[
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
				],
				[
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'triggers' => ''
				],
				[
					'name' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'triggers' => [
						[
							'description' => 'My trigger',
							'comments' => 'Trigger from two hosts',
							'expression' => '{host:item.last(0)}>0&{host2:item.last(0)}'
						],
						[
							'description' => 'Simple check trigger',
							'expression' => '{host:ftp,1.last(0)}'
						],
						[
							'description' => 'Macro trigger',
							'expression' => '{{HOSTNAME}:item.last(0)}>0&{{HOST.HOST}:item.last(0)}>0'
						],
					]
				],
				[
					'name' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'triggers' => [
						[
							'description' => 'My trigger',
							'comments' => 'Trigger from two hosts',
							'expression' => '{host:item.last(0)}>0&{host2:item.last(0)}'
						]
					]
				],
				[
					'name' => 'template',
					'status' => HOST_STATUS_TEMPLATE,
					'triggers' => [
						[
							'description' => 'My trigger 2',
							'expression' => '{template:item.last(0)}>0'
						]
					]
				]
			],
		]);

		$expectedResult = $this->createExpectedResult([
			'triggers' => [
				[
					'name' => 'My trigger',
					'description' => 'Trigger from two hosts',
					'expression' => '{host:item.last(0)}>0&{host2:item.last(0)}'
				],
				[
					'name' => 'Simple check trigger',
					'expression' => '{host:net.tcp.service[ftp,,1].last(0)}'
				],
				[
					'name' => 'Macro trigger',
					'expression' => '{host2:item.last(0)}>0&{host2:item.last(0)}>0'
				],
				[
					'name' => 'My trigger 2',
					'expression' => '{template:item.last(0)}>0'
				],
			],
			'hosts' => [
				[
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
				],
				[
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
				],
				[
					'host' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
				],
				[
					'host' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
				]
			],
			'templates' => [
				[
					'template' => 'template',
				]
			]
		]);

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertTriggerDependencies() {
		$this->assertConvert(
			$this->createExpectedResult([]),
			$this->createSource([])
		);

		// dependencies as an empty string
		$source = $this->createSource([
			'dependencies' => ''
		]);
		$expectedResult = $this->createExpectedResult([]);
		$this->assertConvert($expectedResult, $source);

		// missing hosts
		$source = $this->createSource([
			'dependencies' => []
		]);
		$expectedResult = $this->createExpectedResult([]);
		$this->assertConvert($expectedResult, $source);

		// hosts have no triggers
		$source = $this->createSource([
			'hosts' => [
				[
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
				],
				[
					'name' => 'host2',
					'status' => HOST_STATUS_MONITORED,
				]
			],
			'dependencies' => [
				[
					'description' => 'host1:trigger',
					'depends' => 'host2:trigger',
				]
			]
		]);
		$expectedResult = $this->createExpectedResult([
			'hosts' => [
				[
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
				],
				[
					'host' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
				],
			]
		]);
		$this->assertConvert($expectedResult, $source);

		// the used triggers are missing
		$source = $this->createSource([
			'hosts' => [
				[
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'triggers' => [],
				],
				[
					'name' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'triggers' => [
						[
							'description' => 'trigger',
							'expression' => '{host2:item.last()}>0'
						]
					],
				],
				[
					'name' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'triggers' => [
						[
							'description' => 'trigger2',
							'expression' => '{host3:item.last()}>0'
						]
					],
				]
			],
			'dependencies' => [
				[
					'description' => 'host1:trigger',
					'depends' => 'host1:trigger2',
				],
				// target trigger missing
				[
					'description' => 'host2:trigger',
					'depends' => 'host2:trigger2',
				],
				// source trigger missing
				[
					'description' => 'host3:trigger',
					'depends' => 'host3:trigger2',
				]
			]
		]);
		$expectedResult = $this->createExpectedResult([
			'triggers' => [
				[
					'name' => 'trigger',
					'expression' => '{host2:item.last()}>0'
				],
				[
					'name' => 'trigger2',
					'expression' => '{host3:item.last()}>0'
				],
			],
			'hosts' => [
				[
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
				],
				[
					'host' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
				],
				[
					'host' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
				],
			]
		]);
		$this->assertConvert($expectedResult, $source);

		$source = $this->createSource([
			'hosts' => [
				[
					'name' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'triggers' => [
						[
							'description' => 'common-trigger',
							'expression' => '{host1:item.last(0)}>0&{host2:item.last(0)}'
						],
					]
				],
				// check the case when hosts are in a different order than in the expression
				[
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'triggers' => [
						[
							'description' => 'common-trigger',
							'expression' => '{host1:item.last(0)}>0&{host2:item.last(0)}'
						],
						[
							'description' => 'dep-trigger',
							'expression' => '{host1:item.last(0)}'
						],
					]
				],
				[
					'name' => 'template1',
					'status' => HOST_STATUS_TEMPLATE,
					'triggers' => [
						[
							'description' => 'common-trigger',
							'expression' => '{template1:item.last(0)}>0&{template2:item.last(0)}'
						],
					]
				],
				[
					'name' => 'template2',
					'status' => HOST_STATUS_TEMPLATE,
					'triggers' => [
						[
							'description' => 'common-trigger',
							'expression' => '{template1:item.last(0)}>0&{template2:item.last(0)}'
						],
						[
							'description' => 'dep-trigger',
							'expression' => '{template1:item.last(0)}'
						],
					]
				],
			],
			'dependencies' => [
				[
					'description' => 'host1:common-trigger',
					'depends' => 'host1:dep-trigger'
				],
				[
					'description' => 'template1:common-trigger',
					'depends' => 'template2:dep-trigger'
				],
			]
		]);

		$expectedResult = $this->createExpectedResult([
			'triggers' => [
				[
					'name' => 'common-trigger',
					'expression' => '{host1:item.last(0)}>0&{host2:item.last(0)}',
					'dependencies' => [
						[
							'name' => 'dep-trigger',
							'expression' => '{host1:item.last(0)}'
						]
					]
				],
				[
					'name' => 'dep-trigger',
					'expression' => '{host1:item.last(0)}'
				],
				[
					'name' => 'common-trigger',
					'expression' => '{template1:item.last(0)}>0&{template2:item.last(0)}',
					'dependencies' => [
						[
							'name' => 'dep-trigger',
							'expression' => '{template1:item.last(0)}'
						]
					]
				],
				[
					'name' => 'dep-trigger',
					'expression' => '{template1:item.last(0)}'
				],
			],
			'hosts' => [
				[
					'host' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
				],
				[
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
				],
			],
			'templates' => [
				[
					'template' => 'template1'
				],
				[
					'template' => 'template2'
				],
			],
		]);

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertHostTemplates() {
		$this->assertConvert(
			$this->createExpectedResult([]),
			$this->createSource()
		);
		$this->assertConvert(
			$this->createExpectedResult(['hosts' => '']),
			$this->createSource(['hosts' => ''])
		);

		$source = $this->createSource([
			'hosts' => [
				[
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
				],
				[
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'templates' => []
				],
				[
					'name' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'templates' => [
						'template1',
						'template2',
					]
				],
				[
					'name' => 'template',
					'status' => HOST_STATUS_TEMPLATE,
					'templates' => [
						'template1',
						'template2',
					]
				]
			],
		]);

		$expectedResult = $this->createExpectedResult([
			'hosts' => [
				[
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
				],
				[
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
					'templates' => []
				],
				[
					'host' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
					'templates' => [
						[
							'name' => 'template1'
						],
						[
							'name' => 'template2'
						],
					]
				]
			],
			'templates' => [
				[
					'template' => 'template',
					'templates' => [
						[
							'name' => 'template1'
						],
						[
							'name' => 'template2'
						],
					]
				]
			]
		]);

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertGraphs() {
		$source = $this->createSource([
			'hosts' => [
				[
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
				],
				[
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'graphs' => ''
				],
				[
					'name' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'graphs' => [
						[
							'name' => 'graph1',
							'graphtype' => GRAPH_TYPE_BAR,
							'ymin_item_key' => '',
							'ymax_item_key' => '',
							'graph_elements' => [
								[
									'item' => 'host2:item'
								],
							]
						],
						[
							'name' => 'graph2',
							'graphtype' => GRAPH_TYPE_BAR,
							'ymin_item_key' => 'host2:itemmin',
							'ymax_item_key' => 'host2:itemmax',
							'graph_elements' => [
								[
									'periods_cnt' => 5,
									'item' => 'host2:item'
								],
							]
						],
						[
							'name' => 'graph3',
							'graphtype' => GRAPH_TYPE_NORMAL,
							'ymin_item_key' => 'host2:ftp,1',
							'ymax_item_key' => 'host2:ftp,2',
							'graph_elements' => [
								[
									'item' => 'host2:ftp,3'
								],
								[
									'item' => '{HOSTNAME}:ftp,3'
								],
								[
									'item' => '{HOST.HOST}:ftp,3'
								],
							]
						],
						[
							'name' => 'two-host graph',
							'graphtype' => GRAPH_TYPE_NORMAL,
							'graph_elements' => [
								[
									'item' => 'host2:item'
								],
								[
									'item' => 'host3:item'
								],
							]
						],
					]
				],
				[
					'name' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'graphs' => [
						[
							'name' => 'two-host graph',
							'graphtype' => GRAPH_TYPE_NORMAL,
							'graph_elements' => [
								[
									'item' => 'host2:item'
								],
								[
									'item' => 'host3:item'
								],
							]
						]
					]
				],
				[
					'name' => 'template',
					'status' => HOST_STATUS_TEMPLATE,
					'graphs' => [
						[
							// same name as for the host graph but a different item
							'name' => 'graph2',
							'graphtype' => GRAPH_TYPE_NORMAL,
							'graph_elements' => [
								[
									'item' => 'template:item'
								],
							]
						]
					]
				]
			],
		]);

		$expectedResult = $this->createExpectedResult([
			'graphs' => [
				[
					'name' => 'graph1',
					'type' => GRAPH_TYPE_BAR,
					'ymin_item_1' => '',
					'ymax_item_1' => '',
					'graph_items' => [
						[
							'item' => [
								'host' => 'host2',
								'key' => 'item'
							]
						],
					]
				],
				[
					'name' => 'graph2',
					'type' => GRAPH_TYPE_BAR,
					'ymin_item_1' => [
						'host' => 'host2',
						'key' => 'itemmin'
					],
					'ymax_item_1' => [
						'host' => 'host2',
						'key' => 'itemmax'
					],
					'graph_items' => [
						[
							'item' => [
								'host' => 'host2',
								'key' => 'item'
							]
						],
					]
				],
				[
					'name' => 'graph3',
					'type' => GRAPH_TYPE_NORMAL,
					'ymin_item_1' => [
						'host' => 'host2',
						'key' => 'net.tcp.service[ftp,,1]'
					],
					'ymax_item_1' => [
						'host' => 'host2',
						'key' => 'net.tcp.service[ftp,,2]'
					],
					'graph_items' => [
						[
							'item' => [
								'host' => 'host2',
								'key' => 'net.tcp.service[ftp,,3]'
							],
						],
						[
							'item' => [
								'host' => 'host2',
								'key' => 'net.tcp.service[ftp,,3]'
							],
						],
						[
							'item' => [
								'host' => 'host2',
								'key' => 'net.tcp.service[ftp,,3]'
							],
						],
					]
				],
				[
					'name' => 'two-host graph',
					'type' => GRAPH_TYPE_NORMAL,
					'graph_items' => [
						[
							'item' => [
								'host' => 'host2',
								'key' => 'item'
							],
						],
						[
							'item' => [
								'host' => 'host3',
								'key' => 'item'
							],
						]
					]
				],
				[
					'name' => 'graph2',
					'type' => GRAPH_TYPE_NORMAL,
					'graph_items' => [
						[
							'item' => [
								'host' => 'template',
								'key' => 'item'
							],
						],
					]
				]
			],
			'hosts' => [
				[
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
				],
				[
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
				],
				[
					'host' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
				],
				[
					'host' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
				],
			],
			'templates' => [
				[
					'template' => 'template',
				]
			]
		]);

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertMacros() {
		$source = $this->createSource([
			'hosts' => [
				[
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
				],
				[
					'name' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'macros' => ''
				],
				[
					'name' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'macros' => [
						[
							'name' => '{$MACRO}',
							'value' => 'value',
						]
					]
				],
				[
					'name' => 'template',
					'status' => HOST_STATUS_TEMPLATE,
					'macros' => [
						[
							'name' => '{$MACRO}',
							'value' => 'value',
						]
					]
				],
			],
		]);

		$expectedResult = $this->createExpectedResult([
			'hosts' => [
				[
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
				],
				[
					'host' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
					'macros' => ''
				],
				[
					'host' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => [
						'inventory_mode' => HOST_INVENTORY_DISABLED
					],
					'macros' => [
						[
							'macro' => '{$MACRO}',
							'value' => 'value',
						]
					]
				]
			],
			'templates' => [
				[
					'template' => 'template',
					'macros' => [
						[
							'macro' => '{$MACRO}',
							'value' => 'value',
						]
					]
				]
			]
		]);

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertSysmaps() {
		$this->assertConvert(
			$this->createExpectedResult([]),
			$this->createSource()
		);
		$this->assertConvert(
			$this->createExpectedResult(['sysmaps' => '']),
			$this->createSource(['sysmaps' => ''])
		);

		$source = $this->createSource([
			'sysmaps' => [
				[],
				[
					'selements' => [],
					'links' => [],
				],
				[
					'selements' => [
						[
							'selementid' => 1,
							'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
							'elementid' => [
								'host' => 'host',
							],
							'iconid_off' => [],
							'iconid_on' => [],
							'iconid_disabled' => [],
							'iconid_maintenance' => [],
							'iconid_unknown' => [],
						],
						[
							'selementid' => 2,
							'elementtype' => SYSMAP_ELEMENT_TYPE_TRIGGER,
							'elementid' => [
								'host' => 'host',
								'description' => 'trigger',
								'expression' => '{host:item.last()}'
							]
						]
					],
					'links' => [
						[],
						[
							'linktriggers' => []
						],
						[
							'linktriggers' => [
								[
									'triggerid' => [
										'host' => 'host',
										'description' => 'trigger',
										'expression' => '{host:item.last()}'
									]
								]
							]
						]
					]
				]
			]
		]);

		$expectedResult = $this->createExpectedResult([
			'maps' => [
				[],
				[
					'selements' => [],
					'links' => [],
				],
				[
					'selements' => [
						[
							'selementid' => 1,
							'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
							'element' => [
								'host' => 'host'
							],
							'icon_off' => [],
							'icon_on' => [],
							'icon_disabled' => [],
							'icon_maintenance' => [],
						],
						[
							'selementid' => 2,
							'elementtype' => SYSMAP_ELEMENT_TYPE_TRIGGER,
							'element' => [
								'description' => 'trigger',
								'expression' => '{host:item.last()}'
							]
						]
					],
					'links' => [
						[],
						[
							'linktriggers' => []
						],
						[
							'linktriggers' => [
								[
									'trigger' => [
										'description' => 'trigger',
										'expression' => '{host:item.last()}'
									]
								]
							]
						]
					]
				]
			]
		]);

		$this->assertConvert($expectedResult, $source);

	}

	public function testConvertScreens() {
		$this->assertConvert(
			$this->createExpectedResult([]),
			$this->createSource()
		);
		$this->assertConvert(
			$this->createExpectedResult(['screens' => '']),
			$this->createSource(['screens' => ''])
		);

		$source = $this->createSource([
			'screens' => [
				[],
				[
					'screenitems' => ''
				],
				[
					'screenitems' => [
						// resource is exported as "0" if it's not used
						[
							'resourceid' => '0'
						],
						[
							'resourceid' => [
								'key_' => 'itemkey',
							],
						]
					]
				]
			]
		]);

		$expectedResult = $this->createExpectedResult([
			'screens' => [
				[],
				[
					'screen_items' => ''
				],
				[
					'screen_items' => [
						[
							'resource' => '0'
						],
						[
							'resource' => [
								'key' => 'itemkey',
							],
						]
					]
				]
			]
		]);

		$this->assertConvert($expectedResult, $source);

	}

	protected function createSource(array $data = []) {
		return [
			'zabbix_export' => array_merge([
				'version' => '1.0',
				'date' => '19.11.14',
				'time' => '12.19'
			], $data)
		];
	}

	protected function createExpectedResult(array $data = []) {
		return [
			'zabbix_export' => array_merge([
				'version' => '2.0',
				'date' => '2014-11-19T12:19:00Z'
			], $data)
		];
	}

	protected function createConverter() {
		return new C10ImportConverter();
	}

}

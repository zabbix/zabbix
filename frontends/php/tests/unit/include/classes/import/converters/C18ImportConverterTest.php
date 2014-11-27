<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class C18ImportConverterTest extends CImportConverterTest {

	public function testTemplateSeparation() {
		$this->assertConvert(
			$this->createExpectedResult(array()),
			$this->createSource()
		);
		$this->assertConvert(
			$this->createExpectedResult(array('hosts' => '')),
			$this->createSource(array('hosts' => ''))
		);

		$source = $this->createSource(array(
			'hosts' => array(
				array(
					'name' => 'Template 1',
				),
				array(
					'name' => 'Template 2',
					'status' => HOST_STATUS_TEMPLATE
				),
				array(
					'name' => 'Host 1',
					'status' => HOST_STATUS_NOT_MONITORED
				),
				array(
					'name' => 'Host 2',
					'status' => HOST_STATUS_MONITORED
				),
			),
		));

		$expectedResult = $this->createExpectedResult(array(
			'hosts' => array(
				array(
					'host' => 'Host 1',
					'status' => HOST_STATUS_NOT_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					)
				),
				array(
					'host' => 'Host 2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					)
				),
			),
			'templates' => array(
				array(
					'template' => 'Template 1',
				),
				array(
					'template' => 'Template 2'
				),
			)
		));

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertGroups() {
		$source = $this->createSource(array(
			'hosts' => array(
				array(
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED
				),
				array(
					'name' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'groups' => array(
						'Zabbix server',
						'Linux server',
					),
				),
				array(
					'name' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'groups' => array(
						'Zabbix server',
						'My group',
					),
				),
				array(
					'name' => 'template',
					'status' => HOST_STATUS_TEMPLATE,
					'groups' => array(
						'Templates',
					),
				),
			),
		));

		$result = $this->createExpectedResult(array(
			'groups' => array(
				array(
					'name' => 'Zabbix server'
				),
				array(
					'name' => 'Linux server'
				),
				array(
					'name' => 'My group'
				),
				array(
					'name' => 'Templates'
				),
			),
			'hosts' => array(
				array(
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					)
				),
				array(
					'host' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
					'groups' => array(
						array(
							'name' => 'Zabbix server'
						),
						array(
							'name' => 'Linux server'
						),
					),
				),
				array(
					'host' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
					'groups' => array(
						array(
							'name' => 'Zabbix server'
						),
						array(
							'name' => 'My group'
						),
					),
				),
			),
			'templates' => array(
				array(
					'template' => 'template',
					'groups' => array(
						array(
							'name' => 'Templates'
						)
					)
				)
			)
		));

		$this->assertConvert($result, $source);
	}

	public function testConvertHosts() {
		$this->assertConvert(
			$this->createExpectedResult(array()),
			$this->createSource()
		);
		$this->assertConvert(
			$this->createExpectedResult(array('hosts' => '')),
			$this->createSource(array('hosts' => ''))
		);

		$source = $this->createSource(array(
			'hosts' => array(
				array(
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
				),
				array(
					'name' => 'host2',
					'status' => HOST_STATUS_MONITORED,
				),
			)
		));
		$result = $this->createExpectedResult(array(
			'hosts' => array(
				array(
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					)
				),
				array(
					'host' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					)
				),
			)
		));
		$this->assertConvert($result, $source);
	}

	public function testConvertHostInterfaces() {
		$source = $this->createSource(array(
			'hosts' => array(
				array(
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
				),
				// host with an agent interface
				array(
					'name' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => 'http://zabbix.com',
					'port' => '123',
					'items' => array(
						array(
							'key' => 'item1',
							'type' => ITEM_TYPE_ZABBIX,
						),
						array(
							'key' => 'item2',
							'type' => ITEM_TYPE_SIMPLE,
						)
					)
				),
				// missing interface data
				array(
					'name' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'useip' => 1,
					'ip' => '127.0.0.1',
				),
				// host with an IPMI interface
				array(
					'name' => 'host4',
					'status' => HOST_STATUS_MONITORED,
					'ip' => '127.0.0.1',
					'ipmi_ip' => '127.0.0.2',
					'ipmi_port' => '123',
					'items' => array(
						// an IPMI item to test
						array(
							'key' => 'item1',
							'type' => ITEM_TYPE_IPMI,
						)
					)
				),
				// host with an IPMI interface, fallback to "ip"
				array(
					'name' => 'host5',
					'status' => HOST_STATUS_MONITORED,
					'ip' => '127.0.0.1',
					'ipmi_port' => '123',
					'items' => array(
						// an IPMI item to test
						array(
							'key' => 'item1',
							'type' => ITEM_TYPE_IPMI,
						)
					)
				),
				// host with SNMP interfaces
				array(
					'name' => 'host6',
					'status' => HOST_STATUS_MONITORED,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => 'http://zabbix.com',
					'items' => array(
						array(
							'key' => 'item1',
							'type' => ITEM_TYPE_SNMPV1,
							'snmp_port' => '1'
						),
						array(
							'key' => 'item2',
							'type' => ITEM_TYPE_SNMPV2C,
							'snmp_port' => '2'
						),
						array(
							'key' => 'item3',
							'type' => ITEM_TYPE_SNMPV3,
							'snmp_port' => '3'
						),
						array(
							'key' => 'item4',
							'type' => ITEM_TYPE_SNMPV1,
							'snmp_port' => '1'
						),
						array(
							'key' => 'item5',
							'type' => ITEM_TYPE_SNMPV3,
						),
					)
				),
				// missing item type
				array(
					'name' => 'host7',
					'status' => HOST_STATUS_MONITORED,
					'items' => array(
						array(
							'key' => 'item1',
						)
					)
				)
			)
		));
		$expectedResult = $this->createExpectedResult(array(
			'hosts' => array(
				array(
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					)
				),
				// host with an agent interface
				array(
					'host' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
					'interfaces' => array(
						array(
							'type' => INTERFACE_TYPE_AGENT,
							'useip' => 1,
							'ip' => '127.0.0.1',
							'dns' => 'http://zabbix.com',
							'port' => '123',
							'default' => INTERFACE_PRIMARY,
							'interface_ref' => 'if0'
						)
					),
					'items' => array(
						array(
							'key' => 'item1',
							'type' => ITEM_TYPE_ZABBIX,
							'interface_ref' => 'if0'
						),
						array(
							'key' => 'item2',
							'type' => ITEM_TYPE_SIMPLE,
							'interface_ref' => 'if0'
						)
					)
				),
				// missing interface data
				array(
					'host' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
				),
				// host with an IPMI interface
				array(
					'host' => 'host4',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
					'interfaces' => array(
						array(
							'type' => INTERFACE_TYPE_IPMI,
							'useip' => 1,
							'ip' => '127.0.0.2',
							'dns' => '',
							'port' => '123',
							'default' => INTERFACE_PRIMARY,
							'interface_ref' => 'if0'
						),
					),
					'items' => array(
						array(
							'key' => 'item1',
							'type' => ITEM_TYPE_IPMI,
							'interface_ref' => 'if0'
						)
					)
				),
				// host with an IPMI interface, fallback to "ip"
				array(
					'host' => 'host5',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
					'interfaces' => array(
						array(
							'type' => INTERFACE_TYPE_IPMI,
							'useip' => 1,
							'ip' => '127.0.0.1',
							'dns' => '',
							'port' => '123',
							'default' => INTERFACE_PRIMARY,
							'interface_ref' => 'if0'
						),
					),
					'items' => array(
						array(
							'key' => 'item1',
							'type' => ITEM_TYPE_IPMI,
							'interface_ref' => 'if0'
						)
					)
				),
				// host with SNMP interfaces
				array(
					'host' => 'host6',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
					'interfaces' => array(
						array(
							'type' => INTERFACE_TYPE_SNMP,
							'useip' => 1,
							'ip' => '127.0.0.1',
							'dns' => 'http://zabbix.com',
							'port' => '1',
							'default' => INTERFACE_PRIMARY,
							'interface_ref' => 'if0'
						),
						array(
							'type' => INTERFACE_TYPE_SNMP,
							'useip' => 1,
							'ip' => '127.0.0.1',
							'dns' => 'http://zabbix.com',
							'port' => '2',
							'default' => INTERFACE_SECONDARY,
							'interface_ref' => 'if1'
						),
						array(
							'type' => INTERFACE_TYPE_SNMP,
							'useip' => 1,
							'ip' => '127.0.0.1',
							'dns' => 'http://zabbix.com',
							'port' => '3',
							'default' => INTERFACE_SECONDARY,
							'interface_ref' => 'if2'
						),
					),
					'items' => array(
						array(
							'key' => 'item1',
							'type' => ITEM_TYPE_SNMPV1,
							'snmp_port' => '1',
							'interface_ref' => 'if0'
						),
						array(
							'key' => 'item2',
							'type' => ITEM_TYPE_SNMPV2C,
							'snmp_port' => '2',
							'interface_ref' => 'if1'
						),
						array(
							'key' => 'item3',
							'type' => ITEM_TYPE_SNMPV3,
							'snmp_port' => '3',
							'interface_ref' => 'if2'
						),
						array(
							'key' => 'item4',
							'type' => ITEM_TYPE_SNMPV1,
							'snmp_port' => '1',
							'interface_ref' => 'if0'
						),
						array(
							'key' => 'item5',
							'type' => ITEM_TYPE_SNMPV3,
						),
					)
				),
				// missing item type
				array(
					'host' => 'host7',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
					'items' => array(
						array(
							'key' => 'item1',
						)
					)
				)
			)
		));

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertHostProfiles() {
		$source = $this->createSource(array(
			'hosts' => array(
				array(
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
				),
				array(
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'host_profile' => '',
					'host_profiles_ext' => '',
				),
				array(
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'host_profile' => array(
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
					),
					'host_profiles_ext' => array(
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
					)
				)
			)
		));

		$expectedResult = $this->createExpectedResult(array(
			'hosts' => array(
				array(
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					)
				),
				array(
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					)
				),
				array(
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
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
					)
				)
			)
		));

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertItems() {
		$source = $this->createSource(array(
			'hosts' => array(
				array(
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
				),
				array(
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'items' => ''
				),
				array(
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'items' => array(
						array(
							'key' => 'item',
						),
						array(
							'key' => 'item',
							'applications' => ''
						),
						array(
							'description' => 'My item',
							'key' => 'ftp,1',
							'applications' => array(
								'Application 1',
								'Application 2',
							)
						)
					)
				),
				array(
					'name' => 'template',
					'status' => HOST_STATUS_TEMPLATE,
					'items' => array(
						array(
							'key' => 'item',
						),
						array(
							'description' => 'My item',
							'key' => 'ftp,1',
							'applications' => array(
								'Application 1',
								'Application 2',
							)
						)
					)
				)
			),
		));

		$expectedResult = $this->createExpectedResult(array(
			'hosts' => array(
				array(
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
				),
				array(
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
					'items' => ''
				),
				array(
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
					'items' => array(
						array(
							'key' => 'item',
						),
						array(
							'key' => 'item',
							'applications' => ''
						),
						array(
							'name' => 'My item',
							'key' => 'net.tcp.service[ftp,,1]',
							'applications' => array(
								array(
									'name' => 'Application 1'
								),
								array(
									'name' => 'Application 2'
								),
							)
						)
					)
				)
			),
			'templates' => array(
				array(
					'template' => 'template',
					'items' => array(
						array(
							'key' => 'item',
						),
						array(
							'name' => 'My item',
							'key' => 'net.tcp.service[ftp,,1]',
							'applications' => array(
								array(
									'name' => 'Application 1'
								),
								array(
									'name' => 'Application 2'
								),
							)
						)
					)
				)
			)
		));

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertTriggers() {
		$source = $this->createSource(array(
			'hosts' => array(
				array(
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
				),
				array(
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'triggers' => ''
				),
				array(
					'name' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'triggers' => array(
						array(
							'description' => 'My trigger',
							'comments' => 'Trigger from two hosts',
							'expression' => '{host:item.last(0)}>0&{host2:item.last(0)}'
						),
						array(
							'description' => 'Simple check trigger',
							'expression' => '{host:ftp,1.last(0)}'
						),
						array(
							'description' => 'Macro trigger',
							'expression' => '{{HOSTNAME}:item.last(0)}>0&{{HOST.HOST}:item.last(0)}>0'
						),
					)
				),
				array(
					'name' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'triggers' => array(
						array(
							'description' => 'My trigger',
							'comments' => 'Trigger from two hosts',
							'expression' => '{host:item.last(0)}>0&{host2:item.last(0)}'
						)
					)
				),
				array(
					'name' => 'template',
					'status' => HOST_STATUS_TEMPLATE,
					'triggers' => array(
						array(
							'description' => 'My trigger 2',
							'expression' => '{template:item.last(0)}>0'
						)
					)
				)
			),
		));

		$expectedResult = $this->createExpectedResult(array(
			'triggers' => array(
				array(
					'name' => 'My trigger',
					'description' => 'Trigger from two hosts',
					'expression' => '{host:item.last(0)}>0&{host2:item.last(0)}'
				),
				array(
					'name' => 'Simple check trigger',
					'expression' => '{host:net.tcp.service[ftp,,1].last(0)}'
				),
				array(
					'name' => 'Macro trigger',
					'expression' => '{host2:item.last(0)}>0&{host2:item.last(0)}>0'
				),
				array(
					'name' => 'My trigger 2',
					'expression' => '{template:item.last(0)}>0'
				),
			),
			'hosts' => array(
				array(
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
				),
				array(
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
				),
				array(
					'host' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
				),
				array(
					'host' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
				)
			),
			'templates' => array(
				array(
					'template' => 'template',
				)
			)
		));

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertTriggerDependencies() {
		$this->assertConvert(
			$this->createExpectedResult(array()),
			$this->createSource(array())
		);

		// dependencies as an empty string
		$source = $this->createSource(array(
			'dependencies' => ''
		));
		$expectedResult = $this->createExpectedResult(array());
		$this->assertConvert($expectedResult, $source);

		// missing hosts
		$source = $this->createSource(array(
			'dependencies' => array()
		));
		$expectedResult = $this->createExpectedResult(array());
		$this->assertConvert($expectedResult, $source);

		// hosts have no triggers
		$source = $this->createSource(array(
			'hosts' => array(
				array(
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
				),
				array(
					'name' => 'host2',
					'status' => HOST_STATUS_MONITORED,
				)
			),
			'dependencies' => array(
				array(
					'description' => 'host1:trigger',
					'depends' => 'host2:trigger',
				)
			)
		));
		$expectedResult = $this->createExpectedResult(array(
			'hosts' => array(
				array(
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
				),
				array(
					'host' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
				),
			)
		));
		$this->assertConvert($expectedResult, $source);

		// the used triggers are missing
		$source = $this->createSource(array(
			'hosts' => array(
				array(
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'triggers' => array(),
				),
				array(
					'name' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'triggers' => array(
						array(
							'description' => 'trigger',
							'expression' => '{host2:item.last()}>0'
						)
					),
				),
				array(
					'name' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'triggers' => array(
						array(
							'description' => 'trigger2',
							'expression' => '{host3:item.last()}>0'
						)
					),
				)
			),
			'dependencies' => array(
				array(
					'description' => 'host1:trigger',
					'depends' => 'host1:trigger2',
				),
				// target trigger missing
				array(
					'description' => 'host2:trigger',
					'depends' => 'host2:trigger2',
				),
				// source trigger missing
				array(
					'description' => 'host3:trigger',
					'depends' => 'host3:trigger2',
				)
			)
		));
		$expectedResult = $this->createExpectedResult(array(
			'triggers' => array(
				array(
					'name' => 'trigger',
					'expression' => '{host2:item.last()}>0'
				),
				array(
					'name' => 'trigger2',
					'expression' => '{host3:item.last()}>0'
				),
			),
			'hosts' => array(
				array(
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
				),
				array(
					'host' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
				),
				array(
					'host' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
				),
			)
		));
		$this->assertConvert($expectedResult, $source);

		$source = $this->createSource(array(
			'hosts' => array(
				array(
					'name' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'triggers' => array(
						array(
							'description' => 'common-trigger',
							'expression' => '{host1:item.last(0)}>0&{host2:item.last(0)}'
						),
					)
				),
				// check the case when hosts are in a different order than in the expression
				array(
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'triggers' => array(
						array(
							'description' => 'common-trigger',
							'expression' => '{host1:item.last(0)}>0&{host2:item.last(0)}'
						),
						array(
							'description' => 'dep-trigger',
							'expression' => '{host1:item.last(0)}'
						),
					)
				),
				array(
					'name' => 'template1',
					'status' => HOST_STATUS_TEMPLATE,
					'triggers' => array(
						array(
							'description' => 'common-trigger',
							'expression' => '{template1:item.last(0)}>0&{template2:item.last(0)}'
						),
					)
				),
				array(
					'name' => 'template2',
					'status' => HOST_STATUS_TEMPLATE,
					'triggers' => array(
						array(
							'description' => 'common-trigger',
							'expression' => '{template1:item.last(0)}>0&{template2:item.last(0)}'
						),
						array(
							'description' => 'dep-trigger',
							'expression' => '{template1:item.last(0)}'
						),
					)
				),
			),
			'dependencies' => array(
				array(
					'description' => 'host1:common-trigger',
					'depends' => 'host1:dep-trigger'
				),
				array(
					'description' => 'template1:common-trigger',
					'depends' => 'template2:dep-trigger'
				),
			)
		));

		$expectedResult = $this->createExpectedResult(array(
			'triggers' => array(
				array(
					'name' => 'common-trigger',
					'expression' => '{host1:item.last(0)}>0&{host2:item.last(0)}',
					'dependencies' => array(
						array(
							'name' => 'dep-trigger',
							'expression' => '{host1:item.last(0)}'
						)
					)
				),
				array(
					'name' => 'dep-trigger',
					'expression' => '{host1:item.last(0)}'
				),
				array(
					'name' => 'common-trigger',
					'expression' => '{template1:item.last(0)}>0&{template2:item.last(0)}',
					'dependencies' => array(
						array(
							'name' => 'dep-trigger',
							'expression' => '{template1:item.last(0)}'
						)
					)
				),
				array(
					'name' => 'dep-trigger',
					'expression' => '{template1:item.last(0)}'
				),
			),
			'hosts' => array(
				array(
					'host' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
				),
				array(
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
				),
			),
			'templates' => array(
				array(
					'template' => 'template1'
				),
				array(
					'template' => 'template2'
				),
			),
		));

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertHostTemplates() {
		$this->assertConvert(
			$this->createExpectedResult(array()),
			$this->createSource()
		);
		$this->assertConvert(
			$this->createExpectedResult(array('hosts' => '')),
			$this->createSource(array('hosts' => ''))
		);

		$source = $this->createSource(array(
			'hosts' => array(
				array(
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
				),
				array(
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'templates' => ''
				),
				array(
					'name' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'templates' => array(
						'template1',
						'template2',
					)
				),
				array(
					'name' => 'template',
					'status' => HOST_STATUS_TEMPLATE,
					'templates' => array(
						'template1',
						'template2',
					)
				)
			),
		));

		$expectedResult = $this->createExpectedResult(array(
			'hosts' => array(
				array(
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
				),
				array(
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
					'templates' => ''
				),
				array(
					'host' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
					'templates' => array(
						array(
							'name' => 'template1'
						),
						array(
							'name' => 'template2'
						),
					)
				)
			),
			'templates' => array(
				array(
					'template' => 'template',
					'templates' => array(
						array(
							'name' => 'template1'
						),
						array(
							'name' => 'template2'
						),
					)
				)
			)
		));

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertGraphs() {
		$source = $this->createSource(array(
			'hosts' => array(
				array(
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
				),
				array(
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'graphs' => ''
				),
				array(
					'name' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'graphs' => array(
						array(
							'name' => 'graph1',
							'graphtype' => GRAPH_TYPE_BAR,
							'ymin_item_key' => '',
							'ymax_item_key' => '',
							'graph_elements' => array(
								array(
									'item' => 'host2:item'
								),
							)
						),
						array(
							'name' => 'graph2',
							'graphtype' => GRAPH_TYPE_BAR,
							'ymin_item_key' => 'host2:itemmin',
							'ymax_item_key' => 'host2:itemmax',
							'graph_elements' => array(
								array(
									'periods_cnt' => 5,
									'item' => 'host2:item'
								),
							)
						),
						array(
							'name' => 'graph3',
							'ymin_item_key' => 'host2:ftp,1',
							'ymax_item_key' => 'host2:ftp,2',
							'graph_elements' => array(
								array(
									'item' => 'host2:ftp,3'
								),
								array(
									'item' => '{HOSTNAME}:ftp,3'
								),
								array(
									'item' => '{HOST.HOST}:ftp,3'
								),
							)
						),
						array(
							'name' => 'two-host graph',
							'graph_elements' => array(
								array(
									'item' => 'host2:item'
								),
								array(
									'item' => 'host3:item'
								),
							)
						),
					)
				),
				array(
					'name' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'graphs' => array(
						array(
							'name' => 'two-host graph',
							'graph_elements' => array(
								array(
									'item' => 'host2:item'
								),
								array(
									'item' => 'host3:item'
								),
							)
						)
					)
				),
				array(
					'name' => 'template',
					'status' => HOST_STATUS_TEMPLATE,
					'graphs' => array(
						array(
							// same name as for the host graph but a different item
							'name' => 'graph2',
							'graph_elements' => array(
								array(
									'item' => 'template:item'
								),
							)
						)
					)
				)
			),
		));

		$expectedResult = $this->createExpectedResult(array(
			'graphs' => array(
				array(
					'name' => 'graph1',
					'type' => GRAPH_TYPE_BAR,
					'ymin_item_1' => '',
					'ymax_item_1' => '',
					'graph_items' => array(
						array(
							'item' => array(
								'host' => 'host2',
								'key' => 'item'
							)
						),
					)
				),
				array(
					'name' => 'graph2',
					'type' => GRAPH_TYPE_BAR,
					'ymin_item_1' => array(
						'host' => 'host2',
						'key' => 'itemmin'
					),
					'ymax_item_1' => array(
						'host' => 'host2',
						'key' => 'itemmax'
					),
					'graph_items' => array(
						array(
							'item' => array(
								'host' => 'host2',
								'key' => 'item'
							)
						),
					)
				),
				array(
					'name' => 'graph3',
					'ymin_item_1' => array(
						'host' => 'host2',
						'key' => 'net.tcp.service[ftp,,1]'
					),
					'ymax_item_1' => array(
						'host' => 'host2',
						'key' => 'net.tcp.service[ftp,,2]'
					),
					'graph_items' => array(
						array(
							'item' => array(
								'host' => 'host2',
								'key' => 'net.tcp.service[ftp,,3]'
							),
						),
						array(
							'item' => array(
								'host' => 'host2',
								'key' => 'net.tcp.service[ftp,,3]'
							),
						),
						array(
							'item' => array(
								'host' => 'host2',
								'key' => 'net.tcp.service[ftp,,3]'
							),
						),
					)
				),
				array(
					'name' => 'two-host graph',
					'graph_items' => array(
						array(
							'item' => array(
								'host' => 'host2',
								'key' => 'item'
							),
						),
						array(
							'item' => array(
								'host' => 'host3',
								'key' => 'item'
							),
						)
					)
				),
				array(
					'name' => 'graph2',
					'graph_items' => array(
						array(
							'item' => array(
								'host' => 'template',
								'key' => 'item'
							),
						),
					)
				)
			),
			'hosts' => array(
				array(
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
				),
				array(
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
				),
				array(
					'host' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
				),
				array(
					'host' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
				),
			),
			'templates' => array(
				array(
					'template' => 'template',
				)
			)
		));

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertMacros() {
		$source = $this->createSource(array(
			'hosts' => array(
				array(
					'name' => 'host1',
					'status' => HOST_STATUS_MONITORED,
				),
				array(
					'name' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'macros' => ''
				),
				array(
					'name' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'macros' => array(
						array(
							'name' => '{$MACRO}',
							'value' => 'value',
						)
					)
				),
				array(
					'name' => 'template',
					'status' => HOST_STATUS_TEMPLATE,
					'macros' => array(
						array(
							'name' => '{$MACRO}',
							'value' => 'value',
						)
					)
				),
			),
		));

		$expectedResult = $this->createExpectedResult(array(
			'hosts' => array(
				array(
					'host' => 'host1',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
				),
				array(
					'host' => 'host2',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
					'macros' => ''
				),
				array(
					'host' => 'host3',
					'status' => HOST_STATUS_MONITORED,
					'inventory' => array(
						'inventory_mode' => HOST_INVENTORY_DISABLED
					),
					'macros' => array(
						array(
							'macro' => '{$MACRO}',
							'value' => 'value',
						)
					)
				)
			),
			'templates' => array(
				array(
					'template' => 'template',
					'macros' => array(
						array(
							'macro' => '{$MACRO}',
							'value' => 'value',
						)
					)
				)
			)
		));

		$this->assertConvert($expectedResult, $source);
	}

	public function testConvertSysmaps() {
		$this->assertConvert(
			$this->createExpectedResult(array()),
			$this->createSource()
		);
		$this->assertConvert(
			$this->createExpectedResult(array('sysmaps' => '')),
			$this->createSource(array('sysmaps' => ''))
		);

		$source = $this->createSource(array(
			'sysmaps' => array(
				array(),
				array(
					'selements' => '',
					'links' => '',
				),
				array(
					'selements' => array(
						array(
							'selementid' => 1,
							'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
							'elementid' => array(
								'host' => 'host',
							),
							'iconid_off' => array(),
							'iconid_on' => array(),
							'iconid_disabled' => array(),
							'iconid_maintenance' => array(),
							'iconid_unknown' => array(),
						),
						array(
							'selementid' => 2,
							'elementtype' => SYSMAP_ELEMENT_TYPE_TRIGGER,
							'elementid' => array(
								'host' => 'host',
								'description' => 'trigger'
							)
						)
					),
					'links' => array(
						array(),
						array(
							'linktriggers' => ''
						),
						array(
							'linktriggers' => array(
								array(
									'triggerid' => array(
										'host' => 'host',
										'description' => 'trigger'
									)
								)
							)
						)
					)
				)
			)
		));

		$expectedResult = $this->createExpectedResult(array(
			'maps' => array(
				array(),
				array(
					'selements' => '',
					'links' => '',
				),
				array(
					'selements' => array(
						array(
							'selementid' => 1,
							'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
							'element' => array(
								'host' => 'host'
							),
							'icon_off' => array(),
							'icon_on' => array(),
							'icon_disabled' => array(),
							'icon_maintenance' => array(),
						),
						array(
							'selementid' => 2,
							'elementtype' => SYSMAP_ELEMENT_TYPE_TRIGGER,
							'element' => array(
								'description' => 'trigger'
							)
						)
					),
					'links' => array(
						array(),
						array(
							'linktriggers' => ''
						),
						array(
							'linktriggers' => array(
								array(
									'trigger' => array(
										'description' => 'trigger'
									)
								)
							)
						)
					)
				)
			)
		));

		$this->assertConvert($expectedResult, $source);

	}

	public function testConvertScreens() {
		$this->assertConvert(
			$this->createExpectedResult(array()),
			$this->createSource()
		);
		$this->assertConvert(
			$this->createExpectedResult(array('screens' => '')),
			$this->createSource(array('screens' => ''))
		);

		$source = $this->createSource(array(
			'screens' => array(
				array(),
				array(
					'screenitems' => ''
				),
				array(
					'screenitems' => array(
						// resource is exported as "0" if it's not used
						array(
							'resourceid' => '0'
						),
						array(
							'resourceid' => array(
								'key_' => 'itemkey',
							),
						)
					)
				)
			)
		));

		$expectedResult = $this->createExpectedResult(array(
			'screens' => array(
				array(),
				array(
					'screen_items' => ''
				),
				array(
					'screen_items' => array(
						array(
							'resource' => '0'
						),
						array(
							'resource' => array(
								'key' => 'itemkey',
							),
						)
					)
				)
			)
		));

		$this->assertConvert($expectedResult, $source);

	}

	protected function createSource(array $data = array()) {
		return array(
			'zabbix_export' => array_merge(array(
				'version' => '1.0',
				'date' => '19.11.14',
				'time' => '12.19'
			), $data)
		);
	}

	protected function createExpectedResult(array $data = array()) {
		return array(
			'zabbix_export' => array_merge(array(
				'version' => '2.0',
				'date' => '2014-11-19T12:19:00Z'
			), $data)
		);
	}

	protected function createConverter() {
		$itemKeyConverter = new C18ItemKeyConverter();

		return new C18ImportConverter($itemKeyConverter, new C18TriggerConverter($itemKeyConverter));
	}

}

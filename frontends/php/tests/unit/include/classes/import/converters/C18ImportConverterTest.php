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


class C18ImportConverterTest extends PHPUnit_Framework_TestCase {

	public function testConvertGeneral() {
		$this->assertConvert($this->createResult(), $this->createSource());
	}

	public function testTemplateSeparation() {
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

		$expectedResult = $this->createResult(array(
			'hosts' => array(
				array(
					'host' => 'Host 1',
					'status' => HOST_STATUS_NOT_MONITORED,
					'inventory_mode' => HOST_INVENTORY_DISABLED
				),
				array(
					'host' => 'Host 2',
					'status' => HOST_STATUS_MONITORED,
					'inventory_mode' => HOST_INVENTORY_DISABLED
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
					'status' => HOST_STATUS_MONITORED
				),
				array(
					'status' => HOST_STATUS_MONITORED,
					'groups' => array(
						array('Zabbix server'),
						array('Linux server'),
					),
				),
				array(
					'status' => HOST_STATUS_MONITORED,
					'groups' => array(
						array('Zabbix server'),
						array('My group'),
					),
				),
				array(
					'status' => HOST_STATUS_TEMPLATE,
					'groups' => array(
						array('Templates'),
					),
				)
			),
		));

		$result = $this->createResult(array(
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
					'status' => HOST_STATUS_MONITORED,
					'inventory_mode' => HOST_INVENTORY_DISABLED
				),
				array(
					'status' => HOST_STATUS_MONITORED,
					'inventory_mode' => HOST_INVENTORY_DISABLED
				),
				array(
					'status' => HOST_STATUS_MONITORED,
					'inventory_mode' => HOST_INVENTORY_DISABLED
				),
			),
			'templates' => array(
				array()
			)
		));

		$this->assertConvert($result, $source);
	}

	public function testConvertHosts() {
		$source = $this->createSource(array(
			'hosts' => array(
				array(
					'status' => HOST_STATUS_MONITORED,
				),
				array(
					'status' => HOST_STATUS_MONITORED,
					'name' => 'Zabbix server',
				),
			)
		));
		$result = $this->createResult(array(
			'hosts' => array(
				array(
					'status' => HOST_STATUS_MONITORED,
					'inventory_mode' => HOST_INVENTORY_DISABLED,
				),
				array(
					'status' => HOST_STATUS_MONITORED,
					'host' => 'Zabbix server',
					'inventory_mode' => HOST_INVENTORY_DISABLED,
				),
			)
		));
		$this->assertConvert($result, $source);
	}

	public function testConvertHostInterfaces() {
		$source = $this->createSource(array(
			'hosts' => array(
				array(
					'status' => HOST_STATUS_MONITORED,
				),
				// host with an agent interface
				array(
					'status' => HOST_STATUS_MONITORED,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => 'http://zabbix.com',
					'port' => '123',
					'items' => array(
						array(
							'type' => ITEM_TYPE_ZABBIX,
						),
						array(
							'type' => ITEM_TYPE_SIMPLE,
						)
					)
				),
				// missing interface data
				array(
					'status' => HOST_STATUS_MONITORED,
					'useip' => 1,
					'ip' => '127.0.0.1',
				),
				// host with an IPMI interface
				array(
					'status' => HOST_STATUS_MONITORED,
					'ip' => '127.0.0.1',
					'ipmi_ip' => '127.0.0.2',
					'ipmi_port' => '123',
					'items' => array(
						// an IPMI item to test
						array(
							'type' => ITEM_TYPE_IPMI,
						)
					)
				),
				// host with an IPMI interface, fallback to "ip"
				array(
					'status' => HOST_STATUS_MONITORED,
					'ip' => '127.0.0.1',
					'ipmi_port' => '123',
					'items' => array(
						// an IPMI item to test
						array(
							'type' => ITEM_TYPE_IPMI,
						)
					)
				),
				// host with SNMP interfaces
				array(
					'status' => HOST_STATUS_MONITORED,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => 'http://zabbix.com',
					'items' => array(
						array(
							'type' => ITEM_TYPE_SNMPV1,
							'snmp_port' => '1'
						),
						array(
							'type' => ITEM_TYPE_SNMPV2C,
							'snmp_port' => '2'
						),
						array(
							'type' => ITEM_TYPE_SNMPV3,
							'snmp_port' => '3'
						),
						array(
							'type' => ITEM_TYPE_SNMPV1,
							'snmp_port' => '1'
						),
						array(
							'type' => ITEM_TYPE_SNMPV3,
						),
					)
				),
				// missing item type
				array(
					'status' => HOST_STATUS_MONITORED,
					'items' => array(
						array()
					)
				)
			)
		));
		$expectedResult = $this->createResult(array(
			'hosts' => array(
				array(
					'status' => HOST_STATUS_MONITORED,
					'inventory_mode' => HOST_INVENTORY_DISABLED
				),
				// host with an agent interface
				array(
					'status' => HOST_STATUS_MONITORED,
					'inventory_mode' => HOST_INVENTORY_DISABLED,
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
							'type' => ITEM_TYPE_ZABBIX,
							'interface_ref' => 'if0'
						),
						array(
							'type' => ITEM_TYPE_SIMPLE,
							'interface_ref' => 'if0'
						)
					)
				),
				// missing interface data
				array(
					'status' => HOST_STATUS_MONITORED,
					'inventory_mode' => HOST_INVENTORY_DISABLED
				),
				// host with an IPMI interface
				array(
					'status' => HOST_STATUS_MONITORED,
					'inventory_mode' => HOST_INVENTORY_DISABLED,
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
							'type' => ITEM_TYPE_IPMI,
							'interface_ref' => 'if0'
						)
					)
				),
				// host with an IPMI interface, fallback to "ip"
				array(
					'status' => HOST_STATUS_MONITORED,
					'inventory_mode' => HOST_INVENTORY_DISABLED,
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
							'type' => ITEM_TYPE_IPMI,
							'interface_ref' => 'if0'
						)
					)
				),
				// host with SNMP interfaces
				array(
					'status' => HOST_STATUS_MONITORED,
					'inventory_mode' => HOST_INVENTORY_DISABLED,
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
							'type' => ITEM_TYPE_SNMPV1,
							'snmp_port' => '1',
							'interface_ref' => 'if0'
						),
						array(
							'type' => ITEM_TYPE_SNMPV2C,
							'snmp_port' => '2',
							'interface_ref' => 'if1'
						),
						array(
							'type' => ITEM_TYPE_SNMPV3,
							'snmp_port' => '3',
							'interface_ref' => 'if2'
						),
						array(
							'type' => ITEM_TYPE_SNMPV1,
							'snmp_port' => '1',
							'interface_ref' => 'if0'
						),
						array(
							'type' => ITEM_TYPE_SNMPV3,
						),
					)
				),
				// missing item type
				array(
					'status' => HOST_STATUS_MONITORED,
					'inventory_mode' => HOST_INVENTORY_DISABLED,
					'items' => array(
						array()
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
					'status' => HOST_STATUS_MONITORED,
				),
				array(
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
						'device_serial' => 'device serial',
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

		$expectedResult = $this->createResult(array(
			'hosts' => array(
				array(
					'status' => HOST_STATUS_MONITORED,
					'inventory_mode' => HOST_INVENTORY_DISABLED
				),
				array(
					'status' => HOST_STATUS_MONITORED,
					'inventory_mode' => HOST_INVENTORY_MANUAL,
					'inventory' => array(
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
					'status' => HOST_STATUS_MONITORED,
				),
				array(
					'status' => HOST_STATUS_MONITORED,
					'items' => array(
						array(),
						array(
							'description' => 'My item',
							'key' => 'ftp,1',
							'applications' => array(
								array('Application 1'),
								array('Application 2'),
							)
						)
					)
				),
				array(
					'status' => HOST_STATUS_TEMPLATE,
					'items' => array(
						array(),
						array(
							'description' => 'My item',
							'key' => 'ftp,1',
							'applications' => array(
								array('Application 1'),
								array('Application 2'),
							)
						)
					)
				)
			),
		));

		$expectedResult = $this->createResult(array(
			'hosts' => array(
				array(
					'status' => HOST_STATUS_MONITORED,
					'inventory_mode' => HOST_INVENTORY_DISABLED
				),
				array(
					'status' => HOST_STATUS_MONITORED,
					'inventory_mode' => HOST_INVENTORY_DISABLED,
					'items' => array(
						array(),
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
					'items' => array(
						array(),
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

	protected function createSource(array $data = array()) {
		return array(
			'zabbix_export' => array_merge(array(
				'version' => '1.0',
				'date' => '19.11.14',
				'time' => '12.19'
			), $data)
		);
	}

	protected function createResult(array $data = array()) {
		return array(
			'zabbix_export' => array_merge(array(
				'version' => '2.0',
				'date' => '2014-11-19T12:19:00Z'
			), $data)
		);
	}

	protected function assertConvert(array $expectedResult, array $source) {
		$result = $this->createConverter()->convert($source);
		$this->assertEquals($expectedResult, $result);
	}


	protected function createConverter() {
		return new C18ImportConverter(new C18ItemKeyConverter());
	}

}

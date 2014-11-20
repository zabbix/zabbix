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

	public function testConvertGroups() {
		$source = $this->createSource(array(
			'hosts' => array(
				array(),
				array(
					'groups' => array(
						array('Zabbix server'),
						array('Linux server'),
					),
				),
				array(
					'groups' => array(
						array('Zabbix server'),
						array('My group'),
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
			),
			'hosts' => array(
				array(),
				array(),
				array(),
			)
		));

		$this->assertConvert($result, $source);
	}

	public function testConvertHosts() {
		$source = $this->createSource(array(
			'hosts' => array(
				array(),
				array(
					'name' => 'Zabbix server',
				),
			)
		));
		$result = $this->createResult(array(
			'hosts' => array(
				array(),
				array(
					'host' => 'Zabbix server',
				),
			)
		));
		$this->assertConvert($result, $source);
	}

	public function testConvertHostInterfaces() {

		$source = $this->createSource(array(
			'hosts' => array(
				array(),
				// host with an agent interface
				array(
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
					'useip' => 1,
					'ip' => '127.0.0.1',
				),
				// host with an IPMI interface
				array(
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
					'items' => array(
						array()
					)
				)
			)
		));
		$expectedResult = $this->createResult(array(
			'hosts' => array(
				array(),
				// host with an agent interface
				array(
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
				array(),
				// host with an IPMI interface
				array(
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
					'items' => array(
						array()
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
		return new C18ImportConverter();
	}

}

<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

require_once dirname(__FILE__).'/common/testMassUpdateItems.php';

/**
 * Test the mass update of item prototypes.
 *
 * @backup items, interface
 */
class testPageMassUpdateItemPrototypes extends testMassUpdateItems {

	/**
	 * Add items for mass updating.
	 */
	public function prepareItemPrototypesData() {
		CDataHelper::call('itemprototype.create', [
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '1_Item',
				'key_' => '1agent[{#KEY}]',
				'type' => 0,
				'value_type' => 0,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '1m',
				'applications' => [5000, 5001],
				'applicationPrototypes' => [['name' => 'Old Application proto']]
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '2_Item',
				'key_' => '2agent[{#KEY}]',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '2m',
				'applications' => [5000, 5001],
				'applicationPrototypes' => [['name' => 'Old Application proto']]
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '3_SNMP_trap',
				'key_' => 'snmptrap[{#KEY1}]',
				'type' => 17,
				'value_type' => 0,
				'interfaceid' => self::SNMP2_INTERFACE_ID,
				'delay' => '3m',
				'applications' => [5002, 5003],
				'applicationPrototypes' => [['name' => 'App proto for replace']]
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '4_SNMP_trap',
				'key_' => 'snmptrap[{#KEY2}]',
				'type' => 17,
				'value_type' => 1,
				'interfaceid' => self::SNMP2_INTERFACE_ID,
				'delay' => '4m',
				'applications' => [5002, 5003],
				'applicationPrototypes' => [['name' => 'App proto for replace']]
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '5_Aggregate',
				'key_' => 'grpavg["host group", [{#KEY}], avg, last]',
				'type' => 8,
				'value_type' => 0,
				'interfaceid' => self::SNMP2_INTERFACE_ID,
				'delay' => '9m',
				'applications' => [5004, 5005]
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '6_Aggregate',
				'key_' => 'grpmin["host group", [{#KEY}], avg, min]',
				'type' => 8,
				'value_type' => 3,
				'interfaceid' => self::SNMP2_INTERFACE_ID,
				'delay' => '30s',
				'applications' => [5004, 5005],
				'applicationPrototypes' => [['name' => 'App proto for remove']]
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '7_IPMI',
				'key_' => 'ipmi1[{#KEY}]',
				'type' => 12,
				'value_type' => 0,
				'interfaceid' => self::IPMI_INTERFACE_ID,
				'delay' => '10m',
				'ipmi_sensor' => 'temp',
				'applications' => [5002, 5003],
				'applicationPrototypes' => [['name' => 'App proto for replace']]
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '8_IPMI',
				'key_' => 'ipmi2[{#KEY}]',
				'type' => 12,
				'value_type' => 3,
				'interfaceid' => self::IPMI_INTERFACE_ID,
				'delay' => '11s',
				'ipmi_sensor' => 'temp',
				'applications' => [5002, 5003],
				'applicationPrototypes' => [['name' => 'App proto for replace']]
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '9_SNMP_Agent',
				'key_' => 'snmp1[{#KEY}]',
				'type' => 20,
				'value_type' => 4,
				'interfaceid' => self::SNMP2_INTERFACE_ID,
				'delay' => '9m',
				'snmp_oid' => '.1.3.6.1.2.1.1.1.0',
				'applications' => [5004, 5005],
				'applicationPrototypes' => [['name' => 'App proto for remove']]
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '10_SNMP_Agent',
				'key_' => 'snmp2[{#KEY}]',
				'type' => 20,
				'value_type' => 4,
				'interfaceid' => self::SNMP2_INTERFACE_ID,
				'delay' => '101s',
				'snmp_oid' => '.1.3.8.1.2.1.1.1.0',
				'applications' => [5004, 5005],
				'applicationPrototypes' => [['name' => 'App proto for remove']]
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '11_SSH_Agent',
				'key_' => 'ssh.run[{#KEY}]',
				'type' => 13,
				'value_type' => 1,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '22s',
				'authtype' => 0,
				'username' => 'username1',
				'params' => 'executed script 1'
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '12_SSH_Agent',
				'key_' => 'ssh.run[{#KEY}]',
				'type' => 13,
				'value_type' => 1,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '55s',
				'authtype' => 0,
				'username' => 'username2',
				'params' => 'executed script 2'
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '13_DB_Monitor',
				'key_' => 'db.odbc.select[{#KEY}]',
				'type' => 11,
				'value_type' => 0,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '10s',
				'username' => 'test_username',
				'password' => 'test_password',
				'params' => 'SELECT * FROM hosts',
				'preprocessing' => [
					[
						'type' => '5',
						'params' => "regular expression pattern\noutput template",
						'error_handler' => 2,
						'error_handler_params' => 'Error custom value'
					]
				]
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '14_DB_Monitor',
				'key_' => 'db.odbc.select[{#KEY}]',
				'type' => 11,
				'value_type' => 0,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '90s',
				'params' => 'SELECT * FROM items',
				'preprocessing' => [
					[
						'type' => '1',
						'params' => "2",
						'error_handler' => 0,
						'error_handler_params' => ''
					]
				]
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '15_Calculated',
				'key_' => 'calculated1[{#KEY}]',
				'type' => 15,
				'value_type' => 0,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '50s',
				'params' => 'avg("Zabbix Server:zabbix[wcache,values]",600)'
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '16_Calculated',
				'key_' => 'calculated2[{#KEY}]',
				'type' => 15,
				'value_type' => 0,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '30s',
				'params' => 'sum("Zabbix Server:zabbix[wcache,values]",900)'
			]
		]);
	}

	/**
	 * Data for mass updating of item prototypes.
	 */
	public function getItemPrototypesChangeData() {
		return [
			[
				[
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1 : 10051'],
						'Create enabled' => ['id' => 'status', 'value' => 'Disabled'],
						'Discover' => ['id' => 'discover', 'value' => 'No'],
						'Application prototypes' => [
							'action' => 'Add',
							'applications' => ['New_application_proto_1', 'New_application_proto_2']
						]
					],
					'expected_applications' => [
						'New_application_proto_1',
						'New_application_proto_2',
						'Old Application proto'
					],
					'not_expected_applications' => [
						'App proto for remove',
						'App proto for replace'
					]
				]
			],
			[
				[
					'names' => [
						'7_IPMI',
						'8_IPMI'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'IPMI agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.3 : 10053'],
						'Create enabled' => ['id' => 'status', 'value' => 'Enabled'],
						'Discover' => ['id' => 'discover', 'value' => 'Yes'],
						'Application prototypes' => [
							'action' => 'Replace'
						]
					],
					'expected_applications' => null,
					'not_expected_applications' => [
						'Old Application proto',
						'App proto for remove',
						'App proto for replace'
					]
				]
			],
			[
				[
					'names' => [
						'3_SNMP_trap',
						'3_SNMP_trap'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'SNMP trap'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.5 : 10055'],
					'Application prototypes' => [
							'action' => 'Replace',
							'applications' => ['Replaced_application_proto_1', 'Replaced_application_proto_2']
						]
					],
					'expected_applications' => [
						'Replaced_application_proto_1',
						'Replaced_application_proto_2'
					],
					'not_expected_applications' => [
						'Old Application proto',
						'App proto for remove',
						'App proto for replace'
					]
				]
			],
			[
				[
					'names' => [
						'9_SNMP_Agent',
						'10_SNMP_Agent'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'SNMP agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.5 : 10055'],
						'Application prototypes' => [
							'action' => 'Remove'
						]
					],
					'expected_applications' => [
						'App proto for remove'
					],
					'not_expected_applications' => [
						'Old Application proto',
						'App proto for replace'
					]
				]
			]
		];
	}

	/**
	 * @onBeforeOnce prepareItemPrototypesData, prepareInterfaceData
	 *
	 * @dataProvider getCommonChangeData
	 * @dataProvider getItemPrototypesChangeData
	 */
	public function testPageMassUpdateItemPrototypes_ChangeItems($data) {
		$this->executeItemsMassUpdate($data, true);
	}

	/**
	 * Add items with preprocessing for mass updating.
	 */
	public function prepareItemPrototypePreprocessingData() {
		CDataHelper::call('itemprototype.create', [
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '1_Item_Preprocessing',
				'key_' => '1agent.preproc[{#KEY}]',
				'type' => 0,
				'value_type' => 0,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '1m',
				'preprocessing' => [
					[
						'type' => '4',
						'params' => '123',
						'error_handler' => 0,
						'error_handler_params' => ''
					],
					[
						'type' => '25',
						'params' => "error\nmistake",
						'error_handler' => 0,
						'error_handler_params' => ''
					]
				]
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '2_Item_Preprocessing',
				'key_' => '2agent.preproc[{#KEY}]',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '2m',
				'applications' => [5000, 5001],
				'preprocessing' => [
					[
						'type' => '5',
						'params' => "pattern\noutput",
						'error_handler' => 2,
						'error_handler_params' => 'custom_value'
					],
					[
						'type' => '16',
						'params' => '$path',
						'error_handler' => 3,
						'error_handler_params' => 'custom_error'
					]
				]
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '1_Item_No_Preprocessing',
				'key_' => '1agent.no.preproc[{#KEY}]',
				'type' => 0,
				'value_type' => 0,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '1m'
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '2_Item_No_Preprocessing',
				'key_' => '2agent.no.preproc[{#KEY}]',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '2m'
			]
		]);
	}

	/**
	 * @onBeforeOnce prepareItemPrototypePreprocessingData
	 */
	public function testPageMassUpdateItemPrototypes_Cancel() {
		$this->executeMassUpdateCancel(true);
	}

	/**
	 * @dataProvider getCommonPreprocessingChangeData
	 *
	 * @depends testPageMassUpdateItemPrototypes_Cancel
	 */
	public function testPageMassUpdateItemPrototypes_ChangePreprocessing($data) {
		$this->executeItemsPreprocessingMassUpdate($data, true);
	}
}

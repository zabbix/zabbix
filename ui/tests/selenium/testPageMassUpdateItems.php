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
 * Test the mass update of items.
 *
 * @backup items, interface
 */
class testPageMassUpdateItems extends testMassUpdateItems {

	/**
	 * Add items for mass updating.
	 */
	public function prepareItemData() {
		CDataHelper::call('item.create', [
			[
				'hostid' => self::HOSTID,
				'name' => '1_Item',
				'key_' => '1agent',
				'type' => 0,
				'value_type' => 0,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '1m',
				'applications' => [5000, 5001]
			],
			[
				'hostid' => self::HOSTID,
				'name' => '2_Item',
				'key_' => '2agent',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '2m',
				'applications' => [5000, 5001]
			],
			[
				'hostid' => self::HOSTID,
				'name' => '3_SNMP_trap',
				'key_' => 'snmptrap.fallback',
				'type' => 17,
				'value_type' => 0,
				'interfaceid' => self::SNMP2_INTERFACE_ID,
				'delay' => '3m',
				'applications' => [5002, 5003]
			],
			[
				'hostid' => self::HOSTID,
				'name' => '4_SNMP_trap',
				'key_' => 'snmptrap[regexp]',
				'type' => 17,
				'value_type' => 1,
				'interfaceid' => self::SNMP2_INTERFACE_ID,
				'delay' => '4m',
				'applications' => [5002, 5003]
			],
			[
				'hostid' => self::HOSTID,
				'name' => '5_Aggregate',
				'key_' => 'grpavg["host group","key",avg,last]',
				'type' => 8,
				'value_type' => 0,
				'interfaceid' => self::SNMP2_INTERFACE_ID,
				'delay' => '9m',
				'applications' => [5004, 5005]
			],
			[
				'hostid' => self::HOSTID,
				'name' => '6_Aggregate',
				'key_' => 'grpmin["host group","key",avg,min]',
				'type' => 8,
				'value_type' => 3,
				'interfaceid' => self::SNMP2_INTERFACE_ID,
				'delay' => '30s',
				'applications' => [5004, 5005]
			],
			[
				'hostid' => self::HOSTID,
				'name' => '7_IPMI',
				'key_' => 'ipmi1',
				'type' => 12,
				'value_type' => 0,
				'interfaceid' => self::IPMI_INTERFACE_ID,
				'delay' => '10m',
				'ipmi_sensor' => 'temp',
				'applications' => [5002, 5003]
			],
			[
				'hostid' => self::HOSTID,
				'name' => '8_IPMI',
				'key_' => 'ipmi2',
				'type' => 12,
				'value_type' => 3,
				'interfaceid' => self::IPMI_INTERFACE_ID,
				'delay' => '11s',
				'ipmi_sensor' => 'temp',
				'applications' => [5002, 5003]
			],
			[
				'hostid' => self::HOSTID,
				'name' => '9_SNMP_Agent',
				'key_' => 'snmp1',
				'type' => 20,
				'value_type' => 4,
				'interfaceid' => self::SNMP2_INTERFACE_ID,
				'delay' => '9m',
				'snmp_oid' => '.1.3.6.1.2.1.1.1.0',
				'applications' => [5004, 5005]
			],
			[
				'hostid' => self::HOSTID,
				'name' => '10_SNMP_Agent',
				'key_' => 'snmp2',
				'type' => 20,
				'value_type' => 4,
				'interfaceid' => self::SNMP2_INTERFACE_ID,
				'delay' => '101s',
				'snmp_oid' => '.1.3.8.1.2.1.1.1.0',
				'applications' => [5004, 5005]
			],
			[
				'hostid' => self::HOSTID,
				'name' => '11_SSH_Agent',
				'key_' => 'ssh.run[]',
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
				'name' => '12_SSH_Agent',
				'key_' => 'ssh.run[description]',
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
				'name' => '13_DB_Monitor',
				'key_' => 'db.odbc.select',
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
				'name' => '14_DB_Monitor',
				'key_' => 'db.odbc.select',
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
				'name' => '15_Calculated',
				'key_' => 'calculated1',
				'type' => 15,
				'value_type' => 0,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '50s',
				'params' => 'avg("Zabbix Server:zabbix[wcache,values]",600)'
			],
			[
				'hostid' => self::HOSTID,
				'name' => '16_Calculated',
				'key_' => 'calculated2',
				'type' => 15,
				'value_type' => 0,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '30s',
				'params' => 'sum("Zabbix Server:zabbix[wcache,values]",900)'
			]
		]);
	}

	/**
	 * Data for mass updating of items.
	 */
	public function getItemChangeData() {
		return [
			[
				[
					'names' => [
						'3_SNMP_trap',
						'4_SNMP_trap'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1 : 10051'],
						'Status' => ['id' => 'status', 'value' => 'Disabled']
					]
				]
			],
			[
				[
					'names' => [
						'3_SNMP_trap',
						'4_SNMP_trap'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1 : 10051'],
						'Status' => ['id' => 'status', 'value' => 'Enabled']
					]
				]
			]
		];
	}

	/**
	 * @onBeforeOnce prepareItemData, prepareInterfaceData
	 *
	 * @dataProvider getCommonChangeData
	 * @dataProvider getItemChangeData
	 */
	public function testPageMassUpdateItems_ChangeItems($data) {
		$this->executeItemsMassUpdate($data);
	}

	/**
	 * Add items with preprocessing for mass updating.
	 */
	public function prepareItemPreprocessingData() {
		CDataHelper::call('item.create', [
			[
				'hostid' => self::HOSTID,
				'name' => '1_Item_Preprocessing',
				'key_' => '1agent.preproc',
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
				'name' => '2_Item_Preprocessing',
				'key_' => '2agent.preproc',
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
				'name' => '1_Item_No_Preprocessing',
				'key_' => '1agent.no.preproc',
				'type' => 0,
				'value_type' => 0,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '1m'
			],
			[
				'hostid' => self::HOSTID,
				'name' => '2_Item_No_Preprocessing',
				'key_' => '2agent.no.preproc',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '2m'
			]
		]);
	}

	/**
	 * @onBeforeOnce prepareItemPreprocessingData
	 */
	public function testPageMassUpdateItems_Cancel() {
		$this->executeMassUpdateCancel();
	}


	/**
	 * @dataProvider getCommonPreprocessingChangeData
	 *
	 * @depends testPageMassUpdateItems_Cancel
	 */
	public function testPageMassUpdateItems_ChangePreprocessing($data) {
		$this->executeItemsPreprocessingMassUpdate($data);
	}
}

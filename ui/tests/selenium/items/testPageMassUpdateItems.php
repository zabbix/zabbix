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


require_once dirname(__FILE__).'/../common/testMassUpdateItems.php';

/**
 * Test the mass update of items.
 *
 * @onBefore prepareItemTagsPreprocessingData
 *
 * @backup items, interface
 *
 * TODO: remove ignoreBrowserErrors after DEV-4233
 * @ignoreBrowserErrors
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
				'delay' => '1m'
			],
			[
				'hostid' => self::HOSTID,
				'name' => '2_Item',
				'key_' => '2agent',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '2m'
			],
			[
				'hostid' => self::HOSTID,
				'name' => '3_SNMP_trap',
				'key_' => 'snmptrap.fallback',
				'type' => 17,
				'value_type' => 0,
				'interfaceid' => self::SNMP2_INTERFACE_ID
			],
			[
				'hostid' => self::HOSTID,
				'name' => '4_SNMP_trap',
				'key_' => 'snmptrap[regexp]',
				'type' => 17,
				'value_type' => 1,
				'interfaceid' => self::SNMP2_INTERFACE_ID
			],
			[
				'hostid' => self::HOSTID,
				'name' => '7_IPMI',
				'key_' => 'ipmi1',
				'type' => 12,
				'value_type' => 0,
				'interfaceid' => self::IPMI_INTERFACE_ID,
				'delay' => '10m',
				'ipmi_sensor' => 'temp'
			],
			[
				'hostid' => self::HOSTID,
				'name' => '8_IPMI',
				'key_' => 'ipmi2',
				'type' => 12,
				'value_type' => 3,
				'interfaceid' => self::IPMI_INTERFACE_ID,
				'delay' => '11s',
				'ipmi_sensor' => 'temp'
			],
			[
				'hostid' => self::HOSTID,
				'name' => '9_SNMP_Agent',
				'key_' => 'snmp1',
				'type' => 20,
				'value_type' => 4,
				'interfaceid' => self::SNMP2_INTERFACE_ID,
				'delay' => '9m',
				'snmp_oid' => '.1.3.6.1.2.1.1.1.0'
			],
			[
				'hostid' => self::HOSTID,
				'name' => '10_SNMP_Agent',
				'key_' => 'snmp2',
				'type' => 20,
				'value_type' => 4,
				'interfaceid' => self::SNMP2_INTERFACE_ID,
				'delay' => '101s',
				'snmp_oid' => '.1.3.8.1.2.1.1.1.0'
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
				'key_' => 'db.odbc.select[]',
				'type' => 11,
				'value_type' => 0,
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
				'delay' => '50s',
				'params' => 'avg("Zabbix Server:zabbix[wcache,values]",600)',
				'tags' => [
					[
						'tag' => 'Item_tag_name',
						'value' => 'Item_tag_value'
					]
				]
			],
			[
				'hostid' => self::HOSTID,
				'name' => '16_Calculated',
				'key_' => 'calculated2',
				'type' => 15,
				'value_type' => 0,
				'delay' => '30s',
				'params' => 'sum("Zabbix Server:zabbix[wcache,values]",900)',
				'tags' => [
					[
						'tag' => 'Item_tag_name_1',
						'value' => 'Item_tag_value_1'
					],
					[
						'tag' => 'Item_tag_name_2',
						'value' => 'Item_tag_value_2'
					]
				]
			],
			[
				'hostid' => self::HOSTID,
				'name' => '17_Script',
				'key_' => 'script1',
				'type' => 21,
				'value_type' => 0,
				'delay' => '15s',
				'timeout' => '13s',
				'params' => 'test Script 1'
			],
			[
				'hostid' => self::HOSTID,
				'name' => '18_Script',
				'key_' => 'script2',
				'type' => 21,
				'value_type' => 0,
				'delay' => '14s',
				'timeout' => '13s',
				'params' => 'test Script 2'
			]
		]);
	}

	/**
	 * Data for mass updating of items.
	 */
	public function getItemChangeData() {
		return [
			// #58.
			[
				[
					'names' => [
						'3_SNMP_trap',
						'4_SNMP_trap'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'Status' => ['id' => 'status', 'value' => 'Disabled'],
						'Update interval' => ['Delay' => '1m']
					]
				]
			],
			// #59.
			[
				[
					'names' => [
						'3_SNMP_trap',
						'4_SNMP_trap'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Zabbix agent'],
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'Status' => ['id' => 'status', 'value' => 'Enabled'],
						'Update interval' => ['Delay' => '1m']
					]
				]
			],
			// #60.
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'1_Item',
						'2_Item'
					],
					'change' => [
						'Type' => ['id' => 'type', 'value' => 'Dependent item']
					],
					'details' => 'Invalid parameter "/1/master_itemid": an item ID is expected.'
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
	public function prepareItemTagsPreprocessingData() {
		CDataHelper::call('item.create', [
			[
				'hostid' => self::HOSTID,
				'name' => '1_Item_Tags_Preprocessing',
				'key_' => '1agent.preproc',
				'type' => 0,
				'value_type' => 0,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '1m',
				'tags' => [
					[
						'tag' => 'old_tag_1',
						'value' => 'old_value_1'
					]
				],
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
				'name' => '2_Item_Tags_Preprocessing',
				'key_' => '2agent.preproc',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '2m',
				'tags' => [
					[
						'tag' => 'old_tag_2',
						'value' => 'old_value_2'
					],
					[
						'tag' => 'old_tag_3',
						'value' => 'old_value_3'
					]
				],
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
				'name' => '1_Item_No_Tags_Preprocessing',
				'key_' => '1agent.no.preproc',
				'type' => 0,
				'value_type' => 0,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '1m'
			],
			[
				'hostid' => self::HOSTID,
				'name' => '2_Item_No_Tags_Preprocessing',
				'key_' => '2agent.no.preproc',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '2m'
			],
			[
				'hostid' => self::HOSTID,
				'name' => '1_Item_Tags_replace',
				'key_' => '1agent.tags.replace',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '2m',
				'tags' => [
					[
						'tag' => 'Replace_tag_1',
						'value' => 'replace_value_1'
					],
					[
						'tag' => 'Replace_tag_2',
						'value' => 'Replace_value_2'
					]
				]
			],
			[
				'hostid' => self::HOSTID,
				'name' => '2_Item_Tags_replace',
				'key_' => '2agent.tags.replace',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '2m',
				'tags' => [
					[
						'tag' => 'Replace_tag_3',
						'value' => 'Replace_value_3'
					]
				]
			],
			[
				'hostid' => self::HOSTID,
				'name' => '1_Item_Tags_remove',
				'key_' => '1agent.tags.remove',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '2m',
				'tags' => [
					[
						'tag' => 'remove_tag_1',
						'value' => 'remove_value_1'
					],
					[
						'tag' => 'remove_tag_2',
						'value' => 'remove_value_2'
					]
				]
			],
			[
				'hostid' => self::HOSTID,
				'name' => '2_Item_Tags_remove',
				'key_' => '2agent.tags.remove',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '2m',
				'tags' => [
					[
						'tag' => 'remove_tag_2',
						'value' => 'remove_value_2'
					]
				]
			],
			[
				'hostid' => self::HOSTID,
				'name' => '3_Item_Tags_remove',
				'key_' => '3agent.tags.remove',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '2m',
				'tags' => [
					[
						'tag' => 'remove_tag_3',
						'value' => 'remove_value_3'
					]
				]
			]
		]);
	}

	public function testPageMassUpdateItems_Cancel() {
		$this->executeMassUpdateCancel();
	}

	/**
	 * @dataProvider getCommonTagsChangeData
	 */
	public function testPageMassUpdateItems_ChangeTags($data) {
		$this->executeItemsTagsMassUpdate($data);
	}

	/**
	 * @dataProvider getCommonPreprocessingChangeData
	 */
	public function testPageMassUpdateItems_ChangePreprocessing($data) {
		$this->executeItemsPreprocessingMassUpdate($data);
	}
}

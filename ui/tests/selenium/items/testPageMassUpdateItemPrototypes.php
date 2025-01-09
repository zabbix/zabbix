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
				'delay' => '1m'
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '2_Item',
				'key_' => '2agent[{#KEY}]',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '2m'
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '3_SNMP_trap',
				'key_' => 'snmptrap[{#KEY1}]',
				'type' => 17,
				'value_type' => 0,
				'interfaceid' => self::SNMP2_INTERFACE_ID
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '4_SNMP_trap',
				'key_' => 'snmptrap[{#KEY2}]',
				'type' => 17,
				'value_type' => 1,
				'interfaceid' => self::SNMP2_INTERFACE_ID
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
				'ipmi_sensor' => 'temp'
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
				'ipmi_sensor' => 'temp'
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
				'snmp_oid' => '.1.3.6.1.2.1.1.1.0'
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
				'snmp_oid' => '.1.3.8.1.2.1.1.1.0'
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
				'key_' => 'ssh.run[{#KEY2}]',
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
				'key_' => 'db.odbc.select[{#KEY2}]',
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
				'ruleid' => self::RULEID,
				'name' => '15_Calculated',
				'key_' => 'calculated1[{#KEY}]',
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
				'ruleid' => self::RULEID,
				'name' => '16_Calculated',
				'key_' => 'calculated2[{#KEY}]',
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
				'ruleid' => self::RULEID,
				'name' => '17_Script',
				'key_' => 'script1[{#KEY}]',
				'type' => 21,
				'value_type' => 0,
				'delay' => '15s',
				'timeout' => '13s',
				'params' => 'test Script 1'
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '18_Script',
				'key_' => 'script2[{#KEY}]',
				'type' => 21,
				'value_type' => 0,
				'delay' => '14s',
				'timeout' => '13s',
				'params' => 'test Script 2'
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
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.1:10051'],
						'Create enabled' => ['id' => 'status', 'value' => 'No'],
						'Discover' => ['id' => 'discover', 'value' => 'No']
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
						'Host interface' => ['id' => 'interface-select', 'value' => '127.0.5.3:10053'],
						'Create enabled' => ['id' => 'status', 'value' => 'Yes'],
						'Discover' => ['id' => 'discover', 'value' => 'Yes']
					]
				]
			],
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
					'details' => 'Invalid parameter "/1/master_itemid": an item/item prototype ID is expected.'
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
	public function prepareItemPrototypeTagsPreprocessingData() {
		CDataHelper::call('itemprototype.create', [
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '1_Item_Tags_Preprocessing',
				'key_' => '1agent.preproc[{#KEY}]',
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
				'ruleid' => self::RULEID,
				'name' => '2_Item_Tags_Preprocessing',
				'key_' => '2agent.preproc[{#KEY}]',
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
				'ruleid' => self::RULEID,
				'name' => '1_Item_No_Tags_Preprocessing',
				'key_' => '1agent.no.preproc[{#KEY}]',
				'type' => 0,
				'value_type' => 0,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '1m'
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '2_Item_No_Tags_Preprocessing',
				'key_' => '2agent.no.preproc[{#KEY}]',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => self::AGENT_INTERFACE_ID,
				'delay' => '2m'
			],
			[
				'hostid' => self::HOSTID,
				'ruleid' => self::RULEID,
				'name' => '1_Item_Tags_replace',
				'key_' => '1agent.tags.replace[{#KEY}]',
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
				'ruleid' => self::RULEID,
				'name' => '2_Item_Tags_replace',
				'key_' => '2agent.tags.replace[{#KEY}]',
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
				'ruleid' => self::RULEID,
				'name' => '1_Item_Tags_remove',
				'key_' => '1agent.tags.remove[{#KEY}]',
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
				'ruleid' => self::RULEID,
				'name' => '2_Item_Tags_remove',
				'key_' => '2agent.tags.remove[{#KEY}]',
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
				'ruleid' => self::RULEID,
				'name' => '3_Item_Tags_remove',
				'key_' => '3agent.tags.remove[{#KEY}]',
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

	/**
	 * @onBeforeOnce prepareItemPrototypeTagsPreprocessingData
	 */
	public function testPageMassUpdateItemPrototypes_Cancel() {
		$this->executeMassUpdateCancel(true);
	}

	/**
	 * @dataProvider getCommonTagsChangeData
	 *
	 * @depends testPageMassUpdateItemPrototypes_Cancel
	 */
	public function testPageMassUpdateItemPrototypes_ChangeTags($data) {
		$this->executeItemsTagsMassUpdate($data, true);
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

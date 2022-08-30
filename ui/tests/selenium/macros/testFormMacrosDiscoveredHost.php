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


require_once dirname(__FILE__).'/../common/testFormMacros.php';

/**
 * @onBefore prepareDiscoveredHostMacrosData
 *
 * @backup hosts, config
 */
class testFormMacrosDiscoveredHost extends testFormMacros {

	use MacrosTrait;

	/**
	 * Parent hostid for macros test.
	 *
	 * @var integer
	 */
	protected static $hostid;

	/**
	 * Parent hostid for macros inheritance test.
	 *
	 * @var integer
	 */
	protected static $inherit_hostid;

	const DISCOVERED_HOST_UPDATE = 'Discovered host with macros 1 for update';
	const DISCOVERED_UPDATE_HOSTID = 90000080;
	const DISCOVERED_UPDATE_INTERFACEID = 90000081;
	const DISCOVERED_UPDATE_HOST_GROUPID = 90000082;

	const DISCOVERED_HOST_REMOVE = 'Discovered host with macros 2 for remove';
	const DISCOVERED_REMOVE_HOSTID = 90000083;
	const DISCOVERED_REMOVE_INTERFACEID = 90000084;
	const DISCOVERED_REMOVE_HOST_GROUPID = 90000085;

	const DISCOVERED_HOST_SECRET_LAYOUT = 'Discovered host with macros 3 for secret macros layout';
	const DISCOVERED_SECRET_LAYOUT_HOSTID = 90000086;
	const DISCOVERED_SECRET_LAYOUT_INTERFACEID = 90000087;
	const DISCOVERED_SECRET_LAYOUT_HOST_GROUPID = 90000088;

	const DISCOVERED_HOST_SECRET_CREATE = 'Discovered host with macros 4 for secret macros create';
	const DISCOVERED_SECRET_CREATE_HOSTID = 90000089;
	const DISCOVERED_SECRET_CREATE_INTERFACEID = 90000090;
	const DISCOVERED_SECRET_CREATE_HOST_GROUPID = 90000091;

	const DISCOVERED_HOST_SECRET_REVERT = 'Discovered host with macros 5 for secret macros revert';
	const DISCOVERED_SECRET_REVERT_HOSTID = 90000092;
	const DISCOVERED_SECRET_REVERT_INTERFACEID = 90000093;
	const DISCOVERED_SECRET_REVERT_HOST_GROUPID = 90000094;

	const DISCOVERED_HOST_INHERIT = 'key Discovered host for macros inheritance';
	const DISCOVERED_INHERIT_HOSTID = 90000095;
	const DISCOVERED_INHERIT_INTERFACEID = 90000096;
	const DISCOVERED_INHERIT_HOST_GROUPID = 90000097;

//	/**
//	 * The id of the host for removing inherited macros.
//	 *
//	 * @var integer
//	 */
//	protected static $hostid_remove_inherited;

	public $macro_resolve = '{$X_SECRET_HOST_MACRO_2_RESOLVE}';
	public $macro_resolve_hostid = 99135;

	public $vault_object = 'host';
	public $hashi_error_field = '/1/macros/3/value';
	public $cyber_error_field = '/1/macros/4/value';
	public $update_vault_macro = '{$VAULT_HOST_MACRO3_CHANGED}';
	public $vault_macro_index = 2;

	public $revert_macro_1 = '{$SECRET_HOST_MACRO_REVERT}';
	public $revert_macro_2 = '{$SECRET_HOST_MACRO_2_TEXT_REVERT}';
	public $revert_macro_object = 'host';

	/**
	 * Create new macros for host.
	 */
	public function prepareDiscoveredHostMacrosData() {
		$hosts = CDataHelper::call('host.create', [
			[
				'host' => 'Parent host for discovered hosts macros',
				'groups' => [
					['groupid' => 4]
				],
				'macros' => [
					[
						'macro' => '{$MACRO1}',
						'value' => '1'
					],
					[
						'macro' => '{$MACRO2}',
						'value' => '2'
					]
				],
				'interfaces' => [
					'type'=> 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => 10050
				]
			],
			[
				'host' => 'Parent host for macros inheritance',
				'groups' => [
					['groupid' => 4]
				],
				'macros' => [
//					[
//						'macro' => '{$HOST_MACRO}',
//						'value' => 'host_macro_value'
//					],
//					[
//						'macro' => '{$HOST_SECRET}',
//						'value' => 'host_secret_value',
//						'type' => 1
//					],
//					[
//						'macro' => '{$HOST_VAULT}',
//						'value' => 'host/vault:key',
//						'type' => 2
//					]
				],
				'interfaces' => [
					'type'=> 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => 10050
				]
			]
		]);

		self::$hostid = $hosts['hostids'][0];
		self::$inherit_hostid = $hosts['hostids'][1];

		$interfaceid = CDBHelper::getValue('SELECT interfaceid FROM interface WHERE hostid='.zbx_dbstr(self::$hostid));
		$inherit_interfaceid = CDBHelper::getValue('SELECT interfaceid FROM interface WHERE hostid='.zbx_dbstr(self::$inherit_hostid));

		// Create discovery rules.
		$llds = CDataHelper::call('discoveryrule.create', [
			[
				'name' => 'LLD for Discovered host macros tests',
				'key_' => 'vfs.fs.discovery',
				'hostid' => self::$hostid,
				'type' => ITEM_TYPE_ZABBIX,
				'interfaceid' => $interfaceid,
				'delay' => 30
			],
			[
				'name' => 'LLD for Discovered host inherited macros tests',
				'key_' => 'vfs.fs.discovery',
				'hostid' => self::$inherit_hostid,
				'type' => ITEM_TYPE_ZABBIX,
				'interfaceid' => $inherit_interfaceid,
				'delay' => 30
			]
		]);
		$lldid = $llds['itemids'][0];
		$inherit_lldid = $llds['itemids'][1];

		// Create host prototype.
		$host_prototypes = CDataHelper::call('hostprototype.create', [
			[
				'host' => 'Discovered host with macros {#KEY} for update',
				'ruleid' => $lldid,
				'groupLinks' => [['groupid' => 4]],
				'macros' => [
					[
						'macro' => '{$MACRO1}',
						'value' => ''
					],
					[
						'macro' => '{$MACRO2}',
						'value' => ''
					]
				]
			],
			[
				'host' => 'Discovered host with macros {#KEY} for remove',
				'ruleid' => $lldid,
				'groupLinks' => [['groupid' => 4]],
				'macros' => [
					[
						'macro' => '{$TEST_MACRO123}',
						'value' => 'test123',
						'description' => 'description 123'
					],
					[
						'macro' => '{$MACRO_FOR_DELETE_HOST1}',
						'value' => 'test1',
						'description' => 'description 1'
					],
					[
						'macro' => '{$MACRO_FOR_DELETE_HOST2}',
						'value' => 'test2',
						'description' => 'description 2'
					],
					[
						'macro' => '{$MACRO_FOR_DELETE_GLOBAL1}',
						'value' => 'test global 1',
						'description' => 'global description 1'
					],
					[
						'macro' => '{$MACRO_FOR_DELETE_GLOBAL2}',
						'value' => 'test global 2',
						'description' => 'global description 2'
					],
					[
						'macro' => '{$SNMP_COMMUNITY}',
						'value' => 'redefined value',
						'description' => 'redefined description'
					]
				]
			],
			[
				'host' => 'Discovered host with macros {#KEY} for secret macros layout',
				'ruleid' => $lldid,
				'groupLinks' => [['groupid' => 4]],
				'macros' => [
					[
						'macro' => '{$SECRET_HOST_MACRO}',
						'value' => 'some secret value',
						'description' => '',
						'type' => 1
					],
					[
						'macro' => '{$TEXT_HOST_MACRO}',
						'value' => 'some text value',
						'description' => ''
					],
					[
						'macro' => '{$VAULT_HOST_MACRO3}',
						'value' => 'secret/path:key',
						'description' => 'Change name, value, description',
						'type' => 2
					]
				]
			],
			[
				'host' => 'Discovered host with macros {#KEY} for secret macros create',
				'ruleid' => $lldid,
				'groupLinks' => [['groupid' => 4]],
			],
			[
				'host' => 'Discovered host with macros {#KEY} for secret macros revert',
				'ruleid' => $lldid,
				'groupLinks' => [['groupid' => 4]],
				'macros' => [
					[
						'macro' => '{$SECRET_HOST_MACRO_2_TEXT_REVERT}',
						'value' => 'Secret host value 2 text',
						'description' => 'Secret host macro that will be changed to text',
						'type' => 1
					],
					[
						'macro' => '{$SECRET_HOST_MACRO_REVERT}',
						'value' => 'Secret host value',
						'description' => 'Secret host macro description',
						'type' => 1
					],
					[
						'macro' => '{$SECRET_HOST_MACRO_UPDATE}',
						'value' => 'Secret host macro value',
						'description' => 'Secret host macro that is going to stay secret',
						'type' => 1
					],
					[
						'macro' => '{$SECRET_HOST_MACRO_UPDATE_2_TEXT}',
						'value' => 'Secret host value 2 B updated',
						'description' => 'Secret host macro that is going to be updated',
						'type' => 1
					],
					[
						'macro' => '{$TEXT_HOST_MACRO_2_SECRET}',
						'value' => 'Text host macro value',
						'description' => 'Text host macro that is going to become secret'
					],
					[
						'macro' => '{$X_SECRET_HOST_MACRO_2_RESOLVE}',
						'value' => 'Value 2 B resolved',
						'description' => 'Host macro to be resolved'
					]
				]
			],
			[
				'host' => '{#KEY} Discovered host for macros inheritance',
				'ruleid' => $lldid,
				'groupLinks' => [['groupid' => 4]],
				'macros' => [
					[
						'macro' => '{$TEST_MACRO123}',
						'value' => 'test123',
						'description' => 'description 123'
					],
					[
						'macro' => '{$MACRO_FOR_DELETE_HOST1}',
						'value' => 'test1',
						'description' => 'description 1'
					],
					[
						'macro' => '{$MACRO_FOR_DELETE_HOST2}',
						'value' => 'test2',
						'description' => 'description 2'
					],
					[
						'macro' => '{$MACRO_FOR_DELETE_GLOBAL1}',
						'value' => 'test global 1',
						'description' => 'global description 1'
					],
					[
						'macro' => '{$MACRO_FOR_DELETE_GLOBAL2}',
						'value' => 'test global 2',
						'description' => 'global description 2'
					],
					[
						'macro' => '{$SNMP_COMMUNITY}',
						'value' => 'redefined value',
						'description' => 'redefined description'
					]
//					[
//						'macro' => '{$PROTO_MACRO}',
//						'value' => 'proto_macro_value'
//					],
//					[
//						'macro' => '{$PROTO_SECRET}',
//						'value' => 'proto_secret_value',
//						'type' => 1
//					],
//					[
//						'macro' => '{$PROTO_VAULT}',
//						'value' => 'proto/vault:key',
//						'type' => 2
//					]
				]
			]
		]);

		$host_prototype_update_id = $host_prototypes['hostids'][0];
		$host_prototype_remove_id = $host_prototypes['hostids'][1];
		$host_prototype_layout_secret_id = $host_prototypes['hostids'][2];
		$host_prototype_revert_secret_id = $host_prototypes['hostids'][3];
		$host_prototype_create_secret_id = $host_prototypes['hostids'][4];
		$host_prototype_inherit_id = $host_prototypes['hostids'][5];

		// Emulate host discovery in DB.
		// 'Discovered host with macros {#KEY} for update'.
		DBexecute("INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (".zbx_dbstr(self::DISCOVERED_UPDATE_HOSTID).
				",".zbx_dbstr(self::DISCOVERED_HOST_UPDATE).",".zbx_dbstr(self::DISCOVERED_HOST_UPDATE).", 0, 4, '')"
		);
		DBexecute("INSERT INTO host_discovery (hostid, parent_hostid) VALUES (".zbx_dbstr(self::DISCOVERED_UPDATE_HOSTID).", ".
				zbx_dbstr($host_prototype_update_id).")"
		);
		DBexecute("INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (".
				zbx_dbstr(self::DISCOVERED_UPDATE_INTERFACEID).",".zbx_dbstr(self::DISCOVERED_UPDATE_HOSTID).", 1, 1, 1, '127.0.0.1', '', '10050')"
		);
		DBexecute("INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (".zbx_dbstr(self::DISCOVERED_UPDATE_HOST_GROUPID).
				", ".zbx_dbstr(self::DISCOVERED_UPDATE_HOSTID).", 4)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, automatic) VALUES (990100, ".
				zbx_dbstr(self::DISCOVERED_UPDATE_HOSTID).", '\{\$MACRO1\}', '', '', 1)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, automatic) VALUES (990101, ".
				zbx_dbstr(self::DISCOVERED_UPDATE_HOSTID).", '\{\$MACRO2\}', '', '', 1)"
		);

		// 'Discovered host with macros {#KEY} for remove'.
		DBexecute("INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (".zbx_dbstr(self::DISCOVERED_REMOVE_HOSTID).
				",".zbx_dbstr(self::DISCOVERED_HOST_REMOVE).",".zbx_dbstr(self::DISCOVERED_HOST_REMOVE).", 0, 4, '')"
		);
		DBexecute("INSERT INTO host_discovery (hostid, parent_hostid) VALUES (".zbx_dbstr(self::DISCOVERED_REMOVE_HOSTID).", ".
				zbx_dbstr($host_prototype_remove_id).")"
		);
		DBexecute("INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (".
				zbx_dbstr(self::DISCOVERED_REMOVE_INTERFACEID).",".zbx_dbstr(self::DISCOVERED_REMOVE_HOSTID).", 1, 1, 1, '127.0.0.1', '', '10050')"
		);
		DBexecute("INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (".zbx_dbstr(self::DISCOVERED_REMOVE_HOST_GROUPID).
				", ".zbx_dbstr(self::DISCOVERED_REMOVE_HOSTID).", 4)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, automatic) VALUES (990102, ".
				zbx_dbstr(self::DISCOVERED_REMOVE_HOSTID).", '\{\$TEST_MACRO123\}', 'test123', 'description 123', 1)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, automatic) VALUES (990103, ".
				zbx_dbstr(self::DISCOVERED_REMOVE_HOSTID).", '\{\$MACRO_FOR_DELETE_HOST1\}', 'test1', 'description 1', 1)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, automatic) VALUES (990104, ".
				zbx_dbstr(self::DISCOVERED_REMOVE_HOSTID).", '\{\$MACRO_FOR_DELETE_HOST2\}', 'test2', 'description 2', 1)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, automatic) VALUES (990105, ".
				zbx_dbstr(self::DISCOVERED_REMOVE_HOSTID).", '\{\$MACRO_FOR_DELETE_GLOBAL1\}', 'test global 1', 'global description 1', 1)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, automatic) VALUES (990106, ".
				zbx_dbstr(self::DISCOVERED_REMOVE_HOSTID).", '\{\$MACRO_FOR_DELETE_GLOBAL2\}', 'test global 2', 'global description 2', 1)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, automatic) VALUES (990107, ".
				zbx_dbstr(self::DISCOVERED_REMOVE_HOSTID).", '\{\$SNMP_COMMUNITY\}', 'redefined value', 'redefined description', 1)"
		);

		// 'Discovered host with macros {#KEY} for secret macros layout'.
		DBexecute("INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (".zbx_dbstr(self::DISCOVERED_SECRET_LAYOUT_HOSTID).
				",".zbx_dbstr(self::DISCOVERED_HOST_SECRET_LAYOUT).",".zbx_dbstr(self::DISCOVERED_HOST_SECRET_LAYOUT).", 0, 4, '')"
		);
		DBexecute("INSERT INTO host_discovery (hostid, parent_hostid) VALUES (".zbx_dbstr(self::DISCOVERED_SECRET_LAYOUT_HOSTID).", ".
				zbx_dbstr($host_prototype_layout_secret_id).")"
		);
		DBexecute("INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (".zbx_dbstr(self::DISCOVERED_SECRET_LAYOUT_INTERFACEID).
				",".zbx_dbstr(self::DISCOVERED_SECRET_LAYOUT_HOSTID).", 1, 1, 1, '127.0.0.1', '', '10050')"
		);
		DBexecute("INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (".zbx_dbstr(self::DISCOVERED_SECRET_LAYOUT_HOST_GROUPID).
				", ".zbx_dbstr(self::DISCOVERED_SECRET_LAYOUT_HOSTID).", 4)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type, automatic) VALUES (990108, ".
				zbx_dbstr(self::DISCOVERED_SECRET_LAYOUT_HOSTID).", '\{\$SECRET_HOST_MACRO\}', 'some secret value', '', 1, 1)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type, automatic) VALUES (990109, ".
				zbx_dbstr(self::DISCOVERED_SECRET_LAYOUT_HOSTID).", '\{\$TEXT_HOST_MACRO\}', 'some text value', '', 0, 1)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type, automatic) VALUES (990110, ".
				zbx_dbstr(self::DISCOVERED_SECRET_LAYOUT_HOSTID).", '\{\$VAULT_HOST_MACRO3\}', 'secret/path:key', 'Change name, value, description', 2, 1)"
		);

		// 'Discovered host with macros {#KEY} for secret macros revert'.
		DBexecute("INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (".zbx_dbstr(self::DISCOVERED_SECRET_REVERT_HOSTID).
				",".zbx_dbstr(self::DISCOVERED_HOST_SECRET_REVERT).",".zbx_dbstr(self::DISCOVERED_HOST_SECRET_REVERT).", 0, 4, '')"
		);
		DBexecute("INSERT INTO host_discovery (hostid, parent_hostid) VALUES (".zbx_dbstr(self::DISCOVERED_SECRET_REVERT_HOSTID).", ".
				zbx_dbstr($host_prototype_revert_secret_id).")"
		);
		DBexecute("INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (".zbx_dbstr(self::DISCOVERED_SECRET_REVERT_INTERFACEID).
				",".zbx_dbstr(self::DISCOVERED_SECRET_REVERT_HOSTID).", 1, 1, 1, '127.0.0.1', '', '10050')"
		);
		DBexecute("INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (".zbx_dbstr(self::DISCOVERED_SECRET_REVERT_HOST_GROUPID).
				", ".zbx_dbstr(self::DISCOVERED_SECRET_REVERT_HOSTID).", 4)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type, automatic) VALUES (99011, ".
				zbx_dbstr(self::DISCOVERED_SECRET_REVERT_HOSTID).", '\{\$SECRET_HOST_MACRO_2_TEXT_REVERT\}', 'Secret host value 2 text', 'Secret host macro that will be changed to text', 1, 1)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type, automatic) VALUES (990112, ".
				zbx_dbstr(self::DISCOVERED_SECRET_REVERT_HOSTID).", '\{\$SECRET_HOST_MACRO_REVERT\}', 'Secret host value', 'Secret host macro description', 1, 1)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type, automatic) VALUES (990113, ".
				zbx_dbstr(self::DISCOVERED_SECRET_REVERT_HOSTID).", '\{\$SECRET_HOST_MACRO_UPDATE\}', 'Secret host macro value', 'Secret host macro that is going to stay secret', 1, 1)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type, automatic) VALUES (990114, ".
				zbx_dbstr(self::DISCOVERED_SECRET_REVERT_HOSTID).", '\{\$SECRET_HOST_MACRO_UPDATE_2_TEXT\}', 'Secret host value 2 B updated', 'Secret host macro that is going to be updated', 1, 1)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type, automatic) VALUES (990115, ".
				zbx_dbstr(self::DISCOVERED_SECRET_REVERT_HOSTID).", '\{\$TEXT_HOST_MACRO_2_SECRET\}', 'Text host macro value', 'Text host macro that is going to become secret', 0, 1)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type, automatic) VALUES (990116, ".
				zbx_dbstr(self::DISCOVERED_SECRET_REVERT_HOSTID).", '\{\$X_SECRET_HOST_MACRO_2_RESOLVE\}', 'Value 2 B resolved', 'Host macro to be resolved', 0, 1)"
		);

		// 'Discovered host with macros {#KEY} for secret macros create'.
		DBexecute("INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (".zbx_dbstr(self::DISCOVERED_SECRET_CREATE_HOSTID).
				",".zbx_dbstr(self::DISCOVERED_HOST_SECRET_CREATE).",".zbx_dbstr(self::DISCOVERED_HOST_SECRET_CREATE).", 0, 4, '')"
		);
		DBexecute("INSERT INTO host_discovery (hostid, parent_hostid) VALUES (".zbx_dbstr(self::DISCOVERED_SECRET_CREATE_HOSTID).", ".
				zbx_dbstr($host_prototype_create_secret_id).")"
		);
		DBexecute("INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (".zbx_dbstr(self::DISCOVERED_SECRET_CREATE_INTERFACEID).
				",".zbx_dbstr(self::DISCOVERED_SECRET_CREATE_HOSTID).", 1, 1, 1, '127.0.0.1', '', '10050')"
		);
		DBexecute("INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (".zbx_dbstr(self::DISCOVERED_SECRET_CREATE_HOST_GROUPID).
				", ".zbx_dbstr(self::DISCOVERED_SECRET_CREATE_HOSTID).", 4)"
		);

		// {#KEY} Discovered host for macros inheritance'.
		DBexecute("INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (".zbx_dbstr(self::DISCOVERED_INHERIT_HOSTID).
				",".zbx_dbstr(self::DISCOVERED_HOST_INHERIT).",".zbx_dbstr(self::DISCOVERED_HOST_INHERIT).", 0, 4, '')"
		);
		DBexecute("INSERT INTO host_discovery (hostid, parent_hostid) VALUES (".zbx_dbstr(self::DISCOVERED_INHERIT_HOSTID).", ".
				zbx_dbstr($host_prototype_inherit_id).")"
		);
		DBexecute("INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (".
				zbx_dbstr(self::DISCOVERED_INHERIT_INTERFACEID).",".zbx_dbstr(self::DISCOVERED_INHERIT_HOSTID).", 1, 1, 1, '127.0.0.1', '', '10050')"
		);
		DBexecute("INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (".zbx_dbstr(self::DISCOVERED_INHERIT_HOST_GROUPID).
				", ".zbx_dbstr(self::DISCOVERED_INHERIT_HOSTID).", 4)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, automatic) VALUES (990117, "
				.zbx_dbstr(self::DISCOVERED_INHERIT_HOSTID).", '\{\$TEST_MACRO123\}', 'test123', 'description 123', 1)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, automatic) VALUES (990118, "
				.zbx_dbstr(self::DISCOVERED_INHERIT_HOSTID).", '\{\$MACRO_FOR_DELETE_HOST1\}', 'test1', 'description 1', 1)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, automatic) VALUES (990119, "
				.zbx_dbstr(self::DISCOVERED_INHERIT_HOSTID).", '\{\$MACRO_FOR_DELETE_HOST2\}', 'test2', 'description 2', 1)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, automatic) VALUES (990120, "
				.zbx_dbstr(self::DISCOVERED_INHERIT_HOSTID).", '\{\$MACRO_FOR_DELETE_GLOBAL1\}', 'test global 1', 'global description 1', 1)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, automatic) VALUES (990121, "
				.zbx_dbstr(self::DISCOVERED_INHERIT_HOSTID).", '\{\$MACRO_FOR_DELETE_GLOBAL2\}', 'test global 2', 'global description 2', 1)"
		);
		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, automatic) VALUES (990122, "
				.zbx_dbstr(self::DISCOVERED_INHERIT_HOSTID).", '\{\$SNMP_COMMUNITY\}', 'redefined value', 'redefined description', 1)"
		);


//		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, automatic) VALUES (990108, "
//				.zbx_dbstr(self::DISCOVERED_INHERIT_HOSTID).", '\{\$HOST_MACRO\}', 'host_macro_value', '', 1)"
//		);
//		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, automatic, type) VALUES (990109, "
//				.zbx_dbstr(self::DISCOVERED_INHERIT_HOSTID).", '\{\$HOST_SECRET\}', 'host_macro_secret', '', 1, 1)"
//		);
//		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, automatic, type) VALUES (990110, "
//				.zbx_dbstr(self::DISCOVERED_INHERIT_HOSTID).", '\{\$HOST_VAULT\}', 'host_macro_vault', '', 1, 2)"
//		);
//		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, automatic) VALUES (990111, "
//				.zbx_dbstr(self::DISCOVERED_INHERIT_HOSTID).", '\{\$PROTO_MACRO\}', 'proto_macro_value', '', 1)"
//		);
//		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, automatic, type) VALUES (990112, "
//				.zbx_dbstr(self::DISCOVERED_INHERIT_HOSTID).", '\{\$PROTO_SECRET\}', 'proto_macro_secret', '', 1, 1)"
//		);
//		DBexecute("INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, automatic, type) VALUES (990113, "
//				.zbx_dbstr(self::DISCOVERED_INHERIT_HOSTID).", '\{\$PROTO_VAULT\}', 'proto_macro_vault', '', 1, 2)"
//		);
	}

	public static function getDiscoveredHostUpdateMacrosData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'discovered_first_case' => true,
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'value' => 'updated value1',
							'description' => 'updated description 1'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'value' => 'Updated value 2',
							'description' => 'Updated description 2'
						]
					],
					'expected_macros' => [
						[
							'macro' => '{$MACRO1}'
						],
						[
							'macro' => '{$MACRO2}'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$UPDATED_MACRO1}',
							'value' => '',
							'description' => ''
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$UPDATED_MACRO2}',
							'value' => 'Updated Value 2',
							'description' => ''
						],
						[
							'macro' => '{$UPDATED_MACRO3}',
							'value' => '',
							'description' => 'Updated Description 3'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$MACRO:A}',
							'value' => '{$MACRO:B}',
							'description' => '{$MACRO:C}'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$UPDATED_MACRO_1}',
							'value' => '',
							'description' => 'DESCRIPTION'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'macro' => '{$UPDATED_MACRO_2}',
							'value' => 'Значение',
							'description' => 'Описание'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$lowercase}',
							'value' => 'lowercase_value',
							'description' => 'UPPERCASE DESCRIPTION'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$MACRO:regex:"^[a-z]"}',
							'value' => 'regex',
							'description' => ''
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'macro' => '{$MACRO:regex:^[0-9a-z]}',
							'value' => '',
							'description' => 'DESCRIPTION'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'Without dollar in Macros',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{MACRO}'
						]
					],
					'error' => 'Invalid parameter "/1/macros/1/macro": incorrect syntax near "MACRO}".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With empty Macro',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '',
							'value' => 'Macro_Value',
							'description' => 'Macro Description'
						]
					],
					'error'  => 'Invalid parameter "/1/macros/1/macro": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With two dollars in MACROS',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$$MACRO}'
						]
					],
					'error' => 'Invalid parameter "/1/macros/1/macro": incorrect syntax near "$MACRO}'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With wrong symbols in MACROS',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$MAC%^}'
						]
					],
					'error' => 'Invalid parameter "/1/macros/1/macro": incorrect syntax near "%^}".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With LLD macro in MACROS',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{#LLD_MACRO}'
						]
					],
					'error'  => 'Invalid parameter "/1/macros/1/macro": incorrect syntax near "#LLD_MACRO}".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With repeated Macros',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$MACRO}',
							'value' => 'Macro_Value_1',
							'description' => 'Macro Description_1'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$MACRO}',
							'value' => 'Macro_Value_2',
							'description' => 'Macro Description_2'
						]
					],
					'error'  => 'Invalid parameter "/1/macros/2": value (macro)=({$MACRO}) already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With repeated regex Macros',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$M:regex:"[a-z]"}',
							'value' => 'Macro_Value_1',
							'description' => 'Macro Description_1'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$M:regex:"[a-z]"}',
							'value' => 'Macro_Value_2',
							'description' => 'Macro Description_2'
						]
					],
					'error'  => 'Invalid parameter "/1/macros/2": value (macro)=({$M:regex:"[a-z]"}) already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With repeated regex Macros and quotes',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$MACRO:regex:"^[0-9].*$"}',
							'value' => 'Macro_Value_1',
							'description' => 'Macro Description_1'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$MACRO:regex:^[0-9].*$}',
							'value' => 'Macro_Value_2',
							'description' => 'Macro Description_2'
						]
					],
					'error'  => 'Invalid parameter "/1/macros/2": value (macro)=({$MACRO:regex:^[0-9].*$}) already exists.'
				]
			]
		];
	}

	/**
	 * @dataProvider getDiscoveredHostUpdateMacrosData
	 */
	public function testFormMacrosDiscoveredHost_Update($data) {
		$this->checkMacros($data, 'host', self::DISCOVERED_HOST_UPDATE, true, false, null, true);
	}

	public function testFormMacrosDiscoveredHost_RemoveAll() {
		$this->checkRemoveAll(self::DISCOVERED_HOST_REMOVE, 'host');
	}

//	/**
//	 * @dataProvider getCheckInheritedMacrosData
//	 */
//	public function testFormMacrosDiscoveredHost_ChangeInheritedMacro($data) {
//		$this->checkChangeInheritedMacros($data, 'host');
//	}

	/**
	 * @dataProvider getRemoveInheritedMacrosData
	 */
	public function testFormMacrosDiscoveredHost_RemoveInheritedMacro($data) {
		$this->checkRemoveInheritedMacros($data, 'host', self::DISCOVERED_INHERIT_HOSTID, false, null,
				self::DISCOVERED_HOST_INHERIT
		);
	}

	public function getSecretMacrosLayoutData() {
		return [
			[
				[
					'macro' => '{$SECRET_HOST_MACRO}',
					'type' => 'Secret text'
				]
			],
			[
				[
					'macro' => '{$SECRET_HOST_MACRO}',
					'type' => 'Secret text',
					'chenge_type' => true
				]
			],
			[
				[
					'macro' => '{$TEXT_HOST_MACRO}',
					'type' => 'Text'
				]
			],
			[
				[
					'global' => true,
					'macro' => '{$X_TEXT_2_SECRET}',
					'type' => 'Text'
				]
			],
			[
				[
					'global' => true,
					'macro' => '{$X_SECRET_2_SECRET}',
					'type' => 'Secret text'
				]
			]
		];
	}

	/**
	 * @dataProvider getSecretMacrosLayoutData
	 */
	public function testFormMacrosDiscoveredHost_CheckSecretMacrosLayout($data) {
		$this->checkSecretMacrosLayout($data, 'zabbix.php?action=host.view', 'hosts', self::DISCOVERED_HOST_SECRET_LAYOUT, true);
	}

	public function getCreateSecretMacrosData() {
		return [
			[
				[
					'macro_fields' => [
						'action' => USER_ACTION_UPDATE,
						'index' => 0,
						'macro' => '{$SECRET_MACRO}',
						'value' => [
							'text' => 'host secret value',
							'type' => 'Secret text'
						],
						'description' => 'secret description'
					],
					'check_default_type' => true
				]
			],
			[
				[
					'macro_fields' => [
						'macro' => '{$TEXT_MACRO}',
						'value' => [
							'text' => 'host plain text value',
							'type' => 'Secret text'
						],
						'description' => 'plain text description'
					],
					'back_to_text' => true
				]
			],
			[
				[
					'macro_fields' => [
						'macro' => '{$SECRET_EMPTY_MACRO}',
						'value' => [
							'text' => '',
							'type' => 'Secret text'
						],
						'description' => 'secret empty value'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateSecretMacrosData
	 */
	public function testFormMacrosDiscoveredHost_CreateSecretMacros($data) {
		$this->createSecretMacros($data, 'zabbix.php?action=host.view', 'hosts', self::DISCOVERED_HOST_SECRET_CREATE);
	}

	/**
	 * @dataProvider getRevertSecretMacrosData
	 */
	public function testFormMacrosDiscoveredHost_RevertSecretMacroChanges($data) {
		$this->revertSecretMacroChanges($data, 'zabbix.php?action=host.view', 'hosts', self::DISCOVERED_HOST_SECRET_REVERT);
	}

	public function getUpdateSecretMacrosData() {
		return [
			[
				[
					'fields' => [
						'action' => USER_ACTION_UPDATE,
						'index' => 2,
						'value' => [
							'text' => 'Updated secret value'
						]
					],
					'expected' => [
						'macro' => '{$SECRET_HOST_MACRO_UPDATE}',
						'value' => [
							'text' => 'Updated secret value'
						]
					]
				]
			],
			[
				[
					'fields' => [
						'action' => USER_ACTION_UPDATE,
						'index' => 3,
						'value' => [
							'text' => 'New text value',
							'type' => 'Text'
						]
					],
					'expected' => [
						'macro' => '{$SECRET_HOST_MACRO_UPDATE_2_TEXT}',
						'value' => [
							'text' => 'New text value',
							'type' => 'Text'
						]
					]
				]
			],
			[
				[
					'fields' => [
						'action' => USER_ACTION_UPDATE,
						'index' => 4,
						'value' => [
							'text' => 'New secret value',
							'type' => 'Secret text'
						]
					],
					'expected' => [
						'macro' => '{$TEXT_HOST_MACRO_2_SECRET}',
						'value' => [
							'text' => 'New secret value',
							'type' => 'Secret text'
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateSecretMacrosData
	 */
	public function testFormMacrosDiscoveredHost_UpdateSecretMacros($data) {
		$this->updateSecretMacros($data, 'zabbix.php?action=host.view', 'hosts', self::DISCOVERED_HOST_SECRET_REVERT, true);
	}

	/**
	 * Check Vault macros validation.
	 */
	public function testFormMacrosDiscoveredHost_checkVaultValidation() {
		$this->checkVaultValidation('zabbix.php?action=host.view', 'hosts', 'Host for different items types');
	}

	/**
	 * @dataProvider getCreateVaultMacrosData
	 *
	 */
	public function testFormMacrosDiscoveredHost_CreateVaultMacros($data) {
		$host = ($data['vault'] === 'Hashicorp') ? 'Host 1 from first group' : 'Empty host';
		$this->createVaultMacros($data, 'zabbix.php?action=host.view', 'hosts', $host);
	}

	/**
	 * @dataProvider getUpdateVaultMacrosData
	 */
	public function testFormMacrosDiscoveredHost_UpdateVaultMacros($data) {
		$this->updateVaultMacros($data, 'zabbix.php?action=host.view', 'hosts', 'Host for suppression');
	}
}

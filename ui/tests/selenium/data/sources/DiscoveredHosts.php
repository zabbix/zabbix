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


class DiscoveredHosts {

	const DISCOVERED_HOST = 'Discovered host from prototype 1';
	const DISCOVERED_HOST2 = 'Discovered host from prototype 11';
	const DISCOVERED_HOSTID = 90000079;
	const DISCOVERED_HOSTID2 = 90000080;
	const DISCOVERED_INTERFACEID = 90000080;
	const DISCOVERED_HOST_GROUPID = 90000081;
	const DISCOVERED_HOST_GROUPID2 = 90000082;

	/**
	 * Parent hostid.
	 *
	 * @var integer
	 */
	protected static $hostid;

	/**
	 * Create data for testFormHost, Discovered host scenario.
	 *
	 * @return array
	 */
	public static function load() {
		$hosts = CDataHelper::call('host.create', [
			'host' => 'Test of discovered host',
			'groups' => [
				['groupid' => 4]
			],
			'interfaces' => [
				'type'=> 1,
				'main' => 1,
				'useip' => 1,
				'ip' => '127.0.0.1',
				'dns' => '',
				'port' => 10050
			]
		]);

		self::$hostid = $hosts['hostids'][0];
		$interfaceid = CDBHelper::getValue('SELECT interfaceid FROM interface WHERE hostid='.zbx_dbstr(self::$hostid));

		// Create discovery rule.
		$llds = CDataHelper::call('discoveryrule.create', [
			'name' => 'LLD for Discovered host tests',
			'key_' => 'vfs.fs.discovery',
			'hostid' => self::$hostid,
			'type' => ITEM_TYPE_ZABBIX,
			'interfaceid' => $interfaceid,
			'delay' => 30
		]);

		$lldid = $llds['itemids'][0];

		// Create host prototype.
		$host_prototypes = CDataHelper::call('hostprototype.create', [
			'host' => 'Host created from host prototype {#KEY}',
			'ruleid' => $lldid,
			'groupLinks' => [['groupid' => 4]],
			'tags' => [
				'tag' => 'prototype',
				'value' => 'true'
			]
		]);

		$host_prototypeid = $host_prototypes['hostids'][0];

		// Emulate host discovery in DB.
		DBexecute("INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (".zbx_dbstr(self::DISCOVERED_HOSTID).
				",".zbx_dbstr(self::DISCOVERED_HOST).",".zbx_dbstr(self::DISCOVERED_HOST).", 0, 4, '')"
		);
		DBexecute("INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (".zbx_dbstr(self::DISCOVERED_HOSTID2).
				",".zbx_dbstr(self::DISCOVERED_HOST2).",".zbx_dbstr(self::DISCOVERED_HOST2).", 0, 4, '')"
		);
		DBexecute("INSERT INTO host_discovery (hostid, parent_hostid) VALUES (".zbx_dbstr(self::DISCOVERED_HOSTID).", ".
				zbx_dbstr($host_prototypeid).")"
		);
		DBexecute("INSERT INTO host_discovery (hostid, parent_hostid) VALUES (".zbx_dbstr(self::DISCOVERED_HOSTID2).", ".
				zbx_dbstr($host_prototypeid).")"
		);
		DBexecute("INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (".
				zbx_dbstr(self::DISCOVERED_INTERFACEID).",".zbx_dbstr(self::DISCOVERED_HOSTID).", 1, 1, 1, '127.0.0.1', '', '10050')"
		);
		DBexecute("INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (".zbx_dbstr(self::DISCOVERED_HOST_GROUPID).
				", ".zbx_dbstr(self::DISCOVERED_HOSTID).", 4)"
		);
		DBexecute("INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (".zbx_dbstr(self::DISCOVERED_HOST_GROUPID2).
				", ".zbx_dbstr(self::DISCOVERED_HOSTID2).", 4)"
		);
		DBexecute("INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (90000082, ".
				zbx_dbstr(self::DISCOVERED_HOSTID).", 'action', 'update')"
		);
		DBexecute("INSERT INTO host_tag (hosttagid, hostid, tag) VALUES (90000083, ".
				zbx_dbstr(self::DISCOVERED_HOSTID).", 'tag without value')"
		);
		DBexecute("INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (90000084, ".
				zbx_dbstr(self::DISCOVERED_HOSTID).", 'test', 'update')"
		);
		DBexecute("INSERT INTO host_tag (hosttagid, hostid, tag, value, automatic) VALUES (90000085, ".
				zbx_dbstr(self::DISCOVERED_HOSTID2).", 'discovered', 'true', 1)"
		);
		DBexecute("INSERT INTO host_tag (hosttagid, hostid, tag, value, automatic) VALUES (90000086, ".
				zbx_dbstr(self::DISCOVERED_HOSTID2).", 'discovered without tag value', '', 1)"
		);

		// Create templates.
		$templates = CDataHelper::createTemplates([
			[
				'host' => 'Test of discovered host Template',
				'groups' => ['groupid' => 1],
				'items' => [
					[
						'name' => 'Template item',
						'key_' => 'trap.template',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Template item with tag',
						'key_' => 'template.tags.clone',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'tags' => [
							[
								'tag' => 'a',
								'value' => ':a'
							]
						]
					]
				],
				'discoveryrules' => [
					[
						'name' => 'Template discovery rule',
						'key_' => 'vfs.fs.discovery',
						'type' => ITEM_TYPE_TRAPPER
					]
				]
			],
			[
				'host' => 'Test of discovered host 1 template for unlink',
				'groups' => ['groupid' => 1],
				'items' => [
					[
						'name' => 'Template1 item1',
						'key_' => 'trap.template1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Template1 item2',
						'key_' => 'template.item1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				],
				'discoveryrules' => [
					[
						'name' => 'Template1 discovery rule',
						'key_' => 'vfs.fs.discovery',
						'type' => ITEM_TYPE_TRAPPER
					]
				]
			],
			[
				'host' => 'Test of discovered host 2 template for clear',
				'groups' => ['groupid' => 1],
				'items' => [
					[
						'name' => 'Template2 item1',
						'key_' => 'trap.template2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Template2 item2',
						'key_' => 'template.item2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				],
				'discoveryrules' => [
					[
						'name' => 'Template2 discovery rule',
						'key_' => 'vfs.fs.discovery',
						'type' => ITEM_TYPE_TRAPPER
					]
				]
			]
		]);

		CDataHelper::call('graph.create', [
			[
				'name' => 'Template graph',
				'width' => 850,
				'height' => 480,
				'gitems' => [
					[
						'itemid' => $templates['itemids']['Test of discovered host Template:trap.template'] ,
						'color'=> '00AA00'
					]
				]
			],
			[
				'name' => 'Template1 graph',
				'width' => 850,
				'height' => 480,
				'gitems' => [
					[
						'itemid' => $templates['itemids']['Test of discovered host 1 template for unlink:trap.template1'],
						'color'=> 'FFAA00'
					]
				]
			],
			[
				'name' => 'Template2 graph',
				'width' => 850,
				'height' => 480,
				'gitems' => [
					[
						'itemid' => $templates['itemids']['Test of discovered host 2 template for clear:template.item2'],
						'color'=> '99AA00'
					]
				]
			]
		]);

		CDataHelper::call('trigger.create', [
			[
				'description' => 'Template trigger',
				'expression' => 'last(/Test of discovered host Template/trap.template)=0',
				'priority' => 3
			],
			[
				'description' => 'Template1 trigger',
				'expression' => 'last(/Test of discovered host 1 template for unlink/trap.template1)=0',
				'priority' => 1
			],
			[
				'description' => 'Template2 trigger',
				'expression' => 'last(/Test of discovered host 2 template for clear/trap.template2)=0',
				'priority' => 4
			]
		]);

		CDataHelper::call('httptest.create', [
			[
				'name' => 'Template web scenario',
				'hostid' => $templates['templateids']['Test of discovered host Template'],
				'steps' => [
					[
						'name' => 'Test name',
						'url' => 'http://example.com',
						'status_codes' => '200',
						'no' => '1'
					]
				]
			],
			[
				'name' => 'Template web scenario 1',
				'hostid' => $templates['templateids']['Test of discovered host 1 template for unlink'],
				'steps' => [
					[
						'name' => 'Test name 1',
						'url' => 'http://example1.com',
						'status_codes' => '200',
						'no' => '1'
					]
				]
			],
			[
				'name' => 'Template web scenario 2',
				'hostid' => $templates['templateids']['Test of discovered host 2 template for clear'],
				'steps' => [
					[
						'name' => 'Test name 2',
						'url' => 'http://example2.com',
						'status_codes' => '200',
						'no' => '1'
					]
				]
			]
		]);

		// Link templates.
		CDataHelper::call('host.update', [
			'hostid' => self::DISCOVERED_HOSTID,
			'templates' => [
				$templates['templateids']['Test of discovered host Template'],
				$templates['templateids']['Test of discovered host 1 template for unlink'],
				$templates['templateids']['Test of discovered host 2 template for clear']
			]
		]);

		return [
			'discovered_hostid' => self::DISCOVERED_HOSTID,
			'discovered_interfaceid' => self::DISCOVERED_INTERFACEID
		];
	}
}

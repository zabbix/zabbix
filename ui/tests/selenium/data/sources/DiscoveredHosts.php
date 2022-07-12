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

	const DISCOVERED_HOST = 'Discovered host test host from prototype 1';
	const DISCOVERED_HOSTID = 90000079;
	const DISCOVERED_INTERFACEID = 90000080;
	const DISCOVERED_HOST_GROUPID = 90000081;

	protected static $template_names;
	protected static $templateids;

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
		CDataHelper::reset();

		CDataHelper::setSessionId(null);

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
		$interfaceid = CDBHelper::getValue('SELECT interfaceid FROM interface'.
				' WHERE hostid='.zbx_dbstr(self::$hostid));

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
				",".zbx_dbstr(self::DISCOVERED_HOST).",".zbx_dbstr(self::DISCOVERED_HOST).", 0, 4, '')");
		DBexecute("INSERT INTO host_discovery (hostid, parent_hostid) VALUES (".zbx_dbstr(self::DISCOVERED_HOSTID).", ".
				zbx_dbstr($host_prototypeid).")");
		DBexecute("INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (".
				zbx_dbstr(self::DISCOVERED_INTERFACEID).",".zbx_dbstr(self::DISCOVERED_HOSTID).", 1, 1, 1, '127.0.0.1', '', '10050')");
		DBexecute("INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (".zbx_dbstr(self::DISCOVERED_HOST_GROUPID).
				", ".zbx_dbstr(self::DISCOVERED_HOSTID).", 4)");
		DBexecute("INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (90000082, ".
				zbx_dbstr(self::DISCOVERED_HOSTID).", 'discovered', 'true')");
		DBexecute("INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (90000083, ".
				zbx_dbstr(self::DISCOVERED_HOSTID).", 'host', 'no')");

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
						'value_type' => ITEM_VALUE_TYPE_UINT64,
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
						'value_type' => ITEM_VALUE_TYPE_UINT64,
					]
				]
			]
		]);

		foreach ($templates['templateids'] as $name => $value) {
			self::$template_names[] = $name;
			self::$templateids[] = $value;
		}

		// Link templates.
		CDataHelper::call('host.update',[
			'hostid' => self::DISCOVERED_HOSTID,
			'templates' => self::$templateids
		]);
	}
}

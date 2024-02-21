<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
	const DISCOVERED_GROUP = 'Group created from host prototype 1';
	const DISCOVERED_GROUP2 = 'Group created from host prototype 11';
	const DISCOVERED_GROUPID = 90000079;
	const DISCOVERED_GROUPID2 = 90000080;
	const DISCOVERED_HOST_GROUP_PROTOTYPEID = 90000083;
	const DISCOVERED_HOST_GROUP_PROTOTYPEID2 = 90000084;
	const DISCOVERED_HOSTTEMPLATEID = 90000079;
	const DISCOVERED_HOSTTEMPLATEID2 = 90000080;

	/**
	 * Parent hostid.
	 *
	 * @var integer
	 */
	protected static $hostid;

	/**
	 * Create data for testFormHost, testPageHostGroups, testFormGroups, Discovered host scenario.
	 *
	 * @return array
	 */
	public static function load() {
		// Create hostgroup for discovered host test.
		$hostgroups = CDataHelper::call('hostgroup.create', [
			[
				'name' => 'Group for discovered host test'
			]
		]);
		$hostgroupid = $hostgroups['groupids'][0];

		$hosts = CDataHelper::call('host.create', [
			'host' => 'Test of discovered host',
			'groups' => [
				['groupid' => $hostgroupid]
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

		// Create template.
		$template = CDataHelper::createTemplates([
			[
				'host' => 'Test of discovered host Template',
				'groups' => ['groupid' => 1]
			]
		]);
		$templateid = $template['templateids']['Test of discovered host Template'];

		// Create host prototype.
		$host_prototypes = CDataHelper::call('hostprototype.create', [
			'host' => 'Host created from host prototype {#KEY}',
			'ruleid' => $lldid,
			'groupLinks' => [['groupid' => $hostgroupid]],
			'groupPrototypes' => [['name' => 'Group created from host prototype {#KEY}']],
			'tags' => [
				'tag' => 'prototype',
				'value' => 'true'
			],
			'templates' => [
				['templateid' => $templateid]
			]
		]);

		$host_prototypeid = $host_prototypes['hostids'][0];
		$group_prototypeid = CDBHelper::getValue('SELECT group_prototypeid FROM group_prototype WHERE name='.
				zbx_dbstr('Group created from host prototype {#KEY}')
		);

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
		// Link template to discovered hosts.
		DBexecute("INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) values (".zbx_dbstr(self::DISCOVERED_HOSTTEMPLATEID).
				", ".zbx_dbstr(self::DISCOVERED_HOSTID).",".zbx_dbstr($templateid).")"
		);
		DBexecute("INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) values (".zbx_dbstr(self::DISCOVERED_HOSTTEMPLATEID2).
				", ".zbx_dbstr(self::DISCOVERED_HOSTID2).",".zbx_dbstr($templateid).")"
		);
		// Emulate host group discovery.
		DBexecute("INSERT INTO hstgrp (groupid, name, flags, uuid) VALUES (".zbx_dbstr(self::DISCOVERED_GROUPID).
				", ".zbx_dbstr(self::DISCOVERED_GROUP).", 4, '')"
		);
		DBexecute("INSERT INTO hstgrp (groupid, name, flags, uuid) VALUES (".zbx_dbstr(self::DISCOVERED_GROUPID2).
				", ".zbx_dbstr(self::DISCOVERED_GROUP2).", 4, '')"
		);
		DBexecute("INSERT INTO group_discovery (groupid, parent_group_prototypeid, name, lastcheck, ts_delete) VALUES(".
				zbx_dbstr(self::DISCOVERED_GROUPID).", ".$group_prototypeid.", ".zbx_dbstr(self::DISCOVERED_GROUP).", '1672831234', '1677670843')"
		);
		DBexecute("INSERT INTO group_discovery (groupid, parent_group_prototypeid, name, lastcheck, ts_delete) VALUES(".
				zbx_dbstr(self::DISCOVERED_GROUPID2).", ".$group_prototypeid.", ".zbx_dbstr(self::DISCOVERED_GROUP2).", '1672831234', '1677670843')"
		);
		DBexecute("INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (".zbx_dbstr(self::DISCOVERED_HOST_GROUPID).
				", ".zbx_dbstr(self::DISCOVERED_HOSTID).", ".$hostgroupid.")"
		);
		DBexecute("INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (".zbx_dbstr(self::DISCOVERED_HOST_GROUPID2).
				", ".zbx_dbstr(self::DISCOVERED_HOSTID2).", ".$hostgroupid.")"
		);
		DBexecute("INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (".zbx_dbstr(self::DISCOVERED_HOST_GROUP_PROTOTYPEID).
				", ".zbx_dbstr(self::DISCOVERED_HOSTID).", ".zbx_dbstr(self::DISCOVERED_GROUPID).")"
		);
		DBexecute("INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (".zbx_dbstr(self::DISCOVERED_HOST_GROUP_PROTOTYPEID2).
				", ".zbx_dbstr(self::DISCOVERED_HOSTID2).",".zbx_dbstr(self::DISCOVERED_GROUPID2).")"
		);
		// Add tags for discovered hosts.
		DBexecute("INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (90000082, ".
				zbx_dbstr(self::DISCOVERED_HOSTID).", 'prototype', 'true')"
		);
		DBexecute("INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (90000083, ".
				zbx_dbstr(self::DISCOVERED_HOSTID2).", 'prototype', 'true')"
		);

		return [
			'discovered_hostid' => self::DISCOVERED_HOSTID,
			'discovered_interfaceid' => self::DISCOVERED_INTERFACEID
		];
	}
}

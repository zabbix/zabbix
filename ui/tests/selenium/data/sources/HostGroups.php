<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


class HostGroups {

	/**
	 * Create host groups that are discovered by multiple LLDs.
	 *
	 * @return array
	 */
	public static function load() {
		$hostgroups = CDataHelper::call('hostgroup.create', [['name' => 'Group for hostgroup discovery']]);
		$hostgroupid = $hostgroups['groupids'][0];

		$host = CDataHelper::call('host.create', [
			'host' => 'Host for hostgroup discovery',
			'groups' => [['groupid' => $hostgroupid]]
		]);

		// Create LLDs.
		$lld_list = [
			'1st LLD' => 'trap1',
			'2nd LLD' => 'trap2',
			'3rd LLD' => 'trap3',
			'forth LLD' => 'trap4',
			'fifth LLD' => 'trap5',
			'sixth LLD' => 'trap6',
			'sevenths LLD' => 'trap7',
			'LLD number 8' => 'trap8',
			'LLD 🙂🙃 !@#$%^&*()_+ 祝你今天过得愉快' => 'trap9',
			'Mūsu desmitais LLD' => 'trap10',
			'Eleventh LLD' => 'trap11',
			'12th LLD' => 'trap12',
			'Trīspadsmitais LLD' => 'trap13',
			'Četrpadsmitais LLD' => 'trap14',
			'15th LLD 🙃^天!' => 'trap15',
			'16th LLD' => 'trap16'
		];

		$lld_array = [];
		foreach ($lld_list as $lld_name => $lld_key) {
			$lld_array[] = [
				'name' => $lld_name,
				'key_' => $lld_key,
				'hostid' => $host['hostids'][0],
				'type' => ITEM_TYPE_TRAPPER
			];
		}

		$llds = CDataHelper::call('discoveryrule.create', $lld_array);

		$group_patterns = ['グループプロトタイプ番号 1', 'Trešais grupu prototips', '6 prototype group', 'Double GP'];
		$group_prototypes = [];
		$discovered_hostids = [];

		// Create host prototypes.
		foreach ($llds['itemids'] as $i => $lldid) {
			$hostgroup_index = self::getHostgroupIndex($i);

			$host_prototype = CDataHelper::call('hostprototype.create', [
				'host' => '{#KEY} HP number '.$i,
				'ruleid' => $lldid,
				'groupLinks' => [['groupid' => $hostgroupid]],
				'groupPrototypes' => [['name' => $group_patterns[$hostgroup_index].' {#KEY}']]
			]);

			$host_prototypeid = $host_prototype['hostids'][0];

			// Insert a discovered host the ID of which is by 1000 more than of the corresponding host prototype.
			$discovered_hostids[$i] = $host_prototypeid + 1000;
			DBexecute('INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES ('.
					zbx_dbstr($discovered_hostids[$i]).','.zbx_dbstr('KEY HP number '.$i).','.
					zbx_dbstr('KEY HP number '.$i).', 0, 4, \'\')'
			);

			DBexecute('INSERT INTO host_discovery (hostid, parent_hostid) VALUES ('.zbx_dbstr($discovered_hostids[$i]).
					', '.zbx_dbstr($host_prototypeid).')'
			);

			$group_prototypes[$i] = CDBHelper::getValue('SELECT group_prototypeid FROM'.' group_prototype WHERE hostid='.
					zbx_dbstr($host_prototypeid)
			);
		}

		// Create discovered host groups.
		$hostgroup_names = [];
		for ($k = 0; $k <= count($group_patterns) - 1; $k++) {
			$hostgroup_names[$k] = ['name' => $group_patterns[$k].' KEY'];
		}

		$discovered_hostgroups = CDataHelper::call('hostgroup.create', $hostgroup_names);

		// Mark the created groups as discovered.
		foreach ($discovered_hostgroups['groupids'] as $discovered_hostgroup) {
			DBexecute('UPDATE hstgrp SET flags=4 WHERE groupid='.zbx_dbstr($discovered_hostgroup));
		}

		// Link the corresponding host groups with their hostgroup prototypes and their discovered hosts.
		foreach ($group_prototypes as $i => $group_prototypeid) {
			$index = self::getHostgroupIndex($i);

			DBexecute('INSERT INTO group_discovery (groupdiscoveryid, groupid, parent_group_prototypeid, name, lastcheck, ts_delete)'.
					' VALUES ('.zbx_dbstr($group_prototypeid + 1000).', '.zbx_dbstr($discovered_hostgroups['groupids'][$index]).', '.
					$group_prototypeid.', '.zbx_dbstr($group_patterns[$index].' {#KEY}').', '.zbx_dbstr(time() - $i).', 0)'
			);

			DBexecute('INSERT INTO hosts_groups (hostgroupid, hostid, groupid)'.
					' VALUES ('.zbx_dbstr($discovered_hostids[$i].$i).', '.zbx_dbstr($discovered_hostids[$i]).', '.
					zbx_dbstr($discovered_hostgroups['groupids'][$index]).')'
			);
		}
	}

	/**
	 * Get the index of the discovered host group based on the LLD or group prototype index.
	 *
	 * @param int $index	index of the LLD or of the group prototype
	 *
	 * @return int
	 */
	protected static function getHostgroupIndex($index) {
		if ($index < 5) {
			// Discovered hostgroup 'グループプロトタイプ番号 1' has 5 LLD group prototypes with indexes from 0 to 4 in LLD list.
			return 0;
		}
		elseif ($index <= 7) {
			// Discovered hostgroup 'Trešais grupu prototips' corresponds to indexes from 5 to 7 in LLD list.
			return 1;
		}
		elseif ($index <= 13) {
			// Discovered hostgroup '6 prototype group' corresponds to indexes from 8 to 13 in LLD list.
			return 2;
		}
		else {
			// Discovered hostgroup 'Double GP' corresponds to the last two indexes in LLD list.
			return 3;
		}
	}
}

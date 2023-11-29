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


/**
 * Create data for host and template groups test.
 */
class HostTemplateGroups {

	public static function load() {
		// Prepare data for template groups.
		CDataHelper::call('templategroup.create', [
			[
				'name' => 'Group empty for Delete test'
			],
			[
				'name' => 'One group belongs to one object for Delete test'
			],
			[
				'name' => 'First group to one object for Delete test'
			],
			[
				'name' => 'Second group to one object for Delete test'
			]
		]);
		$template_groupids = CDataHelper::getIds('name');
		CDataHelper::createTemplates([
			[
				'host' => 'Template for template group testing',
				'groups' => [
					'groupid' => $template_groupids['One group belongs to one object for Delete test']
				]
			],
			[
				'host' => 'Template with two groups',
				'groups' => [
					['groupid' => $template_groupids['First group to one object for Delete test']],
					['groupid' => $template_groupids['Second group to one object for Delete test']]
				]
			]
		]);

		// Prepare data for host groups.
		CDataHelper::call('hostgroup.create', [
			[
				'name' => 'Group empty for Delete test'
			],
			[
				'name' => 'One group belongs to one object for Delete test'
			],
			[
				'name' => 'First group to one object for Delete test'
			],
			[
				'name' => 'Second group to one object for Delete test'
			],
			[
				'name' => 'Group for Script'
			],
			[
				'name' => 'Group for Action'
			],
			[
				'name' => 'Group for Maintenance'
			],
			[
				'name' => 'Group for Host prototype'
			],
			[
				'name' => 'Group for Correlation'
			]
		]);
		$host_groupids = CDataHelper::getIds('name');

		// Create elements with host groups.
		$host = CDataHelper::createHosts([
			[
				'host' => 'Host for host group testing',
				'interfaces' => [],
				'groups' => [
					'groupid' => $host_groupids['One group belongs to one object for Delete test']
				]
			],
			[
				'host' => 'Host with two groups',
				'interfaces' => [],
				'groups' => [
					'groupid' => $host_groupids['First group to one object for Delete test'],
					'groupid' => $host_groupids['Second group to one object for Delete test']
				]
			]
		]);
		$hostid = $host['hostids']['Host for host group testing'];

		$lld = CDataHelper::call('discoveryrule.create', [
			'name' => 'LLD for host group test',
			'key_' => 'lld.hostgroup',
			'hostid' => $hostid,
			'type' => ITEM_TYPE_TRAPPER,
			'delay' => 0
		]);
		$lldid = $lld['itemids'][0];
		CDataHelper::call('hostprototype.create', [
			'host' => 'Host prototype {#KEY} for host group testing',
			'ruleid' => $lldid,
			'groupLinks' => [
				[
					'groupid' => $host_groupids['Group for Host prototype']
				]
			]
		]);

		CDataHelper::call('script.create', [
			[
				'name' => 'Script for host group testing',
				'scope' => ZBX_SCRIPT_SCOPE_ACTION,
				'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
				'command' => 'return 1',
				'groupid' => $host_groupids['Group for Script']
			]
		]);

		CDataHelper::call('action.create', [
			[
				'name' => 'Discovery action for host group testing',
				'eventsource' => EVENT_SOURCE_DISCOVERY,
				'status' => ACTION_STATUS_ENABLED,
				'operations' => [
					[
						'operationtype' => OPERATION_TYPE_GROUP_ADD,
						'opgroup' => [
							[
								'groupid' => $host_groupids['Group for Action']
							]
						]
					]
				]
			]
		]);

		CDataHelper::call('maintenance.create', [
			[
				'name' => 'Maintenance for host group testing',
				'active_since' => 1358844540,
				'active_till' => 1390466940,
				'groups' => [
					[
						'groupid' => $host_groupids['Group for Maintenance']
					]
				],
				'timeperiods' => [[]]
			]
		]);

		CDataHelper::call('correlation.create', [
			[
				'name' => 'Corellation for host group testing',
				'filter' => [
					'evaltype' => ZBX_CORR_OPERATION_CLOSE_OLD,
					'conditions' => [
						[
							'type' => ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP,
							'groupid' => $host_groupids['Group for Correlation']
						]
					]
				],
				'operations' => [
					[
						'type' => ZBX_CORR_OPERATION_CLOSE_OLD
					]
				]
			]
		]);

		// Create host groups that are discovered by multiple LLDs.
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
			'16th LLD' => 'trap16',
			'17th LLD' => 'trap17'
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

		$group_patterns = ['グループプロトタイプ番号 1', 'Trešais grupu prototips', '5 prototype group', 'Two prototype group',
				'Single prototype group'
		];
		$group_prototypes = [];
		$discovered_hostids = [];
		$return_ids = [];

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

			// Collect LLD id and corresponding host ptototype id for tests that check LLD links for host groups.
			$return_ids[array_keys($lld_list)[$i]] = [
				'LLD id' => $lldid,
				'HP id' => $host_prototypeid
			];

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

		return [
			'templategroups' => $template_groupids,
			'hostgroups' => $host_groupids,
			'LLD_HP_ids' => $return_ids
		];
	}

	/**
	 * Get the index of the discovered host group based on the LLD or group prototype index.
	 *
	 * @param int $index	index of the LLD or of the group prototype
	 *
	 * @return int
	 */
	protected static function getHostgroupIndex($index) {
		if ($index < 6) {
			// Discovered hostgroup 'グループプロトタイプ番号 1' has 6 LLD group prototypes with indexes from 0 to 5 in LLD list.
			return 0;
		}
		elseif ($index <= 8) {
			// Discovered hostgroup 'Trešais grupu prototips' corresponds to indexes from 6 to 8 in LLD list.
			return 1;
		}
		elseif ($index <= 13) {
			// Discovered hostgroup '5 prototype group' corresponds to indexes from 9 to 13 in LLD list.
			return 2;
		}
		elseif ($index <= 15) {
			// Discovered hostgroup 'Two prototype group' corresponds to indexes 14 and 15 in LLD list.
			return 3;
		}
		else {
			// Discovered hostgroup 'Single prototype group' corresponds to the last index in LLD list.
			return 4;
		}
	}
}

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
			],
			[
				'name' => 'Group for hostgroup discovery'
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
				'name' => 'Correlation for host group testing',
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
		$lld_host = CDataHelper::call('host.create', [
			'host' => 'Host for hostgroup discovery',
			'groups' => [['groupid' => $host_groupids['Group for hostgroup discovery']]]
		]);

		$group_names = ['グループプロトタイプ番号 1', 'Trešais grupu prototips', '5 prototype group', 'Two prototype group',
				'Single prototype group'
		];

		// Create LLDs.
		$lld_discovered_groups = [
			'1st LLD' => $group_names[0],
			'2nd LLD' => $group_names[0],
			'3rd LLD' => $group_names[0],
			'forth LLD' => $group_names[0],
			'fifth LLD' => $group_names[0],
			'sixth LLD' => $group_names[0],
			'sevenths LLD' => $group_names[1],
			'LLD number 8' => $group_names[1],
			'LLD 🙂🙃 !@#$%^&*()_+ 祝你今天过得愉快' => $group_names[1],
			'Mūsu desmitais LLD' => $group_names[2],
			'Eleventh LLD' => $group_names[2],
			'12th LLD' => $group_names[2],
			'Trīspadsmitais LLD' => $group_names[2],
			'Četrpadsmitais LLD' => $group_names[2],
			'15th LLD 🙃^天!' => $group_names[3],
			'16th LLD' => $group_names[3],
			'17th LLD' => $group_names[4]
		];

		$lld_array = [];
		$lld_list = array_keys($lld_discovered_groups);
		foreach ($lld_list as $i => $lld_name) {
			$lld_array[] = [
				'name' => $lld_name,
				'key_' => 'trap'.$i,
				'hostid' => $lld_host['hostids'][0],
				'type' => ITEM_TYPE_TRAPPER
			];
		}

		CDataHelper::call('discoveryrule.create', $lld_array);
		$lldids = CDataHelper::getIds('name');

		$group_prototypeids = [];
		$discovered_hostids = [];
		$return_ids = [];
		$host_prototype_api = [];

		// Create host prototypes.
		foreach ($lldids as $lld_name => $lldid) {
			$host_prototype_api[] = [
				'host' => '{#KEY} host prototype of LLD '.$lldid,
				'ruleid' => $lldid,
				'groupLinks' => [['groupid' => $host_groupids['Group for hostgroup discovery']]],
				'groupPrototypes' => [['name' => $lld_discovered_groups[$lld_name].' {#KEY}']]
			];
		}

		CDataHelper::call('hostprototype.create', $host_prototype_api);
		$host_prototype_ids = CDataHelper::getIds('host');

		$i = 0;
		foreach ($lldids as $lld_name => $lldid) {
			$host_prototypeid = $host_prototype_ids['{#KEY} host prototype of LLD '.$lldid];

			// Collect LLD id and corresponding host prototype id for tests that check LLD links for host groups.
			$return_ids[$lld_name] = [
				'lld_id' => $lldid,
				'host_prototype_id' => $host_prototypeid
			];

			// Insert a discovered host the ID of which is by 1000 more than of the corresponding host prototype.
			$discovered_hostids[$i] = $host_prototypeid + 1000;
			DBexecute('INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES ('.
					zbx_dbstr($discovered_hostids[$i]).','.zbx_dbstr('KEY host prototype number '.$i).','.
					zbx_dbstr('KEY host prototype number '.$i).', 0, 4, \'\')'
			);

			DBexecute('INSERT INTO host_discovery (hostid, parent_hostid) VALUES ('.zbx_dbstr($discovered_hostids[$i]).
					', '.zbx_dbstr($host_prototypeid).')'
			);

			$group_prototypeids[$i] = CDBHelper::getValue('SELECT group_prototypeid FROM'.' group_prototype WHERE hostid='.
					zbx_dbstr($host_prototypeid)
			);

			$i++;
		}

		// Create discovered host groups.
		$hostgroup_names = [];
		foreach ($group_names as $k => $group_name) {
			$hostgroup_names[$k] = ['name' => $group_name.' KEY'];
		}

		$discovered_hostgroups = CDataHelper::call('hostgroup.create', $hostgroup_names);

		// Mark the created groups as discovered.
		DBexecute('UPDATE hstgrp SET flags=4 WHERE groupid IN ('.implode(', ', $discovered_hostgroups['groupids']).')');
		$discovered_hostgroupids = CDataHelper::getIds('name');

		// Link the corresponding host groups with their hostgroup prototypes and their discovered hosts.
		foreach ($group_prototypeids as $i => $group_prototypeid) {
			$discovered_hostgroup = array_values($lld_discovered_groups)[$i];

			DBexecute('INSERT INTO group_discovery (groupdiscoveryid, groupid, parent_group_prototypeid, name, lastcheck, ts_delete)'.
					' VALUES ('.zbx_dbstr($group_prototypeid + 1000).', '.zbx_dbstr($discovered_hostgroupids[$discovered_hostgroup.' KEY']).
					', '.$group_prototypeid.', '.zbx_dbstr($discovered_hostgroup.' {#KEY}').', '.zbx_dbstr(time() - $i).', 0)'
			);

			DBexecute('INSERT INTO hosts_groups (hostgroupid, hostid, groupid)'.
					' VALUES ('.zbx_dbstr($discovered_hostids[$i].$i).', '.zbx_dbstr($discovered_hostids[$i]).', '.
					zbx_dbstr($discovered_hostgroupids[$discovered_hostgroup.' KEY']).')'
			);
		}

		return [
			'templategroups' => $template_groupids,
			'hostgroups' => $host_groupids,
			'lld_host_prototype_ids' => $return_ids
		];
	}
}

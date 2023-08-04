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

		return ['templategroups' => $template_groupids, 'hostgroups' => $host_groupids];
	}
}

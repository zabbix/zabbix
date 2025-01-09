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
 * Create data user permissions for hosts and host groups access.
 */
class UserPermissions {

	public static function load() {
		// Prepare host group and host with item and triggers.
		$hostgroups = CDataHelper::call('hostgroup.create', [['name' => 'Host group for tag permissions']]);
		$hostgroupid = $hostgroups['groupids'][0];

		$hosts = CDataHelper::call('host.create', [
			'host' => 'Host for tag permissions',
			'groups' => [['groupid' => $hostgroupid]],
			'interfaces' => []
		]);

		$items = CDataHelper::call('item.create', [
			[
				'hostid' => $hosts['hostids'][0],
				'name' => 'tag.item',
				'key_' => 'tag.key',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			]
		]);

		CDataHelper::call('trigger.create', [
			[
				'description' => 'Trigger for tag permissions MySQL',
				'expression' => 'last(/Host for tag permissions/tag.key,#1)=0',
				'tags' => [['tag' => 'Service', 'value' => 'MySQL']]
			],
			[
				'description' => 'Trigger for tag permissions Oracle',
				'expression' => 'last(/Host for tag permissions/tag.key,#1)=0',
				'tags' => [['tag' => 'Service', 'value' => 'Oracle']]
			]
		]);
		$triggerids = CDataHelper::getIds('description');

		// Create events and problems..
		CDataHelper::addItemData($items['itemids'][0], 0);

		foreach (array_keys($triggerids) as $trigger_name) {
			CDBHelper::setTriggerProblem($trigger_name, TRIGGER_VALUE_TRUE, ['clock' => time()]);
		}

		// Create user groups with corresponding permissions and users.
		CDataHelper::call('usergroup.create', [
			[
				'name' => 'Selenium user group for tag permissions AAA',
				'hostgroup_rights' => ['id' => $hostgroupid, 'permission' => PERM_READ_WRITE]
			],
			[
				'name' => 'Selenium user group for tag permissions BBB',
				'hostgroup_rights' => ['id' => $hostgroupid, 'permission' => PERM_READ_WRITE]
			]
		]);
		$usergroupids = CDataHelper::getIds('name');

		CDataHelper::call('user.create', [
			[
				'username' => 'Tag-user',
				'passwd' => 'Zabbix_Test_123',
				'roleid' => USER_TYPE_ZABBIX_USER,
				'usrgrps' => [
					['usrgrpid' => $usergroupids['Selenium user group for tag permissions AAA']],
					['usrgrpid' => $usergroupids['Selenium user group for tag permissions BBB']]
				]
			]
		]);
	}
}


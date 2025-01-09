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

class ExecuteNowAction {

	/**
	 * Create data for "Execute now" action testing.
	 *
	 * @return array
	 */
	public static function load() {
		// Create host group.
		CDataHelper::call('hostgroup.create', [
			['name' => 'HG-for-executenow']
		]);
		$hostgroupids = CDataHelper::getIds('name');

		// Create host.
		$hosts = CDataHelper::createHosts([
			[
				'host' => 'Host for execute now permissions',
				'interfaces' => [
					[
						'type' => 1,
						'main' => 1,
						'useip' => 1,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => '10051'
					]
				],
				'groups' => [
					'groupid' => $hostgroupids['HG-for-executenow']
				],
				'status' => HOST_STATUS_MONITORED,
				'items' => [
					[
						'name' => 'I1-lvl1-agent-num',
						'key_' => 'I1-lvl1-agent-num',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => '1h'
					],
					[
						'name' => 'I2-lvl1-trap-num',
						'key_' => 'I2-lvl1-trap-num',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'I4-trap-log',
						'key_' => 'I4-trap-log',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_LOG
					],
					[
						'name' => 'I5-agent-txt',
						'key_' => 'I5-agent-txt',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_TEXT,
						'delay' => '1h'
					]
				]
			]
		]);
		$itemids = CDataHelper::getIds('name');

		// Create web scenario.
		CDataHelper::call('httptest.create', [
			[
				'name' => 'Web scenario for execute now',
				'hostid' => $hosts['hostids']['Host for execute now permissions'],
				'steps' => [
					[
						'name' => 'Homepage',
						'url' => 'http://zabbix.com',
						'no' => 1
					]
				]
			]
		]);

		// Get item from web scenario.
		$web_item = CDataHelper::call('item.get', [
			'hostids' => $hosts['hostids']['Host for execute now permissions'],
			'webitems' => 'extend',
			'search' => [
				'key_' => 'web.test.error[Web scenario for execute now]'
			]
		]);

		// Create dependent items.
		$items = [
			'Host for execute now permissions' => [
				[
					'name' => 'I1-lvl2-dep-log',
					'key_' => 'I1-lvl2-dep-log',
					'master_itemid' => $itemids['I1-lvl1-agent-num'],
					'type' => ITEM_TYPE_DEPENDENT,
					'value_type' => ITEM_VALUE_TYPE_LOG
				],
				[
					'name' => 'I1-lvl3-dep-txt',
					'key_' => 'I1-lvl3-dep-txt',
					'master_itemid' => $itemids['I1-lvl1-agent-num'],
					'type' => ITEM_TYPE_DEPENDENT,
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'name' => 'I2-lvl2-dep-log',
					'key_' => 'I2-lvl2-dep-log',
					'master_itemid' => $itemids['I2-lvl1-trap-num'],
					'type' => ITEM_TYPE_DEPENDENT,
					'value_type' => ITEM_VALUE_TYPE_LOG
				],
				[
					'name' => 'I2-lvl3-dep-txt',
					'key_' => 'I2-lvl3-dep-txt',
					'master_itemid' => $itemids['I2-lvl1-trap-num'],
					'type' => ITEM_TYPE_DEPENDENT,
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'name' => 'I3-web-dep',
					'key_' => 'I3-web-dep',
					'master_itemid' => $web_item[0]['itemid'],
					'type' => ITEM_TYPE_DEPENDENT,
					'value_type' => ITEM_VALUE_TYPE_UINT64
				]
			]
		];
		CDataHelper::createItems('item', $items, $hosts['hostids']);

		// Create discovery rules.
		$discoveryrule = [
			'Host for execute now permissions' => [
				[
					'name' => 'DR1-agent',
					'key_' => 'DR1-agent',
					'type' => ITEM_TYPE_ZABBIX,
					'delay' => '1h'
				],
				[
					'name' => 'DR2-trap',
					'key_' => 'DR2-trap',
					'type' => ITEM_TYPE_TRAPPER
				],
				[
					'name' => 'DR3-I1-dep-agent',
					'key_' => 'DR3-I1-dep-agent',
					'master_itemid' => $itemids['I1-lvl1-agent-num'],
					'type' => ITEM_TYPE_DEPENDENT
				],
				[
					'name' => 'DR4-I2-dep-trap',
					'key_' => 'DR4-I2-dep-trap',
					'master_itemid' => $itemids['I2-lvl1-trap-num'],
					'type' => ITEM_TYPE_DEPENDENT
				],
				[
					'name' => 'DR5-web-dep',
					'key_' => 'DR5-web-dep',
					'master_itemid' => $web_item[0]['itemid'],
					'type' => ITEM_TYPE_DEPENDENT
				]
			]
		];
		CDataHelper::createItems('discoveryrule', $discoveryrule, $hosts['hostids']);

		// Create user data.
		CDataHelper::call('role.create', [
			[
				'name' => 'UR1-executenow-on',
				'type' => 1
			],
			[
				'name' => 'UR2-executenow-off',
				'type' => 2,
				'rules' => [
					'actions' => [
						[
							'name' => 'invoke_execute_now',
							'status' => 0
						]
					]
				]
			]
		]);
		$roleids = CDataHelper::getIds('name');

		CDataHelper::call('usergroup.create', [
			[
				'name' => 'UG1-rw',
				'hostgroup_rights' => [
					'permission' => 3,
					'id' => $hostgroupids['HG-for-executenow']
				]
			],
			[
				'name' => 'UG2-r',
				'hostgroup_rights' => [
					'permission' => 2,
					'id' => $hostgroupids['HG-for-executenow']
				]
			]
		]);
		$usrgrpids = CDataHelper::getIds('name');

		CDataHelper::call('user.create', [
			[
				'username' => 'U1-r-on',
				'passwd' => 'zabbixzabbix',
				'roleid' => $roleids['UR1-executenow-on'],
				'usrgrps' => [
					[
						'usrgrpid' => $usrgrpids['UG2-r']
					]
				]
			],
			[
				'username' => 'U2-r-off',
				'passwd' => 'zabbixzabbix',
				'roleid' => $roleids['UR2-executenow-off'],
				'usrgrps' => [
					[
						'usrgrpid' => $usrgrpids['UG2-r']
					]
				]
			],
			[
				'username' => 'U3-rw-off',
				'passwd' => 'zabbixzabbix',
				'roleid' => $roleids['UR2-executenow-off'],
				'usrgrps' => [
					[
						'usrgrpid' => $usrgrpids['UG1-rw']
					]
				]
			]
		]);

		return $hosts;
	}
}

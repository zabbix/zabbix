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


require_once dirname(__FILE__).'/../common/testTimeoutsDisplay.php';

/**
 * @onBefore prepareTimeoutsData
 *
 * @backup config, hosts, proxy
 */
class testTemplatesTimeouts extends testTimeoutsDisplay {
	protected static $proxyid;
	protected static $templateid;
	protected static $template_druleids;

	public static function prepareTimeoutsData() {
		CDataHelper::call('proxy.create',
			[
				[
					'name' => 'Proxy assigned to host',
					'operating_mode' => 0
				],
				[
					'name' => 'Proxy for timeouts check',
					'operating_mode' => 0
				]
			]
		);
		self::$proxyid = CDataHelper::getIds('name');


		$template_result = CDataHelper::createTemplates([
			[
				'host' => 'Template with items linked to host',
				'groups' => ['groupid' => 1], // Templates.
				'items' => [
					[
						'name' => 'Zabbix agent',
						'key_' => 'zabbix_agent',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => 5
					],
					[
						'name' => 'Zabbix agent (active)',
						'key_' => 'zabbix_agent_active',
						'type' => ITEM_TYPE_ZABBIX_ACTIVE,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => 5
					],
					[
						'name' => 'Simple check',
						'key_' => 'simple_check',
						'type' => ITEM_TYPE_SIMPLE,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => 5
					],
					[
						'name' => 'SNMP agent',
						'key_' => 'snmp',
						'type' => ITEM_TYPE_SNMP,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'snmp_oid' => 'walk[222]',
						'delay' => 5
					],
					[
						'name' => 'External check',
						'key_' => 'external',
						'type' => ITEM_TYPE_EXTERNAL,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => 5
					],
					[
						'name' => 'Database monitor',
						'key_' => 'database',
						'type' => ITEM_TYPE_DB_MONITOR,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'params' => 'test',
						'delay' => 5
					],
					[
						'name' => 'HTTP agent',
						'key_' => 'http',
						'type' => ITEM_TYPE_HTTPAGENT,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'url' => 'test',
						'delay' => 5
					],
					[
						'name' => 'SSH agent',
						'key_' => 'ssh',
						'type' => ITEM_TYPE_SSH,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'params' => 'test',
						'username' => 'test_username',
						'delay' => 5
					],
					[
						'name' => 'TELNET agent',
						'key_' => 'telnet',
						'type' => ITEM_TYPE_TELNET,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'params' => 'test',
						'username' => 'test_username',
						'delay' => 5
					],
					[
						'name' => 'Script',
						'key_' => 'script',
						'type' => ITEM_TYPE_SCRIPT,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'params' => 'test',
						'delay' => 5
					]
				],
				'discoveryrules' => [
					[
						'name' => 'Zabbix agent drule',
						'key_' => 'zabbix_agent_drule',
						'type' => ITEM_TYPE_ZABBIX,
						'delay' => 5
					],
					[
						'name' => 'Zabbix agent drule',
						'key_' => 'zabbix_agent_drule',
						'type' => ITEM_TYPE_ZABBIX_ACTIVE,
						'delay' => 5
					],
					[
						'name' => 'Simple check drule',
						'key_' => 'simple_check_drule',
						'type' => ITEM_TYPE_SIMPLE,
						'delay' => 5
					],
					[
						'name' => 'SNMP agent drule',
						'key_' => 'snmp_drule',
						'type' => ITEM_TYPE_SNMP,
						'snmp_oid' => 'walk[222]',
						'delay' => 5
					],
					[
						'name' => 'External check drule',
						'key_' => 'external_drule',
						'type' => ITEM_TYPE_EXTERNAL,
						'delay' => 5
					],
					[
						'name' => 'Database monitor drule',
						'key_' => 'database_drule',
						'type' => ITEM_TYPE_DB_MONITOR,
						'params' => 'test',
						'delay' => 5
					],
					[
						'name' => 'HTTP agent drule',
						'key_' => 'http_drule',
						'type' => ITEM_TYPE_HTTPAGENT,
						'url' => 'test',
						'delay' => 5
					],
					[
						'name' => 'SSH agent drule',
						'key_' => 'ssh_drule',
						'type' => ITEM_TYPE_SSH,
						'params' => 'test',
						'username' => 'test_username',
						'delay' => 5
					],
					[
						'name' => 'TELNET agent drule',
						'key_' => 'telnet_drule',
						'type' => ITEM_TYPE_TELNET,
						'params' => 'test',
						'username' => 'test_username',
						'delay' => 5
					],
					[
						'name' => 'Script drule',
						'key_' => 'script_drule',
						'type' => ITEM_TYPE_SCRIPT,
						'params' => 'test',
						'delay' => 5
					]
				]
			]
		]);
		self::$templateid = $template_result['templateids'];
		self::$template_druleids = $template_result['discoveryruleids'];

		CDataHelper::call('itemprototype.create', [
			[
				'name' => 'Zabbix agent prototype',
				'key_' => 'zabbix_agent_[{#KEY}]',
				'hostid' => self::$templateid['Template with items linked to host'],
				'ruleid' => self::$template_druleids['Template with items linked to host:zabbix_agent_drule'],
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 5
			],
			[
				'name' => 'Zabbix agent (active) prototype',
				'key_' => 'zabbix_agent_active_[{#KEY}]',
				'hostid' => self::$templateid['Template with items linked to host'],
				'ruleid' => self::$template_druleids['Template with items linked to host:zabbix_agent_drule'],
				'type' => ITEM_TYPE_ZABBIX_ACTIVE,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 5
			],
			[
				'name' => 'Simple check prototype',
				'key_' => 'simple_check_[{#KEY}]',
				'hostid' => self::$templateid['Template with items linked to host'],
				'ruleid' => self::$template_druleids['Template with items linked to host:zabbix_agent_drule'],
				'type' => ITEM_TYPE_SIMPLE,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 5
			],
			[
				'name' => 'SNMP agent prototype',
				'key_' => 'snmp_[{#KEY}]',
				'hostid' => self::$templateid['Template with items linked to host'],
				'ruleid' => self::$template_druleids['Template with items linked to host:zabbix_agent_drule'],
				'type' => ITEM_TYPE_SNMP,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'snmp_oid' => 'walk[222]',
				'delay' => 5
			],
			[
				'name' => 'External check prototype',
				'key_' => 'external_[{#KEY}]',
				'hostid' => self::$templateid['Template with items linked to host'],
				'ruleid' => self::$template_druleids['Template with items linked to host:zabbix_agent_drule'],
				'type' => ITEM_TYPE_EXTERNAL,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 5
			],
			[
				'name' => 'Database monitor prototype',
				'key_' => 'database_[{#KEY}]',
				'hostid' => self::$templateid['Template with items linked to host'],
				'ruleid' => self::$template_druleids['Template with items linked to host:zabbix_agent_drule'],
				'type' => ITEM_TYPE_DB_MONITOR,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'params' => 'test',
				'delay' => 5
			],
			[
				'name' => 'HTTP agent prototype',
				'key_' => 'http_[{#KEY}]',
				'hostid' => self::$templateid['Template with items linked to host'],
				'ruleid' => self::$template_druleids['Template with items linked to host:zabbix_agent_drule'],
				'type' => ITEM_TYPE_HTTPAGENT,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'url' => 'test',
				'delay' => 5
			],
			[
				'name' => 'SSH agent prototype',
				'key_' => 'ssh_[{#KEY}]',
				'hostid' => self::$templateid['Template with items linked to host'],
				'ruleid' => self::$template_druleids['Template with items linked to host:zabbix_agent_drule'],
				'type' => ITEM_TYPE_SSH,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'params' => 'test',
				'username' => 'test_username',
				'delay' => 5
			],
			[
				'name' => 'TELNET agent prototype',
				'key_' => 'telnet_[{#KEY}]',
				'hostid' => self::$templateid['Template with items linked to host'],
				'ruleid' => self::$template_druleids['Template with items linked to host:zabbix_agent_drule'],
				'type' => ITEM_TYPE_TELNET,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'params' => 'test',
				'username' => 'test_username',
				'delay' => 5
			],
			[
				'name' => 'Script prototype',
				'key_' => 'script_[{#KEY}]',
				'hostid' => self::$templateid['Template with items linked to host'],
				'ruleid' => self::$template_druleids['Template with items linked to host:zabbix_agent_drule'],
				'type' => ITEM_TYPE_SCRIPT,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'params' => 'test',
				'delay' => 5
			]
		]);
	}
}
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
class testLinkedTimeouts extends testTimeoutsDisplay {

	protected static $templateid;
	protected static $hostid;

	public static function prepareTimeoutsData() {
		CDataHelper::call('proxy.create', [
			[
				'name' => 'Proxy assigned to host',
				'operating_mode' => 0
			]
		]);
		$proxyid = CDataHelper::getIds('name');

		$template_result = CDataHelper::createTemplates([
			[
				'host' => 'Template for linking',
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
						'name' => 'Zabbix agent',
						'key_' => 'zabbix_agent_drule',
						'type' => ITEM_TYPE_ZABBIX,
						'delay' => 5
					],
					[
						'name' => 'Zabbix agent (active)',
						'key_' => 'zabbix_agent_drule_active',
						'type' => ITEM_TYPE_ZABBIX_ACTIVE,
						'delay' => 5
					],
					[
						'name' => 'Simple check',
						'key_' => 'simple_check_drule',
						'type' => ITEM_TYPE_SIMPLE,
						'delay' => 5
					],
					[
						'name' => 'SNMP agent',
						'key_' => 'snmp_drule',
						'type' => ITEM_TYPE_SNMP,
						'snmp_oid' => 'walk[222]',
						'delay' => 5
					],
					[
						'name' => 'External check',
						'key_' => 'external_drule',
						'type' => ITEM_TYPE_EXTERNAL,
						'delay' => 5
					],
					[
						'name' => 'Database monitor',
						'key_' => 'database_drule',
						'type' => ITEM_TYPE_DB_MONITOR,
						'params' => 'test',
						'delay' => 5
					],
					[
						'name' => 'HTTP agent',
						'key_' => 'http_drule',
						'type' => ITEM_TYPE_HTTPAGENT,
						'url' => 'test',
						'delay' => 5
					],
					[
						'name' => 'SSH agent',
						'key_' => 'ssh_drule',
						'type' => ITEM_TYPE_SSH,
						'params' => 'test',
						'username' => 'test_username',
						'delay' => 5
					],
					[
						'name' => 'TELNET agent',
						'key_' => 'telnet_drule',
						'type' => ITEM_TYPE_TELNET,
						'params' => 'test',
						'username' => 'test_username',
						'delay' => 5
					],
					[
						'name' => 'Script',
						'key_' => 'script_drule',
						'type' => ITEM_TYPE_SCRIPT,
						'params' => 'test',
						'delay' => 5
					]
				]
			]
		]);
		$linked_template = $template_result['templateids'];
		$template_druleids = $template_result['discoveryruleids'];

		CDataHelper::call('itemprototype.create', [
			[
				'name' => 'Zabbix agent',
				'key_' => 'zabbix_agent_[{#KEY}]',
				'hostid' => $linked_template['Template for linking'],
				'ruleid' => $template_druleids['Template for linking:zabbix_agent_drule'],
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 5
			],
			[
				'name' => 'Zabbix agent (active)',
				'key_' => 'zabbix_agent_active_[{#KEY}]',
				'hostid' => $linked_template['Template for linking'],
				'ruleid' => $template_druleids['Template for linking:zabbix_agent_drule'],
				'type' => ITEM_TYPE_ZABBIX_ACTIVE,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 5
			],
			[
				'name' => 'Simple check',
				'key_' => 'simple_check_[{#KEY}]',
				'hostid' => $linked_template['Template for linking'],
				'ruleid' => $template_druleids['Template for linking:zabbix_agent_drule'],
				'type' => ITEM_TYPE_SIMPLE,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 5
			],
			[
				'name' => 'SNMP agent',
				'key_' => 'snmp_[{#KEY}]',
				'hostid' => $linked_template['Template for linking'],
				'ruleid' => $template_druleids['Template for linking:zabbix_agent_drule'],
				'type' => ITEM_TYPE_SNMP,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'snmp_oid' => 'walk[222]',
				'delay' => 5
			],
			[
				'name' => 'External check',
				'key_' => 'external_[{#KEY}]',
				'hostid' => $linked_template['Template for linking'],
				'ruleid' => $template_druleids['Template for linking:zabbix_agent_drule'],
				'type' => ITEM_TYPE_EXTERNAL,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 5
			],
			[
				'name' => 'Database monitor',
				'key_' => 'database_[{#KEY}]',
				'hostid' => $linked_template['Template for linking'],
				'ruleid' => $template_druleids['Template for linking:zabbix_agent_drule'],
				'type' => ITEM_TYPE_DB_MONITOR,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'params' => 'test',
				'delay' => 5
			],
			[
				'name' => 'HTTP agent',
				'key_' => 'http_[{#KEY}]',
				'hostid' => $linked_template['Template for linking'],
				'ruleid' => $template_druleids['Template for linking:zabbix_agent_drule'],
				'type' => ITEM_TYPE_HTTPAGENT,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'url' => 'test',
				'delay' => 5
			],
			[
				'name' => 'SSH agent',
				'key_' => 'ssh_[{#KEY}]',
				'hostid' => $linked_template['Template for linking'],
				'ruleid' => $template_druleids['Template for linking:zabbix_agent_drule'],
				'type' => ITEM_TYPE_SSH,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'params' => 'test',
				'username' => 'test_username',
				'delay' => 5
			],
			[
				'name' => 'TELNET agent',
				'key_' => 'telnet_[{#KEY}]',
				'hostid' => $linked_template['Template for linking'],
				'ruleid' => $template_druleids['Template for linking:zabbix_agent_drule'],
				'type' => ITEM_TYPE_TELNET,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'params' => 'test',
				'username' => 'test_username',
				'delay' => 5
			],
			[
				'name' => 'Script',
				'key_' => 'script_[{#KEY}]',
				'hostid' => $linked_template['Template for linking'],
				'ruleid' => $template_druleids['Template for linking:zabbix_agent_drule'],
				'type' => ITEM_TYPE_SCRIPT,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'params' => 'test',
				'delay' => 5
			]
		]);

		$host_result = CDataHelper::call('host.create', [
			[
				'host' => 'Host for linked timeout check with proxy',
				'groups' => [
					[
						'groupid' => 4 // Zabbix servers
					]
				],
				'interfaces' => [
					[
						'type'=> INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_DNS,
						'ip' => '',
						'dns' => 'zabbix_agent',
						'port' => '1'
					],
					[
						'type'=> INTERFACE_TYPE_SNMP,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_DNS,
						'ip' => '',
						'dns' => 'snmp',
						'port' => '2',
						'details' => [
							'version' => 1,
							'community' => '{$SNMP_COMMUNITY}'
						]
					]
				],
				'proxyid' => $proxyid['Proxy assigned to host'],
				'templates' => [
					'templateid' => $linked_template['Template for linking']
				]
			],
			[
				'host' => 'Host for linked timeout check',
				'groups' => [
					[
						'groupid' => 4 // Zabbix servers
					]
				],
				'interfaces' => [
					[
						'type'=> INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_DNS,
						'ip' => '',
						'dns' => 'zabbix_agent',
						'port' => '1'
					],
					[
						'type'=> INTERFACE_TYPE_SNMP,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_DNS,
						'ip' => '',
						'dns' => 'snmp',
						'port' => '2',
						'details' => [
							'version' => 1,
							'community' => '{$SNMP_COMMUNITY}'
						]
					]
				],
				'templates' => [
					'templateid' => $linked_template['Template for linking']
				]
			]
		]);
		self::$hostid = $host_result['hostids'];

		$response = CDataHelper::call('template.create', [
			'host' => 'Template for timeout check',
			'groups' => ['groupid' => 1], // Templates.
			'templates' => [
				'templateid' => $linked_template['Template for linking']
			]
		]);
		self::$templateid = $response['templateids'][0];
	}

	public function testLinkedTimeouts_checkTemplateItemsMacros() {
		$link = 'zabbix.php?action=item.list&filter_set=1&context=template&filter_hostids%5B0%5D='.self::$templateid;
		$this->checkGlobal('global_macros', $link, 'name:item_list', false, true);
	}
}

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
class testHostsTimeouts extends testTimeoutsDisplay {

	protected static $proxyid;
	protected static $hostid;
	protected static $hostid_druleids;
	protected static $interfaceid;

	public static function prepareTimeoutsData() {
		CDataHelper::call('proxy.create',
			[
				[
					'name' => 'Proxy assigned to host',
					'operating_mode' => 0
				]
			]
		);
		self::$proxyid = CDataHelper::getIds('name');

		$host_result = CDataHelper::createHosts([
			[
				'host' => 'Host for timeouts check',
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
		self::$hostid = $host_result['hostids'];
		self::$hostid_druleids = $host_result['discoveryruleids'];
		self::$interfaceid = CDataHelper::getInterfaces([self::$hostid['Host for timeouts check']]);

		CDataHelper::call('itemprototype.create', [
			[
				'name' => 'Zabbix agent',
				'key_' => 'zabbix_agent_[{#KEY}]',
				'hostid' => self::$hostid['Host for timeouts check'],
				'ruleid' => self::$hostid_druleids['Host for timeouts check:zabbix_agent_drule'],
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'interfaceid' => self::$interfaceid['ids']['Host for timeouts check']['zabbix_agent:1'],
				'delay' => 5
			],
			[
				'name' => 'Zabbix agent (active)',
				'key_' => 'zabbix_agent_active_[{#KEY}]',
				'hostid' => self::$hostid['Host for timeouts check'],
				'ruleid' => self::$hostid_druleids['Host for timeouts check:zabbix_agent_drule'],
				'type' => ITEM_TYPE_ZABBIX_ACTIVE,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 5
			],
			[
				'name' => 'Simple check',
				'key_' => 'simple_check_[{#KEY}]',
				'hostid' => self::$hostid['Host for timeouts check'],
				'ruleid' => self::$hostid_druleids['Host for timeouts check:zabbix_agent_drule'],
				'type' => ITEM_TYPE_SIMPLE,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'interfaceid' => self::$interfaceid['ids']['Host for timeouts check']['zabbix_agent:1'],
				'delay' => 5
			],
			[
				'name' => 'SNMP agent',
				'key_' => 'snmp_[{#KEY}]',
				'hostid' => self::$hostid['Host for timeouts check'],
				'ruleid' => self::$hostid_druleids['Host for timeouts check:zabbix_agent_drule'],
				'type' => ITEM_TYPE_SNMP,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'interfaceid' => self::$interfaceid['ids']['Host for timeouts check']['snmp:2'],
				'snmp_oid' => 'walk[222]',
				'delay' => 5
			],
			[
				'name' => 'External check',
				'key_' => 'external_[{#KEY}]',
				'hostid' => self::$hostid['Host for timeouts check'],
				'ruleid' => self::$hostid_druleids['Host for timeouts check:zabbix_agent_drule'],
				'type' => ITEM_TYPE_EXTERNAL,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'interfaceid' => self::$interfaceid['ids']['Host for timeouts check']['zabbix_agent:1'],
				'delay' => 5
			],
			[
				'name' => 'Database monitor',
				'key_' => 'database_[{#KEY}]',
				'hostid' => self::$hostid['Host for timeouts check'],
				'ruleid' => self::$hostid_druleids['Host for timeouts check:zabbix_agent_drule'],
				'type' => ITEM_TYPE_DB_MONITOR,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'params' => 'test',
				'delay' => 5
			],
			[
				'name' => 'HTTP agent',
				'key_' => 'http_[{#KEY}]',
				'hostid' => self::$hostid['Host for timeouts check'],
				'ruleid' => self::$hostid_druleids['Host for timeouts check:zabbix_agent_drule'],
				'type' => ITEM_TYPE_HTTPAGENT,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'url' => 'test',
				'delay' => 5
			],
			[
				'name' => 'SSH agent',
				'key_' => 'ssh_[{#KEY}]',
				'hostid' => self::$hostid['Host for timeouts check'],
				'ruleid' => self::$hostid_druleids['Host for timeouts check:zabbix_agent_drule'],
				'type' => ITEM_TYPE_SSH,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'interfaceid' => self::$interfaceid['ids']['Host for timeouts check']['zabbix_agent:1'],
				'params' => 'test',
				'username' => 'test_username',
				'delay' => 5
			],
			[
				'name' => 'TELNET agent',
				'key_' => 'telnet_[{#KEY}]',
				'hostid' => self::$hostid['Host for timeouts check'],
				'ruleid' => self::$hostid_druleids['Host for timeouts check:zabbix_agent_drule'],
				'type' => ITEM_TYPE_TELNET,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'interfaceid' => self::$interfaceid['ids']['Host for timeouts check']['zabbix_agent:1'],
				'params' => 'test',
				'username' => 'test_username',
				'delay' => 5
			],
			[
				'name' => 'Script',
				'key_' => 'script_[{#KEY}]',
				'hostid' => self::$hostid['Host for timeouts check'],
				'ruleid' => self::$hostid_druleids['Host for timeouts check:zabbix_agent_drule'],
				'type' => ITEM_TYPE_SCRIPT,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'params' => 'test',
				'delay' => 5
			]
		]);
	}

	public function testHostsTimeouts_checkItemsMacros() {
		$link = 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='.
				self::$hostid['Host for timeouts check'];
		$this->checkGlobal('macros', $link, 'name:item_list');
	}

	public function testHostsTimeouts_checkDiscoveryMacros() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
			self::$hostid['Host for timeouts check'];
		$this->checkGlobal('macros', $link, 'name:discovery');
	}

	public function testHostsTimeouts_checkPrototypeMacros() {
		$link = 'zabbix.php?action=item.prototype.list&context=host&parent_discoveryid='.
			self::$hostid_druleids['Host for timeouts check:zabbix_agent_drule'];
		$this->checkGlobal('macros', $link, 'name:itemprototype');
	}

	public function testHostsTimeouts_checkItemsSeconds() {
		$link = 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='.
				self::$hostid['Host for timeouts check'];
		$this->checkGlobal('seconds', $link, 'name:item_list');
	}

	public function testHostsTimeouts_checkDiscoverySeconds() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
			self::$hostid['Host for timeouts check'];
		$this->checkGlobal('seconds', $link, 'name:discovery');
	}

	public function testHostsTimeouts_checkPrototypeSeconds() {
		$link = 'zabbix.php?action=item.prototype.list&context=host&parent_discoveryid='.
			self::$hostid_druleids['Host for timeouts check:zabbix_agent_drule'];
		$this->checkGlobal('seconds', $link, 'name:itemprototype');
	}

	public function testHostsTimeouts_checkItemsDefault() {
		$link = 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='.
				self::$hostid['Host for timeouts check'];
		$this->checkGlobal('reset', $link, 'name:item_list');
	}

	public function testHostsTimeouts_checkDiscoveryDefault() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
			self::$hostid['Host for timeouts check'];
		$this->checkGlobal('reset', $link, 'name:discovery');
	}

	public function testHostsTimeouts_checkPrototypeDefault() {
		$link = 'zabbix.php?action=item.prototype.list&context=host&parent_discoveryid='.
			self::$hostid_druleids['Host for timeouts check:zabbix_agent_drule'];
		$this->checkGlobal('reset', $link, 'name:itemprototype');
	}
}

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


require_once __DIR__.'/../common/testTimeoutsDisplay.php';

/**
 * @onBefore prepareTimeoutsData
 *
 * @backup config, hosts, proxy
 *
 * TODO: remove ignoreBrowserErrors after DEV-4233
 * @ignoreBrowserErrors
 */
class testTimeoutsLinkedTemplates extends testTimeoutsDisplay {

	protected static $templateid;
	protected static $hostids;

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
		$linked_template = $template_result['templateids']['Template for linking'];
		$template_druleids = $template_result['discoveryruleids'];

		CDataHelper::call('itemprototype.create', [
			[
				'name' => 'Zabbix agent',
				'key_' => 'zabbix_agent_[{#KEY}]',
				'hostid' => $linked_template,
				'ruleid' => $template_druleids['Template for linking:zabbix_agent_drule'],
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 5
			],
			[
				'name' => 'Zabbix agent (active)',
				'key_' => 'zabbix_agent_active_[{#KEY}]',
				'hostid' => $linked_template,
				'ruleid' => $template_druleids['Template for linking:zabbix_agent_drule'],
				'type' => ITEM_TYPE_ZABBIX_ACTIVE,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 5
			],
			[
				'name' => 'Simple check',
				'key_' => 'simple_check_[{#KEY}]',
				'hostid' => $linked_template,
				'ruleid' => $template_druleids['Template for linking:zabbix_agent_drule'],
				'type' => ITEM_TYPE_SIMPLE,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 5
			],
			[
				'name' => 'SNMP agent',
				'key_' => 'snmp_[{#KEY}]',
				'hostid' => $linked_template,
				'ruleid' => $template_druleids['Template for linking:zabbix_agent_drule'],
				'type' => ITEM_TYPE_SNMP,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'snmp_oid' => 'walk[222]',
				'delay' => 5
			],
			[
				'name' => 'External check',
				'key_' => 'external_[{#KEY}]',
				'hostid' => $linked_template,
				'ruleid' => $template_druleids['Template for linking:zabbix_agent_drule'],
				'type' => ITEM_TYPE_EXTERNAL,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 5
			],
			[
				'name' => 'Database monitor',
				'key_' => 'database_[{#KEY}]',
				'hostid' => $linked_template,
				'ruleid' => $template_druleids['Template for linking:zabbix_agent_drule'],
				'type' => ITEM_TYPE_DB_MONITOR,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'params' => 'test',
				'delay' => 5
			],
			[
				'name' => 'HTTP agent',
				'key_' => 'http_[{#KEY}]',
				'hostid' => $linked_template,
				'ruleid' => $template_druleids['Template for linking:zabbix_agent_drule'],
				'type' => ITEM_TYPE_HTTPAGENT,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'url' => 'test',
				'delay' => 5
			],
			[
				'name' => 'SSH agent',
				'key_' => 'ssh_[{#KEY}]',
				'hostid' => $linked_template,
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
				'hostid' => $linked_template,
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
				'hostid' => $linked_template,
				'ruleid' => $template_druleids['Template for linking:zabbix_agent_drule'],
				'type' => ITEM_TYPE_SCRIPT,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'params' => 'test',
				'delay' => 5
			]
		]);

		CDataHelper::call('host.create', [
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
				'monitored_by' => ZBX_MONITORED_BY_PROXY,
				'proxyid' => $proxyid['Proxy assigned to host'],
				'templates' => [
					'templateid' => $linked_template
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
					'templateid' => $linked_template
				]
			]
		]);
		self::$hostids = CDataHelper::getIds('host');

		$response = CDataHelper::call('template.create', [
			'host' => 'Template for timeout check',
			'groups' => ['groupid' => 1], // Templates.
			'templates' => [
				'templateid' => $linked_template
			]
		]);
		self::$templateid = $response['templateids'][0];
	}

	public function testTimeoutsLinkedTemplates_CheckTemplateItemsMacros() {
		$link = 'zabbix.php?action=item.list&filter_set=1&context=template&filter_hostids%5B0%5D='.self::$templateid;
		$this->checkGlobal('global_macros', $link, 'name:item_list', false, true);
	}

	public function testTimeoutsLinkedTemplates_CheckTemplateDiscoveryMacros() {
		$link = 'host_discovery.php?filter_set=1&context=template&filter_hostids%5B0%5D='.self::$templateid;
		$this->checkGlobal('global_macros', $link, 'name:discovery', false, true);
	}

	public function testTimeoutsLinkedTemplates_CheckTemplatePrototypeMacros() {
		$link = 'host_discovery.php?filter_set=1&context=template&filter_hostids%5B0%5D='.self::$templateid;
		$this->checkGlobal('global_macros', $link, 'name:itemprototype', false, true);
	}

	public function testTimeoutsLinkedTemplates_CheckTemplateItemsCustom() {
		$link = 'zabbix.php?action=item.list&filter_set=1&context=template&filter_hostids%5B0%5D='.self::$templateid;
		$this->checkGlobal('global_custom', $link, 'name:item_list', false, true);
	}

	public function testTimeoutsLinkedTemplates_CheckTemplateDiscoveryCustom() {
		$link = 'host_discovery.php?filter_set=1&context=template&filter_hostids%5B0%5D='.self::$templateid;
		$this->checkGlobal('global_custom', $link, 'name:discovery', false, true);
	}

	public function testTimeoutsLinkedTemplates_CheckTemplatePrototypeCustom() {
		$link = 'host_discovery.php?filter_set=1&context=template&filter_hostids%5B0%5D='.self::$templateid;
		$this->checkGlobal('global_custom', $link, 'name:itemprototype', false, true);
	}

	public function testTimeoutsLinkedTemplates_CheckTemplateItemsDefault() {
		$link = 'zabbix.php?action=item.list&filter_set=1&context=template&filter_hostids%5B0%5D='.self::$templateid;
		$this->checkGlobal('global_default', $link, 'name:item_list', false, true);
	}

	public function testTimeoutsLinkedTemplates_CheckTemplateDiscoveryDefault() {
		$link = 'host_discovery.php?filter_set=1&context=template&filter_hostids%5B0%5D='.self::$templateid;
		$this->checkGlobal('global_default', $link, 'name:discovery', false, true);
	}

	public function testTimeoutsLinkedTemplates_CheckTemplatePrototypeDefault() {
		$link = 'host_discovery.php?filter_set=1&context=template&filter_hostids%5B0%5D='.self::$templateid;
		$this->checkGlobal('global_default', $link, 'name:itemprototype', false, true);
	}

	public function testTimeoutsLinkedTemplates_CheckHostItemsMacros() {
		$link = 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='.
				self::$hostids['Host for linked timeout check'];
		$this->checkGlobal('global_macros', $link, 'name:item_list', false, true);
	}

	public function testTimeoutsLinkedTemplates_CheckHostDiscoveryMacros() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
				self::$hostids['Host for linked timeout check'];
		$this->checkGlobal('global_macros', $link, 'name:discovery', false, true);
	}

	public function testTimeoutsLinkedTemplates_CheckHostPrototypeMacros() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
				self::$hostids['Host for linked timeout check'];
		$this->checkGlobal('global_macros', $link, 'name:itemprototype', false, true);
	}

	public function testTimeoutsLinkedTemplates_CheckHostItemsCustom() {
		$link = 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='.
				self::$hostids['Host for linked timeout check'];
		$this->checkGlobal('global_custom', $link, 'name:item_list', false, true);
	}

	public function testTimeoutsLinkedTemplates_CheckHostDiscoveryCustom() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
				self::$hostids['Host for linked timeout check'];
		$this->checkGlobal('global_custom', $link, 'name:discovery', false, true);
	}

	public function testTimeoutsLinkedTemplates_CheckHostPrototypeCustom() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
				self::$hostids['Host for linked timeout check'];
		$this->checkGlobal('global_custom', $link, 'name:itemprototype', false, true);
	}

	public function testTimeoutsLinkedTemplates_CheckHostItemsDefault() {
		$link = 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='.
				self::$hostids['Host for linked timeout check'];
		$this->checkGlobal('global_default', $link, 'name:item_list', false, true);
	}

	public function testTimeoutsLinkedTemplates_CheckHostDiscoveryDefault() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
				self::$hostids['Host for linked timeout check'];
		$this->checkGlobal('global_default', $link, 'name:discovery', false, true);
	}

	public function testTimeoutsLinkedTemplates_CheckHostPrototypeDefault() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
				self::$hostids['Host for linked timeout check'];
		$this->checkGlobal('global_default', $link, 'name:itemprototype', false, true);
	}

	public function testTimeoutsLinkedTemplates_CheckProxyHostItemsMacros() {
		$link = 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='.
				self::$hostids['Host for linked timeout check with proxy'];
		$this->checkGlobal('proxy_macros', $link, 'name:item_list', true, true);
	}

	public function testTimeoutsLinkedTemplates_CheckProxyHostDiscoveryMacros() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
				self::$hostids['Host for linked timeout check with proxy'];
		$this->checkGlobal('proxy_macros', $link, 'name:discovery', true, true);
	}

	public function testTimeoutsLinkedTemplates_CheckProxyHostPrototypeMacros() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
				self::$hostids['Host for linked timeout check with proxy'];
		$this->checkGlobal('proxy_macros', $link, 'name:itemprototype', true, true);
	}

	public function testTimeoutsLinkedTemplates_CheckProxyHostItemsCustom() {
		$link = 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='.
				self::$hostids['Host for linked timeout check with proxy'];
		$this->checkGlobal('proxy_custom', $link, 'name:item_list', true, true);
	}

	public function testTimeoutsLinkedTemplates_CheckProxyHostDiscoveryCustom() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
				self::$hostids['Host for linked timeout check with proxy'];
		$this->checkGlobal('proxy_custom', $link, 'name:discovery', true, true);
	}

	public function testTimeoutsLinkedTemplates_CheckProxyHostPrototypeCustom() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
				self::$hostids['Host for linked timeout check with proxy'];
		$this->checkGlobal('proxy_custom', $link, 'name:itemprototype', true, true);
	}

	public function testTimeoutsLinkedTemplates_CheckProxyHostItemsDefault() {
		$link = 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='.
				self::$hostids['Host for linked timeout check with proxy'];
		$this->checkGlobal('global_default', $link, 'name:item_list', true, true);
	}

	public function testTimeoutsLinkedTemplates_CheckProxyHostDiscoveryDefault() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
				self::$hostids['Host for linked timeout check with proxy'];
		$this->checkGlobal('global_default', $link, 'name:discovery', true, true);
	}

	public function testTimeoutsLinkedTemplates_CheckProxyHostPrototypeDefault() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
				self::$hostids['Host for linked timeout check with proxy'];
		$this->checkGlobal('global_default', $link, 'name:itemprototype', true, true);
	}
}

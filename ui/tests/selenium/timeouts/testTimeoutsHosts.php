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


require_once dirname(__FILE__).'/../common/testTimeoutsDisplay.php';

/**
 * @onBefore prepareTimeoutsData
 *
 * @backup config, hosts, proxy
 */
class testTimeoutsHosts extends testTimeoutsDisplay {

	protected static $hostids;
	protected static $hostids_druleids;

	public static function prepareTimeoutsData() {
		CDataHelper::call('proxy.create', [
			[
				'name' => 'Proxy assigned to host',
				'operating_mode' => 0
			]
		]);
		$proxyid = CDataHelper::getIds('name');

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
				'discoveryrules' => [
					[
						'name' => 'Zabbix agent',
						'key_' => 'zabbix_agent_drule',
						'type' => ITEM_TYPE_ZABBIX,
						'delay' => 5
					]
				]
			],
			[
				'host' => 'Host for timeouts check with proxy',
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
				'discoveryrules' => [
					[
						'name' => 'Zabbix agent',
						'key_' => 'zabbix_agent_drule',
						'type' => ITEM_TYPE_ZABBIX,
						'delay' => 5
					]
				]
			]
		]);
		self::$hostids = $host_result['hostids'];
		self::$hostids_druleids = $host_result['discoveryruleids'];

		CDataHelper::call('host.update', [
			[
				'hostid' => self::$hostids['Host for timeouts check with proxy'],
				'monitored_by' => ZBX_MONITORED_BY_PROXY,
				'proxyid' => $proxyid['Proxy assigned to host']
			]
		]);
	}

	public function testTimeoutsHosts_CheckItemsMacros() {
		$link = 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='.
				self::$hostids['Host for timeouts check'];
		$this->checkGlobal('global_macros', $link, 'Create item');
	}

	public function testTimeoutsHosts_CheckDiscoveryMacros() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
				self::$hostids['Host for timeouts check'];
		$this->checkGlobal('global_macros', $link, 'Create discovery rule');
	}

	public function testTimeoutsHosts_CheckPrototypeMacros() {
		$link = 'zabbix.php?action=item.prototype.list&context=host&parent_discoveryid='.
				self::$hostids_druleids['Host for timeouts check:zabbix_agent_drule'];
		$this->checkGlobal('global_macros', $link, 'Create item prototype');
	}

	public function testTimeoutsHosts_CheckItemsCustom() {
		$link = 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='.
				self::$hostids['Host for timeouts check'];
		$this->checkGlobal('global_custom', $link, 'Create item');
	}

	public function testTimeoutsHosts_CheckDiscoveryCustom() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
				self::$hostids['Host for timeouts check'];
		$this->checkGlobal('global_custom', $link, 'Create discovery rule');
	}

	public function testTimeoutsHosts_CheckPrototypeCustom() {
		$link = 'zabbix.php?action=item.prototype.list&context=host&parent_discoveryid='.
				self::$hostids_druleids['Host for timeouts check:zabbix_agent_drule'];
		$this->checkGlobal('global_custom', $link, 'Create item prototype');
	}

	public function testTimeoutsHosts_CheckItemsDefault() {
		$link = 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='.
				self::$hostids['Host for timeouts check'];
		$this->checkGlobal('global_default', $link, 'Create item');
	}

	public function testTimeoutsHosts_CheckDiscoveryDefault() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
				self::$hostids['Host for timeouts check'];
		$this->checkGlobal('global_default', $link, 'Create discovery rule');
	}

	public function testTimeoutsHosts_CheckPrototypeDefault() {
		$link = 'zabbix.php?action=item.prototype.list&context=host&parent_discoveryid='.
				self::$hostids_druleids['Host for timeouts check:zabbix_agent_drule'];
		$this->checkGlobal('global_default', $link, 'Create item prototype');
	}

	public function testTimeoutsHosts_CheckItemsProxyDefault() {
		$link = 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='.
				self::$hostids['Host for timeouts check with proxy'];
		$this->checkGlobal('global_default', $link, 'Create item', true);
	}

	public function testTimeoutsHosts_CheckDiscoveryProxyDefault() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
				self::$hostids['Host for timeouts check with proxy'];
		$this->checkGlobal('global_default', $link, 'Create discovery rule', true);
	}

	public function testTimeoutsHosts_CheckPrototypeProxyDefault() {
		$link = 'zabbix.php?action=item.prototype.list&context=host&parent_discoveryid='.
				self::$hostids_druleids['Host for timeouts check with proxy:zabbix_agent_drule'];
		$this->checkGlobal('global_default', $link, 'Create item prototype', true);
	}

	public function testTimeoutsHosts_CheckItemsProxyMacros() {
		$link = 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='.
				self::$hostids['Host for timeouts check with proxy'];
		$this->checkGlobal('proxy_macros', $link, 'Create item', true);
	}

	public function testTimeoutsHosts_CheckDiscoveryProxyMacros() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
				self::$hostids['Host for timeouts check with proxy'];
		$this->checkGlobal('proxy_macros', $link, 'Create discovery rule', true);
	}

	public function testTimeoutsHosts_CheckPrototypeProxyMacros() {
		$link = 'zabbix.php?action=item.prototype.list&context=host&parent_discoveryid='.
				self::$hostids_druleids['Host for timeouts check with proxy:zabbix_agent_drule'];
		$this->checkGlobal('proxy_macros', $link, 'Create item prototype', true);
	}

	public function testTimeoutsHosts_CheckItemsProxyCustom() {
		$link = 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='.
				self::$hostids['Host for timeouts check with proxy'];
		$this->checkGlobal('proxy_custom', $link, 'Create item', true);
	}

	public function testTimeoutsHosts_CheckDiscoveryProxyCustom() {
		$link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.
				self::$hostids['Host for timeouts check with proxy'];
		$this->checkGlobal('proxy_custom', $link, 'Create discovery rule', true);
	}

	public function testTimeoutsHosts_CheckPrototypeProxyCustom() {
		$link = 'zabbix.php?action=item.prototype.list&context=host&parent_discoveryid='.
				self::$hostids_druleids['Host for timeouts check with proxy:zabbix_agent_drule'];
		$this->checkGlobal('proxy_custom', $link, 'Create item prototype', true);
	}
}

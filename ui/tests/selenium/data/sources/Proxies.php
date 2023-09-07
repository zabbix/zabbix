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


class Proxies {

	/**
	 * Names of enabled hosts, monitored by proxies.
	 *
	 * @var array
	 */
	private static $enabled_hosts = [
			'enabled_host1',
			'enabled_host2',
			'enabled_host3',
			'enabled_host4',
			'enabled_host5',
			'enabled_host6',
			'enabled_host7',
			'enabled_host8'
	];

	/**
	 * Names of disabled hosts, monitored by proxies.
	 *
	 * @var array
	 */
	private static $disabled_hosts = [
			'disabled_host1',
			'disabled_host2',
			'disabled_host3',
			'disabled_host4',
			'disabled_host5',
			'disabled_host6',
			'disabled_host7',
			'disabled_host8'
	];

	/**
	 * Names of active proxies for proxy tests.
	 *
	 * @var array
	 */
	private static $active_proxies = [
			'active_proxy1',
			'active_proxy2',
			'active_proxy3',
			'active_proxy4',
			'active_proxy5',
			'active_proxy6',
			'active_proxy7',
			'active_current',
			'active_unknown',
			'Active proxy 1',
			'Active proxy 2',
			'Active proxy 3',
			'Active proxy to delete',
			'Proxy_1 for filter',
			'Proxy_2 for filter'
	];

	/**
	 * Names of passive proxies for proxy tests.
	 *
	 * @var array
	 */
	private static $passive_proxies = [
			'passive_proxy1',
			'passive_proxy2',
			'passive_proxy3',
			'passive_proxy4',
			'passive_proxy5',
			'passive_proxy6',
			'passive_proxy7',
			'passive_outdated',
			'passive_unsupported',
			'Passive proxy 1',
			'Passive proxy 2',
			'Passive proxy 3',
			'Passive proxy to delete'
	];

	/**
	 * Preparing proxies and hosts.
	 */
	public static function load() {
		// Create host group.
		$hostgroups = CDataHelper::call('hostgroup.create', [['name' => 'HG_for_proxies']]);
		$hostgroupid = $hostgroups['groupids'][0];

		// Create enabled hosts.
		$enabled_hosts_data = [];
		foreach (self::$enabled_hosts as $host) {
			$enabled_hosts_data[] = [
				'host' => $host,
				'groups' => [['groupid' => $hostgroupid]],
				'status' => 0
			];
		}

		$enabled_hosts = CDataHelper::call('host.create', $enabled_hosts_data);
		$enabled_hostids = CDataHelper::getIds('host');

		// Create hosts for filtering scenario.
		CDataHelper::call('host.create',  [
			['host' => 'Host_1 with proxy', 'groups' => [['groupid' => 4]]],
			['host' => 'Host_2 with proxy', 'groups' => [['groupid' => 4]]]
		]);
		$filter_hostids = CDataHelper::getIds('host');

		// Disabled hosts data.
		$disabled_hosts_data = [];
		foreach (self::$disabled_hosts as $host) {
			$disabled_hosts_data[] = [
				'host' => $host,
				'groups' => [['groupid' => $hostgroupid]],
				'status' => HOST_STATUS_NOT_MONITORED
			];
		}
		CDataHelper::call('host.create', $disabled_hosts_data);
		$disabled_hostids = CDataHelper::getIds('host');

		// Create active proxies.
		$active_proxy_data = [];

		foreach (self::$active_proxies as $proxy) {
			$active_proxy_data[] = [
				'name' => $proxy,
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			];
		}

		CDataHelper::call('proxy.create', $active_proxy_data);
		$active_proxyids = CDataHelper::getIds('name');

		// Create passive proxies.
		$passive_proxy_data = [];

		foreach (self::$passive_proxies as $proxy) {
			$passive_proxy_data[] = [
				'name' => $proxy,
				'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
				'address' => '127.0.0.1',
				'port' => '10051'
			];
		}

		CDataHelper::call('proxy.create', $passive_proxy_data);
		$passive_proxyids = CDataHelper::getIds('name');

		// Add hosts to proxies.
		CDataHelper::call('proxy.update', [
			[
				'proxyid' => $active_proxyids['active_proxy1'],
				'hosts' => [
					['hostid' => $enabled_hostids['enabled_host1']]
				]
			],
			[
				'proxyid' => $passive_proxyids['passive_proxy1'],
				'hosts' => [
					['hostid' => $disabled_hostids['disabled_host1']]
				]
			],
			[
				'proxyid' => $active_proxyids['active_proxy2'],
				'hosts' => [
					['hostid' => $enabled_hostids['enabled_host2']],
					['hostid' => $enabled_hostids['enabled_host3']]
				]
			],
			[
				'proxyid' => $passive_proxyids['passive_proxy2'],
				'hosts' => [
					['hostid' => $enabled_hostids['enabled_host4']],
					['hostid' => $enabled_hostids['enabled_host5']]
				]
			],
			[
				'proxyid' => $active_proxyids['active_proxy3'],
				'hosts' => [
					['hostid' => $disabled_hostids['disabled_host2']],
					['hostid' => $disabled_hostids['disabled_host3']]
				]
			],
			[
				'proxyid' => $passive_proxyids['passive_proxy3'],
				'hosts' => [
					['hostid' => $disabled_hostids['disabled_host4']],
					['hostid' => $disabled_hostids['disabled_host5']]
				]
			],
			[
				'proxyid' => $active_proxyids['active_proxy4'],
				'hosts' => [
					['hostid' => $enabled_hostids['enabled_host6']],
					['hostid' => $disabled_hostids['disabled_host6']]
				]
			],
			[
				'proxyid' => $passive_proxyids['passive_proxy4'],
				'hosts' => [
					['hostid' => $enabled_hostids['enabled_host7']],
					['hostid' => $enabled_hostids['enabled_host8']],
					['hostid' => $disabled_hostids['disabled_host7']],
					['hostid' => $disabled_hostids['disabled_host8']]
				]
			],
			[
				'proxyid' => $active_proxyids['Active proxy 1'],
				'hosts' => [
					['hostid' => 99136] // Test item host.
				]
			],
			[
				'proxyid' => $active_proxyids['Proxy_1 for filter'],
				'hosts' => [
					['hostid' => $filter_hostids['Host_1 with proxy']]
				]
			],
			[
				'proxyid' => $active_proxyids['Proxy_2 for filter'],
				'hosts' => [
					['hostid' => $filter_hostids['Host_2 with proxy']]
				]
			]
		]);

		$proxies = CDataHelper::call('proxy.create',
			[['name' => 'Delete Proxy used in Network discovery rule', 'operating_mode' => 0]]
		);
		$delete_proxy = $proxies['proxyids'][0];

		CDataHelper::call('drule.create', [
			[
				'name' => 'Discovery rule for proxy delete test',
				'iprange' => '192.168.1.1-255',
				'proxyid' => $delete_proxy,
				'dchecks' => [['type' => SVC_IMAP, 'ports' => 10050]]
			]
		]);

		/**
		 * Add proxies versions.
		 * Supported version "60400" is hardcoded one time, so that no need to change it,
		 * even if newer versions of Zabbix are released.
		 */
		DBexecute('UPDATE proxy_rtdata SET version=60400, compatibility=1 WHERE proxyid='.zbx_dbstr($active_proxyids['active_current']));
		DBexecute('UPDATE proxy_rtdata SET version=60200, compatibility=2 WHERE proxyid='.zbx_dbstr($passive_proxyids['passive_outdated']));
		DBexecute('UPDATE proxy_rtdata SET version=0, compatibility=0 WHERE proxyid='.zbx_dbstr($active_proxyids['active_unknown']));
		DBexecute('UPDATE proxy_rtdata SET version=50401, compatibility=3 WHERE proxyid='.zbx_dbstr($passive_proxyids['passive_unsupported']));
		DBexecute('UPDATE config SET server_status='.zbx_dbstr('{"version": "6.4.0alpha1"}'));
	}
}

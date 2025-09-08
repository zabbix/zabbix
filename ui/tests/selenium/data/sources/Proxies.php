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
	 * Preparing proxies, proxy groups and hosts.
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

		// Create Proxy groups.
		CDataHelper::call('proxygroup.create', [
			[
				'name' => 'Online proxy group',
				'failover_delay' => '10',
				'min_online' => '1',
				'description' => 'Online proxy group that includes multiple proxies'
			],
			[
				'name' => '2nd Online proxy group',
				'failover_delay' => '666',
				'min_online' => '666',
				'description' => 'Another online proxy group that includes two proxies'
			],
			[
				'name' => 'Degrading proxy group',
				'failover_delay' => '15m',
				'min_online' => '100',
				'description' => 'Degrading proxy group that includes passive proxies'
			],
			[
				'name' => 'Offline group',
				'failover_delay' => '900s',
				'min_online' => '1',
				'description' => 'Offline proxy group that includes a proxy'
			],
			[
				'name' => 'Default values - recovering'
			],
			[
				'name' => '⭐️😀⭐Smiley प्रॉक्सी 团体⭐️😀⭐ - unknown',
				'failover_delay' => '123s',
				'min_online' => '123',
				'description' => 'Proxy group that has special utf8mb4 symbols in its name and has unknown state'
			],
			[
				'name' => 'Group without proxies',
				'failover_delay' => '899',
				'min_online' => '999',
				'description' => 'Group without proxies - state should not be displayed'
			],
			[
				'name' => 'Group without proxies with linked host',
				'failover_delay' => '10',
				'min_online' => '1',
				'description' => 'Group without proxies, but with a linked host - should not be possible to delete'
			],
			[
				'name' => 'Delete me 1',
				'failover_delay' => '10',
				'min_online' => '1',
				'description' => 'Group for mass delete scenario'
			],
			[
				'name' => 'Delete me 2',
				'failover_delay' => '10',
				'min_online' => '1',
				'description' => '2nd group for mass delete scenario'
			]
		]);
		$proxy_groupids = CDataHelper::getIds('name');

		$proxyids = array_merge($active_proxyids, $passive_proxyids);
		$group_parameters = [
			'Online proxy group' => [
				'state' => 3,
				'proxies' => ['Active proxy 1', 'Active proxy 2', 'Active proxy 3', 'Active proxy to delete',
						'Proxy_1 for filter', 'Proxy_2 for filter'
				],
				'active_proxies' => ['Active proxy 1', 'Active proxy 2', 'Active proxy 3']
			],
			'2nd Online proxy group' => [
				'state' => 3,
				'proxies' => ['active_proxy3', 'active_proxy5'],
				'active_proxies' => ['active_proxy3']
			],
			'Degrading proxy group' => [
				'state' => 4,
				'proxies' => ['Passive proxy 1', 'passive_proxy1', 'passive_unsupported']
			],
			'Offline group' => [
				'state' => 1,
				'proxies' => ['active_proxy7']
			],
			'Default values - recovering' => [
				'state' => 2,
				'proxies' => ['passive_proxy7']
			],
			'⭐️😀⭐Smiley प्रॉक्सी 团体⭐️😀⭐ - unknown' => [
				'state' => 0,
				'proxies' => ['passive_outdated']
			]
		];
		foreach ($group_parameters as $group => $params) {
			DBexecute('UPDATE proxy_group_rtdata SET state='.zbx_dbstr($params['state']).' WHERE proxy_groupid='.
					zbx_dbstr($proxy_groupids[$group])
			);

			if (array_key_exists('proxies', $params)) {
				foreach ($params['proxies'] as $proxy_name) {
					$proxy_update_data[] = [
						'proxyid' => $proxyids[$proxy_name],
						'proxy_groupid' => $proxy_groupids[$group],
						'local_address' => '127.0.0.1',
						'local_port' => '10055'
					];
				}

				CDataHelper::call('proxy.update', $proxy_update_data);
			}

			if (array_key_exists('active_proxies', $params)) {
				foreach ($params['active_proxies'] as $active_proxy) {
					DBexecute('UPDATE proxy_rtdata SET state=2 WHERE proxyid='.zbx_dbstr($proxyids[$active_proxy]));
				}
			}
		}

		// Link a host directly to a proxy group without proxies.
		CDataHelper::call('host.create', [
			[
				'host' => 'Host linked to proxy group',
				'groups' => [['groupid' => $hostgroupid]],
				'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
				'proxy_groupid' => $proxy_groupids['Group without proxies with linked host']
			],
			[
				'host' => 'Host linked to proxy group 2',
				'groups' => [['groupid' => $hostgroupid]],
				'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
				'proxy_groupid' => $proxy_groupids['Online proxy group']
			]
		]);
	}
}

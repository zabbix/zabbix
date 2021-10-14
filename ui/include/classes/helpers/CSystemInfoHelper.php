<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * Class collecting various system information aspects.
 */
class CSystemInfoHelper {

	/**
	 * Prepare data used to compile as System information.
	 *
	 * @return array
	 */
	public static function getData(): array {
		global $DB, $ZBX_SERVER_STANDALONE, $ZBX_SERVER, $ZBX_SERVER_PORT;

		$data = [
			'status' => static::getServerStatus($ZBX_SERVER, (int) $ZBX_SERVER_PORT),
			'server_details' => '',
			'float_double_precision' => $DB['DOUBLE_IEEE754']
		];

		$db_backend = DB::getDbBackend();
		$data['encoding_warning'] = $db_backend->checkEncoding() ? '' : $db_backend->getWarning();

		$ha_nodes = [];
		$ha_cluster_enabled = false;

		if (!$ZBX_SERVER_STANDALONE) {
			$ha_nodes = API::HaNode()->get([
				'output' => ['name', 'address', 'port', 'lastaccess', 'status'],
				'preservekeys' => true,
				'sortfield' => 'status',
				'sortorder' => 'DESC'
			], false);

			$ha_cluster_enabled = (bool) $ha_nodes;

			foreach ($ha_nodes as $node) {
				if ($node['name'] === '') {
					$ha_cluster_enabled = false;
					$ha_nodes = [];
					break;
				}
			}
		}

		$data['ha_cluster_enabled'] = $ha_cluster_enabled;

		if ($ha_cluster_enabled) {
			$failover_delay = CSettingsHelper::getGlobal(CSettingsHelper::HA_FAILOVER_DELAY);
			$failover_delay_seconds = timeUnitToSeconds($failover_delay);
			$data['failover_delay'] = secondsToPeriod($failover_delay_seconds);
		}

		if (CWebUser::getType() != USER_TYPE_SUPER_ADMIN) {
			return $data;
		}

		$data['server_details'] = $ZBX_SERVER.':'.$ZBX_SERVER_PORT;
		$data['ha_nodes'] = $ha_nodes;

		$setup = new CFrontendSetup();
		$requirements = $setup->checkRequirements();
		$requirements[] = $setup->checkSslFiles();
		$data['requirements'] = $requirements;

		$db_version_status = CSettingsHelper::getGlobal(CSettingsHelper::DBVERSION_STATUS);
		$data['dbversion_status'] = $db_version_status === '' ? [] : json_decode($db_version_status);

		return $data;
	}

	/**
	 * Get a summary of running server stats.
	 *
	 * @param string $ZBX_SERVER
	 * @param int $ZBX_SERVER_PORT
	 *
	 * @return array
	 */
	private static function getServerStatus(string $ZBX_SERVER, int $ZBX_SERVER_PORT): array {
		$status = [
			'is_running' => false,
			'has_status' => false
		];

		$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::SOCKET_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
		);
		$status['is_running'] = $server->isRunning(CSessionHelper::getId());

		if ($status['is_running'] === false) {
			return $status;
		}

		$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)), 15, ZBX_SOCKET_BYTES_LIMIT
		);
		$server_status = $server->getStatus(CSessionHelper::getId());
		$status['has_status'] = (bool) $server_status;

		if ($server_status === false) {
			error($server->getError());
			return $status;
		}

		$status += [
			'triggers_count_disabled' => 0,
			'triggers_count_off' => 0,
			'triggers_count_on' => 0,
			'items_count_monitored' => 0,
			'items_count_disabled' => 0,
			'items_count_not_supported' => 0,
			'hosts_count_monitored' => 0,
			'hosts_count_not_monitored' => 0,
			'hosts_count_template' => 0,
			'users_count' => 0,
			'users_online' => 0
		];

		// hosts
		foreach ($server_status['template stats'] as $stats) {
			$status['hosts_count_template'] += $stats['count'];
		}

		foreach ($server_status['host stats'] as $stats) {
			if ($stats['attributes']['proxyid'] == 0) {
				switch ($stats['attributes']['status']) {
					case HOST_STATUS_MONITORED:
						$status['hosts_count_monitored'] += $stats['count'];
						break;

					case HOST_STATUS_NOT_MONITORED:
						$status['hosts_count_not_monitored'] += $stats['count'];
						break;
				}
			}
		}

		$status['hosts_count'] = $status['hosts_count_monitored'] + $status['hosts_count_not_monitored'];

		// items
		foreach ($server_status['item stats'] as $stats) {
			if ($stats['attributes']['proxyid'] == 0) {
				switch ($stats['attributes']['status']) {
					case ITEM_STATUS_ACTIVE:
						if (array_key_exists('state', $stats['attributes'])) {
							switch ($stats['attributes']['state']) {
								case ITEM_STATE_NORMAL:
									$status['items_count_monitored'] += $stats['count'];
									break;

								case ITEM_STATE_NOTSUPPORTED:
									$status['items_count_not_supported'] += $stats['count'];
									break;
							}
						}
						break;

					case ITEM_STATUS_DISABLED:
						$status['items_count_disabled'] += $stats['count'];
						break;
				}
			}
		}

		$status['items_count'] = $status['items_count_monitored'] + $status['items_count_disabled']
				+ $status['items_count_not_supported'];

		// triggers
		foreach ($server_status['trigger stats'] as $stats) {
			switch ($stats['attributes']['status']) {
				case TRIGGER_STATUS_ENABLED:
					if (array_key_exists('value', $stats['attributes'])) {
						switch ($stats['attributes']['value']) {
							case TRIGGER_VALUE_FALSE:
								$status['triggers_count_off'] += $stats['count'];
								break;

							case TRIGGER_VALUE_TRUE:
								$status['triggers_count_on'] += $stats['count'];
								break;
						}
					}
					break;

				case TRIGGER_STATUS_DISABLED:
					$status['triggers_count_disabled'] += $stats['count'];
					break;
			}
		}

		$status['triggers_count_enabled'] = $status['triggers_count_off'] + $status['triggers_count_on'];
		$status['triggers_count'] = $status['triggers_count_enabled'] + $status['triggers_count_disabled'];

		// users
		foreach ($server_status['user stats'] as $stats) {
			switch ($stats['attributes']['status']) {
				case ZBX_SESSION_ACTIVE:
					$status['users_online'] += $stats['count'];
					break;

				case ZBX_SESSION_PASSIVE:
					$status['users_count'] += $stats['count'];
					break;
			}
		}

		$status['users_count'] += $status['users_online'];

		// performance
		if (array_key_exists('required performance', $server_status)) {
			$status['vps_total'] = 0;

			foreach ($server_status['required performance'] as $stats) {
				if ($stats['attributes']['proxyid'] == 0) {
					$status['vps_total'] += $stats['count'];
				}
			}
		}

		return $status;
	}
}

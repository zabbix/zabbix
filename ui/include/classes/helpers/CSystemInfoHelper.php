<?php declare(strict_types = 0);
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
 * Class collecting various system information aspects.
 */
class CSystemInfoHelper {

	/**
	 * Prepare data used to compile as System information.
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	public static function getData(): array {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$data = [
			'is_global_scripts_enabled' => CSettingsHelper::isGlobalScriptsEnabled(),
			'is_software_update_check_enabled' => CSettingsHelper::isSoftwareUpdateCheckEnabled(),
			'status' => static::getServerStatus($ZBX_SERVER, $ZBX_SERVER_PORT),
			'server_details' => '',
			'failover_delay' => 0
		];

		if ($data['is_software_update_check_enabled']) {
			$data['software_update_check_data'] = [];
			$check_data = CSettingsHelper::getSoftwareUpdateCheckData();

			if ($check_data) {
				$data['software_update_check_data']['lastcheck'] = $check_data['lastcheck'];

				if ($check_data['versions']) {
					$data['software_update_check_data'] += self::getSoftwareUpdateVersionDetails(
						$check_data['versions'],
						ZABBIX_VERSION
					);
				}
			}
		}

		$db_backend = DB::getDbBackend();
		$data['encoding_warning'] = $db_backend->checkEncoding() ? '' : $db_backend->getWarning();

		foreach (CSettingsHelper::getDbVersionStatus() as $dbversion) {
			if (array_key_exists('history_pk', $dbversion)) {
				$data['history_pk'] = ($dbversion['history_pk'] == 1);

				break;
			}
		}

		$housekeeper_warnings = CHousekeepingHelper::getWarnings();

		if (array_key_exists(CHousekeepingHelper::OVERRIDE_NEEDED_HISTORY, $housekeeper_warnings)
				&& CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_MODE) == 1
				&& CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL) == 0) {
			$data[CHousekeepingHelper::OVERRIDE_NEEDED_HISTORY] = true;
		}

		if (array_key_exists(CHousekeepingHelper::OVERRIDE_NEEDED_TRENDS, $housekeeper_warnings)
				&& CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_MODE) == 1
				&& CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL) == 0) {
			$data[CHousekeepingHelper::OVERRIDE_NEEDED_TRENDS] = true;
		}

		$ha_cluster_enabled = false;

		$ha_nodes = API::getApiService('hanode')->get([
			'output' => ['name', 'address', 'port', 'lastaccess', 'status'],
			'sortfield' => 'status',
			'sortorder' => 'DESC'
		], false);

		foreach ($ha_nodes as $index => $node) {
			if ($node['name'] === '' && $node['status'] == ZBX_NODE_STATUS_ACTIVE) {
				$ha_cluster_enabled = false;
				$ha_nodes = [];
				break;
			}
			elseif ($node['status'] == ZBX_NODE_STATUS_STANDBY || $node['status'] == ZBX_NODE_STATUS_ACTIVE) {
				$ha_cluster_enabled = true;
			}

			if ($node['status'] == ZBX_NODE_STATUS_ACTIVE) {
				$server = new CZabbixServer($node['address'], $node['port'],
					timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
					timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::SOCKET_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT);

				if (!$server->canConnect(CSessionHelper::getId())) {
					$ha_nodes[$index]['status'] = ZBX_NODE_STATUS_UNAVAILABLE;
				}
			}
		}

		$data['ha_cluster_enabled'] = $ha_cluster_enabled;
		$data['ha_nodes'] = $ha_nodes;

		if ($ha_cluster_enabled) {
			$failover_delay = CSettingsHelper::get(CSettingsHelper::HA_FAILOVER_DELAY);
			$failover_delay_seconds = timeUnitToSeconds($failover_delay);
			$data['failover_delay'] = secondsToPeriod($failover_delay_seconds);
		}

		if (CWebUser::getType() != USER_TYPE_SUPER_ADMIN) {
			return $data;
		}

		if ($ZBX_SERVER !== null && $ZBX_SERVER_PORT !== null) {
			$data['server_details'] = $ZBX_SERVER.':'.$ZBX_SERVER_PORT;
		}
		elseif (count($ha_nodes) == 1) {
			$data['server_details'] = $ha_nodes[0]['address'].':'.$ha_nodes[0]['port'];
		}

		$setup = new CFrontendSetup();
		$setup->setDefaultLang(CWebUser::$data['lang']);
		$requirements = $setup->checkRequirements();
		$requirements[] = $setup->checkSslFiles();

		$data['requirements'] = $requirements;
		$data['dbversion_status'] = CSettingsHelper::getDbVersionStatus();

		return $data;
	}

	/**
	 * Get version details from versions list supplied by software update request.
	 *
	 * @param array  $versions  Array of versions supplied by software update request.
	 * @param string $version   Version number string, should be in form "<minor>.<major>.<patch>"
	 *                          Version minor and major numbers required, patch is optional.
	 */
	public static function getSoftwareUpdateVersionDetails(array $versions, string $version): array {
		$data = [];
		$lts_version = [];
		$current_version = [];
		$major_minor = implode('.', sscanf($version, '%d.%d'));
		CArrayHelper::sort($versions, [['field' => 'version', 'order' => ZBX_SORT_DOWN]]);

		foreach ($versions as $version) {
			if (version_compare($version['version'], $major_minor, '<')) {
				break;
			}

			if (!$lts_version && explode('.', $version['version'])[1] === '0') {
				$lts_version = $version;
			}

			if ($version['version'] === $major_minor) {
				$current_version = $version;

				break;
			}
		}

		if ($current_version) {
			$data['end_of_full_support'] = $current_version['end_of_full_support'];
			$data['latest_release'] = $current_version['end_of_full_support'] && $lts_version
				? $lts_version['latest_release']['release']
				: $current_version['latest_release']['release'];
		}

		return $data;
	}

	/**
	 * Get a summary of running server stats.
	 *
	 * @param string|null  $ZBX_SERVER
	 * @param int|null     $ZBX_SERVER_PORT
	 *
	 * @return array
	 */
	private static function getServerStatus(?string $ZBX_SERVER, ?int $ZBX_SERVER_PORT): array {
		$status = [
			'is_running' => false,
			'has_status' => false
		];

		if ($ZBX_SERVER === null && $ZBX_SERVER_PORT === null) {
			return $status;
		}

		$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::SOCKET_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
		);

		$status['is_running'] = $server->isRunning() || $server->canConnect(CSessionHelper::getId());

		if (CWebUser::getType() != USER_TYPE_SUPER_ADMIN) {
			return $status;
		}

		if ($status['is_running'] === false) {
			if ($server->getErrorCode() === CZabbixServer::ERROR_CODE_TLS) {
				error($server->getError());
			}
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

		$status['server_version'] = $server_status['server stats']['version'];

		return $status;
	}
}

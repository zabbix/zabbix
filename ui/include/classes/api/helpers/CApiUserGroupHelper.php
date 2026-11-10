<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * Helper class containing methods for checking permissions to proxies and proxy groups.
 */
class CApiUserGroupHelper {
	public static function getProxyPermissionsCondition(string $alias): string {
		if (CApiService::$userData['ugsetid'] == 0) {
			return '1=0';
		}

		$usrgrpids = self::getUserGroupIdsByPermissionLists();

		$proxy_mode_conditions = [];
		$proxy_group_mode_conditions = [];

		if ($usrgrpids['proxy']['deny_list']) {
			$proxy_mode_conditions[] = 'NOT EXISTS ('.
				'SELECT NULL'.
				' FROM usrgrp_proxy ugp'.
				' WHERE '.$alias.'.proxyid=ugp.proxyid'.
					' AND '.dbConditionId('ugp.usrgrpid', $usrgrpids['proxy']['deny_list']).
			')';
		}

		if ($usrgrpids['proxy']['allow_list']) {
			$proxy_mode_conditions[] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM usrgrp_proxy ugp'.
				' WHERE '.$alias.'.proxyid=ugp.proxyid'.
					' AND '.dbConditionId('ugp.usrgrpid', $usrgrpids['proxy']['allow_list']).
			')';
		}

		if ($usrgrpids['proxy_group']['deny_list']) {
			$proxy_group_mode_conditions[] = 'NOT EXISTS ('.
				'SELECT NULL'.
				' FROM usrgrp_proxy_group ugpg'.
				' WHERE '.$alias.'.proxy_groupid=ugpg.proxy_groupid'.
					' AND '.dbConditionId('ugpg.usrgrpid', $usrgrpids['proxy_group']['deny_list']).
			')';
		}

		if ($usrgrpids['proxy_group']['allow_list']) {
			$proxy_group_mode_conditions[] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM usrgrp_proxy_group ugpg'.
				' WHERE '.$alias.'.proxy_groupid=ugpg.proxy_groupid'.
					' AND '.dbConditionId('ugpg.usrgrpid', $usrgrpids['proxy_group']['allow_list']).
			')';
		}

		return '('.
			'('.
				$alias.'.proxy_groupid IS NULL'.
				' AND '.implode(' AND ', $proxy_mode_conditions).
			') OR ('.
				$alias.'.proxy_groupid IS NOT NULL'.
				' AND '.implode(' AND ', $proxy_group_mode_conditions).
			')'.
		')';
	}

	public static function getProxyGroupPermissionsCondition(string $alias): string {
		if (CApiService::$userData['ugsetid'] == 0) {
			return '1=0';
		}

		$usrgrpids = self::getUserGroupIdsByPermissionLists();

		$conditions = [];

		if ($usrgrpids['proxy_group']['deny_list']) {
			$conditions[] = 'NOT EXISTS ('.
				'SELECT NULL'.
				' FROM usrgrp_proxy_group ugpg'.
				' WHERE '.$alias.'.proxy_groupid=ugpg.proxy_groupid'.
					' AND '.dbConditionId('ugpg.usrgrpid', $usrgrpids['proxy_group']['deny_list']).
			')';
		}

		if ($usrgrpids['proxy_group']['allow_list']) {
			$conditions[] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM usrgrp_proxy_group ugpg'.
				' WHERE '.$alias.'.proxy_groupid=ugpg.proxy_groupid'.
					' AND '.dbConditionId('ugpg.usrgrpid', $usrgrpids['proxy_group']['allow_list']).
			')';
		}

		return implode(' AND ', $conditions);
	}

	private static function getUserGroupIdsByPermissionLists(): array {
		static $usrgrpids = null;

		if ($usrgrpids === null) {
			$usrgrpids = [
				'proxy' => [
					'deny_list' => [],
					'allow_list' => []
				],
				'proxy_group' => [
					'deny_list' => [],
					'allow_list' => []
				]
			];

			$resource = DBselect(
				'SELECT uug.usrgrpid,ug.proxy_mode,ug.proxy_group_mode'.
				' FROM users_groups uug'.
				' JOIN usrgrp ug ON uug.usrgrpid=ug.usrgrpid'.
				' WHERE uug.userid='.CApiService::$userData['userid']
			);

			while ($row = DBfetch($resource)) {
				if ($row['proxy_mode'] == PROXY_MODE_DENY) {
					$usrgrpids['proxy']['deny_list'][] = $row['usrgrpid'];
				}
				else {
					$usrgrpids['proxy']['allow_list'][] = $row['usrgrpid'];
				}

				if ($row['proxy_group_mode'] == PROXY_GROUP_MODE_DENY) {
					$usrgrpids['proxy_group']['deny_list'][] = $row['usrgrpid'];
				}
				else {
					$usrgrpids['proxy_group']['allow_list'][] = $row['usrgrpid'];
				}
			}
		}

		return $usrgrpids;
	}

	public static function getApmFilterResourceAttributes(int $signal_type): array {
		$resource = DBselect(
			'SELECT DISTINCT uaf.attribute_key,uaf.value'.
			' FROM users_groups ug'.
			' JOIN usrgrp_apm_filter uaf ON ug.usrgrpid=uaf.usrgrpid'.
			' WHERE ug.userid='.CApiService::$userData['userid'].
				' AND '.dbConditionInt('uaf.signal_type', [SIGNAL_TYPE_ALL, $signal_type])
		);

		$resource_attributes = [];

		while ($row = DBfetch($resource)) {
			$resource_attributes[] = [
				'key' => $row['attribute_key'],
				'operator' => TAG_OPERATOR_EQUAL,
				'value' => $row['value']
			];
		}

		return $resource_attributes;
	}
}

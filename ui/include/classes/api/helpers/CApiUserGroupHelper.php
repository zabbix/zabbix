<?php
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

	private static array $usrgrpid_lists = [];

	public static function getProxyPermissionsCondition(string $alias): string {
		if (CApiService::$userData['ugsetid'] == 0) {
			return '1=0';
		}

		[
			'proxy_deny_list_usrgrpids' => $proxy_deny_list_usrgrpids,
			'proxy_allow_list_usrgrpids' => $proxy_allow_list_usrgrpids,
			'proxy_group_deny_list_usrgrpids' => $proxy_group_deny_list_usrgrpids,
			'proxy_group_allow_list_usrgrpids' => $proxy_group_allow_list_usrgrpids
		] = self::getUserGroupIdsByPermissionLists();

		$proxy_mode_conditions = [];
		$proxy_group_mode_conditions = [];

		if ($proxy_deny_list_usrgrpids) {
			$proxy_mode_conditions[] = 'NOT EXISTS ('.
				'SELECT NULL'.
				' FROM usrgrp_proxy ugp'.
				' WHERE '.$alias.'.proxyid=ugp.proxyid'.
					' AND '.dbConditionId('ugp.usrgrpid', $proxy_deny_list_usrgrpids).
			')';
		}

		if ($proxy_allow_list_usrgrpids) {
			$proxy_mode_conditions[] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM usrgrp_proxy ugp'.
				' WHERE '.$alias.'.proxyid=ugp.proxyid'.
					' AND '.dbConditionId('ugp.usrgrpid', $proxy_allow_list_usrgrpids).
			')';
		}

		if ($proxy_group_deny_list_usrgrpids) {
			$proxy_group_mode_conditions[] = 'NOT EXISTS ('.
				'SELECT NULL'.
				' FROM usrgrp_proxy_group ugpg'.
				' WHERE '.$alias.'.proxy_groupid=ugpg.proxy_groupid'.
					' AND '.dbConditionId('ugpg.usrgrpid', $proxy_group_deny_list_usrgrpids).
			')';
		}

		if ($proxy_group_allow_list_usrgrpids) {
			$proxy_group_mode_conditions[] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM usrgrp_proxy_group ugpg'.
				' WHERE '.$alias.'.proxy_groupid=ugpg.proxy_groupid'.
					' AND '.dbConditionId('ugpg.usrgrpid', $proxy_group_allow_list_usrgrpids).
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

		[
			'proxy_group_deny_list_usrgrpids' => $proxy_group_deny_list_usrgrpids,
			'proxy_group_allow_list_usrgrpids' => $proxy_group_allow_list_usrgrpids
		] = self::getUserGroupIdsByPermissionLists();

		$conditions = [];

		if ($proxy_group_deny_list_usrgrpids) {
			$conditions[] = 'NOT EXISTS ('.
				'SELECT NULL'.
				' FROM usrgrp_proxy_group ugpg'.
				' WHERE '.$alias.'.proxy_groupid=ugpg.proxy_groupid'.
					' AND '.dbConditionId('ugpg.usrgrpid', $proxy_group_deny_list_usrgrpids).
			')';
		}

		if ($proxy_group_allow_list_usrgrpids) {
			$conditions[] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM usrgrp_proxy_group ugpg'.
				' WHERE '.$alias.'.proxy_groupid=ugpg.proxy_groupid'.
					' AND '.dbConditionId('ugpg.usrgrpid', $proxy_group_allow_list_usrgrpids).
			')';
		}

		return $conditions ? '('.implode(' AND ', $conditions).')' : '1=0';
	}

	private static function getUserGroupIdsByPermissionLists(): array {
		if (!self::$usrgrpid_lists) {
			self::$usrgrpid_lists = [
				'proxy_deny_list_usrgrpids' => [],
				'proxy_allow_list_usrgrpids' => [],
				'proxy_group_deny_list_usrgrpids' => [],
				'proxy_group_allow_list_usrgrpids' => []
			];

			$resource = DBselect(
				'SELECT uug.usrgrpid,ug.proxy_mode,ug.proxy_group_mode'.
				' FROM users_groups uug'.
				' JOIN usrgrp ug ON uug.usrgrpid=ug.usrgrpid'.
				' WHERE uug.userid='.CApiService::$userData['userid']
			);

			while ($row = DBfetch($resource)) {
				if ($row['proxy_mode'] == PROXY_MODE_DENY) {
					self::$usrgrpid_lists['proxy_deny_list_usrgrpids'][] = $row['usrgrpid'];
				}
				else {
					self::$usrgrpid_lists['proxy_allow_list_usrgrpids'][] = $row['usrgrpid'];
				}

				if ($row['proxy_group_mode'] == PROXY_GROUP_MODE_DENY) {
					self::$usrgrpid_lists['proxy_group_deny_list_usrgrpids'][] = $row['usrgrpid'];
				}
				else {
					self::$usrgrpid_lists['proxy_group_allow_list_usrgrpids'][] = $row['usrgrpid'];
				}
			}
		}

		return self::$usrgrpid_lists;
	}
}

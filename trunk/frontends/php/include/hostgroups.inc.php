<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
 * Check if user has read permissions for host groups.
 *
 * @param array $groupids
 *
 * @return bool
 */
function isReadableHostGroups(array $groupids) {
	return count($groupids) == API::HostGroup()->get([
		'countOutput' => true,
		'groupids' => $groupids
	]);
}

/**
 * Check if user has write permissions for host groups.
 *
 * @param array $groupids
 *
 * @return bool
 */
function isWritableHostGroups(array $groupids) {
	return count($groupids) == API::HostGroup()->get([
		'countOutput' => true,
		'groupids' => $groupids,
		'editable' => true
	]);
}

/**
 * Apply host group rights to all subgroups.
 *
 * @param string $groupid  Host group ID.
 * @param string $name     Host group name.
 */
function inheritPermissions($groupid, $name) {
	// Get child groupids.
	$parent = $name.'/';
	$len = strlen($parent);

	$groups = API::HostGroup()->get([
		'output' => ['groupid', 'name'],
		'search' => ['name' => $parent],
		'startSearch' => true
	]);

	$child_groupids = [];
	foreach ($groups as $group) {
		if (substr($group['name'], 0, $len) === $parent) {
			$child_groupids[$group['groupid']] = true;
		}
	}

	if ($child_groupids) {
		$child_groupids = array_keys($child_groupids);

		$usrgrps = API::UserGroup()->get([
			'output' => ['usrgrpid'],
			'selectRights' => ['id', 'permission']
		]);

		$upd_usrgrps = [];

		foreach ($usrgrps as $usrgrp) {
			$rights = zbx_toHash($usrgrp['rights'], 'id');

			if (array_key_exists($groupid, $rights)) {
				foreach ($child_groupids as $child_groupid) {
					$rights[$child_groupid] = [
						'id' => $child_groupid,
						'permission' => $rights[$groupid]['permission']
					];
				}
			}
			else {
				foreach ($child_groupids as $child_groupid) {
					unset($rights[$child_groupid]);
				}
			}

			$upd_usrgrps[] = [
				'usrgrpid' => $usrgrp['usrgrpid'],
				'rights' => $rights
			];
		}

		API::UserGroup()->update($upd_usrgrps);
	}
}

/**
 * Get sub-groups of elected host groups.
 *
 * @param array $groupids
 * @param array $ms_groups  [OUT] the list of groups for multiselect
 *
 * @return array
 */
function getSubGroups(array $groupids, array &$ms_groups = null) {
	$db_groups = $groupids
		? API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $groupids,
			'preservekeys' => true
		])
		: [];

	if ($ms_groups !== null) {
		$ms_groups = CArrayHelper::renameObjectsKeys($db_groups, ['groupid' => 'id']);
	}

	$db_groups_names = [];

	foreach ($db_groups as $db_group) {
		$db_groups_names[] = $db_group['name'].'/';
	}

	if ($db_groups_names) {
		$child_groups = API::HostGroup()->get([
			'output' => ['groupid'],
			'search' => ['name' => $db_groups_names],
			'searchByAny' => true,
			'startSearch' => true
		]);

		foreach ($child_groups as $child_group) {
			$groupids[] = $child_group['groupid'];
		}
	}

	return $groupids;
}

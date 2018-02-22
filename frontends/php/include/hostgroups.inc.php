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
 * Returns list of child groups for host group with given name..
 *
 * @param string $name     Host group name.
 */
function getChildGroupIds($name) {
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
			$child_groupids[] = $group['groupid'];
		}
	}

	return $child_groupids;
}

/**
 * Apply host group rights to all subgroups.
 *
 * @param string $groupid  Host group ID.
 * @param string $name     Host group name.
 */
function inheritPermissions($groupid, $name) {
	$child_groupids = getChildGroupIds($name);

	if (!$child_groupids) {
		return;
	}

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

/**
 * Add subgroups with tag filters inherited from main host group ($groupid) to all user groups in which tag filters for
 * particular group are created.
 *
 * @param string $groupid  Host group ID.
 * @param string $name     Host group name.
 */
function inheritTagFilters($groupid, $name) {
	$child_groupids = getChildGroupIds($groupid, $name);

	if (!$child_groupids) {
		return;
	}

	$usrgrps = API::UserGroup()->get([
		'output' => ['usrgrpid'],
		'selectTagFilters' => ['groupid', 'tag', 'value']
	]);

	$upd_usrgrps = [];

	foreach ($usrgrps as $usrgrp) {
		$tag_filters = zbx_toHash($usrgrp['tag_filters'], 'groupid');

		if (array_key_exists($groupid, $tag_filters)) {
			foreach ($child_groupids as $child_groupid) {
				$tag_filters[$child_groupid] = [
					'groupid' => $child_groupid,
					'tag' => $tag_filters[$groupid]['tag'],
					'value' => $tag_filters[$groupid]['value']
				];
			}
		}
		else {
			foreach ($child_groupids as $child_groupid) {
				unset($tag_filters[$child_groupid]);
			}
		}

		$upd_usrgrps[] = [
			'usrgrpid' => $usrgrp['usrgrpid'],
			'tag_filters' => $tag_filters
		];
	}

	API::UserGroup()->update($upd_usrgrps);
}

/**
 * Get sub-groups of selected host groups.
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

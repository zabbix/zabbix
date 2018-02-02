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
 * Add subgroups with tag filters inherited from main host group ($groupid) to all user groups in which tag filters for
 * particular group are created.
 *
 * @param string $groupid  Host group ID.
 * @param string $name     Host group name.
 */
function inheritTagFilters($groupid, $name) {
	// Get child groupids.
	$parent = $name.'/';
	$len = strlen($parent);

	// Select subgroups of particular host group.
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

	if (!$child_groupids) {
		return;
	}

	// Select what tag filters particular host group already has for different user groups.
	$db_host_group_tag_filters = DB::select('tag_filter', [
		'output' => ['usrgrpid', 'tag', 'value'],
		'filter' => ['groupid' => $groupid]
	]);

	$tag_filters = [];

	foreach ($db_host_group_tag_filters as $db_host_group_tag_filter) {
		$tag_filters[$db_host_group_tag_filter['usrgrpid']][] = [
			'tag' => $db_host_group_tag_filter['tag'],
			'value' => $db_host_group_tag_filter['value']
		];
	}

	/**
	 * Since tag filters with subgroups can already be added, but not always filter for the main group will be created,
	 * it is necessary also select user groups in which tag filters are created with each of subgroups. Tag filters for
	 * these subgroups will be deleted if in particular user group, the main hostgroup related tag filter is not
	 * created.
	 */
	$db_subgroup_usrgrps = DB::select('tag_filter', [
		'output' => ['usrgrpid'],
		'filter' => ['groupid' => array_keys($child_groupids)]
	]);
	$db_subgroup_usrgrps = array_flip(zbx_objectValues($db_subgroup_usrgrps, 'usrgrpid'));

	// Select affected user groups.
	$usrgrps = API::UserGroup()->get([
		'output' => ['usrgrpid'],
		'usrgrpids' => array_keys($tag_filters + $db_subgroup_usrgrps),
		'selectTagFilters' => ['groupid', 'tag', 'value']
	]);

	foreach ($usrgrps as $usrgrp) {
		/**
		 * Create an array of new tag filters for each user group. It contains all subgroups of particular host group,
		 * as well as other tag filters which are not linked to subgroups of particular host group.
		 */
		$new_tag_filters = [];

		if (array_key_exists($usrgrp['usrgrpid'], $tag_filters)) {
			foreach ($child_groupids as $child_groupid => $val) {
				foreach ($tag_filters[$usrgrp['usrgrpid']] as $tag_filter) {
					$new_tag_filters[] = [
						'groupid' => $child_groupid,
						'value' => $tag_filter['value'],
						'tag' => $tag_filter['tag']
					];
				}
			}
		}

		foreach ($usrgrp['tag_filters'] as $tag_filter) {
			if (!array_key_exists($tag_filter['groupid'], $child_groupids)) {
				$new_tag_filters[] = $tag_filter;
			}
		}

		// Update User Group.
		API::UserGroup()->update([
			'usrgrpid' => $usrgrp['usrgrpid'],
			'tag_filters' => $new_tag_filters
		]);
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

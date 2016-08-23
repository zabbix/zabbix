<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
 * Return list of group IDs, generated from multiselect output parameters.
 *
 * @param array $groupids				An array of host group IDs.
 * @param array $subgroupids			An array of parent host group IDs.
 *
 * @return array
 */
function getMultiselectGroupIds(array $groupids, array $subgroupids) {
	$db_groups = $subgroupids
		? API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $subgroupids
		])
		: [];

	$groupids = array_fill_keys($groupids, true);

	foreach ($db_groups as $db_group) {
		$groupids[$db_group['groupid']] = true;

		$db_child_groups = API::HostGroup()->get([
			'output' => ['groupid'],
			'search' => ['name' => $db_group['name'].'/'],
			'startSearch' => true
		]);

		foreach ($db_child_groups as $db_child_group) {
			$groupids[$db_child_group['groupid']] = true;
		}
	}

	return array_keys($groupids);
}

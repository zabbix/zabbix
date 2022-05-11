<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * Returns list of child groups for host group with given name.
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

		$rights = array_values($rights);

		if ($usrgrp['rights'] !== $rights) {
			$upd_usrgrps[] = [
				'usrgrpid' => $usrgrp['usrgrpid'],
				'rights' => $rights
			];
		}
	}

	if ($upd_usrgrps) {
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
	$child_groupids = getChildGroupIds($name);

	if (!$child_groupids) {
		return;
	}

	$usrgrps = API::UserGroup()->get([
		'output' => ['usrgrpid'],
		'selectTagFilters' => ['groupid', 'tag', 'value']
	]);

	$upd_usrgrps = [];

	foreach ($usrgrps as $usrgrp) {
		$tag_filters = [];

		foreach ($usrgrp['tag_filters'] as $tag_filter) {
			$tag_filters[$tag_filter['groupid']][] = [
				'tag' => $tag_filter['tag'],
				'value' => $tag_filter['value']
			];
		}

		if (array_key_exists($groupid, $tag_filters)) {
			foreach ($child_groupids as $child_groupid) {
				$tag_filters[$child_groupid] = $tag_filters[$groupid];
			}
		}
		else {
			foreach ($child_groupids as $child_groupid) {
				unset($tag_filters[$child_groupid]);
			}
		}

		$upd_tag_filters = [];

		foreach ($tag_filters as $tag_filter_groupid => $tags) {
			foreach ($tags as $tag) {
				$upd_tag_filters[] = ['groupid' => (string) $tag_filter_groupid] + $tag;
			}
		}

		if ($usrgrp['tag_filters'] !== $upd_tag_filters) {
			$upd_usrgrps[] = [
				'usrgrpid' => $usrgrp['usrgrpid'],
				'tag_filters' => $upd_tag_filters
			];
		}
	}

	if ($upd_usrgrps) {
		API::UserGroup()->update($upd_usrgrps);
	}
}

/**
 * Get sub-groups of selected host groups.
 *
 * @param array $groupids
 * @param array $ms_groups  [OUT] the list of groups for multiselect.
 * @param array $options    additional API options to select host groups.
 *
 * @return array
 */
function getSubGroups(array $groupids, array &$ms_groups = null, array $options = []) {
	$db_groups = $groupids
		? API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $groupids,
			'preservekeys' => true
		] + $options)
		: [];

	if ($ms_groups !== null) {
		$ms_groups = CArrayHelper::renameObjectsKeys($db_groups, ['groupid' => 'id']);
	}

	$db_groups_names = [];

	foreach ($db_groups as $db_group) {
		$db_groups_names[] = $db_group['name'].'/';
	}

	if ($db_groups_names) {
		$db_groups += API::HostGroup()->get([
			'output' => ['groupid'],
			'search' => ['name' => $db_groups_names],
			'searchByAny' => true,
			'startSearch' => true,
			'preservekeys' => true
		] + $options);
	}

	return array_keys($db_groups);
}

/**
 * Creates a hintbox suitable for Problem hosts widget.
 *
 * @param array      $hosts                                                   Array of problematic hosts.
 * @param array      $data                                                    Array of host data, filter settings and
 *                                                                            severity configuration.
 * @param array      $data['filter']['severities']                            Array of severities.
 * @param string     $data['hosts_data'][<hostid>]['host']                    Host name.
 * @param int        $data['hosts_data'][<hostid>]['severities'][<severity>]  Severity count.
 * @param CUrl|null  $url                                                     URL that leads to problems view having
 *                                                                            hostid in its filter.
 *
 * @return CTableInfo
 */
function makeProblemHostsHintBox(array $hosts, array $data, ?CUrl $url) {
	// Set trigger severities as table header, ordered starting from highest severity.
	$header = [_('Host')];

	foreach (range(TRIGGER_SEVERITY_COUNT - 1, TRIGGER_SEVERITY_NOT_CLASSIFIED) as $severity) {
		if (in_array($severity, $data['filter']['severities'])) {
			$header[] = CSeverityHelper::getName($severity);
		}
	}

	$maintenanceids = [];
	foreach ($data['hosts_data'] as $host) {
		if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
			$maintenanceids[$host['maintenanceid']] = true;
		}
	}

	$db_maintenances = $maintenanceids
		? API::Maintenance()->get([
			'output' => ['name', 'description'],
			'maintenanceids' => array_keys($maintenanceids),
			'preservekeys' => true
		])
		: [];

	$table_inf = (new CTableInfo())->setHeader($header);

	$popup_rows = 0;

	foreach ($hosts as $hostid => $host) {
		$host_data = $data['hosts_data'][$hostid];
		if ($url !== null) {
			$url->setArgument('hostids', [$hostid]);
			$host_name = new CLink($host_data['host'], $url->getUrl());
		}
		else {
			$host_name = $host_data['host'];
		}

		if ($host_data['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
			if (array_key_exists($host_data['maintenanceid'], $db_maintenances)) {
				$maintenance = $db_maintenances[$host_data['maintenanceid']];
				$maintenance_icon = makeMaintenanceIcon($host_data['maintenance_type'], $maintenance['name'],
					$maintenance['description']
				);
			}
			else {
				$maintenance_icon = makeMaintenanceIcon($host_data['maintenance_type'], _('Inaccessible maintenance'),
					''
				);
			}

			$host_name = [$host_name, $maintenance_icon];
		}

		$row = new CRow((new CCol($host_name))->addClass(ZBX_STYLE_NOWRAP));

		foreach (range(TRIGGER_SEVERITY_COUNT - 1, TRIGGER_SEVERITY_NOT_CLASSIFIED) as $severity) {
			if (in_array($severity, $data['filter']['severities'])) {
				$row->addItem(
					($host_data['severities'][$severity] != 0)
						? (new CCol($host_data['severities'][$severity]))
							->addClass(CSeverityHelper::getStyle($severity))
						: ''
				);
			}
		}

		$table_inf->addRow($row);

		if (++$popup_rows == ZBX_WIDGET_ROWS) {
			break;
		}
	}

	return $table_inf;
}

/**
 * Enriches host groups array by parent groups.
 *
 * @param array  $groups
 * @param string $groups[<groupid>]['groupid']
 * @param string $groups[<groupid>]['name']
 *
 * @return array
 */
function enrichParentGroups(array $groups) {
	$parents = [];
	foreach ($groups as $group) {
		$parent = explode('/', $group['name']);
		while (array_pop($parent) && $parent) {
			$parents[implode('/', $parent)] = true;
		}
	}

	if ($parents) {
		foreach ($groups as $group) {
			if (array_key_exists($group['name'], $parents)) {
				unset($parents[$group['name']]);
			}
		}
	}

	if ($parents) {
		$groups += API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'filter' => [
				'name' => array_keys($parents)
			],
			'preservekeys' => true
		]);
	}

	return $groups;
}

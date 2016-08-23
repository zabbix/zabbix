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
 * Find user theme or get default theme.
 *
 * @param array $userData
 *
 * @return string
 */
function getUserTheme($userData) {
	$config = select_config();

	if (isset($config['default_theme'])) {
		$css = $config['default_theme'];
	}
	if (isset($userData['theme']) && $userData['theme'] != THEME_DEFAULT) {
		$css = $userData['theme'];
	}
	if (!isset($css)) {
		$css = ZBX_DEFAULT_THEME;
	}

	return $css;
}

/**
 * Get user type name.
 *
 * @param int $userType
 *
 * @return string
 */
function user_type2str($userType = null) {
	$userTypes = [
		USER_TYPE_ZABBIX_USER => _('Zabbix User'),
		USER_TYPE_ZABBIX_ADMIN => _('Zabbix Admin'),
		USER_TYPE_SUPER_ADMIN => _('Zabbix Super Admin')
	];

	if ($userType === null) {
		return $userTypes;
	}
	elseif (isset($userTypes[$userType])) {
		return $userTypes[$userType];
	}
	else {
		return _('Unknown');
	}
}

/**
 * Get user authentication name.
 *
 * @param int $authType
 *
 * @return string
 */
function user_auth_type2str($authType) {
	if ($authType === null) {
		$authType = getUserGuiAccess(CWebUser::$data['userid']);
	}

	$authUserType = [
		GROUP_GUI_ACCESS_SYSTEM => _('System default'),
		GROUP_GUI_ACCESS_INTERNAL => _x('Internal', 'user type'),
		GROUP_GUI_ACCESS_DISABLED => _('Disabled')
	];

	return isset($authUserType[$authType]) ? $authUserType[$authType] : _('Unknown');
}

/**
 * Unblock user account.
 *
 * @param array $userIds
 *
 * @return bool
 */
function unblock_user_login($userIds) {
	zbx_value2array($userIds);

	return DBexecute('UPDATE users SET attempt_failed=0 WHERE '.dbConditionInt('userid', $userIds));
}

/**
 * Get users ids by groups ids.
 *
 * @param array $userGroupIds
 *
 * @return array
 */
function get_userid_by_usrgrpid($userGroupIds) {
	zbx_value2array($userGroupIds);

	$userIds = [];

	$dbUsers = DBselect(
		'SELECT DISTINCT u.userid'.
		' FROM users u,users_groups ug'.
		' WHERE u.userid=ug.userid'.
			' AND '.dbConditionInt('ug.usrgrpid', $userGroupIds)
	);
	while ($user = DBFetch($dbUsers)) {
		$userIds[$user['userid']] = $user['userid'];
	}

	return $userIds;
}

/**
 * Check if group has permissions for update.
 *
 * @param array $userGroupIds
 *
 * @return bool
 */
function granted2update_group($userGroupIds) {
	zbx_value2array($userGroupIds);

	$users = get_userid_by_usrgrpid($userGroupIds);

	return !isset($users[CWebUser::$data['userid']]);
}

/**
 * Change group status.
 *
 * @param array $userGroupIds
 * @param int   $usersStatus
 *
 * @return bool
 */
function change_group_status($userGroupIds, $usersStatus) {
	zbx_value2array($userGroupIds);

	$grant = ($usersStatus == GROUP_STATUS_DISABLED) ? granted2update_group($userGroupIds) : true;

	if ($grant) {
		return DBexecute(
			'UPDATE usrgrp'.
			' SET users_status='.zbx_dbstr($usersStatus).
			' WHERE '.dbConditionInt('usrgrpid', $userGroupIds)
		);
	}
	else {
		error(_('User cannot change status of himself.'));
	}

	return false;
}

/**
 * Change gui access for group.
 *
 * @param array $userGroupIds
 * @param int   $guiAccess
 *
 * @return bool
 */
function change_group_gui_access($userGroupIds, $guiAccess) {
	zbx_value2array($userGroupIds);

	$grant = ($guiAccess == GROUP_GUI_ACCESS_DISABLED) ? granted2update_group($userGroupIds) : true;

	if ($grant) {
		return DBexecute(
			'UPDATE usrgrp SET gui_access='.zbx_dbstr($guiAccess).' WHERE '.dbConditionInt('usrgrpid', $userGroupIds)
		);
	}
	else {
		error(_('User cannot change GUI access for himself.'));
	}

	return false;
}

/**
 * Change debug mode for group.
 *
 * @param array $userGroupIds
 * @param int   $debugMode
 *
 * @return bool
 */
function change_group_debug_mode($userGroupIds, $debugMode) {
	zbx_value2array($userGroupIds);

	return DBexecute(
		'UPDATE usrgrp SET debug_mode='.zbx_dbstr($debugMode).' WHERE '.dbConditionInt('usrgrpid', $userGroupIds)
	);
}

/**
 * Gets user full name in format "alias (name surname)". If both name and surname exist, returns translated string.
 *
 * @param array  $userData
 * @param string $userData['alias']
 * @param string $userData['name']
 * @param string $userData['surname']
 *
 * @return string
 */
function getUserFullname($userData) {
	if (!zbx_empty($userData['surname'])) {
		if (!zbx_empty($userData['name'])) {
			return $userData['alias'].' '._x('(%1$s %2$s)', 'user fullname', $userData['name'], $userData['surname']);
		}

		$fullname = $userData['surname'];
	}
	else {
		$fullname = zbx_empty($userData['name']) ? '' : $userData['name'];
	}

	return zbx_empty($fullname) ? $userData['alias'] : $userData['alias'].' ('.$fullname.')';
}

/**
 * @param string $usrgrpid
 */
function getHostGroupsRights(array $usrgrpids = []) {
	$groups_rights = [
		'0' => [
			'permission' => PERM_NONE,
			'name' => '',
			'grouped' => '1'
		]
	];

	$host_groups = API::HostGroup()->get(['groupid', 'name']);

	foreach ($host_groups as $host_group) {
		$groups_rights[$host_group['groupid']] = [
			'permission' => PERM_NONE,
			'name' => $host_group['name']
		];
	}

	if ($usrgrpids) {
		$db_rights = DBselect(
			'SELECT r.id AS groupid,'.
				'CASE WHEN MIN(r.permission)='.PERM_DENY.' THEN '.PERM_DENY.' ELSE MAX(r.permission) END AS permission'.
			' FROM rights r'.
				' WHERE '.dbConditionInt('r.groupid', $usrgrpids).
			' GROUP BY r.id'
		);

		while ($db_right = DBfetch($db_rights)) {
			$groups_rights[$db_right['groupid']]['permission'] = $db_right['permission'];
		}
	}

	return $groups_rights;
}

/**
 * @param array  $groups_rights
 * @param string $groups_rights[<groupid>]['name']
 * @param int    $groups_rights[<groupid>]['permission']
 */
function collapseHostGroupRights(array $groups_rights) {
	$groups = [];

	foreach ($groups_rights as $groupid => $group_rights) {
		$groups[$group_rights['name']] = $groupid;
	}

	CArrayHelper::sort($groups_rights, [['field' => 'name', 'order' => ZBX_SORT_DOWN]]);

	$permissions = [];

	foreach ($groups_rights as $groupid => $group_rights) {
		if ($groupid == 0) {
			continue;
		}

		$permissions[$group_rights['permission']] = true;

		$pos = strrpos($group_rights['name'], '/');
		$parent_group_name = ($pos === false) ? '' : substr($group_rights['name'], 0, $pos);

		if (!array_key_exists($parent_group_name, $groups)) {
			continue;
		}

		$parent_group_rights = &$groups_rights[$groups[$parent_group_name]];

		if ($parent_group_rights['permission'] == $group_rights['permission']) {
			$parent_group_rights['grouped'] = '1';
			unset($groups_rights[$groupid]);
		}
		unset($parent_group_rights);
	}

	if (count($permissions) == 1) {
		$groups_rights = array_slice($groups_rights, -1);
		$groups_rights[0]['permission'] = key($permissions);
	}

	CArrayHelper::sort($groups_rights, [['field' => 'name', 'order' => ZBX_SORT_UP]]);

	return $groups_rights;
}

/**
 * @param array  $groups_rights
 * @param string $groups_rights[<groupid>]['name']
 * @param int    $groups_rights[<groupid>]['permission']
 * @param int    $groups_rights[<groupid>]['grouped']    (optional)
 * @param array  $groupids
 * @param array  $groupids_subgroupids
 * @param int    $new_permission
 */
function applyHostGroupRights(array $groups_rights, array $groupids = [], array $groupids_subgroupids = [],
		$new_permission = PERM_NONE) {
	// get list of host groups
	$ex_groups_rights = getHostGroupsRights();
	$ex_groups = [];

	foreach ($ex_groups_rights as $groupid => $ex_group_rights) {
		$ex_groups[$ex_group_rights['name']] = $groupid;
	}

	// convert $groupids_subgroupids into $groupids
	foreach ($groupids_subgroupids as $groupid) {
		if (!array_key_exists($groupid, $ex_groups_rights)) {
			continue;
		}

		$groupids[] = $groupid;

		$parent_group_name = $ex_groups_rights[$groupid]['name'].'/';
		$parent_group_name_len = strlen($parent_group_name);

		foreach ($ex_groups_rights as $groupid => $ex_group_rights) {
			if (substr($ex_group_rights['name'], 0, $parent_group_name_len) === $parent_group_name) {
				$groupids[] = $groupid;
			}
		}
	}

	$groupids = array_fill_keys($groupids, true);

	// apply new permissions to all groups
	foreach ($ex_groups_rights as $groupid => &$ex_group_rights) {
		if ($groupid == 0) {
			continue;
		}
		if (array_key_exists($groupid, $groupids)) {
			$ex_group_rights['permission'] = $new_permission;
			continue;
		}
		if (array_key_exists($groupid, $groups_rights)) {
			$ex_group_rights['permission'] = $groups_rights[$groupid]['permission'];
			continue;
		}

		$parent_group_name = $ex_group_rights['name'];

		do {
			$pos = strrpos($parent_group_name, '/');
			$parent_group_name = ($pos === false) ? '' : substr($ex_group_rights['name'], 0, $pos);

			if (array_key_exists($parent_group_name, $ex_groups)
					&& array_key_exists($ex_groups[$parent_group_name], $groups_rights)) {
				$parent_group_rights = $groups_rights[$ex_groups[$parent_group_name]];

				if ($parent_group_rights['grouped']) {
					$ex_group_rights['permission'] = $parent_group_rights['permission'];
					break;
				}
			}
		}
		while ($parent_group_name !== '');
	}
	unset($ex_group_rights);

	CArrayHelper::sort($ex_groups_rights, [['field' => 'name', 'order' => ZBX_SORT_UP]]);

	return $ex_groups_rights;
}

/**
 * Find parent and childs IDs by given host group IDs. If $group_rights is set, check that childs have same rights as
 * parent and onlt then overwrite them. If no $group_rights is set, overwrite them independely.
 *
 * @param array $parentids				An array of host group IDs. (parentids)
 * @param array $group_rights			An array of host group names and current rights. Example:
 *											$group_rights[<Zabbix servers>][<name>] => Zabbix servers
 *											$group_rights[<Zabbix servers>][<rights>] => -1
 *											$group_rights[<Zabbix servers>][<host_groupid>] = 4
 *											$group_rights[<Zabbix servers/My Server>][<name>] => Zabbix servers/My Server
 *											$group_rights[<Zabbix servers/My Server>][<rights>] => -1
 *											$group_rights[<Zabbix servers/My Server>][<host_groupid>] = 5
 *											$group_rights[<Linux servers>][<name>] => Linux servers
 *											$group_rights[<Linux servers>][<rights>] => 3
 *											$group_rights[<Linux servers>][<host_groupid>] = 6
 *
 * @return array
 */
function findParentAndChildsForUpdate(array $parentids, array $group_rights = []) {
	$compare_parent_rights = false;

	if ($group_rights) {
		$compare_parent_rights = true;
	}

	$host_groups = API::HostGroup()->get([
		'output' => ['groupid', 'name'],
		'groupids' => array_keys($parentids)
	]);

	$result = [];

	foreach ($host_groups as $host_group) {
		if ($compare_parent_rights) {
			$parent_rights = $group_rights[$host_group['name']]['rights'];
		}

		$new_rights = $parentids[$host_group['groupid']];

		$childs = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'search' => ['name' => $host_group['name'].'/'],
			'startSearch' => true,
			'preservekeys' => true
		]);

		foreach ($childs as $child) {
			if ($compare_parent_rights) {
				$old_rights = $group_rights[$child['name']]['rights'];

				if ($old_rights == $parent_rights) {
					$result[$child['groupid']] = $new_rights;
				}
			}
			else {
				$result[$child['groupid']] = $new_rights;
			}

		}

		$result[$host_group['groupid']] = $new_rights;
	}

	return $result;
}

/**
 * Get textual representation of given permission.
 *
 * @param string $perm			Numerical value of permission.
 *									Possible values are:
 *									 3 - PERM_READ_WRITE,
 *									 2 - PERM_READ,
 *									 0 - PERM_DENY,
 *									-1 - PERM_NONE;
 *
 * @return string
 */
function permissionText($perm) {
	switch ($perm) {
		case PERM_READ_WRITE: return _('Read-write');
		case PERM_READ: return _('Read');
		case PERM_DENY: return _('Deny');
		case PERM_NONE: return _('None');
	}
}

<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
 * Get permission label.
 *
 * @param int $permission
 *
 * @return string
 */
function permission2str($permission) {
	$permissions = array(
		PERM_READ_WRITE => _('Read-write'),
		PERM_READ => _('Read only'),
		PERM_DENY => _('Deny')
	);

	return isset($permissions[$permission]) ? $permissions[$permission] : _('Unknown');
}

/***********************************************
	CHECK USER ACCESS TO SYSTEM STATUS
************************************************/
/* Function: check_perm2system()
 *
 * Description:
 * 		Checking user permissions to access system (affects server side: no notification will be sent)
 *
 * Comments:
 *		return true if permission is positive
 *
 * Author: Aly
 */
function check_perm2system($userid) {
	$sql = 'SELECT g.usrgrpid'.
			' FROM usrgrp g,users_groups ug'.
			' WHERE ug.userid='.zbx_dbstr($userid).
				' AND g.usrgrpid=ug.usrgrpid'.
				' AND g.users_status='.GROUP_STATUS_DISABLED;
	if ($res = DBfetch(DBselect($sql, 1))) {
		return false;
	}
	return true;
}

/* Function: check_perm2login()
 *
 * Description:
 * 		Checking user permissions to Login in frontend
 *
 * Comments:
 *		return true if permission is positive
 *
 * Author: Aly
 */
function check_perm2login($userid) {
	return GROUP_GUI_ACCESS_DISABLED == get_user_auth($userid) ? false : true;
}

/* Function: get_user_auth()
 *
 * Description:
 * 		Returns user authentication type
 *
 * Comments:
 *		default is SYSTEM auth
 *
 * Author: Aly
 */
function get_user_auth($userid) {
	if (bccomp($userid, CWebUser::$data['userid']) == 0 && isset(CWebUser::$data['gui_access'])) {
		return CWebUser::$data['gui_access'];
	}
	else {
		$result = GROUP_GUI_ACCESS_SYSTEM;
	}

	$sql = 'SELECT MAX(g.gui_access) AS gui_access'.
			' FROM usrgrp g,users_groups ug'.
			' WHERE ug.userid='.zbx_dbstr($userid).
				' AND g.usrgrpid=ug.usrgrpid';
	$db_access = DBfetch(DBselect($sql));
	if (!zbx_empty($db_access['gui_access'])) {
		$result = $db_access['gui_access'];
	}
	return $result;
}

/* Function: get_user_system_auth()
 *
 * Description:
 * 		Returns overal user authentication type in system
 *
 * Comments:
 *		default is INTERNAL auth
 *
 * Author: Aly
 */
function get_user_system_auth($userid) {
	$config = select_config();
	$result = get_user_auth($userid);
	switch ($result) {
		case GROUP_GUI_ACCESS_SYSTEM:
			$result = $config['authentication_type'];
			break;
		case GROUP_GUI_ACCESS_INTERNAL:
			if ($config['authentication_type'] == ZBX_AUTH_HTTP) {
				$result = ZBX_AUTH_HTTP;
			}
			else {
				$result = ZBX_AUTH_INTERNAL;
			}
			break;
		case GROUP_GUI_ACCESS_DISABLED:
			$result = $config['authentication_type'];
		default:
			break;
	}
	return $result;
}

/***********************************************
	GET ACCESSIBLE RESOURCES BY RIGHTS
************************************************/
/* NOTE: right structure is
	$rights[i]['type']	= type of resource
	$rights[i]['permission']= permission for resource
	$rights[i]['id']	= resource id
*/
function get_accessible_hosts_by_rights(&$rights, $user_type, $perm, $perm_res = null) {
	$result = array();
	$res_perm = array();

	foreach ($rights as $id => $right) {
		$res_perm[$right['id']] = $right['permission'];
	}

	$host_perm = array();
	$where = array();

	array_push($where, 'h.status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.')');
	array_push($where, dbConditionInt('h.flags', array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)));

	$where = count($where) ? $where = ' WHERE '.implode(' AND ', $where) : '';

	$perm_by_host = array();

	$dbHosts = DBselect(
		'SELECT hg.groupid AS groupid,h.hostid,h.host,h.name AS host_name,h.status'.
		' FROM hosts h'.
			' LEFT JOIN hosts_groups hg ON hg.hostid=h.hostid'.
			$where
	);
	while ($dbHost = DBfetch($dbHosts)) {
		if (isset($dbHost['groupid']) && isset($res_perm[$dbHost['groupid']])) {
			if (!isset($perm_by_host[$dbHost['hostid']])) {
				$perm_by_host[$dbHost['hostid']] = array();
			}
			$perm_by_host[$dbHost['hostid']][] = $res_perm[$dbHost['groupid']];
			$host_perm[$dbHost['hostid']][$dbHost['groupid']] = $res_perm[$dbHost['groupid']];
		}
		$host_perm[$dbHost['hostid']]['data'] = $dbHost;
	}

	foreach ($host_perm as $hostid => $dbHost) {
		$dbHost = $dbHost['data'];

		// select min rights from groups
		if (USER_TYPE_SUPER_ADMIN == $user_type) {
			$dbHost['permission'] = PERM_READ_WRITE;
		}
		else {
			if (isset($perm_by_host[$hostid])) {
				$dbHost['permission'] = (min($perm_by_host[$hostid]) == PERM_DENY)
					? PERM_DENY
					: max($perm_by_host[$hostid]);
			}
			else {
				$dbHost['permission'] = PERM_DENY;
			}
		}

		if ($dbHost['permission'] < $perm) {
			continue;
		}

		switch ($perm_res) {
			case PERM_RES_DATA_ARRAY:
				$result[$dbHost['hostid']] = $dbHost;
				break;
			default:
				$result[$dbHost['hostid']] = $dbHost['hostid'];
		}
	}

	CArrayHelper::sort($result, array(
		array('field' => 'host_name', 'order' => ZBX_SORT_UP)
	));

	return $result;
}

function get_accessible_groups_by_rights(&$rights, $user_type, $perm, $perm_res = null) {
	$result = array();

	$group_perm = array();
	foreach ($rights as $right) {
		$group_perm[$right['id']] = $right['permission'];
	}

	$dbHostGroups = DBselect('SELECT g.*,'.PERM_DENY.' AS permission FROM groups g');

	while ($dbHostGroup = DBfetch($dbHostGroups)) {
		if ($user_type == USER_TYPE_SUPER_ADMIN) {
			$dbHostGroup['permission'] = PERM_READ_WRITE;
		}
		elseif (isset($group_perm[$dbHostGroup['groupid']])) {
			$dbHostGroup['permission'] = $group_perm[$dbHostGroup['groupid']];
		}
		else {
			$dbHostGroup['permission'] = PERM_DENY;
		}

		if ($dbHostGroup['permission'] < $perm) {
			continue;
		}

		switch ($perm_res) {
			case PERM_RES_DATA_ARRAY:
				$result[$dbHostGroup['groupid']] = $dbHostGroup;
				break;

			default:
				$result[$dbHostGroup['groupid']] = $dbHostGroup['groupid'];
		}
	}

	CArrayHelper::sort($result, array(
		array('field' => 'name', 'order' => ZBX_SORT_UP)
	));

	return $result;
}

/**
 * Returns array of user groups by $userId
 *
 * @param int $userId
 *
 * @return array
 */
function getUserGroupsByUserId($userId) {
	static $userGroups;

	if (!isset($userGroups[$userId])) {
		$userGroups[$userId] = array();

		$result = DBselect('SELECT usrgrpid FROM users_groups WHERE userid='.zbx_dbstr($userId));
		while ($row = DBfetch($result)) {
			$userGroups[$userId][] = $row['usrgrpid'];
		}
	}

	return $userGroups[$userId];
}

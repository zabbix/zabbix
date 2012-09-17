<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
function permission2str($group_permission) {
	$str_perm[PERM_READ_WRITE] = _('Read-write');
	$str_perm[PERM_READ_ONLY] = _('Read only');
	$str_perm[PERM_DENY] = _('Deny');

	if (isset($str_perm[$group_permission])) {
		return $str_perm[$group_permission];
	}
	return _('Unknown');
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
			' WHERE ug.userid='.$userid.
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
	global $USER_DETAILS;

	if (bccomp($userid, $USER_DETAILS['userid']) == 0 && isset($USER_DETAILS['gui_access'])) {
		return $USER_DETAILS['gui_access'];
	}
	else {
		$result = GROUP_GUI_ACCESS_SYSTEM;
	}

	$sql = 'SELECT MAX(g.gui_access) AS gui_access'.
			' FROM usrgrp g,users_groups ug'.
			' WHERE ug.userid='.$userid.
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
	GET ACCESSIBLE RESOURCES BY USERID
************************************************/
function available_groups($groupids, $editable = null) {
	$options = array();
	$options['groupids'] = $groupids;
	$options['editable'] = $editable;
	$groups = API::HostGroup()->get($options);
	return zbx_objectValues($groups, 'groupid');
}

function available_hosts($hostids, $editable = null) {
	$options = array();
	$options['hostids'] = $hostids;
	$options['editable'] = $editable;
	$options['templated_hosts'] = 1;
	$hosts = API::Host()->get($options);
	return zbx_objectValues($hosts, 'hostid');
}

function available_triggers($triggerids, $editable = null) {
	$options = array(
		'triggerids' => $triggerids,
		'editable' => $editable
	);
	$triggers = API::Trigger()->get($options);
	return zbx_objectValues($triggers, 'triggerid');
}

/**
 * Returns the host groups that are accessible by the current user with the permission level given in $perm.
 *
 * Can return results in different formats, based on the $per_res parameter. Possible values are:
 * - PERM_RES_IDS_ARRAY - return only host group ids;
 * - PERM_RES_DATA_ARRAY - return an array of host groups.
 *
 * @param array $user_data      an array defined as array('userid' => userid, 'type' => type)
 * @param int $perm             requested permission level
 * @param int|null $perm_res    result format
 *
 * @return array
 */
function get_accessible_groups_by_user($user_data, $perm, $perm_res = PERM_RES_IDS_ARRAY) {
	$result = array();

	$userid =& $user_data['userid'];
	if (!isset($userid)) {
		fatal_error(_('Incorrect user data in "get_accessible_groups_by_user".'));
	}
	$user_type =& $user_data['type'];

	$processed = array();

	if ($user_type == USER_TYPE_SUPER_ADMIN) {
		$sql = 'SELECT hg.groupid,hg.name'.
				' FROM groups hg'.
				' GROUP BY hg.groupid,hg.name'.
				' ORDER BY hg.name';
	}
	else {
		$sql = 'SELECT hg.groupid,hg.name,MIN(r.permission) AS permission,g.userid'.
				' FROM groups hg'.
					' LEFT JOIN rights r ON r.id=hg.groupid'.
					' LEFT JOIN users_groups g ON r.groupid=g.usrgrpid'.
				' WHERE g.userid='.$userid.
				' GROUP BY hg.groupid,hg.name,g.userid'.
				' ORDER BY hg.name,permission';
	}
	$db_groups = DBselect($sql);
	while ($group_data = DBfetch($db_groups)) {
		// deny if no rights defined
		if ($user_type == USER_TYPE_SUPER_ADMIN) {
			$group_data['permission'] = PERM_MAX;
		}
		elseif (isset($processed[$group_data['groupid']])) {
			if ($group_data['permission'] == PERM_DENY) {
				unset($result[$group_data['groupid']]);
			}
			elseif ($processed[$group_data['groupid']] > $group_data['permission']) {
				unset($processed[$group_data['groupid']]);
			}
			else {
				continue;
			}
		}

		$processed[$group_data['groupid']] = $group_data['permission'];
		if ($group_data['permission'] < $perm) {
			continue;
		}

		switch ($perm_res) {
			case PERM_RES_DATA_ARRAY:
				$result[$group_data['groupid']] = $group_data;
				break;
			default:
				$result[$group_data['groupid']] = $group_data['groupid'];
				break;
		}
	}

	unset($processed, $group_data, $db_groups);

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
function get_accessible_hosts_by_rights(&$rights, $user_type) {
	$result = array();
	$res_perm = array();

	foreach ($rights as $right) {
		$res_perm[$right['id']] = $right['permission'];
	}

	$host_perm = array();
	$where = array();

	array_push($where, 'h.status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.')');
	$where = count($where) ? $where = ' WHERE '.implode(' AND ', $where) : '';

	$perm_by_host = array();
	$db_hosts = DBselect(
		'SELECT hg.groupid AS groupid,h.hostid,h.host,h.name AS host_name,h.status'.
		' FROM hosts h'.
			' LEFT JOIN hosts_groups hg ON hg.hostid=h.hostid'.
		$where.
		' ORDER BY h.name'
	);
	while ($host_data = DBfetch($db_hosts)) {
		if (isset($host_data['groupid']) && isset($res_perm[$host_data['groupid']])) {
			if (!isset($perm_by_host[$host_data['hostid']])) {
				$perm_by_host[$host_data['hostid']] = array();
			}
			$perm_by_host[$host_data['hostid']][] = $res_perm[$host_data['groupid']];
			$host_perm[$host_data['hostid']][$host_data['groupid']] = $res_perm[$host_data['groupid']];
		}
		$host_perm[$host_data['hostid']]['data'] = $host_data;
	}

	foreach ($host_perm as $hostid => $host_data) {
		$host_data = $host_data['data'];

		// select min rights from groups
		if (USER_TYPE_SUPER_ADMIN == $user_type) {
			$host_data['permission'] = PERM_MAX;
		}
		else {
			if (isset($perm_by_host[$hostid])) {
				$host_data['permission'] = min($perm_by_host[$hostid]);
			}
			else {
				$host_data['permission'] = PERM_DENY;
			}
		}

		if ($host_data['permission'] < PERM_DENY) {
			continue;
		}

		$result[$host_data['hostid']] = $host_data;
	}

	return $result;
}

function get_accessible_groups_by_rights(&$rights, $user_type, $perm, $perm_res = null) {
	$result= array();

	$group_perm = array();
	foreach ($rights as $right) {
		$group_perm[$right['id']] = $right['permission'];
	}

	$db_groups = DBselect(
		'SELECT g.*,'.PERM_DENY.' AS permission'.
		' FROM groups g'.
		' ORDER BY g.name'
	);
	while ($group_data = DBfetch($db_groups)) {
		if (USER_TYPE_SUPER_ADMIN == $user_type) {
			$group_data['permission'] = PERM_MAX;
		}
		else {
			if (isset($group_perm[$group_data['groupid']])) {
				$group_data['permission'] = $group_perm[$group_data['groupid']];
			}
			else {
				$group_data['permission'] = PERM_DENY;
			}
		}

		if ($group_data['permission'] < $perm) {
			continue;
		}

		switch ($perm_res) {
			case PERM_RES_DATA_ARRAY:
				$result[$group_data['groupid']] = $group_data;
				break;
			default:
				$result[$group_data['groupid']] = $group_data['groupid'];
		}
	}

	return $result;
}
?>

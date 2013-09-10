<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


function permission2str($group_permission) {
	$str_perm[PERM_READ_WRITE] = _('Read-write');
	$str_perm[PERM_READ] = _('Read only');
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
	if (bccomp($userid, CWebUser::$data['userid']) == 0 && isset(CWebUser::$data['gui_access'])) {
		return CWebUser::$data['gui_access'];
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

/**
 * Returns the host groups that are accessible by the current user with the permission level given in $perm.
 *
 * Can return results in different formats, based on the $per_res parameter. Possible values are:
 * - PERM_RES_IDS_ARRAY - return only host group ids;
 * - PERM_RES_DATA_ARRAY - return an array of host groups.
 *
 * @param array    $userData		an array defined as array('userid' => userid, 'type' => type)
 * @param int      $perm			requested permission level
 * @param int|null $permRes			result format
 * @param int      $nodeId
 *
 * @return array
 */
function get_accessible_groups_by_user($userData, $perm, $permRes = PERM_RES_IDS_ARRAY, $nodeId = null) {
	$userId =& $userData['userid'];
	if (!isset($userId)) {
		fatal_error(_('Incorrect user data in "get_accessible_groups_by_user".'));
	}

	if (is_null($nodeId)) {
		$nodeId = get_current_nodeid();
	}

	$userType =& $userData['type'];
	$result = array();
	$processed = array();

	if ($userType == USER_TYPE_SUPER_ADMIN) {
		$sql = 'SELECT n.nodeid AS nodeid,n.name AS node_name,hg.groupid,hg.name'.
				' FROM groups hg'.
					' LEFT JOIN nodes n ON '.DBid2nodeid('hg.groupid').'=n.nodeid'.
				whereDbNode('hg.groupid', $nodeId).
				' GROUP BY n.nodeid,n.name,hg.groupid,hg.name'.
				' ORDER BY node_name,hg.name';
	}
	else {
		$sql = 'SELECT n.nodeid AS nodeid,n.name AS node_name,hg.groupid,hg.name,MAX(r.permission) AS permission,MIN(r.permission) AS permission_deny,g.userid'.
				' FROM groups hg'.
					' LEFT JOIN rights r ON r.id=hg.groupid'.
					' LEFT JOIN users_groups g ON r.groupid=g.usrgrpid'.
					' LEFT JOIN nodes n ON '.DBid2nodeid('hg.groupid').'=n.nodeid'.
				' WHERE g.userid='.$userId.
					andDbNode('hg.groupid', $nodeId).
				' GROUP BY n.nodeid,n.name,hg.groupid,hg.name,g.userid'.
				' ORDER BY node_name,hg.name,permission';
	}

	$dbGroups = DBselect($sql);
	while ($groupData = DBfetch($dbGroups)) {
		if (zbx_empty($groupData['nodeid'])) {
			$groupData['nodeid'] = id2nodeid($groupData['groupid']);
		}

		// calculate permissions
		if ($userType == USER_TYPE_SUPER_ADMIN) {
			$groupData['permission'] = PERM_READ_WRITE;
		}
		elseif (isset($processed[$groupData['groupid']])) {
			if ($groupData['permission_deny'] == PERM_DENY) {
				unset($result[$groupData['groupid']]);
			}
			elseif ($processed[$groupData['groupid']] > $groupData['permission']) {
				unset($processed[$groupData['groupid']]);
			}
			else {
				continue;
			}
		}

		$processed[$groupData['groupid']] = $groupData['permission'];
		if ($groupData['permission'] < $perm) {
			continue;
		}

		switch ($permRes) {
			case PERM_RES_DATA_ARRAY:
				$result[$groupData['groupid']] = $groupData;
				break;
			default:
				$result[$groupData['groupid']] = $groupData['groupid'];
				break;
		}
	}

	unset($processed, $groupData, $dbGroups);

	return $result;
}

function get_accessible_nodes_by_user(&$userData, $perm, $permRes = null, $nodeId = null, $cache = true) {
	$userId =& $userData['userid'];
	if (!isset($userId)) {
		fatal_error(_('Incorrect user data in "get_accessible_nodes_by_user".'));
	}

	global $ZBX_LOCALNODEID, $ZBX_NODES_IDS;
	static $available_nodes;

	if (is_null($permRes)) {
		$permRes = PERM_RES_IDS_ARRAY;
	}
	if (is_null($nodeId)) {
		$nodeId = $ZBX_NODES_IDS;
	}
	if (!is_array($nodeId)) {
		$nodeId = array($nodeId);
	}

	$userType =& $userData['type'];
	$nodeIdStr = is_array($nodeId) ? md5(implode('', $nodeId)) : strval($nodeId);

	if ($cache && isset($available_nodes[$userId][$perm][$permRes][$nodeIdStr])) {
		return $available_nodes[$userId][$perm][$permRes][$nodeIdStr];
	}

	$nodeData = array();
	$result = array();

	if ($userType == USER_TYPE_SUPER_ADMIN) {
		$dbNodes = DBselect('SELECT n.nodeid FROM nodes n');
		while ($node = DBfetch($dbNodes)) {
			$nodeData[$node['nodeid']] = $node;
			$nodeData[$node['nodeid']]['permission'] = PERM_READ_WRITE;
		}
		if (empty($nodeData)) {
			$nodeData[0]['nodeid'] = 0;
		}
	}
	else {
		$availableGroups = get_accessible_groups_by_user($userData, $perm, PERM_RES_DATA_ARRAY, $nodeId);
		foreach ($availableGroups as $availableGroup) {
			$nodeId = id2nodeid($availableGroup['groupid']);
			$permission = (isset($nodeData[$nodeId]) && $permission < $nodeData[$nodeId]['permission'])
				? $nodeData[$nodeId]['permission']
				: $availableGroup['permission'];

			$nodeData[$nodeId]['nodeid'] = $nodeId;
			$nodeData[$nodeId]['permission'] = $permission;
		}
	}

	foreach ($nodeData as $nodeId => $node) {
		switch ($permRes) {
			case PERM_RES_DATA_ARRAY:
				$dbNode = DBfetch(DBselect('SELECT n.* FROM nodes n WHERE n.nodeid='.$nodeId.' ORDER BY n.name'));

				if (!ZBX_DISTRIBUTED) {
					if (!$node) {
						$dbNode = array(
							'nodeid' => $ZBX_LOCALNODEID,
							'name' => 'local',
							'permission' => PERM_READ_WRITE,
							'userid' => null
						);
					}
					else {
						continue;
					}
				}
				$result[$nodeId] = zbx_array_merge($dbNode, $node);
				break;
			default:
				$result[$nodeId] = $nodeId;
				break;
		}
	}

	$available_nodes[$userId][$perm][$permRes][$nodeIdStr] = $result;

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
function get_accessible_hosts_by_rights(&$rights, $user_type, $perm, $perm_res = null, $nodeid = null) {
	$result = array();
	$res_perm = array();

	foreach ($rights as $id => $right) {
		$res_perm[$right['id']] = $right['permission'];
	}

	$host_perm = array();
	$where = array();

	array_push($where, 'h.status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.')');
	array_push($where, dbConditionInt('h.flags', array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)));
	if (!is_null($nodeid)) {
		$where = sqlPartDbNode($where, 'h.hostid', $nodeid);
	}
	$where = count($where) ? $where = ' WHERE '.implode(' AND ', $where) : '';

	$perm_by_host = array();
	$db_hosts = DBselect(
		'SELECT n.nodeid AS nodeid,n.name AS node_name,hg.groupid AS groupid,h.hostid,h.host,h.name AS host_name,h.status'.
		' FROM hosts h'.
			' LEFT JOIN hosts_groups hg ON hg.hostid=h.hostid'.
			' LEFT JOIN nodes n ON n.nodeid='.DBid2nodeid('h.hostid').
		$where.
		' ORDER BY n.name,h.name'
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
			$host_data['permission'] = PERM_READ_WRITE;
		}
		else {
			if (isset($perm_by_host[$hostid])) {
				$host_data['permission'] = (min($perm_by_host[$hostid]) == PERM_DENY)
					? PERM_DENY
					: max($perm_by_host[$hostid]);
			}
			else {
				if (is_null($host_data['nodeid'])) {
					$host_data['nodeid'] = id2nodeid($host_data['groupid']);
				}
				$host_data['permission'] = PERM_DENY;
			}
		}

		if ($host_data['permission'] < $perm) {
			continue;
		}

		switch ($perm_res) {
			case PERM_RES_DATA_ARRAY:
				$result[$host_data['hostid']] = $host_data;
				break;
			default:
				$result[$host_data['hostid']] = $host_data['hostid'];
		}
	}

	return $result;
}

function get_accessible_groups_by_rights(&$rights, $user_type, $perm, $perm_res = null, $nodeid = null) {
	$result= array();
	$where = array();

	if (!is_null($nodeid)) {
		$where = sqlPartDbNode($where, 'g.groupid', $nodeid);
	}

	if (count($where)) {
		$where = ' WHERE '.implode(' AND ', $where);
	}
	else {
		$where = '';
	}

	$group_perm = array();
	foreach ($rights as $right) {
		$group_perm[$right['id']] = $right['permission'];
	}

	$db_groups = DBselect(
		'SELECT n.nodeid AS nodeid,n.name AS node_name,g.*,'.PERM_DENY.' AS permission'.
		' FROM groups g'.
			' LEFT JOIN nodes n ON '.DBid2nodeid('g.groupid').'=n.nodeid'.
			$where.
		' ORDER BY n.name,g.name'
	);
	while ($group_data = DBfetch($db_groups)) {
		if (USER_TYPE_SUPER_ADMIN == $user_type) {
			$group_data['permission'] = PERM_READ_WRITE;
		}
		else {
			if (isset($group_perm[$group_data['groupid']])) {
				$group_data['permission'] = $group_perm[$group_data['groupid']];
			}
			else {
				if (is_null($group_data['nodeid'])) {
					$group_data['nodeid'] = id2nodeid($group_data['groupid']);
				}
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

function get_accessible_nodes_by_rights(&$rights, $user_type, $perm, $perm_res = null) {
	global $ZBX_LOCALNODEID;
	$nodeid = get_current_nodeid(true);

	if (is_null($user_type)) {
		$user_type = USER_TYPE_ZABBIX_USER;
	}

	$node_data = array();
	$result = array();

	$available_groups = get_accessible_groups_by_rights($rights, $user_type, $perm, PERM_RES_DATA_ARRAY, $nodeid);
	foreach ($available_groups as $id => $group) {
		$nodeid = id2nodeid($group['groupid']);
		$permission = $group['permission'];

		if (isset($node_data[$nodeid]) && $permission < $node_data[$nodeid]['permission']) {
			$permission = $node_data[$nodeid]['permission'];
		}
		$node_data[$nodeid]['nodeid'] = $nodeid;
		$node_data[$nodeid]['permission'] = $permission;
	}

	$available_hosts = get_accessible_hosts_by_rights($rights, $user_type, $perm, PERM_RES_DATA_ARRAY, $nodeid);
	foreach ($available_hosts as $id => $host) {
		$nodeid = id2nodeid($host['hostid']);
		$permission = $host['permission'];

		if (isset($node_data[$nodeid]) && $permission < $node_data[$nodeid]['permission']) {
			$permission = $node_data[$nodeid]['permission'];
		}
		$node_data[$nodeid]['nodeid'] = $nodeid;
		$node_data[$nodeid]['permission'] = $permission;
	}

	foreach ($node_data as $nodeid => $node) {
		switch ($perm_res) {
			case PERM_RES_DATA_ARRAY:
				$db_node = DBfetch(DBselect('SELECT n.* FROM nodes n WHERE n.nodeid='.$nodeid));
				if (!ZBX_DISTRIBUTED) {
					if (!$node) {
						$db_node = array(
							'nodeid' => $ZBX_LOCALNODEID,
							'name' => 'local',
							'permission' => PERM_READ_WRITE,
							'userid' => null
						);
					}
					else {
						continue;
					}
				}
				$result[$nodeid] = zbx_array_merge($db_node, $node);
				break;
			default:
				$result[$nodeid] = $nodeid;
				break;
		}
	}

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

		$result = DBselect('SELECT usrgrpid FROM users_groups WHERE userid='.$userId);
		while ($row = DBfetch($result)) {
			$userGroups[$userId][] = $row['usrgrpid'];
		}
	}

	return $userGroups[$userId];
}

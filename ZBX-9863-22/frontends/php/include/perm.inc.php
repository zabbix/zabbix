<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

/**
 * Get authentication label.
 *
 * @param int $type
 *
 * @return string
 */
function authentication2str($type) {
	$authentications = array(
		ZBX_AUTH_INTERNAL => _('Zabbix internal authentication'),
		ZBX_AUTH_LDAP => _('LDAP authentication'),
		ZBX_AUTH_HTTP => _('HTTP authentication')
	);

	return isset($authentications[$type]) ? $authentications[$type] : _('Unknown');
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

/**
 * Checking user permissions to login in frontend.
 *
 * @param string $userId
 *
 * @return bool
 */
function check_perm2login($userId) {
	return (getUserGuiAccess($userId) != GROUP_GUI_ACCESS_DISABLED);
}

/**
 * Get user gui access.
 *
 * @param string $userId
 * @param int    $maxGuiAccess
 *
 * @return int
 */
function getUserGuiAccess($userId, $maxGuiAccess = null) {
	if (bccomp($userId, CWebUser::$data['userid']) == 0 && isset(CWebUser::$data['gui_access'])) {
		return CWebUser::$data['gui_access'];
	}

	$guiAccess = DBfetch(DBselect(
		'SELECT MAX(g.gui_access) AS gui_access'.
		' FROM usrgrp g,users_groups ug'.
		' WHERE ug.userid='.zbx_dbstr($userId).
			' AND g.usrgrpid=ug.usrgrpid'.
			(($maxGuiAccess === null) ? '' : ' AND g.gui_access<='.$maxGuiAccess)
	));

	return $guiAccess ? $guiAccess['gui_access'] : GROUP_GUI_ACCESS_SYSTEM;
}

/**
 * Get user authentication type.
 *
 * @param string $userId
 * @param int    $maxGuiAccess
 *
 * @return int
 */
function getUserAuthenticationType($userId, $maxGuiAccess = null) {
	$config = select_config();

	switch (getUserGuiAccess($userId, $maxGuiAccess)) {
		case GROUP_GUI_ACCESS_SYSTEM:
			return $config['authentication_type'];

		case GROUP_GUI_ACCESS_INTERNAL:
			return ($config['authentication_type'] == ZBX_AUTH_HTTP) ? ZBX_AUTH_HTTP : ZBX_AUTH_INTERNAL;

		default:
			return $config['authentication_type'];
	}
}

/**
 * Get groups gui access.
 *
 * @param array $groupIds
 * @param int   $maxGuiAccess
 *
 * @return int
 */
function getGroupsGuiAccess($groupIds, $maxGuiAccess = null) {
	$guiAccess = DBfetch(DBselect(
		'SELECT MAX(g.gui_access) AS gui_access'.
		' FROM usrgrp g'.
		' WHERE '.dbConditionInt('g.usrgrpid', $groupIds).
			(($maxGuiAccess === null) ? '' : ' AND g.gui_access<='.$maxGuiAccess)
	));

	return $guiAccess ? $guiAccess['gui_access'] : GROUP_GUI_ACCESS_SYSTEM;
}

/**
 * Get group authentication type.
 *
 * @param array $groupIds
 * @param int   $maxGuiAccess
 *
 * @return int
 */
function getGroupAuthenticationType($groupIds, $maxGuiAccess = null) {
	$config = select_config();

	switch (getGroupsGuiAccess($groupIds, $maxGuiAccess)) {
		case GROUP_GUI_ACCESS_SYSTEM:
			return $config['authentication_type'];

		case GROUP_GUI_ACCESS_INTERNAL:
			return ($config['authentication_type'] == ZBX_AUTH_HTTP) ? ZBX_AUTH_HTTP : ZBX_AUTH_INTERNAL;

		default:
			return $config['authentication_type'];
	}
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
				' GROUP BY n.nodeid,n.name,hg.groupid,hg.name';
	}
	else {
		$sql = 'SELECT n.nodeid AS nodeid,n.name AS node_name,hg.groupid,hg.name,MAX(r.permission) AS permission,MIN(r.permission) AS permission_deny,g.userid'.
				' FROM groups hg'.
					' LEFT JOIN rights r ON r.id=hg.groupid'.
					' LEFT JOIN users_groups g ON r.groupid=g.usrgrpid'.
					' LEFT JOIN nodes n ON '.DBid2nodeid('hg.groupid').'=n.nodeid'.
				' WHERE g.userid='.zbx_dbstr($userId).
					andDbNode('hg.groupid', $nodeId).
				' GROUP BY n.nodeid,n.name,hg.groupid,hg.name,g.userid';
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

	if ($userType == USER_TYPE_SUPER_ADMIN) {
		CArrayHelper::sort($result, array(
			array('field' => 'node_name', 'order' => ZBX_SORT_UP),
			array('field' => 'name', 'order' => ZBX_SORT_UP)
		));
	}
	else {
		CArrayHelper::sort($result, array(
			array('field' => 'node_name', 'order' => ZBX_SORT_UP),
			array('field' => 'name', 'order' => ZBX_SORT_UP),
			array('field' => 'permission', 'order' => ZBX_SORT_UP)
		));
	}

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

	$dbHosts = DBselect(
		'SELECT n.nodeid AS nodeid,n.name AS node_name,hg.groupid AS groupid,h.hostid,h.host,h.name AS host_name,h.status'.
		' FROM hosts h'.
			' LEFT JOIN hosts_groups hg ON hg.hostid=h.hostid'.
			' LEFT JOIN nodes n ON n.nodeid='.DBid2nodeid('h.hostid').
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
				if (is_null($dbHost['nodeid'])) {
					$dbHost['nodeid'] = id2nodeid($dbHost['groupid']);
				}
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
		array('field' => 'node_name', 'order' => ZBX_SORT_UP),
		array('field' => 'host_name', 'order' => ZBX_SORT_UP)
	));

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

	$dbHostGroups = DBselect(
		'SELECT n.nodeid AS nodeid,n.name AS node_name,g.*,'.PERM_DENY.' AS permission'.
		' FROM groups g'.
			' LEFT JOIN nodes n ON '.DBid2nodeid('g.groupid').'=n.nodeid'.
			$where
	);
	while ($dbHostGroup = DBfetch($dbHostGroups)) {
		if (USER_TYPE_SUPER_ADMIN == $user_type) {
			$dbHostGroup['permission'] = PERM_READ_WRITE;
		}
		else {
			if (isset($group_perm[$dbHostGroup['groupid']])) {
				$dbHostGroup['permission'] = $group_perm[$dbHostGroup['groupid']];
			}
			else {
				if (is_null($dbHostGroup['nodeid'])) {
					$dbHostGroup['nodeid'] = id2nodeid($dbHostGroup['groupid']);
				}

				$dbHostGroup['permission'] = PERM_DENY;
			}
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
		array('field' => 'node_name', 'order' => ZBX_SORT_UP),
		array('field' => 'name', 'order' => ZBX_SORT_UP)
	));

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

		$result = DBselect('SELECT usrgrpid FROM users_groups WHERE userid='.zbx_dbstr($userId));
		while ($row = DBfetch($result)) {
			$userGroups[$userId][] = $row['usrgrpid'];
		}
	}

	return $userGroups[$userId];
}

<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


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
 * Get user gui access.
 *
 * @param string $userid
 *
 * @return int
 */
function getUserGuiAccess(string $userid): int {
	if (isset(CWebUser::$data['gui_access'])) {
		return CWebUser::$data['gui_access'];
	}

	$gui_access = DBfetch(DBselect(
		'SELECT MAX(g.gui_access) AS gui_access'.
		' FROM usrgrp g,users_groups ug'.
		' WHERE g.usrgrpid=ug.usrgrpid'.
			' AND ug.userid='.zbx_dbstr($userid)
	));

	return $gui_access ? $gui_access['gui_access'] : GROUP_GUI_ACCESS_SYSTEM;
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
		$userGroups[$userId] = [];

		$result = DBselect('SELECT usrgrpid FROM users_groups WHERE userid='.zbx_dbstr($userId));
		while ($row = DBfetch($result)) {
			$userGroups[$userId][] = $row['usrgrpid'];
		}
	}

	return $userGroups[$userId];
}

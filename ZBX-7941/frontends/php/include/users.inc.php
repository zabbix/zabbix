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
	$userTypes = array(
		USER_TYPE_ZABBIX_USER => _('Zabbix User'),
		USER_TYPE_ZABBIX_ADMIN => _('Zabbix Admin'),
		USER_TYPE_SUPER_ADMIN => _('Zabbix Super Admin')
	);

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
		$authType = get_user_auth(CWebUser::$data['userid']);
	}

	$authUserType = array(
		GROUP_GUI_ACCESS_SYSTEM => _('System default'),
		GROUP_GUI_ACCESS_INTERNAL => _x('Internal', 'user type'),
		GROUP_GUI_ACCESS_DISABLED => _('Disabled')
	);

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

	$userIds = array();

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
 * Check if user can be appended to group.
 *
 * @param string $userId
 * @param string $userGroupId
 *
 * @return bool
 */
function granted2move_user($userId, $userGroupId) {
	$group = API::UserGroup()->get(array(
		'usrgrpids' => $userGroupId,
		'output' => API_OUTPUT_EXTEND
	));
	$group = reset($group);

	if ($group['gui_access'] == GROUP_GUI_ACCESS_DISABLED || $group['users_status'] == GROUP_STATUS_DISABLED) {
		return (bccomp(CWebUser::$data['userid'], $userId) != 0);
	}

	return true;
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
			'UPDATE usrgrp SET users_status='.$usersStatus.' WHERE '.dbConditionInt('usrgrpid', $userGroupIds)
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
			'UPDATE usrgrp SET gui_access='.$guiAccess.' WHERE '.dbConditionInt('usrgrpid', $userGroupIds)
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
		'UPDATE usrgrp SET debug_mode='.$debugMode.' WHERE '.dbConditionInt('usrgrpid', $userGroupIds)
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

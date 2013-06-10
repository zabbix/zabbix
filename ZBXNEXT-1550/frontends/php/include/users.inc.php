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

function user_type2str($user_type = null) {
	$user_types = array(
		USER_TYPE_ZABBIX_USER => _('Zabbix User'),
		USER_TYPE_ZABBIX_ADMIN => _('Zabbix Admin'),
		USER_TYPE_SUPER_ADMIN => _('Zabbix Super Admin')
	);
	if (is_null($user_type)) {
		return $user_types;
	}
	elseif (isset($user_types[$user_type])) {
		return $user_types[$user_type];
	}
	else {
		return _('Unknown');
	}
}

function user_auth_type2str($auth_type) {
	if (is_null($auth_type)) {
		$auth_type = get_user_auth(CWebUser::$data['userid']);
	}
	$auth_user_type[GROUP_GUI_ACCESS_SYSTEM] = _('System default');
	$auth_user_type[GROUP_GUI_ACCESS_INTERNAL] = _('Internal');
	$auth_user_type[GROUP_GUI_ACCESS_DISABLED] = _('Disabled');

	if (isset($auth_user_type[$auth_type])) {
		return $auth_user_type[$auth_type];
	}
	return _('Unknown');
}

function unblock_user_login($userids) {
	zbx_value2array($userids);
	return DBexecute('UPDATE users SET attempt_failed=0 WHERE '.dbConditionInt('userid', $userids));
}

function get_userid_by_usrgrpid($usrgrpids) {
	zbx_value2array($usrgrpids);
	$userids = array();

	$db_users = DBselect(
		'SELECT DISTINCT u.userid'.
		' FROM users u,users_groups ug'.
		' WHERE u.userid=ug.userid'.
			' AND '.dbConditionInt('ug.usrgrpid', $usrgrpids).
			andDbNode('ug.usrgrpid', false)
	);
	while($user = DBFetch($db_users)){
		$userids[$user['userid']] = $user['userid'];
	}
	return $userids;
}

function add_user_to_group($userid, $usrgrpid) {
	$result = false;
	if (granted2move_user($userid,$usrgrpid)) {
		DBexecute('DELETE FROM users_groups WHERE userid='.$userid.' AND usrgrpid='.$usrgrpid);
		$users_groups_id = get_dbid('users_groups', 'id');
		$result = DBexecute('INSERT INTO users_groups (id,usrgrpid,userid) VALUES ('.$users_groups_id.','.$usrgrpid.','.$userid.')');
	}
	else{
		error(_('User cannot change status of himself.'));
	}
	return $result;
}

function remove_user_from_group($userid, $usrgrpid) {
	$result = false;
	if (granted2move_user($userid,$usrgrpid)) {
		$result = DBexecute('DELETE FROM users_groups WHERE userid='.$userid.' AND usrgrpid='.$usrgrpid);
	}
	else {
		error(_('User cannot change status of himself.'));
	}
	return $result;
}

// checks if user is adding himself to disabled group
function granted2update_group($usrgrpids) {
	zbx_value2array($usrgrpids);
	$users = get_userid_by_usrgrpid($usrgrpids);
	return (!isset($users[CWebUser::$data['userid']]));
}

// checks if user is adding himself to disabled group
function granted2move_user($userid, $usrgrpid) {
	$result = true;
	$group = API::UserGroup()->get(array('usrgrpids' => $usrgrpid, 'output' => API_OUTPUT_EXTEND));
	$group = reset($group);
	if ($group['gui_access'] == GROUP_GUI_ACCESS_DISABLED || $group['users_status'] == GROUP_STATUS_DISABLED){
		$result = (bccomp(CWebUser::$data['userid'], $userid) != 0);
	}
	return $result;
}

function change_group_status($usrgrpids, $users_status) {
	zbx_value2array($usrgrpids);
	$result = false;
	$grant = true;
	if ($users_status == GROUP_STATUS_DISABLED) {
		$grant = granted2update_group($usrgrpids);
	}

	if ($grant) {
		$result = DBexecute('UPDATE usrgrp SET users_status='.$users_status.' WHERE '.dbConditionInt('usrgrpid', $usrgrpids));
	}
	else {
		error(_('User cannot change status of himself.'));
	}
	return $result;
}

function change_group_gui_access($usrgrpids, $gui_access) {
	zbx_value2array($usrgrpids);
	$result = false;
	$grant = true;
	if ($gui_access == GROUP_GUI_ACCESS_DISABLED) {
		$grant = granted2update_group($usrgrpids);
	}
	if ($grant) {
		$result = DBexecute('UPDATE usrgrp SET gui_access='.$gui_access.' WHERE '.dbConditionInt('usrgrpid',$usrgrpids));
	}
	else {
		error(_('User cannot change GUI access for himself.'));
	}
	return $result;
}

function change_group_debug_mode($usrgrpids, $debug_mode){
	zbx_value2array($usrgrpids);
	return DBexecute('UPDATE usrgrp SET debug_mode='.$debug_mode.' WHERE '.dbConditionInt('usrgrpid', $usrgrpids));
}

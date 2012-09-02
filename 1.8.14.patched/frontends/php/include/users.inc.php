<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php

	function getUserTheme($USER_DETAILS){
		$config = select_config();

		if(isset($config['default_theme'])) $css = $config['default_theme'];
		if(isset($USER_DETAILS['theme']) && ($USER_DETAILS['theme']!=ZBX_DEFAULT_CSS) && ($USER_DETAILS['alias']!=ZBX_GUEST_USER)) $css = $USER_DETAILS['theme'];
		if(!isset($css)) $css = 'css_ob.css';
	return $css;
	}

	function user_type2str($user_type=null){
		$user_types = array(
			USER_TYPE_ZABBIX_USER => S_ZABBIX_USER,
			USER_TYPE_ZABBIX_ADMIN => S_ZABBIX_ADMIN,
			USER_TYPE_SUPER_ADMIN => S_SUPER_ADMIN,
		);

		if(is_null($user_type)){
			return $user_types;
		}
		else if(isset($user_types[$user_type])){
			return $user_types[$user_type];
		}
		else return S_UNKNOWN;
	}

	function user_auth_type2str($auth_type){
		if(is_null($auth_type)){
			global $USER_DETAILS;
			$auth_type = get_user_auth($USER_DETAILS['userid']);
		}

		$auth_user_type[GROUP_GUI_ACCESS_SYSTEM]	= S_SYSTEM_DEFAULT;
		$auth_user_type[GROUP_GUI_ACCESS_INTERNAL]	= S_INTERNAL_S;
		$auth_user_type[GROUP_GUI_ACCESS_DISABLED]	= S_DISABLED;

		if(isset($auth_user_type[$auth_type]))
			return $auth_user_type[$auth_type];

	return S_UNKNOWN;
	}

	function unblock_user_login($userids){
		zbx_value2array($userids);

		$sql = 'UPDATE users SET attempt_failed=0 WHERE '.DBcondition('userid', $userids);
		$result = DBexecute($sql);

	return $result;
	}

	function get_userid_by_usrgrpid($usrgrpids){
		zbx_value2array($usrgrpids);

		$userids = array();

		$sql = 'SELECT DISTINCT u.userid '.
				' FROM users u,users_groups ug '.
				' WHERE u.userid=ug.userid '.
					' AND '.DBcondition('ug.usrgrpid',$usrgrpids).
					' AND '.DBin_node('ug.usrgrpid', false);
		$res = DBselect($sql);
		while($user = DBFetch($res)){
			$userids[$user['userid']] = $user['userid'];
		}

	return $userids;
	}

	function add_user_to_group($userid,$usrgrpid){
		$result = false;
		if(granted2move_user($userid,$usrgrpid)){
			DBexecute('DELETE FROM users_groups WHERE userid='.$userid.' AND usrgrpid='.$usrgrpid);

			$users_groups_id = get_dbid("users_groups","id");
			$result = DBexecute('INSERT INTO users_groups (id,usrgrpid,userid) '.
									' VALUES ('.$users_groups_id.','.$usrgrpid.','.$userid.')');
		}
		else{
			error(S_USER_CANNOT_CHANGE_STATUS);
		}
	return $result;
	}

	function remove_user_from_group($userid,$usrgrpid){
		$result = false;
		if(granted2move_user($userid,$usrgrpid)){
			$result = DBexecute('DELETE FROM users_groups WHERE userid='.$userid.' AND usrgrpid='.$usrgrpid);
		}
		else{
			error(S_USER_CANNOT_CHANGE_STATUS);
		}
	return  $result;
	}


// description:
//		checks if user is adding himself to disabled group
	function granted2update_group($usrgrpids){
		global $USER_DETAILS;
		zbx_value2array($usrgrpids);

		$users = get_userid_by_usrgrpid($usrgrpids);
		$result=(!isset($users[$USER_DETAILS['userid']]));

	return $result;
	}


// description:
//		checks if user is adding himself to disabled group
	function granted2move_user($userid,$usrgrpid){
		global $USER_DETAILS;

		$result = true;
		$group = CUserGroup::get(array('usrgrpids' => $usrgrpid,  'extendoutput' => 1));
		$group = reset($group);

		if(($group['gui_access'] == GROUP_GUI_ACCESS_DISABLED) || ($group['users_status'] == GROUP_STATUS_DISABLED)){
			$result=(bccomp($USER_DETAILS['userid'],$userid)!=0);
		}

	return $result;
	}
/**************************
	USER GROUPS
**************************/


	function change_group_status($usrgrpids,$users_status){
		zbx_value2array($usrgrpids);

		$res = false;
		$grant = true;
		if($users_status == GROUP_STATUS_DISABLED) $grant = granted2update_group($usrgrpids);

		if($grant)
			$res = DBexecute('UPDATE usrgrp SET users_status='.$users_status.' WHERE '.DBcondition('usrgrpid',$usrgrpids));
		else
			error(S_USER_CANNOT_CHANGE_STATUS);

	return $res;
	}


	function change_group_gui_access($usrgrpids,$gui_access){
		zbx_value2array($usrgrpids);

		$res = false;
		$grant = true;
		if($gui_access == GROUP_GUI_ACCESS_DISABLED) $grant = granted2update_group($usrgrpids);

		if($grant)
			$res = DBexecute('UPDATE usrgrp SET gui_access='.$gui_access.' WHERE '.DBcondition('usrgrpid',$usrgrpids));
		else
			error(S_USER_CANNOT_CHANGE_GUI_ACCESS);

	return $res;
	}

	function change_group_api_access($usrgrpids, $api_access){
		zbx_value2array($usrgrpids);
		$res = false;
		$res = DBexecute('UPDATE usrgrp SET api_access='.$api_access.' WHERE '.DBcondition('usrgrpid',$usrgrpids));
	return $res;
	}

	function change_group_debug_mode($usrgrpids, $debug_mode){
		zbx_value2array($usrgrpids);
		$res = false;
		$res = DBexecute('UPDATE usrgrp SET debug_mode='.$debug_mode.' WHERE '.DBcondition('usrgrpid',$usrgrpids));
	return $res;
	}
?>

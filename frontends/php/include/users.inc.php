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
	function get_user_detail($param){
		global $USER_DETAILS;

		$result = null;
		if(isset($USER_DETAILS[$param])) $result = $USER_DETAILS[$param];

	return $USER_DETAILS[$param];
	}

	function user_type2str($user_type_int){
		$str_user_type[USER_TYPE_ZABBIX_USER]	= S_ZABBIX_USER;
		$str_user_type[USER_TYPE_ZABBIX_ADMIN]	= S_ZABBIX_ADMIN;
		$str_user_type[USER_TYPE_SUPER_ADMIN]	= S_SUPER_ADMIN;

		if(isset($str_user_type[$user_type_int]))
			return $str_user_type[$user_type_int];

		return S_UNKNOWN;
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

// Update User definition
	function update_user_profile($userid, $passwd,$url, $autologin, $autologout, $lang, $theme, $refresh, $user_medias){
		global $USER_DETAILS;

		if((bccomp($userid,$USER_DETAILS["userid"]) != 0)){
			access_deny();
		}

		$sql = 'UPDATE users SET '.
					' url='.zbx_dbstr($url).','.
					' autologin='.$autologin.','.
					' autologout='.$autologout.','.
					' lang='.zbx_dbstr($lang).','.
					' theme='.zbx_dbstr($theme).','.
					(isset($passwd)?(' passwd='.zbx_dbstr(md5($passwd)).',') : '').
					' refresh='.$refresh.
				' WHERE userid='.$userid;

		$result = DBexecute($sql);

		$result = DBexecute('DELEET FROM media WHERE userid='.$userid);
			foreach($user_medias as $mediaid => $media_data){
				$mediaid = get_dbid("media","mediaid");

				$sql = 'INSERT INTO media (mediaid,userid,mediatypeid,sendto,active,severity,period)'.
						' VALUES ('.$mediaid.','.$userid.','.$media_data['mediatypeid'].','.
								zbx_dbstr($media_data['sendto']).','.$media_data['active'].','.$media_data['severity'].','.
								zbx_dbstr($media_data['period']).')';

				$result = DBexecute($sql);
				if(!$result) break;
			}
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

	function add_user_group($name,$users_status,$gui_access,$api_access,$debug_mode,$users=array(),$rights=array()){

		if(DBfetch(DBselect('select * from usrgrp where name='.zbx_dbstr($name).' and '.DBin_node('usrgrpid', get_current_nodeid(false))))){
			error(S_GROUP.SPACE.$name.SPACE.S_ALREADY_EXISTS_SMALL);
			return 0;
		}

		$usrgrpid=get_dbid("usrgrp","usrgrpid");

		$result=DBexecute("INSERT INTO usrgrp (usrgrpid,name) VALUES ($usrgrpid,".zbx_dbstr($name).")");
		if(!$result)	return	$result;

// must come before adding user to group
		$result&=change_group_status($usrgrpid,$users_status);
		$result&=change_group_gui_access($usrgrpid,$gui_access);
		$result&=change_group_api_access($usrgrpid, $api_access);
		$result&=change_group_debug_mode($usrgrpid, $debug_mode);
		if(!$result) return	$result;
//--------

		foreach($users as $userid => $name){
			$result &= add_user_to_group($userid,$usrgrpid);
			if(!$result)	return	$result;
		}

		$result=DBexecute("delete from rights where groupid=".$usrgrpid);
		foreach($rights as $right){
			$id = get_dbid('rights','rightid');
			$result=DBexecute('insert into rights (rightid,groupid,permission,id)'.
				' values ('.$id.','.$usrgrpid.','.$right['permission'].','.$right['id'].')');

			if(!$result)	return	$result;
		}

		return $result;
	}

	function update_user_group($usrgrpid,$name,$users_status,$gui_access,$api_access,$debug_mode,$users=array(),$rights=array()){
		global $USER_DETAILS;

		$sql = 'SELECT * '.
				' FROM usrgrp '.
				' WHERE name='.zbx_dbstr($name).
					' AND usrgrpid<>'.$usrgrpid.
					' AND '.DBin_node('usrgrpid', false);
		if(DBfetch(DBselect($sql))){
			error(S_GROUP.SPACE.$name.SPACE.S_ALREADY_EXISTS_SMALL);
			return 0;
		}

		$result=DBexecute('UPDATE usrgrp SET name='.zbx_dbstr($name).' WHERE usrgrpid='.$usrgrpid);
		if(!$result) return	$result;

// must come before adding user to group
		$result&=change_group_status($usrgrpid,$users_status);
		$result&=change_group_gui_access($usrgrpid,$gui_access);
		$result&=change_group_api_access($usrgrpid, $api_access);
		$result&=change_group_debug_mode($usrgrpid, $debug_mode);
		if(!$result) return	$result;
//-------

		$grant = true;
		if(($gui_access == GROUP_GUI_ACCESS_DISABLED) || ($users_status == GROUP_STATUS_DISABLED)){
			$grant = !uint_in_array($USER_DETAILS['userid'],$users);
		}
		if($grant){
			$result = DBexecute('DELETE FROM users_groups WHERE usrgrpid='.$usrgrpid);
			foreach($users as $userid => $name){
				$result = add_user_to_group($userid,$usrgrpid);
				if(!$result)	return	$result;
			}
		}
		else{
			error(S_USER_CANNOT_DISABLE_ITSELF);
			return false;
		}

		$result=DBexecute('DELETE FROM rights WHERE groupid='.$usrgrpid);
		foreach($rights as $right){
			$id = get_dbid('rights','rightid');
			$result = DBexecute('INSERT INTO rights (rightid,groupid,permission,id)'.
				' VALUES ('.$id.','.$usrgrpid.','.$right['permission'].','.$right['id'].')');

			if(!$result)	return	$result;
		}

	return $result;
	}

	function delete_user_group($usrgrpids){
		zbx_value2array($usrgrpids);

		$result = DBexecute('DELETE FROM rights WHERE '.DBcondition('groupid',$usrgrpids));
		if(!$result)	return	$result;

		DBexecute('DELETE FROM operations WHERE object='.OPERATION_OBJECT_GROUP.' AND '.DBcondition('objectid',$usrgrpids));

		$result = DBexecute('DELETE FROM users_groups WHERE '.DBcondition('usrgrpid',$usrgrpids));
		if(!$result)	return	$result;

		$result = DBexecute('DELETE FROM usrgrp WHERE '.DBcondition('usrgrpid',$usrgrpids));

	return $result;
	}

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

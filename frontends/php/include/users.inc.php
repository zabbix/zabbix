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


// Add User definition
	function add_user($user){
		global $USER_DETAILS;

		if($USER_DETAILS['type'] != USER_TYPE_SUPER_ADMIN){
			error("Insufficient permissions");
			return false;
		}

		$sql = 'SELECT * '.
				' FROM users '.
				' WHERE alias='.zbx_dbstr($user['alias']).
					' AND '.DBin_node('userid', get_current_nodeid(false));
		if(DBfetch(DBselect($sql))){
			error('User "'.$user['alias'].'" already exists');
			return false;
		}

		$user_db_fields = array(
			'name' => 'ZABBIX',
			'surname' => 'USER',
			'alias' => null,
			'passwd' => 'zabbix',
			'url' => '',
			'autologin' => 0,
			'autologout' => 900,
			'lang' => 'en_gb',
			'theme' => 'default.css',
			'refresh' => 30,
			'rows_per_page' => 50,
			'type' => USER_TYPE_ZABBIX_USER,
			'user_groups' => array(),
			'user_medias' => array(),
		);

		if(!check_db_fields($user_db_fields, $user)){
			error('Incorrect arguments pasted to function [add_user]');
			return false;
		}

		$userid = get_dbid('users', 'userid');

		$result = DBexecute('INSERT INTO users (userid,name,surname,alias,passwd,url,autologin,autologout,lang,theme,refresh,rows_per_page,type) VALUES ('.
			$userid.','.
			zbx_dbstr($user['name']).','.
			zbx_dbstr($user['surname']).','.
			zbx_dbstr($user['alias']).','.
			zbx_dbstr(md5($user['passwd'])).','.
			zbx_dbstr($user['url']).','.
			$user['autologin'].','.
			$user['autologout'].','.
			zbx_dbstr($user['lang']).','.
			zbx_dbstr($user['theme']).','.
			$user['refresh'].','.
			$user['rows_per_page'].','.
			$user['type'].
			')');

		if($result){
//			$result = DBexecute('DELETE FROM users_groups WHERE userid='.$userid);
			foreach($user['user_groups'] as $groupid => $group_pname){
				if(!$result) break;
				$users_groups_id = get_dbid("users_groups","id");
				$result = DBexecute('INSERT INTO users_groups (id,usrgrpid,userid)'.
					'values('.$users_groups_id.','.$groupid.','.$userid.')');
			}
		}

		if($result) {
//			$result = DBexecute('DELETE FROM media WHERE userid='.$userid);
			foreach($user['user_medias'] as $mediaid => $media_data){
				if(!$result) break;
				$mediaid = get_dbid("media","mediaid");
				$result = DBexecute('INSERT INTO media (mediaid,userid,mediatypeid,sendto,active,severity,period)'.
					' VALUES ('.$mediaid.','.$userid.','.$media_data['mediatypeid'].','.
					zbx_dbstr($media_data['sendto']).','.$media_data['active'].','.$media_data['severity'].','.
					zbx_dbstr($media_data['period']).')');
			}
		}

		return $result;
	}

// Update User definition
	function update_user($userid, $user) {

		$result = true;

		$sql = 'SELECT DISTINCT * '.
			' FROM users '.
			' WHERE ( alias='.zbx_dbstr($user['alias']).' OR userid='.$userid.' ) '.
				' AND '.DBin_node('userid', id2nodeid($userid));

		$db_users = DBselect($sql);
		while($db_user = DBfetch($db_users)){
			if($db_user['userid'] != $userid){
				error('User '.$user['alias'].' already exists');
				return false;
			}
			$user_db_fields = $db_user;
		}

		if(!isset($user_db_fields)) {
			return false;
		}

		if(isset($user['passwd'])) {
			$user['passwd'] = md5($user['passwd']);
		}

		if(!check_db_fields($user_db_fields, $user)){
			error('Incorrect arguments pasted to function [update_user]');
			return false;
		}

		$sql = 'UPDATE users SET '.
				' name='.zbx_dbstr($user['name']).
				' ,surname='.zbx_dbstr($user['surname']).
				' ,alias='.zbx_dbstr($user['alias']).
				' ,passwd='.zbx_dbstr($user['passwd']).
				' ,url='.zbx_dbstr($user['url']).
				' ,autologin='.$user['autologin'].
				' ,autologout='.$user['autologout'].
				' ,lang='.zbx_dbstr($user['lang']).
				' ,theme='.zbx_dbstr($user['theme']).
				' ,refresh='.$user['refresh'].
				' ,rows_per_page='.$user['rows_per_page'].
				' ,type='.$user['type'].
				' WHERE userid='.$userid;
		$result = DBexecute($sql);

		if($result && !is_null($user['user_groups'])){
			$result = DBexecute('DELETE FROM users_groups WHERE userid='.$userid);
			foreach($user['user_groups'] as $groupid => $group_name){
				if(!$result) break;
				$users_groups_id = get_dbid('users_groups', 'id');
				$result = DBexecute('INSERT INTO users_groups (id, usrgrpid, userid)'.
					'values('.$users_groups_id.','.$groupid.','.$userid.')');
			}
		}

		if($result && !is_null($user['user_medias'])){
			$result = DBexecute('DELETE FROM media WHERE userid='.$userid);
			foreach($user['user_medias'] as $mediaid => $media_data) {
				if(!$result) break;
				$mediaid = get_dbid('media', 'mediaid');
				$result = DBexecute('INSERT INTO media (mediaid, userid, mediatypeid, sendto, active, severity, period)'.
					' values ('.$mediaid.','.$userid.','.$media_data['mediatypeid'].','.
					zbx_dbstr($media_data['sendto']).','.$media_data['active'].','.$media_data['severity'].','.
					zbx_dbstr($media_data['period']).')');
			}
		}

		return $result;
	}


// Update User definition
	function update_user_profile($userid, $passwd,$url, $autologin, $autologout, $lang, $theme, $refresh, $user_medias){
		global $USER_DETAILS;

		if((bccomp($userid,$USER_DETAILS["userid"]) != 0)){
			access_deny();
		}

		DBbegin();
		$result = DBexecute('update users set '.
						' url='.zbx_dbstr($url).
						' ,autologin='.$autologin.
						' ,autologout='.$autologout.
						' ,lang='.zbx_dbstr($lang).
						' ,theme='.zbx_dbstr($theme).
						(isset($passwd) ? (' ,passwd='.zbx_dbstr(md5($passwd))) : '').
						' ,refresh='.$refresh.
					' where userid='.$userid);

		$result = DBexecute('delete from media where userid='.$userid);
			foreach($user_medias as $mediaid => $media_data)
			{
				$mediaid = get_dbid("media","mediaid");
				$result = DBexecute('insert into media (mediaid,userid,mediatypeid,sendto,active,severity,period)'.
					' values ('.$mediaid.','.$userid.','.$media_data['mediatypeid'].','.
					zbx_dbstr($media_data['sendto']).','.$media_data['active'].','.$media_data['severity'].','.
					zbx_dbstr($media_data['period']).')');

				if(!$result) break;
			}
	}

	# Delete User definition

	function delete_user($userid){
		global $USER_DETAILS;

		if(DBfetch(DBselect('SELECT * FROM users WHERE userid='.$userid.' AND alias='.zbx_dbstr(ZBX_GUEST_USER)))){
			error(S_CANNOT_DELETE_USER.SPACE."'".ZBX_GUEST_USER."'");
			return	false;
		}

		if(bccomp($USER_DETAILS['userid'],$userid) == 0){
			error(S_USER_CANNOT_DELETE_ITSELF);
			return false;
		}

		DBexecute('delete from operations where object='.OPERATION_OBJECT_USER.' and objectid='.$userid);

		$result = DBexecute('delete from media where userid='.$userid);
		if(!$result) return $result;

		$result = DBexecute('delete from profiles where userid='.$userid);
		if(!$result) return $result;

		$result = DBexecute('delete from users_groups where userid='.$userid);
		if(!$result) return $result;

		$result = DBexecute('delete from users where userid='.$userid);

		return $result;
	}


	function get_user_by_userid($userid){
		if($row = DBfetch(DBselect('SELECT * FROM users WHERE userid='.$userid))){
			return	$row;
		}
		/* error("No user with id [$userid]"); */
	return	false;
	}

	function get_userid_by_usrgrpid($usrgrpids){
		zbx_value2array($usrgrpids);
		
		$userids = array();
		
		$sql = 'SELECT DISTINCT u.userid '.
				' FROM users u,users_groups ug '.
				' WHERE u.userid=ug.userid '.
					' AND '.DBcondition('ug.usrgrpid',$usrgrpids).
					' AND '.DBin_node('ug.usrgrpid', get_current_nodeid(false));
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
		$result=(!uint_in_array($USER_DETAILS['userid'],$users));

	return $result;
	}


// description:
//		checks if user is adding himself to disabled group
	function granted2move_user($userid,$usrgrpid){
		global $USER_DETAILS;

		$result = true;
		$group = get_group_by_usrgrpid($usrgrpid);

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
			error("Group '$name' already exists");
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
					' AND '.DBin_node('usrgrpid', get_current_nodeid(false));
		if(DBfetch(DBselect($sql))){
			error("Group '$name' already exists");
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

	function get_group_by_usrgrpid($usrgrpid){
		if($row = DBfetch(DBselect("select * from usrgrp where usrgrpid=".$usrgrpid))){
			return $row;
		}
		/* error("No user groups with id [$usrgrpid]"); */
	return  FALSE;
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

/**************** MENU ****************/
	function set_users_jsmenu_array(){
		$menu_all = array();
		$menu_gui_access = array();
		$menu_api_access = array();
		$menu_users_status = array();

		$res = DBselect('SELECT DISTINCT g.usrgrpid, g.name, g.gui_access, g.api_access, g.users_status'.
			' FROM usrgrp g'.
			' WHERE '.DBin_node('g.usrgrpid', get_current_nodeid(false)).
			' ORDER BY g.name');

		while($group=DBfetch($res)){
			$group['name'] = htmlspecialchars($group['name']);

			$gui_access = $group['gui_access'];
			$api_access = $group['api_access'];
			$users_status = $group['users_status'];

			unset($group['gui_access']);
			unset($group['api_access']);
			unset($group['users_status']);

			$menu_all[] = $group;
			if($gui_access != GROUP_GUI_ACCESS_SYSTEM){
				$menu_gui_access[] = $group;
			}
			if($api_access == GROUP_API_ACCESS_ENABLED){
				$menu_api_access[] = $group;
			}
			if($users_status == GROUP_STATUS_DISABLED){
				$menu_users_status[] = $group;
			}
		}
		insert_js(
			'var menu_usrgrp_all='.zbx_jsvalue($menu_all).";\n".
			'var menu_usrgrp_gui='.zbx_jsvalue($menu_gui_access).";\n".
			'var menu_usrgrp_api='.zbx_jsvalue($menu_api_access).";\n".
			'var menu_usrgrp_status='.zbx_jsvalue($menu_users_status).";\n"
		);

	}

	function get_user_actionmenu($userid){
		$usr_grp_all_in = array();
		$usr_grp_gui_in = array();
		$usr_grp_api_in = array();
		$usr_grp_status_in = array();

		$sql = 'SELECT DISTINCT g.name, g.usrgrpid, g.gui_access, g.api_access, g.users_status '.
			' FROM users_groups ug, usrgrp g '.
			' WHERE ug.userid='.$userid.
				' AND g.usrgrpid=ug.usrgrpid '.
				' AND '.DBin_node('g.usrgrpid', get_current_nodeid(false));
		$res = DBselect($sql);

		while($group = DBFetch($res)){
			$group['name'] = htmlspecialchars($group['name']);

			$gui_access = $group['gui_access'];
			$api_access = $group['api_access'];
			$users_status = $group['users_status'];

			unset($group['gui_access']);
			unset($group['api_access']);
			unset($group['users_status']);

			$usr_grp_all_in[] = $group;
			if($gui_access != GROUP_GUI_ACCESS_SYSTEM){
				$usr_grp_gui_in[] = $group;
			}
			if($api_access == GROUP_API_ACCESS_ENABLED){
				$usr_grp_api_in[] = $group;
			}
			if($users_status == GROUP_STATUS_DISABLED){
				$usr_grp_status_in[] = $group;
			}
		}

		$action = new CSpan(S_SELECT);
		$script = new CScript("javascript: create_user_menu(event,".
												$userid.",".
												zbx_jsvalue($usr_grp_all_in).",".
												zbx_jsvalue($usr_grp_gui_in).",".
												zbx_jsvalue($usr_grp_api_in).",".
												zbx_jsvalue($usr_grp_status_in).");"
							 );

		$action->AddAction('onclick',$script);
		$action->setAttribute('onmouseover','javascript: this.style.cursor = "pointer";');

	return $action;
	}
?>

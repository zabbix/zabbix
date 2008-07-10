<?php
/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
		$auth_user_type[GROUP_GUI_ACCESS_INTERNAL]	= S_INTERNAL;
		$auth_user_type[GROUP_GUI_ACCESS_DISABLED]	= S_DISABLED;

		if(isset($auth_user_type[$auth_type]))
			return $auth_user_type[$auth_type];

	return S_UNKNOWN;
	}
	

// Add User definition
	function add_user($name,$surname,$alias,$passwd,$url,$autologin,$autologout,$lang,$theme,$refresh,$user_type,$user_groups,$user_medias){
		global $USER_DETAILS;

		if($USER_DETAILS['type'] != USER_TYPE_SUPER_ADMIN){
			error("Insufficient permissions");
			return 0;
		}
		
		if(DBfetch(DBselect("select * from users where alias=".zbx_dbstr($alias)." and ".DBin_node('userid', get_current_nodeid(false))))){
			error('User "'.$alias.'" already exists');
			return 0;
		}

		$userid = get_dbid("users","userid");

		$result =  DBexecute('insert into users (userid,name,surname,alias,passwd,url,autologin,autologout,lang,theme,refresh,type)'.
			' values ('.$userid.','.zbx_dbstr($name).','.zbx_dbstr($surname).','.zbx_dbstr($alias).','.
			zbx_dbstr(md5($passwd)).','.zbx_dbstr($url).','.$autologin.','.$autologout.','.zbx_dbstr($lang).','.zbx_dbstr($theme).','.$refresh.','.$user_type.')');
		
		if($result){
			DBexecute('delete from users_groups where userid='.$userid);
			foreach($user_groups as $groupid => $grou_pname){
				$users_groups_id = get_dbid("users_groups","id");
				$result = DBexecute('insert into users_groups (id,usrgrpid,userid)'.
					'values('.$users_groups_id.','.$groupid.','.$userid.')');

				if(!$result) break;
			}
			if($result)
			{
				DBexecute('delete from media where userid='.$userid);
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
		}

		return $result;
	}

	# Update User definition

	function update_user($userid,$name,$surname,$alias,$passwd,$url,$autologin,$autologout,$lang,$theme,$refresh,$user_type,$user_groups,$user_medias){
		if(DBfetch(DBselect("select * from users where alias=".zbx_dbstr($alias)." and userid<>$userid and ".DBin_node('userid', get_current_nodeid(false))))){
			error("User '$alias' already exists");
			return 0;
		}

		$result = DBexecute('UPDATE users SET '.
						' name='.zbx_dbstr($name).
						' ,surname='.zbx_dbstr($surname).
						' ,alias='.zbx_dbstr($alias).
						(isset($passwd) ? (',passwd='.zbx_dbstr(md5($passwd))) : '').
						' ,url='.zbx_dbstr($url).
						' ,autologin='.$autologin.
						' ,autologout='.$autologout.
						' ,lang='.zbx_dbstr($lang).
						' ,theme='.zbx_dbstr($theme).
						' ,refresh='.$refresh.
						' ,type='.$user_type.
					' WHERE userid='.$userid);

		if($result){
			DBexecute('delete from users_groups where userid='.$userid);
			foreach($user_groups as $groupid => $grou_pname){
				$users_groups_id = get_dbid("users_groups","id");
				$result = DBexecute('insert into users_groups (id,usrgrpid,userid)'.
					'values('.$users_groups_id.','.$groupid.','.$userid.')');

				if(!$result) break;
			}
			if($result){
				DBexecute('delete from media where userid='.$userid);
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
		}

		return $result;
	}


	# Update User definition

	function	update_user_profile($userid,$passwd,$url,$autologin,$autologout,$lang,$theme,$refresh)
	{
		global $USER_DETAILS;

		if((bccomp($userid,$USER_DETAILS["userid"]) != 0))
		{
			access_deny();
		}

		return DBexecute('update users set '.
						' url='.zbx_dbstr($url).
						' ,autologin='.$autologin.
						' ,autologout='.$autologout.
						' ,lang='.zbx_dbstr($lang).
						' ,theme='.zbx_dbstr($theme).
						(isset($passwd) ? (' ,passwd='.zbx_dbstr(md5($passwd))) : '').
						' ,refresh='.$refresh.
					' where userid='.$userid);
	}

	# Delete User definition

	function	delete_user($userid){
		global $USER_DETAILS;
		if(DBfetch(DBselect('select * from users where userid='.$userid.' and alias='.zbx_dbstr(ZBX_GUEST_USER)))){
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
		if($row = DBfetch(DBselect('select * from users where userid='.zbx_dbstr($userid)))){
			return	$row;
		}
		/* error("No user with id [$userid]"); */
		return	false;
	}

	
	function get_userid_by_usrgrpid($usrgrpid){
		$userids = array();
		if($res=DBselect('SELECT DISTINCT u.userid '.
						' FROM users u,users_groups ug '.
						' WHERE u.userid=ug.userid '.
							' AND ug.usrgrpid='.$usrgrpid.
							' AND '.DBin_node('ug.usrgrpid', get_current_nodeid(false))))
		{
			while($rows = DBFetch($res)) $userids[]=$rows['userid'];
		}

	return $userids;
	}
	
	
	function add_user_to_group($userid,$usrgrpid){
		$result = false;
		if(granted2move_user($userid,$usrgrpid)){
			DBexecute('delete from users_groups where userid='.$userid.' and usrgrpid='.$usrgrpid);
	
			$users_groups_id = get_dbid("users_groups","id");
			$result = DBexecute('insert into users_groups (id,usrgrpid,userid) values('.$users_groups_id.','.$usrgrpid.','.$userid.')');
		}
		else{
			error(S_USER_CANNOT_CHANGE_STATUS);
		}
	return $result;
	}

	function remove_user_from_group($userid,$usrgrpid){
		$result = false;
		if(granted2move_user($userid,$usrgrpid)){
			$result = DBexecute('delete from users_groups where userid='.$userid.' and usrgrpid='.$usrgrpid);
		}
		else{
			error(S_USER_CANNOT_CHANGE_STATUS);
		}
	return  $result;
	}


// description:
//		checks if user is adding himself to disabled group
	function granted2update_group($usrgrpid){
		global $USER_DETAILS;
		$users = get_userid_by_usrgrpid($usrgrpid);
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

	function add_user_group($name,$users_status,$gui_access,$users=array(),$rights=array()){
	
		if(DBfetch(DBselect('select * from usrgrp where name='.zbx_dbstr($name).' and '.DBin_node('usrgrpid', get_current_nodeid(false))))){
			error("Group '$name' already exists");
			return 0;
		}

		$usrgrpid=get_dbid("usrgrp","usrgrpid");

		$result=DBexecute("insert into usrgrp (usrgrpid,name) values ($usrgrpid,".zbx_dbstr($name).")");
		if(!$result)	return	$result;

// must come before adding user to group
		$result&=change_group_status($usrgrpid,$users_status);
		$result&=change_group_gui_access($usrgrpid,$gui_access);
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

	function update_user_group($usrgrpid,$name,$users_status,$gui_access,$users=array(),$rights=array()){
		global $USER_DETAILS;
		
		if(DBfetch(DBselect('select * from usrgrp where name='.zbx_dbstr($name).
			' and usrgrpid<>'.$usrgrpid.' and '.DBin_node('usrgrpid', get_current_nodeid(false)))))
		{
			error("Group '$name' already exists");
			return 0;
		}

		$result=DBexecute("update usrgrp set name=".zbx_dbstr($name)." where usrgrpid=$usrgrpid");
		if(!$result) return	$result;

// must come before adding user to group
		$result&=change_group_status($usrgrpid,$users_status);
		$result&=change_group_gui_access($usrgrpid,$gui_access);
		if(!$result) return	$result;
//-------
		
		$grant = true;
		if(($gui_access == GROUP_GUI_ACCESS_DISABLED) || ($users_status == GROUP_STATUS_DISABLED)){
			$grant = (!uint_in_array($USER_DETAILS['userid'],$users));
		}
		if($grant){
			$result = DBexecute('delete from users_groups where usrgrpid='.zbx_dbstr($usrgrpid));
			foreach($users as $userid => $name){
				$result &= add_user_to_group($userid,$usrgrpid);
				if(!$result)	return	$result;
			}
		}
		else{
			error(S_USER_CANNOT_DISABLE_ITSELF);
			return false;
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

	function	delete_user_group($usrgrpid){
		$result = DBexecute("delete from rights where groupid=$usrgrpid");
		if(!$result)	return	$result;

		DBexecute('delete from operations where object='.OPERATION_OBJECT_GROUP.' and objectid='.$usrgrpid);

		$result = DBexecute("delete from users_groups where usrgrpid=$usrgrpid");
		if(!$result)	return	$result;

		$result = DBexecute("delete from usrgrp where usrgrpid=$usrgrpid");
	return	$result;
	}

	function	get_group_by_usrgrpid($usrgrpid){
		if($row = DBfetch(DBselect("select * from usrgrp where usrgrpid=".$usrgrpid))){
			return $row;
		}
		/* error("No user groups with id [$usrgrpid]"); */
	return  FALSE;
	}

	
	function change_group_status($usrgrpid,$users_status){
		$res = false;
		
		$grant = true;
		if($users_status == GROUP_STATUS_DISABLED) $grant = granted2update_group($usrgrpid);
		
		if($grant){
			$res = DBexecute('UPDATE usrgrp SET users_status='.$users_status.' WHERE usrgrpid='.$usrgrpid);
		}
		else{
			error(S_USER_CANNOT_CHANGE_STATUS);
		}
	return $res;
	}
	
	
	function change_group_gui_access($usrgrpid,$gui_access){		
		$res = false;

		if(($gui_access == GROUP_GUI_ACCESS_DISABLED) && !granted2update_group($usrgrpid)){
			error(S_USER_CANNOT_CHANGE_GUI_ACCESS);
			return false;
		}
				
		if(GROUP_GUI_ACCESS_INTERNAL == $gui_access){
			$config = select_config();
		 	if(ZBX_AUTH_HTTP == $config['authentication_type']){
				error(S_CANNOT_SET.' ['.S_INTERNAL.'] '.S_GROUP.' '.S_GUI_ACCESS);
				return false;
			}
		}
		
		$res = DBexecute('UPDATE usrgrp SET gui_access='.$gui_access.' WHERE usrgrpid='.$usrgrpid);
		
	return $res;
	}
	
/********************************/

	function get_user_actionmenu($userid){
		global $USER_DETAILS;
		
		$action = new CSpan(S_SELECT);		

// add to group
		$menus = "Array(Array('".S_GROUPS."',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}),
						Array('".S_ADD_TO."',null,null,{'outer' : ['pum_o_submenu'],'inner' : ['pum_i_submenu']},";
		$menus.= "['".S_GROUPS."',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],";

		$grp_list = '(';
		if($res = DBselect('SELECT DISTINCT ug.usrgrpid '.
			' FROM users_groups ug'.
			' WHERE ug.userid='.zbx_dbstr($userid).
				' AND '.DBin_node('ug.usrgrpid', get_current_nodeid(false))))
		{
			while($tmp = DBFetch($res)) $grp_list.= "'".$tmp['usrgrpid']."'".',';
		}
		$grp_list.="'0')";
		
		$res = DBselect('SELECT DISTINCT g.usrgrpid, g.name, g.gui_access, g.users_status'.
			' FROM usrgrp g'.
			' WHERE g.usrgrpid NOT IN '.$grp_list.
				' AND '.DBin_node('g.usrgrpid', get_current_nodeid(false)).
			' ORDER BY g.name');

		while($group=DBfetch($res)){
			if(!granted2move_user($userid,$group['usrgrpid'])) continue;
			
			$caption = new CSpan($group['name']);
			if($group['users_status'] == GROUP_STATUS_DISABLED){
				$caption->SetClass('red');
			}
			else if($group['gui_access'] == GROUP_GUI_ACCESS_DISABLED){
				$caption->SetClass('orange');
			}
			
			$caption = htmlspecialchars(unpack_object($caption));
			$menus.="['".$caption."','users.php?config=0&form=update&grpaction=1&userid=".$userid."&usrgrpid=".$group['usrgrpid']."']\n,";
		}

		$menus=rtrim($menus,',').'),';
// remove from group
		$menus.= "Array('".S_REMOVE_FROM."',null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']},";
		$menus.= "['".S_GROUPS."',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],";
				
		$res = DBselect('SELECT DISTINCT g.usrgrpid, g.name, g.gui_access, g.users_status '.
			' FROM usrgrp g, users_groups ug'.
			' WHERE ug.userid='.zbx_dbstr($userid).
				' AND ug.usrgrpid = g.usrgrpid '.
				' AND '.DBin_node('g.usrgrpid', get_current_nodeid(false)).
			' ORDER BY g.name');

		while($group=DBfetch($res)){
			if(!granted2move_user($userid,$group['usrgrpid'])) continue;

			$caption = new CSpan($group['name']);
			if($group['users_status'] == GROUP_STATUS_DISABLED){
				$caption->SetClass('red');
			}
			else if($group['gui_access'] == GROUP_GUI_ACCESS_DISABLED){
				$caption->SetClass('orange');
			}
						
			$caption = htmlspecialchars(unpack_object($caption));
			$menus.="['".$caption."','users.php?config=0&form=update&grpaction=0&userid=".$userid."&usrgrpid=".$group['usrgrpid']."']\n,";
		}

		$menus=rtrim($menus,',').'),';
		if(bccomp($USER_DETAILS['userid'],$userid) == 0){
			$menus=rtrim($menus,',').')';
		}
		else{
// add to GUI ACCESS
			$menus.= "Array('".S_GUI_ACCESS."',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}),
						Array('".S_ADD_TO."',null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']},";
			$menus.= "['".S_GUI_ACCESS."',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],";
			
			$grp_list = '(';
			if($res = DBselect('SELECT DISTINCT ug.usrgrpid '.
				' FROM users_groups ug, usrgrp g'.
				' WHERE ug.userid='.zbx_dbstr($userid).
					' AND g.gui_access='.GROUP_GUI_ACCESS_DISABLED.
					' AND '.DBin_node('g.usrgrpid', get_current_nodeid(false))))
			{
				while($tmp = DBFetch($res)) $grp_list.= "'".$tmp['usrgrpid']."'".',';
			}
			$grp_list.="'0')";
//			$grp_list=rtrim($grp_list,',').')';
			
			$res = DBselect('SELECT DISTINCT g.usrgrpid, g.name'.
				' FROM usrgrp g'.
				' WHERE g.usrgrpid NOT IN'.$grp_list.
					' AND g.gui_access='.GROUP_GUI_ACCESS_DISABLED.
					' AND '.DBin_node('g.usrgrpid', get_current_nodeid(false)).
				' ORDER BY g.name');
	
			while($group=DBfetch($res)){
				$caption = new CSpan($group['name'],'orange');			
				$caption = htmlspecialchars(unpack_object($caption));
				$menus.="['".$caption."','users.php?config=0&form=update&grpaction=1&userid=".$userid."&usrgrpid=".$group['usrgrpid']."']\n,";
			}
	
			$menus=rtrim($menus,',').'),';
// remove from GUI ACCESS
			$menus.= "Array('".S_REMOVE_FROM."',null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']},";
			$menus.= "['".S_GUI_ACCESS."',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],";
					
			$res = DBselect('SELECT g.name, g.usrgrpid'.
				' FROM usrgrp g, users_groups ug'.
				' WHERE ug.userid='.zbx_dbstr($userid).
					' AND ug.usrgrpid = g.usrgrpid '.
					' AND g.gui_access='.GROUP_GUI_ACCESS_DISABLED.
					' AND '.DBin_node('g.usrgrpid', get_current_nodeid(false)).
				' ORDER BY g.name');
	
			while($group=DBfetch($res)){
				$caption = new CSpan($group['name'],'orange');			
				$caption = htmlspecialchars(unpack_object($caption));
	
				$menus.="['".$caption."','users.php?config=0&form=update&grpaction=0&userid=".$userid."&usrgrpid=".$group['usrgrpid']."']\n,";
			}
	
			$menus=rtrim($menus,',').'),';
	
// add to DISABLED
			$menus.= "Array('".S_STATUS_DISABLED."',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}),
						Array('".S_ADD_TO."',null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']},";
			$menus.= "['".S_STATUS_DISABLED."',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],";
			
			$grp_list = '(';
			if($res = DBselect('SELECT DISTINCT ug.usrgrpid '.
				' FROM users_groups ug, usrgrp g'.
				' WHERE ug.userid='.zbx_dbstr($userid).
					' AND g.users_status='.GROUP_STATUS_DISABLED.
					' AND '.DBin_node('g.usrgrpid', get_current_nodeid(false))))
			{
				while($tmp = DBFetch($res)) $grp_list.= "'".$tmp['usrgrpid']."'".',';
			}
			$grp_list.="'0')";
//			$grp_list=rtrim($grp_list,',').')';
			
			$res = DBselect('SELECT DISTINCT g.usrgrpid, g.name'.
				' FROM usrgrp g'.
				' WHERE g.usrgrpid NOT IN'.$grp_list.
					' AND g.users_status='.GROUP_STATUS_DISABLED.
					' AND '.DBin_node('g.usrgrpid', get_current_nodeid(false)).
				' ORDER BY g.name');
	
			while($group=DBfetch($res)){
				$caption = new CSpan($group['name'],'red');			
				$caption = htmlspecialchars(unpack_object($caption));
	
				$menus.="['".$caption."','users.php?config=0&form=update&grpaction=1&userid=".$userid."&usrgrpid=".$group['usrgrpid']."']\n,";
			}
	
			$menus=rtrim($menus,',').'),';
// remove from DISABLED
			$menus.= "Array('".S_REMOVE_FROM."',null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']},";
			$menus.= "['".S_STATUS_DISABLED."',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],";
					
			$res = DBselect('SELECT g.name, g.usrgrpid'.
				' FROM usrgrp g, users_groups ug'.
				' WHERE ug.userid='.zbx_dbstr($userid).
					' AND ug.usrgrpid = g.usrgrpid '.
					' AND g.users_status='.GROUP_STATUS_DISABLED.
					' AND '.DBin_node('g.usrgrpid', get_current_nodeid(false)).
				' ORDER BY g.name');
	
	
			while($group=DBfetch($res)){
				$caption = new CSpan($group['name'],'red');			
				$caption = htmlspecialchars(unpack_object($caption));
	
				$menus.="['".$caption."','users.php?config=0&form=update&grpaction=0&userid=".$userid."&usrgrpid=".$group['usrgrpid']."']\n,";
			}
	
			$menus=rtrim($menus,',').'))';
		}
		
		$script = new CScript("javascript: show_popup_menu(event,".$menus.",240);");
		$action->AddAction('onclick',$script);
		$action->AddOption('onmouseover','javascript: this.style.cursor = "pointer";');
		
	return $action;
	}
?>
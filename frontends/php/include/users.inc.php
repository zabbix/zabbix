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

	function	user_type2str($user_type_int)
	{
		$str_user_type[USER_TYPE_ZABBIX_USER]	= S_ZABBIX_USER;
		$str_user_type[USER_TYPE_ZABBIX_ADMIN]	= S_ZABBIX_ADMIN;
		$str_user_type[USER_TYPE_SUPPER_ADMIN]	= S_SUPPER_ADMIN;

		if(isset($str_user_type[$user_type_int]))
			return $str_user_type[$user_type_int];

		return S_UNCNOWN;
	}

	# Add User definition

	function	add_user($name,$surname,$alias,$passwd,$url,$autologout,$lang,$refresh,$user_type,$user_groups)
	{
		global $USER_DETAILS;

		if($USER_DETAILS['type'] != USER_TYPE_SUPPER_ADMIN)
		{
			error("Insufficient permissions");
			return 0;
		}

		if(DBfetch(DBexecute("select * from users where alias=".zbx_dbstr($alias))))
		{
			error('User "'.$alias.'" already exists');
			return 0;
		}

		$userid = get_dbid("users","userid");

		$result =  DBexecute('insert into users (userid,name,surname,alias,passwd,url,autologout,lang,refresh,type)'.
			' values ('.$userid.','.zbx_dbstr($name).','.zbx_dbstr($surname).','.zbx_dbstr($alias).','.
			zbx_dbstr(md5($passwd)).','.zbx_dbstr($url).','.$autologout.','.zbx_dbstr($lang).','.$refresh.','.$user_type.')');

		if($result)
		{
			DBexecute('delete from users_groups where userid='.$userid);
			foreach($user_groups as $groupid => $grou_pname)
			{
				$users_groups_id = get_dbid("users_groups","id");
				$result = DBexecute('insert into users_groups (id,usrgrpid,userid)'.
					'values('.$users_groups_id.','.$groupid.','.$userid.')');

				if($result == false) break;
			}
		}

		return $result;
	}

	# Update User definition

	function	update_user($userid,$name,$surname,$alias,$passwd, $url,$autologout,$lang,$refresh,$user_type,$user_groups)
	{
		if(!check_right("User","U",$userid))
		{
			error("Insufficient permissions");
			return 0;
		}

		if(DBfetch(DBexecute("select * from users where alias=".zbx_dbstr($alias)." and userid<>$userid")))
		{
			error("User '$alias' already exists");
			return 0;
		}

		$result = DBexecute("update users set name=".zbx_dbstr($name).",surname=".zbx_dbstr($surname).","."alias=".zbx_dbstr($alias).
			(isset($passwd) ? (',passwd='.zbx_dbstr(md5($passwd))) : '').
			",url=".zbx_dbstr($url).","."autologout=$autologout,lang=".zbx_dbstr($lang).",refresh=$refresh,".
			"type=$user_type where userid=$userid");

		if($result)
		{
			DBexecute('delete from users_groups where userid='.$userid);
			foreach($user_groups as $groupid => $grou_pname)
			{
				$users_groups_id = get_dbid("users_groups","id");
				$result = DBexecute('insert into users_groups (id,usrgrpid,userid)'.
					'values('.$users_groups_id.','.$groupid.','.$userid.')');

				if($result == false) break;
			}
		}

		return $result;
	}

	# Delete User definition

	function	delete_user($userid)
	{

		if(DBfetch(DBselect('select * from users where userid='.$userid.' and alias=\'guest\'')))
		{
			error("Cannot delete user 'guest'");
			return	false;
		}

		while($row=DBfetch(DBexecute('select actionid from actions where userid='.$userid)))
		{
			$result = delete_action($row["actionid"]);
			if(!$result) return $result;
		}

		$result = DBexecute('delete from media where userid='.$userid);
		if(!$result) return $result;

		$result = DBexecute('delete from profiles where userid='.$userid);
		if(!$result) return $result;

		$result = DBexecute('delete from users_groups where userid='.$userid);
		if(!$result) return $result;

		$result = DBexecute('delete from users where userid='.$userid);

		return $result;
	}


	# Update User Profile

	function	update_user_profile($userid,$passwd, $url,$autologout,$lang,$refresh)
	{
		global $USER_DETAILS;

		if($userid!=$USER_DETAILS["userid"])
		{
			error("Insufficient permissions");
			return 0;
		}

		return DBexecute("update users set url=".zbx_dbstr($url).",autologout=$autologout,lang=".zbx_dbstr($lang).
				(isset($passwd) ? (',passwd='.zbx_dbstr(md5($passwd))) : '').
				",refresh=$refresh where userid=$userid");
	}

	# Add permission

	function	add_permission($userid,$right,$permission,$id)
	{
		$sql="insert into rights (userid,name,permission,id) values ($userid,".zbx_dbstr($right).",".zbx_dbstr($permission).",$id)";
		return DBexecute($sql);
	}

	function	get_usergroup_by_groupid($groupid)
	{
		$result=DBselect("select * from usrgrp where usrgrpid=".$groupid);
		$row=DBfetch($result);
		if($row)
		{
			return $row;
		}
		error("No user groups with usrgrpid=[$groupid]");
		return  FALSE;
	}

	function	get_user_by_userid($userid)
	{
		$sql="select * from users where userid=$userid"; 
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row)
		{
			return	$row;
		}
		error("No user with itemid=[$userid]");
		return	false;
	}

	function	add_user_group($name,$users=array())
	{
		if(!check_right("Host","A",0))
		{
			error("Insufficient permissions");
			return 0;
		}
		
		if($name==""){
			error("Incorrect group name");
			return 0;
		}

		$sql="select * from usrgrp where name=".zbx_dbstr($name);
		$result=DBexecute($sql);
		if(DBfetch($result))
		{
			error("Group '$name' already exists");
			return 0;
		}

		$usrgrpid=get_dbid("usrgrp","usrgrpid");

		$sql="insert into usrgrp (usrgrpid,name) values ($usrgrpid,".zbx_dbstr($name).")";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		
		update_user_groups($usrgrpid,$users);

		return $result;
	}

	function	update_user_group($usrgrpid,$name,$users=array())
	{
		if(!check_right("Host","U",0))
		{
			error("Insufficient permissions");
			return 0;
		}
		
		if($name==""){
			error("Incorrect group name");
			return 0;
		}

		$sql="select * from usrgrp where name=".zbx_dbstr($name)." and usrgrpid<>$usrgrpid";
		$result=DBexecute($sql);
		if(DBfetch($result))
		{
			error("Group '$name' already exists");
			return 0;
		}

		$sql="update usrgrp set name=".zbx_dbstr($name)." where usrgrpid=$usrgrpid";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		
		update_user_groups($usrgrpid,$users);

		return $result;
	}

	function	delete_user_group($usrgrpid)
	{
		$sql="delete from users_groups where usrgrpid=$usrgrpid";
		DBexecute($sql);
		$sql="delete from usrgrp where usrgrpid=$usrgrpid";
		return DBexecute($sql);
	}

	function	update_user_groups($usrgrpid,$users=array())
	{
		$count=count($users);

		$sql="delete from users_groups where usrgrpid=$usrgrpid";
		DBexecute($sql);

		for($i=0;$i<$count;$i++)
		{
			$sql="insert into users_groups (usrgrpid,userid) values ($usrgrpid,".$users[$i].")";
			DBexecute($sql);
		}
	}
?>

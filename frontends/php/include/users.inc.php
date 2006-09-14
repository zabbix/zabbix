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
	# Add User definition

	function	add_user($name,$surname,$alias,$passwd,$url,$autologout,$lang,$refresh)
	{
		if(!check_right("User","A",0))
		{
			error("Insufficient permissions");
			return 0;
		}

		if($alias==""){
			error("Incorrect Alias name");
			return 0;
		}

		$sql="select * from users where alias=".zbx_dbstr($alias);
		$result=DBexecute($sql);
		if(DBfetch($result))
		{
			error("User '$alias' already exists");
			return 0;
		}
		
		$passwd=md5($passwd);
		$userid = get_dbid("users","userid");
		$sql="insert into users (userid,name,surname,alias,passwd,url,autologout,lang,refresh) values ($userid,".zbx_dbstr($name).",".zbx_dbstr($surname).",".zbx_dbstr($alias).",".zbx_dbstr($passwd).",".zbx_dbstr($url).",$autologout,".zbx_dbstr($lang).",$refresh)";
		DBexecute($sql);
		return $userid;
	}

	# Update User definition

	function	update_user($userid,$name,$surname,$alias,$passwd, $url,$autologout,$lang,$refresh)
	{
		if(!check_right("User","U",$userid))
		{
			error("Insufficient permissions");
			return 0;
		}

		if($alias==""){
			error("incorrect alias name");
			return 0;
		}

		$sql="select * from users where alias=".zbx_dbstr($alias)." and userid<>$userid";
		$result=DBexecute($sql);
		if(DBfetch($result))
		{
			error("User '$alias' already exists");
			return 0;
		}
		
		if($passwd=="")
		{
			$sql="update users set name=".zbx_dbstr($name).",surname=".zbx_dbstr($surname).",alias=".zbx_dbstr($alias).",url=".zbx_dbstr($url).",autologout=$autologout,lang=".zbx_dbstr($lang).",refresh=$refresh where userid=$userid";
		}
		else
		{
			$passwd=md5($passwd);
			$sql="update users set name=".zbx_dbstr($name).",surname=".zbx_dbstr($surname).",alias=".zbx_dbstr($alias).",passwd=".zbx_dbstr($passwd).",url=".zbx_dbstr($url).",autologout=$autologout,lang=".zbx_dbstr($lang).",refresh=$refresh where userid=$userid";
		}
		return DBexecute($sql);
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

		if($passwd=="")
		{
			$sql="update users set url=".zbx_dbstr($url).",autologout=$autologout,lang=".zbx_dbstr($lang).",refresh=$refresh where userid=$userid";
		}
		else
		{
			$passwd=md5($passwd);
			$sql="update users set passwd=".zbx_dbstr($passwd).",url=".zbx_dbstr($url).",autologout=$autologout,lang=".zbx_dbstr($lang).",refresh=$refresh where userid=$userid";
		}
		return DBexecute($sql);
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

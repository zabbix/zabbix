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
require_once "include/config.inc.php";

/*
**  This include file contains all of the non-standard functions required
**  by the bulk loader system.
**/

	//  This function takes a host group name and populates the database.
	//  Returns: 0 if unable to create, otherwise it returns the groupID
	function        create_Host_Group($group)
	{
		$result=DBselect("select distinct(groupid) from groups where name=".zbx_dbstr($group));
		$row=DBfetch($result);
		if($row)
		{
			return $row["groupid"];
		}
		$groupid = get_dbid("groups","groupid");

		$result=DBexecute("insert into groups (groupid,name) values ($groupid,".zbx_dbstr($group).")");
		if($result)
		{
			return $groupid;
		}
		else
		{
			return 0;
		}
	}

	//  This function takes a user group name and populates the database.
	//  Returns: 0 if unable to create, otherwise it returns the groupID
	function        create_User_Group($group)
	{
		$result=DBselect("select distinct(usrgrpid) from usrgrp where name='$group'");
		$row=DBfetch($result);
		if($row)
		{
			return $row["usrgrpid"];
		}

		$usrgrpid=get_dbid("usrgrp","usrgrpid");
		$result=DBexecute("insert into usrgrp (usrgrpid,name) values ($usrgrpid,'$group')");
		if($result)
		{
			return $usrgrpid;
		}
		else
		{
			return 0;
		}
	}

	//  This function adds user to a user group based on ID numbers.
	//  Returns: 0 on failure, otherwise it returns 1
	function	add_User_To_Group($groupid=0,$userid=0)
	{
		if($groupid==0||$userid==0)
		{
			return 0;
		}
		$id=get_dbid("users_groups","id");
		$result=DBexecute("insert into users_groups (id,usrgrpid,userid) values ($id,$groupid,$userid)");
		if($result)
		{
			return $id;
		}
		return 0;	
	}

?>

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

	function	add_user($name,$surname,$alias,$passwd,$url)
	{
		if(!check_right("User","A",0))
		{
			error("Insufficient permissions");
			return 0;
		}
		

		$passwd=md5($passwd);
		$sql="insert into users (name,surname,alias,passwd,url) values ('$name','$surname','$alias','$passwd','$url')";
		return DBexecute($sql);
	}

	# Update User definition

	function	update_user($userid,$name,$surname,$alias,$passwd, $url)
	{
		if(!check_right("User","U",$userid))
		{
			error("Insufficient permissions");
			return 0;
		}

		if($passwd=="")
		{
			$sql="update users set name='$name',surname='$surname',alias='$alias',url='$url' where userid=$userid";
		}
		else
		{
			$passwd=md5($passwd);
			$sql="update users set name='$name',surname='$surname',alias='$alias',passwd='$passwd',url='$url' where userid=$userid";
		}
		return DBexecute($sql);
	}

	# Add permission

	function	add_permission($userid,$right,$permission,$id)
	{
		$sql="insert into rights (userid,name,permission,id) values ($userid,'$right','$permission',$id)";
		return DBexecute($sql);
	}

	function	get_user_by_userid($userid)
	{
		$sql="select * from users where userid=$userid"; 
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			return	DBfetch($result);	
		}
		else
		{
			error("No user with itemid=[$userid]");
		}
		return	$result;
	}
?>

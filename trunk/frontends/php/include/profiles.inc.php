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
	# Add Host Profile

	function	add_host_profile(
		$hostid,$devicetype,$name,$os,$serialno,$tag,$macaddress,
		$hardware,$software,$contact,$location,$notes)
	{
		$result=DBselect("select * from hosts_profiles where hostid=$hostid");
		if(DBfetch($result))
		{
			error("Host profile already exists");
			return 0;
		}

		$result=DBexecute("insert into hosts_profiles".
			" (hostid,devicetype,name,os,serialno,tag,macaddress,hardware,software,contact,".
			"location,notes) values ($hostid,".zbx_dbstr($devicetype).",".zbx_dbstr($name).",".
			zbx_dbstr($os).",".zbx_dbstr($serialno).",".zbx_dbstr($tag).",".zbx_dbstr($macaddress).
			",".zbx_dbstr($hardware).",".zbx_dbstr($software).",".zbx_dbstr($contact).",".
			zbx_dbstr($location).",".zbx_dbstr($notes).")");
		
		return	$result;
	}

	# Delete Host Profile

	function	delete_host_profile($hostid)
	{
		$result=DBexecute("delete from hosts_profiles where hostid=$hostid");

		return $result;
	}
?>

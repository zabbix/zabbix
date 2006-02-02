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

	function	add_host_profile($hostid,$devicetype,$name,$os,$serialno,$tag,$macaddress,$hardware,$software,$contact,$location,$notes)
	{
		// If user has update permission then ok
		if(!check_right("Host","U",0))
		{
			error("Insufficient permissions");
			return 0;
		}

		$sql="select * from hosts_profiles where hostid=$hostid";
		$result=DBexecute($sql);
		if(DBnum_rows($result)>0)
		{
			error("Host profile already exists");
			return 0;
		}

		$sql="insert into hosts_profiles (hostid,devicetype,name,os,serialno,tag,macaddress,hardware,software,contact,location,notes) values ($hostid,'".zbx_ads($devicetype)."','".zbx_ads($name)."','".zbx_ads($os)."','".zbx_ads($serialno)."','".zbx_ads($tag)."','".zbx_ads($macaddress)."','".zbx_ads($hardware)."','".zbx_ads($software)."','".zbx_ads($contact)."','".zbx_ads($location)."','".zbx_ads($notes)."')";
		$result=DBexecute($sql);
		
		return	$result;
	}

	# Update Host Profile

	function	update_host_profile($hostid,$devicetype,$name,$os,$serialno,$tag,$macaddress,$hardware,$software,$contact,$location,$notes)
	{
		// If user has update permission then ok
		if(!check_right("Host","U",0))
		{
			error("Insufficient permissions");
			return 0;
		}

		$sql="select * from hosts_profiles where hostid=$hostid";
		$result=DBexecute($sql);
		if(DBnum_rows($result)==0)
		{
			error("Host profile does not exist");
			return 0;
		}

		$sql="update hosts_profiles set devicetype='".zbx_ads($devicetype)."',name='".zbx_ads($name)."',os='".zbx_ads($os)."',serialno='".zbx_ads($serialno)."',tag='".zbx_ads($tag)."',macaddress='".zbx_ads($macaddress)."',hardware='".zbx_ads($hardware)."',software='".zbx_ads($software)."',contact='".zbx_ads($contact)."',location='".zbx_ads($location)."',notes='".zbx_ads($notes)."' where hostid=$hostid";
		$result=DBexecute($sql);
		
		return	$result;
	}

	# Delete Host Profile

	function	delete_host_profile($hostid)
	{
		if(!check_right("Host","U",0))
		{
			error("Insufficient permissions");
			return 0;
		}
		$sql="delete from hosts_profiles where hostid=$hostid";
		$result=DBexecute($sql);

		return $result;
	}
?>

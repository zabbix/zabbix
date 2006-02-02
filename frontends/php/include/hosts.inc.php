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
	# Add Host definition

	function	add_host($host,$port,$status,$useip,$ip,$host_templateid,$newgroup,$groups)
	{
		if(!check_right("Host","A",0))
		{
			error("Insufficient permissions");
			return 0;
		}

 		if (!eregi('^([0-9a-zA-Z\_\.-]+)$', $host, $arr)) 
		{
			error("Hostname should contain 0-9a-zA-Z_.- characters only");
			return 0;
		}

		$sql="select * from hosts where host='".zbx_ads($host)."'";
		$result=DBexecute($sql);
		if(DBnum_rows($result)>0)
		{
			error("Host '$host' already exists");
			return 0;
		}

		if($useip=="on" || $useip=="yes" || $useip==1)
		{
			$useip=1;
		}
		else
		{
			$useip=0;
		}

		$sql="insert into hosts (host,port,status,useip,ip,disable_until,available) values ('".zbx_ads($host)."',$port,$status,$useip,'".zbx_ads($ip)."',0,".HOST_AVAILABLE_UNKNOWN.")";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		
		$hostid=DBinsert_id($result,"hosts","hostid");

		if($host_templateid != 0)
		{
			add_templates_to_host($hostid,$host_templateid);
			sync_host_with_templates($hostid);
		}
		if($groups != "")
		{
			update_host_groups($hostid,$groups);
		}
		if($newgroup != "")
		{
			add_group_to_host($hostid,$newgroup);
		}

		update_profile("HOST_PORT",$port);
		
		return	$result;
	}

	function	update_host($hostid,$host,$port,$status,$useip,$ip,$newgroup,$groups)
	{
		if(!check_right("Host","U",$hostid))
		{
			error("Insufficient permissions");
			return 0;
		}

 		if (!eregi('^([0-9a-zA-Z\_\.-]+)$', $host, $arr)) 
		{
			error("Hostname should contain 0-9a-zA-Z_.- characters only");
			return 0;
		}

		$sql="select * from hosts where host='".zbx_ads($host)."' and hostid<>$hostid";
		$result=DBexecute($sql);
		if(DBnum_rows($result)>0)
		{
			error("Host '$host' already exists");
			return 0;
		}


		if($useip=="on" || $useip=="yes" || $useip==1)
		{
			$useip=1;
		}
		else
		{
			$useip=0;
		}

		$sql="update hosts set host='".zbx_ads($host)."',port=$port,useip=$useip,ip='".zbx_ads($ip)."' where hostid=$hostid";
		$result=DBexecute($sql);


		update_host_status($hostid, $status);
		update_host_groups($hostid,$groups);
		if($newgroup != "")
		{
			add_group_to_host($hostid,$newgroup);
		}
		return	$result;
	}

	# Add templates linked to template host to the host

	function	add_templates_to_host($hostid,$host_templateid)
	{
		$sql="select * from hosts_templates where hostid=$host_templateid";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			add_template_linkage($hostid,$row["templateid"],$row["items"],$row["triggers"],
						$row["graphs"]);
		}
	}

	function	delete_groups_by_hostid($hostid)
	{
		$sql="select groupid from hosts_groups where hostid=$hostid";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$sql="delete from hosts_groups where hostid=$hostid and groupid=".$row["groupid"];
			DBexecute($sql);
			$sql="select count(*) as count from hosts_groups where groupid=".$row["groupid"];
			$result2=DBselect($sql);
			$row2=DBfetch($result2);
			if($row2["count"]==0)
			{
				$sql="delete from groups where groupid=".$row["groupid"];
				DBexecute($sql);
			}
		}
	}

	# Delete Host

	function	delete_host($hostid)
	{
		global $DB_TYPE;

		$ret = FALSE;

		for($i=0;$i<100;$i++)
		{
			if($DB_TYPE=="MYSQL")
			{
				$sql="update hosts set status=".HOST_STATUS_DELETED.",host=concat(host,\" [DEL$i]\") where hostid=$hostid";
			}
			else
			{
				$sql="update hosts set status=".HOST_STATUS_DELETED.",host=host||' [DEL$i]' where hostid=$hostid";
			}
			if($ret = DBexecute($sql,1))	break;
		}
		if($ret){
			delete_host_profile($hostid);
		}
		return	$ret;
	}

	function	delete_host_group($groupid)
	{
		$sql="delete from hosts_groups where groupid=$groupid";
		DBexecute($sql);
		$sql="delete from groups where groupid=$groupid";
		return DBexecute($sql);
	}

	function	get_group_by_groupid($groupid)
	{
		$result=DBselect("select * from groups where groupid=".$groupid);
		if(DBnum_rows($result) == 1)
		{
			return DBfetch($result);
		}
		error("No groups with groupid=[$groupid]");
		return  FALSE;
	}

	function	get_host_by_itemid($itemid)
	{
		$sql="select h.* from hosts h, items i where i.hostid=h.hostid and i.itemid=$itemid";
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			return DBfetch($result);
		}
		error("No host with itemid=[$itemid]");
		return	FALSE;
	}

	function	get_host_by_hostid($hostid)
	{
		$sql="select * from hosts where hostid=$hostid";
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			return DBfetch($result);
		}
		error("No host with hostid=[$hostid]");
		return	FALSE;
	}

	# Update Host status

	function	update_host_status($hostid,$status)
	{
                if(!check_right("Host","U",0))
                {
                        error("Insufficient permissions");
                        return 0;
                }

		$sql="select status,host from hosts where hostid=$hostid";
		$result=DBselect($sql);
		$row=DBfetch($result);
		$old_status=$row["status"];
		if($status != $old_status)
		{
			update_trigger_value_to_unknown_by_hostid($hostid);
			$sql="update hosts set status=$status where hostid=$hostid and status!=".HOST_STATUS_DELETED;
			info("Updated status of host ".$row["host"]);
			return	DBexecute($sql);
		}
		else
		{
			return 1;
		}
	}
?>

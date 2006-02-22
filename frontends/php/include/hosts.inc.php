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

/* HOST GROUP functions */
	function	add_host_to_group($hostid, $groupid)
	{
		if(!is_numeric($hostid) || !is_numeric($groupid)){
			error("incorrect parameters for 'add_host_to_group'");
			return FALSE;
		}
		return DBexecute("insert into hosts_groups (hostid,groupid) values ($hostid,$groupid)");
	}

	function	db_save_group($name,$groupid=NULL)
	{
		if(!is_string($name)){
			error("incorrect parameters for 'db_save_group'");
			return FALSE;
		}
	
		if($groupid==NULL)
			$result = DBexecute("select * from groups where name=".zbx_dbstr($name));
		else
			$result = DBexecute("select * from groups where name=".zbx_dbstr($name).
				" and groupid<>$groupid");
		
		if(DBnum_rows($result)>0)
		{
			error("Group '$name' already exists");
			return FALSE;
		}
		if($groupid==NULL)
			return DBexecute("insert into groups (name) values (".zbx_dbstr($name).")");
		else
			return DBexecute("update groups set name=".zbx_dbstr($name)." where groupid=$groupid");
	}

	function	add_group_to_host($hostid,$newgroup="")
	{
		if($newgroup == "" || $newgroup == NULL)
			 return TRUE;

		$result = db_save_group($newgroup);
		if(!$result)
			return	$result;
		
		$groupid = DBinsert_id($result,"groupd","groupid");

		return add_host_to_group($hostid, $groupid);
	}

	function	update_host_groups_by_groupid($groupid,$hosts=array())
	{
		DBexecute("delete from hosts_groups where groupid=$groupid");

		foreach($hosts as $hostid)
		{
			add_host_to_group($hostid, $groupid);
		}
	}

	function	update_host_groups($hostid,$groups=array())
	{
		DBexecute("delete from hosts_groups where hostid=$hostid");

		foreach($groups as $groupid)
		{
			add_host_to_group($hostid, $groupid);
		}
	}

	function	add_host_group($name,$hosts)
	{
//		if(!check_right("Host","A",0))
//		{
//			error("Insufficient permissions");
//			return FLASE;
//		}

		$result = db_save_group($name);
		if(!$result)
			return	$result;
		
		$groupid = DBinsert_id($result,"groups","groupid");

		update_host_groups_by_groupid($groupid,$hosts);

		return $groupid;
	}

	function	update_host_group($groupid,$name,$hosts)
	{
//		if(!check_right("Host","U",0))
//		{
//			error("Insufficient permissions");
//			return 0;
//		}


		$result = db_save_group($name,$groupid);
		if(!$result)
			return	$result;
		
		update_host_groups_by_groupid($groupid,$hosts);

		return $result;
	}

/* HOST finction */
	function 	check_circle_host_link($hostid, $templateid)
	{
		if($templateid <= 0)		return FALSE;
		if($hostid == $templateid)	return TRUE;
		$template = get_host_by_hostid($templateid);
		if($template["templateid"] > 0)
			return check_circle_host_link($hostid, $template["templateid"]);

		return FALSE;
	}

	function	db_save_host($host,$port,$status,$useip,$ip,$templateid,$hostid=NULL)
	{
 		if (!eregi('^([0-9a-zA-Z\_\.-]+)$', $host)) 
		{
			error("Hostname should contain 0-9a-zA-Z_.- characters only");
			return FALSE;
		}

		if($hostid==NULL)
			$result=DBexecute("select * from hosts where host=".zbx_dbstr($host));
		else
			$result=DBexecute("select * from hosts where host=".zbx_dbstr($host).
				" and hostid<>$hostid");

		if(DBnum_rows($result)>0)
		{
			error("Host '$host' already exists");
			return FALSE;
		}

		if($useip=="on" || $useip=="yes" || $useip==1)		$useip=1;
		else							$useip=0;

		if($hostid==NULL)
		{
			$result = DBexecute("insert into hosts".
				" (host,port,status,useip,ip,disable_until,available,templateid)".
				" values (".zbx_dbstr($host).",$port,$status,$useip,".zbx_dbstr($ip).",0,"
				.HOST_AVAILABLE_UNKNOWN.",$templateid)");
		}
		else
		{
			if(check_circle_host_link($hostid, $templateid))
			{
				error("Circle link can't be created");
				return FALSE;
			}

			$result = DBexecute("update hosts set host=".zbx_dbstr($host).",".
				"port=$port,useip=$useip,ip=".zbx_dbstr($ip).",templateid=$templateid".
				" where hostid=$hostid");

			update_host_status($hostid, $status);
		}
		return $result;
	}

	function	add_host($host,$port,$status,$useip,$ip,$templateid,$newgroup,$groups)
	{
		if(!check_right("Host","A",0))
		{
			error("Insufficient permissions");
			return FALSE;
		}

		$result = db_save_host($host,$port,$status,$useip,$ip,$templateid);
		if(!$result)
			return $result;
	
		$hostid = DBinsert_id($result,"hosts","hostid");

		update_host_groups($hostid,$groups);

		add_group_to_host($hostid,$newgroup);

		sync_host_with_templates($hostid);

		update_profile("HOST_PORT",$port);
		
		return	$hostid;
	}

	function	update_host($hostid,$host,$port,$status,$useip,$ip,$templateid,$newgroup,$groups)
	{
		if(!check_right("Host","U",$hostid))
		{
			error("Insufficient permissions");
			return FALSE;
		}

		$old_host = get_host_by_hostid($hostid);

		$result = db_save_host($host,$port,$status,$useip,$ip,$templateid,$hostid);
		if(!$result)
			return $result;

		update_host_groups($hostid, $groups);

		add_group_to_host($hostid,$newgroup);

		if($old_host["templateid"] != $templateid)
			sync_host_with_templates($hostid);

		return	$result;
	}

# Sync host with linked template
	function	sync_host_with_templates($hostid)
	{
		$host = get_host_by_hostid($hostid);
		delete_template_graphs_by_hostid($hostid);
		delete_template_triggers_by_hostid($hostid);
		delete_template_items_by_hostid($hostid);
		
		if($host["templateid"] > 0)
		{
// start host syncing
			sync_items_with_template($hostid);
			sync_triggers_with_template($hostid);
			sync_graphs_with_templates($hostid);
// end host syncing
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

	// delete items -> triggers -> graphs
		$db_items = get_items_by_hostid($hostid);
		while($db_item = DBfetch($db_items))
		{
			delete_item($db_item["itemid"]);
		}

	// delete host from maps
		delete_sysmaps_host_by_hostid($hostid);
		
	// delete host from group
		DBexecute("delete from hosts_groups where hostid=$hostid");

	// unlink child hosts
		$db_childs = get_hosts_by_templateid($hostid);
		while($db_child = DBfetch($db_childs))
		{
			DBexecute("update hosts set templateid=0 where hostid=".$db_child["hostid"]);
			sync_host_with_templates($hostid);
		}

	// delete host profile
		delete_host_profile($hostid);

	// delete host
		return DBexecute("delete from hosts where hostid=$hostid");
	}

	function	delete_host_group($groupid)
	{
		$sql="delete from hosts_groups where groupid=$groupid";
		DBexecute($sql);
		$sql="delete from groups where groupid=$groupid";
		return DBexecute($sql);
	}

	function	get_hostgroup_by_groupid($groupid)
	{
		$result=DBselect("select * from groups where groupid=".$groupid);
		if(DBnum_rows($result) == 1)
		{
			return DBfetch($result);
		}
		error("No host groups with groupid=[$groupid]");
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

	function	get_hosts_by_templateid($templateid)
	{
		return DBselect("select * from hosts where templateid=$templateid");
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
			info("Updated status of host ".$row["host"]);
			return	DBexecute("update hosts set status=$status".
				" where hostid=$hostid and status!=".HOST_STATUS_DELETED);
		}
		else
		{
			return 1;
		}
	}
	
	function	get_template_path($hostid)
	{
		$host = get_host_by_hostid($hostid);

		if ($host["templateid"]==0)
			return "/";

		$tmp_host = get_host_by_hostid($host["templateid"]);	
		return get_template_path($tmp_host["hostid"]).$tmp_host["host"]."/";
	}
?>

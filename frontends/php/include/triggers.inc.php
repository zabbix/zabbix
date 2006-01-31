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
	function	update_trigger_comments($triggerid,$comments)
	{
		if(!check_right("Trigger comment","U",$triggerid))
		{
			error("Insufficient permissions");
			return	0;
		}

		$sql="update triggers set comments='".zbx_ads($comments)."' where triggerid=".zbx_ads($triggerid);
		return	DBexecute($sql);
	}

	# Update Trigger status

	function	update_trigger_status($triggerid,$status)
	{
		if(!check_right_on_trigger("U",$triggerid))
		{
                        error("Insufficient permissions");
                        return 0;
		}
		add_alarm($triggerid,2);
		$sql="update triggers set status=$status where triggerid=$triggerid";
		return	DBexecute($sql);
	}

	# "Processor load on {HOSTNAME} is 5" to "Processor load on www.sf.net is 5"
	function	expand_trigger_description_simple($triggerid)
	{
		$sql="select distinct t.description,h.host from triggers t,functions f,items i,hosts h where t.triggerid=$triggerid and f.triggerid=t.triggerid and f.itemid=i.itemid and i.hostid=h.hostid";
//		echo $sql;
		$result=DBselect($sql);
		$row=DBfetch($result);

		if(DBnum_rows($result)>0)
		{
			$description = str_replace("{HOSTNAME}", $row["host"],$row["description"]);
		}
		else
		{
			$sql="select description from triggers where triggerid=$triggerid";
			$result=DBselect($sql);
			$row=DBfetch($result);
			$description = $row["description"];
		}

		return $description;
	}

	# "Processor load on %s is 5" to "Processor load on www.sf.net is 5"
	function	expand_trigger_description($triggerid)
	{
		$description=expand_trigger_description_simple($triggerid);
		$description=stripslashes(htmlspecialchars($description));

		return $description;
	}

	function	update_trigger_value_to_unknown_by_hostid($hostid)
	{
		$sql="select distinct t.triggerid from hosts h,items i,triggers t,functions f where f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and h.hostid=$hostid";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$sql="update triggers set value=2 where triggerid=".$row["triggerid"];
			DBexecute($sql);
		}
	}

	function	add_trigger_dependency($triggerid,$depid)
	{
		$result=insert_dependency($triggerid,$depid);;
		if(!$result)
		{
			return $result;
		}
		add_additional_dependencies($triggerid,$depid);
		return $result;
	}

	# Add Trigger definition

	function	add_trigger($expression,$description,$priority,$status,$comments,$url)
	{
//		if(!check_right("Trigger","A",0))
//		{
//			error("Insufficient permissions");
//			return	0;
//		}

		$sql="insert into triggers  (description,priority,status,comments,url,value,error) values ('".zbx_ads($description)."',".zbx_ads($priority).",".zbx_ads($status).",'".zbx_ads($comments)."','".zbx_ads($url)."',2,'Trigger just added. No status update so far.')";
#		echo $sql,"<br>";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
 
		$triggerid=DBinsert_id($result,"triggers","triggerid");
#		echo $triggerid,"<br>";
		add_alarm($triggerid,2);
 
		$expression=implode_exp($expression,$triggerid);
		$sql="update triggers set expression='".zbx_ads($expression)."' where triggerid=".zbx_ads($triggerid);
#		echo $sql,"<br>";
		DBexecute($sql);
		reset_items_nextcheck($triggerid);
		return $triggerid;
	}

	# Delete Trigger definition

	function	delete_trigger($triggerid)
	{
		$sql="select count(*) as cnt from trigger_depends where triggerid_down=$triggerid or triggerid_up=$triggerid";
		$result=DBexecute($sql);
		$row=DBfetch($result);
		if($row["cnt"]>0)
		{
			error("Delete dependencies first");
			return	FALSE;
		}

		$result=delete_function_by_triggerid($triggerid);
		if(!$result)
		{
			return	$result;
		}
		$result=delete_alarms_by_triggerid($triggerid);
		if(!$result)
		{
			return	$result;
		}
		$result=delete_services_by_triggerid($triggerid);
		if(!$result)
		{
			return	$result;
		}

		$sql="update sysmaps_links set triggerid=NULL where triggerid=$triggerid";
		DBexecute($sql);

		$sql="delete from triggers where triggerid=$triggerid";
		return	DBexecute($sql);
	}

	# Update Trigger definition

	function	update_trigger($triggerid,$expression,$description,$priority,$status,$comments,$url)
	{
		if(!check_right_on_trigger("U",$triggerid))
		{
                        error("Insufficient permissions");
                        return 0;
		}

		$result=delete_function_by_triggerid($triggerid);
		if(!$result)
		{
			return	$result;
		}

		$expression=implode_exp($expression,$triggerid);
		add_alarm($triggerid,2);
//		$sql="update triggers set expression='$expression',description='$description',priority=$priority,status=$status,comments='$comments',url='$url' where triggerid=$triggerid";
		reset_items_nextcheck($triggerid);
		$sql="update triggers set expression='$expression',description='$description',priority=$priority,status=$status,comments='$comments',url='$url',value=2 where triggerid=$triggerid";
		return	DBexecute($sql);
	}

	function	check_right_on_trigger($permission,$triggerid)
	{
                $sql="select distinct h.hostid from functions f,items i,hosts h
where h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=$triggerid";
                $result=DBselect($sql);
                $ok=0;
		while($row=DBfetch($result))
		{
			if(check_right("Host",$permission,$row["hostid"]))
			{
				$ok=1;
			}
		}
		return	$ok;
	}

	function	get_trigger_by_triggerid($triggerid)
	{
		$sql="select triggerid,expression,description,status,priority,lastchange,dep_level,comments,url,value from triggers where triggerid=$triggerid";
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			return	DBfetch($result);	
		}
		else
		{
			error("No trigger with triggerid=[$triggerid]");
		}
		return	$trigger;
	}

	function	delete_trigger_dependency($triggerid_down,$triggerid_up)
	{
		$sql="select triggerid_down,triggerid_up from trigger_depends where triggerid_up=$triggerid_up and triggerid_down=$triggerid_down";
		$result=DBexecute($sql);
		while($row=DBfetch($result))
		{
			$down=$row["triggerid_down"];
			$up=$row["triggerid_up"];
			$sql="update triggers set dep_level=dep_level-1 where triggerid=$up";
			DBexecute($sql);
		}

		$sql="delete from trigger_depends where triggerid_up=$triggerid_up and triggerid_down=$triggerid_down";
		DBexecute($sql);

		return	TRUE;
	}

	function	insert_dependency($triggerid_down,$triggerid_up)
	{
		$sql="insert into trigger_depends (triggerid_down,triggerid_up) values ($triggerid_down,$triggerid_up)";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		$sql="update triggers set dep_level=dep_level+1 where triggerid=$triggerid_up";
		$result=DBexecute($sql);
		return	$result;
	}

	// If 1 depends on 2, and 2 depends on 3, then add dependency 1->3
	function	add_additional_dependencies($triggerid_down,$triggerid_up)
	{
		$sql="select triggerid_down from trigger_depends where triggerid_up=$triggerid_down";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$triggerid=$row["triggerid_down"];
			insert_dependency($triggerid,$triggerid_up);
			add_additional_dependencies($triggerid,$triggerid_up);
		}
		$sql="select triggerid_up from trigger_depends where triggerid_down=$triggerid_up";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$triggerid=$row["triggerid_up"];
			insert_dependency($triggerid_down,$triggerid);
			add_additional_dependencies($triggerid_down,$triggerid);
		}
	}

	function	delete_function_by_triggerid($triggerid)
	{
		$sql="delete from functions where triggerid=$triggerid";
		return	DBexecute($sql);
	}

	function	delete_alarms_by_triggerid($triggerid)
	{
		$sql="delete from alarms where triggerid=$triggerid";
		return	DBexecute($sql);
	}

	function	delete_triggers_by_itemid($itemid)
	{
		$sql="select triggerid from functions where itemid=$itemid";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			if(!delete_trigger($row["triggerid"]))
			{
				return FALSE;
			}
		}
		$sql="delete from functions where itemid=$itemid";
		return	DBexecute($sql);
	}

	# Delete Service definitions by triggerid

	function	delete_services_by_triggerid($triggerid)
	{
		$sql="select serviceid from services where triggerid=$triggerid";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			delete_service($row["serviceid"]);
		}
		return	TRUE;
	}

	# Update triger from 
	function	update_trigger_from_linked_hosts($triggerid)
	{
		if($triggerid<=0)
		{
			return;
		}

		$trigger=get_trigger_by_triggerid($triggerid);

# get hostid by triggerid

		$sql="select distinct h.hostid from hosts h,functions f, items i where i.itemid=f.itemid and h.hostid=i.hostid and f.triggerid=$triggerid";
		$result=DBselect($sql);
		if(DBnum_rows($result)!=1){ return; }
		$row0=DBfetch($result);

#get linked hosts
		$sql="select hostid,templateid,triggers from hosts_templates where templateid=".$row0["hostid"];
		$result=DBselect($sql);
		// Loop: linked hosts
		while($row=DBfetch($result))
		{
			if($row["triggers"]&3 == 0)	continue;
#get triggers
			$sql="select distinct f.triggerid from functions f,items i,triggers t where t.description='".zbx_ads($trigger["description"])."' and t.triggerid=f.triggerid and i.itemid=f.itemid and i.hostid=".$row["hostid"];
			$result2=DBselect($sql);
			// Loop: triggers
			while($row2=DBfetch($result2))
			{
				delete_function_by_triggerid($row2["triggerid"]);
				
				$expression_old=$trigger["expression"];
#get functions
				$sql="select i.key_,f.parameter,f.function,f.functionid from functions f,items i where i.itemid=f.itemid and f.triggerid=".$trigger["triggerid"];
				$result2=DBselect($sql);
				// Loop: functions
				while($row3=DBfetch($result2))
				{

					$sql="select itemid from items where key_=\"".$row3["key_"]."\" and hostid=".$row["hostid"];
					$result3=DBselect($sql);
					if(DBnum_rows($result3)!=1)
					{
						$sql="delete from triggers where triggerid=".$row2["triggerid"];
						DBexecute($sql);
						$sql="delete from functions where triggerid=".$row2["triggerid"];
						DBexecute($sql);
						break;
					}
					$row4=DBfetch($result3);
	
					$item=get_item_by_itemid($row4["itemid"]);
	
					$sql="insert into functions (itemid,triggerid,function,parameter) values (".$item["itemid"].",".$row2["triggerid"].",'".$row3["function"]."','".$row3["parameter"]."')";
					$result5=DBexecute($sql);
					$functionid=DBinsert_id($result5,"functions","functionid");
	
					$sql="update triggers set expression='$expression_old' where triggerid=".$row2["triggerid"];
					DBexecute($sql);
					$expression=str_replace("{".$row3["functionid"]."}","{".$functionid."}",$expression_old);
					$expression_old=$expression;
					$sql="update triggers set expression='$expression' where triggerid=".$row2["triggerid"];
					DBexecute($sql);
				}

				$host=get_host_by_hostid($row["hostid"]);
				info("Updated trigger from linked host ".$host["host"]);
			}

		}
	}

	# Add item to hardlinked hosts

	function	add_trigger_to_linked_hosts($triggerid,$hostid=0)
	{
		if($triggerid<=0)
		{
			return;
		}

		$trigger=get_trigger_by_triggerid($triggerid);

		$sql="select distinct h.hostid from hosts h,functions f, items i where i.itemid=f.itemid and h.hostid=i.hostid and f.triggerid=$triggerid";
		$result=DBselect($sql);
		if(DBnum_rows($result)!=1)
		{
			return;
		}

		$row=DBfetch($result);

		if($hostid==0)
		{
			$sql="select hostid,templateid,triggers from hosts_templates where templateid=".$row["hostid"];
		}
		// Link to one host only
		else
		{
			$sql="select hostid,templateid,triggers from hosts_templates where hostid=$hostid and templateid=".$row["hostid"];
		}
		$result=DBselect($sql);
		// Loop: linked hosts
		while($row=DBfetch($result))
		{
			$expression_old=$trigger["expression"];

			if($row["triggers"]&1 == 0)	continue;

			$sql="insert into triggers  (description,priority,status,comments,url,value,expression) values ('".zbx_ads($trigger["description"])."',".$trigger["priority"].",".$trigger["status"].",'".zbx_ads($trigger["comments"])."','".zbx_ads($trigger["url"])."',2,'$expression_old')";
			$result4=DBexecute($sql);
			$triggerid_new=DBinsert_id($result4,"triggers","triggerid");


			$sql="select i.key_,f.parameter,f.function,f.functionid from functions f,items i where i.itemid=f.itemid and f.triggerid=$triggerid";
			$result2=DBselect($sql);
			// Loop: functions
			while($row2=DBfetch($result2))
			{
				$sql="select itemid from items where key_=\"".$row2["key_"]."\" and hostid=".$row["hostid"];
				$result3=DBselect($sql);
				if(DBnum_rows($result3)!=1)
				{
					$sql="delete from triggers where triggerid=$triggerid_new";
					DBexecute($sql);
					$sql="delete from functions where triggerid=$triggerid_new";
					DBexecute($sql);
					break;
				}
				$row3=DBfetch($result3);

				$item=get_item_by_itemid($row3["itemid"]);

				$sql="insert into functions (itemid,triggerid,function,parameter) values (".$item["itemid"].",$triggerid_new,'".$row2["function"]."','".$row2["parameter"]."')";
				$result5=DBexecute($sql);
				$functionid=DBinsert_id($result5,"functions","functionid");

				$sql="update triggers set expression='$expression_old' where triggerid=$triggerid_new";
				DBexecute($sql);
				$expression=str_replace("{".$row2["functionid"]."}","{".$functionid."}",$expression_old);
				$expression_old=$expression;
				$sql="update triggers set expression='$expression' where triggerid=$triggerid_new";
				DBexecute($sql);

				$host=get_host_by_hostid($row["hostid"]);
				info("Added trigger to linked host ".$host["host"]);
			}
		}
	}

	function	delete_trigger_from_templates($triggerid)
	{
		if($triggerid<=0)
		{
			return;
		}

		$trigger=get_trigger_by_triggerid($triggerid);

		$sql="select distinct h.hostid from hosts h,functions f, items i where i.itemid=f.itemid and h.hostid=i.hostid and f.triggerid=$triggerid";
		$result=DBselect($sql);
		if(DBnum_rows($result)!=1)
		{
			return;
		}

		$row=DBfetch($result);

		$hostid=$row["hostid"];

		$sql="select hostid,templateid,triggers from hosts_templates where templateid=$hostid";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			if($row["triggers"]&4 == 0)	continue;

			$sql="select distinct f.triggerid from functions f,items i,triggers t where t.description='".zbx_ads($trigger["description"])."' and t.triggerid=f.triggerid and i.itemid=f.itemid and i.hostid=".$row["hostid"];
			$result2=DBselect($sql);
			while($row2=DBfetch($result2))
			{
				delete_trigger($row2["triggerid"]);

				$host=get_host_by_hostid($row["hostid"]);
				info("Deleted trigger from linked host ".$host["host"]);
			}

		}
	}

	function	get_severity_description($severity)
	{
		$desc="Unknown";

		if($severity == 0)	$desc = S_NOT_CLASSIFIED;
		else if($severity == 1)	$desc = S_INFORMATION;
		else if($severity == 2)	$desc = S_WARNING;
		else if($severity == 3)	$desc = S_AVERAGE;
		else if($severity == 4)	$desc = S_HIGH;

		return $desc;
	}
?>

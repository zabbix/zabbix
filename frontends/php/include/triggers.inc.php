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

		$sql="update triggers set comments=".zbx_dbstr($comments)." where triggerid=$triggerid";
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

	function	add_trigger($expression,$description,$priority,$status,$comments,$url,$deps=array())
	{
//		if(!check_right("Trigger","A",0))
//		{
//			error("Insufficient permissions");
//			return	0;
//		}

		$sql="insert into triggers  (description,priority,status,comments,url,value,error)".
			" values (".zbx_dbstr($description).",$priority,$status,".zbx_dbstr($comments).",".
			"".zbx_dbstr($url).",2,'Trigger just added. No status update so far.')";
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
		$sql="update triggers set expression=".zbx_dbstr($expression)." where triggerid=$triggerid";
#		echo $sql,"<br>";
		DBexecute($sql);
		reset_items_nextcheck($triggerid);

		foreach($deps as $val)
		{
			$result=add_trigger_dependency($triggerid, $val);
		}


		return $triggerid;
	}

	function	delete_dependencies_by_triggerid($triggerid)
	{
		return DBexecute("delete from trigger_depends".
					" where triggerid_down=".$triggerid);
	}

	# Delete Trigger definition

	function	delete_trigger($triggerid)
	{
		$result=DBexecute("select count(*) as cnt from trigger_depends where triggerid_up=$triggerid");
		$row=DBfetch($result);
		if($row["cnt"]>0)
		{
			error("Delete dependencies first");
			return	FALSE;
		}
		$result = delete_dependencies_by_triggerid($triggerid);
		if(!$result)
                {
                        return  $result;
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

	function	update_trigger($triggerid,$expression,$description,$priority,$status,
		$comments,$url,$deps=array())
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
		reset_items_nextcheck($triggerid);
		$sql="update triggers set expression=".zbx_dbstr($expression).",".
			"description=".zbx_dbstr($description).",priority=$priority,status=$status,".
			"comments=".zbx_dbstr($comments).",url=".zbx_dbstr($url).",value=2".
			" where triggerid=$triggerid";

		$result = DBexecute($sql);

		delete_dependencies_by_triggerid($triggerid);
		foreach($deps as $val)
		{
			$result=add_trigger_dependency($triggerid, $val);
		}
		return $result;
	}

	function	check_right_on_trigger($permission,$triggerid)
	{
                $sql="select distinct h.hostid from functions f,items i,hosts h where h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=$triggerid";
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

	function	cmp_triggers($triggerid1, $triggerid2)
	{
		$trigger1=get_trigger_by_triggerid($triggerid1);
		$trigger2=get_trigger_by_triggerid($triggerid2);
		if($trigger2["description"] != $trigger2["description"])	return 1;

		$deps1 = DBselect("select * from trigger_depends where triggerid_down=$triggerid1");
		$deps2 = DBselect("select * from trigger_depends where triggerid_down=$triggerid2");
		if(DBnum_rows($deps1) != DBnum_rows($deps2))	return 1;

		while($dep1=DBfetch($deps1))
		{
			$ok=0;
			while($dep2=DBfetch($deps2)){
				if($dep1["triggerid_up"] == $dep2["triggerid_up"]){
					$ok=1;
					break;
				}
			}
			if($ok==0)	return 1;
		}
		return 0;
	}

	# Update triger from templates
	function	update_trigger_from_linked_hosts($triggerid,$expression,$description,$priority,$status,
		$comments,$url,$deps=array())
	{
		if($triggerid<=0)
		{
			return;
		}

		$trigger=get_trigger_by_triggerid($triggerid);

# get hostid by triggerid

		$db_hosts=DBselect("select distinct h.hostid, h.host from hosts h,functions f, items i".
			" where i.itemid=f.itemid and h.hostid=i.hostid and f.triggerid=$triggerid");
		if(DBnum_rows($db_hosts)!=1){
			return;
		}
		$db_host=DBfetch($db_hosts);

#get linked hosts
		$result=DBselect("select hostid,templateid,triggers from hosts_templates".
			" where templateid=".$db_host["hostid"]);
		// Loop: linked hosts
		while($row=DBfetch($result))
		{
			if($row["triggers"]&3 == 0)	continue;
#get triggers
			$result2 = DBselect("select distinct f.triggerid from functions f,items i,triggers t".
				" where t.triggerid=f.triggerid and i.itemid=f.itemid".
				" and i.hostid=".$row["hostid"]);
			// Loop: triggers
			while($row2=DBfetch($result2))
			{
				if(cmp_triggers($row2["triggerid"],$triggerid)!=0) continue;

				$host = get_host_by_hostid($row["hostid"]);

				$expression_new = str_replace(
					$db_host["host"].":",
					$host["host"].":",
					$expression);

				if(update_trigger($row2["triggerid"],$expression_new,$description,$priority,
					$status,$comments,$url,$deps))
				{
					info("Updated trigger '".$trigger["description"]."'".
						" from linked host ".$host["host"]);
					break;
				}
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

		$trigger = get_trigger_by_triggerid($triggerid);

		$db_hosts = DBselect("select distinct h.hostid from hosts h,functions f, items i".
			" where i.itemid=f.itemid and h.hostid=i.hostid and f.triggerid=$triggerid");
		if(DBnum_rows($db_hosts)!=1)
		{
			/* trigger must use only one host */
			return;
		}

		$db_host = DBfetch($db_hosts);

		if($hostid==0)
		{
			$sql="select hostid,templateid,triggers from hosts_templates".
				" where templateid=".$db_host["hostid"];
		}
		else
		{
		// Link to one host only
			$sql="select hostid,templateid,triggers from hosts_templates".
				" where hostid=$hostid and templateid=".$db_host["hostid"];
		}
		$result=DBselect($sql);
		// Loop: linked hosts
		while($row=DBfetch($result))
		{
			$expression_old=$trigger["expression"];

			if($row["triggers"]&1 == 0)	continue;

			$result4=DBexecute("insert into triggers".
				" (description,priority,status,comments,url,value,expression)".
				" values (".zbx_dbstr($trigger["description"]).",".$trigger["priority"].","
				.$trigger["status"].",".zbx_dbstr($trigger["comments"]).",".
				zbx_dbstr($trigger["url"]).",2,".zbx_dbstr($expression_old).")");
			$triggerid_new=DBinsert_id($result4,"triggers","triggerid");

			$result2=DBselect("select i.key_,f.parameter,f.function,f.functionid".
				" from functions f,items i".
				" where i.itemid=f.itemid and f.triggerid=$triggerid");
			// Loop: functions
			while($row2=DBfetch($result2))
			{
				$result3=DBselect("select itemid from items".
					" where key_=".zbx_dbstr($row2["key_"]).
					" and hostid=".$row["hostid"]);
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

				$result5=DBexecute("insert into functions (itemid,triggerid,function,parameter)".
					" values (".$item["itemid"].",$triggerid_new,".
					zbx_dbstr($row2["function"]).",".zbx_dbstr($row2["parameter"]).")");
				$functionid=DBinsert_id($result5,"functions","functionid");

				DBexecute("update triggers set expression=".zbx_dbstr($expression_old).
					" where triggerid=$triggerid_new");

				$expression = str_replace(
					"{".$row2["functionid"]."}",
					"{".$functionid."}",
					$expression_old);
				$expression_old=$expression;

				DBexecute("update triggers set expression=".zbx_dbstr($expression).
					" where triggerid=$triggerid_new");
// copy dependences
				delete_dependencies_by_triggerid($triggerid_new);
				$db_deps = DBexecute("select * from trigger_depends where".
					" triggerid_down=".$triggerid);
				while($db_dep = DBfetch($db_deps))
				{
					add_trigger_dependency($triggerid_new, $db_dep["triggerid_up"]);
				}

				$host=get_host_by_hostid($row["hostid"]);
				info("Added trigger '".$trigger["description"]."' to linked host ".$host["host"]);
			}
		}
	}

	function	delete_trigger_from_templates($triggerid)
	{
		if($triggerid<=0)
		{
			// incorrect trigger id
			return;
		}

		$trigger = get_trigger_by_triggerid($triggerid);

		$db_hosts = DBselect("select distinct h.hostid from hosts h,functions f, items i".
			" where i.itemid=f.itemid and h.hostid=i.hostid and f.triggerid=$triggerid");
		if(DBnum_rows($db_hosts)!=1)
		{
			// no hosts with this trigger
			// OR trigger use more then one hosts !!!!!
			return;
		}
		$db_host = DBfetch($db_hosts);

		$db_child_hosts = DBselect("select hostid,templateid,triggers from hosts_templates".
			" where templateid=".$db_host["hostid"]);
		while($db_child_host = DBfetch($db_child_hosts))
		{
			if($db_child_host["triggers"]&4 == 0)	continue;

			$db_triggers = DBselect("select distinct f.triggerid from functions f,items i,triggers t".
				" where t.triggerid=f.triggerid and i.itemid=f.itemid".
				" and i.hostid=".$db_child_host["hostid"]);
			// Loop: triggers
			while($db_trigger = DBfetch($db_triggers))
			{
				if(cmp_triggers($db_trigger["triggerid"],$triggerid)!=0) continue;

				$host=get_host_by_hostid($db_child_host["hostid"]);

				if(!delete_trigger($db_trigger["triggerid"]))
					error("Can't delete trigger from linked host ".$host["host"]);
				else {
					info("Trigger '".$trigger["description"]."' was deleted".
						" from linked host ".$host["host"]);
					break;
				}		
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

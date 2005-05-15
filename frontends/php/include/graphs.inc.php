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
	# Add Graph

	function	add_graph($name,$width,$height,$yaxistype,$yaxismin,$yaxismax)
	{
		if(!check_right("Graph","A",0))
		{
			error("Insufficient permissions");
			return 0;
		}

		$sql="insert into graphs (name,width,height,yaxistype,yaxismin,yaxismax) values ('$name',$width,$height,$yaxistype,$yaxismin,$yaxismax)";
		$result=DBexecute($sql);
		return DBinsert_id($result,"graphs","graphid");
	}

	function	update_graph_item($gitemid,$itemid,$color,$drawtype,$sortorder)
	{
		$sql="update graphs_items set itemid=$itemid,color='$color',drawtype=$drawtype,sortorder=$sortorder where gitemid=$gitemid";
		return	DBexecute($sql);
	}

	function	add_item_to_graph($graphid,$itemid,$color,$drawtype,$sortorder)
	{
		$sql="insert into graphs_items (graphid,itemid,color,drawtype,sortorder) values ($graphid,$itemid,'$color',$drawtype,$sortorder)";
		$result=DBexecute($sql);
		return DBinsert_id($result,"graphs_items","gitemid");
	}

	# Update Graph

	function	update_graph($graphid,$name,$width,$height,$yaxistype,$yaxismin,$yaxismax)
	{
		if(!check_right("Graph","U",0))
		{
			error("Insufficient permissions");
			return 0;
		}

		$sql="update graphs set name='$name',width=$width,height=$height,yaxistype=$yaxistype,yaxismin=$yaxismin,yaxismax=$yaxismax where graphid=$graphid";
		return	DBexecute($sql);
	}

	function	delete_graphs_item($gitemid)
	{
		$sql="delete from graphs_items where gitemid=$gitemid";
		return	DBexecute($sql);
	}

	# Delete Graph

	function	delete_graph($graphid)
	{
		$sql="delete from graphs_items where graphid=$graphid";
		$result=DBexecute($sql);
		if(!$result)
		{
			return	$result;
		}
		$sql="delete from graphs where graphid=$graphid";
		return	DBexecute($sql);
	}

	function	get_graphitem_by_gitemid($gitemid)
	{
		$sql="select * from graphs_items where gitemid=$gitemid"; 
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			return	DBfetch($result);	
		}
		else
		{
			error("No graph item with gitemid=[$gitemid]");
		}
		return	$result;
	}

	function	get_graph_by_graphid($graphid)
	{
		$sql="select * from graphs where graphid=$graphid"; 
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			return	DBfetch($result);	
		}
		else
		{
			error("No graph with graphid=[$graphid]");
		}
		return	$result;
	}

	function		add_graph_item_to_templates($gitemid)
	{
		if($gitemid<=0)
		{
			return;
		}

		$graph_item=get_graphitem_by_gitemid($gitemid);
		$graph=get_graph_by_graphid($graph_item["graphid"]);
		$item=get_item_by_itemid($graph_item["itemid"]);

		$sql="select hostid,templateid,graphs from hosts_templates where templateid=".$item["hostid"];
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			if($row["graphs"]&1 == 0)	continue;

			$sql="select i.itemid from items i where i.key_='".$item["key_"]."' and i.hostid=".$row["hostid"];
			$result2=DBselect($sql);
			if(DBnum_rows($result2)==0)	continue;
			$row2=DBfetch($result2);
			$itemid=$row2["itemid"];

			$sql="select distinct g.graphid from graphs g,graphs_items gi,items i where i.itemid=gi.itemid and i.hostid=".$row["hostid"]." and g.graphid=gi.graphid and g.name='".addslashes($graph["name"])."'";
			$result2=DBselect($sql);
			$host=get_host_by_hostid($result["hostid"]);
			while($row2=DBfetch($result2))
			{
				add_item_to_graph($row2["graphid"],$itemid,$graph_item["color"],$graph_item["drawtype"],$graph_item["sortorder"]);
				info("Added graph element to graph ".$graph["name"]." of linked host ".$host["host"]);
			}
			if(DBnum_rows($result2)==0)
			{
				$graphid=add_graph($graph["name"],$graph["width"],$graph["height"],$graph["yaxistype"],$graph["yaxismin"],$graph["yaxismax"]);
				info("Added graph ".$graph["name"]." of linked host ".$host["host"]);
				add_item_to_graph($graphid,$itemid,$graph_item["color"],$graph_item["drawtype"],$graph_item["sortorder"]);
				info("Added graph element to graph ".$graph["name"]." of linked host ".$host["host"]);
			}
		}
	}

	function	delete_graph_item_from_templates($gitemid)
	{
		if($gitemid<=0)
		{
			return;
		}

		$graph_item=get_graphitem_by_gitemid($gitemid);
		$graph=get_graph_by_graphid($graph_item["graphid"]);
		$item=get_item_by_itemid($graph_item["itemid"]);

		$sql="select hostid,templateid,graphs from hosts_templates where templateid=".$item["hostid"];
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			if($row["graphs"]&4 == 0)	continue;

			$sql="select i.itemid from items i where i.key_='".$item["key_"]."' and i.hostid=".$row["hostid"];
			$result2=DBselect($sql);
			if(DBnum_rows($result2)==0)	continue;
			$row2=DBfetch($result2);
			$itemid=$row2["itemid"];

			$host=get_host_by_hostid($result["hostid"]);

			$sql="select distinct gi.gitemid,gi.graphid from graphs_items gi,items i where i.itemid=gi.itemid and i.hostid=".$row["hostid"]." and i.itemid=$itemid";
			$result2=DBselect($sql);
			while($row2=DBfetch($result2))
			{
				delete_graphs_item($row2["gitemid"]);
				info("Deleted graph element ".$item["key_"]." from linked host ".$host["host"]);
				$sql="select count(*) as count from graphs_items where graphid=".$row2["graphid"];
				$result3=DBselect($sql);
				$row3=DBfetch($result3);
				if($row3["count"]==0)
				{
					delete_graph($row2["graphid"]);
					info("Deleted graph from linked host ".$host["host"]);
				}
			}
		}
	}
?>

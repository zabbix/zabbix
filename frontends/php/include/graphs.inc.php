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
	function 	get_graphs_by_hostid($hostid)
	{
		$sql="select distinct g.* from graphs g, graphs_items gi, items i where g.graphid=gi.graphid and gi.itemid=i.itemid and i.hostid=$hostid";
		$graphs=DBselect($sql);
		return $graphs;
	}

	function 	get_hosts_by_graphid($graphid)
	{
		$sql="select distinct h.* from graphs_items gi, items i, hosts h where h.hostid=i.hostid and gi.itemid=i.itemid and gi.graphid=$graphid";
		$graphs=DBselect($sql);
		return $graphs;
	}

	function	get_graphitems_by_graphid($graphid)
	{
		$sql="select * from graphs_items where graphid=$graphid order by itemid,drawtype,sortorder,color,yaxisside"; 
		$result=DBselect($sql);
		return	$result;
	}

	function	get_graphitem_by_gitemid($gitemid)
	{
		$sql="select * from graphs_items where gitemid=$gitemid"; 
		$result=DBselect($sql);
		if(DBnum_rows($result) == 1)
		{
			return	DBfetch($result);	
		}
		error("No graph item with gitemid=[$gitemid]");
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
		error("No graph with graphid=[$graphid]");
		return	$result;
	}

	# Add Graph

	function	add_graph($name,$width,$height,$yaxistype,$yaxismin,$yaxismax)
	{
		if(!check_right("Graph","A",0))
		{
			error("Insufficient permissions");
			return 0;
		}

		$sql="insert into graphs (name,width,height,yaxistype,yaxismin,yaxismax) values (".zbx_dbstr($name).",$width,$height,$yaxistype,$yaxismin,$yaxismax)";
		$result=DBexecute($sql);
		return DBinsert_id($result,"graphs","graphid");
	}

	# Update Graph

	function	update_graph($graphid,$name,$width,$height,$yaxistype,$yaxismin,$yaxismax)
	{
		if(!check_right("Graph","U",0))
		{
			error("Insufficient permissions");
			return 0;
		}
		$sql="update graphs set name=".zbx_dbstr($name).",width=$width,height=$height,yaxistype=$yaxistype,yaxismin=$yaxismin,yaxismax=$yaxismax where graphid=$graphid";
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

	function 	update_graph_from_templates($graphid,$name,$width,$height,$yaxistype,$yaxismin,$yaxismax)
	{
		if($graphid<=0)			return;
		
		$hosts = get_hosts_by_graphid($graphid);
		if(!$hosts)			return;
		if(DBnum_rows($hosts)!=1)	return;
		$host=DBfetch($hosts);

		$sql="select hostid,templateid,graphs from hosts_templates where templateid=".$host["hostid"];
		$templates=DBselect($sql);
		while($template=DBfetch($templates))
		{
			if($template["graphs"]&4 == 0)	continue;

			$graphs=get_graphs_by_hostid($template["hostid"]);
			while($graph=DBfetch($graphs))
			{
				if(!cmp_graphs($graphid,$graph["graphid"]))		continue;
				if(!cmp_graph_by_item_key($graphid,$graph["graphid"]))	continue;
				update_graph($graph["graphid"],$name,$width,$height,$yaxistype,$yaxismin,$yaxismax);
				$template_host=get_host_by_hostid($template["hostid"]);
				info("Updated graph '".$graph["name"]."' from linked host '".$template_host["host"]."'");
			}
		}
	}

	function 	delete_graph_from_templates($graphid)
	{
		if($graphid<=0)			return;
		
		$hosts = get_hosts_by_graphid($graphid);
		if(!$hosts)			return;
		if(DBnum_rows($hosts)!=1)	return;
		$host=DBfetch($hosts);

		$sql="select hostid,templateid,graphs from hosts_templates where templateid=".$host["hostid"];
		$templates=DBselect($sql);
		while($template=DBfetch($templates))
		{
			if($template["graphs"]&4 == 0)	continue;

			$graphs=get_graphs_by_hostid($template["hostid"]);
			while($graph=DBfetch($graphs))
			{
				if(!cmp_graphs($graphid,$graph["graphid"]))		continue;
				if(!cmp_graph_by_item_key($graphid,$graph["graphid"]))	continue;
				delete_graph($graph["graphid"]);
				$template_host=get_host_by_hostid($template["hostid"]);
				info("Deleted graph '".$graph["name"]."' from linked host '".$template_host["host"]."'");
			}
		}
	}
	
	function	add_item_to_graph($graphid,$itemid,$color,$drawtype,$sortorder,$yaxisside)
	{
		$sql="insert into graphs_items (graphid,itemid,color,drawtype,sortorder,yaxisside) values ($graphid,$itemid,".zbx_dbstr($color).",$drawtype,$sortorder,$yaxisside)";
		$result=DBexecute($sql);
		return DBinsert_id($result,"graphs_items","gitemid");
	}
	
	function	update_graph_item($gitemid,$itemid,$color,$drawtype,$sortorder,$yaxisside)
	{
		$sql="update graphs_items set itemid=$itemid,color=".zbx_dbstr($color).",drawtype=$drawtype,sortorder=$sortorder,yaxisside=$yaxisside where gitemid=$gitemid";
		return	DBexecute($sql);
	}

	function	delete_graphs_item($gitemid)
	{
		$sql="delete from graphs_items where gitemid=$gitemid";
		return	DBexecute($sql);
	}

	function 	move_up_graph_item($gitemid)
	{
		if($gitemid<=0)		return;
		$sql="update graphs_items set sortorder=sortorder-1 where sortorder>0 and gitemid=$gitemid";
		return DBexecute($sql);

	}
	
	function 	move_down_graph_item($gitemid)
	{
		if($gitemid<=0)		return;
		$sql="update graphs_items set sortorder=sortorder+1 where sortorder<100 and gitemid=$gitemid";
		return DBexecute($sql);

	}

	function	copy_graphitems_for_host($src_graphid,$dist_graphid,$hostid)
	{
		$ret_code=0;
		$src_graphitems=get_graphitems_by_graphid($src_graphid);
		while($src_graphitem=DBfetch($src_graphitems))
		{
			$src_item=get_item_by_itemid($src_graphitem["itemid"]);
			$host_items=get_items_by_hostid($hostid);
			$item_exist=0;
			while($host_item=DBfetch($host_items))
			{
				if($src_item["key_"]==$host_item["key_"])
				{
					$item_exist=1;
					$host_itemid=$host_item["itemid"];
					break;
				}
			}
			if($item_exist==0)
			{
				$ret_code|=1;
				continue;
			}
			if(!add_item_to_graph($dist_graphid,$host_itemid,$src_graphitem["color"],$src_graphitem["drawtype"],$src_graphitem["sortorder"],$src_graphitem["yaxisside"]))
			{
				$ret_code|=2;
			}
		}
		return $ret_code;
	}

	function	add_graph_item_to_templates(
		$template_graphid,$template_itemid,
		$color,$drawtype,$sortorder,$yaxisside)
	{
		if($template_graphid<=0)
			return;

// get host count by graph
		$template_hosts = get_hosts_by_graphid($template_graphid);

		if(!$template_hosts)
			return;

		$template_hosts_cnt=DBnum_rows($template_hosts);

		if($template_hosts_cnt==0)
		{
			$template_host=get_host_by_itemid($template_itemid);
			$template_hosts_cnt++;
		}
		else if($template_hosts_cnt==1)
		{
			$template_host=DBfetch($template_hosts);
			$item_host=get_host_by_itemid($template_itemid);
			if($template_host["hostid"]!=$item_host["hostid"])
				$template_hosts_cnt++;
		}
		if($template_hosts_cnt!=1)		return;
// end host counting

		$template_item=get_item_by_itemid($template_itemid);

		$hosts=DBselect("select hostid,templateid,graphs from hosts_templates".
			" where templateid=".$template_host["hostid"]);
		while($host=DBfetch($hosts))
		{
			if($host["graphs"]&2 == 0)	continue;

			$items=DBselect("select i.itemid from items i".
				" where i.key_=".zbx_dbstr($template_item["key_"]).
				" and i.hostid=".$host["hostid"]);
			if(DBnum_rows($items)==0)	continue;
			$item=DBfetch($items);

			$find_graph=0;
			$graphs=get_graphs_by_hostid($host["hostid"]);
			while($graph=DBfetch($graphs))
			{
				if(!cmp_graphs($template_graphid,$graph["graphid"]))		continue;
				if(!cmp_graph_by_item_key($template_graphid,$graph["graphid"]))	continue;

				add_item_to_graph($graph["graphid"],$item["itemid"],
					$color,$drawtype,$sortorder,$yaxisside);

				$host_info=get_host_by_hostid($host["hostid"]);
				info("Added item to graph '".$graph["name"]."'".
					" from linked host '".$host_info["host"]."'");

				remove_duplicated_graphs($graph["graphid"]);
				$find_graph=1;
			}

			if($find_graph==0)
			{
# duplicate graph for new host
				$template_graph=get_graph_by_graphid($template_graphid);

				$new_graphid=add_graph($template_graph["name"],$template_graph["width"],
					$template_graph["height"],$template_graph["yaxistype"],
					$template_graph["yaxismin"],$template_graph["yaxismax"]);

				if(copy_graphitems_for_host($template_graphid,$new_graphid,$host["hostid"])!=0)
				{
					delete_graph($new_graphid);
				}
				else
				{
					add_item_to_graph($new_graphid,$item["itemid"],$color,$drawtype,$sortorder,$yaxisside);
					$new_graph=get_graph_by_graphid($new_graphid);
					$host_info=get_host_by_hostid($host["hostid"]);
					info("Graph ".$new_graph["name"]." coped for linked host ".$host_info["host"]);
					remove_duplicated_graphs($new_graphid);
				}
			}
		}
	}

	function 	move_up_graph_item_from_templates($gitemid)
	{
		if($gitemid<=0)		return;
		$graph_item=get_graphitem_by_gitemid($gitemid);
                $graph=get_graph_by_graphid($graph_item["graphid"]);
                $item=get_item_by_itemid($graph_item["itemid"]);

                $sql="select hostid,templateid,graphs from hosts_templates where templateid=".$item["hostid"];
                $result=DBselect($sql);
                while($row=DBfetch($result))
		{
			if($row["graphs"]&2 == 0)	continue;

			$sql="select i.itemid from items i where i.key_=".zbx_dbstr($item["key_"])." and i.hostid=".$row["hostid"];
			$result2=DBselect($sql);
			if(DBnum_rows($result2)==0)	continue;
			$row2=DBfetch($result2);

			$sql="select distinct gi.gitemid,gi.graphid from graphs_items gi,items i where i.itemid=gi.itemid and i.hostid=".$row["hostid"]." and i.itemid=".$row2["itemid"]." and gi.drawtype=".$graph_item["drawtype"]." and gi.sortorder=".$graph_item["sortorder"]." and gi.color=".zbx_dbstr($graph_item["color"])." and gi.yaxisside= ".$graph_item["yaxisside"];
			$result3=DBselect($sql);
			if(DBnum_rows($result3)==0)	continue; 
			$row3=DBfetch($result3);

			$graph2=get_graph_by_graphid($row3["graphid"]);
			if(!cmp_graphs($graph["graphid"],$graph2["graphid"]))		continue;
			if(!cmp_graph_by_item_key($graph["graphid"],$graph2["graphid"]))continue;

			move_up_graph_item($row3["gitemid"]);
			$host=get_host_by_hostid($row["hostid"]);
			info("Updated graph element ".$item["key_"]." from linked host ".$host["host"]);
		}
	}

	function 	move_down_graph_item_from_templates($gitemid)
	{
		if($gitemid<=0)		return;
		$graph_item=get_graphitem_by_gitemid($gitemid);
                $graph=get_graph_by_graphid($graph_item["graphid"]);
                $item=get_item_by_itemid($graph_item["itemid"]);

                $sql="select hostid,templateid,graphs from hosts_templates where templateid=".$item["hostid"];
                $result=DBselect($sql);
                while($row=DBfetch($result))
		{
			if($row["graphs"]&2 == 0)	continue;

			$sql="select i.itemid from items i where i.key_=".zbx_dbstr($item["key_"])." and i.hostid=".$row["hostid"];
			$result2=DBselect($sql);
			if(DBnum_rows($result2)==0)	continue;
			$row2=DBfetch($result2);

			$sql="select distinct gi.gitemid,gi.graphid from graphs_items gi,items i where i.itemid=gi.itemid and i.hostid=".$row["hostid"]." and i.itemid=".$row2["itemid"]." and gi.drawtype=".$graph_item["drawtype"]." and gi.sortorder=".$graph_item["sortorder"]." and gi.color=".zbx_dbstr($graph_item["color"])." and gi.yaxisside= ".$graph_item["yaxisside"];
			$result3=DBselect($sql);
			if(DBnum_rows($result3)==0)	continue; 
			$row3=DBfetch($result3);

			$graph2=get_graph_by_graphid($row3["graphid"]);
			if(!cmp_graphs($graph["graphid"],$graph2["graphid"]))		continue;
			if(!cmp_graph_by_item_key($graph["graphid"],$graph2["graphid"]))continue;

			move_down_graph_item($row3["gitemid"]);
			$host=get_host_by_hostid($row["hostid"]);
			info("Updated graph element ".$item["key_"]." from linked host ".$host["host"]);
			
		}
	}

	function 	update_graph_item_from_templates($gitemid,$itemid,$color,$drawtype,$sortorder,$yaxisside)
	{
		if($gitemid<=0)		return;
		$graph_item=get_graphitem_by_gitemid($gitemid);
                $graph=get_graph_by_graphid($graph_item["graphid"]);
                $item=get_item_by_itemid($graph_item["itemid"]);

                $sql="select hostid,templateid,graphs from hosts_templates where templateid=".$item["hostid"];
                $result=DBselect($sql);
                while($row=DBfetch($result))
		{
			if($row["graphs"]&2 == 0)	continue;

			$sql="select i.itemid from items i where i.key_=".zbx_dbstr($item["key_"])." and i.hostid=".$row["hostid"];
			$result2=DBselect($sql);
			if(DBnum_rows($result2)==0)	continue;
			$row2=DBfetch($result2);

			$sql="select distinct gi.gitemid,gi.graphid from graphs_items gi,items i where i.itemid=gi.itemid and i.hostid=".$row["hostid"]." and i.itemid=".$row2["itemid"]." and gi.drawtype=".$graph_item["drawtype"]." and gi.sortorder=".$graph_item["sortorder"]." and gi.color=".zbx_dbstr($graph_item["color"])." and gi.yaxisside= ".$graph_item["yaxisside"];
			$result3=DBselect($sql);
			if(DBnum_rows($result3)==0)	continue; 
			$row3=DBfetch($result3);

			$graph2=get_graph_by_graphid($row3["graphid"]);
			if(!cmp_graphs($graph["graphid"],$graph2["graphid"]))		continue;
			if(!cmp_graph_by_item_key($graph["graphid"],$graph2["graphid"]))continue;

			update_graph_item($row3["gitemid"],$row2["itemid"],$color,$drawtype,$sortorder,$yaxisside);
			$host=get_host_by_hostid($row["hostid"]);
			info("Updated graph element ".$item["key_"]." from linked host ".$host["host"]);
			
		}

	}

	function	delete_graph_item_from_templates($gitemid)
	{
		if($gitemid<=0)		return;

		$graph_item=get_graphitem_by_gitemid($gitemid);
		$graph=get_graph_by_graphid($graph_item["graphid"]);
		$item=get_item_by_itemid($graph_item["itemid"]);

		$sql="select hostid,templateid,graphs from hosts_templates where templateid=".$item["hostid"];
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			if($row["graphs"]&2 == 0)	continue;

			$sql="select i.itemid from items i where i.key_=".zbx_dbstr($item["key_"])." and i.hostid=".$row["hostid"];
			$result2=DBselect($sql);
			if(DBnum_rows($result2)==0)	continue;
			$row2=DBfetch($result2);
			$itemid=$row2["itemid"];

			$sql="select distinct gi.gitemid,gi.graphid from graphs_items gi,items i where i.itemid=gi.itemid and i.hostid=".$row["hostid"]." and i.itemid=".$row2["itemid"]." and gi.drawtype=".$graph_item["drawtype"]." and gi.sortorder=".$graph_item["sortorder"]." and gi.color=".zbx_dbstr($graph_item["color"])." and gi.yaxisside= ".$graph_item["yaxisside"];
			$result3=DBselect($sql);
			if(DBnum_rows($result3)==0)	continue; 
			$row3=DBfetch($result3);

			$graph2=get_graph_by_graphid($row3["graphid"]);
			if(!cmp_graphs($graph["graphid"],$graph2["graphid"]))		continue;
			if(!cmp_graph_by_item_key($graph["graphid"],$graph2["graphid"]))continue;
	
			delete_graphs_item($row3["gitemid"]);
			
			$host=get_host_by_hostid($row["hostid"]);
			info("Deleted graph element ".$item["key_"]." from linked host ".$host["host"]);

			$sql="select count(*) as count from graphs_items where graphid=".$row3["graphid"];
			$result4=DBselect($sql);
			$row4=DBfetch($result4);
			if($row4["count"]==0)
			{
				delete_graph($row3["graphid"]);
				info("Deleted graph from linked host ".$host["host"]);
			}
		}
	}

	function	navigation_bar_calc()
	{
		$workingperiod = 3600;

		if(!isset($_REQUEST["period"]))	$_REQUEST["period"]=3600;
		if(!isset($_REQUEST["from"]))	$_REQUEST["from"]=0;

		if(isset($_REQUEST["inc"]))		$workingperiod= $_REQUEST["period"]+$_REQUEST["inc"];
		if(isset($_REQUEST["dec"]))		$workingperiod= $workingperiod-$_REQUEST["dec"];
		//if(isset($_REQUEST["inc"]))		$_REQUEST["period"]= $_REQUEST["period"]+$_REQUEST["inc"];
		//if(isset($_REQUEST["dec"]))		$_REQUEST["period"]= $_REQUEST["period"]-$_REQUEST["dec"];

		if(isset($_REQUEST["left"]))	$_REQUEST["from"]= $_REQUEST["from"]+$_REQUEST["left"];
		if(isset($_REQUEST["right"]))	$_REQUEST["from"]= $_REQUEST["from"]-$_REQUEST["right"];

		//unset($_REQUEST["inc"]);
		//unset($_REQUEST["dec"]);
		unset($_REQUEST["left"]);
		unset($_REQUEST["right"]);

		if($_REQUEST["from"]<=0)		$_REQUEST["from"]=0;
		if($_REQUEST["period"]<=0)		$_REQUEST["period"]=3600;

		if(isset($_REQUEST["reset"]))
		{
			$_REQUEST["period"]=3600;
			$_REQUEST["from"]=0;
			$workingperiod=3600;
		}
		return $workingperiod;
	}

	function	navigation_bar($url)
	{
		$h1=S_NAVIGATE;
		$h2=S_PERIOD."&nbsp;";
		$h2=$h2."<select class=\"biginput\" name=\"period\" onChange=\"submit()\">";
		$h2=$h2.form_select("period",3600,"1h");
		$h2=$h2.form_select("period",2*3600,"2h");
		$h2=$h2.form_select("period",4*3600,"4h");
		$h2=$h2.form_select("period",8*3600,"8h");
		$h2=$h2.form_select("period",12*3600,"12h");
		$h2=$h2.form_select("period",24*3600,"24h");
		$h2=$h2.form_select("period",7*24*3600,"week");
		$h2=$h2.form_select("period",31*24*3600,"month");
		$h2=$h2.form_select("period",365*24*3600,"year");
		$h2=$h2."</select>";
		$h2=$h2."<select class=\"biginput\" name=\"dec\" onChange=\"submit()\">";
		$h2=$h2.form_select("dec",0,S_DECREASE);
		$h2=$h2.form_select("dec",3600,"-1h");
		$h2=$h2.form_select("dec",4*3600,"-4h");
		$h2=$h2.form_select("dec",24*3600,"-24h");
		$h2=$h2.form_select("dec",7*24*3600,"-week");
		$h2=$h2.form_select("dec",31*24*3600,"-month");
		$h2=$h2.form_select("dec",365*24*3600,"-year");
		$h2=$h2."</select>";
		$h2=$h2."<select class=\"biginput\" name=\"inc\" onChange=\"submit()\">";
		$h2=$h2.form_select("inc",0,S_INCREASE);
		$h2=$h2.form_select("inc",3600,"+1h");
		$h2=$h2.form_select("inc",4*3600,"+4h");
		$h2=$h2.form_select("inc",24*3600,"+24h");
		$h2=$h2.form_select("inc",7*24*3600,"+week");
		$h2=$h2.form_select("inc",31*24*3600,"+month");
		$h2=$h2.form_select("inc",365*24*3600,"+year");
		$h2=$h2."</select>";
		$h2=$h2."&nbsp;".S_MOVE."&nbsp;";
		$h2=$h2."<select class=\"biginput\" name=\"left\" onChange=\"submit()\">";
		$h2=$h2.form_select("left",0,S_LEFT_DIR);
		$h2=$h2.form_select("left",1,"-1h");
		$h2=$h2.form_select("left",4,"-4h");
		$h2=$h2.form_select("left",24,"-24h");
		$h2=$h2.form_select("left",7*24,"-week");
		$h2=$h2.form_select("left",31*24,"-month");
		$h2=$h2.form_select("left",365*24,"-year");
		$h2=$h2."</select>";
		$h2=$h2."<select class=\"biginput\" name=\"right\" onChange=\"submit()\">";
		$h2=$h2.form_select("right",0,S_RIGHT_DIR);
		$h2=$h2.form_select("right",1,"+1h");
		$h2=$h2.form_select("right",4,"+4h");
		$h2=$h2.form_select("right",24,"+24h");
		$h2=$h2.form_select("right",7*24,"+week");
		$h2=$h2.form_select("right",31*24,"+month");
		$h2=$h2.form_select("right",365*24,"+year");
		$h2=$h2."</select>";
		$h2=$h2."&nbsp;";
		$h2=$h2."<input name=\"stime\" size=18 class=\"biginput\" value=\"yyyymmddhhmm\" size=12>";
		$h2=$h2."&nbsp;";
		$h2=$h2."<input class=\"button\" type=\"submit\" name=\"action\" value=\"go\">";
		$h2=$h2."<input class=\"button\" type=\"submit\" name=\"reset\" value=\"reset\">";

		if(isset($_REQUEST["graphid"])&&($_REQUEST["graphid"]!=0))
		{
			$h2=$h2."<input name=\"graphid\" type=\"hidden\" value=\"".$_REQUEST["graphid"]."\" size=12>";
		}
		if(isset($_REQUEST["screenid"])&&($_REQUEST["screenid"]!=0))
		{
			$h2=$h2."<input name=\"screenid\" type=\"hidden\" value=\"".$_REQUEST["screenid"]."\" size=12>";
		}
		if(isset($_REQUEST["itemid"])&&($_REQUEST["itemid"]!=0))
		{
			$h2=$h2."<input name=\"itemid\" type=\"hidden\" value=\"".$_REQUEST["itemid"]."\" size=12>";
		}
		if(isset($_REQUEST["action"]))
		{
			$h2=$h2."<input name=\"action\" type=\"hidden\" value=\"".$_REQUEST["action"]."\" size=22>";
		}
		if(isset($_REQUEST["from"]))
		{
			$h2=$h2."<input name=\"from\" type=\"hidden\" value=\"".$_REQUEST["from"]."\" size=22>";
		}
		if(isset($_REQUEST["fullscreen"]))
		{
			$h2=$h2."<input name=\"fullscreen\" type=\"hidden\" value=\"".$_REQUEST["fullscreen"]."\" size=22>";
		}

		show_header2($h1,$h2,"<form name=\"form2\" method=\"get\" action=\"$url\">","</form>");

		return;

		echo "<TABLE BORDER=0 align=center COLS=2 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=1>";
		echo "<TR BGCOLOR=#FFFFFF>";
		echo "<TD ALIGN=LEFT>";

		echo "<div align=left>";
		echo "<b>".S_PERIOD.":</b>&nbsp;";

		$hour=3600;
		
		$a=array(S_1H=>3600,S_2H=>2*3600,S_4H=>4*3600,S_8H=>8*3600,S_12H=>12*3600,
			S_24H=>24*3600,S_WEEK_SMALL=>7*24*3600,S_MONTH_SMALL=>31*24*3600,S_YEAR_SMALL=>365*24*3600);
		foreach($a as $label=>$sec)
		{
			echo "[";
			if($_REQUEST["period"]>$sec)
			{
				$tmp=$_REQUEST["period"]-$sec;
				echo("<A HREF=\"$url&period=$tmp".url_param("graphid").url_param("stime").url_param("from").url_param("keep").url_param("fullscreen")."\">-</A>");
			}
			else
			{
				echo "-";
			}

			echo("<A HREF=\"$url?period=$sec".url_param("graphid").url_param("stime").url_param("from").url_param("keep").url_param("fullscreen")."\">");
			echo($label."</A>");

			$tmp=$_REQUEST["period"]+$sec;
			echo("<A HREF=\"$url?period=$tmp".url_param("graphid").url_param("stime").url_param("from").url_param("keep").url_param("fullscreen")."\">+</A>");

			echo "]&nbsp;";
		}

		echo("</div>");

		echo "</TD>";
		echo "<TD BGCOLOR=#FFFFFF WIDTH=15% ALIGN=RIGHT>";
		echo "<b>".nbsp(S_KEEP_PERIOD).":</b>&nbsp;";
		if($_REQUEST["keep"] == 1)
		{
			echo("[<A HREF=\"$url?keep=0".url_param("graphid").url_param("from").url_param("period").url_param("fullscreen")."\">".S_ON_C."</a>]");
		}
		else
		{
			echo("[<A HREF=\"$url?keep=1".url_param("graphid").url_param("from").url_param("period").url_param("fullscreen")."\">".S_OFF_C."</a>]");
		}
		echo "</TD>";
		echo "</TR>";
		echo "<TR BGCOLOR=#FFFFFF>";
		echo "<TD>";
		if(isset($_REQUEST["stime"]))
		{
			echo "<div align=left>" ;
			echo "<b>".S_MOVE.":</b>&nbsp;" ;

			$day=24;
// $a already defined
			$a=array("1h"=>1,"2h"=>2,"4h"=>4,"8h"=>8,"12h"=>12,
				"24h"=>24,"week"=>7*24,"month"=>31*24,"year"=>365*24);
			foreach($a as $label=>$hours)
			{
				echo "[";

				$stime=$_REQUEST["stime"];
				$tmp=mktime(substr($stime,8,2),substr($stime,10,2),0,substr($stime,4,2),substr($stime,6,2),substr($stime,0,4));
				$tmp=$tmp-3600*$hours;
				$tmp=date("YmdHi",$tmp);
				echo("<A HREF=\"$url?stime=$tmp".url_param("graphid").url_param("period").url_param("keep").url_param("fullscreen")."\">-</A>");
	
				echo($label);
	
				$stime=$_REQUEST["stime"];
				$tmp=mktime(substr($stime,8,2),substr($stime,10,2),0,substr($stime,4,2),substr($stime,6,2),substr($stime,0,4));
				$tmp=$tmp+3600*$hours;
				$tmp=date("YmdHi",$tmp);
				echo("<A HREF=\"$url?stime=$tmp".url_param("graphid").url_param("period").url_param("keep").url_param("fullscreen")."\">+</A>");
	
				echo "]&nbsp;";
			}
			echo("</div>");
		}
		else
		{
			echo "<div align=left>";
			echo "<b>".S_MOVE.":</b>&nbsp;";
	
			$day=24;
// $a already defined
			$a=array("1h"=>1,"2h"=>2,"4h"=>4,"8h"=>8,"12h"=>12,
				"24h"=>24,"week"=>7*24,"month"=>31*24,"year"=>365*24);
			foreach($a as $label=>$hours)
			{
				echo "[";
				$tmp=$_REQUEST["from"]+$hours;
				echo("<A HREF=\"$url?from=$tmp".url_param("graphid").url_param("period").url_param("keep").url_param("fullscreen")."\">-</A>");

				echo($label);

				if($_REQUEST["from"]>=$hours)
				{
					$tmp=$_REQUEST["from"]-$hours;
					echo("<A HREF=\"$url?from=$tmp".url_param("graphid").url_param("period").url_param("keep").url_param("fullscreen")."\">+</A>");
				}
				else
				{
					echo "+";
				}

				echo "]&nbsp;";
			}
			echo("</div>");
		}
		echo "</TD>";
		echo "<TD BGCOLOR=#FFFFFF WIDTH=15% ALIGN=RIGHT>";
		echo "<form method=\"put\" action=\"$url\">";
		echo "<input name=\"graphid\" type=\"hidden\" value=\"".$_REQUEST["graphid"]."\" size=12>";
		echo "<input name=\"period\" type=\"hidden\" value=\"".(9*3600)."\" size=12>";
		if(isset($_REQUEST["stime"]))
		{
			echo "<input name=\"stime\" class=\"biginput\" value=\"".$_REQUEST["stime"]."\" size=12>";
		}
		else
		{
			echo "<input name=\"stime\" class=\"biginput\" value=\"yyyymmddhhmm\" size=12>";
		}
		echo "&nbsp;";
		echo "<input class=\"button\" type=\"submit\" name=\"action\" value=\"go\">";
		echo "</form>";
		echo "</TD>";
		echo "</TR>";
		echo "</TABLE>";
	}
	
	function cmp_graphs($graphid1, $graphid2)
	{

		$graph1 = get_graph_by_graphid($graphid1);
		if($graph1==FALSE)	return FALSE;
		
		$graph2 =get_graph_by_graphid($graphid2);
		if($graph2==FALSE)	return FALSE;

		if($graph1["name"]	!=$graph2["name"]) 	return FALSE;
		if($graph1["width"]	!=$graph2["width"]) 	return FALSE;
		if($graph1["height"]	!=$graph2["height"])	return FALSE;
		if($graph1["yaxistype"]	!=$graph2["yaxistype"])	return FALSE;
		if($graph1["yaxismin"]	!=$graph2["yaxismin"]) 	return FALSE;
		if($graph1["yaxismax"]	!=$graph2["yaxismax"]) 	return FALSE;

		return TRUE;
	}

	function cmp_graph_by_graphs_items($graphid1, $graphid2)
	{
		$graph_items1 = get_graphitems_by_graphid($graphid1);
		$graph_items2 = get_graphitems_by_graphid($graphid2);
		if(DBnum_rows($graph_items1) != DBnum_rows($graph_items2))	return FALSE;

		while(($graph_item1=DBfetch($graph_items1)) && ($graph_item2=DBfetch($graph_items2)))
		{
			if($graph_item1["itemid"] 	!= $graph_item2["itemid"])	return FALSE;
			if($graph_item1["drawtype"] 	!= $graph_item2["drawtype"])	return FALSE;
			if($graph_item1["sortorder"] 	!= $graph_item2["sortorder"])	return FALSE;
			if($graph_item1["color"] 	!= $graph_item2["color"])	return FALSE;
			if($graph_item1["yaxisside"] 	!= $graph_item2["yaxisside"])	return FALSE;
		}

		return TRUE;
	}

	function cmp_graph_by_item_key($graphid1, $graphid2)
	{
		$graph_items1 = get_graphitems_by_graphid($graphid1);
		$graph_items2 = get_graphitems_by_graphid($graphid2);
		if(DBnum_rows($graph_items1) != DBnum_rows($graph_items2))	return FALSE;

		while(($graph_item1=DBfetch($graph_items1)) && ($graph_item2=DBfetch($graph_items2)))
		{
			$item1 = get_item_by_itemid($graph_item1["itemid"]);
			$item2 = get_item_by_itemid($graph_item2["itemid"]);
			if($item1["key_"] != $item2["key_"])	return FALSE;
		}

		return TRUE;
	}
	
	function remove_duplicated_graphs($graphid=0)
	{
		$sql="select graphid from graphs";
		if($graphid!=0)
		{
			$sql.=" where graphid=$graphid";
		}
		$graphs = DBselect($sql);
		if(DBnum_rows($graphs) == 0) return;

		while($graphid=DBfetch($graphs))
		{
			$sql="select graphid from graphs";
			$all_graphs = DBselect($sql);
			if(DBnum_rows($all_graphs) == 0) return;
			while($all_graphid=DBfetch($all_graphs))
			{
				if($graphid["graphid"] == $all_graphid["graphid"]) continue;
				if(cmp_graphs($graphid["graphid"],$all_graphid["graphid"]) == FALSE) continue;
				if(cmp_graph_by_graphs_items($graphid["graphid"],$all_graphid["graphid"]) == FALSE) continue;
				$graph=get_graph_by_graphid($graphid["graphid"]);
				delete_graph($graphid["graphid"]);
				info("Deleted duplicated graph with name '".$graph["name"]."'");
			}
		}
	}

	function		add_graph_item_to_linked_hosts($gitemid,$hostid=0)
	{
		if($gitemid<=0)
		{
			return;
		}

		$graph_item=get_graphitem_by_gitemid($gitemid);
		$graph=get_graph_by_graphid($graph_item["graphid"]);
		$item=get_item_by_itemid($graph_item["itemid"]);

		if($hostid==0)
		{
			$sql="select hostid,templateid,graphs from hosts_templates where templateid=".$item["hostid"];
		}
		else
		{
			$sql="select hostid,templateid,graphs from hosts_templates where hostid=$hostid and templateid=".$item["hostid"];
		}
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			if($row["graphs"]&1 == 0)	continue;

			$sql="select i.itemid from items i where i.key_=".zbx_dbstr($item["key_"])." and i.hostid=".$row["hostid"];
			$result2=DBselect($sql);
			if(DBnum_rows($result2)==0)	continue;
			$row2=DBfetch($result2);
			$itemid=$row2["itemid"];

			$sql="select distinct g.graphid from graphs g,graphs_items gi,items i where i.itemid=gi.itemid and i.hostid=".$row["hostid"]." and g.graphid=gi.graphid and g.name=".zbx_dbstr($graph["name"]);
			$result2=DBselect($sql);
			$host=get_host_by_hostid($row["hostid"]);
			while($row2=DBfetch($result2))
			{
				add_item_to_graph($row2["graphid"],$itemid,$graph_item["color"],$graph_item["drawtype"],$graph_item["sortorder"],$graph_item["yaxisside"]);
				info("Added graph element to graph ".$graph["name"]." of linked host ".$host["host"]);
			}
			if(DBnum_rows($result2)==0)
			{
				$graphid=add_graph($graph["name"],$graph["width"],$graph["height"],$graph["yaxistype"],$graph["yaxismin"],$graph["yaxismax"]);
				info("Added graph ".$graph["name"]." of linked host ".$host["host"]);
				add_item_to_graph($graphid,$itemid,$graph_item["color"],$graph_item["drawtype"],$graph_item["sortorder"],$graph_item["yaxisside"]);
				info("Added graph element to graph ".$graph["name"]." of linked host ".$host["host"]);
			}
		}
	}
?>

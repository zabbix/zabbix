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
	function 	get_graph_by_gitemid($gitemid)
	{
		$db_graphs = DBselect("select distinct g.* from graphs g, graphs_items gi".
			" where g.graphid=gi.graphid and gi.gitemid=$gitemid");
		return DBfetch($db_graphs);
		
	}

	function 	get_graphs_by_hostid($hostid)
	{
		return DBselect("select distinct g.* from graphs g, graphs_items gi, items i".
			" where g.graphid=gi.graphid and gi.itemid=i.itemid and i.hostid=$hostid");
	}

	function	get_realhosts_by_graphid($graphid)
	{
		$graph = get_graph_by_graphid($graphid);
		if($graph["templateid"] != 0)
			return get_realhosts_by_graphid($graph["templateid"]);

		return get_hosts_by_graphid($graphid);
	}

	function 	get_hosts_by_graphid($graphid)
	{
		return DBselect("select distinct h.* from graphs_items gi, items i, hosts h".
			" where h.hostid=i.hostid and gi.itemid=i.itemid and gi.graphid=$graphid");
	}

	function	get_graphitems_by_graphid($graphid)
	{
		return DBselect("select * from graphs_items where graphid=$graphid".
			" order by itemid,drawtype,sortorder,color,yaxisside"); 
	}

	function	get_graphitem_by_gitemid($gitemid)
	{
		$result=DBselect("select * from graphs_items where gitemid=$gitemid");
		if(DBnum_rows($result) == 1)
		{
			return	DBfetch($result);	
		}
		error("No graph item with gitemid=[$gitemid]");
		return	$result;
	}

	function	get_graph_by_graphid($graphid)
	{

		$result=DBselect("select * from graphs where graphid=$graphid");
		if(DBnum_rows($result) == 1)
		{
			return	DBfetch($result);	
		}
		error("No graph with graphid=[$graphid]");
		return	$result;
	}

	function	get_graphs_by_templateid($templateid)
	{
		return DBselect("select * from graphs where templateid=$templateid");
	}

	# Add Graph

	function	add_graph($name,$width,$height,$yaxistype,$yaxismin,$yaxismax,$templateid=0)
	{
		if(!check_right("Graph","A",0))
		{
			error("Insufficient permissions");
			return 0;
		}

		$result=DBexecute("insert into graphs".
			" (name,width,height,yaxistype,yaxismin,yaxismax,templateid)".
			" values (".zbx_dbstr($name).",$width,$height,$yaxistype,$yaxismin,".
			" $yaxismax,$templateid)");
		$graphid =  DBinsert_id($result,"graphs","graphid");
		if($graphid)
		{
			info("Graph '$name' added");
		}
		return $graphid;
	}

	# Update Graph

	function	update_graph($graphid,$name,$width,$height,$yaxistype,$yaxismin,$yaxismax,$templateid=0)
	{
		if(!check_right("Graph","U",0))
		{
			error("Insufficient permissions");
			return 0;
		}

		$g_graph = get_graph_by_graphid($graphid);

		$graphs = get_graphs_by_templateid($graphid);
		while($graph = DBfetch($graphs))
		{
			$result = update_graph($graph["graphid"],$name,$width,
				$height,$yaxistype,$yaxismin,$yaxismax,$graphid);
			if(!$result)
				return $result;
		}

		$result = DBexecute("update graphs set name=".zbx_dbstr($name).",width=$width,height=$height,".
			"yaxistype=$yaxistype,yaxismin=$yaxismin,yaxismax=$yaxismax,templateid=$templateid".
			" where graphid=$graphid");
		if($result)
		{
			info("Graph '".$g_graph["name"]."' updated");
		}
		return $result;
	}
	
	# Delete Graph

	function	delete_graph($graphid)
	{
		if(!check_right("Graph","U",0))
		{
			error("Insufficient permissions");
			return 0;
		}

		$graph = get_graph_by_graphid($graphid);

		$chd_graphs = get_graphs_by_templateid($graphid);
		while($chd_graph = DBfetch($chd_graphs))
		{// recursion
			$result = delete_graph($chd_graph["graphid"]);
			if(!$result)
				return $result;
		}

		// delete graph
		$result=DBexecute("delete from graphs_items where graphid=$graphid");
		if(!$result)
			return	$result;

		$result = DBexecute("delete from graphs where graphid=$graphid");
		if($result)
		{	
			info("Graph '".$graph["name"]."' deleted");
		}
		return $result;
	}

	function	cmp_graphitems(&$gitem1, &$gitem2)
	{
		if($gitem1["drawtype"]	!= $gitem2["drawtype"])		return 1;
		if($gitem1["sortorder"] != $gitem2["sortorder"])	return 2;
		if($gitem1["color"]	!= $gitem2["color"])		return 3;
		if($gitem1["yaxisside"] != $gitem2["yaxisside"])	return 4;

		$item1 = get_item_by_itemid($gitem1["itemid"]);
		$item2 = get_item_by_itemid($gitem2["itemid"]);

		if($item1["key_"] != $item2["key_"])			return 5;

		return 0;
	}

	function	add_item_to_graph($graphid,$itemid,$color,$drawtype,$sortorder,$yaxisside)
	{
		$result = DBexecute("insert into graphs_items".
			" (graphid,itemid,color,drawtype,sortorder,yaxisside)".
			" values ($graphid,$itemid,".zbx_dbstr($color).",$drawtype,$sortorder,$yaxisside)");

		$gitemid = DBinsert_id($result,"graphs_items","gitemid");

		$item = get_item_by_itemid($itemid);
		$graph = get_graph_by_graphid($graphid);

		$host = get_host_by_itemid($itemid);
		if($gitemid && $host["status"]==HOST_STATUS_TEMPLATE)
		{// add to child graphs
			$gitems = get_graphitems_by_graphid($graphid);
			if(DBnum_rows($gitems)==1)
			{// create graphs for childs with item
				$chd_hosts = get_hosts_by_templateid($host["hostid"]);
				while($chd_host = DBfetch($chd_hosts))
				{
					$new_graphid = add_graph($graph["name"],$graph["width"],$graph["height"],
						$graph["yaxistype"],$graph["yaxismin"],$graph["yaxismax"],
						$graph["graphid"]);

					if(!$new_graphid)
					{
						$result = $new_graphid;
						break;
					}
					$db_items = DBselect("select itemid from items".
						" where key_=".$item["key_"].
						" and hostid=".$chd_host["hostid"]);
					if(DBnum_rows($db_items)==0)
					{
						$result = FALSE;
						break;
					}
					$db_item = DBfetch($db_items);
				// recursion
					$result = add_item_to_graph($new_graphid,$db_item["itemid"],
						$color,$drawtype,$sortorder,$yaxisside);

					if(!$result)
						break;
					
				}
			}
			else
			{// copy items to childs
				$childs = get_graphs_by_templateid($graphid);
				while($child = DBfetch($childs))
				{
					$chd_hosts = get_hosts_by_graphid($child["graphid"]);
					$chd_host = DBfetch($chd_hosts);
					$db_items = DBselect("select itemid from items".
						" where key_=".$item["key_"].
						" and hostid=".$chd_host["hostid"]);
					if(DBnum_rows($db_items)==0)
					{
						$result = FALSE;
						break;
					}
					$db_item = DBfetch($db_items);
				// recursion
					$result = add_item_to_graph($child["graphid"],$db_item["itemid"],
						$color,$drawtype,$sortorder,$yaxisside);
					if(!$result)
						break;
				}
				
			}
			if(!$result && $graph["templateid"]==0)
			{// remove only main graph item
				delete_graph_item($gitemid);
				return $result;
			}
		}
		if($result)
		{
			info("Added Item '".$item["description"]."' for graph '".$graph["name"]."'");
		}

		return $gitemid;
	}
	
	function	update_graph_item($gitemid,$itemid,$color,$drawtype,$sortorder,$yaxisside)
	{
		$gitem = get_graphitem_by_gitemid($gitemid);

		$item = get_item_by_itemid($itemid);
		$graph = get_graph_by_gitemid($gitemid);

		$childs = get_graphs_by_templateid($graph["graphid"]);
		while($child = DBfetch($childs))
		{
			$chd_hosts = get_hosts_by_graphid($child["graphid"]);
			$chd_host = DBfetch($chd_hosts);
			$db_items = DBselect("select itemid from items".
				" where key_=".$item["key_"].
				" and hostid=".$chd_host["hostid"]);
			if(DBnum_rows($db_items)==0)
				return FALSE;
			$db_item = DBfetch($db_items);

			$chd_gitems = get_graphitems_by_graphid($child["graphid"]);
			while($chd_gitem = DBfetch($chd_gitems))
			{
				if(cmp_graphitems($gitem, $chd_gitem))	continue;

			// recursion
				$result = update_graph_item($chd_gitem["gitemid"],$db_item["itemid"],
					$color,$drawtype,$sortorder,$yaxisside);
				if(!$result)
					return $reslut;
				break;
			}
		}
		$result = DBexecute("update graphs_items set itemid=$itemid,color=".zbx_dbstr($color).",".
			"drawtype=$drawtype,sortorder=$sortorder,yaxisside=$yaxisside where gitemid=$gitemid");
		if($result)
		{
			$host = get_host_by_itemid($item["itemid"]);
			info("Graph item '".$host["host"].": ".$item["description"].
				" for graph '".$graph["name"]."' updated");
		}
		return $result;
	}

	function	delete_graph_item($gitemid)
	{
		
		$gitem = get_graphitem_by_gitemid($gitemid);

		$graph = get_graph_by_gitemid($gitemid);
		$childs = get_graphs_by_templateid($graph["graphid"]);
		while($child = DBfetch($childs))
		{
			$chd_gitems = get_graphitems_by_graphid($child["graphid"]);
			while($chd_gitem = DBfetch($chd_gitems))
			{
				if(cmp_graphitems($gitem, $chd_gitem))	continue;

			// recursion
				$result = delete_graph_item($chd_gitem["gitemid"]);
				if(!$result)
					return $reslut;
				break;
			}
		}

		$result = DBexecute("delete from graphs_items where gitemid=$gitemid");
		if($result)
		{
			$item = get_item_by_itemid($gitem["itemid"]);
			info("Item '".$item["description"]."' deleted from graph '".$graph["name"]."'");

			$graph_items = get_graphitems_by_graphid($graph["graphid"]);
			if($graph["templateid"]>0 && DBnum_rows($graph_items) < 1)
			{
				return delete_graph($graph["graphid"]);
			}
		}
		return $result;
	}

	function 	move_up_graph_item($gitemid)
	{
		if($gitemid<=0)		return;

		$gitem = get_graphitem_by_gitemid($gitemid);

		$graph = get_graph_by_gitemid($gitemid);
		$childs = get_graphs_by_templateid($graph["graphid"]);
		while($child = DBfetch($childs))
		{
			$chd_gitems = get_graphitems_by_graphid($child["graphid"]);
			while($chd_gitem = DBfetch($chd_gitems))
			{
				if(cmp_graphitems($gitem, $chd_gitem))	continue;

			// recursion
				$result = move_up_graph_item($chd_gitem["gitemid"]);
				if(!$result)
					return $reslut;
				break;
			}
		}
		$result = DBexecute("update graphs_items set sortorder=sortorder-1".
			" where sortorder>0 and gitemid=$gitemid");
		if($result)
		{
			$item = get_item_by_itemid($gitem["itemid"]);
			info("Sort order updated for item '".$item["description"]."'".
				" in graph '".$graph["name"]."'");
		}
		return $result;

	}
	
	function 	move_down_graph_item($gitemid)
	{
		if($gitemid<=0)		return;

		$gitem = get_graphitem_by_gitemid($gitemid);

		$graph = get_graph_by_gitemid($gitemid);
		$childs = get_graphs_by_templateid($graph["graphid"]);
		while($child = DBfetch($childs))
		{
			$chd_gitems = get_graphitems_by_graphid($child["graphid"]);
			while($chd_gitem = DBfetch($chd_gitems))
			{
				if(cmp_graphitems($gitem, $chd_gitem))	continue;

			// recursion
				$result = move_down_graph_item($chd_gitem["gitemid"]);
				if(!$result)
					return $reslut;
				break;
			}
		}
		$result = DBexecute("update graphs_items set sortorder=sortorder+1".
			" where sortorder<100 and gitemid=$gitemid");
		if($result)
		{
			$item = get_item_by_itemid($gitem["itemid"]);
			info("Sort order updated for item '".$item["description"]."'".
				" in graph '".$graph["name"]."'");
		}
		return $result;
	}

	function	delete_template_graphs_by_hostid($hostid)
	{
		$db_graphs = get_graphs_by_hostid($hostid);
		while($db_graph = DBfetch($db_graphs))
		{
			if($db_graph["templateid"] == 0)	continue;
			delete_graph($db_graph["graphid"]);
		}
	}
	
	function	sync_graphs_with_templates($hostid)
	{
		$host = get_host_by_hostid($hostid);	
		$db_graphs = get_graphs_by_hostid($host["templateid"]);
		while($db_graph = DBfetch($db_graphs))
		{
			copy_graph_to_host($db_graph["graphid"], $hostid);
		}
	}

	function	copy_graph_to_host($graphid, $hostid)
	{
		$db_graph = get_graph_by_graphid($graphid);
		$new_graphid = add_graph($db_graph["name"],$db_graph["width"],$db_graph["height"],
			$db_graph["yaxistype"],$db_graph["yaxismin"],$db_graph["yaxismax"],$graphid);
		if(!$new_graphid)
			return $new_graphid;

		$result = copy_graphitems_for_host($graphid, $new_graphid, $hostid);
		if(!$result)
		{
			delete_graph($graphid);
		}
		return $result;
	}

	function	copy_graphitems_for_host($src_graphid,$dist_graphid,$hostid)
	{
		$src_graphitems=get_graphitems_by_graphid($src_graphid);
		while($src_graphitem=DBfetch($src_graphitems))
		{
			$src_item=get_item_by_itemid($src_graphitem["itemid"]);
			$host_items=get_items_by_hostid($hostid);
			$item_exist=0;
			while($host_item=DBfetch($host_items))
			{
				if($src_item["key_"]!=$host_item["key_"])	continue;

				$item_exist=1;
				$host_itemid=$host_item["itemid"];

				$result = add_item_to_graph($dist_graphid,$host_itemid,$src_graphitem["color"],
					$src_graphitem["drawtype"],$src_graphitem["sortorder"],
					$src_graphitem["yaxisside"]);
				if(!$result)
					return $result;
				break;
			}
			if($item_exist==0)
				return FALSE;
		}
		return TRUE;
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
		$h2=S_PERIOD.SPACE;
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
		$h2=$h2.form_select(NULL,0,S_DECREASE);
		$h2=$h2.form_select(NULL,3600,"-1h");
		$h2=$h2.form_select(NULL,4*3600,"-4h");
		$h2=$h2.form_select(NULL,24*3600,"-24h");
		$h2=$h2.form_select(NULL,7*24*3600,"-week");
		$h2=$h2.form_select(NULL,31*24*3600,"-month");
		$h2=$h2.form_select(NULL,365*24*3600,"-year");
		$h2=$h2."</select>";
		$h2=$h2."<select class=\"biginput\" name=\"inc\" onChange=\"submit()\">";
		$h2=$h2.form_select(NULL,0,S_INCREASE);
		$h2=$h2.form_select(NULL,3600,"+1h");
		$h2=$h2.form_select(NULL,4*3600,"+4h");
		$h2=$h2.form_select(NULL,24*3600,"+24h");
		$h2=$h2.form_select(NULL,7*24*3600,"+week");
		$h2=$h2.form_select(NULL,31*24*3600,"+month");
		$h2=$h2.form_select(NULL,365*24*3600,"+year");
		$h2=$h2."</select>";
		$h2=$h2.SPACE.S_MOVE.SPACE;
		$h2=$h2."<select class=\"biginput\" name=\"left\" onChange=\"submit()\">";
		$h2=$h2.form_select(NULL,0,S_LEFT_DIR);
		$h2=$h2.form_select(NULL,1,"-1h");
		$h2=$h2.form_select(NULL,4,"-4h");
		$h2=$h2.form_select(NULL,24,"-24h");
		$h2=$h2.form_select(NULL,7*24,"-week");
		$h2=$h2.form_select(NULL,31*24,"-month");
		$h2=$h2.form_select(NULL,365*24,"-year");
		$h2=$h2."</select>";
		$h2=$h2."<select class=\"biginput\" name=\"right\" onChange=\"submit()\">";
		$h2=$h2.form_select(NULL,0,S_RIGHT_DIR);
		$h2=$h2.form_select(NULL,1,"+1h");
		$h2=$h2.form_select(NULL,4,"+4h");
		$h2=$h2.form_select(NULL,24,"+24h");
		$h2=$h2.form_select(NULL,7*24,"+week");
		$h2=$h2.form_select(NULL,31*24,"+month");
		$h2=$h2.form_select(NULL,365*24,"+year");
		$h2=$h2."</select>";
		$h2=$h2.SPACE;
		$h2=$h2."<input name=\"stime\" size=18 class=\"biginput\" value=\"yyyymmddhhmm\" size=12>";
		$h2=$h2.SPACE;
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
		echo "<b>".S_PERIOD.":</b>".SPACE;

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

			echo "]".SPACE;
		}

		echo("</div>");

		echo "</TD>";
		echo "<TD BGCOLOR=#FFFFFF WIDTH=15% ALIGN=RIGHT>";
		echo "<b>".nbsp(S_KEEP_PERIOD).":</b>".SPACE;
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
			echo "<b>".S_MOVE.":</b>".SPACE;

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
	
				echo "]".SPACE;
			}
			echo("</div>");
		}
		else
		{
			echo "<div align=left>";
			echo "<b>".S_MOVE.":</b>".SPACE;
	
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

				echo "]".SPACE;
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
		echo SPACE;
		echo "<input class=\"button\" type=\"submit\" name=\"action\" value=\"go\">";
		echo "</form>";
		echo "</TD>";
		echo "</TR>";
		echo "</TABLE>";
	}
?>

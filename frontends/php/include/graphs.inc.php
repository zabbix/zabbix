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

			$sql="select i.itemid from items i where i.key_='".$item["key_"]."' and i.hostid=".$row["hostid"];
			$result2=DBselect($sql);
			if(DBnum_rows($result2)==0)	continue;
			$row2=DBfetch($result2);
			$itemid=$row2["itemid"];

			$sql="select distinct g.graphid from graphs g,graphs_items gi,items i where i.itemid=gi.itemid and i.hostid=".$row["hostid"]." and g.graphid=gi.graphid and g.name='".addslashes($graph["name"])."'";
			$result2=DBselect($sql);
			$host=get_host_by_hostid($row["hostid"]);
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

	function	navigation_bar_calc()
	{
		if(!isset($_GET["period"]))	$_GET["period"]=3600;
		if(!isset($_GET["from"]))	$_GET["from"]=0;

		if(isset($_GET["inc"]))		$_GET["period"]= $_GET["period"]+$_GET["inc"];
		if(isset($_GET["dec"]))		$_GET["period"]= $_GET["period"]-$_GET["dec"];

		if(isset($_GET["left"]))	$_GET["from"]= $_GET["from"]+$_GET["left"];
		if(isset($_GET["right"]))	$_GET["from"]= $_GET["from"]-$_GET["right"];

		unset($_GET["inc"]);
		unset($_GET["dec"]);
		unset($_GET["left"]);
		unset($_GET["right"]);

		if($_GET["from"]<=0)		$_GET["from"]=0;
		if($_GET["period"]<=0)		$_GET["period"]=3600;

		if(isset($_GET["reset"]))
		{
			$_GET["period"]=3600;
			$_GET["from"]=0;
		}
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

		if(isset($_GET["graphid"])&&($_GET["graphid"]!=0))
		{
			$h2=$h2."<input name=\"graphid\" type=\"hidden\" value=\"".$_GET["graphid"]."\" size=12>";
		}
		if(isset($_GET["itemid"])&&($_GET["itemid"]!=0))
		{
			$h2=$h2."<input name=\"itemid\" type=\"hidden\" value=\"".$_GET["itemid"]."\" size=12>";
		}
		if(isset($_GET["action"]))
		{
			$h2=$h2."<input name=\"action\" type=\"hidden\" value=\"".$_GET["action"]."\" size=22>";
		}
		if(isset($_GET["from"]))
		{
			$h2=$h2."<input name=\"from\" type=\"hidden\" value=\"".$_GET["from"]."\" size=22>";
		}
		if(isset($_GET["fullscreen"]))
		{
			$h2=$h2."<input name=\"fullscreen\" type=\"hidden\" value=\"".$_GET["fullscreen"]."\" size=22>";
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
			if($_GET["period"]>$sec)
			{
				$tmp=$_GET["period"]-$sec;
				echo("<A HREF=\"$url&period=$tmp".url_param("graphid").url_param("stime").url_param("from").url_param("keep").url_param("fullscreen")."\">-</A>");
			}
			else
			{
				echo "-";
			}

			echo("<A HREF=\"$url?period=$sec".url_param("graphid").url_param("stime").url_param("from").url_param("keep").url_param("fullscreen")."\">");
			echo($label."</A>");

			$tmp=$_GET["period"]+$sec;
			echo("<A HREF=\"$url?period=$tmp".url_param("graphid").url_param("stime").url_param("from").url_param("keep").url_param("fullscreen")."\">+</A>");

			echo "]&nbsp;";
		}

		echo("</div>");

		echo "</TD>";
		echo "<TD BGCOLOR=#FFFFFF WIDTH=15% ALIGN=RIGHT>";
		echo "<b>".nbsp(S_KEEP_PERIOD).":</b>&nbsp;";
		if($_GET["keep"] == 1)
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
		if(isset($_GET["stime"]))
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

				$stime=$_GET["stime"];
				$tmp=mktime(substr($stime,8,2),substr($stime,10,2),0,substr($stime,4,2),substr($stime,6,2),substr($stime,0,4));
				$tmp=$tmp-3600*$hours;
				$tmp=date("YmdHi",$tmp);
				echo("<A HREF=\"$url?stime=$tmp".url_param("graphid").url_param("period").url_param("keep").url_param("fullscreen")."\">-</A>");
	
				echo($label);
	
				$stime=$_GET["stime"];
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
				$tmp=$_GET["from"]+$hours;
				echo("<A HREF=\"$url?from=$tmp".url_param("graphid").url_param("period").url_param("keep").url_param("fullscreen")."\">-</A>");

				echo($label);

				if($_GET["from"]>=$hours)
				{
					$tmp=$_GET["from"]-$hours;
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
		echo "<input name=\"graphid\" type=\"hidden\" value=\"".$_GET["graphid"]."\" size=12>";
		echo "<input name=\"period\" type=\"hidden\" value=\"".(9*3600)."\" size=12>";
		if(isset($_GET["stime"]))
		{
			echo "<input name=\"stime\" class=\"biginput\" value=\"".$_GET["stime"]."\" size=12>";
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
?>

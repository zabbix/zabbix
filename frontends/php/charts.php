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
	include "include/config.inc.php";
	$page["title"] = "S_CUSTOM_GRAPHS";
	$page["file"] = "charts.php";

	$nomenu=0;
	if(isset($_REQUEST["fullscreen"]))
	{
		$nomenu=1;
	}

	if(isset($_REQUEST["graphid"]) && $_REQUEST["graphid"] > 0 && !isset($_REQUEST["period"]) && !isset($_REQUEST["stime"]))
	{
		show_header($page["title"],1,$nomenu);
	}
	else
	{
		show_header($page["title"],0,$nomenu);
	}

	if(!check_anyright("Host","R"))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_page_footer();
		exit;
	}
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"groupid"=>		array(T_ZBX_INT, O_OPT,	 P_SYS,		DB_ID,NULL),
		"hostid"=>		array(T_ZBX_INT, O_OPT,  P_SYS,		DB_ID,NULL),
		"graphid"=>		array(T_ZBX_INT, O_OPT,  P_SYS,		DB_ID,NULL),
		"dec"=>			array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"inc"=>			array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"left"=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"right"=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"from"=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"period"=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"stime"=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	NULL,NULL),
		"action"=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	IN("'go'"),NULL),
		"reset"=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	IN("'reset'"),NULL),
		"fullscreen"=>		array(T_ZBX_INT, O_OPT,	P_SYS,		IN("1"),		NULL)
	);

	check_fields($fields);

	if(isset($_REQUEST["graphid"]) && !isset($_REQUEST["hostid"]))
	{
		$_REQUEST["groupid"] = $_REQUEST["hostid"] = 0;
	}

	$_REQUEST["graphid"] = get_request("graphid", get_profile("web.charts.grapgid", 0));
	$_REQUEST["keep"] = get_request("keep", 1); // possible excessed REQUEST variable !!!

	$_REQUEST["period"] = get_request("period",get_profile("web.graph[".$_REQUEST["graphid"]."].period", 3600));
	$effectiveperiod = navigation_bar_calc();

	validate_group_with_host("R",array("allow_all_hosts","monitored_hosts","with_items", "always_select_first_host"));

	if($_REQUEST["graphid"] > 0 && $_REQUEST["hostid"] > 0)
	{
		$result=DBselect("select g.graphid from graphs g, graphs_items gi, items i".
			" where i.hostid=".$_REQUEST["hostid"]." and gi.itemid = i.itemid".
			" and gi.graphid = g.graphid and g.graphid=".$_REQUEST["graphid"]);
		if(!DBfetch($result))
			$_REQUEST["graphid"] = 0;
	}
?>
<?php
	if($_REQUEST["graphid"] > 0 && $_REQUEST["period"] >= 3600)
	{
		update_profile("web.graph[".$_REQUEST["graphid"]."].period",$_REQUEST["period"]);
	}

	update_profile("web.charts.grapgid",$_REQUEST["graphid"]);
	update_profile("web.menu.view.last",$page["file"]);
?>

<?php
	if($_REQUEST["graphid"] > 0)
	{
		$result=DBselect("select name from graphs where graphid=".$_REQUEST["graphid"]);
		$row=DBfetch($result);
		$graph=$row["name"];
		$h1=iif(isset($_REQUEST["fullscreen"]),
			"<a href=\"charts.php?graphid=".$_REQUEST["graphid"]."\">".$graph."</a>",
			"<a href=\"charts.php?graphid=".$_REQUEST["graphid"]."&fullscreen=1\">".$graph."</a>");
	}
	else
	{
		$h1=S_SELECT_GRAPH_TO_DISPLAY;
	}

	$h1=S_GRAPHS_BIG.nbsp(" / ").$h1;

	$h2=S_GROUP.SPACE;
	$h2=$h2."<select class=\"biginput\" name=\"groupid\" onChange=\"submit()\">";
	$h2=$h2.form_select("groupid",0,S_ALL_SMALL);
	$result=DBselect("select groupid,name from groups order by name");
	while($row=DBfetch($result))
	{
// Check if at least one host with read permission exists for this group
		$result2=DBselect("select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and hg.groupid=".$row["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host");
		$cnt=0;
		while($row2=DBfetch($result2))
		{
			if(!check_right("Host","R",$row2["hostid"]))
			{
				continue;
			}
			$cnt=1; break;
		}
		if($cnt!=0)
		{
			$h2=$h2.form_select("groupid",$row["groupid"],$row["name"]);
		}
	}
	$h2=$h2."</select>";

	$h2=$h2.SPACE.S_HOST.SPACE;
	$h2=$h2."<select class=\"biginput\" name=\"hostid\" onChange=\"submit()\">";

	if($_REQUEST["groupid"] > 0)
	{
		$sql="select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host";
	}
	else
	{
		$h2=$h2.form_select("hostid",0,S_ALL_SMALL);

		$sql="select h.hostid,h.host from hosts h,items i where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid group by h.hostid,h.host order by h.host";
	}

	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		if(!check_right("Host","R",$row["hostid"]))
		{
			continue;
		}
		$h2=$h2.form_select("hostid",$row["hostid"],$row["host"]);
	}
	$h2=$h2."</select>";

	if(isset($_REQUEST["fullscreen"]))
	{
		$h2="<input name=\"fullscreen\" type=\"hidden\" value=".$_REQUEST["fullscreen"].">";
	}

	$h2=$h2.SPACE.S_GRAPH.SPACE;
	$h2=$h2."<select class=\"biginput\" name=\"graphid\" onChange=\"submit()\">";
	$h2=$h2.form_select("graphid",0,S_SELECT_GRAPH_DOT_DOT_DOT);

	if($_REQUEST["hostid"] > 0)
	{
		$sql = "select distinct g.graphid,g.name from graphs g,graphs_items gi,items i".
			" where i.itemid=gi.itemid and g.graphid=gi.graphid and i.hostid=".$_REQUEST["hostid"]." order by g.name";
	}
	elseif ($_REQUEST["groupid"] > 0)
	{
		$sql = "select distinct g.graphid,g.name from graphs g,graphs_items gi,items i,hosts_groups hg,hosts h".
			" where i.itemid=gi.itemid and g.graphid=gi.graphid and i.hostid=hg.hostid and hg.groupid=".$_REQUEST["groupid"].
			" and i.hostid=h.hostid and h.status=".HOST_STATUS_MONITORED.
			" order by g.name";
	}
	else
	{
		$sql = "select distinct g.graphid,g.name from graphs g,graphs_items gi,items i,hosts h".
			" where i.itemid=gi.itemid and g.graphid=gi.graphid ".
			" and i.hostid=h.hostid and h.status=".HOST_STATUS_MONITORED.
			" order by g.name";
	}

	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		if(!check_right("Graph","R",$row["graphid"]))
		{
			continue;
		}
		$h2=$h2.form_select("graphid",$row["graphid"],$row["name"]);
	}
	$h2=$h2."</select>";

	show_header2($h1,$h2,"<form name=\"form2\" method=\"get\" action=\"charts.php\">","</form>");
?>

<?php
	echo "<TABLE BORDER=0 align=center COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=#DDDDDD>";
	echo "<TD ALIGN=CENTER>";
	if($_REQUEST["graphid"] > 0)
	{
		echo "<script language=\"JavaScript\">";
		echo "document.write(\"<IMG SRC='chart2.php?graphid=".$_REQUEST["graphid"].url_param("stime")."&period=".$effectiveperiod."&from=".$_REQUEST["from"]."&width=\"+(document.width-108)+\"'>\")";
		echo "</script>";
	}
	else
	{
		echo "...";
	}
	echo "</TD>";
	echo "</TR>";
	echo "</TABLE>";

	if($_REQUEST["graphid"] > 0/*&&(!isset($_REQUEST["fullscreen"]))*/)
	{
		navigation_bar("charts.php");
	}
	
?>

<?php
	show_page_footer();
?>

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
	if(isset($_REQUEST["graphid"]) && !isset($_REQUEST["period"]) && !isset($_REQUEST["stime"]))
	{
		show_header($page["title"],1,$nomenu);
	}
	else
	{
		show_header($page["title"],0,$nomenu);
	}
	$effectiveperiod=navigation_bar_calc();

?>

<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"groupid"=>		array(T_ZBX_INT, O_OPT,	 P_SYS,		DB_ID,NULL),
		"hostid"=>		array(T_ZBX_INT, O_OPT,  P_SYS,		DB_ID,NULL),
		"graphid"=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,NULL),
		"dec"=>			array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"inc"=>			array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"from"=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"period"=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"stime"=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	NULL,NULL),
		"action"=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	IN("'go'"),NULL),
		"reset"=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	IN("'reset'"),NULL),
		"fullscreen"=>		array(T_ZBX_INT, O_OPT,	P_SYS,		IN("1"),		NULL)
	);

	check_fields($fields);
?>


<?php
	$_REQUEST["hostid"]=@iif(isset($_REQUEST["hostid"]),$_REQUEST["hostid"],get_profile("web.latest.hostid",0));
	update_profile("web.latest.hostid",$_REQUEST["hostid"]);
	update_profile("web.menu.view.last",$page["file"]);
?>

<?php
	if(!isset($_REQUEST["from"]))
	{
		$_REQUEST["from"]=0;
	}
	if(!isset($effectiveperiod))
	{
		$effectiveperiod=3600;
	}

	if(!isset($_REQUEST["keep"]))
	{
		$_REQUEST["keep"]=1;
	}

	if(isset($_REQUEST["groupid"])&&($_REQUEST["groupid"]==0))
	{
		unset($_REQUEST["groupid"]);
	}

	if(isset($_REQUEST["graphid"])&&($_REQUEST["graphid"]==0))
	{
		unset($_REQUEST["graphid"]);
	}

	if(isset($_REQUEST["graphid"]))
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

	$h2=S_GROUP."&nbsp;";
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

	$h2=$h2."&nbsp;".S_HOST."&nbsp;";
	$h2=$h2."<select class=\"biginput\" name=\"hostid\" onChange=\"submit()\">";
	$h2=$h2.form_select("hostid",0,S_SELECT_HOST_DOT_DOT_DOT);

	if(isset($_REQUEST["groupid"]))
	{
		$sql="select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host";
	}
	else
	{
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

	if(isset($_REQUEST["graphid"])&&($_REQUEST["graphid"]==0))
	{
		unset($_REQUEST["graphid"]);
	}

	$h2=$h2."&nbsp;".S_GRAPH."&nbsp;";
	$h2=$h2."<select class=\"biginput\" name=\"graphid\" onChange=\"submit()\">";
	$h2=$h2.form_select("graphid",0,S_SELECT_GRAPH_DOT_DOT_DOT);

	if(isset($_REQUEST["hostid"])&&($_REQUEST["hostid"]!=0))
	{
		$result=DBselect("select distinct g.graphid,g.name from graphs g,graphs_items gi,items i where i.itemid=gi.itemid and g.graphid=gi.graphid and i.hostid=".$_REQUEST["hostid"]." order by g.name");
		while($row=DBfetch($result))
		{
			if(!check_right("Graph","R",$row["graphid"]))
			{
				continue;
			}
			$h2=$h2.form_select("graphid",$row["graphid"],$row["name"]);
		}
	}
	$h2=$h2."</select>";

	show_header2($h1,$h2,"<form name=\"form2\" method=\"get\" action=\"charts.php\">","</form>");
?>

<?php
	echo "<TABLE BORDER=0 align=center COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=#DDDDDD>";
	echo "<TD ALIGN=CENTER>";
	if(isset($_REQUEST["graphid"]))
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

	if(isset($_REQUEST["graphid"])/*&&(!isset($_REQUEST["fullscreen"]))*/)
	{
		navigation_bar("charts.php");
	}
	
?>

<?php
	show_page_footer();
?>

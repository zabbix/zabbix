<?php
/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
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
	include "include/forms.inc.php";
	$page["title"] = S_CONFIGURATION_OF_GRAPHS;
	$page["file"] = "graphs.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
	show_table_header(S_CONFIGURATION_OF_GRAPHS_BIG);
?>

<?php
	if(!check_anyright("Graph","U"))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_footer();
		exit;
	}
?>

<?php
	if(isset($_GET["register"]))
	{
		if($_GET["register"]=="add")
		{
			$result=add_graph($_GET["name"],$_GET["width"],$_GET["height"],$_GET["yaxistype"],$_GET["yaxismin"],$_GET["yaxismax"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_GRAPH,"Graph [".addslashes($_GET["name"])."]");
			}
			show_messages($result, S_GRAPH_ADDED, S_CANNOT_ADD_GRAPH);
		}
		if($_GET["register"]=="update")
		{
			$result=update_graph($_GET["graphid"],$_GET["name"],$_GET["width"],$_GET["height"],$_GET["yaxistype"],$_GET["yaxismin"],$_GET["yaxismax"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_GRAPH,"Graph ID [".$_GET["graphid"]."] Graph [".addslashes($_GET["name"])."]");
			}
			show_messages($result, S_GRAPH_UPDATED, S_CANNOT_UPDATE_GRAPH);
		}
		if($_GET["register"]=="delete")
		{
			$graph=get_graph_by_graphid($_GET["graphid"]);
			$result=delete_graph($_GET["graphid"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_GRAPH,"Graph [".addslashes($graph["name"])."]");
			}
			show_messages($result, S_GRAPH_DELETED, S_CANNOT_DELETE_GRAPH);
			unset($_GET["graphid"]);
		}
	}
?>

<?php
	if(isset($_GET["groupid"])&&($_GET["groupid"]==0))
	{
		unset($_GET["groupid"]);
	}

	if(isset($_GET["graphid"])&&($_GET["graphid"]==0))
	{
		unset($_GET["graphid"]);
	}

	if(isset($_GET["graphid"]))
	{
		$result=DBselect("select name from graphs where graphid=".$_GET["graphid"]);
		$graph=DBget_field($result,0,0);
		$h1=iif(isset($_GET["fullscreen"]),
			"<a href=\"charts.php?graphid=".$_GET["graphid"]."\">".$graph."</a>",
			"<a href=\"charts.php?graphid=".$_GET["graphid"]."&fullscreen=1\">".$graph."</a>");
	}
	else
	{
		$h1=S_SELECT_GRAPH_TO_DISPLAY;
	}

	$h1=S_GRAPHS_BIG.nbsp(" / ").$h1;

	$h2=S_GROUP."&nbsp;";
	$h2=$h2."<select class=\"biginput\" name=\"groupid\" onChange=\"submit()\">";
	$h2=$h2."<option value=\"0\" ".iif(!isset($_GET["groupid"]),"selected","").">".S_ALL_SMALL;
	$result=DBselect("select groupid,name from groups order by name");
	while($row=DBfetch($result))
	{
// Check if at least one host with read permission exists for this group
		$result2=DBselect("select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.hostid=i.hostid and hg.groupid=".$row["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host");
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
			$h2=$h2."<option value=\"".$row["groupid"]."\" ".iif(isset($_GET["groupid"])&&($_GET["groupid"]==$row["groupid"]),"selected","").">".$row["name"];
		}
	}
	$h2=$h2."</select>";

	$h2=$h2."&nbsp;".S_HOST."&nbsp;";
	$h2=$h2."<select class=\"biginput\" name=\"hostid\" onChange=\"submit()\">";
	$h2=$h2."<option value=\"0\"".iif(!isset($_GET["hostid"])||($_GET["hostid"]==0),"selected","").">".S_SELECT_HOST_DOT_DOT_DOT;

	if(isset($_GET["groupid"]))
	{
		$sql="select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.hostid=i.hostid and hg.groupid=".$_GET["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host";
	}
	else
	{
		$sql="select h.hostid,h.host from hosts h,items i where h.hostid=i.hostid group by h.hostid,h.host order by h.host";
	}

	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		if(!check_right("Host","R",$row["hostid"]))
		{
			continue;
		}
		$h2=$h2."<option value=\"".$row["hostid"]."\"".iif(isset($_GET["hostid"])&&($_GET["hostid"]==$row["hostid"]),"selected","").">".$row["host"];
	}
	$h2=$h2."</select>";

	show_header2($h1,$h2,"<form name=\"form2\" method=\"get\" action=\"graphs.php\">","</form>");
?>

<?php
	table_begin();
	table_header(array(S_ID,S_NAME,S_WIDTH,S_HEIGHT,S_ACTIONS));

	if(isset($_GET["hostid"])&&($_GET["hostid"]!=0))
	{
		$result=DBselect("select distinct g.graphid,g.name,g.width,g.height from graphs g,items i,graphs_items gi where gi.itemid=i.itemid and g.graphid=gi.graphid and i.hostid=".$_GET["hostid"]." order by g.name");
	}
	else
	{
		$result=DBselect("select distinct g.graphid,g.name,g.width,g.height from graphs g order by g.name");
	}
	$col=0;
	while($row=DBfetch($result))
	{
		if(!check_right("Graph","U",$row["graphid"]))
		{
			continue;
		}

		if(!isset($_GET["hostid"]))
		{
			$sql="select * from graphs_items where graphid=".$row["graphid"];
			$result2=DBselect($sql);
			if(DBnum_rows($result2)>0)	continue;
		}
	
		table_row(array(
			$row["graphid"],
			"<a href=\"graph.php?graphid=".$row["graphid"]."\">".$row["name"]."</a>",
			$row["width"],
			$row["height"],
			"<A HREF=\"graphs.php?graphid=".$row["graphid"]."#form\">Change</A>"
			),$col++);
	}
	if(DBnum_rows($result)==0)
	{
		echo "<TR BGCOLOR=#EEEEEE>";
		echo "<TD COLSPAN=5 ALIGN=CENTER>".S_NO_GRAPHS_DEFINED."</TD>";
		echo "<TR>";
	}
	table_end();
?>

<?php
	echo "<a name=\"form\"></a>";

	insert_graph_form();

?>

<?php
	show_footer();
?>

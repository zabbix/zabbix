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
	include "include/forms.inc.php";
	$page["title"] = "S_CONFIGURATION_OF_GRAPHS";
	$page["file"] = "graphs.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>
<?php
	show_table_header(S_CONFIGURATION_OF_GRAPHS_BIG);
	echo BR;
?>
<?php
	if(!check_anyright("Graph","U"))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_page_footer();
		exit;
	}

	$_REQUEST["hostid"]=get_request("hostid",get_profile("web.latest.hostid",0));

	update_profile("web.latest.hostid",$_REQUEST["hostid"]);
	update_profile("web.menu.config.last",$page["file"]);
?>
<?php
	if(isset($_REQUEST["register"]))
	{
		if($_REQUEST["register"]=="add")
		{
			$result=add_graph($_REQUEST["name"],$_REQUEST["width"],$_REQUEST["height"],
				$_REQUEST["yaxistype"],$_REQUEST["yaxismin"],$_REQUEST["yaxismax"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_GRAPH,
					"Graph [".zbx_ads($_REQUEST["name"])."]");
			}
			show_messages($result, S_GRAPH_ADDED, S_CANNOT_ADD_GRAPH);
		}
		if($_REQUEST["register"]=="update")
		{
			update_graph_from_templates($_REQUEST["graphid"],$_REQUEST["name"],
				$_REQUEST["width"],$_REQUEST["height"],$_REQUEST["yaxistype"],
				$_REQUEST["yaxismin"],$_REQUEST["yaxismax"]);

			$result=update_graph($_REQUEST["graphid"],$_REQUEST["name"],$_REQUEST["width"],
				$_REQUEST["height"],$_REQUEST["yaxistype"],$_REQUEST["yaxismin"],
				$_REQUEST["yaxismax"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_GRAPH,
					"Graph ID [".$_REQUEST["graphid"]."] Graph [".
					zbx_ads($_REQUEST["name"])."]");
			}
			show_messages($result, S_GRAPH_UPDATED, S_CANNOT_UPDATE_GRAPH);
		}
		if($_REQUEST["register"]=="delete")
		{
			delete_graph_from_templates($_REQUEST["graphid"]);
			$graph=get_graph_by_graphid($_REQUEST["graphid"]);
			$result=delete_graph($_REQUEST["graphid"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_GRAPH,
					"Graph [".zbx_ads($graph["name"])."]");
			}
			show_messages($result, S_GRAPH_DELETED, S_CANNOT_DELETE_GRAPH);
			unset($_REQUEST["graphid"]);
		}
	}
?>
<?php
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
		$result2=DBselect("select h.hostid,h.host from hosts h,items i,hosts_groups hg".
			" where h.hostid=i.hostid and hg.groupid=".$row["groupid"].
			" and hg.hostid=h.hostid and h.status not in (".HOST_STATUS_DELETED.")".
			" group by h.hostid,h.host order by h.host");
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
		$sql="select h.hostid,h.host from hosts h,items i,hosts_groups hg".
			" where h.hostid=i.hostid and hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid".
			" and h.status not in (".HOST_STATUS_DELETED.") group by h.hostid,h.host order by h.host";
	}
	else
	{
		$sql="select h.hostid,h.host from hosts h,items i where h.hostid=i.hostid".
			" and h.status not in (".HOST_STATUS_DELETED.") group by h.hostid,h.host order by h.host";
	}

	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		if(!check_right("Host","R",$row["hostid"]))	continue;
		$h2=$h2.form_select("hostid",$row["hostid"],$row["host"]);
	}
	$h2=$h2."</select>";

	show_header2($h1,$h2,"<form name=\"form2\" method=\"get\" action=\"graphs.php\">","</form>");
?>
<?php
	$table = new CTableInfo(S_NO_GRAPHS_DEFINED);
	$table->setHeader(array(S_ID,S_NAME,S_WIDTH,S_HEIGHT,S_ACTIONS));

	if(isset($_REQUEST["hostid"])&&($_REQUEST["hostid"]!=0))
	{
		$result=DBselect("select distinct g.graphid,g.name,g.width,g.height from graphs g,items i".
			",graphs_items gi where gi.itemid=i.itemid and g.graphid=gi.graphid".
			" and i.hostid=".$_REQUEST["hostid"]." order by g.name");
	}
	else
	{
		$result=DBselect("select distinct g.graphid,g.name,g.width,g.height".
			" from graphs g order by g.name");
	}
	while($row=DBfetch($result))
	{
		if(!check_right("Graph","U",$row["graphid"]))		continue;

		if(!isset($_REQUEST["hostid"]))
		{
			$sql="select * from graphs_items where graphid=".$row["graphid"];
			$result2=DBselect($sql);
			if(DBnum_rows($result2)>0)	continue;
		}
	
		$table->addRow(array(
			$row["graphid"],
			new CLink($row["name"],"graph.php?graphid=".$row["graphid"]),
			$row["width"],
			$row["height"],
			new CLink("Change","graphs.php?graphid=".$row["graphid"])
			));
	}
	$table->show();
?>
<?php
	echo BR;
	insert_graph_form();

?>
<?php
	show_page_footer();
?>

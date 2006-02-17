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
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"groupid"=>	array(T_ZBX_INT, O_OPT,	 NULL,	DB_ID,	NULL),
		"hostid"=>	array(T_ZBX_INT, O_OPT,	 NULL,	DB_ID,	NULL),

		"graphid"=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,	'{form}=="update"'),
		"name"=>	array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,		'isset({save})'),
		"width"=>	array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(0,65535),	'isset({save})'),
		"height"=>	array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(0,65535),	'isset({save})'),
		"yaxistype"=>	array(T_ZBX_INT, O_OPT,	 NULL,	IN("0,1"),		'isset({save})'),
		"yaxismin"=>	array(T_ZBX_DBL, O_OPT,	 NULL,	BETWEEN(-65535,65535),	'isset({save})'),
		"yaxismax"=>	array(T_ZBX_DBL, O_OPT,	 NULL,	BETWEEN(-65535,65535),	'isset({save})'),

/* actions */
		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"delete"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
/* other */
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);

	check_fields($fields);
?>
<?php
	if(!check_anyright("Graph","U"))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_page_footer();
		exit;
	}

	$_REQUEST["hostid"]=get_request("hostid",get_profile("web.latest.hostid",0));
	$_REQUEST["groupid"]=get_request("groupid",get_profile("web.latest.groupid",0));

	update_profile("web.latest.hostid",$_REQUEST["hostid"]);
	update_profile("web.latest.groupid",$_REQUEST["groupid"]);
	update_profile("web.menu.config.last",$page["file"]);
?>
<?php
	if(isset($_REQUEST["save"]))
	{
		if(isset($_REQUEST["graphid"]))
		{
			$result=update_graph($_REQUEST["graphid"],
				$_REQUEST["name"],$_REQUEST["width"],$_REQUEST["height"],
				$_REQUEST["yaxistype"],$_REQUEST["yaxismin"],$_REQUEST["yaxismax"]);

			if($result)
			{
				add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_GRAPH,
					"Graph ID [".$_REQUEST["graphid"]."] Graph [".
					$_REQUEST["name"]."]");
			}
			show_messages($result, S_GRAPH_UPDATED, S_CANNOT_UPDATE_GRAPH);
		}
		else
		{
			$result=add_graph($_REQUEST["name"],$_REQUEST["width"],$_REQUEST["height"],
				$_REQUEST["yaxistype"],$_REQUEST["yaxismin"],$_REQUEST["yaxismax"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_GRAPH,
					"Graph [".$_REQUEST["name"]."]");
			}
			show_messages($result, S_GRAPH_ADDED, S_CANNOT_ADD_GRAPH);
		}
		if($result){
			unset($_REQUEST["form"]);
		}
	}
	elseif(isset($_REQUEST["delete"])&&isset($_REQUEST["graphid"]))
	{
		$graph=get_graph_by_graphid($_REQUEST["graphid"]);
		$result=delete_graph($_REQUEST["graphid"]);
		if($result)
		{
			add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_GRAPH,
				"Graph [".$graph["name"]."]");
			unset($_REQUEST["form"]);
		}
		show_messages($result, S_GRAPH_DELETED, S_CANNOT_DELETE_GRAPH);
	}
?>
<?php
	if(isset($_REQUEST["form"]))
	{
		insert_graph_form();
	} else {
/* HEADER */
		if(isset($_REQUEST["graphid"])&&($_REQUEST["graphid"]==0))
		{
			unset($_REQUEST["graphid"]);
		}
/*
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
*/
		$form = new CForm();
		$form->AddItem(S_GROUP.SPACE);
		$cmbGroup = new CComboBox("groupid",$_REQUEST["groupid"],"submit()");
		$cmbGroup->AddItem(0,S_ALL_SMALL);
		$result=DBselect("select groupid,name from groups order by name");
		while($row=DBfetch($result))
		{
	// Check if at least one host with read permission exists for this group
			$result2=DBselect("select h.hostid,h.host from hosts h,items i,hosts_groups hg".
				" where h.hostid=i.hostid and hg.groupid=".$row["groupid"].
				" and hg.hostid=h.hostid and h.status not in (".HOST_STATUS_DELETED.")".
				" group by h.hostid,h.host order by h.host");
			while($row2=DBfetch($result2))
			{
				if(!check_right("Host","R",$row2["hostid"]))
					continue;
				$cmbGroup->AddItem($row["groupid"],$row["name"]);
				break;
			}
		}
		$form->AddItem($cmbGroup);

		$form->AddItem(SPACE.S_HOST.SPACE);
			
		$cmbHosts = new CComboBox("hostid", $_REQUEST["hostid"], "submit()");
		$cmbHosts->AddItem(0,S_ALL_SMALL);
		if(isset($_REQUEST["groupid"]) && $_REQUEST["groupid"] > 0)
		{
			$sql="select h.hostid,h.host from hosts h,items i,hosts_groups hg".
				" where h.hostid=i.hostid and hg.groupid=".$_REQUEST["groupid"].
				" and hg.hostid=h.hostid"." and h.status not in (".HOST_STATUS_DELETED.")".
				" group by h.hostid,h.host order by h.host";
		}
		else
		{
			$sql="select h.hostid,h.host from hosts h,items i where h.hostid=i.hostid".
				" and h.status not in (".HOST_STATUS_DELETED.") group by h.hostid,h.host".
				" order by h.host";
		}
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			if(!check_right("Host","R",$row["hostid"]))	continue;
			$cmbHosts->AddItem($row["hostid"],$row["host"]);
		}
		$form->AddItem($cmbHosts);

		$form->AddItem(SPACE."|".SPACE);
		$form->AddItem(new CButton("form",S_CREATE_GRAPH));

		show_header2(S_GRAPHS_BIG, $form);

/* TABLE */
		$table = new CTableInfo(S_NO_GRAPHS_DEFINED);
		$table->setHeader(array(S_ID,S_NAME,S_WIDTH,S_HEIGHT,S_GRAPH));

		if(isset($_REQUEST["hostid"])&&($_REQUEST["hostid"]!=0))
		{
			$result=DBselect("select distinct g.* from graphs g,items i".
				",graphs_items gi where gi.itemid=i.itemid and g.graphid=gi.graphid".
				" and i.hostid=".$_REQUEST["hostid"]." order by g.name");
		}
		else
		{
			$result=DBselect("select * from graphs g order by g.name");
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
	
			if($row["templateid"]==0)
			{
				$name = new CLink($row["name"],
					"graphs.php?graphid=".$row["graphid"]."&form=update".
					url_param("groupid").url_param("hostid"),'action');
				$edit = new CLink("Edit",
					"graph.php?graphid=".$row["graphid"]);
			} else {
				$real_hosts = get_realhosts_by_graphid($row["templateid"]);
				if(DBnum_rows($real_hosts) == 1)
				{
					$real_host = DBfetch($real_hosts);
					$name = array(
						new CLink($real_host["host"],"graphs.php?".
							"hostid=".$real_host["hostid"],
							'action'),
						":",
						$row["name"]
						);
				}
				else
				{
					array_push($description,
						new CSpan("error","on"),
						":",
						expand_trigger_description($row["triggerid"])
						);
				}
				$edit = SPACE;
			}

			$table->AddRow(array(
				$row["graphid"],
				$name,
				$row["width"],
				$row["height"],
				$edit
				));
		}
		$table->show();
	}

?>
<?php
	show_page_footer();
?>

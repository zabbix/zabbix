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

	$page["title"] = "S_CONFIGURATION_OF_GRAPH";
	$page["file"] = "graph.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"graphid"=>	array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,NULL),

		"gitemid"=>	array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,			NULL),
		"itemid"=>	array(T_ZBX_INT, O_OPT,  NULL,	NULL,			'isset({save})'),
		"color"=>	array(T_ZBX_STR, O_OPT,  NULL,	NULL,			'isset({save})'),
		"drawtype"=>	array(T_ZBX_INT, O_OPT,  NULL,	IN("0,1,2,3"),		'isset({save})'),
		"sortorder"=>	array(T_ZBX_INT, O_OPT,  NULL,	BETWEEN(0,65535),	'isset({save})'),
		"yaxisside"=>	array(T_ZBX_INT, O_OPT,  NULL,	IN("0,1"),		'isset({save})'),
		"calc_fnc"=>	array(T_ZBX_INT, O_OPT,	 NULL,	IN("1,2,4,7"),		'isset({save})'),
		"type"=>	array(T_ZBX_INT, O_OPT,	 NULL,	IN("0,1"),		'isset({save})'),
		"periods_cnt"=>	array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(0,360),		'isset({save})'),

		"register"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"delete"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);

	check_fields($fields);
?>
<?php
	show_table_header(S_CONFIGURATION_OF_GRAPH_BIG);
	echo BR;
?>
<?php
	if(!check_right("Graph","R",$_REQUEST["graphid"]))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_page_footer();
		exit;
	}
?>
<?php

	if(isset($_REQUEST["save"]))
	{
		if(isset($_REQUEST["gitemid"]))
		{
			$result=update_graph_item($_REQUEST["gitemid"],$_REQUEST["itemid"],
				$_REQUEST["color"],$_REQUEST["drawtype"],$_REQUEST["sortorder"],
				$_REQUEST["yaxisside"],$_REQUEST["calc_fnc"],$_REQUEST["type"],$_REQUEST["periods_cnt"]);

			$gitemid = $_REQUEST["gitemid"];
			$audit= AUDIT_ACTION_UPDATE;
			$msg_ok = S_ITEM_UPDATED;
			$msg_fail =S_CANNOT_UPDATE_ITEM; 
			$action = "Added";
		}
		else
		{
			$gitemid=add_item_to_graph($_REQUEST["graphid"],$_REQUEST["itemid"],
				$_REQUEST["color"],$_REQUEST["drawtype"],$_REQUEST["sortorder"],
				$_REQUEST["yaxisside"],$_REQUEST["calc_fnc"],$_REQUEST["type"],$_REQUEST["periods_cnt"]);

			$result = $gitemid;
			$audit = AUDIT_ACTION_ADD;
			$msg_ok = S_ITEM_ADDED;
			$msg_fail = S_CANNOT_ADD_ITEM; 
			$action = "Updated";
		}
		if($result)
		{
			$graphitem = get_graphitem_by_gitemid($gitemid);
			$graph = get_graph_by_graphid($graphitem["graphid"]);
			$item = get_item_by_itemid($graphitem["itemid"]);
			add_audit($audit, AUDIT_RESOURCE_GRAPH_ELEMENT,
				"Graph ID [".$graphitem["graphid"]."] Name [".$graph["name"]."]".
				" $action [".$item["description"]."]");
			show_messages($result, $msg_ok, $msg_fail);
			unset($_REQUEST["form"]);
		}
	}
	elseif(isset($_REQUEST["delete"]))
	{
		$graphitem=get_graphitem_by_gitemid($_REQUEST["gitemid"]);
		$graph=get_graph_by_graphid($graphitem["graphid"]);
		$item=get_item_by_itemid($graphitem["itemid"]);

		$result=delete_graph_item($_REQUEST["gitemid"]);
		if($result)
		{
			add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_GRAPH_ELEMENT,
				"Graph ID [".$graphitem["graphid"]."] Name [".$graph["name"]."]".
				" Deleted [".$item["description"]."]");
		}
		show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);
		unset($_REQUEST["gitemid"]);
	}
	elseif(isset($_REQUEST["register"]))
	{
		if($_REQUEST["register"]=="up")
		{
			$result = move_up_graph_item($_REQUEST["gitemid"]);
			show_messages($result, S_SORT_ORDER_UPDATED, S_CANNOT_UPDATE_SORT_ORDER);
			unset($_REQUEST["gitemid"]);
		}
		if($_REQUEST["register"]=="down")
		{
			$result = move_down_graph_item($_REQUEST["gitemid"]);
			show_messages($result, S_SORT_ORDER_UPDATED, S_CANNOT_UPDATE_SORT_ORDER);
			unset($_REQUEST["gitemid"]);
		}
	}
?>
<?php
/****** GRAPH ******/

	$db_graphs = DBselect("select name from graphs where graphid=".$_REQUEST["graphid"]);
	$db_graph = DBfetch($db_graphs);
	show_table_header($db_graph["name"]);//,new CButton("cancel",S_CANCEL,"return Redirect('graphs.php');"));

	$table = new CTable(NULL,"graph");
	$table->AddRow(new CImg("chart2.php?graphid=".$_REQUEST["graphid"]."&period=3600&from=0"));
	$table->Show();

	if(isset($_REQUEST["form"]))
	{
/****** FORM ******/
		echo BR;
		insert_graphitem_form();
	}
	else
	{
/****** TABLE ******/
		$form = new CForm();
		$form->AddVar("graphid",$_REQUEST["graphid"]);
		$form->AddItem(new CButton("form",S_ADD_ITEM));
		show_table_header(S_DISPLAYED_PARAMETERS_BIG,$form);

		$table = new CTableInfo("...");
		$table->SetHeader(array(S_SORT_ORDER,S_HOST,S_PARAMETER,S_FUNCTION,S_TYPE,S_DRAW_STYLE,S_COLOR,S_ACTIONS));

		$result=DBselect("select i.itemid,h.host,i.description,gi.*,i.key_".
			" from hosts h,graphs_items gi,items i where i.itemid=gi.itemid".
			" and gi.graphid=".$_REQUEST["graphid"]." and h.hostid=i.hostid order by gi.sortorder desc");
		while($row=DBfetch($result))
		{

			if($row["type"] == GRAPH_ITEM_AGGREGATED)
			{
				$type = S_AGGREGATED." (".$row["periods_cnt"].")";

				$drawtype = "-";
				$fnc_name = "-";
				$color = "-";
			}
			else
			{
				$type = S_SIMPLE;

				$drawtype = get_drawtype_description($row["drawtype"]);
				$color = $row["color"];

				switch($row["calc_fnc"])
				{
				case CALC_FNC_ALL:	$fnc_name = S_ALL_SMALL;	break;
				case CALC_FNC_MIN:	$fnc_name = S_MIN_SMALL;	break;
				case CALC_FNC_MAX:	$fnc_name = S_MAX_SMALL;	break;
				case CALC_FNC_AVG:
				default:
					$fnc_name = S_AVG_SMALL;	break;
				}
			}
			$table->AddRow(array(
					$row["sortorder"],
					$row["host"],
					NEW CLink(item_description($row["description"],$row["key_"]),
						"chart.php?itemid=".$row["itemid"]."&period=3600&from=0",
						"action"),
					$fnc_name,
					$type,
					$drawtype,
					$color,
					array(
						new CLink(S_CHANGE,"graph.php?graphid=".$_REQUEST["graphid"].
							"&gitemid=".$row["gitemid"]."&form=update#form","action"),
						SPACE."-".SPACE,
						new CLink(S_UP,"graph.php?graphid=".$_REQUEST["graphid"].
							"&gitemid=".$row["gitemid"]."&register=up","action"),
						SPACE."-".SPACE,
						new CLink(S_DOWN,"graph.php?graphid=".$_REQUEST["graphid"].
							"&gitemid=".$row["gitemid"]."&register=down","action")
					)
				));
		}
		$table->Show();
	}
?>
<?php
	show_page_footer();
?>

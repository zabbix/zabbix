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
	require_once "include/config.inc.php";
	require_once "include/graphs.inc.php";
	require_once "include/classes/graph.inc.php";
	
	$page["file"]	= "chart2.php";
	$page["title"]	= "S_CHART";
	$page["type"]	= PAGE_TYPE_IMAGE;

include_once "include/page_header.php";

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"graphid"=>		array(T_ZBX_INT, O_MAND,	P_SYS,	DB_ID,		null),
		"period"=>		array(T_ZBX_INT, O_OPT,		P_NZERO,	BETWEEN(ZBX_MIN_PERIOD,ZBX_MAX_PERIOD),	null),
		"from"=>		array(T_ZBX_INT, O_OPT,		P_NZERO,	null,		null),
		"stime"=>		array(T_ZBX_STR, O_OPT,		P_SYS,		null,		null),
		"border"=>		array(T_ZBX_INT, O_OPT,		P_NZERO,	IN('0,1'),	null),
		"width"=>		array(T_ZBX_INT, O_OPT,		P_NZERO,	'{}>0',		null),
		"height"=>		array(T_ZBX_INT, O_OPT,		P_NZERO,	'{}>0',		null),
	);

	check_fields($fields);
?>
<?php
	if(! (DBfetch(DBselect('select graphid from graphs where graphid='.$_REQUEST['graphid']))) )
	{
		show_error_message(S_NO_GRAPH_DEFINED);

	}

	$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_ONLY, PERM_MODE_LT);
	
	if( !($db_data = DBfetch(DBselect("select g.*,h.host,h.hostid from graphs g left join graphs_items gi on g.graphid=gi.graphid ".
		" left join items i on gi.itemid=i.itemid left join hosts h on i.hostid=h.hostid ".
		" where g.graphid=".$_REQUEST["graphid"].
		" and ( h.hostid not in (".$denyed_hosts.") OR h.hostid is NULL) "))))
	{
		access_deny();
	}

	$graph = new Graph($db_data["graphtype"]);

	if(isset($_REQUEST["period"]))		$graph->SetPeriod($_REQUEST["period"]);
	if(isset($_REQUEST["from"]))		$graph->SetFrom($_REQUEST["from"]);
	if(isset($_REQUEST["stime"]))		$graph->SetSTime($_REQUEST["stime"]);
	if(isset($_REQUEST["border"]))		$graph->SetBorder(0);

	$width = get_request("width", 0);

	if($width <= 0) $width = $db_data["width"];

	$height = get_request("height", 0);
	if($height <= 0) $height = $db_data["height"];

	$graph->ShowWorkPeriod($db_data["show_work_period"]);
	$graph->ShowTriggers($db_data["show_triggers"]);

	$graph->SetWidth($width);
	$graph->SetHeight($height);
	$graph->SetHeader($db_data["host"].":".$db_data['name']);
	$graph->SetYAxisType($db_data["yaxistype"]);
	$graph->SetYAxisMin($db_data["yaxismin"]);
	$graph->SetYAxisMax($db_data["yaxismax"]);

	$result = DBselect("select gi.* from graphs_items gi ".
		" where gi.graphid=".$db_data["graphid"].
		" order by gi.sortorder, gi.itemid desc");

	while($db_data=DBfetch($result))
	{
		$graph->AddItem(
			$db_data["itemid"],
			$db_data["yaxisside"],
			$db_data["calc_fnc"],
			$db_data["color"],
			$db_data["drawtype"],
			$db_data["type"],
			$db_data["periods_cnt"]
			);
	}
	$graph->Draw();
?>
<?php

include_once "include/page_footer.php";

?>

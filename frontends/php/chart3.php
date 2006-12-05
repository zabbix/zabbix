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
		"period"=>	array(T_ZBX_INT, O_OPT,	P_NZERO,	BETWEEN(3600,12*31*24*3600),	null),
		"from"=>	array(T_ZBX_INT, O_OPT,	P_NZERO,	null,			null),
		"stime"=>	array(T_ZBX_INT, O_OPT,	P_NZERO,	null,			null),
		"border"=>	array(T_ZBX_INT, O_OPT,	P_NZERO,	IN('0,1'),		null),
		"name"=>	array(T_ZBX_STR, O_OPT,	NULL,		null,			null),
		"width"=>	array(T_ZBX_INT, O_OPT,	NULL,		BETWEEN(0,65535),	null),
		"height"=>	array(T_ZBX_INT, O_OPT,	NULL,		BETWEEN(0,65535),	null),
		"yaxistype"=>	array(T_ZBX_INT, O_OPT,	NULL,		IN("0,1"),		null),
		"graphtype"=>	array(T_ZBX_INT, O_OPT,	NULL,		IN("0,1"),		null),
		"yaxismin"=>	array(T_ZBX_DBL, O_OPT,	NULL,		BETWEEN(-65535,65535),	null),
		"yaxismax"=>	array(T_ZBX_DBL, O_OPT,	NULL,		BETWEEN(-65535,65535),	null),
		"yaxismax"=>	array(T_ZBX_DBL, O_OPT,	NULL,		BETWEEN(-65535,65535),	null),
		"items"=>	array(T_ZBX_STR, O_OPT,	NULL,		null,			null)
	);

	check_fields($fields);
?>
<?php
	$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_ONLY, PERM_MODE_LT, PERM_RES_IDS_ARRAY);
	
	$items = get_request('items', array());

	asort_by_key($items, 'sortorder');

	foreach($items as $gitem)
	{
		$host = DBfetch(DBselect('select h.* from hosts h,items i where h.hostid=i.hostid and i.itemid='.$gitem['itemid']));
		if(in_array($host['hostid'], $denyed_hosts))
		{
			access_deny();
		}
	}

	$graph = new Graph(get_request("graphtype"	,GRAPH_TYPE_NORMAL));

	$graph->SetHeader($host["host"].":".get_request("name",""));

	if(isset($_REQUEST["period"]))		$graph->SetPeriod($_REQUEST["period"]);
	if(isset($_REQUEST["from"]))		$graph->SetFrom($_REQUEST["from"]);
	if(isset($_REQUEST["stime"]))		$graph->SetSTime($_REQUEST["stime"]);
	if(isset($_REQUEST["border"]))		$graph->SetBorder(0);

	$graph->SetWidth(get_request("width",		900));
	$graph->SetHeight(get_request("height",		200));

	$graph->ShowWorkPeriod(get_request("showworkperiod"	,1));
	$graph->ShowTriggers(get_request("showtriggers"		,1));
	$graph->SetYAxisType(get_request("yaxistype"		,GRAPH_YAXIS_TYPE_CALCULATED));
	$graph->SetYAxisMin(get_request("yaxismin"		,0.00));
	$graph->SetYAxisMax(get_request("yaxismax"		,100.00));

	foreach($items as $gitem)
	{
		$graph->AddItem(
			$gitem["itemid"],
			$gitem["yaxisside"],
			$gitem["calc_fnc"],
			$gitem["color"],
			$gitem["drawtype"],
			$gitem["type"],
			$gitem["periods_cnt"]
			);
	}
	$graph->Draw();
?>
<?php

include_once "include/page_footer.php";

?>

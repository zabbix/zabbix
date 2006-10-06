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
	require_once "include/classes/graph.inc.php";
	
	$page["file"]	= "chart.php";
	$page["title"]	= "S_CHART";
	$page["type"]	= PAGE_TYPE_IMAGE;

include "include/page_header.php";

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"itemid"=>		array(T_ZBX_INT, O_MAND,P_SYS,	DB_ID,		null),
		"period"=>		array(T_ZBX_INT, O_OPT,	null,	BETWEEN(3600,365*24*3600),	null),
		"from"=>		array(T_ZBX_INT, O_OPT,	null,	'{}>=0',	null),
		"width"=>		array(T_ZBX_INT, O_OPT,	null,	'{}>0',		null),
		"height"=>		array(T_ZBX_INT, O_OPT,	null,	'{}>0',		null),
		"border"=>		array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),	null)
	);

	check_fields($fields);
?>
<?php
	if(! ($db_data = DBfetch(DBselect("select i.itemid from items i ".
		" where i.hostid in (".get_accessible_hosts_by_userid($USER_DETAILS['userid'],PERM_READ_ONLY).") ".
		" and i.itemid=".$_REQUEST["itemid"]))))
	{
		access_deny();
	}

	$graph = new Graph();
	
	if(isset($_REQUEST["period"]))		$graph->SetPeriod($_REQUEST["period"]);
	if(isset($_REQUEST["from"]))		$graph->SetFrom($_REQUEST["from"]);
	if(isset($_REQUEST["width"]))		$graph->SetWidth($_REQUEST["width"]);
	if(isset($_REQUEST["height"]))		$graph->SetHeight($_REQUEST["height"]);
	if(isset($_REQUEST["border"]))		$graph->SetBorder(0);
	
	$graph->AddItem($_REQUEST["itemid"], GRAPH_YAXIS_SIDE_RIGHT, CALC_FNC_ALL);

	$graph->Draw();
?>
<?php

include "include/page_footer.php";

?>

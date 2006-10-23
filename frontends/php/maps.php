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

	$page["title"] = "S_NETWORK_MAPS";
	$page["file"] = "maps.php";

	if(isset($_REQUEST["fullscreen"]))
	{
		define('ZBX_PAGE_NO_MENU', 1);
	}

	define('ZBX_PAGE_DO_REFRESH', 1);
	
include_once "include/page_header.php";

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"sysmapid"=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,		NULL),
		"fullscreen"=>		array(T_ZBX_INT, O_OPT,	P_SYS,		IN("0,1"),	NULL)
	);

	check_fields($fields);

?>
<?php
	$_REQUEST["sysmapid"] = get_request("sysmapid",get_profile("web.maps.sysmapid",0));

	$all_maps = array();
	
	$result = DBselect("select sysmapid,name from sysmaps ".
		" where ".DBid2nodeid("sysmapid")."=".$ZBX_CURNODEID.
		" order by name");
	while($row=DBfetch($result))
	{
		if(!sysmap_accessiable($row["sysmapid"],PERM_READ_ONLY))
			continue;

		if(!isset($all_maps[0]))
			$all_maps[0] = $row['sysmapid'];

		$all_maps[$row['sysmapid']] = $row['name'];
	}

	if(isset($_REQUEST["sysmapid"]) && (!isset($all_maps[$_REQUEST["sysmapid"]]) || $_REQUEST["sysmapid"] == 0))
	{
		if(count($all_maps))
		{
			$_REQUEST["sysmapid"] = $all_maps[0];
		}
		else
		{
			unset($_REQUEST["sysmapid"]);
		}
	}
	unset($all_maps[0]);
	
	if(isset($_REQUEST["sysmapid"]))
	{
		update_profile("web.maps.sysmapid",$_REQUEST["sysmapid"]);
	}
?>
<?php
	$text = array(S_NETWORK_MAPS_BIG);
	if(isset($_REQUEST["sysmapid"]))
	{
		$sysmap = get_sysmap_by_sysmapid($_REQUEST["sysmapid"]);

		$url = "maps.php?sysmapid=".$_REQUEST["sysmapid"];
		if(!isset($_REQUEST["fullscreen"]))
			$url .= "&fullscreen=1";

		array_push($text, nbsp(" / "), new CLink($all_maps[$_REQUEST["sysmapid"]],$url));
	}

	$form = new CForm();
	if(isset($_REQUEST["fullscreen"]))
		$form->AddVar("fullscreen",$_REQUEST["fullscreen"]);

	$cmbMaps = new CComboBox("sysmapid",get_request("sysmapid",0),"submit()");
	
	foreach($all_maps as $id => $name)
	{
		$cmbMaps->AddItem($id, $name);
	}
	if($cmbMaps->ItemsCount()>0)
	{	
		$form->AddItem($cmbMaps);
	}

	show_table_header($text,$form);
?>
<?php
	$table = new CTable(S_NO_MAPS_DEFINED,"map");
	if(isset($_REQUEST["sysmapid"]))
	{
		$action_map = get_action_map_by_sysmapid($_REQUEST["sysmapid"]);
		$table->AddRow($action_map);

		$imgMap = new CImg("map.php?noedit=1&sysmapid=".$_REQUEST["sysmapid"]);
		$imgMap->SetMap($action_map->GetName());
		$table->AddRow($imgMap);
	}
	$table->Show();
?>
<?php

include_once "include/page_footer.php";

?>

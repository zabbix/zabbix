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

	$_REQUEST["fullscreen"] = get_request("fullscreen", 0);

	show_header($page["title"],1, $_REQUEST["fullscreen"] > 0 ? 1 : 0);

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

	if($_REQUEST["sysmapid"] <=0 )
	{
		$db_sysmaps = DBselect("select sysmapid,name from sysmaps where mod(sysmapid,100)=$ZBX_CURNODEID order by name");
		if($sysmap = DBfetch($db_sysmaps))
		{
			$_REQUEST["sysmapid"] = $sysmap["sysmapid"];
		}
	}

	update_profile("web.maps.sysmapid",$_REQUEST["sysmapid"]);

	if($_REQUEST["sysmapid"] > 0 && !check_right("Network map","R",$_REQUEST["sysmapid"]))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_page_footer();
		exit;
	}
?>


<?php
	$text = array(S_NETWORK_MAPS_BIG);
	if($_REQUEST["sysmapid"] > 0)
	{
		$sysmap = get_sysmap_by_sysmapid($_REQUEST["sysmapid"]);

		$url = "maps.php?sysmapid=".$_REQUEST["sysmapid"];
		if($_REQUEST["fullscreen"]==0)
			$url .= "&fullscreen=1";

		array_push($text, nbsp(" / "), new CLink($sysmap["name"],$url));
	}

	$form = new CForm();
	if($_REQUEST["fullscreen"]>=1)
		$form->AddVar("fullscreen",$_REQUEST["fullscreen"]);

	$cmbMaps = new CComboBox("sysmapid",$_REQUEST["sysmapid"],"submit()");
	$result=DBselect("select sysmapid,name from sysmaps where mod(sysmapid,100)=$ZBX_CURNODEID order by name");
	while($row=DBfetch($result))
	{
		if(!check_right("Network map","R",$row["sysmapid"]))		continue;
		$cmbMaps->AddItem($row["sysmapid"],$row["name"]);
	}
	$form->AddItem($cmbMaps);

	show_header2($text,$form);
?>

<?php
	$table = new CTable(NULL,"map");
	if($_REQUEST["sysmapid"] > 0)
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
	show_page_footer();
?>

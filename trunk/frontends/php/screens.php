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
	require_once "include/screens.inc.php";


	$page["title"] = "S_CUSTOM_SCREENS";
	$page["file"] = "screens.php";

	$_REQUEST["fullscreen"] = get_request("fullscreen", 0);

	if($_REQUEST["fullscreen"])
	{
		define('ZBX_PAGE_NO_MENU', 1);
	}
	define('ZBX_PAGE_DO_REFRESH', 1);
	
include_once "include/page_header.php";

?>

<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"screenid"=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,NULL),
		"dec"=>			array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"inc"=>			array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"from"=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"left"=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"right"=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"period"=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"stime"=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	NULL,NULL),
		"action"=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	IN("'go'"),NULL),
		"reset"=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	IN("'reset'"),NULL),
		"fullscreen"=>		array(T_ZBX_INT, O_OPT,	P_SYS,		IN("0,1"),		NULL)
	);

	check_fields($fields);
?>

<?php
	$_REQUEST["screenid"] = get_request("screenid",get_profile("web.screens.screenid", null));
	$_REQUEST["fullscreen"] = get_request("fullscreen", 0);

	update_profile("web.screens.screenid",$_REQUEST["screenid"]);
?>

<?php
	$text = array(S_SCREENS_BIG);

	$screenid = get_request('screenid', null);
	if($screenid <= 0) $screenid = null;

	if(isset($screenid))
	{
		$screen = get_screen_by_screenid($screenid);
		if($screen) {
			$url = "screens.php?screenid=".$screenid;
			if($_REQUEST["fullscreen"]==0) $url .= "&fullscreen=1";
			array_push($text,nbsp(" / "),new CLink($screen["name"], $url));
		}
		else
		{
			$screenid = null;
			update_profile("web.screens.screenid",0);
		}
	}
	$form = new CForm();
	$form->AddVar("fullscreen",$_REQUEST["fullscreen"]);

	$cmbScreens = new CComboBox("screenid",$screenid,"submit()");
	unset($screen_correct);
	unset($first_screen);

	$result = DBselect("select screenid,name from screens where ".DBid2nodeid("screenid")."=".$ZBX_CURNODEID." order by name");
	while($row=DBfetch($result))
	{
		if(!screen_accessiable($row["screenid"], PERM_READ_ONLY))
			continue;

		$cmbScreens->AddItem($row["screenid"],$row["name"]);
		if($screenid == $row["screenid"]) $screen_correct = 1;
		if(!isset($first_screen)) $first_screen = $row["screenid"];
	}

	if(!isset($screen_correct) && isset($first_screen))
	{
		$screenid = $first_screen;
	}

	if(isset($screenid))
	{
		if(!screen_accessiable($screenid, PERM_READ_ONLY))
			access_deny();
	}
			
	if($cmbScreens->ItemsCount() > 0)
		$form->AddItem($cmbScreens);

	show_table_header($text,$form);
?>
<?php
	if(isset($screenid))
	{
		SDI();
		$effectiveperiod = navigation_bar_calc();
		$table = get_screen($screenid, 0, $effectiveperiod);
		$table->Show();
		
		navigation_bar("screens.php");
	}
	else
	{
		echo unpack_object(new CTableInfo(S_NO_SCREENS_DEFINED));
	}
?>
<?php

include_once "include/page_footer.php";

?>

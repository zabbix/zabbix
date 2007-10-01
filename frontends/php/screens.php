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

	$_REQUEST['config'] = get_request('config',get_profile('web.screens.config',0));

	if( 1 != $_REQUEST['config'])
		define('ZBX_PAGE_DO_REFRESH', 1);
	
include_once "include/page_header.php";

?>

<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"config"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1"),	null), // 0 - screens, 1 - slides

		"elementid"=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,NULL),
		"step"=>		array(T_ZBX_INT, O_OPT,  P_SYS,		BETWEEN(0,65535),NULL),
		"dec"=>			array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"inc"=>			array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"from"=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"left"=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"right"=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		"period"=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(ZBX_MIN_PERIOD,ZBX_MAX_PERIOD),NULL),
		"stime"=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	NULL,NULL),
		"action"=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	IN("'go'"),NULL),
		"reset"=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	IN("'reset'"),NULL),
		"fullscreen"=>		array(T_ZBX_INT, O_OPT,	P_SYS,		IN("0,1,2"),		NULL)
	);

	check_fields($fields);

	$config = $_REQUEST['config'] = get_request('config', 0);

	if( 2 != $_REQUEST["fullscreen"] )
		update_profile('web.screens.config', $_REQUEST['config']);

?>

<?php
	$_REQUEST["elementid"] = get_request("elementid",get_profile("web.screens.elementid", null));
	$_REQUEST["fullscreen"] = get_request("fullscreen", 0);

	if( 2 != $_REQUEST["fullscreen"] )
		update_profile("web.screens.elementid",$_REQUEST["elementid"]);

	$_REQUEST["period"] = get_request('period',get_profile('web.screens'.$_REQUEST['elementid'].'.period', ZBX_PERIOD_DEFAULT));
	if($_REQUEST["period"] >= ZBX_MIN_PERIOD)
	{
		update_profile('web.screens'.$_REQUEST['elementid'].'.period',$_REQUEST['period']);
	}
?>

<?php
	$text = array(S_SCREENS_BIG);

	$elementid = get_request('elementid', null);
	if($elementid <= 0) $elementid = null;

	$form = new CForm();
	$form->SetMethod('get');
	
	$form->AddVar("fullscreen",$_REQUEST["fullscreen"]);

	$cmbConfig = new CComboBox('config', $config, 'submit()');
	$cmbConfig->AddItem(0, S_SCREENS);
	$cmbConfig->AddItem(1, S_SLIDESHOWS);

	$form->AddItem($cmbConfig);

	$cmbElements = new CComboBox("elementid",$elementid,"submit()");
	unset($screen_correct);
	unset($first_screen);

	if( 0 == $config )
	{
		$result = DBselect('select screenid as elementid,name '.
				' from screens '.
				' where '.DBin_node('screenid').
				' order by name'
				);
		while($row=DBfetch($result))
		{
			if(!screen_accessiable($row["elementid"], PERM_READ_ONLY))
				continue;

			$cmbElements->AddItem(
					$row['elementid'],
					get_node_name_by_elid($row['elementid']).$row["name"]
					);
			if($elementid == $row["elementid"]) $element_correct = 1;
			if(!isset($first_element)) $first_element = $row["elementid"];
		}
	}
	else
	{
		$result = DBselect('select slideshowid as elementid,name '.
				' from slideshows '.
				' where '.DBin_node('slideshowid').
				' order by name'
				);
		while($row=DBfetch($result))
		{
			if(!slideshow_accessiable($row["elementid"], PERM_READ_ONLY))
				continue;

			$cmbElements->AddItem(
					$row['elementid'],
					get_node_name_by_elid($row['elementid']).$row['name']
					);
			if($elementid == $row["elementid"]) $element_correct = 1;
			if(!isset($first_element)) $first_element = $row["elementid"];
		}
	}

	if(!isset($element_correct) && isset($first_element))
	{
		$elementid = $first_element;
	}

	if(isset($elementid))
	{
		if( 0 == $config )
		{
			if(!screen_accessiable($elementid, PERM_READ_ONLY))
				access_deny();
			$element = get_screen_by_screenid($elementid);
		}
		else
		{
			if(!slideshow_accessiable($elementid, PERM_READ_ONLY))
				access_deny();
			$element = get_slideshow_by_slideshowid($elementid);
		}

		if( $element ) {
			$url = "?elementid=".$elementid;
			if($_REQUEST["fullscreen"]==0) $url .= "&fullscreen=1";
			$text[] = array(nbsp(" / "),new CLink($element["name"], $url));
		}
		else
		{
			$elementid = null;
			update_profile("web.screens.elementid",0);
		}
	}
			
	if($cmbElements->ItemsCount() > 0)
		$form->AddItem($cmbElements);

	if( 2 != $_REQUEST["fullscreen"] )
		show_table_header($text,$form);
?>
<?php
	if(isset($elementid))
	{
		$effectiveperiod = navigation_bar_calc();
		if( 0 == $config )
		{
			$element = get_screen($elementid, 0, $effectiveperiod);
		}
		else
		{
			$element = get_slideshow($elementid, get_request('step', null), $effectiveperiod);
		}
		if($element) $element->Show();
		
		$_REQUEST['elementid'] = $elementid;

		if( 2 != $_REQUEST["fullscreen"] )
			navigation_bar("screens.php",array('config','elementid'));
	}
	else
	{
		echo unpack_object(new CTableInfo(
					0 == $config ?
						S_NO_SCREENS_DEFINED :
						S_NO_SLIDESHOWS_DEFINED
					));
	}
?>
<?php

include_once "include/page_footer.php";

?>

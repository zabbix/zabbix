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
	require_once "include/hosts.inc.php";
	require_once "include/items.inc.php";

	$page["title"] = "S_LATEST_VALUES";
	$page["file"] = "latest.php";
	$page['hist_arg'] = array('groupid','hostid','show','select','open','applicationid');
	$page['scripts'] = array('updater.js');
	
	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	
	define('ZBX_PAGE_MAIN_HAT','hat_latest');
	define('ZBX_PAGE_DO_JS_REFRESH', 1);
	
include_once "include/page_header.php";
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"applications"=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID,		NULL),
		"applicationid"=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID,		NULL),
		"close"=>		array(T_ZBX_INT, O_OPT,	NULL,	IN("1"),	NULL),
		"open"=>		array(T_ZBX_INT, O_OPT,	NULL,	IN("1"),	NULL),
		"groupbyapp"=>		array(T_ZBX_INT, O_OPT,	NULL,	IN("1"),	NULL),

		"groupid"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		NULL),
		"hostid"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		NULL),
		"select"=>		array(T_ZBX_STR, O_OPT, NULL,	NULL,		NULL),

		"show"=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,		NULL),
		'fullscreen'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		NULL),
		
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			'isset({favid})'),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		NULL),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("hat"=={favobj})'),
	);

	check_fields($fields);
	
/* AJAX	*/
	if(isset($_REQUEST['favobj'])){
		if('hat' == $_REQUEST['favobj']){
			update_profile('web.latest.hats.'.$_REQUEST['favid'].'.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
		if('refresh' == $_REQUEST['favobj']){
			switch($_REQUEST['favid']){
				case ZBX_PAGE_MAIN_HAT:
					include_once('blocks/latest.page.php');
					break;
			}
		}
	}	

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}
//--------


// Refresh tab
	$refresh_tab = array(
		array('id'	=> ZBX_PAGE_MAIN_HAT,
				'interval' 	=> $USER_DETAILS["refresh"],
				'url'	=>	zbx_empty($_SERVER['QUERY_STRING'])?'':'?'.$_SERVER['QUERY_STRING'],
			)
	);
	

	$url = '?fullscreen='.($_REQUEST['fullscreen']?'0':'1').url_param('select');

	$fs_icon = new CDiv(SPACE,'fullscreen');
	$fs_icon->AddOption('title',$_REQUEST['fullscreen']?S_NORMAL.' '.S_VIEW:S_FULLSCREEN);
	$fs_icon->AddAction('onclick',new CScript("javascript: document.location = '".$url."';"));


	$latest_hat = create_hat(
			S_LATEST_DATA_BIG,
			new CSpan(S_LOADING_P,'textcolorstyles'),
			array($fs_icon),
			ZBX_PAGE_MAIN_HAT,
			get_profile('web.latest.hats.hat_latest.state',1)
	);

	$latest_hat->Show();
	
	add_refresh_objects($refresh_tab);
?>
<?php
include_once "include/page_footer.php";
?>

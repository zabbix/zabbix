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
	require_once "include/actions.inc.php";

	$page["title"] = "S_LATEST_ACTIONS";
	$page["file"] = "actions.php";
	$page['hist_arg'] = array('prev','next');
	
	define('ZBX_PAGE_DO_REFRESH', 1);

include_once "include/page_header.php";
	
	define("PAGE_SIZE", 100);
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"groupid"=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,	NULL),
		"hostid"=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,	NULL),
		"start"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(0,65535)."({}%".PAGE_SIZE."==0)",	NULL),
		"next"=>		array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			NULL),
		"prev"=>		array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			NULL),
		
// filter
		"filter_rst"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
		"filter_set"=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,	NULL),
		
		"recipient"=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
		
		'filter_timesince'=>	array(T_ZBX_INT, O_OPT,	P_UNSET_EMPTY,	null,	NULL),
		'filter_timetill'=>	array(T_ZBX_INT, O_OPT,	P_UNSET_EMPTY,	null,	NULL),

//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("filter"=={favobj})'),
	);

	check_fields($fields);
	validate_sort_and_sortorder();
	
/* AJAX */	
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			update_profile('web.actions.filter.state',$_REQUEST['state']);
		}
	}	

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}
//--------

/* FILTER */
	if(isset($_REQUEST['filter_rst'])){
		$_REQUEST['triggerid'] = 0;
		$_REQUEST['show_unknown'] = 0;
		
		$_REQUEST['filter_timesince'] = 0;
		$_REQUEST['filter_timetill'] = 0;
	}
	
	$_REQUEST['triggerid'] = get_request('triggerid',get_profile('web.events.filter.triggerid',0));
	$show_unknown = get_request('show_unknown',get_profile('web.events.filter.show_unknown',0));
	
	$_REQUEST['filter_timesince'] = get_request('filter_timesince',get_profile('web.events.filter.timesince',0));
	$_REQUEST['filter_timetill'] = get_request('filter_timetill',get_profile('web.events.filter.timetill',0));
	
	if(($_REQUEST['filter_timetill'] > 0) && ($_REQUEST['filter_timesince'] > $_REQUEST['filter_timetill'])){
		$tmp = $_REQUEST['filter_timesince'];
		$_REQUEST['filter_timesince'] = $_REQUEST['filter_timetill'];
		$_REQUEST['filter_timetill'] = $tmp;
	}
	
	if(isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst'])){
		update_profile('web.events.filter.triggerid',$_REQUEST['triggerid']);
		update_profile('web.events.filter.show_unknown',$show_unknown);
		
		update_profile('web.events.filter.timesince',$_REQUEST['filter_timesince']);
		update_profile('web.events.filter.timetill',$_REQUEST['filter_timetill']);
	}
// --------------

?>
<?php
	
	$_REQUEST["start"] = get_request("start", 0);

	if(isset($_REQUEST["prev"]))
	{
		$_REQUEST["start"]	-= PAGE_SIZE;
	}
	if(isset($_REQUEST["next"]))
	{
		$_REQUEST["start"]	+= PAGE_SIZE;
	}

	if($_REQUEST["start"] < 0) $_REQUEST["start"] = 0;
?>
<?php
	$table = get_history_of_actions($_REQUEST["start"], PAGE_SIZE);
	
	$form = new CForm();
	$form->SetMethod('get');
	
	$form->AddVar("start",$_REQUEST["start"]);

	$btnPrev = new CButton("prev","<< Prev ".PAGE_SIZE);
	if($_REQUEST["start"] <= 0)
		$btnPrev->SetEnabled('no');
	$form->AddItem($btnPrev);

	$btnNext = new CButton("next","Next ".PAGE_SIZE." >>");
	if($table->GetNumRows() < PAGE_SIZE)
		$btnNext->SetEnabled('no');
	$form->AddItem($btnNext);

	show_table_header(S_HISTORY_OF_ACTIONS_BIG,$form);
	$table->Show();
?>
<?php

include_once "include/page_footer.php";

?>

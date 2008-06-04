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
	require_once "include/acknow.inc.php";
	require_once "include/actions.inc.php";
	require_once "include/events.inc.php";
	require_once "include/triggers.inc.php";
	require_once "include/users.inc.php";
	require_once "include/html.inc.php";

	$page["title"]		= "S_EVENT_DETAILS";
	$page["file"]		= "tr_events.php";
	$page['hist_arg'] = array('triggerid','eventid');
	$page['scripts'] = array('calendar.js');
	
	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	
	include_once "include/page_header.php";
?>
<?php
	define('PAGE_SIZE',	100);
	
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'triggerid'=>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		PAGE_TYPE_HTML.'=='.$page['type']),
		'eventid'=>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		PAGE_TYPE_HTML.'=='.$page['type']),
		
/* actions */
		"save"=>		array(T_ZBX_STR,O_OPT,	P_ACT|P_SYS, null,	null),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		
// ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	IN("'filter','hat'"),		NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})'),
	);
	
	check_fields($fields);

/* AJAX */	
	if(isset($_REQUEST['favobj'])){
		if('hat' == $_REQUEST['favobj']){
			update_profile('web.tr_events.hats.'.$_REQUEST['favid'].'.state',$_REQUEST['state']);
		}
	}	

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}
//--------

	if(!check_right_on_trigger_by_triggerid(PERM_READ_ONLY, $_REQUEST["triggerid"]))
		access_deny();

	$trigger_data = DBfetch(DBselect('SELECT h.host, t.* '.
						' FROM hosts h, items i, functions f, triggers t '.
	                   	' WHERE i.itemid=f.itemid '.
							' AND f.triggerid=t.triggerid '.
							' AND t.triggerid='.$_REQUEST["triggerid"].
							' AND h.hostid=i.hostid '.
							' AND '.DBin_node('t.triggerid')));
?>
<?php
	
	$trigger_data['exp_expr'] = explode_exp($trigger_data["expression"],1);
	$trigger_data['exp_desc'] =  expand_trigger_description_by_data($trigger_data);
	
	show_table_header(array(S_EVENTS_BIG.': "'.$trigger_data['exp_desc'].'"'), null);

$left_tab = new CTable();
$left_tab->SetCellPadding(3);
$left_tab->SetCellSpacing(3);

$left_tab->AddOption('border',0);

$left_tab->AddRow(create_hat(
			S_EVENT.SPACE.S_SOURCE.SPACE.S_DETAILS,
			make_trigger_details($_REQUEST['triggerid'],$trigger_data),//null,
			null,
			'hat_triggerdetails',
			get_profile('web.tr_events.hats.hat_triggerdetails.state',1)
		));
		
$left_tab->AddRow(create_hat(
			S_EVENT_DETAILS,
			make_event_details($_REQUEST['eventid']),//null,
			null,
			'hat_eventdetails',
			get_profile('web.tr_events.hats.hat_eventdetails.state',1)
		));
		

$right_tab = new CTable();
$right_tab->SetCellPadding(3);
$right_tab->SetCellSpacing(3);

$right_tab->AddOption('border',0);


$right_tab->AddRow(create_hat(
			S_ACKNOWLEDGES,
			make_acktab_by_eventid($_REQUEST['eventid']),//null,
			null,
			'hat_eventack',
			get_profile('web.tr_events.hats.hat_eventack.state',1)
		));
		
$right_tab->AddRow(create_hat(
			S_MESSAGE_ACTIONS,
			get_action_msgs_for_event($_REQUEST['eventid']),//null,
			null,
			'hat_eventactionmsgs',
			get_profile('web.tr_events.hats.hat_eventactionmsgs.state',1)
		));

$right_tab->AddRow(create_hat(
			S_COMMAND_ACTIONS,
			get_action_cmds_for_event($_REQUEST['eventid']),//null,
			null,
			'hat_eventactioncmds',
			get_profile('web.tr_events.hats.hat_eventactioncmds.state',1)
		));

$right_tab->AddRow(create_hat(
			S_EVENTS.SPACE.S_LIST.SPACE.'['.S_LAST.' 20]', 
			//null,//
			make_small_eventlist($_REQUEST['triggerid'],$trigger_data),
			null,
			'hat_eventlist',
			get_profile('web.tr_events.hats.hat_eventlist.state',1)
		));


$td_l = new CCol($left_tab);
$td_l->AddOption('valign','top');

$td_r = new CCol($right_tab);
$td_r->AddOption('valign','top');

$outer_table = new CTable();
$outer_table->AddOption('border',0);
$outer_table->SetCellPadding(1);
$outer_table->SetCellSpacing(1);
$outer_table->AddRow(array($td_l,$td_r));

$outer_table->Show();
?>
<?php

include_once "include/page_footer.php";

?>

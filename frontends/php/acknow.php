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
	require_once "include/triggers.inc.php";
	require_once "include/forms.inc.php";

	$page["title"]	= "S_ACKNOWLEDGES";
	$page["file"]	= "acknow.php";
	$page['hist_arg'] = array('eventid');
	
include_once "include/page_header.php";

?>
<?php
	$bulk = isset($_REQUEST['bulkacknowledge']);

//		VAR				TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'eventid'=>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,				null),
		'events'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,				null),
		'message'=>		array(T_ZBX_STR, O_OPT,	NULL,	$bulk ? NULL : NOT_EMPTY,	'isset({save})||isset({saveandreturn})'),
	/* actions */
		'bulkacknowledge'=> array(T_ZBX_STR,O_OPT,	P_ACT|P_SYS, NULL,	NULL),
		"saveandreturn" =>	array(T_ZBX_STR,O_OPT,	P_ACT|P_SYS, NULL,	NULL),
		"save"=>			array(T_ZBX_STR,O_OPT,	P_ACT|P_SYS, NULL,	NULL),
		"cancel"=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null)
	);
	check_fields($fields);
	
	if(!isset($_REQUEST['events']) && !isset($_REQUEST['eventid'])){
		show_message(S_NO_EVENTS_TO_ACKNOWLEDGE);
		include_once("include/page_footer.php");
	}
	
	if(isset($_REQUEST['eventid'])){
		$events[$_REQUEST['eventid']] = $_REQUEST['eventid'];
	}
	else{
		$events = $_REQUEST['events'];
	}

//$bulk = (count($events) > 1);
?>
<?php
	$available_triggers = get_accessible_triggers(PERM_READ_ONLY, PERM_RES_IDS_ARRAY, get_current_nodeid());
		
	$db_data = DBfetch(DBselect('SELECT COUNT(DISTINCT  e.eventid) as cnt'.
			' FROM events e'.
			' WHERE '.DBcondition('e.eventid',$events).
				' AND '.DBcondition('e.objectid',$available_triggers).
				' AND e.object='.EVENT_OBJECT_TRIGGER.
				' AND '.DBin_node('e.eventid')
			));
			
	if($db_data['cnt'] != count($events)){
		access_deny();
	}
	
	$db_data = DBfetch(DBselect('SELECT DISTINCT  e.*,t.triggerid,t.expression,t.description,t.expression,h.host,h.hostid '.
		' FROM hosts h, items i, functions f, events e, triggers t'.
		' WHERE h.hostid=i.hostid '.
			' AND i.itemid=f.itemid '.
			' AND f.triggerid=t.triggerid '.
			' AND '.DBcondition('e.eventid',$events).
			' AND e.object='.EVENT_OBJECT_TRIGGER.
			' AND e.objectid=t.triggerid '.
			' AND '.DBcondition('t.triggerid',$available_triggers).
			' AND '.DBin_node('e.eventid')
			));

	if(isset($_REQUEST['save']) && !$bulk){
		$result = add_acknowledge_coment(
			$db_data['eventid'],
			$USER_DETAILS['userid'],
			$_REQUEST['message']);

		show_messages($result, S_COMMENT_ADDED, S_CANNOT_ADD_COMMENT);
		
		if($result){
			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_TRIGGER, S_ACKNOWLEDGE_ADDED.
				' ['.expand_trigger_description_by_data($db_data).']'.
				' ['.$_REQUEST["message"].']');
		}
	}
	else if(isset($_REQUEST["saveandreturn"])){
		$result = true;
		if($bulk) {
			$_REQUEST['message'] .= ($_REQUEST['message'] != ''? "\n\r" : '').S_SYS_BULK_ACKNOWLEDGE;
		}
		
		foreach($events as $id => $eventid){
			$result &= add_acknowledge_coment(
						$eventid,
						$USER_DETAILS['userid'],
						$_REQUEST['message']);
		}

		if($result){
			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_TRIGGER, S_ACKNOWLEDGE_ADDED.
				' ['.($bulk)?(' BULK ACKNOWLEDGE '):(expand_trigger_description_by_data($db_data)).']'.
				' ['.$_REQUEST['message'].']');
		}
		
		Redirect('tr_status.php?hostid='.get_profile('web.tr_status.hostid',0));
		exit;
	}
	else if(isset($_REQUEST['cancel'])){
		Redirect('tr_status.php?hostid='.get_profile('web.tr_status.hostid',0));
		exit;
	}
?>
<?php
	$msg=($bulk)?(' BULK ACKNOWLEDGE '):(array('"'.expand_trigger_description_by_data($db_data).'"',BR(),explode_exp($db_data["expression"],1)));
	show_table_header(array(S_ALARM_ACKNOWLEDGES_BIG,' : ',$msg));

	echo SBR;
	if(!$bulk){
		$table = new CTable(NULL,"ack_msgs");
		$table->SetAlign("center");

		$db_acks = get_acknowledges_by_eventid($db_data["eventid"]);
		while($db_ack = DBfetch($db_acks))
		{
			$db_user = get_user_by_userid($db_ack["userid"]);
			$table->AddRow(array(
				new CCol($db_user["alias"],"user"),
				new CCol(date("d-m-Y h:i:s A",$db_ack["clock"]),"time")),
				"title");
	
			$msgCol = new CCol(nl2br($db_ack["message"]));
			$msgCol->SetColspan(2);
			$table->AddRow($msgCol,"msg");
		}
/**/
		if($table->GetNumRows() > 0){
			$table->Show();
			echo SBR;
		}
	}
	
	insert_new_message_form($events,$bulk);
?>

<?php

include_once "include/page_footer.php";

?>

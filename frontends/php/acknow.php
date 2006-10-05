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

include "include/page_header.php";

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"eventid"=>		array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		NULL),
		"message"=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,	'isset({save})'),

	/* actions */
		"save"=>		array(T_ZBX_STR,O_OPT,	P_ACT|P_SYS, NULL,	NULL),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null)
	);
	check_fields($fields);
?>
<?php
	$denyed_hosts = get_accessible_hosts_by_userid($USER_DETAILS['userid'],PERM_READ_LIST, PERM_MODE_LE);
	
	if(! ($db_data = DBfetch(DBselect('select * from items i, functions f, events e '.
	                        ' where i.itemid=f.itemid and f.triggerid=e.triggerid and e.eventid='.$_REQUEST["eventid"].
				" and i.hostid not in (".$denyed_hosts.")".
				" and ".DBid2nodeid("e.eventid")."=".$ZBX_CURNODEID
				))))
	{
		access_deny();
	}
	$trigger_hostid = $db_data['hostid'];

	if(isset($_REQUEST["save"]))
	{
		$result = add_acknowledge_coment(
			$_REQUEST["eventid"],
			$USER_DETAILS["userid"],
			$_REQUEST["message"]);

		show_messages($result, S_COMMENT_ADDED, S_CANNOT_ADD_COMMENT);
	}
	else if(isset($_REQUEST["cancel"]))
	{
		Redirect('tr_status.php?hostid='.$trigger_hostid);
		exit;
	}
?>
<?php

	$event 		= get_event_by_eventid($_REQUEST["eventid"]);
	$trigger	= get_trigger_by_triggerid($event["triggerid"]);
	$expression	= explode_exp($trigger["expression"],1);
	$description	= expand_trigger_description($event["triggerid"]);

	show_table_header(S_ALARM_ACKNOWLEDGES_BIG." : \"".$description."\"".BR.$expression);

	echo BR;
	$table = new CTable(NULL,"ack_msgs");
	$table->SetAlign("center");

	$db_acks = get_acknowledges_by_eventid($_REQUEST["eventid"]);
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
	if($table->GetNumRows() > 0)
	{
		$table->Show();
		echo BR;
	}
	insert_new_message_form();
?>

<?php

include "include/page_footer.php";

?>

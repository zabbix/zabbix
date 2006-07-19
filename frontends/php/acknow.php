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
	$page["title"]="S_ACKNOWLEDGES";
	$page["file"]="acknow.php";
	$page["menu.url"] = "tr_status.php";

	include "include/config.inc.php";
	include "include/forms.inc.php";
?>
<?php
	show_header($page["title"],0,0);
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"alarmid"=>		array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		NULL),
		"message"=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,	'isset({save})'),

		"save"=>		array(T_ZBX_STR,O_OPT,	P_ACT|P_SYS, NULL,	NULL)
	);
	check_fields($fields);
?>
<?php
	if(isset($_REQUEST["save"]))
	{
		$result = add_acknowledge_coment(
			$_REQUEST["alarmid"],
			$USER_DETAILS["userid"],
			$_REQUEST["message"]);

		show_messages($result, S_COMMENT_ADDED, S_CANNOT_ADD_COMMENT);
	}
?>
<?php

	$alarm = get_alarm_by_alarmid($_REQUEST["alarmid"]);
	$trigger=get_trigger_by_triggerid($alarm["triggerid"]);
	$expression=explode_exp($trigger["expression"],1);
	$description=expand_trigger_description($alarm["triggerid"]);

	show_table_header(S_ALARM_ACKNOWLEDGES_BIG.":".$description.BR.$expression);

	echo BR;
	$table = new CTable(NULL,"ack_msgs");
	$table->SetAlign("center");

	$db_acks = get_acknowledges_by_alarmid($_REQUEST["alarmid"]);
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
	$table->Show();
	echo BR;
	insert_new_message_form();
?>

<?php
	show_page_footer();
?>

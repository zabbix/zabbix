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
	require_once "include/triggers.inc.php";
	require_once "include/forms.inc.php";

	$page["title"] = "S_TRIGGER_COMMENTS";
	$page["file"] = "tr_comments.php";

include_once "include/page_header.php";

?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"triggerid"=>	array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID, null),
		"comments"=>	array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY,'isset({save})'),

/* actions */
		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
/* other */
/*
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		"form_copy_to"=>	array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	null,	null,	null)
*/
	);

	check_fields($fields);
?>
<?php
	$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY, PERM_MODE_LT);

	if(! ($db_data = DBfetch(DBselect('select * from items i, functions f '.
	                        ' where i.itemid=f.itemid and f.triggerid='.$_REQUEST["triggerid"].
				" and i.hostid not in (".$denyed_hosts.")".
				' and '.DBin_node('f.triggerid')
				))))
	{
		access_deny();
	}
	$trigger_hostid = $db_data['hostid'];
	
	if(isset($_REQUEST["save"]))
	{
		$result = update_trigger_comments($_REQUEST["triggerid"],$_REQUEST["comments"]);
	
		show_messages($result, S_COMMENT_UPDATED, S_CANNOT_UPDATE_COMMENT);

		if($result)
		{
			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_TRIGGER,
				S_TRIGGER." [".$_REQUEST["triggerid"]."] [".expand_trigger_description($_REQUEST["triggerid"])."] ".
				S_COMMENTS." [".$_REQUEST["comments"]."]");
		}
	}
	else if(isset($_REQUEST["cancel"]))
	{
		Redirect('tr_status.php?hostid='.$trigger_hostid);
		exit;
		
	}
?>
<?php
	show_table_header(S_TRIGGER_COMMENTS_BIG);
	echo BR;
	insert_trigger_comment_form($_REQUEST["triggerid"]);
?>
<?php

include_once "include/page_footer.php";

?>

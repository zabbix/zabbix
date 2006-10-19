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
	require_once "include/forms.inc.php";

        $page["title"] = "S_NODES";
        $page["file"] = "nodes.php";

include_once "include/page_header.php";

	insert_confirm_javascript();
?>
<?php
	$fields=array(
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION

// media form
		"nodeid"=>		array(T_ZBX_INT, O_NO,	null,	DB_ID,		'{form}=="update"'),
/* actions */
		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"delete"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
/* other */
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);
	
	check_fields($fields);
	
	$accessible_nodes = get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_LIST);

	if(isset($_REQUEST["nodeid"]) && !in_array($_REQUEST["nodeid"], explode(',',$accessible_nodes)))
	{
		access_deny();
	}
?>
<?php
	if(isset($_REQUEST["form"]))
	{
		insert_node_form();
	}
	else
	{
		$form = new CForm();
		$form->AddItem(new CButton('form',S_NEW_NODE));
		show_table_header(S_NODES_BIG,$form);

		$table=new CTableInfo(S_NO_NODES_DEFINED);
		$table->SetHeader(array(S_NAME));

		$db_nodes = DBselect('select * from nodes where nodeid in ('.
			get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_LIST).') '.
			' order by name ');
		while($row=DBfetch($db_nodes))
		{

			$table->AddRow(array(
				new CLink($row["name"],"?&form=update&nodeid=".$row["nodeid"],'action'),
				));
		}
		$table->Show();
	}
?>
<?php

include_once "include/page_footer.php";

?>

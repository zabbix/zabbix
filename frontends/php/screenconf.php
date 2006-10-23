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
	require_once "include/screens.inc.php";
	require_once "include/forms.inc.php";

	$page["title"] = "S_SCREENS";
	$page["file"] = "screenconf.php";

include_once "include/page_header.php";
	
	insert_confirm_javascript();
?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"screenid"=>	array(T_ZBX_INT, O_NO,	 P_SYS,	DB_ID,		'{form}=="update"'),
		"name"=>	array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,	'isset({save})'),
		"hsize"=>	array(T_ZBX_INT, O_OPT,  NULL,  BETWEEN(1,100),	'isset({save})'),
		"vsize"=>	array(T_ZBX_INT, O_OPT,  NULL,  BETWEEN(1,100),	'isset({save})'),

		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"delete"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);

	check_fields($fields);
?>
<?php
	if(isset($_REQUEST["screenid"]))
	{
		if(!screen_accessiable($_REQUEST["screenid"], PERM_READ_WRITE))
			access_deny();
	}
	
	if(isset($_REQUEST["save"]))
	{
		if(isset($_REQUEST["screenid"]))
		{
			// TODO check permission by new value.
			$result=update_screen($_REQUEST["screenid"],
				$_REQUEST["name"],$_REQUEST["hsize"],$_REQUEST["vsize"]);
			$audit_action = AUDIT_ACTION_UPDATE;
			show_messages($result, S_SCREEN_UPDATED, S_CANNOT_UPDATE_SCREEN);
		} else {
			if(count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_MODE_LT,PERM_RES_IDS_ARRAY,$ZBX_CURNODEID)))
				access_deny();

			$result=add_screen($_REQUEST["name"],$_REQUEST["hsize"],$_REQUEST["vsize"]);
			$audit_action = AUDIT_ACTION_ADD;
			show_messages($result,S_SCREEN_ADDED,S_CANNOT_ADD_SCREEN);
		}
		if($result){
			add_audit($audit_action,AUDIT_RESOURCE_SCREEN," Name [".$_REQUEST['name']."] ");
			unset($_REQUEST["form"]);
			unset($_REQUEST["screenid"]);
		}
	}
	if(isset($_REQUEST["delete"])&&isset($_REQUEST["screenid"]))
	{
		if($screen = get_screen_by_screenid($_REQUEST["screenid"]))
		{
			$result = delete_screen($_REQUEST["screenid"]);
			show_messages($result, S_SCREEN_DELETED, S_CANNOT_DELETE_SCREEN);
			add_audit_if($result, AUDIT_ACTION_DELETE,AUDIT_RESOURCE_SCREEN," Name [".$screen['name']."] ");
		}
		unset($_REQUEST["screenid"]);
		unset($_REQUEST["form"]);
	}
?>
<?php
	$form = new CForm("screenconf.php");
	$form->AddItem(new CButton("form",S_CREATE_SCREEN));
	show_table_header(S_CONFIGURATION_OF_SCREENS_BIG, $form);
	echo BR;

	if(isset($_REQUEST["form"]))
	{
		insert_screen_form();
	}
	else
	{
		show_table_header(S_SCREENS_BIG);

		$table = new CTableInfo(S_NO_SCREENS_DEFINED);
		$table->SetHeader(array(S_NAME,S_DIMENSION_COLS_ROWS,S_SCREEN));

		$result=DBselect("select screenid,name,hsize,vsize from screens where ".DBid2nodeid("screenid")."=".$ZBX_CURNODEID.
				" order by name");
		while($row=DBfetch($result))
		{
			if(!screen_accessiable($row["screenid"], PERM_READ_WRITE)) continue;

			$table->AddRow(array(
				new CLink($row["name"],"screenconf.php?form=update&screenid=".$row["screenid"],
					'action'),
				$row["hsize"]." x ".$row["vsize"],
				new CLink(S_EDIT,"screenedit.php?screenid=".$row["screenid"])
				));
		}
		$table->Show();
	}
?>

<?php

include_once "include/page_footer.php";

?>

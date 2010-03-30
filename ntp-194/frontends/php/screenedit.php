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

	$page["title"] = "S_CONFIGURATION_OF_SCREENS";
	$page["file"] = "screenedit.php";

include_once "include/page_header.php";
	
?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"screenid"=>	array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null),
		
		"screenitemid"=>array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,			'(isset({form})&&({form}=="update"))&&(!isset({x})||!isset({y}))'),
		"resourcetype"=>	array(T_ZBX_INT, O_OPT,  null,  
					BETWEEN(SCREEN_RESOURCE_GRAPH,SCREEN_RESOURCE_EVENTS),	'isset({save})'),
		"resourceid"=>	array(T_ZBX_INT, O_OPT,  null,  DB_ID,			'isset({save})'),
		"width"=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),	null),
		"height"=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),	null),
		"colspan"=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,100),		null),
		"rowspan"=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,100),		null),
		"elements"=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(1,65535),	null),
		"valign"=>	array(T_ZBX_INT, O_OPT,  null,  
					BETWEEN(VALIGN_MIDDLE,VALIGN_BOTTOM),		null),
		"halign"=>	array(T_ZBX_INT, O_OPT,  null,  
					BETWEEN(HALIGN_CENTER,HALIGN_RIGHT),		null),
		"style"=>	array(T_ZBX_INT, O_OPT,  null,  
					BETWEEN(STYLE_HORISONTAL,STYLE_VERTICAL),	'isset({save})'),
		"url"=>		array(T_ZBX_STR, O_OPT,  null,  null,			'isset({save})'),
		"x"=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(1,100),		'isset({save})&&(isset({form})&&({form}!="update"))'),
		"y"=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(1,100),		'isset({save})&&(isset({form})&&({form}!="update"))'),

		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"delete"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	null,	null,	null)
	);

	check_fields($fields);
?>
<?php
	show_table_header(S_CONFIGURATION_OF_SCREEN_BIG);

	if(isset($_REQUEST["screenid"]))
	{
		if(!screen_accessiable($_REQUEST["screenid"], PERM_READ_WRITE))
			access_deny();

		$screen = get_screen_by_screenid($_REQUEST["screenid"]);

		echo BR;
		if(isset($_REQUEST["save"]))
		{
			if(!isset($_REQUEST["elements"]))	$_REQUEST["elements"]=0;

			if(isset($_REQUEST["screenitemid"]))
			{
				$result=update_screen_item($_REQUEST["screenitemid"],
					$_REQUEST["resourcetype"],$_REQUEST["resourceid"],$_REQUEST["width"],
					$_REQUEST["height"],$_REQUEST["colspan"],$_REQUEST["rowspan"],
					$_REQUEST["elements"],$_REQUEST["valign"],
					$_REQUEST["halign"],$_REQUEST["style"],$_REQUEST["url"]);

				show_messages($result, S_ITEM_UPDATED, S_CANNOT_UPDATE_ITEM);
			}
			else
			{
				$result=add_screen_item(
					$_REQUEST["resourcetype"],$_REQUEST["screenid"],
					$_REQUEST["x"],$_REQUEST["y"],$_REQUEST["resourceid"],
					$_REQUEST["width"],$_REQUEST["height"],$_REQUEST["colspan"],
					$_REQUEST["rowspan"],$_REQUEST["elements"],$_REQUEST["valign"],
					$_REQUEST["halign"],$_REQUEST["style"],$_REQUEST["url"]);

				show_messages($result, S_ITEM_ADDED, S_CANNOT_ADD_ITEM);
			}
			if($result){
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_SCREEN," Name [".$screen['name']."] cell changed ".
					(isset($_REQUEST["screenitemid"]) ? "[".$_REQUEST["screenitemid"]."]" : 
						"[".$_REQUEST["x"].",".$_REQUEST["y"]."]"));
				unset($_REQUEST["form"]);
			}
		} elseif(isset($_REQUEST["delete"])) {
			$result=delete_screen_item($_REQUEST["screenitemid"]);
			show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);
			unset($_REQUEST["x"]);
		}

		if($_REQUEST["screenid"] > 0)
		{
			$table = get_screen($_REQUEST["screenid"], 1);
			$table->Show();
		}

	}
?>

<?php

include_once "include/page_footer.php";

?>

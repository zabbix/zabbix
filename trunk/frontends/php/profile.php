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
	require_once "include/users.inc.php";
	require_once "include/forms.inc.php";

	$page["title"] = "S_USER_PROFILE";
	$page["file"] = "profile.php";

include_once "include/page_header.php";

	insert_confirm_javascript();
?>
<?php
	if($USER_DETAILS["alias"]=="guest")
	{
		access_deny();
	}
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"password1"=>	array(T_ZBX_STR, O_OPT,	null,	null,		'isset({save})&&{form}!="update"&&isset({change_password})'),
		"password2"=>	array(T_ZBX_STR, O_OPT,	null,	null,		'isset({save})&&{form}!="update"&&isset({change_password})'),
		"lang"=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	'isset({save})'),
		"autologout"=>  array(T_ZBX_INT, O_OPT, null,   BETWEEN(0,3600),'isset({save})'),
		"url"=>		array(T_ZBX_STR, O_OPT,	null,	null,		'isset({save})'),
		"refresh"=>	array(T_ZBX_INT, O_OPT,	null,	BETWEEN(0,3600),'isset({save})'),
		"change_password"=>	array(T_ZBX_STR, O_OPT,	null,	null,	null),
/* actions */
		"save"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"cancel"=>	array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
/* other */
		"form"=>	array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		"form_refresh"=>array(T_ZBX_STR, O_OPT, null,	null,	null)
	);


	check_fields($fields);
?>
<?php
	if(isset($_REQUEST["cancel"]))
	{
		Redirect('index.php');
	}
	elseif(isset($_REQUEST["save"]))
	{
		$_REQUEST["password1"] = get_request("password1", null);
		$_REQUEST["password2"] = get_request("password2", null);

		if(isset($_REQUEST["password1"]) && $_REQUEST["password1"] == "")
		{
			show_error_message(S_ONLY_FOR_GUEST_ALLOWED_EMPTY_PASSWORD);
		}
		elseif($_REQUEST["password1"]==$_REQUEST["password2"])
		{
			$result=update_user_profile($USER_DETAILS["userid"],$_REQUEST["password1"],$_REQUEST["url"],$_REQUEST["autologout"],$_REQUEST["lang"],$_REQUEST["refresh"]);
			show_messages($result, S_USER_UPDATED, S_CANNOT_UPDATE_USER);
			if($result)
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_USER,
					"User alias [".$USER_DETAILS["alias"].
					"] name [".$USER_DETAILS["name"]."] surname [".
					$USER_DETAILS["surname"]."] profile id [".$USER_DETAILS["userid"]."]");
		}
		else
		{
			show_error_message(S_CANNOT_UPDATE_USER_BOTH_PASSWORDS);
		}
	}
?>
<?php
	show_table_header(S_USER_PROFILE_BIG." : ".$USER_DETAILS["name"]." ".$USER_DETAILS["surname"]);
	echo "<br>";
	insert_user_form($USER_DETAILS["userid"],1);
?>
<?php

include_once "include/page_footer.php";

?>

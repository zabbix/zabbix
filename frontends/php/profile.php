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

	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
	if($USER_DETAILS["alias"]=="guest")
	{
		access_deny();
		exit;
	}
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
			$result=update_user_profile($_REQUEST["userid"],$_REQUEST["password1"],$_REQUEST["url"],$_REQUEST["autologout"],$_REQUEST["lang"],$_REQUEST["refresh"]);
			show_messages($result, S_USER_UPDATED, S_CANNOT_UPDATE_USER);
			if($result)
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_USER,
					"User alias [".$USER_DETAILS["alias"].
					"] name [".$USER_DETAILS["name"]."] surname [".
					$USER_DETAILS["surname"]."] profile id [".$_REQUEST["userid"]."]");
		}
		else
		{
			show_error_message(S_CANNOT_UPDATE_USER_BOTH_PASSWORDS);
		}
	}
	if(isset($_REQUEST["save"]))
	{
		unset($_REQUEST["userid"]);
	}
?>

<?php
	show_table_header(S_USER_PROFILE_BIG." : ".$USER_DETAILS["name"]." ".$USER_DETAILS["surname"]);
	echo "<br>";
?>

<?php
	insert_user_form($USER_DETAILS["userid"],1);
?>

<?php
	show_page_footer();
?>

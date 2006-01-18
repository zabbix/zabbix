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
	include "include/config.inc.php";
	include "include/forms.inc.php";

	$page["title"] = "S_USER_PROFILE";
	$page["file"] = "profile.php";

	show_header($page["title"],0,0);
//	insert_confirm_javascript();
?>

<?php
	if($USER_DETAILS["alias"]=="guest")
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_page_footer();
		exit;
	}
?>

<?php
	if(isset($_REQUEST["register"]))
	{
		if($_REQUEST["register"]=="update profile")
		{
			if($_REQUEST["password1"]==$_REQUEST["password2"])
			{
				$result=update_user_profile($_REQUEST["userid"],$_REQUEST["password1"],$_REQUEST["url"],$_REQUEST["autologout"],$_REQUEST["lang"],$_REQUEST["refresh"]);
				show_messages($result, S_USER_UPDATED, S_CANNOT_UPDATE_USER);
				if($result)
					add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_USER,"User ID [".$_REQUEST["userid"]."]");
			}
			else
			{
				show_error_message(S_CANNOT_UPDATE_USER_BOTH_PASSWORDS);
			}
		}
	}
?>

<?php
	show_table_header(S_USER_PROFILE_BIG." : ".$USER_DETAILS["name"]." ".$USER_DETAILS["surname"]);
	echo "<br>";
?>

<?php
	@insert_user_form($USER_DETAILS["userid"],1);
?>

<?php
	show_page_footer();
?>

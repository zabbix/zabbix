<?php
/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
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
	$page["title"] = "Users";
	$page["file"] = "users.php";

	include "include/config.inc.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
        if(!check_anyright("User","U"))
        {
                show_table_header("<font color=\"AA0000\">No permissions !</font
>");
                show_footer();
                exit;
        }
?>

<?php
	if(isset($HTTP_GET_VARS["register"]))
	{
		if($HTTP_GET_VARS["register"]=="add")
		{
			if($HTTP_GET_VARS["password1"]==$HTTP_GET_VARS["password2"])
			{
				$result=add_user($HTTP_GET_VARS["name"],$HTTP_GET_VARS["surname"],$HTTP_GET_VARS["alias"],$HTTP_GET_VARS["password1"]);
				show_messages($result, "User added", "Cannot add user");
			}
			else
			{
				show_error_message("Cannot add user. Both passwords must be equal.");
			}
		}
		if($HTTP_GET_VARS["register"]=="delete")
		{
			$result=delete_user($HTTP_GET_VARS["userid"]);
			show_messages($result, "User successfully deleted", "Cannot delete user");
			unset($userid);
		}
		if($HTTP_GET_VARS["register"]=="delete_permission")
		{
			$result=delete_permission($HTTP_GET_VARS["rightid"]);
			show_messages($result, "Permission successfully deleted", "Cannot delete permission");
			unset($rightid);
		}
		if($HTTP_GET_VARS["register"]=="add permission")
		{
			$result=add_permission($HTTP_GET_VARS["userid"],$HTTP_GET_VARS["right"],$HTTP_GET_VARS["permission"],$HTTP_GET_VARS["id"]);
			show_messages($result, "Permission successfully added", "Cannot add permission");
		}
		if($HTTP_GET_VARS["register"]=="update")
		{
			if($HTTP_GET_VARS["password1"]==$HTTP_GET_VARS["password2"])
			{
				$result=update_user($HTTP_GET_VARS["userid"],$HTTP_GET_VARS["name"],$HTTP_GET_VARS["surname"],$HTTP_GET_VARS["alias"],$HTTP_GET_VARS["password1"]);
				show_messages($result, "Information successfully updated", "Cannot update information");
			}
			else
			{
				show_error_message("Cannot update user. Both passwords must be equal.");
			}
		}
		if($HTTP_GET_VARS["register"]=="add group")
		{
			$result=add_user_group($HTTP_GET_VARS["name"], $HTTP_GET_VARS["users"]);
			show_messages($result, "Group added", "Cannot add group");
		}
		if($HTTP_GET_VARS["register"]=="update group")
		{
			$result=update_user_group($HTTP_GET_VARS["usrgrpid"], $HTTP_GET_VARS["name"], $HTTP_GET_VARS["users"]);
			show_messages($result, "Group updated", "Cannot update group");
		}
		if($HTTP_GET_VARS["register"]=="delete group")
		{
			$result=delete_user_group($HTTP_GET_VARS["usrgrpid"]);
			show_messages($result, "Group deleted", "Cannot delete group");
			unset($HTTP_GET_VARS["usrgrpid"]);
		}
	}
?>

<?php
	show_table_header("CONFIGURATION OF USER GROUPS");
?>


<?php
	echo "<TABLE BORDER=0 COLS=4 align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR><TD WIDTH=3%><B>Id</B></TD>";
	echo "<TD><B>Name</B></TD>";
	echo "<TD><B>Members</B></TD>";
	echo "<TD WIDTH=10%><B>Actions</B></TD>";
	echo "</TR>";

	$result=DBselect("select usrgrpid,name from usrgrp order by name");
	$col=0;
	while($row=DBfetch($result))
	{
		if(!check_right("User group","R",$row["usrgrpid"]))
		{
			continue;
		}
		if($col++%2==0)	{ echo "<TR BGCOLOR=#EEEEEE>"; }
		else		{ echo "<TR BGCOLOR=#DDDDDD>"; }
		echo "<TD>".$row["usrgrpid"]."</TD>";
		echo "<TD>".$row["name"]."</TD>";
		echo "<TD>";
		$result1=DBselect("select distinct u.alias from users u,users_groups ug where u.userid=ug.userid and ug.usrgrpid=".$row["usrgrpid"]." order by alias");
		for($i=0;$i<DBnum_rows($result1);$i++)
//		while($row1=DBfetch($result1))
		{
			echo DBget_field($result1,$i,0);
			if($i<DBnum_rows($result1)-1)
			{
				echo ", ";
			}
		}
		echo "</TD>";
		echo "<TD>";
		echo "<A HREF=\"users.php?usrgrpid=".$row["usrgrpid"]."#form\">Change</A>";
		echo "</TD>";
		echo "</TR>";
	}
	if(DBnum_rows($result)==0)
	{
			echo "<TR BGCOLOR=#EEEEEE>";
			echo "<TD COLSPAN=3 ALIGN=CENTER>-No user groups defined-</TD>";
			echo "<TR>";
	}
	echo "</TABLE>";
	echo "<br>";
?>

<?php
	show_table_header("CONFIGURATION OF USERS");
?>

<?php
	echo "<TABLE BORDER=0 COLS=4 align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR><TD WIDTH=3%><B>Id</B></TD>";
	echo "<TD WIDTH=10%><B>Alias</B></TD>";
	echo "<TD WIDTH=10%><B>Name</B></TD>";
	echo "<TD WIDTH=10%><B>Surname</B></TD>";
	echo "<TD WIDTH=10%><B>Is online?</B></TD>";
	echo "<TD WIDTH=10%><B>Actions</B></TD>";
	echo "</TR>";

	$result=DBselect("select u.userid,u.alias,u.name,u.surname from users u order by u.alias");
	$col=0;
	while($row=DBfetch($result))
	{
		if(!check_right("User","R",$row["userid"]))
		{
			continue;
		}
		if($col++%2==0)	{ echo "<TR BGCOLOR=#EEEEEE>"; }
		else		{ echo "<TR BGCOLOR=#DDDDDD>"; }
	
		echo "<TD>".$row["userid"]."</TD>";
		echo "<TD>".$row["alias"]."</TD>";
		echo "<TD>".$row["name"]."</TD>";
		echo "<TD>".$row["surname"]."</TD>";
		$sql="select count(*) as count from sessions where userid=".$row["userid"]." and lastaccess-600<".time();
		$result2=DBselect($sql);
		$row2=DBfetch($result2);
		if($row2["count"]>0)
		{
			echo "<TD><font color=\"00AA00\">Yes</font></TD>";
		}
		else
		{
			echo "<TD><font color=\"AA0000\">No</font></TD>";
		}
		echo "<TD>";
        	if(check_right("User","U",$row["userid"]))
		{
			if(get_media_count_by_userid($row["userid"])>0)
			{
				echo "<A HREF=\"users.php?register=change&userid=".$row["userid"]."#form\">Change</A> - <A HREF=\"media.php?userid=".$row["userid"]."\"><b>M</b>edia</A>";
			}
			else
			{
				echo "<A HREF=\"users.php?register=change&userid=".$row["userid"]."#form\">Change</A> - <A HREF=\"media.php?userid=".$row["userid"]."\">Media</A>";
			}
		}
		else
		{
			echo "Change - Media";
		}
		echo "</TD>";
		echo "</TR>";
	}
	if(DBnum_rows($result)==0)
	{
			echo "<TR BGCOLOR=#EEEEEE>";
			echo "<TD COLSPAN=6 ALIGN=CENTER>-No users defined-</TD>";
			echo "<TR>";
	}
	echo "</TABLE>";
?>

<?php
	if(isset($HTTP_GET_VARS["userid"]))
	{
	echo "<br>";
	echo "<a name=\"form\"></a>";
	show_table_header("USER PERMISSIONS");
	echo "<TABLE BORDER=0 align=center COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR><TD WIDTH=10%><B>Permission</B></TD>";
	echo "<TD WIDTH=10%><B>Right</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Resource name</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Actions</B></TD>";
	echo "</TR>";
	$result=DBselect("select rightid,name,permission,id from rights where userid=".$HTTP_GET_VARS["userid"]." order by name,permission,id");
	$col=0;
	while($row=DBfetch($result))
	{
//        	if(!check_right("User","R",$row["userid"]))
//		{
//			continue;
//		}
		if($col++%2==0)	{ echo "<TR BGCOLOR=#EEEEEE>"; }
		else		{ echo "<TR BGCOLOR=#DDDDDD>"; }
	
		echo "<TD>".$row["name"]."</TD>";
		if($row["permission"]=="R")
		{
			echo "<TD>Read only</TD>";
		}
		else if($row["permission"]=="U")
		{
			echo "<TD>Read-write</TD>";
		}
		else if($row["permission"]=="H")
		{
			echo "<TD>Hide</TD>";
		}
		else if($row["permission"]=="A")
		{
			echo "<TD>Add</TD>";
		}
		else
		{
			echo "<TD>".$row["permission"]."</TD>";
		}
		echo "<TD>".get_resource_name($row["name"],$row["id"])."</TD>";
		echo "<TD><A HREF=users.php?userid=".$HTTP_GET_VARS["userid"]."&rightid=".$row["rightid"]."&register=delete_permission>Delete</A></TD>";
	}
	echo "</TR>";
	echo "</TABLE>";

	insert_permissions_form($HTTP_GET_VARS["userid"]);

	}
?>

<?php
	echo "<br>";
	@insert_usergroups_form($HTTP_GET_VARS["usrgrpid"]);

	echo "<br>";
	@insert_user_form($HTTP_GET_VARS["userid"]);
?>

<?php
	show_footer();
?>

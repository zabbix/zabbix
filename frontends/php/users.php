<?
	$page["title"] = "Users";
	$page["file"] = "users.php";

	include "include/config.inc.php";
	show_header($page["title"],0,0);
?>

<?
        if(!check_right("User","R",0))
        {
                show_table_header("<font color=\"AA0000\">No permissions !</font
>");
                show_footer();
                exit;
        }
?>

<?
	if(isset($register))
	{
		if($register=="add")
		{
			if($password1==$password2)
			{
				$result=add_user($name,$surname,$alias,$password1);
				show_messages($result, "User added", "Cannot add user");
			}
			else
			{
				show_error_message("Cannot add user. Both passwords must be equal.");
			}
		}
		if($register=="delete")
		{
			$result=delete_user($userid);
			show_messages($result, "User successfully deleted", "Cannot delete user");
			unset($userid);
		}
		if($register=="delete_permission")
		{
			$result=delete_permission($rightid);
			show_messages($result, "Permission successfully deleted", "Cannot delete permission");
			unset($rightid);
		}
		if($register=="add permission")
		{
			$result=add_permission($userid,$right,$permission,$id);
			show_messages($result, "Permission successfully added", "Cannot add permission");
		}
		if($register=="update")
		{
			if($password1==$password2)
			{
				$result=update_user($userid,$name,$surname,$alias,$password1);
				show_messages($result, "Information successfully updated", "Cannot update information");
			}
			else
			{
				show_error_message("Cannot update user. Both passwords must be equal.");
			}
		}
	}
?>

<?
	show_table_header("CONFIGURATION OF USERS");
	echo "<br>";
?>

<?
	show_table_header("USERS");
	echo "<TABLE BORDER=0 COLS=4 WIDTH=\"100%\" BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR><TD WIDTH=\"3%\"><B>Id</B></TD>";
	echo "<TD WIDTH=\"10%\"><B>Alias</B></TD>";
	echo "<TD WIDTH=\"10%\" NOSAVE><B>Name</B></TD>";
	echo "<TD WIDTH=\"10%\" NOSAVE><B>Surname</B></TD>";
	echo "<TD WIDTH=\"10%\" NOSAVE><B>Actions</B></TD>";
	echo "</TR>";

	$result=DBselect("select u.userid,u.alias,u.name,u.surname from users u order by u.alias");
	echo "<CENTER>";
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
        	if(check_right("User","U",$row["userid"]))
		{
			echo "<TD><A HREF=\"users.php?register=change&userid=".$row["userid"]."\">Change</A> - <A HREF=\"media.php?userid=".$row["userid"]."\">Media</A>";
		}
		else
		{
			echo "<TD>Change - Media";
		}
		echo "</TD>";
		echo "</TR>";
	}
	echo "</TABLE>";
?>

<?
	if(isset($userid))
	{

	echo "<br>";
	show_table_header("USER PERMISSIONS");
	echo "<TABLE BORDER=0 COLS=4 WIDTH=\"100%\" BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR><TD WIDTH=\"10%\"><B>Permission</B></TD>";
	echo "<TD WIDTH=\"10%\"><B>Right</B></TD>";
	echo "<TD WIDTH=\"10%\" NOSAVE><B>Resource name</B></TD>";
	echo "<TD WIDTH=\"10%\" NOSAVE><B>Actions</B></TD>";
	echo "</TR>";
	$result=DBselect("select rightid,name,permission,id from rights where userid=$userid order by name,permission,id");
	echo "<CENTER>";
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
		echo "<TD><A HREF=users.php?userid=$userid&rightid=".$row["rightid"]."&register=delete_permission>Delete</A></TD>";
	}
	echo "</TR>";
	echo "</TABLE>";

	insert_permissions_form($userid);

	}
?>

<?
	echo "<br>";

	@insert_user_form($userid);
?>

<?
	show_footer();
?>

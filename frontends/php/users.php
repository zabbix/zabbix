<?
	$page["title"] = "Users";
	$page["file"] = "users.php";

	include "include/config.inc";
	show_header($page["title"],0,0);
?>


<?
	if(isset($register))
	{
		if($register=="add")
		{
			if($password1==$password2)
			{
				$result=add_user($groupid,$name,$surname,$alias,$password1);
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
		if($register=="update")
		{
			if($password1==$password2)
			{
				$result=update_user($userid,$groupid,$name,$surname,$alias,$password1);
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
	echo "<TR><TD WIDTH=\"10%\"><B>Group</B></TD>";
	echo "<TD WIDTH=\"10%\"><B>Alias</B></TD>";
	echo "<TD WIDTH=\"10%\" NOSAVE><B>Name</B></TD>";
	echo "<TD WIDTH=\"10%\" NOSAVE><B>Surname</B></TD>";
	echo "<TD WIDTH=\"10%\" NOSAVE><B>Actions</B></TD>";
	echo "</TR>";

	$result=DBselect("select u.userid,u.alias,u.name,u.surname,g.name as grp from users u,groups g where u.groupid=g.groupid order by g.name,u.alias");
	echo "<CENTER>";
	$col=0;
	while($row=DBfetch($result))
	{
		if($col++%2==0)	{ echo "<TR BGCOLOR=#EEEEEE>"; }
		else		{ echo "<TR BGCOLOR=#DDDDDD>"; }
	
		echo "<TD>".$row["grp"]."</TD>";
		echo "<TD>".$row["alias"]."</TD>";
		echo "<TD>".$row["name"]."</TD>";
		echo "<TD>".$row["surname"]."</TD>";
		echo "<TD><A HREF=\"users.php?register=change&userid=".$row["userid"]."\">Change</A> - <A HREF=\"media.php?userid=".$row["userid"]."\">Media</A>";
		echo "</TD>";
		echo "</TR>";
	}
	echo "</TABLE>";
?>

<?
	echo "<br>";

	@insert_user_form($userid);
?>

<?
	show_footer();
?>

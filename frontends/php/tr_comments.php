<?
	$page["title"] = "Trigger comments";
	$page["file"] = "tr_comments.php";

	include "include/config.inc";
	show_header($page["title"],0,0);
?>

<?
	show_table_header("TRIGGER COMMENTS");
	echo "<br>";
?>

<?
	if(isset($register) && ($register=="update"))
	{
		$result=update_trigger_comments($triggerid,$comments);
		show_messages($result,"Trigger comment updated","Cannot update trigger comment");
	}
?>

<?
	$result=DBselect("select comments from triggers where triggerid=$triggerid");
	$comments=stripslashes(DBget_field($result,0,0));
?>

<?
	show_table2_header_begin();
	echo "Comments";

	show_table2_v_delimiter();
	echo "<form method=\"post\" action=\"tr_comments.php\">";
	echo "<input name=\"triggerid\" type=\"hidden\" value=$triggerid>";
	echo "Comments";
	show_table2_h_delimiter();
	echo "<textarea name=\"comments\" cols=100 ROWS=\"25\" wrap=\"soft\">$comments</TEXTAREA>";

	show_table2_v_delimiter2();
	echo "<input type=\"submit\" name=\"register\" value=\"update\">";

	show_table2_header_end();
?>

<?
	show_footer();
?>

<?
	$page["title"] = "Trigger comments";
	$page["file"] = "tr_comments.php";

	include "include/config.inc.php";
	show_header($page["title"],0,0);
?>

<?
	show_table_header("TRIGGER COMMENTS");
	echo "<br>";
?>

<?
	if(isset($HTTP_GET_VARS["register"]) && ($HTTP_GET_VARS["register"]=="update"))
	{
		$result=update_trigger_comments($HTTP_GET_VARS["triggerid"],$HTTP_GET_VARS["comments"]);
		show_messages($result,"Trigger comment updated","Cannot update trigger comment");
	}
?>

<?
	$result=DBselect("select comments from triggers where triggerid=".$HTTP_GET_VARS["triggerid"]);
	$comments=stripslashes(DBget_field($result,0,0));
?>

<?
	show_table2_header_begin();
	echo "Comments";

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"tr_comments.php\">";
	echo "<input name=\"triggerid\" type=\"hidden\" value=".$HTTP_GET_VARS["triggerid"].">";
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

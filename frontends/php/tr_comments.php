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
	$page["title"] = "Trigger comments";
	$page["file"] = "tr_comments.php";

	include "include/config.inc.php";
	show_header($page["title"],0,0);
?>
<?php
	if(!check_right("Trigger comment","R",$HTTP_GET_VARS["triggerid"]))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_footer();
		exit;
	}
?>

<?php
	show_table_header("TRIGGER COMMENTS");
	echo "<br>";
?>

<?php
	if(isset($HTTP_GET_VARS["register"]) && ($HTTP_GET_VARS["register"]=="update"))
	{
		$result=update_trigger_comments($HTTP_GET_VARS["triggerid"],$HTTP_GET_VARS["comments"]);
		show_messages($result,"Trigger comment updated","Cannot update trigger comment");
	}
?>

<?php
	$result=DBselect("select comments from triggers where triggerid=".$HTTP_GET_VARS["triggerid"]);
	$comments=stripslashes(DBget_field($result,0,0));
?>

<?php
	show_table2_header_begin();
	echo "Comments";

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"tr_comments.php\">";
	echo "<input name=\"triggerid\" type=\"hidden\" value=".$HTTP_GET_VARS["triggerid"].">";
	echo "Comments";
	show_table2_h_delimiter();
	echo "<textarea name=\"comments\" cols=100 ROWS=\"25\" wrap=\"soft\">$comments</TEXTAREA>";

	show_table2_v_delimiter2();
	echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";

	show_table2_header_end();
?>

<?php
	show_footer();
?>

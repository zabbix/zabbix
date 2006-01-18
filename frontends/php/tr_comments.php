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
	$page["title"] = "S_TRIGGER_COMMENTS";
	$page["file"] = "tr_comments.php";
	show_header($page["title"],0,0);
?>

<?php
	if(!check_right("Trigger comment","R",$_REQUEST["triggerid"]))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_page_footer();
		exit;
	}
?>

<?php
	show_table_header(S_TRIGGER_COMMENTS_BIG);
?>

<?php
	if(isset($_REQUEST["register"]) && ($_REQUEST["register"]=="update"))
	{
		$result=update_trigger_comments($_REQUEST["triggerid"],$_REQUEST["comments"]);
		show_messages($result, S_COMMENT_UPDATED, S_CANNOT_UPDATE_COMMENT);
	}
?>

<?php
	$trigger=get_trigger_by_triggerid($_REQUEST["triggerid"]);
	$comments=stripslashes($trigger["comments"]);
?>

<?php
	show_form_begin("tr_comments.comments");
	echo S_COMMENTS;

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"tr_comments.php\">";
	echo "<input name=\"triggerid\" type=\"hidden\" value=".$_REQUEST["triggerid"].">";
	echo S_COMMENTS;
	show_table2_h_delimiter();
	echo "<textarea name=\"comments\" cols=100 ROWS=\"25\" wrap=\"soft\">$comments</TEXTAREA>";

	show_table2_v_delimiter2();
	echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";

	show_table2_header_end();
?>

<?php
	show_page_footer();
?>

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

	$page["title"] = "S_SCREENS";
	$page["file"] = "screenconf.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
	if(!check_right("Screen","U",0))
	{
//		show_table_header("<font color=\"AA0000\">No permissions !</font>");
//		show_page_footer();
//		exit;
	}
	update_profile("web.menu.config.last",$page["file"]);
?>


<?php
	if(isset($_REQUEST["save"])&&!isset($_REQUEST["screenid"]))
	{
		$result=add_screen($_REQUEST["name"],$_REQUEST["cols"],$_REQUEST["rows"]);
		show_messages($result,S_SCREEN_ADDED,S_CANNOT_ADD_SCREEN);
	}

	if(isset($_REQUEST["save"])&&isset($_REQUEST["screenid"]))
	{
		$result=update_screen($_REQUEST["screenid"],$_REQUEST["name"],$_REQUEST["cols"],$_REQUEST["rows"]);
		show_messages($result, S_SCREEN_UPDATED, S_CANNOT_UPDATE_SCREEN);
	}

	if(isset($_REQUEST["delete"]))
	{
		$result=delete_screen($_REQUEST["screenid"]);
		show_messages($result, S_SCREEN_DELETED, S_CANNOT_DELETE_SCREEN);
		unset($_REQUEST["screenid"]);
	}
?>

<?php
	if(!isset($_REQUEST["form"]))
	{
		$h1=S_CONFIGURATION_OF_SCREENS_BIG;

		$h2="<input class=\"button\" type=\"submit\" name=\"form\" value=\"".S_CREATE_SCREEN."\">";

		show_header2($h1, $h2, "<form name=\"selection\" method=\"get\" action=\"screenconf.php\">", "</form>");

		$table = new CTableInfo(S_NO_SCREENS_DEFINED);
		$table->setHeader(array(S_ID,S_NAME,S_COLUMNS,S_ROWS,S_ACTIONS));

		$result=DBselect("select screenid,name,cols,rows from screens order by name");
		while($row=DBfetch($result))
		{
			if(!check_right("Screen","R",$row["screenid"]))
			{
				continue;
			}
			$table->addRow(array(
				$row["screenid"],
				"<a href=\"screenedit.php?screenid=".$row["screenid"]."\">".$row["name"]."</a>",
				$row["cols"],
				$row["rows"],
				"<A HREF=\"screenconf.php?screenid=".$row["screenid"]."&form=0\">".S_CHANGE."</A>"
				));
		}
		$table->show();
	}
	else
	{
		insert_screen_form();
	}
?>

<?php
	show_page_footer();
?>

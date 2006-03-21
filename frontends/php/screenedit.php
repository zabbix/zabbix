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
	$page["title"] = "S_CONFIGURATION_OF_SCREENS";
	$page["file"] = "screenedit.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
	show_table_header(S_CONFIGURATION_OF_SCREEN_BIG);

	if(isset($_REQUEST["screenid"]))
	{
		echo BR;
		if(!check_right("Screen","U",$_REQUEST["screenid"]))
		{
			show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
			show_page_footer();
			exit;
		}
		if(isset($_REQUEST["save"]))
		{
			if(!isset($_REQUEST["elements"]))	$_REQUEST["elements"]=0;

			if(isset($_REQUEST["screenitemid"]))
			{
				$result=update_screen_item($_REQUEST["screenitemid"],
					$_REQUEST["resource"],$_REQUEST["resourceid"],$_REQUEST["width"],
					$_REQUEST["height"],$_REQUEST["colspan"],$_REQUEST["rowspan"],
					$_REQUEST["elements"],$_REQUEST["valign"],
					$_REQUEST["halign"],$_REQUEST["style"],$_REQUEST["url"]);

				show_messages($result, S_ITEM_UPDATED, S_CANNOT_UPDATE_ITEM);
			}
			else
			{
				$result=add_screen_item(
					$_REQUEST["resource"],$_REQUEST["screenid"],
					$_REQUEST["x"],$_REQUEST["y"],$_REQUEST["resourceid"],
					$_REQUEST["width"],$_REQUEST["height"],$_REQUEST["colspan"],
					$_REQUEST["rowspan"],$_REQUEST["elements"],$_REQUEST["valign"],
					$_REQUEST["halign"],$_REQUEST["style"],$_REQUEST["url"]);

				show_messages($result, S_ITEM_ADDED, S_CANNOT_ADD_ITEM);
			}
			if($result){
				unset($_REQUEST["form"]);
			}
		} elseif(isset($_REQUEST["delete"])) {
			$result=delete_screen_item($_REQUEST["screenitemid"]);
			show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);
			unset($_REQUEST["x"]);
		}

		if($_REQUEST["screenid"] > 0)
		{
			$table = get_screen($_REQUEST["screenid"], 1);
			$table->Show();
		}

	}
?>

<?php
	show_page_footer();
?>

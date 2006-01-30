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
	$page["title"] = "S_NETWORK_MAPS";
	$page["file"] = "sysmaps.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
	if(!check_anyright("Network map","U"))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_page_footer();
		exit;
	}
	update_profile("web.menu.config.last",$page["file"]);
?>

<?php
	if(isset($_REQUEST["save"]))
	{
		if(isset($_REQUEST["sysmapid"]))
		{
			$result=update_sysmap($_REQUEST["sysmapid"],$_REQUEST["name"],$_REQUEST["width"],
				$_REQUEST["height"],$_REQUEST["background"],$_REQUEST["label_type"],
				$_REQUEST["label_location"]);

			show_messages($result,"Network map updated","Cannot update network map");
		} else {
			$result=add_sysmap($_REQUEST["name"],$_REQUEST["width"],$_REQUEST["height"],
				$_REQUEST["background"],$_REQUEST["label_type"],$_REQUEST["label_location"]);

			show_messages($result,"Network map added","Cannot add network map");
		}
		if($result){
			unset($_REQUEST["form"]);
		}
	}
	elseif(isset($_REQUEST["delete"])&&isset($_REQUEST["sysmapid"]))
	{
		$result=delete_sysmap($_REQUEST["sysmapid"]);
		show_messages($result,"Network map deleted","Cannot delete network map");
		unset($_REQUEST["sysmapid"]);
	}
?>

<?php
	$form = new CForm();
	$form->AddItem(new CButton("form",S_CREATE_MAP));
	show_header2(S_CONFIGURATION_OF_NETWORK_MAPS, $form);
?>

<?php
	if(!isset($_REQUEST["form"]))
	{
		$table = new CTableInfo(S_NO_MAPS_DEFINED);
		$table->setHeader(array(S_ID,S_NAME,S_WIDTH,S_HEIGHT,S_MAP));

		$result=DBselect("select s.sysmapid,s.name,s.width,s.height from sysmaps s order by s.name");
		while($row=DBfetch($result))
		{
		        if(!check_right("Network map","U",$row["sysmapid"]))
		        {
		                continue;
		        }
	
			$table->addRow(array(
				$row["sysmapid"],
				new CLink($row["name"], "sysmaps.php?form=0&sysmapid=".$row["sysmapid"]."#form"),
				$row["width"],
				$row["height"],
				new CLink(S_SHOW,"sysmap.php?sysmapid=".$row["sysmapid"])
				));
		}
		$table->show();
	}
	else
	{
		echo BR;
		insert_map_form();
	}
?>

<?php
	show_page_footer();
?>

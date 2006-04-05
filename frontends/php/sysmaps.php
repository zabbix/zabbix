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
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"sysmapid"=>		array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,NULL),

		"name"=>		array(T_ZBX_STR, O_OPT,	 NULL,	NOT_EMPTY,"isset({save})"),
		"width"=>		array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(0,65535),"isset({save})"),
		"height"=>		array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(0,65535),"isset({save})"),
		"background"=>		array(T_ZBX_STR, O_OPT,	 NULL,	NULL,"isset({save})"),
		"label_type"=>		array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(0,4),"isset({save})"),
		"label_location"=>	array(T_ZBX_INT, O_OPT,	 NULL,	BETWEEN(0,3),"isset({save})"),

		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"delete"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)

//		"triggerid"=>	array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,'{form}=="update"'),

//		"description"=>	array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,'isset({save})'),
//		"expression"=>	array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,'isset({save})'),
//		"priority"=>	array(T_ZBX_INT, O_OPT,  NULL,  IN("0,1,2,3,4,5"),'isset({save})'),
//		"comments"=>	array(T_ZBX_STR, O_OPT,  NULL,	NULL,'isset({save})'),
//		"url"=>		array(T_ZBX_STR, O_OPT,  NULL,	NULL,'isset({save})'),
//		"disabled"=>	array(T_ZBX_STR, O_OPT,  NULL,	NULL,NULL)
	);
	check_fields($fields);
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
		if($result){
			unset($_REQUEST["form"]);
		}
	}
?>

<?php
	$form = new CForm();
	$form->AddItem(new CButton("form",S_CREATE_MAP));
	show_header2(S_CONFIGURATION_OF_NETWORK_MAPS, $form);
	echo BR;
?>

<?php
	if(isset($_REQUEST["form"]))
	{
		insert_map_form();
	}
	else
	{
		show_header2(S_MAPS_BIG);
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
				new CLink($row["name"], "sysmaps.php?form=update".
					"&sysmapid=".$row["sysmapid"]."#form",'action'),
				$row["width"],
				$row["height"],
				new CLink(S_EDIT,"sysmap.php?sysmapid=".$row["sysmapid"])
				));
		}
		$table->show();
	}
?>

<?php
	show_page_footer();
?>

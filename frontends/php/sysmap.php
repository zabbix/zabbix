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
	$page["title"] = "S_CONFIGURATION_OF_NETWORK_MAPS";
	$page["file"] = "sysmap.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
	if(!check_right("Network map","U",$_REQUEST["sysmapid"]))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_page_footer();
		exit;
	}
?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"sysmapid"=>	array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,NULL),

		"selementid"=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,		NULL),
		"elementid"=>	array(T_ZBX_INT, O_OPT,  NULL, DB_ID,		'isset({save})'),
		"elementtype"=>	array(T_ZBX_INT, O_OPT,  NULL, IN("0,1,2,3"),	'isset({save})'),
		"label"=>	array(T_ZBX_STR, O_OPT,  NULL, NOT_EMPTY,	'isset({save})'),
		"x"=>		array(T_ZBX_INT, O_OPT,  NULL,  BETWEEN(0,65535),'isset({save})'),
		"y"=>           array(T_ZBX_INT, O_OPT,  NULL,  BETWEEN(0,65535),'isset({save})'),
		"icon"=>	array(T_ZBX_STR, O_OPT,  NULL, NOT_EMPTY,	'isset({save})'),
		"icon_on"=>	array(T_ZBX_STR, O_OPT,  NULL, NOT_EMPTY,	'isset({save})'),
		"url"=>		array(T_ZBX_STR, O_OPT,  NULL, NULL,		'isset({save})'),
		"label_location"=>array(T_ZBX_INT, O_OPT, NULL,	IN("-1,0,1,2,3"),'isset({save})'),

		"linkid"=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,NULL),
		"selementid1"=>	array(T_ZBX_INT, O_OPT,  NULL, DB_ID,'isset({save_link})'),
		"selementid2"=> array(T_ZBX_INT, O_OPT,  NULL, DB_ID,'isset({save_link})'),
		"triggerid"=>	array(T_ZBX_INT, O_OPT,  NULL, DB_ID,'isset({save_link})'),
		"drawtype_off"=>array(T_ZBX_INT, O_OPT,  NULL, IN("0,1,2,3,4"),'isset({save_link})'),
		"drawtype_on"=>	array(T_ZBX_INT, O_OPT,  NULL, IN("0,1,2,3,4"),'isset({save_link})'),
		"color_off"=>	array(T_ZBX_STR, O_OPT,  NULL, NOT_EMPTY,'isset({save_link})'),
		"color_on"=>	array(T_ZBX_STR, O_OPT,  NULL, NOT_EMPTY,'isset({save_link})'),

/* actions */
		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"save_link"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"delete"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
/* other */
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);

	check_fields($fields);
?>

<?php
	show_table_header("CONFIGURATION OF NETWORK MAP");
	echo BR;
?>

<?php
	if(isset($_REQUEST["save"]))
	{
		if(isset($_REQUEST["selementid"]))
		{ // update element
			$result=update_sysmap_element($_REQUEST["selementid"],
				$_REQUEST["sysmapid"],$_REQUEST["elementid"],$_REQUEST["elementtype"],
				$_REQUEST["label"],$_REQUEST["x"],$_REQUEST["y"],
				$_REQUEST["icon"],$_REQUEST["url"],$_REQUEST["icon_on"],
				$_REQUEST["label_location"]);
			show_messages($result,"Element updated","Cannot update element");
		}
		else
		{ // add element
			$result=add_element_to_sysmap($_REQUEST["sysmapid"],$_REQUEST["elementid"],
				$_REQUEST["elementtype"],$_REQUEST["label"],$_REQUEST["x"],$_REQUEST["y"],
				$_REQUEST["icon"],$_REQUEST["url"],$_REQUEST["icon_on"],
				$_REQUEST["label_location"]);

			show_messages($result,"Element added","Cannot add element");
		}
		if($result)	unset($_REQUEST["form"]);
	}
	if(isset($_REQUEST["save_link"]))
	{
		if(isset($_REQUEST["linkid"]))
		{ // update link
			$result=update_link($_REQUEST["linkid"],
				$_REQUEST["sysmapid"],$_REQUEST["selementid1"],$_REQUEST["selementid2"],
				$_REQUEST["triggerid"],	$_REQUEST["drawtype_off"],$_REQUEST["color_off"],
				$_REQUEST["drawtype_on"],$_REQUEST["color_on"]);

			show_messages($result,"Link updated","Cannot update link");
		}
		else
		{ // add link
			$result=add_link($_REQUEST["sysmapid"],$_REQUEST["selementid1"],$_REQUEST["selementid2"],
				$_REQUEST["triggerid"],	$_REQUEST["drawtype_off"],$_REQUEST["color_off"],
				$_REQUEST["drawtype_on"],$_REQUEST["color_on"]);

			show_messages($result,"Link added","Cannot add link");
		}
		if($result)	unset($_REQUEST["form"]);
	}
	elseif(isset($_REQUEST["delete"]))
	{
		if(isset($_REQUEST["linkid"]))
		{
			$result=delete_link($_REQUEST["linkid"]);
			show_messages($result,"Link deleted","Cannot delete link");
			if($result)
			{
				unset($_REQUEST["linkid"]);
				unset($_REQUEST["form"]);
			}
		}
		elseif(isset($_REQUEST["selementid"]))
		{
			$result=delete_sysmaps_element($_REQUEST["selementid"]);
			show_messages($result,"Element deleted","Cannot delete element");
			if($result)
			{
				unset($_REQUEST["selementid"]);
				unset($_REQUEST["form"]);
			}
		}
	}
?>

<?php
	if(isset($_REQUEST["form"]) && ($_REQUEST["form"]=="add_element" ||
		($_REQUEST["form"]=="update" && isset($_REQUEST["selementid"]))))
	{
		show_table_header("DISPLAYED ELEMENTS");
		echo BR;
		insert_map_element_form();
	}
	elseif(isset($_REQUEST["form"]) && ($_REQUEST["form"]=="add_link" || 
		($_REQUEST["form"]=="update" && isset($_REQUEST["linkid"]))))
	{
		$result=DBselect("select count(*) as count from sysmaps_elements where sysmapid=".$_REQUEST["sysmapid"]);
		$row=DBfetch($result);;
		if($row["count"]>1)
		{
			show_table_header("CONNECTORS");
			echo BR;
			insert_map_link_form();
		}
		else
		{
			info("No elements in this map");
		}
	}
	else
	{
		show_table_header("DISPLAYED ELEMENTS", new CButton("form","Add element",
			"return Redirect('".$page["file"]."?form=add_element".url_param("sysmapid")."');"));

		$table = new CTableInfo();
		$table->setHeader(array(S_LABEL,S_TYPE,S_X,S_Y,S_ICON_ON,S_ICON_OFF));

		$db_elements = DBselect("select * from sysmaps_elements where sysmapid=".$_REQUEST["sysmapid"].
			" order by label");
		while($db_element = DBfetch($db_elements))
		{

			if(    $db_element["elementtype"] == SYSMAP_ELEMENT_TYPE_HOST)		$type = S_HOST;
			elseif($db_element["elementtype"] == SYSMAP_ELEMENT_TYPE_MAP)		$type = S_MAP;
			elseif($db_element["elementtype"] == SYSMAP_ELEMENT_TYPE_TRIGGER)	$type = S_TRIGGER;
			elseif($db_element["elementtype"] == SYSMAP_ELEMENT_TYPE_HOST_GROUP)	$type = S_HOST_GROUP;
			else $type = "Map element";

			$table->addRow(array(
				new CLink(
					$db_element["label"],
					"sysmap.php?sysmapid=".$db_element["sysmapid"].
					"&form=update&selementid=".$db_element["selementid"],
					"action"),
				nbsp($type),
				$db_element["x"],
				$db_element["y"],
				nbsp($db_element["icon_on"]),
				nbsp($db_element["icon"])
				));
		}
		$table->show();

		echo BR;
		show_table_header("CONNECTORS", new CButton("form","Create connection",
			"return Redirect('".$page["file"]."?form=add_link".
			url_param("sysmapid")."');"));

		$table = new CTableInfo();
		$table->SetHeader(array(S_LINK,S_ELEMENT_1,S_ELEMENT_2,S_LINK_STATUS_INDICATOR));

		$i = 1;
		$result=DBselect("select linkid,selementid1,selementid2,triggerid from sysmaps_links".
			" where sysmapid=".$_REQUEST["sysmapid"]." order by linkid");
		while($row=DBfetch($result))
		{
	/* prepare label 1 */
			$result1=DBselect("select label from sysmaps_elements".
				" where selementid=".$row["selementid1"]);
			$row1=DBfetch($result1);
			$label1=$row1["label"];

	/* prepare label 2 */
			$result1=DBselect("select label from sysmaps_elements".
				" where selementid=".$row["selementid2"]);
			$row1=DBfetch($result1);
			$label2=$row1["label"];

	/* prepare description */
			if(isset($row["triggerid"]))
				$description=expand_trigger_description($row["triggerid"]);
			else
				$description="-";

	/* draw row */
			$table->addRow(array(
				new CLink("link ".$i++,
					"sysmap.php?sysmapid=".$_REQUEST["sysmapid"].
					"&form=update&linkid=".$row["linkid"],
					"action"),
				$label1,
				$label2,
				$description
				));
		}
		$table->show();
	}

	echo BR;
	$map=get_sysmap_by_sysmapid($_REQUEST["sysmapid"]);
	show_table_header($map["name"]);

	$table = new CTable(NULL,"map");
	if(isset($_REQUEST["sysmapid"]))
	{
		$linkMap = new CMap("links".$_REQUEST["sysmapid"]."_".rand(0,100000));

		$db_elements = DBselect("select * from sysmaps_elements where sysmapid=".$_REQUEST["sysmapid"]);
		while($db_element = DBfetch($db_elements))
		{
			$tmp_img = get_png_by_selementid($db_element["selementid"]);
			if(!$tmp_img) continue;

			$x1_		= $db_element["x"];
			$y1_		= $db_element["y"];
			$x2_		= $db_element["x"] + imagesx($tmp_img);
			$y2_		= $db_element["y"] + imagesy($tmp_img);

			$linkMap->AddRectArea($x1_,$y1_,$x2_,$y2_,
				"sysmap.php?form=update&sysmapid=".$_REQUEST["sysmapid"].
				"&selementid=".$db_element["selementid"],
				$db_element["label"]);

		}
		$imgMap = new CImg("map.php?sysmapid=".$_REQUEST["sysmapid"]);
		$imgMap->SetMap($linkMap->GetName());
		$table->AddRow($linkMap);
		$table->AddRow($imgMap);
	}
	$table->Show();
?>
<?php
	show_page_footer();
?>

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
	show_table_header("CONFIGURATION OF NETWORK MAP");
	echo BR;
?>

<?php
	if(isset($_REQUEST["register"]))
	{
		if($_REQUEST["register"]=="add")
		{
			$result=add_host_to_sysmap($_REQUEST["sysmapid"],$_REQUEST["hostid"],
				$_REQUEST["label"],$_REQUEST["x"],$_REQUEST["y"],$_REQUEST["icon"],
				$_REQUEST["url"],$_REQUEST["icon_on"]);
			show_messages($result,"Host added","Cannot add host");
		}
		if($_REQUEST["register"]=="update")
		{
			$result=update_sysmap_host($_REQUEST["shostid"],$_REQUEST["sysmapid"],
				$_REQUEST["hostid"],$_REQUEST["label"],$_REQUEST["x"],$_REQUEST["y"],
				$_REQUEST["icon"],$_REQUEST["url"],$_REQUEST["icon_on"]);
			show_messages($result,"Host updated","Cannot update host");
		}
		if($_REQUEST["register"]=="add link")
		{
			$result=add_link($_REQUEST["sysmapid"],$_REQUEST["shostid1"],$_REQUEST["shostid2"],
				$_REQUEST["triggerid"],	$_REQUEST["drawtype_off"],$_REQUEST["color_off"],
				$_REQUEST["drawtype_on"],$_REQUEST["color_on"]);
			show_messages($result,"Link added","Cannot add link");
		}
		if($_REQUEST["register"]=="delete_link")
		{
			$result=delete_link($_REQUEST["linkid"]);
			show_messages($result,"Link deleted","Cannot delete link");
			unset($_REQUEST["linkid"]);
		}
		if($_REQUEST["register"]=="delete")
		{
			$result=delete_sysmaps_host($_REQUEST["shostid"]);
			show_messages($result,"Host deleted","Cannot delete host");
			unset($_REQUEST["shostid"]);
		}
	}
?>

<?php
	$result=DBselect("select h.host,sh.shostid,sh.sysmapid,sh.hostid,sh.label,sh.x,".
		"sh.y,sh.icon from sysmaps_hosts sh,hosts h".
		" where sh.sysmapid=".$_REQUEST["sysmapid"].
		" and h.status not in (".HOST_STATUS_DELETED.") and h.hostid=sh.hostid".
		" order by h.host");

	if(isset($_REQUEST["form"]) && $_REQUEST["form"]=="Create Connection" && DBnum_rows($result)>1)
	{
		insert_map_link_form();
	}
	elseif(isset($_REQUEST["form"]) && $_REQUEST["form"]=="Add Host")
	{
		insert_map_host_form();
	}
	else
	{
		$map=get_map_by_sysmapid($_REQUEST["sysmapid"]);
		show_table_header($map["name"]);

		echo "<TABLE BORDER=0 COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
		echo "<TR BGCOLOR=#DDDDDD>";
		echo "<TD ALIGN=CENTER>";
		if(isset($_REQUEST["sysmapid"]))
		{
			$map_name="links".$_REQUEST["sysmapid"]."_".rand(0,100000);
			$map="\n<map name=".$map_name.">";

			$result=DBselect("select h.host,sh.shostid,sh.sysmapid,sh.hostid,sh.label,".
				"sh.x,sh.y,h.status from sysmaps_hosts sh,hosts h".
				" where sh.sysmapid=".$_REQUEST["sysmapid"].
				" and h.status not in (".HOST_STATUS_DELETED.") and h.hostid=sh.hostid");

			while($row=DBfetch($result))
			{
				$host_		= $row["host"];
				$shostid_	= $row["shostid"];
				$sysmapid_	= $row["sysmapid"];
				$hostid_	= $row["hostid"];
				$label_		= $row["label"];
				$x_		= $row["x"];
				$y_		= $row["y"];
				$status_	= $row["status"];

				if(function_exists("imagecreatetruecolor")&&@imagecreatetruecolor(1,1))
				{
					$map .= "\n<area shape=rect coords=$x_,$y_,".($x_+48).",".($y_+48).
						" href=\"sysmap.php?form=Add+Host&sysmapid=$sysmapid_".
						"&shostid=$shostid_#form\"".
						" alt=\"$host_\">";
				}
				else
				{
					$map .= "\n<area shape=rect coords=$x_,$y_,".($x_+32).",".($y_+32).
						" href=\"sysmap.php?sysmapid=$sysmapid_&shostid=$shostid_#form\"".
						" alt=\"$host_\">";
				}
			}
			$map=$map."\n</map>";
			echo $map;
			echo "<IMG SRC=\"map.php?sysmapid=".$_REQUEST["sysmapid"]."\" border=0 usemap=#$map_name>";
		}

		echo "</TD>";
		echo "</TR>";
		echo "</TABLE>";
?>

<?php

		$form = new CForm();
		$form->AddVar("sysmapid",$_REQUEST["sysmapid"]);
		$form->AddItem(new CButton("form","Add Host"));
		show_table_header("DISPLAYED HOSTS",$form);

		$table = new CTableInfo();
		$table->setHeader(array(S_HOST,S_LABEL,S_X,S_Y,S_ICON,S_ACTIONS));

		$result=DBselect("select h.host,sh.shostid,sh.sysmapid,sh.hostid,sh.label,sh.x,".
			"sh.y,sh.icon from sysmaps_hosts sh,hosts h".
			" where sh.sysmapid=".$_REQUEST["sysmapid"].
			" and h.status not in (".HOST_STATUS_DELETED.") and h.hostid=sh.hostid".
			" order by h.host");
		while($row=DBfetch($result))
		{
			$table->addRow(array(
				$row["host"],
				$row["label"],
				$row["x"],
				$row["y"],
				nbsp($row["icon"]),
				array(
					new CLink("Change",
						"sysmap.php?sysmapid=".$row["sysmapid"].
						"&form=Add+Host&shostid=".$row["shostid"]."#form"),
					SPACE."-".SPACE,
					new CLink("Delete",
						"sysmap.php?register=delete&sysmapid=".$row["sysmapid"].
						"&shostid=".$row["shostid"])
				)
				));
		}
		$table->show();
?>

<?php
		echo BR;
		$form = new CForm();
		$form->AddVar("sysmapid",$_REQUEST["sysmapid"]);
		$form->AddItem(new CButton("form","Create Connection"));
		show_table_header("CONNECTORS",$form);

		$table = new CTableInfo();
		$table->SetHeader(array(S_HOST_1,S_HOST_2,S_LINK_STATUS_INDICATOR,S_ACTIONS));

		$result=DBselect("select linkid,shostid1,shostid2,triggerid from sysmaps_links".
			" where sysmapid=".$_REQUEST["sysmapid"]." order by linkid");
		while($row=DBfetch($result))
		{
	/* prepare label 1 */
			$db_hosts = DBselect("select h.*".
				" from sysmaps_hosts sh,hosts h".
				" where sh.sysmapid=".$_REQUEST["sysmapid"].
				" and h.hostid=sh.hostid".
				" and h.status not in (".HOST_STATUS_DELETED.")".
				" and sh.shostid=".$row["shostid1"]);
			if(DBnum_rows($db_hosts)==0) continue;
			$result1=DBselect("select label from sysmaps_hosts where shostid=".$row["shostid1"]);
			$row1=DBfetch($result1);
			$label1=$row1["label"];
			if($label1==""){
				$db_host = DBfetch($db_hosts);
				$label1 = $db_host['host'];
			}

	/* prepare label 2 */
			$db_hosts = DBselect("select h.*".
				" from sysmaps_hosts sh,hosts h".
				" where sh.sysmapid=".$_REQUEST["sysmapid"].
				" and h.hostid=sh.hostid".
				" and h.status not in (".HOST_STATUS_DELETED.")".
				" and sh.shostid=".$row["shostid2"]);
			if(DBnum_rows($db_hosts)==0) continue;
			$result1=DBselect("select label from sysmaps_hosts where shostid=".$row["shostid2"]);
			$row1=DBfetch($result1);
			$label2=$row1["label"];
			if($label2==""){
				$db_host = DBfetch($db_hosts);
				$label2 = $db_host['host'];
			}

	/* prepare description */
			if(isset($row["triggerid"]))
				$description=expand_trigger_description($row["triggerid"]);
			else
				$description="-";

	/* draw row */
			$table->addRow(array(
				$label1,
				$label2,
				$description,
				new CLink("Delete","sysmap.php?register=delete_link".url_param("sysmapid").
					"&linkid=".$row["linkid"])
				));
		}
		$table->show();
	}
?>

<?php
	show_page_footer();
?>

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
			$result=add_host_to_sysmap($_REQUEST["sysmapid"],$_REQUEST["hostid"],$_REQUEST["label"],$_REQUEST["x"],$_REQUEST["y"],$_REQUEST["icon"],$_REQUEST["url"],$_REQUEST["icon_on"]);
			show_messages($result,"Host added","Cannot add host");
		}
		if($_REQUEST["register"]=="update")
		{
			$result=update_sysmap_host($_REQUEST["shostid"],$_REQUEST["sysmapid"],$_REQUEST["hostid"],$_REQUEST["label"],$_REQUEST["x"],$_REQUEST["y"],$_REQUEST["icon"],$_REQUEST["url"],$_REQUEST["icon_on"]);
			show_messages($result,"Host updated","Cannot update host");
		}
		if($_REQUEST["register"]=="add link")
		{
			$result=add_link($_REQUEST["sysmapid"],$_REQUEST["shostid1"],$_REQUEST["shostid2"],$_REQUEST["triggerid"],
					$_REQUEST["drawtype_off"],$_REQUEST["color_off"],$_REQUEST["drawtype_on"],$_REQUEST["color_on"]);
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
	$map=get_map_by_sysmapid($_REQUEST["sysmapid"]);
	show_table_header($map["name"]);

	echo "<TABLE BORDER=0 COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=#DDDDDD>";
	echo "<TD ALIGN=CENTER>";
	if(isset($_REQUEST["sysmapid"]))
	{
		$map_name="links".$_REQUEST["sysmapid"]."_".rand(0,100000);
		$map="\n<map name=".$map_name.">";

		$result=DBselect("select h.host,sh.shostid,sh.sysmapid,sh.hostid,sh.label,sh.x,sh.y,h.status".
			" from sysmaps_hosts sh,hosts h where sh.sysmapid=".$_REQUEST["sysmapid"].
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
					" href=\"sysmap.php?sysmapid=$sysmapid_&shostid=$shostid_#form\"".
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

	show_table_header("DISPLAYED HOSTS");

	$table = new CTableInfo();
	$table->setHeader(array(S_HOST,S_LABEL,S_X,S_Y,S_ICON,S_ACTIONS));

	$result=DBselect("select h.host,sh.shostid,sh.sysmapid,sh.hostid,sh.label,sh.x,sh.y,sh.icon".
		" from sysmaps_hosts sh,hosts h where sh.sysmapid=".$_REQUEST["sysmapid"].
		" and h.status not in (".HOST_STATUS_DELETED.") and h.hostid=sh.hostid order by h.host");
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
						"&shostid=".$row["shostid"]."#form"),
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
	show_table_header("CONNECTORS");

	$table = new CTableInfo();
	$table->SetHeader(array(S_HOST_1,S_HOST_2,S_LINK_STATUS_INDICATOR,S_ACTIONS));

	$result=DBselect("select linkid,shostid1,shostid2,triggerid from sysmaps_links".
		" where sysmapid=".$_REQUEST["sysmapid"]." order by linkid");
	while($row=DBfetch($result))
	{
		$result1=DBselect("select label from sysmaps_hosts where shostid=".$row["shostid1"]);
		$row1=DBfetch($result1);
		$label1=$row1["label"];
		$result1=DBselect("select label from sysmaps_hosts where shostid=".$row["shostid2"]);
		$row1=DBfetch($result1);
		$label2=$row1["label"];

		if(isset($row["triggerid"]))
		{
			$description=expand_trigger_description($row["triggerid"]);
		}
		else
		{
			$description="-";
		}

		$table->addRow(array(
			$label1,
			$label2,
			$description,
			new CLink("Delete","sysmap.php?register=delete_link".url_param("sysmapid").
				"&linkid=".$row["linkid"])
			));
	}
	$table->show();
?>

<?php

	echo BR;

	insert_map_host_form();
?>

<?php
	$result=DBselect("select shostid,label,hostid from sysmaps_hosts".
		" where sysmapid=".$_REQUEST["sysmapid"]." order by label");
	if(DBnum_rows($result)>1)
	{
		show_form_begin("sysmap.connector");
		echo "New connector";
		$col=0;

		show_table2_v_delimiter($col++);
		echo "<form method=\"post\" action=\"sysmap.php?sysmapid=".$_REQUEST["sysmapid"]."\">";
		echo nbsp("Host 1");
		show_table2_h_delimiter();
//		$result=DBselect("select shostid,label from sysmaps_hosts where sysmapid=".$_REQUEST["sysmapid"]." order by label");
		echo "<SELECT class=\"biginput\" name=\"shostid1\" size=1>";
		while($row=DBfetch($result))
		{
			$shostid_=$row["shostid"];
			$label=$row["label"];
			$host=get_host_by_hostid($row["hostid"]);
			if(isset($_REQUEST["shostid"])&&($_REQUEST["shostid"]==$shostid_))
			{
				echo "<OPTION VALUE='$shostid_' SELECTED>".$host["host"].":$label";
			}
			else
			{
				echo "<OPTION VALUE='$shostid_'>".$host["host"].":$label";
			}
		}
		echo "</SELECT>";

		show_table2_v_delimiter($col++);
//		echo "<form method=\"get\" action=\"sysmap.php?sysmapid=".$_REQUEST["sysmapid"].">";
		echo nbsp("Host 2");
		show_table2_h_delimiter();
		$result=DBselect("select shostid,label,hostid from sysmaps_hosts where sysmapid=".$_REQUEST["sysmapid"]." order by label");
		echo "<SELECT class=\"biginput\" name=\"shostid2\" size=1>";
		$selected=0;
		while($row=DBfetch($result))
		{
			$shostid_=$row["shostid"];
			$label=$row["label"];
			$host=get_host_by_hostid($row["hostid"]);
			if(isset($_REQUEST["shostid"])&&($_REQUEST["shostid"]!=$shostid_)&&($selected==0))
			{
				echo "<OPTION VALUE='$shostid_' SELECTED>".$host["host"].":$label";
				$selected=1;
			}
			else
			{
				echo "<OPTION VALUE='$shostid_'>".$host["host"].":$label";
			}
		}
		echo "</SELECT>";

		show_table2_v_delimiter($col++);
		echo nbsp("Link status indicator");
		show_table2_h_delimiter();
	        $result=DBselect("select triggerid from triggers order by description");
	        echo "<SELECT class=\"biginput\" name=\"triggerid\" size=1>";
		echo "<OPTION VALUE='0' SELECTED>-";
		while($row=DBfetch($result))
	        {
	                $triggerid_=$row["triggerid"];
			$description_=expand_trigger_description($triggerid_);
			echo "<OPTION VALUE='$triggerid_'>$description_";
	        }
	        echo "</SELECT>";

		show_table2_v_delimiter($col++);
		echo "Type (OFF)";
		show_table2_h_delimiter();
		echo "<select name=\"drawtype_off\" size=1>";
		echo "<OPTION VALUE='0' ".iif(isset($drawtype_off)&&($drawtype_off==0),"SELECTED","").">".get_drawtype_description(0);
//		echo "<OPTION VALUE='1' ".iif(isset($drawtype_off)&&($drawtype_off==1),"SELECTED","").">".get_drawtype_description(1);
		echo "<OPTION VALUE='2' ".iif(isset($drawtype_off)&&($drawtype_off==2),"SELECTED","").">".get_drawtype_description(2);
//		echo "<OPTION VALUE='3' ".iif(isset($drawtype_off)&&($drawtype_off==3),"SELECTED","").">".get_drawtype_description(3);
		echo "<OPTION VALUE='4' ".iif(isset($drawtype_off)&&($drawtype_off==4),"SELECTED","").">".get_drawtype_description(4);
		echo "</SELECT>";

		show_table2_v_delimiter($col++);
		echo "Color (OFF)";
		show_table2_h_delimiter();
		echo "<select name=\"color_off\" size=1>";
		echo "<OPTION VALUE='Black' ".iif(isset($color_off)&&($color_off=="Black"),"SELECTED","").">Black";
		echo "<OPTION VALUE='Blue' ".iif(isset($color_off)&&($color_off=="Blue"),"SELECTED","").">Blue";
		echo "<OPTION VALUE='Cyan' ".iif(isset($color_off)&&($color_off=="Cyan"),"SELECTED","").">Cyan";
		echo "<OPTION VALUE='Dark Blue' ".iif(isset($color_off)&&($color_off=="Dark Blue"),"SELECTED","").">Dark blue";
		echo "<OPTION VALUE='Dark Green' ".iif(isset($color_off)&&($color_off=="Dark Green"),"SELECTED","").">Dark green";
		echo "<OPTION VALUE='Dark Red' ".iif(isset($color_off)&&($color_off=="Dark Red"),"SELECTED","").">Dark red";
		echo "<OPTION VALUE='Dark Yellow' ".iif(isset($color_off)&&($color_off=="Dark Yellow"),"SELECTED","").">Dark yellow";
		echo "<OPTION VALUE='Green' ".iif(isset($color_off)&&($color_off=="Green"),"SELECTED","").">Green";
		echo "<OPTION VALUE='Red' ".iif(isset($color_off)&&($color_off=="Red"),"SELECTED","").">Red";
		echo "<OPTION VALUE='White' ".iif(isset($color_off)&&($color_off=="White"),"SELECTED","").">White";
		echo "<OPTION VALUE='Yellow' ".iif(isset($color_off)&&($color_off=="Yellow"),"SELECTED","").">Yellow";
		echo "</SELECT>";

		show_table2_v_delimiter($col++);
		echo "Type (ON)";
		show_table2_h_delimiter();
		echo "<select name=\"drawtype_on\" size=1>";
		echo "<OPTION VALUE='0' ".iif(isset($drawtype_on)&&($drawtype_on==0),"SELECTED","").">".get_drawtype_description(0);
//		echo "<OPTION VALUE='1' ".iif(isset($drawtype_on)&&($drawtype_on==1),"SELECTED","").">".get_drawtype_description(1);
		echo "<OPTION VALUE='2' ".iif(isset($drawtype_on)&&($drawtype_on==2),"SELECTED","").">".get_drawtype_description(2);
//		echo "<OPTION VALUE='3' ".iif(isset($drawtype_on)&&($drawtype_on==3),"SELECTED","").">".get_drawtype_description(3);
		echo "<OPTION VALUE='4' ".iif(isset($drawtype_on)&&($drawtype_on==4),"SELECTED","").">".get_drawtype_description(4);
		echo "</SELECT>";

		show_table2_v_delimiter($col++);
		echo "Color (ON)";
		show_table2_h_delimiter();
		echo "<select name=\"color_on\" size=1>";
		echo "<OPTION VALUE='Red' ".iif(isset($color_on)&&($color_on=="Red"),"SELECTED","").">Red";
		echo "<OPTION VALUE='Black' ".iif(isset($color_on)&&($color_on=="Black"),"SELECTED","").">Black";
		echo "<OPTION VALUE='Blue' ".iif(isset($color_on)&&($color_on=="Blue"),"SELECTED","").">Blue";
		echo "<OPTION VALUE='Cyan' ".iif(isset($color_on)&&($color_on=="Cyan"),"SELECTED","").">Cyan";
		echo "<OPTION VALUE='Dark Blue' ".iif(isset($color_on)&&($color_on=="Dark Blue"),"SELECTED","").">Dark blue";
		echo "<OPTION VALUE='Dark Green' ".iif(isset($color_on)&&($color_on=="Dark Green"),"SELECTED","").">Dark green";
		echo "<OPTION VALUE='Dark Yellow' ".iif(isset($color_on)&&($color_on=="Dark Yellow"),"SELECTED","").">Dark yellow";
		echo "<OPTION VALUE='Green' ".iif(isset($color_on)&&($color_on=="Green"),"SELECTED","").">Green";
		echo "<OPTION VALUE='Dark Red' ".iif(isset($color_on)&&($color_on=="Dark Red"),"SELECTED","").">Dark red";
		echo "<OPTION VALUE='White' ".iif(isset($color_on)&&($color_on=="White"),"SELECTED","").">White";
		echo "<OPTION VALUE='Yellow' ".iif(isset($color_on)&&($color_on=="Yellow"),"SELECTED","").">Yellow";
		echo "</SELECT>";

		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add link\">";
		show_table2_header_end();
	}
?>

<?php
	show_page_footer();
?>

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
	$page["title"] = "S_CONFIGURATION_OF_NETWORK_MAPS";
	$page["file"] = "sysmap.php";
	show_header($page["title"],0,0);
?>

<?php
	if(!check_right("Network map","U",$_GET["sysmapid"]))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_footer();
		exit;
	}
?>

<?php
	show_table_header("CONFIGURATION OF NETWORK MAP");
	echo "<br>";
?>

<?php
	if(isset($_GET["register"]))
	{
		if($_GET["register"]=="add")
		{
			$result=add_host_to_sysmap($_GET["sysmapid"],$_GET["hostid"],$_GET["label"],$_GET["x"],$_GET["y"],$_GET["icon"],$_GET["url"],$_GET["icon_on"]);
			show_messages($result,"Host added","Cannot add host");
		}
		if($_GET["register"]=="update")
		{
			$result=update_sysmap_host($_GET["shostid"],$_GET["sysmapid"],$_GET["hostid"],$_GET["label"],$_GET["x"],$_GET["y"],$_GET["icon"],$_GET["url"],$_GET["icon_on"]);
			show_messages($result,"Host updated","Cannot update host");
		}
		if($_GET["register"]=="add link")
		{
			$result=add_link($_GET["sysmapid"],$_GET["shostid1"],$_GET["shostid2"],$_GET["triggerid"],
					$_GET["drawtype_off"],$_GET["color_off"],$_GET["drawtype_on"],$_GET["color_on"]);
			show_messages($result,"Link added","Cannot add link");
		}
		if($_GET["register"]=="delete_link")
		{
			$result=delete_link($_GET["linkid"]);
			show_messages($result,"Link deleted","Cannot delete link");
			unset($_GET["linkid"]);
		}
		if($_GET["register"]=="delete")
		{
			$result=delete_sysmaps_host($_GET["shostid"]);
			show_messages($result,"Host deleted","Cannot delete host");
			unset($_GET["shostid"]);
		}
	}
?>

<?php
	$result=DBselect("select name from sysmaps where sysmapid=".$_GET["sysmapid"]);
	$map=DBget_field($result,0,0);
	show_table_header($map);
	echo "<TABLE BORDER=0 COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=#DDDDDD>";
	echo "<TD ALIGN=CENTER>";
	if(isset($_GET["sysmapid"]))
	{
		$map="\n<map name=links".$_GET["sysmapid"]."_".rand(0,100000).">";
		$result=DBselect("select h.host,sh.shostid,sh.sysmapid,sh.hostid,sh.label,sh.x,sh.y,h.status from sysmaps_hosts sh,hosts h where sh.sysmapid=".$_GET["sysmapid"]." and h.status not in (".HOST_STATUS_DELETED.") and h.hostid=sh.hostid");
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$host_=DBget_field($result,$i,0);
			$shostid_=DBget_field($result,$i,1);
			$sysmapid_=DBget_field($result,$i,2);
			$hostid_=DBget_field($result,$i,3);
			$label_=DBget_field($result,$i,4);
			$x_=DBget_field($result,$i,5);
			$y_=DBget_field($result,$i,6);
			$status_=DBget_field($result,$i,7);

			if(function_exists("imagecreatetruecolor")&&@imagecreatetruecolor(1,1))
			{
				$map=$map."\n<area shape=rect coords=$x_,$y_,".($x_+48).",".($y_+48)." href=\"sysmap.php?sysmapid=$sysmapid_&shostid=$shostid_#form\" alt=\"$host_\">";
			}
			else
			{
				$map=$map."\n<area shape=rect coords=$x_,$y_,".($x_+32).",".($y_+32)." href=\"sysmap.php?sysmapid=$sysmapid_&shostid=$shostid_#form\" alt=\"$host_\">";
			}
		}
		$map=$map."\n</map>";
		echo $map;
		echo "<IMG SRC=\"map.php?sysmapid=".$_GET["sysmapid"]."\" border=0 usemap=#links>";
	}

	echo "</TD>";
	echo "</TR>";
	echo "</TABLE>";

	show_table_header("DISPLAYED HOSTS");
	table_begin();
	table_header(array(S_HOST,S_LABEL,S_X,S_Y,S_ICON,S_ACTIONS));

	$result=DBselect("select h.host,sh.shostid,sh.sysmapid,sh.hostid,sh.label,sh.x,sh.y,sh.icon from sysmaps_hosts sh,hosts h where sh.sysmapid=".$_GET["sysmapid"]." and h.status not in (".HOST_STATUS_DELETED.") and h.hostid=sh.hostid order by h.host");
	$col=0;
	while($row=DBfetch($result))
	{
		table_row(array(
			$row["host"],
			$row["label"],
			$row["x"],
			$row["y"],
			nbsp($row["icon"]),
			"<A HREF=\"sysmap.php?sysmapid=".$row["sysmapid"]."&shostid=".$row["shostid"]."#form\">Change</A> - <A HREF=\"sysmap.php?register=delete&sysmapid=".$row["sysmapid"]."&shostid=".$row["shostid"]."\">Delete</A>"
			),$col++);
	}
	table_end();
?>

<?php
	show_table_header("CONNECTORS");
	table_begin();
	table_header(array(S_HOST_1,S_HOST_2,S_LINK_STATUS_INDICATOR,S_ACTIONS));

	$result=DBselect("select linkid,shostid1,shostid2,triggerid from sysmaps_links where sysmapid=".$_GET["sysmapid"]." order by linkid");
	$col=0;
	while($row=DBfetch($result))
	{
		$result1=DBselect("select label from sysmaps_hosts where shostid=".$row["shostid1"]);
		$label1=DBget_field($result1,0,0);
		$result1=DBselect("select label from sysmaps_hosts where shostid=".$row["shostid2"]);
		$label2=DBget_field($result1,0,0);

		if(isset($row["triggerid"]))
		{
			$description=expand_trigger_description($row["triggerid"]);
		}
		else
		{
			$description="-";
		}

		table_row(array(
			$label1,
			$label2,
			$description,
			"<A HREF=\"sysmap.php?sysmapid=".$_GET["sysmapid"]."&register=delete_link&linkid=".$row["linkid"]."\">Delete</A>"
			),$col++);
	}
	table_end();
?>

<?php
	echo "<a name=\"form\"></a>";

	if(isset($_GET["shostid"]))
	{
		$result=DBselect("select hostid,label,x,y,icon,url,icon_on from sysmaps_hosts where shostid=".$_GET["shostid"]);
		$hostid=DBget_field($result,0,0);
		$label=DBget_field($result,0,1);
		$x=DBget_field($result,0,2);
		$y=DBget_field($result,0,3);
		$icon=DBget_field($result,0,4);
		$url=DBget_field($result,0,5);
		$icon_on=DBget_field($result,0,6);
	}
	else
	{
		$label="";
		$x=0;
		$y=0;
		$icon="";
		$url="";
		$icon_on="";
	}

	show_form_begin("sysmap.host");
	echo "New host to display";
	$col=0;

	show_table2_v_delimiter($col++);
	echo "<form method=\"get\" action=\"sysmap.php\">";
	if(isset($_GET["shostid"]))
	{
		echo "<input name=\"shostid\" type=\"hidden\" value=".$_GET["shostid"].">";
	}
	if(isset($_GET["sysmapid"]))
	{
		echo "<input name=\"sysmapid\" type=\"hidden\" value=".$_GET["sysmapid"].">";
	}
	echo "Host";
	show_table2_h_delimiter();
	$result=DBselect("select hostid,host from hosts where status not in (".HOST_STATUS_DELETED.") order by host");
	echo "<select class=\"biginput\" name=\"hostid\" size=1>";
	for($i=0;$i<DBnum_rows($result);$i++)
	{
		$hostid_=DBget_field($result,$i,0);
		$host_=DBget_field($result,$i,1);
		if(isset($_GET["shostid"]) && ($hostid==$hostid_))
//		if(isset($_GET["hostid"]) && ($_GET["hostid"]==$hostid_))
		{
			echo "<OPTION VALUE='$hostid_' SELECTED>$host_";
		}
		else
		{
			echo "<OPTION VALUE='$hostid_'>$host_";
		}
	}
	echo "</SELECT>";

	show_table2_v_delimiter($col++);
	echo "Icon (OFF)";
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"icon\" size=1>";
	$result=DBselect("select name from images where imagetype=1 order by name");
	for($i=0;$i<DBnum_rows($result);$i++)
	{
		$name=DBget_field($result,$i,0);
		if(isset($_GET["shostid"]) && ($icon==$name))
		{
			echo "<OPTION VALUE='".$name."' SELECTED>".$name;
		}
		else
		{
			echo "<OPTION VALUE='".$name."'>".$name;
		}
	}
	echo "</SELECT>";

	show_table2_v_delimiter($col++);
	echo "Icon (ON)";
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"icon_on\" size=1>";
	$result=DBselect("select name from images where imagetype=1 order by name");
	for($i=0;$i<DBnum_rows($result);$i++)
	{
		$name=DBget_field($result,$i,0);
		if(isset($_GET["shostid"]) && ($icon_on==$name))
		{
			echo "<OPTION VALUE='".$name."' SELECTED>".$name;
		}
		else
		{
			echo "<OPTION VALUE='".$name."'>".$name;
		}
	}
	echo "</SELECT>";

	show_table2_v_delimiter($col++);
	echo "Label";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"label\" size=32 value=\"$label\">";

	show_table2_v_delimiter($col++);
	echo nbsp("Coordinate X");
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"x\" size=5 value=\"$x\">";

	show_table2_v_delimiter($col++);
	echo nbsp("Coordinate Y");
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"y\" size=5 value=\"$y\">";

	show_table2_v_delimiter($col++);
	echo nbsp("URL");
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"url\" size=64 value=\"$url\">";

	show_table2_v_delimiter2();
	echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
	if(isset($_GET["shostid"]))
	{
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
	}

	show_table2_header_end();
?>

<?php
	$result=DBselect("select shostid,label,hostid from sysmaps_hosts where sysmapid=".$_GET["sysmapid"]." order by label");
	if(DBnum_rows($result)>1)
	{
		show_form_begin("sysmap.connector");
		echo "New connector";
		$col=0;

		show_table2_v_delimiter($col++);
		echo "<form method=\"post\" action=\"sysmap.php?sysmapid=".$_GET["sysmapid"]."\">";
		echo nbsp("Host 1");
		show_table2_h_delimiter();
//		$result=DBselect("select shostid,label from sysmaps_hosts where sysmapid=".$_GET["sysmapid"]." order by label");
		echo "<select class=\"biginput\" name=\"shostid1\" size=1>";
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$shostid_=DBget_field($result,$i,0);
			$label=DBget_field($result,$i,1);
			$host=get_host_by_hostid(DBget_field($result,$i,2));
			if(isset($_GET["shostid"])&&($_GET["shostid"]==$shostid_))
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
//		echo "<form method=\"get\" action=\"sysmap.php?sysmapid=".$_GET["sysmapid"].">";
		echo nbsp("Host 2");
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"shostid2\" size=1>";
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			$shostid_=DBget_field($result,$i,0);
			$label=DBget_field($result,$i,1);
			$host=get_host_by_hostid(DBget_field($result,$i,2));
			echo "<OPTION VALUE='$shostid_'>".$host["host"].":$label";
		}
		echo "</SELECT>";

		show_table2_v_delimiter($col++);
		echo nbsp("Link status indicator");
		show_table2_h_delimiter();
	        $result=DBselect("select triggerid,description from triggers order by description");
	        echo "<select class=\"biginput\" name=\"triggerid\" size=1>";
		echo "<OPTION VALUE='0' SELECTED>-";
	        for($i=0;$i<DBnum_rows($result);$i++)
	        {
	                $triggerid_=DBget_field($result,$i,0);
//	                $description_=DBget_field($result,$i,1);
//			if( strstr($description_,"%s"))
//			{
				$description_=expand_trigger_description($triggerid_);
//			}
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
	show_footer();
?>

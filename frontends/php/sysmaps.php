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
	include "include/config.inc.php";
	$page["title"] = "Network maps";
	$page["file"] = "sysmaps.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
	if(!check_anyright("Network map","U"))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_footer();
		exit;
	}
?>

<?php
	if(isset($_GET["register"]))
	{
		if($_GET["register"]=="add")
		{
			$result=add_sysmap($_GET["name"],$_GET["width"],$_GET["height"],$_GET["background"]);
			show_messages($result,"Network map added","Cannot add network map");
		}
		if($_GET["register"]=="update")
		{
			$result=update_sysmap($_GET["sysmapid"],$_GET["name"],$_GET["width"],$_GET["height"],$_GET["background"]);
			show_messages($result,"Network map updated","Cannot update network map");
		}
		if($_GET["register"]=="delete")
		{
			$result=delete_sysmap($_GET["sysmapid"]);
			show_messages($result,"Network map deleted","Cannot delete network map");
			unset($_GET["sysmapid"]);
		}
	}
?>

<?php
	show_table_header("CONFIGURATION OF NETWORK MAPS");
	echo "<br>";
?>

<?php
	show_table_header("NETWORK MAPS");
	table_begin();
	table_header(array(S_ID,S_NAME,S_WIDTH,S_HEIGHT,S_ACTIONS));

	$result=DBselect("select s.sysmapid,s.name,s.width,s.height from sysmaps s order by s.name");
	$col=0;
	while($row=DBfetch($result))
	{
	        if(!check_right("Network map","U",$row["sysmapid"]))
	        {
	                continue;
	        }

		table_row(array(
			$row["sysmapid"],
			"<a href=\"sysmap.php?sysmapid=".$row["sysmapid"]."\">".$row["name"]."</a>",
			$row["width"],
			$row["height"],
			"<A HREF=\"sysmaps.php?sysmapid=".$row["sysmapid"]."#form\">Change</A>"
			),$col++);
	}
	if(DBnum_rows($result)==0)
	{
			echo "<TR BGCOLOR=#EEEEEE>";
			echo "<TD COLSPAN=5 ALIGN=CENTER>-No maps defined-</TD>";
			echo "<TR>";
	}
	table_end();
?>

<?php
	echo "<a name=\"form\"></a>";

	if(isset($_GET["sysmapid"]))
	{
		$result=DBselect("select s.sysmapid,s.name,s.width,s.height,s.background from sysmaps s where sysmapid=".$_GET["sysmapid"]);
		$name=DBget_field($result,0,1);
		$width=DBget_field($result,0,2);
		$height=DBget_field($result,0,3);
		$background=DBget_field($result,0,4);
	}
	else
	{
		$name="";
		$width=800;
		$height=600;
		$background="";
	}

	show_table2_header_begin();
	echo "New system map";

	$col=0;

	show_table2_v_delimiter($col++);
	echo "<form method=\"get\" enctype=\"multipart/form-data\" action=\"sysmaps.php\">";
	if(isset($_GET["sysmapid"]))
	{
		echo "<input class=\"biginput\" name=\"sysmapid\" type=\"hidden\" value=".$_GET["sysmapid"].">";
	}
	echo "Name";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"name\" value=\"$name\" size=32>";

	show_table2_v_delimiter($col++);
	echo "Width";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"width\" size=5 value=\"$width\">";

	show_table2_v_delimiter($col++);
	echo "Height";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"height\" size=5 value=\"$height\">";

	show_table2_v_delimiter($col++);
	echo "Background image";
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"background\" size=1>";
	$result=DBselect("select name from images where imagetype=2 order by name");
	echo "<OPTION VALUE=''>No image...";
	for($i=0;$i<DBnum_rows($result);$i++)
	{
		$name=DBget_field($result,$i,0);
		if(isset($_GET["sysmapid"]) && ($background==$name))
//		if(isset($_GET["hostid"]) && ($_GET["icon"]==$icons[$i]))
		{
			echo "<OPTION VALUE='".$name."' SELECTED>".$name;
		}
		else
		{
			echo "<OPTION VALUE='".$name."'>".$name;
		}
	}
	echo "</SELECT>";

	show_table2_v_delimiter2();
	echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
	if(isset($_GET["sysmapid"]))
	{
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('Delete system map?');\">";
	}

	show_table2_header_end();
?>

<?php
	show_footer();
?>

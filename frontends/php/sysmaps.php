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
	if(isset($HTTP_GET_VARS["register"]))
	{
		if($HTTP_GET_VARS["register"]=="add")
		{
			$result=add_sysmap($HTTP_GET_VARS["name"],$HTTP_GET_VARS["width"],$HTTP_GET_VARS["height"]);
			show_messages($result,"Network map added","Cannot add network map");
		}
		if($HTTP_GET_VARS["register"]=="update")
		{
			$result=update_sysmap($HTTP_GET_VARS["sysmapid"],$HTTP_GET_VARS["name"],$HTTP_GET_VARS["width"],$HTTP_GET_VARS["height"]);
			show_messages($result,"Network map updated","Cannot update network map");
		}
		if($HTTP_GET_VARS["register"]=="delete")
		{
			$result=delete_sysmap($HTTP_GET_VARS["sysmapid"]);
			show_messages($result,"Network map deleted","Cannot delete network map");
			unset($HTTP_GET_VARS["sysmapid"]);
		}
	}
?>

<?php
	show_table_header("CONFIGURATION OF NETWORK MAPS");
	echo "<br>";
?>

<?php
	show_table_header("NETWORK MAPS");
	echo "<TABLE BORDER=0 align=center COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TD WIDTH=3% NOSAVE><B>Id</B></TD>";
	echo "<TD><B>Name</B></TD>";
	echo "<TD WIDTH=5% NOSAVE><B>Width</B></TD>";
	echo "<TD WIDTH=5% NOSAVE><B>Height</B></TD>";
	echo "<TD WIDTH=15% NOSAVE><B>Actions</B></TD>";
	echo "</TR>";

	$result=DBselect("select s.sysmapid,s.name,s.width,s.height from sysmaps s order by s.name");
	$col=0;
	while($row=DBfetch($result))
	{
	        if(!check_right("Network map","U",$row["sysmapid"]))
	        {
	                continue;
	        }

		if($col==1)
		{
			echo "<TR BGCOLOR=#EEEEEE>";
			$col=0;
		} else
		{
			echo "<TR BGCOLOR=#DDDDDD>";
			$col=1;
		}
	
		echo "<TD>".$row["sysmapid"]."</TD>";
		echo "<TD><a href=\"sysmap.php?sysmapid=".$row["sysmapid"]."\">".$row["name"]."</a></TD>";
		echo "<TD>".$row["width"]."</TD>";
		echo "<TD>".$row["height"]."</TD>";
		echo "<TD><A HREF=\"sysmaps.php?sysmapid=".$row["sysmapid"]."#form\">Change</A></TD>";
		echo "</TR>";
	}
	if(DBnum_rows($result)==0)
	{
			echo "<TR BGCOLOR=#EEEEEE>";
			echo "<TD COLSPAN=5 ALIGN=CENTER>-No maps defined-</TD>";
			echo "<TR>";
	}
	echo "</TABLE>";
?>

<?php
	echo "<a name=\"form\"></a>";

	if(isset($HTTP_GET_VARS["sysmapid"]))
	{
		$result=DBselect("select s.sysmapid,s.name,s.width,s.height from sysmaps s where sysmapid=".$HTTP_GET_VARS["sysmapid"]);
		$name=DBget_field($result,0,1);
		$width=DBget_field($result,0,2);
		$height=DBget_field($result,0,3);
	}
	else
	{
		$name="";
		$width=800;
		$height=600;
	}

	echo "<br>";
	show_table2_header_begin();
	echo "New system map";

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"sysmaps.php\">";
	if(isset($HTTP_GET_VARS["sysmapid"]))
	{
		echo "<input class=\"biginput\" name=\"sysmapid\" type=\"hidden\" value=".$HTTP_GET_VARS["sysmapid"].">";
	}
	echo "Name";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"name\" value=\"$name\" size=32>";

	show_table2_v_delimiter();
	echo "Width";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"width\" size=5 value=\"$width\">";

	show_table2_v_delimiter();
	echo "Height";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"height\" size=5 value=\"$height\">";

	show_table2_v_delimiter2();
	echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
	if(isset($HTTP_GET_VARS["sysmapid"]))
	{
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('Delete system map?');\">";
	}

	show_table2_header_end();
?>

<?php
	show_footer();
?>

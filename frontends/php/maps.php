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
	$page["file"] = "maps.php";

	$nomenu=0;
	if(isset($HTTP_GET_VARS["fullscreen"]))
	{
		$nomenu=1;
	}
	if(isset($HTTP_GET_VARS["sysmapid"]))
	{
		show_header($page["title"],30,$nomenu);
	}
	else
	{
		show_header($page["title"],0,$nomenu);
	}
?>

<?php
	if(isset($HTTP_GET_VARS["sysmapid"])&&!check_right("Network map","R",$HTTP_GET_VARS["sysmapid"]))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_footer();
		exit;
	}
?>

<?php
	if(!isset($HTTP_GET_VARS["fullscreen"]))
	{
		show_table_header_begin();
		echo "NETWORK MAPS";

		show_table_v_delimiter();

		$lasthost="";
		$result=DBselect("select sysmapid,name from sysmaps order by name");

		while($row=DBfetch($result))
		{
			if(!check_right("Network map","R",$row["sysmapid"]))
			{
				continue;
			}
			if( isset($HTTP_GET_VARS["sysmapid"]) && ($HTTP_GET_VARS["sysmapid"] == $row["sysmapid"]) )
			{
				echo "<b>[";
			}
			echo "<a href='maps.php?sysmapid=".$row["sysmapid"]."'>".$row["name"]."</a>";
			if(isset($HTTP_GET_VARS["sysmapid"]) && ($HTTP_GET_VARS["sysmapid"] == $row["sysmapid"]) )
			{
				echo "]</b>";
			}
			echo " ";
		}

		if(DBnum_rows($result) == 0)
		{
			echo "No maps to display";
		}

		show_table_header_end();
		echo "<br>";
	}
?>

<?php
	if(isset($HTTP_GET_VARS["sysmapid"]))
	{
		$result=DBselect("select name from sysmaps where sysmapid=".$HTTP_GET_VARS["sysmapid"]);
		$map=DBget_field($result,0,0);
		if(isset($HTTP_GET_VARS["fullscreen"]))
		{
			$map="<a href=\"maps.php?sysmapid=".$HTTP_GET_VARS["sysmapid"]."\">".$map."</a>";;
		}
		else
		{
			$map="<a href=\"maps.php?sysmapid=".$HTTP_GET_VARS["sysmapid"]."&fullscreen=1\">".$map."</a>";;
		}
	}
	else
	{
		$map="Select map to display";
	}

	show_table_header($map);

	echo "<TABLE BORDER=0 COLS=4 align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=#EEEEEE>";
	echo "<TR BGCOLOR=#DDDDDD>";
	echo "<TD ALIGN=CENTER>";
	if(isset($HTTP_GET_VARS["sysmapid"]))
	{
		echo get_map_imagemap($HTTP_GET_VARS["sysmapid"]);
		echo "<IMG SRC=\"map.php?noedit=1&sysmapid=".$HTTP_GET_VARS["sysmapid"]."\" border=0 usemap=#links>";
	}
	else
	{
		echo "...";
	}
	echo "</TD>";
	echo "</TR>";
	echo "</TABLE>";
?>

<?php
	show_footer();
?>

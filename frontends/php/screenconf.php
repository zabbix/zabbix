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
	$page["title"] = "Screens";
	$page["file"] = "screenconf.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
	show_table_header("CONFIGURATION OF SCREENS");
	echo "<br>";
?>

<?php
	if(!check_right("Screen","U",0))
	{
//		show_table_header("<font color=\"AA0000\">No permissions !</font>");
//		show_footer();
//		exit;
	}
?>

<?php
	if(isset($HTTP_GET_VARS["register"]))
	{
		if($HTTP_GET_VARS["register"]=="add")
		{
			$result=add_screen($HTTP_GET_VARS["name"],$HTTP_GET_VARS["cols"],$HTTP_GET_VARS["rows"]);
			show_messages($result,"Screen added","Cannot add screen");
		}
		if($HTTP_GET_VARS["register"]=="update")
		{
			$result=update_screen($HTTP_GET_VARS["screenid"],$HTTP_GET_VARS["name"],$HTTP_GET_VARS["cols"],$HTTP_GET_VARS["rows"]);
			show_messages($result,"Screen updated","Cannot update screen");
		}
		if($HTTP_GET_VARS["register"]=="delete")
		{
			$result=delete_screen($HTTP_GET_VARS["screenid"]);
			show_messages($result,"Screen deleted","Cannot delete screen");
			unset($HTTP_GET_VARS["screenid"]);
		}
	}
?>

<?php
	show_table_header("SCREENS");
	echo "<TABLE BORDER=0 COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TD WIDTH=5% NOSAVE><B>Id</B></TD>";
	echo "<TD><B>Name</B></TD>";
	echo "<TD WIDTH=5% NOSAVE><B>Columns</B></TD>";
	echo "<TD WIDTH=5% NOSAVE><B>Rows</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Actions</B></TD>";
	echo "</TR>";

	$result=DBselect("select screenid,name,cols,rows from screens order by name");
	$col=0;
	while($row=DBfetch($result))
	{
		if(!check_right("Screen","R",$row["screenid"]))
		{
			continue;
		}
		if($col++%2==0)	{ echo "<TR BGCOLOR=#EEEEEE>"; }
		else		{ echo "<TR BGCOLOR=#DDDDDD>"; }
	
		echo "<TD>".$row["screenid"]."</TD>";
		echo "<TD><a href=\"screenedit.php?screenid=".$row["screenid"]."\">".$row["name"]."</a></TD>";
		echo "<TD>".$row["cols"]."</TD>";
		echo "<TD>".$row["rows"]."</TD>";
		echo "<TD><A HREF=\"screenconf.php?screenid=".$row["screenid"]."#form\">Change</A></TD>";
		echo "</TR>";
	}
	if(DBnum_rows($result)==0)
	{
			echo "<TR BGCOLOR=#EEEEEE>";
			echo "<TD COLSPAN=5 ALIGN=CENTER>-No screens defined-</TD>";
			echo "<TR>";
	}
	echo "</TABLE>";
?>

<?php
	echo "<a name=\"form\"></a>";

	if(isset($HTTP_GET_VARS["screenid"]))
	{
		$result=DBselect("select screenid,name,cols,rows from screens g where screenid=".$HTTP_GET_VARS["screenid"]);
		$row=DBfetch($result);
		$name=$row["name"];
		$cols=$row["cols"];
		$rows=$row["rows"];
	}
	else
	{
		$name="";
		$cols=1;
		$rows=1;
	}

	echo "<br>";
	show_table2_header_begin();
	echo "Screen";

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"screenconf.php\">";
	if(isset($HTTP_GET_VARS["screenid"]))
	{
		echo "<input class=\"biginput\" name=\"screenid\" type=\"hidden\" value=".$HTTP_GET_VARS["screenid"].">";
	}
	echo "Name";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"name\" value=\"$name\" size=32>";

	show_table2_v_delimiter();
	echo "Columns";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"cols\" size=5 value=\"$cols\">";

	show_table2_v_delimiter();
	echo "Rows";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"rows\" size=5 value=\"$rows\">";

	show_table2_v_delimiter2();
	echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
	if(isset($HTTP_GET_VARS["screenid"]))
	{
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('Delete screen?');\">";
	}

	show_table2_header_end();
?>

<?php
	show_footer();
?>

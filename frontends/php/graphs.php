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
	$page["title"] = S_CONFIGURATION_OF_GRAPHS;
	$page["file"] = "graphs.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
	show_table_header(S_CONFIGURATION_OF_GRAPHS_BIG);
	echo "<br>";
?>

<?php
	if(!check_anyright("Graph","U"))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_footer();
		exit;
	}
?>

<?php
	if(isset($HTTP_GET_VARS["register"]))
	{
		if($HTTP_GET_VARS["register"]=="add")
		{
			$result=add_graph($HTTP_GET_VARS["name"],$HTTP_GET_VARS["width"],$HTTP_GET_VARS["height"]);
			show_messages($result, S_GRAPH_ADDED, S_CANNOT_ADD_GRAPH);
		}
		if($HTTP_GET_VARS["register"]=="update")
		{
			$result=update_graph($HTTP_GET_VARS["graphid"],$HTTP_GET_VARS["name"],$HTTP_GET_VARS["width"],$HTTP_GET_VARS["height"]);
			show_messages($result, S_GRAPH_UPDATED, S_CANNOT_UPDATE_GRAPH);
		}
		if($HTTP_GET_VARS["register"]=="delete")
		{
			$result=delete_graph($HTTP_GET_VARS["graphid"]);
			show_messages($result, S_GRAPH_DELETED, S_CANNOT_DELETE_GRAPH);
			unset($HTTP_GET_VARS["graphid"]);
		}
	}
?>

<?php
	show_table_header("GRAPHS");
	echo "<TABLE BORDER=0 COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TD WIDTH=5% NOSAVE><B>".S_ID."</B></TD>";
	echo "<TD><B>".S_NAME."</B></TD>";
	echo "<TD WIDTH=5% NOSAVE><B>".S_WIDTH."</B></TD>";
	echo "<TD WIDTH=5% NOSAVE><B>".S_HEIGHT."</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>".S_ACTIONS."</B></TD>";
	echo "</TR>";

	$result=DBselect("select g.graphid,g.name,g.width,g.height from graphs g order by g.name");
	$col=0;
	while($row=DBfetch($result))
	{
		if(!check_right("Graph","U",$row["graphid"]))
		{
			continue;
		}
		if($col++%2==0)	{ echo "<TR BGCOLOR=#EEEEEE>"; }
		else		{ echo "<TR BGCOLOR=#DDDDDD>"; }
	
		echo "<TD>".$row["graphid"]."</TD>";
		echo "<TD><a href=\"graph.php?graphid=".$row["graphid"]."\">".$row["name"]."</a></TD>";
		echo "<TD>".$row["width"]."</TD>";
		echo "<TD>".$row["height"]."</TD>";
		echo "<TD><A HREF=\"graphs.php?graphid=".$row["graphid"]."#form\">Change</A></TD>";
		echo "</TR>";
	}
	if(DBnum_rows($result)==0)
	{
		echo "<TR BGCOLOR=#EEEEEE>";
		echo "<TD COLSPAN=5 ALIGN=CENTER>".S_NO_GRAPHS_DEFINED."</TD>";
		echo "<TR>";
	}
	echo "</TABLE>";
?>

<?php
	echo "<a name=\"form\"></a>";

	if(isset($HTTP_GET_VARS["graphid"]))
	{
		$result=DBselect("select g.graphid,g.name,g.width,g.height from graphs g where graphid=".$HTTP_GET_VARS["graphid"]);
		$row=DBfetch($result);
		$name=$row["name"];
		$width=$row["width"];
		$height=$row["height"];
	}
	else
	{
		$name="";
		$width=900;
		$height=200;
	}

	echo "<br>";
	show_table2_header_begin();
	echo S_GRAPH;

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"graphs.php\">";
	if(isset($HTTP_GET_VARS["graphid"]))
	{
		echo "<input class=\"biginput\" name=\"graphid\" type=\"hidden\" value=".$HTTP_GET_VARS["graphid"].">";
	}
	echo S_NAME; 
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"name\" value=\"$name\" size=32>";

	show_table2_v_delimiter();
	echo S_WIDTH;
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"width\" size=5 value=\"$width\">";

	show_table2_v_delimiter();
	echo S_HEIGHT;
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"height\" size=5 value=\"$height\">";

	show_table2_v_delimiter2();
	echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
	if(isset($HTTP_GET_VARS["graphid"]))
	{
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('".S_DELETE_GRAPH_Q."');\">";
	}

	show_table2_header_end();
?>

<?php
	show_footer();
?>

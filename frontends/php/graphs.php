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
	include "include/forms.inc.php";
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
	if(isset($_GET["register"]))
	{
		if($_GET["register"]=="add")
		{
			$result=add_graph($_GET["name"],$_GET["width"],$_GET["height"],$_GET["yaxistype"],$_GET["yaxismin"],$_GET["yaxismax"]);
			show_messages($result, S_GRAPH_ADDED, S_CANNOT_ADD_GRAPH);
		}
		if($_GET["register"]=="update")
		{
			$result=update_graph($_GET["graphid"],$_GET["name"],$_GET["width"],$_GET["height"],$_GET["yaxistype"],$_GET["yaxismin"],$_GET["yaxismax"]);
			show_messages($result, S_GRAPH_UPDATED, S_CANNOT_UPDATE_GRAPH);
		}
		if($_GET["register"]=="delete")
		{
			$result=delete_graph($_GET["graphid"]);
			show_messages($result, S_GRAPH_DELETED, S_CANNOT_DELETE_GRAPH);
			unset($_GET["graphid"]);
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

	insert_graph_form();

?>

<?php
	show_footer();
?>

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
	$page["title"] = "Configuration of graph";
	$page["file"] = "graph.php";
	show_header($page["title"],0,0);
?>

<?php
	show_table_header("CONFIGURATION OF GRAPH");
	echo "<br>";
?>

<?php
	if(!check_right("Graph","R",$HTTP_GET_VARS["graphid"]))
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
			$result=add_item_to_graph($HTTP_GET_VARS["graphid"],$HTTP_GET_VARS["itemid"],$HTTP_GET_VARS["color"],$HTTP_GET_VARS["drawtype"],$HTTP_GET_VARS["sortorder"]);
			show_messages($result,"Item added","Cannot add item");
		}
		if($HTTP_GET_VARS["register"]=="update")
		{
			$result=update_graph_item($HTTP_GET_VARS["gitemid"],$HTTP_GET_VARS["itemid"],$HTTP_GET_VARS["color"],$HTTP_GET_VARS["drawtype"],$HTTP_GET_VARS["sortorder"]);
			show_messages($result,"Item updated","Cannot update item");
		}
		if($HTTP_GET_VARS["register"]=="delete")
		{
			$result=delete_graphs_item($HTTP_GET_VARS["gitemid"]);
			show_messages($result,"Item deleted","Cannot delete item");
			unset($HTTP_GET_VARS["gitemid"]);
		}
		if($HTTP_GET_VARS["register"]=="up")
		{
			$sql="update graphs_items set sortorder=sortorder-1 where sortorder>0 and gitemid=".$HTTP_GET_VARS["gitemid"];
			$result=DBexecute($sql);
			show_messages($result,"Sort order updated","Cannot update sort order");
			unset($HTTP_GET_VARS["gitemid"]);
		}
		if($HTTP_GET_VARS["register"]=="down")
		{
			$sql="update graphs_items set sortorder=sortorder+1 where sortorder<100 and gitemid=".$HTTP_GET_VARS["gitemid"];
			$result=DBexecute($sql);
			show_messages($result,"Sort order updated","Cannot update sort order");
			unset($HTTP_GET_VARS["gitemid"]);
		}
	}
?>

<?php
	$result=DBselect("select name from graphs where graphid=".$HTTP_GET_VARS["graphid"]);
	$row=DBfetch($result);
	show_table_header($row["name"]);
	echo "<TABLE BORDER=0 COLS=4 align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=#DDDDDD>";
	echo "<TD ALIGN=CENTER>";
	echo "<IMG SRC=\"chart2.php?graphid=".$HTTP_GET_VARS["graphid"]."&period=3600&from=0\">";
	echo "</TD>";
	echo "</TR>";
	echo "</TABLE>";

	show_table_header("DISPLAYED PARAMETERS");
	echo "<TABLE BORDER=0 COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TD WIDTH=5% NOSAVE><B>Sort order</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Host</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Parameter</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Type</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Color</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Actions</B></TD>";
	echo "</TR>";

	$sql="select i.itemid,h.host,i.description,gi.gitemid,gi.color,gi.drawtype,gi.sortorder from hosts h,graphs_items gi,items i where i.itemid=gi.itemid and gi.graphid=".$HTTP_GET_VARS["graphid"]." and h.hostid=i.hostid order by gi.sortorder";
	$result=DBselect($sql);
	$col=0;
	while($row=DBfetch($result))
	{
		if($col++%2==0)	{ echo "<TR BGCOLOR=#EEEEEE>"; }
		else		{ echo "<TR BGCOLOR=#DDDDDD>"; }

		echo "<TD>".$row["sortorder"]."</TD>";
		echo "<TD>".$row["host"]."</TD>";
		echo "<TD><a href=\"chart.php?itemid=".$row["itemid"]."&period=3600&from=0\">".$row["description"]."</a></TD>";
		echo "<TD>".get_drawtype_description($row["drawtype"])."</TD>";
		echo "<TD>".$row["color"]."</TD>";
		echo "<TD>";
		echo "<A HREF=\"graph.php?graphid=".$HTTP_GET_VARS["graphid"]."&gitemid=".$row["gitemid"]."#form\">Change</A>";
		echo " - ";
		echo "<A HREF=\"graph.php?graphid=".$HTTP_GET_VARS["graphid"]."&gitemid=".$row["gitemid"]."&register=up\">Up</A>";
		echo " - ";
		echo "<A HREF=\"graph.php?graphid=".$HTTP_GET_VARS["graphid"]."&gitemid=".$row["gitemid"]."&register=down\">Down</A>";
		echo "</TD>";
		echo "</TR>";
	}
	echo "</TABLE>";
?>

<?php
	echo "<br>";
	echo "<a name=\"form\"></a>";

	if(isset($HTTP_GET_VARS["gitemid"]))
	{
		$sql="select itemid,color,drawtype,sortorder from graphs_items where gitemid=".$HTTP_GET_VARS["gitemid"];
		$result=DBselect($sql);
		$itemid=DBget_field($result,0,0);
		$color=DBget_field($result,0,1);
		$drawtype=DBget_field($result,0,2);
		$sortorder=DBget_field($result,0,3);
	}
	else
	{
		$sortorder=0;
	}

	show_table2_header_begin();
	echo "New item for graph";

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"graph.php\">";
	echo "<input name=\"graphid\" type=\"hidden\" value=".$HTTP_GET_VARS["graphid"].">";
	if(isset($HTTP_GET_VARS["gitemid"]))
	{
		echo "<input name=\"gitemid\" type=\"hidden\" value=".$HTTP_GET_VARS["gitemid"].">";
	}

	echo "Parameter";
	show_table2_h_delimiter();
	$result=DBselect("select h.host,i.description,i.itemid from hosts h,items i where h.hostid=i.hostid and h.status in (0,2) and i.status=0 order by h.host,i.description");
	echo "<select name=\"itemid\" size=1>";
	for($i=0;$i<DBnum_rows($result);$i++)
	{
		$host_=DBget_field($result,$i,0);
		$description_=DBget_field($result,$i,1);
		$itemid_=DBget_field($result,$i,2);
		if(isset($itemid)&&($itemid==$itemid_))
		{
			echo "<OPTION VALUE='$itemid_' SELECTED>$host_: $description_";
		}
		else
		{
			echo "<OPTION VALUE='$itemid_'>$host_: $description_";
		}
	}
	echo "</SELECT>";

	show_table2_v_delimiter();
	echo "Type";
	show_table2_h_delimiter();
	echo "<select name=\"drawtype\" size=1>";
	echo "<OPTION VALUE='0' ".iif(isset($drawtype)&&($drawtype==0),"SELECTED","").">".get_drawtype_description(0);
	echo "<OPTION VALUE='1' ".iif(isset($drawtype)&&($drawtype==1),"SELECTED","").">".get_drawtype_description(1);
	echo "<OPTION VALUE='2' ".iif(isset($drawtype)&&($drawtype==2),"SELECTED","").">".get_drawtype_description(2);
	echo "<OPTION VALUE='3' ".iif(isset($drawtype)&&($drawtype==3),"SELECTED","").">".get_drawtype_description(3);
	echo "</SELECT>";

	show_table2_v_delimiter();
	echo "Color";
	show_table2_h_delimiter();
	echo "<select name=\"color\" size=1>";
	echo "<OPTION VALUE='Black' ".iif(isset($color)&&($color=="Black"),"SELECTED","").">Black";
	echo "<OPTION VALUE='Blue' ".iif(isset($color)&&($color=="Blue"),"SELECTED","").">Blue";
	echo "<OPTION VALUE='Cyan' ".iif(isset($color)&&($color=="Cyan"),"SELECTED","").">Cyan";
	echo "<OPTION VALUE='Dark Blue' ".iif(isset($color)&&($color=="Dark Blue"),"SELECTED","").">Dark blue";
	echo "<OPTION VALUE='Dark Green' ".iif(isset($color)&&($color=="Dark Green"),"SELECTED","").">Dark green";
	echo "<OPTION VALUE='Dark Red' ".iif(isset($color)&&($color=="Dark Red"),"SELECTED","").">Dark red";
	echo "<OPTION VALUE='Dark Yellow' ".iif(isset($color)&&($color=="Dark Yellow"),"SELECTED","").">Dark yellow";
	echo "<OPTION VALUE='Green' ".iif(isset($color)&&($color=="Green"),"SELECTED","").">Green";
	echo "<OPTION VALUE='Red' ".iif(isset($color)&&($color=="Red"),"SELECTED","").">Red";
	echo "<OPTION VALUE='White' ".iif(isset($color)&&($color=="White"),"SELECTED","").">White";
	echo "<OPTION VALUE='Yellow' ".iif(isset($color)&&($color=="Yellow"),"SELECTED","").">Yellow";
	echo "</SELECT>";

	show_table2_v_delimiter();
	echo nbsp("Sort order (0->100)");
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"sortorder\" value=\"$sortorder\" size=3>";

	show_table2_v_delimiter2();
	echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
	if(isset($itemid))
	{
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\">";
	}

	show_table2_header_end();
?>

<?php
	show_footer();
?>

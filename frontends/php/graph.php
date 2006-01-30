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
	$page["title"] = "S_CONFIGURATION_OF_GRAPH";
	$page["file"] = "graph.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>
<?php
	show_table_header(S_CONFIGURATION_OF_GRAPH_BIG);
	echo BR;
?>
<?php
	if(!check_right("Graph","R",$_REQUEST["graphid"]))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_page_footer();
		exit;
	}
?>
<?php

	if(isset($_REQUEST["register"]))
	{
		if($_REQUEST["register"]=="add")
		{
			add_graph_item_to_templates($_REQUEST["graphid"],$_REQUEST["itemid"],
				$_REQUEST["color"],$_REQUEST["drawtype"],$_REQUEST["sortorder"],
				$_REQUEST["yaxisside"]);

			$gitemid=add_item_to_graph($_REQUEST["graphid"],$_REQUEST["itemid"],
				$_REQUEST["color"],$_REQUEST["drawtype"],$_REQUEST["sortorder"],
				$_REQUEST["yaxisside"]);
			if($gitemid)
			{
				$graph=get_graph_by_graphid($_REQUEST["graphid"]);
				$item=get_item_by_itemid($_REQUEST["itemid"]);
				add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_GRAPH_ELEMENT,
					"Graph ID [".$_REQUEST["graphid"]."] Name [".$graph["name"]."]".
					" Added [".$item["description"]."]");
			}
			show_messages($gitemid,S_ITEM_ADDED, S_CANNOT_ADD_ITEM);
		}
		if($_REQUEST["register"]=="update")
		{
			update_graph_item_from_templates($_REQUEST["gitemid"],$_REQUEST["itemid"],
				$_REQUEST["color"],$_REQUEST["drawtype"],$_REQUEST["sortorder"],
				$_REQUEST["yaxisside"]);

			$result=update_graph_item($_REQUEST["gitemid"],$_REQUEST["itemid"],
				$_REQUEST["color"],$_REQUEST["drawtype"],$_REQUEST["sortorder"],
				$_REQUEST["yaxisside"]);
			if($result)
			{
				$graphitem=get_graphitem_by_gitemid($_REQUEST["gitemid"]);
				$graph=get_graph_by_graphid($graphitem["graphid"]);
				$item=get_item_by_itemid($graphitem["itemid"]);
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_GRAPH_ELEMENT,
					"Graph ID [".$graphitem["graphid"]."] Name [".$graph["name"]."]".
					" Updated [".$item["description"]."]");
			}
			show_messages($result, S_ITEM_UPDATED, S_CANNOT_UPDATE_ITEM);
		}
		if($_REQUEST["register"]=="delete")
		{
			delete_graph_item_from_templates($_REQUEST["gitemid"]);
			$graphitem=get_graphitem_by_gitemid($_REQUEST["gitemid"]);
			$graph=get_graph_by_graphid($graphitem["graphid"]);
			$item=get_item_by_itemid($graphitem["itemid"]);
			$result=delete_graphs_item($_REQUEST["gitemid"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_GRAPH_ELEMENT,
					"Graph ID [".$graphitem["graphid"]."] Name [".$graph["name"]."]".
					" Deleted [".$item["description"]."]");
			}
			show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);
			unset($_REQUEST["gitemid"]);
		}
		if($_REQUEST["register"]=="up")
		{
			move_up_graph_item_from_templates($_REQUEST["gitemid"]);
			$result = move_up_graph_item($_REQUEST["gitemid"]);
			show_messages($result, S_SORT_ORDER_UPDATED, S_CANNOT_UPDATE_SORT_ORDER);
			unset($_REQUEST["gitemid"]);
		}
		if($_REQUEST["register"]=="down")
		{
			move_down_graph_item_from_templates($_REQUEST["gitemid"]);
			$result = move_down_graph_item($_REQUEST["gitemid"]);
			show_messages($result, S_SORT_ORDER_UPDATED, S_CANNOT_UPDATE_SORT_ORDER);
			unset($_REQUEST["gitemid"]);
		}
	}
?>
<?php

	$result=DBselect("select name from graphs where graphid=".$_REQUEST["graphid"]);
	$row=DBfetch($result);
	show_table_header($row["name"]);
	echo "<TABLE BORDER=0 COLS=4 align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=#DDDDDD>";
	echo "<TD ALIGN=CENTER>";
	echo "<IMG SRC=\"chart2.php?graphid=".$_REQUEST["graphid"]."&period=3600&from=0\">";
	echo "</TD>";
	echo "</TR>";
	echo "</TABLE>";

	show_table_header(S_DISPLAYED_PARAMETERS_BIG);
	echo "<TABLE BORDER=0 COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TD WIDTH=5% NOSAVE><B>".S_SORT_ORDER."</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>".S_HOST."</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>".S_PARAMETER."</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>".S_TYPE."</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>".S_COLOR."</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>".S_ACTIONS."</B></TD>";
	echo "</TR>";

	$sql="select i.itemid,h.host,i.description,gi.gitemid,gi.color,gi.drawtype,gi.sortorder,i.key_".
		" from hosts h,graphs_items gi,items i where i.itemid=gi.itemid".
		" and gi.graphid=".$_REQUEST["graphid"]." and h.hostid=i.hostid order by gi.sortorder";
	$result=DBselect($sql);
	$col=0;
	while($row=DBfetch($result))
	{
		if($col++%2==0)	{ echo "<TR BGCOLOR=#EEEEEE>"; }
		else		{ echo "<TR BGCOLOR=#DDDDDD>"; }

		echo "<TD>".$row["sortorder"]."</TD>";
		echo "<TD>".$row["host"]."</TD>";
		echo "<TD><a href=\"chart.php?itemid=".$row["itemid"]."&period=3600&from=0\">".item_description($row["description"],$row["key_"])."</a></TD>";
		echo "<TD>".get_drawtype_description($row["drawtype"])."</TD>";
		echo "<TD>".$row["color"]."</TD>";
		echo "<TD>";
		echo "<A HREF=\"graph.php?graphid=".$_REQUEST["graphid"]."&gitemid=".$row["gitemid"]."#form\">".S_CHANGE."</A>";
		echo " - ";
		echo "<A HREF=\"graph.php?graphid=".$_REQUEST["graphid"]."&gitemid=".$row["gitemid"]."&register=up\">".S_UP."</A>";
		echo " - ";
		echo "<A HREF=\"graph.php?graphid=".$_REQUEST["graphid"]."&gitemid=".$row["gitemid"]."&register=down\">".S_DOWN."</A>";
		echo "</TD>";
		echo "</TR>";
	}
	echo "</TABLE>";
?>
<?php
	echo "<br>";
	echo "<a name=\"form\"></a>";

	if(isset($_REQUEST["gitemid"]))
	{
		$sql="select itemid,color,drawtype,sortorder,yaxisside from graphs_items".
			" where gitemid=".$_REQUEST["gitemid"];
		$result=DBselect($sql);
		$row=DBfetch($result);
		$itemid=$row["itemid"];
		$color=$row["color"];
		$drawtype=$row["drawtype"];
		$sortorder=$row["sortorder"];
		$yaxisside=$row["yaxisside"];
	}
	else
	{
		$sortorder=0;
		$yaxisside=1;
	}

	show_form_begin("graph.item");
	echo S_NEW_ITEM_FOR_THE_GRAPH;

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"graph.php\">";
	echo "<input name=\"graphid\" type=\"hidden\" value=".$_REQUEST["graphid"].">";
	if(isset($_REQUEST["gitemid"]))
	{
		echo "<input name=\"gitemid\" type=\"hidden\" value=".$_REQUEST["gitemid"].">";
	}

	echo S_PARAMETER;
	show_table2_h_delimiter();
	$result=DBselect("select h.host,i.description,i.itemid,i.key_ from hosts h,items"
		" where h.hostid=i.hostid and h.status in(".HOST_STATUS_MONITORED.",".HOST_STATUS_TEMPLATE.")".
		" and i.status=".ITEM_STATUS_ACTIVE." order by h.host,i.description");
	echo "<select name=\"itemid\" size=1>";
	while($row=DBfetch($result))
	{
		$host_=$row["host"];
		$description_=item_description($row["description"],$row["key_"]);
		$itemid_=$row["itemid"];
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
	echo S_TYPE;
	show_table2_h_delimiter();
	echo "<select name=\"drawtype\" size=1>";
	echo "<OPTION VALUE='0' ".iif(isset($drawtype)&&($drawtype==0),"SELECTED","").">".get_drawtype_description(0);
	echo "<OPTION VALUE='1' ".iif(isset($drawtype)&&($drawtype==1),"SELECTED","").">".get_drawtype_description(1);
	echo "<OPTION VALUE='2' ".iif(isset($drawtype)&&($drawtype==2),"SELECTED","").">".get_drawtype_description(2);
	echo "<OPTION VALUE='3' ".iif(isset($drawtype)&&($drawtype==3),"SELECTED","").">".get_drawtype_description(3);
	echo "</SELECT>";

	show_table2_v_delimiter();
	echo S_YAXIS_SIDE;
	show_table2_h_delimiter();
	echo "<SELECT class=\"biginput\" NAME=\"yaxisside\" size=\"1\"\">";
	echo "<OPTION VALUE=\"1\" "; if($yaxisside==GRAPH_YAXIS_SIDE_RIGHT)	echo "SELECTED"; echo ">".S_RIGHT;
	echo "<OPTION VALUE=\"0\" "; if($yaxisside==GRAPH_YAXIS_SIDE_LEFT)	echo "SELECTED"; echo ">".S_LEFT;
	echo "</SELECT>";

	show_table2_v_delimiter();
	echo S_COLOR;
	show_table2_h_delimiter();
	echo "<select name=\"color\" size=1>";
	echo "<OPTION VALUE='Black' ".iif(isset($color)&&($color=="Black"),"SELECTED","").">".S_BLACK;
	echo "<OPTION VALUE='Blue' ".iif(isset($color)&&($color=="Blue"),"SELECTED","").">".S_BLUE;
	echo "<OPTION VALUE='Cyan' ".iif(isset($color)&&($color=="Cyan"),"SELECTED","").">".S_CYAN;
	echo "<OPTION VALUE='Dark Blue' ".iif(isset($color)&&($color=="Dark Blue"),"SELECTED","").">".S_DARK_BLUE;
	echo "<OPTION VALUE='Dark Green' ".iif(isset($color)&&($color=="Dark Green"),"SELECTED","").">".S_DARK_GREEN;
	echo "<OPTION VALUE='Dark Red' ".iif(isset($color)&&($color=="Dark Red"),"SELECTED","").">".S_DARK_RED;
	echo "<OPTION VALUE='Dark Yellow' ".iif(isset($color)&&($color=="Dark Yellow"),"SELECTED","").">".S_DARK_YELLOW;
	echo "<OPTION VALUE='Green' ".iif(isset($color)&&($color=="Green"),"SELECTED","").">".S_GREEN;
	echo "<OPTION VALUE='Red' ".iif(isset($color)&&($color=="Red"),"SELECTED","").">".S_RED;
	echo "<OPTION VALUE='White' ".iif(isset($color)&&($color=="White"),"SELECTED","").">".S_WHITE;
	echo "<OPTION VALUE='Yellow' ".iif(isset($color)&&($color=="Yellow"),"SELECTED","").">".S_YELLOW;
	echo "</SELECT>";

	show_table2_v_delimiter();
	echo nbsp(S_SORT_ORDER_1_100);
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"sortorder\" value=\"$sortorder\" size=3>";

	show_table2_v_delimiter2();
	echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
	if(isset($itemid))
	{
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('Delete graph element?');\">";
	}

	show_table2_header_end();
?>
<?php
	show_page_footer();
?>

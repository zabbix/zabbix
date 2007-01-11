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
	include "include/classes/graph.inc.php";

	if(!check_right("Graph","R",$_REQUEST["graphid"]))
	{
		exit;
	}
	
	$graph=new Graph();
	if(isset($_REQUEST["period"]))
	{
		$graph->setPeriod($_REQUEST["period"]);
	}
	if(isset($_REQUEST["from"]))
	{
		$graph->setFrom($_REQUEST["from"]);
	}
	if(isset($_REQUEST["stime"]))
	{
		$graph->setSTime($_REQUEST["stime"]);
	}
	if(isset($_REQUEST["border"]))
	{
		$graph->setBorder(0);
	}

	$result=DBselect("select * from graphs where graphid=".$_REQUEST["graphid"]);
	$row=DBfetch($result);
	$db_hosts = get_hosts_by_graphid($_REQUEST["graphid"]);
	$name=$row["name"];

	$db_host = DBfetch($db_hosts);
	if($db_host)
	{
		$name = $db_host["host"].":".$name;
	}
	if(isset($_REQUEST["width"])&&$_REQUEST["width"]>0)
	{
		$width=$_REQUEST["width"];
	}
	else
	{
		$width=$row["width"];
	}
	if(isset($_REQUEST["height"])&&$_REQUEST["height"]>0)
	{
		$height=$_REQUEST["height"];
	}
	else
	{
		$height=$row["height"];
	}

	$graph->ShowWorkPeriod($row["show_work_period"]);
	$graph->ShowTriggers($row["show_triggers"]);

	$graph->setWidth($width);
	$graph->setHeight($height);
	$graph->setHeader($name);
	$graph->setYAxisType($row["yaxistype"]);
	$graph->setYAxisMin($row["yaxismin"]);
	$graph->setYAxisMax($row["yaxismax"]);

	$result=DBselect("select gi.*,i.description,h.host,gi.drawtype from graphs_items gi,items i,hosts h where gi.itemid=i.itemid and gi.graphid=".$_REQUEST["graphid"]." and i.hostid=h.hostid order by gi.sortorder");

	while($row=DBfetch($result))
	{
		$graph->addItem(
			$row["itemid"],
			$row["yaxisside"],
			$row["calc_fnc"],
			$row["color"],
			$row["drawtype"],
			$row["type"],
			$row["periods_cnt"]
			);
	}
	$graph->Draw();
?>

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
	include "include/classes.inc.php";

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

	$result=DBselect("select name,width,height,yaxistype,yaxismin,yaxismax from graphs where graphid=".$_REQUEST["graphid"]);

	$name=DBget_field($result,0,0);
	if(isset($_REQUEST["width"])&&$_REQUEST["width"]>0)
	{
		$width=$_REQUEST["width"];
	}
	else
	{
		$width=DBget_field($result,0,1);
	}
	if(isset($_REQUEST["height"])&&$_REQUEST["height"]>0)
	{
		$height=$_REQUEST["height"];
	}
	else
	{
		$height=DBget_field($result,0,2);
	}

	$graph->setWidth($width);
	$graph->setHeight($height);
	$graph->setHeader(DBget_field($result,0,0));
	$graph->setYAxisType(DBget_field($result,0,3));
	$graph->setYAxisMin(DBget_field($result,0,4));
	$graph->setYAxisMax(DBget_field($result,0,5));

	$result=DBselect("select gi.itemid,i.description,gi.color,h.host,gi.drawtype,gi.yaxisside from graphs_items gi,items i,hosts h where gi.itemid=i.itemid and gi.graphid=".$_REQUEST["graphid"]." and i.hostid=h.hostid order by gi.sortorder");

	for($i=0;$i<DBnum_rows($result);$i++)
	{
		$graph->addItem(DBget_field($result,$i,0));
		$graph->setColor(DBget_field($result,$i,0), DBget_field($result,$i,2));
		$graph->setDrawtype(DBget_field($result,$i,0), DBget_field($result,$i,4));
		$graph->setYAxisSide(DBget_field($result,$i,0), DBget_field($result,$i,5));
	}

	$graph->Draw();
?>


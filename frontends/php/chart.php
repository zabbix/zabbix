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

	$graph=new Graph();
	if(isset($_REQUEST["period"]))
	{
		$graph->setPeriod($_REQUEST["period"]);
	}
	if(isset($_REQUEST["from"]))
	{
		$graph->setFrom($_REQUEST["from"]);
	}
	if(isset($_REQUEST["width"]))
	{
		$graph->setWidth($_REQUEST["width"]);
	}
	if(isset($_REQUEST["height"]))
	{
		$graph->setHeight($_REQUEST["height"]);
	}
	if(isset($_REQUEST["border"]))
	{
		$graph->setBorder(0);
	}
	if(isset($_REQUEST["stime"]))
	{
		$graph->setSTime($_REQUEST["stime"]);
	}
	$graph->addItem($_REQUEST["itemid"], GRAPH_YAXIS_SIDE_RIGHT, CALC_FNC_ALL);

	$graph->Draw();
?>

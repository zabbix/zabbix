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
	if(isset($_GET["period"]))
	{
		$graph->setPeriod($_GET["period"]);
	}
	if(isset($_GET["from"]))
	{
		$graph->setFrom($_GET["from"]);
	}
	if(isset($_GET["width"]))
	{
		$graph->setWidth($_GET["width"]);
	}
	if(isset($_GET["height"]))
	{
		$graph->setHeight($_GET["height"]);
	}
	if(isset($_GET["border"]))
	{
		$graph->setBorder(0);
	}
	$graph->addItem($_GET["itemid"]);

	$graph->Draw();
?>


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
	include "include/classes.inc.php";

	$graph=new Graph();
	if(isset($HTTP_GET_VARS["period"]))
	{
		$graph->setPeriod($HTTP_GET_VARS["period"]);
	}
	if(isset($HTTP_GET_VARS["from"]))
	{
		$graph->setFrom($HTTP_GET_VARS["from"]);
	}
	if(isset($HTTP_GET_VARS["width"]))
	{
		$graph->setWidth($HTTP_GET_VARS["width"]);
	}
	if(isset($HTTP_GET_VARS["height"]))
	{
		$graph->setHeight($HTTP_GET_VARS["height"]);
	}
	if(isset($HTTP_GET_VARS["border"]))
	{
		$graph->setBorder(0);
	}
	$graph->addItem($HTTP_GET_VARS["itemid"]);

	$graph->Draw();
?>


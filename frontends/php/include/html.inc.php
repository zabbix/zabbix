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
<?
	function	nbsp($str)
	{
		return str_replace(" ","&nbsp;",$str);;
	}

	function url1_param($parameter)
	{
		global $_GET;
	
		if(isset($_GET[$parameter]))
		{
			return "$parameter=".$_GET[$parameter];
		}
		else
		{
			return "";
		}
	}

	function url_param($parameter)
	{
		global $_GET;
	
		if(isset($_GET[$parameter]))
		{
			return "&$parameter=".$_GET[$parameter];
		}
		else
		{
			return "";
		}
	}

	function table_td($text,$attr)
	{
		echo "<td $attr>$text</td>";
	}
?>

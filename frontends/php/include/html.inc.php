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

	function table_begin($class="tborder")
	{
		echo "<table class=\"$class\" border=0 width=100% bgcolor='#AAAAAA' cellspacing=1 cellpadding=3>";
		echo "\n";
	}

	function table_header($elements)
	{
		echo "<tr bgcolor='#CCCCCC'>";
		while(list($num,$element)=each($elements))
		{
			echo "<td><b>".$element."</b></td>";
		}
		echo "</tr>";
		echo "\n";
	}

	function table_row($elements, $rownum)
	{
		if($rownum%2 == 1)	{ echo "<TR BGCOLOR=#DDDDDD>"; }
		else			{ echo "<TR BGCOLOR=#EEEEEE>"; }

		while(list($num,$element)=each($elements))
		{
			if(!$element)	continue;
			if(is_array($element))
			{
				echo "<td class=\"".$element["class"]."\">".$element["value"]."</td>";
			}
			else
			{
				echo "<td>".$element."</td>";
			}
		}
		echo "</tr>";
		echo "\n";
	}


	function table_end()
	{
		echo "</table>";
		echo "\n";
	}

	function table_td($text,$attr)
	{
		echo "<td $attr>$text</td>";
	}
?>

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
	$page["title"] = "Latest values";
	$page["file"] = "latest.php";
	show_header($page["title"],0,0);
?>

<?php
	if(!check_right("Item","R",$HTTP_GET_VARS["itemid"]))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_footer();
		exit;
	}
?>

<?php
	show_table_header_begin();
	$result=DBselect("select i.description,h.host,h.hostid from items i,hosts h where i.hostid=h.hostid and i.itemid=".$HTTP_GET_VARS["itemid"]);
	$description=DBget_field($result,0,0);
	$host=DBget_field($result,0,1);
	$hostid=DBget_field($result,0,2);

	echo "<A HREF='latest.php?hostid=$hostid'>$host</A> : <a href='compare.php?action=showhistory&itemid=".$HTTP_GET_VARS["itemid"]."'>$description</a>";

	show_table_v_delimiter();

	if(isset($HTTP_GET_VARS["type"])&&$HTTP_GET_VARS["type"]=="12hours")
	{
		echo "<b>[<a href='trends.php?itemid=".$HTTP_GET_VARS["itemid"]."&type=12hours'>12hours</a>]</b> ";
	}
	else
	{
		echo "<a href='trends.php?itemid=".$HTTP_GET_VARS["itemid"]."&type=12hours'>12hours</a> ";
	}
	if(isset($HTTP_GET_VARS["type"])&&$HTTP_GET_VARS["type"]=="4hours")
	{
		echo "<b>[<a href='trends.php?itemid=".$HTTP_GET_VARS["itemid"]."&type=4hours'>4hours</a>]</b> ";
	}
	else
	{
		echo "<a href='trends.php?itemid=".$HTTP_GET_VARS["itemid"]."&type=4hours'>4hours</a> ";
	}
	if(isset($HTTP_GET_VARS["type"])&&$HTTP_GET_VARS["type"]=="hour")
	{
		echo "<b>[<a href='trends.php?itemid=".$HTTP_GET_VARS["itemid"]."&type=hour'>hour</a>]</b> ";
	}
	else
	{
		echo "<a href='trends.php?itemid=".$HTTP_GET_VARS["itemid"]."&type=hour'>hour</a> ";
	}
	if(isset($HTTP_GET_VARS["type"])&&$HTTP_GET_VARS["type"]=="30min")
	{
		echo "<b>[<a href='trends.php?itemid=".$HTTP_GET_VARS["itemid"]."&type=30min'>30min</a>]</b> ";
	}
	else
	{
		echo "<a href='trends.php?itemid=".$HTTP_GET_VARS["itemid"]."&type=30min'>30min</a> ";
	}
	if(isset($HTTP_GET_VARS["type"])&&$HTTP_GET_VARS["type"]=="15min")
	{
		echo "<b>[<a href='trends.php?itemid=".$HTTP_GET_VARS["itemid"]."&type=15min'>15min</a>]</b> ";
	}
	else
	{
		echo "<a href='trends.php?itemid=".$HTTP_GET_VARS["itemid"]."&type=15min'>15min</a> ";
	}
	echo "</font>";


	if(isset($HTTP_GET_VARS["type"]))
	{
		show_table_v_delimiter();
		if(isset($HTTP_GET_VARS["trendavg"]))
		{
			echo "<a href='trends.php?itemid=".$HTTP_GET_VARS["itemid"]."&type=".$HTTP_GET_VARS["type"]."'>ALL</a> ";
		}
		else
		{
			echo "<a href='trends.php?itemid=".$HTTP_GET_VARS["itemid"]."&type=".$HTTP_GET_VARS["type"]."&trendavg=1'>AVG</a> ";
		}
	}

	show_table_header_end();
	echo "<br>";
?>

<?php
	if(isset($HTTP_GET_VARS["itemid"])&&isset($HTTP_GET_VARS["type"]))
	{
		show_table_header(strtoupper($HTTP_GET_VARS["type"]));
	}
	else
	{
		show_table_header("Select type of trend");
	}
	echo "<TABLE BORDER=0 COLS=4 align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=#EEEEEE>";
	echo "<TR BGCOLOR=#DDDDDD>";
	echo "<TD ALIGN=CENTER>";
	if(isset($HTTP_GET_VARS["itemid"])&&isset($HTTP_GET_VARS["type"]))
	{
		if(isset($HTTP_GET_VARS["trendavg"]))
		{
			echo "<script language=\"JavaScript\">";
			echo "if (navigator.appName == \"Microsoft Internet Explorer\")";
			echo "{";
			echo " document.write(\"<IMG SRC='trend.php?itemid=".$HTTP_GET_VARS["itemid"]."&type=".$HTTP_GET_VARS["type"]."&trendavg=1&width=\"+(document.body.clientWidth-108)+\"'>\")";
			echo "}";
			echo "else if (navigator.appName == \"Netscape\")";
			echo "{";
			echo " document.write(\"<IMG SRC='trend.php?itemid=".$HTTP_GET_VARS["itemid"]."&type=".$HTTP_GET_VARS["type"]."&trendavg=1&width=\"+(document.width-108)+\"'>\")";
			echo "}";
			echo "else";
			echo "{";
			echo " document.write(\"<IMG SRC='trend.php?itemid=".$HTTP_GET_VARS["itemid"]."&type=".$HTTP_GET_VARS["type"]."&trendavg=1'>\")";
			echo "}";
			echo "</script>";
		}
		else
		{
			echo "<script language=\"JavaScript\">";
			echo "if (navigator.appName == \"Microsoft Internet Explorer\")";
			echo "{";
			echo " document.write(\"<IMG SRC='trend.php?itemid=".$HTTP_GET_VARS["itemid"]."&type=".$HTTP_GET_VARS["type"]."&width=\"+(document.body.clientWidth-108)+\"'>\")";
			echo "}";
			echo "else if (navigator.appName == \"Netscape\")";
			echo "{";
			echo " document.write(\"<IMG SRC='trend.php?itemid=".$HTTP_GET_VARS["itemid"]."&type=".$HTTP_GET_VARS["type"]."&width=\"+(document.width-108)+\"'>\")";
			echo "}";
			echo "else";
			echo "{";
			echo " document.write(\"<IMG SRC='trend.php?itemid=".$HTTP_GET_VARS["itemid"]."&type=".$HTTP_GET_VARS["type"]."'>\")";
			echo "}";
			echo "</script>";
		}
	}
	else
	{
		echo "...";
	}
	echo "</TD>";
	echo "</TR>";
	echo "</TABLE>";

?>

<?php
	show_footer();
?>

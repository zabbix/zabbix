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

	$page["title"] = S_QUEUE_BIG;
	$page["file"] = "queue.php";
	show_header($page["title"],10,0);
?>
 
<?php
	if(!check_anyright("Host","R"))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_footer();
		exit;	
	}
?>

<?php
	show_table_header(S_QUEUE_OF_ITEMS_TO_BE_UPDATED_BIG);
?>
<?php
	$now=time();
	$result=DBselect("select i.itemid, i.nextcheck, i.description, h.host,h.hostid from items i,hosts h where i.status=0 and i.type not in (2) and h.status=0 and i.hostid=h.hostid and i.nextcheck<$now and i.key_<>'status' order by i.nextcheck");
	echo "<table border=0 width=100% bgcolor='#AAAAAA' cellspacing=1 cellpadding=3>";
	echo "\n";
	echo "<tr bgcolor='#CCCCCC'><td><b>".S_NEXT_CHECK."</b></td><td><b>".S_HOST."</b></td><td><b>".S_DESCRIPTION."</b></td></tr>";
	echo "\n";
	$col=0;
	while($row=DBfetch($result))
	{
		if(!check_right("Host","R",$row["hostid"]))
		{
			continue;
		}
		iif_echo($col++%2==0,
			"<tr bgcolor=#EEEEEE>",
			"<tr bgcolor=#DDDDDD>");
		table_td(date("m.d.Y H:i:s",$row["nextcheck"]),"");
		table_td($row["host"],"");
		table_td($row["description"],"");
		echo "</tr>";
		cr();
	}
	iif_echo(DBnum_rows($result)==0,
		"<TR BGCOLOR=#EEEEEE><TD COLSPAN=3 ALIGN=CENTER>".S_THE_QUEUE_IS_EMPTY."</TD><TR>",
		"");
	echo "</table>";
?>
<?php
	show_table_header(S_TOTAL.":$col");
?>

<?php
	show_footer();
?>

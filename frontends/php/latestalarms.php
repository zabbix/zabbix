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
	$page["title"] = "Latest alarms";
	$page["file"] = "latestalarms.php";

	include "include/config.inc.php";
	show_header($page["title"],30,0);
?>

<?php
	show_table_header_begin();
	echo "HISTORY OF ALARMS";
 
	show_table_v_delimiter();
?>

<?php
	if(isset($HTTP_GET_VARS["start"])&&($HTTP_GET_VARS["start"]<=0))
	{
		unset($HTTP_GET_VARS["start"]);
	}
	if(isset($HTTP_GET_VARS["start"]))
	{
		echo "[<A HREF=\"latestalarms.php?start=".($HTTP_GET_VARS["start"]-100)."\">";
		echo "Show previous 100</A>] ";
		echo "[<A HREF=\"latestalarms.php?start=".($HTTP_GET_VARS["start"]+100)."\">";
		echo "Show next 100</A>]";
	}
	else 
	{
		echo "[<A HREF=\"latestalarms.php?start=100\">";
		echo "Show next 100</A>]";
	}

	show_table_header_end();
	echo "<br>";

	show_table_header("ALARMS");
?>

<FONT COLOR="#000000">
<?php
	$sql="select max(alarmid) as max from alarms";
	$result=DBselect($sql);
	$row=DBfetch($result);
	$maxalarmid=@iif(DBnum_rows($result)>0,$row["max"],0);

	if(!isset($HTTP_GET_VARS["start"]))
	{
		$sql="select t.description,a.clock,a.value,t.triggerid,t.priority from alarms a,triggers t where t.triggerid=a.triggerid and a.alarmid>$maxalarmid-200 order by clock desc limit 200";
	}
	else
	{
		$sql="select t.description,a.clock,a.value,t.triggerid,t.priority from alarms a,triggers t where t.triggerid=a.triggerid and a.alarmid>$maxalarmid-".($HTTP_GET_VARS["start"]+200)." order by clock desc limit ".($HTTP_GET_VARS["start"]+200);
	}
	$result=DBselect($sql);

	echo "<TABLE WIDTH=100% align=center BORDER=0 BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR>";
	echo "<TD width=20%><b>Time</b></TD>";
	echo "<TD><b>Description</b></TD>";
	echo "<TD width=10%><b>Value</b></TD>";
	echo "<TD width=10%><b>Severity</b></TD>";
	echo "</TR>";
	$col=0;
	$i=0;
	while($row=DBfetch($result))
	{
		$i++;
		if(isset($HTTP_GET_VARS["start"])&&($i<$HTTP_GET_VARS["start"]))
		{
			continue;
		}
		if(!check_right_on_trigger("R",$row["triggerid"]))
		{
			continue;
		}
		if($col++%2==0)	{ echo "<tr bgcolor=#DDDDDD>"; }
		else		{ echo "<tr bgcolor=#EEEEEE>"; }

		if($col>100)	break;

		echo "<TD>",date("Y.M.d H:i:s",$row["clock"]),"</TD>";
//		$description=$row["description"];
//		if( strstr($description,"%s"))
//		{
			$description=expand_trigger_description($row["triggerid"]);
//		}
		echo "<TD><a href=\"alarms.php?triggerid=".$row["triggerid"]."\">$description</a></TD>";
//		echo "<TD><a href=\"alarms.php?triggerid=".$row["triggerid"]."\">".htmlspecialchars($description)."</a></TD>";
		if($row["value"] == 0)
		{
			echo "<TD><font color=\"00AA00\">OFF</font></TD>";
		}
		elseif($row["value"] == 1)
		{
			echo "<TD><font color=\"AA0000\">ON</font></TD>";
		}
		else
		{
			echo "<TD><font color=\"AAAAAA\">UNKNOWN</font></TD>";
		}
		if($row["priority"]==0)         echo "<TD ALIGN=CENTER>Not classified</TD>";
		elseif($row["priority"]==1)     echo "<TD ALIGN=CENTER>Information</TD>";
		elseif($row["priority"]==2)     echo "<TD ALIGN=CENTER>Warning</TD>";
		elseif($row["priority"]==3)     echo "<TD ALIGN=CENTER BGCOLOR=#DDAAAA>Average</TD>";
		elseif($row["priority"]==4)     echo "<TD ALIGN=CENTER BGCOLOR=#FF8888>High</TD>";
		elseif($row["priority"]==5)     echo "<TD ALIGN=CENTER BGCOLOR=RED>Disaster !!!</TD>";
		else                            echo "<TD ALIGN=CENTER><B>".$row["priority"]."</B></TD>";
		echo "</TR>";
		cr();
	}
	echo "</TABLE>";
?>
</FONT>
</TR>
</TABLE>

<?php
	show_footer();
?>

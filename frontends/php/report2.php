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
	$page["title"] = "Availability report";
	$page["file"] = "report2.php";
	show_header($page["title"],0,0);
?>

<?php
	if(!check_anyright("Host","R"))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_footer();
		exit;
	}
?>

<?php
	show_table_header_begin();
	echo "AVAILABILITY REPORT";

	show_table_v_delimiter();

	$result=DBselect("select h.hostid,h.host from hosts h,items i where h.status in (0,2) and h.hostid=i.hostid group by h.hostid,h.host order by h.host");

	while($row=DBfetch($result))
	{
		if(!check_right("Host","R",$row["hostid"]))
		{
			continue;
		}
		if( isset($HTTP_GET_VARS["hostid"]) && ($HTTP_GET_VARS["hostid"] == $row["hostid"]) )
		{
			echo "<b>[";
		}
		echo "<a href='report2.php?hostid=".$row["hostid"]."'>".$row["host"]."</a>";
		if(isset($HTTP_GET_VARS["hostid"]) && ($HTTP_GET_VARS["hostid"] == $row["hostid"]) )
		{
			echo "]</b>";
		}
		echo " ";
	}

	show_table_header_end();
?>

<?php
	if(isset($HTTP_GET_VARS["hostid"])&&!isset($HTTP_GET_VARS["triggerid"]))
	{
		echo "<br>";
		$result=DBselect("select host from hosts where hostid=".$HTTP_GET_VARS["hostid"]);
		$row=DBfetch($result);
		show_table_header($row["host"]);

		$result=DBselect("select distinct h.hostid,h.host,t.triggerid,t.expression,t.description,t.value from triggers t,hosts h,items i,functions f where f.itemid=i.itemid and h.hostid=i.hostid and t.status=0 and t.triggerid=f.triggerid and h.hostid=".$HTTP_GET_VARS["hostid"]." and h.status in (0,2) and i.status=0 order by h.host, t.description");
		echo "<TABLE BORDER=0 COLS=3 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
		echo "<TR>";
		echo "<TD><B>Description</B></TD>";
//		echo "<TD><B>Expression</B></TD>";
		echo "<TD WIDTH=5%><B>True</B></TD>";
		echo "<TD WIDTH=5%><B>False</B></TD>";
		echo "<TD WIDTH=5%><B>Unknown</B></TD>";
		echo "<TD WIDTH=5%><B>Graph</B></TD>";
		echo "</TR>\n";
		$col=0;
		while($row=DBfetch($result))
		{
			if(!check_right_on_trigger("R",$row["triggerid"])) 
			{
				continue;
			}
			$lasthost=$row["host"];

		        if($col++%2 == 1)	{ echo "<TR BGCOLOR=#DDDDDD>"; }
			else			{ echo "<TR BGCOLOR=#EEEEEE>"; }

//			$description=$row["description"];

//			if( strstr($description,"%s"))
//			{
				$description=expand_trigger_description($row["triggerid"]);
//			}
			echo "<TD><a href=\"alarms.php?triggerid=".$row["triggerid"]."\">$description</a></TD>";
//			$description=rawurlencode($row["description"]);
	
//			echo "<TD>".explode_exp($row["expression"],1)."</TD>";
			$availability=calculate_availability($row["triggerid"],0,0);
			echo "<TD><font color=\"AA0000\">";
			printf("%.4f%%",$availability["true"]);
			echo "</font></TD>";
			echo "<TD><font color=\"00AA00\">";
			printf("%.4f%%",$availability["false"]);
			echo "</font></TD>";
			echo "<TD>";
			printf("%.4f%%",$availability["unknown"]);
			echo "</TD>";
			echo "<TD>";
			echo "<a href=\"report2.php?hostid=".$HTTP_GET_VARS["hostid"]."&triggerid=".$row["triggerid"]."\">Show</a>";
			echo "</TD>";
			echo "</TR>\n";
		}
		echo "</table>\n";
	}
?>

<?php
	if(isset($HTTP_GET_VARS["triggerid"]))
	{
		echo "<TABLE BORDER=0 COLS=4 align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
		echo "<TR BGCOLOR=#EEEEEE>";
		echo "<TR BGCOLOR=#DDDDDD>";
		echo "<TD ALIGN=CENTER>";
		echo "<IMG SRC=\"chart4.php?triggerid=".$HTTP_GET_VARS["triggerid"]."\" border=0>";
		echo "</TD>";
		echo "</TR>";
		echo "</TABLE>";
	}
?>


<?php
	show_footer();
?>

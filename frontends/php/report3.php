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
	$page["title"] = "IT Services availability report";
	$page["file"] = "report3.php";
	show_header($page["title"],0,0);
?>

<?php
//	if(!check_right("Host","R",0))
//	{
//		show_table_header("<font color=\"AA0000\">No permissions !</font>");
//		show_footer();
//		exit;
//	}
?>

<?php
	if(!isset($HTTP_GET_VARS["serviceid"]))
	{
		show_table_header("<font color=\"AA0000\">Undefined serviceid !</font>");
		show_footer();
		exit;
	}
	$service=get_service_by_serviceid($HTTP_GET_VARS["serviceid"]);
?>

<?php
	show_table_header_begin();
	echo "IT SERVICES AVAILABILITY REPORT";
	echo "<br>";
	echo "<a href=\"srv_status.php?serviceid=".$service["serviceid"]."\">",$service["name"],"</a>";;

	show_table_v_delimiter();

	$result=DBselect("select h.hostid,h.host from hosts h,items i where h.status in (0,2) and h.hostid=i.hostid group by h.hostid,h.host order by h.host");

	$year=date("Y");
	for($year=date("Y")-2;$year<=date("Y");$year++)
	{
		if( isset($HTTP_GET_VARS["year"]) && ($HTTP_GET_VARS["year"] == $year) )
		{
			echo "<b>[";
		}
		echo "<a href='report3.php?serviceid=".$HTTP_GET_VARS["serviceid"]."&year=$year'>".$year."</a>";
		if(isset($HTTP_GET_VARS["year"]) && ($HTTP_GET_VARS["year"] == $year) )
		{
			echo "]</b>";
		}
		echo " ";
	}

	show_table_header_end();
?>

<?php


	echo "<br>";
	echo "<TABLE BORDER=0 COLS=3 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR>";
	echo "<TD WIDTH=15%><B>From</B></TD>";
	echo "<TD WIDTH=15%><B>Till</B></TD>";
	echo "<TD WIDTH=10%><B>OK</B></TD>";
	echo "<TD WIDTH=10%><B>Problems</B></TD>";
	echo "<TD WIDTH=15%><B>Percentage</B></TD>";
	echo "<TD><B>SLA</B></TD>";
	echo "</TR>\n";

	$col=0;
	$year=date("Y");
	for($year=date("Y")-2;$year<=date("Y");$year++)
	{
		if( isset($HTTP_GET_VARS["year"]) && ($HTTP_GET_VARS["year"] != $year) )
		{
			continue;
		}
		$start=mktime(0,0,0,1,1,$year);

		$wday=date("w",$start);
		if($wday==0) $wday=7;
		$start=$start-($wday-1)*24*3600;

		for($i=0;$i<53;$i++)
		{
			$period_start=$start+7*24*3600*$i;
			$period_end=$start+7*24*3600*($i+1);
			if($period_start>time())
			{
				break;
			}
			$stat=calculate_service_availability($service["serviceid"],$period_start,$period_end);
	
			if($col++%2==0)	{ echo "<tr bgcolor=#EEEEEE>"; }
			else		{ echo "<tr bgcolor=#DDDDDD>"; }

			echo "<td>"; echo  date("d M Y",$period_start); echo "</td>";
			echo "<td>"; echo  date("d M Y",$period_end); echo "</td>";
			$t=sprintf("%2.2f%%",$stat["problem"]);
			$t_time=sprintf("%dd %dh %dm",$stat["problem_time"]/(24*3600),($stat["problem_time"]%(24*3600))/3600,($stat["problem_time"]%(3600))/(60));
			$f=sprintf("%2.2f%%",$stat["ok"]);
			$f_time=sprintf("%dd %dh %dm",$stat["ok_time"]/(24*3600),($stat["ok_time"]%(24*3600))/3600,($stat["ok_time"]%(3600))/(60));
			echo "<td>"; echo "<font color=\"00AA00\">$f_time</font>" ; echo "</td>";
			echo "<td>"; echo "<font color=\"AA0000\">$t_time</a>" ; echo "</td>";
			echo "<td>"; echo "<font color=\"00AA00\">$f</font>/<font color=\"AA0000\">$t</font>" ; echo "</td>";
			if($service["showsla"]==1)
			{
				if($stat["ok"]>=$service["goodsla"])
				{
					echo "<td><font color=\"00AA00\">".$service["goodsla"]."%</font></td>";
				}
				else
				{
					echo "<td><font color=\"AA0000\">".$service["goodsla"]."%</font></td>";
				}
			}
			else
			{
				echo "<td>-</td>";
			}
		
			echo "</tr>";
		}
	}
	echo "</TABLE>";

	show_footer();
?>

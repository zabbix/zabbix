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
	$page["title"] = S_AVAILABILITY_REPORT;
	$page["file"] = "report2.php";
	show_header($page["title"],0,0);
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
	show_table_header_begin();
	echo S_AVAILABILITY_REPORT_BIG;

	show_table_v_delimiter();

	$result=DBselect("select h.hostid,h.host from hosts h,items i where h.status in (0,2) and h.hostid=i.hostid group by h.hostid,h.host order by h.host");

	while($row=DBfetch($result))
	{
		if(!check_right("Host","R",$row["hostid"]))
		{
			continue;
		}
		if( isset($_GET["hostid"]) && ($_GET["hostid"] == $row["hostid"]) )
		{
			echo "<b>[";
		}
		echo "<a href='report2.php?hostid=".$row["hostid"]."'>".$row["host"]."</a>";
		if(isset($_GET["hostid"]) && ($_GET["hostid"] == $row["hostid"]) )
		{
			echo "]</b>";
		}
		echo " ";
	}

	show_table_header_end();
?>

<?php
	if(isset($_GET["hostid"])&&!isset($_GET["triggerid"]))
	{
		echo "<br>";
		$result=DBselect("select host from hosts where hostid=".$_GET["hostid"]);
		$row=DBfetch($result);
		show_table_header($row["host"]);

		$result=DBselect("select distinct h.hostid,h.host,t.triggerid,t.expression,t.description,t.value from triggers t,hosts h,items i,functions f where f.itemid=i.itemid and h.hostid=i.hostid and t.status=0 and t.triggerid=f.triggerid and h.hostid=".$_GET["hostid"]." and h.status in (0,2) and i.status=0 order by h.host, t.description");
		table_begin();
		table_header(array(S_DESCRIPTION,S_TRUE,S_FALSE,S_UNKNOWN,S_GRAPH));
		$col=0;
		while($row=DBfetch($result))
		{
			if(!check_right_on_trigger("R",$row["triggerid"])) 
			{
				continue;
			}
			$lasthost=$row["host"];

			$description=expand_trigger_description($row["triggerid"]);
			$description="<a href=\"alarms.php?triggerid=".$row["triggerid"]."\">$description</a>";
	
			$availability=calculate_availability($row["triggerid"],0,0);

			$true=array("value"=>sprintf("%.4f%%",$availability["true"]), "class"=>"on");
			$false=array("value"=>sprintf("%.4f%%",$availability["false"]), "class"=>"off");
			$unknown=array("value"=>sprintf("%.4f%%",$availability["unknown"]), "class"=>"unknown");
			$actions="<a href=\"report2.php?hostid=".$_GET["hostid"]."&triggerid=".$row["triggerid"]."\">".S_SHOW."</a>";

			table_row(array(
				$description,
				$true,
				$false,
				$unknown,
				$actions
				),$col++);
		}
		table_end();
	}
?>

<?php
	if(isset($_GET["triggerid"]))
	{
		echo "<TABLE BORDER=0 COLS=4 align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
		echo "<TR BGCOLOR=#EEEEEE>";
		echo "<TR BGCOLOR=#DDDDDD>";
		echo "<TD ALIGN=CENTER>";
		echo "<IMG SRC=\"chart4.php?triggerid=".$_GET["triggerid"]."\" border=0>";
		echo "</TD>";
		echo "</TR>";
		echo "</TABLE>";
	}
?>


<?php
	show_footer();
?>

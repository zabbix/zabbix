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
?>

<?php
	show_table_header_begin();
	echo "IT SERVICES AVAILABILITY REPORT";

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

	$service=get_service_by_serviceid($HTTP_GET_VARS["serviceid"]);

	echo "<br>";
	echo "<TABLE BORDER=0 COLS=3 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR>";
	echo "<TD WIDTH=15%><B>From</B></TD>";
	echo "<TD WIDTH=15%><B>Till</B></TD>";
	echo "<TD WIDTH=10%><B>OK</B></TD>";
	echo "<TD WIDTH=10%><B>Problems</B></TD>";
	echo "<TD WIDTH=10%><B>Percentage</B></TD>";
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
			$t=sprintf("%2.2f%%",$stat["true"]);
			$t_time=sprintf("%dd %dh %dm",$stat["true_time"]/(24*3600),($stat["true_time"]%(24*3600))/3600,($stat["true_time"]%(3600))/(60));
			$f=sprintf("%2.2f%%",$stat["false"]);
			$f_time=sprintf("%dd %dh %dm",$stat["false_time"]/(24*3600),($stat["false_time"]%(24*3600))/3600,($stat["false_time"]%(3600))/(60));
			echo "<td>"; echo "<font color=\"00AA00\">$f_time</font>" ; echo "</td>";
			echo "<td>"; echo "<font color=\"AA0000\">$t_time</a>" ; echo "</td>";
			echo "<td>"; echo "<font color=\"00AA00\">$f</font>/<font color=\"AA0000\">$t</font>" ; echo "</td>";
			echo "<td></td>";
		
			echo "</tr>";
		}
	}
	echo "</TABLE>";

	show_footer();
	exit;
?>

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

			$description=$row["description"];

			if( strstr($description,"%s"))
			{
				$description=expand_trigger_description($row["triggerid"]);
			}
			echo "<TD><a href=\"alarms.php?triggerid=".$row["triggerid"]."\">$description</a></TD>";
//			$description=rawurlencode($row["description"]);
	
//			echo "<TD>".explode_exp($row["expression"],1)."</TD>";
			$availability=calculate_availability($row["triggerid"],0,0);
			echo "<TD>";
			printf("%.4f%%",$availability["true"]);
			echo "</TD>";
			echo "<TD>";
			printf("%.4f%%",$availability["false"]);
			echo "</TD>";
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

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
	$page["title"] = "High-level representation of monitored data";
	$page["file"] = "srv_status.php";

	include "include/config.inc.php";
	show_header($page["title"],30,0);
?>
 
<?php
	show_table_header("IT SERVICES");

	if(isset($HTTP_GET_VARS["serviceid"])&&isset($HTTP_GET_VARS["showgraph"]))
	{
		echo "<TABLE BORDER=0 COLS=4 align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
		echo "<TR BGCOLOR=#EEEEEE>";
		echo "<TR BGCOLOR=#DDDDDD>";
		echo "<TD ALIGN=CENTER>";
		echo "<IMG SRC=\"chart5.php?serviceid=".$HTTP_GET_VARS["serviceid"]."\" border=0>";
		echo "</TD>";
		echo "</TR>";
		echo "</TABLE>";
		show_footer();
		exit;
	}

	$now=time();
	$result=DBselect("select serviceid,name,triggerid,status,showsla,goodsla from services order by sortorder,name");
	echo "<table border=0 width=100% bgcolor='#CCCCCC' cellspacing=1 cellpadding=3>";
	echo "\n";
	echo "<tr>";
	echo "<td width=40%><b>Service</b></td>";
	echo "<td width=10%><b>Status</b></td>";
	echo "<td><b>Reason</b></td>";
	echo "<td width=20%><b>SLA (last 7 days)</b></td>";
	echo "<td width=10% align=center><b>Planned/current&nbsp;SLA</b></td>";
	echo "<td width=5%><b>Graph</b></td>";
	echo "</tr>";
	echo "\n";
	$col=0;
	if(isset($HTTP_GET_VARS["serviceid"]))
	{
		echo "<tr bgcolor=#EEEEEE>";
		$service=get_service_by_serviceid($HTTP_GET_VARS["serviceid"]);
		echo "<td><b><a href=\"srv_status.php?serviceid=".$service["serviceid"]."\">".$service["name"]."</a></b></td>";
		echo "<td>".get_service_status_description($service["status"])."</td>";
		echo "<td>&nbsp;</td>";
		if($service["showsla"]==1)
		{
			echo "<td><img src=\"chart_sla.php?serviceid=".$service["serviceid"]."\"></td>";
		}
		else
		{
			echo "<td>-</td>";
		}
		if($service["showsla"]==1)
		{
			$now=time(NULL);
			$period_start=$now-7*24*3600;
			$period_end=$now;
			$stat=calculate_service_availability($service["serviceid"],$period_start,$period_end);

			if($service["goodsla"]>$stat["ok"])
			{
				$color="AA0000";
			}
			else
			{
				$color="00AA00";
			}
			printf ("<td><font color=\"00AA00\">%.2f%%</font><b>/</b><font color=\"%s\">%.2f%%</font></td>",$service["goodsla"],$color,$stat["ok"]);
		}
		else
		{
			echo "<td>-</td>";
		}
		echo "<td><a href=\"srv_status.php?serviceid=".$service["serviceid"]."&showgraph=1\">Show</a></td>";
		echo "</tr>"; 
		$col++;
	}
	while($row=DBfetch($result))
	{
		if(!isset($HTTP_GET_VARS["serviceid"]) && service_has_parent($row["serviceid"]))
		{
			continue;
		}
		if(isset($HTTP_GET_VARS["serviceid"]) && service_has_no_this_parent($HTTP_GET_VARS["serviceid"],$row["serviceid"]))
		{
			continue;
		}
		if(isset($row["triggerid"])&&!check_right_on_trigger("R",$row["triggerid"]))
		{
			continue;
		}
		if(isset($HTTP_GET_VARS["serviceid"])&&($HTTP_GET_VARS["serviceid"]==$row["serviceid"]))
		{
			echo "<tr bgcolor=#99AABB>";
		}
		else
		{
			if($col++%2==0)	{ echo "<tr bgcolor=#EEEEEE>"; }
			else		{ echo "<tr bgcolor=#DDDDDD>"; }
		}
		$childs=get_num_of_service_childs($row["serviceid"]);
		if(isset($row["triggerid"]))
		{
//			$trigger=get_trigger_by_triggerid($row["triggerid"]);
//			$description=$trigger["description"];
//			if( strstr($description,"%s"))
//			{
				$description=nbsp(expand_trigger_description($row["triggerid"]));
//			}
			$description="[<a href=\"alarms.php?triggerid=".$row["triggerid"]."\">TRIGGER</a>] $description";
		}
		else
		{
			$trigger_link="";
			$description=$row["name"];
		}
		if(isset($HTTP_GET_VARS["serviceid"]))
		{
			if($childs == 0)
			{
				echo "<td> - $description</td>";
			}
			else
			{
				echo "<td> - <a href=\"srv_status.php?serviceid=".$row["serviceid"]."\">$description</a></td>";
			}
		}
		else
		{
			if($childs == 0)
			{
				echo "<td>$description</td>";
			}
			else
			{
				echo "<td><a href=\"srv_status.php?serviceid=".$row["serviceid"]."\"> $description</a></td>";
			}
		}
		echo "<td>".get_service_status_description($row["status"])."</td>";
		if($row["status"]==0)
		{
			echo "<td>-</td>";
		}
		else
		{
			echo "<td>";
			echo "<ul>";
			$sql="select s.triggerid,s.serviceid from services s, triggers t where s.status>0 and s.triggerid is not NULL and t.triggerid=s.triggerid order by s.status desc,t.description";
			$result2=DBselect($sql);
			while($row2=DBfetch($result2))
			{
				if(does_service_depend_on_the_service($row["serviceid"],$row2["serviceid"]))
				{
//					$trigger=get_trigger_by_triggerid($row2["triggerid"]);
//					$description=$trigger["description"];
//					if( strstr($description,"%s"))
//					{
						$description=nbsp(expand_trigger_description($row2["triggerid"]));
//					}
					echo "<li class=\"itservices\"><a href=\"alarms.php?triggerid=".$row2["triggerid"]."\">$description</a></li>";
				}
			}
			echo "</ul>";
			echo "</td>";
		}

		if($row["showsla"]==1)
		{
			echo "<td><a href=\"report3.php?serviceid=".$row["serviceid"]."&year=".date("Y")."\"><img src=\"chart_sla.php?serviceid=".$row["serviceid"]."\" border=0></td>";
		}
		else
		{
			echo "<td>-</td>";
		}

		if($row["showsla"]==1)
		{
			$now=time(NULL);
			$period_start=$now-7*24*3600;
			$period_end=$now;
			$stat=calculate_service_availability($row["serviceid"],$period_start,$period_end);

			if($row["goodsla"]>$stat["ok"])
			{
				$color="AA0000";
			}
			else
			{
				$color="00AA00";
			}
			printf ("<td><font color=\"00AA00\">%.2f%%</font><b>/</b><font color=\"%s\">%.2f%%</font></td>",$row["goodsla"],$color,$stat["ok"]);
		}
		else
		{
			echo "<td>-</td>";
		}



		echo "<td><a href=\"srv_status.php?serviceid=".$row["serviceid"]."&showgraph=1\">Show</a></td>";
		echo "</tr>"; 
	}
	echo "</table>";
?>

<?php
	show_footer();
?>

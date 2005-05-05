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
	if(!isset($_GET["serviceid"]))
	{
		show_table_header("<font color=\"AA0000\">Undefined serviceid !</font>");
		show_footer();
		exit;
	}
	$service=get_service_by_serviceid($_GET["serviceid"]);
?>

<?php
	if(!isset($_GET["show"]))
	{
		$_GET["show"]=0;
	}

	$h1=S_IT_SERVICES_AVAILABILITY_REPORT_BIG;
	$h1=$h1.":"."<a href=\"srv_status.php?serviceid=".$service["serviceid"]."\">".$service["name"]."</a>";

#	$h2=S_GROUP."&nbsp;";
	$h2=S_YEAR."&nbsp;";
	$h2=$h2."<input name=\"serviceid\" type=\"hidden\" value=".$_GET["serviceid"].">";
	$h2=$h2."<select class=\"biginput\" name=\"year\" onChange=\"submit()\">";
	$result=DBselect("select h.hostid,h.host from hosts h,items i where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid group by h.hostid,h.host order by h.host");

	$year=date("Y");
	for($year=date("Y")-2;$year<=date("Y");$year++)
	{
		$h2=$h2."<option value=\"$year\" ".iif(isset($_GET["year"])&&($_GET["year"]==$year),"selected","").">".$year;
	}
	$h2=$h2."</select>";

	show_header2($h1, $h2, "<form name=\"selection\" method=\"get\" action=\"report3.php\">", "</form>");
?>

<?php
	table_begin();
	table_header(array(S_FROM,S_TILL,S_OK,S_PROBLEMS,S_PERCENTAGE,S_SLA));
	$col=0;
	$year=date("Y");
	for($year=date("Y")-2;$year<=date("Y");$year++)
	{
		if( isset($_GET["year"]) && ($_GET["year"] != $year) )
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

			$from=date(S_DATE_FORMAT_YMD,$period_start);
			$till=date(S_DATE_FORMAT_YMD,$period_end);
	
			$t=sprintf("%2.2f%%",$stat["problem"]);
			$t_time=sprintf("%dd %dh %dm",$stat["problem_time"]/(24*3600),($stat["problem_time"]%(24*3600))/3600,($stat["problem_time"]%(3600))/(60));
			$f=sprintf("%2.2f%%",$stat["ok"]);
			$f_time=sprintf("%dd %dh %dm",$stat["ok_time"]/(24*3600),($stat["ok_time"]%(24*3600))/3600,($stat["ok_time"]%(3600))/(60));

			$ok=array("value"=>$f_time,"class"=>"off");
			$problems=array("value"=>$t_time,"class"=>"on");
			$percentage=array("value"=>$f,"class"=>"off");

			if($service["showsla"]==1)
			{
				if($stat["ok"]>=$service["goodsla"])
				{
					$sla=array("value"=>$service["goodsla"],"class"=>"off");
				}
				else
				{
					$sla=array("value"=>$service["goodsla"],"class"=>"on");
				}
			}
			else
			{
				$sla="-";
			}
		
			table_row(array(
				$from,
				$till,
				$ok,
				$problems,
				$percentage,
				$sla
				),$col++);
		}
	}
	table_end();

	show_footer();
?>

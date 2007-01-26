<?php
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	$page["title"] = "S_IT_SERVICES_AVAILABILITY_REPORT";
	$page["file"] = "report3.php";
	show_header($page["title"],0,0);
?>

<?php
	if(!check_anyright("Host","R"))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_page_footer();
		exit;
	}
?>

<?php
	if(!isset($_REQUEST["serviceid"]))
	{
		show_table_header("<font color=\"AA0000\">Undefined serviceid !</font>");
		show_page_footer();
		exit;
	}
	$service=get_service_by_serviceid($_REQUEST["serviceid"]);
?>

<?php
	if(!isset($_REQUEST["period"]))
	{
		$_REQUEST["period"]="weekly";
	}

	$h1=S_IT_SERVICES_AVAILABILITY_REPORT_BIG;
	$h1=$h1.":"."<a href=\"srv_status.php?serviceid=".$service["serviceid"]."\">".$service["name"]."</a>";

#	$h2=S_GROUP.SPACE;
	$h2=S_YEAR.SPACE;
	$h2=$h2."<input name=\"serviceid\" type=\"hidden\" value=".$_REQUEST["serviceid"].">";
	$h2=$h2."<select class=\"biginput\" name=\"year\" onChange=\"submit()\">";
	$result=DBselect("select h.hostid,h.host from hosts h,items i where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid group by h.hostid,h.host order by h.host");

	$year=date("Y");
	for($year=date("Y")-2;$year<=date("Y");$year++)
	{
		$h2=$h2.form_select("year",$year,$year);
	}
	$h2=$h2."</select>";

	$h2=$h2.SPACE.S_PERIOD.SPACE;
	$h2=$h2."<select class=\"biginput\" name=\"period\" onChange=\"submit()\">";
	$h2=$h2.form_select("period","daily",S_DAILY);
	$h2=$h2.form_select("period","weekly",S_WEEKLY);
	$h2=$h2.form_select("period","monthly",S_MONTHLY);
	$h2=$h2.form_select("period","yearly",S_YEARLY);
	$h2=$h2."</select>";

	show_header2($h1, $h2, "<form name=\"selection\" method=\"get\" action=\"report3.php\">", "</form>");
?>

<?php
	$table = new CTableInfo();
	if($_REQUEST["period"]=="yearly")
	{
		$table->setHeader(array(S_YEAR,S_OK,S_PROBLEMS,S_PERCENTAGE,S_SLA));
		for($year=date("Y")-5;$year<=date("Y");$year++)
		{
			$start=mktime(0,0,0,1,1,$year);
			$end=mktime(0,0,0,1,1,$year+1);
			$stat=calculate_service_availability($service["serviceid"],$start,$end);

			$t=sprintf("%2.2f%%",$stat["problem"]);
			$t_time=sprintf("%dd %dh %dm",$stat["problem_time"]/(24*3600),($stat["problem_time"]%(24*3600))/3600,($stat["problem_time"]%(3600))/(60));
			$f=sprintf("%2.2f%%",$stat["ok"]);
			$f_time=sprintf("%dd %dh %dm",$stat["ok_time"]/(24*3600),($stat["ok_time"]%(24*3600))/3600,($stat["ok_time"]%(3600))/(60));

			$ok=new CSpan($f_time,"off");
			$problems=new CSpan($t_time,"on");
			$percentage=new CSpan($f,"off");

			if($service["showsla"]==1)
			{
				if($stat["ok"]>=$service["goodsla"])
				{
					$sla=new CSpan($service["goodsla"],"off");
				}
				else
				{
					$sla=new CSpan($service["goodsla"],"on");
				}
			}
			else
			{
				$sla="-";
			}
			$table->addRow(array(
				$year,
				$ok,
				$problems,
				$percentage,
				$sla
				));
		}
	}
	else if($_REQUEST["period"]=="monthly")
	{
		$table->setHeader(array(S_MONTH,S_OK,S_PROBLEMS,S_PERCENTAGE,S_SLA));
		for($month=1;$month<=12;$month++)
		{
			$start=mktime(0,0,0,$month,1,$_REQUEST["year"]);
			$end=mktime(0,0,0,$month+1,1,$_REQUEST["year"]);

			if($start>time())	break;

			$stat=calculate_service_availability($service["serviceid"],$start,$end);

			$t=sprintf("%2.2f%%",$stat["problem"]);
			$t_time=sprintf("%dd %dh %dm",$stat["problem_time"]/(24*3600),($stat["problem_time"]%(24*3600))/3600,($stat["problem_time"]%(3600))/(60));
			$f=sprintf("%2.2f%%",$stat["ok"]);
			$f_time=sprintf("%dd %dh %dm",$stat["ok_time"]/(24*3600),($stat["ok_time"]%(24*3600))/3600,($stat["ok_time"]%(3600))/(60));

			$ok=new CSpan($f_time,"off");
			$problems=new CSpan($t_time,"on");
			$percentage=new CSpan($f,"off");

			if($service["showsla"]==1)
			{
				if($stat["ok"]>=$service["goodsla"])
				{
					$sla=new CSpan($service["goodsla"],"off");
				}
				else
				{
					$sla=new CSpan($service["goodsla"],"on");
				}
			}
			else
			{
				$sla="-";
			}
			$table->addRow(array(
				date("M Y",$start),
				$ok,
				$problems,
				$percentage,
				$sla
				));
		}
	}
	else if($_REQUEST["period"]=="daily")
	{
		$table->setHeader(array(S_DAY,S_OK,S_PROBLEMS,S_PERCENTAGE,S_SLA));
		$s=mktime(0,0,0,1,1,$_REQUEST["year"]);
		$e=mktime(0,0,0,1,1,$_REQUEST["year"]+1);
		for($day=$s;$day<$e;$day+=24*3600)
		{
			$start=$day;
			$end=$day+24*3600;

			if($start>time())	break;

			$stat=calculate_service_availability($service["serviceid"],$start,$end);

			$t=sprintf("%2.2f%%",$stat["problem"]);
			$t_time=sprintf("%dd %dh %dm",$stat["problem_time"]/(24*3600),($stat["problem_time"]%(24*3600))/3600,($stat["problem_time"]%(3600))/(60));
			$f=sprintf("%2.2f%%",$stat["ok"]);
			$f_time=sprintf("%dd %dh %dm",$stat["ok_time"]/(24*3600),($stat["ok_time"]%(24*3600))/3600,($stat["ok_time"]%(3600))/(60));

			$ok=new CSpan($f_time,"off");
			$problems=new CSpan($t_time,"on");
			$percentage=new CSpan($f,"off");

			if($service["showsla"]==1)
			{
				if($stat["ok"]>=$service["goodsla"])
				{
					$sla=new CSpan($service["goodsla"],"off");
				}
				else
				{
					$sla=new CSpan($service["goodsla"],"on");
				}
			}
			else
			{
				$sla="-";
			}
			$table->addRow(array(
				date("d M Y",$start),
				$ok,
				$problems,
				$percentage,
				$sla
				));
		}
	}
	else
	{
	//--------Weekly-------------
	$table->setHeader(array(S_FROM,S_TILL,S_OK,S_PROBLEMS,S_PERCENTAGE,S_SLA));
	$year=date("Y");
	for($year=date("Y")-2;$year<=date("Y");$year++)
	{
		if( isset($_REQUEST["year"]) && ($_REQUEST["year"] != $year) )
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

			$ok=new CSpan($f_time,"off");
			$problems=new CSpan($t_time,"on");
			$percentage=new CSpan($f,"off");

			if($service["showsla"]==1)
			{
				if($stat["ok"]>=$service["goodsla"])
				{
					$sla=new CSpan($service["goodsla"],"off");
				}
				else
				{
					$sla=new CSpan($service["goodsla"],"on");
				}
			}
			else
			{
				$sla="-";
			}
		
			$table->addRow(array(
				$from,
				$till,
				$ok,
				$problems,
				$percentage,
				$sla
				));
		}
	}
	//--------Weekly-------------
	}
	$table->show();

	show_page_footer();
?>

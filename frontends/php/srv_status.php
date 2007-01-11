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
	$page["title"] = "S_IT_SERVICES";
	$page["file"] = "srv_status.php";
	show_header($page["title"],1,0);
?>
<?php
	if(!check_anyright("Service","R"))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_page_footer();
		exit;
	}
?>
<?php
	update_profile("web.menu.view.last",$page["file"]);
?>

<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"serviceid"=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,			NULL),
		"showgraph"=>		array(T_ZBX_INT, O_OPT,	P_SYS,		IN("1")."isset({serviceid})",NULL)
	);

	check_fields($fields);
?>
 
<?php
	show_table_header(S_IT_SERVICES_BIG);

	if(isset($_REQUEST["serviceid"])&&isset($_REQUEST["showgraph"]))
	{
		$table  = new CTableInfo();
		$table->AddRow("<IMG SRC=\"chart5.php?serviceid=".$_REQUEST["serviceid"]."\" border=0>");
		$table->Show();
		show_page_footer();
		exit;
	}

	$now=time();
	$result=DBselect("select serviceid,name,triggerid,status,showsla,goodsla from services order by sortorder,name");
//	table_begin();
	$table  = new CTableInfo();
	$table->SetHeader(array(S_SERVICE,S_STATUS,S_REASON,S_SLA_LAST_7_DAYS,nbsp(S_PLANNED_CURRENT_SLA),S_GRAPH));
	if(isset($_REQUEST["serviceid"]))
	{
		$service=get_service_by_serviceid($_REQUEST["serviceid"]);
		$srvc=new CLink($service["name"],"srv_status.php?serviceid=".$service["serviceid"],"action");

		$status=get_service_status_description($service["status"]);

		$reason=SPACE;
		if($service["showsla"]==1)
		{
			$sla="<img src=\"chart_sla.php?serviceid=".$service["serviceid"]."\">";
		}
		else
		{
			$sla=new CSpan("-","center");
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
			$sla2=sprintf("<font color=\"00AA00\">%.2f%%</font><b>/</b><font color=\"%s\">%.2f%%</font>",$service["goodsla"],$color,$stat["ok"]);
		}
		else
		{
			$sla2="-";
		}
		$actions=new CLink(S_SHOW,"srv_status.php?serviceid=".$service["serviceid"]."&showgraph=1","action");
		$table->addRow(array(
			$srvc,
			$status,
			$reason,
			$sla,
			$sla2,
			$actions
			));
	}
	while($row=DBfetch($result))
	{
		if(!isset($_REQUEST["serviceid"]) && service_has_parent($row["serviceid"]))
		{
			continue;
		}
		if(isset($_REQUEST["serviceid"]) && service_has_no_this_parent($_REQUEST["serviceid"],$row["serviceid"]))
		{
			continue;
		}
		if(isset($row["triggerid"])&&!check_right_on_trigger("R",$row["triggerid"]))
		{
			continue;
		}
		$childs=get_num_of_service_childs($row["serviceid"]);
		if(isset($row["triggerid"]))
		{
			$description=nbsp(expand_trigger_description($row["triggerid"]));
			$description="[<a href=\"alarms.php?triggerid=".$row["triggerid"]."\">".S_TRIGGER_BIG."</a>] $description";
		}
		else
		{
			$trigger_link="";
			$description=$row["name"];
		}
		if(isset($_REQUEST["serviceid"]))
		{
			if($childs == 0)
			{
				$service="$description";
			}
			else
			{
				$service=new CLink($description,"srv_status.php?serviceid=".$row["serviceid"],"action");
			}
		}
		else
		{
			if($childs == 0)
			{
				$service="$description";
			}
			else
			{
				$service=new CLink($description,"srv_status.php?serviceid=".$row["serviceid"],"action");
			}
		}
		$status=get_service_status_description($row["status"]);
		if($row["status"]==0)
		{
			$reason="-";
		}
		else
		{
			$reason="<ul>";
			$sql="select s.triggerid,s.serviceid from services s, triggers t where s.status>0 and s.triggerid is not NULL and t.triggerid=s.triggerid order by s.status desc,t.description";
			$result2=DBselect($sql);
			while($row2=DBfetch($result2))
			{
				if(does_service_depend_on_the_service($row["serviceid"],$row2["serviceid"]))
				{
					$description=nbsp(expand_trigger_description($row2["triggerid"]));
					$reason=$reason."<li class=\"itservices\"><a href=\"alarms.php?triggerid=".$row2["triggerid"]."\">$description</a></li>";
				}
			}
			$reason=$reason."</ul>";
		}

		if($row["showsla"]==1)
		{
			$sla="<a href=\"report3.php?serviceid=".$row["serviceid"]."&year=".date("Y")."\"><img src=\"chart_sla.php?serviceid=".$row["serviceid"]."\" border=0>";
		}
		else
		{
			$sla="-";
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
			$sla2=sprintf("<font color=\"00AA00\">%.2f%%</font><b>/</b><font color=\"%s\">%.2f%%</font>",$row["goodsla"],$color,$stat["ok"]);
		}
		else
		{
			$sla2="-";
		}

		$actions=new CLink(S_SHOW,"srv_status.php?serviceid=".$row["serviceid"]."&showgraph=1","action");
		$table->addRow(array(
			$service,
			$status,
			$reason,
			$sla,
			$sla2,
			$actions
			));
	}
	$table->Show();
?>

<?php
	show_page_footer();
?>

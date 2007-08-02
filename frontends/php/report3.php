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
	require_once "include/config.inc.php";
	require_once "include/services.inc.php";

	$page["title"] = "S_IT_SERVICES_AVAILABILITY_REPORT";
	$page["file"] = "report3.php";
	
include_once "include/page_header.php";

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"serviceid"=>		array(T_ZBX_INT, O_MAND,P_SYS,	DB_ID,			NULL),
		"period"=>		array(T_ZBX_STR, O_OPT,	null,	IN('"dayly","weekly","monthly","yearly"'),	NULL),
		"year"=>		array(T_ZBX_INT, O_OPT,	null,	null,		NULL)
	);

	check_fields($fields);

	$period = get_request("period", "weekly");
	$year	= get_request("year",	date("Y"));
	
	define("YEAR_LEFT_SHIFT", 5);
?>
<?php
	if(! (DBfetch(DBselect('select serviceid from services where serviceid='.$_REQUEST["serviceid"]))) )
	{
		fatal_error(S_NO_IT_SERVICE_DEFINED);
	}

	$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_MODE_LT);
	
	if( !($service = DBfetch(DBselect("select s.* from services s left join triggers t on s.triggerid=t.triggerid ".
		" left join functions f on t.triggerid=f.triggerid left join items i on f.itemid=i.itemid ".
		" where (i.hostid is NULL or i.hostid not in (".$denyed_hosts.")) ".
		" and ".DBid2nodeid("s.serviceid")."=".$ZBX_CURNODEID.
		" and s.serviceid=".$_REQUEST["serviceid"]
		))))
	{
		access_deny();
	}
?>
<?php
	$form = new CForm();
	$form->AddVar("serviceid", $_REQUEST["serviceid"]);

	$cmbPeriod = new CComboBox("period", $period, "submit();");
	$cmbPeriod->AddItem("dayly",S_DAILY);
	$cmbPeriod->AddItem("weekly",S_WEEKLY);
	$cmbPeriod->AddItem("monthly",S_MONTHLY);
	$cmbPeriod->AddItem("yearly",S_YEARLY);
	$form->AddItem(array(SPACE.S_PERIOD.SPACE, $cmbPeriod));

	$cmbYear = new CComboBox("year", $year, "submit();");
	for($y = (date("Y") - YEAR_LEFT_SHIFT); $y <= date("Y"); $y++)
	{
		$cmbYear->AddItem($y, $y);
	}
	$form->AddItem(array(SPACE.S_YEAR.SPACE, $cmbYear));

	show_table_header(array(
			S_IT_SERVICES_AVAILABILITY_REPORT_BIG,
			SPACE."\"",
			new CLink($service["name"],"srv_status.php?serviceid=".$service["serviceid"]),
			"\""
		),
		$form);
?>
<?php
	$table = new CTableInfo();
	
	$header = array(S_OK,S_PROBLEMS,S_DOWNTIME,S_PERCENTAGE,S_SLA);

        switch($period)
	{
		case "yearly":
			$from	= (date("Y") - YEAR_LEFT_SHIFT);
			$to	= date("Y");
			array_unshift($header, new CCol(S_YEAR,"center"));
			function get_time($y)	{	return mktime(0,0,0,1,1,$y);		}
			function format_time($t){	return date("Y", $t);			}
			function format_time2($t){	return null; };
			break;
		case "monthly":
			$from	= 1;
			$to	= 12;
			array_unshift($header, new CCol(S_MONTH,"center"));
			function get_time($m)	{	global $year;	return mktime(0,0,0,$m,1,$year);	}
			function format_time($t){	return date("M Y",$t);			}
			function format_time2($t){	return null; };
			break;
		case "dayly":
			$from	= 1;
			$to	= 365;
			array_unshift($header, new CCol(S_DAY,"center"));
			function get_time($d)	{	global $year;	return mktime(0,0,0,1,$d,$year);	}
			function format_time($t){	return date("d M Y",$t);		}
			function format_time2($t){	return null; };
			break;
		case "weekly":
		default:
			$from	= 0;
			$to	= 52;
			array_unshift($header,new CCol(S_FROM,"center"),new CCol(S_TILL,"center"));
			function get_time($w)	{
				global $year;	

				$time	= mktime(0,0,0,1, 1, $year);
				$wd	= date("w", $time);
				$wd	= $wd == 0 ? 6 : $wd - 1;

				return ($time + ($w*7 - $wd)*24*3600);
			}
			function format_time($t){	return date("d M Y H:i",$t);	}
			function format_time2($t){	return format_time($t); };
			break;

	}

	$table->SetHeader($header);

	for($t = $from; $t <= $to; $t++)
	{       
		if(($start = get_time($t)) > time())
			break;
		
		if(($end = get_time($t+1)) > time())
			$end = time();
		
		$stat = calculate_service_availability($service["serviceid"],$start,$end);

		$ok 		= new CSpan(
					sprintf("%dd %dh %dm",
						$stat["ok_time"]/(24*3600),
						($stat["ok_time"]%(24*3600))/3600,
						($stat["ok_time"]%(3600))/(60)),
					"off");
		
		$problems	= new CSpan(
					sprintf("%dd %dh %dm",
						$stat["problem_time"]/(24*3600),
						($stat["problem_time"]%(24*3600))/3600,
						($stat["problem_time"]%(3600))/(60)),
					"on");

		$downtime	= sprintf("%dd %dh %dm",
					$stat["downtime_time"]/(24*3600),
					($stat["downtime_time"]%(24*3600))/3600,
					($stat["downtime_time"]%(3600))/(60));
		
		$percentage	= new CSpan(sprintf("%2.2f%%",$stat["ok"]) , "off");

		$table->AddRow(array(
			format_time($start),
			format_time2($end),
			$ok,
			$problems,
			$downtime,
			$percentage,
			($service["showsla"]==1) ?
				new CSpan($service["goodsla"], ($stat["ok"] >= $service["goodsla"]) ? "off" : "on") :
				"-"
				
			));
	}

	$table->Show();
?>
<?php

include_once "include/page_footer.php";

?>

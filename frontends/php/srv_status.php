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

	$page["title"] = "S_IT_SERVICES";
	$page["file"] = "srv_status.php";

	define('ZBX_PAGE_DO_REFRESH', 1);

include_once "include/page_header.php";

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"serviceid"=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,			NULL),
		"showgraph"=>		array(T_ZBX_INT, O_OPT,	P_SYS,		IN("1")."isset({serviceid})",NULL),
		"path"=>		array(T_ZBX_STR, O_OPT,	null,		null,			NULL)
	);

	check_fields($fields);
?>
<?php
	$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_MODE_LT);

	if(isset($_REQUEST["serviceid"]) && $_REQUEST["serviceid"] > 0)
	{
		
		if( !($service = DBfetch(DBselect("select s.* from services s left join triggers t on s.triggerid=t.triggerid ".
			" left join functions f on t.triggerid=f.triggerid left join items i on f.itemid=i.itemid ".
			" where (i.hostid is null or i.hostid not in (".$denyed_hosts.")) ".
			" and ".DBid2nodeid("s.serviceid")."=".$ZBX_CURNODEID.
			" and s.serviceid=".$_REQUEST["serviceid"]
			))))
		{
			access_deny();
		}
	}
	unset($_REQUEST["serviceid"]);
?>
<?php
	$path = get_request('path', array());
	if(isset($service))
	{
		$path[count($path)] = array('id'=>$service["serviceid"], 'name'=>$service["name"]);
	}
	array_unique($path);
	
	$menu_path = array();
	$new_path = array();
	foreach($path as $el)
	{
		if(count($new_path)==0) 
		{
			$back_name = S_ROOT_SMALL;
			$back_id = 0;
		}
		else 
		{
			$back_name = $new_path[count($new_path)-1]['name'];
			$back_id = $new_path[count($new_path)-1]['id'];
		}

		if(isset($service) && $back_id == $service['serviceid'])	break;

		array_push($menu_path, unpack_object(new CLink($back_name, '?serviceid='.$back_id.url_param($new_path,false,'path'))));
		array_push($new_path, $el);
	}
	$_REQUEST['path'] = $path = $new_path;

	show_table_header(S_IT_SERVICES_BIG.": ".implode('/',$menu_path));

	unset($menu_path, $new_path, $el);

	if(isset($service)&&isset($_REQUEST["showgraph"]))
	{
		$table  = new CTable(null,'chart');
		$table->AddRow(new CImg("chart5.php?serviceid=".$service["serviceid"].url_param('path')));
		$table->Show();
	}
	else
	{
		$now=time();
		
		$table  = new CTableInfo();
		$table->SetHeader(array(S_SERVICE,S_STATUS,S_REASON,S_SLA_LAST_7_DAYS,nbsp(S_PLANNED_CURRENT_SLA),S_GRAPH));

		$result = DBselect("select distinct s.* from services s left join triggers t on s.triggerid=t.triggerid ".
				" left join functions f on t.triggerid=f.triggerid left join items i on f.itemid=i.itemid ".
				" left join services_links sl on s.serviceid=sl.servicedownid ".
				" where (i.hostid is null or i.hostid not in (".$denyed_hosts.")) ".
				" and ".DBid2nodeid("s.serviceid")."=".$ZBX_CURNODEID.
				" and (sl.serviceupid".(!isset($service) ?
					" is NULL " :
					"=".$service['serviceid']." or s.serviceid=".$service['serviceid'] ).") ".
				" order by sl.serviceupid,s.sortorder,s.name");

		while($row=DBfetch($result))
		{
			$description = array();
			
			if(isset($service))
			{
				if($row['serviceid'] == $service['serviceid'])
				{
					$row['name'] = new CSpan($row['name'],'bold');
				}
				else
				{
					array_push($description, " - ");
				}
			}

			$childs = get_num_of_service_childs($row["serviceid"]);


			if($childs && !(isset($service) && $service["serviceid"] == $row["serviceid"]))
			{
				array_push($description, new CLink($row['name'],"?serviceid=".$row["serviceid"].url_param('path'),'action'));
			}
			else
			{
				array_push($description, $row['name']);
			}

			if(isset($row["triggerid"]))
			{
				array_push($description, SPACE, "[", new CLink(
					expand_trigger_description($row["triggerid"]),
					"tr_events.php?triggerid=".$row["triggerid"]),
					"]");
			}
			
			if($row["status"]==0 || $service["serviceid"] == $row["serviceid"])
			{
				$reason="-";
			}
			else
			{
				$reason = new CList(null,"itservices");
				$result2=DBselect("select s.triggerid,s.serviceid from services s, triggers t ".
					" where s.status>0 and s.triggerid is not NULL and t.triggerid=s.triggerid ".
					" and ".DBid2nodeid("s.serviceid")."=".$ZBX_CURNODEID.
					" order by s.status desc,t.description");
					
				while($row2=DBfetch($result2))
				{
					if(does_service_depend_on_the_service($row["serviceid"],$row2["serviceid"]))
					{
						$reason->AddItem(new CLink(
							expand_trigger_description($row2["triggerid"]),
							"tr_events.php?triggerid=".$row2["triggerid"]));
					}
				}
			}

			if($row["showsla"]==1)
			{
				$sla = new CLink(new CImg("chart_sla.php?serviceid=".$row["serviceid"]),
						"report3.php?serviceid=".$row["serviceid"]."&year=".date("Y"));
				
				$now		= time(NULL);
				$period_start	= $now-7*24*3600;
				$period_end	= $now;
				
				$stat = calculate_service_availability($row["serviceid"],$period_start,$period_end);

				if($row["goodsla"] > $stat["ok"])
				{
					$color="AA0000";
				}
				else
				{
					$color="00AA00";
				}
				
				$sla2 = sprintf("<font color=\"00AA00\">%.2f%%</font><b>/</b><font color=\"%s\">%.2f%%</font>",
					$row["goodsla"], $color,$stat["ok"]);
			}
			else
			{
				$sla	= "-";
				$sla2	= "-";
			}

			$table->AddRow(array(
				$description,
				get_service_status_description($row["status"]),
				$reason,
				$sla,
				$sla2,
				new CLink(S_SHOW,"srv_status.php?serviceid=".$row["serviceid"]."&showgraph=1".url_param('path'),"action")
				));
		}
		$table->Show();
	}
?>
<?php

include_once "include/page_footer.php";

?>

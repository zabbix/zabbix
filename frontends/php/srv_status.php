<?php
/* 
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
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
		"showgraph"=>		array(T_ZBX_INT, O_OPT,	P_SYS,		IN("1")."isset({serviceid})",NULL)
	);

	check_fields($fields);
?>
<?php
	$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_MODE_LT);

	if(isset($_REQUEST["serviceid"]) && $_REQUEST["serviceid"] > 0){
		
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
	show_table_header(S_IT_SERVICES_BIG);

	if(isset($service)&&isset($_REQUEST["showgraph"])){
		$table  = new CTable(null,'chart');
		$table->AddRow(new CImg("chart5.php?serviceid=".$service["serviceid"].url_param('path')));
		$table->Show();
	} else {
	
		$query = 'SELECT DISTINCT s.serviceid, sl.servicedownid, sl_p.serviceupid as serviceupid, s.triggerid, '.
				' s.name as caption, s.algorithm, t.description, s.sortorder, sl.linkid, s.showsla, s.goodsla, s.status '.
			' FROM services s '.
				' LEFT JOIN triggers t ON s.triggerid = t.triggerid '.
				' LEFT JOIN services_links sl ON  s.serviceid = sl.serviceupid and NOT(sl.soft=0) '.
				' LEFT JOIN services_links sl_p ON  s.serviceid = sl_p.servicedownid and sl_p.soft=0 '.
				' LEFT JOIN functions f ON t.triggerid=f.triggerid '.
				' LEFT JOIN items i ON f.itemid=i.itemid '.
			' WHERE '.DBid2nodeid("s.serviceid").'='.$ZBX_CURNODEID.
			' AND (i.hostid is null or i.hostid not in ('.$denyed_hosts.')) '.
			' ORDER BY s.sortorder, sl.serviceupid, s.serviceid';
		
		$result=DBSelect($query);
		
		$services = array();
		$row = array(
						'0' => 0,'serviceid' => 0,
						'1' => 0,'serviceupid' => 0,
						'2' => '','caption' => 'root',
						'3' => '','status' => SPACE,
						'4' => '','reason' => SPACE,
						'5' => '','sla' => SPACE,
						'6' => '','sla2' => SPACE,
						'7' => '','graph' => SPACE,
						'7' => '','linkid'=>''
						);
		
		$services[0]=$row;
		$now=time();
		
		while($row = DBFetch($result)){
		
			(empty($row['serviceupid']))?($row['serviceupid']='0'):('');
			(empty($row['description']))?($row['description']='None'):('');
			$row['graph'] = new CLink(S_SHOW,"srv_status.php?serviceid=".$row["serviceid"]."&showgraph=1".url_param('path'),"action");
			
			if(isset($row["triggerid"]) && !empty($row["triggerid"])){

				$url = new CLink(expand_trigger_description($row['triggerid']),'tr_events.php?triggerid='.$row['triggerid']);
				$row['caption'] = $row['caption'].SPACE.'['.$url->ToString().']';

			}
			
			if($row["status"]==0 || (isset($service) && $service["serviceid"] == $row["serviceid"])){
				$row['reason']="-";
			} else {
				$row['reason'] = new CList(null,"itservices");
				$result2=DBselect("select s.triggerid,s.serviceid from services s, triggers t ".
					" where s.status>0 and s.triggerid is not NULL and t.triggerid=s.triggerid ".
					" and ".DBid2nodeid("s.serviceid")."=".$ZBX_CURNODEID.
					" order by s.status desc,t.description");
					
				while($row2=DBfetch($result2)){
					if(does_service_depend_on_the_service($row["serviceid"],$row2["serviceid"])){
						$row['reason']->AddItem(new CLink(
							expand_trigger_description($row2["triggerid"]),
							"tr_events.php?triggerid=".$row2["triggerid"]));
					}
				}
			}
			
			if($row["showsla"]==1){
				$row['sla'] = new CLink(new CImg("chart_sla.php?serviceid=".$row["serviceid"]),
						"report3.php?serviceid=".$row["serviceid"]."&year=".date("Y"));
				
				$now		= time(NULL);
				$period_start	= $now-7*24*3600;
				$period_end	= $now;
				
				$stat = calculate_service_availability($row["serviceid"],$period_start,$period_end);

				if($row["goodsla"] > $stat["ok"]){
					$color="AA0000";
				} else {
					$color="00AA00";
				}
				
				$row['sla2'] = sprintf("<font color=\"00AA00\">%.2f%%</font><b>/</b><font color=\"%s\">%.2f%%</font>",
					$row["goodsla"], $color,$stat["ok"]);
			} else {
				$row['sla']= "-";
				$row['sla2']= "-";
			}
			
			if(isset($services[$row['serviceid']])){
				$services[$row['serviceid']] = array_merge($services[$row['serviceid']],$row);
			} else {
				
				$services[$row['serviceid']] = $row;
			}
		
			if(isset($row['serviceupid']))
			$services[$row['serviceupid']]['childs'][] = array('id' => $row['serviceid'], 'soft' => 0, 'linkid' => 0);
	
			if(isset($row['servicedownid']))
			$services[$row['serviceid']]['childs'][] = array('id' => $row['servicedownid'], 'soft' => 1, 'linkid' => $row['linkid']);
		}
		
		$treeServ = array();
		createShowServiceTree($services,$treeServ);	//return into $treeServ parametr
		
		//permission issue
		$treeServ = del_empty_nodes($treeServ);
		
		echo '<script src="js/services.js" type="text/javascript"></script>';
		
		$tree = new CTree($treeServ,array('caption' => '<b>'.S_SERVICE.'</b>',
						'status' => '<b>'.S_STATUS.'</b>', 
						'reason' => '<b>'.S_REASON.'</b>',
						'sla' => '<b>'.S_SLA_LAST_7_DAYS.'</b>',
						'sla2' => '<b>'.nbsp(S_PLANNED_CURRENT_SLA).'</b>',
						'graph' => '<b>'.S_GRAPH.'</b>'));
		
		if($tree){
			echo $tree->CreateJS();
			echo $tree->SimpleHTML();
		} else {
			error('Can\'t format Tree. Check logick structure in service links');
		}
	}
?>
<?php

include_once "include/page_footer.php";

?>

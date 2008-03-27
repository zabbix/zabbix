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
	require_once("include/config.inc.php");
	require_once("include/services.inc.php");
	require_once('include/classes/ctree.inc.php');
		
	$page["title"] = "S_IT_SERVICES";
	$page["file"] = "srv_status.php";
	$page['scripts'] = array('services.js');
	$page['hist_arg'] = array();

	define('ZBX_PAGE_DO_REFRESH', 1);

include_once "include/page_header.php";

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"serviceid"=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,			NULL),
		"showgraph"=>		array(T_ZBX_INT, O_OPT,	P_SYS,		IN("1")."isset({serviceid})",NULL),
// ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	IN("'hat'"),		NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})'),
	);

	check_fields($fields);

/* AJAX */	
	if(isset($_REQUEST['favobj'])){
		if('hat' == $_REQUEST['favobj']){
			update_profile('web.srv_status.hats.'.$_REQUEST['favid'].'.state',$_REQUEST['state']);
		}
	}	

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}
//--------?>
<?php
        if( isset($_REQUEST["serviceid"]) && $_REQUEST["serviceid"] > 0 && ! (DBfetch(DBselect('select serviceid from services where serviceid='.$_REQUEST["serviceid"]))) )
        {
                unset($_REQUEST["serviceid"]);
        }

	$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_MODE_LT);

	if(isset($_REQUEST["serviceid"]) && $_REQUEST["serviceid"] > 0){
		
		if( !($service = DBfetch(DBselect('SELECT s.* '.
					' FROM services s '.
						' LEFT JOIN triggers t ON s.triggerid=t.triggerid '.
						' LEFT JOIN functions f ON t.triggerid=f.triggerid '.
						' LEFT JOIN items i on f.itemid=i.itemid '.
			' WHERE (i.hostid IS NULL OR i.hostid NOT IN ('.$denyed_hosts.')) '.
				' AND '.DBin_node('s.serviceid').
				' AND s.serviceid='.$_REQUEST['serviceid']
			))))
		{
			access_deny();
		}
	}
	unset($_REQUEST['serviceid']);
?>
<?php
//	show_table_header(S_IT_SERVICES_BIG);

	if(isset($service)&&isset($_REQUEST["showgraph"])){
		$table  = new CTable(null,'chart');
		$table->AddRow(new CImg("chart5.php?serviceid=".$service["serviceid"].url_param('path')));
		$table->Show();
	} else {
	
		$query = 'SELECT DISTINCT s.serviceid, sl.servicedownid, sl_p.serviceupid as serviceupid, s.triggerid, '.
				' s.name as caption, s.algorithm, t.description, t.expression, s.sortorder, sl.linkid, s.showsla, s.goodsla, s.status '.
			' FROM services s '.
				' LEFT JOIN triggers t ON s.triggerid = t.triggerid '.
				' LEFT JOIN services_links sl ON  s.serviceid = sl.serviceupid and NOT(sl.soft=0) '.
				' LEFT JOIN services_links sl_p ON  s.serviceid = sl_p.servicedownid and sl_p.soft=0 '.
				' LEFT JOIN functions f ON t.triggerid=f.triggerid '.
				' LEFT JOIN items i ON f.itemid=i.itemid '.
			' WHERE '.DBin_node('s.serviceid').
				' AND (i.hostid is null or i.hostid not in ('.$denyed_hosts.')) '.
			' ORDER BY s.sortorder, sl_p.serviceupid, s.serviceid';
		
		$result=DBSelect($query);
		
		$services = array();
		$row = array(
						'id' => 0,
						'serviceid' => 0,
						'serviceupid' => 0,
						'caption' => 'root',
						'status' => SPACE,
						'reason' => SPACE,
						'sla' => SPACE,
						'sla2' => SPACE,
						'graph' => SPACE,
						'linkid'=>''
						);
		
		$services[0]=$row;
		$now=time();
		
		while($row = DBFetch($result)){
			$row['id'] = $row['serviceid'];
		
			(empty($row['serviceupid']))?($row['serviceupid']='0'):('');
			(empty($row['description']))?($row['description']='None'):('');
			$row['graph'] = new CLink(S_SHOW,"srv_status.php?serviceid=".$row["serviceid"]."&showgraph=1".url_param('path'),"action");
			
			if(isset($row["triggerid"]) && !empty($row["triggerid"])){

				$url = new CLink(expand_trigger_description($row['triggerid']),'events.php?triggerid='.$row['triggerid']);
				$row['caption'] = array($row['caption'].' [',$url,']');

			}
			
			if($row["status"]==0 || (isset($service) && (bccomp($service["serviceid"] , $row["serviceid"]) == 0))){
				$row['reason']="-";
			} else {
				$row['reason'] = new CList(null,"itservices");
				$result2=DBselect("select s.triggerid,s.serviceid from services s, triggers t ".
					" where s.status>0 and s.triggerid is not NULL and t.triggerid=s.triggerid ".
						' and '.DBin_node('s.serviceid').
					" order by s.status desc,t.description");
					
				while($row2=DBfetch($result2)){
					if(does_service_depend_on_the_service($row["serviceid"],$row2["serviceid"])){
						$row['reason']->AddItem(new CLink(
							expand_trigger_description($row2["triggerid"]),
							"events.php?triggerid=".$row2["triggerid"]));
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
					$sla_style='red';
				} else {
					$sla_style='green';
				}
				
				$row['sla2'] = array(new CSpan(round($row["goodsla"],3),'green'),'/', new CSpan(round($stat["ok"],3),$sla_style));
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
		
		$tree = new CTree($treeServ,array('caption' => bold(S_SERVICE),
						'status' => bold(S_STATUS), 
						'reason' => bold(S_REASON),
						'sla' => bold(S_SLA_LAST_7_DAYS),
						'sla2' => bold(nbsp(S_PLANNED_CURRENT_SLA)),
						'graph' => bold(S_GRAPH)));
		
		if($tree){
			$tab = create_hat(
					S_IT_SERVICES_BIG,
					$tree->getHTML(),
					null,
					'hat_services',
					get_profile('web.srv_status.hats.hat_services.state',1)
				);
				
			$tab->Show();
			unset($tab);
		} 
		else {
			error('Can\'t format Tree. Check logick structure in service links');
		}
	}
?>
<?php

include_once "include/page_footer.php";

?>

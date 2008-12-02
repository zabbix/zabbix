<?php
/*
** ZABBIX
** Copyright (C) 2000-2008 SIA Zabbix
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
require_once('include/graphs.inc.php');
require_once('include/screens.inc.php');
require_once('include/maps.inc.php');
require_once('include/users.inc.php');


// Author: Aly
function make_favorite_graphs(){
	$table = new CTableInfo();
	
	$fav_graphs = get_favorites('web.favorite.graphids');
	foreach($fav_graphs as $key => $favorite){
		
		$source = $favorite['source'];
		$sourceid = $favorite['value'];

		if('itemid' == $source){
			if(!$item = get_item_by_itemid($sourceid)) continue;
	
			$host = get_host_by_itemid($sourceid);
			$item["description"] = item_description($item);
			
			$link = new CLink(get_node_name_by_elid($sourceid).$host['host'].':'.$item['description'],'history.php?action=showgraph&itemid='.$sourceid);
			$link->SetTarget('blank');
			
			$capt = new CSpan($link);
			$capt->AddOption('style','line-height: 14px; vertical-align: middle;');
			
			$icon = new CLink(new CImg('images/general/chart.png','chart',18,18,'borderless'),'history.php?action=showgraph&itemid='.$sourceid.'&fullscreen=1');
			$icon->SetTarget('blank');
		}
		else{
			if(!$graph = get_graph_by_graphid($sourceid)) continue;
			if(!graph_accessible($sourceid)) continue;
			
			$result = get_hosts_by_graphid($sourceid);
			$ghost = DBFetch($result);
	
			$link = new CLink(get_node_name_by_elid($sourceid).$ghost['host'].':'.$graph['name'],'charts.php?graphid='.$sourceid);
			$link->SetTarget('blank');
	
			$capt = new CSpan($link);
			$capt->AddOption('style','line-height: 14px; vertical-align: middle;');
			
			$icon = new CLink(new CImg('images/general/chart.png','chart',18,18,'borderless'),'charts.php?graphid='.$sourceid.'&fullscreen=1');
			$icon->SetTarget('blank');
		}
		
		$table->AddRow(new CCol(array(
			$icon,
			SPACE,
			$capt)
		));
	}
	$td = new CCol(array(new CLink(S_GRAPHS.' &raquo;','charts.php','highlight')));
	$td->AddOption('style','text-align: right;');

	$table->SetFooter($td);
	
return $table;
}

// Author: Aly
function make_favorite_screens(){
	$table = new CTableInfo();
	
	$fav_screens = get_favorites('web.favorite.screenids');

	foreach($fav_screens as $key => $favorite){
		$source = $favorite['source'];
		$sourceid = $favorite['value'];

		if('slideshowid' == $source){
			if(!$slide = get_slideshow_by_slideshowid($sourceid)) continue;
			if(!slideshow_accessible($sourceid, PERM_READ_ONLY)) continue;

			$link = new CLink(get_node_name_by_elid($sourceid).$slide['name'],'screens.php?config=1&elementid='.$sourceid);
			$link->SetTarget('blank');
		
			$capt = new CSpan($link);
			$capt->AddOption('style','line-height: 14px; vertical-align: middle;');
			
			$icon = new CLink(new CImg('images/general/chart.png','screen',18,18,'borderless'),'screens.php?config=1&elementid='.$sourceid.'&fullscreen=1');
			$icon->SetTarget('blank');
		}
		else{
			if(!$screen = get_screen_by_screenid($sourceid)) continue;
			if(!screen_accessible($sourceid, PERM_READ_ONLY)) continue;
		
			$link = new CLink(get_node_name_by_elid($sourceid).$screen['name'],'screens.php?config=0&elementid='.$sourceid);
			$link->SetTarget('blank');
			
			$capt = new CSpan($link);
			$capt->AddOption('style','line-height: 14px; vertical-align: middle;');
			
			$icon = new CLink(new CImg('images/general/chart.png','screen',18,18,'borderless'),'screens.php?config=0&elementid='.$sourceid.'&fullscreen=1');
			$icon->SetTarget('blank');
		}
		
		$table->AddRow(new CCol(array(
			$icon,
			SPACE,
			$capt)
		));
	}
	
	$td = new CCol(array(new CLink(S_SCREENS.' &raquo;','screens.php','highlight')));
	$td->AddOption('style','text-align: right;');

	$table->SetFooter($td);

return $table;
}

// Author: Aly
function make_favorite_maps(){
	$table = new CTableInfo();
	
	$fav_sysmaps = get_favorites('web.favorite.sysmapids');
	
	foreach($fav_sysmaps as $key => $favorite){
	
		$source = $favorite['source'];
		$sourceid = $favorite['value'];
		
		if(!$sysmap = get_sysmap_by_sysmapid($sourceid)) continue;
		if(!sysmap_accessible($sourceid,PERM_READ_ONLY)) continue;
		
		$link = new CLink(get_node_name_by_elid($sourceid).$sysmap['name'],'maps.php?sysmapid='.$sourceid);
		$link->SetTarget('blank');

		$capt = new CSpan($link);
		$capt->AddOption('style','line-height: 14px; vertical-align: middle;');
		
		$icon = new CLink(new CImg('images/general/chart.png','map',18,18,'borderless'),'maps.php?sysmapid='.$sourceid.'&fullscreen=1');
		$icon->SetTarget('blank');

		$table->AddRow(new CCol(array(
			$icon,
			SPACE,
			$capt)
		));
	}
	
	$td = new CCol(array(new CLink(S_MAPS.' &raquo;','maps.php','highlight')));
	$td->AddOption('style','text-align: right;');

	$table->SetFooter($td);

return $table;
}

// Author: Aly
function make_system_summary(){
	global $USER_DETAILS;
	$config = select_config();
	
	$available_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_RES_IDS_ARRAY);
	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_RES_IDS_ARRAY);
	$available_triggers = get_accessible_triggers(PERM_READ_ONLY,PERM_RES_IDS_ARRAY);
		
	$table = new CTableInfo();
	$table->SetHeader(array(
		is_show_subnodes() ? S_NODE : null,
		S_HOST_GROUP,
		S_DISASTER,
		S_HIGH,
		S_AVERAGE,
		S_WARNING,
		S_INFORMATION,
		S_NOT_CLASSIFIED
	));

	$sql = 'SELECT DISTINCT g.groupid,g.name '.
			' FROM groups g, hosts_groups hg, hosts h, items i, functions f, triggers t '.
			' WHERE '.DBcondition('h.hostid',$available_hosts).
				' AND '.DBcondition('g.groupid',$available_groups).
				' AND hg.groupid=g.groupid '.
				' AND h.status='.HOST_STATUS_MONITORED.
				' AND h.hostid=i.hostid '.
				' AND hg.hostid=h.hostid '.
				' AND i.status='.ITEM_STATUS_ACTIVE.
				' AND i.itemid=f.itemid '.
				' AND t.triggerid=f.triggerid '.
				' AND t.status='.TRIGGER_STATUS_ENABLED.
			' ORDER BY g.name';
	$gr_result = DBselect($sql);
					
	while($group = DBFetch($gr_result)){
		$group_row = new CRow();
		if(is_show_subnodes())
			$group_row->AddItem(get_node_name_by_elid($group['groupid']));
		
		$name = new CLink($group['name'],'tr_status.php?groupid='.$group['groupid'].'&show_triggers='.TRIGGERS_OPTION_ONLYTRUE);
		$name->SetTarget('blank');
		$group_row->AddItem($name);
		
		$tab_priority[TRIGGER_SEVERITY_DISASTER] = 0;
		$tab_priority[TRIGGER_SEVERITY_HIGH] = 0;
		$tab_priority[TRIGGER_SEVERITY_AVERAGE] = 0;
		$tab_priority[TRIGGER_SEVERITY_WARNING] = 0;
		$tab_priority[TRIGGER_SEVERITY_INFORMATION] = 0;
		$tab_priority[TRIGGER_SEVERITY_NOT_CLASSIFIED] = 0;

		$sql='SELECT count(DISTINCT t.triggerid) as tr_cnt,t.priority '.
			' FROM hosts h,items i,hosts_groups hg, functions f, triggers t '.
			' WHERE h.status='.HOST_STATUS_MONITORED.
				' AND h.hostid=i.hostid '.
				' AND hg.groupid='.$group['groupid'].
				' AND hg.hostid=h.hostid'.
				' AND i.status='.ITEM_STATUS_ACTIVE.
				' AND i.itemid=f.itemid '.
				' AND t.triggerid=f.triggerid '.
				' AND t.value='.TRIGGER_VALUE_TRUE.
				' AND t.status='.TRIGGER_STATUS_ENABLED.
				' AND '.DBcondition('h.hostid',$available_hosts).
			' GROUP BY t.priority';
//SDI($sql);
		$tr_result = DBSelect($sql);
		while($group_stat = DBFetch($tr_result)){
			$tab_priority[$group_stat['priority']] = $group_stat['tr_cnt'];
		}

		foreach($tab_priority as $key => $value){
			$normal = $value;
			if($value){
				$tr_count = 0;
//* trigger list
				$table_inf  = new CTableInfo();
				$table_inf->AddOption('style', 'width: 400px;');
				$table_inf->SetHeader(array(
					is_show_subnodes() ? S_NODE : null,
					S_HOST,
					S_ISSUE,
					S_AGE,
					($config['event_ack_enable'])? S_ACK : NULL,
					S_ACTIONS
					));
				
				$sql = 'SELECT DISTINCT t.triggerid,t.status,t.description, t.priority, t.lastchange,t.value,h.host,h.hostid '.
							' FROM triggers t,hosts h,items i,functions f, hosts_groups hg '.
							' WHERE f.itemid=i.itemid '.
								' AND hg.groupid='.$group['groupid'].
								' AND h.hostid=i.hostid '.
								' AND hg.hostid=h.hostid '.
								' AND t.triggerid=f.triggerid '.
								' AND t.status='.TRIGGER_STATUS_ENABLED.
								' AND i.status='.ITEM_STATUS_ACTIVE.
								' AND '.DBcondition('t.triggerid', $available_triggers).
								' AND h.status='.HOST_STATUS_MONITORED.
								' AND t.value='.TRIGGER_VALUE_TRUE.
								' AND t.priority='.$key.
							' ORDER BY t.lastchange DESC';
				$result = DBselect($sql,30);
				while($row_inf=DBfetch($result)){	
// Check for dependencies
					if(trigger_dependent($row_inf["triggerid"]))	continue;
					
					$tr_count++;
					$host = new CSpan($row_inf['host']);
								
					$event_sql = 'SELECT e.eventid, e.value, e.clock, e.objectid as triggerid, e.acknowledged, t.type '.
								' FROM events e, triggers t '.
								' WHERE e.object='.EVENT_SOURCE_TRIGGERS.
									' AND e.objectid='.$row_inf['triggerid'].
									' AND t.triggerid=e.objectid '.
									' AND e.value='.TRIGGER_VALUE_TRUE.
								' ORDER by e.object DESC, e.objectid DESC, e.eventid DESC';
					if($row_inf_event=DBfetch(DBselect($event_sql,1))){
						
						if($config['event_ack_enable']){
							if($row_inf_event['acknowledged'] == 1){
								$ack=new CLink(S_YES,'acknow.php?eventid='.$row_inf_event['eventid'],'action');
							}
							else{
								$ack= new CLink(S_NO,'acknow.php?eventid='.$row_inf_event['eventid'],'on');
							}
						}
			
						$description = expand_trigger_description_by_data(
								array_merge($row_inf, array('clock'=>$row_inf_event['clock'])),
								ZBX_FLAG_EVENT);
						
//actions								
						$actions= get_event_actions_status($row_inf_event['eventid']);
//--------				
					}
					else{
						$description = expand_trigger_description_by_data($row_inf, ZBX_FLAG_EVENT);
						$ack = '-';
						$actions = S_NO_DATA;
						$row_inf_event['clock'] = $row_inf['clock'];
					}
					
					$table_inf->addRow(array(
						get_node_name_by_elid($row_inf['triggerid']),
						$host,
						new CCol($description,get_severity_style($row_inf['priority'])),
						zbx_date2age($row_inf_event['clock']),
						($config['event_ack_enable'])?(new CCol($ack,'center')):NULL,
						$actions
					));
					
					unset($row_inf,$description,$actions);
				}
				
				$value = new CSpan($tr_count);
				$value->SetHint($table_inf);
//-------------*/
			}
			$group_row->AddItem(new CCol($value,get_severity_style($key,$normal)));
			unset($table_inf);
		}		
		$table->AddRow($group_row);
	}
	$table->SetFooter(new CCol(S_UPDATED.': '.date("H:i:s",time())));
return $table;
}

// Author: Aly
function make_status_of_zbx(){
	$table = new CTableInfo();

	$table->SetHeader(array(
		S_PARAMETER,
		S_VALUE,
		S_DETAILS
	));

	$status=get_status();

	$table->AddRow(array(S_ZABBIX_SERVER_IS_RUNNING,new CSpan($status['zabbix_server'], ($status['zabbix_server'] == S_YES ? 'off' : 'on')),' - '));
//	$table->AddRow(array(S_VALUES_STORED,$status['history_count']));$table->AddRow(array(S_TRENDS_STORED,$status['trends_count']));
	$table->AddRow(array(S_NUMBER_OF_HOSTS,$status['hosts_count'],
		array(
			new CSpan($status['hosts_count_monitored'],'off'),' / ',
			new CSpan($status['hosts_count_not_monitored'],'on'),' / ',
			new CSpan($status['hosts_count_template'],'unknown')
		)
	));
	$table->AddRow(array(S_NUMBER_OF_ITEMS,$status['items_count'],
		array(
			new CSpan($status['items_count_monitored'],'off'),' / ',
			new CSpan($status['items_count_disabled'],'on'),' / ',
			new CSpan($status['items_count_not_supported'],'unknown')
		)
	));
	$table->AddRow(array(S_NUMBER_OF_TRIGGERS,$status['triggers_count'],
		array(
			$status['triggers_count_enabled'],' / ',
			$status['triggers_count_disabled'].SPACE.SPACE.'[',
			new CSpan($status['triggers_count_on'],'on'),' / ',
			new CSpan($status['triggers_count_unknown'],'unknown'),' / ',
			new CSpan($status['triggers_count_off'],'off'),']'
		)
	));
/*	$table->AddRow(array(S_NUMBER_OF_EVENTS,$status['events_count'],' - '));
	$table->AddRow(array(S_NUMBER_OF_ALERTS,$status['alerts_count'],' - '));*/

//Log Out 10min	
	$sql = 'SELECT DISTINCT u.userid, MAX(s.lastaccess) as lastaccess, MAX(u.autologout) as autologout, s.status '.
			' FROM users u '.
				' LEFT JOIN sessions s ON s.userid=u.userid AND s.status='.ZBX_SESSION_ACTIVE.
			' WHERE '.DBin_node('u.userid').
			' GROUP BY u.userid,s.status';
	$db_users = DBSelect($sql);
	
	$usr_cnt = 0;
	$online_cnt = 0;
	while($user=DBFetch($db_users)){
		$online_time = (($user['autologout'] == 0) || (ZBX_USER_ONLINE_TIME<$user['autologout']))?ZBX_USER_ONLINE_TIME:$user['autologout'];
		if(!is_null($user['lastaccess']) && (($user['lastaccess']+$online_time)>=time()) && (ZBX_SESSION_ACTIVE == $user['status'])) $online_cnt++;
		$usr_cnt++;
	}

	$table->AddRow(array(S_NUMBER_OF_USERS,$usr_cnt,new CSpan($online_cnt,'green')));
	$table->AddRow(array(S_REQUIRED_SERVER_PERFORMANCE_NVPS,$status['qps_total'],' - '));
	$table->SetFooter(new CCol(S_UPDATED.': '.date("H:i:s",time())));
return $table;
}

function make_discovery_status(){
	$drules = array();
	
	$db_drules = DBselect('select distinct * from drules where '.DBin_node('druleid').' order by name');
	while($drule_data = DBfetch($db_drules)){
		$drules[$drule_data['druleid']] = $drule_data;
		$drules[$drule_data['druleid']]['up'] = 0;
		$drules[$drule_data['druleid']]['down'] = 0;
	}

	$db_dhosts = DBselect('SELECT d.* '.
					' FROM dhosts d '.
					' ORDER BY d.dhostid,d.status,d.ip');

	$services = array();
	$discovery_info = array();

	while($drule_data = DBfetch($db_dhosts)){
		if(DHOST_STATUS_DISABLED == $drule_data['status']){
			$drules[$drule_data['druleid']]['down']++;		}
		else{
			$drules[$drule_data['druleid']]['up']++;
		}
	}

	$header = array(
		is_show_subnodes() ? new CCol(S_NODE, 'center') : null,
		new CCol(S_DISCOVERY_RULE, 'center'),
		new CCol(S_UP),
		new CCol(S_DOWN)
		);

	$table  = new CTableInfo();
	$table->SetHeader($header,'vertical_header');

	foreach($drules as $druleid => $drule){
		$table->AddRow(array(
			get_node_name_by_elid($druleid),
			new CLink(get_node_name_by_elid($drule['druleid']).$drule['name'],'discovery.php?druleid='.$druleid),
			new CSpan($drule['up'],'green'),
			new CSpan($drule['down'],($drule['down'] > 0)?'red':'green')
		));
	}
	$table->SetFooter(new CCol(S_UPDATED.': '.date("H:i:s",time())));

return 	$table;
}

// author Aly
function make_latest_issues(){
	global $USER_DETAILS;
	
	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY);
	$available_triggers = get_accessible_triggers(PERM_READ_ONLY);

	$scripts_by_hosts = get_accessible_scripts_by_hosts($available_hosts);
	$config=select_config();
	
	$table  = new CTableInfo();
	$table->SetHeader(array(
		is_show_subnodes() ? S_NODE : null,
		S_HOST,
		S_ISSUE,
		S_LAST_CHANGE,
		S_AGE,
		($config['event_ack_enable'])? S_ACK : NULL,
		S_ACTIONS
		));
	
	$sql = 'SELECT DISTINCT t.triggerid,t.status,t.description, t.priority, t.lastchange,t.value,h.host,h.hostid '.
				' FROM triggers t,hosts h,items i,functions f, hosts_groups hg '.
				' WHERE f.itemid=i.itemid '.
					' AND h.hostid=i.hostid '.
					' AND hg.hostid=h.hostid '.
					' AND t.triggerid=f.triggerid '.
					' AND t.status='.TRIGGER_STATUS_ENABLED.
					' AND i.status='.ITEM_STATUS_ACTIVE.
//					' AND '.DBin_node('t.triggerid').
					' AND '.DBcondition('t.triggerid',$available_triggers).
					' AND h.status='.HOST_STATUS_MONITORED.
					' AND t.value='.TRIGGER_VALUE_TRUE.
				' ORDER BY t.lastchange DESC';

	$result = DBselect($sql,20);
	while($row=DBfetch($result)){
// Check for dependencies
		if(trigger_dependent($row["triggerid"]))	continue;

		$host = null;
		$menus = '';

		$host_nodeid = id2nodeid($row['hostid']);
		foreach($scripts_by_hosts[$row['hostid']] as $id => $script){
			$script_nodeid = id2nodeid($script['scriptid']);
			if( (bccomp($host_nodeid ,$script_nodeid ) == 0))
				$menus.= "['".$script['name']."',\"javascript: openWinCentered('scripts_exec.php?execute=1&hostid=".$row['hostid']."&scriptid=".$script['scriptid']."','".S_TOOLS."',760,540,'titlebar=no, resizable=yes, scrollbars=yes, dialog=no');\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";
		}

		$menus.= "[".zbx_jsvalue(S_LINKS).",null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],";
		$menus.= "['".S_LATEST_DATA."',\"javascript: redirect('latest.php?hostid=".$row['hostid']."')\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";

		$menus = rtrim($menus,',');
		$menus="show_popup_menu(event,[[".zbx_jsvalue(S_TOOLS).",null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],".$menus."],180);";
		
		$host = new CSpan($row['host']);
		$host->AddOption('onclick','javascript: '.$menus);
		$host->AddOption('onmouseover',"javascript: this.style.cursor = 'pointer';");

		$event_sql = 'SELECT e.eventid, e.value, e.clock, e.objectid as triggerid, e.acknowledged, t.type '.
					' FROM events e, triggers t '.
					' WHERE e.object='.EVENT_SOURCE_TRIGGERS.
						' AND e.objectid='.$row['triggerid'].
						' AND t.triggerid=e.objectid '.
						' AND e.value='.TRIGGER_VALUE_TRUE.
					' ORDER by e.object DESC, e.objectid DESC, e.eventid DESC';
		$res_events = DBSelect($event_sql,1);

		while($row_event=DBfetch($res_events)){
			$ack = NULL;
			if($config['event_ack_enable']){
				if($row_event['acknowledged'] == 1){
					$ack_info = make_acktab_by_eventid($row_event['eventid']);
					$ack_info->AddOption('style','width: auto;');
					
					$ack=new CLink(S_YES,'acknow.php?eventid='.$row_event['eventid'],'action');
					$ack->SetHint($ack_info);
				}
				else{
					$ack= new CLink(S_NO,'acknow.php?eventid='.$row_event['eventid'],'on');
				}
			}

			$description = expand_trigger_description_by_data(
					array_merge($row, array("clock"=>$row_event["clock"])),
					ZBX_FLAG_EVENT);
					
//actions								
			$actions = get_event_actions_stat_hints($row_event['eventid']);
//--------			
			$clock = new CLink(zbx_date2str(S_DATE_FORMAT_YMDHMS,$row_event['clock']),'events.php?triggerid='.$row['triggerid'].'&source=0&nav_time='.$row['lastchange'],'action');
			
			$table->AddRow(array(
				get_node_name_by_elid($row['triggerid']),
				$host,
				new CCol($description,get_severity_style($row["priority"])),
				$clock,
				zbx_date2age($row_event['clock']),
				$ack,
				$actions
			));			
		}
		unset($row,$description,$actions,$alerts,$hint);
	}
	$table->SetFooter(new CCol(S_UPDATED.': '.date("H:i:s",time())));
return $table;
}

// author Aly
function make_webmon_overview(){
	global $USER_DETAILS;
	
	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_RES_IDS_ARRAY);

	$table  = new CTableInfo();
	$table->SetHeader(array(
		is_show_subnodes() ? S_NODE : null,
		S_HOST_GROUP,
		S_OK,
		S_FAILED,
		S_IN_PROGRESS,
		S_UNKNOWN
		));

	$sql = 'SELECT DISTINCT g.groupid, g.name '.
			' FROM httptest ht, applications a, groups g, hosts_groups hg '.
			' WHERE '.DBcondition('hg.hostid',$available_hosts).
				' AND hg.hostid=a.hostid '.
				' AND g.groupid=hg.groupid '.
				' AND a.applicationid=ht.applicationid '.
				' AND ht.status='.HTTPTEST_STATUS_ACTIVE.
			' ORDER BY g.name';
	$host_groups = DBSelect($sql);
	
	while($group = DBFetch($host_groups)){

		$apps['ok'] = 0;
		$apps['failed'] = 0;
		$apps[HTTPTEST_STATE_BUSY] = 0;
		$apps[HTTPTEST_STATE_UNKNOWN] = 0;
		
		$sql = 'SELECT DISTINCT ht.httptestid, ht.curstate, ht.lastfailedstep '.
				' FROM httptest ht, applications a, hosts_groups hg, groups g '.
				' WHERE g.groupid='.$group['groupid'].
					' AND hg.groupid=g.groupid '.
					' AND a.hostid=hg.hostid '.
					' AND ht.applicationid=a.applicationid '.
					' AND ht.status='.HTTPTEST_STATUS_ACTIVE;

		$db_httptests = DBselect($sql);
	
		while($httptest_data = DBfetch($db_httptests)){

			if( HTTPTEST_STATE_BUSY == $httptest_data['curstate'] ){
				$apps[HTTPTEST_STATE_BUSY]++;
			}
			else if( HTTPTEST_STATE_IDLE == $httptest_data['curstate'] ){
				if($httptest_data['lastfailedstep'] > 0){
					$apps['failed']++;
				}
				else{
					$apps['ok']++;
				}
			}
			else{
				$apps[HTTPTEST_STATE_UNKNOWN]++;
			}
		}
		
		$table->AddRow(array(
			is_show_subnodes() ? get_node_name_by_elid($group['groupid']) : null,
			$group['name'],
			new CSpan($apps['ok'],'off'),
			new CSpan($apps['failed'],$apps['failed']?'on':'off'),
			new CSpan($apps[HTTPTEST_STATE_BUSY],$apps[HTTPTEST_STATE_BUSY]?'orange':'off'),
			new CSpan($apps[HTTPTEST_STATE_UNKNOWN],'unknown')
		));
	}
	$table->SetFooter(new CCol(S_UPDATED.': '.date("H:i:s",time())));
return $table;	
}

function make_latest_data(){
	global $USER_DETAILS;
	
	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_RES_IDS_ARRAY);
	
	while($db_app = DBfetch($db_applications)){
		$db_items = DBselect('SELECT DISTINCT i.* '.
					' FROM items i,items_applications ia'.
					' WHERE ia.applicationid='.$db_app['applicationid'].
						' AND i.itemid=ia.itemid'.
						' AND i.status='.ITEM_STATUS_ACTIVE.
					order_by('i.description,i.itemid,i.lastclock'));

		$app_rows = array();
		$item_cnt = 0;
		while($db_item = DBfetch($db_items)){
			$description = item_description($db_item);

			if( '' != $_REQUEST["select"] && !zbx_stristr($description, $_REQUEST["select"]) ) continue;

			++$item_cnt;
			if(!uint_in_array($db_app["applicationid"],$_REQUEST["applications"]) && !isset($show_all_apps)) continue;

			if(isset($db_item["lastclock"]))
				$lastclock=date(S_DATE_FORMAT_YMDHMS,$db_item["lastclock"]);
			else
				$lastclock = new CCol('-', 'center');

			$lastvalue=format_lastvalue($db_item);

			if( isset($db_item["lastvalue"]) && isset($db_item["prevvalue"]) &&
				($db_item["value_type"] == 0) && ($db_item["lastvalue"]-$db_item["prevvalue"] != 0) )
			{
				if($db_item["lastvalue"]-$db_item["prevvalue"]<0){
					$change=convert_units($db_item["lastvalue"]-$db_item["prevvalue"],$db_item["units"]);
				}
				else{
					$change="+".convert_units($db_item["lastvalue"]-$db_item["prevvalue"],$db_item["units"]);
				}
				$change=nbsp($change);
			}
			else{
				$change=new CCol("-","center");
			}
			if(($db_item["value_type"]==ITEM_VALUE_TYPE_FLOAT) ||($db_item["value_type"]==ITEM_VALUE_TYPE_UINT64)){
				$actions=new CLink(S_GRAPH,"history.php?action=showgraph&itemid=".$db_item["itemid"],"action");
			}
			else{
				$actions=new CLink(S_HISTORY,"history.php?action=showvalues&period=3600&itemid=".$db_item["itemid"],"action");
			}
			array_push($app_rows, new CRow(array(
				is_show_subnodes() ? SPACE : null,
				$_REQUEST["hostid"] > 0 ? NULL : SPACE,
				str_repeat(SPACE,6).$description,
				$lastclock,
				new CCol($lastvalue, $lastvalue=='-' ? 'center' : null),
				$change,
				$actions
				)));
		}
		
		if($item_cnt > 0){
			if(uint_in_array($db_app["applicationid"],$_REQUEST["applications"]) || isset($show_all_apps)){
				$link = new CLink(new CImg("images/general/opened.gif"),
					"?close=1&applicationid=".$db_app["applicationid"].
					url_param("groupid").url_param("hostid").url_param("applications").
					url_param("select"));
			}
			else{
				$link = new CLink(new CImg("images/general/closed.gif"),
					"?open=1&applicationid=".$db_app["applicationid"].
					url_param("groupid").url_param("hostid").url_param("applications").
					url_param("select"));
			}

			$col = new CCol(array($link,SPACE,bold($db_app["name"]),
				SPACE."(".$item_cnt.SPACE.S_ITEMS.")"));
			$col->SetColSpan(5);

			$table->ShowRow(array(
					get_node_name_by_elid($db_app['hostid']),
					$_REQUEST["hostid"] > 0 ? NULL : $db_app["host"],
					$col
					));

			$any_app_exist = true;
		
			foreach($app_rows as $row)	$table->ShowRow($row);
		}
	}
}

function make_graph_menu(&$menu,&$submenu){

	$menu['menu_graphs'][] = array(
				S_FAVORITE.SPACE.S_GRAPHS, 
				null,
				null, 
				array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader'))
		);
	$menu['menu_graphs'][] = array(
				S_ADD.SPACE.S_GRAPH, 
				'javascript: '.
				"PopUp('popup.php?srctbl=graphs&".
					'reference=dashboard&'.
					'dstfrm=fav_form&'.
					'dstfld1=favobj&'.
					'dstfld2=favid&'.
					'srcfld1=name&'.
					"srcfld2=graphid',800,450);".
				"void(0);",
				null, 
				array('outer' => 'pum_o_submenu', 'inner'=>array('pum_i_submenu'))
		);
	$menu['menu_graphs'][] = array(
				S_ADD.SPACE.S_SIMPLE_GRAPH, 
				'javascript: '.
				"PopUp('popup.php?srctbl=simple_graph&".
					'reference=dashboard&'.
					'dstfrm=fav_form&'.
					'dstfld1=favobj&'.
					'dstfld2=favid&'.
					'srcfld1=description&'.
					"srcfld2=itemid',800,450);".
				"void(0);",				
				null, 
				array('outer' => 'pum_o_submenu', 'inner'=>array('pum_i_submenu'))
		);
	$menu['menu_graphs'][] = array(
				S_REMOVE, 
				null, 
				null, 
				array('outer' => 'pum_o_submenu', 'inner'=>array('pum_i_submenu'))
		);
	$submenu['menu_graphs'] = make_graph_submenu();
}

function make_graph_submenu(){
	$graphids = array();
	
	$fav_graphs = get_favorites('web.favorite.graphids');
	
	foreach($fav_graphs as $key => $favorite){
		
		$source = $favorite['source'];
		$sourceid = $favorite['value'];

		if('itemid' == $source){
			if(!$item = get_item_by_itemid($sourceid)) continue;
			
			$item_added = true;
	
			$host = get_host_by_itemid($sourceid);
			$item["description"] = item_description($item);
			
			$graphids[] = array( 
							'name'	=>	$host['host'].':'.$item['description'],
							'favobj'=>	'itemid',
							'favid'	=>	$sourceid,
							'action'=>	'remove'
						);
		}
		else{
			if(!$graph = get_graph_by_graphid($sourceid)) continue;
			
			$graph_added = true;
	
			$result = get_hosts_by_graphid($sourceid);
			$ghost = DBFetch($result);
			
			$graphids[] = array( 
							'name'	=>	$ghost['host'].':'.$graph['name'],
							'favobj'=>	'graphid',
							'favid'	=>	$sourceid,
							'action'=>	'remove'
						);
		}
	}

	if(isset($graph_added)){
			$graphids[] = array( 
			'name'	=>	S_REMOVE.SPACE.S_ALL_S.SPACE.S_GRAPHS,
			'favobj'=>	'graphid',
			'favid'	=>	0,
			'action'=>	'remove'
		);
	}
	
	if(isset($item_added)){
		$graphids[] = array( 
			'name'	=>	S_REMOVE.SPACE.S_ALL_S.SPACE.S_SIMPLE_GRAPHS,
			'favobj'=>	'itemid',
			'favid'	=>	0,
			'action'=>	'remove'
		);
	}
	
return $graphids;
}

function make_sysmap_menu(&$menu,&$submenu){

	$menu['menu_sysmaps'][] = array(S_FAVORITE.SPACE.S_MAPS, null, null, array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader')));
	$menu['menu_sysmaps'][] = array(
				S_ADD.SPACE.S_MAP, 
				'javascript: '.
				"PopUp('popup.php?srctbl=sysmaps&".
					'reference=dashboard&'.
					'dstfrm=fav_form&'.
					'dstfld1=favobj&'.
					'dstfld2=favid&'.
					'srcfld1=name&'.
					"srcfld2=sysmapid',800,450);".
				"void(0);",	 
				null, 
				array('outer' => 'pum_o_submenu', 'inner'=>array('pum_i_submenu')
		));
	$menu['menu_sysmaps'][] = array(S_REMOVE, null, null, array('outer' => 'pum_o_submenu', 'inner'=>array('pum_i_submenu')));
	$submenu['menu_sysmaps'] = make_sysmap_submenu();
}

function make_sysmap_submenu(){
	$sysmapids = array();
	$fav_sysmaps = get_favorites('web.favorite.sysmapids');
	
	foreach($fav_sysmaps as $key => $favorite){
	
		$source = $favorite['source'];
		$sourceid = $favorite['value'];
		
		if(!$sysmap = get_sysmap_by_sysmapid($sourceid)) continue;

		$sysmapids[] = array( 
							'name'	=>	$sysmap['name'],
							'favobj'=>	'sysmapid',
							'favid'	=>	$sourceid,
							'action'=>	'remove'
						);
	}
	
	if(!empty($sysmapids)){
		$sysmapids[] = array( 
							'name'	=>	S_REMOVE.SPACE.S_ALL_S.SPACE.S_MAPS,
							'favobj'=>	'sysmapid',
							'favid'	=>	0,
							'action'=>	'remove'
						);
	}
	
return $sysmapids;
}

function make_screen_menu(&$menu,&$submenu){

	$menu['menu_screens'][] = array(S_FAVORITE.SPACE.S_SCREENS, null, null, array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader')));
	$menu['menu_screens'][] = array(
				S_ADD.SPACE.S_SCREEN, 
				'javascript: '.
				"PopUp('popup.php?srctbl=screens&".
					'reference=dashboard&'.
					'dstfrm=fav_form&'.
					'dstfld1=favobj&'.
					'dstfld2=favid&'.
					'srcfld1=name&'.
					"srcfld2=screenid',800,450);".
				"void(0);",	
				null, 
				array('outer' => 'pum_o_submenu', 'inner'=>array('pum_i_submenu')
		));
	$menu['menu_screens'][] = array(
				S_ADD.SPACE.S_SLIDESHOW, 
				'javascript: '.
				"PopUp('popup.php?srctbl=slides&".
					'reference=dashboard&'.
					'dstfrm=fav_form&'.
					'dstfld1=favobj&'.
					'dstfld2=favid&'.
					'srcfld1=name&'.
					"srcfld2=slideshowid',800,450);".
				"void(0);",	
				null, 
				array('outer' => 'pum_o_submenu', 'inner'=>array('pum_i_submenu')
		));
	$menu['menu_screens'][] = array(S_REMOVE, null, null, array('outer' => 'pum_o_submenu', 'inner'=>array('pum_i_submenu')));
	$submenu['menu_screens'] = make_screen_submenu();
}

function make_screen_submenu(){
	$screenids = array();
	
	$fav_screens = get_favorites('web.favorite.screenids');

	foreach($fav_screens as $key => $favorite){
		$source = $favorite['source'];
		$sourceid = $favorite['value'];

		if('slideshowid' == $source){
			if(!$slide = get_slideshow_by_slideshowid($sourceid)) continue;
			$slide_added = true;
			
			$screenids[] = array( 
								'name'	=>	$slide['name'],
								'favobj'=>	'slideshowid',
								'favid'	=>	$sourceid,
								'action'=>	'remove'
							);

		}
		else{
			if(!$screen = get_screen_by_screenid($sourceid)) continue;			
			$screen_added = true;
			
			$screenids[] = array( 
								'name'	=>	$screen['name'],
								'favobj'=>	'screenid',
								'favid'	=>	$sourceid,
								'action'=>	'remove'
							);
		}
	}
	

	if(isset($screen_added)){
		$screenids[] = array( 
			'name'	=>	S_REMOVE.SPACE.S_ALL_S.SPACE.S_SCREENS,
			'favobj'=>	'screenid',
			'favid'	=>	0,
			'action'=>	'remove'
		);
	}
	
	if(isset($slide_added)){
		$screenids[] = array( 
			'name'	=>	S_REMOVE.SPACE.S_ALL_S.SPACE.S_SLIDES,
			'favobj'=>	'slideshowid',
			'favid'	=>	0,
			'action'=>	'remove'
		);
	}
	
return $screenids;
}

?>

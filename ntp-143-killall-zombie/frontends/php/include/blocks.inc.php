<?php
/*
** ZABBIX
** Copyright (C) 2001-2010 SIA Zabbix
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
require_once('include/requirements.inc.php');


// Author: Aly
function make_favorite_graphs(){
	$table = new CTableInfo();

	$graphids = array();
	$itemids = array();

	$fav_graphs = get_favorites('web.favorite.graphids');
	foreach($fav_graphs as $key => $favorite){
		if('itemid' == $favorite['source']){
			$itemids[$favorite['value']] = $favorite['value'];
		}
		else{
			$graphids[$favorite['value']] = $favorite['value'];
		}
	}

	$options = array(
			'graphids' => $graphids,
			'select_hosts' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND,
		);
	$graphs = CGraph::get($options);
	$graphs = zbx_toHash($graphs, 'graphid');

	$options = array(
			'itemids' => $itemids,
			'select_hosts' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND
		);
	$items = CItem::get($options);
	$items = zbx_toHash($items, 'itemid');

	foreach($fav_graphs as $key => $favorite){
		$sourceid = $favorite['value'];

		if('itemid' == $favorite['source']){
			if(!isset($items[$sourceid])) continue;

			$item = $items[$sourceid];
			$host = reset($item['hosts']);

			$item['description'] = item_description($item);

			$link = new CLink(get_node_name_by_elid($sourceid, null, ': ').$host['host'].':'.$item['description'],'history.php?action=showgraph&itemid='.$sourceid);
			$link->setTarget('blank');

			$capt = new CSpan($link);
			$capt->setAttribute('style','line-height: 14px; vertical-align: middle;');

			$icon = new CLink(new CImg('images/general/chart.png','chart',18,18,'borderless'),'history.php?action=showgraph&itemid='.$sourceid.'&fullscreen=1');
			$icon->setTarget('blank');
		}
		else{
			if(!isset($graphs[$sourceid])) continue;

			$graph = $graphs[$sourceid];
			$ghost = reset($graph['hosts']);

			$link = new CLink(get_node_name_by_elid($sourceid, null, ': ').$ghost['host'].':'.$graph['name'],'charts.php?graphid='.$sourceid);
			$link->setTarget('blank');

			$capt = new CSpan($link);
			$capt->setAttribute('style','line-height: 14px; vertical-align: middle;');

			$icon = new CLink(new CImg('images/general/chart.png','chart',18,18,'borderless'),'charts.php?graphid='.$sourceid.'&fullscreen=1');
			$icon->setTarget('blank');
		}

		$table->addRow(new CCol(array(
			$icon,
			SPACE,
			$capt)
		));
	}
	$td = new CCol(array(new CLink(S_GRAPHS.' &raquo;','charts.php','highlight')));
	$td->setAttribute('style','text-align: right;');

	$table->setFooter($td);

return $table;
}

// Author: Aly
function make_favorite_screens(){
	$table = new CTableInfo();

	$fav_screens = get_favorites('web.favorite.screenids');

	$screenids = array();
	foreach($fav_screens as $key => $favorite){
		if('screenid' == $favorite['source']){
			$screenids[$favorite['value']] = $favorite['value'];
		}
	}

	$options = array(
		'screenids' => $screenids,
		'output' => API_OUTPUT_EXTEND,
	);
	$screens = CScreen::get($options);
	$screens = zbx_toHash($screens, 'screenid');

	foreach($fav_screens as $key => $favorite){
		$source = $favorite['source'];
		$sourceid = $favorite['value'];

		if('slideshowid' == $source){
			if(!slideshow_accessible($sourceid, PERM_READ_ONLY)) continue;
			if(!$slide = get_slideshow_by_slideshowid($sourceid)) continue;

			$link = new CLink(get_node_name_by_elid($sourceid, null, ': ').$slide['name'],'screens.php?config=1&elementid='.$sourceid);
			$link->setTarget('blank');

			$capt = new CSpan($link);
			$capt->setAttribute('style','line-height: 14px; vertical-align: middle;');

			$icon = new CLink(new CImg('images/general/chart.png','screen',18,18,'borderless'),'screens.php?config=1&elementid='.$sourceid.'&fullscreen=1');
			$icon->setTarget('blank');
		}
		else{
			if(!isset($screens[$sourceid])) continue;
			$screen = $screens[$sourceid];

			$link = new CLink(get_node_name_by_elid($sourceid, null, ': ').$screen['name'],'screens.php?config=0&elementid='.$sourceid);
			$link->setTarget('blank');

			$capt = new CSpan($link);
			$capt->setAttribute('style','line-height: 14px; vertical-align: middle;');

			$icon = new CLink(new CImg('images/general/chart.png','screen',18,18,'borderless'),'screens.php?config=0&elementid='.$sourceid.'&fullscreen=1');
			$icon->setTarget('blank');
		}

		$table->addRow(new CCol(array(
			$icon,
			SPACE,
			$capt)
		));
	}

	$td = new CCol(array(new CLink(S_SCREENS.' &raquo;','screens.php','highlight')));
	$td->setAttribute('style','text-align: right;');

	$table->setFooter($td);

return $table;
}

// Author: Aly
function make_favorite_maps(){
	$table = new CTableInfo();

	$fav_sysmaps = get_favorites('web.favorite.sysmapids');

	$sysmapids = array();
	foreach($fav_sysmaps as $key => $favorite){
		$sysmapids[$favorite['value']] = $favorite['value'];
	}

	$options = array(
			'sysmapids' => $sysmapids,
			'output' => API_OUTPUT_EXTEND,
		);
	$sysmaps = CMap::get($options);

	foreach($sysmaps as $snum => $sysmap){
		$sysmapid = $sysmap['sysmapid'];

		$link = new CLink(get_node_name_by_elid($sysmapid, null, ': ').$sysmap['name'],'maps.php?sysmapid='.$sysmapid);
		$link->setTarget('blank');

		$capt = new CSpan($link);
		$capt->setAttribute('style','line-height: 14px; vertical-align: middle;');

		$icon = new CLink(new CImg('images/general/chart.png','map',18,18,'borderless'),'maps.php?sysmapid='.$sysmapid.'&fullscreen=1');
		$icon->setTarget('blank');

		$table->addRow(new CCol(array(
			$icon,
			SPACE,
			$capt)
		));
	}

	$td = new CCol(array(new CLink(S_MAPS.' &raquo;','maps.php','highlight')));
	$td->setAttribute('style','text-align: right;');

	$table->setFooter($td);

return $table;
}

// Author: Aly
function make_system_summary($filter){
	global $USER_DETAILS;

	$config = select_config();

	$table = new CTableInfo();
	$table->setHeader(array(
		is_show_all_nodes() ? S_NODE : null,
		S_HOST_GROUP,
		is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_DISASTER])?S_DISASTER:null,
		is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_HIGH])?S_HIGH:null,
		is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_AVERAGE])?S_AVERAGE:null,
		is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_WARNING])?S_WARNING:null,
		is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_INFORMATION])?S_INFORMATION:null,
		is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_NOT_CLASSIFIED])?S_NOT_CLASSIFIED:null
	));

// SELECT HOST GROUPS {{{
	$options = array(
		'nodeids' => get_current_nodeid(),
		'monitored_hosts' => 1,
		'groupids' => $filter['groupids'],
		'output' => API_OUTPUT_EXTEND,
	);
	$groups = CHostGroup::get($options);

	$groups = zbx_toHash($groups, 'groupid');

	order_result($groups, 'name');
	$groupids = array();
	foreach($groups as $gnum => $group){
		$groupids[] = $group['groupid'];
		$group['tab_priority'] = array();
		$group['tab_priority'][TRIGGER_SEVERITY_DISASTER] = array('count' => 0, 'triggers' => array());
		$group['tab_priority'][TRIGGER_SEVERITY_HIGH] = array('count' => 0, 'triggers' => array());
		$group['tab_priority'][TRIGGER_SEVERITY_AVERAGE] = array('count' => 0, 'triggers' => array());
		$group['tab_priority'][TRIGGER_SEVERITY_WARNING] = array('count' => 0, 'triggers' => array());
		$group['tab_priority'][TRIGGER_SEVERITY_INFORMATION] = array('count' => 0, 'triggers' => array());
		$group['tab_priority'][TRIGGER_SEVERITY_NOT_CLASSIFIED] = array('count' => 0, 'triggers' => array());
		$groups[$gnum] = $group;
	}
// }}} SELECT HOST GROUPS

// SELECT TRIGGERS {{{
	$options = array(
		'nodeids' => get_current_nodeid(),
		'groupids' => $groupids,
		'monitored' => 1,
		'maintenance' => $filter['maintenance'],
		'only_problems' => 1,
		'expand_data' => 1,
		'skipDependent' => 1,
		'filter' => array('priority' => $filter['severity']),
		'output' => API_OUTPUT_EXTEND,
	);

	$triggers = CTrigger::get($options);
	order_result($triggers, 'lastchange', ZBX_SORT_DOWN);

	foreach($triggers as $tnum => $trigger){
		$trigger['groups'] = zbx_toHash($trigger['groups'], 'groupid');

		foreach($groups as $groupid => $group){
			if(isset($trigger['groups'][$group['groupid']])){
				if($groups[$groupid]['tab_priority'][$trigger['priority']]['count'] < 30){
					$groups[$groupid]['tab_priority'][$trigger['priority']]['triggers'][] = $trigger;
				}
				$groups[$groupid]['tab_priority'][$trigger['priority']]['count']++;
			}
		}
		unset($triggers[$tnum]);
	}
// }}} SELECT TRIGGERS

	foreach($groups as $gnum => $group){
		$group_row = new CRow();
		if(is_show_all_nodes())
			$group_row->addItem(get_node_name_by_elid($group['groupid']));

		$name = new CLink($group['name'], 'tr_status.php?groupid='.$group['groupid'].'&show_triggers='.TRIGGERS_OPTION_ONLYTRUE);
		$group_row->addItem($name);

		foreach($group['tab_priority'] as $severity => $data){
			if(!is_null($filter['severity']) && !isset($filter['severity'][$severity])) continue;
			
			$trigger_count = $data['count'];

			if($trigger_count){
				$table_inf = new CTableInfo();
				$table_inf->setAttribute('style', 'width: 400px;');
				$table_inf->setHeader(array(
					is_show_all_nodes() ? S_NODE : null,
					S_HOST,
					S_ISSUE,
					S_AGE,
					($config['event_ack_enable']) ? S_ACK : NULL,
					S_ACTIONS
				));

				foreach($data['triggers'] as $tnum => $trigger){
					$trigger_hosts = $trigger['host'];

					$options = array(
						'nodeids' => get_current_nodeid(),
						'triggerids' => $trigger['triggerid'],
						'object' => EVENT_SOURCE_TRIGGERS,
						'value' => TRIGGER_VALUE_TRUE,
						'output' => API_OUTPUT_EXTEND,
						'nopermissions' => 1,
						'limit' => 1,
						'sortfield' => 'eventid',
						'sortorder' => ZBX_SORT_DOWN
					);

					$event = array();
					$event = CEvent::get($options);
					$event = reset($event);

					$actions = S_NO_DATA_SMALL;
					$ack = '-';
//*
					if(!empty($event)){
						if($config['event_ack_enable']){
							$ack = ($event['acknowledged'] == 1) ? new CLink(S_YES, 'acknow.php?eventid='.$event['eventid'], 'off')
								: new CLink(S_NO, 'acknow.php?eventid='.$event['eventid'], 'on');
						}

						// $description = expand_trigger_description_by_data(zbx_array_merge($trigger, array('clock' => $event['clock'])), ZBX_FLAG_EVENT);
						$actions = get_event_actions_status($event['eventid']);
					}
					else{
						$ack = '-';
						$actions = S_NO_DATA_SMALL;
						$event['clock'] = $trigger['lastchange'];
					}
//*/
//					$description = expandTriggerDescription($trigger, ZBX_FLAG_EVENT);
					$description = expand_trigger_description_by_data($trigger);

					$table_inf->addRow(array(
						get_node_name_by_elid($trigger['triggerid']),
						$trigger_hosts,
						new CCol($description, get_severity_style($trigger['priority'])),
						zbx_date2age($event['clock']),
//						zbx_date2age($trigger['lastchange']),
						($config['event_ack_enable']) ? (new CCol($ack, 'center')) : NULL,
						$actions
					));
				}

				$trigger_count = new CSpan($trigger_count, 'pointer');
				$trigger_count->setHint($table_inf);
			}

			$group_row->addItem(new CCol($trigger_count, get_severity_style($severity, $trigger_count)));
			unset($table_inf);
		}
		$table->addRow($group_row);
	}

	$table->setFooter(new CCol(S_UPDATED.': '.date('H:i:s', time())));

return $table;
}

function make_hoststat_summary($filter){
	global $USER_DETAILS;

	$table = new CTableInfo();
	$table->setHeader(array(
		is_show_all_nodes() ? S_NODE : null,
		S_HOST_GROUP,
		S_WITHOUT_PROBLEMS,
		S_WITH_PROBLEMS,
		S_TOTAL
	));

// SELECT HOST GROUPS {{{
	$options = array(
		'nodeids' => get_current_nodeid(),
		'groupids' => $filter['groupids'],
		'monitored_hosts' => 1,
		'output' => API_OUTPUT_EXTEND
	);

	$groups = CHostGroup::get($options);
	$groups = zbx_toHash($groups, 'groupid');

	order_result($groups, 'name');
// }}} SELECT HOST GROUPS

// SELECT HOSTS {{{
	$options = array(
		'nodeids' => get_current_nodeid(),
		'groupids' => zbx_objectValues($groups, 'groupid'),
		'monitored_hosts' => 1,
		'filter' => array('maintenance_status' => $filter['maintenance']),
		'output' => API_OUTPUT_EXTEND
	);

	$hosts = CHost::get($options);
	$hosts = zbx_toHash($hosts, 'hostid');
// }}} SELECT HOSTS

// SELECT TRIGGERS {{{
	$options = array(
		'nodeids' => get_current_nodeid(),
		'monitored' => 1,
		'maintenance' => $filter['maintenance'],
		'only_problems' => 1,
		'expand_data' => 1,
		'filter' => array('priority' => $filter['severity']),
		'output' => API_OUTPUT_EXTEND,
	);

	$triggers = CTrigger::get($options);
// }}} SELECT TRIGGERS

	$hosts_data = array();
	$problematic_host_list = array();
	$highest_severity = array();

	foreach($triggers as $tnum => $trigger){
		foreach($trigger['hosts'] as $thnum => $trigger_host){
			if(!isset($hosts[$trigger_host['hostid']])) continue;
			else $host = $hosts[$trigger_host['hostid']];

			if(!isset($problematic_host_list[$host['hostid']])){
				$problematic_host_list[$host['hostid']] = array();
				$problematic_host_list[$host['hostid']]['host'] = $host['host'];
				$problematic_host_list[$host['hostid']]['hostid'] = $host['hostid'];
				$problematic_host_list[$host['hostid']]['severities'] = array();
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_DISASTER] = 0;
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_HIGH] = 0;
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_AVERAGE] = 0;
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_WARNING] = 0;
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_INFORMATION] = 0;
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_NOT_CLASSIFIED] = 0;
			}

			$problematic_host_list[$host['hostid']]['severities'][$trigger['priority']]++;

			foreach($host['groups'] as $gnum => $group){
				if(!isset($highest_severity[$group['groupid']]))
					$highest_severity[$group['groupid']] = 0;
				
				if($trigger['priority'] > $highest_severity[$group['groupid']]){
					$highest_severity[$group['groupid']] = $trigger['priority'];
				}

				if(!isset($hosts_data[$group['groupid']]))
					$hosts_data[$group['groupid']] = array('problematic' => 0, 'ok' => 0, 'hostids' => array());

				if(!isset($hosts_data[$group['groupid']]['hostids'][$host['hostid']])){
					$hosts_data[$group['groupid']]['hostids'][$host['hostid']] = $host['hostid'];
					$hosts_data[$group['groupid']]['problematic']++;
				}
			}
		}
	}

	foreach($hosts as $hnum => $host){
		foreach($host['groups'] as $gnum => $group){
			if(!isset($groups[$group['groupid']]['hosts'])) 
				$groups[$group['groupid']]['hosts'] = array();

			$groups[$group['groupid']]['hosts'][$host['hostid']] = array('hostid'=> $host['hostid']);

			if(!isset($highest_severity[$group['groupid']]))
				$highest_severity[$group['groupid']] = 0;

			if(!isset($hosts_data[$group['groupid']]))
				$hosts_data[$group['groupid']] = array('problematic' => 0,'ok' => 0);

			if(!isset($problematic_host_list[$host['hostid']]))
				$hosts_data[$group['groupid']]['ok']++;
		}
	}

	foreach($groups as $gnum => $group){
		if(!isset($hosts_data[$group['groupid']])) continue;

		$group_row = new CRow();
		if(is_show_all_nodes())
			$group_row->addItem(get_node_name_by_elid($group['groupid']));

		$name = new CLink($group['name'], 'tr_status.php?groupid='.$group['groupid'].'&hostid=0&show_triggers='.TRIGGERS_OPTION_ONLYTRUE);
		$group_row->addItem($name);

// if hostgroup contains problematic hosts, hint should be built
		if($hosts_data[$group['groupid']]['problematic']){
			$table_inf = new CTableInfo();
			$table_inf->setAttribute('style', 'width: 400px;');
			$table_inf->setHeader(array(
				S_HOST,
				is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_DISASTER])?S_DISASTER:null,
				is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_HIGH])?S_HIGH:null,
				is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_AVERAGE])?S_AVERAGE:null,
				is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_WARNING])?S_WARNING:null,
				is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_INFORMATION])?S_INFORMATION:null,
				is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_NOT_CLASSIFIED])?S_NOT_CLASSIFIED:null
			));

			$popup_rows = 0;

			foreach($group['hosts'] as $hnum => $host){
				$hostid = $host['hostid'];
				if(!isset($problematic_host_list[$hostid])) continue;

				if($popup_rows >= ZBX_POPUP_MAX_ROWS) break;
				$popup_rows++;

				$host_data = $problematic_host_list[$hostid];

				$r = new CRow();
				$r->addItem(new CLink($host_data['host'], 'tr_status.php?groupid='.$group['groupid'].'&hostid='.$hostid.'&show_triggers='.TRIGGERS_OPTION_ONLYTRUE));

				foreach($problematic_host_list[$host['hostid']]['severities'] as $severity => $trigger_count){
					if(!is_null($filter['severity'])&&!isset($filter['severity'][$severity])) continue;

					$r->addItem(new CCol($trigger_count, get_severity_style($severity, $trigger_count)));
				}

				$table_inf->addRow($r);
			}

			$problematic_count = new CSpan($hosts_data[$group['groupid']]['problematic'], 'pointer');
			$problematic_count->setHint($table_inf);
		}
		else{
			$problematic_count = 0;
		}



		$group_row->addItem(
				new CCol(
					$hosts_data[$group['groupid']]['ok'],
					get_severity_style($highest_severity[$group['groupid']], 0)
				)
			);
		$group_row->addItem(
				new CCol(
					$problematic_count,
					get_severity_style($highest_severity[$group['groupid']], $hosts_data[$group['groupid']]['problematic'])
				)
			);
		$group_row->addItem($hosts_data[$group['groupid']]['problematic'] + $hosts_data[$group['groupid']]['ok']);

		$table->addRow($group_row);
	}

	$table->setFooter(new CCol(S_UPDATED.': '.date('H:i:s', time())));

return $table;
}

// Author: Aly
function make_status_of_zbx(){
	global $USER_DETAILS;

	$table = new CTableInfo();
	$table->setHeader(array(
		S_PARAMETER,
		S_VALUE,
		S_DETAILS
	));

	show_messages(); //because in function get_status(); function clear_messages() is called when fsockopen() fails.
	$status=get_status();

	$table->addRow(array(S_ZABBIX_SERVER_IS_RUNNING,
	new CSpan($status['zabbix_server'], ($status['zabbix_server'] == S_YES ? 'off' : 'on')),' - '));
	//	$table->addRow(array(S_VALUES_STORED,$status['history_count']));$table->addRow(array(S_TRENDS_STORED,$status['trends_count']));
	$title = new CSpan(S_NUMBER_OF_HOSTS);
	$title->setAttribute('title', 'asdad');
	$table->addRow(array(S_NUMBER_OF_HOSTS ,$status['hosts_count'],
		array(
			new CSpan($status['hosts_count_monitored'],'off'),' / ',
			new CSpan($status['hosts_count_not_monitored'],'on'),' / ',
			new CSpan($status['hosts_count_template'],'unknown')
		)
	));
	$title = new CSpan(S_NUMBER_OF_ITEMS);
	$title->setAttribute('title', S_NUMBER_OF_ITEMS_TOOLTIP);
	$table->addRow(array($title, $status['items_count'],
		array(
			new CSpan($status['items_count_monitored'],'off'),' / ',
			new CSpan($status['items_count_disabled'],'on'),' / ',
			new CSpan($status['items_count_not_supported'],'unknown')
		)
	));
	$title = new CSpan(S_NUMBER_OF_TRIGGERS);
	$title->setAttribute('title', S_NUMBER_OF_TRIGGERS_TOOLTIP);
	$table->addRow(array($title,$status['triggers_count'],
		array(
			$status['triggers_count_enabled'],' / ',
			$status['triggers_count_disabled'].SPACE.SPACE.'[',
			new CSpan($status['triggers_count_on'],'on'),' / ',
			new CSpan($status['triggers_count_unknown'],'unknown'),' / ',
			new CSpan($status['triggers_count_off'],'off'),']'
		)
	));
/*
	$table->addRow(array(S_NUMBER_OF_EVENTS,$status['events_count'],' - '));
	$table->addRow(array(S_NUMBER_OF_ALERTS,$status['alerts_count'],' - '));
*/

//Log Out 10min
	$sql = 'SELECT COUNT(*) as usr_cnt FROM users u WHERE '.DBin_node('u.userid');
	$usr_cnt = DBfetch(DBselect($sql));

	$online_cnt = 0;
	$sql = 'SELECT DISTINCT s.userid, MAX(s.lastaccess) as lastaccess, MAX(u.autologout) as autologout, s.status '.
			' FROM sessions s, users u '.
			' WHERE '.DBin_node('s.userid').
				' AND u.userid=s.userid '.
				' AND s.status='.ZBX_SESSION_ACTIVE.
			' GROUP BY s.userid,s.status';
	$db_users = DBselect($sql);
	while($user=DBfetch($db_users)){
		$online_time = (($user['autologout'] == 0) || (ZBX_USER_ONLINE_TIME<$user['autologout']))?ZBX_USER_ONLINE_TIME:$user['autologout'];
		if(!is_null($user['lastaccess']) && (($user['lastaccess']+$online_time)>=time()) && (ZBX_SESSION_ACTIVE == $user['status'])) $online_cnt++;
	}

	$table->addRow(array(S_NUMBER_OF_USERS,$usr_cnt,new CSpan($online_cnt,'green')));
	$table->addRow(array(S_REQUIRED_SERVER_PERFORMANCE_NVPS,$status['qps_total'],' - '));


// CHECK REQUIREMENTS {{{
	if($USER_DETAILS['type'] == USER_TYPE_SUPER_ADMIN){
		$reqs = check_php_requirements();
		foreach($reqs as $req){
			if($req['result'] == false){
				$table->addRow(array(
					new CSpan($req['name'], 'red'),
					new CSpan($req['current'], 'red'),
					new CSpan($req['error'], 'red')
				));
			}
		}
	}
// }}}CHECK REQUIREMENTS


	$table->setFooter(new CCol(S_UPDATED.': '.date("H:i:s",time())));

return $table;
}


// author Aly
function make_latest_issues($filter = array()){
	global $USER_DETAILS;

	$config = select_config();

	$limit = isset($filter['limit']) ? $filter['limit'] : 20;
	$options = array(
		'groupids' => $filter['groupids'],
		'only_problems' => 1,
		'monitored' => 1,
		'maintenance' => $filter['maintenance'],
		'skipDependent' => 1,
		'filter' => array('priority' => $filter['severity']),
		'select_hosts' => API_OUTPUT_EXTEND,
		'output' => API_OUTPUT_EXTEND,
		'sortfield' => 'lastchange',
		'sortorder' => ZBX_SORT_DOWN,
		'limit' => $limit
	);
	if(isset($filter['hostids'])) $options['hostids'] = $filter['hostids'];
	$triggers = CTrigger::get($options);

// GATHER HOSTS FOR SELECTED TRIGGERS {{{
	$triggers_hosts = array();
	foreach($triggers as $tnum => $trigger){
// if trigger is lost(broken expression) we skip it
		if(empty($trigger['hosts'])){
			unset($triggers[$tnum]);
			continue;
		}

		$triggers_hosts = array_merge($triggers_hosts, $trigger['hosts']);
	}
	$triggers_hosts = zbx_toHash($triggers_hosts, 'hostid');
	$triggers_hostids = array_keys($triggers_hosts);
// }}} GATHER HOSTS FOR SELECTED TRIGGERS

	$scripts_by_hosts = CScript::getScriptsByHosts($triggers_hostids);

	$table  = new CTableInfo();
	$table->setHeader(array(
		is_show_all_nodes() ? S_NODE : null,
		S_HOST,
		S_ISSUE,
		S_LAST_CHANGE,
		S_AGE,
		($config['event_ack_enable'])? S_ACK : NULL,
		S_ACTIONS
	));

	foreach($triggers as $tnum => $trigger){
// Check for dependencies
		$host = reset($trigger['hosts']);

		$trigger['hostid'] = $host['hostid'];
		$trigger['host'] = $host['host'];

		$host = null;
		$menus = '';

		$host_nodeid = id2nodeid($trigger['hostid']);
		foreach($scripts_by_hosts[$trigger['hostid']] as $id => $script){
			$script_nodeid = id2nodeid($script['scriptid']);
			if( (bccomp($host_nodeid ,$script_nodeid ) == 0))
				$menus.= "['".$script['name']."',\"javascript: openWinCentered('scripts_exec.php?execute=1&hostid=".$trigger['hostid']."&scriptid=".$script['scriptid']."','".S_TOOLS."',760,540,'titlebar=no, resizable=yes, scrollbars=yes, dialog=no');\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";
		}

		if(!empty($scripts_by_hosts)){
			$menus = "[".zbx_jsvalue(S_TOOLS).",null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],".$menus;
		}

		$menus.= "[".zbx_jsvalue(S_LINKS).",null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],";
		$menus.= "['".S_LATEST_DATA."',\"javascript: redirect('latest.php?groupid=0&hostid=".$trigger['hostid']."')\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";

		$menus = rtrim($menus,',');
		$menus = 'show_popup_menu(event,['.$menus.'],180);';

		$host = new CSpan($trigger['host'],'link_menu pointer');
		$host->setAttribute('onclick','javascript: '.$menus);
		//$host = new CSpan($trigger['host'],'link_menu pointer');
		//$host->setAttribute('onclick','javascript: '.$menus);

// Maintenance {{{

		$trigger_host = $triggers_hosts[$trigger['hostid']];

		$text = null;
		$style = 'link_menu';
		if($trigger_host['maintenance_status']){
			$style.= ' orange';

			$options = array(
				'maintenanceids' => $trigger_host['maintenanceid'],
				'output' => API_OUTPUT_EXTEND
			);
			$maintenances = CMaintenance::get($options);
			$maintenance = reset($maintenances);

			$text = $maintenance['name'];
			$text.=' ['.($trigger_host['maintenance_type'] ? S_NO_DATA_MAINTENANCE : S_NORMAL_MAINTENANCE).']';
		}

		$host = new CSpan($trigger['host'], $style.' pointer');
		$host->setAttribute('onclick','javascript: '.$menus);
		if(!is_null($text)) $host->setHint($text, '', '', false);

// }}} Maintenance

		$event_sql = 'SELECT e.eventid, e.value, e.clock, e.objectid as triggerid, e.acknowledged'.
					' FROM events e'.
					' WHERE e.object='.EVENT_OBJECT_TRIGGER.
						' AND e.objectid='.$trigger['triggerid'].
						' AND e.value='.TRIGGER_VALUE_TRUE.
					' ORDER by e.object DESC, e.objectid DESC, e.eventid DESC';
		$res_events = DBSelect($event_sql,1);
		while($row_event=DBfetch($res_events)){
			$ack = NULL;
			if($config['event_ack_enable']){
				if($row_event['acknowledged'] == 1){
					$ack_info = make_acktab_by_eventid($row_event['eventid']);
					$ack_info->setAttribute('style','width: auto;');

					$ack=new CLink(S_YES,'acknow.php?eventid='.$row_event['eventid'],'off');
					$ack->setHint($ack_info, '', '', false);
				}
				else{
					$ack= new CLink(S_NO,'acknow.php?eventid='.$row_event['eventid'],'on');
				}
			}

//			$description = expand_trigger_description($row['triggerid']);
			$description = expand_trigger_description_by_data(zbx_array_merge($trigger, array('clock'=>$row_event['clock'])),ZBX_FLAG_EVENT);

//actions
			$actions = get_event_actions_stat_hints($row_event['eventid']);

			$clock = new CLink(
					zbx_date2str(S_DATE_FORMAT_YMDHMS,$row_event['clock']),
					'events.php?triggerid='.$trigger['triggerid'].'&source=0&show_unknown=1&nav_time='.$row_event['clock']
					);

			if($trigger['url'])
				$description = new CLink($description, $trigger['url'], null, null, true);
			else
				$description = new CSpan($description,'pointer');

			$description = new CCol($description,get_severity_style($trigger['priority']));
			$description->setHint(make_popup_eventlist($row_event['eventid'], $trigger['type'], $trigger['triggerid']), '', '', false);

			$table->addRow(array(
				get_node_name_by_elid($trigger['triggerid']),
				$host,
				$description,
				$clock,
				zbx_date2age($row_event['clock']),
				$ack,
				$actions
			));
		}
		unset($trigger,$description,$actions,$alerts,$hint);
	}
	$table->setFooter(new CCol(S_UPDATED.': '.date("H:i:s",time())));

return $table;
}

// author Aly
function make_webmon_overview($filter){
	global $USER_DETAILS;

	$options = array(
		'groupids' => $filter['groupids'],
		'monitored_hosts' => 1,
		'filter' => array('maintenance_status' => $filter['maintenance'])
	);

	$available_hosts = CHost::get($options);
	$available_hosts = zbx_objectValues($available_hosts,'hostid');

	$table  = new CTableInfo();
	$table->setHeader(array(
		is_show_all_nodes() ? S_NODE : null,
		S_HOST_GROUP,
		S_OK,
		S_FAILED,
		S_IN_PROGRESS,
		S_UNKNOWN
		));

	$options = array(
		'monitored_hosts' => 1,
		'with_monitored_httptests' => 1,
		'output' => API_OUTPUT_EXTEND
	);
	$groups = CHostGroup::get($options);
	foreach($groups as $gnum => $group){
		$showGroup = false;
		$apps['ok'] = 0;
		$apps['failed'] = 0;
		$apps[HTTPTEST_STATE_BUSY] = 0;
		$apps[HTTPTEST_STATE_UNKNOWN] = 0;

		$sql = 'SELECT DISTINCT ht.name, ht.httptestid, ht.curstate, ht.lastfailedstep '.
				' FROM httptest ht, applications a, hosts_groups hg, groups g '.
				' WHERE g.groupid='.$group['groupid'].
					' AND '.DBcondition('hg.hostid',$available_hosts).
					' AND hg.groupid=g.groupid '.
					' AND a.hostid=hg.hostid '.
					' AND ht.applicationid=a.applicationid '.
					' AND ht.status='.HTTPTEST_STATUS_ACTIVE;
		$db_httptests = DBselect($sql);
		while($httptest_data = DBfetch($db_httptests)){
			$showGroup = true;
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

		if(!$showGroup) continue;

		$table->addRow(array(
			is_show_all_nodes() ? get_node_name_by_elid($group['groupid']) : null,
			$group['name'],
			new CSpan($apps['ok'],'off'),
			new CSpan($apps['failed'],$apps['failed']?'on':'off'),
			new CSpan($apps[HTTPTEST_STATE_BUSY],$apps[HTTPTEST_STATE_BUSY]?'orange':'off'),
			new CSpan($apps[HTTPTEST_STATE_UNKNOWN],'unknown')
		));
	}
	$table->setFooter(new CCol(S_UPDATED.': '.date("H:i:s",time())));
return $table;
}

// Author: Aly
function make_discovery_status(){

	$drules = array();
	$druleids = array();
	$sql = 'SELECT DISTINCT * '.
			' FROM drules '.
			' WHERE '.DBin_node('druleid').
				' AND status='.DHOST_STATUS_ACTIVE.
			' ORDER BY name';
	$db_drules = DBselect($sql);
	while($drule_data = DBfetch($db_drules)){
		$druleids[$drule_data['druleid']] = $drule_data['druleid'];

		$drules[$drule_data['druleid']] = $drule_data;
		$drules[$drule_data['druleid']]['up'] = 0;
		$drules[$drule_data['druleid']]['down'] = 0;
	}


	$services = array();
	$discovery_info = array();

	$sql = 'SELECT d.* '.
			' FROM dhosts d '.
			' WHERE '.DBin_node('d.dhostid').
				' AND '.DBcondition('d.druleid', $druleids).
			' ORDER BY d.dhostid,d.status';
	$db_dhosts = DBselect($sql);
	while($drule_data = DBfetch($db_dhosts)){
		if(DRULE_STATUS_DISABLED == $drule_data['status']){
			$drules[$drule_data['druleid']]['down']++;		}
		else{
			$drules[$drule_data['druleid']]['up']++;
		}
	}

	$header = array(
		is_show_all_nodes() ? new CCol(S_NODE, 'center') : null,
		new CCol(S_DISCOVERY_RULE, 'center'),
		new CCol(S_UP),
		new CCol(S_DOWN)
		);

	$table  = new CTableInfo();
	$table->setHeader($header,'vertical_header');

	foreach($drules as $druleid => $drule){
		$table->addRow(array(
			get_node_name_by_elid($druleid),
			new CLink(get_node_name_by_elid($drule['druleid'], null, ': ').$drule['name'],'discovery.php?druleid='.$druleid),
			new CSpan($drule['up'],'green'),
			new CSpan($drule['down'],($drule['down'] > 0)?'red':'green')
		));
	}
	$table->setFooter(new CCol(S_UPDATED.': '.date("H:i:s",time())));

return 	$table;
}

function make_graph_menu(&$menu,&$submenu){

	$menu['menu_graphs'][] = array(
				S_FAVOURITE.SPACE.S_GRAPHS,
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
				'void(0);',
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
	$itemids = array();

	$fav_graphs = get_favorites('web.favorite.graphids');
	foreach($fav_graphs as $key => $favorite){
		if('itemid' == $favorite['source']){
			$itemids[$favorite['value']] = $favorite['value'];
		}
		else{
			$graphids[$favorite['value']] = $favorite['value'];
		}
	}

	$options = array(
			'graphids' => $graphids,
			'nopermissions' => API_OUTPUT_EXTEND,
			'select_hosts' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND
		);
	$graphs = CGraph::get($options);
	$graphs = zbx_toHash($graphs, 'graphid');

	$options = array(
			'itemids' => $itemids,
			'nopermissions' => 1,
			'select_hosts' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND
		);
	$items = CItem::get($options);
	$items = zbx_toHash($items, 'itemid');

	$graphids = array();
	
	foreach($fav_graphs as $key => $favorite){
		$source = $favorite['source'];
		$sourceid = $favorite['value'];

		if('itemid' == $source){
			if(!isset($items[$sourceid])) continue;
			$item_added = true;

			$item = $items[$sourceid];
			$host = reset($item['hosts']);

			$item['description'] = item_description($item);

			$graphids[] = array(
							'name'	=>	$host['host'].':'.$item['description'],
							'favobj'=>	'itemid',
							'favid'	=>	$sourceid,
							'action'=>	'remove'
						);
		}
		else{
			if(!isset($graphs[$sourceid])) continue;
			$graph_added = true;

			$graph = $graphs[$sourceid];
			$ghost = reset($graph['hosts']);

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

	$menu['menu_sysmaps'][] = array(S_FAVOURITE.SPACE.S_MAPS, null, null, array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader')));
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
	$fav_sysmaps = get_favorites('web.favorite.sysmapids');

	$maps = array();
	$sysmapids = array();
	foreach($fav_sysmaps as $key => $favorite){
		$sysmapids[$favorite['value']] = $favorite['value'];
	}

	$options = array(
			'sysmapids' => $sysmapids,
			'nopermissions' => 1,
			'output' => API_OUTPUT_EXTEND,
		);
	$sysmaps = CMap::get($options);

	foreach($sysmaps as $snum => $sysmap){
		$maps[] = array(
				'name'	=>	$sysmap['name'],
				'favobj'=>	'sysmapid',
				'favid'	=>	$sysmap['sysmapid'],
				'action'=>	'remove'
			);
	}

	if(!empty($maps)){
		$maps[] = array(
				'name'	=>	S_REMOVE.SPACE.S_ALL_S.SPACE.S_MAPS,
				'favobj'=>	'sysmapid',
				'favid'	=>	0,
				'action'=>	'remove'
			);
	}

return $maps;
}

function make_screen_menu(&$menu,&$submenu){

	$menu['menu_screens'][] = array(S_FAVOURITE.SPACE.S_SCREENS, null, null, array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader')));
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

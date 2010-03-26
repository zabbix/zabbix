<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
	require_once('include/config.inc.php');
	require_once('include/hosts.inc.php');
	require_once('include/events.inc.php');
	require_once('include/actions.inc.php');
	require_once('include/discovery.inc.php');
	require_once('include/html.inc.php');

	$page['title'] = 'S_LATEST_EVENTS';
	$page['file'] = 'events.php';
	$page['hist_arg'] = array('groupid','hostid');
	$page['scripts'] = array('class.calendar.js','scriptaculous.js?load=effects,dragdrop','gtlc.js');

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);

	if(PAGE_TYPE_HTML == $page['type']){
		define('ZBX_PAGE_DO_REFRESH', 1);
	}

	include_once('include/page_header.php');
?>
<?php
	$allow_discovery = check_right_on_discovery(PERM_READ_ONLY);

	$allowed_sources[] = EVENT_SOURCE_TRIGGERS;
	if($allow_discovery) $allowed_sources[] = EVENT_SOURCE_DISCOVERY;

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'source'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	IN($allowed_sources),	NULL),
		'groupid'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
		'hostid'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
		'triggerid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),

		'period'=>	array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'dec'=>		array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'inc'=>		array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'left'=>	array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'right'=>	array(T_ZBX_INT, O_OPT,	 null,	null, null),
		'stime'=>	array(T_ZBX_STR, O_OPT,	 null,	null, null),
		
		'load'=>			array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			NULL),
		'fullscreen'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		NULL),
// filter
		'filter_rst'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
		'filter_set'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,	NULL),

		'hide_unknown'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			'isset({favid})'),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		NULL),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
	);

	check_fields($fields);
	
/* AJAX */
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.events.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		include_once('include/page_footer.php');
		exit();
	}
//--------

// FILTER
	if(isset($_REQUEST['filter_rst'])){
		$_REQUEST['triggerid'] = 0;
		$_REQUEST['hide_unknown'] = 0;
	}

	$_REQUEST['triggerid'] = get_request('triggerid',CProfile::get('web.events.filter.triggerid',0));
	$_REQUEST['hide_unknown'] = get_request('hide_unknown',CProfile::get('web.events.filter.hide_unknown',0));

	if(isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst'])){
		CProfile::update('web.events.filter.triggerid',$_REQUEST['triggerid'], PROFILE_TYPE_ID);
		CProfile::update('web.events.filter.hide_unknown',$_REQUEST['hide_unknown'], PROFILE_TYPE_INT);
	}
// --------------
	
	$source = get_request('source', CProfile::get('web.events.source', EVENT_SOURCE_TRIGGERS));
	CProfile::update('web.events.source',$source, PROFILE_TYPE_INT);
?>
<?php

	$events_wdgt = new CWidget();
	
// PAGE HEADER {{{
	$url = '?fullscreen='.($_REQUEST['fullscreen']?'0':'1');
	$fs_icon = new CDiv(SPACE,'fullscreen');
	$fs_icon->setAttribute('title',$_REQUEST['fullscreen']?S_NORMAL.' '.S_VIEW:S_FULLSCREEN);
	$fs_icon->addAction('onclick',new CJSscript("javascript: document.location = '".$url."';"));
	$events_wdgt->addPageHeader(array(S_HISTORY_OF_EVENTS_BIG.SPACE.S_ON.SPACE, date(S_DATE_FORMAT_YMDHMS,time())), $fs_icon);
// }}}PAGE HEADER	
	
	
// HEADER {{{
	$r_form = new CForm(null, 'get');
	$r_form->addVar('fullscreen',$_REQUEST['fullscreen']);
	
	if(EVENT_SOURCE_TRIGGERS == $source){
		if(isset($_REQUEST['triggerid']) && ($_REQUEST['triggerid']>0)){
			$sql = 'SELECT DISTINCT hg.groupid, hg.hostid '.
					' FROM hosts_groups hg, functions f, items i'.
					' WHERE i.itemid=f.itemid '.
						' AND hg.hostid=i.hostid '.
						' AND f.triggerid='.$_REQUEST['triggerid'];
			if($host_group = DBfetch(DBselect($sql,1))){
				$_REQUEST['groupid'] = $host_group['groupid'];
				$_REQUEST['hostid'] = $host_group['hostid'];
			}
			else{
				unset($_REQUEST['triggerid']);
			}
		}

		$options = array(
			'allow_all_hosts' => 1,
			'monitored_hosts' => 1,
			'with_items' => 1
		);
		if(!$ZBX_WITH_ALL_NODES) $options['only_current_node'] = 1;

		$PAGE_GROUPS = get_viewed_groups(PERM_READ_ONLY, $options);
		$PAGE_HOSTS = get_viewed_hosts(PERM_READ_ONLY, $PAGE_GROUPS['selected'], $options);
		validate_group_with_host($PAGE_GROUPS,$PAGE_HOSTS);

		
		if(isset($_REQUEST['triggerid']) && ($_REQUEST['triggerid']>0)){
			$triggers = CTrigger::get(array( 'triggerids' => $_REQUEST['triggerid'] ));
			if(empty($triggers)){
				unset($_REQUEST['triggerid']);
			}
		}

		$cmbGroups = new CComboBox('groupid',$PAGE_GROUPS['selected'],'javascript: submit();');
		foreach($PAGE_GROUPS['groups'] as $groupid => $name){
			$cmbGroups->addItem($groupid, get_node_name_by_elid($groupid, null, ': ').$name);
		}
		$cmbHosts = new CComboBox('hostid',$PAGE_HOSTS['selected'],'javascript: submit();');
		foreach($PAGE_HOSTS['hosts'] as $hostid => $name){
			$cmbHosts->addItem($hostid, get_node_name_by_elid($hostid, null, ': ').$name);
		}
		$r_form->addItem(array(S_GROUP.SPACE,$cmbGroups));
		$r_form->addItem(array(SPACE.S_HOST.SPACE,$cmbHosts));
	}

	if($allow_discovery){
		$cmbSource = new CComboBox('source', $source, 'submit()');
		$cmbSource->addItem(EVENT_SOURCE_TRIGGERS, S_TRIGGER);
		$cmbSource->addItem(EVENT_SOURCE_DISCOVERY, S_DISCOVERY);
		$r_form->addItem(array(SPACE.S_SOURCE.SPACE, $cmbSource));
	}

	$events_wdgt->addHeader(S_EVENTS_BIG, $r_form);
	
	$numrows = new CDiv();
	$numrows->setAttribute('name', 'numrows');
	$events_wdgt->addHeader($numrows);
// }}} HEADER 


// FILTER {{{
	if(EVENT_SOURCE_TRIGGERS == $source){
		$filterForm = new CFormTable(null, null, 'get');//,'events.php?filter_set=1','POST',null,'sform');
		$filterForm->setAttribute('name', 'zbx_filter');
		$filterForm->setAttribute('id', 'zbx_filter');
	
		$filterForm->addVar('triggerid', get_request('triggerid'));

		if(isset($_REQUEST['triggerid']) && ($_REQUEST['triggerid']>0)){
			$trigger = expand_trigger_description($_REQUEST['triggerid']);
		}
		else{
			$trigger = '';
		}

		$row = new CRow(array(
			new CCol(S_TRIGGER,'form_row_l'),
			new CCol(array(
				new CTextBox('trigger',$trigger,96,'yes'),
				new CButton("btn1",S_SELECT,"return PopUp('popup.php?"."dstfrm=".$filterForm->GetName()."&dstfld1=triggerid&dstfld2=trigger"."&srctbl=triggers&srcfld1=triggerid&srcfld2=description&real_hosts=1');",'T')
			),'form_row_r')
		));
		$filterForm->addRow($row);

		$filterForm->addVar('hide_unknown',$_REQUEST['hide_unknown']);
		$unkcbx = new CCheckBox('hide_unk',$_REQUEST['hide_unknown'],null,'1');
		$unkcbx->setAction('javascript: create_var("'.$filterForm->GetName().'", "hide_unknown", (this.checked?1:0), 0); ');

		$filterForm->addRow(S_HIDE_UNKNOWN,$unkcbx);
		
		$reset = new CButton('filter_rst',S_RESET);
		$reset->setType('button');
		$reset->setAction('javascript: var uri = new Curl(location.href); uri.setArgument("filter_rst",1); location.href = uri.getUrl();');

		$filterForm->addItemToBottomRow(new CButton('filter_set',S_FILTER));
		$filterForm->addItemToBottomRow($reset);

		$events_wdgt->addFlicker($filterForm, CProfile::get('web.events.filter.state',0));

		
		$scroll_div = new CDiv();
		$scroll_div->setAttribute('id', 'scrollbar_cntr');
		$events_wdgt->addFlicker($scroll_div, CProfile::get('web.events.filter.state',0));
	}
// }}} FILTER

	
	$table = new CTableInfo(S_NO_EVENTS_FOUND);
	
// CHECK IF EVENTS EXISTS {{{
	$options = array(
		'output' => API_OUTPUT_EXTEND,
		'sortfield' => 'eventid',
		'sortorder' => ZBX_SORT_UP,
		'nopermissions' => 1,
		'limit' => 1
	);
	
	if($source == EVENT_SOURCE_DISCOVERY){
		$options['source'] = EVENT_SOURCE_DISCOVERY;
	}
	else{			
		if($_REQUEST['triggerid']){
			$options['object'] = EVENT_OBJECT_TRIGGER;
			$options['triggerids'] = $_REQUEST['triggerid'];
		}
	}

	$firstEvent = CEvent::get($options);
// }}} CHECK IF EVENTS EXISTS

	$_REQUEST['period'] = get_request('period', 604800); // 1 week
	$effectiveperiod = navigation_bar_calc();
	$bstime = $_REQUEST['stime'];
	$from = mktime(substr($bstime,8,2),substr($bstime,10,2),0,substr($bstime,4,2),substr($bstime,6,2),substr($bstime,0,4));
	$till = $from + $effectiveperiod;

	if(empty($firstEvent)){
		$events_wdgt->addItem($table);
		$starttime = null;
	}
	else{
		$firstEvent = reset($firstEvent);
		$starttime = $firstEvent['clock'];		
		
		if($source == EVENT_SOURCE_DISCOVERY){
			$options = array(
				'source' => EVENT_SOURCE_DISCOVERY,
				'time_from' => $from,
				'time_till' => $till,
				'output' => API_OUTPUT_SHORTEN,
				'sortfield' => 'eventid',
				'sortorder' => ZBX_SORT_DOWN,
				'limit' => ($config['search_limit']+1)
			);
			$dsc_events = CEvent::get($options);

			$paging = getPagingLine($dsc_events);

			$options = array(
				'source' => EVENT_SOURCE_DISCOVERY,
				'eventids' => zbx_objectValues($dsc_events,'eventid'),
				'output' => API_OUTPUT_EXTEND,
				'select_hosts' => API_OUTPUT_EXTEND,
				'select_triggers' => API_OUTPUT_EXTEND,
				'select_items' => API_OUTPUT_EXTEND,
			);
			$dsc_events = CEvent::get($options);
			order_result($dsc_events, 'eventid', ZBX_SORT_DOWN);

			
			$objectids = array();
			foreach($dsc_events as $enum => $event_data){
				$objectids[$event_data['objectid']] = $event_data['objectid'];
			}

// OBJECT DHOST
			$dhosts = array();
			$sql = 'SELECT s.dserviceid, s.dhostid, s.ip '.
					' FROM dservices s '.
					' WHERE '.DBcondition('s.dhostid', $objectids);
			$res = DBselect($sql);
			while($dservices = DBfetch($res)){
				$dhosts[$dservices['dhostid']] = $dservices;
			}

// OBJECT DSERVICE
			$dservices = array();
			$sql = 'SELECT s.dserviceid,s.ip,s.type,s.port '.
					' FROM dservices s '.
					' WHERE '.DBcondition('s.dserviceid', $objectids);
			$res = DBselect($sql);
			while($dservice = DBfetch($res)){
				$dservices[$dservice['dserviceid']] = $dservice;
			}

// TABLE
			$table->setHeader(array(
				S_TIME,
				S_IP,
				S_DESCRIPTION,
				S_STATUS
			));

			foreach($dsc_events as $num => $event_data){
				switch($event_data['object']){
					case EVENT_OBJECT_DHOST:
						if(isset($dhosts[$event_data['objectid']])){
							$event_data['object_data'] = $dhosts[$event_data['objectid']];
						}
						else{
							$event_data['object_data']['ip'] = S_UNKNOWN;
						}
						$event_data['description'] = S_HOST;
						break;
					case EVENT_OBJECT_DSERVICE:
						if(isset($dservices[$event_data['objectid']])){
							$event_data['object_data'] = $dservices[$event_data['objectid']];
						}
						else{
							$event_data['object_data']['ip'] = S_UNKNOWN;
							$event_data['object_data']['type'] = S_UNKNOWN;
							$event_data['object_data']['port'] = S_UNKNOWN;
						}

						$event_data['description'] = S_SERVICE.': '.discovery_check_type2str($event_data['object_data']['type']).'; '.
							S_PORT.': '.$event_data['object_data']['port'];
						break;
					default:
						continue;
				}

				if(!isset($event_data['object_data'])) continue;

				$table->addRow(array(
					date('Y.M.d H:i:s',$event_data['clock']),
					$event_data['object_data']['ip'],
					$event_data['description'],
					new CCol(trigger_value2str($event_data['value']), get_trigger_value_style($event_data['value']))
				));
			}
		}
		else{
			$table->setHeader(array(
				S_TIME,
				is_show_all_nodes()?S_NODE:null,
				($_REQUEST['hostid'] == 0)?S_HOST:null,
				S_DESCRIPTION,
				S_STATUS,
				S_SEVERITY,
				S_DURATION,
				($config['event_ack_enable'])?S_ACK:NULL,
				S_ACTIONS
			));
			
			$trigOpt = array(
				'nodeids' => get_current_nodeid(),
				'output' => API_OUTPUT_SHORTEN
			);
			if(($PAGE_HOSTS['selected'] > 0) || empty($PAGE_HOSTS['hostids'])){
				$trigOpt['hostids'] = $PAGE_HOSTS['selected'];
			}
			else if(!empty($PAGE_HOSTS['hostids'])){
				$trigOpt['hostids'] = $PAGE_HOSTS['hostids'];
			}
			else if(($PAGE_GROUPS['selected'] > 0) || empty($PAGE_GROUPS['groupids'])){
				$trigOpt['groupids'] = $PAGE_GROUPS['selected'];
			}
			if(isset($_REQUEST['triggerid']) && ($_REQUEST['triggerid']>0)){
				$trigOpt['triggerids'] = $_REQUEST['triggerid'];
			}
			$triggers = CTrigger::get($trigOpt);
		
			$options = array(
				'nodeids' => get_current_nodeid(),
				'triggerids' => zbx_objectValues($triggers, 'triggerid'),
				'object' => EVENT_OBJECT_TRIGGER,
				'time_from' => $from,
				'time_till' => $till,
				'output' => API_OUTPUT_SHORTEN,
				'sortfield' => 'eventid',
				'sortorder' => ZBX_SORT_DOWN,
				'nopermissions' => 1,
				'limit' => ($config['search_limit']+1)
			);
			if($_REQUEST['hide_unknown']) $options['hide_unknown'] = 1;
			$events = CEvent::get($options);
			
			$paging = getPagingLine($events);
			
			$options = array(
				'nodeids' => get_current_nodeid(),
				'eventids' => zbx_objectValues($events,'eventid'),
				'output' => API_OUTPUT_EXTEND,
				'select_hosts' => API_OUTPUT_EXTEND,
				'select_triggers' => API_OUTPUT_EXTEND,
				'select_items' => API_OUTPUT_EXTEND,
				'nopermissions' => 1
			);
			$events = CEvent::get($options);
			order_result($events, 'eventid', ZBX_SORT_DOWN);

			foreach($events as $enum => $event){
				$trigger = reset($event['triggers']);

				$event['desc'] = expand_trigger_description_by_data($trigger);

				$event += $trigger;

				$event['duration'] = zbx_date2age($event['clock']);
				if($next_event = get_next_event($event,$_REQUEST['hide_unknown'])){
					$event['duration'] = zbx_date2age($event['clock'],$next_event['clock']);
				}

				$event['value_col'] = new CCol(trigger_value2str($event['value']), get_trigger_value_style($event['value']));

				$events[$enum] = $event;
			}

			foreach($events as $enum => $event){
// Host
				$host = array_pop($event['hosts']);

// Trigger
				$trigger = reset($event['triggers']);

// Items
				$items = array();
				foreach($event['items'] as $inum => $item){
					$item['itemid'] = $item['itemid'];
					$item['action'] = str_in_array($item['value_type'],array(ITEM_VALUE_TYPE_FLOAT,ITEM_VALUE_TYPE_UINT64))?'showgraph':'showvalues';
					$item['description'] = item_description($item);
					$items[] = $item;
				}

// Actions
				$actions = get_event_actions_status($event['eventid']);

				if($config['event_ack_enable']){
					if($event['acknowledged'] == 1){
						$ack = new CLink(S_YES,'acknow.php?eventid='.$event['eventid']);
					}
					else{
						$ack = new CLink(S_NO,'acknow.php?eventid='.$event['eventid'],'on');
					}
				}

				$tr_desc = new CSpan($event['desc'],'pointer');
				$tr_desc->addAction('onclick',"create_mon_trigger_menu(event, ".
										" new Array({'triggerid': '".$trigger['triggerid']."', 'lastchange': '".$event['clock']."'}),".
										zbx_jsvalue($items).");");

				$table->addRow(array(
					new CLink(date('Y.M.d H:i:s',$event['clock']),
						'tr_events.php?triggerid='.$event['objectid'].'&eventid='.$event['eventid'],
						'action'
						),
					is_show_all_nodes() ? get_node_name_by_elid($event['objectid']) : null,
					$_REQUEST['hostid'] == 0 ? $host['host'] : null,
					$tr_desc,
					$event['value_col'],
					new CCol(get_severity_description($trigger['priority']), get_severity_style($trigger['priority'],$event['value'])),
					$event['duration'],
					($config['event_ack_enable'])?$ack:NULL,
					$actions
				));
			}

		}

		$table = array($paging, $table, $paging);
		$events_wdgt->addItem($table);

		
		$jsmenu = new CPUMenu(null,170);
		$jsmenu->InsertJavaScript();
	}
	
// NAV BAR
	$timeline = array(
		'period' => $effectiveperiod,
		'starttime' => $starttime,
		'usertime' => $till
	);

	$dom_graph_id = 'scroll_events_id';
	$objData = array(
		'id' => 'timeline_1',
		'loadSBox' => 0,
		'loadImage' => 0,
		'loadScroll' => 1,
		'dynamic' => 0,
		'mainObject' => 1
	);

	zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
	zbx_add_post_js('timeControl.processObjects();');
	
	$events_wdgt->show();
	
	
include_once('include/page_footer.php');
?>
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
require_once('include/config.inc.php');
require_once('include/hosts.inc.php');
require_once('include/events.inc.php');
require_once('include/actions.inc.php');
require_once('include/discovery.inc.php');
require_once('include/html.inc.php');

$page['title'] = "S_LATEST_EVENTS";
$page['file'] = 'events.php';
$page['hist_arg'] = array('groupid','hostid');
$page['scripts'] = array('class.calendar.js','scriptaculous.js?load=effects');

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

		'nav_time'=>		array(T_ZBX_INT, O_OPT,	P_UNSET_EMPTY,	null,	NULL),

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

	$_REQUEST['source'] = get_request('source', CProfile::get('web.events.source', 0));

	check_fields($fields);
//SDI($_REQUEST);
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
		$_REQUEST['nav_time'] = time();
		$_REQUEST['triggerid'] = 0;
		$_REQUEST['hide_unknown'] = 0;
	}

	$_REQUEST['nav_time'] = get_request('nav_time',CProfile::get('web.events.filter.nav_time',time()));
	$_REQUEST['triggerid'] = get_request('triggerid',CProfile::get('web.events.filter.triggerid',0));
	$_REQUEST['hide_unknown'] = get_request('hide_unknown',CProfile::get('web.events.filter.hide_unknown',0));
	$hide_unknown = $_REQUEST['hide_unknown'];

	if(isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst'])){
		CProfile::update('web.events.filter.nav_time',$_REQUEST['nav_time'], PROFILE_TYPE_INT);
		CProfile::update('web.events.filter.triggerid',$_REQUEST['triggerid'], PROFILE_TYPE_ID);
		CProfile::update('web.events.filter.hide_unknown',$hide_unknown, PROFILE_TYPE_INT);
	}
// --------------

	validate_sort_and_sortorder('clock',ZBX_SORT_DOWN);

	$source = get_request('source', EVENT_SOURCE_TRIGGERS);
	CProfile::update('web.events.source',$source, PROFILE_TYPE_INT);

?>
<?php
	$source = get_request('source', EVENT_SOURCE_TRIGGERS);

	$r_form = new CForm();
	$r_form->setMethod('get');
	$r_form->setAttribute('name','events_menu');

	$r_form->addVar('fullscreen',$_REQUEST['fullscreen']);
//	$r_form->addVar('nav_time',$_REQUEST['nav_time']);

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

		$options = array('allow_all_hosts','monitored_hosts','with_items');
		if(!$ZBX_WITH_ALL_NODES)	array_push($options,'only_current_node');

//SDI($_REQUEST['groupid'].' : '.$_REQUEST['hostid']);
		$params = array();
		foreach($options as $option) $params[$option] = 1;

		$PAGE_GROUPS = get_viewed_groups(PERM_READ_ONLY, $params);
		$PAGE_HOSTS = get_viewed_hosts(PERM_READ_ONLY, $PAGE_GROUPS['selected'], $params);
//SDI($_REQUEST['groupid'].' : '.$_REQUEST['hostid']);
		validate_group_with_host($PAGE_GROUPS,$PAGE_HOSTS);
//SDI($_REQUEST['groupid'].' : '.$_REQUEST['hostid']);

	    $available_groups= $PAGE_GROUPS['groupids'];
		$available_hosts = $PAGE_HOSTS['hostids'];

		if(isset($_REQUEST['triggerid']) && ($_REQUEST['triggerid']>0)){
			$triggers = CTrigger::get(array( 'triggerids' => $_REQUEST['triggerid'] ));
			if(empty($triggers)){
				unset($_REQUEST['triggerid']);
			}
		}

		$cmbGroups = new CComboBox('groupid',$PAGE_GROUPS['selected'],'javascript: submit();');
		$cmbHosts = new CComboBox('hostid',$PAGE_HOSTS['selected'],'javascript: submit();');

		foreach($PAGE_GROUPS['groups'] as $groupid => $name){
			$cmbGroups->addItem($groupid, get_node_name_by_elid($groupid, null, ': ').$name);
		}
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

// Header
	$events_wdgt = new CWidget();

	$text = array(S_HISTORY_OF_EVENTS_BIG.SPACE.S_ON.SPACE,date(S_DATE_FORMAT_YMDHMS,time()));

	$url = '?fullscreen='.($_REQUEST['fullscreen']?'0':'1');

	$fs_icon = new CDiv(SPACE,'fullscreen');
	$fs_icon->setAttribute('title',$_REQUEST['fullscreen']?S_NORMAL.' '.S_VIEW:S_FULLSCREEN);
	$fs_icon->addAction('onclick',new CJSscript("javascript: document.location = '".$url."';"));

	$events_wdgt->addPageHeader($text,$fs_icon);

	$numrows = new CDiv();
	$numrows->setAttribute('name', 'numrows');

	$events_wdgt->addHeader(S_EVENTS_BIG,$r_form);
	$events_wdgt->addHeader($numrows);
//-------------

	if($source == EVENT_SOURCE_DISCOVERY){
		$options = array(
						'source' => EVENT_SOURCE_DISCOVERY,
						'time_from' => $_REQUEST['nav_time'],
						'time_till' => null,
						'extendoutput' => 1,
						'select_hosts' => 1,
						'select_triggers' => 1,
						'select_items' => 1,
						'sortfield' => 'clock',
						'sortorder' => getPageSortOrder(),
						'limit' => ($config['search_limit']+1)
					);

		$dsc_events = CEvent::get($options);

// PAGING UPPER && SORTING
		order_page_result($dsc_events, 'clock', ZBX_SORT_DOWN);
		$paging = getPagingLine($dsc_events);
//------

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
		$table = new CTableInfo(S_NO_EVENTS_FOUND);
		$table->setHeader(array(
				make_sorting_header(S_TIME, 'clock'),
				S_IP,
				S_DESCRIPTION,
				S_STATUS));

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

			$value = new CCol(trigger_value2str($event_data['value']), get_trigger_value_style($event_data['value']));

			$table->addRow(array(
				date('Y.M.d H:i:s',$event_data['clock']),
				$event_data['object_data']['ip'],
				$event_data['description'],
				$value));
		}

		$table = array($paging, $table, $paging);
	}
	else{

		$table = new CTableInfo(S_NO_EVENTS_FOUND);
		$table->setHeader(array(
				make_sorting_header(S_TIME,'clock'),
				is_show_all_nodes()?S_NODE:null,
				($_REQUEST['hostid'] == 0)?S_HOST:null,
				S_DESCRIPTION,
				S_STATUS,
				S_SEVERITY,
				S_DURATION,
				($config['event_ack_enable'])?S_ACK:NULL,
				S_ACTIONS
			));

		$options = array(
			'nodeids' => get_current_nodeid(),
			'object' => EVENT_OBJECT_TRIGGER,
			'time_from' => $_REQUEST['nav_time'],
			'time_till' => null,
			'extendoutput' => 1,
			'sortfield' => 'clock',
			'sortorder' => getPageSortOrder(),
			'nopermissions' => 1,
			'limit' => ($config['search_limit']+1)
		);

		$trigOpt = array(
			'nodeids' => get_current_nodeid(),
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

		if(!empty($trigOpt)){
			$triggers = CTrigger::get($trigOpt);
			$triggerids = zbx_objectValues($triggers, 'triggerid');
			$options['triggerids'] = $triggerids;
		}

		if($hide_unknown) $options['hide_unknown'] = 1;

		$events = CEvent::get($options);

// sorting & paging
		order_page_result($events, 'eventid');
		$paging = getPagingLine($events);
//------

		$options = array(
			'nodeids' => get_current_nodeid(),
			'eventids' => zbx_objectValues($events,'eventid'),
			'extendoutput' => 1,
			'select_hosts' => 1,
			'select_triggers' => 1,
			'select_items' => 1,
			'nopermissions' => 1
		);
		$events = CEvent::get($options);

// sorting & paging
		order_page_result($events, 'eventid');
//------

		foreach($events as $enum => $event){
			$eventid = $event['eventid'];

			$trigger = reset($event['triggers']);

			$event['desc'] = expand_trigger_description_by_data($trigger);

			$event += $trigger;

			$event['duration'] = zbx_date2age($event['clock']);
			if($next_event = get_next_event($event,$hide_unknown)){
				$event['duration'] = zbx_date2age($event['clock'],$next_event['clock']);
			}

			$event['value_col'] = new CCol(trigger_value2str($event['value']), get_trigger_value_style($event['value']));

			$events[$enum] = $event;
		}

		foreach($events as $enum => $event){
			$eventid = $event['eventid'];
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

// PAGING FOOTER
		$table = array($paging, $table, $paging);
//---------
	}

	$events_wdgt->addItem($table);

/************************* FILTER **************************/
/***********************************************************/

	$filterForm = new CFormTable(null, null, 'get');//,'events.php?filter_set=1','POST',null,'sform');
	$filterForm->setAttribute('name','zbx_filter');
	$filterForm->setAttribute('id','zbx_filter');
//	$filterForm->setMethod('get');

	$filterForm->addVar('nav_time',$_REQUEST['nav_time']);

	$script = new CJSscript("javascript: if(CLNDR['nav_time'].clndr.setSDateFromOuterObj()){".
							"$('nav_time').value = parseInt(CLNDR['nav_time'].clndr.sdt.getTime()/1000); }"
							);
	$filterForm->addAction('onsubmit',$script);

	$clndr_icon = new CImg('images/general/bar/cal.gif','calendar', 16, 12, 'pointer');
	$clndr_icon->addAction('onclick',"javascript: var pos = getPosition(this); pos.top+=14; pos.left-=174; CLNDR['nav_time'].clndr.clndrshow(pos.top,pos.left);");
	$clndr_icon->setAttribute('style','vertical-align: middle;');

	$nav_clndr =  array(
					new CNumericBox('nav_day',(($_REQUEST['nav_time']>0)?date('d',$_REQUEST['nav_time']):''),2),
					new CNumericBox('nav_month',(($_REQUEST['nav_time']>0)?date('m',$_REQUEST['nav_time']):''),2),
					new CNumericBox('nav_year',(($_REQUEST['nav_time']>0)?date('Y',$_REQUEST['nav_time']):''),4),
					SPACE,
					new CNumericBox('nav_hour',(($_REQUEST['nav_time']>0)?date('H',$_REQUEST['nav_time']):''),2),
					':',
					new CNumericBox('nav_minute',(($_REQUEST['nav_time']>0)?date('i',$_REQUEST['nav_time']):''),2),

					$clndr_icon
				);
	zbx_add_post_js('create_calendar(null,["nav_day","nav_month","nav_year","nav_hour","nav_minute"],"nav_time");');

	$filterForm->addRow(S_EVENTS_SINCE,$nav_clndr);

	if(EVENT_SOURCE_TRIGGERS == $source){

		$filterForm->addVar('triggerid', get_request('triggerid'));

		if(isset($_REQUEST['triggerid']) && ($_REQUEST['triggerid']>0)){
			$trigger = expand_trigger_description($_REQUEST['triggerid']);
		}
		else{
			$trigger = "";
		}

		$row = new CRow(array(
						new CCol(S_TRIGGER,'form_row_l'),
						new CCol(array(
									new CTextBox("trigger",$trigger,96,'yes'),
									new CButton("btn1",S_SELECT,"return PopUp('popup.php?"."dstfrm=".$filterForm->GetName()."&dstfld1=triggerid&dstfld2=trigger"."&srctbl=triggers&srcfld1=triggerid&srcfld2=description&real_hosts=1');",'T')
								),'form_row_r')
							));

		$filterForm->addRow($row);

		$filterForm->addVar('hide_unknown',$hide_unknown);

		$unkcbx = new CCheckBox('hide_unk',$hide_unknown,null,'1');
		$unkcbx->setAction('javascript: create_var("'.$filterForm->GetName().'", "hide_unknown", (this.checked?1:0), 0); ');

		$filterForm->addRow(S_HIDE_UNKNOWN,$unkcbx);
	}

	$reset = new CButton("filter_rst",S_RESET);
	$reset->setType('button');
	$reset->setAction('javascript: var uri = new Curl(location.href); uri.setArgument("filter_rst",1); location.href = uri.getUrl();');

	$filterForm->addItemToBottomRow(new CButton('filter_set',S_FILTER));
	$filterForm->addItemToBottomRow($reset);
//-------

	$events_wdgt->addFlicker($filterForm, CProfile::get('web.events.filter.state',0));
	$events_wdgt->show();

	$jsmenu = new CPUMenu(null,170);
	$jsmenu->InsertJavaScript();
?>
<?php

include_once('include/page_footer.php');

?>

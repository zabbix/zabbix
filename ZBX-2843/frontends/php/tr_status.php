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
	require_once('include/acknow.inc.php');
	require_once('include/triggers.inc.php');
	require_once('include/events.inc.php');
	require_once('include/scripts.inc.php');

	$page['file'] = 'tr_status.php';
	$page['title'] = 'S_STATUS_OF_TRIGGERS';
	$page['hist_arg'] = array('groupid', 'hostid');
	$page['scripts'] = array('class.cswitcher.js');

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);

?>
<?php
if($page['type'] == PAGE_TYPE_HTML){
	define('ZBX_PAGE_DO_REFRESH', 1);
}

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'groupid'=>				array(T_ZBX_INT, O_OPT,	 	P_SYS,	DB_ID, 					null),
		'hostid'=>				array(T_ZBX_INT, O_OPT,	 	P_SYS,	DB_ID, 					null),

		'fullscreen'=>			array(T_ZBX_INT, O_OPT,		P_SYS,	IN('0,1'),				null),
		'btnSelect'=>			array(T_ZBX_STR, O_OPT,  	null,  	null, 					null),
// filter
		'filter_rst'=>			array(T_ZBX_STR, O_OPT,		P_ACT,	null,	NULL),
		'filter_set'=>			array(T_ZBX_STR, O_OPT,		P_ACT,	null,	NULL),
		'show_triggers'=>		array(T_ZBX_INT, O_OPT,  	null, 	null, 	null),
		'show_events'=>			array(T_ZBX_INT, O_OPT,		P_SYS,	null,	null),
		'ack_status'=>			array(T_ZBX_INT, O_OPT,		P_SYS,	null,	null),
		'show_severity'=>		array(T_ZBX_INT, O_OPT,		P_SYS,	null,	null),
		'show_details'=>		array(T_ZBX_INT, O_OPT,  	null,	null, 	null),
		'status_change_days'=>	array(T_ZBX_INT, O_OPT,  	null,	null, 	null),
		'status_change'=>		array(T_ZBX_INT, O_OPT,  	null,	null, 	null),
		'txt_select'=>			array(T_ZBX_STR, O_OPT,  	null,	null, 	null),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
	);

	check_fields($fields);

	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.tr_status.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		include_once('include/page_footer.php');
		exit();
	}
//--------

	$config = select_config();

	$options = array(
		'groups' => array(
			'monitored_hosts' => 1,
			'with_monitored_triggers' => 1,
		),
		'hosts' => array(
			'monitored_hosts' => 1,
			'with_monitored_triggers' => 1,
		),
		'hostid' => get_request('hostid', null),
		'groupid' => get_request('groupid', null),
	);
	$pageFilter = new CPageFilter($options);
	$_REQUEST['groupid'] = $pageFilter->groupid;
	$_REQUEST['hostid'] = $pageFilter->hostid;


/* FILTER */
	if(isset($_REQUEST['filter_rst'])){
		$_REQUEST['show_details'] =	0;
		$_REQUEST['show_triggers'] = TRIGGERS_OPTION_ONLYTRUE;
		$_REQUEST['show_events'] = EVENTS_OPTION_NOEVENT;
		$_REQUEST['ack_status'] = ZBX_ACK_STS_ANY;
		$_REQUEST['show_severity'] = -1;
		$_REQUEST['txt_select'] = '';
		$_REQUEST['status_change'] = 0;
		$_REQUEST['status_change_days'] = 14;
	}
	else{
		if(isset($_REQUEST['filter_set'])){
			$_REQUEST['show_details'] = get_request('show_details', 0);
			$_REQUEST['status_change'] = get_request('status_change', 0);
			$_REQUEST['show_triggers'] = get_request('show_triggers', TRIGGERS_OPTION_ONLYTRUE);
		}
		else{
			$_REQUEST['show_details'] = get_request('show_details',	CProfile::get('web.tr_status.filter.show_details', 0));
			$_REQUEST['status_change'] = get_request('status_change', CProfile::get('web.tr_status.filter.status_change', 0));
			$_REQUEST['show_triggers'] = TRIGGERS_OPTION_ONLYTRUE;
		}
		$_REQUEST['show_events'] = get_request('show_events', CProfile::get('web.tr_status.filter.show_events', EVENTS_OPTION_NOEVENT));
		$_REQUEST['ack_status'] = get_request('ack_status', CProfile::get('web.tr_status.filter.ack_status', ZBX_ACK_STS_ANY));
		$_REQUEST['show_severity'] = get_request('show_severity', CProfile::get('web.tr_status.filter.show_severity', -1));
		$_REQUEST['status_change_days'] = get_request('status_change_days', CProfile::get('web.tr_status.filter.status_change_days', 14));
		$_REQUEST['txt_select'] = get_request('txt_select', CProfile::get('web.tr_status.filter.txt_select', ''));

		if(EVENT_ACK_DISABLED == $config['event_ack_enable']){
			if(!str_in_array($_REQUEST['show_events'],array(EVENTS_OPTION_NOEVENT,EVENTS_OPTION_ALL))){
				$_REQUEST['show_events'] = EVENTS_OPTION_NOEVENT;
			}
			$_REQUEST['ack_status'] = ZBX_ACK_STS_ANY;
		}
	}

	if(get_request('show_events') != CProfile::get('web.tr_status.filter.show_events')){
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}
//--

	if(isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst'])){
		CProfile::update('web.tr_status.filter.show_details', $_REQUEST['show_details'], PROFILE_TYPE_INT);
		CProfile::update('web.tr_status.filter.show_events', $_REQUEST['show_events'], PROFILE_TYPE_INT);
		CProfile::update('web.tr_status.filter.ack_status', $_REQUEST['ack_status'], PROFILE_TYPE_INT);
		CProfile::update('web.tr_status.filter.show_severity', $_REQUEST['show_severity'], PROFILE_TYPE_INT);
		CProfile::update('web.tr_status.filter.txt_select', $_REQUEST['txt_select'], PROFILE_TYPE_STR);
		CProfile::update('web.tr_status.filter.status_change', $_REQUEST['status_change'], PROFILE_TYPE_INT);
		CProfile::update('web.tr_status.filter.status_change_days', $_REQUEST['status_change_days'], PROFILE_TYPE_INT);
	}

	$show_triggers = $_REQUEST['show_triggers'];
	$show_events = $_REQUEST['show_events'];
	$show_severity = $_REQUEST['show_severity'];
	$ack_status = $_REQUEST['ack_status'];
// --------------
	validate_sort_and_sortorder('lastchange', ZBX_SORT_DOWN);

	$mute = CProfile::get('web.tr_status.mute', 0);
	if(isset($audio) && !$mute){
		play_sound($audio);
	}
?>
<?php
	$trigg_wdgt = new CWidget();

	$r_form = new CForm(null, 'get');
	$r_form->addItem(array(S_GROUP . SPACE, $pageFilter->getGroupsCB(true)));
	$r_form->addItem(array(SPACE . S_HOST . SPACE, $pageFilter->getHostsCB(true)));
	$r_form->addVar('fullscreen', $_REQUEST['fullscreen']);

	$fs_icon = get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']));
	$trigg_wdgt->addPageHeader(S_STATUS_OF_TRIGGERS_BIG . ' [' . date(S_DATE_FORMAT_YMDHMS) . ']', array($fs_icon));

	$numrows = new CDiv();
	$numrows->setAttribute('name', 'numrows');
	$trigg_wdgt->addHeader(S_TRIGGERS_BIG, $r_form);
	$trigg_wdgt->addHeader($numrows);

/************************* FILTER **************************/
/***********************************************************/

	$filterForm = new CFormTable(null, null, 'get');//,'tr_status.php?filter_set=1','POST',null,'sform');
	$filterForm->setAttribute('name', 'zbx_filter');
	$filterForm->setAttribute('id', 'zbx_filter');

	$filterForm->addVar('fullscreen', $_REQUEST['fullscreen']);
	$filterForm->addVar('groupid', $_REQUEST['groupid']);
	$filterForm->addVar('hostid', $_REQUEST['hostid']);

	$tr_select = new CComboBox('show_triggers', $show_triggers);
	$tr_select->addItem(TRIGGERS_OPTION_ALL, S_ANY);
	$tr_select->additem(TRIGGERS_OPTION_ONLYTRUE, S_PROBLEM);
	$filterForm->addRow(S_TRIGGERS_STATUS, $tr_select);

	if($config['event_ack_enable']){
		$cb_ack_status = new CComboBox('ack_status', $ack_status);
		$cb_ack_status->addItem(ZBX_ACK_STS_ANY, S_ANY);
		$cb_ack_status->additem(ZBX_ACK_STS_WITH_UNACK, S_WITH_UNACKNOWLEDGED_EVENTS);
		$cb_ack_status->additem(ZBX_ACK_STS_WITH_LAST_UNACK, S_WITH_LAST_EVENT_UNACKNOWLEDGED);
		$filterForm->addRow(S_ACKNOWLEDGE_STATUS, $cb_ack_status);
	}

	$ev_select = new CComboBox('show_events', $_REQUEST['show_events']);
	$ev_select->addItem(EVENTS_OPTION_NOEVENT, S_HIDE_ALL);
	$ev_select->addItem(EVENTS_OPTION_ALL, S_SHOW_ALL.' ('.$config['event_expire'].' '.(($config['event_expire']>1)?S_DAYS:S_DAY).')');
	if($config['event_ack_enable']){
		$ev_select->addItem(EVENTS_OPTION_NOT_ACK, S_SHOW_UNACKNOWLEDGED.' ('.$config['event_expire'].' '.(($config['event_expire']>1)?S_DAYS:S_DAY).')');
	}
	$filterForm->addRow(S_EVENTS, $ev_select);

	$severity_select = new CComboBox('show_severity', $show_severity);
	$cb_items = array(
		-1 => S_ALL_S,
		TRIGGER_SEVERITY_NOT_CLASSIFIED => S_NOT_CLASSIFIED,
		TRIGGER_SEVERITY_INFORMATION => S_INFORMATION,
		TRIGGER_SEVERITY_WARNING => S_WARNING,
		TRIGGER_SEVERITY_AVERAGE => S_AVERAGE,
		TRIGGER_SEVERITY_HIGH => S_HIGH,
		TRIGGER_SEVERITY_DISASTER => S_DISASTER,
	);
	$severity_select->addItems($cb_items);
	$filterForm->addRow(S_MIN_SEVERITY, $severity_select);

	$action = 'javascript: this.checked ? $("status_change_days").enable() : $("status_change_days").disable()';
	$sts_change_days_cb = new CNumericBox('status_change_days', $_REQUEST['status_change_days'], 4);
	if(!$_REQUEST['status_change']) $sts_change_days_cb->setAttribute('disabled', 'disabled');
    $sts_change_days_cb->addStyle('vertical-align: middle;');

	$cbd = new CCheckBox('status_change', $_REQUEST['status_change'], $action, 1);
	$cbd->addStyle('vertical-align: middle;');

	$spand = new CSpan(S_DAYS_SMALL);
	$spand->addStyle('vertical-align: middle;');
	$filterForm->addRow(S_AGE_LESS_THAN, array(
		$cbd,
		$sts_change_days_cb,
		$spand,
	));

	$filterForm->addRow(S_SHOW_DETAILS, new CCheckBox('show_details', $_REQUEST['show_details'], null, 1));

	$filterForm->addRow(S_FILTER_BY_NAME, new CTextBox('txt_select', $_REQUEST['txt_select'], 40));

	$filterForm->addItemToBottomRow(new CButton('filter_set', S_FILTER));
	$filterForm->addItemToBottomRow(new CButton('filter_rst', S_RESET));

	$trigg_wdgt->addFlicker($filterForm, CProfile::get('web.tr_status.filter.state', 0));
/*************** FILTER END ******************/

  	if($_REQUEST['fullscreen']){
		$triggerInfo = new CTriggersInfo($_REQUEST['groupid'], $_REQUEST['hostid']);
		$triggerInfo->HideHeader();
		$triggerInfo->show();
	}

	$m_form = new CForm('acknow.php');
	$m_form->setName('tr_status');
	$m_form->addVar('backurl', $page['file']);

	$admin_links = (($USER_DETAILS['type'] == USER_TYPE_ZABBIX_ADMIN) || ($USER_DETAILS['type'] == USER_TYPE_SUPER_ADMIN));
	$show_event_col = ($config['event_ack_enable'] && ($_REQUEST['show_events'] != EVENTS_OPTION_NOEVENT));

	$table = new CTableInfo();
	$switcherName = 'trigger_switchers';

	$header_cb = ($show_event_col) ? new CCheckBox('all_events', false, "checkAll('".$m_form->GetName()."','all_events','events');")
		: new CCheckBox('all_triggers', false, "checkAll('".$m_form->GetName()."','all_triggers', 'triggers');");

	if($show_events != EVENTS_OPTION_NOEVENT){
		$whow_hide_all = new CDiv(SPACE, 'filterclosed');
		$whow_hide_all->setAttribute('id', $switcherName);
	}
	else{
		$whow_hide_all = NULL;
	}

	$table->setHeader(array(
		$whow_hide_all,
		$config['event_ack_enable'] ? $header_cb : null,
		make_sorting_header(S_SEVERITY, 'priority'),
		S_STATUS,
		make_sorting_header(S_LAST_CHANGE, 'lastchange'),
		S_AGE,
		$show_event_col ? S_DURATION : NULL,
		$config['event_ack_enable'] ? S_ACKNOWLEDGED : NULL,
		is_show_all_nodes() ? S_NODE : null,
		S_HOST,
		make_sorting_header(S_NAME, 'description'),
		S_COMMENTS
	));


	$sortfield = getPageSortField('description');
	$sortorder = getPageSortOrder();
	$options = array(
		'nodeids' => get_current_nodeid(),
		'filter' => array(),
		'monitored' => 1,
		'output' => API_OUTPUT_EXTEND,
		'skipDependent' => 1,
		'sortfield' => $sortfield,
		'sortorder' => $sortorder,
		'limit' => ($config['search_limit']+1)
	);

// Filtering
	if($pageFilter->hostsSelected){
		if($pageFilter->hostid > 0)
			$options['hostids'] = $pageFilter->hostid;
		else if($pageFilter->groupid > 0)
			$options['groupids'] = $pageFilter->groupid;
	}
	else{
		$options['hostids'] = array();
	}


	if(!zbx_empty($_REQUEST['txt_select'])){
		$options['search'] = array('description' => $_REQUEST['txt_select']);
	}
	if($show_triggers == TRIGGERS_OPTION_ONLYTRUE){
		$options['only_true'] = 1;
	}
	if($ack_status == ZBX_ACK_STS_WITH_UNACK){
		$options['withUnacknowledgedEvents'] = 1;
	}
	if($ack_status == ZBX_ACK_STS_WITH_LAST_UNACK){
		$options['withLastEventUnacknowledged'] = 1;
	}
	if($show_severity > -1){
		$options['min_severity'] = $show_severity;
	}
	if($_REQUEST['status_change']){
		$options['lastChangeSince'] = time() - ($_REQUEST['status_change_days'] * 86400);
	}

	$triggers = CTrigger::get($options);

// sorting && paging
	order_result($triggers, $sortfield, $sortorder);
	$paging = getPagingLine($triggers);

	$options = array(
		'nodeids' => get_current_nodeid(),
		'triggerids' => zbx_objectValues($triggers, 'triggerid'),
		'output' => API_OUTPUT_EXTEND,
		'select_hosts' => array('hostid', 'host', 'maintenance_status', 'maintenance_type', 'maintenanceid'),
		'select_items' => API_OUTPUT_EXTEND,
		'select_dependencies' => API_OUTPUT_EXTEND
	);
	$triggers = CTrigger::get($options);
	$triggers = zbx_toHash($triggers, 'triggerid');

	order_result($triggers, $sortfield, $sortorder);
//---------

	if($config['event_ack_enable']){
		foreach($triggers as $tnum => $trigger){
			$options = array(
				'countOutput' => 1,
				'triggerids' => $trigger['triggerid'],
				'object' => EVENT_OBJECT_TRIGGER,
				'acknowledged' => 0,
				'value' => TRIGGER_VALUE_TRUE,
				'nopermissions' => 1
			);
			$triggers[$tnum]['event_count'] = CEvent::get($options);
		}
	}


	$tr_hostids = array();
	foreach($triggers as $tnum => $trigger){
		$triggers[$tnum]['events'] = array();

		//getting all host ids and names
		foreach($trigger['hosts'] as $tr_hosts){
			$tr_hostids[$tr_hosts['hostid']] = $tr_hosts['hostid'];
		}
	}


	$scripts_by_hosts = Cscript::getScriptsByHosts($tr_hostids);

	if($show_events != EVENTS_OPTION_NOEVENT){
		$ev_options = array(
			'nodeids' => get_current_nodeid(),
			'triggerids' => zbx_objectValues($triggers, 'triggerid'),
			'nopermissions' => true,
			'output' => API_OUTPUT_EXTEND,
			'time_from' => time() - ($config['event_expire'] * 86400),
			'time_till' => time()
		);

		switch($show_events){
			case EVENTS_OPTION_ALL:
			break;
			case EVENTS_OPTION_NOT_ACK:
				$ev_options['acknowledged'] = 0;
				$ev_options['value'] = TRIGGER_VALUE_TRUE;
			break;
		}
		$events = CEvent::get($ev_options);

		$sortFields = array(
			array('field' => 'clock', 'order' => ZBX_SORT_DOWN),
			array('field' => 'eventid', 'order' => ZBX_SORT_DOWN)
		);
		ArraySorter::sort($events, $sortFields);

		foreach($events as $enum => $event){
			$triggers[$event['objectid']]['events'][] = $event;
		}
	}


	foreach($triggers as $tnum => $trigger){

		$trigger['desc'] = $description = expand_trigger_description($trigger['triggerid']);

		$items = array();

		$used_hosts = array();
		foreach($trigger['hosts'] as $th){
			$used_hosts[$th['hostid']] = $th['host'];
		}
		$used_host_count = count($used_hosts);

		foreach($trigger['items'] as $inum => $item){
			$item_description = item_description($item);

			//if we have items from different hosts, we must prefix a host name
			if ($used_host_count > 1) {
				$item_description = $used_hosts[$item['hostid']].':'.$item_description;
			}

			$items[$inum]['itemid'] = $item['itemid'];
			$items[$inum]['value_type'] = $item['value_type']; //ZBX-3059: So it would be possible to show different caption for history for chars and numbers (KB)
			$items[$inum]['action'] = str_in_array($item['value_type'], array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64)) ? 'showgraph' : 'showvalues';
			$items[$inum]['description'] = htmlspecialchars($item_description);
		}
		$trigger['items'] = $items;


//----

		$description = new CSpan($description, 'link_menu');

// trigger description js menu {{{
		$hosts = reset($trigger['hosts']);

		$menu_trigger_conf = 'null';
		if($admin_links){
			$menu_trigger_conf = "['".S_CONFIGURATION_OF_TRIGGERS."',\"javascript:
				redirect('triggers.php?form=update&triggerid=".$trigger['triggerid'].'&switch_node='.id2nodeid($trigger['triggerid'])."')\",
				null, {'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}]";
		}
		$menu_trigger_url = 'null';
		if (!zbx_empty($trigger['url'])) {
			// double zbx_jsvalue is required to prevent XSS attacks
			$menu_trigger_url = "['".S_URL."',\"javascript: window.location.href=".zbx_jsvalue(zbx_jsvalue(resolveTriggerUrl($trigger), null, false))."\",
				null, {'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}]";
		}
		$description->addAction('onclick',
			"javascript: create_mon_trigger_menu(event, new Array({'triggerid': '".$trigger['triggerid'].
				"', 'lastchange': '".$trigger['lastchange']."'}, ".$menu_trigger_conf.", ".$menu_trigger_url."),".
			zbx_jsvalue($items, true).");"
		);


// }}} trigger description js menu

		if($_REQUEST['show_details']){
			$font = new CTag('font', 'yes');
			$font->setAttribute('color', '#000');
			$font->setAttribute('size', '-2');
			$font->addItem(explode_exp($trigger['expression'], true, true));
			$description = array($description, BR(), $font);
		}

// DEPENDENCIES {{{
		if(!empty($trigger['dependencies'])){
			$dep_table = new CTableInfo();
			$dep_table->setAttribute('style', 'width: 200px;');
			$dep_table->addRow(bold(S_DEPENDS_ON.':'));

			foreach($trigger['dependencies'] as $dep){
				$dep_table->addRow(' - '.expand_trigger_description($dep['triggerid']));
			}

			$img = new Cimg('images/general/down_icon.png', 'DEP_UP');
			$img->setAttribute('style', 'vertical-align: middle; border: 0px;');
			$img->setHint($dep_table);

			$description = array($img, SPACE, $description);
		}

		$dependency = false;
		$dep_table = new CTableInfo();
		$dep_table->setAttribute('style', 'width: 200px;');
		$dep_table->addRow(bold(S_DEPENDENT.':'));

		$sql_dep = 'SELECT * FROM trigger_depends WHERE triggerid_up='.$trigger['triggerid'];
		$dep_res = DBselect($sql_dep);
		while($dep_row = DBfetch($dep_res)){
			$dep_table->addRow(SPACE.'-'.SPACE.expand_trigger_description($dep_row['triggerid_down']));
			$dependency = true;
		}

		if($dependency){
			$img = new Cimg('images/general/up_icon.png','DEP_UP');
			$img->setAttribute('style','vertical-align: middle; border: 0px;');
			$img->setHint($dep_table);

			$description = array($img,SPACE,$description);
		}
		unset($img, $dep_table, $dependency);
// }}} DEPENDENCIES

		$tr_desc = new CSpan($description);


// host JS menu {{{
		$hosts_list = array();
		foreach($trigger['hosts'] as $num => $trigger_host){
			$menus = '';

			$host_nodeid = id2nodeid($trigger_host['hostid']);
			if(isset($scripts_by_hosts[$trigger_host['hostid']])){
				foreach($scripts_by_hosts[$trigger_host['hostid']] as $id => $script){
					$script_nodeid = id2nodeid($script['scriptid']);
					if( (bccomp($host_nodeid, $script_nodeid ) == 0))
						$menus.= "['".$script['name']."',\"javascript: openWinCentered('scripts_exec.php?execute=1&hostid=".$trigger_host['hostid']."&scriptid=".$script['scriptid']."','".S_TOOLS."',760,540,'titlebar=no, resizable=yes, scrollbars=yes, dialog=no');\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";
				}
			}
			if(!empty($scripts_by_hosts)){
				$menus = "[".zbx_jsvalue(S_TOOLS).",null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],".$menus;
			}

			$menus.= "[".zbx_jsvalue(S_LINKS).",null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],";
			$menus.= "['".S_LATEST_DATA."',\"javascript: redirect('latest.php?hostid=".$trigger_host['hostid']."')\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";

			$menus = rtrim($menus,',');
			$menus = 'show_popup_menu(event,['.$menus.'],180);';



			$maint_span = null;
			if($trigger_host['maintenance_status']){
				$text = $trigger_host['maintenance_type'] ? S_NO_DATA_MAINTENANCE : S_NORMAL_MAINTENANCE;
				$text = ' ['.$text.']';
				$maint_span = new CSpan($text, 'orange pointer');

				$maintenanceOptions = array(
					'maintenanceids' => $trigger_host['maintenanceid'],
					'output' => API_OUTPUT_EXTEND
				);
				$maintenances = CMaintenance::get($maintenanceOptions);
				$maintenance = reset($maintenances);

				$maint_hint = new CSpan($maintenance['name'].($maintenance['description']=='' ? '' : ': '.$maintenance['description']));

				$maint_span->setHint($maint_hint);
			}

			$hosts_span = new CSpan($trigger_host['host'], 'link_menu');
			$hosts_span->setAttribute('onclick','javascript: '.$menus);
			$hosts_list[] = $hosts_span;
			$hosts_list[] = $maint_span;
			$hosts_list[] = ', ';
		}

		array_pop($hosts_list);
		$host = new CCol($hosts_list);
		$host->addStyle('white-space: normal;');
// }}} host JS menu



		$status = new CSpan(trigger_value2str($trigger['value']), get_trigger_value_style($trigger['value']));
		if((time() - $trigger['lastchange']) < TRIGGER_BLINK_PERIOD){
			$status->setAttribute('name', 'blink');
		}
		$lastchange = new CLink(zbx_date2str(S_DATE_FORMAT_YMDHMS, $trigger['lastchange']), 'events.php?triggerid='.$trigger['triggerid']);
		//.'&stime='.date('YmdHis', $trigger['lastchange']

		if($config['event_ack_enable']){
			if($trigger['event_count']){
				$to_ack = new CCol(array(new CLink(S_ACKNOWLEDGE, 'acknow.php?triggers[]='.$trigger['triggerid'].'&backurl='.$page['file'], 'on'), ' ('.$trigger['event_count'].')'));
			}
			else{
				$to_ack = new CCol(S_ACKNOWLEDGED, 'off');
			}
		}
		else{
			$to_ack = null;
		}


		if(($show_events != EVENTS_OPTION_NOEVENT) && !empty($trigger['events'])){
			$open_close = new CDiv(SPACE, 'filterclosed');
			$open_close->setAttribute('data-switcherid', $trigger['triggerid']);
		}
		else if($show_events == EVENTS_OPTION_NOEVENT){
			$open_close = null;
		}
		else{
			$open_close = SPACE;
		}


		$severity_col = new CCol(get_severity_description($trigger['priority']), get_severity_style($trigger['priority'], $trigger['value']));
		if($show_event_col) $severity_col->setColSpan(2);

		$table->addRow(array(
			$open_close,
			$config['event_ack_enable'] ?
				($show_event_col ? null : new CCheckBox('triggers['.$trigger['triggerid'].']', 'no', null, $trigger['triggerid'])) : null,
			$severity_col,
			$status,
			$lastchange,
			zbx_date2age($trigger['lastchange']),
			$show_event_col ? SPACE : NULL,
			$to_ack,
			get_node_name_by_elid($trigger['triggerid']),
			$host,
			$tr_desc,
			new CLink(zbx_empty($trigger['comments']) ? S_ADD : S_SHOW, 'tr_comments.php?triggerid='.$trigger['triggerid'])
		), 'even_row');


		if($show_events != EVENTS_OPTION_NOEVENT){
			$i = 1;

			foreach($trigger['events'] as $enum => $row_event){
				$i++;

				$status = new CSpan(trigger_value2str($row_event['value']), get_trigger_value_style($row_event['value']));

				if($config['event_ack_enable']){
					if($row_event['value'] == TRIGGER_VALUE_TRUE){
						if($row_event['acknowledged'] == 1){
							$acks_cnt = DBfetch(DBselect('SELECT COUNT(*) as cnt FROM acknowledges WHERE eventid='.$row_event['eventid']));
							$ack = array(new CSpan(S_YES, 'off'),SPACE.'('.$acks_cnt['cnt'].SPACE,
								new CLink(S_SHOW,'acknow.php?eventid='.$row_event['eventid'].'&backurl='.$page['file']),')');
						}
						else{
							$ack = new CLink(S_NOT_ACKNOWLEDGED, 'acknow.php?eventid='.$row_event['eventid'].'&backurl='.$page['file'], 'on');
						}
					}
					else{
						$ack = SPACE;
					}
				}

				if(($row_event['acknowledged'] == 0) && ($row_event['value'] == TRIGGER_VALUE_TRUE)){
					$ack_cb = new CCheckBox('events['.$row_event['eventid'].']', 'no', NULL, $row_event['eventid']);
				}
				else{
					$ack_cb = SPACE;
				}

				$clock = new CLink(zbx_date2str(S_DATE_FORMAT_YMDHMS, $row_event['clock']),
					'tr_events.php?triggerid='.$trigger['triggerid'].'&eventid='.$row_event['eventid']);
				$next_clock = isset($trigger['events'][$enum-1]) ? $trigger['events'][$enum-1]['clock'] : time();

				$empty_col = new CCol(SPACE);
				$empty_col->setColSpan(3);
				$ack_cb_col = new CCol($ack_cb);
				$ack_cb_col->setColSpan(2);
				$row = new CRow(array(
					SPACE,
					$config['event_ack_enable'] ? $ack_cb_col : null,
					$status,
					$clock,
					zbx_date2age($row_event['clock']),
					zbx_date2age($next_clock, $row_event['clock']),
					($config['event_ack_enable']) ? $ack : NULL,
					is_show_all_nodes() ? SPACE : null,
					$empty_col
				), 'odd_row');
				$row->setAttribute('data-parentid', $trigger['triggerid']);
				$row->addStyle('display: none;');
				$table->addRow($row);

				if($i > $config['event_show_max']) break;
			}
		}
	}


//----- GO ------
	$footer = null;
	if($config['event_ack_enable']){
		$goBox = new CComboBox('go');
		$goBox->addItem('bulkacknowledge', S_BULK_ACKNOWLEDGE);

// goButton name is necessary!!!
		$goButton = new CButton('goButton', S_GO.' (0)');
		$goButton->setAttribute('id', 'goButton');

		$show_event_col ? zbx_add_post_js('chkbxRange.pageGoName = "events";') : zbx_add_post_js('chkbxRange.pageGoName = "triggers";');

		$footer = get_table_header(array($goBox, $goButton));
	}
//----

	$table = array($paging, $table, $paging, $footer);
	$m_form->addItem($table);
	$trigg_wdgt->addItem($m_form);
	$trigg_wdgt->show();

	zbx_add_post_js('blink.init();');
	zbx_add_post_js("var switcher = new CSwitcher('$switcherName');");

include_once('include/page_footer.php');

?>

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
require_once('include/config.inc.php');
require_once('include/hosts.inc.php');
require_once('include/html.inc.php');

$page['title'] = 'S_SEARCH';
$page['file'] = 'search.php';
$page['hist_arg'] = array();
$page['scripts'] = array('class.pmaster.js','effects.js');

$page['type'] = detect_page_type(PAGE_TYPE_HTML);

include_once('include/page_header.php');

//		VAR				TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'type'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		NULL),
		'search'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,			NULL),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'favcnt'=>		array(T_ZBX_INT, O_OPT,	null,	null,			NULL),

		'action'=>		array(T_ZBX_STR, O_OPT, P_ACT, 	IN("'flop','refresh'"),NULL),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({action}) && ("flop"=={action})'),
	);

	check_fields($fields);

// ACTION /////////////////////////////////////////////////////////////////////////////
	if(isset($_REQUEST['favobj'])){
		$_REQUEST['pmasterid'] = get_request('pmasterid','mainpage');

		if('hat' == $_REQUEST['favobj']){
			if('flop' == $_REQUEST['action']){
				CProfile::update('web.dashboard.hats.'.$_REQUEST['favref'].'.state',$_REQUEST['state'], PROFILE_TYPE_INT);
			}
			else if('refresh' == $_REQUEST['action']){
				switch($_REQUEST['favref']){
				}
			}
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		include_once('include/page_footer.php');
		exit();
	}
?>
<?php

	$admin = uint_in_array($USER_DETAILS['type'], array(USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN));
	$rows_per_page = $USER_DETAILS['rows_per_page'];

	$search_wdgt = new CWidget('search_wdgt');

	$search = get_request('search', '');

// Header
	if(zbx_empty($search)){
		$search = S_SEARCH_PATTERN_EMPTY;
	}
	$search_wdgt->setClass('header');
	$search_wdgt->addHeader(array(S_SEARCH_BIG.': ',bold($search)), SPACE);

//-------------
	$left_col[] = array();
	$right_col[] = array();

// FIND Hosts
	$params = array(
		'nodeids'=> get_current_nodeid(true),
		'search' => array(
			'host' => $search,
			'dns' => $search,
			'ip' => $search
		),
		'limit' => $rows_per_page,
		'selectGroups' => API_OUTPUT_EXTEND,
		'selectInterfaces' => API_OUTPUT_EXTEND,
		'selectItems' => API_OUTPUT_COUNT,
		'select_triggers' => API_OUTPUT_COUNT,
		'select_graphs' => API_OUTPUT_COUNT,
		'select_applications' => API_OUTPUT_COUNT,
		'output' => array('host','status'),
		'searchByAny' => true
	);
	$db_hosts = CHost::get($params);

	order_result($db_hosts, 'host');

	$hosts = selectByPattern($db_hosts, 'host', $search, $rows_per_page);
	$hostids = zbx_objectValues($hosts, 'hostid');

	$params = array(
		'nodeids'=> get_current_nodeid(true),
		'hostids' => $hostids,
		'editable' => 1
	);
	$rw_hosts = CHost::get($params);
	$rw_hosts = zbx_toHash($rw_hosts,'hostid');

	$params = array(
		'nodeids'=> get_current_nodeid(true),
		'search' => array(
			'host' => $search,
			'dns' => $search,
			'ip' => $search
		),
		'countOutput' => 1,
		'searchByAny' => true
	);

	$overalCount = CHost::get($params);
	$viewCount = count($hosts);

	$header = array(
		ZBX_DISTRIBUTED?new CCol(S_NODE):null,
		new CCol(S_HOSTS),
		new CCol(S_IP),
		new CCol(S_DNS),
		new CCol(S_LATEST_DATA),
		new CCol(S_TRIGGERS),
		new CCol(S_EVENTS),
		new CCol(S_APPLICATIONS),
		new CCol(S_ITEMS),
		new CCol(S_TRIGGERS),
		new CCol(S_GRAPHS),
	);

	$table  = new CTableInfo();
	$table->setHeader($header);

	foreach($hosts as $hnum => $host){
		$hostid = $host['hostid'];

		$interface = reset($host['interfaces']);
		$host['ip'] = $interface['ip'];
		$host['dns'] = $interface['dns'];
		$host['port'] = $interface['port'];

		switch($host['status']){
			case HOST_STATUS_NOT_MONITORED:
				$style = 'on';
			break;
			default:
				$style = null;
			break;
		}


		$group = reset($host['groups']);
		$link = 'groupid='.$group['groupid'].'&hostid='.$hostid.'&switch_node='.id2nodeid($hostid);

		$caption = make_decoration($host['host'], $search);

		if(isset($rw_hosts[$hostid])){
			$host_link = new CLink($caption,'hosts.php?form=update&'.$link, $style);
			$applications_link = array(new CLink(S_APPLICATIONS,'applications.php?'.$link), ' ('.$host['applications'].')');
			$items_link = array(new CLink(S_ITEMS,'items.php?'.$link), ' ('.$host['items'].')');
			$triggers_link = array(new CLink(S_TRIGGERS,'triggers.php?'.$link), ' ('.$host['triggers'].')');
			$graphs_link = array(new CLink(S_GRAPHS,'graphs.php?'.$link), ' ('.$host['graphs'].')');
		}
		else{
			$host_link = new CSpan($caption, $style);
			$applications_link = array(new CSpan(S_APPLICATIONS,'unknown'), ' ('.$host['applications'].')');
			$items_link = array(new CSpan(S_ITEMS,'unknown'), ' ('.$host['items'].')');
			$triggers_link = array(new CSpan(S_TRIGGERS,'unknown'), ' ('.$host['triggers'].')');
			$graphs_link = array(new CSpan(S_GRAPHS,'unknown'), ' ('.$host['graphs'].')');
		}

		if(!$admin){
			$host_link = new CSpan($caption, $style);
		}

		$hostip = make_decoration($host['ip'], $search);
		$hostdns = make_decoration($host['dns'], $search);

		$table->addRow(array(
			get_node_name_by_elid($hostid, true),
			$host_link,
			$hostip,
			$hostdns,
			new CLink(S_LATEST_DATA,'latest.php?'.$link),
			new CLink(S_TRIGGERS,'tr_status.php?'.$link),
			new CLink(S_EVENTS,'events.php?'.$link),
			$applications_link,
			$items_link,
			$triggers_link,
			$graphs_link,
		));
	}

	$sysmap_menu = get_icon('menu', array('menu' => 'sysmaps'));

	$wdgt_hosts = new CUIWidget('search_hosts',$table);
	$wdgt_hosts->setHeader(S_HOSTS, SPACE);
	$wdgt_hosts->setFooter(S_DISPLAYING.SPACE.$viewCount.SPACE.S_OF_SMALL.SPACE.$overalCount.SPACE.S_FOUND_SMALL);

	$left_col[] = $wdgt_hosts;
//----------------


// Find Host groups
	$params = array(
		'nodeids'=> get_current_nodeid(true),
		'output' => API_OUTPUT_EXTEND,
		'search' => array('name' => $search),
		'limit' => $rows_per_page
	);

	$db_hostGroups = CHostGroup::get($params);
	order_result($db_hostGroups, 'name');

	$hostGroups = selectByPattern($db_hostGroups, 'name', $search, $rows_per_page);
	$groupids = zbx_objectValues($hostGroups, 'groupid');

	$params = array(
		'nodeids'=> get_current_nodeid(true),
		'groupids' => $groupids,
		'editable' => 1
	);

	$rw_hostGroups = CHostGroup::get($params);
	$rw_hostGroups = zbx_toHash($rw_hostGroups, 'groupid');

	$params = array(
		'nodeids'=> get_current_nodeid(true),
		'search' => array('name' => $search),
		'countOutput' => 1
	);
	$overalCount = CHostGroup::get($params);
	$viewCount = count($hostGroups);

	$header = array(
		ZBX_DISTRIBUTED?new CCol(S_NODE):null,
		new CCol(S_HOST_GROUP),
		new CCol(S_LATEST_DATA),
		new CCol(S_TRIGGERS),
		new CCol(S_EVENTS),
		$admin?new CCol(S_EDIT_HOSTS):null,
	);

	$table  = new CTableInfo();
	$table->setHeader($header);

	foreach($hostGroups as $hnum => $group){
		$hostgroupid = $group['groupid'];

		$caption = make_decoration($group['name'], $search);
		$link = 'groupid='.$hostgroupid.'&hostid=0&switch_node='.id2nodeid($hostgroupid);

		if($admin){
			if(isset($rw_hostGroups[$hostgroupid])){
				$admin_link = new CLink(S_EDIT_HOSTS,'hosts.php?config=1&groupid='.$hostgroupid.'&hostid=0'.'&switch_node='.id2nodeid($hostgroupid));
				$hgroup_link = new CLink($caption,'hostgroups.php?form=update&'.$link);
			}
			else{
				$admin_link = new CSpan(S_EDIT_HOSTS,'unknown');
				$hgroup_link = new CSpan($caption);
			}
		}
		else{
			$admin_link = null;
			$hgroup_link = new CSpan($caption);
		}

		$table->addRow(array(
			get_node_name_by_elid($hostgroupid, true),
			$hgroup_link,
			new CLink(S_LATEST_DATA,'latest.php?'.$link),
			new CLink(S_TRIGGERS,'tr_status.php?'.$link),
			new CLink(S_EVENTS,'events.php?'.$link),
			$admin_link,
		));
	}

	$wdgt_hgroups = new CUIWidget('search_hostgroup',$table);
	$wdgt_hgroups->setHeader(S_HOST_GROUPS, SPACE);
	$wdgt_hgroups->setFooter(S_DISPLAYING.SPACE.$viewCount.SPACE.S_OF_SMALL.SPACE.$overalCount.SPACE.S_FOUND_SMALL);
	$right_col[] = $wdgt_hgroups;
//----------------

// FIND Templates
	if($admin){
		$params = array(
			'nodeids'=> get_current_nodeid(true),
			'search' => array('host' => $search),
			'output' => array('host'),
			'selectGroups' => API_OUTPUT_REFER,
			'sortfield' => 'host',
			'selectItems' => API_OUTPUT_COUNT,
			'select_triggers' => API_OUTPUT_COUNT,
			'select_graphs' => API_OUTPUT_COUNT,
			'select_applications' => API_OUTPUT_COUNT,
			'limit' => $rows_per_page
		);
		$db_templates = CTemplate::get($params);
		order_result($db_templates, 'host');

		$templates = selectByPattern($db_templates, 'host', $search, $rows_per_page);
		$templateids = zbx_objectValues($templates, 'templateid');

		$params = array(
			'nodeids'=> get_current_nodeid(true),
			'templateids' => $templateids,
			'editable' => 1
		);
		$rw_templates = CTemplate::get($params);
		$rw_templates = zbx_toHash($rw_templates,'templateid');

		$params = array(
			'nodeids'=> get_current_nodeid(true),
			'search' => array('host' => $search),
			'countOutput' => 1,
			'editable' => 1
		);

		$overalCount = CTemplate::get($params);
		$viewCount = count($templates);

		$header = array(
			ZBX_DISTRIBUTED?new CCol(S_NODE):null,
			new CCol(S_TEMPLATES),
			new CCol(S_APPLICATIONS),
			new CCol(S_ITEMS),
			new CCol(S_TRIGGERS),
			new CCol(S_GRAPHS),
		);

		$table  = new CTableInfo();
		$table->setHeader($header);

		foreach($templates as $tnum => $template){
			$templateid = $template['hostid'];

			$group = reset($template['groups']);
			$link = 'groupid='.$group['groupid'].'&hostid='.$templateid.'&switch_node='.id2nodeid($templateid);

			$caption = make_decoration($template['host'], $search);

			if(isset($rw_templates[$templateid])){
				$template_link = new CLink($caption,'templates.php?form=update&'.'&templateid='.$templateid.'&switch_node='.id2nodeid($templateid));
				$applications_link = array(new CLink(S_APPLICATIONS,'applications.php?'.$link), ' ('.$template['applications'].')');
				$items_link = array(new CLink(S_ITEMS,'items.php?'.$link), ' ('.$template['items'].')');
				$triggers_link = array(new CLink(S_TRIGGERS,'triggers.php?'.$link), ' ('.$template['triggers'].')');
				$graphs_link = array(new CLink(S_GRAPHS,'graphs.php?'.$link), ' ('.$template['graphs'].')');
			}
			else{
				$template_link = new CSpan($caption);
				$applications_link = array(new CSpan(S_APPLICATIONS,'unknown'), ' ('.$template['applications'].')');
				$items_link = array(new CSpan(S_ITEMS,'unknown'), ' ('.$template['items'].')');
				$triggers_link = array(new CSpan(S_TRIGGERS,'unknown'), ' ('.$template['triggers'].')');
				$graphs_link = array(new CSpan(S_GRAPHS,'unknown'), ' ('.$template['graphs'].')');
			}

			$table->addRow(array(
				get_node_name_by_elid($templateid, true),
				$template_link,
				$applications_link,
				$items_link,
				$triggers_link,
				$graphs_link
			));
		}

		$wdgt_templates = new CUIWidget('search_templates',$table);
		$wdgt_templates->setHeader(S_TEMPLATES, SPACE);
		$wdgt_templates->setFooter(S_DISPLAYING.SPACE.$viewCount.SPACE.S_OF_SMALL.SPACE.$overalCount.SPACE.S_FOUND_SMALL);
		$right_col[] = $wdgt_templates;
	}
//----------------

	$leftDiv = new CDiv($left_col, 'column');
	$rightDiv = new CDiv($right_col, 'column');

	$ieTab = new CTable();
	$ieTab->addRow(array($leftDiv,$rightDiv), 'top');

	$search_wdgt->addItem($ieTab);
	$search_wdgt->show();

?>
<?php

include_once('include/page_footer.php');

?>
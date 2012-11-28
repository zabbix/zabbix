<?php
/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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
require_once('include/reports.inc.php');

$page['title'] = 'S_AVAILABILITY_REPORT';
$page['file'] = 'report2.php';
$page['hist_arg'] = array('config', 'groupid', 'hostid', 'tpl_triggerid');
$page['scripts'] = array('class.calendar.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

include_once('include/page_header.php');
?>
<?php

//		VAR				TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields = array(
		'config' => array(T_ZBX_INT, O_OPT, P_SYS, IN('0,1'), NULL),
		'filter_groupid' => array(T_ZBX_INT, O_OPT, P_SYS, DB_ID, NULL),
		'hostgroupid' => array(T_ZBX_INT, O_OPT, P_SYS, DB_ID, NULL),
		'filter_hostid' => array(T_ZBX_INT, O_OPT, P_SYS, DB_ID, NULL),
		'tpl_triggerid' => array(T_ZBX_INT, O_OPT, P_SYS, DB_ID, NULL),
		'triggerid' => array(T_ZBX_INT, O_OPT, P_SYS | P_NZERO, DB_ID, NULL),
// filter
		'filter_rst' => array(T_ZBX_INT, O_OPT, P_SYS, IN(array(0, 1)), NULL),
		'filter_set' => array(T_ZBX_STR, O_OPT, P_SYS, null, NULL),
		'filter_timesince' => array(T_ZBX_STR, O_OPT, P_UNSET_EMPTY, null, NULL),
		'filter_timetill' => array(T_ZBX_STR, O_OPT, P_UNSET_EMPTY, null, NULL),
//ajax
		'favobj' => array(T_ZBX_STR, O_OPT, P_ACT, NULL, NULL),
		'favref' => array(T_ZBX_STR, O_OPT, P_ACT, NOT_EMPTY, 'isset({favobj})'),
		'state' => array(T_ZBX_INT, O_OPT, P_ACT, NOT_EMPTY, 'isset({favobj}) && ("filter"=={favobj})'),
	);

	check_fields($fields);

// AJAX
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.avail_report.filter.state', $_REQUEST['state'], PROFILE_TYPE_INT);
		}
	}
	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		include_once('include/page_footer.php');
		exit();
	}

//--------
// FILTER
	if(isset($_REQUEST['filter_rst'])){
		$_REQUEST['filter_groupid'] = 0;
		$_REQUEST['filter_hostid'] = 0;
		$_REQUEST['filter_timesince'] = 0;
		$_REQUEST['filter_timetill'] = 0;
	}
	else{
		$_REQUEST['filter_groupid'] = get_request('filter_groupid', 0);
		$_REQUEST['filter_hostid'] = get_request('filter_hostid', 0);
		$_REQUEST['filter_timesince'] = get_request('filter_timesince', CProfile::get('web.avail_report.filter.timesince', 0));
		$_REQUEST['filter_timetill'] = get_request('filter_timetill', CProfile::get('web.avail_report.filter.timetill', 0));
	}

	if(($_REQUEST['filter_timetill'] > 0) && ($_REQUEST['filter_timesince'] > $_REQUEST['filter_timetill'])){
		$tmp = $_REQUEST['filter_timesince'];
		$_REQUEST['filter_timesince'] = $_REQUEST['filter_timetill'];
		$_REQUEST['filter_timetill'] = $tmp;
	}

	if(isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst'])){
		CProfile::update('web.avail_report.filter.timesince', $_REQUEST['filter_timesince'], PROFILE_TYPE_STR);
		CProfile::update('web.avail_report.filter.timetill', $_REQUEST['filter_timetill'], PROFILE_TYPE_STR);
	}

	$_REQUEST['filter_timesince'] = zbxDateToTime($_REQUEST['filter_timesince']);
	$_REQUEST['filter_timetill'] = zbxDateToTime($_REQUEST['filter_timetill']);

	$_REQUEST['groupid'] = $_REQUEST['filter_groupid'];
	$_REQUEST['hostid'] = $_REQUEST['filter_hostid'];
// --------------

	$config = get_request('config', CProfile::get('web.avail_report.config', 0));
	CProfile::update('web.avail_report.config', $config, PROFILE_TYPE_INT);

	$params = array();
	$options = array('allow_all_hosts', 'with_items');

	if(0 == $config)
		array_push($options, 'monitored_hosts');
	else
		array_push($options, 'templated_hosts');

	if(!$ZBX_WITH_ALL_NODES) array_push($options, 'only_current_node');

	foreach($options as $option)
		$params[$option] = 1;

	$PAGE_GROUPS = get_viewed_groups(PERM_READ_ONLY, $params);
	$PAGE_HOSTS = get_viewed_hosts(PERM_READ_ONLY, $PAGE_GROUPS['selected'], $params);

	validate_group_with_host($PAGE_GROUPS, $PAGE_HOSTS);
// SDI($_REQUEST['groupid'].' : '.$_REQUEST['hostid']);
?>
<?php

	$rep2_wdgt = new CWidget();

// HEADER
	if(0 == $config){
		$available_groups = $PAGE_GROUPS['groupids'];
		$available_hosts = $PAGE_HOSTS['hostids'];
	}
	else{
		$available_groups = get_accessible_groups_by_user($USER_DETAILS, PERM_READ_ONLY);

		if($PAGE_HOSTS['selected'] != 0)
			$PAGE_HOSTS['hostids'] = $available_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_ONLY);
		else
			$available_hosts = $PAGE_HOSTS['hostids'];
	}

	$rep2_wdgt->addPageHeader(S_AVAILABILITY_REPORT_BIG);
//	show_report2_header($config, $PAGE_GROUPS, $PAGE_HOSTS);

	if(isset($_REQUEST['triggerid'])){
		$options = array(
			'triggerids' => $_REQUEST['triggerid'],
			'output' => API_OUTPUT_EXTEND,
			'select_hosts' => API_OUTPUT_EXTEND,
			'nodeids' => get_current_nodeid(true)
		);

		$trigger_data = CTrigger::get($options);
		if(empty($trigger_data)){
			unset($_REQUEST['triggerid']);
		}
		else{
			$trigger_data = reset($trigger_data);

			$host = reset($trigger_data['hosts']);
			$trigger_data['hostid'] = $host['hostid'];
			$trigger_data['host'] = $host['host'];
		}
	}


	if(isset($_REQUEST['triggerid'])){
		$rep2_wdgt->addHeader(array(
			new CLink($trigger_data['host'], '?filter_groupid=' . $_REQUEST['groupid'] . '&filter_hostid=' . $trigger_data['hostid']),
			' : ',
			expand_trigger_description_by_data($trigger_data)
				), SPACE);

		$table = new CTableInfo(null, 'graph');
		$table->addRow(new CImg('chart4.php?triggerid=' . $_REQUEST['triggerid']));

		$rep2_wdgt->addItem($table);
		$rep2_wdgt->show();
	}
	else if(isset($_REQUEST['hostid'])){

		$r_form = new CForm();
		$r_form->setMethod('get');

		$cmbConf = new CComboBox('config', $config, 'submit()');
		$cmbConf->addItem(0, S_BY_HOST);
		$cmbConf->addItem(1, S_BY_TRIGGER_TEMPLATE);
		$r_form->addItem($cmbConf);

		$rep2_wdgt->addHeader(S_REPORT_BIG, array(S_MODE . SPACE, $r_form));
// FILTER
		$filterForm = get_report2_filter($config, $PAGE_GROUPS, $PAGE_HOSTS);
		$rep2_wdgt->addFlicker($filterForm, CProfile::get('web.avail_report.filter.state', 0));
//-------

		$options = array(
			'output' => array('triggerid', 'description', 'expression', 'value'),
			'expandDescription' => true,
			'expandData' => true,
			'monitored' => true,
			'filter' => array()
		);

		if(0 == $config){
			if($_REQUEST['groupid'] > 0){
				$options['groupids'] = $_REQUEST['groupid'];
			}

			if($_REQUEST['hostid'] > 0){
				$options['hostids'] = $_REQUEST['hostid'];
			}
		}
		else{
			if($_REQUEST['hostid'] > 0){
				$hosts = CHost::get(array('templateids' => $_REQUEST['hostid']));
				$options['hostids'] = zbx_objectValues($hosts, 'hostid');
			}

			if(isset($_REQUEST['tpl_triggerid']) && ($_REQUEST['tpl_triggerid'] > 0)){
				$options['filter']['templateid'] = $_REQUEST['tpl_triggerid'];
			}
		}

		$triggers = CTrigger::get($options);
		ArraySorter::sort($triggers, array('host', 'description'));

		$table = new CTableInfo();
		$table->setHeader(array(
			is_show_all_nodes() ? S_NODE : null,
			(($_REQUEST['hostid'] == 0) || (1 == $config)) ? S_HOST : NULL,
			S_NAME,
			S_PROBLEMS,
			S_OK,
			S_UNKNOWN,
			S_GRAPH
		));

		foreach($triggers as $trigger){
			$availability = calculate_availability($trigger['triggerid'], $_REQUEST['filter_timesince'], $_REQUEST['filter_timetill']);

			$true = new CSpan(sprintf('%.4f%%', $availability['true']), 'on');
			$false = new CSpan(sprintf('%.4f%%', $availability['false']), 'off');
			$unknown = new CSpan(sprintf('%.4f%%', $availability['unknown']), 'unknown');
			$actions = new CLink(S_SHOW, 'report2.php?filter_groupid=' . $_REQUEST['groupid'] . '&filter_hostid=' . $_REQUEST['hostid'] . '&triggerid=' . $trigger['triggerid']);

			$table->addRow(array(
				get_node_name_by_elid($trigger['hostid']),
				(($_REQUEST['hostid'] == 0) || (1 == $config)) ? $trigger['host'] : NULL,
				new CLink($trigger['description'], 'events.php?triggerid=' . $trigger['triggerid']),
				$true,
				$false,
				$unknown,
				$actions
			));
		}

		$rep2_wdgt->addItem($table);
		$rep2_wdgt->show();
	}
?>
<?php
	include_once('include/page_footer.php');
?>

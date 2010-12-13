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
require_once('include/maintenances.inc.php');
require_once('include/forms.inc.php');
require_once('include/ident.inc.php');

$page['type'] = detect_page_type(PAGE_TYPE_HTML);
$page['title'] = 'S_HOSTS';
$page['file'] = 'hosts_mon.php';
$page['hist_arg'] = array('groupid');

include_once('include/page_header.php');
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
// host
		'groupid'=>			array(T_ZBX_INT, O_OPT,	P_SYS, 	DB_ID,	NULL),
		'hostid'=>			array(T_ZBX_INT, O_OPT,	P_SYS,  DB_ID,	null),
// Filter
		'filter_set' =>		array(T_ZBX_STR, O_OPT,	P_ACT,	null,	null),

		'filter_host'=>		array(T_ZBX_STR, O_OPT,  null,	null,	null),
		'filter_ip'=>		array(T_ZBX_STR, O_OPT,  null,	null,	null),
		'filter_dns'=>		array(T_ZBX_STR, O_OPT,  null,	null,	null),
		'filter_port'=>		array(T_ZBX_STR, O_OPT,  null,	null,	null),
//ajax
		'favobj'=>			array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favref'=>			array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'state'=>			array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("filter"=={favobj})')
	);

// OUTER DATA
	check_fields($fields);
	validate_sort_and_sortorder('host', ZBX_SORT_UP);

	$_REQUEST['go'] = get_request('go', 'none');

// PERMISSIONS
	if(get_request('groupid', 0) > 0){
		$groupids = available_groups($_REQUEST['groupid'], 0);
		if(empty($groupids)) access_deny();
	}

	if(get_request('hostid', 0) > 0){
		$hostids = available_hosts($_REQUEST['hostid'], 0);
		if(empty($hostids)) access_deny();
	}
?>
<?php
	/* AJAX */
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.hosts.filter.state', $_REQUEST['state'], PROFILE_TYPE_INT);
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		include_once('include/page_footer.php');
		exit();
	}
//--------


/* FILTER */
	if(isset($_REQUEST['filter_set'])){
		$_REQUEST['filter_ip'] = get_request('filter_ip');
		$_REQUEST['filter_dns'] = get_request('filter_dns');
		$_REQUEST['filter_host'] = get_request('filter_host');
		$_REQUEST['filter_port'] = get_request('filter_port');

		CProfile::update('web.hosts_mon.filter_ip', $_REQUEST['filter_ip'], PROFILE_TYPE_STR);
		CProfile::update('web.hosts_mon.filter_dns', $_REQUEST['filter_dns'], PROFILE_TYPE_STR);
		CProfile::update('web.hosts_mon.filter_host', $_REQUEST['filter_host'], PROFILE_TYPE_STR);
		CProfile::update('web.hosts_mon.filter_port', $_REQUEST['filter_port'], PROFILE_TYPE_STR);
	}
	else{
		$_REQUEST['filter_ip'] = CProfile::get('web.hosts_mon.filter_ip');
		$_REQUEST['filter_dns'] = CProfile::get('web.hosts_mon.filter_dns');
		$_REQUEST['filter_host'] = CProfile::get('web.hosts_mon.filter_host');
		$_REQUEST['filter_port'] = CProfile::get('web.hosts_mon.filter_port');
	}
?>
<?php
	$options = array(
		'groups' => array(
			'monitored_hosts' => 1,
		),
		'groupid' => get_request('groupid', null),
	);
	$pageFilter = new CPageFilter($options);

	$_REQUEST['groupid'] = $pageFilter->groupid;
	$_REQUEST['hostid'] = get_request('hostid', 0);

?>
<?php
	$hosts_wdgt = new CWidget();
	$hosts_wdgt->addPageHeader(S_CONFIGURATION_OF_HOSTS);


	$frmForm = new CForm();
	$frmForm->addItem(array(S_GROUP.SPACE, $pageFilter->getGroupsCB()));
	$hosts_wdgt->addHeader(S_HOSTS_BIG, $frmForm);

	$numrows = new CDiv();
	$numrows->setAttribute('name', 'numrows');

	$hosts_wdgt->addHeader($numrows);

// HOSTS FILTER {{{
	$filter_table = new CTable('', 'filter_config');
	$filter_table->addRow(array(
		array(array(bold(S_HOST), SPACE.S_LIKE_SMALL.': '), new CTextBox('filter_host', $_REQUEST['filter_host'], 20)),
		array(array(bold(S_DNS), SPACE.S_LIKE_SMALL.': '), new CTextBox('filter_dns', $_REQUEST['filter_dns'], 20)),
		array(array(bold(S_IP), SPACE.S_LIKE_SMALL.': '), new CTextBox('filter_ip', $_REQUEST['filter_ip'], 20)),
		array(bold(S_PORT.': '), new CTextBox('filter_port', $_REQUEST['filter_port'], 20))
	));

	$reset = new CSpan(S_RESET,'menu_link');
	$reset->onClick("javascript: clearAllForm('zbx_filter');");

	$filter = new CButton('filter', S_FILTER, "javascript: create_var('zbx_filter', 'filter_set', '1', true);");
	$filter->useJQueryStyle();

	$footer_col = new CCol(array($filter, SPACE, SPACE, SPACE, $reset), 'center');
	$footer_col->setColSpan(4);

	$filter_table->addRow($footer_col);

	$filter_form = new CForm(null, 'get');
	$filter_form->setAttribute('name','zbx_filter');
	$filter_form->setAttribute('id','zbx_filter');
	$filter_form->addItem($filter_table);
	$hosts_wdgt->addFlicker($filter_form, CProfile::get('web.hosts.filter.state', 0));
// }}} HOSTS FILTER


// table HOSTS
	$table = new CTableInfo(S_NO_HOSTS_DEFINED);
	$table->setHeader(array(
		make_sorting_header(S_NAME, 'host'),
		S_APPLICATIONS,
		S_ITEMS,
		S_TRIGGERS,
		S_GRAPHS,
		S_SCREENS,
		S_AVAILABILITY
	));

// get Hosts
	$sortfield = getPageSortField('host');
	$sortorder = getPageSortOrder();

	if($pageFilter->groupsSelected){
		$options = array(
			'with_monitored_items' => 1,
			'output' => API_OUTPUT_SHORTEN,
			'search' => array(
				'host' => (empty($_REQUEST['filter_host']) ? null : $_REQUEST['filter_host']),
				'ip' => (empty($_REQUEST['filter_ip']) ? null : $_REQUEST['filter_ip']),
				'dns' => (empty($_REQUEST['filter_dns']) ? null : $_REQUEST['filter_dns']),
			),
			'filter' => array(
				'status' => HOST_STATUS_MONITORED,
				'port' => (empty($_REQUEST['filter_port']) ? null : $_REQUEST['filter_port']),
			),
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'limit' => ($config['search_limit']+1)
		);
		if($pageFilter->groupid > 0) $options['groupids'] = $pageFilter->groupid;

		$hosts = CHost::get($options);
	}

// sorting && paging
	order_result($hosts, $sortfield, $sortorder);
	$paging = getPagingLine($hosts);

	$options = array(
		'hostids' => zbx_objectValues($hosts, 'hostid'),
		'output' => API_OUTPUT_EXTEND,
		'selectParentTemplates' => array('hostid', 'host'),
		'selectItems' => API_OUTPUT_COUNT,
		'select_triggers' => API_OUTPUT_COUNT,
		'select_graphs' => API_OUTPUT_COUNT,
		'select_applications' => API_OUTPUT_COUNT,
		'selectScreens' => API_OUTPUT_COUNT,
		'nopermissions' => 1
	);
	$hosts = CHost::get($options);
	order_result($hosts, $sortfield, $sortorder);

	foreach($hosts as $num => $host){
		$applications = array(new CLink(S_APPLICATIONS, 'applications.php?groupid='.$_REQUEST['groupid'].'&hostid='.$host['hostid']),
			' ('.$host['applications'].')');
		$items = array(new CLink(S_ITEMS, 'latest.php?filter_set=1&hostid='.$host['hostid']),
			' ('.$host['items'].')');
		$triggers = array(new CLink(S_TRIGGERS, 'tr_status.php?groupid='.$_REQUEST['groupid'].'&hostid='.$host['hostid']),
			' ('.$host['triggers'].')');
		$graphs = array(new CLink(S_GRAPHS, 'charts.php?groupid='.$_REQUEST['groupid'].'&hostid='.$host['hostid']),
			' ('.$host['graphs'].')');
		$screens = array(new CLink(S_SCREENS, 'host_screen.php?groupid='.$_REQUEST['groupid'].'&hostid='.$host['hostid']),
			' ('.$host['screens'].')');

		$description = array();
		if($host['proxy_hostid']){
			$proxy = CProxy::get(array(
				'proxyids' => $host['proxy_hostid'],
				'output' => API_OUTPUT_EXTEND
			));
			$proxy = reset($proxy);
			$description[] = $proxy['host'] . ':';
		}

		$description[] = new CLink($host['host'], 'hosts.php?form=update&hostid='.$host['hostid'].url_param('groupid'));


		switch($host['available']){
			case HOST_AVAILABLE_TRUE:
				$zbx_available = new CDiv(SPACE, 'iconzbxavailable');
				break;
			case HOST_AVAILABLE_FALSE:
				$zbx_available = new CDiv(SPACE, 'iconzbxunavailable');
				$zbx_available->setHint($host['error'], '', 'on');
				break;
			case HOST_AVAILABLE_UNKNOWN:
				$zbx_available = new CDiv(SPACE, 'iconzbxunknown');
				break;
		}

		switch($host['snmp_available']){
			case HOST_AVAILABLE_TRUE:
				$snmp_available = new CDiv(SPACE, 'iconsnmpavailable');
				break;
			case HOST_AVAILABLE_FALSE:
				$snmp_available = new CDiv(SPACE, 'iconsnmpunavailable');
				$snmp_available->setHint($host['snmp_error'], '', 'on');
				break;
			case HOST_AVAILABLE_UNKNOWN:
				$snmp_available = new CDiv(SPACE, 'iconsnmpunknown');
				break;
		}

		switch($host['ipmi_available']){
			case HOST_AVAILABLE_TRUE:
				$ipmi_available = new CDiv(SPACE, 'iconipmiavailable');
				break;
			case HOST_AVAILABLE_FALSE:
				$ipmi_available = new CDiv(SPACE, 'iconipmiunavailable');
				$ipmi_available->setHint($host['ipmi_error'], '', 'on');
				break;
			case HOST_AVAILABLE_UNKNOWN:
				$ipmi_available = new CDiv(SPACE, 'iconipmiunknown');
				break;
		}

		$av_table = new CTable(null, 'invisible');
		$av_table->addRow(array($zbx_available, $snmp_available, $ipmi_available));

		$table->addRow(array(
			$description,
			$applications,
			$items,
			$triggers,
			$graphs,
			$screens,
			$av_table
		));
	}

	$hosts_wdgt->addItem(array($paging, $table, $paging));
	$hosts_wdgt->show();
?>
<?php

include_once('include/page_footer.php');

?>

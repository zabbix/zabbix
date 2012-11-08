<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/reports.inc.php';

$page['title'] = _('Availability report');
$page['file'] = 'report2.php';
$page['hist_arg'] = array('mode', 'groupid', 'hostid', 'tpl_triggerid');
$page['scripts'] = array('class.calendar.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';


//		VAR				TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'mode' => array(T_ZBX_INT, O_OPT, P_SYS, IN('0,1'), NULL),
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
	'favstate' => array(T_ZBX_INT, O_OPT, P_ACT, NOT_EMPTY, 'isset({favobj})&&("filter"=={favobj})'),
);

check_fields($fields);

// AJAX
if (isset($_REQUEST['favobj'])) {
	if ('filter' == $_REQUEST['favobj']) {
		CProfile::update('web.avail_report.filter.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}
}
if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

//--------
// FILTER
if (isset($_REQUEST['filter_rst'])) {
	$_REQUEST['filter_groupid'] = 0;
	$_REQUEST['filter_hostid'] = 0;
	$_REQUEST['filter_timesince'] = 0;
	$_REQUEST['filter_timetill'] = 0;
}

$availabilityReportMode = get_request('mode', CProfile::get('web.avail_report.mode', AVAILABILITY_REPORT_BY_HOST));
CProfile::update('web.avail_report.mode', $availabilityReportMode, PROFILE_TYPE_INT);
$config = select_config();

if ($config['dropdown_first_remember']) {
	if (!isset($_REQUEST['filter_rst'])) {
		$_REQUEST['filter_groupid'] = get_request('filter_groupid', CProfile::get('web.avail_report.'.$availabilityReportMode.'.groupid', 0));
		$_REQUEST['filter_hostid'] = get_request('filter_hostid', CProfile::get('web.avail_report.'.$availabilityReportMode.'.hostid', 0));
		$_REQUEST['filter_timesince'] = get_request('filter_timesince', CProfile::get('web.avail_report.'.$availabilityReportMode.'.timesince', 0));
		$_REQUEST['filter_timetill'] = get_request('filter_timetill', CProfile::get('web.avail_report.'.$availabilityReportMode.'.timetill', 0));
	}
	CProfile::update('web.avail_report.'.$availabilityReportMode.'.groupid', $_REQUEST['filter_groupid'], PROFILE_TYPE_INT);
	CProfile::update('web.avail_report.'.$availabilityReportMode.'.timesince', $_REQUEST['filter_timesince'], PROFILE_TYPE_STR);
	CProfile::update('web.avail_report.'.$availabilityReportMode.'.timetill', $_REQUEST['filter_timetill'], PROFILE_TYPE_STR);
}
else {
	if (!isset($_REQUEST['filter_rst'])) {
		$_REQUEST['filter_groupid'] = get_request('filter_groupid', 0);
		$_REQUEST['filter_hostid'] = get_request('filter_hostid', 0);
		$_REQUEST['filter_timesince'] = get_request('filter_timesince', 0);
		$_REQUEST['filter_timetill'] = get_request('filter_timetill', 0);
	}
}

CProfile::update('web.avail_report.'.$availabilityReportMode.'.hostid', $_REQUEST['filter_hostid'], PROFILE_TYPE_INT);

if (($_REQUEST['filter_timetill'] > 0) && ($_REQUEST['filter_timesince'] > $_REQUEST['filter_timetill'])) {
	$tmp = $_REQUEST['filter_timesince'];
	$_REQUEST['filter_timesince'] = $_REQUEST['filter_timetill'];
	$_REQUEST['filter_timetill'] = $tmp;
}

$_REQUEST['filter_timesince'] = zbxDateToTime($_REQUEST['filter_timesince']);
$_REQUEST['filter_timetill'] = zbxDateToTime($_REQUEST['filter_timetill']);

$_REQUEST['groupid'] = $_REQUEST['filter_groupid'];
$_REQUEST['hostid'] = $_REQUEST['filter_hostid'];
// --------------

$params = array();
$options = array('allow_all_hosts', 'with_items');

if ($availabilityReportMode == AVAILABILITY_REPORT_BY_HOST) {
	array_push($options, 'monitored_hosts');
}
elseif($availabilityReportMode == AVAILABILITY_REPORT_BY_TEMPLATE) {
	array_push($options, 'templated_hosts');
}

if (!$ZBX_WITH_ALL_NODES) {
	array_push($options, 'only_current_node');
}

foreach ($options as $option) {
	$params[$option] = 1;
}

$PAGE_GROUPS = get_viewed_groups(PERM_READ_ONLY, $params);
$PAGE_HOSTS = get_viewed_hosts(PERM_READ_ONLY, $PAGE_GROUPS['selected'], $params);

validate_group_with_host($PAGE_GROUPS, $PAGE_HOSTS);

$rep2_wdgt = new CWidget();
$rep2_wdgt->addPageHeader(_('AVAILABILITY REPORT'));

if (isset($_REQUEST['triggerid'])) {
	$options = array(
		'triggerids' => $_REQUEST['triggerid'],
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => API_OUTPUT_EXTEND,
		'nodeids' => get_current_nodeid(true),
		'expandDescription' => true
	);
	$trigger_data = API::Trigger()->get($options);
	if (empty($trigger_data)) {
		unset($_REQUEST['triggerid']);
	}
	else {
		$trigger_data = reset($trigger_data);

		$host = reset($trigger_data['hosts']);
		$trigger_data['hostid'] = $host['hostid'];
		$trigger_data['hostname'] = $host['name'];
	}
}

if (isset($_REQUEST['triggerid'])) {
	$rep2_wdgt->addHeader(array(
		new CLink($trigger_data['hostname'], '?filter_groupid=' . $_REQUEST['groupid'] . '&filter_hostid=' . $trigger_data['hostid']),
		' : ',
		$trigger_data['description']
	), SPACE);

	$table = new CTableInfo(null, 'graph');
	$table->addRow(new CImg('chart4.php?triggerid='.$_REQUEST['triggerid']));

	$rep2_wdgt->addItem(BR());
	$rep2_wdgt->addItem($table);
	$rep2_wdgt->show();
}
else if (isset($_REQUEST['hostid'])) {

	$r_form = new CForm();
	$r_form->setMethod('get');

	$cmbConf = new CComboBox('mode', $availabilityReportMode, 'submit()');
	$cmbConf->addItem(AVAILABILITY_REPORT_BY_HOST, _('By host'));
	$cmbConf->addItem(AVAILABILITY_REPORT_BY_TEMPLATE, _('By trigger template'));
	$r_form->addItem($cmbConf);

	$rep2_wdgt->addHeader(_('Report'), array(
		_('Mode').SPACE,
		$r_form
	));

	$options = array(
		'output' => array('triggerid', 'description', 'expression', 'value'),
		'expandDescription' => true,
		'expandData' => true,
		'monitored' => true,
// Rquired for getting visible host name
		'selectHosts' => API_OUTPUT_EXTEND,
		'filter' => array(),
		'hostids' => null
	);

	if ($availabilityReportMode == AVAILABILITY_REPORT_BY_HOST) {
		if ($_REQUEST['groupid'] > 0 || !$config['dropdown_first_entry']) {
			$options['groupids'] = $_REQUEST['groupid'];
		}
		if ($_REQUEST['hostid'] > 0 || !$config['dropdown_first_entry']) {
			$options['hostids'] = $_REQUEST['hostid'];
		}
	}
	elseif ($availabilityReportMode == AVAILABILITY_REPORT_BY_TEMPLATE) {
		// if a template is selected, fetch all of the hosts, that are linked to those templates
		if ($_REQUEST['hostid'] > 0 || !$config['dropdown_first_entry']) {
			$hosts = API::Host()->get(array('templateids' => $_REQUEST['hostid']));
			$options['hostids'] = zbx_objectValues($hosts, 'hostid');
		}
		if (isset($_REQUEST['tpl_triggerid']) && $_REQUEST['tpl_triggerid'] > 0) {
			$options['filter']['templateid'] = $_REQUEST['tpl_triggerid'];
		}
		if (isset($_REQUEST['hostgroupid']) && $_REQUEST['hostgroupid'] > 0) {
			$options['groupids'] = $_REQUEST['hostgroupid'];
		}
	}

	// filter
	$filterForm = get_report2_filter($availabilityReportMode, $PAGE_GROUPS, $PAGE_HOSTS, $options['hostids']);
	$rep2_wdgt->addFlicker($filterForm, CProfile::get('web.avail_report.filter.state', 0));

	$triggers = API::Trigger()->get($options);
	CArrayHelper::sort($triggers, array(
		'host',
		'description'
	));

	$table = new CTableInfo(_('No triggers defined.'));
	$table->setHeader(array(
		is_show_all_nodes() ? _('Node') : null,
		($_REQUEST['hostid'] == 0) || ($availabilityReportMode == AVAILABILITY_REPORT_BY_TEMPLATE) ? _('Host') : null,
		_('Name'),
		_('Problems'),
		_('Ok'),
		_('Unknown'),
		_('Graph')
	));

	foreach ($triggers as $trigger) {
		$availability = calculate_availability($trigger['triggerid'], $_REQUEST['filter_timesince'], $_REQUEST['filter_timetill']);

		$true = new CSpan(sprintf('%.4f%%', $availability['true']), 'on');
		$false = new CSpan(sprintf('%.4f%%', $availability['false']), 'off');
		$unknown = new CSpan(sprintf('%.4f%%', $availability['unknown']), 'unknown');
		$actions = new CLink(_('Show'), 'report2.php?filter_groupid='.$_REQUEST['groupid'].'&filter_hostid='.$_REQUEST['hostid'].'&triggerid='.$trigger['triggerid']);

		$table->addRow(array(
			get_node_name_by_elid($trigger['hostid']),
			($_REQUEST['hostid'] == 0) || ($availabilityReportMode == AVAILABILITY_REPORT_BY_TEMPLATE) ? $trigger['hosts'][0]['name'] : null,
			new CLink($trigger['description'], 'events.php?triggerid='.$trigger['triggerid']),
			$true,
			$false,
			$unknown,
			$actions
		));
	}

	$rep2_wdgt->addItem(BR());
	$rep2_wdgt->addItem($table);
	$rep2_wdgt->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';

<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/services.inc.php';

$page['title'] = _('IT services');
$page['file'] = 'srv_status.php';
$page['scripts'] = array();
$page['hist_arg'] = array();

define('ZBX_PAGE_DO_REFRESH', 1);

include_once('include/page_header.php');

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'serviceid' =>	array(T_ZBX_INT, O_OPT, P_SYS|P_NZERO, DB_ID,	null),
	'showgraph' =>	array(T_ZBX_INT, O_OPT, P_SYS,	IN('1'),		'isset({serviceid})'),
	'period' =>		array(T_ZBX_STR, O_OPT, P_SYS,	null,			null),
	'fullscreen' => array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),		null),
	// ajax
	'favobj' =>		array(T_ZBX_STR, O_OPT, P_ACT,	IN('"hat"'),	null),
	'favref' =>		array(T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY,		'isset({favobj})'),
	'favstate' =>	array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,		'isset({favobj})')
);
check_fields($fields);

/*
 * Ajax
 */
if (isset($_REQUEST['favobj'])) {
	if ($_REQUEST['favobj'] == 'hat') {
		CProfile::update('web.srv_status.hats.'.$_REQUEST['favref'].'.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}
}
if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	include_once('include/page_footer.php');
	exit();
}

if (isset($_REQUEST['serviceid']) && isset($_REQUEST['showgraph'])) {
	$service = API::Service()->get(array(
		'serviceids' => $_REQUEST['serviceid'],
		'preservekeys' => true
	));

	if ($service) {
		$table = new CTable(null, 'chart');
		$table->addRow(new CImg('chart5.php?serviceid='.key($service).url_param('path')));
		$table->show();
	}
	else {
		access_deny();
	}
}
else {
	$periods = array(
		'today' => _('Today'),
		'week' => _('This week'),
		'month' => _('This month'),
		'year' => _('This year'),
		24 => _('Last 24 hours'),
		24 * 7 => _('Last 7 days'),
		24 * 30 => _('Last 30 days'),
		24 * DAY_IN_YEAR => _('Last 365 days')
	);
	$period = get_request('period', 7 * 24);
	$period_end = time();

	switch ($period) {
		case 'today':
			$period_start = mktime(0, 0, 0, date('n'), date('j'), date('Y'));
			break;
		case 'week':
			$period_start = strtotime('last sunday');
			break;
		case 'month':
			$period_start = mktime(0, 0, 0, date('n'), 1, date('Y'));
			break;
		case 'year':
			$period_start = mktime(0, 0, 0, 1, 1, date('Y'));
			break;
		case 24:
		case 24 * 7:
		case 24 * 30:
		case 24 * DAY_IN_YEAR:
			$period_start = $period_end - ($period * 3600);
			break;
	}

	// fetch services
	$services = API::Service()->get(array(
		'output' => array('name', 'serviceid', 'showsla', 'goodsla', 'algorithm'),
		'selectParent' => array('serviceid'),
		'selectDependencies' => array('servicedownid', 'soft', 'linkid'),
		'selectTrigger' => array('description', 'triggerid', 'expression'),
		'preservekeys' => true,
		'sortfield' => 'sortorder',
		'sortorder' => ZBX_SORT_UP
	));

	// expand trigger descriptions
	$triggers = zbx_objectValues($services, 'trigger');

	$triggers = CTriggerHelper::batchExpandDescription($triggers);

	foreach ($services as &$service) {
		if ($service['trigger']) {
			$service['trigger'] = $triggers[$service['trigger']['triggerid']];
		}
	}
	unset($service);

	// fetch sla
	$slaData = API::Service()->getSla(array(
		'intervals' => array(array(
			'from' => $period_start,
			'to' => $period_end
		))
	));

	// expand problem trigger descriptions
	foreach ($slaData as &$serviceSla) {
		foreach ($serviceSla['problems'] as &$problemTrigger) {
			$problemTrigger['description'] = $triggers[$problemTrigger['triggerid']]['description'];
		}
		unset($problemTrigger);
	}
	unset($serviceSla);

	$treeData = array();
	createServiceMonitoringTree($services, $slaData, $period, $treeData);
	$tree = new CServiceTree('service_status_tree',
		$treeData,
		array(
			'caption' => _('Service'),
			'status' => _('Status'),
			'reason' => _('Reason'),
			'sla' => _('Problem time'),
			'sla2' => nbsp(_('SLA').' / '._('Acceptable SLA'))
		)
	);

	if ($tree) {
		// creates form for choosing a preset interval
		$r_form = new CForm();
		$r_form->setAttribute('class', 'nowrap');
		$r_form->setMethod('get');
		$r_form->setAttribute('name', 'period_choice');
		$r_form->addVar('fullscreen', $_REQUEST['fullscreen']);

		$period_combo = new CComboBox('period', $period, 'javascript: submit();');
		foreach ($periods as $key => $val) {
			$period_combo->addItem($key, $val);
		}
		$r_form->addItem(array(_('Period').SPACE, $period_combo));

		$srv_wdgt = new CWidget('hat_services', 'service-list service-mon');
		$srv_wdgt->addPageHeader(_('IT SERVICES'), get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen'])));
		$srv_wdgt->addHeader(_('IT services'), $r_form);
		$srv_wdgt->addItem(BR());
		$srv_wdgt->addItem($tree->getHTML());
		$srv_wdgt->show();
	}
	else {
		error(_('Cannot format Tree. Check logic structure in service links.'));
	}
}
include_once('include/page_footer.php');

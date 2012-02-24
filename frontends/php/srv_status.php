<?php
/*
** Zabbix
** Copyright (C) 2001-2011 Zabbix SIA
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
?>
<?php
require_once('include/config.inc.php');
require_once('include/triggers.inc.php');
require_once('include/services.inc.php');

$page['title'] = _('IT services');
$page['file'] = 'srv_status.php';
$page['scripts'] = array();
$page['hist_arg'] = array();

define('ZBX_PAGE_DO_REFRESH', 1);

include_once('include/page_header.php');
?>
<?php
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

$available_triggers = get_accessible_triggers(PERM_READ_ONLY, array());

if (isset($_REQUEST['serviceid'])) {
	if ($service = DBfetch(DBselect('SELECT DISTINCT s.serviceid,s.triggerid FROM services s WHERE s.serviceid='.$_REQUEST['serviceid']))) {
		if ($service['triggerid'] && !isset($available_triggers[$service['triggerid']])) {
			access_deny();
		}
	}
	else {
		unset($service);
	}
}
unset($_REQUEST['serviceid']);

if (isset($service) && isset($_REQUEST['showgraph'])) {
	$table = new CTable(null, 'chart');
	$table->addRow(new CImg('chart5.php?serviceid='.$service['serviceid'].url_param('path')));
	$table->show();
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

	// create root service
	$services = array();
	$row = array(
		'id' => 0,
		'serviceid' => 0,
		'serviceupid' => 0,
		'caption' => _('root'),
		'status' => SPACE,
		'reason' => SPACE,
		'sla' => SPACE,
		'sla2' => SPACE,
		'graph' => SPACE,
		'linkid' => ''
	);
	$services[0] = $row;

	// get service reason
	$db_services_reasons = DBfetchArray(DBSelect(
		'SELECT s.triggerid,s.serviceid'.
		' FROM services s,triggers t'.
		' WHERE s.status>0'.
			' AND s.triggerid IS NOT NULL'.
			' AND t.triggerid=s.triggerid'.
			' AND '.DBcondition('t.triggerid', $available_triggers).
			' AND '.DBin_node('s.serviceid').
		' ORDER BY s.status DESC,t.description'
	));

	// get services
	$db_services = DBSelect(
		'SELECT DISTINCT s.serviceid,sl.servicedownid,sl_p.serviceupid AS serviceupid,s.triggerid,'.
			' s.name AS caption,s.algorithm,t.description,t.expression,s.sortorder,sl.linkid,s.showsla,s.goodsla,s.status'.
		' FROM services s'.
			' LEFT JOIN triggers t ON s.triggerid=t.triggerid'.
			' LEFT JOIN services_links sl ON s.serviceid=sl.serviceupid AND NOT sl.soft=0'.
			' LEFT JOIN services_links sl_p ON s.serviceid=sl_p.servicedownid AND sl_p.soft=0'.
		' WHERE '.DBin_node('s.serviceid').
			' AND (t.triggerid IS NULL OR '.DBcondition('t.triggerid', $available_triggers).')'.
		' ORDER BY s.sortorder,sl_p.serviceupid,s.serviceid'
	);
	while ($row = DBFetch($db_services)) {
		$row['id'] = $row['serviceid'];
		$row['caption'] = array(get_node_name_by_elid($row['serviceid'], null, ': '), $row['caption']);
		$row['graph'] = !empty($row['triggerid']) ? new CLink(_('Show'), 'srv_status.php?serviceid='.$row['serviceid'].'&showgraph=1'.url_param('path')) : '-';

		if (empty($row['serviceupid'])) {
			$row['serviceupid'] = '0';
		}
		if (empty($row['description'])) {
			$row['description'] = _('None');
		}

		if (isset($row['triggerid']) && !empty($row['triggerid'])) {
			$url = new CLink(expand_trigger_description($row['triggerid']), 'events.php?source='.EVENT_SOURCE_TRIGGERS.'&triggerid='.$row['triggerid']);
			$row['caption'] = array($row['caption'], ' [', $url, ']');
		}

		if ($row['status'] == 0 || (isset($service) && (bccomp($service['serviceid'], $row['serviceid']) == 0))) {
			$row['reason'] = '-';
		}
		else {
			$row['reason'] = '-';
			foreach ($db_services_reasons as $services_reason) {
				if (is_string($row['reason']) && $row['reason'] == '-') {
					$row['reason'] = new CList(null, 'itservices');
				}
				if (does_service_depend_on_the_service($row['serviceid'], $services_reason['serviceid'])) {
					$row['reason']->addItem(new CLink(expand_trigger_description($services_reason['triggerid']), 'events.php?source='.EVENT_SOURCE_TRIGGERS.'&triggerid='.$services_reason['triggerid']));
				}
			}
		}

		if ($row['showsla'] == 1) {
			$stat = calculateServiceAvailability($row['serviceid'], $period_start, $period_end);

			$p = min($stat['problem'], 20);
			$sla_style = $row['goodsla'] > $stat['ok'] ? 'on' : 'off';

			$sizeX = 160;
			$sizeY = 15;
			$sizeX_red = $sizeX * $p / 20;
			$sizeX_green = $sizeX - $sizeX_red;

			$sla_tab = new CTable(null, 'invisible');

			$chart1 = null;
			if ($sizeX_green > 0) {
				$chart1 = new CDiv(null, 'sla_green');
				$chart1->setAttribute('style', 'width: '.$sizeX_green.'px;');
				$chart1 = new CLink($chart1, 'report3.php?serviceid='.$row['serviceid'].'&year='.date('Y'), 'image');
			}
			$chart2 = null;
			if ($sizeX_red > 0) {
				$chart2 = new CDiv(null, 'sla_red');
				$chart2->setAttribute('style', 'width: '.$sizeX_red.'px;');
				$chart2 = new CLink($chart2, 'report3.php?serviceid='.$row['serviceid'].'&year='.date('Y'), 'image');
			}

			$text = new CLink(sprintf('%.2f', $stat['problem']), 'report3.php?serviceid='.$row['serviceid'].'&year='.date('Y'), $sla_style);
			$sla_tab->addRow(array($chart1, $chart2, SPACE, $text));

			$row['sla'] = $sla_tab;

			if ($row['goodsla'] > $stat['ok']) {
				$sla_style = 'red';
			}
			else {
				$sla_style = 'green';
			}

			$row['sla2'] = array(new CSpan(sprintf('%.2f', $row['goodsla']), 'green'), '/', new CSpan(sprintf('%.2f', $stat['ok']), $sla_style));
		}
		else {
			$row['sla'] = '-';
			$row['sla2'] = '-';
		}

		if (isset($services[$row['serviceid']])) {
			$services[$row['serviceid']] = zbx_array_merge($services[$row['serviceid']], $row);
		}
		else {
			$services[$row['serviceid']] = $row;
		}

		if (isset($row['serviceupid'])) {
			$services[$row['serviceupid']]['childs'][] = array('id' => $row['serviceid'], 'soft' => 0, 'linkid' => 0);
		}

		if (isset($row['servicedownid'])) {
			$services[$row['serviceid']]['childs'][] = array('id' => $row['servicedownid'], 'soft' => 1, 'linkid' => $row['linkid']);
		}
	}

	$treeServ = array();
	createShowServiceTree($services, $treeServ);

	// permission issue
	$treeServ = del_empty_nodes($treeServ);

	$tree = new CTree('service_status_tree',
		$treeServ,
		array(
			'caption' => _('Service'),
			'status' => _('Status'),
			'reason' => _('Reason'),
			'sla' => 'SLA ('.$periods[$period].')',
			'sla2' => nbsp(_('SLA')),
			'graph' => _('Graph')
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

		$srv_wdgt = new CWidget('hat_services');
		$srv_wdgt->addPageHeader(_('IT SERVICES'), get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen'])));
		$srv_wdgt->addHeader(_('IT SERVICES'), $r_form);
		$srv_wdgt->addItem(BR());
		$srv_wdgt->addItem($tree->getHTML());
		$srv_wdgt->show();
	}
	else {
		error(_('Cannot format Tree. Check logic structure in service links'));
	}
}
include_once('include/page_footer.php');
?>

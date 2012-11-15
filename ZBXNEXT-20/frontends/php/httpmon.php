<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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

$page['title'] = _('Status of Web monitoring');
$page['file'] = 'httpmon.php';
$page['hist_arg'] = array('groupid', 'hostid');

define('ZBX_PAGE_DO_REFRESH', 1);

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR				TYPE		OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'fullscreen' =>		array(T_ZBX_INT,	O_OPT,		P_SYS,	IN('0,1'),	null),
	'groupid' =>		array(T_ZBX_INT,	O_OPT,		P_SYS,	DB_ID,		null),
	'hostid' =>		array(T_ZBX_INT,	O_OPT,		P_SYS,	DB_ID,		null),
);
check_fields($fields);

validate_sort_and_sortorder('name', ZBX_SORT_DOWN);

$options = array(
	'groups' => array(
		'monitored_hosts' => 1,
		'with_monitored_httptests' => 1,
	),
	'hosts' => array(
		'monitored_hosts' => 1,
		'with_monitored_httptests' => 1,
	),
	'hostid' => get_request('hostid', null),
	'groupid' => get_request('groupid', null),
);
$pageFilter = new CPageFilter($options);
$_REQUEST['groupid'] = $pageFilter->groupid;
$_REQUEST['hostid'] = $pageFilter->hostid;


$r_form = new CForm('get');
$r_form->addVar('fullscreen',$_REQUEST['fullscreen']);
$r_form->addItem(array(_('Group').SPACE,$pageFilter->getGroupsCB(true)));
$r_form->addItem(array(SPACE._('Host').SPACE,$pageFilter->getHostsCB(true)));

$httpmon_wdgt = new CWidget();
$httpmon_wdgt->addPageHeader(
	_('STATUS OF WEB MONITORING'),
	get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']))
);
$httpmon_wdgt->addHeader(_('Web checks'), $r_form);

// TABLE
$table = new CTableInfo(_('No web checks defined.'));
$table->SetHeader(array(
	$_REQUEST['hostid'] == 0 ? make_sorting_header(_('Host'), 'hostname') : null,
	make_sorting_header(_('Name'), 'name'),
	_('Number of steps'),
	_('Last check'),
	_('Status')
));
$paging = null;


if ($pageFilter->hostsSelected) {
	$options = array(
		'editable' => true,
		'output' => array('httptestid'),
		'limit' => $config['search_limit'] + 1
	);
	if ($pageFilter->hostid > 0) {
		$options['hostids'] = $pageFilter->hostid;
	}
	elseif ($pageFilter->groupid > 0) {
		$options['groupids'] = $pageFilter->groupid;
	}
	$httpTests = API::HttpTest()->get($options);

	$paging = getPagingLine($httpTests);

	$dbHttpTests = DBselect(
		'SELECT ht.httptestid,ht.name,ht.delay,ht.status,ht.hostid,ht.templateid,h.name AS hostname'.
			' FROM httptest ht'.
			' INNER JOIN httpstep hs ON hs.httptestid=ht.httptestid'.
			' INNER JOIN hosts h ON h.hostid=ht.hostid'.
			' WHERE '.DBcondition('ht.httptestid', zbx_objectValues($httpTests, 'httptestid')).
				' AND h.status='.HOST_STATUS_MONITORED.
				' AND ht.status='.HTTPTEST_STATUS_ACTIVE
	);
	$httpTests = array();
	while ($dbHttpTest = DBfetch($dbHttpTests)) {
		$httpTests[$dbHttpTest['httptestid']] = $dbHttpTest;
	}

	$dbHttpSteps = DBselect(
		'SELECT hs.httptestid,COUNT(hs.httpstepid) AS stepscnt'.
				' FROM httpstep hs'.
				' WHERE '.DBcondition('hs.httptestid', zbx_objectValues($httpTests, 'httptestid')).
				' GROUP BY hs.httptestid'
	);
	while ($dbHttpStep = DBfetch($dbHttpSteps)) {
		$httpTests[$dbHttpStep['httptestid']]['stepscnt'] = $dbHttpStep['stepscnt'];
	}

	$httpTests = resolveHttpTestMacros($httpTests, true, false);

	order_result($httpTests, getPageSortField('name'), getPageSortOrder());

	$dbSteps = DBselect(
		'SELECT hti.httptestid,hti.type,i.lastvalue,i.lastclock'.
				' FROM httptestitem hti,items i'.
				' WHERE hti.itemid=i.itemid'.
				' AND hti.type IN ('.HTTPSTEP_ITEM_TYPE_LASTSTEP.','.HTTPSTEP_ITEM_TYPE_LASTERROR.')'.
				' AND i.lastclock IS NOT NULL'.
				' AND '.DBcondition('hti.httptestid', array_keys($httpTests))
	);
	while ($dbStep = DBfetch($dbSteps)) {
		if ($dbStep['type'] == HTTPSTEP_ITEM_TYPE_LASTSTEP) {
			if (!isset($httpTests[$dbStep['httptestid']]['lastcheck'])) {
				$httpTests[$dbStep['httptestid']]['lastcheck'] = $dbStep['lastclock'];
			}
			$httpTests[$dbStep['httptestid']]['lastfailedstep'] = $dbStep['lastvalue'];
		}
		else {
			$httpTests[$dbStep['httptestid']]['error'] = $dbStep['lastvalue'];
		}
	}


	foreach($httpTests as $httpTest) {
		$lastcheck = isset($httpTest['lastcheck']) ? zbx_date2str(_('d M Y H:i:s'), $httpTest['lastcheck']) : _('Never');

		if (!isset($httpTest['lastcheck'])) {
			$status['msg'] = _('Unknown');
			$status['style'] = 'unknown';
		}
		elseif ($httpTest['lastfailedstep'] != 0) {
			$step_data = get_httpstep_by_no($httpTest['httptestid'], $httpTest['lastfailedstep']);
			$status['msg'] = _s('Step "%1$s" [%2$s of %3$s] failed: %4$s', $step_data['name'],
				$httpTest['lastfailedstep'], $httpTest['stepscnt'], $httpTest['error']);
			$status['style'] = 'disabled';
		}
		else {
			$status['msg'] = _('OK');
			$status['style'] = 'enabled';
		}

		$table->addRow(new CRow(array(
			($_REQUEST['hostid'] > 0) ? null : $httpTest['hostname'],
			new CLink($httpTest['name'], 'httpdetails.php?httptestid='.$httpTest['httptestid']),
			$httpTest['stepscnt'],
			$lastcheck,
			new CSpan($status['msg'], $status['style'])
		)));
	}
}

$httpmon_wdgt->addItem(array($paging, $table, $paging));
$httpmon_wdgt->show();


require_once dirname(__FILE__).'/include/page_footer.php';

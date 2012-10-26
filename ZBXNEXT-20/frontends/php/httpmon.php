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
$page['hist_arg'] = array('open','groupid','hostid');

define('ZBX_PAGE_DO_REFRESH', 1);

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR				TYPE		OPTIONAL	FLAGS	VALIDATION	EXCEPTION
	$fields = array(
		'applications' =>	array(T_ZBX_INT,	O_OPT,		null,	DB_ID,		null),
		'applicationid' =>	array(T_ZBX_INT,	O_OPT,		null,	DB_ID,		null),
		'close' =>		array(T_ZBX_INT,	O_OPT,		null,	IN('1'),	null),
		'open' =>		array(T_ZBX_INT,	O_OPT,		null,	IN('1'),	null),
		'fullscreen' =>		array(T_ZBX_INT,	O_OPT,		P_SYS,	IN('0,1'),	null),
		'groupid' =>		array(T_ZBX_INT,	O_OPT,		P_SYS,	DB_ID,		null),
		'hostid' =>		array(T_ZBX_INT,	O_OPT,		P_SYS,	DB_ID,		null),
//ajax
		'favobj' =>		array(T_ZBX_STR,	O_OPT,		P_ACT,	null,		null),
		'favref' =>		array(T_ZBX_STR,	O_OPT,		P_ACT,	NOT_EMPTY,	'isset({favobj})'),
		'favstate' =>		array(T_ZBX_INT,	O_OPT,		P_ACT,	NOT_EMPTY,	'isset({favobj})')
	);

	check_fields($fields);

/* AJAX	*/
	if (isset($_REQUEST['favobj'])) {
		if ('hat' == $_REQUEST['favobj']) {
			CProfile::update('web.httpmon.hats.'.$_REQUEST['favref'].'.state',$_REQUEST['favstate'], PROFILE_TYPE_INT);
		}
	}

	if (PAGE_TYPE_JS == $page['type'] || PAGE_TYPE_HTML_BLOCK == $page['type']) {
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit();
	}
//--------
	validate_sort_and_sortorder('wt.name',ZBX_SORT_DOWN);

	$_REQUEST['applications'] = get_request('applications', CFavorite::get('web.httpmon.applications'));
	$_REQUEST['applications'] = zbx_objectValues($_REQUEST['applications'], 'value');

	if (isset($_REQUEST['open'])) {
		if (!isset($_REQUEST['applicationid'])) {
			$_REQUEST['applications'] = array();
			$show_all_apps = 1;
		}
		elseif (!uint_in_array($_REQUEST['applicationid'], $_REQUEST['applications'])) {
			array_push($_REQUEST['applications'],$_REQUEST['applicationid']);
		}

	}
	elseif (isset($_REQUEST['close'])) {
		if (!isset($_REQUEST['applicationid'])) {
			$_REQUEST['applications'] = array();
		}
		elseif (($i = array_search($_REQUEST['applicationid'], $_REQUEST['applications'])) !== FALSE) {
			unset($_REQUEST['applications'][$i]);
		}
	}

	if (count($_REQUEST['applications']) > 25) {
		$_REQUEST['applications'] = array_slice($_REQUEST['applications'], -25);
	}
	CFavorite::remove('web.httpmon.applications');
	foreach ($_REQUEST['applications'] as $application) {
		CFavorite::add('web.httpmon.applications', $application);
	}


	$httpmon_wdgt = new CWidget();
// Table HEADER
	$fs_icon = get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']));
	$httpmon_wdgt->addPageHeader(_('STATUS OF WEB MONITORING'), $fs_icon);

// 2nd header
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

	$available_hosts = $pageFilter->hostsSelected ? array_keys($pageFilter->hosts) : array();

	$r_form = new CForm('get');
	$r_form->addVar('fullscreen',$_REQUEST['fullscreen']);

	$r_form->addItem(array(_('Group').SPACE,$pageFilter->getGroupsCB(true)));
	$r_form->addItem(array(SPACE._('Host').SPACE,$pageFilter->getHostsCB(true)));

	$httpmon_wdgt->addHeader(_('Web checks'), $r_form);
	$httpmon_wdgt->addItem(SPACE);

// TABLE
	$form = new CForm('get');
	$form->setName('scenarios');
	$form->addVar('hostid', $_REQUEST['hostid']);

	if (isset($show_all_apps)) {
		$link = new CLink(new CImg('images/general/minus.png'),'?close=1'.url_param('groupid').url_param('hostid'));
	}
	else {
		$link = new CLink(new CImg('images/general/plus.png'),'?open=1'.url_param('groupid').url_param('hostid'));
	}

	$table = new CTableInfo(_('No web checks defined.'));
	$table->SetHeader(array(
		is_show_all_nodes() ? make_sorting_header(_('Node'), 'h.hostid') : null,
		$_REQUEST['hostid'] == 0 ? make_sorting_header(_('Host'), 'h.name') : null,
		make_sorting_header(array($link, SPACE, _('Name')), 'wt.name'),
		_('Number of steps'),
		_('Last check'),
		_('Status')
	));

	$any_app_exist = false;

	$db_apps = array();
	$db_appids = array();

	$sql_where = '';
	if ($_REQUEST['hostid'] > 0) {
		$sql_where = ' AND h.hostid='.$_REQUEST['hostid'];
	}

	$result = DBselect(
			'SELECT DISTINCT h.name as hostname,h.hostid,a.*'.
			' FROM applications a,hosts h'.
			' WHERE a.hostid=h.hostid'.
				$sql_where.
				' AND '.DBcondition('h.hostid',$available_hosts).
			order_by('a.applicationid,h.name,h.hostid','a.name')
	);
	while ($row = DBfetch($result)) {
		$row['scenarios_cnt'] = 0;

		$db_apps[$row['applicationid']] = $row;
		$db_appids[$row['applicationid']] = $row['applicationid'];
	}

	$db_httptests = array();
	$db_httptestids = array();

	$result = DBselect(
			'SELECT wt.*,a.name as application,h.name as hostname,h.hostid'.
			' FROM httptest wt,applications a,hosts h'.
			' WHERE wt.applicationid=a.applicationid'.
				' AND a.hostid=h.hostid'.
				' AND '.DBcondition('a.applicationid', $db_appids).
				' AND wt.status<>1'.
			order_by('wt.name', 'h.host')
	);
	while ($row = DBfetch($result)) {
		$row['step_count'] = null;
		$row['lastfailedstep'] = 0;
		$row['error'] = '';
		$db_apps[$row['applicationid']]['scenarios_cnt']++;

		$db_httptests[$row['httptestid']] = $row;
		$db_httptestids[$row['httptestid']] = $row['httptestid'];
	}

	$result = DBselect(
			'SELECT hs.httptestid,COUNT(hs.httpstepid) AS cnt'.
			' FROM httpstep hs'.
			' WHERE '.DBcondition('hs.httptestid', $db_httptestids).
			' GROUP BY hs.httptestid'
	);
	while ($row = DBfetch($result)) {
		$db_httptests[$row['httptestid']]['step_count'] = $row['cnt'];
	}

	$result = DBselect(
			'SELECT hti.httptestid,hti.type,i.lastvalue,i.lastclock'.
			' FROM httptestitem hti,items i'.
			' WHERE hti.itemid=i.itemid'.
				' AND hti.type IN ('.HTTPSTEP_ITEM_TYPE_LASTSTEP.','.HTTPSTEP_ITEM_TYPE_LASTERROR.')'.
				' AND i.lastclock IS NOT NULL'.
				' AND '.DBcondition('hti.httptestid', $db_httptestids)
	);
	while ($row = DBfetch($result)) {
		if ($row['type'] == HTTPSTEP_ITEM_TYPE_LASTSTEP) {
			if (!isset($db_httptests[$row['httptestid']]['lastcheck'])) {
				$db_httptests[$row['httptestid']]['lastcheck'] = $row['lastclock'];
			}
			$db_httptests[$row['httptestid']]['lastfailedstep'] = $row['lastvalue'];
		}
		else {
			$db_httptests[$row['httptestid']]['error'] = $row['lastvalue'];
		}
	}

	$tab_rows = array();
	foreach ($db_httptests as $httptest_data) {
		$db_app = &$db_apps[$httptest_data['applicationid']];

		if (!isset($tab_rows[$db_app['applicationid']])) {
			$tab_rows[$db_app['applicationid']] = array();
		}
		$app_rows = &$tab_rows[$db_app['applicationid']];

		if (!uint_in_array($db_app['applicationid'], $_REQUEST['applications']) && !isset($show_all_apps)) {
			continue;
		}

		$name = array();
		array_push($name, new CLink($httptest_data['name'],'httpdetails.php?httptestid='.$httptest_data['httptestid']));

		if (isset($httptest_data['lastcheck'])) {
			$lastcheck = zbx_date2str(_('d M Y H:i:s'), $httptest_data['lastcheck']);
		}
		else {
			$lastcheck = new CCol(_('Never'));
		}

		if (!isset($httptest_data['lastcheck'])) {
			$status['msg'] = _('Unknown');
			$status['style'] = 'unknown';
		}
		elseif ($httptest_data['lastfailedstep'] != 0) {
			$step_data = get_httpstep_by_no($httptest_data['httptestid'], $httptest_data['lastfailedstep']);
			$status['msg'] = _s('Step "%1$s" [%2$s of %3$s] failed: %4$s', $step_data['name'],
				$httptest_data['lastfailedstep'], $httptest_data['step_count'], $httptest_data['error']);
			$status['style'] = 'disabled';
		}
		else {
			$status['msg'] = _('OK');
			$status['style'] = 'enabled';
		}

		array_push($app_rows, new CRow(array(
			is_show_all_nodes() ? SPACE : null,
			($_REQUEST['hostid'] > 0) ? null : SPACE,
			array(str_repeat(SPACE,6), $name),
			$httptest_data['step_count'],
			$lastcheck,
			new CSpan($status['msg'], $status['style'])
			)));
	}
	unset($app_rows);
	unset($db_app);

	foreach ($tab_rows as $appid => $app_rows) {
		$db_app = &$db_apps[$appid];

		if (uint_in_array($db_app['applicationid'], $_REQUEST['applications']) || isset($show_all_apps)) {
			$link = new CLink(new CImg('images/general/minus.png'),
				'?close=1&applicationid='.$db_app['applicationid'].
				url_param('groupid').url_param('hostid').url_param('applications').
				url_param('select'));
		}
		else {
			$link = new CLink(new CImg('images/general/plus.png'),
				'?open=1&applicationid='.$db_app['applicationid'].
				url_param('groupid').url_param('hostid').url_param('applications').
				url_param('select'));
		}

		$col = new CCol(array(
			$link,
			SPACE,
			bold($db_app['name']),
			SPACE.'('._n('%1$d scenario', '%1$d scenarios', $db_app['scenarios_cnt']).')'
		));
		$col->setColSpan(6);

		$table->addRow(array(
				get_node_name_by_elid($db_app['applicationid']),
				($_REQUEST['hostid'] > 0) ? null : $db_app['hostname'],
				$col
			));

		$any_app_exist = true;

		foreach ($app_rows as $row) {
			$table->addRow($row);
		}
	}

	$form->addItem($table);

	$httpmon_wdgt->addItem($form);

	$httpmon_wdgt->show();


require_once dirname(__FILE__).'/include/page_footer.php';

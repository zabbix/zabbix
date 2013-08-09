<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
require_once dirname(__FILE__).'/include/audit.inc.php';
require_once dirname(__FILE__).'/include/actions.inc.php';
require_once dirname(__FILE__).'/include/users.inc.php';

$page['title'] = _('Audit actions');
$page['file'] = 'auditacts.php';
$page['hist_arg'] = array();
$page['scripts'] = array('class.calendar.js', 'gtlc.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	// filter
	'filter_rst' =>	array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null),
	'filter_set' =>	array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'alias' =>		array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'period' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'dec' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'inc' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'left' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'right' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'stime' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	// ajax
	'favobj' =>		array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favref' =>		array(T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})&&"filter"=={favobj}'),
	'favstate' =>	array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})&&"filter"=={favobj}'),
	'favid' =>		array(T_ZBX_INT, O_OPT, P_ACT,	null,		null)
);
check_fields($fields);

/*
 * Ajax
 */
if (isset($_REQUEST['favobj'])) {
	if ($_REQUEST['favobj'] == 'filter') {
		CProfile::update('web.auditacts.filter.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}
	// saving fixed/dynamic setting to profile
	if ($_REQUEST['favobj'] == 'timelinefixedperiod') {
		if (isset($_REQUEST['favid'])) {
			CProfile::update('web.auditacts.timelinefixed', $_REQUEST['favid'], PROFILE_TYPE_INT);
		}
	}
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

/*
 * Filter
 */
$_REQUEST['alias'] = isset($_REQUEST['filter_rst'])
	? ''
	: get_request('alias', CProfile::get('web.auditacts.filter.alias', ''));

if (isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst'])) {
	CProfile::update('web.auditacts.filter.alias', $_REQUEST['alias'], PROFILE_TYPE_STR);
}

/*
 * Display
 */
$effectivePeriod = navigation_bar_calc('web.auditacts.timeline', 0, true);
$data = array(
	'stime' => get_request('stime'),
	'alias' => get_request('alias'),
	'alerts' => array()
);

$from = zbxDateToTime($data['stime']);
$till = $from + $effectivePeriod;

$user = null;
$queryData = true;
$firstAlert = null;

if ($data['alias']) {
	$user = API::User()->get(array(
		'output' => array('userid'),
		'filter' => array('alias' => $data['alias'])
	));

	if ($user) {
		$user = reset($user);
	}
	else {
		$queryData = false;
	}
}

// fetch alerts for different objects and sources and combine them in a single stream
if ($queryData) {
	foreach (eventSourceObjects() as $eventSource) {
		$data['alerts'] = array_merge($data['alerts'], API::Alert()->get(array(
			'output' => API_OUTPUT_EXTEND,
			'selectMediatypes' => API_OUTPUT_EXTEND,
			'userids' => $data['alias'] ? $user['userid'] : null,
			'time_from' => $from,
			'time_till' => $till,
			'eventsource' => $eventSource['source'],
			'eventobject' => $eventSource['object'],
			'limit' => $config['search_limit'] + 1
		)));
	}

	CArrayHelper::sort($data['alerts'], array(
		array('field' => 'alertid', 'order' => ZBX_SORT_DOWN)
	));

	$data['alerts'] = array_slice($data['alerts'], 0, $config['search_limit'] + 1);

	// get first alert
	if ($user) {
		$firstAlert = DBfetch(DBselect(
			'SELECT MIN(a.clock) AS clock'.
			' FROM alerts a'.
			' WHERE a.userid='.$user['userid']
		));
	}
}

// padding
$data['paging'] = getPagingLine($data['alerts']);

// timeline
$data['timeline'] = array(
	'period' => $effectivePeriod,
	'starttime' => date(TIMESTAMP_FORMAT, ($firstAlert ? $firstAlert['clock'] : ZBX_MAX_PERIOD)),
	'usertime' => isset($data['stime']) ? date(TIMESTAMP_FORMAT, zbxDateToTime($data['stime']) + $effectivePeriod) : null
);

// render view
$auditView = new CView('administration.auditacts.list', $data);
$auditView->render();
$auditView->show();

require_once dirname(__FILE__).'/include/page_footer.php';

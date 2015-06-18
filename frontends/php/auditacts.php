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
require_once dirname(__FILE__).'/include/audit.inc.php';
require_once dirname(__FILE__).'/include/actions.inc.php';
require_once dirname(__FILE__).'/include/users.inc.php';

$page['title'] = _('Action log');
$page['file'] = 'auditacts.php';
$page['scripts'] = ['class.calendar.js', 'gtlc.js'];
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	// filter
	'filter_rst' =>	[T_ZBX_STR, O_OPT, P_SYS,	null,	null],
	'filter_set' =>	[T_ZBX_STR, O_OPT, P_SYS,	null,	null],
	'alias' =>		[T_ZBX_STR, O_OPT, P_SYS,	null,	null],
	'period' =>		[T_ZBX_INT, O_OPT, null,	null,	null],
	'stime' =>		[T_ZBX_STR, O_OPT, null,	null,	null],
	// ajax
	'favobj' =>		[T_ZBX_STR, O_OPT, P_ACT,	null,	null],
	'favid' =>		[T_ZBX_INT, O_OPT, P_ACT,	null,	null]
];
check_fields($fields);

/*
 * Ajax
 */
if (isset($_REQUEST['favobj'])) {
	// saving fixed/dynamic setting to profile
	if ($_REQUEST['favobj'] == 'timelinefixedperiod') {
		if (isset($_REQUEST['favid'])) {
			CProfile::update('web.auditacts.timelinefixed', $_REQUEST['favid'], PROFILE_TYPE_INT);
		}
	}
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Filter
 */
if (hasRequest('filter_set')) {
	CProfile::update('web.auditacts.filter.alias', getRequest('alias', ''), PROFILE_TYPE_STR);
}
elseif (hasRequest('filter_rst')) {
	DBStart();
	CProfile::delete('web.auditacts.filter.alias');
	DBend();
}

/*
 * Display
 */
$effectivePeriod = navigation_bar_calc('web.auditacts.timeline', 0, true);

$data = [
	'stime' => getRequest('stime'),
	'alias' => CProfile::get('web.auditacts.filter.alias', ''),
	'users' => [],
	'alerts' => [],
	'paging' => null
];

$userId = null;

if ($data['alias']) {
	$data['users'] = API::User()->get([
		'output' => ['userid', 'alias', 'name', 'surname'],
		'filter' => ['alias' => $data['alias']],
		'preservekeys' => true
	]);

	if ($data['users']) {
		$user = reset($data['users']);

		$userId = $user['userid'];
	}
}

if (!$data['alias'] || $data['users']) {
	$from = zbxDateToTime($data['stime']);
	$till = $from + $effectivePeriod;

	$config = select_config();

	// fetch alerts for different objects and sources and combine them in a single stream
	foreach (eventSourceObjects() as $eventSource) {
		$data['alerts'] = array_merge($data['alerts'], API::Alert()->get([
			'output' => ['alertid', 'actionid', 'userid', 'clock', 'sendto', 'subject', 'message', 'status',
				'retries', 'error', 'alerttype'
			],
			'selectMediatypes' => ['mediatypeid', 'description'],
			'userids' => $userId,
			'time_from' => $from,
			'time_till' => $till,
			'eventsource' => $eventSource['source'],
			'eventobject' => $eventSource['object'],
			'sortfield' => 'alertid',
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => $config['search_limit'] + 1
		]));
	}

	CArrayHelper::sort($data['alerts'], [
		['field' => 'alertid', 'order' => ZBX_SORT_DOWN]
	]);

	$data['alerts'] = array_slice($data['alerts'], 0, $config['search_limit'] + 1);

	// paging
	$data['paging'] = getPagingLine($data['alerts'], ZBX_SORT_DOWN);

	// get users
	if (!$data['alias']) {
		$data['users'] = API::User()->get([
			'output' => ['userid', 'alias', 'name', 'surname'],
			'userids' => zbx_objectValues($data['alerts'], 'userid'),
			'preservekeys' => true
		]);
	}
}

// get first alert clock
$firstAlert = null;
if ($userId) {
	$firstAlert = DBfetch(DBselect(
		'SELECT MIN(a.clock) AS clock'.
		' FROM alerts a'.
		' WHERE a.userid='.zbx_dbstr($userId)
	));
}
elseif ($data['alias'] === '') {
	$firstAlert = DBfetch(DBselect('SELECT MIN(a.clock) AS clock FROM alerts a'));
}
$minStartTime = ($firstAlert) ? $firstAlert['clock'] : null;

// get actions names
if ($data['alerts']) {
	$data['actions'] = API::Action()->get([
		'output' => ['actionid', 'name'],
		'actionids' => array_unique(zbx_objectValues($data['alerts'], 'actionid')),
		'preservekeys' => true
	]);
}

// timeline
$data['timeline'] = [
	'period' => $effectivePeriod,
	'starttime' => date(TIMESTAMP_FORMAT, $minStartTime),
	'usertime' => $data['stime'] ? date(TIMESTAMP_FORMAT, zbxDateToTime($data['stime']) + $effectivePeriod) : null
];

// render view
$auditView = new CView('administration.auditacts.list', $data);
$auditView->render();
$auditView->show();

require_once dirname(__FILE__).'/include/page_footer.php';

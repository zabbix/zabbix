<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
$page['scripts'] = ['class.calendar.js', 'gtlc.js', 'flickerfreescreen.js'];
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	// filter
	'filter_rst' =>		[T_ZBX_STR,			O_OPT, P_SYS,	null,	null],
	'filter_set' =>		[T_ZBX_STR,			O_OPT, P_SYS,	null,	null],
	'filter_userids' =>	[T_ZBX_STR,			O_OPT, P_SYS,	null,	null],
	'from' =>			[T_ZBX_RANGE_TIME,	O_OPT, P_SYS,	null,	null],
	'to' =>				[T_ZBX_RANGE_TIME,	O_OPT, P_SYS,	null,	null]
];
check_fields($fields);
validateTimeSelectorPeriod(getRequest('from'), getRequest('to'));

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Filter
 */
if (hasRequest('filter_set')) {
	CProfile::updateArray('web.auditacts.filter.userids', getRequest('filter_userids', []), PROFILE_TYPE_ID);
}
elseif (hasRequest('filter_rst')) {
	DBStart();
	CProfile::deleteIdx('web.auditacts.filter.userids');
	DBend();
}

/*
 * Display
 */
$timeselector_options = [
	'profileIdx' => 'web.auditacts.filter',
	'profileIdx2' => 0,
	'from' => getRequest('from'),
	'to' => getRequest('to')
];
updateTimeSelectorPeriod($timeselector_options);

$data = [
	'filter_userids' => CProfile::getArray('web.auditacts.filter.userids', []),
	'users' => [],
	'alerts' => [],
	'paging' => null,
	'timeline' => getTimeSelectorPeriod($timeselector_options),
	'active_tab' => CProfile::get('web.auditacts.filter.active', 1)
];

$userids = [];

if ($data['filter_userids']) {
	$data['users'] = API::User()->get([
		'output' => ['userid', 'username', 'name', 'surname'],
		'userids' => $data['filter_userids'],
		'preservekeys' => true
	]);

	$userids = array_column($data['users'], 'userid');

	// Sanitize userids for multiselect.
	$data['filter_userids'] = array_map(function (array $value): array {
		return ['id' => $value['userid'], 'name' => getUserFullname($value)];
	}, $data['users']);

	CArrayHelper::sort($data['filter_userids'], ['name']);
}

if (!$data['filter_userids'] || $data['users']) {
	// Fetch alerts for different objects and sources and combine them in a single stream.
	$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
	foreach (eventSourceObjects() as $eventSource) {
		$data['alerts'] = array_merge($data['alerts'], API::Alert()->get([
			'output' => ['alertid', 'actionid', 'userid', 'clock', 'sendto', 'subject', 'message', 'status',
				'retries', 'error', 'alerttype'
			],
			'selectMediatypes' => ['mediatypeid', 'name', 'maxattempts'],
			'userids' => $userids ? $userids : null,
			// API::Alert operates with 'open' time interval therefore before call have to alter 'from' and 'to' values.
			'time_from' => $data['timeline']['from_ts'] - 1,
			'time_till' => $data['timeline']['to_ts'] + 1,
			'eventsource' => $eventSource['source'],
			'eventobject' => $eventSource['object'],
			'sortfield' => 'alertid',
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => $limit
		]));
	}

	CArrayHelper::sort($data['alerts'], [
		['field' => 'alertid', 'order' => ZBX_SORT_DOWN]
	]);

	$data['alerts'] = array_slice($data['alerts'], 0, CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1);

	$data['paging'] = CPagerHelper::paginate(getRequest('page', 1), $data['alerts'], ZBX_SORT_DOWN,
		new CUrl('auditacts.php')
	);

	// Get users.
	if (!$data['filter_userids']) {
		$data['users'] = API::User()->get([
			'output' => ['userid', 'username', 'name', 'surname'],
			'userids' => array_column($data['alerts'], 'userid'),
			'preservekeys' => true
		]);
	}
}

// Get actions names.
if ($data['alerts']) {
	$data['actions'] = API::Action()->get([
		'output' => ['actionid', 'name'],
		'actionids' => array_unique(array_column($data['alerts'], 'actionid')),
		'preservekeys' => true
	]);
}

echo (new CView('administration.auditacts.list', $data))->getOutput();

require_once dirname(__FILE__).'/include/page_footer.php';

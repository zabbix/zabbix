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
require_once dirname(__FILE__).'/include/actions.inc.php';
require_once dirname(__FILE__).'/include/events.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/users.inc.php';
require_once dirname(__FILE__).'/include/html.inc.php';

$page['title'] = _('Event details');
$page['file'] = 'tr_events.php';
$page['type'] = detect_page_type();
$page['scripts'] = ['layout.mode.js', 'class.calendar.js'];
$page['web_layout_mode'] = CViewHelper::loadLayoutMode();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'triggerid' =>	[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		PAGE_TYPE_HTML.'=='.$page['type']],
	'eventid' =>	[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		PAGE_TYPE_HTML.'=='.$page['type']],
	// Ajax
	'widget' =>		[T_ZBX_STR, O_OPT, P_ACT,	IN('"'.WIDGET_HAT_EVENTACTIONS.'","'.WIDGET_HAT_EVENTLIST.'"'), null],
	'state' =>		[T_ZBX_INT, O_OPT, P_ACT,	IN('0,1'), null]
];
check_fields($fields);

/*
 * Ajax
 */
if (hasRequest('widget') && hasRequest('state')) {
	CProfile::update('web.tr_events.hats.'.getRequest('widget').'.state', getRequest('state'), PROFILE_TYPE_INT);
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

// triggers
$triggers = API::Trigger()->get([
	'output' => API_OUTPUT_EXTEND,
	'selectHosts' => API_OUTPUT_EXTEND,
	'triggerids' => getRequest('triggerid')
]);

if (!$triggers) {
	access_deny();
}

$trigger = reset($triggers);

$events = API::Event()->get([
	'output' => ['eventid', 'r_eventid', 'clock', 'ns', 'objectid', 'name', 'acknowledged', 'severity'],
	'selectTags' => ['tag', 'value'],
	'select_acknowledges' => ['clock', 'message', 'action', 'userid', 'old_severity', 'new_severity',
		'suppress_until'
	],
	'source' => EVENT_SOURCE_TRIGGERS,
	'object' => EVENT_OBJECT_TRIGGER,
	'eventids' => getRequest('eventid'),
	'objectids' => getRequest('triggerid'),
	'value' => TRIGGER_VALUE_TRUE
]);

if (!$events) {
	access_deny();
}
$event = reset($events);

$event['comments'] = ($trigger['comments'] !== '')
	? CMacrosResolverHelper::resolveTriggerDescription(
		[
			'triggerid' => $trigger['triggerid'],
			'expression' => $trigger['expression'],
			'comments' => $trigger['comments'],
			'clock' => $event['clock'],
			'ns' => $event['ns']
		],
		['events' => true]
	)
	: '';

if ($event['r_eventid'] != 0) {
	$r_events = API::Event()->get([
		'output' => ['correlationid', 'userid'],
		'source' => EVENT_SOURCE_TRIGGERS,
		'object' => EVENT_OBJECT_TRIGGER,
		'eventids' => [$event['r_eventid']],
		'objectids' => getRequest('triggerid')
	]);

	if ($r_events) {
		$r_event = reset($r_events);

		$event['correlationid'] = $r_event['correlationid'];
		$event['userid'] = $r_event['userid'];
	}
}

if ($trigger['opdata'] !== '') {
	$event['opdata'] = (new CCol(CMacrosResolverHelper::resolveTriggerOpdata(
		[
			'triggerid' => $trigger['triggerid'],
			'expression' => $trigger['expression'],
			'opdata' => $trigger['opdata'],
			'clock' => $event['clock'],
			'ns' => $event['ns']
		],
		[
			'events' => true,
			'html' => true
		]
	)))->addClass('opdata');
}
else {
	$db_items = API::Item()->get([
		'output' => ['itemid', 'name', 'value_type', 'units'],
		'selectValueMap' => ['mappings'],
		'triggerids' => $event['objectid']
	]);
	$event['opdata'] = (new CCol(CScreenProblem::getLatestValues($db_items)))->addClass('latest-values');
}

$actions = getEventDetailsActions($event);
$users = API::User()->get([
	'output' => ['username', 'name', 'surname'],
	'userids' => array_keys($actions['userids']),
	'preservekeys' => true
]);
$mediatypes = API::Mediatype()->get([
	'output' => ['maxattempts'],
	'mediatypeids' => array_keys($actions['mediatypeids']),
	'preservekeys' => true
]);

$allowed = [
	'ui_correlation' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_EVENT_CORRELATION),
	'add_comments' => CWebUser::checkAccess(CRoleHelper::ACTIONS_ADD_PROBLEM_COMMENTS),
	'change_severity' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CHANGE_SEVERITY),
	'acknowledge' => CWebUser::checkAccess(CRoleHelper::ACTIONS_ACKNOWLEDGE_PROBLEMS),
	'suppress_problems' => CWebUser::checkAccess(CRoleHelper::ACTIONS_SUPPRESS_PROBLEMS),
	'close' => ($trigger['manual_close'] == ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED
			&& CWebUser::checkAccess(CRoleHelper::ACTIONS_CLOSE_PROBLEMS)
	)
];

/*
 * Display
 */
require_once dirname(__FILE__).'/include/views/js/tr_events.js.php';

$event_tab = (new CDiv([
	new CDiv([
		(new CUiWidget(WIDGET_HAT_TRIGGERDETAILS, make_trigger_details($trigger, $event['eventid'])))
			->setHeader(_('Trigger details')),
		(new CUiWidget(WIDGET_HAT_EVENTDETAILS, make_event_details($event, $allowed)))
			->setHeader(_('Event details'))
	]),
	new CDiv([
		(new CCollapsibleUiWidget(WIDGET_HAT_EVENTACTIONS,
			makeEventDetailsActionsTable($actions, $users, $mediatypes)
		))
			->setExpanded((bool) CProfile::get('web.tr_events.hats.'.WIDGET_HAT_EVENTACTIONS.'.state', true))
			->setHeader(_('Actions'), [], 'web.tr_events.hats.'.WIDGET_HAT_EVENTACTIONS.'.state')
			->addClass(ZBX_STYLE_DASHBOARD_WIDGET_FLUID),
		(new CCollapsibleUiWidget(WIDGET_HAT_EVENTLIST, make_small_eventlist($event, $allowed)))
			->setExpanded((bool) CProfile::get('web.tr_events.hats.'.WIDGET_HAT_EVENTLIST.'.state', true))
			->setHeader(_('Event list [previous 20]'), [], 'web.tr_events.hats.'.WIDGET_HAT_EVENTLIST.'.state')
			->addClass(ZBX_STYLE_DASHBOARD_WIDGET_FLUID)
	])
]))
	->addClass(ZBX_STYLE_COLUMNS)
	->addClass(ZBX_STYLE_COLUMNS_2);

(new CWidget())
	->setTitle(_('Event details'))
	->setWebLayoutMode($page['web_layout_mode'])
	->setDocUrl(CDocHelper::getUrl(CDocHelper::TR_EVENTS))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(get_icon('kioskmode', ['mode' => $page['web_layout_mode']]))
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem($event_tab)
	->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();

require_once dirname(__FILE__).'/include/page_footer.php';

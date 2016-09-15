<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
require_once dirname(__FILE__).'/include/acknow.inc.php';
require_once dirname(__FILE__).'/include/actions.inc.php';
require_once dirname(__FILE__).'/include/events.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/users.inc.php';
require_once dirname(__FILE__).'/include/html.inc.php';

$page['title'] = _('Event details');
$page['file'] = 'tr_events.php';
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

define('PAGE_SIZE', 100);

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'triggerid' =>	[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		PAGE_TYPE_HTML.'=='.$page['type']],
	'eventid' =>	[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		PAGE_TYPE_HTML.'=='.$page['type']],
	'fullscreen' =>	[T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null],
	// Ajax
	'widget' =>	[T_ZBX_STR, O_OPT, P_ACT,
		IN('"'.WIDGET_HAT_EVENTACK.'","'.WIDGET_HAT_EVENTACTIONMSGS.'","'.WIDGET_HAT_EVENTACTIONMCMDS.'","'.
			WIDGET_HAT_EVENTLIST.'"'),
		null
	],
	'state'=>	[T_ZBX_INT, O_OPT, P_ACT, IN('0,1'), null]
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

// events
$events = API::Event()->get([
	'output' => API_OUTPUT_EXTEND,
	'select_alerts' => API_OUTPUT_EXTEND,
	'select_acknowledges' => API_OUTPUT_EXTEND,
	'selectHosts' => API_OUTPUT_EXTEND,
	'selectTags' => ['tag', 'value'],
	'source' => EVENT_SOURCE_TRIGGERS,
	'object' => EVENT_OBJECT_TRIGGER,
	'eventids' => getRequest('eventid'),
	'objectids' => getRequest('triggerid')
]);

if (!$events) {
	access_deny();
}

$event = reset($events);

/*
 * Display
 */
$config = select_config();

$eventTab = (new CTable())
	->addRow([
		new CDiv([
			(new CUiWidget(WIDGET_HAT_TRIGGERDETAILS, make_trigger_details($trigger)))
				->setHeader(_('Event source details')),
			(new CUiWidget(WIDGET_HAT_EVENTDETAILS,
				make_event_details($event, $trigger,
					$page['file'].'?triggerid='.getRequest('triggerid').'&eventid='.getRequest('eventid')
				)
			))->setHeader(_('Event details'))
		]),
		new CDiv([
			($config['event_ack_enable'] && $event['value'] == TRIGGER_VALUE_TRUE)
				? (new CCollapsibleUiWidget(WIDGET_HAT_EVENTACK, makeAckTab($event['acknowledges'])))
					->setExpanded((bool) CProfile::get('web.tr_events.hats.'.WIDGET_HAT_EVENTACK.'.state', true))
					->setHeader(_('Acknowledgements'), [], false, 'tr_events.php')
				: null,
			(new CCollapsibleUiWidget(WIDGET_HAT_EVENTACTIONMSGS, getActionMessages($event['alerts'])))
				->setExpanded((bool) CProfile::get('web.tr_events.hats.'.WIDGET_HAT_EVENTACTIONMSGS.'.state', true))
				->setHeader(_('Message actions'), [], false, 'tr_events.php'),
			(new CCollapsibleUiWidget(WIDGET_HAT_EVENTACTIONMCMDS, getActionCommands($event['alerts'])))
				->setExpanded((bool) CProfile::get('web.tr_events.hats.'.WIDGET_HAT_EVENTACTIONMCMDS.'.state', true))
				->setHeader(_('Command actions'), [], false, 'tr_events.php'),
			(new CCollapsibleUiWidget(WIDGET_HAT_EVENTLIST,
				make_small_eventlist($event,
					$page['file'].'?triggerid='.getRequest('triggerid').'&eventid='.getRequest('eventid')
				)
			))
				->setExpanded((bool) CProfile::get('web.tr_events.hats.'.WIDGET_HAT_EVENTLIST.'.state', true))
				->setHeader(_('Event list [previous 20]'), [], false, 'tr_events.php')
		])
	]);

$eventWidget = (new CWidget())
	->setTitle(_('Event details'))
	->setControls((new CList())->addItem(get_icon('fullscreen', ['fullscreen' => getRequest('fullscreen')])))
	->addItem($eventTab)
	->show();

require_once dirname(__FILE__).'/include/page_footer.php';

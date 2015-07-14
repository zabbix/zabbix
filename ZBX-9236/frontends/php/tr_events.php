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
	// ajax
	'favobj' =>		[T_ZBX_STR, O_OPT, P_ACT,	IN('"filter","hat"'), null],
	'favref' =>		[T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})'],
	'favstate' =>	[T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})']
];
check_fields($fields);

/*
 * Ajax
 */
if (getRequest('favobj') === 'hat') {
	CProfile::update('web.tr_events.hats.'.getRequest('favobj').'.state', getRequest('favstate'), PROFILE_TYPE_INT);
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
	'source' => EVENT_SOURCE_TRIGGERS,
	'object' => EVENT_OBJECT_TRIGGER,
	'eventids' => getRequest('eventid'),
	'objectids' => getRequest('triggerid')
]);

$event = reset($events);

/*
 * Display
 */
$config = select_config();

$eventWidget = (new CWidget())
	->setTitle(_('Event details'))
	->setControls((new CList())->addItem(get_icon('fullscreen', ['fullscreen' => getRequest('fullscreen')])));

// if acknowledges are not disabled in configuration, let's show them
if ($config['event_ack_enable']) {
	$eventAcknowledgesWidget = (new CCollapsibleUiWidget('hat_eventack', makeAckTab($event)))
		->setHeader(_('Acknowledges'))
		->setExpanded((bool) CProfile::get('web.tr_events.hats.hat_eventack.state', true));
}
else {
	$eventAcknowledgesWidget = null;
}

// actions messages
$actionMessagesWidget = (new CCollapsibleUiWidget('hat_eventactionmsgs', getActionMessages($event['alerts'])))
	->setHeader(_('Message actions'))
	->setExpanded((bool) CProfile::get('web.tr_events.hats.hat_eventactionmsgs.state', true));

// actions commands
$actionCommandWidget = (new CCollapsibleUiWidget('hat_eventactionmcmds', getActionCommands($event['alerts'])))
	->setHeader(_('Command actions'))
	->setExpanded((bool) CProfile::get('web.tr_events.hats.hat_eventactioncmds.state', true));

// event history
$eventHistoryWidget = (new CCollapsibleUiWidget('hat_eventlist', make_small_eventlist($event)))
	->setHeader(_('Event list [previous 20]'))
	->setExpanded((bool) CProfile::get('web.tr_events.hats.hat_eventlist.state', true));

$eventTab = new CTable();
$eventTab->addRow([
	new CDiv([
		(new CUiWidget('hat_triggerdetails', make_trigger_details($trigger)))
			->setHeader(_('Event source details')),
		(new CUiWidget('hat_eventdetails', make_event_details($event, $trigger)))
			->setHeader(_('Event details'))
	]),
	new CDiv([$eventAcknowledgesWidget, $actionMessagesWidget, $actionCommandWidget, $eventHistoryWidget])
]);

$eventWidget->addItem($eventTab)->show();

require_once dirname(__FILE__).'/include/page_footer.php';

<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
$page['hist_arg'] = array('triggerid', 'eventid');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

define('PAGE_SIZE', 100);

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'triggerid' =>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		PAGE_TYPE_HTML.'=='.$page['type']),
	'eventid' =>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		PAGE_TYPE_HTML.'=='.$page['type']),
	'fullscreen' =>	array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null),
	// actions
	'save' =>		array(T_ZBX_STR,O_OPT,	P_ACT|P_SYS, null,	null),
	'cancel' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	// ajax
	'favobj' =>		array(T_ZBX_STR, O_OPT, P_ACT,	IN("'filter','hat'"), null),
	'favref' =>		array(T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})'),
	'favstate' =>	array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})')
);
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
$triggers = API::Trigger()->get(array(
	'output' => API_OUTPUT_EXTEND,
	'selectHosts' => API_OUTPUT_EXTEND,
	'expandData' => true,
	'triggerids' => getRequest('triggerid')
));

if (!$triggers) {
	access_deny();
}

$trigger = reset($triggers);

// events
$events = API::Event()->get(array(
	'output' => API_OUTPUT_EXTEND,
	'select_alerts' => API_OUTPUT_EXTEND,
	'select_acknowledges' => API_OUTPUT_EXTEND,
	'selectHosts' => API_OUTPUT_EXTEND,
	'source' => EVENT_SOURCE_TRIGGERS,
	'object' => EVENT_OBJECT_TRIGGER,
	'eventids' => getRequest('eventid'),
	'objectids' => getRequest('triggerid')
));

$event = reset($events);

/*
 * Display
 */
$config = select_config();

$eventWidget = new CWidget();
$eventWidget->setClass('header');
$eventWidget->addHeader(
	array(_('EVENTS').': "'.CMacrosResolverHelper::resolveTriggerName($trigger).'"'),
	get_icon('fullscreen', array('fullscreen' => getRequest('fullscreen')))
);

// trigger details
$triggerDetailsWidget = new CUIWidget('hat_triggerdetails', make_trigger_details($trigger));
$triggerDetailsWidget->setHeader(_('Event source details'));

// event details
$eventDetailsWidget = new CUIWidget('hat_eventdetails', make_event_details($event, $trigger));
$eventDetailsWidget->setHeader(_('Event details'));

// if acknowledges are not disabled in configuration, let's show them
if ($config['event_ack_enable']) {
	$eventAcknowledgesWidget = new CCollapsibleUIWidget('hat_eventack', makeAckTab($event));
	$eventAcknowledgesWidget->open = (bool) CProfile::get('web.tr_events.hats.hat_eventack.state', true);
	$eventAcknowledgesWidget->setHeader(_('Acknowledges'));
}
else {
	$eventAcknowledgesWidget = null;
}

// actions messages
$actionMessagesWidget = new CCollapsibleUIWidget('hat_eventactionmsgs', getActionMessages($event['alerts']));
$actionMessagesWidget->open = (bool) CProfile::get('web.tr_events.hats.hat_eventactionmsgs.state', true);
$actionMessagesWidget->setHeader(_('Message actions'));

// actions commands
$actionCommandWidget = new CCollapsibleUIWidget('hat_eventactionmcmds', getActionCommands($event['alerts']));
$actionCommandWidget->open = (bool) CProfile::get('web.tr_events.hats.hat_eventactioncmds.state', true);
$actionCommandWidget->setHeader(_('Command actions'));

// event history
$eventHistoryWidget = new CCollapsibleUIWidget('hat_eventlist', make_small_eventlist($event));
$eventHistoryWidget->open = (bool) CProfile::get('web.tr_events.hats.hat_eventlist.state', true);
$eventHistoryWidget->setHeader(_('Event list [previous 20]'));

$eventTab = new CTable();
$eventTab->addRow(
	array(
		new CDiv(array($triggerDetailsWidget, $eventDetailsWidget), 'column'),
		new CDiv(
			array(
				$eventAcknowledgesWidget, $actionMessagesWidget, $actionCommandWidget, $eventHistoryWidget
			),
			'column'
		)
	),
	'top'
);

$eventWidget->addItem($eventTab);
$eventWidget->show();

require_once dirname(__FILE__).'/include/page_footer.php';

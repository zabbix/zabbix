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
	'fullscreen' =>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),	null),
	// actions
	'save' =>		array(T_ZBX_STR,O_OPT,	P_ACT|P_SYS, null,	null),
	'cancel' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	// ajax
	'favobj' =>		array(T_ZBX_STR, O_OPT, P_ACT,	IN("'filter','hat'"), null),
	'favref' =>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})'),
	'favstate' =>	array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})')
);
check_fields($fields);

/*
 * Ajax
 */
if (isset($_REQUEST['favobj'])) {
	if ($_REQUEST['favobj'] == 'hat') {
		CProfile::update('web.tr_events.hats.'.$_REQUEST['favref'].'.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}
}

if (PAGE_TYPE_JS == $page['type'] || PAGE_TYPE_HTML_BLOCK == $page['type']) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

// get triggers
$options = array(
	'triggerids' => $_REQUEST['triggerid'],
	'expandData' => 1,
	'selectHosts' => API_OUTPUT_EXTEND,
	'output' => API_OUTPUT_EXTEND
);
$triggers = API::Trigger()->get($options);
if (empty($triggers)) {
	access_deny();
}
$trigger = reset($triggers);

// get events
$options = array(
	'source' => EVENT_SOURCE_TRIGGERS,
	'object' => EVENT_OBJECT_TRIGGER,
	'eventids' => $_REQUEST['eventid'],
	'objectids' => $_REQUEST['triggerid'],
	'select_alerts' => API_OUTPUT_EXTEND,
	'select_acknowledges' => API_OUTPUT_EXTEND,
	'output' => API_OUTPUT_EXTEND,
	'selectHosts' => API_OUTPUT_EXTEND
);
$events = API::Event()->get($options);
$event = reset($events);

$tr_event_wdgt = new CWidget();
$tr_event_wdgt->setClass('header');

// Main widget header
$text = array(_('EVENTS').': "'.CMacrosResolverHelper::resolveTriggerName($trigger).'"');

$fs_icon = get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']));
$tr_event_wdgt->addHeader($text, $fs_icon);

$left_col = array();

// tr details
$triggerDetails = new CUIWidget('hat_triggerdetails', make_trigger_details($trigger));
$triggerDetails->setHeader(_('Event source details'));
$left_col[] = $triggerDetails;

// event details
$eventDetails = new CUIWidget('hat_eventdetails', make_event_details($event, $trigger));
$eventDetails->setHeader(_('Event details'));
$left_col[] = $eventDetails;

$right_col = array();

// if acknowledges are not disabled in configuration, let's show them
if ($config['event_ack_enable']) {
	$event_ack = new CUIWidget('hat_eventack', makeAckTab($event), CProfile::get('web.tr_events.hats.hat_eventack.state', 1));
	$event_ack->setHeader(_('Acknowledges'));
	$right_col[] = $event_ack;
}

// event sms actions
$actions_sms = new CUIWidget('hat_eventactionmsgs', get_action_msgs_for_event($event), CProfile::get('web.tr_events.hats.hat_eventactionmsgs.state', 1));
$actions_sms->setHeader(_('Message actions'));
$right_col[] = $actions_sms;

// event cmd actions
$actions_cmd = new CUIWidget('hat_eventactionmcmds', get_action_cmds_for_event($event), CProfile::get('web.tr_events.hats.hat_eventactioncmds.state', 1));
$actions_cmd->setHeader(_('Command actions'));
$right_col[] = $actions_cmd;

// event history
$events_histry = new CUIWidget('hat_eventlist', make_small_eventlist($event), CProfile::get('web.tr_events.hats.hat_eventlist.state', 1));
$events_histry->setHeader(_('Event list [previous 20]'));
$right_col[] = $events_histry;

$leftDiv = new CDiv($left_col, 'column');
$middleDiv = new CDiv($right_col, 'column');

$ieTab = new CTable();
$ieTab->addRow(array($leftDiv, $middleDiv), 'top');

$tr_event_wdgt->addItem($ieTab);
$tr_event_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';

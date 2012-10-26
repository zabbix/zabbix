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
require_once dirname(__FILE__).'/include/acknow.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Acknowledges');
$page['file'] = 'acknow.php';
$page['hist_arg'] = array('eventid');

ob_start();
require_once dirname(__FILE__).'/include/page_header.php';


$_REQUEST['go'] = get_request('go', null);
$bulk = ($_REQUEST['go'] == 'bulkacknowledge');

//	VAR		TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'eventid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'triggers' =>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		null),
	'triggerid' =>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		null),
	'screenid' =>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		null),
	'events' =>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		null),
	'message' =>		array(T_ZBX_STR, O_OPT,	null,	$bulk ? null : NOT_EMPTY, 'isset({save})||isset({saveandreturn})'),
	'backurl' =>		array(T_ZBX_STR, O_OPT,	null,	null,		null),
	// actions
	'go' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	// form
	'saveandreturn' =>	array(T_ZBX_STR, O_OPT,	P_ACT|P_SYS, null,	null),
	'save' =>			array(T_ZBX_STR, O_OPT,	P_ACT|P_SYS, null,	null),
	'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null)
);
check_fields($fields);

$_REQUEST['backurl'] = get_request('backurl', 'tr_status.php');

if (isset($_REQUEST['cancel'])) {
	ob_end_clean();
	if ($_REQUEST['backurl'] == 'tr_events.php') {
		redirect($_REQUEST['backurl'].'?eventid='.$_REQUEST['eventid'].'&triggerid='.$_REQUEST['triggerid']);
	}
	elseif ($_REQUEST['backurl'] == 'screenedit.php') {
		redirect($_REQUEST['backurl'].'?screenid='.$_REQUEST['screenid']);
	}
	elseif ($_REQUEST['backurl'] == 'screens.php') {
		redirect($_REQUEST['backurl'].'?elementid='.$_REQUEST['screenid']);
	}
	else {
		redirect($_REQUEST['backurl']);
	}
}

if (!isset($_REQUEST['events']) && !isset($_REQUEST['eventid']) && !isset($_REQUEST['triggers'])) {
	show_message(_('No events to acknowledge'));
	require_once dirname(__FILE__).'/include/page_footer.php';
}

$bulk = !isset($_REQUEST['eventid']);
$_REQUEST['backurl'] = get_request('backurl', 'tr_status.php');

if (!$bulk) {
	$options = array(
		'output' => API_OUTPUT_EXTEND,
		'selectTriggers' => API_OUTPUT_EXTEND,
		'eventids' => $_REQUEST['eventid']
	);
	$events = API::Event()->get($options);
	$event = reset($events);
	$event_trigger = reset($event['triggers']);
	$event_acknowledged = $event['acknowledged'];
	$_REQUEST['events'] = $_REQUEST['eventid'];
}

if (isset($_REQUEST['save']) || isset($_REQUEST['saveandreturn'])) {
	if ($bulk) {
		$_REQUEST['message'] .= $_REQUEST['message'] == '' ? '' : "\n\r" . _('----[BULK ACKNOWLEDGE]----');
	}

	if (isset($_REQUEST['events'])) {
		$_REQUEST['events'] = zbx_toObject($_REQUEST['events'], 'eventid');
	}
	elseif (isset($_REQUEST['triggers'])) {
		$options = array(
			'output' => API_OUTPUT_SHORTEN,
			'acknowledged' => 0,
			'triggerids' => $_REQUEST['triggers'],
			'filter'=> array('value_changed' => TRIGGER_VALUE_CHANGED_YES)
		);
		$_REQUEST['events'] = API::Event()->get($options);
	}

	$eventsData = array(
		'eventids' => zbx_objectValues($_REQUEST['events'], 'eventid'),
		'message' => $_REQUEST['message']
	);
	$result = API::Event()->acknowledge($eventsData);

	show_messages($result, _('Event acknowledged'), _('Cannot acknowledge event'));
	if ($result) {
		$event_acknowledged = true;
		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_TRIGGER, _('Acknowledge added').
			' ['.($bulk) ? ' BULK ACKNOWLEDGE ' : $event_trigger.']'.
			' ['.$_REQUEST['message'].']');
	}

	if (isset($_REQUEST['saveandreturn'])) {
		ob_end_clean();
		if ($_REQUEST['backurl'] == 'tr_events.php') {
			redirect($_REQUEST['backurl'].'?eventid='.$_REQUEST['eventid'].'&triggerid='.$_REQUEST['triggerid']);
		}
		elseif ($_REQUEST['backurl'] == 'screenedit.php') {
			redirect($_REQUEST['backurl'].'?screenid='.$_REQUEST['screenid']);
		}
		elseif ($_REQUEST['backurl'] == 'screens.php') {
			redirect($_REQUEST['backurl'].'?elementid='.$_REQUEST['screenid']);
		}
		else {
			redirect($_REQUEST['backurl']);
		}
	}
}
ob_end_flush();

$msg = $bulk ? ' BULK ACKNOWLEDGE ' : CTriggerHelper::expandDescription($event_trigger);
show_table_header(array(_('ALARM ACKNOWLEDGES').': ', $msg));
echo SBR;

if ($bulk) {
	$title = _('Acknowledge alarm by');
	$btn_txt2 = _('Acknowledge and return');
}
else {
	$db_acks = get_acknowledges_by_eventid($_REQUEST['eventid']);
	if ($db_acks) {
		$table = new CTable(null, 'ack_msgs');
		$table->setAlign('center');

		while ($db_ack = DBfetch($db_acks)) {
			$table->addRow(array(
				new CCol($db_ack['alias'], 'user'),
				new CCol(zbx_date2str(_('d M Y H:i:s'), $db_ack['clock']), 'time')),
				'title'
			);
			$msgCol = new CCol(zbx_nl2br($db_ack['message']));
			$msgCol->setColspan(2);
			$table->addRow($msgCol, 'msg');
		}
		$table->Show();
	}

	if ($event_acknowledged) {
		$title = _('Add comment by');
		$btn_txt = _('Save');
		$btn_txt2 = _('Save and return');
	}
	else {
		$title = _('Acknowledge alarm by');
		$btn_txt = _('Acknowledge');
		$btn_txt2 = _('Acknowledge and return');
	}
}

$frmMsg = new CFormTable($title.' "'.CWebUser::$data['alias'].'"');
$frmMsg->addVar('backurl', $_REQUEST['backurl']);
if ($_REQUEST['backurl'] == 'tr_events.php') {
	$frmMsg->addVar('eventid', $_REQUEST['eventid']);
	$frmMsg->addVar('triggerid', $_REQUEST['triggerid']);
}
elseif ($_REQUEST['backurl'] == 'screenedit.php' || $_REQUEST['backurl'] == 'screens.php') {
	$frmMsg->addVar('screenid', $_REQUEST['screenid']);
}

if (isset($_REQUEST['eventid'])) {
	$frmMsg->addVar('eventid', $_REQUEST['eventid']);
}
elseif (isset($_REQUEST['triggers'])) {
	foreach ($_REQUEST['triggers'] as $triggerid) {
		$frmMsg->addVar('triggers['.$triggerid.']', $triggerid);
	}
}
elseif (isset($_REQUEST['events'])) {
	foreach ($_REQUEST['events'] as $eventid) {
		$frmMsg->addVar('events['.$eventid.']', $eventid);
	}
}

$frmMsg->addRow(_('Message'), new CTextArea('message', '', array('rows' => ZBX_TEXTAREA_STANDARD_ROWS, 'width' => ZBX_TEXTAREA_BIG_WIDTH, 'maxlength' => 255)));
$frmMsg->addItemToBottomRow(new CSubmit('saveandreturn', $btn_txt2));
if (!$bulk) {
	$frmMsg->addItemToBottomRow(new CSubmit('save', $btn_txt));
}
$frmMsg->addItemToBottomRow(new CButtonCancel(url_param('backurl').url_param('eventid').url_param('triggerid').url_param('screenid')));
$frmMsg->show(false);

require_once dirname(__FILE__).'/include/page_footer.php';

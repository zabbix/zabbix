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
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Acknowledges');
$page['file'] = 'acknow.php';
$page['hist_arg'] = array('eventid');

ob_start();

require_once dirname(__FILE__).'/include/page_header.php';

$bulk = (getRequest('action', '') == 'trigger.bulkacknowledge');

//	VAR		TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'eventid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'triggers' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'triggerid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'screenid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'events' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'message' =>		array(T_ZBX_STR, O_OPT, null,	$bulk ? null : NOT_EMPTY, 'isset({save}) || isset({saveandreturn})'),
	'backurl' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	// actions
	'action' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, IN('"trigger.bulkacknowledge"'),	null),
	'saveandreturn' =>	array(T_ZBX_STR, O_OPT, P_ACT|P_SYS, null,	null),
	'save' =>			array(T_ZBX_STR, O_OPT, P_ACT|P_SYS, null,	null),
	'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null)
);
check_fields($fields);

$_REQUEST['backurl'] = getRequest('backurl', 'tr_status.php');

/*
 * Redirect
 */
if (isset($_REQUEST['cancel'])) {
	ob_end_clean();

	if (in_array($_REQUEST['backurl'], array('tr_events.php', 'events.php'))) {
		redirect($_REQUEST['backurl'].'?eventid='.$_REQUEST['eventid'].'&triggerid='.$_REQUEST['triggerid'].
			'&source='.EVENT_SOURCE_TRIGGERS
		);
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

/*
 * Permissions
 */
if (!isset($_REQUEST['events']) && !isset($_REQUEST['eventid']) && !isset($_REQUEST['triggers'])) {
	show_message(_('No events to acknowledge'));
	require_once dirname(__FILE__).'/include/page_footer.php';
}
elseif (getRequest('eventid')) {
	$event = API::Event()->get(array(
		'eventids' => getRequest('eventid'),
		'output' => array('eventid'),
		'limit' => 1
	));
	if (!$event) {
		access_deny();
	}
}
elseif (getRequest('triggers')) {
	$trigger = API::Trigger()->get(array(
		'triggerids' => getRequest('triggers'),
		'output' => array('triggerid'),
		'limit' => 1
	));
	if (!$trigger) {
		access_deny();
	}
}

/*
 * Actions
 */
$eventTrigger = null;
$eventAcknowledged = null;
$eventTriggerName = null;

$bulk = !isset($_REQUEST['eventid']);

if (!$bulk) {
	$events = API::Event()->get(array(
		'eventids' => $_REQUEST['eventid'],
		'output' => API_OUTPUT_EXTEND,
		'selectRelatedObject' => API_OUTPUT_EXTEND
	));

	if ($events) {
		$event = reset($events);

		$eventTriggerName = CMacrosResolverHelper::resolveTriggerName($event['relatedObject']);
		$eventAcknowledged = $event['acknowledged'];
	}

	$_REQUEST['events'] = $_REQUEST['eventid'];
}

if (isset($_REQUEST['save']) || isset($_REQUEST['saveandreturn'])) {
	if ($bulk) {
		$_REQUEST['message'] .= ($_REQUEST['message'] == '') ? '' : "\n\r"._('----[BULK ACKNOWLEDGE]----');
	}

	if (isset($_REQUEST['events'])) {
		$_REQUEST['events'] = zbx_toObject($_REQUEST['events'], 'eventid');
	}
	elseif (isset($_REQUEST['triggers'])) {
		$_REQUEST['events'] = API::Event()->get(array(
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'objectids' => $_REQUEST['triggers'],
			'output' => array('eventid'),
			'acknowledged' => EVENT_NOT_ACKNOWLEDGED
		));
	}

	DBstart();

	$result = API::Event()->acknowledge(array(
		'eventids' => zbx_objectValues($_REQUEST['events'], 'eventid'),
		'message' => $_REQUEST['message']
	));

	if ($result) {
		$eventAcknowledged = true;

		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_TRIGGER, _('Acknowledge added').
			' ['.($bulk ? ' BULK ACKNOWLEDGE ' : $eventTriggerName).']'.
			' ['.$_REQUEST['message'].']'
		);
	}

	$result = DBend($result);
	show_messages($result, _('Event acknowledged'), _('Cannot acknowledge event'));

	if (isset($_REQUEST['saveandreturn'])) {
		ob_end_clean();

		if (in_array($_REQUEST['backurl'], array('tr_events.php', 'events.php'))) {
			redirect($_REQUEST['backurl'].'?eventid='.$_REQUEST['eventid'].'&triggerid='.$_REQUEST['triggerid'].
				'&source='.EVENT_SOURCE_TRIGGERS
			);
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

/*
 * Display
 */
show_table_header(array(_('ALARM ACKNOWLEDGES').NAME_DELIMITER, ($bulk ? ' BULK ACKNOWLEDGE ' : $eventTriggerName)));

echo BR();

if ($bulk) {
	$title = _('Acknowledge alarm by');
	$saveAndReturnLabel = _('Acknowledge and return');
}
else {
	$acknowledges = DBselect(
		'SELECT a.*,u.alias,u.name,u.surname'.
		' FROM acknowledges a'.
			' LEFT JOIN users u ON u.userid=a.userid'.
		' WHERE a.eventid='.zbx_dbstr($_REQUEST['eventid'])
	);

	if ($acknowledges) {
		$acknowledgesTable = new CTable(null, 'ack_msgs');
		$acknowledgesTable->setAlign('center');

		while ($acknowledge = DBfetch($acknowledges)) {
			$acknowledgesTable->addRow(array(
				new CCol(getUserFullname($acknowledge), 'user'),
				new CCol(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $acknowledge['clock']), 'time')),
				'title'
			);
			$acknowledgesTable->addRow(new CCol(zbx_nl2br($acknowledge['message']), null, 2), 'msg');
		}

		$acknowledgesTable->show();
	}

	if ($eventAcknowledged) {
		$title = _('Add comment by');
		$saveLabel = _('Update');
		$saveAndReturnLabel = _('Update and return');
	}
	else {
		$title = _('Acknowledge alarm by');
		$saveLabel = _('Acknowledge');
		$saveAndReturnLabel = _('Acknowledge and return');
	}
}

$messageTable = new CFormTable($title.' "'.getUserFullname(CWebUser::$data).'"');
$messageTable->addClass('acknowledge-edit');

$backURL = getRequest('backurl');
$messageTable->addVar('backurl', $backURL);

if ($backURL === 'tr_events.php' || $backURL === 'events.php') {
	$messageTable->addVar('triggerid', getRequest('triggerid'));
	$messageTable->addVar('source', EVENT_SOURCE_TRIGGERS);
}
elseif ($backURL === 'screenedit.php' || $backURL === 'screens.php') {
	$messageTable->addVar('screenid', $_REQUEST['screenid']);
}

if (hasRequest('eventid')) {
	$messageTable->addVar('eventid', getRequest('eventid'));
}
elseif (hasRequest('triggers')) {
	foreach (getRequest('triggers') as $triggerId) {
		$messageTable->addVar('triggers['.$triggerId.']', $triggerId);
	}
}
elseif (hasRequest('events')) {
	foreach (getRequest('events') as $eventId) {
		$messageTable->addVar('events['.$eventId.']', $eventId);
	}
}

$message = new CTextArea('message', '', array(
	'rows' => ZBX_TEXTAREA_STANDARD_ROWS,
	'width' => ZBX_TEXTAREA_BIG_WIDTH,
	'maxlength' => 255
));
$message->attr('autofocus', 'autofocus');

$messageTable->addRow(_('Message'), $message);
$messageTable->addItemToBottomRow(new CSubmit('saveandreturn', $saveAndReturnLabel));

if (!$bulk) {
	$messageTable->addItemToBottomRow(new CSubmit('save', $saveLabel));
}

$messageTable->addItemToBottomRow(new CButtonCancel(url_params(array('backurl', 'eventid', 'triggerid', 'screenid'))));
$messageTable->show();

require_once dirname(__FILE__).'/include/page_footer.php';

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
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Acknowledges');
$page['file'] = 'acknow.php';

ob_start();

require_once dirname(__FILE__).'/include/page_header.php';

$bulk = (getRequest('action', '') == 'trigger.bulkacknowledge');

//	VAR		TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	'eventid' =>		[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'triggers' =>		[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'triggerid' =>		[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'screenid' =>		[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'events' =>			[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'message' =>		[T_ZBX_STR, O_OPT, null,	$bulk ? null : NOT_EMPTY, 'isset({save}) || isset({saveandreturn})'],
	'backurl' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	// actions
	'action' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, IN('"trigger.bulkacknowledge"'),	null],
	'saveandreturn' =>	[T_ZBX_STR, O_OPT, P_ACT|P_SYS, null,	null],
	'save' =>			[T_ZBX_STR, O_OPT, P_ACT|P_SYS, null,	null],
	'cancel' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null]
];
check_fields($fields);

$_REQUEST['backurl'] = getRequest('backurl', 'tr_status.php');

/*
 * Redirect
 */
if (isset($_REQUEST['cancel'])) {
	ob_end_clean();

	if (in_array($_REQUEST['backurl'], ['tr_events.php', 'events.php'])) {
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
	$event = API::Event()->get([
		'eventids' => getRequest('eventid'),
		'output' => ['eventid'],
		'limit' => 1
	]);
	if (!$event) {
		access_deny();
	}
}
elseif (getRequest('triggers')) {
	$trigger = API::Trigger()->get([
		'triggerids' => getRequest('triggers'),
		'output' => ['triggerid'],
		'limit' => 1
	]);
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
	$events = API::Event()->get([
		'eventids' => $_REQUEST['eventid'],
		'output' => API_OUTPUT_EXTEND,
		'selectRelatedObject' => API_OUTPUT_EXTEND
	]);

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
		$_REQUEST['events'] = API::Event()->get([
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'objectids' => $_REQUEST['triggers'],
			'output' => ['eventid'],
			'acknowledged' => EVENT_NOT_ACKNOWLEDGED
		]);
	}

	DBstart();

	$result = API::Event()->acknowledge([
		'eventids' => zbx_objectValues($_REQUEST['events'], 'eventid'),
		'message' => $_REQUEST['message']
	]);

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

		if (in_array($_REQUEST['backurl'], ['tr_events.php', 'events.php'])) {
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

$widget = (new CWidget())->setTitle(_('Alarm acknowledgements').SPACE.($bulk ? 'Bulk acknowledge ' : $eventTriggerName));

echo BR();

$acknowledgesTable = null;
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
		$acknowledgesTable = (new CTableInfo())
			->setHeader([_('Time'), _('User'), _('Message')]);

		while ($acknowledge = DBfetch($acknowledges)) {
			$acknowledgesTable->addRow([
				zbx_date2str(DATE_TIME_FORMAT_SECONDS, $acknowledge['clock']),
				getUserFullname($acknowledge),
				zbx_nl2br($acknowledge['message'])
			]);
		}
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

$backURL = getRequest('backurl');

$form = (new CForm())->addVar('backurl', $backURL);

if ($backURL === 'tr_events.php' || $backURL === 'events.php') {
	$form->addVar('triggerid', getRequest('triggerid'));
	$form->addVar('source', EVENT_SOURCE_TRIGGERS);
}
elseif ($backURL === 'screenedit.php' || $backURL === 'screens.php') {
	$form->addVar('screenid', $_REQUEST['screenid']);
}

if (hasRequest('eventid')) {
	$form->addVar('eventid', getRequest('eventid'));
}
elseif (hasRequest('triggers')) {
	foreach (getRequest('triggers') as $triggerId) {
		$form->addVar('triggers['.$triggerId.']', $triggerId);
	}
}
elseif (hasRequest('events')) {
	foreach (getRequest('events') as $eventId) {
		$form->addVar('events['.$eventId.']', $eventId);
	}
}

$formList = (new CFormList())
	->addRow(_('Message'),
		(new CTextArea('message', '', ['maxlength' => 255]))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setAttribute('autofocus', 'autofocus')
	);

// append tabs to form
$ackTab = new CTabView();
$ackTab->addTab('ackTab', _('Acknowledge'), $formList);
if ($acknowledgesTable !== null) {
	$ackTab->addTab('ackListTab', _('List'), $acknowledgesTable);
}

if (!$bulk) {
	$ackTab->setFooter(makeFormFooter(
		new CSubmit('saveandreturn', $saveAndReturnLabel),
		[
			new CSubmit('save', $saveLabel),
			new CButtonCancel(url_params(['backurl', 'eventid', 'triggerid', 'screenid']))
		]
	));
}
else {
	$ackTab->setFooter(makeFormFooter(
		new CSubmit('saveandreturn', $saveAndReturnLabel),
		[
			new CButtonCancel(url_params(['backurl', 'eventid', 'triggerid', 'screenid']))
		]
	));
}

$form->addItem($ackTab);
$widget->addItem($form);
$widget->show();

require_once dirname(__FILE__).'/include/page_footer.php';

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


$this->includeJSfile('app/views/monitoring.acknowledge.edit.js.php');

$form_list = (new CFormList())
	->addRow(_('Message'),
		(new CTextArea('message', $data['message']))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setMaxLength(255)
			->setAttribute('autofocus', 'autofocus')
	);

if (CRemedyService::$enabled && (CRemedyService::$severity & (1 << $data['event']['triggerSeverity']))
		&& $data['event']['value'] == TRIGGER_VALUE_TRUE) {
	$ticket_status_message = (!$data['ticket'] || $data['ticket']['status'] === 'Closed'
			|| $data['ticket']['status'] === 'Cancelled'
	)
		? _('Create ticket')
		: [_('Update ticket').' ', $data['ticket']['link']];

	$form_list->addRow($ticket_status_message,
		(new CCheckBox('ticket_status'))->setChecked($data['ticket_status'])
	);
}

if (array_key_exists('event', $data)) {
	$acknowledgesTable = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->setHeader([_('Time'), _('User'), _('Message')]);

	foreach ($data['event']['acknowledges'] as $acknowledge) {
		$acknowledgesTable->addRow([
			(new CCol(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $acknowledge['clock'])))->addClass(ZBX_STYLE_NOWRAP),
			(new CCol(getUserFullname($acknowledge)))->addClass(ZBX_STYLE_NOWRAP),
			zbx_nl2br($acknowledge['message'])
		]);
	}

	$form_list->addRow(_('History'),
		(new CDiv($acknowledgesTable))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);
}

$selected_events = count($data['eventids']);

$form_list->addRow(_('Acknowledge'),
	(new CDiv(
		(new CRadioButtonList('acknowledge_type', (int) $data['acknowledge_type']))
			->makeVertical()
			->addValue([
				_n('Only selected event', 'Only selected events', $selected_events),
				$selected_events > 1 ? (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN) : null,
				$selected_events > 1 ? new CSup(_n('%1$s event', '%1$s events', $selected_events)) : null
			], ZBX_ACKNOWLEDGE_SELECTED)
			->addValue([
				_('Selected and all unacknowledged PROBLEM events'),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				new CSup(_n('%1$s event', '%1$s events', $data['unack_problem_events_count']))
			], ZBX_ACKNOWLEDGE_PROBLEM)
			->addValue([
				_('Selected and all unacknowledged events'),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				new CSup(_n('%1$s event', '%1$s events', $data['unack_events_count']))
			], ZBX_ACKNOWLEDGE_ALL)
	))
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
);

$footer_buttons = makeFormFooter(
	new CSubmitButton(_('Acknowledge'), 'action', 'acknowledge.create'),
	[new CRedirectButton(_('Cancel'), $data['backurl'])]
);

$form = (new CForm())
			->setId('acknowledge_form')
			->addVar('eventids', $data['eventids'])
			->addVar('backurl', $data['backurl']);

// Create a Remedy ticket block before the actual form.
if (CRemedyService::$enabled && $data['ticket']) {
	$widget = new CWidget();

	$ticket = (new CFormList())->addRow(_('Ticket'), $data['ticket']['link']);

	if ($data['ticket']['assignee']) {
		$ticket->addRow(_('Assignee'), $data['ticket']['assignee']);
	}

	$ticket
		->addRow(_('Status'), $data['ticket']['status'])
		->addRow(_('Created'), $data['ticket']['created']);

	$widget->addItem((new CDiv($ticket))->addClass(ZBX_STYLE_CELL));

	$form->addItem(
		(new CDiv((new CDiv($widget))->addClass(ZBX_STYLE_FILTER_FORMS)))->addClass(ZBX_STYLE_FILTER_CONTAINER)
	);
}

$form->addItem(
	(new CTabView())
		->addTab('ackTab', null, $form_list)
		->setFooter($footer_buttons)
);

(new CWidget())
	->setTitle(_('Alarm acknowledgements'))
	->addItem($form)
	->show();

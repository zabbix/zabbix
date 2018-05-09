<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	->addRow(
		new CLabel(_('Message'), 'message'),
		(new CTextArea('message', $data['message']))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setMaxLength(255)
			->setAttribute('autofocus', 'autofocus')
	);

if (array_key_exists('event', $data)) {
	$acknowledgesTable = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->setHeader([_('Time'), _('User'), _('Message'), _('User action')]);

	foreach ($data['event']['acknowledges'] as $acknowledge) {
		$acknowledgesTable->addRow([
			(new CCol(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $acknowledge['clock'])))->addClass(ZBX_STYLE_NOWRAP),
			(new CCol(array_key_exists('alias', $acknowledge)
				? getUserFullname($acknowledge)
				: _('Inaccessible user')
			))->addClass(ZBX_STYLE_NOWRAP),
			zbx_nl2br($acknowledge['message']),
			($acknowledge['action'] & ZBX_PROBLEM_UPDATE_CLOSE) ? _('Close problem') : ''
		]);
	}

	$form_list->addRow(_('History'),
		(new CDiv($acknowledgesTable))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);
}

$selected_events = count($data['eventids']);

$form_list
	->addRow(_x('Scope', 'selected problems'),
		(new CDiv(
			(new CRadioButtonList('scope', $data['scope']))
				->makeVertical()
				->addValue([
					_n('Only selected problem', 'Only selected problems', $selected_events),
					$selected_events > 1 ? (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN) : null,
					$selected_events > 1 ? new CSup(_n('%1$s event', '%1$s events', $selected_events)) : null
				], ZBX_ACKNOWLEDGE_SELECTED)
				->addValue([
					_('Selected and all other problems of related triggers'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					new CSup(_n('%1$s event', '%1$s events', $data['related_problems_count']))
				], ZBX_ACKNOWLEDGE_PROBLEM)
		))
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	)
	->addRow(_('Change severity'),
		(new CList([
			(new CCheckBox('change_severity', ZBX_PROBLEM_UPDATE_SEVERITY))
				->onClick('javascript: jQuery("#severity input").attr("disabled", this.checked ? false : true)')
				->setChecked($data['change_severity']),
			(new CSeverity(['name' => 'severity', 'value' => null], $data['change_severity']))
		]))
			->addClass('hor-list')
	)
	->addRow(_('Acknowledge'),
		(new CCheckBox('acknowledge_problem', ZBX_PROBLEM_UPDATE_ACKNOWLEDGE))
			->setChecked($data['acknowledge_problem'])
			->setEnabled($data['problem_can_be_acknowledged'])
	)
	->addRow(_('Close problem'),
		(new CCheckBox('close_problem', ZBX_PROBLEM_UPDATE_CLOSE))
			->setChecked($data['close_problem'])
			->setEnabled($data['problem_can_be_closed'])
	);

$footer_buttons = makeFormFooter(
	new CSubmitButton(_('Update'), 'action', 'acknowledge.create'),
	[new CRedirectButton(_('Cancel'), $data['backurl'])]
);

(new CWidget())
	->setTitle(_('Update problem'))
	->addItem(
		(new CForm())
			->setId('acknowledge_form')
			->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
			->addVar('eventids', $data['eventids'])
			->addVar('backurl', $data['backurl'])
			->addItem(
				(new CTabView())
					->addTab('ackTab', null, $form_list)
					->setFooter($footer_buttons)
			)
	)
	->show();

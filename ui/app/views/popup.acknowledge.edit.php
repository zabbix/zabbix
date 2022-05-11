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


/**
 * @var CView $this
 */

$form = (new CForm())
	->cleanItems()
	->setId('acknowledge_form')
	->addVar('action', 'popup.acknowledge.create')
	->addVar('eventids', $data['eventids']);

$form_list = (new CFormList())
	->addRow(new CLabel(_('Problem')), (new CDiv($data['problem_name']))->addClass(ZBX_STYLE_WORDBREAK))
	->addRow(
		new CLabel(_('Message'), 'message'),
		(new CTextArea('message', $data['message']))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setAttribute('maxlength', DB::getFieldLength('acknowledges', 'message'))
			->setEnabled($data['allowed_add_comments'])
	);

if (array_key_exists('history', $data)) {
	$form_list->addRow(_('History'),
		(new CDiv(makeEventHistoryTable($data['history'], $data['users'])))
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
					($selected_events > 1) ? (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN) : null,
					($selected_events > 1) ? new CSup(_n('%1$s event', '%1$s events', $selected_events)) : null
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
				->setChecked($data['change_severity'])
				->setEnabled($data['allowed_change_severity'] && $data['problem_severity_can_be_changed']),
			(new CSeverity('severity', (int) $data['severity'], $data['change_severity']))
		]))
			->addClass(ZBX_STYLE_HOR_LIST)
	);

if ($data['has_unack_events']) {
	$form_list->addRow(_('Acknowledge'),
		(new CCheckBox('acknowledge_problem', ZBX_PROBLEM_UPDATE_ACKNOWLEDGE))
			->onChange("$('#unacknowledge_problem').prop('disabled', this.checked)")
			->setEnabled($data['allowed_acknowledge'])
	);
}

if ($data['has_ack_events']) {
	$form_list->addRow(_('Unacknowledge'),
		(new CCheckBox('unacknowledge_problem', ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE))
			->onChange("$('#acknowledge_problem').prop('disabled', this.checked)")
			->setEnabled($data['allowed_acknowledge'])
	);
}

$form_list
	->addRow(_('Close problem'),
		(new CCheckBox('close_problem', ZBX_PROBLEM_UPDATE_CLOSE))
			->setChecked($data['close_problem'])
			->setEnabled($data['allowed_close'] && $data['problem_can_be_closed'])
	)
	->addRow('',
		(new CDiv((new CLabel(_('At least one update operation or message must exist.')))->setAsteriskMark()))
	);

$form->addItem($form_list);

$output = [
	'header' => $data['title'],
	'body' => (new CDiv([$data['errors'], $form]))->toString(),
	'buttons' => [
		[
			'title' => _('Update'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return submitAcknowledge(overlay);'
		]
	],
	'script_inline' => $this->readJsFile('popup.acknowledge.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

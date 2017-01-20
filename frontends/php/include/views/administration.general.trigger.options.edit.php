<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


include('include/views/js/administration.general.trigger.options.js.php');

$widget = (new CWidget())
	->setTitle(_('Trigger displaying options'))
	->setControls((new CForm())
		->cleanItems()
		->addItem((new CList())->addItem(makeAdministrationGeneralMenu('adm.triggerdisplayoptions.php')))
	);

$triggerDOFormList = (new CFormList())
	->addRow(_('Unacknowledged PROBLEM events'), [
		new CColor('problem_unack_color', $data['problem_unack_color']),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		new CLabel([
			(new CCheckBox('problem_unack_style'))->setChecked($data['problem_unack_style'] == 1),
			_('blinking')
		], 'problem_unack_style')
	])
	->addRow(_('Acknowledged PROBLEM events'), [
		new CColor('problem_ack_color', $data['problem_ack_color']),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		new CLabel([
			(new CCheckBox('problem_ack_style'))->setChecked($data['problem_ack_style'] == 1),
			_('blinking')
		], 'problem_ack_style')
	])
	->addRow(_('Unacknowledged OK events'), [
		new CColor('ok_unack_color', $data['ok_unack_color']),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		new CLabel([
			(new CCheckBox('ok_unack_style'))->setChecked($data['ok_unack_style'] == 1),
			_('blinking')
		], 'ok_unack_style')
	])
	->addRow(_('Acknowledged OK events'), [
		new CColor('ok_ack_color', $data['ok_ack_color']),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		new CLabel([
			(new CCheckBox('ok_ack_style'))->setChecked($data['ok_ack_style'] == 1),
			_('blinking')
		], 'ok_ack_style')
	])
	->addRow(null)
	->addRow(_('Display OK triggers for'), [
		(new CTextBox('ok_period', $data['ok_period']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAttribute('maxlength', '6'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		_('seconds')
	])
	->addRow(_('On status change triggers blink for'), [
		(new CTextBox('blink_period', $data['blink_period']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAttribute('maxlength', '6'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		_('seconds')
	]);

$severityForm = (new CForm())
	->addItem(
		(new CTabView())
			->addTab('triggerdo', _('Trigger displaying options'), $triggerDOFormList)
			->setFooter(makeFormFooter(
				new CSubmit('update', _('Update')),
				[new CButton('resetDefaults', _('Reset defaults'))]
			))
	);

$widget->addItem($severityForm);

return $widget;

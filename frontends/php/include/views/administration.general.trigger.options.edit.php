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


include('include/views/js/administration.general.trigger.options.js.php');

$widget = (new CWidget())
	->setTitle(_('Trigger displaying options'))
	->setControls((new CForm())
		->cleanItems()
		->addItem((new CList())->addItem(makeAdministrationGeneralMenu('adm.triggerdisplayoptions.php')))
	);

$triggerDOFormList = (new CFormList())
	->addRow(_('Use custom event status colors'), (new CCheckBox('custom_color'))
		->setChecked($data['custom_color'] == EVENT_CUSTOM_COLOR_ENABLED))
	->addRow((new CLabel(_('Unacknowledged PROBLEM events'), 'problem_unack_color'))->setAsteriskMark(), [
		(new CColor('problem_unack_color', $data['problem_unack_color']))
			->setEnabled($data['custom_color'] == EVENT_CUSTOM_COLOR_ENABLED)
			->setAriaRequired(),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CCheckBox('problem_unack_style'))
			->setLabel(_('blinking'))
			->setChecked($data['problem_unack_style'] == 1)
	])
	->addRow((new CLabel(_('Acknowledged PROBLEM events'), 'problem_ack_color'))->setAsteriskMark(), [
		(new CColor('problem_ack_color', $data['problem_ack_color']))
			->setEnabled($data['custom_color'] == EVENT_CUSTOM_COLOR_ENABLED)
			->setAriaRequired(),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CCheckBox('problem_ack_style'))
			->setLabel(_('blinking'))
			->setChecked($data['problem_ack_style'] == 1)
	])
	->addRow((new CLabel(_('Unacknowledged OK events'), 'ok_unack_color'))->setAsteriskMark(), [
		(new CColor('ok_unack_color', $data['ok_unack_color']))
			->setEnabled($data['custom_color'] == EVENT_CUSTOM_COLOR_ENABLED)
			->setAriaRequired(),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CCheckBox('ok_unack_style'))
			->setLabel(_('blinking'))
			->setChecked($data['ok_unack_style'] == 1)
	])
	->addRow((new CLabel(_('Acknowledged OK events'), 'ok_ack_color'))->setAsteriskMark(), [
		(new CColor('ok_ack_color', $data['ok_ack_color']))
			->setEnabled($data['custom_color'] == EVENT_CUSTOM_COLOR_ENABLED)
			->setAriaRequired(),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CCheckBox('ok_ack_style'))
			->setLabel(_('blinking'))
			->setChecked($data['ok_ack_style'] == 1)
	])
	->addRow(null)
	->addRow((new CLabel(_('Display OK triggers for'), 'ok_period'))->setAsteriskMark(), [
		(new CTextBox('ok_period', $data['ok_period']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
			->setAttribute('maxlength', '6')
	])
	->addRow((new CLabel(_('On status change triggers blink for'), 'blink_period'))->setAsteriskMark(), [
		(new CTextBox('blink_period', $data['blink_period']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
			->setAttribute('maxlength', '6')
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

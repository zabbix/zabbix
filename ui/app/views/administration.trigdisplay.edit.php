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

$this->addJsFile('colorpicker.js');

$this->includeJsFile('administration.trigdisplay.edit.js.php');

$widget = (new CWidget())
	->setTitle(_('Trigger displaying options'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu());

$form_list = (new CFormList())
	->addRow(_('Use custom event status colors'), (new CCheckBox('custom_color'))
		->setUncheckedValue(EVENT_CUSTOM_COLOR_DISABLED)
		->setChecked($data['custom_color'] == EVENT_CUSTOM_COLOR_ENABLED)
		->setAttribute('autofocus', 'autofocus')
	)
	->addRow((new CLabel(_('Unacknowledged PROBLEM events'), 'problem_unack_color'))->setAsteriskMark(), [
		(new CColor('problem_unack_color', $data['problem_unack_color']))
			->setEnabled($data['custom_color'] == EVENT_CUSTOM_COLOR_ENABLED)
			->addClass(($data['custom_color'] == EVENT_CUSTOM_COLOR_DISABLED) ? ZBX_STYLE_DISABLED : null)
			->addClass('js-event-color-picker')
			->setAriaRequired(),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CCheckBox('problem_unack_style'))
			->setLabel(_('blinking'))
			->setChecked($data['problem_unack_style'] == 1)
			->setUncheckedValue('0')
	])
	->addRow((new CLabel(_('Acknowledged PROBLEM events'), 'problem_ack_color'))->setAsteriskMark(), [
		(new CColor('problem_ack_color', $data['problem_ack_color']))
			->setEnabled($data['custom_color'] == EVENT_CUSTOM_COLOR_ENABLED)
			->addClass(($data['custom_color'] == EVENT_CUSTOM_COLOR_DISABLED) ? ZBX_STYLE_DISABLED : null)
			->addClass('js-event-color-picker')
			->setAriaRequired(),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CCheckBox('problem_ack_style'))
			->setLabel(_('blinking'))
			->setChecked($data['problem_ack_style'] == 1)
			->setUncheckedValue('0')
	])
	->addRow((new CLabel(_('Unacknowledged RESOLVED events'), 'ok_unack_color'))->setAsteriskMark(), [
		(new CColor('ok_unack_color', $data['ok_unack_color']))
			->setEnabled($data['custom_color'] == EVENT_CUSTOM_COLOR_ENABLED)
			->addClass(($data['custom_color'] == EVENT_CUSTOM_COLOR_DISABLED) ? ZBX_STYLE_DISABLED : null)
			->addClass('js-event-color-picker')
			->setAriaRequired(),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CCheckBox('ok_unack_style'))
			->setLabel(_('blinking'))
			->setChecked($data['ok_unack_style'] == 1)
			->setUncheckedValue('0')
	])
	->addRow((new CLabel(_('Acknowledged RESOLVED events'), 'ok_ack_color'))->setAsteriskMark(), [
		(new CColor('ok_ack_color', $data['ok_ack_color']))
			->setEnabled($data['custom_color'] == EVENT_CUSTOM_COLOR_ENABLED)
			->addClass(($data['custom_color'] == EVENT_CUSTOM_COLOR_DISABLED) ? ZBX_STYLE_DISABLED : null)
			->addClass('js-event-color-picker')
			->setAriaRequired(),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CCheckBox('ok_ack_style'))
			->setLabel(_('blinking'))
			->setChecked($data['ok_ack_style'] == 1)
			->setUncheckedValue('0')
	])
	->addRow(null)
	->addRow((new CLabel(_('Display OK triggers for'), 'ok_period'))->setAsteriskMark(), [
		(new CTextBox('ok_period', $data['ok_period'], false, DB::getFieldLength('config', 'ok_period')))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	])
	->addRow((new CLabel(_('On status change triggers blink for'), 'blink_period'))->setAsteriskMark(), [
		(new CTextBox('blink_period', $data['blink_period'], false, DB::getFieldLength('config', 'blink_period')))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	])
	->addRow(null)
	->addRow((new CLabel(_('Not classified'), 'severity_name_0'))->setAsteriskMark(), [
		(new CTextBox('severity_name_0', $data['severity_name_0'], false,
			DB::getFieldLength('config', 'severity_name_0')
		))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		new CColor('severity_color_0', $data['severity_color_0'])
	])
	->addRow((new CLabel(_('Information'), 'severity_name_1'))->setAsteriskMark(), [
		(new CTextBox('severity_name_1', $data['severity_name_1'], false,
			DB::getFieldLength('config', 'severity_name_1')
		))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		new CColor('severity_color_1', $data['severity_color_1'])
	])
	->addRow((new CLabel(_('Warning'), 'severity_name_2'))->setAsteriskMark(), [
		(new CTextBox('severity_name_2', $data['severity_name_2'], false,
			DB::getFieldLength('config', 'severity_name_2')
		))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		new CColor('severity_color_2', $data['severity_color_2'])
	])
	->addRow((new CLabel(_('Average'), 'severity_name_3'))->setAsteriskMark(), [
		(new CTextBox('severity_name_3', $data['severity_name_3'], false,
			DB::getFieldLength('config', 'severity_name_3')
		))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		new CColor('severity_color_3', $data['severity_color_3'])
	])
	->addRow((new CLabel(_('High'), 'severity_name_4'))->setAsteriskMark(), [
		(new CTextBox('severity_name_4', $data['severity_name_4'], false,
			DB::getFieldLength('config', 'severity_name_4')
		))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		new CColor('severity_color_4', $data['severity_color_4'])
	])
	->addRow((new CLabel(_('Disaster'), 'severity_name_5'))->setAsteriskMark(), [
		(new CTextBox('severity_name_5', $data['severity_name_5'], false,
			DB::getFieldLength('config', 'severity_name_5')
		))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired(),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		new CColor('severity_color_5', $data['severity_color_5'])
	])
	->addRow(null)
	->addInfo(_('Custom severity names affect all locales and require manual translation!'));

$form = (new CForm())
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', 'trigdisplay.update')
		->getUrl()
	)
	->addItem(
		(new CTabView())
			->addTab('triggerdo', _('Trigger displaying options'), $form_list)
			->setFooter(makeFormFooter(
				new CSubmit('update', _('Update')),
				[new CButton('resetDefaults', _('Reset defaults'))]
			))
	);

$widget->addItem($form)->show();

<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
	->setId('service-time-form')
	->setName('service-time-form')
	->addVar('edit', $data['is_edit'] ? '1' : null)
	->addVar('row_index', $data['row_index'])
	->addItem(getMessages());

// Enable form submitting on Enter.
$form->addItem((new CInput('submit'))->addStyle('display: none;'));

$form_grid = (new CFormGrid())
	->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_FIXED)
	->addItem([
		(new CLabel(_('Period type'), 'service_time_type_focusable')),
		(new CSelect('type'))
			->setId('service_time_type')
			->setFocusableElementId('service_time_type_focusable')
			->setValue($data['form']['type'])
			->addOptions(CSelect::createOptionsFromArray([
				SERVICE_TIME_TYPE_UPTIME => _('Uptime'),
				SERVICE_TIME_TYPE_DOWNTIME => _('Downtime'),
				SERVICE_TIME_TYPE_ONETIME_DOWNTIME => _('One-time downtime')
			]))
	]);

switch ($data['form']['type']) {
	case SERVICE_TIME_TYPE_UPTIME:
	case SERVICE_TIME_TYPE_DOWNTIME:
		$week_days = [];
		for ($i = 0; $i < 7; $i++) {
			$week_days[$i] = getDayOfWeekCaption($i);
		}

		$form_grid
			->addItem([
				(new CLabel(_('From'), 'service_time_from_week_focusable'))->setAsteriskMark(),
				(new CHorList([
					(new CSelect('from_week'))
						->setId('service_time_from_week')
						->setFocusableElementId('service_time_from_week_focusable')
						->setValue($data['form']['from_week'])
						->addOptions(CSelect::createOptionsFromArray($week_days)),
					_('Time'),
					(new CDiv([
						(new CTextBox('from_hour', $data['form']['from_hour']))
							->setId('service_time_from_hour')
							->setWidth(ZBX_TEXTAREA_2DIGITS_WIDTH)
							->setAttribute('placeholder', _('hh'))
							->setAriaRequired(),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						':',
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						(new CTextBox('from_minute', $data['form']['from_minute']))
							->setId('service_time_from_minute')
							->setWidth(ZBX_TEXTAREA_2DIGITS_WIDTH)
							->setAttribute('placeholder', _('mm'))
							->setAriaRequired()
					]))
				]))
			])
			->addItem([
				(new CLabel(_('Till'), 'service_time_till_week_focusable'))->setAsteriskMark(),
				(new CHorList([
					(new CSelect('till_week'))
						->setId('service_time_till_week')
						->setFocusableElementId('service_time_till_week_focusable')
						->setValue($data['form']['till_week'])
						->addOptions(CSelect::createOptionsFromArray($week_days)),
					_('Time'),
					(new CDiv([
						(new CTextBox('till_hour', $data['form']['till_hour']))
							->setId('service_time_till_hour')
							->setWidth(ZBX_TEXTAREA_2DIGITS_WIDTH)
							->setAttribute('placeholder', _('hh'))
							->setAriaRequired(),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						':',
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						(new CTextBox('till_minute', $data['form']['till_minute']))
							->setId('service_time_till_minute')
							->setWidth(ZBX_TEXTAREA_2DIGITS_WIDTH)
							->setAttribute('placeholder', _('mm'))
							->setAriaRequired()
					]))
				]))
			]);
		break;

	case SERVICE_TIME_TYPE_ONETIME_DOWNTIME:
		$form_grid
			->addItem([
				new CLabel(_('Note'), 'time_note'),
				(new CTextBox('note', $data['form']['note']))
					->setId('service_time_note')
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAttribute('placeholder', _('short description'))
			])
			->addItem([
				(new CLabel(_('From'), 'time_from'))->setAsteriskMark(),
				(new CDateSelector('from', $data['form']['from']))
					->setId('service_time_from')
					->setDateFormat(DATE_TIME_FORMAT)
					->setPlaceholder(_('YYYY-MM-DD hh:mm'))
					->setAriaRequired()
			])
			->addItem([
				(new CLabel(_('Till'), 'time_till'))->setAsteriskMark(),
				(new CDateSelector('till', $data['form']['till']))
					->setId('service_time_till')
					->setDateFormat(DATE_TIME_FORMAT)
					->setPlaceholder(_('YYYY-MM-DD hh:mm'))
					->setAriaRequired()
			]);
		break;
}

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag('
			service_time_edit_popup.init();
		'))->setOnDocumentReady()
	);

$output = [
	'header' => $data['title'],
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => $data['is_edit'] ? _('Update') : _('Add'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'service_time_edit_popup.submit();'
		]
	],
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.service.time.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

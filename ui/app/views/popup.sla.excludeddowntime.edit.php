<?php declare(strict_types = 1);
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
 * @var array $data
 */

$form = (new CForm())
	->setId('sla-excluded-downtime-form')
	->setName('sla_excluded_downtime_form')
	->addVar('edit', $data['is_edit'] ? '1' : null)
	->addVar('row_index', $data['row_index'])
	->addItem(getMessages());

// Enable form submitting on Enter.
$form->addItem((new CInput('submit', null))->addStyle('display: none;'));

$form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('name', $data['form']['name'], false, DB::getFieldLength('sla_excluded_downtime', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('placeholder', _('short description'))
				->setAttribute('autofocus', 'autofocus')
		)
	])
	->addItem([
		(new CLabel(_('Start time'), 'start_time'))->setAsteriskMark(),
		new CFormField(
			(new CDateSelector('start_time', $data['form']['start_time']))
				->setDateFormat(ZBX_DATE_TIME)
				->setPlaceholder(_('YYYY-MM-DD hh:mm'))
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('Duration'), 'duration_days'))->setAsteriskMark(),
		new CFormField(
			(new CDiv([
				(new CNumericBox('duration_days', $data['form']['duration_days'], 4))
					->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
				new CLabel(_('Days'), 'duration_days'),
				(new CSelect('duration_hours'))
					->setFocusableElementId('duration-hours-focusable')
					->setValue($data['form']['duration_hours'])
					->addOptions(CSelect::createOptionsFromArray(range(0, 23))),
				new CLabel(_('Hours'), 'duration-hours-focusable'),
				(new CSelect('duration_minutes'))
					->setFocusableElementId('duration-minutes-focusable')
					->setValue($data['form']['duration_minutes'])
					->addOptions(CSelect::createOptionsFromArray(range(0, 59))),
				new CLabel(_('Minutes'), 'duration-minutes-focusable')
			]))->addClass(ZBX_STYLE_FORM_FIELDS_INLINE)
		)
	]);

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag('
			sla_excluded_downtime_edit_popup.init();
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
			'action' => 'sla_excluded_downtime_edit_popup.submit();'
		]
	],
	'script_inline' => getPagePostJs().
		$this->readJsFile('popup.sla.excludeddowntime.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

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

$form = (new CForm('post'))
	->setId('sla-form')
	->setAction($data['form_action'])
	->setName('sla_form')
	->addItem(getMessages());

if ($data['form']['slaid'] !== null) {
	$form->addVar('id', $data['form']['slaid']);
}

// Enable form submitting on Enter.
$form->addItem((new CInput('submit'))->addStyle('display: none;'));

$sla_tab = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('name', $data['form']['name'], false, DB::getFieldLength('sla', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
		)
	])
	->addItem([
		(new CLabel(_('SLO'), 'slo'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('slo', $data['form']['slo'], false))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired()
		)
	]);

$period_switch = (new CRadioButtonList('period', (int) $data['form']['period']))->setModern(true);

foreach (CSlaHelper::periods() as $period => $label) {
	$period_switch->addValue($label, $period);
}

$timezone_select = (new CSelect('timezone'))
	->addOptions(CSelect::createOptionsFromArray($data['timezones']))
	->setValue($data['form']['timezone']);
$schedule_switch = (new CRadioButtonList('schedule_mode', (int) $data['schedule_mode']))->setModern(true);

$schedule_switch->addValue(
	CSlaHelper::scheduleModeToStr(CSlaHelper::SCHEDULE_MODE_NONSTOP),
	CSlaHelper::SCHEDULE_MODE_NONSTOP
);
$schedule_switch->addValue(
	CSlaHelper::scheduleModeToStr(CSlaHelper::SCHEDULE_MODE_CUSTOM),
	CSlaHelper::SCHEDULE_MODE_CUSTOM
);

$schedule_list = (new CTable())
	->setId('schedules')
	->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
	->setHeader(
		(new CRowHeader([
			(new CColHeader(''))
				->setWidth('1%'),
			_('Week day'),
			_('Schedule')
		]))
			->addClass(ZBX_STYLE_GREY)
	);

foreach ($data['form']['schedule'] as $weekday => $day) {
	$row = [
		(new CCheckBox('day[]', $weekday))
			->setChecked(!$day['disabled'])
			->addClass('js-toggle-schedule')
			->removeId(),
		getDayOfWeekCaption($weekday)
	];

	$period_input = (new CTextBox('schedule['.$weekday.']', $day['periods']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

	if ($day['disabled']) {
		$period_input->setAttribute('disabled', 'disabled');
	}

	$row[] = $period_input;

	$schedule_list->addRow($row);
}

$sla_tab
	->addItem([
		new CLabel(_('Reporting period'), 'period'),
		new CFormField($period_switch)
	])
	->addItem([
		new CLabel(_('Time zone'), 'timezone'),
		new CFormField($timezone_select)
	])
	->addItem([
		new CLabel(_('Schedule'), 'schedule_mode'),
		new CFormField($schedule_switch)
	])
	->addItem([
		(new CLabel(''))
			->addClass($data['schedule_mode'] != CSlaHelper::SCHEDULE_MODE_CUSTOM ? ZBX_STYLE_DISPLAY_NONE : '')
			->addClass('js-schedules'),
		(new CFormField($schedule_list))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addClass($data['schedule_mode'] != CSlaHelper::SCHEDULE_MODE_CUSTOM ? ZBX_STYLE_DISPLAY_NONE : '')
			->addClass('js-schedules')
	])
	->addItem([
		(new CLabel(_('Effective date'), 'effective_date'))->setAsteriskMark(),
		new CFormField(
			(new CDateSelector('effective_date', $data['form']['effective_date']))
				->setDateFormat(DATE_TIME_FORMAT_SECONDS)
				->setPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('Service tags')))->setAsteriskMark(),
		new CFormField(
			(new CDiv([
				(new CTable())
					->setId('service_tags')
					->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
					->setHeader(
						(new CRowHeader([_('Name'), _('Operation'), _('Value'), _('Action')]))
							->addClass(ZBX_STYLE_GREY)
					)
					->setFooter(
						(new CCol(
							(new CSimpleButton(_('Add')))
								->addClass(ZBX_STYLE_BTN_LINK)
								->addClass('element-table-add')
						))
					),
				(new CScriptTemplate('tag-row-tmpl'))->addItem(
					(new CRow([
						(new CTextBox(
							'service_tags[#{rowNum}][tag]', '#{tag}', false, DB::getFieldLength('sla_service_tag', 'tag')
						))
							->addClass('js-tag-input')
							->addClass('js-tag-tag')
							->setAttribute('placeholder', _('tag'))
							->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
						(new CSelect('service_tags[#{rowNum}][operator]'))
							->addClass('js-tag-input')
							->addOptions(CSelect::createOptionsFromArray([
								ZBX_SERVICE_PROBLEM_TAG_OPERATOR_EQUAL => _('Equals'),
								ZBX_SERVICE_PROBLEM_TAG_OPERATOR_LIKE => _('Contains')
							]))
							->setValue('#{operator}'),
						(new CTextBox(
							'service_tags[#{rowNum}][value]', '#{value}', false, DB::getFieldLength('sla_service_tag', 'value')
						))
							->addClass('js-tag-input')
							->setAttribute('placeholder', _('value'))
							->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
						(new CSimpleButton(_('Remove')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->addClass('element-table-remove')
					]))->addClass('form_row')
				)
			]))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		)
	])
	->addItem([
		new CLabel(_('Description'), 'description'),
		new CFormField(
			(new CTextArea('description',
				$data['form']['description'], false,  DB::getFieldLength('sla', 'description')
			))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setMaxlength(DB::getFieldLength('sla', 'description'))
		)
	])
	->addItem([
		new CLabel(_('Status'), 'status'),
		new CFormField(
			(new CCheckBox('status', $data['form']['status']))
				->setChecked($data['form']['status'] == CSlaHelper::SLA_STATUS_ENABLED)
				->setUncheckedValue(CSlaHelper::SLA_STATUS_DISABLED)
				->addClass(ZBX_STYLE_CURSOR_POINTER)
		)
	]);

$downtime_tab = (new CFormGrid())
	->addItem([
		(new CLabel(_('Excluded downtimes'))),
		new CFormField(
			(new CDiv([
				(new CTable())
					->setId('excluded_downtimes')
					->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
					->setHeader(
						(new CRowHeader([
							_('Start time'),
							_('Duration'),
							(new CCol(_('Name')))->addStyle('max-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;'),
							_('Action')
						]))
							->addClass(ZBX_STYLE_GREY)
					)
					->setFooter(
						(new CTag('tfoot', true))->addItem(
							(new CCol(
								(new CSimpleButton(_('Add')))
									->addClass(ZBX_STYLE_BTN_LINK)
									->addClass('js-add')
							))
							->setColSpan(4)
						)
					)
					->addClass(ZBX_STYLE_TABLE_FORMS),
				(new CScriptTemplate('downtimes-row-tmpl'))
					->addItem(
						(new CRow([
							'#{start_time}',
							'#{duration}',
							(new CCol('#{*name}'))->addClass(ZBX_STYLE_WORDBREAK),
							[
								new CVar('excluded_downtimes[#{row_index}][name]', '#{name}'),
								new CVar('excluded_downtimes[#{row_index}][period_from]', '#{period_from}'),
								new CVar('excluded_downtimes[#{row_index}][period_to]', '#{period_to}'),

								new CVar('js[#{row_index}][start_time]', '#{start_time}'),
								new CVar('js[#{row_index}][duration_days]', '#{duration_days}'),
								new CVar('js[#{row_index}][duration_hours]', '#{duration_hours}'),
								new CVar('js[#{row_index}][duration_minutes]', '#{duration_minutes}'),

								(new CList([
									(new CSimpleButton(_('Edit')))
										->addClass(ZBX_STYLE_BTN_LINK)
										->addClass('js-edit'),
									(new CSimpleButton(_('Remove')))
										->addClass(ZBX_STYLE_BTN_LINK)
										->addClass('js-remove')
								]))->addClass(ZBX_STYLE_HOR_LIST)
							],
						]))
						->addClass('form_row')
						->setAttribute('data-row_index', '#{row_index}')
					)
			]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addStyle('margin-top: -7px;')
		)
	]);

$form
	->addItem(
		(new CTabView())
			->setSelected(0)
			->addTab('sla-tab', _('SLA'), $sla_tab)
			->addTab('sla-downtimes-tab',
				_('Excluded downtimes'), $downtime_tab, CSlaHelper::TAB_INDICATOR_SLA_DOWNTIMES
			)
		)
	->show();

<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
	->addItem((new CVar(CCsrfTokenHelper::CSRF_TOKEN_NAME, CCsrfTokenHelper::get('maintenance')))->removeId())
	->setId('maintenance-period-form')
	->setName('maintenance_period_form')
	->addVar('edit', $data['is_edit'] ? '1' : null)
	->addVar('row_index', $data['row_index'])
	->addItem(getMessages())
	->addItem((new CInput('submit', null))->addStyle('display: none;'));

$days_weekly = [];
$days_monthly = [];

foreach (range(1, 7) as $day) {
	$value = 1 << ($day - 1);
	$days_weekly[] = [
		'label' => getDayOfWeekCaption($day),
		'value' => $value,
		'checked' => $data['form']['timeperiod_type'] == TIMEPERIOD_TYPE_WEEKLY && ($value & $data['form']['dayofweek'])
	];
	$days_monthly[] = [
		'label' => getDayOfWeekCaption($day),
		'value' => $value,
		'checked' => $data['form']['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY
			&& ($value & $data['form']['dayofweek'])
	];
}

$months = [];

foreach (range(1, 12) as $month) {
	$value = 1 << ($month - 1);
	$months[] = [
		'label' => getMonthCaption($month),
		'value' => $value,
		'checked' => $data['form']['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY && ($value & $data['form']['month'])
	];
}

$form_grid = (new CFormGrid())
	->addItem([
		new CLabel(_('Period type'), 'label-timeperiod-type'),
		(new CFormField(
			(new CSelect('timeperiod_type'))
				->setId('timeperiod_type')
				->setFocusableElementId('label-timeperiod-type')
				->setValue($data['form']['timeperiod_type'])
				->addOptions(CSelect::createOptionsFromArray([
					TIMEPERIOD_TYPE_ONETIME	=> _('One time only'),
					TIMEPERIOD_TYPE_DAILY	=> _('Daily'),
					TIMEPERIOD_TYPE_WEEKLY	=> _('Weekly'),
					TIMEPERIOD_TYPE_MONTHLY	=> _('Monthly')
				]))
				->setAttribute('autofocus', 'autofocus')
		))->setId('row_timeperiod_type')
	])
	->addItem([
		(new CLabel(_('Every day(s)'), 'every_day'))->setAsteriskMark(),
		(new CFormField(
			(new CNumericBox('every',
				$data['form']['timeperiod_type'] == TIMEPERIOD_TYPE_DAILY ? $data['form']['every'] : 1, 3
			))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
				->setId('every_day')
				->setAriaRequired()
		))->setId('row_timeperiod_every_day')
	])
	->addItem([
		(new CLabel(_('Every week(s)'), 'every_week'))->setAsteriskMark(),
		(new CFormField(
			(new CNumericBox('every',
				$data['form']['timeperiod_type'] == TIMEPERIOD_TYPE_WEEKLY ? $data['form']['every'] : 1, 2
			))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
				->setId('every_week')
				->setAriaRequired()
		))->setId('row_timeperiod_every_week')
	])
	->addItem([
		(new CLabel(_('Day of week'), 'days'))->setAsteriskMark(),
		(new CFormField(
			(new CCheckBoxList('days'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setOptions($days_weekly)
				->setVertical(true)
				->setColumns(3)
		))->setId('row_timeperiod_dayofweek')
	])
	->addItem([
		(new CLabel(_('Month'), 'months'))->setAsteriskMark(),
		(new CFormField(
			(new CCheckBoxList('months'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setOptions($months)
				->setVertical(true)
				->setColumns(3)
		))->setId('row_timeperiod_months')
	])
	->addItem([
		new CLabel(_('Date'), 'month_date_type'),
		(new CFormField(
			(new CRadioButtonList('month_date_type', (int) $data['form']['month_date_type']))
				->addValue(_('Day of month'), 0)
				->addValue(_('Day of week'), 1)
				->setModern(true)
		))->setId('row_timeperiod_date')
	])
	->addItem([
		(new CLabel(_('Day of week'), 'label-every-dow'))->setAsteriskMark(),
		(new CFormField(
			(new CSelect('every'))
				->setValue($data['form']['every'])
				->setFocusableElementId('label-every-dow')
				->addOptions(CSelect::createOptionsFromArray([
					1 => _('first'),
					2 => _x('second', 'adjective'),
					3 => _('third'),
					4 => _('fourth'),
					5 => _x('last', 'week of month')
				]))
				->setId('every_dow')
		))->setId('row_timeperiod_week')
	])
	->addItem(
		(new CFormField(
			(new CCheckBoxList('monthly_days'))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setOptions($days_monthly)
				->setVertical(true)
				->setColumns(3)
		))->setId('row_timeperiod_week_days')
	)
	->addItem([
		(new CLabel(_('Day of month'), 'day'))->setAsteriskMark(),
		(new CFormField(
			(new CNumericBox('day', ($data['form']['month_date_type'] == 0) ? $data['form']['day'] : 1, 2))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
				->setAriaRequired()
		))->setId('row_timeperiod_day')
	])
	->addItem([
		(new CLabel(_('Date'), 'start_date'))->setAsteriskMark(),
		(new CFormField(
			(new CDateSelector('start_date', $data['form']['start_date']))
				->setDateFormat(ZBX_DATE_TIME)
				->setPlaceholder(_('YYYY-MM-DD hh:mm'))
				->setAriaRequired()
		))->setId('row_timepreiod_start_date')
	])
	->addItem([
		new CLabel(_('At (hour:minute)'), 'hour'),
		(new CFormField(
			(new CDiv([
				(new CNumericBox('hour', $data['form']['hour'], 2))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
				' : ',
				(new CNumericBox('minute', $data['form']['minute'], 2))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			]))->addClass(ZBX_STYLE_FORM_FIELDS_INLINE)
		))->setId('row_timeperiod_period_at_hours_minutes')
	])
	->addItem([
		(new CLabel(_('Maintenance period length'), 'period_days'))->setAsteriskMark(),
		(new CFormField(
			(new CDiv([
				(new CNumericBox('period_days', $data['form']['period_days'], 3))
					->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
				new CLabel(_('Days'), 'period_days'),
				(new CSelect('period_hours'))
					->setFocusableElementId('label-period-hours')
					->setValue($data['form']['period_hours'])
					->addOptions(CSelect::createOptionsFromArray(range(0, 23))),
				new CLabel(_('Hours'), 'label-period-hours'),
				(new CSelect('period_minutes'))
					->setFocusableElementId('label-period-minutes')
					->setValue($data['form']['period_minutes'])
					->addOptions(CSelect::createOptionsFromArray(range(0, 59))),
				new CLabel(_('Minutes'), 'label-period-minutes')
			]))->addClass(ZBX_STYLE_FORM_FIELDS_INLINE)
		))->setId('row_timeperiod_period_length')
	]);

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag('
			maintenance_period_edit.init();
		'))->setOnDocumentReady()
	);

$output = [
	'header' => $data['title'],
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => $data['is_edit'] ? _('Apply') : _('Add'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'maintenance_period_edit.submit();'
		]
	],
	'script_inline' => getPagePostJs().
		$this->readJsFile('maintenance.period.edit.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
